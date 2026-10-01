/*
 * RockAdmin core behaviour.
 *
 * Milestone 4 built the delegated action dispatcher and the behaviour
 * registry. Milestone 6 adds what a fragment needs once the server starts
 * sending them: RockAdmin.load() to fetch and swap one region, a submit
 * listener for a region's own filter form, the sort and pager links a list
 * region already renders as data-ra-action="sort"/"paginate", and a
 * popstate listener so the back button walks through filter changes instead
 * of leaving the address bar and the grid disagreeing.
 *
 * Everything this file binds to already works with no JavaScript at all: a
 * sort header is a link, the toolbar is a GET form, a pager link is a link.
 * This only intercepts the same navigation and replaces a full page load
 * with an in-place one.
 */
(function () {
    'use strict';

    var MARKER = 'raAttached';

    /**
     * Action kinds, keyed by the value of data-ra-action. Starts empty: the
     * kinds themselves — open, post, and whatever a region adds — arrive
     * with the regions that use them, in milestone 6.
     *
     * @type {Object<string, function(Element, Event): void>}
     */
    var actions = {};

    /** @type {Object<string, {attach?: function(Element): void, detach?: function(Element): void}>} */
    var behaviors = {};

    /**
     * A confirmation in front of anything carrying data-ra-confirm — the
     * delete button on an edit form, and whatever else asks for one later.
     *
     * Bound in the capture phase, before the action dispatcher below and
     * before any submit handler, so declining actually stops the event rather
     * than stopping it after something already acted on it. Delegated for the
     * same reason everything else here is: a button that arrived with an AJAX
     * fragment must behave exactly like one that was in the first response.
     *
     * This is a courtesy, not a check. The server never looks for evidence
     * that it ran, because an attribute in a document the reader can edit is
     * not evidence of anything; ActionHandler verifies the CSRF token and the
     * method, which is all that can be verified.
     *
     * @param {Event} event
     */
    function confirmFirst(event) {
        var target = event.target;
        var trigger = target && target.closest ? target.closest('[data-ra-confirm]') : null;

        if (!trigger) {
            return;
        }

        if (window.confirm(trigger.getAttribute('data-ra-confirm') || 'Are you sure?')) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
    }

    document.addEventListener('click', confirmFirst, true);
    document.addEventListener('submit', confirmFirst, true);

    /**
     * Dismissing a thing that announces itself — a toast, an alert.
     *
     * Bootstrap did this through data-bs-dismiss, and it goes with Bootstrap.
     * Delegated rather than bound, because a toast arrives after a write and
     * an alert can arrive inside a region fetched over AJAX: anything bound
     * at load would miss both.
     *
     * The element removed is the closest ancestor matching what the attribute
     * names, so one handler serves every dismissible thing and the markup
     * says which. A button naming something that is not an ancestor removes
     * nothing rather than guessing.
     */
    document.addEventListener('click', function (event) {
        var target = event.target;
        var trigger = target && target.closest ? target.closest('[data-ra-dismiss]') : null;

        if (!trigger) {
            return;
        }

        var what = trigger.getAttribute('data-ra-dismiss');
        var doomed = trigger.closest('.ra-' + what);

        if (doomed && doomed.parentNode) {
            doomed.parentNode.removeChild(doomed);
        }
    });

    /**
     * The shared modal and offcanvas: opening and closing them.
     *
     * Bootstrap's bundle used to do this, and it went with Bootstrap. The
     * design system has no behaviour of its own -- it is CSS, and its React
     * decides when each one is in the document at all -- so the two
     * containers in the shell are shown and hidden here instead.
     *
     * Hidden, never removed. There is one of each for the whole page and a
     * later action has to be able to fill it again, which is why none of this
     * goes through `data-ra-dismiss`: that removes its target, correctly, for
     * a toast that will never be wanted again.
     *
     * The element that carries the id is the backdrop, and the dialog is
     * inside it. So a click lands on the backdrop only when it misses the
     * dialog, which is what makes "click outside to close" a test of where
     * the click landed rather than a measurement of coordinates.
     */
    function overlay(name) {
        return document.getElementById('ra-' + name);
    }

    /**
     * What to focus again when an overlay closes, per overlay.
     *
     * A dialog that leaves focus where it was is one a keyboard user arrives
     * at by tabbing through the page behind it; a dialog that drops focus on
     * close leaves them at the top of the document, having lost their place.
     * Both are the same bug from opposite ends, and this is the half of it
     * that has to be remembered across the open.
     */
    var opener = {};

    /** Every size modifier an overlay has, so one can be swapped for another. */
    var overlaySizes = {
        modal: ['sm', 'md', 'lg', 'xl'],
        offcanvas: ['sm', 'lg'],
    };

    function overlayDialog(el) {
        return el.querySelector('[role="dialog"]') || el;
    }

    /**
     * The focusable things inside an element, in document order.
     *
     * No library and no curated selector list beyond this: these are the
     * elements that take focus by default, and `:not([disabled])` plus a
     * negative-tabindex check is what separates "can be tabbed to" from
     * "exists".
     */
    function focusable(el) {
        var candidates = el.querySelectorAll(
            'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]),'
            + ' textarea:not([disabled]), [tabindex]'
        );

        return Array.prototype.filter.call(candidates, function (node) {
            return node.getAttribute('tabindex') !== '-1'
                && node.offsetWidth + node.offsetHeight > 0;
        });
    }

    function sizeOverlay(el, name, size) {
        var dialog = overlayDialog(el);
        var sizes = overlaySizes[name] || [];

        sizes.forEach(function (one) {
            dialog.classList.remove('ra-' + name + '--' + one);
        });

        if (size && sizes.indexOf(size) !== -1) {
            dialog.classList.add('ra-' + name + '--' + size);
        }
    }

    function openOverlay(name, title, options) {
        var el = overlay(name);

        if (!el) {
            return null;
        }

        options = options || {};

        var heading = el.querySelector('.ra-' + name + '-title');

        if (heading) {
            heading.textContent = title || '';
        }

        if (options.size !== undefined) {
            sizeOverlay(el, name, options.size);
        }

        var stale = el.getAttribute('data-ra-added-class');

        if (stale) {
            overlayDialog(el).classList.remove(stale);
            el.removeAttribute('data-ra-added-class');
        }

        // Remembered before the overlay is shown, because showing it can move
        // focus and then there is nothing left to remember.
        opener[name] = options.opener
            || (document.activeElement !== document.body ? document.activeElement : null);

        el.hidden = false;

        var dialog = overlayDialog(el);

        if (dialog && dialog.focus) {
            dialog.focus();
        }

        return el;
    }

    function closeOverlay(name) {
        var el = overlay(name);

        if (!el || el.hidden) {
            return;
        }

        // Released before anything is emptied: `detach` is what lets an
        // editor or a chart give up its own global listeners, and a tab left
        // open all day is the difference between a fresh page and a degraded
        // one (8.6.2).
        var body = el.querySelector('.ra-' + name + (name === 'modal' ? '-content' : '-body'));

        if (body) {
            detach(body);
            // Emptied rather than left holding the last fragment. A modal
            // that flashes the previous row's data for 200ms before the next
            // fetch lands is indistinguishable from a data leak in a
            // screenshot, and reads as one to whoever reports it.
            body.innerHTML = '';
        }

        el.hidden = true;

        var back = opener[name];

        opener[name] = null;

        if (back && back.focus && document.contains(back)) {
            back.focus();
        }
    }

    /**
     * Tab does not leave an open overlay.
     *
     * Without this the next Tab goes to the page behind the dialog, which is
     * inert to the eye and perfectly reachable to a keyboard — so somebody
     * tabs into content they cannot see, with no way back but Escape, which
     * they have no reason to think will help.
     */
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Tab') {
            return;
        }

        var open = ['modal', 'offcanvas'].map(overlay).filter(function (el) {
            return el && !el.hidden;
        })[0];

        if (!open) {
            return;
        }

        var stops = focusable(overlayDialog(open));

        if (stops.length === 0) {
            // Nothing to move to, so the dialog itself keeps focus rather
            // than the page behind it getting it by default.
            event.preventDefault();
            overlayDialog(open).focus();

            return;
        }

        var first = stops[0];
        var last = stops[stops.length - 1];
        var active = document.activeElement;

        if (event.shiftKey && (active === first || !open.contains(active))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (active === last || !open.contains(active))) {
            event.preventDefault();
            first.focus();
        }
    });

    document.addEventListener('click', function (event) {
        var target = event.target;
        var trigger = target && target.closest ? target.closest('[data-ra-close]') : null;

        if (!trigger) {
            return;
        }

        // The backdrop carries the attribute as well as the close button
        // does, so every click inside the dialog finds it by walking up and
        // would close the thing being used. A backdrop counts only when it
        // is what was clicked; a close button counts wherever in it the
        // click landed, which for a button wrapping an icon is usually the
        // icon.
        var isBackdrop = trigger.hasAttribute('data-ra-modal') || trigger.hasAttribute('data-ra-offcanvas');

        if (!isBackdrop || trigger === target) {
            closeOverlay(trigger.getAttribute('data-ra-close'));
        }
    });

    /**
     * Escape closes the topmost thing that is open. Only one of the two is
     * ever open at a time today; checking both means that stays true by
     * accident rather than by assumption.
     */
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }

        ['modal', 'offcanvas'].forEach(function (name) {
            var el = overlay(name);

            if (el && !el.hidden) {
                closeOverlay(name);
            }
        });
    });

    /**
     * The one delegated listener every action relies on. It never binds to
     * an individual element, so an element inserted after this runs behaves
     * exactly like one that was here from the start.
     */
    document.addEventListener('click', function (event) {
        var target = event.target;
        var trigger = target && target.closest ? target.closest('[data-ra-action]') : null;

        if (!trigger) {
            return;
        }

        var kind = trigger.getAttribute('data-ra-action');
        var handler = actions[kind];

        if (typeof handler !== 'function') {
            // eslint-disable-next-line no-console
            console.warn('RockAdmin: no action registered for "' + kind + '".');

            return;
        }

        handler(trigger, event);
    });

    /**
     * Registers a handler for one action kind — the value of a
     * data-ra-action attribute.
     *
     * @param {string} kind
     * @param {function(Element, Event): void} handler
     */
    function action(kind, handler) {
        actions[kind] = handler;
    }

    /**
     * Registers a behaviour by name. attach() runs once per matching element
     * per container; detach() releases whatever attach() set up, called
     * before an element carrying it is removed. Both are optional.
     *
     * @param {string} name
     * @param {{attach?: function(Element): void, detach?: function(Element): void}} definition
     */
    function behavior(name, definition) {
        behaviors[name] = definition;
    }

    /**
     * Runs attach() for every behaviour declared under the given container,
     * skipping any element already marked — the guard that stops an element
     * initialising twice when a container is attached more than once.
     *
     * @param {Element|Document} container
     */
    function attach(container) {
        Object.keys(behaviors).forEach(function (name) {
            var definition = behaviors[name];

            if (typeof definition.attach !== 'function') {
                return;
            }

            container.querySelectorAll('[data-ra-behavior="' + name + '"]').forEach(function (el) {
                if (el.dataset[MARKER] === name) {
                    return;
                }

                el.dataset[MARKER] = name;
                definition.attach(el);
            });
        });
    }

    /**
     * Runs detach() for every behaviour declared under the given container,
     * clearing the marker so the element could be attached again elsewhere.
     *
     * @param {Element|Document} container
     */
    function detach(container) {
        Object.keys(behaviors).forEach(function (name) {
            var definition = behaviors[name];

            if (typeof definition.detach !== 'function') {
                return;
            }

            container.querySelectorAll('[data-ra-behavior="' + name + '"]').forEach(function (el) {
                if (el.dataset[MARKER] !== name) {
                    return;
                }

                delete el.dataset[MARKER];
                definition.detach(el);
            });
        });
    }

    /**
     * Fetches `url`, detaches whatever behaviour was attached to `target`,
     * replaces `target` in the document with the fragment's own root
     * element, and attaches behaviour to that new element.
     *
     * The fragment itself carries its identity (data-ra-region and
     * data-ra-region-url) on its root, per the fragment contract — so the
     * element this resolves to is a complete, self-describing replacement
     * for `target`, not a chunk of markup poured into it.
     *
     * @param {string} url
     * @param {Element} target
     * @return {Promise<Element>}
     */
    function load(url, target) {
        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('RockAdmin: ' + url + ' answered ' + response.status);
                }

                return response.text();
            })
            .then(function (html) {
                var wrapper = document.createElement('div');
                wrapper.innerHTML = html;

                var fresh = wrapper.firstElementChild;

                if (!fresh) {
                    throw new Error('RockAdmin: ' + url + ' returned an empty fragment.');
                }

                detach(target);
                target.replaceWith(fresh);
                attach(fresh);

                return fresh;
            });
    }

    /**
     * Shows a message when a region could not be refreshed. The existing
     * region is left exactly as it was — a fetch failing is not a reason to
     * blank out a grid somebody was reading — so this is the only visible
     * sign anything went wrong.
     *
     * @param {string} message
     */
    function notifyFailure(message) {
        var container = document.querySelector('.ra-toast-container');

        if (!container) {
            // eslint-disable-next-line no-console
            console.error('RockAdmin: ' + message);

            return;
        }

        var toast = document.createElement('div');
        toast.className = 'ra-flash ra-flash-danger text-bg-danger toast show';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.setAttribute('aria-atomic', 'true');
        toast.setAttribute('data-ra-toast', '');

        var body = document.createElement('div');
        body.className = 'ra-toast-body toast-body';

        var text = document.createElement('span');
        text.className = 'ra-toast-message';
        text.textContent = message;
        body.appendChild(text);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'ra-toast-close btn-close';
        close.setAttribute('aria-label', 'Close');
        close.addEventListener('click', function () {
            toast.remove();
        });
        body.appendChild(close);

        toast.appendChild(body);
        container.appendChild(toast);

        setTimeout(function () {
            toast.remove();
        }, 8000);
    }

    /** The region element most recently loaded through navigate(), for popstate. */
    var currentRegion = null;

    /**
     * The query string a value carries, with any leading '?' and everything
     * before it stripped. Works equally on a full href ('/p/ads?a=b'), a
     * bare '?'-prefixed search string (location.search) and an already-bare
     * query string with no '?' at all (URLSearchParams#toString()), so a
     * caller never has to know which shape it is holding.
     *
     * @param {string} value
     * @return {string}
     */
    function queryOf(value) {
        var index = value.indexOf('?');

        return index === -1 ? value : value.slice(index + 1);
    }

    /**
     * The region's own fragment address (data-ra-region-url) with the given
     * URL's or bare string's query string appended.
     *
     * Every sort link, pager link and toolbar action addresses the *page*
     * route — spec 8.11: grid state lives in the URL so a copied link is
     * shareable, and a link that lands on a shell-less fragment when pasted
     * into a fresh tab is not shareable. So the href (or the form's action)
     * is always what a no-JavaScript browser should navigate to; this is
     * the translation that turns that same address into the one JavaScript
     * actually fetches, by combining its query with the region's own
     * fragment URL, already sitting on the fragment's root element.
     *
     * In path mode the fragment address carries no query string of its own
     * ('/admin/r/ads/grid'), so appending '?' + query is correct. In query
     * mode it already carries one ('/admin/index.php?ra=r%2Fads%2Fgrid'),
     * naming the fragment route itself — appending a second '?' produced
     * '...?ra=r%2Fads%2Fgrid?ra=p%2Fads&sort=...', which PHP parses as a
     * single 'ra' parameter with a literal '?' in it, matching no route at
     * all. Both query strings are parsed and merged instead: the page
     * route's own 'ra' entry is dropped from the carried state — it names
     * the *page* route, not the fragment, and the fragment address already
     * names its own route — and everything else layers on top of the
     * fragment address's own parameters.
     *
     * @param {Element} region
     * @param {string} hrefOrQuery
     * @return {string}
     */
    function regionRequestUrl(region, hrefOrQuery) {
        var base = region.getAttribute('data-ra-region-url') || '';
        var query = queryOf(hrefOrQuery);

        var splitAt = base.indexOf('?');
        var baseUrl = splitAt === -1 ? base : base.slice(0, splitAt);
        var baseParams = new URLSearchParams(splitAt === -1 ? '' : base.slice(splitAt + 1));

        var stateParams = new URLSearchParams(query);
        stateParams.delete('ra');

        stateParams.forEach(function (value, key) {
            baseParams.append(key, value);
        });

        var merged = baseParams.toString();

        return merged === '' ? baseUrl : baseUrl + '?' + merged;
    }

    /**
     * Fetches `fetchUrl` into `region` and, once it lands, records
     * `historyUrl` — the shareable page address — in browser history. A
     * failure leaves the region as it was and tells the person rather than
     * the grid silently going stale.
     *
     * @param {string} historyUrl
     * @param {string} fetchUrl
     * @param {Element} region
     */
    function navigate(historyUrl, fetchUrl, region) {
        load(fetchUrl, region).then(function (fresh) {
            currentRegion = fresh;
            window.history.pushState({ raRegion: true }, '', historyUrl);
        }).catch(function () {
            notifyFailure('Could not reach the server. The grid was not updated.');
        });
    }

    /**
     * A sort header or a pager link: both are ordinary
     * data-ra-action="sort"/"paginate" links inside a region, carrying the
     * page's own address in their href — the no-JavaScript destination and
     * the shareable link alike. JavaScript treats that href as a signal,
     * not a target: it takes the query string off it and fetches the
     * region's own fragment address with that query instead, so the page
     * itself never reloads.
     *
     * @param {Element} trigger
     * @param {Event} event
     */
    function followRegionLink(trigger, event) {
        var region = trigger.closest('[data-ra-region]');
        var href = trigger.getAttribute('href');

        if (!region || !href) {
            return;
        }

        event.preventDefault();
        currentRegion = region;
        navigate(href, regionRequestUrl(region, href), region);
    }

    /**
     * An `open` action: fetch the region and put it in an overlay.
     *
     * The control is an ordinary anchor pointing at the standalone page, so
     * everything here is an upgrade on something that already worked. The
     * click is only taken when it is a plain one — a middle click, a
     * ctrl-click or a shift-click is somebody asking for a new tab, and
     * stealing those would make a link that is not a link.
     *
     * The overlay opens before the fetch resolves, with the action's own
     * title, because an overlay that appears a beat after the click reads as
     * a click that missed.
     */
    action('open', function (trigger, event) {
        if (event.defaultPrevented || event.button > 0 || event.metaKey
            || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        var url = trigger.getAttribute('data-ra-open');
        var where = trigger.getAttribute('data-ra-in');

        if (!url || !where) {
            return;
        }

        var el = openOverlay(where, trigger.getAttribute('data-ra-title') || '', {
            size: trigger.getAttribute('data-ra-size') || '',
            opener: trigger,
        });

        if (!el) {
            // No overlay of that kind in this document. The anchor is left to
            // do what it would have done, which is navigate to the standalone
            // page -- a working control rather than a dead one.
            return;
        }

        event.preventDefault();

        var body = el.querySelector('.ra-' + where + (where === 'modal' ? '-content' : '-body'));

        if (!body) {
            return;
        }

        body.innerHTML = '';

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(url + ' answered ' + response.status);
                }

                return response.text();
            })
            .then(function (html) {
                // Still the same overlay, and still open. A second click
                // while the first fetch was in flight means this answer is
                // for a panel that has moved on, and writing it in would show
                // the wrong row.
                if (el.hidden || opener[where] !== trigger) {
                    return;
                }

                body.innerHTML = html;

                var fragment = body.firstElementChild;

                // 8.6.3: the fragment root carries its own title, size and
                // class.
                //
                // The fragment's title fills a gap rather than winning. The
                // plan for this milestone said the opposite -- the answer
                // knows which row it turned out to be about -- and driving it
                // showed why that is wrong: a preview's own title is the row
                // key, so a control the page author labelled 'Ad #{id}'
                // opened a panel headed '2'. One of the two is a generic name
                // a region gives itself and the other is what somebody wrote
                // for this control, and the author's choice is the specific
                // one.
                //
                // Size and class do win, because a fragment asking to be wide
                // is asking about its own content, which the action cannot
                // know.
                if (fragment) {
                    var title = fragment.getAttribute('data-ra-title');
                    var size = fragment.getAttribute('data-ra-size');
                    var extra = fragment.getAttribute('data-ra-class');
                    var heading = el.querySelector('.ra-' + where + '-title');

                    if (title && heading && !trigger.getAttribute('data-ra-title')) {
                        heading.textContent = title;
                    }

                    if (size) {
                        sizeOverlay(el, where, size);
                    }

                    if (extra) {
                        overlayDialog(el).classList.add(extra);
                        // Remembered so the next open can take it off again.
                        // Without this the overlay accumulates every class
                        // every fragment ever asked for, and the second row
                        // is drawn with the first one's styling on top.
                        el.setAttribute('data-ra-added-class', extra);
                    }
                }

                attach(body);

                // Focus moves in only now. Before the fragment arrived there
                // was nothing inside to move to, and a dialog focused on its
                // own empty body is one a screen reader announces as empty.
                var stops = focusable(overlayDialog(el));

                if (stops.length > 0) {
                    stops[0].focus();
                }
            })
            .catch(function (error) {
                if (el.hidden) {
                    return;
                }

                // Said inside the overlay rather than closing it. An overlay
                // that opens and closes again is indistinguishable from a
                // control that did nothing, and leaves nobody anything to
                // read or to retry.
                body.innerHTML = '';
                body.appendChild(overlayFailure(trigger.getAttribute('href') || url));

                // eslint-disable-next-line no-console
                console.warn('RockAdmin: ' + error.message);
            });
    });

    /**
     * What an overlay shows when its fragment could not be fetched: the
     * reason, and the link the control would have followed without any
     * script at all.
     *
     * @param {string} href
     */
    function overlayFailure(href) {
        var box = document.createElement('p');
        box.className = 'ra-overlay-error';
        box.textContent = 'Could not load this. ';

        var link = document.createElement('a');
        link.className = 'ra-overlay-error-link';
        link.href = href;
        link.textContent = 'Open it as a page instead.';

        box.appendChild(link);

        return box;
    }

    action('sort', followRegionLink);
    action('paginate', followRegionLink);

    // The toolbar's search box and filters: an ordinary GET form
    // (data-ra-behavior="grid-toolbar") whose action is the page's own
    // address, for the same reason a sort or pager link's href is: a
    // no-JavaScript submit, or a copied link built from it, must land on
    // the whole page. attach()/detach() rebind this on every fragment swap,
    // so a toolbar returned by a reload keeps working exactly like the one
    // that was there first.
    /**
     * The selection: a running count, buttons that are off while nothing is
     * ticked, and a select-all that ticks the page.
     *
     * Everything here is a convenience over markup that already works. The
     * checkboxes submit with the form by `form="..."`, the buttons choose
     * their action with `formaction`, and the server refuses an empty
     * selection by name. With no script at all the only thing lost is the
     * count and the select-all box, which is why the select-all carries no
     * `name` -- it is the one control here that does nothing without this,
     * so it must never be part of what is submitted.
     *
     * The boxes are found by the form attribute rather than by walking the
     * table, because that is the actual relationship: they are this form's
     * controls regardless of where they sit in the document, and a selector
     * over the table would quietly miss one the day a row is drawn elsewhere.
     */
    behavior('grid-bulk', {
        attach: function (form) {
            function boxes() {
                return Array.prototype.slice.call(
                    document.querySelectorAll('[form="' + form.id + '"][data-ra-select]')
                );
            }

            function selected() {
                return boxes().filter(function (box) {
                    return box.checked;
                });
            }

            function sync() {
                var all = boxes();
                var chosen = selected();
                var count = form.querySelector('.ra-grid-bulk-count-number');
                var toggle = document.querySelector('[form="' + form.id + '"][data-ra-select-all]')
                    || (form.closest('[data-ra-region]') || document).querySelector('[data-ra-select-all]');

                if (count) {
                    count.textContent = String(chosen.length);
                }

                form.classList.toggle('ra-grid-bulk-empty', chosen.length === 0);

                form.querySelectorAll('.ra-grid-bulk-action').forEach(function (button) {
                    button.disabled = chosen.length === 0;
                });

                if (toggle) {
                    toggle.checked = all.length > 0 && chosen.length === all.length;
                    // Some but not all: the box says so rather than reading
                    // as "none selected", which is what an unticked select-all
                    // over a part-selected page would say.
                    toggle.indeterminate = chosen.length > 0 && chosen.length < all.length;
                }
            }

            function onChange(event) {
                var target = event.target;

                if (!target || !target.matches) {
                    return;
                }

                if (target.matches('[data-ra-select-all]')) {
                    boxes().forEach(function (box) {
                        box.checked = target.checked;
                    });
                }

                if (target.matches('[data-ra-select-all]') || target.matches('[data-ra-select]')) {
                    sync();
                }
            }

            document.addEventListener('change', onChange);
            form._raBulkChange = onChange;
            sync();
        },
        detach: function (form) {
            if (form._raBulkChange) {
                document.removeEventListener('change', form._raBulkChange);
                delete form._raBulkChange;
            }
        },
    });

    behavior('grid-toolbar', {
        attach: function (form) {
            function onSubmit(event) {
                var region = form.closest('[data-ra-region]');

                if (!region) {
                    return;
                }

                event.preventDefault();

                var query = new URLSearchParams(new FormData(form)).toString();
                var pageAction = form.getAttribute('action') || window.location.pathname;
                var historyUrl = query === '' ? pageAction : pageAction + '?' + query;

                currentRegion = region;
                navigate(historyUrl, regionRequestUrl(region, query), region);
            }

            form.addEventListener('submit', onSubmit);
            form._raSubmit = onSubmit;
        },
        detach: function (form) {
            if (form._raSubmit) {
                form.removeEventListener('submit', form._raSubmit);
                delete form._raSubmit;
            }
        },
    });

    // The back button walks through the filter, sort and page changes
    // navigate() pushed, by reloading the same region from the query string
    // history just restored — never a full page navigation. The address bar
    // now holds a page URL, not the region's own fragment address, so the
    // reload asks for the region's fragment URL with that page URL's query
    // rather than fetching the page URL itself.
    window.addEventListener('popstate', function () {
        if (!currentRegion || !currentRegion.isConnected) {
            return;
        }

        var fetchUrl = regionRequestUrl(currentRegion, window.location.search);

        load(fetchUrl, currentRegion).then(function (fresh) {
            currentRegion = fresh;
        }).catch(function () {
            notifyFailure('Could not reach the server. The grid was not updated.');
        });
    });

    window.RockAdmin = {
        action: action,
        behavior: behavior,
        attach: attach,
        detach: detach,
        load: load,
        openOverlay: openOverlay,
        closeOverlay: closeOverlay,
    };

    document.addEventListener('DOMContentLoaded', function () {
        attach(document);
    });
})();

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
     * @param {Element} region
     * @param {string} hrefOrQuery
     * @return {string}
     */
    function regionRequestUrl(region, hrefOrQuery) {
        var base = region.getAttribute('data-ra-region-url') || '';
        var query = queryOf(hrefOrQuery);

        return query === '' ? base : base + '?' + query;
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

    action('sort', followRegionLink);
    action('paginate', followRegionLink);

    // The toolbar's search box and filters: an ordinary GET form
    // (data-ra-behavior="grid-toolbar") whose action is the page's own
    // address, for the same reason a sort or pager link's href is: a
    // no-JavaScript submit, or a copied link built from it, must land on
    // the whole page. attach()/detach() rebind this on every fragment swap,
    // so a toolbar returned by a reload keeps working exactly like the one
    // that was there first.
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
    };

    document.addEventListener('DOMContentLoaded', function () {
        attach(document);
    });
})();

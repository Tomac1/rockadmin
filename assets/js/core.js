/*
 * RockAdmin core behaviour.
 *
 * This is milestone 4's slice only: the delegated action dispatcher and the
 * behaviour registry, neither of which needs a fragment to exist. Fragment
 * loading, region refresh and filling the shared modal/offcanvas from a
 * fragment's root attributes belong to milestone 6, where fragments start
 * arriving from the server — nothing here inserts HTML yet.
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

    window.RockAdmin = {
        action: action,
        behavior: behavior,
        attach: attach,
        detach: detach,
    };

    document.addEventListener('DOMContentLoaded', function () {
        attach(document);
    });
})();

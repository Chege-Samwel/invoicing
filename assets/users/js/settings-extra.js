/*
 * Small behaviours for the settings pages.
 *
 * These used to be inline <script> blocks inside the views. Settings pages are
 * served under a CSP that forbids inline script, so those blocks were stripped
 * in production: the plugins they called loaded and then did nothing. Anything
 * that has to run on these pages belongs in an external file like this one.
 */

(function () {
    'use strict';

    function onReady(fn) {
        if (document.readyState !== 'loading') { fn(); }
        else { document.addEventListener('DOMContentLoaded', fn); }
    }

    onReady(function () {
        /*
         * Equal-height columns on the settings pages. jQuery is required by the
         * plugin, so this is skipped rather than throwing when it is absent.
         */
        if (typeof window.jQuery === 'undefined') { return; }

        var $ = window.jQuery;
        var pane = document.querySelector('.settings-inner-page');
        if (!pane || typeof $.fn.eqHeight !== 'function') { return; }
        if (!pane.querySelector('.col-settings')) { return; }

        $(pane).eqHeight('.col-settings');
    });
})();

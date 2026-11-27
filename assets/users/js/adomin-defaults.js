/*
 * The document defaults dialog.
 *
 * Opens from any element carrying data-adomin-defaults, asks the endpoint what
 * the current values are, and saves them back. Kept out of general.js because it
 * is self-contained and because general.js is loaded on every page while this
 * only does anything on the pages that have a trigger.
 *
 * The values are never printed into the page: the dialog is one file shared by
 * every screen, so a stale list would follow the user around until a reload.
 */
(function () {
    'use strict';

    var modal = document.getElementById('modalDefaults');
    if (!modal) { return; }

    var url = modal.getAttribute('data-url');
    var currency = document.getElementById('adomin-default-currency');
    var language = document.getElementById('adomin-default-language');
    var state = document.getElementById('adomin-defaults-state');
    var save = document.getElementById('adomin-defaults-save');

    function csrfBody() {
        var body = new URLSearchParams();
        var token = document.querySelector('meta[name="csrf-token"]');
        var name = document.querySelector('meta[name="csrf-name"]');
        if (token && name) { body.set(name.getAttribute('content'), token.getAttribute('content')); }
        return body;
    }

    function say(text, kind) {
        if (!state) { return; }
        state.textContent = text || '';
        state.className = 'adomin-defaults-state' + (kind ? ' is-' + kind : '');
    }

    function fill(select, items, current) {
        select.innerHTML = '';
        items.forEach(function (item) {
            var option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.label;
            if (item.value === current) { option.selected = true; }
            select.appendChild(option);
        });
    }

    function open() {
        say('Loading…');

        var request = new XMLHttpRequest();
        request.open('GET', url, true);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('Accept', 'application/json');
        request.onload = function () {
            var data = null;
            try { data = JSON.parse(request.responseText); } catch (err) {}

            if (!data || data.status !== 'success') {
                say((data && data.message) || 'Could not load the current defaults.', 'error');
                return;
            }

            var currencies = Object.keys(data.currencies || {}).map(function (code) {
                return { value: code, label: data.currencies[code] };
            });
            var languages = (data.languages || []).map(function (row) {
                return { value: row.name, label: row.name };
            });

            fill(currency, currencies, data.currency);
            fill(language, languages, data.language);

            /*
             * A host with one language has nothing to choose. The select is
             * hidden rather than shown with a single option, so the dialog stays
             * a two-field form where that is meaningful and a one-field form
             * where it is not.
             */
            var langGroup = language.closest('.form-group');
            if (langGroup) { langGroup.hidden = languages.length < 2; }

            say('');
            window.jQuery(modal).modal('show');
        };
        request.onerror = function () {
            say('Could not reach the server. Check your connection.', 'error');
        };
        request.send();
    }

    function submit() {
        if (save) { save.disabled = true; }
        say('Saving…');

        var body = csrfBody();
        if (currency && !currency.closest('.form-group').hidden) { body.set('currency', currency.value); }
        if (language && !language.closest('.form-group').hidden) { body.set('language', language.value); }

        var request = new XMLHttpRequest();
        request.open('POST', url, true);
        request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.onload = function () {
            if (save) { save.disabled = false; }
            var data = null;
            try { data = JSON.parse(request.responseText); } catch (err) {}

            if (data && data.status === 'success') {
                say(data.message || 'Saved.', 'ok');
                /*
                 * The page behind the dialog shows the currency in totals and the
                 * language in labels, so it will not match until it is redrawn.
                 * Reloading is honest here - the alternative is half a page
                 * showing the old unit.
                 */
                window.setTimeout(function () { window.location.reload(); }, 700);
            } else {
                say((data && data.message) || 'Could not save. Try again.', 'error');
            }
        };
        request.onerror = function () {
            if (save) { save.disabled = false; }
            say('Could not reach the server. Check your connection.', 'error');
        };
        request.send(body.toString());
    }

    if (save) { save.addEventListener('click', submit); }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-adomin-defaults]');
        if (!trigger) { return; }
        e.preventDefault();
        open();
    });

    // a page that has never had its defaults set asks once, quietly
    if (modal.getAttribute('data-prompt') === '1') {
        window.setTimeout(open, 1200);
    }
})();

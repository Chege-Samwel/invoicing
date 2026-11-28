/*
 * The document defaults dialog: currency and language, saved together.
 *
 * Three things this had to stop doing, all of them reported:
 *
 *  - It opened itself. A dialog nobody asked for, arriving 1.2 seconds into a
 *    page they were reading, is the definition of annoying; the prompt is gone
 *    and the dialog opens only when its button is pressed.
 *  - It reloaded the page after saving. The reload was there because the page
 *    shows the currency in totals - but it also threw away whatever the reader
 *    was doing and made a two-field change feel like a page crash. The page is
 *    not reloaded: the dialog confirms, and the values it just saved are what
 *    the next document reads.
 *  - It could leave the screen dimmed. If the browser could not open the dialog
 *    the request had already put the page behind a backdrop. Nothing here shows
 *    a backdrop until there is content to show, and every exit path - save,
 *    close button, Escape, clicking the page - clears it.
 *
 * The save is one request for both fields. Two requests would need two
 * failures handled and would leave the pair inconsistent in between.
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
    var loaded = false;
    var busy = false;

    function csrfBody() {
        var body = new URLSearchParams();
        var token = document.querySelector('meta[name="csrf-token"]');
        var name = document.querySelector('meta[name="csrf-name"]');
        if (token && name) {
            body.set(name.getAttribute('content'), token.getAttribute('content'));
        }
        return body;
    }

    function say(text, kind) {
        if (!state) { return; }
        state.textContent = text || '';
        state.className = 'adomin-defaults-state' + (kind ? ' is-' + kind : '');
    }

    /*
     * A UI blocker is left over from a dialog that failed to open or failed to
     * close. Nothing here creates one on purpose - the dialog is small and the
     * page behind it stays usable - so clearing it is only ever fixing damage
     * another script did.
     */
    function clearBackdrop() {
        var open = document.querySelector('.modal.in');
        if (open) { return; }
        document.querySelectorAll('.modal-backdrop').forEach(function (el) { el.remove(); });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }

    function close() {
        if (window.jQuery) {
            window.jQuery(modal).modal('hide');
        }
        clearBackdrop();
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

    function apply(data) {
        var currencies = Object.keys(data.currencies || {}).map(function (code) {
            return { value: code, label: data.currencies[code] };
        });
        var languages = (data.languages || []).map(function (row) {
            return { value: row.name, label: row.label || row.name };
        });

        fill(currency, currencies, data.currency);

        /*
         * If the current language is not in the installed list - it was removed
         * after being chosen - keep it as an option so the dialog shows the
         * truth rather than silently reporting the first entry.
         */
        if (data.language && !languages.some(function (l) { return l.value === data.language; })) {
            languages.unshift({ value: data.language, label: data.language });
        }
        fill(language, languages, data.language);

        var langGroup = language.closest('.form-group');
        if (langGroup) { langGroup.hidden = languages.length < 2; }

        var curGroup = currency.closest('.form-group');
        if (curGroup) { curGroup.hidden = currencies.length === 0; }

        loaded = true;
    }

    function open() {
        /*
         * The dialog is one file used by every page, so its values are never
         * printed into the markup - they are fetched. A failed fetch leaves the
         * page exactly as it was: no backdrop, no dialog, and a line of text
         * saying so on the button that was pressed.
         */
        say('Loading…');

        var request = new XMLHttpRequest();
        request.open('GET', url, true);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('Accept', 'application/json');
        request.onload = function () {
            var data = null;
            try { data = JSON.parse(request.responseText); } catch (err) {}

            if (!data || data.status !== 'success') {
                say((data && data.message) || 'Could not load the defaults.', 'error');
                return;
            }

            apply(data);
            say('');

            if (window.jQuery) {
                window.jQuery(modal).modal('show');
            } else {
                modal.classList.add('in');
                modal.style.display = 'block';
            }
        };
        request.onerror = function () {
            say('Could not reach the server.', 'error');
        };
        request.send();
    }

    function submit() {
        if (busy) { return; }
        busy = true;
        if (save) { save.disabled = true; }
        say('Saving…');

        var body = csrfBody();
        var curGroup = currency.closest('.form-group');
        var langGroup = language.closest('.form-group');
        if (curGroup && !curGroup.hidden) { body.set('currency', currency.value); }
        if (langGroup && !langGroup.hidden) { body.set('language', language.value); }

        var request = new XMLHttpRequest();
        request.open('POST', url, true);
        request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('Accept', 'application/json');
        request.onload = function () {
            busy = false;
            if (save) { save.disabled = false; }

            var data = null;
            try { data = JSON.parse(request.responseText); } catch (err) {}

            /*
             * A reply that is not JSON is a redirect - the framework does that
             * when it rejects a POST for CSRF or because the session expired.
             * Saying "could not save" is the honest report; retrying silently
             * would just do the same thing.
             */
            if (data && data.status === 'success') {
                say(data.message || 'Saved.', 'ok');
                // the two selects now hold what the server stored
                if (data.currency) { fill(currency, currencyOptions(), data.currency); }
                if (data.language) { fill(language, languageOptions(), data.language); }
                window.setTimeout(close, 900);
            } else {
                say((data && data.message) || 'Could not save. Try again.', 'error');
            }
        };
        request.onerror = function () {
            busy = false;
            if (save) { save.disabled = false; }
            say('Could not reach the server. Try again.', 'error');
        };
        request.send(body.toString());
    }

    // keep the current option lists so the selects can be re-filled after a save
    function currencyOptions() {
        return Array.prototype.map.call(currency.options, function (o) {
            return { value: o.value, label: o.textContent };
        });
    }
    function languageOptions() {
        return Array.prototype.map.call(language.options, function (o) {
            return { value: o.value, label: o.textContent };
        });
    }

    if (save) { save.addEventListener('click', submit); }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-adomin-defaults]');
        if (!trigger) { return; }
        e.preventDefault();
        if (loaded) {
            if (window.jQuery) { window.jQuery(modal).modal('show'); }
            return;
        }
        open();
    });

    /*
     * Every way out of the dialog clears the backdrop, including the ones
     * Bootstrap does not tell us about. This is the belt to clearBackdrop()'s
     * braces: the report was a dimmed page, and the fix is to be sure rather
     * than to be right.
     */
    modal.addEventListener('hidden.bs.modal', clearBackdrop);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { window.setTimeout(clearBackdrop, 60); }
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) { close(); }
    });
})();

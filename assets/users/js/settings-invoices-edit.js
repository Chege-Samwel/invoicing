/*
 * Edit in place for the four settings lists.
 *
 * Add and delete were there; edit was not, so correcting a mistyped currency
 * name meant deleting the row and adding it back - and the row is what keeps
 * every existing document pointing at that currency. The row becomes editable
 * where it sits instead, which is the smallest change that fixes it and does
 * not add another page to a settings screen that already has plenty.
 *
 * Everything is delegated from the document. The lists are rendered by the
 * server and this file is loaded once, so per-row listeners would also have to
 * be re-attached after any refresh; delegation cannot go stale.
 *
 * No inline handlers, no inline styles: the page may not have a <script> block
 * in it (see views/settings/*), so all behaviour lives here.
 */
(function () {
    'use strict';

    var root = document.querySelector('.settings-inner-page');
    if (!root) { return; }

    var URL_ATTR = 'data-adomin-rename-url';
    var url = root.getAttribute(URL_ATTR) || document.body.getAttribute(URL_ATTR);
    if (!url) { return; }

    var strings = {
        save: root.getAttribute('data-msg-save') || 'Save',
        cancel: root.getAttribute('data-msg-cancel') || 'Cancel',
        failed: root.getAttribute('data-msg-failed') || 'Could not save that.',
        saving: root.getAttribute('data-msg-saving') || 'Saving...'
    };

    function status(text, kind) {
        var box = root.querySelector('.adomin-settings-status');
        if (!box) { return; }
        box.textContent = text || '';
        box.className = 'adomin-settings-status' + (kind ? ' is-' + kind : '');
        if (text) {
            box.hidden = false;
            window.clearTimeout(status.timer);
            status.timer = window.setTimeout(function () {
                box.hidden = true;
            }, 4000);
        }
    }

    function post(data, done) {
        var body = new URLSearchParams();
        Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
        /*
         * The CSRF token. footer.php already teaches every form and every
         * $.ajax call about it; this is a plain fetch, so it has to add the
         * field itself - without it the framework answers with a redirect to
         * the login page and the row would appear to do nothing.
         */
        var meta = document.querySelector('meta[name="csrf-token"]');
        var nameMeta = document.querySelector('meta[name="csrf-name"]');
        if (meta) {
            body.append(nameMeta && nameMeta.getAttribute('content') || 'csrf_test_name',
                meta.getAttribute('content'));
        }
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(function (r) {
            return r.json().catch(function () {
                return { status: 'error', message: strings.failed };
            });
        }).then(done).catch(function () {
            done({ status: 'error', message: strings.failed });
        });
    }

    function cellText(row, field) {
        var cell = row.querySelector('[data-field="' + field + '"]');
        return cell ? cell.textContent.trim() : '';
    }

    function cancel(row, restore) {
        row.querySelectorAll('[data-adomin-editor]').forEach(function (el) { el.remove(); });
        /*
         * The buttons cell this created has to go as well. Leaving it behind
         * stacked a second set of Save/Cancel under the row on every edit, and
         * because the old Save was disabled while its request was in flight it
         * stayed disabled - so the next edit's Save looked dead.
         */
        row.querySelectorAll('[data-adomin-buttons]').forEach(function (el) { el.remove(); });
        Object.keys(restore).forEach(function (field) {
            var cell = row.querySelector('[data-field="' + field + '"]');
            if (cell) { cell.textContent = restore[field]; }
        });
        var actions = row.querySelector('.adomin-row-actions');
        if (actions) { actions.hidden = false; }
    }

    function begin(button) {
        var row = button.closest('[data-adomin-row]');
        if (!row || row.getAttribute('data-editing') === '1') { return; }
        row.setAttribute('data-editing', '1');

        var kind = button.getAttribute('data-kind');
        var id = button.getAttribute('data-id');
        var restore = { name: cellText(row, 'name') };
        var fields = { name: restore.name };
        if (kind === 'currency') {
            restore.value = cellText(row, 'value');
            fields.value = restore.value;
        }

        // replace each cell with an input holding its current text
        Object.keys(fields).forEach(function (field) {
            var cell = row.querySelector('[data-field="' + field + '"]');
            if (!cell) { return; }
            cell.textContent = '';
            var input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control input-sm';
            input.value = fields[field];
            input.setAttribute('data-adomin-editor', field);
            input.setAttribute('aria-label', field);
            cell.appendChild(input);
        });

        var actions = row.querySelector('.adomin-row-actions');
        if (actions) { actions.hidden = true; }

        var buttons = document.createElement('td');
        buttons.className = 'text-right adomin-row-actions';
        buttons.setAttribute('data-adomin-buttons', '1');
        var save = document.createElement('button');
        save.type = 'button';
        save.className = 'btn btn-xs btn-primary';
        save.textContent = strings.save;
        save.setAttribute('data-adomin-save', '1');
        var no = document.createElement('button');
        no.type = 'button';
        no.className = 'btn btn-xs btn-default';
        no.textContent = strings.cancel;
        no.setAttribute('data-adomin-cancel', '1');
        buttons.appendChild(save);
        buttons.appendChild(no);
        row.appendChild(buttons);

        var first = row.querySelector('[data-adomin-editor]');
        if (first) { first.focus(); first.select(); }

        save.addEventListener('click', function () {
            var data = { kind: kind, id: id };
            var ok = true;
            Object.keys(fields).forEach(function (field) {
                var input = row.querySelector('[data-adomin-editor="' + field + '"]');
                var value = input ? input.value.trim() : '';
                if (value === '') { ok = false; }
                data[field] = value;
            });
            if (!ok) {
                status(strings.failed, 'error');
                return;
            }
            save.disabled = true;
            save.textContent = strings.saving;
            post(data, function (res) {
                if (res.status === 'success') {
                    cancel(row, {
                        name: res.name || data.name,
                        value: res.value || data.value
                    });
                    row.removeAttribute('data-editing');
                    status(res.message || 'Saved.', 'ok');
                } else {
                    save.disabled = false;
                    save.textContent = strings.save;
                    status(res.message || strings.failed, 'error');
                }
            });
        });

        no.addEventListener('click', function () {
            cancel(row, restore);
            row.removeAttribute('data-editing');
        });

        row.querySelectorAll('[data-adomin-editor]').forEach(function (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); save.click(); }
                if (e.key === 'Escape') { e.preventDefault(); no.click(); }
            });
        });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('[data-adomin-edit]');
        if (!btn) { return; }
        e.preventDefault();
        begin(btn);
    });
})();

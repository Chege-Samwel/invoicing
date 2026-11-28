/*
 * Invoice template canvas.
 *
 * The document is drawn by the server, from the same template the invoice and
 * the PDF use, and then adopted here: every element the template marked with
 * data-avc-component becomes a Fabric.js object on a page-sized canvas, so each
 * one can be dragged, resized and restyled on its own.
 *
 * Two things shaped the design:
 *
 *  - The document has to print what the canvas shows, so nothing is drawn twice.
 *    The canvas holds the server's own markup; Fabric only moves it about. A
 *    component's box, size and face are written back as one string the server
 *    reads (opt_invPlacement), and the printed document applies them.
 *  - Settings pages are served under a CSP that forbids inline script, which is
 *    why this file exists at all - an earlier inline version was stripped in
 *    production and the canvas rendered without responding to anything.
 *
 * Without JavaScript the form still submits every setting; this file only adds
 * the live behaviour on top.
 */

(function () {
    'use strict';

    var form = document.getElementById('avc-form');
    /*
     * Everything the preview draws now goes inside the page stage. The editor
     * that owns the geometry reads the same element (see invoice-page-editor.js),
     * so there is one document on screen and one place it lives.
     */
    var host = document.getElementById('avc-stage');
    if (!form || !host) { return; }

    var PREVIEW = form.getAttribute('data-preview-url');
    var UPLOAD = form.getAttribute('data-upload-url');

    /*
     * A4 in points - the same number adomin_invoice_page() uses. The stored
     * layout is a fraction of this box, so the screen, the preview and the PDF
     * all resolve it to the same place. Points rather than pixels because the
     * printed page is measured in points: 794px was only ever an approximation
     * of 595pt and the two drifted apart on a high-density display.
     */
    var PAGE_W = 595;
    var PAGE_H = 842;

    var state = document.getElementById('avc-canvas-state');
    var placementField = document.getElementById('avc-placement');
    var edgeField = document.getElementById('avc-edge');

    var activeKey = null;      // which component the style controls edit
    var dirty = false;

    /*
     * The draft.
     *
     * Every setting on this page is already held in a hidden input, so a draft is
     * just those values written to localStorage. Keeping the same shape the form
     * posts means restoring is a matter of putting the values back and asking the
     * server to draw the document - no second serialisation to keep in step.
     *
     * Keyed per user and per firm: two accounts, or one account with two firms,
     * must not see each other's unsaved changes.
     */
    var DRAFT_KEY = 'avc-draft:' + (form.getAttribute('data-user') || '0')
        + ':' + (form.getAttribute('data-firm') || '0');

    var DRAFT_FIELDS = ['template', 'sections', 'logoPos', 'addressPos', 'accent',
        'currencyPos', 'showLogo', 'showAddress', 'showContacts', 'showFirmVat', 'showFirmPin',
        'showRemarks', 'placement', 'edge', 'footerNote', 'grid'];

    function draftSnapshot() {
        var values = {};
        DRAFT_FIELDS.forEach(function (name) {
            var el = field(name);
            if (el) { values[name] = el.value; }
        });
        return values;
    }

    function draftRead() {
        try {
            var raw = window.localStorage.getItem(DRAFT_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (err) {
            return null;
        }
    }

    function draftWrite() {
        try {
            window.localStorage.setItem(DRAFT_KEY, JSON.stringify({
                saved: draftSnapshot(),
                at: Date.now()
            }));
            return true;
        } catch (err) {
            // storage full or blocked: the page still works, there is just no draft
            return false;
        }
    }

    function draftClear() {
        try { window.localStorage.removeItem(DRAFT_KEY); } catch (err) {}
        noteDraft('');
    }

    function noteDraft(text, kind) {
        var el = document.getElementById('avc-draft-note');
        if (!el) { return; }
        if (!text) { el.hidden = true; el.textContent = ''; return; }
        el.hidden = false;
        el.textContent = text;
        el.className = 'avc-draft-note' + (kind ? ' is-' + kind : '');
    }

    /*
     * Depth of the canvas history.
     *
     * 40 steps is about a screen's worth of fiddling: deep enough to undo a
     * sequence of drags, shallow enough that the whole stack is a few kilobytes
     * of strings and never a reason to slow the canvas down.
     */
    var HISTORY_LIMIT = 40;
    var history = [];
    var historyAt = -1;
    var restoring = false;

    /*
     * The baseline is taken here, from the form as the server rendered it, and
     * not from the first canvas draw.
     *
     * The first draw is asynchronous - the canvas adopts the document that is
     * already on the page, but any redraw replaces it - so a change made before
     * that completed would have become the baseline and then been impossible to
     * undo. Reading the form here is synchronous and is exactly what the account
     * has stored.
     */
    history.push(JSON.stringify(draftSnapshot()));
    historyAt = 0;

    function field(name) { return document.getElementById('avc-' + name); }
    /*
     * The page editor is a separate file because it owns geometry and this file
     * owns settings. If it did not load - a stale cache, a blocked script - the
     * document still renders and still saves; only dragging is unavailable, and
     * the page should say so rather than appear broken.
     */
    function hasEditor() { return typeof window.avcPageEditor === 'object'; }

    function say(text, busy) {
        if (!state) { return; }
        state.textContent = text;
        state.classList.toggle('is-busy', !!busy);
    }

    function csrf(data) {
        var token = document.querySelector('meta[name="csrf-token"]');
        var name = document.querySelector('meta[name="csrf-name"]');
        if (token && name) {
            data.set(name.getAttribute('content'), token.getAttribute('content'));
        }
        return data;
    }

    /* ---- the settings payload ------------------------------------------- */

    function payload() {
        var data = new URLSearchParams();
        arrayOf(['template', 'sections', 'logoPos', 'addressPos', 'accent',
            'currencyPos', 'showLogo', 'showAddress', 'showContacts',
            'showFirmVat', 'showFirmPin', 'showRemarks', 'placement', 'edge']).forEach(function (k) {
            var el = field(k);
            if (el) { data.set('opt_inv' + k.charAt(0).toUpperCase() + k.slice(1), el.value); }
        });
        var note = document.getElementById('avc-footerNote');
        if (note) { data.set('opt_invFooterNote', note.value); }
        return csrf(data).toString();
    }

    function arrayOf(list) { return list; }

    /*
     * "123.45pt" -> 123.45. The document states its geometry in points, which is
     * the only unit that means the same thing in the browser and in the PDF
     * engine; this is the one place that reads it back.
     */
    function pts(value) {
        var n = parseFloat(String(value || '').replace('pt', ''));
        return isNaN(n) ? 0 : n;
    }

    /* ---- preview -------------------------------------------------------- */

    var timer = null, inFlight = false, queued = false;

    function draw() {
        if (inFlight) { queued = true; return; }
        inFlight = true;
        say('Updating…', true);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', PREVIEW, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            inFlight = false;
            if (xhr.status === 200) {
                host.innerHTML = xhr.responseText;
                say('Up to date', false);
                adopt();
            } else {
                say('Preview unavailable', false);
            }
            if (queued) { queued = false; draw(); }
        };
        xhr.onerror = function () {
            inFlight = false;
            say('Preview unavailable', false);
        };
        xhr.send(payload());
    }

    /*
     * Every change on this page ends up here: a choice button, a toggle, a
     * reorder, a finished drag, a font change all call soon(). Putting the
     * history push at this one funnel means a new control cannot forget it.
     */
    function soon() {
        pushHistory();
        clearTimeout(timer);
        timer = setTimeout(draw, 180);
    }

    /* ---- hand the new document to the page editor ------------------------ */

    /*
     * The preview was a picture drawn by a canvas library; it is now the document
     * itself, on a real page, with the page editor handling the geometry. This
     * function's whole job is to tell the editor a new document has arrived.
     *
     * What used to happen here is worth recording, because it is why the preview
     * was useless: every component was measured and re-drawn as a canvas object,
     * so the QR code disappeared, the type was a stand-in font, and the picture
     * could disagree with the page that prints. All of that is now the document
     * the server rendered, dragged directly.
     */
    function adopt() {
        document.dispatchEvent(new CustomEvent('avc:drawn'));
        syncControlsToValues();
        updateUndoButton();
    }

    /*
     * The page editor decides what is selected and announces it; this page only
     * owns the style controls, so it listens rather than reaching into the page.
     */
    document.addEventListener('avc:select', function (e) {
        select(e.detail && e.detail.key);
    });

    /*
     * The geometry itself now lives in invoice-page-editor.js, which reads and
     * writes the same hidden inputs this page does (opt_invPlacement for the
     * letterhead, opt_invGrid for the five page regions). Keeping one owner for
     * it is the point: the previous version had the drag handled by a canvas
     * library and the values recounted here from canvas objects, so the two
     * could - and did - disagree about where a component was.
     */

    /* ---- style controls ------------------------------------------------- */

    /*
     * The left panel edits the selected block's type. It does not own geometry -
     * the page editor does - so it reads the element out of the page and writes
     * font size and family into the stored placement string, where the document
     * renderer already looks for them.
     */

    function activeElement() {
        if (!activeKey) { return null; }
        return host.querySelector('[data-avc-component="' + activeKey + '"], [data-avc-block="' + activeKey + '"]');
    }

    function select(key) {
        activeKey = key;
        var el = activeElement();
        var body = document.getElementById('avc-style-body');
        var none = document.getElementById('avc-style-none');
        if (body) { body.hidden = !el; }
        if (none) { none.hidden = !!el; }

        var labels = { logo: 'Logo', firm: 'Company name', address: 'Company details', docno: 'Number and dates',
            header: 'Company and client', items: 'Items', totals: 'Totals',
            signature: 'Signature and cipher', remarks: 'Remarks and message' };
        var title = document.getElementById('avc-active-name');
        if (title) { title.textContent = labels[key] || 'Nothing selected'; }

        var size = document.getElementById('avc-size');
        var sizeOut = document.getElementById('avc-size-out');
        var face = document.getElementById('avc-face');

        if (!el) {
            if (size) { size.disabled = true; }
            if (sizeOut) { sizeOut.textContent = ''; }
            if (face) { face.disabled = true; }
            return;
        }

        var placement = readPlacement(key);
        var isText = key !== 'logo';
        if (size) {
            size.disabled = !isText;
            if (isText && placement && placement[4]) { size.value = placement[4]; }
        }
        if (sizeOut) { sizeOut.textContent = isText ? ((placement && placement[4]) || 'default') + ' pt' : 'image'; }
        if (face) {
            face.disabled = !isText;
            face.value = (placement && placement[5]) || '';
        }

        var dims = document.getElementById('avc-dims');
        if (dims && el.style.width) {
            dims.textContent = Math.round(parseFloat(el.style.width)) + ' × '
                + Math.round(parseFloat(el.style.height) || 0) + ' pt';
        }
    }

    /* the placement tuple for a component, or null */
    function readPlacement(key) {
        var raw = placementField ? placementField.value : '';
        var found = null;
        raw.split(';').forEach(function (chunk) {
            var parts = chunk.split('=');
            if (parts.length === 2 && parts[0] === key) {
                found = parts[1].split(',');
            }
        });
        return found;
    }

    function writePlacement(key, tuple) {
        if (!placementField) { return; }
        var lines = placementField.value.split(';').filter(function (chunk) {
            return chunk.split('=')[0] !== key;
        });
        lines.unshift(key + '=' + tuple.join(','));
        placementField.value = lines.filter(Boolean).join(';');
    }

    function applySize(pt) {
        if (!activeKey || activeKey === 'logo') { return; }
        var tuple = readPlacement(activeKey) || ['0', '0', '0.5', '0.05', '0', ''];
        tuple[4] = String(pt);
        writePlacement(activeKey, tuple);

        var el = activeElement();
        if (el) { el.style.fontSize = pt + 'pt'; }

        var out = document.getElementById('avc-size-out');
        if (out) { out.textContent = pt + ' pt'; }
        soon();
    }

    function applyFace(name) {
        if (!activeKey || activeKey === 'logo') { return; }
        var tuple = readPlacement(activeKey) || ['0', '0', '0.5', '0.05', '0', ''];
        tuple[5] = name || '';
        writePlacement(activeKey, tuple);

        var el = activeElement();
        if (el) {
            el.style.fontFamily = name ? "'" + name + "',Helvetica,Arial,sans-serif" : '';
        }
        soon();
    }

    /*
     * A finished drag on the page is a change like any other: one history entry
     * per drag (not one per mouse move) and a redraw so the stored values and
     * the document agree.
     */
    document.addEventListener('avc:change', function () {
        soon();
    });

    var sizeInput = document.getElementById('avc-size');
    if (sizeInput) {
        /* one history entry when the slider is released, not one per pixel */
        sizeInput.addEventListener('input', function () {
            applySize(parseInt(sizeInput.value, 10));
        });
    }
    var faceInput = document.getElementById('avc-face');
    if (faceInput) {
        faceInput.addEventListener('change', function () { applyFace(faceInput.value); });
    }
    var resetOne = document.getElementById('avc-reset-one');
    if (resetOne) {
        resetOne.addEventListener('click', function () {
            if (!activeKey) { return; }
            /*
             * Reset removes this component's stored position so the server's
             * designed position comes back. For a page region the same idea in
             * the grid: drop the band and the design applies again.
             */
            if (placementField) {
                placementField.value = placementField.value
                    .split(';')
                    .filter(function (p) { return p && p.split('=')[0] !== activeKey; })
                    .join(';');
            }
            var gridField = document.getElementById('avc-grid');
            if (gridField) {
                gridField.value = gridField.value
                    .split(';')
                    .filter(function (p) { return p && p.split('=')[0] !== activeKey; })
                    .join(';');
            }
            soon();
        });
    }

    /* ---- choice buttons and toggles ------------------------------------- */

    /* ---- whole designs -------------------------------------------------- */

    /*
     * A starter is not just a template name: it is a complete design - the
     * letterhead arrangement, the accent, the edge, the block order and the
     * proportions of the page. Choosing "Classic" and then having to also pick
     * centring, serif accents and a rule is the work this is meant to save.
     *
     * These are deliberately few and complete. A gallery of forty near-identical
     * variations is a harder choice than six that look different from each
     * other.
     *
     * Keys match Invoices::canvasTemplates(); a key with no starter here simply
     * keeps whatever the current settings are.
     */
    var STARTERS = {
        banner: {
            logoPos: 'left', addressPos: 'below', accent: 'blue', edge: 'wave',
            sections: 'header,items,totals,signature,remarks', currencyPos: 'totals',
            grid: 'header=0.055,0.245,0.89,0.075;items=0.055,0.33,0.89,0.35;totals=0.52,0.695,0.425,0.125;signature=0.055,0.83,0.89,0.06;remarks=0.055,0.9,0.62,0.055'
        },
        creative: {
            logoPos: 'left', addressPos: 'side', accent: 'blue', edge: 'corner',
            sections: 'header,items,totals,signature,remarks', currencyPos: 'totals',
            grid: 'header=0.055,0.25,0.89,0.085;items=0.055,0.345,0.89,0.33;totals=0.5,0.69,0.445,0.13;signature=0.055,0.835,0.89,0.06;remarks=0.055,0.905,0.62,0.05'
        },
        'toner-save': {
            logoPos: 'left', addressPos: 'below', accent: 'mono', edge: 'none',
            sections: 'header,items,totals,signature,remarks', currencyPos: 'totals',
            grid: 'header=0.06,0.06,0.88,0.13;items=0.06,0.2,0.88,0.44;totals=0.5,0.655,0.44,0.14;signature=0.06,0.81,0.88,0.06;remarks=0.06,0.88,0.62,0.07'
        },
        minimal: {
            logoPos: 'left', addressPos: 'below', accent: 'slate', edge: 'none',
            sections: 'header,items,totals,signature,remarks', currencyPos: 'totals',
            grid: 'header=0.07,0.09,0.86,0.12;items=0.07,0.23,0.86,0.42;totals=0.5,0.67,0.43,0.13;signature=0.07,0.82,0.86,0.055;remarks=0.07,0.885,0.6,0.065'
        },
        classic: {
            logoPos: 'center', addressPos: 'below', accent: 'slate', edge: 'rule',
            sections: 'header,items,totals,signature,remarks', currencyPos: 'header',
            grid: 'header=0.055,0.05,0.89,0.15;items=0.055,0.215,0.89,0.42;totals=0.48,0.655,0.465,0.14;signature=0.055,0.81,0.89,0.06;remarks=0.055,0.88,0.62,0.07'
        },
        modern: {
            logoPos: 'right', addressPos: 'side', accent: 'green', edge: 'chevron',
            sections: 'header,items,totals,signature,remarks', currencyPos: 'header',
            grid: 'header=0.055,0.06,0.89,0.14;items=0.055,0.215,0.89,0.43;totals=0.49,0.665,0.455,0.135;signature=0.055,0.815,0.89,0.06;remarks=0.055,0.885,0.62,0.065'
        }
    };

    function applyStarter(key) {
        var design = STARTERS[key];
        var template = field('template');
        if (template) { template.value = key; }
        if (!design) { soon(); return; }

        Object.keys(design).forEach(function (name) {
            var target = field(name);
            if (target) { target.value = design[name]; }
        });

        /*
         * The buttons in the panels below have to show the new state, or the
         * person sees a design change and a controls panel that disagrees with
         * it. The selects and sliders are refreshed from the values in the same
         * pass that the undo stack uses.
         */
        syncControlsToValues();
        markStarters();
        soon();
    }

    function markStarters() {
        var current = field('template') ? field('template').value : '';
        var buttons = document.querySelectorAll('[data-avc-starter]');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].classList.toggle('is-on',
                buttons[i].getAttribute('data-avc-starter') === current);
        }
        var designs = document.querySelectorAll('.avc-design');
        for (var j = 0; j < designs.length; j++) {
            var radio = designs[j].querySelector('input');
            designs[j].classList.toggle('is-on', !!radio && radio.value === current);
        }
    }

    function bindStarters() {
        document.addEventListener('click', function (e) {
            var button = e.target.closest && e.target.closest('[data-avc-starter]');
            if (!button) { return; }
            e.preventDefault();
            applyStarter(button.getAttribute('data-avc-starter'));
        });
    }

    function bindChoices() {
        var choices = document.querySelectorAll('.avc-choice');
        for (var i = 0; i < choices.length; i++) {
            choices[i].addEventListener('click', function () {
                var group = this.parentNode;
                var siblings = group.querySelectorAll('.avc-choice');
                for (var j = 0; j < siblings.length; j++) { siblings[j].classList.remove('is-on'); }
                this.classList.add('is-on');

                var name = group.getAttribute('data-field');
                var target = field(name);
                if (target) { target.value = this.getAttribute('data-value'); }

                // "hidden" means the same thing as the logo toggle being off
                if (name === 'logoPos' && this.getAttribute('data-value') === 'hide' && field('showLogo')) {
                    field('showLogo').value = '0';
                }
                soon();
            });
        }

        var toggles = document.querySelectorAll('.avc-toggle input');
        for (var t = 0; t < toggles.length; t++) {
            toggles[t].addEventListener('change', function () {
                var target = field(this.getAttribute('data-field'));
                if (target) { target.value = this.checked ? '1' : '0'; }
                soon();
            });
        }

        var note = document.getElementById('avc-footerNote');
        if (note) { note.addEventListener('input', soon); }

        var designs = document.querySelectorAll('input[name="avc-design"]');
        for (var d = 0; d < designs.length; d++) {
            designs[d].addEventListener('change', function () {
                field('template').value = this.value;
                var labels = document.querySelectorAll('.avc-design');
                for (var k = 0; k < labels.length; k++) {
                    labels[k].classList.toggle('is-on', labels[k].contains(this));
                }
                soon();
            });
        }

        var edges = document.querySelectorAll('.avc-edge-choice');
        for (var e = 0; e < edges.length; e++) {
            edges[e].addEventListener('click', function () {
                var group = this.parentNode;
                var siblings = group.querySelectorAll('.avc-edge-choice');
                for (var s = 0; s < siblings.length; s++) { siblings[s].classList.remove('is-on'); }
                this.classList.add('is-on');
                if (edgeField) { edgeField.value = this.getAttribute('data-value'); }
                soon();
            });
        }
    }

    /* ---- block order ---------------------------------------------------- */

    function bindBlocks() {
        var list = document.getElementById('avc-blocks');
        if (!list) { return; }
        var dragged = null;

        function syncOrder() {
            var order = [];
            var items = list.querySelectorAll('.avc-block');
            for (var i = 0; i < items.length; i++) { order.push(items[i].getAttribute('data-block')); }
            field('sections').value = order.join(',');
        }

        list.addEventListener('dragstart', function (e) {
            var li = e.target.closest('.avc-block');
            if (!li) { return; }
            dragged = li;
            li.classList.add('is-drag');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', li.getAttribute('data-block')); } catch (err) {}
        });

        list.addEventListener('dragover', function (e) {
            e.preventDefault();
            var li = e.target.closest('.avc-block');
            if (!li || li === dragged) { return; }
            var items = Array.prototype.slice.call(list.querySelectorAll('.avc-block'));
            var from = items.indexOf(dragged), to = items.indexOf(li);
            if (from < 0 || to < 0) { return; }
            if (from < to) { list.insertBefore(dragged, li.nextSibling); }
            else { list.insertBefore(dragged, li); }
            syncOrder();
            soon();
        });

        list.addEventListener('dragend', function () {
            if (dragged) { dragged.classList.remove('is-drag'); }
            dragged = null;
            syncOrder();
        });

        /* keyboard move, because drag is not available to everyone */
        list.addEventListener('keydown', function (e) {
            var li = e.target.closest('.avc-block');
            if (!li) { return; }
            if (e.key === 'ArrowUp' && li.previousElementSibling) {
                list.insertBefore(li, li.previousElementSibling);
            } else if (e.key === 'ArrowDown' && li.nextElementSibling) {
                list.insertBefore(li.nextElementSibling, li);
            } else { return; }
            e.preventDefault();
            li.focus();
            syncOrder();
            soon();
        });

        var blocks = list.querySelectorAll('.avc-block');
        for (var b = 0; b < blocks.length; b++) { blocks[b].tabIndex = 0; }
    }

    /* ---- logo upload ---------------------------------------------------- */

    function bindUpload() {
        var file = document.getElementById('avc-logo-file');
        var logoState = document.getElementById('avc-logo-state');
        if (!file) { return; }

        file.addEventListener('change', function () {
            if (!file.files || !file.files.length) { return; }
            var body = new FormData();
            body.append('logo', file.files[0]);
            var token = document.querySelector('meta[name="csrf-token"]');
            var name = document.querySelector('meta[name="csrf-name"]');
            if (token && name) { body.append(name.getAttribute('content'), token.getAttribute('content')); }

            if (logoState) { logoState.textContent = 'Uploading…'; }

            var xhr = new XMLHttpRequest();
            xhr.open('POST', UPLOAD, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function () {
                var res = null;
                try { res = JSON.parse(xhr.responseText); } catch (err) {}
                if (res && res.status === 'success') {
                    if (logoState) { logoState.textContent = res.message; }
                    var logoImg = document.getElementById('avc-logo-img');
                    if (logoImg) { logoImg.src = res.url; logoImg.hidden = false; }
                    var empty = document.getElementById('avc-logo-empty');
                    if (empty) { empty.style.display = 'none'; }
                    if (field('showLogo')) { field('showLogo').value = '1'; }
                    // a new logo changes the logo component's image, so the
                    // document has to be redrawn before it can be adopted again
                    activeKey = 'logo';
                    draw();
                } else if (logoState) {
                    logoState.textContent = (res && res.message) ? res.message : 'Upload failed. Try again.';
                }
            };
            xhr.onerror = function () {
                if (logoState) { logoState.textContent = 'Upload failed. Check your connection.'; }
            };
            xhr.send(body);
        });
    }

    /* ---- undo ----------------------------------------------------------- */

    /*
     * Undo works on the form's own values, not on the canvas objects.
     *
     * There are two ways to change this page - drag a component, or press one of
     * the settings buttons - and only the first is a canvas operation. Recording
     * the form values means one stack covers both, and replaying a step is the
     * same code path as restoring a draft: put the values back and redraw.
     */
    function pushHistory() {
        if (restoring) { return; }

        var snapshot = JSON.stringify(draftSnapshot());
        if (historyAt >= 0 && history[historyAt] === snapshot) {
            return;
        }

        // a new change after undoing discards the steps that were undone
        history = history.slice(0, historyAt + 1);
        history.push(snapshot);
        if (history.length > HISTORY_LIMIT) {
            history.shift();
        }
        historyAt = history.length - 1;
        updateUndoButton();
    }

    function updateUndoButton() {
        var btn = document.getElementById('avc-undo');
        if (!btn) { return; }
        // index 0 is the document as loaded; there is nothing before it
        btn.disabled = historyAt <= 0;
    }

    function restoreSnapshot(snapshot) {
        var values;
        try { values = JSON.parse(snapshot); } catch (err) { return; }

        restoring = true;
        Object.keys(values).forEach(function (name) {
            var el = field(name);
            if (el) { el.value = values[name]; }
        });
        restoring = false;

        syncControlsToValues();
        draw();
    }

    function undo() {
        if (historyAt <= 0) { return; }
        historyAt--;
        restoreSnapshot(history[historyAt]);
        updateUndoButton();
        noteDraft('Undone. Save the template to keep this.');
    }

    /*
     * After an undo the buttons and selects on the right have to agree with the
     * values that were put back, or the pane describes a document it is no
     * longer showing.
     */
    function syncControlsToValues() {
        var choices = document.querySelectorAll('.avc-choice');
        for (var i = 0; i < choices.length; i++) {
            var group = choices[i].parentNode;
            var name = group.getAttribute('data-field');
            var target = field(name);
            if (target) {
                choices[i].classList.toggle('is-on', choices[i].getAttribute('data-value') === target.value);
            }
        }

        var edges = document.querySelectorAll('.avc-edge-choice');
        for (var e = 0; e < edges.length; e++) {
            edges[e].classList.toggle('is-on', edges[e].getAttribute('data-value') === edgeField.value);
        }

        var toggles = document.querySelectorAll('.avc-toggle input');
        for (var t = 0; t < toggles.length; t++) {
            var tf = field(toggles[t].getAttribute('data-field'));
            if (tf) { toggles[t].checked = tf.value === '1'; }
        }

        var designs = document.querySelectorAll('input[name="avc-design"]');
        for (var d = 0; d < designs.length; d++) {
            designs[d].checked = designs[d].value === field('template').value;
            var label = designs[d].closest('.avc-design');
            if (label) { label.classList.toggle('is-on', designs[d].checked); }
        }
    }

    var undoBtn = document.getElementById('avc-undo');
    if (undoBtn) { undoBtn.addEventListener('click', undo); }

    // Ctrl/Cmd+Z, because that is the key everyone will try
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z') && !e.shiftKey) {
            var tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea') { return; }   // let the field undo itself
            e.preventDefault();
            undo();
        }
    });

    /* ---- draft ---------------------------------------------------------- */

    var saveDraftBtn = document.getElementById('avc-draft');
    if (saveDraftBtn) {
        saveDraftBtn.addEventListener('click', function () {
            if (draftWrite()) {
                noteDraft('Draft saved in this browser at ' + new Date().toLocaleTimeString()
                    + '. It is not saved to your account until you press Save template.', 'ok');
            } else {
                noteDraft('This browser would not let the draft be saved. Saving the template still works.', 'error');
            }
        });
    }

    /*
     * Offer the draft back on load, but never apply it silently: the stored
     * template is what every real invoice uses, and replacing it on screen
     * without saying so would make the page a lie about what is saved.
     */
    (function offerDraft() {
        var draft = draftRead();
        if (!draft || !draft.saved) { return; }

        var changed = false;
        Object.keys(draft.saved).forEach(function (name) {
            var el = field(name);
            if (el && el.value !== draft.saved[name]) { changed = true; }
        });
        if (!changed) {
            return;   // the draft matches what is stored; nothing to offer
        }

        var when = draft.at ? new Date(draft.at).toLocaleString() : 'earlier';
        noteDraft('You have unsaved changes from ' + when + '.');

        var banner = document.getElementById('avc-draft-note');
        if (!banner) { return; }

        var restore = document.createElement('button');
        restore.type = 'button';
        restore.className = 'avc-draft-action';
        restore.textContent = 'Restore them';
        restore.addEventListener('click', function () {
            pushHistory();
            restoreSnapshot(JSON.stringify(draft.saved));
            pushHistory();
            noteDraft('Unsaved changes restored. Save the template to keep them.');
        });

        var discard = document.createElement('button');
        discard.type = 'button';
        discard.className = 'avc-draft-action avc-draft-discard';
        discard.textContent = 'Discard';
        discard.addEventListener('click', function () {
            draftClear();
            noteDraft('');
        });

        banner.appendChild(document.createTextNode(' '));
        banner.appendChild(restore);
        banner.appendChild(document.createTextNode(' '));
        banner.appendChild(discard);
    })();

    /* ---- save ----------------------------------------------------------- */

    form.addEventListener('submit', function () {
        var btn = document.getElementById('avc-save');
        if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
        // once it is saved to the account, the browser copy has no purpose
        draftClear();
    });

    window.addEventListener('beforeunload', function (e) {
        // a saved draft means the work survives, so no warning is needed
        if (!dirty || draftRead()) { return undefined; }
        e.preventDefault();
        e.returnValue = '';
        return '';
    });

    /* ---- start ---------------------------------------------------------- */

    bindChoices();
    bindBlocks();
    bindUpload();
    bindStarters();

    /*
     * The view renders the document once, on a real page, so there is something
     * to drag from the first frame. Only if that came back empty does the first
     * draw have to fill it.
     */
    if (host.querySelector('.adv-page')) { adopt(); }
    else { draw(); }

    if (!hasEditor()) {
        say('Preview only - the page editor did not load', false);
    }
})();

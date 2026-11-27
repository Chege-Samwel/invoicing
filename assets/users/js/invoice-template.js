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
    var host = document.getElementById('avc-canvas');
    if (!form || !host) { return; }

    var PREVIEW = form.getAttribute('data-preview-url');
    var UPLOAD = form.getAttribute('data-upload-url');

    // A4 at 96dpi, the unit Fabric works in. The stored values are fractions of
    // this box, so the same layout is independent of the screen size.
    var PAGE_W = 794;
    var PAGE_H = 1123;

    var state = document.getElementById('avc-canvas-state');
    var placementField = document.getElementById('avc-placement');
    var edgeField = document.getElementById('avc-edge');

    var canvas = null;         // fabric.Canvas
    var objects = {};          // component key -> fabric object
    var activeKey = null;      // which component the style controls edit
    var dirty = false;

    function field(name) { return document.getElementById('avc-' + name); }
    function hasFabric() { return typeof window.fabric !== 'undefined'; }

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
            'showFirmVat', 'showRemarks', 'placement', 'edge']).forEach(function (k) {
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

    function soon() { clearTimeout(timer); timer = setTimeout(draw, 180); }

    /* ---- adopt the server's document onto the canvas --------------------- */

    /*
     * Reading a component's geometry back from the rendered preview keeps one
     * source of truth: the server decides where things go, and the canvas shows
     * that, rather than starting from a second set of numbers that could drift.
     */
    function adopt() {
        if (!hasFabric()) { return; }

        var items = host.querySelectorAll('[data-avc-component]');
        if (!items.length) { return; }

        if (canvas) { canvas.dispose(); canvas = null; }
        objects = {};

        var wrap = document.createElement('div');
        wrap.className = 'avc-fabric-host';
        host.innerHTML = '';
        host.appendChild(wrap);

        canvas = new window.fabric.Canvas(wrap, {
            width: PAGE_W,
            height: PAGE_H,
            selection: false,
            preserveObjectStacking: true,
            backgroundColor: '#ffffff'
        });

        Array.prototype.forEach.call(items, function (el) {
            var key = el.getAttribute('data-avc-component');
            var rect = el.getBoundingClientRect();

            /*
             * The server states each component's box in points (fractions of the
             * 595pt page width). Reading those back rather than measuring the
             * element keeps the canvas and the printed page on the same numbers:
             * a measurement would fold in the browser's own line-height and
             * zoom, and the two would drift apart.
             */
            var left = pts(el.style.left) / 595 * PAGE_W;
            var top = pts(el.style.top) / 595 * PAGE_W;
            var width = pts(el.style.width) / 595 * PAGE_W || rect.width;
            if (!width) { width = rect.width || 200; }
            var height = pts(el.style.height) / 595 * PAGE_W || rect.height;
            var fontSize = parseFloat(el.style.fontSize) || 12;
            var face = (el.style.fontFamily || '').split(',')[0].replace(/['"]/g, '').trim();

            var text = el.textContent.replace(/\s+/g, ' ').trim();
            var obj;

            if (key === 'logo') {
                var img = el.querySelector('img');
                obj = new window.fabric.Rect({
                    width: width,
                    height: Math.max(height, 34),
                    fill: '#f4f6f8',
                    stroke: '#c8d0d8',
                    strokeDashArray: [4, 3],
                    rx: 3, ry: 3
                });
                // an image box has no type, so it carries no size or face
                obj.set({ left: left, top: top, avcKey: key, avcKind: 'logo',
                    avcSrc: img ? img.src : '', avcFontSizePt: 0, avcFace: '', avcHeightPt: height });
            } else {
                // the size the document actually uses, so the canvas is not a
                // different document from the one that prints
                var lines = el.querySelectorAll('tr').length || 1;
                obj = new window.fabric.Textbox(text || key, {
                    width: width,
                    fontSize: fontSize * 1.333,
                    fontFamily: face || 'Helvetica',
                    fill: key === 'firm' ? '#111418' : '#2a323c',
                    fontWeight: key === 'firm' ? 'bold' : 'normal',
                    textAlign: key === 'docno' ? 'right' : 'left',
                    splitByGrapheme: false,
                    lineHeight: 1.35
                });
                obj.set({ left: left, top: top, avcKey: key, avcKind: 'text', avcLines: lines });
            }

            // the image box set its own (empty) type above; leave it alone
            if (obj.avcKind === 'text') {
                obj.set({ avcFontSizePt: fontSize, avcFace: face });
            }
            objects[key] = obj;
            canvas.add(obj);
            canvas.setActiveObject(obj);
        });

        canvas.setActiveObject(null);
        if (!activeKey && objects.logo) { select('logo'); }
        else if (activeKey && objects[activeKey]) { select(activeKey); }

        canvas.on('object:moving', onMove);
        canvas.on('object:scaling', onScale);
        canvas.on('object:selected', onSelect);
        canvas.on('selection:created', onSelect);
        canvas.on('selection:updated', onSelect);
        canvas.on('mouse:down', function (e) {
            if (!e.target) { canvas.discardActiveObject(); canvas.requestRenderAll(); }
        });

        syncPlacement();
        say('Drag a component to move it', false);
    }

    function onSelect(e) {
        if (e.target && e.target.avcKey) { select(e.target.avcKey); }
    }

    function onMove() { syncPlacement(); }

    function onScale(e) {
        // resizing a text object should re-wrap, not stretch the letters
        if (e.target && e.target.avcKind === 'text') {
            e.target.set({
                width: Math.max(40, e.target.width * e.target.scaleX),
                scaleX: 1
            });
        }
        syncPlacement();
    }

    /* ---- writing geometry back ------------------------------------------ */

    function clamp01(v) { return v < 0 ? 0 : (v > 1 ? 1 : v); }
    function r3(v) { return Math.round(v * 1000) / 1000; }

    function syncPlacement() {
        if (!placementField || !canvas) { return; }

        Object.keys(objects).forEach(function (key) {
            var obj = objects[key];
            if (obj.avcKind === 'text') {
                obj.avcLines = Math.max(1, obj.getLines ? obj.getLines().length : 1);
            }
        });

        var parts = [];

        ['logo', 'firm', 'address', 'docno'].forEach(function (key) {
            var obj = objects[key];
            if (!obj) { return; }

            var w = obj.getScaledWidth() / PAGE_W;
            var h;

            if (obj.avcKind === 'text') {
                /*
                 * A text box is as tall as the words in it, at the size it is
                 * drawn. Writing the drawn height instead would record whatever
                 * the browser wrapped at, and a smaller page would then clip the
                 * address - so the height is derived from the type instead, and
                 * the document is free to grow past it if it needs to.
                 */
                var lineH = obj.getScaledHeight() / Math.max(1, obj.avcLines || 1);
                h = (lineH * Math.max(1, obj.avcLines || 1)) / PAGE_W;
            } else {
                h = obj.getScaledHeight() / PAGE_W;
            }

            parts.push(key
                + '=' + r3(clamp01(obj.left / PAGE_W))
                + ',' + r3(clamp01(obj.top / PAGE_W))
                + ',' + r3(clamp01(w))
                + ',' + r3(clamp01(h))
                + ',' + Math.round(obj.avcFontSizePt)
                + ',' + (obj.avcFace || ''));
        });

        placementField.value = parts.join(';');
        dirty = true;
    }

    /* ---- style controls ------------------------------------------------- */

    function current() { return activeKey ? objects[activeKey] : null; }

    function select(key) {
        activeKey = key;
        var body = document.getElementById('avc-style-body');
        var none = document.getElementById('avc-style-none');
        if (body) { body.hidden = !objects[key]; }
        if (none) { none.hidden = !!objects[key]; }

        var title = document.getElementById('avc-active-name');
        if (title) {
            var labels = { logo: 'Logo', firm: 'Company name', address: 'Company details', docno: 'Number and dates' };
            title.textContent = labels[key] || 'Component';
        }

        var obj = objects[key];
        if (!obj) { return; }

        var size = document.getElementById('avc-size');
        var sizeOut = document.getElementById('avc-size-out');
        var face = document.getElementById('avc-face');

        if (obj.avcKind === 'text') {
            if (size) { size.value = obj.avcFontSizePt; size.disabled = false; }
            if (sizeOut) { sizeOut.textContent = obj.avcFontSizePt + ' pt'; }
            if (face) { face.value = obj.avcFace || ''; face.disabled = false; }
        } else {
            if (size) { size.disabled = true; }
            if (sizeOut) { sizeOut.textContent = 'not text'; }
            if (face) { face.disabled = true; }
        }

        var dims = document.getElementById('avc-dims');
        if (dims && obj) {
            dims.textContent = Math.round(obj.getScaledWidth() / PAGE_W * 210)
                + ' × ' + Math.round(obj.getScaledHeight() / PAGE_W * 297) + ' mm';
        }

        if (canvas) { canvas.setActiveObject(obj); canvas.requestRenderAll(); }
    }

    function applySize(pt) {
        var obj = current();
        if (!obj || obj.avcKind !== 'text') { return; }
        obj.avcFontSizePt = pt;
        obj.set({ fontSize: pt * 1.333 });
        // width stays, so a bigger face re-wraps rather than overflowing
        obj.set({ height: obj.height });
        canvas.requestRenderAll();
        syncPlacement();
        var out = document.getElementById('avc-size-out');
        if (out) { out.textContent = pt + ' pt'; }
    }

    function applyFace(name) {
        var obj = current();
        if (!obj || obj.avcKind !== 'text') { return; }
        obj.avcFace = name;
        obj.set({ fontFamily: name || 'Helvetica' });
        canvas.requestRenderAll();
        syncPlacement();
    }

    var size = document.getElementById('avc-size');
    if (size) {
        size.addEventListener('input', function () { applySize(parseInt(size.value, 10)); });
    }
    var face = document.getElementById('avc-face');
    if (face) {
        face.addEventListener('change', function () { applyFace(face.value); });
    }
    var resetOne = document.getElementById('avc-reset-one');
    if (resetOne) {
        resetOne.addEventListener('click', function () {
            var key = activeKey;
            canvas.remove(current());
            delete objects[key];
            activeKey = null;
            // the server's default for this component comes back with the next draw
            if (placementField) {
                placementField.value = placementField.value
                    .split(';')
                    .filter(function (p) { return p.split('=')[0] !== key; })
                    .join(';');
            }
            soon();
        });
    }

    /* ---- choice buttons and toggles ------------------------------------- */

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

    /* ---- save ----------------------------------------------------------- */

    form.addEventListener('submit', function () {
        var btn = document.getElementById('avc-save');
        if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
    });

    window.addEventListener('beforeunload', function (e) {
        if (!dirty) { return undefined; }
        e.preventDefault();
        e.returnValue = '';
        return '';
    });

    /* ---- start ---------------------------------------------------------- */

    bindChoices();
    bindBlocks();
    bindUpload();

    if (hasFabric()) {
        // the view renders the document once so this has something to adopt; if
        // it is empty the first draw fills it
        if (host.querySelector('[data-avc-component]')) { adopt(); }
        else { draw(); }
    } else {
        say('Preview only - the editor did not load', false);
        draw();
    }
})();

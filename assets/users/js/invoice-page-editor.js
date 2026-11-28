/*
 * The page editor.
 *
 * A document is a sheet of paper with things placed on it. This edits those
 * things: every block of the invoice - the letterhead pieces and the five
 * regions that follow them - is a box on an A4 page, and the person moves and
 * resizes the box. Nothing here reflows, because reflow is exactly what made the
 * previous preview useless: blocks pushed each other around, so what was on
 * screen was never the page that would print.
 *
 * Why not the canvas library the previous version used: it drew a *picture* of
 * the page. Text became a Fabric textbox with a font that only approximated the
 * template's, the QR code was not there at all, and the picture was a second
 * representation of the document that could disagree with the real one. Editing
 * the real DOM means the thing on screen is the thing that prints.
 *
 * Coordinates are fractions of the page (0..1), which is what the server stores
 * and what the PDF reads, so a drag here lands in the same place on paper.
 */
(function () {
    'use strict';

    var PAGE_W = 595;                 // A4 in points, matching adomin_invoice_page()
    var PAGE_H = 842;
    var PAGE_ATTR = 'data-avc-component';   // letterhead pieces
    var BLOCK_ATTR = 'data-avc-block';      // page regions

    var form = document.getElementById('avc-form');
    var desk = document.getElementById('avc-desk');
    var stage = document.getElementById('avc-stage');
    var hiddenPlacement = document.getElementById('avc-placement');
    var hiddenGrid = document.getElementById('avc-grid');

    if (!form || !desk || !stage) { return; }

    var page = null;          // the .adv-page element
    var targets = {};         // key -> { el, kind }
    var activeKey = null;
    var scale = 1;

    /* ---- state ---------------------------------------------------------- */

    function placements() {
        var out = {};
        if (!hiddenPlacement) { return out; }
        hiddenPlacement.value.split(';').forEach(function (chunk) {
            var parts = chunk.split('=');
            if (parts.length !== 2) { return; }
            var nums = parts[1].split(',');
            if (nums.length < 6) { return; }
            out[parts[0]] = nums;
        });
        return out;
    }

    function savePlacements(map) {
        if (!hiddenPlacement) { return; }
        hiddenPlacement.value = Object.keys(map).map(function (key) {
            return key + '=' + map[key].join(',');
        }).join(';');
    }

    function grid() {
        var out = {};
        if (!hiddenGrid) { return out; }
        hiddenGrid.value.split(';').forEach(function (chunk) {
            var parts = chunk.split('=');
            if (parts.length !== 2) { return; }
            var nums = parts[1].split(',').map(parseFloat);
            if (nums.length < 4) { return; }
            out[parts[0]] = nums;
        });
        return out;
    }

    function saveGrid(map) {
        if (!hiddenGrid) { return; }
        hiddenGrid.value = Object.keys(map).map(function (key) {
            return key + '=' + map[key].map(function (n) {
                return Math.round(n * 1000) / 1000;
            }).join(',');
        }).join(';');
    }

    function clamp(v) { return v < 0 ? 0 : (v > 1 ? 1 : v); }

    /* ---- reading the page off the DOM ----------------------------------- */

    /*
     * The page arrives already rendered by the server, so the geometry is read
     * from what is there rather than recomputed. If the two ever disagreed, the
     * server would still be right - it is what prints.
     */
    function measure() {
        page = stage.querySelector('.adv-page');
        if (!page) {
            page = stage.querySelector('#av-canvas .adv-page');
        }
        targets = {};
        if (!page) { return false; }

        var gridMap = grid();

        Array.prototype.forEach.call(page.querySelectorAll('[' + BLOCK_ATTR + ']'), function (el) {
            var key = el.getAttribute(BLOCK_ATTR);
            targets[key] = { el: el, kind: 'block' };
            if (!gridMap[key]) {
                // a band with no stored value: adopt where it actually is
                gridMap[key] = rectOf(el);
            }
            el.setAttribute('data-avc-key', key);
        });
        saveGrid(gridMap);

        var placeMap = placements();

        Array.prototype.forEach.call(page.querySelectorAll('[' + PAGE_ATTR + ']'), function (el) {
            var key = el.getAttribute(PAGE_ATTR);
            targets[key] = { el: el, kind: 'component' };
            if (!placeMap[key]) {
                placeMap[key] = rectOf(el).concat([0, '']);
            }
            el.setAttribute('data-avc-key', key);
        });
        savePlacements(placeMap);

        return true;
    }

    function rectOf(el) {
        var style = el.style;
        var left = parseFloat(style.left) || 0;
        var top = parseFloat(style.top) || 0;
        var width = parseFloat(style.width) || el.offsetWidth || 0;
        var height = parseFloat(style.height) || el.offsetHeight || 0;
        /*
         * The same split the drawing side uses: x and width are fractions of
         * the page width, y and height of its height. Reading the vertical
         * pair against the width stored bands that were shorter and higher
         * than the ones on screen, so a drag looked right and the saved
         * document did not match it.
         */
        return [
            Math.round(left / PAGE_W * 1000) / 1000,
            Math.round(top / PAGE_H * 1000) / 1000,
            Math.round(width / PAGE_W * 1000) / 1000,
            Math.round(height / PAGE_H * 1000) / 1000
        ];
    }

    /* ---- writing geometry back ------------------------------------------ */

    function apply(key) {
        var t = targets[key];
        if (!t) { return; }

        var box;
        if (t.kind === 'block') {
            box = grid()[key];
        } else {
            box = placements()[key];
        }
        if (!box) { return; }

        /*
         * Width against the page width, height against the page height.
         *
         * The stored fractions are fractions of the sheet as a whole: the five
         * bands of the default design run 0.042 -> 0.965 down the page and sum
         * to one, which only makes sense against PAGE_H. Reading the vertical
         * ones against the width drew every block into the top 70% of the
         * sheet - the editor showed a page of content crammed into the upper
         * half with a band of blank paper under it.
         */
        t.el.style.left = (box[0] * PAGE_W).toFixed(2) + 'pt';
        t.el.style.top = (box[1] * PAGE_H).toFixed(2) + 'pt';
        t.el.style.width = (box[2] * PAGE_W).toFixed(2) + 'pt';
        t.el.style.height = (box[3] * PAGE_H).toFixed(2) + 'pt';
    }

    function commit(key, box) {
        var t = targets[key];
        if (!t) { return; }
        if (t.kind === 'block') {
            var g = grid();
            g[key] = box;
            saveGrid(g);
        } else {
            var p = placements();
            if (!p[key]) { p[key] = [0, 0, 0, 0, 0, '']; }
            p[key][0] = box[0];
            p[key][1] = box[1];
            p[key][2] = box[2];
            p[key][3] = box[3];
            savePlacements(p);
        }
        apply(key);
    }

    /* ---- scaling -------------------------------------------------------- */

    /*
     * The page is 595pt wide. On a laptop the canvas column is around 620px, so
     * it fits at roughly 1:1; on a 13" screen it does not, and on a tablet it
     * certainly does not. The scale is measured from the desk rather than
     * guessed from a media query, and applied as a transform, so the page keeps
     * its internal geometry whatever size it is drawn at.
     */
    function fit() {
        var available = desk.clientWidth - 36;
        var natural = PAGE_W * (96 / 72);       // 595pt in CSS pixels
        var wanted = available > 0 ? available / natural : 1;
        scale = Math.max(0.35, Math.min(1.6, wanted));

        /*
         * The scale lives on the stage, which exists whether or not a page has
         * been drawn into it. Setting it before the first document arrives means
         * the page appears already fitted instead of snapping to size a frame
         * later - and means the control reports a real number on a page whose
         * preview failed, rather than a meaningless 100%.
         */
        var frame = stage;
        if (frame) {
            frame.style.setProperty('--av-page-scale', scale.toFixed(3));
            // a transform does not reserve room, so the scaled height is set too
            frame.style.height = (PAGE_H * (96 / 72) * scale) + 'px';
        }
        setZoomLabel();
    }

    function setZoomLabel() {
        var out = document.getElementById('avc-zoom-out');
        if (out) { out.textContent = Math.round(scale * 100) + '%'; }
    }

    /* ---- selection ------------------------------------------------------ */

    function select(key) {
        if (activeKey && targets[activeKey]) {
            targets[activeKey].el.classList.remove('avc-band-selected');
            removeTag(targets[activeKey].el);
        }
        activeKey = key;
        if (!key || !targets[key]) {
            document.body.classList.add('avc-nothing-selected');
            return;
        }
        document.body.classList.remove('avc-nothing-selected');
        var el = targets[key].el;
        el.classList.add('avc-band-selected');
        addTag(el, labelOf(key));

        var name = document.getElementById('avc-active-name');
        if (name) { name.textContent = labelOf(key); }

        var dims = document.getElementById('avc-dims');
        if (dims) {
            var box = targets[key].kind === 'block' ? grid()[key] : placements()[key];
            if (box) {
                dims.textContent = Math.round(box[2] * PAGE_W) + ' × '
                    + Math.round(box[3] * PAGE_H) + ' pt';
            }
        }
        document.dispatchEvent(new CustomEvent('avc:select', { detail: { key: key } }));
    }

    function labelOf(key) {
        var t = targets[key];
        if (t && t.el.getAttribute('data-avc-label')) {
            return t.el.getAttribute('data-avc-label');
        }
        var labels = { logo: 'Logo', firm: 'Company name', address: 'Company details', docno: 'Number and dates',
            header: 'Company and client', items: 'Items', totals: 'Totals',
            signature: 'Signature', remarks: 'Remarks' };
        return labels[key] || key;
    }

    function addTag(el, text) {
        removeTag(el);
        var tag = document.createElement('span');
        tag.className = 'avc-band-tag';
        tag.textContent = text;
        el.appendChild(tag);
        var handle = document.createElement('span');
        handle.className = 'avc-band-handle';
        handle.setAttribute('data-avc-handle', '1');
        el.appendChild(handle);
    }

    function removeTag(el) {
        Array.prototype.forEach.call(el.querySelectorAll('.avc-band-tag, .avc-band-handle'), function (n) {
            n.remove();
        });
    }

    /* ---- dragging ------------------------------------------------------- */

    var drag = null;

    function pointOf(e) {
        var touch = e.touches && e.touches[0];
        return { x: touch ? touch.clientX : e.clientX, y: touch ? touch.clientY : e.clientY };
    }

    function onDown(e) {
        var handle = e.target.closest && e.target.closest('[data-avc-handle]');
        var hit = e.target.closest && e.target.closest('[data-avc-key]');
        if (!hit || !page) { return; }

        var key = hit.getAttribute('data-avc-key');
        select(key);

        var box = targets[key].kind === 'block' ? grid()[key] : placements()[key];
        if (!box) { return; }

        var p = pointOf(e);
        drag = {
            key: key,
            mode: handle ? 'resize' : 'move',
            startX: p.x,
            startY: p.y,
            box: box.slice(0, 4),
            kind: targets[key].kind
        };

        desk.classList.add('is-dragging');
        e.preventDefault();
    }

    function onMove(e) {
        if (!drag) { return; }
        var p = pointOf(e);
        // screen pixels to page fractions: the drag happens at the current scale
        var dx = (p.x - drag.startX) / scale / (PAGE_W * (96 / 72));
        var dy = (p.y - drag.startY) / scale / (PAGE_H * (96 / 72));
        var box = drag.box.slice();

        if (drag.mode === 'move') {
            box[0] = clamp(box[0] + dx);
            box[1] = clamp(box[1] + dy);
            // keep the box on the page rather than letting it run off the edge
            box[0] = Math.min(box[0], 1 - box[2]);
            box[1] = Math.min(box[1], 1 - box[3]);
        } else {
            box[2] = Math.max(0.03, clamp(box[2] + dx));
            box[3] = Math.max(0.02, clamp(box[3] + dy));
            box[2] = Math.min(box[2], 1 - box[0]);
            box[3] = Math.min(box[3], 1 - box[1]);
        }

        box = box.map(function (n) { return Math.round(n * 1000) / 1000; });
        commit(drag.key, box);
        e.preventDefault();
    }

    function onUp() {
        if (!drag) { return; }
        var finished = drag;
        drag = null;
        desk.classList.remove('is-dragging');
        // one history entry per drag, not one per mouse move
        document.dispatchEvent(new CustomEvent('avc:change', { detail: { key: finished.key } }));
    }

    /* ---- wiring --------------------------------------------------------- */

    desk.addEventListener('mousedown', onDown);
    desk.addEventListener('touchstart', onDown, { passive: false });
    document.addEventListener('mousemove', onMove);
    document.addEventListener('touchmove', onMove, { passive: false });
    document.addEventListener('mouseup', onUp);
    document.addEventListener('touchend', onUp);

    // clicking the desk away from anything drops the selection
    desk.addEventListener('click', function (e) {
        if (!e.target.closest('[data-avc-key]')) { select(null); }
    });

    window.addEventListener('resize', fit);

    var zoomIn = document.getElementById('avc-zoom-in');
    var zoomOut = document.getElementById('avc-zoom-out-btn');
    if (zoomIn) { zoomIn.addEventListener('click', function () { setScale(scale + 0.1); }); }
    if (zoomOut) { zoomOut.addEventListener('click', function () { setScale(scale - 0.1); }); }

    function setScale(next) {
        scale = Math.max(0.3, Math.min(2, next));
        stage.style.setProperty('--av-page-scale', scale.toFixed(3));
        stage.style.height = (PAGE_H * (96 / 72) * scale) + 'px';
        setZoomLabel();
    }

    /*
     * Re-reading after a redraw. The preview is replaced whenever a design,
     * accent or block changes, so the handles have to be re-attached to the new
     * markup - and the selection is kept so the panel on the left does not jump
     * back to nothing while someone is adjusting a component.
     */
    function refresh() {
        var keep = activeKey;
        if (!measure()) { return; }
        fit();
        if (keep && targets[keep]) { select(keep); }
        else { select(null); }
    }

    // the rest of the page tells us when a new document has been drawn
    document.addEventListener('avc:drawn', refresh);

    refresh();

    window.avcPageEditor = {
        refresh: refresh,
        select: select,
        fit: fit,
        grid: grid,
        placements: placements,
        scaleOf: function () { return scale; }
    };
})();

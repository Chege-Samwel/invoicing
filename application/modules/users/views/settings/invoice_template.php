<?php
/**
 * Template canvas.
 *
 * The invoice document, editable in place: blocks on the left that can be
 * dragged into a different order, the document itself in the middle, and the
 * settings that change how it looks on the right. Every change redraws the
 * preview from the same helper the printed document uses, so what is on this
 * screen is what comes out of the printer.
 *
 * @var array  $layout     from adomin_invoice_layout()
 * @var array  $firm       name / address / city / bulstat / vat / mol / image
 * @var array  $templates  key => [label, description]
 */
defined('BASEPATH') OR exit('No direct script access allowed');

$sections = adomin_invoice_sections($layout);
$accent = adomin_invoice_accent($layout['accent']);

$logoUrl = '';
if (!empty($firm['image'])) {
    $rel = 'attachments/' . COMPANIES_IMAGES_DIR . '/' . (int) SELECTED_COMPANY_ID . '/' . $firm['image'];
    if (is_file(FCPATH . $rel)) {
        $logoUrl = base_url($rel);
    }
}
?>
<div class="avc">

    <div class="avc-head">
        <div>
            <h1>Invoice template</h1>
            <p>Drag a block to move it. Everything here is what the invoice prints.</p>
        </div>
        <div class="avc-head-actions">
            <a class="av-btn" href="<?= lang_url('user/settings/invoices') ?>">Back to settings</a>
            <button type="submit" form="avc-form" name="saveCanvas" value="1" class="av-btn av-btn-primary" id="avc-save">
                <i class="fa fa-check" aria-hidden="true"></i> Save template
            </button>
        </div>
    </div>

    <form method="POST" action="<?= lang_url('user/settings/invoices/template') ?>" id="avc-form">
        <input type="hidden" name="opt_invTemplate" id="avc-template" value="<?= html_escape($layout['template']) ?>">
        <input type="hidden" name="opt_invSections" id="avc-sections" value="<?= html_escape(implode(',', $sections)) ?>">
        <input type="hidden" name="opt_invLogoPos" id="avc-logoPos" value="<?= html_escape($layout['logoPos']) ?>">
        <input type="hidden" name="opt_invAddressPos" id="avc-addressPos" value="<?= html_escape($layout['addressPos']) ?>">
        <input type="hidden" name="opt_invAccent" id="avc-accent" value="<?= html_escape($layout['accent']) ?>">
        <input type="hidden" name="opt_invCurrencyPos" id="avc-currencyPos" value="<?= html_escape($layout['currencyPos']) ?>">
        <input type="hidden" name="opt_invShowLogo" id="avc-showLogo" value="<?= $layout['showLogo'] === '1' ? '1' : '0' ?>">
        <input type="hidden" name="opt_invShowAddress" id="avc-showAddress" value="<?= $layout['showAddress'] === '1' ? '1' : '0' ?>">
        <input type="hidden" name="opt_invShowContacts" id="avc-showContacts" value="<?= $layout['showContacts'] === '1' ? '1' : '0' ?>">
        <input type="hidden" name="opt_invShowFirmVat" id="avc-showFirmVat" value="<?= $layout['showFirmVat'] === '1' ? '1' : '0' ?>">
        <input type="hidden" name="opt_invShowRemarks" id="avc-showRemarks" value="<?= $layout['showRemarks'] === '1' ? '1' : '0' ?>">

        <div class="avc-grid">

            <!-- ── components ─────────────────────────────────────────────── -->
            <aside class="avc-panel">
                <h2>Components</h2>
                <p class="avc-note">Drag to reorder. Each one is a block of the document.</p>
                <ul class="avc-blocks" id="avc-blocks">
                    <?php foreach ($sections as $name) { ?>
                        <li class="avc-block" draggable="true" data-block="<?= html_escape($name) ?>">
                            <span class="avc-grip" aria-hidden="true">⋮⋮</span>
                            <span class="avc-block-name"><?= html_escape(adomin_invoice_section_label($name)) ?></span>
                        </li>
                    <?php } ?>
                </ul>
                <p class="avc-hint" id="avc-order-state">Order saved with the template.</p>

                <h2 class="avc-mt">Logo</h2>
                <div class="avc-logo">
                    <div class="avc-logo-box">
                        <?php if ($logoUrl !== '') { ?>
                            <img src="<?= html_escape($logoUrl) ?>?v=<?= time() ?>" alt="Current logo" id="avc-logo-img">
                        <?php } else { ?>
                            <span class="avc-logo-empty" id="avc-logo-empty">No logo yet</span>
                            <img src="" alt="" id="avc-logo-img" hidden>
                        <?php } ?>
                    </div>
                    <label class="av-btn avc-upload" for="avc-logo-file">
                        <i class="fa fa-upload" aria-hidden="true"></i> Upload logo
                    </label>
                    <input type="file" id="avc-logo-file" accept="image/png,image/jpeg,image/gif,image/webp" hidden>
                    <p class="avc-hint" id="avc-logo-state">PNG, JPEG, GIF or WebP. Under 2 MB.</p>
                </div>
            </aside>

            <!-- ── the document ───────────────────────────────────────────── -->
            <section class="avc-canvas-wrap">
                <div class="avc-canvas-head">
                    <span>Live document</span>
                    <span class="avc-canvas-state" id="avc-canvas-state">Up to date</span>
                </div>
                <div class="avc-canvas" id="avc-canvas">
                    <noscript><p class="avc-hint">Saving works without JavaScript; the live preview needs it.</p></noscript>
                </div>
            </section>

            <!-- ── settings ───────────────────────────────────────────────── -->
            <aside class="avc-panel">
                <h2>Design</h2>
                <div class="avc-designs">
                    <?php foreach ($templates as $key => $meta) { ?>
                        <label class="avc-design<?= $layout['template'] === $key ? ' is-on' : '' ?>">
                            <input type="radio" name="avc-design" value="<?= html_escape($key) ?>"
                                <?= $layout['template'] === $key ? 'checked' : '' ?>>
                            <span class="avc-design-name"><?= html_escape($meta[0]) ?></span>
                            <span class="avc-design-sub"><?= html_escape($meta[1]) ?></span>
                        </label>
                    <?php } ?>
                </div>

                <h2 class="avc-mt">Accent</h2>
                <div class="avc-choices" data-field="accent">
                    <?php foreach (array('blue' => 'Blue', 'slate' => 'Slate', 'green' => 'Green', 'mono' => 'Mono') as $key => $label) { ?>
                        <button type="button" class="avc-choice<?= $layout['accent'] === $key ? ' is-on' : '' ?>"
                            data-value="<?= $key ?>">
                            <i class="avc-dot avc-dot-<?= $key ?>"></i><?= $label ?>
                        </button>
                    <?php } ?>
                </div>

                <h2 class="avc-mt">Layout</h2>
                <label class="avc-label">Logo position</label>
                <div class="avc-choices" data-field="logoPos">
                    <?php foreach (array('left' => 'Left', 'center' => 'Centre', 'right' => 'Right', 'hide' => 'Hidden') as $key => $label) { ?>
                        <button type="button" class="avc-choice<?= $layout['logoPos'] === $key ? ' is-on' : '' ?>"
                            data-value="<?= $key ?>"><?= $label ?></button>
                    <?php } ?>
                </div>

                <label class="avc-label">Company details</label>
                <div class="avc-choices" data-field="addressPos">
                    <?php foreach (array('below' => 'Under logo', 'side' => 'Beside logo') as $key => $label) { ?>
                        <button type="button" class="avc-choice<?= $layout['addressPos'] === $key ? ' is-on' : '' ?>"
                            data-value="<?= $key ?>"><?= $label ?></button>
                    <?php } ?>
                </div>

                <label class="avc-label">Currency shown</label>
                <div class="avc-choices" data-field="currencyPos">
                    <?php foreach (array('totals' => 'In totals', 'header' => 'In header') as $key => $label) { ?>
                        <button type="button" class="avc-choice<?= $layout['currencyPos'] === $key ? ' is-on' : '' ?>"
                            data-value="<?= $key ?>"><?= $label ?></button>
                    <?php } ?>
                </div>

                <h2 class="avc-mt">Shown on the invoice</h2>
                <div class="avc-toggles">
                    <?php
                    $toggles = array(
                        'showLogo' => 'Logo',
                        'showAddress' => 'Address',
                        'showContacts' => 'Contact person',
                        'showFirmVat' => 'VAT number',
                        'showRemarks' => 'Remarks',
                    );
                    foreach ($toggles as $field => $label) { ?>
                        <label class="avc-toggle">
                            <input type="checkbox" data-field="<?= $field ?>"
                                <?= $layout[$field] === '1' ? 'checked' : '' ?>>
                            <span><?= $label ?></span>
                        </label>
                    <?php } ?>
                </div>

                <h2 class="avc-mt">Message</h2>
                <textarea name="opt_invFooterNote" id="avc-footerNote" rows="3" maxlength="300"
                    class="form-control" placeholder="Payment within 14 days. Thank you."><?= html_escape($layout['footerNote']) ?></textarea>
                <p class="avc-hint">300 characters, printed under the totals.</p>
            </aside>
        </div>
    </form>
</div>

<style>
.avc { max-width: none; }
.avc-head { display:flex; align-items:flex-start; justify-content:space-between; gap:18px; flex-wrap:wrap; margin-bottom:18px; }
.avc-head h1 { margin:0; font-size:22px; font-weight:700; color:var(--av-ink); }
.avc-head p { margin:5px 0 0; font-size:13.5px; color:var(--av-mut); }
.avc-head-actions { display:flex; gap:8px; flex-wrap:wrap; }

.avc-grid { display:grid; grid-template-columns:250px minmax(0,1fr) 280px; gap:16px; align-items:start; }
@media (max-width:1200px) { .avc-grid { grid-template-columns:230px minmax(0,1fr); } .avc-grid > aside:last-child { grid-column:1 / -1; } }
@media (max-width:860px)  { .avc-grid { grid-template-columns:1fr; } }

.avc-panel { background:var(--av-surface); border:1px solid var(--av-line); border-radius:var(--av-radius); padding:16px; }
.avc-panel h2, .avc-canvas-head { margin:0 0 4px; font-size:11px; letter-spacing:.1em; text-transform:uppercase; color:var(--av-mut); font-weight:700; }
.avc-mt { margin-top:20px !important; }
.avc-note, .avc-hint { margin:6px 0 0; font-size:11.5px; color:var(--av-mut); line-height:1.45; }

.avc-blocks { list-style:none; margin:12px 0 0; padding:0; }
.avc-block { display:flex; align-items:center; gap:9px; padding:10px 11px; margin-bottom:6px;
             border:1px solid var(--av-line); border-radius:8px; background:var(--av-surface);
             font-size:13px; font-weight:600; color:var(--av-ink-soft); cursor:grab;
             transition:border-color .14s, box-shadow .14s, background .14s; }
.avc-block:hover { border-color:#c9dcf2; background:#f7fbff; }
.avc-block.is-drag { opacity:.45; }
.avc-block.is-over { border-color:var(--av-acc); box-shadow:0 0 0 2px rgba(11,107,203,.14); }
.avc-grip { color:#9aa5b1; font-size:12px; letter-spacing:-2px; }
.avc-block-name { flex:1 1 auto; min-width:0; }

.avc-logo { margin-top:10px; }
.avc-logo-box { display:flex; align-items:center; justify-content:center; min-height:84px;
                border:1px dashed var(--av-line-strong); border-radius:8px; padding:10px; background:var(--av-soft); }
.avc-logo-box img { max-width:100%; max-height:64px; display:block; }
.avc-logo-empty { font-size:12px; color:var(--av-mut); }
.avc-upload { margin-top:9px; width:100%; justify-content:center; cursor:pointer; }

.avc-canvas-wrap { min-width:0; }
.avc-canvas-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
.avc-canvas-state { text-transform:none; letter-spacing:0; font-weight:600; color:var(--av-ok); }
.avc-canvas-state.is-busy { color:var(--av-mut); }
.avc-canvas { background:#fff; border:1px solid var(--av-line); border-radius:var(--av-radius);
              padding:22px; min-height:420px; box-shadow:var(--av-shadow); overflow:auto; }
.avc-canvas.is-busy { opacity:.6; }

.avc-designs { display:flex; flex-direction:column; gap:7px; margin-top:10px; }
.avc-design { display:block; padding:10px 11px; border:1px solid var(--av-line); border-radius:8px;
              cursor:pointer; transition:border-color .14s, background .14s; }
.avc-design:hover { border-color:#cfe0f3; background:#f7fbff; }
.avc-design.is-on { border-color:var(--av-acc); background:#f2f8ff; }
.avc-design input { display:none; }
.avc-design-name { display:block; font-size:13px; font-weight:700; color:var(--av-ink); }
.avc-design-sub { display:block; font-size:11.5px; color:var(--av-mut); margin-top:2px; line-height:1.4; }

.avc-label { display:block; margin:14px 0 5px; font-size:12px; font-weight:700; color:var(--av-ink-soft); }
.avc-choices { display:flex; flex-wrap:wrap; gap:6px; }
.avc-choice { display:inline-flex; align-items:center; gap:6px; padding:7px 11px; font-size:12.5px;
              font-weight:600; color:var(--av-ink-soft); background:var(--av-surface);
              border:1px solid var(--av-line); border-radius:7px; cursor:pointer;
              transition:border-color .14s, background .14s, color .14s; }
.avc-choice:hover { border-color:#cfe0f3; background:#f7fbff; }
.avc-choice.is-on { border-color:var(--av-acc); background:#eef4fb; color:var(--av-acc); }
.avc-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
.avc-dot-blue { background:#0b6bcb; } .avc-dot-slate { background:#33475b; }
.avc-dot-green { background:#157347; } .avc-dot-mono { background:#111418; }

.avc-toggles { margin-top:10px; }
.avc-toggle { display:flex; align-items:center; gap:8px; margin-bottom:7px; font-size:13px;
              font-weight:600; color:var(--av-ink-soft); cursor:pointer; }
.avc-toggle input { width:16px; height:16px; margin:0; accent-color:var(--av-acc); cursor:pointer; }

textarea#avc-footerNote { width:100%; resize:vertical; font-size:13px; }
</style>

<script>
(function () {
    var form = document.getElementById('avc-form');
    var canvas = document.getElementById('avc-canvas');
    var state = document.getElementById('avc-canvas-state');
    if (!form || !canvas) { return; }

    var PREVIEW = '<?= lang_url('user/settings/invoices/preview') ?>';
    var UPLOAD = '<?= lang_url('user/settings/invoices/upload-logo') ?>';

    function field(name) { return document.getElementById('avc-' + name); }

    /* ---- preview ------------------------------------------------------- */
    var timer = null, inFlight = false, queued = false;

    function payload() {
        var data = new URLSearchParams();
        data.set('opt_invTemplate', field('template').value);
        data.set('opt_invSections', field('sections').value);
        data.set('opt_invLogoPos', field('logoPos').value);
        data.set('opt_invAddressPos', field('addressPos').value);
        data.set('opt_invAccent', field('accent').value);
        data.set('opt_invCurrencyPos', field('currencyPos').value);
        ['showLogo', 'showAddress', 'showContacts', 'showFirmVat', 'showRemarks'].forEach(function (k) {
            data.set('opt_inv' + k.charAt(0).toUpperCase() + k.slice(1), field(k).value);
        });
        data.set('opt_invFooterNote', document.getElementById('avc-footerNote').value);
        var meta = document.querySelector('meta[name="csrf-token"]');
        var metaName = document.querySelector('meta[name="csrf-name"]');
        if (meta && metaName) { data.set(metaName.getAttribute('content'), meta.getAttribute('content')); }
        return data.toString();
    }

    function draw() {
        if (inFlight) { queued = true; return; }
        inFlight = true;
        canvas.classList.add('is-busy');
        state.textContent = 'Updating…';
        state.classList.add('is-busy');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', PREVIEW, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            inFlight = false;
            canvas.classList.remove('is-busy');
            if (xhr.status === 200) {
                canvas.innerHTML = xhr.responseText;
                state.textContent = 'Up to date';
                state.classList.remove('is-busy');
            } else {
                state.textContent = 'Preview unavailable';
                state.classList.remove('is-busy');
            }
            if (queued) { queued = false; draw(); }
        };
        xhr.onerror = function () {
            inFlight = false;
            canvas.classList.remove('is-busy');
            state.textContent = 'Preview unavailable';
            state.classList.remove('is-busy');
        };
        xhr.send(payload());
    }

    function soon() { clearTimeout(timer); timer = setTimeout(draw, 140); }

    /* ---- choice buttons and toggles ------------------------------------ */
    var choices = document.querySelectorAll('.avc-choice');
    for (var i = 0; i < choices.length; i++) {
        choices[i].addEventListener('click', function () {
            var group = this.parentNode;
            var siblings = group.querySelectorAll('.avc-choice');
            for (var j = 0; j < siblings.length; j++) { siblings[j].classList.remove('is-on'); }
            this.classList.add('is-on');
            var target = field(group.getAttribute('data-field'));
            if (target) { target.value = this.getAttribute('data-value'); }
            // "hidden" means the same thing as the logo toggle being off
            if (group.getAttribute('data-field') === 'logoPos' && this.getAttribute('data-value') === 'hide') {
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

    document.getElementById('avc-footerNote').addEventListener('input', soon);

    /* ---- design -------------------------------------------------------- */
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

    /* ---- drag to reorder ----------------------------------------------- */
    var list = document.getElementById('avc-blocks');
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
        // Firefox needs data set for a drag to start
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

    /* ---- logo upload --------------------------------------------------- */
    var file = document.getElementById('avc-logo-file');
    var logoState = document.getElementById('avc-logo-state');
    var logoImg = document.getElementById('avc-logo-img');

    file.addEventListener('change', function () {
        if (!file.files || !file.files.length) { return; }
        var body = new FormData();
        body.append('logo', file.files[0]);
        var meta = document.querySelector('meta[name="csrf-token"]');
        var metaName = document.querySelector('meta[name="csrf-name"]');
        if (meta && metaName) { body.append(metaName.getAttribute('content'), meta.getAttribute('content')); }

        logoState.textContent = 'Uploading…';
        var xhr = new XMLHttpRequest();
        xhr.open('POST', UPLOAD, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (err) {}
            if (res && res.status === 'success') {
                logoState.textContent = res.message;
                if (logoImg) { logoImg.src = res.url; logoImg.hidden = false; }
                var empty = document.getElementById('avc-logo-empty');
                if (empty) { empty.style.display = 'none'; }
                field('showLogo').value = '1';
                draw();
            } else {
                logoState.textContent = (res && res.message) ? res.message : 'Upload failed. Try again.';
            }
        };
        xhr.onerror = function () { logoState.textContent = 'Upload failed. Check your connection.'; };
        xhr.send(body);
    });

    /* ---- save ---------------------------------------------------------- */
    form.addEventListener('submit', function () {
        var btn = document.getElementById('avc-save');
        if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
    });

    draw();
})();
</script>

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

    <form method="POST" action="<?= lang_url('user/settings/invoices/template') ?>" id="avc-form"
          data-preview-url="<?= lang_url('user/settings/invoices/preview') ?>"
          data-upload-url="<?= lang_url('user/settings/invoices/upload-logo') ?>">
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
        <input type="hidden" name="opt_invPlacement" id="avc-placement" value="<?= html_escape(isset($layout['placement']) ? $layout['placement'] : '') ?>">
        <input type="hidden" name="opt_invEdge" id="avc-edge" value="<?= html_escape(isset($layout['edge']) ? $layout['edge'] : 'none') ?>">

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

                <h2 class="avc-mt">Selected component</h2>
                <p class="avc-hint" id="avc-active-name">Logo</p>
                <p class="avc-hint" id="avc-dims">&nbsp;</p>

                <p class="avc-hint" id="avc-style-none" hidden>
                    Nothing is selected on the page. Choose a component on the
                    document, or use <em>Reset</em> on one you have moved.
                </p>

                <div id="avc-style-body">
                    <label class="avc-label" for="avc-size">Font size</label>
                    <input type="range" id="avc-size" min="6" max="30" step="1" value="12" class="avc-range">
                    <p class="avc-hint"><span id="avc-size-out">12 pt</span></p>

                    <label class="avc-label" for="avc-face">Font family</label>
                    <select id="avc-face" class="form-control avc-select">
                        <option value="">Helvetica (default)</option>
                        <option value="Arial">Arial</option>
                        <option value="Georgia">Georgia</option>
                        <option value="Times New Roman">Times New Roman</option>
                        <option value="Courier New">Courier New</option>
                        <option value="Verdana">Verdana</option>
                        <option value="Trebuchet MS">Trebuchet MS</option>
                    </select>

                    <button type="button" class="av-btn avc-reset-one" id="avc-reset-one">
                        <i class="fa fa-undo" aria-hidden="true"></i> Reset this component
                    </button>
                </div>
            </aside>

            <!-- ── the document ───────────────────────────────────────────── -->
            <section class="avc-canvas-wrap">
                <div class="avc-canvas-head">
                    <span>Live document</span>
                    <span class="avc-canvas-state" id="avc-canvas-state">Up to date</span>
                </div>
                <!--
                    The document is already here, drawn by the server. The editor
                    adopts it on load, so there is something to drag from the
                    first frame instead of an empty box waiting on a request.
                -->
                <div class="avc-canvas" id="avc-canvas"><?= isset($previewHtml) ? $previewHtml : '' ?></div>
                <noscript><p class="avc-hint">Saving works without JavaScript; the live preview needs it.</p></noscript>
            </section>

            <!-- ── settings ───────────────────────────────────────────────── -->
            <aside class="avc-panel">
                <h2>Design</h2>
                <div class="avc-designs">
                    <?php foreach ($templates as $key => $meta) { ?>
                        <label class="avc-design<?= $layout['template'] === $key ? ' is-on' : '' ?>">
                            <input type="radio" name="avc-design" value="<?= html_escape($key) ?>"
                                <?= $layout['template'] === $key ? 'checked' : '' ?>>
                            <span class="avc-design-swatch avc-swatch-<?= html_escape($key) ?>" aria-hidden="true"></span>
                            <span class="avc-design-text">
                                <span class="avc-design-name"><?= html_escape($meta[0]) ?></span>
                                <span class="avc-design-sub"><?= html_escape($meta[1]) ?></span>
                            </span>
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

                <h2 class="avc-mt">Edge graphic</h2>
                <p class="avc-hint">A band or border on the printed page. Drawn with
                    gradients, so nothing extra is fetched when it prints.</p>
                <div class="avc-edges">
                    <?php
                    $edges = function_exists('adomin_invoice_edge_designs') ? adomin_invoice_edge_designs() : array();
                    $edgeOn = isset($layout['edge']) ? $layout['edge'] : 'none';
                    foreach ($edges as $key => $meta) { ?>
                        <button type="button" class="avc-edge-choice<?= $edgeOn === $key ? ' is-on' : '' ?>"
                            data-value="<?= html_escape($key) ?>" title="<?= html_escape($meta[1]) ?>">
                            <span class="avc-edge-swatch avc-edge-<?= html_escape($key) ?>" aria-hidden="true"></span>
                            <?= html_escape($meta[0]) ?>
                        </button>
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

<!-- the document's own stylesheets: the canvas shows the real design -->
<link rel="stylesheet" href="<?= adomin_asset('assets/users/css/invoices-templates.css') ?>">
<link rel="stylesheet" href="<?= adomin_asset('assets/users/css/invoice-banner.css') ?>">
<link rel="stylesheet" href="<?= adomin_asset('assets/users/css/invoice-canvas.css') ?>">

<!-- the canvas editor. Page-scoped: nothing else on the site uses it. -->
<script src="<?= adomin_asset('assets/users/js/vendor/fabric/fabric.min.js') ?>"></script>
<script src="<?= adomin_asset('assets/users/js/invoice-template.js') ?>"></script>

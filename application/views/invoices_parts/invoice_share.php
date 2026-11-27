<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Public, read-only copy of one document - what the client sees when you use
 * "Send via link" (Adomin Technologies).
 *
 * No login, no editing, no CSRF surface: the token in invoices.uniqid is the
 * only thing that grants access, and "Revoke link" rotates it. If the sender
 * still wants the accept/refuse step, that lives on accept/invoice/<token>.
 */
// this page is rendered by the public controller, where the users language
// file is not loaded - keep the few labels we need local
$statusLabels = array(
    'undo' => 'Awaiting',
    'accepted' => 'Accepted',
    'refused' => 'Refused',
    'canceled' => 'Cancelled',
);
$firmName = isset($invoice['firm']['name']) ? $invoice['firm']['name'] : '';
$currency = isset($invoice['inv_currency']) ? $invoice['inv_currency'] : '';
$issued = !empty($invoice['date_create']) ? date('d.m.Y', (int) $invoice['date_create']) : '';
?>
<div class="container">
    <div class="inv-accept adomin-share-page">
        <a href="<?= base_url() ?>" class="logo">
            <img src="<?= adomin_logo_url() ?>" alt="<?= html_escape(adomin_conf('brand', 'app_name')) ?>">
        </a>
        <hr>

        <div class="adomin-doc-bar">
            <p class="adomin-doc-title">
                <?= html_escape($typeLabel) ?> № <?= html_escape($invoice['inv_number']) ?>
            </p>
            <?php if ($issued !== '') { ?>
                <span class="adomin-inline-dot">·</span>
                <span><?= html_escape($issued) ?></span>
            <?php } ?>
            <span class="adomin-doc-total">
                <?= html_escape($invoice['final_total'] . ' ' . $currency) ?>
            </span>
            <span class="adomin-status-pill <?= $invoice['status'] == 'accepted' ? 'is-accepted' : ($invoice['status'] == 'refused' ? 'is-refused' : ($invoice['status'] == 'canceled' ? 'is-refused' : 'is-undo')) ?>">
                <?= html_escape($invoice['status']) ?>
            </span>
            <span class="adomin-doc-actions adomin-no-print">
                <a href="<?= html_escape($pdfUrl) ?>" class="btn btn-adomin" target="_blank" rel="noopener">
                    <i class="fa fa-file-pdf-o" aria-hidden="true"></i>
                    <?= lang('adomin_download_pdf') ?>
                </a>
                <a href="javascript:void(0);" class="btn btn-default" onclick="window.print();">
                    <i class="fa fa-print" aria-hidden="true"></i>
                    <?= lang('adomin_print') ?>
                </a>
                <?php
                /*
                 * The code the document carries, so a client can check the copy
                 * in front of them. On a screen it is more useful as a link than
                 * as something to scan, so the markup is wrapped in one - the
                 * code is still drawn, because one of the two is always available
                 * to whoever is reading.
                 */
                $shareVerifySvg = function_exists('adomin_qr_svg') ? adomin_qr_svg($invoice, 30) : '';
                if ($shareVerifySvg !== '') {
                    ?>
                    <span class="adomin-verify-inline" title="<?= html_escape(lang('verify_title')) ?>">
                        <?= $shareVerifySvg ?>
                    </span>
                <?php } ?>
            </span>
        </div>

        <p class="adomin-share-lead">
            <?= html_escape($firmName) ?>
            <?php if (!empty($invoice['client']['client_name'])) { ?>
                · <?= lang('for') ?> <?= html_escape($invoice['client']['client_name']) ?>
            <?php } ?>
        </p>

        <div class="view-container">
            <div class="invoice-box">
                <?php
                if (is_file($templateFile)) {
                    include $templateFile;
                }
                ?>
            </div>
        </div>

        <hr>
        <p class="footer-text">
            <?= lang('create_and_send_inv_with') ?> -
            <a href="<?= html_escape(adomin_conf('brand', 'website')) ?>" target="_blank" rel="noopener">
                <?= html_escape(adomin_conf('brand', 'name')) ?>
            </a>
        </p>
        <div class="adomin-stamp">
            <?= adomin_foot() ?>
        </div>
    </div>
</div>

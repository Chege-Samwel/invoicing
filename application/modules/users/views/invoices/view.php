<?php
/*
 * The preview is the printed document, so it needs the sheets the printed
 * document is made of.
 *
 * Only two of the four were linked here. .adv-page and .adv-band are declared
 * in pdf-base.css and nowhere else, so in the preview every block lost its
 * absolute position and stacked in normal flow: the letterhead at the top, the
 * item table under it, the totals under that, on a sheet with no page box. The
 * download looked right because the PDF loads all four sheets - which is
 * exactly the difference the reader notices, two views of one invoice that do
 * not agree. These are the same four AdominDocs::invoiceHtml() inlines.
 */ ?>
<link rel="stylesheet" href="<?= adomin_asset('assets/users/css/invoices-templates.css') ?>">
<link rel="stylesheet" href="<?= adomin_asset('assets/users/css/invoice-banner.css') ?>">
<link rel="stylesheet" href="<?= adomin_asset('assets/users/css/invoice-adoc.css') ?>">
<link rel="stylesheet" href="<?= adomin_asset('assets/users/css/pdf-base.css') ?>">
<div class="selected-page">
    <div class="inner">
        <h1>
            <i class="fa fa-file-text-o" aria-hidden="true"></i>
            <?= lang('invoices') ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?= lang_url('user') ?>"><?= lang('home') ?></a></li> 
			<li><a href="<?= lang_url('user/invoices') ?>"><?= lang('invoices') ?></a></li>
            <li class="active"><?= $invNum ?></li>
        </ol>
    </div> 
</div>
<div class="inner-page-menu">
    <?php /*
        There used to be two buttons here, "Download/Print Copy" and
        "Download/Print Original", which produced the same document with a
        different word in the corner. The word is gone, so one button remains.
    */ ?>
    <a href="<?= base_url('user/' . $invType . '/print/' . $invNum) ?>" target="_blank" class="btn btn-blue" style="position: relative;">
        <?= lang('adomin_download_print') ?>
    </a>
    <?php /* The PDF is not a sharing feature. */ ?>
    <a href="<?= base_url('user/' . $invType . '/print/' . $invNum) ?>?download=1" class="btn btn-blue" data-adomin-download>
        <i class="fa fa-download" aria-hidden="true"></i>
        <?= lang('adomin_download_pdf') ?>
    </a>
    <?php
    /*
     * "Send via link" is the only thing behind the share switch.
     *
     * The download button used to sit inside this block, so a deployment that
     * turned share links off - ADOMIN_SHARE_LINKS=0, or a business that does
     * not want public URLs - lost the ability to download its own invoice as a
     * PDF. Two features, one switch, and the one that disappeared was the one
     * with nothing to do with sharing.
     */
    ?>
    <?php if (!empty($shareEnabled)) { ?>
        <a href="javascript:void(0);" class="btn btn-adomin" data-adomin-share data-invoice-id="<?= (int) $invoice['id'] ?>">
            <i class="fa fa-link" aria-hidden="true"></i>
            <?= lang('adomin_send_via_link') ?>
        </a>
    <?php } ?>
</div> 
<?php if (!empty($docVerdict)) { ?>
    <?php
    /*
     * The self-diagnosis, when asked for with ?why=1.
     *
     * Inline styles on purpose: the whole point is to work when the page's own
     * stylesheet is the thing that is wrong.
     */
    ?>
    <div style="margin:14px 0;padding:14px 16px;border-left:4px solid #0b6bcb;background:#f5f9fd;font:13px/1.55 system-ui,sans-serif;color:#22282f;">
        <p style="margin:0 0 8px;font-weight:600;">Why this preview looks the way it does</p>
        <p style="margin:0 0 10px;"><?= html_escape($docVerdict) ?></p>
        <table style="border-collapse:collapse;width:100%;margin-bottom:10px;">
            <tr><th style="text-align:left;padding:3px 8px 3px 0;font-weight:600;">block</th>
                <th style="text-align:left;padding:3px 8px 3px 0;font-weight:600;">text</th>
                <th style="text-align:left;padding:3px 8px 3px 0;font-weight:600;">opens with</th></tr>
            <?php foreach ($docReport['bands'] as $bandName => $band) { ?>
                <tr>
                    <td style="padding:3px 8px 3px 0;"><?= html_escape($bandName) ?></td>
                    <td style="padding:3px 8px 3px 0;<?= $band['text_chars'] === 0 ? 'color:#c0392b;font-weight:600;' : '' ?>">
                        <?= (int) $band['text_chars'] ?> chars
                    </td>
                    <td style="padding:3px 8px 3px 0;font-family:ui-monospace,monospace;font-size:11.5px;">
                        <?= html_escape($band['opens'] !== '' ? $band['opens'] : '(nothing)') ?>
                        <?php if ($band['error'] !== NULL) { ?>
                            <br><span style="color:#c0392b;"><?= html_escape($band['error']) ?></span>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </table>
        <p style="margin:0 0 6px;">
            <strong>Snapshot:</strong>
            <?php $bits = array(); ?>
            <?php foreach ($docReport['snapshot'] as $key => $value) { ?>
                <?php $bits[] = $key . '=' . (is_bool($value) ? ($value ? 'present' : 'ABSENT') : $value); ?>
            <?php } ?>
            <code style="font-size:11.5px;"><?= html_escape(implode(' · ', $bits)) ?></code>
        </p>
        <p style="margin:0;">
            <strong>Template:</strong> <code><?= html_escape($docReport['template']) ?></code>
            <?= $docReport['template_found'] ? 'found' : '<span style="color:#c0392b;">MISSING</span>' ?>
            · <?= (int) $docReport['template_bytes'] ?> bytes
            <?php if ($docReport['template_error'] !== NULL) { ?>
                <br><span style="color:#c0392b;"><?= html_escape($docReport['template_error']) ?></span>
            <?php } ?>
            · page body <?= (int) $docReport['page_bytes'] ?> bytes
        </p>
        <?php if (!empty($docReport['notices'])) { ?>
            <p style="margin:8px 0 0;"><strong>Notices</strong></p>
            <ul style="margin:4px 0 0;padding-left:18px;font-family:ui-monospace,monospace;font-size:11.5px;">
                <?php foreach ($docReport['notices'] as $note) { ?>
                    <li><?= html_escape($note) ?></li>
                <?php } ?>
            </ul>
        <?php } ?>
    </div>
<?php } ?>
<div class="view-container">
    <div class="pageDelivery hidden"></div>
    <div class="invoice-box">
        <?php
        /*
         * The watermark and the stamp are part of the printed document -
         * invoiceHtml() draws both for the PDF - so the preview draws them
         * inside the page box, in the same order. Without them a Free-plan
         * invoice previewed as though it printed clean, and the business only
         * found out what its client would see after downloading it.
         */
        echo $this->adomindocs->watermark($invoice);
        include $templateFile;
        echo $this->adomindocs->stampBlock();
        ?>
    </div>
    <div class="adomin-stamp">
        <?= adomin_foot() ?>
    </div>
</div> 
<?php
if (!empty($actionHistory)) {
    ?>
    <div class="action-history">
        <h1><?= lang('inv_action_history') ?></h1>
        <table>
            <?php foreach ($actionHistory as $action) { ?> 
                <tr>
                    <td><?= lang('status_' . $action['action']) ?></td>
                    <td><?= $action['info'] ?></td>
                    <td><?= date('d.m.Y', $action['time']) ?></td>
                </tr> 
            <?php } ?>
        </table>
    </div>
    <?php
}
?>
<?php if (!empty($shareEnabled)) { ?>
    <?php $this->load->view('invoices/share_modal'); ?>
<?php } ?>

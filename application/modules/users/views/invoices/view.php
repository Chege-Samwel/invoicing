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
    <?php if (!empty($shareEnabled)) { ?>
        <a href="javascript:void(0);" class="btn btn-adomin" data-adomin-share data-invoice-id="<?= (int) $invoice['id'] ?>">
            <i class="fa fa-link" aria-hidden="true"></i>
            <?= lang('adomin_send_via_link') ?>
        </a>
        <a href="<?= base_url('user/' . $invType . '/print/' . $invNum) ?>?download=1" class="btn btn-blue" data-adomin-download>
            <i class="fa fa-download" aria-hidden="true"></i>
            <?= lang('adomin_download_pdf') ?>
        </a>
    <?php } ?>
</div> 
<div class="view-container">
    <div class="pageDelivery hidden"></div>
    <div class="invoice-box">
        <?php include $templateFile; ?>
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

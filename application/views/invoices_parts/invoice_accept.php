<div class="container">
    <div class="inv-accept"> 
        <a href="<?= base_url() ?>" class="logo">
            <img src="<?= adomin_logo_url() ?>" alt="<?= html_escape(adomin_conf('brand', 'app_name')) ?>">
        </a>
        <hr>
        <div>
            <?php
            $typeTranslate = '';
            if ($invoice['inv_type'] == 'tax_inv') {
                $typeTranslate = $invoice['translation']['invoice'];
            }
            if ($invoice['inv_type'] == 'prof') {
                $typeTranslate = $invoice['translation']['pro_forma'];
            }
            if ($invoice['inv_type'] == 'debit') {
                $typeTranslate = $invoice['translation']['debit_note'];
            }
            if ($invoice['inv_type'] == 'credit') {
                $typeTranslate = $invoice['translation']['credit_note'];
            }
            ?>
            <h1><?= $invoice['translation']['receive_inv'] . ' ' . $typeTranslate . ' ' . $invoice['translation']['receive_inv_from'] ?> <?= $invoice['firm']['name'] ?></h1>
        </div>
        <div class="view-container">
            <div class="pageDelivery hidden"></div>
            <div class="invoice-box">
                <?php include $templateFile; ?>
            </div>
        </div>
        <?php /*
            The accept/refuse form that used to sit here is gone: it wrote the
            invoice's status from an anonymous POST. The decision belongs to the
            business, so the buttons live on the share page - as a request, for
            someone with an account to confirm. Keeping a second, weaker path to
            the same column would have been the hole in the whole idea.
        */ ?>
        <?php if (!empty($pdfUrl)) { ?>
            <p class="adomin-doc-actions" style="margin-top:12px;">
                <a href="<?= html_escape($pdfUrl) ?>" class="btn btn-adomin" target="_blank" rel="noopener">
                    <i class="fa fa-file-pdf-o" aria-hidden="true"></i>
                    <?= lang('adomin_download_pdf') ?>
                </a>
                <a href="javascript:void(0);" class="btn btn-default" onclick="window.print();">
                    <i class="fa fa-print" aria-hidden="true"></i>
                    <?= lang('adomin_print') ?>
                </a>
            </p>
        <?php } ?>
        <?php if ($invoice['status'] == 'accepted' || $invoice['status'] == 'refused') { ?>
            <div class="status-label <?= $invoice['status'] == 'accepted' ? 'bg-success' : 'bg-danger' ?>">
                <?= lang('status_' . $invoice['status']) ?>
            </div>
        <?php } ?>
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
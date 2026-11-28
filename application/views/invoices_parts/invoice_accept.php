<div class="container">
    <div class="inv-accept"> 
        <a href="<?= base_url() ?>" class="logo">
            <img src="<?= adomin_logo_url() ?>" alt="<?= html_escape(adomin_conf('brand', 'app_name')) ?>">
        </a>
        <hr>
        <div>
            <?php
            /*
             * Read through isset(): the wording is a snapshot copied when the
             * document was issued, so a document older than a label - or one
             * whose translation row is missing - would otherwise raise a notice
             * on a page a client is looking at.
             */
            $tr = isset($invoice['translation']) && is_array($invoice['translation']) ? $invoice['translation'] : array();
            $typeTranslate = '';
            if ($invoice['inv_type'] == 'tax_inv') {
                $typeTranslate = isset($tr['invoice']) ? $tr['invoice'] : '';
            }
            if ($invoice['inv_type'] == 'prof') {
                $typeTranslate = isset($tr['pro_forma']) ? $tr['pro_forma'] : '';
            }
            if ($invoice['inv_type'] == 'debit') {
                $typeTranslate = isset($tr['debit_note']) ? $tr['debit_note'] : '';
            }
            if ($invoice['inv_type'] == 'credit') {
                $typeTranslate = isset($tr['credit_note']) ? $tr['credit_note'] : '';
            }
            ?>
            <?php
            /*
             * The number belongs in the heading.
             *
             * "Receive Invoice from BONITECH LTD" is true of every document that
             * business ever sent, so a client holding three of them cannot tell
             * which one this page is about - and the number is the thing they
             * quote back on the phone. It is printed by the template below, but
             * not before the reader has had to look for it.
             */
            ?>
            <h1>
                <?= (isset($tr['receive_inv']) ? $tr['receive_inv'] : '') . ' ' . $typeTranslate . ' ' . (isset($tr['receive_inv_from']) ? $tr['receive_inv_from'] : '') ?>
                <?= html_escape($invoice['firm']['name']) ?>
                <?php if (!empty($invoice['inv_number'])) { ?>
                    <span class="adomin-doc-number">№ <?= html_escape($invoice['inv_number']) ?></span>
                <?php } ?>
            </h1>
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
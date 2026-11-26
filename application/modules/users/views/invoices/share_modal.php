<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * "Send via link" sheet (Adomin Technologies).
 *
 * One modal per page. Any element with [data-adomin-share] opens it; the
 * invoice id and the endpoint live on the trigger, so the same markup works in
 * the invoices list and on the invoice preview. assets/public/js/adomin-app.js
 * asks the server for the public token, then fills the copy/WhatsApp/SMS/mail
 * actions. Nothing is editable through the link - see Home::sharedInvoice().
 */
?>
<div class="modal fade" id="adominShareModal" tabindex="-1" role="dialog" aria-labelledby="adominShareLabel">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="adominShareLabel">
                    <i class="fa fa-link" aria-hidden="true"></i>
                    <?= lang('adomin_send_via_link_title') ?>
                </h4>
            </div>
            <div class="modal-body adomin-share-body">
                <p class="adomin-share-state">
                    <span class="adomin-spinner" aria-hidden="true"></span>
                    <?= lang('adomin_generating_link') ?>…
                </p>
                <input type="text" class="adomin-share-url" id="adominShareUrl" readonly onclick="this.select();">
                <textarea class="form-control adomin-share-message" id="adominShareMessage" rows="3" placeholder="<?= lang('adomin_share_hint') ?>"></textarea>
                <div class="adomin-share-actions">
                    <button type="button" class="btn btn-adomin" data-share-action="copy">
                        <i class="fa fa-clipboard" aria-hidden="true"></i>
                        <?= lang('adomin_copy_link') ?>
                    </button>
                    <a href="javascript:void(0);" class="btn adomin-wa" target="_blank" rel="noopener" data-share-action="whatsapp">
                        <i class="fa fa-whatsapp" aria-hidden="true"></i>
                        <?= lang('adomin_share_whatsapp') ?>
                    </a>
                    <a href="javascript:void(0);" class="btn btn-default" data-share-action="sms">
                        <i class="fa fa-commenting-o" aria-hidden="true"></i>
                        <?= lang('adomin_share_sms') ?>
                    </a>
                    <a href="javascript:void(0);" class="btn btn-default adomin-mail" data-share-action="email">
                        <i class="fa fa-envelope-o" aria-hidden="true"></i>
                        <?= lang('adomin_share_email') ?>
                    </a>
                    <a href="javascript:void(0);" class="btn btn-default" data-share-action="open">
                        <i class="fa fa-external-link" aria-hidden="true"></i>
                        <?= lang('adomin_open_preview') ?>
                    </a>
                    <a href="javascript:void(0);" class="btn btn-default" target="_blank" rel="noopener" data-share-action="pdf">
                        <i class="fa fa-file-pdf-o" aria-hidden="true"></i>
                        <?= lang('adomin_download_pdf') ?>
                    </a>
                    <button type="button" class="btn btn-default" data-adomin-native-share>
                        <i class="fa fa-share-alt" aria-hidden="true"></i>
                        <?= lang('adomin_share_more') ?>
                    </button>
                </div>
                <div class="adomin-share-note">
                    <?= lang('adomin_share_note') ?>
                </div>
            </div>
            <div class="modal-footer">
                <a href="javascript:void(0);" class="btn btn-link adomin-danger-link pull-left" data-share-action="revoke" data-invoice-id="0">
                    <i class="fa fa-ban" aria-hidden="true"></i>
                    <?= lang('adomin_revoke_link') ?>
                </a>
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= lang('close') ?></button>
            </div>
        </div>
    </div>
</div>
<script>
    window.adominShareConfig = {
        // ":id" is replaced by assets/public/js/adomin-app.js
        endpoint: '<?= lang_url('user/invoice/sharelink/:id') ?>',
        revoke: '<?= lang_url('user/invoice/revoke-share/:id') ?>',
        acceptTemplate: '<?= base_url('accept/invoice/:token') ?>',
        csrfName: '<?= $this->security->get_csrf_token_name() ?>',
        csrfHash: '<?= $this->security->get_csrf_hash() ?>',
        labels: {
            copy: '<?= str_replace("'", "\\'", lang('adomin_copy_link')) ?>',
            copied: '<?= str_replace("'", "\\'", lang('adomin_copied')) ?>',
            busy: '<?= str_replace("'", "\\'", lang('adomin_generating_link')) ?>'
        }
    };
</script>

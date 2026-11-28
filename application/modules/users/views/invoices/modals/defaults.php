<!--
  Document defaults: currency and language.

  Two answers that every new document needs and that used to live in two
  different places. Small on purpose - it is two selects and a button, and it is
  opened while someone is part-way through creating an invoice, so it must not
  take over the screen.

  Values arrive from the endpoint rather than being printed here, so the dialog
  is one file on every page and cannot show a stale list.
-->
<div class="modal fade" id="modalDefaults" tabindex="-1" role="dialog" aria-labelledby="modalDefaultsTitle"
     data-url="<?= lang_url('user/settings/invoices/defaults') ?>">
    <div class="modal-dialog modal-sm adomin-defaults-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modalDefaultsTitle"><?= lang('adomin_defaults_title') ?></h4>
            </div>
            <div class="modal-body">
                <p class="adomin-defaults-lead"><?= lang('adomin_defaults_lead') ?></p>

                <div class="form-group">
                    <label for="adomin-default-currency"><?= lang('adomin_defaults_currency') ?></label>
                    <select id="adomin-default-currency" class="form-control"></select>
                </div>

                <div class="form-group">
                    <label for="adomin-default-language"><?= lang('adomin_defaults_language') ?></label>
                    <select id="adomin-default-language" class="form-control"></select>
                </div>

                <p class="adomin-defaults-state" id="adomin-defaults-state" role="status" aria-live="polite"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= lang('close') ?></button>
                <button type="button" class="btn btn-primary" id="adomin-defaults-save"><?= lang('adomin_defaults_save_both') ?></button>
            </div>
        </div>
    </div>
</div>

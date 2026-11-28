<div class="selected-page">
    <div class="inner">
        <h1> 
            <?= lang('settings_invoices') ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?= lang_url('user') ?>"><?= lang('home') ?></a></li>  
            <li><a href="<?= lang_url('user/settings') ?>"><?= lang('settings') ?></a></li> 
            <li class="active"><?= lang('settings_invoices') ?></li>
        </ol>
    </div> 
</div>
<?php
/*
 * The rename endpoint and its button labels. They travel as data attributes on
 * the container so the script that reads them does not have to know any PHP, and
 * so there is no inline <script> on this page - views/settings/* must not have
 * one.
 */
$__renameUrl = lang_url('user/settings/invoices/rename');
?>
<div class="settings-inner-page"
     data-adomin-rename-url="<?= html_escape($__renameUrl) ?>"
     data-msg-save="<?= html_escape(lang('save')) ?>"
     data-msg-cancel="<?= html_escape(lang('cancel')) ?>"
     data-msg-saving="<?= html_escape($this->lang->line('adomin_saving') ?: 'Saving...') ?>"
     data-msg-failed="<?= html_escape($this->lang->line('adomin_save_failed') ?: 'Could not save that.') ?>">
    <p class="adomin-settings-status" hidden role="status" aria-live="polite"></p>
    <div class="row">
        <div class="col-sm-6 col-md-4 col-settings">
            <!--
                The pair of answers every new document needs. The currency also
                has its own card below, with the full list and its CRUD; this is
                the quick way in, next to the language it is used with.
            -->
            <div class="adomin-defaults-card">
                <h4><?= lang('adomin_defaults_title') ?></h4>
                <p class="adomin-defaults-lead"><?= lang('adomin_defaults_lead') ?></p>
                <button type="button" class="av-btn av-btn-primary" data-adomin-defaults>
                    <i class="fa fa-sliders" aria-hidden="true"></i> <?= lang('adomin_defaults_open') ?>
                </button>
            </div>

            <h4><?= lang('default_currency') ?></h4>
            <p class="selected-new-default"><?= lang('selected_new_def') ?></p>
            <p class="not-selected-new-default"><?= lang('not_selected_new_def') ?></p>
            <?php foreach ($myFirms as $myFirm) { ?>
                <div class="firm-currency-row">
                    <?= $myFirm['name'] ?> - <?= $myFirm['default_currency'] == null ? lang('default_currency_null') : '' ?> 
                    <select class="selectpicker selectDefaultCurrency" title="<?= lang('select_def_currency') ?>" data-default-for="<?= $myFirm['id'] ?>" data-live-search="true">
                        <?php foreach ($currencies as $currency) { ?>
                            <option value="<?= $currency['value'] ?>" <?= $currency['value'] == $myFirm['default_currency'] ? 'selected' : '' ?>><?= $currency['name'] ?></option>
                        <?php } ?>
                    </select> 
                    <?php if ($myFirm['default_currency'] != null) { ?>
                        <a href="<?= lang_url('user/settings/invoices/delete/default/' . $myFirm['id']) ?>" class="confirm" data-my-message="<?= lang('confirm_del_def_currency') . $myFirm['name'] . '?' ?>">
                            <?= lang('clear_def_currency') ?>
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
        <div class="col-sm-6 col-md-4 col-settings">
            <h4><?= lang('my_currencies') ?></h4>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th><?= lang('currency_name') ?><sup><?= lang('curr_name_for_int_use') ?></sup></th>
                        <th colspan="2"><?= lang('currency_value') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($myCurrencies)) {
                        foreach ($myCurrencies as $myCurrency) {
                            ?>
                            <tr data-adomin-row>
                                <td data-field="name"><?= html_escape($myCurrency['name']) ?></td>
                                <td data-field="value"><?= html_escape($myCurrency['value']) ?></td>
                                <td class="text-right adomin-row-actions">
                                    <button type="button" class="btn btn-xs btn-default"
                                            data-adomin-edit
                                            data-kind="currency"
                                            data-id="<?= (int) $myCurrency['id'] ?>"
                                            data-name="<?= html_escape($myCurrency['name']) ?>"
                                            data-value="<?= html_escape($myCurrency['value']) ?>">
                                        <?= lang('edit') ?>
                                    </button>
                                    <a href="<?= base_url('user/settings/invoices/delete/currency/' . $myCurrency['id']) ?>" class="confirm btn btn-xs btn-default" data-my-message="<?= lang('conirm_del_my_currency') ?>">
                                        <?= lang('delete') ?>
                                    </a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="3"><?= lang('no_my_currencies_added') ?></td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <td colspan="3">
                            <form method="POST" action="" class="site-form" id="formAddCurrency">
                                <table>
                                    <tr>
                                        <td><input type="text" placeholder="<?= lang('currency_name') ?>" name="currencyName" class="form-control field c-name"></td>
                                        <td><input type="text" placeholder="<?= lang('currency_value') ?>" name="currencyValue" class="form-control field c-value"></td>
                                        <td>
                                            <a href="javascript:void(0);" onclick="addNewCurrency()" class="btn btn-default pull-right">
                                                <?= lang('add_new_currency') ?>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="col-sm-6 col-md-4 col-settings">
            <h4><?= lang('my_quantity_types') ?></h4>
            <table class="table table-bordered">
                <thead>
                    <tr> 
                        <th colspan="2"><?= lang('quantity_name') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($myQuantityTypes)) {
                        foreach ($myQuantityTypes as $myQuantityType) {
                            ?>
                            <tr data-adomin-row>
                                <td data-field="name"><?= html_escape($myQuantityType['name']) ?></td>
                                <td class="text-right adomin-row-actions">
                                    <button type="button" class="btn btn-xs btn-default"
                                            data-adomin-edit
                                            data-kind="quantitytype"
                                            data-id="<?= (int) $myQuantityType['id'] ?>"
                                            data-name="<?= html_escape($myQuantityType['name']) ?>">
                                        <?= lang('edit') ?>
                                    </button>
                                    <a href="<?= base_url('user/settings/invoices/delete/quantitytype/' . $myQuantityType['id']) ?>" class="confirm btn btn-xs btn-default" data-my-message="<?= lang('conirm_del_my_quantity_type') ?>">
                                        <?= lang('delete') ?>
                                    </a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="2"><?= lang('no_my_quantity_types') ?></td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <td colspan="2">
                            <form method="POST" action="" class="site-form" id="formAddQuantityType">
                                <table>
                                    <tr>
                                        <td><input type="text" name="quantityTypeName" class="form-control field"></td>
                                        <td>
                                            <a href="javascript:void(0);" onclick="addNewQuantityType()" class="btn btn-default pull-right">
                                                <?= lang('add_new_q_type') ?>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="col-sm-6 col-md-4 col-settings">
            <h4><?= lang('my_payment_methods') ?></h4>
            <table class="table table-bordered">
                <thead>
                    <tr> 
                        <th colspan="2"><?= lang('quantity_name') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($myPaymentMethods)) {
                        foreach ($myPaymentMethods as $myPaymentMethod) {
                            ?>
                            <tr data-adomin-row>
                                <td data-field="name"><?= html_escape($myPaymentMethod['name']) ?></td>
                                <td class="text-right adomin-row-actions">
                                    <button type="button" class="btn btn-xs btn-default"
                                            data-adomin-edit
                                            data-kind="paymentmethod"
                                            data-id="<?= (int) $myPaymentMethod['id'] ?>"
                                            data-name="<?= html_escape($myPaymentMethod['name']) ?>">
                                        <?= lang('edit') ?>
                                    </button>
                                    <a href="<?= base_url('user/settings/invoices/delete/paymentmethod/' . $myPaymentMethod['id']) ?>" class="confirm btn btn-xs btn-default" data-my-message="<?= lang('conirm_del_my_pay_method') ?>">
                                        <?= lang('delete') ?>
                                    </a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="2"><?= lang('no_my_pay_methods') ?></td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <td colspan="2">
                            <form method="POST" action="" class="site-form" id="formAddPaymentMethod">
                                <table>
                                    <tr>
                                        <td><input type="text" name="paymentMethodName" class="form-control field"></td>
                                        <td>
                                            <a href="javascript:void(0);" onclick="addNewPaymentMethod()" class="btn btn-default pull-right">
                                                <?= lang('add_new_p_method') ?>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="col-sm-6 col-md-4 col-settings">
            <h4><?= lang('my_no_vat_reasons') ?></h4>
            <table class="table table-bordered">
                <thead>
                    <tr> 
                        <th colspan="2"><?= lang('vat_reason') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($myNoVatReasons)) {
                        foreach ($myNoVatReasons as $myNoVatReason) {
                            ?>
                            <tr data-adomin-row>
                                <td data-field="name"><?= html_escape($myNoVatReason['reason']) ?></td>
                                <td class="text-right adomin-row-actions">
                                    <button type="button" class="btn btn-xs btn-default"
                                            data-adomin-edit
                                            data-kind="novatreason"
                                            data-id="<?= (int) $myNoVatReason['id'] ?>"
                                            data-name="<?= html_escape($myNoVatReason['reason']) ?>">
                                        <?= lang('edit') ?>
                                    </button>
                                    <a href="<?= base_url('user/settings/invoices/delete/novatreason/' . $myNoVatReason['id']) ?>" class="confirm btn btn-xs btn-default" data-my-message="<?= lang('confirm_del_no_vat_reason') ?>">
                                        <?= lang('delete') ?>
                                    </a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="2"><?= lang('no_my_no_vat_reasons') ?></td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <td colspan="2">
                            <form method="POST" action="" class="site-form" id="formAddNoVatReason">
                                <table>
                                    <tr>
                                        <td><input type="text" name="noVatReason" class="form-control field"></td>
                                        <td>
                                            <a href="javascript:void(0);" onclick="addNewNoVatReason()" class="btn btn-default pull-right">
                                                <?= lang('add_new_vat_reas') ?>
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="col-sm-6 col-md-4 col-settings">
            <h4><?= lang('how_do_you_round_values') ?></h4>
            <form method="POST" action="" class="site-form" id="formRoundTotals">
                <table>
                    <tr>
                        <td><input type="text" value="<?= $opt_invRoundTo ?>" name="opt_invRoundTo" class="form-control field optRoundTo"></td>
                        <td>
                            <a href="javascript:void(0);" onclick="updateRoundTotals()" class="btn btn-default pull-right">
                                <?= lang('update_round_totals') ?>
                            </a>
                        </td>
                    </tr>
                </table>
            </form>
        </div>    
        <div class="col-sm-6 col-md-4 col-settings">
            <h4><?= lang('stop_inv_calculator') ?></h4>
            <!--
                Saved as opt_invCalculator, where 1 means the calculator is
                offered and 0 means it is hidden - that is what newinvoice.php
                tests. The label is the negative ("stop..."), so the box is
                ticked when the stored value is 0. It used to be ticked when the
                value was 0 but save the opposite, so the box never came back
                the way it was left.
            -->
            <form method="POST" action="">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="opt_invCalculator" value="0" <?= ((int) $opt_invCalculator === 0) ? 'checked="checked"' : '' ?>>
                        <?= lang('stop_inv_calculator') ?>
                    </label>
                </div>
                <button class="btn btn-default" name="updateInvCalculator" value="1" type="submit">
                    <?= lang('save') ?>
                </button>
            </form>
        </div>
        <div class="col-sm-6 col-md-4 col-settings" id="template">
            <h4>Invoice template</h4>
            <p class="av-hint">The design, the blocks of the document, your logo and the message - all on one canvas.</p>
            <a class="av-btn av-btn-primary" href="<?= lang_url('user/settings/invoices/template') ?>">
                <i class="fa fa-pencil" aria-hidden="true"></i> Open template canvas
            </a>
        </div>

        <?php
        /*
         * The status list. Five keys are fixed - they are column values that
         * decide where an invoice appears - but the words beside them are the
         * business's own. Renaming "Refused" to "Query raised" changes nothing
         * downstream because only the label moves.
         */
        ?>
        <div class="col-sm-6 col-md-4 col-settings" id="invoice-statuses">
            <h4><?= lang('adomin_status_label') ?></h4>
            <p class="av-hint"><?= lang('adomin_status_labels_help') ?></p>
            <form method="POST" action="" class="site-form" id="formInvoiceStatusLabels">
                <?php foreach ($statusLabels as $statusKey => $statusLabel) { ?>
                    <div class="form-group">
                        <label class="adomin-status-key" for="status-label-<?= html_escape($statusKey) ?>">
                            <code><?= html_escape($statusKey) ?></code>
                        </label>
                        <input type="text"
                               class="form-control field"
                               id="status-label-<?= html_escape($statusKey) ?>"
                               name="statusLabels[<?= html_escape($statusKey) ?>]"
                               value="<?= html_escape($statusLabel) ?>"
                               maxlength="60">
                    </div>
                <?php } ?>
                <button type="submit" name="saveInvStatusLabels" value="1" class="btn btn-default">
                    <?= lang('save') ?>
                </button>
            </form>
        </div>
    </div>
</div>

<script src="<?= adomin_asset('assets/plugins/jquery.eqheight.js') ?>"></script>
<script src="<?= adomin_asset('assets/users/js/settings-invoices-edit.js') ?>"></script>
<script src="<?= adomin_asset('assets/users/js/settings-invoices.js') ?>"></script>
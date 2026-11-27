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
<div class="settings-inner-page">
    <div class="row">
        <div class="col-sm-6 col-md-4 col-settings">
            <h4><?= lang('default_currency') ?></h4>
            <p class="selected-new-default"><?= lang('selected_new_def') ?></p>
            <p class="not-selected-new-default"><?= lang('not_selected_new_def') ?></p>
            <?php foreach ($myFirms as $myFirm) { ?>
                <div style="margin-bottom: 10px;">
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
                            <tr>
                                <td><?= $myCurrency['name'] ?></td>
                                <td><?= $myCurrency['value'] ?></td>
                                <td class="text-right">
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
                            <tr>
                                <td><?= $myQuantityType['name'] ?></td>
                                <td class="text-right">
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
                            <tr>
                                <td><?= $myPaymentMethod['name'] ?></td>
                                <td class="text-right">
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
                            <tr>
                                <td><?= $myNoVatReason['reason'] ?></td>
                                <td class="text-right">
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
            <form method="POST" action="">
                <div class="checkbox">
                    <label><input type="checkbox" name="opt_invCalculator" <?= $opt_invCalculator == 0 ? 'checked="checked"' : '' ?> value=""><?= lang('stop_inv_calculator') ?></label>
                </div> 
                <button class="btn btn-default" name="updateInvCalculator" value="" type="submit">
                    <?= lang('save') ?>
                </button> 
            </form>
        </div>
        <div class="col-sm-6 col-md-4 col-settings" id="template">
            <h4>Template</h4>
            <form method="POST" action="">
                <div class="av-tpl-pick">
                    <?php
                    $templates = array(
                        'creative'   => array('Creative', 'Accent bar, firm block left, roomy spacing.'),
                        'toner-save' => array('Toner-save', 'No fills or heavy rules. Prints clean and cheap.'),
                    );
                    foreach ($templates as $key => $meta) {
                        ?>
                        <label class="av-tpl-opt<?= $opt_invTemplate == $key ? ' is-on' : '' ?>">
                            <input type="radio" name="invTempl" value="<?= $key ?>" <?= $opt_invTemplate == $key ? 'checked' : '' ?>>
                            <span class="av-tpl-name"><?= html_escape($meta[0]) ?></span>
                            <span class="av-tpl-sub"><?= html_escape($meta[1]) ?></span>
                        </label>
                    <?php } ?>
                </div>
                <button class="btn btn-primary" name="updateInvTemplate" value="" type="submit">Save</button>
            </form>
        </div>
    </div>
</div>

<?php
/*
 * Invoice layout.
 *
 * Design and placement are separate: the pane above chooses the design, this
 * one places things within it. Every control posts and previews immediately, so
 * the effect of a choice is visible before it is saved.
 */
$lay = adomin_invoice_layout();
$layKeys = array(
    'opt_invLogoPos' => array('Logo position', 'logoPos', array('left' => 'Left', 'center' => 'Centre', 'right' => 'Right', 'hide' => 'Hidden')),
    'opt_invAddressPos' => array('Company details', 'addressPos', array('below' => 'Under the logo', 'side' => 'Beside the logo')),
    'opt_invCurrencyPos' => array('Currency shown', 'currencyPos', array('totals' => 'In the totals', 'header' => 'In the header')),
    'opt_invAccent' => array('Accent colour', 'accent', array('blue' => 'Blue', 'slate' => 'Slate', 'green' => 'Green', 'mono' => 'Mono')),
);
$layToggles = array(
    'opt_invShowLogo' => array('Show logo', 'showLogo'),
    'opt_invShowAddress' => array('Show address', 'showAddress'),
    'opt_invShowContacts' => array('Show contact person', 'showContacts'),
    'opt_invShowFirmVat' => array('Show VAT number', 'showFirmVat'),
    'opt_invShowRemarks' => array('Show remarks', 'showRemarks'),
);
?>
<div class="row" id="layout">
    <div class="col-sm-12">
        <h4>Layout</h4>
        <form method="POST" action="" id="av-layout-form" class="av-layout">
            <?php /* the design is saved with the layout: one pane, one Save */ ?>
            <input type="hidden" name="opt_invTemplate" id="av-layout-template" value="<?= html_escape($opt_invTemplate) ?>">
            <div class="col-md-5 av-layout-fields">
                <?php foreach ($layKeys as $name => $meta) { ?>
                    <label for="<?= $name ?>"><?= html_escape($meta[0]) ?></label>
                    <select class="form-control" name="<?= $name ?>" id="<?= $name ?>" data-av-preview>
                        <?php foreach ($meta[2] as $value => $label) { ?>
                            <option value="<?= $value ?>" <?= $lay[$meta[1]] === $value ? 'selected' : '' ?>><?= html_escape($label) ?></option>
                        <?php } ?>
                    </select>
                <?php } ?>

                <div class="av-toggles">
                    <?php foreach ($layToggles as $name => $meta) { ?>
                        <label class="av-toggle">
                            <input type="hidden" name="<?= $name ?>" value="0">
                            <input type="checkbox" name="<?= $name ?>" value="1" data-av-preview
                                <?= $lay[$meta[1]] === '1' ? 'checked' : '' ?>>
                            <span><?= html_escape($meta[0]) ?></span>
                        </label>
                    <?php } ?>
                </div>

                <label for="opt_invFooterNote">Message on the invoice</label>
                <textarea class="form-control" name="opt_invFooterNote" id="opt_invFooterNote"
                          rows="3" maxlength="300" data-av-preview
                          placeholder="e.g. Payment within 14 days. Thank you for your business."><?= html_escape($lay['footerNote']) ?></textarea>
                <p class="av-hint">300 characters. Printed under the totals.</p>

                <button class="btn btn-primary" name="saveInvLayout" value="1" type="submit">Save layout</button>
                <span class="av-save-state" id="av-save-state"></span>
            </div>

            <div class="col-md-7 av-layout-preview">
                <div class="av-preview-head">
                    <span>Preview</span>
                    <span class="av-preview-hint">Updates as you change a setting</span>
                </div>
                <div class="av-preview-frame" id="av-preview-frame">
                    <noscript><p class="av-hint">Saving still applies the layout; the preview needs JavaScript.</p></noscript>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.av-layout { display:flex; flex-wrap:wrap; gap:22px; }
.av-layout > div { float:none; }
.av-layout-fields { flex:0 0 340px; max-width:100%; }
.av-layout-fields label { display:block; margin:12px 0 5px; font-size:12.5px; font-weight:700; color:#384250; }
.av-layout-fields label:first-child { margin-top:0; }
.av-layout-fields .form-control { width:100%; }
.av-layout-fields .btn { margin-top:16px; }
.av-hint { margin:6px 0 0; font-size:11.5px; color:#5b6570; }
.av-toggles { margin:14px 0 4px; border-top:1px solid #e3e7ee; padding-top:12px; }
.av-toggle { display:flex; align-items:center; gap:8px; margin:0 0 8px; font-size:13px;
            font-weight:600; color:#384250; cursor:pointer; }
.av-toggle input { width:16px; height:16px; margin:0; accent-color:#0b6bcb; cursor:pointer; }
.av-save-state { margin-left:10px; font-size:12.5px; font-weight:600; color:#157347; }

.av-layout-preview { flex:1 1 420px; min-width:0; }
.av-preview-head { display:flex; align-items:baseline; justify-content:space-between; gap:12px;
                   font-size:12.5px; font-weight:700; color:#384250; margin-bottom:7px; }
.av-preview-hint { font-weight:400; color:#5b6570; font-size:11.5px; }
.av-preview-frame { background:#fff; border:1px solid #e3e7ee; border-radius:10px; padding:16px;
                    min-height:260px; overflow:auto; box-shadow:0 1px 2px rgba(15,20,25,.05); }
.av-preview-frame.is-loading { opacity:.55; }

.av-tpl-pick { display:flex; flex-direction:column; gap:8px; margin-bottom:14px; }
.av-tpl-opt { display:block; padding:11px 13px; border:1px solid #e3e7ee; border-radius:9px;
              cursor:pointer; transition:border-color .15s, background .15s; }
.av-tpl-opt:hover { border-color:#cfe0f3; background:#f7fbff; }
.av-tpl-opt.is-on { border-color:#0b6bcb; background:#f2f8ff; }
.av-tpl-opt input { display:none; }
.av-tpl-name { display:block; font-size:13.5px; font-weight:700; color:#111418; }
.av-tpl-sub { display:block; font-size:12px; color:#5b6570; margin-top:2px; }

@media (max-width:991px) { .av-layout-fields, .av-layout-preview { flex:1 1 100%; } }
</style>

<script>
(function () {
    var form = document.getElementById('av-layout-form');
    var frame = document.getElementById('av-preview-frame');
    var state = document.getElementById('av-save-state');
    if (!form || !frame) { return; }

    var timer = null;

    function refresh() {
        if (window.jQuery) {
            var data = jQuery(form).serialize();
            frame.classList.add('is-loading');
            jQuery.post('<?= lang_url('user/settings/invoices/preview') ?>', data, function (html) {
                frame.innerHTML = html;
                frame.classList.remove('is-loading');
            }).fail(function () {
                frame.classList.remove('is-loading');
                frame.innerHTML = '<p class="av-hint">The preview could not load. Your settings can still be saved.</p>';
            });
            return;
        }
        // no jQuery: same request, with the CSRF token the footer put on the page
        var meta = document.querySelector('meta[name="csrf-token"]');
        var csrf = meta ? meta.getAttribute('content') : '';
        var csrfName = 'csrf_test_name';
        var params = new URLSearchParams(new FormData(form));
        if (csrf !== '' && !params.has(csrfName)) { params.set(csrfName, csrf); }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '<?= lang_url('user/settings/invoices/preview') ?>', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            if (xhr.status === 200) { frame.innerHTML = xhr.responseText; }
            frame.classList.remove('is-loading');
        };
        xhr.onerror = function () {
            frame.classList.remove('is-loading');
            frame.innerHTML = '<p class="av-hint">The preview could not load. Your settings can still be saved.</p>';
        };
        xhr.send(params.toString());
    }

    function debounced() {
        clearTimeout(timer);
        timer = setTimeout(refresh, 180);
    }

    var fields = form.querySelectorAll('[data-av-preview]');
    for (var i = 0; i < fields.length; i++) {
        fields[i].addEventListener('change', refresh);
        if (fields[i].tagName === 'TEXTAREA') { fields[i].addEventListener('input', debounced); }
    }

    /*
     * The design radios sit in their own form (they have their own Save button
     * for a plain post). They also feed the layout form's hidden field, so the
     * preview reflects a design the moment it is clicked, and the layout Save
     * stores the whole pane rather than half of it.
     */
    var hidden = document.getElementById('av-layout-template');
    var tpl = document.querySelectorAll('input[name="invTempl"]');
    for (var j = 0; j < tpl.length; j++) {
        tpl[j].addEventListener('change', function () {
            if (hidden) { hidden.value = this.value; }
            var opts = document.querySelectorAll('.av-tpl-opt');
            for (var k = 0; k < opts.length; k++) {
                opts[k].classList.toggle('is-on', opts[k].contains(this));
            }
            refresh();
        });
    }

    form.addEventListener('submit', function (e) {
        var btn = form.querySelector('button[name="saveInvLayout"]');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Saving…';
        }
        state.textContent = '';
        // let the browser finish the post even if the frame is mid-refresh
        clearTimeout(timer);
    });

    refresh();
})();
</script>
<script src="<?= base_url('assets/plugins/jquery.eqheight.js') ?>"></script>
<script type="text/javascript">
                                $(document).ready(function () {
                                    $(".settings-inner-page").eqHeight(".col-settings");
                                });
</script>
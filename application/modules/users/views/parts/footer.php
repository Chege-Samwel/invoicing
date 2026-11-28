<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>
<?php if (!empty($myFirms)) { ?>
    </div>
    </div>
    </div> 
    </div>
    <?php
}
?>
<?php
/*
 * The modal dialogs that general.js binds to.
 *
 * modalSelector, addQuantityType and addPaymentMethod were each included by one
 * page only, while general.js binds their triggers on every page. Anywhere else,
 * clicking "choose from list" or "add new" ran `.modal('show')` against an
 * element that was not in the document: Bootstrap still added its backdrop and
 * locked scrolling, so the screen dimmed and then nothing happened - the
 * dialog it was trying to open did not exist.
 *
 * They are emitted here so every page has them. `$__modalsAlreadyOnPage` is set
 * by the views that include their own, so those pages do not get a second copy
 * with duplicate ids.
 */
if (empty($__modalsAlreadyOnPage)) {
    $__modalDir = __DIR__ . '/../../views/invoices/modals/';
    foreach (array('selector', 'add_quantity_type', 'add_payment_method', 'defaults') as $__modal) {
        $__modalFile = $__modalDir . $__modal . '.php';
        if (is_file($__modalFile)) {
            include $__modalFile;
        }
    }
}
?>
<footer class="adomin-footer">
    <p class="adomin-footer-brand">
        <?= adomin_foot() ?>
    </p>
    <p class="adomin-footer-meta">
        © <?= (int) adomin_conf('brand', 'copyright_year') ?> <?= html_escape(adomin_conf('brand', 'name')) ?> ·
        <?= html_escape(adomin_conf('brand', 'tagline')) ?>
        <?php $supportEmail = adomin_conf('brand', 'support_email'); ?>
        <?php if ($supportEmail) { ?>
            · <a href="mailto:<?= html_escape($supportEmail) ?>"><?= html_escape($supportEmail) ?></a>
        <?php } ?>
        <?php $supportPhone = adomin_conf('brand', 'support_phone'); ?>
        <?php if ($supportPhone) { ?>
            · <a href="tel:<?= html_escape(preg_replace('/[^0-9+]/', '', $supportPhone)) ?>"><?= html_escape($supportPhone) ?></a>
        <?php } ?>
    </p>
</footer>
</div> 
</div> 
<?php
if ($this->session->flashdata('resultAction')) {
    geterror($this->session->flashdata('resultAction'));
}
?>
<script src="<?= adomin_asset('assets/bootstrap-select-1.12.2/dist/js/bootstrap-select.min.js') ?>"></script>
<script src="<?= adomin_asset('assets/bootstrap-datepicker-1.6.4-dist/js/bootstrap-datepicker.min.js') ?>"></script>
<script src="<?= adomin_asset('assets/plugins/placeholders.min.js') ?>"></script>
<script src="<?= adomin_asset('assets/bootstrap/js/bootstrap.min.js') ?>"></script>  
<script src="<?= adomin_asset('assets/plugins/bootbox.min.js') ?>"></script>
<script>
    var urls = {
        changeDefaultCurrency: "<?= lang_url('user/defaultcurrency') ?>",
        addNewQuantityType: "<?= lang_url('user/addnewquantitytype') ?>",
        addNewPaymentMethod: "<?= lang_url('user/addnewpaymentmethod') ?>",
        modalSelector: "<?= lang_url('user/modalselector') ?>",
        itemSuggest: "<?= lang_url('user/itemsuggest') ?>",
        changeInvoiceStatus: "<?= lang_url('user/changeinvoicestatus') ?>",
        topNavSearch: "<?= lang_url('user/findresults') ?>"
    };
</script>
<script src="<?= adomin_asset('assets/users/js/general.js') ?>"></script>
<script src="<?= adomin_asset('assets/users/js/adomin-defaults.js') ?>"></script>
<script>
    (function() {
        var csrfName = '<?= $this->security->get_csrf_token_name() ?>';
        var csrfHash = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        function injectCsrf() {
            var forms = document.querySelectorAll('form[method="post"], form[method="POST"]');
            for (var i = 0; i < forms.length; i++) {
                if (!forms[i].querySelector('input[name="' + csrfName + '"]')) {
                    var input = document.createElement('input');
                    input.type  = 'hidden';
                    input.name  = csrfName;
                    input.value = csrfHash;
                    forms[i].appendChild(input);
                }
            }
        }

        // DOM is already ready here (footer script) — call directly
        injectCsrf();

        // Fallback for dynamically added forms submitted via submit event
        document.addEventListener('submit', function(e) {
            var form = e.target;
            if (form.method && form.method.toLowerCase() === 'post' && !form.querySelector('input[name="' + csrfName + '"]')) {
                var input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = csrfName;
                input.value = csrfHash;
                form.appendChild(input);
            }
        }, true);

        // Patch jQuery $.ajax for AJAX POST requests
        if (typeof $ !== 'undefined') {
            $.ajaxSetup({
                beforeSend: function(xhr, settings) {
                    if (settings.type && settings.type.toUpperCase() === 'POST') {
                        if (typeof settings.data === 'string') {
                            settings.data += '&' + csrfName + '=' + csrfHash;
                        } else if (settings.data instanceof FormData) {
                            settings.data.append(csrfName, csrfHash);
                        } else {
                            settings.data = settings.data || {};
                            settings.data[csrfName] = csrfHash;
                        }
                    }
                }
            });
        }
    })();
</script>
<?= adomin_app_js() ?>
<?php if ((int) $this->session->userdata('user_id') > 0) : ?>
<div id="adomin-install" class="adomin-install" hidden
     data-user="<?= (int) $this->session->userdata('user_id') ?>"
     data-text="<?= html_escape($this->lang->line('adomin_install_text') ?: 'Add this app to your desktop for one-click access.') ?>"
     data-text-ios="<?= html_escape($this->lang->line('adomin_install_ios') ?: 'Add to Home Screen: tap Share, then add it to your home screen.') ?>"
     data-add="<?= html_escape($this->lang->line('adomin_install_add') ?: 'Add') ?>"
     data-later="<?= html_escape($this->lang->line('adomin_install_later') ?: 'Not now') ?>"
     role="complementary"
     aria-label="<?= html_escape($this->lang->line('adomin_install_label') ?: 'Install this app') ?>"></div>
<script src="<?= html_escape(adomin_asset('assets/public/js/adomin-install.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
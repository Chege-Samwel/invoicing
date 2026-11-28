/*
 * okay we lets ready :)
 * @description Users JavaScript
 */
var pattern_sums = /^-?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)$/;
var pattern_email = /\S+@\S+\.\S+/;
var border_color_fields = '#e9e9e9';
var border_color_wrong = 'red';
var alertBoxHtml = '<div class="alert-errors">%output%<a href="javascript:void(0);" class="close-alert" onclic="cs"><i class="fa fa-times" aria-hidden="true"></i></a></div>';
var chooseItemIndex;
var pixelsPdfDelivery = 1300; // pixels that wkhtmltopdf deliver segments of pdf

$(document).ready(function () {
    /*
     * Confirm dialog
     */
    $('.confirm').click(function (e) {
        e.preventDefault();
        var lHref = $(this).attr('href');
        var myMessage = $(this).data('my-message');
        bootbox.confirm({
            message: myMessage,
            buttons: {
                confirm: {
                    label: 'Yes',
                    className: 'btn-success'
                },
                cancel: {
                    label: 'No',
                    className: 'btn-danger'
                }
            },
            callback: function (result) {
                if (result) {
                    window.location.href = lHref;
                }
            }
        });
    });
    /*
     * Alerts fade out after 5sec.
     */
    if ($('.alert-errors').length) {
        $('.alert-errors').delay(4000).fadeOut(1500);
    }
    /*
     * DatePicker
     */
    $('.datepicker').datepicker({
        format: 'dd.mm.yyyy'
    });
    /*
     * Sortable items in Create Invoice page
     */
    $('.body-items').sortable({
        handle: '.move-me'
    });
    /*
     * NavBar Border Bottom check
     */
    if ($(window).scrollTop() != 0) {
        $(".navbar-user").addClass("navbar-scroll-border");
    } else {
        $(".navbar-user").removeClass("navbar-scroll-border");
    }
});
/*
 * NavBar Border Bottom check
 */
$(window).scroll(function () {
    if ($(this).scrollTop() != 0) {
        $(".navbar-user").addClass("navbar-scroll-border");
    } else {
        $(".navbar-user").removeClass("navbar-scroll-border");
    }
});
/*
 * Alerts close
 */
$(document).on("click", ".close-alert", function () {
    $('.alert-errors').hide();
});
/*
 * Checkbox maturity date in craete invoice page
 */
$('#maturity-date').change(function () {
    if ($(this).is(":checked")) {
        $('.maturity-date').show();
    } else {
        $('.maturity-date').hide();
    }
});
/*
 * Show Translations
 * When edit invoice we hide translations
 * if user want to show him.. when save the invoice edit
 * we will get choosed translation :)
 */
$('#show-translations').change(function () {
    if ($(this).is(":checked")) {
        $('.choose-translation').show();
    } else {
        $('.choose-translation').hide();
    }
});
/*
 * Show Translations
 * When edit invoice we hide translations
 * if user want to show him.. when save the invoice edit
 * we will get choosed translation :)
 */
$('#show-translations-firms').change(function () {
    if ($(this).is(":checked")) {
        $('.choose-firm-translation').show();
    } else {
        $('.choose-firm-translation').hide();
    }
});
/*
 * Change company to individaul and back
 */
$('#individual-client').change(function () {
    if ($(this).is(":checked")) {
        $('.client-company').hide();
        $('.client-individial').show();
    } else {
        $('.client-company').show();
        $('.client-individial').hide();
        vatRegisteredFieldVisibility();
    }
});
/*
 * Show client vat field for vat registration
 */
$('#client-vat-registered').change(function () {
    vatRegisteredFieldVisibility();
});
/*
 * Checkbox no vat in craete invoice page
 */
$('#no-vat').change(function () {
    if ($(this).is(":checked")) {
        $('.no-vat-container, .the-vat').hide();
        $('.no-vat-field').show();
    } else {
        $('.no-vat-container, .the-vat').show();
        $('.no-vat-field').hide();
    }
});
/*
 * Add Item in create invoice page
 */
var numItemsDefault = 1;
$('.add-new-item').click(function () {
    var obj = $('.body-items tr:first').clone(true).appendTo($('.body-items').first());
    obj.find('.field').val('').css("border-color", border_color_fields);
    obj.find('.quantity-field').val('1.00').css("border-color", border_color_fields);
    obj.find('.price-field').val('0.00').css("border-color", border_color_fields);
    obj.find('.item-total-price').text('');
    obj.find('[name="item_from_list[]"]').val('0'); // clear some indicators
    obj.find('[name="is_item_update[]"]').val('0'); // clear some indicators
    var selectedOption = $('#selectCurrencyNewInv').find(":selected").val();
    if (!selectedOption) {
        selectedOption = $('.currency-text').first().text();
    }
    obj.find('.item-total-price').append('<span class="item-total">0.00</span> <input type="hidden" class="item-total field" value="0.00" name="items_totals[]"> <span class="currency-text">' + selectedOption + '</span>');
    $('.body-items .actions').css('display', 'inline-block');
    numItemsDefault = numItemsDefault + 1;
    obj.find('.quantity-type select').attr('data-my-id', numItemsDefault);
    // clone(true) copies the selected option too. Without this the new row opens
    // on whatever the row above was on - including the "create new quantity"
    // entry, which is not a real unit and leaves the row in an odd state.
    var $qt = obj.find('.quantity-type select');
    $qt.find('option').prop('selected', false);
    $qt.find('option').first().prop('selected', true);
    $qt.val($qt.find('option').first().val());
    /*
     * The cloned row carried its parent's bootstrap-select wrapper, which was
     * drawn from the parent's value and holds a copy of the parent's ids. It
     * cannot simply be re-initialised: the plugin sees an already-built wrapper
     * and returns early, leaving the stale copy in place. The wrapper is thrown
     * away here and rebuilt from the <select>, which now holds the reset value.
     */
    var $clonedPicker = $qt.closest('.bootstrap-select');
    if ($clonedPicker.length) {
        $clonedPicker.before($qt);
        $clonedPicker.remove();
    }
    $qt.removeClass('av-sp-ready').removeAttr('id');
    /*
     * The plugin keeps its state on the element, so a control that was once
     * initialised and then unwrapped is still marked as built. Removing the
     * data lets it build from scratch instead of returning early.
     */
    $qt.removeData('selectpicker');
    if (typeof avInitSelectPickers === 'function') {
        avInitSelectPickers(obj);
    }
    if (createDocument.calculatorStatus == 0) {
        $('input.item-total, [name="invoice_amount"], [name="tax_base"], [name="vat_sum"], [name="final_total"]').attr('type', 'text');

    }
});
/*
 * Remove Item in create invoice page
 */
$('.delete-item').on("click", function () {
    $(this).closest('tr').remove();
    if ($('.body-items tr').length < 2) {
        $('.body-items .actions').hide();
    }
});
// Tap targets move the entire row, preserving its fields and values.
$('#setInvoiceForm .body-items').on('click', '.item-move-up, .item-move-down', function () {
    var row = $(this).closest('tr');
    if ($(this).hasClass('item-move-up') && row.prev().length) {
        row.insertBefore(row.prev());
    } else if ($(this).hasClass('item-move-down') && row.next().length) {
        row.insertAfter(row.next());
    }
    $(this).trigger('focus');
});
$(function () {
    if ($('#setInvoiceForm .body-items tr').length > 1) {
        $('#setInvoiceForm .body-items .actions').css('display', 'inline-block');
    }
});
/*
 * Select Currency for new invoice
 * On change we change curreny texts in class currency-text tags
 */
$('#selectCurrencyNewInv').change(function () {
    $('.currency-text').text($(this).val());
    avRefreshSelectPickers();
});
/*
 * Select default currency for company
 */
$('.selectDefaultCurrency').change(function () {
    var forId = $(this).data('default-for');
    var newDefault = $(this).val();
    $.post(urls.changeDefaultCurrency, {forId: forId, newDefault: newDefault}, function (result) {
        if (result == '1') {
            $('.selected-new-default').fadeIn(500).delay(1000).fadeOut(500);
        } else {
            $('.not-selected-new-default').fadeIn(500).delay(1000).fadeOut(500);
        }
    });
});
/*
 * Quantity Type in create invoice page
 * When click create new show dialog box
 * Save new added values
 * Full manage logic is here
 */
var myNewQuantityType = false;
var whoIAm = 0;
/*
 * Delegated from the document, not bound to the selects that exist at load.
 * Invoice rows are cloned into the table as the user adds items, so a direct
 * binding only ever covered the first row and every row after it had a dead
 * dropdown - which is why the "add new" choices looked unresponsive.
 */
$(document).on('change', '.quantity-type select', function () {
    var selectedValue = $(this).val();
    whoIAm = $(this).attr('data-my-id');
    if (selectedValue == 'createNewQuantity') {
        if (!$('#addQuantityType').length) { return; }
        $('#addQuantityType').modal('show');
        $('.new-quantity-value').val('');
        $('.new-quantity-value').css("border-color", border_color_fields);
        myNewQuantityType = false;
    }
    if (selectedValue == '--') {
        $('[data-my-id="' + whoIAm + '"] option:first').prop("selected", "selected");
    }
});
$(document).on('click', '.add-my-new-quantity-type', function () {
    var newVal = jQuery.trim($('.new-quantity-value').val());
    if (newVal != '') {
        myNewQuantityType = true;
        $('.quantity-type select').prepend('<option value="' + newVal + '">' + newVal + '</option>');
        $('[data-my-id="' + whoIAm + '"] option:first').prop("selected", "selected");
        $('#addQuantityType').modal('hide');
        $.post(urls.addNewQuantityType, {newVal: newVal}, function (result) {});
    } else {
        $('.new-quantity-value').css("border-color", border_color_wrong);
    }
});
$('#addQuantityType').on('hidden.bs.modal', function () {
    if (myNewQuantityType == false) {
        $('[data-my-id="' + whoIAm + '"] option:first').prop("selected", "selected");
    }
});
/*
 * Payment method - add new method
 */
$('select.payment-method').change(function () {
    var selectedValue = $(this).val();
    if (selectedValue == 'createNewMethod') {
        $('#addPaymentMethod').modal('show');
        $('.my-new-pay-method').val('');
        $('.my-new-pay-method').css("border-color", border_color_fields);
    }
    if (selectedValue == '--') {
        $('option', this).first().prop("selected", "selected");
    }
});
$('.add-my-new-pay-method').click(function () {
    var newVal = jQuery.trim($('.my-new-pay-method').val());
    if (newVal != '') {
        $('select.payment-method').prepend('<option value="' + newVal + '">' + newVal + '</option>');
        $('#addPaymentMethod').modal('hide');
        $.post(urls.addNewPaymentMethod, {newVal: newVal}, function (result) {});
    } else {
        $('.my-new-pay-method').css("border-color", border_color_wrong);
    }
});
$('#addPaymentMethod').on('hidden.bs.modal', function () {
    $('select.payment-method option:first').prop("selected", "selected");
    avRefreshSelectPickers();
});
/*
 * On change some form this html-s :)
 * Call the calculator
 */
$('.quantity-field, .price-field, .text-discount, .vat-field, #no-vat, #discount-value').change(function () {
    createInvoiceCalculator();
});
/*
 * Invoice type changer
 */
$('[name="inv_type"]').change(function () {
    var inv_type = $(this).val();
    if (inv_type == 'prof') {
        $('.inv-type-title').text(lang.proforma);
        $('.inv-type-num').text(lang.proforma);
        $('.credit-debit-option').hide();
    }
    if (inv_type == 'tax_inv') {
        $('.inv-type-title').text(lang.invoice);
        $('.inv-type-num').text(lang.invoice);
        $('.credit-debit-option').hide();
    }
    if (inv_type == 'debit') {
        $('.inv-type-title').text(lang.debit_note);
        $('.inv-type-num').text(lang.debit_note);
        $('.credit-debit-option').show();
    }
    if (inv_type == 'credit') {
        $('.inv-type-title').text(lang.credit_note);
        $('.inv-type-num').text(lang.credit_note);
        $('.credit-debit-option').show();
    }
});
/*
 * Item-name suggestions.
 *
 * Typing "Cem" in a new invoice should offer "Cement 50kg bag" from the
 * company's own item list - the one filled from the trade given at
 * registration. Two rules keep this from getting in the way:
 *
 *  - It only ever writes the *name* back into the field. The unit is copied
 *    because it is a property of the product, but the price is deliberately
 *    left alone: a suggestion is a shortcut for typing, not a quote, and a
 *    stored price that has moved since would otherwise be silently billed.
 *  - It debounces, cancels its own in-flight request and is bound to the
 *    document rather than to each row, so the "add item" clone gets it for
 *    free and no listener leaks when a row is removed.
 */
(function () {
    if (typeof jQuery === 'undefined' || !urls || !urls.itemSuggest) {
        return;
    }

    var $list = null;          // the one visible suggestion box
    var timer = null;
    var lastQuery = '';
    var xhr = null;

    function closeList() {
        if ($list) {
            $list.remove();
            $list = null;
        }
    }

    /*
     * Escapes anything that came back from the server. The names are stored
     * HTML-escaped and are decoded again by the endpoint, so they reach here as
     * text - which means this must return text too, or a product called
     * "<b>Bolt</b>" would arrive as markup.
     */
    function esc(text) {
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    function openList($field, rows) {
        closeList();
        if (!rows || !rows.length) {
            return;
        }

        $list = $('<ul class="av-item-suggest" role="listbox"></ul>');

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var $li = $('<li role="option"></li>');
            $li.attr('data-unit', row.quantity_type || '');
            $li.append('<span class="av-sg-name">' + esc(row.name) + '</span>');
            if (row.quantity_type) {
                $li.append('<span class="av-sg-unit">' + esc(row.quantity_type) + '</span>');
            }
            $list.append($li);
        }

        var offset = $field.offset();
        $list.css({
            left: offset.left + 'px',
            top: (offset.top + $field.outerHeight()) + 'px',
            width: Math.max($field.outerWidth(), 220) + 'px'
        });

        $('body').append($list);
    }

    function choose($li, $field) {
        var name = $li.find('.av-sg-name').text();
        var unit = $li.attr('data-unit') || '';

        $field.val(name);
        // the row's unit picker lives beside the price column
        var $row = $field.closest('tr');
        var $unit = $row.find('.quantity-type select');
        if (unit && $unit.length) {
            var $option = $unit.find('option').filter(function () {
                return String($(this).val()) === unit;
            });
            if ($option.length) {
                $unit.val(unit);
                /*
                 * bootstrap-select draws its own button over the <select>, so
                 * setting the value alone leaves the old unit on screen. Asking
                 * the plugin to refresh is the documented way to resync it.
                 */
                if ($unit.data('selectpicker') && typeof $unit.selectpicker === 'function') {
                    $unit.selectpicker('refresh');
                }
            }
        }

        closeList();
        $field.trigger('change');
        $row.find('.price-field').first().trigger('focus');
    }

    function fetch($field) {
        var term = $.trim($field.val());

        if (term.length < 2) {
            closeList();
            return;
        }
        if (term === lastQuery) {
            return;
        }
        lastQuery = term;

        if (xhr && xhr.readyState !== 4) {
            xhr.abort();
        }

        xhr = $.post(urls.itemSuggest, {term: term})
            .done(function (rows) {
                // the field may have moved on while the request was out
                if ($.trim($field.val()) !== term) {
                    return;
                }
                openList($field, rows);
            })
            .fail(function () {
                // a failed suggestion must never block typing; say nothing
                closeList();
            });
    }

    $(document)
        .on('keyup focus', '.field-item-name', function (e) {
            var $field = $(this);
            /*
             * Keyed off both `which` and `key`: older jQuery puts the code in
             * which, everything current also fills key, and a keyboard that
             * reports only one of them must still be able to close the list.
             */
            var key = e.which || 0;
            var named = e.key || '';

            if (key === 27 || named === 'Escape') {
                closeList();
                return;
            }
            if ((key === 13 || named === 'Enter') && $list) { // enter takes the highlighted item
                e.preventDefault();
                choose($list.find('li.is-on').first(), $field);
                return;
            }
            if ((key === 40 || named === 'ArrowDown') && $list) { // down
                e.preventDefault();
                var $items = $list.find('li');
                var $on = $items.filter('.is-on').removeClass('is-on');
                var next = $on.next();
                (next.length ? next : $items.first()).addClass('is-on');
                return;
            }
            if ((key === 38 || named === 'ArrowUp') && $list) { // up
                e.preventDefault();
                var $rows = $list.find('li');
                var $cur = $rows.filter('.is-on').removeClass('is-on');
                var prev = $cur.prev();
                (prev.length ? prev : $rows.last()).addClass('is-on');
                return;
            }

            clearTimeout(timer);
            timer = setTimeout(function () { fetch($field); }, 180);
        })
        .on('click', '.av-item-suggest li', function (e) {
            e.preventDefault();
            choose($(this), $(this).closest('.av-item-suggest').data('field'));
        })
        .on('mouseenter', '.av-item-suggest li', function () {
            $(this).siblings().removeClass('is-on');
            $(this).addClass('is-on');
        })
        .on('click', function (e) {
            // a click anywhere else closes the list
            if ($list && !$(e.target).closest('.av-item-suggest, .field-item-name').length) {
                closeList();
            }
        });

    /*
     * The click handler above needs to know which field the list belongs to.
     * Keeping that on the element is simpler than closing over it, and it
     * survives the list being rebuilt by a later keystroke.
     */
    var originalOpen = openList;
    openList = function ($field, rows) {
        originalOpen($field, rows);
        if ($list) {
            $list.data('field', $field);
            $list.find('li').first().addClass('is-on');
        }
    };
})();
/*
 * Modal Selector
 */
$('.choose').click(function () {
    /*
     * If the dialog is not in the document, Bootstrap still adds its backdrop
     * on .modal('show') and locks scrolling - the screen dims with nothing in
     * it and the only way out is a reload. That is what happened on every page
     * that did not include selector.php. The dialogs now come from the shared
     * footer, and this guard means a future page that omits them fails visibly
     * instead of hanging.
     */
    if (!$('#modalSelector').length) {
        showError('This list cannot open here. Reload the page and try again.');
        return;
    }
    var modalTitle = $(this).data('choose-title');
    $('#modalSelector .modal-title').text(modalTitle);
    var selectorType = $(this).data('selector-type');

    /*
     * If we click choose in table of items
     * save this index because we will need after to
     * add item_from_list[] value to 1
     */
    if ($(this).closest('td').parent()[0]) {
        chooseItemIndex = $(this).closest('td').parent()[0].sectionRowIndex;
    }

    /*
     * The dialog is opened when the list is here, not before it is asked for.
     *
     * This used to call modal('show') straight after firing the request, so the
     * page dimmed immediately and then waited. Any failure - a rejected token, a
     * timeout, a dropped connection - left the backdrop up over an empty dialog
     * with the page behind it unclickable, and the only way out was a reload.
     * Opening on arrival means a failure leaves the page exactly as it was.
     */
    var $body = $('#modalSelector .modal-body');
    $body.html('<p class="adomin-modal-loading">' + 'Loading…' + '</p>');

    $.post(urls.modalSelector, {selectType: selectorType})
        .done(function (htmlList) {
            $body.empty().append(htmlList);
            $('#modalSelector').modal('show');
        })
        .fail(function () {
            // leave the page usable; say what happened in the existing error UI
            $body.empty();
            showError('That list could not be loaded. Check your connection and try again.');
        });
});
/*
 * Nothing on this page may leave the screen dimmed or unclickable.
 *
 * This is the reported problem, and it has more than one cause, so it is fixed
 * at more than one level.
 *
 * Bootstrap 3 has no way to show a dialog without blocking the page. Even with
 * `backdrop: false` it adds `modal-open` to the body, which sets
 * `overflow: hidden` and stops the page scrolling, and its `.modal` layer sits
 * over the whole viewport at a high z-index and swallows every click that is not
 * on the dialog. So a dialog that fails to appear, or appears and then cannot
 * close, leaves a page that looks - or behaves - stuck, and the only way out is
 * a reload.
 *
 *   1. The framework's own defaults are changed: no backdrop, ever, and no
 *      scroll lock. Dialogs still dim nothing and the page behind stays live.
 *   2. Backdrops are removed the moment they appear rather than on a timer, so a
 *      dialog opened by a plugin nobody here controls is covered too.
 *   3. The .modal layer only covers the screen while it is actually showing a
 *      dialog; when it is hidden it stops swallowing clicks.
 *   4. A dialog whose content failed to load closes itself rather than sitting
 *      there empty.
 *
 * Every dialog in the app is small: a list to pick from, two fields, a confirm.
 * None of them needs to own the screen, and a dimmed backdrop is exactly the
 * heaviness that was being complained about.
 */
(function () {
    var BACKDROP = '.modal-backdrop';

    function anyDialogOpen() {
        var dialogs = document.querySelectorAll('.modal.in, .modal.show');
        for (var i = 0; i < dialogs.length; i++) {
            if (dialogs[i].getAttribute('aria-hidden') !== 'true') {
                return true;
            }
        }
        return false;
    }

    function stripBackdrops() {
        var backdrops = document.querySelectorAll(BACKDROP);
        for (var i = 0; i < backdrops.length; i++) {
            if (backdrops[i].parentNode) {
                backdrops[i].parentNode.removeChild(backdrops[i]);
            }
        }
        return backdrops.length > 0;
    }

    /*
     * Undo the scroll lock Bootstrap adds. Only when nothing is genuinely open:
     * with no backdrop there is no reason to freeze the page at all, and a page
     * that cannot scroll on a phone looks broken.
     */
    function unlockPage() {
        /*
         * Only write when there is something to write. The observer below watches
         * the body's class and style, so touching them unconditionally would make
         * every check trigger another change, which would trigger another check -
         * a loop that pegs the CPU and never ends. Guarding the write is what
         * makes the observer safe.
         */
        var changed = false;
        if (document.body.classList.contains('modal-open')) {
            document.body.classList.remove('modal-open');
            changed = true;
        }
        if (document.body.style.overflow) {
            document.body.style.removeProperty('overflow');
            changed = true;
        }
        if (document.body.style.paddingRight) {
            document.body.style.removeProperty('padding-right');
            changed = true;
        }
        return changed;
    }

    function settle() {
        stripBackdrops();
        unlockPage();
    }

    /*
     * Watch the page rather than every code path.
     *
     * Backdrops appear without an event being fired - a plugin calls
     * .modal('show'), a request finishes late, a second script shows something -
     * so the reliable signal is the DOM changing. This reacts to the change
     * instead of waiting for the next tick of a timer, which is what made the
     * previous version feel stuck for up to a second.
     */
    function watch() {
        if (typeof window.MutationObserver !== 'function') {
            return;
        }
        var observer = new window.MutationObserver(function () {
            /*
             * Nothing is touched while a dialog is legitimately open: a dialog
             * that is showing gets to keep its own state and its own money. The
             * moment none is open, any leftover is damage and is cleared.
             */
            if (!anyDialogOpen()) {
                settle();
            } else if (!document.querySelector(BACKDROP)) {
                // an open dialog still should not freeze the page behind it
                unlockPage();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'style', 'aria-hidden'] });
    }

    /*
     * A timer as well, because an observer that is somehow not running must not
     * be the only defence. Slower than the observer, so it is genuinely a
     * fallback rather than the mechanism.
     */
    window.setInterval(function () {
        if (!anyDialogOpen()) {
            settle();
        }
    }, 1500);

    document.addEventListener('DOMContentLoaded', function () {
        settle();
        watch();
    });
    if (document.readyState !== 'loading') {
        settle();
        watch();
    }

    document.addEventListener('load', settle);

    /*
     * Escape closes an open dialog; if there is no dialog to close but the page
     * is dimmed or frozen, it clears that instead. Either way the key does
     * something useful rather than nothing, which is what a stuck screen needs.
     */
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' && e.keyCode !== 27) {
            return;
        }
        var open = document.querySelectorAll('.modal.in, .modal.show');
        if (open.length && window.jQuery) {
            window.jQuery(open[open.length - 1]).modal('hide');
            window.setTimeout(settle, 400);
            return;
        }
        settle();
    });

    /*
     * Back navigation closes the dialog before it leaves the page.
     *
     * A dialog is not a page, but a phone's back gesture is the natural way out
     * of one, and a user who lands on a dimmed invoice form expects back to
     * undo that, not to abandon the invoice they were typing. When a dialog is
     * open, one history entry is pushed so back closes it; the listener removes
     * that entry again so back does not have to be pressed twice.
     */
    var pushed = false;

    function pushGuard() {
        if (pushed) { return; }
        pushed = true;
        try { window.history.pushState({ adominModal: true }, ''); } catch (err) { pushed = false; }
    }

    function dropGuard() {
        pushed = false;
    }

    if (window.jQuery) {
        /*
         * The framework's own defaults. Setting them on the plugin covers every
         * .modal('show') in the app and in the third-party plugins, without
         * having to find each call site. A dialog in this app is never
         * important enough to own the page.
         */
        if (window.jQuery.fn.modal && window.jQuery.fn.modal.Constructor &&
                window.jQuery.fn.modal.Constructor.prototype) {
            /*
             * Read the defaults off the constructor. Bootstrap 3 keeps them on
             * $.fn.modal.Constructor.DEFAULTS, and a plugin that was loaded
             * after this one may have reset them, so they are also set here at
             * the moment a dialog is about to open.
             */
            var proto = window.jQuery.fn.modal.Constructor.prototype;
            var originalShow = proto.show;
            if (typeof originalShow === 'function') {
                proto.show = function () {
                    this.options.backdrop = false;
                    var result = originalShow.apply(this, arguments);
                    return result;
                };
            }
        }

        window.jQuery(document)
            .on('show.bs.modal', function () { pushGuard(); })
            .on('shown.bs.modal', function () { settle(); })
            .on('hide.bs.modal', function () { window.setTimeout(settle, 0); })
            .on('hidden.bs.modal', function () { dropGuard(); settle(); });
    }

    window.addEventListener('popstate', function () {
        var open = document.querySelectorAll('.modal.in, .modal.show');
        if (open.length && window.jQuery) {
            window.jQuery(open[open.length - 1]).modal('hide');
            pushed = false;
            window.setTimeout(settle, 400);
            return;
        }
        pushed = false;
    });
})();
/*
 * Select vat reason from list
 */
$('#select-vat-from-list').change(function () {
    $('[name="no_vat_reason"]').val($(this).val());
});
/*
 * Search in modal selector
 */
$(document).on("keyup", '[name="SearchDualList"]', function (e) {
    var current_query = $('[name="SearchDualList"]').val();
    if (current_query !== "") {
        $("#modalSelector .list-group li").hide();
        $("#modalSelector .list-group li").each(function () {
            var current_keyword = $(this).text();
            if (current_keyword.search(current_query) >= 0) {
                $(this).show();
            }
        });
    } else {
        $(".list-group li").show();
    }
});
/*
 * If we are on invoice preview page
 * Add pages segments
 */
if ($('.invoice-box').length) {
    var invoice_box = $('.invoice-box');
    var num_parts = parseInt(invoice_box.height() / pixelsPdfDelivery);
    var i = 1;
    var position = 0;
    /*
     * The clones are anchored to the element we already hold, not to a fresh
     * ".pageDelivery:last" lookup. Looking the anchor up again on every pass
     * means the anchor becomes the clone that was just inserted, so the loop
     * walks forward and never reaches its limit - a frozen tab on a long
     * document. A hard cap backs that up in case the measurements are odd.
     */
    var lastDelivery = $('.pageDelivery').last();
    var maxParts = Math.min(num_parts, 200);
    while (i <= maxParts && lastDelivery.length) {
        var newDelivery = lastDelivery.clone();
        newDelivery.insertAfter(lastDelivery);
        position = position + pixelsPdfDelivery;
        newDelivery.css('top', position).removeClass('hidden').addClass('clone-delivery');
        lastDelivery = newDelivery;
        i++;
    }
}
/*
 * if check checkbox {check-all-boxes}
 * lets check all boxes with class check-me-now :)
 */
$(".check-all-boxes").change(function () {
    if (this.checked) {
        $('.check-me-now').prop("checked", true);
    } else {
        $('.check-me-now').prop("checked", false);
    }
});
/*
 * .list-action buttons call action like delete, send
 * for all selected elements in list
 */
$('.list-action').click(function () {
    var action = $(this).data('action-type');
    $('[name="action"]').val(action);
    var num_checked = $(".check-me-now:checked").length;
    if (num_checked == 0) {
        showError(lang.noCheckedCheckboxes);
        return;
    }
    if (action == 'delete') {
        var the_message = lang.confirmDelete;
    }
    if (action == 'stat_canceled') {
        var the_message = lang.confirmCancel;
    }
    if (action == 'remove_canceled') {
        var the_message = lang.confirmRemoveCancel;
    }
    bootbox.confirm({
        message: the_message,
        buttons: {
            confirm: {
                label: 'Yes',
                className: 'btn-success'
            },
            cancel: {
                label: 'No',
                className: 'btn-danger'
            }
        },
        callback: function (result) {
            if (result) {
                document.getElementById('action-form').submit();
            }
        }
    });
});
/*
 * Change invoices payment status
 */
var parent_div_statuses;
$('.show-pay-statuses').click(function () { // show statuses
    parent_div_statuses = $(this);
    $('#payment-statuses').show().appendTo($(this).parents('.status-changer'));
});
$('.change-pay-status').click(function () { // ajax change status
    var new_pay_status = $(this).data('new-pay-status');
    var new_pay_status_text = $(this).text();
    var to_inv_id = parent_div_statuses.data('inv-id');
    parent_div_statuses.find('.new_pay_status_text').text(new_pay_status_text);
    parent_div_statuses.parents('.status-changer').removeClass().addClass('txt_status_' + new_pay_status + ' status-changer');
    $('#payment-statuses').hide();
    $.post(urls.changeInvoiceStatus, {invId: to_inv_id, newStatus: new_pay_status}, function (result) {
        if (result != '1') {
            showError(lang.errorChangeInvStatusAjax);
        }
    });
});
/*
 * Change a document's own status from the list.
 *
 * The payment column has had a dropdown since before this fork; the status
 * column printed a word, so the only way to accept or cancel a document was to
 * find another screen. This is the same control over the other axis, and the
 * endpoint re-checks the allowlist - a dropdown is a hint, not a rule.
 */
$(document).on('change', '.doc-status-select', function () {
    var $select = $(this);
    var previous = $select.attr('data-previous-status');
    var next = $select.val();
    if (!next || next === previous) { return; }
    $select.prop('disabled', true);
    $.post(urls.setInvoiceStatus, {invId: $select.data('inv-id'), newStatus: next})
        .done(function (result) {
            if ($.trim(result) === '1') {
                $select.attr('data-previous-status', next);
            } else {
                $select.val(previous);
                showError('That status could not be set. Nothing was changed.');
            }
        })
        .fail(function () {
            $select.val(previous);
            showError('That status could not be set. Nothing was changed.');
        })
        .always(function () { $select.prop('disabled', false); });
});
/*
 * Top search form
 */
$('.top-search-form .field').keyup(function () {
    var search_phrase = jQuery.trim($(this).val());
    if (search_phrase != '') {
        $('.top-search-form .fa-spinner').show();
        $('.top-search-form .spray-search').hide();
        $.post(urls.topNavSearch, {search: search_phrase}, function (result) {
            $('#topSearchResults').empty().hide();
            $('.top-search-form .fa-spinner').hide();
            $('.top-search-form .spray-search').show();
            $('#topSearchResults').append(result).show();
        });
    } else {
        $('#topSearchResults').empty().hide();
    }
});
/*
 * when export if check export all
 * disable input fields
 */
$('[name="export_all"]').change(function () {
    if ($(this).is(":checked")) {
        $('[name="from_date"]').attr('disabled', true);
        $('[name="to_date"]').attr('disabled', true);
    } else {
        $('[name="from_date"]').attr('disabled', false);
        $('[name="to_date"]').attr('disabled', false);
    }
});
/*
 * Store movements type
 */
$('.movement-type').change(function () {
    var movement_type = $(this).val();
    if (movement_type == 'in') {
        $('.label-from-client').removeClass('hidden');
        $('.label-to-client').addClass('hidden');

        $('.label-from-store').addClass('hidden');
        $('.label-to-store').removeClass('hidden');

        $('.movement-for-client').show();
        $('.to-store-movement').addClass('hidden');
    }
    if (movement_type == 'out') {
        $('.label-from-client').addClass('hidden');
        $('.label-to-client').removeClass('hidden');

        $('.label-from-store').removeClass('hidden');
        $('.label-to-store').addClass('hidden');

        $('.movement-for-client').show();
        $('.to-store-movement').addClass('hidden');
    }

    if (movement_type == 'move') {
        $('.movement-for-client').hide();
        $('.label-from-store').removeClass('hidden');
        $('.label-to-store').addClass('hidden');
        $('.to-store-movement').removeClass('hidden');
    }

    if (movement_type == 'revision') {
        $('.movement-for-client').hide();
        $('.label-from-store').removeClass('hidden');
        $('.label-to-store').addClass('hidden');
        $('.to-store-movement').addClass('hidden');
    }
});
/*
 * Choose saved condition 
 * When create warranty
 */
$('.saved-condition').change(function () {
    var selected_condition = $(this).val();
    var description_text = $('[data-saved-condition="' + selected_condition + '"]').text();
    $('[name="conditions"]').empty().append(description_text);
});
/*
 * Choose saved contracts 
 * When create protocols
 */
$('.saved-contract').change(function () {
    var selected = $(this).val();
    var description_text = $('[data-saved-contract="' + selected + '"]').text();
    $('[name="contract"]').empty().append(description_text);
});
/*
 * Choose saved contracts 
 * When create protocols
 */
$('.saved-prov-trans').change(function () {
    var selected = $(this).val();
    var description_text = $('[data-saved-prov-trans="' + selected + '"]').text();
    $('[name="provider_trasmit"]').empty().append(description_text);
});
/*
 * Firm vat registration field show/hide
 */
$('[name="is_vat_registered"]').change(function () {
    if ($(this).is(":checked")) {
        $('.firm-vat-number').removeClass('hidden');
    } else {
        $('.firm-vat-number').addClass('hidden');
    }
});
/*
 * The main menu's collapse toggle used to live here. It is now the button in
 * the top bar (see the collapsible navigation block at the end of this file),
 * which works at every width rather than only on phones.
 */
/* Submit through the form so mouse, keyboard and assistive technology all
 * take the same validation path. The hidden value also makes the selected
 * status survive disabling the buttons while the request is in flight. */
$(document).on('submit', '#setInvoiceForm', function (event) {
    var form = this;
    if (form.dataset.submitting === '1') { event.preventDefault(); return; }
    var submitter = event.originalEvent && event.originalEvent.submitter;
    if (!submitter && document.activeElement && document.activeElement.form === form) submitter = document.activeElement;
    $(form).find('input[name="status"]').val(submitter && submitter.value === 'draft' ? 'draft' : 'issued');
    try {
        if (!createNewInvValidate()) { event.preventDefault(); return; }
    } catch (error) {
        // Never submit stale totals if the calculator or a dependency failed.
        event.preventDefault();
        showError(lang.errorCreateDocument);
        console.error('Invoice validation failed', error);
        return;
    }
    form.dataset.submitting = '1';
    $(form).find('button[type="submit"]').prop('disabled', true);
});
$(window).on('pageshow', function () {
    var form = document.getElementById('setInvoiceForm');
    if (form) { delete form.dataset.submitting; $(form).find('button[type="submit"]').prop('disabled', false); }
});
/*
 * Create Invoice form validation
 */
function createNewInvValidate() {
    var valid = true;
    var validItems = true;

    $('[name="client_name"]').css("border-color", border_color_fields);
    $('[name="client_address"]').css("border-color", border_color_fields);
    $('[name="inv_number"]').css("border-color", border_color_fields);
    $('[name="date_create"]').css("border-color", border_color_fields);
    $('[name="date_tax_event"]').css("border-color", border_color_fields);
    var client_name = $('[name="client_name"]').val();
    var client_address = $('[name="client_address"]').val();
    var date_create = $('[name="date_create"]').val();
    var date_tax_event = $('[name="date_tax_event"]').val();
    var inv_number = $('[name="inv_number"]').val();
    if ($('[name="inv_type"]:checked').val() == 'debit' || $('[name="inv_type"]:checked').val() == 'credit') {
        $('[name="to_inv_number"]').css("border-color", border_color_fields);
        $('[name="to_inv_date"]').css("border-color", border_color_fields);
        var to_inv_number = $('[name="to_inv_number"]').val();
        var to_inv_date = $('[name="to_inv_date"]').val();
        if ($.trim(to_inv_number).length == 0) {
            $('[name="to_inv_number"]').css("border-color", border_color_wrong);
            valid = false;
        }
        if ($.trim(to_inv_date).length == 0) {
            $('[name="to_inv_date"]').css("border-color", border_color_wrong);
            valid = false;
        }
    }
    if ($.trim(client_name).length == 0) {
        $('[name="client_name"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if ($.trim(client_address).length == 0) {
        $('[name="client_address"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if ($.trim(date_create).length == 0) {
        $('[name="date_create"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if ($.trim(date_tax_event).length == 0) {
        $('[name="date_tax_event"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if ($.trim(inv_number).length == 0) {
        $('[name="inv_number"]').css("border-color", border_color_wrong);
        valid = false;
    }
    validItems = validateItems();
    if (validItems == false || $('#setInvoiceForm .body-items tr').length === 0) {
        valid = false;
    }
    $('#setInvoiceForm .price-field').each(function () {
        var value = $.trim($(this).val());
        var ok = pattern_sums.test(value) && Number(value) >= 0;
        $(this).css('border-color', ok ? border_color_fields : border_color_wrong);
        if (!ok) valid = false;
    });
    if (typeof createDocument !== 'undefined' && createDocument.calculatorStatus == 1) {
        $('#setInvoiceForm .text-discount, #setInvoiceForm .vat-field').each(function () {
            if ($(this).hasClass('vat-field') && $('#no-vat').is(':checked')) return;
            var value = $.trim($(this).val());
            var ok = pattern_sums.test(value) && Number(value) >= 0;
            $(this).css('border-color', ok ? border_color_fields : border_color_wrong);
            if (!ok) valid = false;
        });
        if (valid) createInvoiceCalculator();
    }
    if (valid == true) {
        return true;
    } else {
        /*
         * Return to some default values
         * who is changed from submit buttons
         */
        $('[name="status"]').val('issued');
        $('html, body').animate({
            scrollTop: $("#setInvoiceForm").offset().top
        }, 1000);
        showError(lang.errorCreateDocument);
        return false;
    }
}
/*
 * Store movement validator
 */
function validateStoreMovement() {
    var valid = true;
    var validItems = true;
    validItems = validateItems();
    if (validItems == false) {
        valid = false;
    }
	var selectedStore = $('[name="selected_store"]').val(); 
	if(!selectedStore) {
		valid = false;
		$('.store-selector button.btn-default').css("border-color", "red");
	}
    if (valid == true) {
        document.getElementById('setMovementForm').submit();
    } else {
        /*
         * Return to some default values
         * who is changed from submit buttons
         */
        $('[name="status"]').val('issued');
        $('html, body').animate({
            scrollTop: $("#setMovementForm").offset().top
        }, 1000);
        showError(lang.errorCreateDocument);
    }
}
/*
 * Create warranty validator
 */
function validateWarranty() {
    var valid = true;
    var validItems = true;
    validItems = validateWarrantyItems();
    if (validItems == false) {
        valid = false;
    }
    if (valid == true) {
        document.getElementById('setWarrantyForm').submit();
    } else {
        $('html, body').animate({
            scrollTop: $("#setWarrantyForm").offset().top
        }, 1000);
        showError(lang.errorCreateDocument);
    }
}
function validateWarrantyItems() {
    var valid = true;
    $('.body-items tr').each(function () {
        $('.field-item-name', this).css("border-color", border_color_fields);
        $('.field-item-months', this).css("border-color", border_color_fields);
        var item_name = $('.field-item-name', this).val();
        var items_months = $('.field-item-months', this).val();
        if (!pattern_sums.test(items_months) || $.trim(items_months).length == 0 || items_months <= 0) {
            $('.field-item-months', this).css("border-color", border_color_wrong);
            valid = false;
        }
        if ($.trim(item_name).length == 0) {
            $('.field-item-name', this).css("border-color", border_color_wrong);
            valid = false;
        }
    });
    if (valid == false) {
        return false;
    }
    return true;
}
/*
 * Add items validation
 */
function validateItems() {
    var valid = true;
    $('.body-items tr').each(function () {
        $('.quantity-field', this).css("border-color", border_color_fields);
        $('.field-item-name', this).css("border-color", border_color_fields);
        var item_quantity = $('.quantity-field', this).val();
        var item_name = $('.field-item-name', this).val();
        if (!pattern_sums.test(item_quantity) || $.trim(item_quantity).length == 0 || item_quantity <= 0) {
            $('.quantity-field', this).css("border-color", border_color_wrong);
            valid = false;
        }
        if ($.trim(item_name).length == 0) {
            $('.field-item-name', this).css("border-color", border_color_wrong);
            valid = false;
        }
    });
    if (valid == false) {
        return false;
    }
    return true;
}
/*
 * Show alert box
 */
function showError(text) {
    $(document.body).append(alertBoxHtml.replace('%output%', text));
    $('.alert-errors').css('position', 'fixed').delay(4000).fadeOut(1500);
}
/*
 * Add new currency validator
 */
function addNewCurrency() {
    var valid = true;
    var name = $('#formAddCurrency .c-name').val();
    var value = $('#formAddCurrency .c-value').val();
    name = $.trim(name);
    value = $.trim(value);
    if (name.length == 0) {
        $('#formAddCurrency .c-name').css("border-color", border_color_wrong);
        valid = false;
    }
    if (value.length == 0) {
        $('#formAddCurrency .c-value').css("border-color", border_color_wrong);
        valid = false;
    }
    if (valid == true) {
        document.getElementById('formAddCurrency').submit();
    }
}
/*
 * Add new quantity type validator
 */
function addNewQuantityType() {
    var valid = true;
    var name = $('[name="quantityTypeName"]').val();
    name = $.trim(name);
    if (name.length == 0) {
        $('[name="quantityTypeName"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if (valid == true) {
        document.getElementById('formAddQuantityType').submit();
    }
}
/*
 * Add new payment method validator
 */
function addNewPaymentMethod() {
    var valid = true;
    var name = $('[name="paymentMethodName"]').val();
    name = $.trim(name);
    if (name.length == 0) {
        $('[name="paymentMethodName"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if (valid == true) {
        document.getElementById('formAddPaymentMethod').submit();
    }
}
/*
 * Add new no vat reason validator
 */
function addNewNoVatReason() {
    var valid = true;
    var name = $('[name="noVatReason"]').val();
    name = $.trim(name);
    if (name.length == 0) {
        $('[name="noVatReason"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if (valid == true) {
        document.getElementById('formAddNoVatReason').submit();
    }
}
/*
 * Add new store validator
 */
function addNewStore() {
    var valid = true;
    var name = $('[name="newStore"]').val();
    name = $.trim(name);
    if (name.length == 0) {
        $('[name="newStore"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if (valid == true) {
        document.getElementById('formAddStore').submit();
    }
}
/*
 * Create Invoice Calculator
 * Sum Items prices
 * Sum Vat %/Currency
 * Sum Discount
 * We will use math.js library
 * createDocument variable is initialized in calculator used pages
 */
function createInvoiceCalculator() {
    // if is enabled calculator
    if (createDocument.calculatorStatus == 1) {
        // Sum item by item
        var items_total = 0.00;
        $('.body-items tr').each(function () {
            $('.quantity-field', this).css("border-color", border_color_fields);
            $('.price-field', this).css("border-color", border_color_fields);
            var item_quantity = $('.quantity-field', this).val();
            var item_price = $('.price-field', this).val();
            var is_valid = true;
            if (!pattern_sums.test(item_quantity)) {
                $('.quantity-field', this).css("border-color", border_color_wrong);
                is_valid = false;
            }
            if (!pattern_sums.test(item_price)) {
                $('.price-field', this).css("border-color", border_color_wrong);
                is_valid = false;
            }
            if (is_valid == true) {
                var item_total = math.multiply(item_quantity, item_price).toFixed(createDocument.rountTo);
                $('.item-total', this).text(item_total);
                $('.item-total', this).val(item_total);
                items_total = math.add(items_total, item_total).toFixed(createDocument.rountTo);
            }
        });
        $('#items-total').text(items_total);
        $('.items-total').val(items_total);
        // Tax base after discount
        var discount_type = $('#discount-value option:selected').val();
        var discount_value = $('.text-discount').val();
        if (pattern_sums.test(discount_value)) {
            $('.text-discount').css("border-color", border_color_fields);
            if (discount_type == '%') {
                var tax_base = math.subtract(items_total, math.multiply(items_total, math.divide(discount_value, 100))).toFixed(createDocument.rountTo);
            } else {
                var tax_base = math.subtract(items_total, discount_value).toFixed(createDocument.rountTo);
            }
            $('#tax-base').text(tax_base);
            $('.tax-base').val(tax_base);
        } else {
            $('.text-discount').css("border-color", border_color_wrong);
        }
        // Get vat 
        var final_sum = tax_base;
        if (!$('#no-vat').is(":checked")) {
            var vat_percent = $('.vat-field').val();
            if (pattern_sums.test(vat_percent)) {
                $('.vat-field').css("border-color", border_color_fields);
                var vat_sum = math.multiply(math.divide(tax_base, 100), vat_percent).toFixed(createDocument.rountTo);
                $('#vat-sum').text(vat_sum);
                $('.vat-sum').val(vat_sum);
                final_sum = math.add(tax_base, vat_sum).toFixed(createDocument.rountTo);
            } else {
                $('.vat-field').css("border-color", border_color_wrong);
            }
        } else {
            // Do not persist VAT calculated before the exemption was checked.
            $('#vat-sum').text('0.00');
            $('.vat-sum').val('0.00');
        }
        // Set final sum
        $('#final-total').text(final_sum);
        $('.final-total').val(final_sum);
    }
}
/*
 * Verify that filed for round totals is not empty
 */
function updateRoundTotals() {
    var pattern = /^[0-9]+$/;
    var valid = true;
    var name = $('.optRoundTo').val();
    name = $.trim(name);
    if (name.length == 0 || !pattern.test(name)) {
        $('[name="opt_invRoundTo"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if (valid == true) {
        document.getElementById('formRoundTotals').submit();
    }
}
/*
 * Verifi that translation no have empty fields
 * Submit form and add to db
 */
function saveNewTranslation() {
    $('.field-new-translate').css("border-color", border_color_fields);
    var valid = true;
    $('.field-new-translate').each(function (index) {
        var translate = $(this).val();
        translate = $.trim(translate);
        if (translate.length == 0) {
            $(this).css("border-color", border_color_wrong);
            valid = false;
        }
    });
    if (valid == true) {
        document.getElementById('formAddNewTranslate').submit();
    }
}
/*
 * When click client from list
 * parse object and fill fields
 */
function getClient(id) {
    $('[name="client_from_list"]').val(1);
    $('[name="client_name"]').val(clients[id].client_name);
    $('[name="client_bulstat"]').val(clients[id].client_bulstat);
    if (clients[id].is_to_person == '1') {
        $('[name="is_to_person"]').prop("checked", true);
        $('.client-company').hide();
        $('.client-individial').show();
    } else {
        $('[name="is_to_person"]').prop("checked", false);
        $('.client-company').show();
        $('.client-individial').hide();
    }
    if (clients[id].client_vat_registered == '1') {
        $('[name="client_vat_registered"]').prop("checked", true);
        $('.client-vat-registered').show();
    } else {
        $('.client-vat-registered').hide();
    }
    $('[name="client_city"]').val(clients[id].client_city);
    $('[name="client_country"]').val(clients[id].client_country);
    $('[name="accountable_person"]').val(clients[id].accountable_person);
    $('[name="client_address"]').val(clients[id].client_address);
    $('[name="recipient_name"]').val(clients[id].recipient_name);
    $('[name="client_ident_num"]').val(clients[id].client_ident_num);
    $('[name="vat_number"]').val(clients[id].vat_number);
    $('#modalSelector').modal('hide');
}
/*
 * When click item from list
 * parse object and fill fields
 */
function getItem(id) {
    $('[name="item_from_list[]"]:eq(' + chooseItemIndex + ')').val(items[id].id);
    $('[name="items_names[]"]:eq(' + chooseItemIndex + ')').val(items[id].name);
    $('[name="items_prices[]"]:eq(' + chooseItemIndex + ')').val(items[id].single_price);
    if ($('[name="items_quantity_types[]"]:eq(' + chooseItemIndex + ') option[value="' + items[id].quantity_type + '"]').length <= 0) {
        $('[name="items_quantity_types[]"]:eq(' + chooseItemIndex + ')').prepend('<option value="' + items[id].quantity_type + '">' + items[id].quantity_type + '</option>');
    }
    $('[name="items_quantity_types[]"]:eq(' + chooseItemIndex + ')').val(items[id].quantity_type);
    var selectedCurrency = $('#selectCurrencyNewInv').val();
    // typeof checking is because in some fileds we do not use currency
    if (typeof selectedCurrency !== "undefined" && selectedCurrency != items[id].currency) {
        showError(lang.currencyItemNotSame + ' - ' + items[id].currency);
    }
    $('#modalSelector').modal('hide');
    createInvoiceCalculator();
}
/*
 * Vat field visibility check
 */
function vatRegisteredFieldVisibility() {
    if ($('#client-vat-registered').is(":checked")) {
        $('.client-vat-registered').show();
    } else {
        $('.client-vat-registered').hide();
    }
}
/*
 * When create/update client
 * Validate him
 */
function newClientValidate() {
    var valid = true;
    $('[name="client_name"]').css("border-color", border_color_fields);
    $('[name="client_address"]').css("border-color", border_color_fields);
    var client_name = $('[name="client_name"]').val();
    var client_address = $('[name="client_address"]').val();
    if ($.trim(client_name).length == 0) {
        $('[name="client_name"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if ($.trim(client_address).length == 0) {
        $('[name="client_address"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if (valid == true) {
        document.getElementById('setNewClient').submit();
    } else {
        $('html, body').animate({
            scrollTop: $("#setNewClient").offset().top
        }, 1000);
        showError(lang.errorCreateClient);
    }
}
/*
 * When create/update item
 * Validate him
 */
function newItemValidate() {
    var valid = true;
    $('[name="name"]').css("border-color", border_color_fields);
    var item_name = $('[name="name"]').val();
    if ($.trim(item_name).length == 0) {
        $('[name="name"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if (valid == true) {
        document.getElementById('setNewItem').submit();
    } else {
        showError(lang.errorCreateItem);
    }
}
/*
 * When create/update employee
 * Validate him
 */
function newEmployeeValidate() {
    var valid = true;
    $('[name="email"]').css("border-color", border_color_fields);
    $('[name="password"]').css("border-color", border_color_fields);
    var emp_email = $('[name="email"]').val();
    if ($.trim(emp_email).length == 0) {
        $('[name="email"]').css("border-color", border_color_wrong);
        valid = false;
    } else {
        if (!pattern_email.test(emp_email)) {
            $('[name="email"]').css("border-color", border_color_wrong);
            valid = false;
        }
    }
    /*
     * The form says whether a password is mandatory. It used to be a global
     * `opt.passReq` set by an inline <script>, which the settings page CSP
     * blocked - so `opt` was undefined and this threw before validating.
     */
    var form = document.getElementById('setNewEmployee');
    var passRequired = form && form.getAttribute('data-password-required') === '1';
    if (passRequired) {
        var emp_pass = $('[name="password"]').val();
        if ($.trim(emp_pass).length == 0) {
            $('[name="password"]').css("border-color", border_color_wrong);
            valid = false;
        }
    }
    if (valid == true) {
        document.getElementById('setNewEmployee').submit();
    } else {
        showError(lang.errorCreateEmployee);
    }
}
/*
 * Validation add warranty conditions
 */
function addNewWarrantyCondition() {
    var valid = true;
    var condition = $('[name="condition"]').val();
    condition = $.trim(condition);
    var condition_title = $('[name="conditionTitle"]').val();
    condition_title = $.trim(condition_title);
    if (condition_title.length == 0) {
        $('[name="conditionTitle"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if (condition.length == 0) {
        $('[name="condition"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if (valid == true) {
        document.getElementById('formAddWarrantyCondition').submit();
    }
}
/*
 * Open preview warranty descriptions
 */
function openConditionDescription(id) {
    var description_text = $('[data-descr-id="' + id + '"]').text();
    $('#modalDescriptionExplain .modal-body').empty().append(description_text);
}
/*
 * Create protocol validator
 */
function createNewProtocolValidate() {
    var valid = true;
    var validItems = true;
    $('[name="client_name"]').css("border-color", border_color_fields);

    var client_name = $('[name="client_name"]').val();
    if ($.trim(client_name).length == 0) {
        $('[name="client_name"]').css("border-color", border_color_wrong);
        valid = false;
    }
    validItems = validateItems();
    if (validItems == false) {
        valid = false;
    }
    if (valid == false) {
        $('html, body').animate({
            scrollTop: $("#setProtocol").offset().top
        }, 1000);
        showError(lang.errorCreateDocument);
    }
    if (valid == true) {
        document.getElementById('setProtocol').submit();
    }
}
/*
 * Validation add provider transmit
 */
function addNewProviderTransmitText() {
    var valid = true;
    var description = $('[name="description"]').val();
    description = $.trim(description);
    var title = $('[name="title"]').val();
    title = $.trim(title);
    if (title.length == 0) {
        $('[name="title"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if (description.length == 0) {
        $('[name="description"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if (valid == true) {
        document.getElementById('formAddProviderTransmitText').submit();
    }
}
/*
 * Open provider transmit description
 */
function openProviderTransmitDescription(id) {
    var description_text = $('[data-descr-id="' + id + '"]').text();
    $('#modalDescriptionExplain .modal-body').empty().append(description_text);
}
/*
 * Open contract description
 */
function openContractDescription(id) {
    var description_text = $('[data-contr-id="' + id + '"]').text();
    $('#modalDescriptionExplain .modal-body').empty().append(description_text);
}
/*
 * Validation contract texts add
 */
function addNewContractText() {
    var valid = true;
    var description = $('[name="contract"]').val();
    description = $.trim(description);
    var title = $('[name="title_contract"]').val();
    title = $.trim(title);
    if (title.length == 0) {
        $('[name="title_contract"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if (description.length == 0) {
        $('[name="contract"]').css("border-color", border_color_wrong);
        valid = false;
    }
    if (valid == true) {
        document.getElementById('formContractText').submit();
    }
}
/*
 * Remove firm logo when edit firm
 */
function removeFirmLogo() {
    $('[name="old_image"]').val('');
    $('.firm-image-container').remove();
    $('.remove-firm-logo-btn').remove();
}
/*
 * Validate and submit form for
 * custom plan request
 */
function makePlanRequest() {
    var submit = true;
    $('[name="inv_per_month"]').css("border-color", border_color_fields);
    $('[name="want_companies"]').css("border-color", border_color_fields);
    var inv_per_month = parseInt($('[name="inv_per_month"]').val());
    var want_companies = parseInt($('[name="want_companies"]').val());
    if (inv_per_month == 0 || isNaN(inv_per_month)) {
        $('[name="inv_per_month"]').css("border-color", border_color_wrong);
        submit = false;
    }
    if (want_companies == 0 || isNaN(want_companies)) {
        $('[name="want_companies"]').css("border-color", border_color_wrong);
        submit = false;
    }
    if (submit == true) {
        document.getElementById("formMakePlanReq").submit();
    }
}
/*
 * Enhanced dropdowns.
 *
 * bootstrap-select is loaded on every page and the markup carries
 * data-live-search, titles and .selectpicker classes, but nothing ever called
 * .selectpicker() - so none of the enhanced behaviour existed. The native
 * <select> was shown instead, which ignores data-live-search and the title, and
 * the two .selectpicker('refresh') calls elsewhere did nothing because
 * refreshing an uninitialised control is a no-op.
 *
 * Initialising here, once, per control:
 *   - live search filters the list as the user types
 *   - the menu is capped in height and scrolls, so a long currency or client
 *     list does not run off the screen
 *   - the chosen value stays on the control when a row is cloned or a form is
 *     redrawn, because bootstrap-select reads it back from the <select>
 */
var avSpSeq = 0;
function avInitSelectPickers(scope) {
    if (typeof $.fn.selectpicker !== 'function') {
        return;                       // the plugin is not on this page
    }
    var $targets = $(scope || document).find('.selectpicker').not('.av-sp-ready');
    $targets.each(function () {
        var $el = $(this);
        /*
         * A cloned invoice row carries its parent's bootstrap-select wrapper
         * along with the <select>. Cloning again would nest a second wrapper,
         * so any wrapper that arrived from a clone is removed first and the
         * <select> is rebuilt where it belongs.
         */
        var $stale = $el.closest('.bootstrap-select');
        if ($stale.length) {
            $stale.before($el);
            $stale.remove();
        }
        // ids are cloned too, and a duplicate id makes a label point at the
        // wrong control; the clone gets a fresh one
        if ($el.attr('id')) {
            $el.attr('id', $el.attr('id') + '-' + (++avSpSeq));
        }
        $el.selectpicker({
            liveSearch: $el.data('live-search') === true || $el.attr('data-live-search') === 'true',
            liveSearchPlaceholder: $el.data('live-search-placeholder') || 'Search',
            noneSelectedText: $el.attr('title') || '--',
            size: 8,                  // eight rows, then the menu scrolls
            width: 'auto'
        });
        $el.addClass('av-sp-ready');
    });
}
$(function () {
    avInitSelectPickers();
    // reveal the popup above the fold where there is room: bootstrap-select
    // drops it below the button and it can fall off a short window
    $(document).on('shown.bs.select', '.bootstrap-select', function () {
        var $menu = $(this).find('.dropdown-menu');
        if (!$menu.length) { return; }
        var spaceBelow = window.innerHeight - $menu[0].getBoundingClientRect().bottom;
        if (spaceBelow < 0) {
            $menu.css({ top: 'auto', bottom: '100%' });
        }
    });
});
/* refresh every picker after the page has added or removed options */
function avRefreshSelectPickers() {
    if (typeof $.fn.selectpicker === 'function') {
        $('.selectpicker.av-sp-ready').selectpicker('refresh');
    }
}

/*
 * Collapsible navigation.
 *
 * The menu occupies a fixed column; hiding it hands that width to the page.
 * The choice is kept in localStorage so it holds across pages, and the initial
 * class is applied by an inline head script (see parts/header.php) so the menu
 * does not flash open and then collapse on every load.
 */
(function () {
    // nav-state.js put the initial class on <html> (body did not exist yet);
    // from here on it lives on both so either selector in the CSS works.
    var body = document.body;
    var root = document.documentElement;
    var btn = document.getElementById('av-nav-toggle');
    var closeBtn = document.querySelector('.av-nav-close');
    var label = document.getElementById('av-nav-toggle-label');
    if (!btn) {
        return;
    }

    function isNarrow() {
        return window.matchMedia && window.matchMedia('(max-width: 991px)').matches;
    }

    function setExpanded(expanded) {
        btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (label && typeof lang !== 'undefined') {
            label.textContent = expanded ? lang.hide_main_menu : lang.show_main_menu;
        }
        if (isNarrow()) {
            body.classList.toggle('av-nav-open', expanded);
        } else {
            body.classList.toggle('av-nav-collapsed', !expanded);
            root.classList.toggle('av-nav-collapsed', !expanded);
        }
    }

    var stored = null;
    try { stored = window.localStorage.getItem('av-nav-collapsed'); } catch (e) {}
    // wide: expanded unless the user collapsed it. narrow: always collapsed.
    setExpanded(isNarrow() ? false : stored !== '1');

    btn.addEventListener('click', function () {
        var nowExpanded = isNarrow()
            ? !body.classList.contains('av-nav-open')
            : body.classList.contains('av-nav-collapsed');
        setExpanded(nowExpanded);
        if (isNarrow() && nowExpanded && closeBtn) { closeBtn.focus(); }
        if (!isNarrow()) {
            stored = nowExpanded ? '0' : '1';
            try { window.localStorage.setItem('av-nav-collapsed', stored); } catch (e) {}
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () { setExpanded(false); btn.focus(); });
    }
    document.addEventListener('keydown', function (e) {
        if (!isNarrow() || !body.classList.contains('av-nav-open')) { return; }
        if (e.key === 'Escape') {
            setExpanded(false);
            btn.focus();
        } else if (e.key === 'Tab') {
            var menu = document.getElementById('main-menu');
            var focusable = menu ? menu.querySelectorAll('a[href], button:not([disabled])') : [];
            if (!focusable.length) { return; }
            var first = focusable[0], last = focusable[focusable.length - 1];
            if (e.shiftKey && (document.activeElement === first || !menu.contains(document.activeElement))) {
                e.preventDefault(); last.focus();
            } else if (!e.shiftKey && (document.activeElement === last || !menu.contains(document.activeElement))) {
                e.preventDefault(); first.focus();
            }
        }
    });

    // tapping the page behind the overlay closes it on small screens
    document.addEventListener('click', function (e) {
        if (!isNarrow() || !body.classList.contains('av-nav-open')) {
            return;
        }
        if (!e.target.closest('.left-col') && !e.target.closest('#av-nav-toggle')) {
            setExpanded(false);
        }
    });

    // a resize that crosses the breakpoint must not leave the overlay stuck on
    var wasNarrow = isNarrow();
    window.addEventListener('resize', function () {
        var narrow = isNarrow();
        if (narrow === wasNarrow) {
            return;
        }
        wasNarrow = narrow;
        body.classList.remove('av-nav-open');
        setExpanded(narrow ? false : (stored !== '1'));
    });
})();

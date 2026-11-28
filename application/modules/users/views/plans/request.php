<div class="selected-page">
    <div class="inner">
        <h1> 
            <?= lang('my_plan_request') ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?= lang_url('user') ?>"><?= lang('home') ?></a></li>
            <li class="active"><?= lang('my_plan_request') ?></li>
        </ol>
    </div> 
</div>
<?php if ($this->permissions->hasPerm('perm_view_plans_page')) { ?>
    <div class="plan-req-page">
        <h1><?= lang('payment_awaiting') ?></h1>
        <hr>
        <div class="payment-request">
            <div><b><?= lang('plan_payment_type') ?>:</b> <?= $paymentReq['payment_type'] == 'bank' ? lang('plan_payment_bank') : lang('plan_payment_paypal') ?></div>
            <div>
                <b><?= lang('plan_type') ?>:</b> <?= lang('plan_' . strtolower($paymentReq['plan_type'])) ?>(<?= $plans[$paymentReq['plan_type']]['NUM_INVOICES'] . ' ' . lang('plans_num_inv') . ', ' . $plans[$paymentReq['plan_type']]['NUM_FIRMS'] . ' ' . lang('plans_num_firms') ?>)
            </div>
            <?php /*
             * No period, because there is no term. This printed
             * "Period: 12 months (KSh 1,750 per 12 months)", which was a
             * subscription describing itself to somebody who has just bought
             * the software outright.
             */ ?>
            <div><b><?= lang('plan_amount_due') ?>:</b> <?= html_escape($amountFormatted ?: $plans[$paymentReq['plan_type']]['PRICE_FORMATTED']) ?> <?= lang('plans_once') ?></div>
            <div><b><?= lang('plan_date_generated') ?>:</b> <?= date('d.m.Y', $paymentReq['date_generated']) ?></div>
            <div><b><?= lang('plan_req_num') ?>:</b> <?= $paymentReq['req_num'] ?></div>
            <div><a href="<?= lang_url('user/myplan/request?payment=cancel') ?>" class="btn btn-default confirm" data-my-message="<?= lang('confirm_cancel_req') ?>"><?= lang('cancel_pay_req') ?></a></div>
        </div>
		<hr>
        <div class="payment-details">
            <?php /*
             * The card logos are gone, and so is the sentence promising that
             * the plan "will be started" - nothing here starts by itself.
             *
             * This panel used to show eight card brands and a PayPal mark, on
             * a page a customer reaches after asking to pay. There is no
             * gateway behind it: card payment was removed from this build, and
             * the handler it used held sandbox credentials in a shell command.
             * Showing the logos was inviting somebody to look for a card form
             * that is not there. What happens instead is written out, and it
             * is a person on the other end.
             */
            ?>
            <p><b><?= lang('plan_how_to_pay') ?></b></p>
            <p><?= lang('plan_how_to_pay_text') ?></p>
            <p class="text-muted"><?= lang('plan_req_ref') ?>: <b><?= html_escape($paymentReq['req_num']) ?></b></p>
        </div>
        <div class="alert alert-info">
            <strong><?= lang('plan_request_what_next') ?></strong> <?= lang('plan_request_what_next_text') ?>
        </div>
    </div>
    <?php
} else {
    ?>
    <h1 class="no-permissions"><?= lang('no_permissions') ?></h1>
<?php } ?>
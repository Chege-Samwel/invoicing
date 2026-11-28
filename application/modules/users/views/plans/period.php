<div class="selected-page">
    <div class="inner">
        <h1> 
            <?= lang('plans_period') ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?= lang_url('user') ?>"><?= lang('home') ?></a></li> 
            <li><a href="<?= lang_url('user/plans') ?>"><?= lang('plans') ?></a></li>
            <li class="active"><?= lang('plan_period') ?></li>
        </ol>
    </div> 
</div>
<?php if ($this->permissions->hasPerm('perm_view_plans_page')) { ?>
    <div class="row">
        <div class="col-sm-6 col-sm-offset-3 col-md-6 col-md-offset-3">
            <form method="POST" action="">
                <input type="hidden" name="type" value="<?= strtoupper($planType) ?>">
                <div class="plan-container">
                    <div class="plan-header">
                        <div class="icon-box"><i class="fa fa-users icon"></i></div>
                        <h2><?= lang('plan_' . $planType) ?></h2>
                        <p><?= $plans[strtoupper($planType)]['PRICE_FORMATTED'] ?> <?= lang('plans_once') ?></p>
                    </div>
                    <div class="plan-details">
                        <?php /*
                         * There is no period to choose.
                         *
                         * This screen asked for one, two, three or four years
                         * of a subscription and posted the answer in months.
                         * The product is bought once now, so the only question
                         * left is whether to send the request - and the panel
                         * says plainly that a person will come back with
                         * payment details, because there is no card form here
                         * to take them.
                         */ ?>
                        <ul>
                            <li class="option-li">
                                <b><?= lang('plan_what_you_get') ?></b>
                                <?= $plans[strtoupper($planType)]['NUM_INVOICES'] ?> <?= lang('plans_num_inv') ?>,
                                <?= $plans[strtoupper($planType)]['NUM_FIRMS'] ?> <?= lang('plans_num_firms') ?>
                            </li>
                            <li class="option-li">
                                <b><?= lang('plan_how_to_pay') ?></b>
                                <?= lang('plan_how_to_pay_text') ?>
                            </li>
                        </ul>
                        <?php /*
                         * plan_period is NOT NULL on firms_payment_requests and
                         * the column means "months". 0 records a one-off
                         * ownership - there is no term to store, and inventing
                         * one would put a fake renewal date in the admin list.
                         */ ?>
                        <input type="hidden" name="period" value="0">
                        <input type="hidden" name="payment_type" value="bank">
                        <button type="submit" class="button-confirm">
                            <?= lang('confirm_plan_requiest') ?> 
                            <i class="fa fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php } else { ?>
    <h1 class="no-permissions"><?= lang('no_permissions') ?></h1>
<?php } ?>
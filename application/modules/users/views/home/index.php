<?php
/**
 * Dashboard.
 *
 * Shows the four numbers that decide what to do next, then the actions that
 * act on them. No chart by default: a chart answers a question nobody asked
 * until there is history to see, so it only appears once invoices exist.
 */
$hasCompany = (SELECTED_COMPANY_ID != null);
$s = isset($summary) && is_array($summary) ? $summary : array();
$num = function ($key) use ($s) {
    return isset($s[$key]) ? (int) $s[$key] : 0;
};
$money = function ($key) use ($s) {
    return isset($s[$key]) ? (float) $s[$key] : 0.0;
};
$currency = function_exists('adomin_conf') ? (adomin_conf('locale', 'currency') ?: 'KES') : 'KES';
$fmt = function ($value) use ($currency) {
    // Whole units when the amount is large; the exact cents are on the invoices.
    return $currency . ' ' . number_format($value, $value >= 1000 ? 0 : 2);
};
$companyName = isset($_SESSION['selected_company']['name']) ? $_SESSION['selected_company']['name'] : '';
$greeting = function_exists('adomin_conf') ? (adomin_conf('locale', 'greeting') ?: '') : '';
?>
<?php if (!$hasCompany) { ?>
    <?php /* first run: no company yet, so there is nothing to summarise */ ?>
    <div class="av-page">
        <h1>Add your company</h1>
        <p class="av-page-sub">Your invoices carry these details, so they come first.</p>
        <div class="av-empty">
            <p>No company on this account yet.</p>
        </div>
    </div>
<?php } else { ?>
<div class="av-page">

    <div class="av-page-head">
        <div>
            <h1><?= $greeting !== '' ? html_escape($greeting) . ', ' : '' ?><?= html_escape($companyName) ?></h1>
            <p class="av-page-sub">All time · <?= $num('num_this_month') ?> issued this month</p>
        </div>
        <div class="av-actions">
            <a class="av-btn av-btn-primary" href="<?= lang_url('user/new/invoice') ?>">
                <i class="fa fa-plus" aria-hidden="true"></i> New invoice
            </a>
            <a class="av-btn" href="<?= lang_url('user/invoices') ?>">All invoices</a>
        </div>
    </div>

    <div class="av-kpis">
        <a class="av-kpi" href="<?= lang_url('user/invoices') ?>">
            <span class="av-kpi-k">Invoices</span>
            <span class="av-kpi-v"><?= number_format($num('num_invoices')) ?></span>
            <span class="av-kpi-m"><?= $num('num_drafts') ?> <?= $num('num_drafts') === 1 ? 'draft' : 'drafts' ?></span>
        </a>
        <a class="av-kpi" href="<?= lang_url('user/reports') ?>">
            <span class="av-kpi-k">Invoiced</span>
            <span class="av-kpi-v"><?= $fmt($money('total_value')) ?></span>
            <span class="av-kpi-m">Total issued</span>
        </a>
        <a class="av-kpi av-kpi-warn" href="<?= lang_url('user/invoices') ?>">
            <span class="av-kpi-k">Unpaid</span>
            <span class="av-kpi-v"><?= $fmt($money('total_unpaid')) ?></span>
            <span class="av-kpi-m"><?= $fmt($money('total_partly')) ?> part paid</span>
        </a>
        <a class="av-kpi av-kpi-ok" href="<?= lang_url('user/reports') ?>">
            <span class="av-kpi-k">Paid</span>
            <span class="av-kpi-v"><?= $fmt($money('total_paid')) ?></span>
            <span class="av-kpi-m">Settled</span>
        </a>
    </div>

    <?php if ($num('num_invoices') === 0) { ?>
        <div class="av-empty">
            <p>No invoices yet. The first one takes about a minute.</p>
            <a class="av-btn av-btn-primary" href="<?= lang_url('user/new/invoice') ?>">Create the first invoice</a>
        </div>
    <?php } else { ?>
        <?php /* the chart is worth its weight only once there is history */ ?>
        <div class="av-chart">
            <?php
            /*
             * The partial draws into this element by id. Without it Highcharts
             * has nowhere to render and the section stays blank, so the two
             * lines belong together.
             */
            ?>
            <div id="report-num-invoices"></div>
            <?php /* absolute, not relative: the include must not depend on cwd */
            include APPPATH . 'modules/users/views/reports/reportNumInvoices.php'; ?>
        </div>
    <?php } ?>

    <div class="av-quick">
        <a class="av-quick-item" href="<?= lang_url('user/new/invoice') ?>">
            <i class="fa fa-file-text-o" aria-hidden="true"></i>
            <span>New invoice</span>
        </a>
        <a class="av-quick-item" href="<?= lang_url('user/clients') ?>">
            <i class="fa fa-users" aria-hidden="true"></i>
            <span>Clients</span>
        </a>
        <a class="av-quick-item" href="<?= lang_url('user/items') ?>">
            <i class="fa fa-cube" aria-hidden="true"></i>
            <span>Items</span>
        </a>
        <a class="av-quick-item" href="<?= lang_url('user/reports') ?>">
            <i class="fa fa-bar-chart" aria-hidden="true"></i>
            <span>Reports</span>
        </a>
        <a class="av-quick-item" href="<?= lang_url('user/settings/invoices') ?>">
            <i class="fa fa-sliders" aria-hidden="true"></i>
            <span>Template</span>
        </a>
    </div>
</div>
<?php } ?>

<?php
/*
 * The pricing page.
 *
 * Three tiers, one comparison matrix, and a currency the visitor can change.
 * Everything on it is derived from application/config/adomin_plans.php through
 * AdominPricing, so the price on this page and the price on the invoice are the
 * same object - there is no second copy of "200" anywhere.
 *
 * The annual figure is quoted with the saving shown, because "1,750 a year"
 * beside "200 a month" asks the buyer to do mental arithmetic and conclude we
 * are hiding something. 650 saved is a better sentence than either number.
 */
$rows = $pricing->featureMatrix();
$plans = $pricing->plans();
$quotes = $pricing->priceList($period, $currency);
// the annual list is needed even on the monthly view: the card shows both
$quotesYearly = $period === 'yearly' ? $quotes : $pricing->priceList('yearly', $currency);
$trial = $pricing->trial();
$countries = $geography;
?>
<div class="adomin-pricing">

    <section class="pr-hero">
        <h1>Simple pricing. Start free, pay when it earns its keep.</h1>
        <p class="pr-lede">
            Issue your first invoices at no cost. Move to Pro when you need
            unlimited invoicing, your own branding and no platform fee on the
            payments you collect.
        </p>

        <div class="pr-controls">

            <?php /*
             * Period switch. Two links rather than a checkbox, so it works
             * without JavaScript and the choice is a URL somebody can share.
             */ ?>
            <div class="pr-switch" role="group" aria-label="Billing period">
                <a class="pr-switch-opt<?= $period === 'monthly' ? ' is-on' : '' ?>"
                   href="<?= $switchPeriod['monthly'] ?>"
                   aria-pressed="<?= $period === 'monthly' ? 'true' : 'false' ?>">Monthly</a>
                <a class="pr-switch-opt<?= $period === 'yearly' ? ' is-on' : '' ?>"
                   href="<?= $switchPeriod['yearly'] ?>"
                   aria-pressed="<?= $period === 'yearly' ? 'true' : 'false' ?>">Yearly</a>
                <?php
                $proYearly = $quotes['PRO'];
                if ($period === 'yearly' && !empty($proYearly['saving_formatted'])) {
                    echo '<span class="pr-switch-note">Save ' . html_escape($proYearly['saving_formatted']) . '</span>';
                }
                ?>
            </div>

            <?php /*
             * Currency. This is a guess at where the visitor is, and the label
             * says so - a wrong guess that cannot be corrected is worse than no
             * guess at all. The form posts and the session remembers it.
             */ ?>
            <form class="pr-currency" method="post" action="<?= $currencyAction ?>">
                <label for="pr-currency-select">Show prices in</label>
                <select id="pr-currency-select" name="currency" onchange="this.form.submit()">
                    <?php foreach ($currencies as $code => $label) { ?>
                        <option value="<?= html_escape($code) ?>"<?= $code === $currency ? ' selected' : '' ?>><?= html_escape($label) ?></option>
                    <?php } ?>
                </select>
                <noscript><button type="submit" class="btn btn-default btn-xs">Update</button></noscript>
                <input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfHash ?>">
                <input type="hidden" name="period" value="<?= html_escape($period) ?>">
            </form>
        </div>

        <?php if ($currencyGuessed) { ?>
            <p class="pr-guess">
                Prices shown in <b><?= html_escape($currency) ?></b>, guessed from your location (<?= html_escape($countryName) ?>).
                <?php if ($currency !== 'KES') { ?>
                    You will be billed in <?= html_escape($currency) ?>; our books are kept in Kenyan Shillings.
                <?php } ?>
            </p>
        <?php } ?>

        <?php if ($ratesStale) { ?>
            <p class="pr-guess pr-guess-warn">
                The exchange rate has not refreshed today, so these figures may be a little out of date.
                Paying in <?= html_escape($currency) ?> still charges the amount shown.
            </p>
        <?php } ?>
    </section>

    <section class="pr-cards">
        <?php foreach ($plans as $key => $plan) {
            $quote = $quotes[$key];
            $sellable = !empty($quote['sellable']);
            $isTrial = !empty($trial['enabled']) && isset($trial['plan_key']) && $trial['plan_key'] === $key;
            ?>
            <article class="pr-card<?= !empty($plan['highlight']) ? ' is-highlight' : '' ?><?= $sellable ? '' : ' is-quote' ?>">

                <?php if (!empty($plan['highlight'])) { ?>
                    <div class="pr-badge">Most popular</div>
                <?php } ?>

                <h2><?= html_escape($plan['name']) ?></h2>
                <p class="pr-tagline"><?= html_escape($plan['tagline']) ?></p>

                <div class="pr-price">
                    <?php if ($sellable) { ?>
                        <span class="pr-amount"><?= html_escape($quote['formatted']) ?></span>
                        <?php
                        // "KSh 0 /year" is a strange thing to print; free is
                        // free, and the period is irrelevant to it.
                        if (!$quote['free']) { ?>
                            <span class="pr-per"><?= $period === 'yearly' ? '/year' : '/month' ?></span>
                        <?php } ?>
                    <?php } else { ?>
                        <span class="pr-amount pr-amount-talk">Let's talk</span>
                        <span class="pr-per">priced to your volume</span>
                    <?php } ?>
                </div>

                <?php if ($sellable && $period === 'yearly' && !empty($quote['saving_formatted'])) { ?>
                    <?php /*
                     * The saving, and what it works out to per month. "Just over
                     * three months free" is what 650 against 200 actually is -
                     * the usual "two months free" would be a lie in our favour.
                     */ ?>
                    <p class="pr-saving">
                        <b><?= html_escape($quote['saving_formatted']) ?></b> cheaper than paying monthly
                        <span class="pr-muted">
                            (about <?= html_escape($pricing->format($quote['monthly_equivalent'], $quote['currency'])) ?> a month;
                            <?= $quote['free_months'] > 0 ? 'just over ' . (int) floor($quote['free_months']) . ' months free' : '' ?>)
                        </span>
                    </p>
                <?php } elseif ($sellable && $period === 'monthly' && (int) $quote['amount_minor'] > 0) {
                    /*
                     * The monthly view still has to show the annual, or nobody
                     * discovers it. This used to print $quotes[$key] - the very
                     * figure above it - so the card read "200 a month, or 200 a
                     * year", which is a strange thing for a price list to say.
                     */
                    $yearly = $quotesYearly[$key];
                    ?>
                    <p class="pr-saving pr-muted">
                        or <?= html_escape($yearly['formatted']) ?> a year
                        <?php if (!empty($yearly['saving_formatted'])) { ?>
                            (save <?= html_escape($yearly['saving_formatted']) ?>)
                        <?php } ?>
                    </p>
                <?php } elseif ($key === 'FREE') { ?>
                    <p class="pr-saving pr-muted">Free for as long as you like. No card.</p>
                <?php } ?>

                <?php if ($isTrial) { ?>
                    <p class="pr-trial"><?= (int) $trial['days'] ?>-day free trial, no card needed</p>
                <?php } ?>

                <ul class="pr-features">
                    <?php foreach ($rows as $row) {
                        $cell = isset($row['cells'][$key]) ? $row['cells'][$key] : NULL;
                        if ($cell === NULL || $cell === '') {
                            continue;
                        }
                        ?>
                        <li><span class="pr-feature-label"><?= html_escape($row['label']) ?>:</span> <?= html_escape($cell) ?></li>
                    <?php } ?>
                </ul>

                <div class="pr-cta">
                    <?php if ($key === 'FREE') { ?>
                        <a class="btn btn-default btn-block" href="<?= $registerUrl ?>">Start free</a>
                    <?php } elseif ($sellable) { ?>
                        <a class="btn btn-primary btn-block"
                           href="<?= $buyUrlBase . strtolower($key) . '?period=' . urlencode($period) ?>">
                           <?= $isTrial ? 'Start the free trial' : 'Choose ' . html_escape($plan['name']) ?>
                        </a>
                    <?php } else { ?>
                        <a class="btn btn-default btn-block" href="<?= $contactUrl ?>">Ask us for a quote</a>
                    <?php } ?>
                </div>
            </article>
        <?php } ?>
    </section>

    <section class="pr-matrix-wrap">
        <h2>What changes between them</h2>
        <table class="pr-matrix">
            <thead>
                <tr>
                    <th scope="col"><span class="sr-only">Feature</span></th>
                    <?php foreach ($plans as $key => $plan) { ?>
                        <th scope="col"><?= html_escape($plan['name']) ?></th>
                    <?php } ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) { ?>
                    <tr>
                        <th scope="row"><?= html_escape($row['label']) ?></th>
                        <?php foreach ($plans as $key => $plan) {
                            $cell = isset($row['cells'][$key]) ? $row['cells'][$key] : NULL; ?>
                            <td><?= $cell === NULL ? '<span class="pr-muted">&mdash;</span>' : html_escape($cell) ?></td>
                        <?php } ?>
                    </tr>
                <?php } ?>
                <tr class="pr-matrix-row-limits">
                    <th scope="row">Hard limits</th>
                    <?php foreach ($plans as $key => $plan) {
                        $inv = $pricing->limit($key, 'invoices_per_month');
                        $cli = $pricing->limit($key, 'clients');
                        $usr = $pricing->limit($key, 'users');
                        ?>
                        <td>
                            <?= html_escape($pricing->limitLabel($inv)) ?> invoices/month<br>
                            <?= html_escape($pricing->limitLabel($cli)) ?> clients<br>
                            <?= html_escape($pricing->limitLabel($usr)) ?> <?= $usr === 1 ? 'user' : 'users' ?>
                        </td>
                    <?php } ?>
                </tr>
            </tbody>
        </table>
    </section>

    <section class="pr-notes">
        <h2>The small print, in plain words</h2>
        <ul>
            <li>
                <b>Prices are set in Kenyan Shillings.</b> When you pay in another currency the
                amount is the shilling price converted at the day's rate, rounded to a sensible
                local figure. What you are quoted is what you are charged.
            </li>
            <li>
                <b>The Free plan carries our mark.</b> Invoices made on Free show
                "Powered by anvoice" in the footer. Pro removes it.
            </li>
            <li>
                <b>Payments collected through the Free plan carry a platform fee.</b>
                Pro accounts pay their own gateway fees, and nothing to us.
            </li>
            <li>
                <b>Downgrading never changes an invoice you already issued.</b> Documents are
                records; what they contained when issued is what they keep.
            </li>
            <li>
                <b>VAT and tax.</b> Prices shown exclude any tax that may apply in your
                jurisdiction. Businesses buying from Kenya may need to account for VAT themselves.
            </li>
        </ul>
    </section>

    <section class="pr-faq">
        <h2>Questions people actually ask</h2>

        <details>
            <summary>What happens when the trial ends?</summary>
            <p>
                Nothing breaks and nothing is charged. The account drops to Free and keeps
                working: your invoices stay, your clients stay. You only lose the Pro features
                - the mark comes back on new documents, and invoicing goes back to five a month
                until you choose a paid plan.
            </p>
        </details>

        <details>
            <summary>Can I change plan later?</summary>
            <p>
                Yes, in either direction, at any time. Upgrades apply immediately. A downgrade
                applies at the end of the period you have already paid for, so you never lose
                time you have bought.
            </p>
        </details>

        <details>
            <summary>Do you take a cut of the invoices I send?</summary>
            <p>
                No. We take a percentage of payments you <em>collect through us</em> on the Free
                plan, because that costs us money to process. Invoices sent outside the platform,
                and everything on Pro, carries no fee from us.
            </p>
        </details>

        <details>
            <summary>Why is Business "let's talk"?</summary>
            <p>
                Because it depends on what you need: several business profiles, your own domain,
                regional e-invoicing and an audit trail. Those have real setup costs, so we quote
                them rather than inventing a number that would be wrong for most buyers.
            </p>
        </details>
    </section>

    <section class="pr-final">
        <h2>Start with the free plan. You will know within a month.</h2>
        <a class="btn btn-primary" href="<?= $registerUrl ?>">Create a free account</a>
        <p class="pr-muted">
            One minute to register. No card. Export everything if you leave.
        </p>
    </section>
</div>

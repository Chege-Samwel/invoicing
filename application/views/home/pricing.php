<?php
/*
 * The pricing page.
 *
 * Three tiers, one comparison matrix, and a currency the visitor can change.
 * Everything on it is derived from application/config/adomin_plans.php through
 * AdominPricing, so the price on this page and the price on the invoice are the
 * same object - there is no second copy of "200" anywhere.
 *
 * One price, paid once.
 *
 * There used to be a monthly/yearly switch and a paragraph of arithmetic
 * underneath every card explaining how much the year saved over twelve
 * months. None of that exists now: Pro is 6,000 KES for life, and the Free
 * plan is free. The comparison the page has to make is between what Free
 * does and what Pro does, which is a table, not a discount.
 */
$rows = $pricing->featureMatrix();
$unbuilt = $pricing->unbuiltRows();
$plans = $pricing->plans();
$quotes = $pricing->priceList('once', $currency);
$trial = $pricing->trial();
$term = $pricing->term();
// the same test the landing page uses: is there a signed-in session
$signedIn = isset($_SESSION['user_login']);
$countries = $geography;
?>
<div class="adomin-pricing">

    <section class="pr-hero">
        <h1>One price. You own it.</h1>
        <p class="pr-lede">
            Pay <?= html_escape($pricing->quote('PRO', 'once', $currency)['formatted']) ?> once and keep it.
            No monthly charge, no renewal, no card on file. Start free if you
            want to try it first - the free plan does not expire.
        </p>

        <div class="pr-controls">

            <?php /*
             * There is no period to switch. The product is bought once, so
             * this is a statement of what happens after the payment rather
             * than a control - a switch with one option is a button that
             * looks broken.
             */ ?>
            <p class="pr-owned">
                <span class="pr-switch-opt is-on">Paid once</span>
                <span class="pr-switch-note">No renewal. No monthly bill.</span>
            </p>

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
                        /*
                         * "KSh 0 /for life" is a strange thing to print, and
                         * "/month" would be a lie now. Free says nothing
                         * after the figure; Pro says it is paid once.
                         */
                        if (!$quote['free']) { ?>
                            <span class="pr-per">once, <?= html_escape($term['label']) ?></span>
                        <?php } ?>
                    <?php } else { ?>
                        <span class="pr-amount pr-amount-talk">Coming soon</span>
                        <span class="pr-per">not sold yet</span>
                    <?php } ?>
                </div>

                <?php if ($key === 'FREE') { ?>
                    <p class="pr-saving pr-muted">Free for as long as you like. No card, no expiry.</p>
                <?php } elseif ($sellable) {
                    /*
                     * The old card compared two prices here. There is one, so
                     * the useful thing to say is what the payment does not do:
                     * it does not come back.
                     */
                    ?>
                    <p class="pr-saving pr-muted">
                        One payment. <?= (int) $term['owner_years'] ?> years of use written into the record,
                        and nothing to renew when it runs out.
                    </p>
                <?php } elseif ($key === 'BUSINESS') { ?>
                    <p class="pr-saving pr-muted">In development. The rows below are the plan.</p>
                <?php } ?>

                <?php if ($isTrial) { ?>
                    <?php /*
                     * The trial is started from registration, not from a plan
                     * link - signing up grants it. So the day count sits above
                     * the button as a fact about the offer, and the button
                     * itself keeps saying what it does.
                     */ ?>
                    <p class="pr-trial"><?= (int) $trial['days'] ?> days of Pro included when you register, no card needed</p>
                <?php } ?>

                <ul class="pr-features">
                    <?php foreach ($rows as $row) {
                        $cell = isset($row['cells'][$key]) ? $row['cells'][$key] : NULL;
                        if ($cell === NULL || $cell === '') {
                            continue;
                        }
                        $isSoon = isset($unbuilt[$row['key']]);
                        ?>
                        <li<?= $isSoon ? ' class="is-soon"' : '' ?>>
                            <span class="pr-feature-label"><?= html_escape($row['label']) ?>:</span>
                            <?= html_escape($cell) ?>
                            <?php if ($isSoon) { ?>
                                <span class="pr-soon">coming soon</span>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ul>

                <div class="pr-cta">
                    <?php if ($key === 'FREE') { ?>
                        <a class="btn btn-default btn-block" href="<?= $registerUrl ?>">Start free</a>
                    <?php } elseif ($sellable && $signedIn) { ?>
                        <?php /*
                         * The honest label. There is no gateway in this build,
                         * so the link opens a request the owner approves by
                         * hand - and the button says so rather than implying a
                         * card form that does not exist.
                         */ ?>
                        <a class="btn btn-primary btn-block"
                           href="<?= $buyUrlBase . strtolower($key) ?>">
                           <?= $isTrial ? 'Start with the ' . (int) $trial['days'] . '-day trial' : 'Request an activation' ?>
                        </a>
                    <?php } elseif ($sellable) { ?>
                        <?php /*
                         * Nobody signed in yet. The plan link lives inside the
                         * account, so sending a visitor there lands them on a
                         * login screen they did not ask for. Registration is
                         * where the trial starts and where the request is made
                         * once there is an account to attach it to.
                         */ ?>
                        <a class="btn btn-primary btn-block" href="<?= $registerUrl ?>">
                           <?= $isTrial ? 'Create an account - ' . (int) $trial['days'] . ' days of Pro included' : 'Create an account to buy' ?>
                        </a>
                    <?php } else { ?>
                        <a class="btn btn-default btn-block" href="<?= $contactUrl ?>">Ask to be told when it lands</a>
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
                    <tr<?= isset($unbuilt[$row['key']]) ? ' class="is-soon"' : '' ?>>
                        <th scope="row">
                            <?= html_escape($row['label']) ?>
                            <?php if (isset($unbuilt[$row['key']])) { ?>
                                <span class="pr-soon">coming soon</span>
                            <?php } ?>
                        </th>
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
                <b>You pay once.</b> Pro is a single payment, not a subscription: there is no
                renewal, no monthly charge and nothing to cancel. Should we ever need to stop
                running the service, existing buyers would be told with enough notice to export
                everything.
            </li>
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
                <b>Anything marked "coming soon" is not built yet.</b> It is on the list
                because we would rather show you the roadmap than quietly drop it, and it
                is labelled so that nothing here is a feature you pay for and then find
                missing. Pro is the product; the marked rows are what comes next.
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
            <summary>Is this a subscription?</summary>
            <p>
                No. You pay once and the software is yours to use. There is no card on file,
                no renewal date and no monthly invoice from us. The free plan is likewise free
                indefinitely - it is a smaller product, not a trial.
            </p>
        </details>

        <details>
            <summary>How do I pay, and how quickly does it activate?</summary>
            <p>
                You request an activation from your account and we send payment details by
                reply - M-Pesa or bank transfer. Activation is done by hand today, usually the
                same working day. Nothing is charged automatically at any point.
            </p>
        </details>

        <details>
            <summary>Do you take a cut of the invoices I send?</summary>
            <p>
                No, and we never will on the plans sold today. This page used to describe a
                platform fee on payments collected through the Free plan; there is no payment
                gateway in the product yet, so no such fee is collected, and the line has been
                removed rather than left as a small print that could be enforced later.
            </p>
        </details>

        <details>
            <summary>What is Business?</summary>
            <p>
                The list of what comes next: several business profiles, role-based permissions,
                your own domain, KRA eTIMS and regional e-invoicing, part payments and audit
                records. It is marked "coming soon" because it is not built yet - we would
                rather show you the roadmap than sell you a promise and a price.
            </p>
        </details>
    </section>

    <section class="pr-final">
        <h2>Start free. Own it when it earns its keep.</h2>
        <a class="btn btn-primary" href="<?= $registerUrl ?>">Create a free account</a>
        <p class="pr-muted">
            One minute to register. No card. Pay once when you are ready, and export everything if you leave.
        </p>
    </section>
</div>

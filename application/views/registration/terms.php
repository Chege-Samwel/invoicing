<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * The terms a business accepts when it registers.
 *
 * Written to be read - short sections, no sub-clauses - because a document
 * nobody reads protects nobody, and the acceptance checkbox on the signup form
 * is only meaningful if the text behind it is legible. The content is plain
 * HTML rather than a stored record: terms that live in the database can be
 * edited after an account accepted them, which is exactly what a version
 * number and a date are for.
 */
$brandName = adomin_conf('brand', 'name');
$company = adomin_conf('brand', 'company');
$email = adomin_conf('brand', 'support_email');
$site = adomin_conf('brand', 'website');
$updated = '27 November 2026';
?><!DOCTYPE html>
<html lang="<?= MY_LANGUAGE_ABBR ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="The terms under which <?= html_escape($brandName) ?> is provided.">
    <title>Terms and conditions · <?= html_escape($brandName) ?></title>
    <style>
        :root { --accent: <?= html_escape(adomin_conf('brand', 'accent')) ?>; }
        body {
            margin: 0;
            background: #f6f7f9;
            color: #1f2a36;
            font: 16px/1.65 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        .tc-wrap { max-width: 760px; margin: 0 auto; padding: 36px 20px 70px; }
        .tc-top { margin-bottom: 26px; }
        .tc-top a { color: #667085; text-decoration: none; font-size: 14px; }
        .tc-card {
            background: #fff;
            border-radius: 10px;
            padding: 40px 40px 44px;
            box-shadow: 0 1px 2px rgba(16, 24, 40, .06), 0 8px 24px rgba(16, 24, 40, .05);
        }
        h1 { font-size: 27px; line-height: 1.25; margin: 0 0 6px; letter-spacing: -.01em; }
        .tc-meta { color: #98a2b3; font-size: 13px; margin: 0 0 28px; }
        h2 {
            font-size: 16px;
            margin: 30px 0 8px;
            padding-top: 20px;
            border-top: 1px solid #eef1f5;
        }
        h2:first-of-type { border-top: 0; padding-top: 0; }
        p, li { color: #475467; }
        ul { padding-left: 20px; margin: 8px 0; }
        li { margin: 0 0 6px; }
        a { color: var(--accent); }
        .tc-note {
            background: #f7f9fc;
            border-left: 3px solid var(--accent);
            padding: 14px 16px;
            border-radius: 4px;
            font-size: 14px;
            color: #475467;
            margin: 24px 0;
        }
        .tc-foot { margin-top: 34px; font-size: 13px; color: #98a2b3; text-align: center; }
        .tc-foot a { color: #667085; }
        @media (max-width: 560px) { .tc-card { padding: 26px 20px 30px; } }
    </style>
</head>
<body>
<div class="tc-wrap">
    <p class="tc-top"><a href="<?= base_url('registration') ?>">&larr; Back to sign up</a></p>

    <div class="tc-card">
        <h1>Terms and conditions</h1>
        <p class="tc-meta">Version 1.0 · updated <?= html_escape($updated) ?></p>

        <p>
            These are the terms under which <?= html_escape($company) ?> provides
            <?= html_escape($brandName) ?>, the invoicing and stock system you are
            signing up for. By creating an account you agree to them. If you are
            registering on behalf of a business, you are confirming that you may
            agree to them for that business.
        </p>

        <h2>1. Your account</h2>
        <ul>
            <li>You are responsible for the accuracy of the details on the account, and for keeping your password to yourself.</li>
            <li>One account belongs to one business. Employees you invite act under your account, and you remain responsible for what they do with it.</li>
            <li>Tell us at once at <a href="mailto:<?= html_escape($email) ?>"><?= html_escape($email) ?></a> if you believe someone else has access to it.</li>
        </ul>

        <h2>2. Your documents</h2>
        <ul>
            <li>The invoices, pro-forma, credit notes and other documents you create are yours. We do not claim ownership of them or of the data in them.</li>
            <li>You are responsible for what those documents say, including the prices, the tax treatment and whether they meet the requirements of the authority you report to.</li>
            <li><?= html_escape($brandName) ?> records what you enter. It does not give legal, tax or accounting advice, and a document produced here is not a substitute for professional advice.</li>
        </ul>

        <h2>3. Share links</h2>
        <ul>
            <li>A share link lets anyone holding it open the document it points to. Treat it the way you would treat a copy of the invoice.</li>
            <li>You can revoke a link at any time; the old address then stops working. Documents can be reissued with a new link.</li>
            <li>A client's answer through a link - accepting or refusing - is recorded as a request. It becomes a status on your invoice only when you confirm it.</li>
        </ul>

        <h2>4. Availability</h2>
        <p>
            We work to keep <?= html_escape($brandName) ?> available and your data intact,
            but we do not promise uninterrupted service. Maintenance, provider outages
            and events outside our control can interrupt it. Keep your own export of
            anything you could not afford to lose; you can export at any time.
        </p>

        <h2>5. Fees</h2>
        <p>
            The free plan costs nothing. Pro is a single payment at the price shown on
            the pricing page when you buy it, and it is not a subscription: there is no
            renewal, no recurring charge and no card kept on file. If we raise the price
            later, the new price applies to people who have not yet bought - it does not
            apply to you.
        </p>

        <h2>6. Cancelling and your data</h2>
        <ul>
            <li>You can stop using <?= html_escape($brandName) ?> whenever you like.</li>
            <li>Before you close the account, export what you need. We keep your records for a reasonable period after closure so a mistake can be undone, then delete them.</li>
            <li>We may suspend an account we reasonably believe is being used to issue documents that are false, or to attack the service or another customer.</li>
        </ul>

        <h2>7. What we are not liable for</h2>
        <p>
            To the extent the law allows, our liability for any claim arising out of
            the service is limited to the fees you paid us for it. We are not liable for lost profit or for losses that flow from a
            document you issued, priced or filed incorrectly.
        </p>

        <h2>8. Changes to these terms</h2>
        <p>
            We may update these terms. When we do, the version and the date at the top
            of this page change, and material changes are announced in the app before
            they take effect. Continuing to use the service after that means you accept
            the new version.
        </p>

        <div class="tc-note">
            Questions about any of this? Write to
            <a href="mailto:<?= html_escape($email) ?>"><?= html_escape($email) ?></a>.
            We answer in plain language.
        </div>
    </div>

    <p class="tc-foot">
        <?= html_escape($company) ?> ·
        <a href="<?= html_escape($site) ?>" target="_blank" rel="noopener"><?= html_escape($site) ?></a>
    </p>
</div>
</body>
</html>

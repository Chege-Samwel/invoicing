<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * The page for a shared document that no longer resolves.
 *
 * Written for a client, not for us: it opens with what happened, says what to
 * do next, and puts our services at the bottom under a rule - an invitation,
 * not the point of the page.
 */
?><!DOCTYPE html>
<html lang="<?= MY_LANGUAGE_ABBR ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= html_escape($copy['title']) ?> · <?= html_escape($brand['name']) ?></title>
    <link href="<?= base_url('assets/bootstrap/css/bootstrap.min.css') ?>" rel="stylesheet">
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css" rel="stylesheet">
    <style>
        :root { --accent: <?= html_escape($brand['accent']) ?>; }
        body {
            margin: 0;
            background: #eef1f5;
            font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1f2a36;
        }
        .su-wrap { max-width: 680px; margin: 0 auto; padding: 40px 20px 60px; }
        .su-card {
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 1px 2px rgba(16, 24, 40, .06), 0 8px 24px rgba(16, 24, 40, .06);
            padding: 36px 32px;
        }
        .su-mark {
            display: inline-block;
            font-size: 13px;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: var(--accent);
            font-weight: 600;
            margin-bottom: 18px;
        }
        h1 { font-size: 26px; line-height: 1.25; margin: 0 0 10px; letter-spacing: -.01em; }
        .su-lead { font-size: 17px; color: #475467; margin: 0 0 12px; }
        .su-body { color: #667085; margin: 0 0 24px; }
        .su-next {
            background: #f7f9fc;
            border: 1px solid #e4e9f0;
            border-left: 3px solid var(--accent);
            border-radius: 6px;
            padding: 16px 18px;
            margin: 0 0 26px;
        }
        .su-next h2 { font-size: 14px; text-transform: uppercase; letter-spacing: .08em; color: #344054; margin: 0 0 8px; }
        .su-next ol { margin: 0; padding-left: 20px; color: #475467; }
        .su-next li { margin: 0 0 4px; }
        .su-actions { display: flex; flex-wrap: wrap; gap: 10px; margin: 0 0 6px; }
        .su-btn {
            display: inline-block;
            padding: 11px 18px;
            border-radius: 6px;
            text-decoration: none !important;
            font-weight: 600;
            font-size: 15px;
            border: 1px solid transparent;
        }
        .su-btn-primary { background: var(--accent); color: #fff !important; }
        .su-btn-quiet { background: #fff; color: #344054 !important; border-color: #d0d7e2; }
        .su-help { font-size: 14px; color: #667085; margin: 14px 0 0; }
        .su-services {
            margin-top: 34px;
            padding-top: 26px;
            border-top: 1px solid #e4e9f0;
        }
        .su-services h2 { font-size: 15px; color: #344054; margin: 0 0 4px; }
        .su-services > p { color: #667085; font-size: 14px; margin: 0 0 18px; }
        .su-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        @media (max-width: 520px) { .su-grid { grid-template-columns: 1fr; } .su-card { padding: 26px 20px; } }
        .su-grid div { font-size: 14px; color: #667085; }
        .su-grid i { color: var(--accent); margin-right: 6px; }
        .su-grid b { display: block; color: #1f2a36; font-size: 14px; margin-bottom: 2px; }
        .su-foot { text-align: center; font-size: 12px; color: #98a2b3; margin-top: 22px; }
        .su-foot a { color: #667085; }
    </style>
</head>
<body>
<div class="su-wrap">
    <div class="su-card">
        <span class="su-mark"><?= html_escape($brand['name']) ?></span>

        <h1><?= html_escape($copy['title']) ?></h1>
        <p class="su-lead"><?= html_escape($copy['lead']) ?></p>
        <p class="su-body"><?= html_escape($copy['body']) ?></p>

        <div class="su-next">
            <h2>How to get it sorted</h2>
            <ol>
                <li>Reply to the message that brought you here and ask for an updated link.</li>
                <li>If you opened it from an e-mail, check there is not a newer one.</li>
                <li>Still nothing? Write to <a href="mailto:<?= html_escape($brand['email']) ?>"><?= html_escape($brand['email']) ?></a> and we will trace it.</li>
            </ol>
        </div>

        <div class="su-actions">
            <a class="su-btn su-btn-primary"
               href="mailto:<?= html_escape($brand['email']) ?>?subject=<?= rawurlencode('Request an updated invoice link') ?>">
                <i class="fa fa-envelope-o" aria-hidden="true"></i>
                Request an updated link
            </a>
            <?php if (!empty($brand['website'])) { ?>
                <a class="su-btn su-btn-quiet" href="<?= html_escape($brand['website']) ?>" target="_blank" rel="noopener">
                    <i class="fa fa-external-link" aria-hidden="true"></i>
                    Visit <?= html_escape($brand['company']) ?>
                </a>
            <?php } ?>
        </div>
        <p class="su-help">
            <?php if (!empty($brand['phone'])) { ?>
                Or call <a href="tel:<?= html_escape(preg_replace('/[^0-9+]/', '', $brand['phone'])) ?>"><?= html_escape($brand['phone']) ?></a>.
            <?php } else { ?>
                The business that sent you the invoice can reissue the link at any time.
            <?php } ?>
        </p>

        <div class="su-services">
            <h2>What <?= html_escape($brand['company']) ?> builds</h2>
            <p><?= html_escape($brand['name']) ?> is their invoicing and stock system. The same team can help with:</p>
            <div class="su-grid">
                <?php foreach ($services as $service) { ?>
                    <div>
                        <b><i class="fa <?= html_escape($service['icon']) ?>" aria-hidden="true"></i><?= html_escape($service['title']) ?></b>
                        <?= html_escape($service['body']) ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <p class="su-foot">
        <?= html_escape(adomin_conf('brand', 'powered_by')) ?>
        · <a href="<?= html_escape($brand['website']) ?>" target="_blank" rel="noopener"><?= html_escape($brand['website']) ?></a>
    </p>
</div>
</body>
</html>

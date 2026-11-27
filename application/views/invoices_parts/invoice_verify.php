<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * What a scanned verification code opens (Adomin Technologies).
 *
 * Public page, no login, no form, no CSRF surface: the only input is the URL
 * that was printed into a QR code. Three outcomes, and they are kept visibly
 * apart because confusing them would be worse than saying nothing:
 *
 *   verified   - this number and signature belong to a real, un-deleted
 *                document, and here are the figures it was issued with
 *   no match   - the number has no document, or the signature is wrong; the
 *                same message for both, so the page cannot be used to discover
 *                which invoice numbers exist
 *   cannot check - this host cannot verify anything (library or key missing);
 *                it must not be mistaken for "forged"
 *
 * The wording avoids the word "genuine". The code proves the paper matches the
 * record; it is not a legal certification and the copy should not imply one.
 */
$issued = !empty($invoice['date_create']) ? date('d.m.Y', (int) $invoice['date_create']) : '';
$currency = isset($invoice['inv_currency']) ? $invoice['inv_currency'] : '';
$clientName = isset($invoice['client']['client_name']) ? $invoice['client']['client_name'] : '';
?>
<div class="container">
    <div class="inv-accept adomin-share-page adomin-verify-page">
        <a href="<?= base_url() ?>" class="logo">
            <img src="<?= adomin_logo_url() ?>" alt="<?= html_escape(adomin_conf('brand', 'app_name')) ?>">
        </a>
        <hr>

        <?php if (!$checked) { ?>
            <?php if (!empty($unavailable)) { ?>
                <div class="adomin-verify-card is-unknown">
                    <span class="adomin-verify-badge">
                        <i class="fa fa-question" aria-hidden="true"></i>
                    </span>
                    <h1 class="adomin-verify-title"><?= lang('verify_unavailable_title') ?></h1>
                    <p class="adomin-verify-text"><?= lang('verify_unavailable_text') ?></p>
                </div>
            <?php } else { ?>
                <div class="adomin-verify-card is-unknown">
                    <span class="adomin-verify-badge">
                        <i class="fa fa-question" aria-hidden="true"></i>
                    </span>
                    <h1 class="adomin-verify-title"><?= lang('verify_nocode_title') ?></h1>
                    <p class="adomin-verify-text"><?= lang('verify_nocode_text') ?></p>
                </div>
            <?php } ?>
        <?php } elseif ($verified) { ?>

            <div class="adomin-verify-card is-verified">
                <span class="adomin-verify-badge">
                    <i class="fa fa-check" aria-hidden="true"></i>
                </span>
                <h1 class="adomin-verify-title"><?= lang('verify_ok_title') ?></h1>
                <p class="adomin-verify-text"><?= lang('verify_ok_text') ?></p>
            </div>

            <dl class="adomin-verify-facts">
                <div>
                    <dt><?= lang('verify_doc_number') ?></dt>
                    <dd><?= html_escape($invoice['inv_number']) ?></dd>
                </div>
                <div>
                    <dt><?= lang('verify_doc_issued') ?></dt>
                    <dd><?= html_escape($issued) ?></dd>
                </div>
                <div>
                    <dt><?= lang('verify_doc_total') ?></dt>
                    <dd><?= html_escape($invoice['final_total'] . ' ' . $currency) ?></dd>
                </div>
                <?php if ($clientName !== '') { ?>
                    <div>
                        <dt><?= lang('verify_doc_client') ?></dt>
                        <dd><?= html_escape($clientName) ?></dd>
                    </div>
                <?php } ?>
                <div>
                    <dt><?= lang('verify_doc_status') ?></dt>
                    <dd><?= html_escape($invoice['status']) ?></dd>
                </div>
            </dl>

            <p class="adomin-verify-note"><?= lang('verify_ok_note') ?></p>

        <?php } else { ?>

            <div class="adomin-verify-card is-failed">
                <span class="adomin-verify-badge">
                    <i class="fa fa-times" aria-hidden="true"></i>
                </span>
                <h1 class="adomin-verify-title"><?= lang('verify_bad_title') ?></h1>
                <p class="adomin-verify-text"><?= lang('verify_bad_text') ?></p>
                <?php if ($invNumber !== '') { ?>
                    <p class="adomin-verify-ref">
                        <?= lang('verify_ref') ?> <strong><?= html_escape($invNumber) ?></strong>
                    </p>
                <?php } ?>
            </div>

        <?php } ?>

        <p class="adomin-verify-foot adomin-no-print">
            <a href="<?= base_url() ?>"><?= html_escape(adomin_conf('brand', 'app_name')) ?></a>
        </p>
    </div>
</div>

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 * Public, read-only copy of one document - what the client sees when you use
 * "Send via link" (Adomin Technologies).
 *
 * No login, no editing, no CSRF surface: the token in invoices.uniqid is the
 * only thing that grants access, and "Revoke link" rotates it. If the sender
 * still wants the accept/refuse step, that lives on accept/invoice/<token>.
 */
$firmName = isset($invoice['firm']['name']) ? $invoice['firm']['name'] : '';
$currency = isset($invoice['inv_currency']) ? $invoice['inv_currency'] : '';
$issued = !empty($invoice['date_create']) ? date('d.m.Y', (int) $invoice['date_create']) : '';
?>
<div class="container">
    <div class="inv-accept adomin-share-page">
        <?php /*
            No logo above the document.

            This used to be a 56px copy of our own mark, pinned between the top
            of the page and the client's invoice - so the first thing a client
            saw on somebody else's invoice was our brand. The document belongs
            to the business that sent it; we are the tool, and a tool does not
            sign the letter. Our name appears once, quietly, at the foot of the
            PDF, which is where it belongs and where the sender expects it.
        */ ?>

        <div class="adomin-doc-bar">
            <p class="adomin-doc-title">
                <?= html_escape($typeLabel) ?> № <?= html_escape($invoice['inv_number']) ?>
            </p>
            <?php if ($issued !== '') { ?>
                <span class="adomin-inline-dot">·</span>
                <span><?= html_escape($issued) ?></span>
            <?php } ?>
            <span class="adomin-doc-total">
                <?= html_escape($invoice['final_total'] . ' ' . $currency) ?>
            </span>
            <?php /*
                No status here, on purpose. This page is what the client sees,
                and the status is the business's own bookkeeping - "accepted"
                is not true until the business says so, and printing "awaiting"
                beside a document the client has already agreed to reads as an
                accusation. The status lives in the app; the link shows the
                document.
            */ ?>
            <span class="adomin-doc-actions adomin-no-print">
                <a href="<?= html_escape($pdfUrl) ?>" class="btn btn-adomin" target="_blank" rel="noopener">
                    <i class="fa fa-file-pdf-o" aria-hidden="true"></i>
                    <?= lang('adomin_download_pdf') ?>
                </a>
                <a href="javascript:void(0);" class="btn btn-default" onclick="window.print();">
                    <i class="fa fa-print" aria-hidden="true"></i>
                    <?= lang('adomin_print') ?>
                </a>
                <?php /*
                    A 30px copy of the code used to sit here, in the button bar.
                    The document below already carries the code in its
                    letterhead, and two codes on one page is one too many - a
                    reader cannot tell which one the verification page expects.
                */ ?>
            </span>
        </div>

        <p class="adomin-share-lead">
            <?= html_escape($firmName) ?>
            <?php if (!empty($invoice['client']['client_name'])) { ?>
                · <?= lang('for') ?> <?= html_escape($invoice['client']['client_name']) ?>
            <?php } ?>
        </p>

        <?php
        /*
         * The client's answer.
         *
         * There is no "Accepted" here, because nothing has been accepted yet -
         * the business has not confirmed it. The wording is "ask the business to
         * record this", and what the client sends is a request. Anything else
         * would be the page telling the client that a decision has been taken
         * that no one at the firm has seen.
         */
        ?>
        <?php if (!empty($justRequested) && $justRequested !== 'failed') { ?>
            <p class="adomin-request-note is-pending" role="status">
                <i class="fa fa-clock-o" aria-hidden="true"></i>
                <?= $justRequested === 'accepted'
                    ? 'Your acceptance has been sent. The business will record it.'
                    : 'Your refusal has been sent. The business will record it.' ?>
            </p>
        <?php } elseif ($justRequested === 'failed') { ?>
            <p class="adomin-request-note is-refused" role="alert">
                <i class="fa fa-exclamation-circle" aria-hidden="true"></i>
                That could not be sent. Nothing was recorded - please try again.
            </p>
        <?php } elseif (!empty($pendingRequest)) { ?>
            <p class="adomin-request-note is-pending" role="status">
                <i class="fa fa-clock-o" aria-hidden="true"></i>
                <?= $pendingRequest['status'] === 'accepted'
                    ? 'Your acceptance is waiting for the business to record it.'
                    : 'Your refusal is waiting for the business to record it.' ?>
            </p>
        <?php } ?>

        <?php if (!empty($canRequest) && empty($pendingRequest)) { ?>
            <form method="POST" action="<?= base_url('share/invoice/' . rawurlencode($shareToken)) ?>" class="adomin-request-form adomin-no-print">
                <?= adomin_csrf_field() ?>
                <fieldset>
                    <legend>Can this invoice be recorded as accepted?</legend>
                    <p class="adomin-request-lead">
                        This asks the business to mark the invoice. It does not
                        do so by itself.
                    </p>
                    <textarea name="reason" maxlength="500" rows="2"
                              placeholder="Anything to add (optional, used for a refusal)"></textarea>
                    <div class="adomin-request-actions">
                        <button type="submit" name="decision" value="accepted" class="btn btn-success">
                            <i class="fa fa-check-circle-o" aria-hidden="true"></i>
                            I accept this invoice
                        </button>
                        <button type="submit" name="decision" value="refused" class="btn btn-default">
                            <i class="fa fa-times-circle-o" aria-hidden="true"></i>
                            I do not accept it
                        </button>
                    </div>
                </fieldset>
            </form>
        <?php } ?>

        <div class="view-container">
            <div class="invoice-box">
                <?php
                if (is_file($templateFile)) {
                    include $templateFile;
                }
                ?>
            </div>
        </div>

        <?php /*
            One quiet line, not two blocks.

            The page carried a "create and send invoices with - anvoice" line
            AND an adomin_foot() stamp (powered-by, brand, website and the
            render time). Two credits, one above the other, on a page that is
            not ours. The powered-by line survives, at small type, and the
            render time - which told the client nothing and was never asked for
            - is gone.
        */ ?>
        <p class="adomin-share-credit">
            <?= adomin_stamp_label() ?>
        </p>
    </div>
</div>

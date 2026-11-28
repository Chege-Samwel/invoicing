<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
/*
 * Client status requests: the other half of the accept button on a share link.
 *
 * The link is unauthenticated, so what a client sends is a request. This page
 * is where somebody with an account confirms or declines it - and only a
 * confirmation moves the document's status. Declining closes the request and
 * leaves the document where it was.
 */
?>
<div class="selected-page">
    <div class="inner">
        <h1>
            <i class="fa fa-inbox" aria-hidden="true"></i>
            <?= lang('adomin_status_requests') ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?= lang_url('user') ?>"><?= lang('home') ?></a></li>
            <li><a href="<?= lang_url('user/invoices') ?>"><?= lang('invoices') ?></a></li>
            <li class="active"><?= lang('adomin_status_requests') ?></li>
        </ol>
    </div>
</div>

<div class="inner-page-menu">
    <p class="adomin-requests-lead">
        <?= lang('adomin_status_requests_help') ?>
    </p>
</div>

<div class="table-responsive">
    <table class="table table-list table-striped">
        <thead>
            <tr>
                <th><?= lang('list_inv_num') ?></th>
                <th><?= lang('adomin_request_client_asked') ?></th>
                <th><?= lang('adomin_request_reason') ?></th>
                <th><?= lang('adomin_request_when') ?></th>
                <th class="text-right"><?= lang('adomin_request_decision') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($requests)) { ?>
                <tr>
                    <td colspan="5" class="adomin-requests-empty">
                        <?php if ($available) { ?>
                            <?= lang('adomin_status_requests_none') ?>
                        <?php } else { ?>
                            <?= lang('adomin_status_requests_no_table') ?>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            <?php foreach ($requests as $request) { ?>
                <tr>
                    <td>
                        <?php $readable = isset($inv_readable_types[$request['inv_type']]) ? $inv_readable_types[$request['inv_type']] : 'invoice'; ?>
                        <a href="<?= lang_url('user/' . $readable . '/view/' . $request['inv_number']) ?>">
                            <?= html_escape($request['inv_number']) ?>
                        </a>
                        <?php if (!empty($request['client_name'])) { ?>
                            <br><small class="adomin-request-client"><?= html_escape($request['client_name']) ?></small>
                        <?php } ?>
                        <br>
                        <small><?= html_escape($request['final_total'] . ' ' . $request['inv_currency']) ?></small>
                    </td>
                    <td>
                        <?php
                        /*
                         * What the client asked for, said plainly. Not the app's
                         * own status wording: the two are different statements.
                         * A client asking to be recorded as accepting is not the
                         * invoice being accepted yet.
                         */
                        ?>
                        <span class="label <?= $request['status'] === 'accepted' ? 'label-success' : 'label-danger' ?>">
                            <?= $request['status'] === 'accepted'
                                ? lang('adomin_request_accepts')
                                : lang('adomin_request_refuses') ?>
                        </span>
                        <br>
                        <small class="text-muted">
                            <?= lang('adomin_request_current') ?>
                            <?= html_escape(isset($labels[$request['invoice_status']]) ? $labels[$request['invoice_status']] : $request['invoice_status']) ?>
                        </small>
                    </td>
                    <td>
                        <?= $request['reason'] !== ''
                            ? nl2br(html_escape($request['reason']))
                            : '<span class="text-muted">-</span>' ?>
                    </td>
                    <td>
                        <?= date('d.m.Y H:i', (int) $request['time']) ?>
                        <?php if (!empty($request['ip'])) { ?>
                            <br><small class="text-muted"><?= html_escape($request['ip']) ?></small>
                        <?php } ?>
                    </td>
                    <td class="text-right">
                        <form method="POST" action="<?= lang_url('user/invoices/requests') ?>" class="adomin-request-decision">
                            <?= adomin_csrf_field() ?>
                            <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                            <button type="submit" name="decision" value="confirm" class="btn btn-success btn-sm">
                                <i class="fa fa-check" aria-hidden="true"></i>
                                <?= lang('adomin_request_confirm') ?>
                            </button>
                            <button type="submit" name="decision" value="decline" class="btn btn-default btn-sm">
                                <i class="fa fa-times" aria-hidden="true"></i>
                                <?= lang('adomin_request_decline') ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<?php
/**
 * "Forgot your password?"
 *
 * There is no self-service reset in this app. Saying so is better than a 404
 * or a form that accepts an address and then does nothing: the person is
 * already locked out, and what they need is the next step, not a dead end.
 *
 * The link is on the sign-in page, so it has to lead somewhere.
 */
?>
<div class="av-auth">
    <div class="container">
        <div class="row">
            <div class="col-md-5 col-md-offset-1 col-md-push-6 av-auth-side">
                <div class="av-auth-brand">
                    <img src="<?= base_url('assets/public/imgs/adomin-mark.svg') ?>" alt="" width="34" height="34">
                    <span>anvoice</span>
                </div>
                <h1>Getting back in.</h1>
                <p class="av-auth-sub">Your invoices are safe. Only the way in is locked.</p>
            </div>

            <div class="col-md-5 col-md-pull-6">
                <div class="av-form">
                    <h2>Reset your password</h2>

                    <p>
                        Password resets are not automated yet. Send the address you signed up
                        with to your anvoice administrator and they will reset it for you.
                    </p>

                    <p class="av-form-foot">
                        <a href="<?= lang_url('login') ?>">Back to sign in</a>
                    </p>
                    <p class="av-form-alt">
                        No account yet? <a href="<?= lang_url('registration') ?>">Create one free</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    <p class="av-auth-foot">
        <a href="<?= base_url('pricing') ?>">Pricing</a>
        · <a href="<?= base_url('terms') ?>">Terms and conditions</a>
        · anvoice · Powered by Adomin Technologies
    </p>
</div>

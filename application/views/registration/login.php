<?php
/**
 * Sign in.
 *
 * One column, one job. The error state is shown in place, above the form, and
 * the email the visitor typed is kept so it never has to be retyped.
 */
$err = $this->session->flashdata('loginErrors');
$kept = trim((string) $this->session->flashdata('email'));
?>
<div class="av-auth">
    <div class="container">
        <div class="row">
            <div class="col-md-5 col-md-offset-1 col-md-push-6 av-auth-side">
                <div class="av-auth-brand">
                    <img src="<?= base_url('assets/public/imgs/adomin-mark.svg') ?>" alt="" width="34" height="34">
                    <span>anvoice</span>
                </div>
                <h1>Welcome back.</h1>
                <p class="av-auth-sub">Sign in to your invoices, clients and stock.</p>
                <ul class="av-auth-points">
                    <li>Your records, exactly where you left them</li>
                    <li>Signed in for three days on this device</li>
                    <li>No install, works on a phone</li>
                </ul>
            </div>

            <div class="col-md-5 col-md-pull-6">
                <form class="av-form" method="POST" action="">
                    <?= adomin_csrf_field() ?>
                    <h2>Sign in</h2>

                    <?php if ($err) { ?>
                        <p class="av-note av-note-bad" role="alert"><?= html_escape($err) ?></p>
                    <?php } elseif (isset($_GET['csrf_error'])) { ?>
                        <p class="av-note av-note-bad" role="alert">That form had expired, so nothing was sent. Try again.</p>
                    <?php } ?>

                    <label for="av-email">Email</label>
                    <input id="av-email" type="email" name="email" value="<?= html_escape($kept) ?>"
                           autocomplete="username" placeholder="yourname@domain.com" required>

                    <label for="av-pass">Password</label>
                    <input id="av-pass" type="password" name="password" autocomplete="current-password"
                           placeholder="Your password" required>

                    <button type="submit" class="av-btn">Sign in</button>

                    <p class="av-form-foot">
                        <a href="<?= lang_url('password-forgotten') ?>">Forgot your password?</a>
                    </p>
                    <p class="av-form-alt">
                        No account yet? <a href="<?= lang_url('registration') ?>">Create one free</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
    <p class="av-auth-foot">anvoice · Powered by Adomin Technologies</p>
</div>

<style>
.av-auth { --ink:#0f1419; --mut:#59636e; --line:#e4e8ee; --acc:#0b6bcb; --acc-d:#094f96;
           background:#fff; color:var(--ink); padding:56px 0 34px; min-height:82vh;
           font-family:"Open Sans",-apple-system,"Segoe UI",Helvetica,Arial,sans-serif; }
.av-auth h1 { font-size:34px; line-height:1.15; letter-spacing:-.025em; font-weight:700; margin:22px 0 10px; }
.av-auth-sub { font-size:16px; color:var(--mut); line-height:1.6; }
.av-auth-brand { display:inline-flex; align-items:center; gap:9px; font-size:20px; font-weight:700; letter-spacing:-.02em; }
.av-auth-points { list-style:none; padding:0; margin:28px 0 0; border-top:1px solid var(--line); }
.av-auth-points li { position:relative; padding:12px 0 12px 24px; border-bottom:1px solid var(--line);
                     font-size:14px; color:var(--mut); }
.av-auth-points li:before { content:""; position:absolute; left:0; top:19px; width:8px; height:8px;
                            border-radius:2px; background:var(--acc); }

.av-form { border:1px solid var(--line); border-radius:14px; padding:30px 28px;
           box-shadow:0 16px 44px rgba(12,18,25,.09); background:#fff; }
.av-form h2 { font-size:21px; font-weight:700; margin:0 0 20px; letter-spacing:-.02em; }
.av-form label { display:block; font-size:11.5px; font-weight:700; letter-spacing:.09em;
                 text-transform:uppercase; color:var(--mut); margin-bottom:6px; }
.av-form input[type=email], .av-form input[type=password] {
  width:100%; height:47px; border:1px solid var(--line); border-radius:8px; padding:0 14px;
  font-size:15px; background:#fcfdfe; color:var(--ink); margin-bottom:16px;
  transition:border-color .15s, box-shadow .15s, background .15s; }
.av-form input:focus { outline:none; border-color:var(--acc); background:#fff;
  box-shadow:0 0 0 3px rgba(11,107,203,.13); }
.av-btn { display:block; width:100%; height:48px; border:0; border-radius:8px; cursor:pointer;
          background:var(--acc); color:#fff; font-size:15px; font-weight:700;
          transition:background .16s, transform .16s, box-shadow .16s; }
.av-btn:hover { background:var(--acc-d); transform:translateY(-1px); box-shadow:0 10px 24px rgba(11,107,203,.24); }
.av-btn:active { transform:none; }
.av-form-foot { margin:14px 0 0; text-align:right; font-size:13px; }
.av-form-foot a { color:var(--mut); font-weight:600; }
.av-form-foot a:hover { color:var(--acc); }
.av-form-alt { margin:18px 0 0; padding-top:16px; border-top:1px solid var(--line);
               text-align:center; font-size:13.5px; color:var(--mut); }
.av-form-alt a { color:var(--acc); font-weight:700; }

.av-note { border-radius:8px; padding:11px 13px; font-size:13.5px; line-height:1.5; margin:0 0 18px; }
.av-note-bad { background:#fdecee; border:1px solid #f5c2c7; color:#a4232b; }

.av-auth-foot { text-align:center; font-size:12px; color:var(--mut); margin-top:34px; }

@media (max-width:991px) { .av-auth-side { margin-bottom:30px; } .av-auth h1 { font-size:28px; } }
@media (max-width:600px) { .av-auth { padding:32px 0 24px; } .av-form { padding:24px 20px; } }
@media (prefers-reduced-motion: reduce) { .av-btn, .av-form input { transition:none!important; } .av-btn:hover { transform:none; } }
</style>

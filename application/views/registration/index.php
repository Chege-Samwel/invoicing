<?php
/**
 * Registration.
 *
 * Two fields was not an onboarding experience: no name to address the account
 * by, no phone for support to reach, and a mistyped password discovered only
 * after the account was created. This asks for the three things an account
 * genuinely needs, confirms the password, and states what the account comes
 * with before anything is submitted.
 *
 * @var string $title
 */
$err = $this->session->flashdata('resultRegister');
$val = array(
    'name'  => trim((string) $this->session->flashdata('name')),
    'phone' => trim((string) $this->session->flashdata('phone')),
    'email' => trim((string) $this->session->flashdata('email')),
);
?>
<div class="av-auth av-auth-reg">

    <header class="av-reg-head">
        <div class="container">
            <nav class="av-reg-nav">
                <a class="av-auth-brand" href="<?= base_url() ?>">
                    <img src="<?= base_url('assets/public/imgs/adomin-mark.svg') ?>" alt="" width="30" height="30">
                    <span>anvoice</span>
                </a>
                <div class="av-reg-navlinks">
                    <a href="<?= base_url() ?>">Home</a>
                    <a class="av-nav-cta" href="<?= lang_url('login') ?>">Sign in</a>
                </div>
            </nav>
        </div>
    </header>

    <div class="container av-reg-body">
        <div class="row">

            <div class="col-md-6">
                <p class="av-eyebrow">Create an account</p>
                <h1 class="av-reg-h1">Create your account.</h1>
                <p class="av-auth-sub">
                    One month on Pro, free. 3000 invoices, 10 companies, no card.
                </p>

                <ul class="av-auth-points">
                    <li><strong>One month on Pro</strong> — the highest plan, from the moment you register.</li>
                    <li><strong>No card, no install</strong> — it runs in the browser and installs to your phone's home screen.</li>
                    <li><strong>Your records</strong> — clients, items and invoices, exportable whenever you want them.</li>
                </ul>

                <p class="av-reg-alt">
                    Already registered? <a href="<?= lang_url('login') ?>">Sign in instead</a>
                </p>
            </div>

            <div class="col-md-5 col-md-offset-1">
                <div class="av-form av-form-reg">
                    <?php if ($err) { ?>
                        <div class="av-note av-note-bad" role="alert"><?= html_escape($err) ?></div>
                    <?php } elseif (isset($_GET['csrf_error'])) { ?>
                        <div class="av-note av-note-bad" role="alert">
                            That form was submitted after its security token expired, so nothing was sent.
                            Please enter it once more.
                        </div>
                    <?php } ?>

                    <form av-form-inner method="POST" action="">
                        <?= adomin_csrf_field() ?>

                        <h2>Register</h2>
                        <p class="av-form-sub">Four fields, then you are in.</p>

                        <label for="reg-name">Full name</label>
                        <input id="reg-name" type="text" name="name" autocomplete="name"
                               value="<?= html_escape($val['name']) ?>" placeholder="e.g. Samwel Chege" required>

                        <label for="reg-email">Email address</label>
                        <input id="reg-email" type="email" name="email" autocomplete="email"
                               value="<?= html_escape($val['email']) ?>" placeholder="<?= lang('reg_p_email') ?>" required>

                        <label for="reg-phone">Phone</label>
                        <input id="reg-phone" type="tel" name="phone" autocomplete="tel"
                               value="<?= html_escape($val['phone']) ?>" placeholder="+254 7.. ... ...">

                        <label for="reg-pass">Password</label>
                        <input id="reg-pass" type="password" name="password" autocomplete="new-password"
                               placeholder="<?= lang('reg_p_secret') ?>" minlength="8" required>
                        <span class="av-hint">At least 8 characters.</span>

                        <label for="reg-pass2">Repeat password</label>
                        <input id="reg-pass2" type="password" name="password_repeat" autocomplete="new-password"
                               placeholder="<?= lang('reg_p_secret2') ?>" minlength="8" required>

                        <button type="submit" class="av-btn"><?= lang('register_me') ?></button>

                        <p class="av-terms">
                            By registering you accept that your records are stored on our servers
                            and can be exported by you at any time.
                        </p>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<style>
.av-auth { --ink:#0f1419; --mut:#59636e; --line:#e4e8ee; --acc:#0b6bcb; --acc-d:#094f96;
           background:#fff; color:var(--ink); min-height:100vh;
           font-family:"Open Sans",-apple-system,"Segoe UI",Helvetica,Arial,sans-serif; }
.av-auth h1, .av-auth h2, .av-auth p { margin:0; }
.av-auth a { text-decoration:none; }

.av-reg-head { border-bottom:1px solid var(--line); background:#fff; }
.av-reg-nav { display:flex; align-items:center; justify-content:space-between; height:64px; }
.av-auth-brand { display:inline-flex; align-items:center; gap:9px; font-size:19px; font-weight:700;
                 letter-spacing:-.02em; color:var(--ink); }
.av-reg-navlinks { display:flex; align-items:center; gap:20px; }
.av-reg-navlinks a { font-size:14.5px; font-weight:600; color:var(--mut); }
.av-reg-navlinks a:hover { color:var(--ink); }
.av-nav-cta { background:var(--acc); color:#fff!important; padding:9px 16px; border-radius:7px;
              transition:background .15s, transform .15s; }
.av-nav-cta:hover { background:var(--acc-d); transform:translateY(-1px); }

.av-reg-body { padding:52px 0 70px; }
.av-reg-h1 { font-size:clamp(26px,3.4vw,36px); line-height:1.15; letter-spacing:-.025em;
             font-weight:700; margin:6px 0 14px; }
.av-eyebrow { font-size:11px; font-weight:700; letter-spacing:.12em; text-transform:uppercase;
              color:var(--acc); }
.av-auth-sub { font-size:16px; color:var(--mut); line-height:1.6; max-width:420px; }

.av-auth-points { list-style:none; padding:0; margin:26px 0 0; border-top:1px solid var(--line); }
.av-auth-points li { position:relative; padding:13px 0 13px 24px; border-bottom:1px solid var(--line);
                     font-size:14px; color:var(--mut); line-height:1.55; }
.av-auth-points li:before { content:""; position:absolute; left:0; top:20px; width:8px; height:8px;
                            border-radius:2px; background:var(--acc); }
.av-auth-points strong { color:var(--ink); }
.av-reg-alt { font-size:13.5px; color:var(--mut); margin-top:24px; }
.av-reg-alt a { color:var(--acc); font-weight:700; }

.av-form { border:1px solid var(--line); border-radius:14px; padding:30px 28px;
           box-shadow:0 16px 44px rgba(12,18,25,.09); background:#fff; }
.av-form h2 { font-size:21px; font-weight:700; letter-spacing:-.02em; margin:0 0 4px; }
.av-form-sub { font-size:13.5px; color:var(--mut); margin:0 0 20px; }
.av-form label { display:block; font-size:11.5px; font-weight:700; letter-spacing:.09em;
                 text-transform:uppercase; color:var(--mut); margin-bottom:6px; }
.av-form input[type=text], .av-form input[type=email], .av-form input[type=tel],
.av-form input[type=password] {
  width:100%; height:47px; border:1px solid var(--line); border-radius:8px; padding:0 14px;
  font-size:15px; background:#fcfdfe; color:var(--ink); margin-bottom:15px;
  transition:border-color .15s, box-shadow .15s, background .15s; }
.av-form input:focus { outline:none; border-color:var(--acc); background:#fff;
  box-shadow:0 0 0 3px rgba(11,107,203,.13); }
.av-hint { display:block; margin:-9px 0 15px; font-size:12px; color:var(--mut); }
.av-btn { display:block; width:100%; height:48px; border:0; border-radius:8px; cursor:pointer;
          background:var(--acc); color:#fff; font-size:15px; font-weight:700; margin-top:4px;
          transition:background .16s, transform .16s, box-shadow .16s; }
.av-btn:hover { background:var(--acc-d); transform:translateY(-1px); box-shadow:0 10px 24px rgba(11,107,203,.24); }
.av-btn:active { transform:none; }
.av-note { border-radius:8px; padding:11px 13px; font-size:13.5px; line-height:1.5; margin:0 0 18px; }
.av-note-bad { background:#fdecee; border:1px solid #f5c2c7; color:#a4232b; }
.av-terms { margin:16px 0 0; font-size:11.5px; color:var(--mut); line-height:1.5; text-align:center; }

@media (max-width:991px) { .av-form { margin-top:30px; } }
@media (max-width:600px) { .av-reg-body { padding:32px 0 50px; } .av-form { padding:24px 20px; } }
@media (prefers-reduced-motion: reduce) { .av-btn, .av-form input { transition:none!important; } .av-btn:hover { transform:none; } }
</style>

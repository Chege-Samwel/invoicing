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
<div class="lp lp-reg-wrap">

    <header class="lp-reg-head">
        <div class="container">
            <nav class="lp-nav">
                <a class="lp-brand" href="<?= base_url() ?>">
                    <img src="<?= base_url('assets/public/imgs/adomin-mark.svg') ?>" alt="" width="30" height="30">
                    <span>Adomin<small>Technologies</small></span>
                </a>
                <div class="lp-nav-links">
                    <a href="<?= base_url() ?>">Home</a>
                    <a class="lp-nav-cta" href="<?= lang_url('login') ?>">Sign in</a>
                </div>
            </nav>
        </div>
    </header>

    <div class="container lp-reg-body">
        <div class="row">

            <div class="col-md-6">
                <p class="lp-eyebrow">Create an account</p>
                <h1 class="lp-reg-h1">Start issuing invoices<br>in the next few minutes.</h1>
                <p class="lp-lede">
                    Your account opens on the Pro plan for one month: up to 3000 invoices,
                    10 companies, stock, warranty and employee accounts. No card.
                </p>

                <ul class="lp-reg-incl">
                    <li><strong>One month on Pro</strong> — the highest plan, from the moment you register.</li>
                    <li><strong>No card, no install</strong> — it runs in the browser and installs to your phone's home screen.</li>
                    <li><strong>Your records</strong> — clients, items and invoices, exportable whenever you want them.</li>
                </ul>

                <p class="lp-reg-alt">
                    Already registered? <a href="<?= lang_url('login') ?>">Sign in instead</a>
                </p>
            </div>

            <div class="col-md-5 col-md-offset-1">
                <div class="lp-access">
                    <?php if ($err) { ?>
                        <div class="lp-reg-err" role="alert"><?= html_escape($err) ?></div>
                    <?php } elseif (isset($_GET['csrf_error'])) { ?>
                        <div class="lp-reg-err" role="alert">
                            That form was submitted after its security token expired, so nothing was sent.
                            Please enter it once more.
                        </div>
                    <?php } ?>

                    <form class="lp-form lp-reg-form" method="POST" action="">
                        <?= adomin_csrf_field() ?>

                        <h3>Register</h3>
                        <p class="lp-form-sub">Three details, then you are in.</p>

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
                        <span class="lp-reg-hint">At least 8 characters.</span>

                        <label for="reg-pass2">Repeat password</label>
                        <input id="reg-pass2" type="password" name="password_repeat" autocomplete="new-password"
                               placeholder="<?= lang('reg_p_secret2') ?>" minlength="8" required>

                        <button type="submit" class="lp-btn lp-btn-primary lp-btn-block"><?= lang('register_me') ?></button>

                        <p class="lp-reg-terms">
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
.lp { --lp-ink:#12171d; --lp-mut:#5c6672; --lp-line:#e4e8ee; --lp-acc:#0b6bcb; --lp-acc-d:#094f96;
      color:var(--lp-ink); font-family:"Open Sans",Helvetica,Arial,sans-serif; }
.lp-reg-wrap { background:#fff; min-height:100vh; }
.lp-reg-head { background:linear-gradient(168deg,#f7fafe,#eef4fb); border-bottom:1px solid var(--lp-line); }
.lp-reg-body { padding:56px 0 80px; }
.lp-reg-h1 { font-size:36px; line-height:1.15; font-weight:700; letter-spacing:-.02em; margin:6px 0 16px; }

.lp-eyebrow { text-transform:uppercase; letter-spacing:.14em; font-size:11.5px; font-weight:700;
              color:var(--lp-acc); margin:0 0 8px; }
.lp-lede { font-size:16px; color:var(--lp-mut); line-height:1.65; max-width:460px; }

.lp-reg-incl { list-style:none; padding:0; margin:28px 0 0; border-top:1px solid var(--lp-line); }
.lp-reg-incl li { position:relative; padding:14px 0 14px 26px; border-bottom:1px solid var(--lp-line);
                  font-size:14.5px; color:var(--lp-mut); line-height:1.55; }
.lp-reg-incl li:before { content:""; position:absolute; left:0; top:21px; width:9px; height:9px;
                         border-radius:2px; background:var(--lp-acc); }
.lp-reg-incl strong { color:var(--lp-ink); }
.lp-reg-alt { font-size:13.5px; margin-top:26px; color:var(--lp-mut); }
.lp-reg-alt a { color:var(--lp-acc); font-weight:600; }

.lp-access { background:#fff; border:1px solid var(--lp-line); border-radius:13px;
             padding:30px 28px; box-shadow:0 14px 40px rgba(16,25,40,.09); }
.lp-access h3 { font-size:21px; font-weight:700; margin:0 0 4px; }
.lp-form-sub { font-size:13.5px; color:var(--lp-mut); margin-bottom:20px; }

.lp-form label { display:block; font-size:11.5px; font-weight:700; letter-spacing:.09em;
                 text-transform:uppercase; color:var(--lp-mut); margin-bottom:6px; }
.lp-form input[type=text], .lp-form input[type=email], .lp-form input[type=tel], .lp-form input[type=password] {
    width:100%; height:46px; border:1px solid var(--lp-line); border-radius:7px;
    padding:0 14px; font-size:15px; margin-bottom:15px; background:#fcfdfe; color:var(--lp-ink); }
.lp-form input:focus { outline:none; border-color:var(--lp-acc);
    box-shadow:0 0 0 3px rgba(11,107,203,.12); background:#fff; }
.lp-reg-hint { display:block; margin:-10px 0 15px; font-size:12px; color:var(--lp-mut); }

.lp-btn { display:inline-block; padding:13px 26px; border-radius:7px; font-size:14.5px;
          font-weight:700; text-decoration:none; text-align:center; cursor:pointer;
          border:1px solid transparent; transition:background .12s,transform .12s; }
.lp-btn-primary { background:var(--lp-acc); color:#fff; box-shadow:0 6px 18px rgba(11,107,203,.22); }
.lp-btn-primary:hover { background:var(--lp-acc-d); color:#fff; transform:translateY(-1px); }
.lp-btn-block { display:block; width:100%; }

.lp-reg-err { background:#fdecee; border:1px solid #f5c2c7; color:#a4232b; border-radius:7px;
              padding:11px 14px; font-size:13.5px; line-height:1.5; margin-bottom:18px; }
.lp-reg-terms { font-size:11.5px; color:var(--lp-mut); margin:16px 0 0; line-height:1.5; text-align:center; }

.lp-nav { display:flex; align-items:center; justify-content:space-between;
          padding:18px 0; flex-wrap:wrap; gap:12px; }
.lp-brand { display:inline-flex; align-items:center; gap:9px; text-decoration:none;
            color:var(--lp-ink); font-size:19px; font-weight:700; letter-spacing:-.02em; }
.lp-brand small { display:block; font-size:10.5px; font-weight:600; letter-spacing:.16em;
                  text-transform:uppercase; color:var(--lp-mut); }
.lp-nav-links { display:flex; align-items:center; gap:20px; }
.lp-nav-links a { color:var(--lp-mut); text-decoration:none; font-size:14px; font-weight:600; }
.lp-nav-cta { padding:9px 18px; border:1px solid var(--lp-acc); border-radius:6px; color:var(--lp-acc)!important; }
.lp-nav-cta:hover { background:var(--lp-acc); color:#fff!important; }

@media (max-width:991px) { .lp-access { margin-top:32px; } }
@media (max-width:600px) { .lp-reg-h1 { font-size:27px; } .lp-reg-body { padding:34px 0 60px; } }
</style>

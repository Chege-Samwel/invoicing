<?php
/**
 * Landing page.
 *
 * Deliberately short: what it is, what it costs, what it does, and a way in.
 * If a line does not help someone decide or get started, it is not here.
 *
 * Images are WebP with a 24px placeholder behind each, so the first paint is
 * instant on a slow connection and the real file fades in over it.
 *
 * @var string $title
 */
$img = 'assets/public/imgs/landing/';
$signedIn = isset($_SESSION['user_login']);
/*
 * A photo here is drawn about 500px wide. Serving the 1400px original meant
 * every visitor downloaded roughly three times the pixels they could see, so
 * the browser is given the widths that exist and picks one. The 24px file sits
 * behind it, so the space is never empty while the real file arrives.
 */
$box = function ($file, $alt, $class = '', $eager = FALSE) use ($img) {
    $srcset = array();
    foreach (array(700, 1000) as $width) {
        $srcset[] = base_url($img . $file . '-' . $width . '.webp') . ' ' . $width . 'w';
    }
    $srcset[] = base_url($img . $file . '.webp') . ' 1400w';

    return '<span class="lp-media ' . $class . '" style="background-image:url(' . base_url($img . $file . '-tiny.webp') . ')">'
        . '<img src="' . base_url($img . $file . '-700.webp') . '"'
        . ' srcset="' . implode(', ', $srcset) . '"'
        . ' sizes="(max-width: 767px) 92vw, 500px"'
        . ' width="1400" height="764"'
        . ' alt="' . html_escape($alt) . '"'
        . ($eager ? ' fetchpriority="high" decoding="sync"' : ' loading="lazy" decoding="async"')
        . '>'
        . '</span>';
};
$firm = '';
if ($signedIn && function_exists('adomin_conf')) {
    $firm = trim((string) adomin_conf('brand', 'company'));
}
?>
<div class="lp">

    <header class="lp-head">
        <div class="lp-head-row lp-head-wide">
            <a class="lp-logo" href="<?= base_url() ?>">
                <img src="<?= base_url('assets/public/imgs/adomin-mark.svg') ?>" alt="" width="28" height="28">
                anvoice
            </a>
            <nav class="lp-links">
                <a href="#price">Price</a>
                <?php if ($signedIn) { ?>
                    <a class="lp-cta" href="<?= lang_url('user') ?>">Dashboard</a>
                <?php } else { ?>
                    <a href="<?= lang_url('login') ?>">Sign in</a>
                    <a class="lp-cta" href="<?= lang_url('registration') ?>">Start free</a>
                <?php } ?>
            </nav>
        </div>
    </header>

    <section class="lp-hero">
        <div class="container">
            <div class="row lp-hero-row">
                <div class="col-md-6">
                    <h1>Invoice, stock and records in one place.</h1>
                    <p class="lp-lede">Issue an invoice in a minute, keep the client and
                        the items beside it, and let the stock and warranty records follow
                        the same document.</p>

                    <div class="lp-actions">
                        <?php if ($signedIn) { ?>
                            <a class="lp-btn" href="<?= lang_url('user') ?>">Open dashboard</a>
                            <a class="lp-btn lp-btn-quiet" href="<?= lang_url('user/new/invoice') ?>">New invoice</a>
                        <?php } else { ?>
                            <a class="lp-btn" href="<?= lang_url('registration') ?>">Start free</a>
                            <a class="lp-btn lp-btn-quiet" href="<?= lang_url('login') ?>">Sign in</a>
                        <?php } ?>
                    </div>

                    <dl class="lp-facts">
                        <div><dt>Free for</dt><dd>1 month on Pro</dd></div>
                        <div><dt>Card needed</dt><dd>No</dd></div>
                        <div><dt>Setup</dt><dd>Under a minute</dd></div>
                        <div><dt>Currency</dt><dd>KES, and others</dd></div>
                    </dl>
                </div>
                <div class="col-md-6">
                    <?= $box('hero', 'A business owner working through invoices at her desk', 'lp-hero-media is-first', TRUE) ?>
                </div>
            </div>
        </div>
    </section>

    <section class="lp-strip" id="what">
        <div class="container">
            <ul class="lp-strip-list">
                <li><strong>Invoices</strong>Issue, print, export, share</li>
                <li><strong>Clients</strong>Contact and document history</li>
                <li><strong>Items</strong>Catalogue, price, unit</li>
                <li><strong>Stock</strong>Movements, running quantity</li>
                <li><strong>Warranty</strong>Cover and dated events</li>
                <li><strong>Reports</strong>Totals from issued documents</li>
            </ul>
        </div>
    </section>

    <section class="lp-steps" id="how">
        <div class="container">
            <h2>Three steps, then the record keeps itself.</h2>
            <ul class="lp-step-list">
                <li>
                    <span class="lp-step-n">1</span>
                    <h3>Add your company and your first client</h3>
                    <p>Your details go on the document; the client's go on the record.
                       Both stay where the next invoice can reach them.</p>
                </li>
                <li>
                    <span class="lp-step-n">2</span>
                    <h3>Issue the invoice</h3>
                    <p>Pick the client, add the items, and the totals, tax and numbering
                       are worked out for you. Print it, download the PDF, or send a link.</p>
                </li>
                <li>
                    <span class="lp-step-n">3</span>
                    <h3>Mark it as it is paid</h3>
                    <p>Paid, unpaid or partly paid. Every change is kept against the
                       invoice, so what you owe and what you are owed never needs a guess.</p>
                </li>
            </ul>
        </div>
    </section>

    <section class="lp-price" id="price">
        <div class="container">
            <div class="row">
                <div class="col-md-5">
                    <h2>Free to start. <?= html_escape($homePricing['PRO']['formatted']) ?> a month when you outgrow it.</h2>
                    <p>
                        Pro comes free for <?= (int) $homePricing['trial_days'] ?> days at signup - no card.
                        After that, stay on Free, or pay for Pro.
                    </p>
                    <?php if (!$signedIn) { ?>
                        <a class="lp-btn" href="<?= lang_url('registration') ?>">Start free</a>
                        <a class="lp-btn lp-btn-quiet" href="<?= base_url('pricing') ?>">See all plans</a>
                    <?php } ?>
                </div>
                <div class="col-md-6 col-md-offset-1">
                    <?php /*
                     * This table used to advertise Basic / Pro / Custom with
                     * "10 companies" - tiers and numbers that existed in no
                     * config and could not be bought. It now reads the same
                     * three plans the pricing page does, so the front door and
                     * the price list cannot disagree.
                     */ ?>
                    <table class="lp-plans">
                        <tr>
                            <th>Free</th>
                            <th class="lp-plans-lead">Pro <span><?= (int) $homePricing['trial_days'] ?> days free</span></th>
                            <th>Business</th>
                        </tr>
                        <tr>
                            <td><?= html_escape($homePricing['FREE']['formatted']) ?></td>
                            <td class="lp-plans-lead"><?= html_escape($homePricing['PRO']['formatted']) ?> / month</td>
                            <td>Let's talk</td>
                        </tr>
                        <tr>
                            <td>5 invoices a month</td>
                            <td class="lp-plans-lead">Unlimited invoices</td>
                            <td>Unlimited, several businesses</td>
                        </tr>
                        <tr>
                            <td>Our mark on the footer</td>
                            <td class="lp-plans-lead">Your logo and colours</td>
                            <td>White-label, own domain</td>
                        </tr>
                        <tr>
                            <td>Paid / unpaid only</td>
                            <td class="lp-plans-lead">Read receipts, charts</td>
                            <td>Time, expenses, reconciliation</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section class="lp-band" id="questions">
        <div class="container">
            <div class="row lp-band-row">
                <div class="col-sm-6">
                    <h2>Questions worth asking before you start.</h2>
                    <dl class="lp-faq">
                        <dt>What happens after the free month?</dt>
                        <dd>You choose a plan. Nothing is charged before you choose one, and no card is taken at signup.</dd>

                        <dt>Can I keep my own numbering?</dt>
                        <dd>The system assigns the number, which is what stops gaps and duplicates. Your company details, currency and template are yours to set.</dd>

                        <dt>What can I get out?</dt>
                        <dd>Every document can be printed or downloaded as a PDF, and the full record can be exported whenever you want it.</dd>

                        <dt>Does it work on a phone?</dt>
                        <dd>It runs in the browser and installs to a home screen. There is nothing to download and no local database to keep in step.</dd>
                    </dl>
                </div>
                <div class="col-sm-5 col-sm-offset-1">
                    <?= $box('team', 'Two colleagues reviewing an invoice together') ?>
                </div>
            </div>
        </div>
    </section>

    <footer class="lp-foot">
        <div class="container lp-foot-row">
            <span class="lp-foot-brand">anvoice</span>
            <span><?= $firm !== '' ? 'Powered by ' . html_escape($firm) : 'Powered by Adomin Technologies' ?></span>
            <span class="lp-foot-links">
                <?php if ($signedIn) { ?>
                    <a href="<?= lang_url('user') ?>">Dashboard</a>
                <?php } else { ?>
                    <a href="<?= lang_url('registration') ?>">Start free</a>
                    <a href="<?= lang_url('login') ?>">Sign in</a>
                <?php } ?>
            </span>
        </div>
    </footer>
</div>

<style>
/* ── landing ──────────────────────────────────────────────────────────── */
.lp { --ink:#0f1419; --mut:#59636e; --line:#e4e8ee; --acc:#0b6bcb; --acc-d:#094f96;
      --soft:#f6f8fb; --dark:#0c1219;
      background:#fff; color:var(--ink);
      font-family:"Open Sans",-apple-system,"Segoe UI",Helvetica,Arial,sans-serif;
      -webkit-font-smoothing:antialiased; }
.lp h1,.lp h2,.lp p,.lp dl,.lp ul { margin:0; }
.lp a { text-decoration:none; }
.lp .container { max-width:1080px; }

/* header */
.lp-head { border-bottom:1px solid var(--line); background:#fff; position:sticky; top:0; z-index:20; }
.lp-head-row { display:flex; align-items:center; justify-content:space-between; height:64px; }
/*
 * The bar spans the window, not the readable column. Inside a fixed-width
 * .container the two ends of the bar sat at the container's edges, so on a
 * wide screen the brand and the actions both appeared to be pulled toward the
 * middle with empty page either side of them.
 */
.lp-head-wide { width:100%; max-width:none; margin:0; padding:0 32px; }
@media (max-width:600px) { .lp-head-wide { padding:0 18px; } }
.lp-logo { display:inline-flex; align-items:center; gap:8px; font-size:19px; font-weight:700;
           letter-spacing:-.02em; color:var(--ink); }
.lp-links { display:flex; align-items:center; gap:22px; }
.lp-links a { font-size:14.5px; font-weight:600; color:var(--mut); transition:color .15s; }
.lp-links a:hover { color:var(--ink); }
.lp-cta { background:var(--acc); color:#fff!important; padding:9px 16px; border-radius:7px;
          transition:background .15s, transform .15s; }
.lp-cta:hover { background:var(--acc-d); transform:translateY(-1px); }

/* hero */
.lp-lede { margin:18px 0 0; max-width:44ch; font-size:16.5px; line-height:1.6; color:var(--mut); }
.lp-hero { padding:64px 0 56px; }
.lp-hero h1 { font-size:clamp(30px,4vw,46px); line-height:1.1; letter-spacing:-.03em; font-weight:700; }
.lp-actions { margin-top:26px; display:flex; gap:10px; flex-wrap:wrap; }
.lp-btn { display:inline-block; padding:13px 24px; border-radius:8px; font-size:15px; font-weight:700;
          background:var(--acc); color:#fff; border:1px solid var(--acc);
          transition:background .16s, transform .16s, box-shadow .16s; }
.lp-btn:hover { background:var(--acc-d); transform:translateY(-2px); box-shadow:0 10px 24px rgba(11,107,203,.24); color:#fff; }
.lp-btn-quiet { background:#fff; color:var(--ink); border-color:var(--line); }
.lp-btn-quiet:hover { background:#fff; color:var(--acc); border-color:var(--acc); box-shadow:none; }

.lp-facts { display:grid; grid-template-columns:1fr 1fr; gap:1px; margin-top:34px;
            background:var(--line); border:1px solid var(--line); border-radius:10px; overflow:hidden; }
.lp-facts > div { background:#fff; padding:13px 15px; }
.lp-facts dt { font-size:11px; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--mut); }
.lp-facts dd { margin:4px 0 0; font-size:15px; font-weight:700; color:var(--ink); }

/* media box */
.lp-media { display:block; position:relative; border-radius:14px; overflow:hidden;
            background-size:cover; background-position:center;
            box-shadow:0 18px 44px rgba(12,18,25,.14); aspect-ratio:4/3; }
.lp-media img { width:100%; height:100%; object-fit:cover; display:block; opacity:0;
                transition:opacity .6s ease; }
.lp-media.is-first img { opacity:1; }   /* above the fold: no fade, no delay */
.lp-media img.adomin-loaded, .lp-media img[complete] { opacity:1; }
.lp-hero-media { aspect-ratio:5/4; }
@media (max-width:991px) { .lp-hero-media { margin-top:34px; } }

/* capability strip */
.lp-strip { background:var(--soft); border-top:1px solid var(--line); border-bottom:1px solid var(--line); padding:34px 0; }
.lp-strip-list { list-style:none; padding:0; display:grid; grid-template-columns:repeat(3,1fr); gap:18px 30px; }
.lp-strip-list li { font-size:13.5px; color:var(--mut); line-height:1.5; }
.lp-strip-list strong { display:block; font-size:14.5px; color:var(--ink); margin-bottom:2px; }

/* steps */
.lp-steps { padding:56px 0 20px; border-top:1px solid var(--line); }
.lp-steps h2 { font-size:clamp(22px,2.8vw,30px); letter-spacing:-.02em; line-height:1.2; }
.lp-step-list { list-style:none; display:grid; grid-template-columns:repeat(3,minmax(0,1fr));
                gap:22px; margin:26px 0 0; padding:0; }
.lp-step-list li { position:relative; padding-top:6px; }
.lp-step-n { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px;
             border-radius:50%; background:var(--acc); color:#fff; font-size:13px; font-weight:700; }
.lp-step-list h3 { margin:12px 0 0; font-size:16px; line-height:1.35; letter-spacing:-.01em; }
.lp-step-list p { margin:7px 0 0; font-size:14px; line-height:1.6; color:var(--mut); }
@media (max-width:767px) { .lp-step-list { grid-template-columns:1fr; gap:18px; } }

/* questions */
.lp-faq { margin:20px 0 0; }
.lp-faq dt { font-size:15px; font-weight:700; color:var(--ink); margin-top:16px; }
.lp-faq dt:first-child { margin-top:0; }
.lp-faq dd { margin:5px 0 0; font-size:14px; line-height:1.6; color:var(--mut); }

/* price */
.lp-price { padding:64px 0; }
.lp-price h2 { font-size:clamp(24px,3vw,32px); letter-spacing:-.02em; line-height:1.2; }
.lp-price p { margin:12px 0 22px; color:var(--mut); font-size:15.5px; line-height:1.6; max-width:400px; }

.lp-plans { width:100%; border-collapse:collapse; font-size:14px; }
.lp-plans th, .lp-plans td { padding:13px 14px; text-align:left; border-bottom:1px solid var(--line); }
.lp-plans th { font-size:15px; font-weight:700; }
.lp-plans th span { display:block; font-size:10.5px; font-weight:700; letter-spacing:.08em;
                    text-transform:uppercase; color:var(--acc); margin-top:3px; }
.lp-plans td { color:var(--mut); }
.lp-plans-lead { background:#f2f8ff; color:var(--ink)!important; font-weight:600; }
.lp-plans th.lp-plans-lead { border-radius:8px 8px 0 0; }
.lp-plans tr:last-child td.lp-plans-lead { border-radius:0 0 8px 8px; }

/* band */
.lp-band { padding:52px 0 64px; border-top:1px solid var(--line); }
.lp-band h2 { font-size:clamp(21px,2.6vw,28px); letter-spacing:-.02em; line-height:1.25; }
.lp-band-row { display:flex; align-items:center; }
.lp-band-row > div { float:none; }
.lp-band-copy { padding-left:38px; }
.lp-band-list { list-style:none; padding:0; margin:0; }
.lp-band-list li { font-size:15px; }
.lp-band-list li { position:relative; padding-left:20px; margin-bottom:9px; font-size:14px; color:var(--mut); }
.lp-band-list li:before { content:""; position:absolute; left:0; top:7px; width:7px; height:7px;
                          border-radius:2px; background:var(--acc); }
@media (max-width:991px) { .lp-band-copy { padding:28px 0 0; } }

/* footer */
.lp-foot { border-top:1px solid var(--line); padding:22px 0; }
.lp-foot-row { display:flex; align-items:center; justify-content:space-between; gap:14px;
               flex-wrap:wrap; font-size:13px; color:var(--mut); }
.lp-foot-brand { font-weight:700; color:var(--ink); font-size:15px; }
.lp-foot-links a { color:var(--mut); font-weight:600; margin-left:16px; }
.lp-foot-links a:hover { color:var(--acc); }

@media (max-width:600px) {
  .lp-hero { padding:40px 0 36px; }
  .lp-strip-list { grid-template-columns:1fr 1fr; }
  .lp-price { padding:44px 0; }
  .lp-links { gap:14px; }
  .lp-links a:not(.lp-cta) { display:none; }
  .lp-plans th, .lp-plans td { padding:10px 8px; font-size:13px; }
}
@media (prefers-reduced-motion: reduce) {
  .lp-btn, .lp-cta, .lp-media img { transition:none!important; }
  .lp-btn:hover, .lp-cta:hover { transform:none; }
}
</style>

<script>
/* fade each image in once it has decoded, over its placeholder */
(function () {
    var imgs = document.querySelectorAll('.lp-media img');
    for (var i = 0; i < imgs.length; i++) {
        (function (img) {
            if (img.complete) { img.classList.add('adomin-loaded'); return; }
            img.addEventListener('load', function () { img.classList.add('adomin-loaded'); });
            img.addEventListener('error', function () { img.classList.add('adomin-loaded'); });
        })(imgs[i]);
    }
})();
</script>

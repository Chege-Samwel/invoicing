<?php
/**
 * Public landing page.
 *
 * Direct statements only: definition, aims, industry practice, plans,
 * engagement. The sign-in form stays on the page so a returning user does not
 * have to hunt for it.
 *
 * Every section is written to be readable with no JavaScript and no motion:
 * the reveal classes start visible and are only hidden by the stylesheet when
 * body.adomin-motion is present, so nothing depends on the observer running.
 *
 * @var string $title
 */
$img = 'assets/public/imgs/landing/';
?>
<div class="lp">

    <!-- ── hero ─────────────────────────────────────────────────────────── -->
    <header class="lp-hero">
        <div class="lp-hero-media">
            <img class="lp-hero-img" src="<?= base_url($img . 'hero.jpg') ?>"
                 alt="A business owner reviewing invoices at her desk in Nairobi" fetchpriority="high">
            <span class="lp-hero-scrim"></span>
        </div>

        <div class="lp-hero-inner">
            <nav class="lp-nav">
                <a class="lp-brand" href="<?= base_url() ?>">
                    <img src="<?= base_url('assets/public/imgs/adomin-mark.svg') ?>" alt="" width="30" height="30">
                    <span>Adomin<small>Technologies</small></span>
                </a>
                <div class="lp-nav-links">
                    <a href="#aims">Aims</a>
                    <a href="#practice">Practice</a>
                    <a href="#plans">Plans</a>
                    <a href="#engagement">Clients</a>
                    <a class="lp-nav-cta" href="#access">Sign in</a>
                </div>
            </nav>

            <div class="lp-hero-copy">
                <p class="lp-eyebrow">Invoicing and record-keeping for East African business</p>
                <h1>
                    Every invoice, client and payment<br>
                    <em>on one record.</em>
                </h1>
                <p class="lp-lede">
                    Adomin is a browser-based invoicing system. It issues the invoice,
                    keeps the client file, tracks what is owed, and holds the warranty and
                    stock records that usually live in three separate places.
                </p>

                <dl class="lp-defs">
                    <div>
                        <dt>Invoice</dt>
                        <dd>A numbered, dated claim for payment, issued from your own company details.</dd>
                    </div>
                    <div>
                        <dt>Ledger</dt>
                        <dd>Every document you have issued, searchable, exportable, and print-ready.</dd>
                    </div>
                    <div>
                        <dt>Record</dt>
                        <dd>The client, item, warranty and movement history behind each document.</dd>
                    </div>
                </dl>

                <div class="lp-hero-actions">
                    <a class="lp-btn lp-btn-primary" href="<?= lang_url('registration') ?>">Open an account</a>
                    <a class="lp-btn lp-btn-ghost" href="#plans">See what is included</a>
                </div>

                <p class="lp-note">First month on the highest plan, free. No card to start.</p>
            </div>
        </div>

        <div class="lp-hero-stats">
            <div class="lp-stat">
                <span class="lp-stat-v" data-lp-count="3000">3000</span>
                <span class="lp-stat-k">Invoices in the first month</span>
            </div>
            <div class="lp-stat">
                <span class="lp-stat-v" data-lp-count="10">10</span>
                <span class="lp-stat-k">Companies per account</span>
            </div>
            <div class="lp-stat">
                <span class="lp-stat-v" data-lp-count="9">9</span>
                <span class="lp-stat-k">Connected record types</span>
            </div>
            <div class="lp-stat">
                <span class="lp-stat-v" data-lp-count="0" data-lp-suffix=" min">0 min</span>
                <span class="lp-stat-k">To install</span>
            </div>
        </div>
    </header>

    <!-- ── aims ─────────────────────────────────────────────────────────── -->
    <section class="lp-section lp-reveal" id="aims">
        <div class="container">
            <p class="lp-eyebrow">Aims</p>
            <h2>Four things this system is built to do</h2>

            <div class="row lp-aims">
                <div class="col-sm-6 lp-stagger">
                    <article class="lp-aim">
                        <span class="lp-aim-n">01</span>
                        <h3>Issue a document in one pass</h3>
                        <p>Company details, client, items, tax and totals on a single screen. Nothing is retyped from a template.</p>
                    </article>
                </div>
                <div class="col-sm-6 lp-stagger">
                    <article class="lp-aim">
                        <span class="lp-aim-n">02</span>
                        <h3>Keep one record, not five</h3>
                        <p>Clients, items, stock movements, warranties and protocols sit beside the invoices they belong to.</p>
                    </article>
                </div>
                <div class="col-sm-6 lp-stagger">
                    <article class="lp-aim">
                        <span class="lp-aim-n">03</span>
                        <h3>Make the numbers answerable</h3>
                        <p>Reports draw on what was actually issued, so a total can be traced back to the document that produced it.</p>
                    </article>
                </div>
                <div class="col-sm-6 lp-stagger">
                    <article class="lp-aim">
                        <span class="lp-aim-n">04</span>
                        <h3>Work from a phone</h3>
                        <p>The interface is served in the browser and installs to a home screen. No desktop, no download, no local database.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <!-- ── capability ───────────────────────────────────────────────────── -->
    <section class="lp-section lp-section-tint lp-reveal" id="practice">
        <div class="container">
            <p class="lp-eyebrow">Practice</p>
            <h2>What the system holds</h2>
            <p class="lp-sub">Nine record types, connected. Each one is a working module, not a menu item.</p>

            <div class="lp-grid">
                <div class="lp-feat lp-stagger"><h3>Invoices</h3><p>Issue, list, print and export. Quotes and receipts share the same numbering.</p></div>
                <div class="lp-feat lp-stagger"><h3>Clients</h3><p>Company and contact details, with the full document history attached to each.</p></div>
                <div class="lp-feat lp-stagger"><h3>Items</h3><p>Catalogue, price and unit of measure, drawn on when an invoice is written.</p></div>
                <div class="lp-feat lp-stagger"><h3>Stock</h3><p>Movements in and out, with running quantities per item.</p></div>
                <div class="lp-feat lp-stagger"><h3>Warranty</h3><p>Cover per item sold, with dated events against each record.</p></div>
                <div class="lp-feat lp-stagger"><h3>Protocols</h3><p>Service and installation records, referenced from the invoice.</p></div>
                <div class="lp-feat lp-stagger"><h3>Reports</h3><p>Totals by period, client and item, taken from issued documents.</p></div>
                <div class="lp-feat lp-stagger"><h3>Companies</h3><p>Several issuing entities under one account, each with its own details.</p></div>
                <div class="lp-feat lp-stagger"><h3>Employees</h3><p>Staff accounts with per-section permissions, so not everyone can void an invoice.</p></div>
            </div>
        </div>
    </section>

    <!-- ── band: stock ──────────────────────────────────────────────────── -->
    <section class="lp-band lp-reveal">
        <img class="lp-band-img lp-settle" src="<?= base_url($img . 'warehouse.jpg') ?>"
             alt="Stock on shelving in a small warehouse" loading="lazy">
        <div class="lp-band-copy">
            <p class="lp-eyebrow">Stock and warranty</p>
            <h2>The shelf and the paperwork agree</h2>
            <p>
                A movement records what left the store and which document sent it.
                Warranty cover follows the item, not a notebook entry made weeks later.
            </p>
            <ul class="lp-band-list">
                <li>Movements in and out, with a running quantity per item</li>
                <li>Warranty events dated against the record they belong to</li>
                <li>Every movement traceable to the invoice that caused it</li>
            </ul>
        </div>
    </section>

    <!-- ── industry insight ─────────────────────────────────────────────── -->
    <section class="lp-section lp-section-dark lp-reveal" id="insight">
        <div class="container">
            <p class="lp-eyebrow">Industry insight</p>
            <h2>Where invoicing actually breaks</h2>

            <div class="lp-insights">
                <article class="lp-insight lp-stagger">
                    <h3>Paper survives the first year, then fails the audit</h3>
                    <p>A carbon-copy book answers a customer at the counter. It cannot answer "what did this client owe in March" without being read end to end. The answer is only as good as the numbering.</p>
                </article>
                <article class="lp-insight lp-stagger">
                    <h3>Numbering is the control, not the formatting</h3>
                    <p>Gaps and duplicates are what a reviewer looks for first. A system that assigns the number itself removes the possibility rather than asking people to be careful.</p>
                </article>
                <article class="lp-insight lp-stagger">
                    <h3>Most late payment is a document problem</h3>
                    <p>An invoice that reaches the client with the wrong reference, the wrong entity or the wrong total is paid late by default. Getting the document right the first time is the cheapest collection tactic there is.</p>
                </article>
                <article class="lp-insight lp-stagger">
                    <h3>Records outlive the person who kept them</h3>
                    <p>When the bookkeeper leaves, whatever lived only in their notebook leaves with them. A shared record is a continuity plan that costs nothing extra.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ── band: paperwork ──────────────────────────────────────────────── -->
    <section class="lp-band lp-band-alt lp-reveal">
        <div class="lp-band-copy">
            <p class="lp-eyebrow">What changes</p>
            <h2>From a book of carbon copies to a record that answers back</h2>
            <p>
                The invoice is the same document it always was. What changes is that it
                can be found, totalled and traced without reading a year of paper to do it.
            </p>
            <div class="lp-band-stat">
                <span class="lp-band-stat-v" data-lp-count="58">58</span>
                <span class="lp-band-stat-k">Record tables behind one account</span>
            </div>
        </div>
        <img class="lp-band-img lp-settle" src="<?= base_url($img . 'paperwork.jpg') ?>"
             alt="Invoice books, a pen and a calculator on a desk" loading="lazy">
    </section>

    <!-- ── plans ────────────────────────────────────────────────────────── -->
    <section class="lp-section lp-section-dark lp-reveal" id="plans">
        <div class="container">
            <p class="lp-eyebrow">Plans</p>
            <h2>Start on the highest plan, free for a month</h2>
            <p class="lp-sub">Every account opens on Pro for one month with no card. After that, choose the level you actually use.</p>

            <div class="row lp-plans">
                <div class="col-sm-4 lp-stagger">
                    <div class="lp-plan">
                        <h3>Basic</h3>
                        <p class="lp-plan-for">For a single trader issuing a handful of documents a month.</p>
                        <ul>
                            <li>One company</li>
                            <li>Invoices, clients, items</li>
                            <li>Print and export</li>
                        </ul>
                    </div>
                </div>
                <div class="col-sm-4 lp-stagger">
                    <div class="lp-plan lp-plan-featured">
                        <span class="lp-plan-tag">Included at signup</span>
                        <h3>Pro</h3>
                        <p class="lp-plan-for">For a trading business with stock, warranty and staff.</p>
                        <ul>
                            <li>Up to 10 companies</li>
                            <li>Stock, warranty, protocols</li>
                            <li>Employee accounts and permissions</li>
                            <li>Reports and import/export</li>
                        </ul>
                    </div>
                </div>
                <div class="col-sm-4 lp-stagger">
                    <div class="lp-plan">
                        <h3>Custom</h3>
                        <p class="lp-plan-for">For a group issuing across several entities and currencies.</p>
                        <ul>
                            <li>Higher document ceilings</li>
                            <li>Separate data schema</li>
                            <li>Onboarding with your own records</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ── engagement + access ──────────────────────────────────────────── -->
    <section class="lp-section lp-reveal" id="engagement">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="lp-eyebrow">Client engagement</p>
                    <h2>How we work with an account</h2>

                    <ol class="lp-steps">
                        <li>
                            <strong>You open the account.</strong>
                            Email and password. The first month is already on Pro.
                        </li>
                        <li>
                            <strong>You bring your records.</strong>
                            Clients and items import from a spreadsheet, or you start clean and add them as you invoice.
                        </li>
                        <li>
                            <strong>You issue your first document.</strong>
                            Your company details, your numbering, your currency.
                        </li>
                        <li>
                            <strong>We stay reachable.</strong>
                            Support by mail and phone during business hours, in English and Kiswahili.
                        </li>
                    </ol>

                    <div class="lp-contact">
                        <div>
                            <span class="lp-contact-k">Support</span>
                            <span class="lp-contact-v">Business hours, EAT</span>
                        </div>
                        <div>
                            <span class="lp-contact-k">Data</span>
                            <span class="lp-contact-v">Export at any time</span>
                        </div>
                        <div>
                            <span class="lp-contact-k">Setup</span>
                            <span class="lp-contact-v">One session, no install</span>
                        </div>
                    </div>
                </div>

                <div class="col-md-5 col-md-offset-1">
                    <div class="lp-access" id="access">
                        <?php if (!isset($_SESSION['user_login'])) { ?>
                            <form class="lp-form" action="<?= lang_url('login') ?>" method="POST">
                                <?= adomin_csrf_field() ?>
                                <h3>Sign in</h3>
                                <p class="lp-form-sub">Existing accounts.</p>

                                <label for="lp-email">Email</label>
                                <input id="lp-email" type="email" name="email" autocomplete="username"
                                       placeholder="<?= lang('login_email') ?>" required>

                                <label for="lp-pass">Password</label>
                                <input id="lp-pass" type="password" name="password" autocomplete="current-password"
                                       placeholder="<?= lang('login_pass') ?>" required>

                                <button type="submit" class="lp-btn lp-btn-primary lp-btn-block"><?= lang('btn_logme') ?></button>

                                <p class="lp-form-alt">
                                    No account yet?
                                    <a href="<?= lang_url('registration') ?>">Register now for free</a>
                                </p>
                            </form>
                        <?php } else { ?>
                            <div class="lp-form">
                                <h3>You are signed in</h3>
                                <p class="lp-form-sub">Continue to your records.</p>
                                <a href="<?= lang_url('user') ?>" class="lp-btn lp-btn-primary lp-btn-block">Open the app</a>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ── closing ──────────────────────────────────────────────────────── -->
    <section class="lp-close lp-reveal">
        <img class="lp-close-img" src="<?= base_url($img . 'team.jpg') ?>"
             alt="Two colleagues reviewing an invoice together" loading="lazy">
        <div class="lp-close-inner">
            <h2>The next invoice can be the first one on the record.</h2>
            <p>One month on Pro, no card, from the moment you register.</p>
            <a class="lp-btn lp-btn-primary" href="<?= lang_url('registration') ?>">Open an account</a>
        </div>
    </section>

    <footer class="lp-foot">
        <div class="container">
            <div class="lp-foot-row">
                <span class="lp-foot-brand">Adomin Technologies</span>
                <span>Invoicing and record-keeping</span>
            </div>
            <div class="lp-foot-row lp-foot-links">
                <a href="#aims">Aims</a>
                <a href="#practice">Practice</a>
                <a href="#plans">Plans</a>
                <a href="<?= lang_url('registration') ?>">Register</a>
                <a href="<?= lang_url('login') ?>">Sign in</a>
            </div>
        </div>
    </footer>
</div>

<style>
/* ── landing page ─────────────────────────────────────────────────────────
   Scoped to .lp so nothing here changes the app or the other public pages. */
.lp { --lp-ink:#12171d; --lp-mut:#5c6672; --lp-line:#e4e8ee; --lp-acc:#0b6bcb;
      --lp-acc-d:#094f96; --lp-paper:#fbfcfe; --lp-dark:#0b1220;
      color:var(--lp-ink); background:#fff; font-family:"Open Sans",Helvetica,Arial,sans-serif; }
.lp h1,.lp h2,.lp h3 { margin:0 0 10px; letter-spacing:-.015em; }
.lp p { color:var(--lp-mut); line-height:1.65; }
.lp-eyebrow { text-transform:uppercase; letter-spacing:.14em; font-size:11.5px; font-weight:700;
              color:var(--lp-acc); margin:0 0 10px; display:flex; align-items:center; gap:10px; }
.lp-eyebrow::before { content:""; width:26px; height:2px; background:var(--lp-acc); display:inline-block; }
.lp h2 { font-size:32px; line-height:1.2; font-weight:700; margin-bottom:8px; }
.lp-sub { max-width:640px; font-size:15px; }

/* ── hero ─────────────────────────────────────────────────────────────── */
.lp-hero { position:relative; overflow:hidden; background:#0b1220; color:#fff;
           display:flex; flex-direction:column; min-height:100vh; }
.lp-hero-media { position:absolute; inset:0; overflow:hidden; }
.lp-hero-img { width:100%; height:100%; object-fit:cover; display:block;
               transform:scale(1.06); transform-origin:center; will-change:transform; }
.lp-hero-scrim { position:absolute; inset:0;
    background:linear-gradient(102deg, rgba(9,15,26,.94) 0%, rgba(9,15,26,.82) 38%, rgba(9,15,26,.45) 68%, rgba(9,15,26,.62) 100%); }
.lp-hero-inner { position:relative; z-index:2; width:100%; max-width:1140px;
                 margin:0 auto; padding:0 15px; flex:1 0 auto; }
.lp-hero-copy { max-width:600px; padding:26px 0 44px; }
.lp-hero .lp-eyebrow { color:#7fbcf5; }
.lp-hero .lp-eyebrow::before { background:#7fbcf5; }
.lp-hero h1 { font-size:clamp(30px,4.6vw,52px); line-height:1.08; font-weight:700;
              margin:6px 0 18px; color:#fff; letter-spacing:-.025em; }
.lp-hero h1 em { font-style:normal; color:#7fbcf5; }
.lp-hero .lp-lede { color:#c3d2e2; font-size:16.5px; max-width:540px; }

.lp-nav { display:flex; align-items:center; justify-content:space-between;
          padding:22px 0; flex-wrap:wrap; gap:14px; }
.lp-brand { display:inline-flex; align-items:center; gap:9px; text-decoration:none;
            color:#fff; font-size:19px; font-weight:700; letter-spacing:-.02em; }
.lp-brand small { display:block; font-size:10.5px; font-weight:600; letter-spacing:.16em;
                  text-transform:uppercase; color:#8fa6bf; }
.lp-nav-links { display:flex; align-items:center; gap:22px; flex-wrap:wrap; }
.lp-nav-links a { color:#c3d2e2; text-decoration:none; font-size:14px; font-weight:600; }
.lp-nav-links a:hover { color:#fff; }
.lp-nav-cta { padding:9px 18px; border:1px solid rgba(127,188,245,.6); border-radius:6px; color:#bfe0ff!important; }
.lp-nav-cta:hover { background:#0b6bcb; border-color:#0b6bcb; color:#fff!important; }

.lp-defs { margin:26px 0 0; border-top:1px solid rgba(255,255,255,.16); }
.lp-defs > div { display:flex; gap:18px; padding:13px 0; border-bottom:1px solid rgba(255,255,255,.12); }
.lp-defs dt { min-width:86px; font-size:12px; font-weight:700; letter-spacing:.1em;
              text-transform:uppercase; color:#fff; padding-top:2px; }
.lp-defs dd { margin:0; font-size:14.5px; color:#bfd0e2; line-height:1.55; }

.lp-hero-actions { display:flex; gap:12px; flex-wrap:wrap; margin-top:28px; }
.lp-note { font-size:12.5px; color:#9fb3c9; margin-top:14px; }

.lp-btn { display:inline-block; padding:13px 26px; border-radius:7px; font-size:14.5px;
          font-weight:700; text-decoration:none; text-align:center; cursor:pointer;
          border:1px solid transparent; transition:transform .14s,box-shadow .14s,background .14s; }
.lp-btn:hover { text-decoration:none; }
.lp-btn-primary { background:var(--lp-acc); color:#fff; box-shadow:0 8px 24px rgba(11,107,203,.34); }
.lp-btn-primary:hover { background:var(--lp-acc-d); color:#fff; transform:translateY(-2px); }
.lp-btn-ghost { background:rgba(255,255,255,.08); color:#fff; border-color:rgba(255,255,255,.34);
                backdrop-filter:blur(3px); }
.lp-btn-ghost:hover { background:rgba(255,255,255,.16); color:#fff; }
.lp-btn-block { display:block; width:100%; }

/* hero stat strip */
.lp-hero-stats { position:relative; z-index:2; display:grid;
                 grid-template-columns:repeat(4,1fr); gap:1px; background:rgba(255,255,255,.14);
                 border-top:1px solid rgba(255,255,255,.14); }
.lp-stat { background:rgba(8,13,23,.72); padding:20px 22px; backdrop-filter:blur(8px); }
.lp-stat-v { display:block; font-size:30px; font-weight:700; color:#fff;
             letter-spacing:-.02em; line-height:1.1; }
.lp-stat-k { display:block; font-size:11.5px; color:#9fb3c9; margin-top:5px;
             letter-spacing:.03em; text-transform:uppercase; }

/* ── sections ─────────────────────────────────────────────────────────── */
.lp-section { padding:78px 0; border-bottom:1px solid var(--lp-line); }
.lp-section-tint { background:var(--lp-paper); }
.lp-section-dark { background:var(--lp-dark); border-bottom:none; }
.lp-section-dark h2, .lp-section-dark h3 { color:#fff; }
.lp-section-dark p { color:#9fb0c4; }
.lp-section-dark .lp-eyebrow { color:#6fb0ef; }
.lp-section-dark .lp-eyebrow::before { background:#6fb0ef; }

/* aims */
.lp-aims { margin-top:26px; }
.lp-aim { padding:24px 24px 22px; border:1px solid var(--lp-line); border-radius:11px;
          background:#fff; margin-bottom:22px; height:100%;
          transition:transform .2s,box-shadow .2s,border-color .2s; }
.lp-aim:hover { transform:translateY(-3px); box-shadow:0 14px 34px rgba(16,25,40,.10);
                border-color:#c9dcf0; }
.lp-aim-n { display:block; font-size:12px; font-weight:700; letter-spacing:.14em;
            color:var(--lp-acc); margin-bottom:9px; }
.lp-aim h3 { font-size:18px; font-weight:700; }
.lp-aim p { font-size:14.5px; margin:0; }

/* capability grid */
.lp-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:1px; margin-top:28px;
           background:var(--lp-line); border:1px solid var(--lp-line); border-radius:11px; overflow:hidden; }
.lp-feat { background:#fff; padding:22px 20px; transition:background .18s; }
.lp-feat:hover { background:#f2f8ff; }
.lp-feat h3 { font-size:15px; font-weight:700; margin-bottom:6px; }
.lp-feat p { font-size:13.5px; margin:0; line-height:1.55; }

/* ── image bands ──────────────────────────────────────────────────────── */
.lp-band { display:grid; grid-template-columns:1fr 1fr; align-items:stretch;
           min-height:clamp(360px,44vw,540px); background:#0d1520; overflow:hidden; }
.lp-band-alt { background:var(--lp-ink); }
.lp-band-img { width:100%; height:100%; object-fit:cover; display:block; min-height:320px; }
.lp-band-copy { padding:clamp(34px,5vw,74px); display:flex; flex-direction:column;
                justify-content:center; }
.lp-band-copy h2 { color:#fff; }
.lp-band-copy p { color:#9fb0c4; font-size:15.5px; max-width:460px; }
.lp-band .lp-eyebrow { color:#6fb0ef; }
.lp-band .lp-eyebrow::before { background:#6fb0ef; }
.lp-band-list { list-style:none; padding:0; margin:20px 0 0; }
.lp-band-list li { position:relative; padding-left:22px; margin-bottom:11px;
                   font-size:14px; color:#c3d2e2; line-height:1.55; }
.lp-band-list li:before { content:""; position:absolute; left:0; top:8px; width:8px; height:8px;
                          border-radius:2px; background:#3f8fdc; }
.lp-band-stat { margin-top:22px; padding-top:18px; border-top:1px solid rgba(255,255,255,.16); }
.lp-band-stat-v { display:block; font-size:32px; font-weight:700; color:#fff; line-height:1.1; }
.lp-band-stat-k { display:block; font-size:12px; color:#9fb3c9; margin-top:4px; }

/* insights */
.lp-insights { display:grid; grid-template-columns:repeat(2,1fr); gap:26px 40px; margin-top:28px; }
.lp-insight { border-left:3px solid var(--lp-acc); padding:2px 0 2px 18px; }
.lp-insight h3 { font-size:17px; font-weight:700; line-height:1.35; }
.lp-insight p { font-size:14.5px; margin:0; }

/* plans */
.lp-plans { margin-top:30px; }
.lp-plan { position:relative; border:1px solid rgba(255,255,255,.16); border-radius:12px;
           padding:26px 24px; height:100%; background:rgba(255,255,255,.03);
           transition:transform .2s,border-color .2s,background .2s; }
.lp-plan:hover { transform:translateY(-4px); border-color:rgba(111,176,239,.55); background:rgba(255,255,255,.06); }
.lp-plan h3 { font-size:20px; font-weight:700; }
.lp-plan-featured { background:#fff; border-color:#fff; }
.lp-plan-featured:hover { background:#fff; }
.lp-plan-featured h3, .lp-plan-featured .lp-plan-for { color:var(--lp-ink); }
.lp-plan-featured ul li { color:var(--lp-mut); }
.lp-plan-tag { position:absolute; top:-11px; left:24px; background:var(--lp-acc); color:#fff;
               font-size:10.5px; font-weight:700; letter-spacing:.1em; text-transform:uppercase;
               padding:4px 11px; border-radius:999px; }
.lp-plan-for { font-size:13.5px; margin-bottom:16px; }
.lp-plan ul { list-style:none; padding:0; margin:0; }
.lp-plan ul li { position:relative; padding-left:20px; margin-bottom:9px; font-size:14px;
                 color:#c8d4e2; line-height:1.5; }
.lp-plan ul li:before { content:""; position:absolute; left:0; top:7px; width:7px; height:7px;
                        border-radius:2px; background:var(--lp-acc); }

/* engagement */
.lp-steps { list-style:none; counter-reset:step; padding:0; margin:22px 0 0; }
.lp-steps li { position:relative; padding:0 0 20px 46px; counter-increment:step; font-size:14.5px;
               color:var(--lp-mut); line-height:1.6; }
.lp-steps li:before { content:counter(step); position:absolute; left:0; top:0;
                      width:30px; height:30px; border-radius:50%; background:#eef4fb; color:var(--lp-acc);
                      font-size:13px; font-weight:700; display:flex; align-items:center; justify-content:center; }
.lp-steps li:after { content:""; position:absolute; left:15px; top:34px; bottom:4px; width:1px; background:var(--lp-line); }
.lp-steps li:last-child:after { display:none; }
.lp-steps strong { display:block; color:var(--lp-ink); font-size:15px; margin-bottom:2px; }

.lp-contact { display:flex; flex-wrap:wrap; gap:26px; margin-top:10px; padding-top:18px;
              border-top:1px solid var(--lp-line); }
.lp-contact-k { display:block; font-size:10.5px; font-weight:700; letter-spacing:.12em;
                text-transform:uppercase; color:var(--lp-mut); }
.lp-contact-v { display:block; font-size:13.5px; font-weight:600; color:var(--lp-ink); margin-top:3px; }

/* access card */
.lp-access { background:#fff; border:1px solid var(--lp-line); border-radius:13px;
             padding:30px 28px; box-shadow:0 18px 50px rgba(16,25,40,.11); }
.lp-access h3 { font-size:21px; font-weight:700; margin-bottom:4px; }
.lp-form-sub { font-size:13.5px; margin-bottom:20px; }
.lp-form label { display:block; font-size:11.5px; font-weight:700; letter-spacing:.09em;
                 text-transform:uppercase; color:var(--lp-mut); margin-bottom:6px; }
.lp-form input[type=email], .lp-form input[type=password] {
    width:100%; height:46px; border:1px solid var(--lp-line); border-radius:7px;
    padding:0 14px; font-size:15px; margin-bottom:16px; background:#fcfdfe; color:var(--lp-ink); }
.lp-form input[type=email]:focus, .lp-form input[type=password]:focus {
    outline:none; border-color:var(--lp-acc); box-shadow:0 0 0 3px rgba(11,107,203,.12); background:#fff; }
.lp-form-alt { font-size:13px; margin:16px 0 0; text-align:center; }
.lp-form-alt a { color:var(--lp-acc); font-weight:600; }

/* closing */
.lp-close { position:relative; overflow:hidden; min-height:340px; display:flex;
            align-items:center; justify-content:center; text-align:center; }
.lp-close-img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
.lp-close::after { content:""; position:absolute; inset:0;
                   background:linear-gradient(180deg, rgba(8,13,23,.86), rgba(8,13,23,.94)); }
.lp-close-inner { position:relative; z-index:2; padding:60px 20px; max-width:620px; }
.lp-close h2 { color:#fff; font-size:clamp(24px,3.4vw,36px); margin-bottom:10px; }
.lp-close p { color:#bfd0e2; margin-bottom:24px; }

/* footer */
.lp-foot { background:#f7f9fc; padding:26px 0; }
.lp-foot-row { display:flex; justify-content:space-between; align-items:center; gap:16px;
               flex-wrap:wrap; font-size:13px; color:var(--lp-mut); }
.lp-foot-brand { font-weight:700; color:var(--lp-ink); }
.lp-foot-links a { color:var(--lp-mut); text-decoration:none; margin-left:18px; font-weight:600; }
.lp-foot-links a:hover { color:var(--lp-acc); }

@media (max-width:991px) {
    .lp-hero { min-height:auto; }
    .lp-hero-copy { padding:10px 0 40px; }
    .lp-hero-stats { grid-template-columns:repeat(2,1fr); }
    .lp-band, .lp-band-alt { grid-template-columns:1fr; }
    .lp-band-alt .lp-band-img { order:-1; }
    .lp-band-img { min-height:240px; max-height:320px; }
    .lp-grid { grid-template-columns:repeat(2,1fr); }
    .lp-access { margin-top:30px; }
}
@media (max-width:600px) {
    .lp h2 { font-size:25px; }
    .lp-section { padding:52px 0; }
    .lp-grid, .lp-insights { grid-template-columns:1fr; }
    .lp-nav-links { gap:14px; font-size:13px; }
    .lp-defs > div { flex-direction:column; gap:4px; }
    .lp-stat-v { font-size:24px; }
}
</style>

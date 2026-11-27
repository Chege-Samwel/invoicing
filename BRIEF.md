# Invoicing — Capability Brief

> **One-liner:** Self-hosted electronic invoicing + warehouse system (CodeIgniter 3.1.13 / PHP / MySQL / Bootstrap 3). Unlimited invoices, PDF export, multi-company, multi-language, warehouse stock, protocols & warranties.

---

## 1. What this repo is

| Layer | Tech |
|---|---|
| Framework | CodeIgniter 3.1.13 (PHP 7.4 recommended; PHP 8+ = deprecation errors) |
| Frontend | Bootstrap 3.3.7, jQuery, CKEditor, Highcharts |
| Database | MySQL / MariaDB via `mysqli` (`database.sql`: 57 tables, +1 from `database/adomin-updates.sql`) |
| PDF engine | **`dompdf` (bundled, pure PHP) by default** via `application/libraries/AdominPdf.php`; `wkhtmltopdf` is opt-in with `PDF_ENGINE=wkhtmltopdf` |
| Excel/XML | Bundled PHPExcel (`ExcelExport.php`, `XmlExport.php`, `UniversalXmlImport.php`) |
| Mail | PHPMailer (`MailSend.php`) |
| Structure | `application/modules/users/` (customer app), `application/modules/admin/` (super-admin), `application/controllers/` (public: login/register, public invoice accept) |

**Roles:** Public visitor → Registered user (owns firms/clients/items) → Employee (permission-gated sub-user) → Super-admin (`/admin`, default `admin` / `admin` — **change immediately**).

---

## 2. Core capabilities

**Invoicing**
- 4 document types: Tax Invoice, Pro-forma, Debit Note, Credit Note (`$config['inv_readable_types']` in `config.php`)
- Statuses: issued / draft / sended / canceled / accepted / refused; paid/unpaid tracking; public accept/refuse link (`accept/invoice/...`)
- Line items with quantity types, VAT %, discounts, rounding options, calculator toggle
- Clients & items CRUD with pricing; global search; history/audit log (`history` table)

**Warehouse / Store**
- Multiple stores, stock movements (in/out), stock availability, negative-quantity toggle, bill-of-goods PDF (`Movementview`)

**Protocols & Warranties**
- Transmission protocols (with contracts & provider-transmit texts) and warranty cards with events/conditions — all PDF-exportable

**Reports & Import/Export**
- Statistics dashboard (Highcharts), reports page, export invoices to Excel/XML, import via universal XML

**Users, plans & admin**
- Registration/login, forgotten password, employee permissions (`perm_*` in `config.php`), subscription plans (`Plans.php`, plan requests), admin panel for plan requests & languages

**Languages:** English, French, Bulgarian (`application/language/*`). Per-user invoice translations (`invoices_languages`, `firms_translations`, etc.) — each firm can have multiple named translations (e.g. "English", "Swahili").

---

## 3. Customisations for a specific company

This is the most important part — the app is **already built for per-company branding**, no code change needed for most of it:

### 3.1 Multi-company ("firms")
- One user account can own **many companies**. Switch/manage at `user/managefirms`.
- Per company: Bulstat/registration no., VAT-registered toggle + VAT number, **default currency**, and one or more **translations** (name, registered address, city, MOL/accountable person, logo).
- Files: `Managefirms.php` controller, `ManagefirmsModel.php`, views `managefirms/index.php` + `edit.php`, tables `firms_users`, `firms_translations`, `firms_plans`.

### 3.2 Logo customisation
- Upload per company **per translation**: `Manage Firms → Edit → Firm Image` (stored in `attachments/companiesimages/{firm_id}/{file}`).
- Toggle **"Show logo in invoices"** (`show_logo` checkbox) — controls whether the logo appears on PDFs.
- Remove/replace anytime ("Remove logo" button). Allowed types: `gif|jpg|png|jpeg` (`$config['allowed_img_types']`).
- Templates check: `if ($invoice['firm']['image'] != null && $firmInfo['show_logo'] == 1)` in both PDF templates.

### 3.3 PDF customisation (and why the PDF used to break)
- **Two built-in templates** — switch per user at `Settings → Invoices → Invoice template`:
  - `creative` — coloured, branded layout (`views/invoices/templates/creative.php`)
  - `toner-save` — plain black-on-white, printer-friendly (`views/invoices/templates/toner-save.php`)
  - Registered in `$config['templates']` (`config.php`); stored per user in `value_store.opt_invTemplate`.
- **Rendering path now:** `Invoiceview::viewInvoiceAsPdf()` → `AdominDocs::invoiceHtml()` (template + CSS read from disk) → `AdominPdf::fromHtml()` → bundled **dompdf**. Protocols, warehouse movements and warranty cards go through the same library via the `HtmlToPdf` shim, so all four document types share one code path.
- **What was broken and how it was fixed:**

  | Break | Cause | Fix |
  |---|---|---|
  | PDF hangs / 500 on Render free tier | `wkhtmltopdf --footer-html https://your-app/pdffooter` — the binary called the app over HTTP while the app was blocked waiting for the binary (1 CPU, 1 worker) → self-request deadlock | footer is drawn in-process by `AdominPdf::drawFooter()`, no HTTP at all |
  | Unstyled / broken invoice PDF | `file_get_contents(base_url('assets/users/css/invoices-templates.css'))` — remote read of its own file (fails with `allow_url_fopen=Off`, also deadlocks) | CSS is read from `FCPATH` in `AdominDocs::invoiceHtml()` |
  | Firm logo missing in PDF | `<img src="https://…">` with `isRemoteEnabled=false` in dompdf | every `<img>` is inlined as a `data:` URI (`AdominPdf::inlineImages`) |
  | Layout depends on `bootstrap.min.css` fetched at render time | remote `<link>` | local `<link>`s are inlined from disk, remote ones dropped, `assets/users/css/pdf-base.css` carries the grid |
  | Broken binary path `/home/kirilkirkov91/…` | hard-coded | `WKHTMLTOPDF_PATH` env var, and the engine defaults to dompdf so no binary is needed |
  | `pdfs.log` in the doc root | `exec … &>> pdfs.log` (unwritable dir → fatal) | logs go to the writable temp dir, `pdfs.log` is removed from the image |

- **Footer:** `<type> № <number>` on the left, `Page {PAGE_NUM} / {PAGE_COUNT}` and the **Powered by Adomin Technologies timestamp** on the right, painted on every page.
- **To add a 3rd template:** copy `creative.php` to e.g. `modern.php`, add `'modern'` to `$config['templates']` — it appears in Settings automatically and in PDFs immediately (same renderer, same CSS inlining).
- **To rebrand colours/fonts:** edit `assets/users/css/invoices-templates.css` (screen + PDF share it) and the accent in `application/config/adomin.php` (`ADOMIN_ACCENT`). Print CSS lives in `assets/users/css/pdf-base.css`. Avoid flexbox/grid in PDF templates — use tables and floats (dompdf is CSS 2.1).
- **Debug a render:** `ADOMIN_PDF_INLINE_ASSETS=0`, `ADOMIN_PDF_FRAMEWORK_CSS=1`, `ADOMIN_PDF_STAMP=0`; the engine actually used is `HtmlToPdf::getEngine()` / `AdominPdf::getLastEngine()` and notices land in `application/logs/`.

### 3.4 Currency, VAT & payment customisation
- **Default currency per firm** (Settings → Invoices); 100+ currencies preloaded in `currencies` table. Add custom ones (name + code).
- **Kenya note:** default config is `EUR` (`config.php`). For Kenya set default currency to `KES`/`KSh`, VAT rate to **16%**, and add payment methods like *M-Pesa, Bank Transfer, Cash* (Settings → Invoices → Payment methods). "No-VAT reasons" are also customisable.
- Quantity types (pcs, kg, hrs…) and rounding precision are user-defined.

### 3.5 Other per-company settings
- Global settings: pagination size, rounding, calculator on/off (`Settings → Global`).
- Invoice settings: template, rounding, calculator (`Settings → Invoices`).
- Store settings: movement calculator, rounding, negative quantities (`Settings → Stores`).
- Warranty/protocol settings: conditions, contracts, provider texts.
- Employee permissions: granular `perm_*` flags per employee.

### 3.6 Kenya localisation gaps (needs small code changes)
The app is Bulgaria/EUR-oriented out of the box. For a Kenyan company you should rename/repurpose:
- `Bulstat` → **KRA PIN** (label in `users_lang.php`: `firm_bulstat`, `bulstat`)
- `MOL` (accountable person) → **Director / Contact Person**
- `VAT number` → **VAT PIN** (same field works)
- Default language/currency → English + KES; add a Swahili translation set if needed
- These are label-only changes in `application/language/english/users_lang.php` + invoice translation tables — no schema change required.

---

## 4. Deployment

The procedure lives in **[DEPLOYMENT.md](DEPLOYMENT.md)**, in one place and in order. Short
version, and why each line is the way it is:

| Target | Verdict | Where |
|---|---|---|
| Local XAMPP/WAMP | PHP 7.4 + MySQL, `database.sql` imported, three writable directories | DEPLOYMENT.md §1 |
| **Render free tier** | the supported path: `render.yaml` + `Dockerfile`, database external, dompdf for PDFs | DEPLOYMENT.md §2–§5 |
| VPS (Hetzner/DO, ~$5) | the same Docker image, plus persistent disk and `wkhtmltopdf` if you want it | DEPLOYMENT.md §7 |
| Vercel | works for a read-mostly demo, wrong for a business: read-only filesystem, function timeouts | DEPLOYMENT.md §8 |

**The three things a hosted deploy needs**, in one paragraph: a **MySQL** database (not the
free Postgres Render offers — DEPLOYMENT.md §6 explains what a port would involve), the two
SQL files imported **with the schema selected** (neither contains a `USE` statement), and the
connection passed as environment variables (`DB_HOST`/`DB_USER`/`DB_PASS`/`DB_NAME`/`DB_PORT`,
or one `DATABASE_URL`) so no credential is ever committed. TLS is explicit
(`DB_SSL=1`, `DB_SSL_CA`, `DB_SSL_VERIFY`): this application does not detect or guess a host's
requirements. The configuration reference, the troubleshooting table and the pre-flight
checklist are DEPLOYMENT.md §9–§12.

---

## 5. Personalisation — Adomin Technologies

Everything brand-shaped lives in **one config file**: `application/config/adomin.php`, and every
key can be overridden by an environment variable, so a client gets their own identity without a
code change.

| Knob | Env var | Default in this repo |
|---|---|---|
| Brand / app name | `ADOMIN_BRAND_NAME`, `ADOMIN_BRAND_SHORT`, `ADOMIN_APP_NAME` | Adomin Technologies / Adomin / Adomin Invoices |
| Tagline, site, support | `ADOMIN_TAGLINE`, `ADOMIN_WEBSITE`, `ADOMIN_SUPPORT_EMAIL`, `ADOMIN_SUPPORT_PHONE` | invoicing & warehouse mgmt, adomin.co.ke |
| Powered-by text | `ADOMIN_POWERED_BY` | `Powered by Adomin Technologies` |
| Accent / theme colour | `ADOMIN_ACCENT`, `ADOMIN_THEME_COLOR` | `#0b6bcb` |
| Logo / icon | `ADOMIN_LOGO`, `ADOMIN_ICON` | `assets/public/imgs/logo.png`, `…/icons/icon-512.png` |
| Timezone | `ADOMIN_TIMEZONE` | `Africa/Nairobi` (set globally in `config/constants.php`) |
| Currency, date format | `ADOMIN_CURRENCY`, `ADOMIN_DATE_FORMAT` | `KES`, `d.m.Y` |
| **Timestamp on documents** | `ADOMIN_STAMP_FORMAT` | `d M Y, H:i` + zone abbreviation |
| Animations / PWA / share links | `ADOMIN_ANIMATIONS`, `ADOMIN_PWA`, `ADOMIN_SHARE_LINKS` | all on |
| PDF engine, margins, dpi, off-site logo fetch | `PDF_ENGINE`, `ADOMIN_PDF_MARGIN_LR`, `ADOMIN_PDF_MARGIN_TB`, `ADOMIN_PDF_DPI`, `ADOMIN_PDF_REMOTE_IMAGES` | `dompdf`, 8 mm, 12 mm, 96, on |

**Where the stamp appears** (all rendered server side, so also inside the PDF):
- app footer (`users/views/parts/footer.php`) and public/accept footers —
  `Powered by Adomin Technologies · 26 Nov 2026, 14:03 EAT`
- invoice preview under the document (`invoices/view.php` → `.adomin-stamp`)
- every PDF, on **every page**, in the right margin (`AdominPdf::drawFooter`)
- the public share page header/meta

Helper API (`application/helpers/adomin_helper.php`, autoloaded): `adomin_conf()`,
`adomin_stamp()`, `adomin_stamp_time()`, `adomin_foot()`, `adomin_logo_url()`,
`adomin_firm_logo_src()`, `adomin_share_url()`, `adomin_pwa_head()`, `adomin_data_uri()`.

To white-label for another company: change the env vars (or the config defaults), swap
`assets/public/imgs/logo.png` + `icons/*` (`assets/public/imgs/adomin-mark.svg` is the fallback),
and the accent in `adomin-brand.css`. Nothing else is hard-coded.

---

## 6. Send via link (share a document without e-mail)

Invoices already carried an invisible `uniqid` used by the accept page; this build turns it into a
first-class share feature.

| Piece | Where |
|---|---|
| Trigger: **Send via link** in the invoice list dropdown and on the invoice view | `users/views/invoices/index.php`, `view.php` |
| Sheet (copy / WhatsApp / SMS / e-mail / open / PDF / native share / revoke) | `users/views/invoices/share_modal.php` |
| `POST user/invoice/sharelink/{id}` → JSON `{url, pdfUrl, acceptUrl, revokeUrl, message, …}` | `Invoiceview::shareLink()` |
| Token created on demand if the row has none | `NewInvoiceModel::ensureShareToken()` |
| Public read-only page `/invoice/<token>` | `Home::sharedInvoice()` + `views/invoices_parts/invoice_share.php` |
| Public PDF `/invoice/<token>/pdf` (same renderer, `?download=1` supported) | `Home::sharedInvoicePdf()` |
| Revoke (rotates the token, every old link 404s) | `Invoices::revokeShare()` |
| Legacy accept/refuse flow kept | `/accept/invoice/<token>` |
| Optional view log (who opened it, when) | `invoice_share_views` table → `Home::logShareView()` |

Properties worth stating to a client:
- the link is **view-only** — no edit surface, no session, no CSRF token involved;
- it works while the app sleeps? No: it needs the service awake (that is what the offline card is for);
- one token per invoice; `Revoke link` invalidates it instantly;
- the message is pre-filled (invoice №, total, currency) so sending is two taps on a phone;
- phone/e-mail of the client is **not** stored by this app, so WhatsApp/SMS open the picker
  with the text pre-filled rather than guessing a number.

---

## 7. Animations (motion layer)

`assets/public/css/adomin-animations.css` + `assets/public/js/adomin-app.js`, mounted in
`users/views/parts/header.php`, `views/parts/header.php` and the accept/share headers.

- document entrance: sections rise (`adomin-rise`), staggered 0 → 150 ms
- reveal-on-scroll for invoice preview, panels, charts, tables (`IntersectionObserver`)
- table rows fade with a capped stagger (a 200-row list is still one repaint)
- hover/press feedback on every `.btn`, dropdowns pop, modals slide+scale
- `adomin-toast` slide-in + auto-dismiss for the app's `geterror()` alerts, `adominToast()` for JS
- `adomin-spinner` / `adomin-skeleton` while a share link is created or a PDF is rendering
- count-up on report/dashboard numbers, ripple on buttons
- page-transition veil the PWA/Android shell uses (`#adomin-view-veil`)
- **never** at the cost of correctness: `prefers-reduced-motion` collapses everything to 1 ms,
  `media print` disables all motion and hides nav/buttons (`.adomin-no-print`), and
  `ADOMIN_ANIMATIONS=0` removes the stylesheet *and* the `adomin-motion` class server side
- brand layer (`adomin-brand.css`) ships with it: footer identity, stamp pill, share sheet,
  status pills, safe-area padding and 40 px touch targets when running as an installed app

Add motion without touching CSS: give an element `data-adomin-count` (count-up) or wrap a block and
let `initReveal()` pick it up; anything inside the listed selectors already animates.

---

## 8. Android app + installable PWA

Two delivery paths, one codebase (details in `android-app/README.md`):

1. **PWA, today, no build:** `manifest.json` (icons, shortcuts, theme colour) + `sw.js`
   (app-shell cache, offline card for the Render cold start, session-scoped URLs never cached) +
   `offline.html`. Chrome on Android offers *Add to home screen*; it opens at `/user`.
2. **Native shell for the Play Store:** Capacitor project in `android-app/`
   (`capacitor.config.json` + `package.json` + `scripts/build-apk.sh` + manifest snippet + brand
   assets in `android-app/resources/`). It wraps the hosted URL, so there is still one source of
   truth: same login, same invoice numbering, same dompdf output, and `navigator.share` becomes the
   native share sheet.

```bash
cd android-app && npm install
npx cap add android && npx cap sync android
./scripts/build-apk.sh https://your-app.onrender.com    # -> AdominInvoices-debug.apk
# release: npm run build:release  -> app-release.aab for the Play Console
```

The shell adds: brand splash (hides a ~60 s cold start), tinted status bar, back-button-safe
navigation, `AdominApp/1.0` in the User-Agent, and the share sheet. No offline invoice editing, no
second implementation of numbering — deliberate, so the phone app can never drift from the server.

---

## 9. Production checklist

- [ ] Change admin password (`/admin`, default `admin`/`admin`) and `$config['salt']` in `config.php`
- [ ] `ENVIRONMENT = 'production'` in `index.php` (already set)
- [ ] Set `DB_*` on Render; keep them `sync: false` so secrets never sit in `render.yaml`
- [ ] TLS is mandatory for the free external MySQL (TiDB): `DATABASE_URL=…?ssl-mode=REQUIRED` or `DB_SSL=1`; `DB_SSL_CA` / `DB_SSL_VERIFY=1` to pin and verify the certificate
- [ ] Verify HTTPS redirect through the proxy: `BASE_URL` now trusts `X-Forwarded-Proto`/`Host`
      (`config/constants.php`); without that the PDF footer/asset URLs would be `http://`
- [ ] `application/libraries/dompdf/lib/fonts/` must stay writable: dompdf parses the DejaVu metrics into
      `dejavu_sans_*.{ufm,ufm.php}` on the first render after a deploy (about a second on 0.1 CPU). The
      Dockerfile already chmods it, and `AdominPdf::writableDir()` falls back to `/tmp` if it is read-only
- [ ] Run `database/adomin-updates.sql` (KES, `firms_users.logo_url`, `uniqid` index, share log)
- [ ] Test after deploy: PDF download (original + copy), protocol/movement/warranty PDF,
      logo upload **and** `logo_url`, send-via-link open + revoke, accept/refuse, animations off flag
- [ ] Keep-alive ping every 10 min if you want the free instance to stay warm (750 h/month ≈ 1 service)
- [ ] Back up MySQL; `attachments/` is *not* backed up on free tier (it is disposable by design)

---

### 9.1 How the PDF path was verified (no web server needed)

dompdf is pure PHP, so the whole document pipeline can be exercised from a CLI/PHP runtime with a stub
`get_instance()` — the approach used here was a ~250-line harness that defines `lang()`, `base_url()`,
`log_message()`, a `StubConfig` that loads `application/config/adomin.php`, a real invoice fixture (the
exact shape `NewInvoiceModel::getInvoiceByNumber()` returns: `items`/`client`/`firm`/`translation`), and
then asserts:

| Assertion | Why |
|---|---|
| `invoiceHtml()` output starts with `<!DOCTYPE html>`, contains `.invoice-box`, no `<link>` | CSS must come from disk, never a self-request |
| `<img src="<base_url>/attachments/…/logo.png">` → `data:image/png;base64,` and `/Image` in the PDF bytes | the logo survives with `isRemoteEnabled=false` |
| a same-host URL resolves through `publicPath()` (no fetch), an off-site one through the bounded fetch | no deadlock, but a CDN logo still prints |
| `footerLines()` returns `{PAGE_NUM}`/`{PAGE_COUNT}` + the brand stamp, and nothing brand-related when `ADOMIN_PDF_STAMP=0` | the footer is generated in-process |
| 40-line invoice → 3 page objects, output still starts with `%PDF` | multi-page margins/footer math holds |
| unreachable logo host → PDF still produced + a notice | a CDN outage cannot break billing |
| `DATABASE_URL` with/without `?ssl-mode=`, percent-encoded credentials, `DB_*` overriding a URL | the connection cases in DEPLOYMENT.md §4 all resolve through `adomin_db_config()` |

That harness is not committed (it would ship a second `lang()`/`base_url()` definition inside
`application/`); rebuild it in a scratch directory when the PDF layer changes.

---

## 10. Where to customise (file map)

| Want | File(s) |
|---|---|
| Brand, stamp, locale, engine flags | `application/config/adomin.php` (env-overridable) |
| Footer / stamp markup | `application/helpers/adomin_helper.php` (`adomin_foot`, `adomin_stamp`) |
| Company details, logo upload + **logo URL** | `modules/users/views/managefirms/edit.php`, `ManagefirmsModel.php` |
| Logo resolution order (URL → disk → none) | `adomin_firm_logo_src()` in the adomin helper, used by both templates |
| PDF engine, footer, inlining, fallbacks | `application/libraries/AdominPdf.php` |
| Shared document builder (preview + PDF + public link) | `application/libraries/AdominDocs.php` |
| Backwards-compatible `generatePdf()` | `application/libraries/HtmlToPdf.php` |
| PDF/print CSS (screen + PDF shared) | `assets/users/css/invoices-templates.css`, `assets/users/css/pdf-base.css`, `assets/users/css/protocols-templates.css` |
| Invoice template | `modules/users/views/invoices/templates/creative.php` / `toner-save.php` |
| Send via link | `Invoiceview::shareLink`, `Invoices::revokeShare`, `Home::sharedInvoice`, `views/invoices/share_modal.php` |
| Animations + PWA runtime | `assets/public/css/adomin-animations.css`, `assets/public/js/adomin-app.js` |
| PWA install + offline | `manifest.json`, `sw.js`, `offline.html` |
| Android shell | `android-app/` |
| Currency / VAT / payment methods | `Settings → Invoices`; `modules/users/controllers/settings/Invoices.php` |
| Labels (KRA PIN, KSh…) | `application/language/english/users_lang.php` (+ `france`, `bulgarian`) |
| Permissions | `application/config/config.php` (`perm_*`) |
| DB connection | `application/config/database.php` (`DB_*` env vars) |
| Deploy | `render.yaml`, `Dockerfile`, `vercel.json`, `.htaccess` |
| Apache tuning that cannot break the deploy | `docker/adomin-mpm.conf`, `docker/adomin-start.sh` |
| Apache MPM cap, `$PORT` binding | `docker/adomin-mpm.conf`, `docker/adomin-start.sh` (both validated by `apache2ctl -t` at build *and* start) |
| Kenya SQL defaults and the columns this fork needs | `database/adomin-updates.sql` |

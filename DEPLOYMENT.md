# Deployment Guide — Adomin Invoices

The complete, ordered procedure for putting this application online. It is written for
someone who has the repository and a browser, and nothing else installed.

* **Render (free tier)** is the supported target → §2–§5.
* **Local machine** (XAMPP/WAMP/Laragon) for development → §1.
* **A VPS** when you need persistent disk and a real domain → §7.
* **Vercel** is possible but a poor fit, and §8 explains exactly why in numbers.

What the application needs, whatever the host: **PHP 7.4** with `mysqli`, `gd`, `mbstring`,
`xml`, `zip`, `intl`; a **MySQL-compatible** database (§6); and write access to three
directories. Everything else in this guide is how to provide those three things.

---

## 1. Local install (any machine, ~10 minutes)

1. Install **PHP 7.4 + Apache + MySQL** (XAMPP, WAMP or Laragon). Do not use PHP 8: the
   framework (CodeIgniter 3.1.13) emits deprecation notices on it and the bundled PHPExcel
   breaks. `wkhtmltopdf` is **not** needed — PDFs render with the bundled dompdf.
2. Copy the repository to the web root, e.g. `htdocs/invoicing`.
3. Create a database and import the schema:
   ```bash
   mysql -u root -p -e "CREATE DATABASE invoicing DEFAULT CHARACTER SET utf8"
   mysql -u root -p invoicing < database.sql
   mysql -u root -p invoicing < database/adomin-updates.sql
   ```
4. Tell PHP where the database is — either environment variables, or the values at the top
   of `application/config/database.php` (its `localhost` / `root` / `test` fallbacks are the
   defaults, and env vars override them):
   ```bash
   export DB_HOST=127.0.0.1 DB_USER=root DB_PASS=yourpass DB_NAME=invoicing DB_PORT=3306
   ```
5. Make three directories writable by the web server:
   `attachments/`, `application/cache/`, `application/logs/` (and
   `application/libraries/dompdf/lib/fonts/`, which dompdf needs for its font metrics).
6. Open `http://localhost/invoicing` → **Register** → log in. The super-admin panel is
   `/admin` and its seeded login is `admin` / `admin` — change it in step one of §5.
7. The base URL auto-detects from the request. If links ever come out wrong (a subfolder,
   a proxy), hard-code `$config['base_url'] = 'https://example.com/';` in
   `application/config/config.php`.

---

## 2. Create the database (TiDB Cloud free tier, ~5 minutes)

Render's own free database is **PostgreSQL**, and this application is MySQL
(§6), so the database lives elsewhere. TiDB Cloud Starter is MySQL-compatible with a free
quota, which makes it the practical pairing.

1. [tidbcloud.com](https://tidbcloud.com) → **Create Cluster** → **Starter (Serverless)**.
2. Choose the **region nearest to Render's instance region**. Render free instances are
   normally `singapore-ap-southeast-1`; a cluster in `eu-central-1` behind them costs about
   1.5–1.9 s on *every* request, which is the difference between a snappy app and a sluggish
   one. You can change the Render region later, not the cluster region.
3. Set a database password when prompted, then press **Connect** and copy:
   * the **username** — it is `root@<cluster-id>`. The `@<cluster-id>` part is required by
     the TiDB gateway and is not decoration; `root` alone will fail authentication.
   * the **host** — `gateway01.<region>.prod.aws.tidbcloud.com`
   * the **port** — `4000`
4. **Create the application schema.** Open **DB Editor** → new SQL tab (with no schema
   selected) and run:
   ```sql
   CREATE DATABASE adomin;
   ```
   TiDB does not create an application schema for you. `defaultdb` exists on some Starter
   clusters and not on others, and the system schemas (`sys`, `mysql`, `information_schema`,
   `performance_schema`) are server-owned metadata — the app's tables cannot be created in
   them. Use your own name; `SHOW DATABASES;` lists what you have.
5. TLS is mandatory on this host: the gateway refuses an unencrypted connection, which in
   this application means setting `DB_SSL=1` (§4) — nothing is detected or guessed for you.

---

## 3. Import the schema (one time, ~2 minutes)

Two files, in this order, **into the schema you created**:

| File | Contents |
|---|---|
| `database.sql` | the application's 57 tables plus their seed data — 50 KB, 193 statements |
| `database/adomin-updates.sql` | Kenya defaults (KES currency, `KSh` symbol) and the columns and tables this fork expects: `firms_users.logo_url`, the `invoices.uniqid` index, `invoice_share_views` |

Neither file contains a `USE` statement, because the schema name differs per installation.
So the schema must be selected **before** running them: with `mysql`, pass
`--database=adomin`; in a GUI console (TiDB SQL editor, phpMyAdmin, Adminer, DBeaver), pick
it in the tree or the schema dropdown first. Running either file with nothing selected fails
every statement with `No database selected`, which reads like a broken migration but is not.

**Command line:**

```bash
mysql --ssl-mode=REQUIRED -h gateway01.<region>.prod.aws.tidbcloud.com -P 4000 \
      -u 'root@<cluster-id>' -p --database=adomin < database.sql

mysql --ssl-mode=REQUIRED -h gateway01.<region>.prod.aws.tidbcloud.com -P 4000 \
      -u 'root@<cluster-id>' -p --database=adomin < database/adomin-updates.sql
```

**In the TiDB SQL editor instead:** select `adomin` in the dropdown, paste the whole of
`database.sql`, run, then repeat with `database/adomin-updates.sql`. If the editor rejects a
statement, it is almost certainly the `PREPARE`/`EXECUTE` guards in the second file (some
consoles refuse them); run the two `ALTER TABLE` lines quoted inside that file's comments
instead, and the rest normally.

Both files are safe to re-run. `database.sql` uses the dump's own `CREATE TABLE` statements,
and everything in `adomin-updates.sql` checks whether it has already been applied — including
the KES row, which is inserted with `NOT EXISTS` rather than `INSERT IGNORE` because
`currencies` is seeded without a primary key for `IGNORE` to conflict on.

---

## 4. Create the Render service

1. Push this branch to GitHub.
2. Render → **New + → Blueprint** → choose the repository and this branch. Render reads
   `render.yaml`: `runtime: docker`, `plan: free`, `autoDeploy: true`, `healthCheckPath: /`,
   and every `ADOMIN_*` default.
3. The Blueprint form prompts for the six values marked `sync: false` — the secrets, which
   deliberately are not in the repository:

   | Key | Value |
   |---|---|
   | `DB_HOST` | `gateway01.<region>.prod.aws.tidbcloud.com` |
   | `DB_USER` | `root@<cluster-id>` |
   | `DB_PASS` | the database password from §2.3 |
   | `DB_NAME` | `adomin` |
   | `DB_PORT` | `4000` |
   | `DB_SSL` | `1` |

   Alternatively paste the provider's single connection string as `DATABASE_URL` and leave the
   six empty:
   `mysql://root%40<cluster-id>:password@host:4000/adomin?ssl-mode=REQUIRED`.
   Inside a URL every special character in the credentials must be percent-encoded
   (`root@123` → `root%40123`), which is the most common cause of an "access denied" that looks
   like a wrong password. Where both are set, the individual `DB_*` values win, so a partial
   override is possible.
4. **Apply**. The first build takes 2–4 minutes (Docker image: PHP 7.4 + Apache + the
   extensions in the header of this file, dompdf, no `wkhtmltopdf`); later builds are cached
   and take seconds.

You can add or change these at any time under **Render → your service → Environment →
Edit → Add**. Changing any of them triggers a restart, not a rebuild.

---

## 5. Verify the deployment

Read top to bottom; each step is the precondition for the next.

1. **Render → Log tail.** A good start looks like this:
   ```
   ==> Running docker/adomin-start.sh…
   Adomin: Apache listening on 0.0.0.0:10000
   AH00163: Apache/2.4.54 (Debian) configured -- resuming normal operations
   "GET / HTTP/1.1" 200 ...
   ```
   `200` on `GET /` is the whole test. Any `500` there means the app reached PHP but could not
   use the database — go to §9.
2. **Register.** Open `https://<service>.onrender.com` and create an account. The
   super-admin panel is at `/admin` with the seeded `admin` / `admin` — change that password
   now, before anything else, and set `$config['salt']` in `application/config/config.php`.
3. **Configure the company.** *Settings → Invoices*: currency **KES**, VAT **16 %**
   (output and input tax rates), payment terms, invoice number prefix.
   *Settings → Manage firms*: logo. On the free tier the filesystem is **disposable** — an
   uploaded logo disappears at the next deploy — so use the **Logo URL** field with a hosted
   image (Cloudinary, S3, a CDN, or any public URL). `logo_url` is read by both the interface
   and the PDF, and a file on disk still wins if it exists.
4. **Print.** Create an invoice and press **PDF**. This is the part a free container usually
   cannot do; it works here because dompdf is pure PHP. The first render after a deploy takes
   about a second while dompdf parses the DejaVu font metrics, then it is fast.
5. **Know the free tier's two behaviours**, so they are not mistaken for bugs: the instance
   **sleeps after 15 minutes** without traffic (the first request afterwards takes 30–60 s
   while the container starts — a keep-alive ping every 10 minutes prevents it, at the cost of
   your 750 free hours), and there is **no persistent disk**, which is why the database and the
   logos live outside the box.

---

## 6. Databases that will not work

This is a MySQL application: `mysqli`, 57 tables of MySQL DDL in `database.sql` plus one
more in `database/adomin-updates.sql` (`AUTO_INCREMENT`, `ENGINE=InnoDB`,
`utf8_general_ci`), and MySQL date functions in the models.

| Host | Verdict |
|---|---|
| TiDB Cloud Starter | ✅ recommended above — MySQL-compatible, free quota, TLS required |
| Aiven for MySQL, PlanetScale (paid), ClearDB, Railway, Scalegrid | ✅ any MySQL 5.7+ / MariaDB works; set `DB_SSL=1` where the host demands TLS |
| Your own VPS, Docker `mysql:5.7` / `mariadb:10.6` | ✅ import `database.sql` and `database/adomin-updates.sql` |
| **Neon, Supabase, Render's free Postgres** | ❌ PostgreSQL. Not a configuration problem: the dump does not run there and `mysqli` cannot speak the protocol. Porting means rewriting the DDL, switching `dbdriver` to `postgre` and retesting every raw query. Measured, that is bounded work — 14 `db->query()` calls and one MySQL-only function (`DATE_FORMAT`) in the whole app — but it is a migration, not a setting. |
| Firebase, Firestore, MongoDB, DynamoDB | ❌ different data model entirely |

If you already have a Postgres URL and want to keep it, that is a decision to make
deliberately: the files to change are `application/config/database.php` (`dbdriver`),
the models that use `DATE_FORMAT`, and the dump itself.

---

## 7. A VPS (when you outgrow the free tier)

The same image, no other change. Useful as soon as you want a real domain, HTTPS with your own
certificate, persistent disk for `attachments/`, or `wkhtmltopdf` for pixel-identical PDFs.

```bash
# on the box: Docker from the official install script, then
git clone <your repo> /srv/adomin && cd /srv/adomin
docker build -t adomin .                      # pass --build-arg WITH_WKHTMLTOPDF=1 for the binary
docker run -d --restart unless-stopped -p 80:10000 \
  -e DB_HOST=127.0.0.1 -e DB_USER=adomin -e DB_PASS=secret \
  -e DB_NAME=adomin -e DB_PORT=3306 \
  -e PDF_ENGINE=wkhtmltopdf -e BASE_URL=https://yourdomain.com \
  -v /srv/adomin/attachments:/var/www/html/attachments \
  adomin
```

The volume mount is what makes uploads survive; `BASE_URL` is read by `config/constants.php`
and stops the proxy auto-detection. Put nginx or Caddy in front for TLS, or use a panel
(Coolify, CapRover) that gives you the certificate. MySQL on the same box is fine — `DB_HOST`
becomes the Docker network name or the host address, not `localhost` (inside the container that
is the container).

---

## 8. Vercel ⚠️ — technically viable, still the wrong tool

Vercel is serverless and edge-oriented. Three of this application's assumptions do not exist
there:

| Problem | Detail | Status |
|---|---|---|
| PDF generation | was an `exec()` of `wkhtmltopdf`, and there is no shell in a Vercel function | **fixed**: `PDF_ENGINE=dompdf` is the default and never calls `exec()` |
| Logo uploads | the filesystem is read-only except `/tmp`; `attachments/` is lost on every deploy | mitigated: the `logo_url` field (§5.3). Full fix needs S3/Cloudinary in `ManagefirmsModel` |
| No database | Vercel offers none | external MySQL, same `DB_*` variables as §4 |
| PHP runtime | community legacy runtime, function timeouts of 10–60 s | long invoices can time out mid-render |

`vercel.json` is included: it rewrites every route to `index.php`. Import the repo, add the
`DB_*` variables, import the schema (§3), and set `ADOMIN_PDF_TMP=/tmp`. Honest verdict: viable
for a read-mostly demo; **Render free tier or a $5 VPS is the deployment to hand a client**,
because it is a persistent process with a filesystem.

---

## 9. Troubleshooting

Every symptom below was produced by a real deploy of this repository. The left column is what
you actually see in Render's log tail or browser.

| Symptom | Cause | Fix |
|---|---|---|
| **Build** fails: `Failed to fetch http://deb.debian.org/… 404 Not Found`, `exit code 100` | Debian 11 left the main mirrors when it reached end-of-life: `apt-get update` succeeds against a stale index and the *install* 404s | Handled in the Dockerfile: the install retries through `archive.debian.org`, and a failure can only cost an optional package, never the build. Set `WITH_WKHTMLTOPDF=0` to skip the 170 MB binary |
| `port scan timeout` on boot, `502 Bad Gateway` in the browser | Render sends traffic to `$PORT` (10000); stock Apache `Listen`s on 80 | Handled by `docker/adomin-start.sh`, which rewrites `Listen` and the vhost to `$PORT` and then execs `apache2-foreground` |
| `AH00526: Syntax error … Invalid command 'MinSpareThreads'`, then a restart loop | thread-MPM directives written inside `<IfModule mpm_prefork_module>` — PHP is an Apache *module* here, so the MPM is prefork, which has no thread knobs | Handled: `docker/adomin-mpm.conf` uses `Min/MaxSpareServers`, and `apache2ctl -t` runs at build **and** at start — the start script deletes its own tuning file rather than crash-looping |
| `Your base URL is wrong` / mixed-content warnings / unstyled pages | `BASE_URL` built from `http://` behind Render's TLS proxy, so assets and the PDF's asset URLs are wrong | Handled: `application/config/{constants,config}.php` honour `X-Forwarded-Proto` and `X-Forwarded-Host`. If it still happens, set `BASE_URL` explicitly |
| **Every page 500**, ~441-byte body, build and Apache look fine | CodeIgniter's generic *production* error page: the app reached PHP but cannot use the database — no `DB_NAME` schema, no tables in it, TLS refused, or the username is missing `@<cluster-id>`. `autoload.php` loads the `database` library on every request, so **all** routes fail, not just the ones that read data | See §10 |
| `No database selected` on every statement while importing | the schema was not selected in the editor | select it first — neither file has a `USE` statement (§3) |
| `Unknown database 'adomin'` | the schema was never created, or `DB_NAME` is spelled differently | `SHOW DATABASES;` in the SQL editor, then fix `DB_NAME` (§2.4) |
| `SSL connection error: plaintext connections are disallowed` | TLS off on a host that requires it | `DB_SSL=1` (§4) |
| `Access denied for user 'root'` | TiDB needs the cluster suffix, and `@` must be encoded inside a URL | `DB_USER=root@<cluster-id>`; as a URL, `root%40<cluster-id>` (§4.3) |
| PDFs unstyled, or the logo is missing from the PDF but visible on screen | `application/libraries/dompdf/lib/fonts/` not writable, or a logo that only exists on a disposable disk | the image already `chmod`s that directory; check the mount, and use **Logo URL** (§5.3) |
| "Uploads vanish after a deploy" | free instance has no persistent disk | by design. Database external, files on object storage, or a VPS volume (§7) |
| Requests are uniformly slow (~1.5–2 s on top of everything) | Render free tier is in Singapore and the database region was chosen in another continent | recreate the cluster in the Render region (free, new cluster) or move the Render service to the DB's region. Measure before assuming code |

---

## 10. When a page 500s, get the real error

The app runs with `ENVIRONMENT = 'production'` (`index.php`, line 57), which is correct for
customers: errors go to the log file, and the visitor gets a generic page. To read the actual
message, use the framework's own switch — nothing custom is needed:

1. In `index.php`, temporarily change `$env = 'production';` to `$env = 'development';`,
   commit and push (this is the only case where editing `index.php` is the right move).
2. Reload the page. CodeIgniter now prints the exception and, because `db_debug` is
   `TRUE` outside production, the literal MySQL error text and the failed SQL.
3. Read `application/logs/log-*.php` — the same detail is written there on *every* request
   in production too, which is the place to look when you cannot spare a visible error page.
   On Render you reach it through **Render → SSH** (free tier: no shell), so the practical
   route is to deploy where you can read files (§7), or copy the file from a local run.
4. **Revert step 1.** A development error page on a public host discloses paths, SQL and
   configuration to anyone who trips an error.

The four causes account for almost every case, and §9 covers all four.

---

## 11. Configuration reference

Everything is optional and read from the environment; `application/config/adomin.php` holds
the defaults, and an environment variable always overrides the file. Set them in
**Render → Environment** (or `export` for a shell, or the `env:` list in a `docker run`).

| Variable | Default | Meaning |
|---|---|---|
| `DB_HOST` `DB_USER` `DB_PASS` `DB_NAME` `DB_PORT` | `localhost` `root` – `test` `3306` | connection. `DB_PASS` is never read from the file, only the environment |
| `DATABASE_URL` / `DB_URL` / `MYSQL_URL` / `CLEARDB_DATABASE_URL` | – | one connection string instead of the five; `mysql://` only |
| `DB_SSL` | off | `1` turns TLS on for the `mysqli` connection. Explicit by design — no host is detected or guessed |
| `DB_SSL_CA` | system bundle | path to the CA file, used when TLS is on |
| `DB_SSL_VERIFY` | `0` | `1` additionally requires the server certificate to match the host name. Keep at `0` for TiDB/Aiven gateways whose certificate does not match the gateway hostname; that is encryption without identity pinning, like `mysql --ssl-mode=REQUIRED` |
| `PDF_ENGINE` | `dompdf` | `dompdf` (pure PHP, any host) or `wkhtmltopdf` (needs the binary, §7) |
| `WKHTMLTOPDF_PATH` | `wkhtmltopdf` on `PATH` | only read when `PDF_ENGINE=wkhtmltopdf` |
| `ADOMIN_PDF_TMP` | the dompdf `lib/fonts` dir | where dompdf writes cache and metrics when that dir is not writable (`/tmp` on Vercel) |
| `ADOMIN_PDF_STAMP` | `1` | the "Powered by Adomin Technologies · <date>" line on documents and the PDF footer. `0` removes both |
| `ADOMIN_BRAND_NAME` `ADOMIN_BRAND_SHORT` `ADOMIN_APP_NAME` `ADOMIN_TAGLINE` `ADOMIN_WEBSITE` `ADOMIN_SUPPORT_EMAIL` | Adomin values | naming in titles, PDFs, e-mail bodies, the manifest and the install splash |
| `ADOMIN_ACCENT` `ADOMIN_THEME_COLOR` | `#2f8f5b` `#0f1720` | accent colour, and the browser theme colour of the installed shell |
| `ADOMIN_TIMEZONE` `ADOMIN_CURRENCY` `ADOMIN_DATE_FORMAT` | `Africa/Nairobi` `KES` `d.m.Y` | locale defaults; the per-company settings override them |
| `ADOMIN_ANIMATIONS` | `1` | the motion layer. `0` ships a stylesheet that cancels transitions and animations |
| `ADOMIN_PWA` | `1` | manifest + service worker (installable, offline shell) |
| `ADOMIN_SHARE_LINKS` | `1` | the public share-by-link routes |

Two more things are deployment-relevant but not environment variables:

* `application/config/database.php` is the only file that touches credentials; it contains
  **no secret** — just the fallbacks a local install uses.
* `render.yaml` is the source of truth for the Render service; the secrets in it are
  `sync: false`, which means Render prompts for them and never stores them in the repository.

---

## 12. Pre-flight checklist

- [ ] `admin` / `admin` at `/admin` changed, and `$config['salt']` in `config.php` is not the shipped value
- [ ] `ENVIRONMENT = 'production'` in `index.php` (§10 reverted if you changed it)
- [ ] `DB_*` set on Render (or `DATABASE_URL`), `DB_SSL=1` for hosts that require TLS, secrets kept out of `render.yaml`
- [ ] Both SQL files imported into **your** schema, with the schema selected
- [ ] `application/logs/` writable and a `log-*.php` file appears — if nothing is written there, error logs are the next thing to hunt
- [ ] `application/libraries/dompdf/lib/fonts/` writable (first render parses the DejaVu metrics into it)
- [ ] HTTPS through the proxy verified: assets load, and the PDF's URLs are `https://`
- [ ] KES / 16 % VAT / M-Pesa set in *Settings → Invoices*; `database/adomin-updates.sql` applied
- [ ] Tested after the first deploy: PDF download (original and copy), protocol / movement / warranty PDF, logo upload **and** `logo_url`, send-via-link open and revoke, accept/refuse, `ADOMIN_ANIMATIONS=0`
- [ ] MySQL backed up (a TiDB export or `mysqldump`); `attachments/` understood to be disposable on the free tier
- [ ] Keep-alive decided: 750 free hours ≈ one always-on service, so pinging it every 10 minutes spends the month's budget

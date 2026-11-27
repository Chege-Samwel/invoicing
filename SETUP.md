# Setup Guide — TiDB Cloud + Render (start here)

This is the ordered walkthrough for putting **Adomin Invoices** online with
**TiDB Cloud** (the database) and **Render** (the web server). It assumes you
have a browser, a GitHub account, and nothing else installed. Follow it top
to bottom; each step is the precondition for the next.

> **Already tried the old guide and it failed?** Open
> `https://<your-service>.onrender.com/status.php` and read the one line at the
> top — it names the failure. Then see §5. The common causes are: the TiDB
> username typed from memory instead of copied from the Connect dialog (§1.3),
> `DB_PORT` left at `3306` instead of `4000` (§2.3), `DB_SSL` left off (§2.3),
> and an import that produced 58 tables but no auto-increment counters (§3.1).
> The container now creates the database, imports the tables and repairs those
> counters automatically, so you no longer paste SQL in a browser console.

**What you are building:**

```
Browser (you / your client)
   |  https://<service>.onrender.com
   v
Render — Docker container (PHP 7.4 + Apache, free tier)
   |  TLS (encrypted) on port 4000
   v
TiDB Cloud Serverless (MySQL-compatible, free tier)
   database: adomin  (58 tables)
```

Render cannot host MySQL (its free database is PostgreSQL, which this app
cannot use), so the database lives on TiDB Cloud and the app connects to it
over the internet. That is normal — it is why the connection settings in §2
matter so much.

**Time:** about 20 minutes. **Cost:** $0 (both free tiers).

---

## 1. Create the database on TiDB Cloud (~5 minutes)

### 1.1 Create the cluster

1. Go to [tidbcloud.com](https://tidbcloud.com) and create an account.
2. **Create Cluster** → **Starter (Serverless)** — this is the free tier.
3. **Region:** pick the region closest to where Render will run (§2.1).
   For Kenya the best pairing is **Render: Frankfurt** + **TiDB:
   `eu-central-1` (Frankfurt)**. If you already created the Render service
   in Singapore, pick TiDB `ap-southeast-1` (Singapore) instead. The two
   must be on the same continent — every page load talks to the database,
   and a cross-continent pairing adds 1.5–2 seconds to *every* request.
4. Set a **database password** when prompted (save it — you will need it in
   §2.3). Wait for the cluster status to become **Available** (a minute or
   two).

### 1.2 Open the Connect dialog

1. Click your cluster → **Connect** (top right).
2. In the dialog set:
   * **Endpoint Type:** Public
   * **Connect With:** General
   * **Operating System:** Linux (any — it only changes the example commands)
3. Leave the dialog open. You need five values from it.

### 1.3 Copy the five values — exactly, do not type them

| Value | Example | Where to put it |
|---|---|---|
| **Host** | `gateway01.eu-central-1.prod.aws.tidbcloud.com` | `DB_HOST` |
| **Port** | `4000` | `DB_PORT` |
| **Username** | `AbCdEfGh123456.root` | `DB_USER` |
| **Password** | the password from §1.1 step 4 | `DB_PASS` |
| **Database** | `test` (ignore this — the app uses `adomin`) | — |

**The username is the #1 failure.** On current TiDB Cloud Serverless it
looks like `<prefix>.root` (e.g. `AbCdEfGh123456.root`) — the whole string,
dot included. Older clusters show `root@<cluster-id>` instead. Whichever
form the dialog shows, **copy it character for character**. Typing `root`
alone, or dropping the `.root` / `@...` suffix, gives
`Access denied for user 'root'` and the app shows a blank 500 page.

**You do NOT need to create a database or import anything by hand.** The
container creates the `adomin` schema and imports all 58 tables on its
first start (see §3.2). The browser SQL editor is not used in this guide —
it is known to run only some statements and still report "Query OK", which
leaves a half-built database that looks like a broken app.

---

## 2. Create the Render service (~5 minutes)

### 2.1 Push the code to GitHub

1. Push this repository to GitHub on the **default branch** (`master`).
   The Blueprint in `render.yaml` follows the default branch — there is no
   branch pin, so pushing to `master` is what triggers a deploy.
2. Confirm these files are in the repository root: `render.yaml`,
   `Dockerfile`, `docker/adomin-start.sh`, `docker/adomin-init-db.php`,
   `database.sql`, `database/adomin-updates.sql`.

### 2.2 Create the service from the Blueprint

1. Go to [dashboard.render.com](https://dashboard.render.com) → **New +** →
   **Blueprint**.
2. Connect GitHub, select this repository. Render reads `render.yaml`
   automatically: Docker runtime, free plan, Frankfurt region, health check
   on `/`, auto-deploy on.
3. Render prompts for the secrets marked `sync: false` (they are never
   stored in the repository). Fill them in from §1.3 — see the table in
   §2.3. Leave `DATABASE_URL` empty (you are using the five `DB_*` fields).
4. Click **Apply**. The first build takes 2–4 minutes (PHP 7.4 + Apache).
   Later builds are cached and take seconds.

> **Manual service instead?** If you click **New + → Web Service** instead
> of Blueprint, you must choose **Runtime: Docker**, **Region: Frankfurt**,
> **Instance Type: Free**, **Dockerfile Path: `./Dockerfile`**, and then add
> every environment variable from §2.3 by hand. Missing one variable is the
> usual reason a manual service 500s while a Blueprint service works.

### 2.3 Environment variables (the table that matters)

In the Blueprint form, or afterwards under **Render → your service →
Environment**, the database variables must be exactly:

| Key | Value | Notes |
|---|---|---|
| `DB_HOST` | `gateway01.eu-central-1.prod.aws.tidbcloud.com` | from §1.3 |
| `DB_USER` | `AbCdEfGh123456.root` | from §1.3, copied exactly |
| `DB_PASS` | *(your TiDB password)* | from §1.1 |
| `DB_NAME` | `adomin` | the container creates it |
| `DB_PORT` | `4000` | TiDB's port — **not** `3306` |
| `DB_SSL` | `1` | **mandatory** on TiDB — without it the gateway refuses the connection |
| `DB_SSL_VERIFY` | `0` | encryption without hostname pinning (like `ssl-mode=REQUIRED`) |
| `APP_ENV` | `production` | set to `development` only while debugging (§5) |
| `ADOMIN_AUTO_IMPORT` | `1` | create schema + import tables on first start, and repair primary keys on every start |
| `ADOMIN_FIX_AUTO_INCREMENT` | `1` | repair primary keys TiDB left without a counter (§3.1). Leave at `1` |
| `STATUS_TOKEN` | *(any long random string)* | optional; unlocks the detailed report at `/status.php?token=…` (§4 step 0) |

Changing any of these triggers a **restart**, not a rebuild (fast). The
`ADOMIN_*` branding, locale (KES, `Africa/Nairobi`) and PDF settings are
already in `render.yaml` — you do not need to set them.

**Do not use `DATABASE_URL` unless you have to.** The five `DB_*` fields
above are simpler and safer. A connection URL must percent-encode special
characters in the password (`@` → `%40`, etc.), and a single unencoded
character gives "access denied" that looks like a wrong password. If you
do use it, the form is
`mysql://<user>:<password>@<host>:4000/adomin?ssl-mode=REQUIRED` with the
user and password percent-encoded.

---

## 3. First deploy — what happens and what to watch

### 3.1 Watch the logs

Go to **Render → your service → Logs**. A good first start looks like:

```
Adomin: Apache listening on 0.0.0.0:10000
Adomin: initialising database (if needed)...
Adomin DB: Connecting to gateway01.eu-central-1.prod.aws.tidbcloud.com:4000 as AbCdEfGh123456.root / db adomin (TLS on)...
Adomin DB: Schema adomin currently has 0 tables.
Adomin DB: database.sql: 193 statements to run.
Adomin DB: This server refuses ALTER TABLE ... AUTO_INCREMENT (57 statements); rebuilding those tables instead.
Adomin DB: database.sql: finished - 186 ok, 0 failed.
Adomin DB: adomin-updates.sql: finished - 5 ok, 0 failed.
Adomin DB: 57 table(s) have a primary key without AUTO_INCREMENT (TiDB cannot add it with ALTER TABLE) - rebuilding them...
Adomin DB: AUTO_INCREMENT repaired on 57 table(s).
Adomin DB: Done. Schema adomin now has 58 tables (expected 58).
Adomin DB: Database ready. ...
AH00163: Apache/2.4.54 (Debian) configured -- resuming normal operations
"GET / HTTP/1.1" 200 ...
```

**Why the AUTO_INCREMENT lines matter.** `database.sql` is a phpMyAdmin
dump: it creates every table without an auto-increment counter and adds
the counter afterwards with `ALTER TABLE ... MODIFY id ... AUTO_INCREMENT`.
MySQL allows that, TiDB does not (error `8200`, the attribute can only be
set in `CREATE TABLE`) — so on a TiDB import every table exists but none
of the primary keys can generate an id, and the first `INSERT` fails with
*Field 'id' doesn't have a default value*. Registration and login then
fail on a schema that looks perfectly imported. The container therefore
rebuilds each affected table (`CREATE ... AUTO_INCREMENT` + copy rows +
rename) right after the import. If you imported by hand instead, run
`php database/tidb-fix-increments.php` once (§3.3) or simply redeploy.

The rebuild prints one line per table and takes about 1–2 minutes for all
57 on the free TiDB tier, so a first start can spend a couple of minutes
there before Apache comes up — that is expected, and Render's log shows the
`[n/57]` counter moving. On a plain MySQL server every counter is already
set, the block is skipped and only the single `information_schema` query
runs.

The import runs once. On every later restart the check is one query
(`Schema adomin currently has 58 tables. Already installed - checking the
primary keys.`) plus one `information_schema` lookup that finds nothing to
repair, and Apache starts immediately.

### 3.3 If you imported by hand (browser SQL console)

Do not skip the last step: a manual import into TiDB leaves the same
missing counters. Either let the container fix it — set
`ADOMIN_AUTO_IMPORT=1`, restart, and it repairs without re-importing — or
run the repair script once from the Render **Shell** (or any machine with
the same `DB_*` variables):

```bash
php database/tidb-fix-increments.php --check   # lists the affected tables
php database/tidb-fix-increments.php           # repairs them
```

### 3.2 What the container did for you

* Created the `adomin` schema (TiDB never creates it for you).
* Imported `database.sql` (57 tables + seed data) and
  `database/adomin-updates.sql` (KES currency, logo URL column, share-link
  index + view log table = 58 tables total).
* Rebuilt every table whose primary key TiDB would not give a counter
  (§3.1) — without this step the site loads but nobody can register or log
  in.
* Healed a partial import: if a previous browser import left 1–56 tables,
  the container drops them and imports fresh, so you do not need to clean
  up by hand.

If the logs instead show `ERROR: could not connect ...` or
`ERROR: import incomplete`, go to §5 — the log line names the exact cause.

---

## 4. Verify the deployment (~5 minutes)

Read top to bottom; each step is the precondition for the next.

0. **Check the deployment health page first.** Visit
   `https://<service>.onrender.com/status.php`. It answers, without
   depending on the app booting, whether PHP, the database and the schema
   are usable, and prints one line telling you what to do next. A healthy
   deployment shows *ready*, “58 of 58” tables and “none” under
   *Primary keys missing AUTO_INCREMENT*.
   Add a `STATUS_TOKEN` environment variable in Render and open
   `/status.php?token=<value>` to also see host, TLS mode, the exact MySQL
   error, the initialiser log and **Recent errors** (the last lines of
   `application/logs/`, including every `Adomin FATAL`); without it those
   details stay hidden. This is how you read the log on the free tier —
   there is no shell.
   `?format=json` gives the same report as JSON, and the HTTP status is
   `200` when usable / `503` when not — so it also works as a readiness
   probe if you would rather Render restart an instance that cannot reach
   its database (change `healthCheckPath` in `render.yaml` to `/status.php`
   only if you want that; the default `/` is liveness).
1. **Open the site.** Visit `https://<service>.onrender.com`. The first
   visit after a deploy may take 30–60 seconds (the free instance sleeps
   after 15 idle minutes and needs to wake up). The service worker shows
   an offline page during wake-up — wait and reload once.
2. **Register.** Click **Register**, create your account, log in.
3. **Change the admin password immediately.** Go to `/admin` (e.g.
   `https://<service>.onrender.com/admin`) and log in with the seeded
   `admin` / `admin`. Change that password before anything else, and set
   `$config['salt']` in `application/config/config.php` to a long random
   string (then redeploy). The seeded login is public knowledge.
4. **Configure the company.** *Settings → Invoices*: currency **KES**,
   VAT **16%** (output and input rates), payment terms, invoice number
   prefix. *Settings → Manage firms*: logo. On the free tier the
   filesystem is disposable — an uploaded logo disappears at the next
   deploy — so use the **Logo URL** field with a hosted image (Cloudinary,
   S3, any public URL). A file on disk still wins if it exists.
5. **Print a PDF.** Create an invoice and press **PDF**. This works on the
   free tier because PDFs render with bundled dompdf (pure PHP, no binary).
   The first render after a deploy takes about a second while font metrics
   are parsed, then it is fast.
6. **Test the rest once:** protocol / movement / warranty PDF, share-by-link
   open and revoke, accept/refuse.

**Know the free tier's two behaviours** so they are not mistaken for bugs:
the instance **sleeps after 15 minutes** without traffic (first request
afterwards takes 30–60 s), and there is **no persistent disk** (uploads are
wiped on deploy; the database and logos live outside the box — §4 step 4).

---

## 5. Troubleshooting — match your symptom

Every row below was produced by a real deploy of this repository. The
left column is what you actually see in Render's **Logs** or the browser.

| Symptom (log / browser) | Cause | Fix |
|---|---|---|
| `ERROR: could not connect to gateway01...` + `Access denied for user 'root'` | Username is wrong — typed `root` instead of copying the full `<prefix>.root` (or `root@<cluster-id>`) from the Connect dialog | Re-copy `DB_USER` from §1.3 character for character. Save, restart. |
| `ERROR: could not connect ...` + `SSL connection error: plaintext connections are disallowed` / `Connections using insecure transport are prohibited` | TLS off on a host that requires it | Set `DB_SSL=1` (§2.3). Save, restart. |
| `ERROR: could not connect ...` + `Connection refused` / timeout on port `3306` | `DB_PORT` left at MySQL's `3306`; TiDB listens on `4000` | Set `DB_PORT=4000` (§2.3). Save, restart. |
| `ERROR: could not connect ...` + `Unknown database 'adomin'` | `DB_NAME` misspelled, or the schema was dropped | Set `DB_NAME=adomin` exactly. The container recreates it on next start. |
| `ERROR: CREATE DATABASE failed ...` + privilege error | TiDB user lacks CREATE privilege (rare on Serverless) | Create the schema once in the TiDB SQL editor: `CREATE DATABASE adomin;` (select no schema first), then restart. |
| `ERROR: import incomplete - only N tables` + per-statement errors above | A statement in `database.sql` was rejected. Read the `[errno]` lines — they name the table/statement | If errors mention a table that already exists, the schema was half-imported: the container heals this automatically on next start. If errors persist, set `APP_ENV=development`, reload the site to see the MySQL error in the browser, then set `APP_ENV=production` again. |
| Every page 500, small error body, Apache looks fine | The app reached PHP but cannot use the database | Open `/status.php` (§4 step 1) — it names the cause and the exact MySQL error. The usual four: username, TLS, port, schema. |
| **Submitting a form** (`/login`, registration, accept/refuse) returns 500, everything else works | Either the CSRF token was missing from the POST (CodeIgniter's answer is a 403; behind Render's gzip it shows up as a small 500-sized body), or a fatal happened in the handler | Both are now covered: every posting form carries a server-rendered token (no JavaScript needed), so a reload is enough; and a fatal writes an `Adomin FATAL:` line to Render → Logs **and** to `application/logs/`, which `/status.php` (with `STATUS_TOKEN`) shows under *Recent errors*. |
| 500 page says *This page could not be rendered* | That is the new fatal-error page — it replaced a blank Apache 500 | Read the detail on the page when `APP_ENV=development`, or the `Adomin FATAL` line in Render → Logs / `/status.php`. |
| Site loads, but **Register** or **login** fails with `Field 'id' doesn't have a default value` (or `/status.php` says tables are missing `AUTO_INCREMENT`) | TiDB rejected the dump's `ALTER TABLE ... AUTO_INCREMENT` block, so primary keys have no counter (§3.1) | Redeploy with `ADOMIN_AUTO_IMPORT=1`: the container repairs them on start. Or run `php database/tidb-fix-increments.php` once (§3.3). |
| Every page 500 **with a development error page** naming `languages` / `PublicModel` | Older image: the language library queried the database during boot and gave up when that one query failed | The library now falls back to the default language and logs the reason; redeploy from the current branch. The page loads and `/status.php` tells you what the database is doing. |
| `/status.php` says *Not found* | The service is running an image built before this page existed | Redeploy (Manual Deploy → *Deploy latest commit*). |
| `port scan timeout` on boot, `502 Bad Gateway` | Apache listening on 80 while Render sends traffic to `$PORT` (10000) | Handled by `docker/adomin-start.sh`. If you see this, the container is running an old image — redeploy from the current `master`. |
| Build fails: `Failed to fetch http://deb.debian.org/... 404`, `exit code 100` | Debian 11 (the PHP 7.4 base) reached end-of-life and left the main mirrors | Handled in the `Dockerfile` (retries via `archive.debian.org`). If the build still fails, redeploy — the retry is best-effort and a failure can only cost an optional package, never the build. |
| `Your base URL is wrong` / mixed-content / unstyled pages | `BASE_URL` built from `http://` behind Render's TLS proxy | Handled by `application/config/constants.php` (honours `X-Forwarded-Proto/Host`). If it persists, set `BASE_URL=https://<service>.onrender.com` explicitly. |
| PDFs unstyled, or logo missing from PDF but visible on screen | dompdf font dir not writable, or logo only exists on the disposable disk | The image already makes the font dir writable. Use **Logo URL** (§4 step 4) for the logo. |
| "Uploads vanish after a deploy" | Free instance has no persistent disk | By design. Database is external, files go on object storage, or move to a VPS volume (see `DEPLOYMENT.md` §7). |
| Requests uniformly slow (~1.5–2 s on top of everything) | Render and TiDB in different continents | Recreate the TiDB cluster in the Render region (free, new cluster) or move the Render service to the DB's region. Measure before assuming code. |

**To see the real error in the browser (debugging only):** set
`APP_ENV=development` on Render, save (restart), reload the page.
CodeIgniter prints the exception and the literal MySQL error text. When
you are done, set `APP_ENV=production` again — a development error page on
a public host discloses paths, SQL and configuration to anyone who trips
an error.

---

## 6. Local development (optional)

For testing on your own machine (XAMPP / WAMP / Laragon):

1. Install **PHP 7.4 + Apache + MySQL**. Do not use PHP 8: CodeIgniter
   3.1.13 emits deprecation notices on it and the bundled PHPExcel breaks.
   `wkhtmltopdf` is **not** needed — PDFs render with bundled dompdf.
2. Copy the repository to the web root, e.g. `htdocs/invoicing`.
3. Create a database and import the schema:
   ```bash
   mysql -u root -p -e "CREATE DATABASE invoicing DEFAULT CHARACTER SET utf8"
   mysql -u root -p invoicing < database.sql
   mysql -u root -p invoicing < database/adomin-updates.sql
   ```
   (Or point the local MySQL at TiDB and let the container logic do it —
   see `DEPLOYMENT.md` §3.3 for the DBeaver route.)
4. Tell PHP where the database is — environment variables, or the values
   at the top of `application/config/database.php`. With nothing set it
   uses the file's own `localhost` / `root` / `toor` / `test` / `3306`
   (this project's local defaults, unchanged).
   ```bash
   export DB_HOST=127.0.0.1 DB_USER=root DB_PASS=yourpass DB_NAME=invoicing DB_PORT=3306
   ```
5. Make three directories writable by the web server: `attachments/`,
   `application/cache/`, `application/logs/` (and
   `application/libraries/dompdf/lib/fonts/`).
6. Open `http://localhost/invoicing` → **Register** → log in. The
   super-admin panel is `/admin` (`admin` / `admin` — change it).

---

## 7. After you are live

* [ ] `admin` / `admin` at `/admin` changed, and `$config['salt']` in
      `config.php` is not the shipped value
* [ ] `APP_ENV=production` on Render (reverted if you debugged with
      `development`)
* [ ] `DB_*` set on Render, `DB_SSL=1`, secrets kept out of `render.yaml`
      (they are `sync: false` — Render prompts for them, never commits them)
* [ ] Logs show `58 tables`, `AUTO_INCREMENT repaired` (or nothing to
      repair) and `GET / 200`
* [ ] `/status.php` shows **ready**, 58 of 58 tables, no missing
      `AUTO_INCREMENT`
* [ ] KES / 16% VAT / M-Pesa set in *Settings → Invoices*
* [ ] Tested: PDF download (original and copy), protocol / movement /
      warranty PDF, logo URL, send-via-link open and revoke
* [ ] MySQL backed up (TiDB export or `mysqldump`); `attachments/`
      understood to be disposable on the free tier
* [ ] Keep-alive decided: 750 free hours ≈ one always-on service, so
      pinging it every 10 minutes spends the month's budget

**Deeper references:** `DEPLOYMENT.md` (VPS, Vercel, manual import,
configuration reference), `BRIEF.md` (what the app does and how to
customise it per company), `database/import/README.md` (browser-console
import, only needed if you set `ADOMIN_AUTO_IMPORT=0`).

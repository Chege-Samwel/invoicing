# Deployment Guide — Adomin Invoices

> **Start with `SETUP.md`** for the ordered TiDB + Render walkthrough (database
> creation → Render service → first login → troubleshooting). This file is the
> deeper reference behind it (VPS, Vercel, manual import, configuration
> reference). The container now creates the schema and imports the tables
> automatically on first start (`docker/adomin-init-db.php`, controlled by
> `ADOMIN_AUTO_IMPORT`), so §3 below is only needed if you set that to `0`.

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
   of `application/config/database.php`. With nothing set it uses the file's own
   `localhost` / `root` / `toor` / `test` / `3306`, i.e. this project's local defaults,
   unchanged; an environment variable overrides them.
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
2. Choose the **region nearest to Render's instance region**. For Kenya the
   best pairing is **Render: Frankfurt** + **TiDB: `eu-central-1`** (or
   Render Singapore + TiDB `ap-southeast-1`). A cross-continent pairing
   costs about 1.5–1.9 s on *every* request, which is the difference between
   a snappy app and a sluggish one. You can change the Render region later
   (by creating a new service), not the cluster region.
3. Set a database password when prompted, then press **Connect** (Endpoint
   Type: Public, Connect With: General) and copy **exactly** what the dialog
   shows — do not type it from memory:
   * the **username** — on current Serverless/Starter clusters it is
     `<prefix>.root` (e.g. `AbCdEfGh123456.root`). Older clusters show
     `root@<cluster-id>` instead. Copy whichever form the dialog shows,
     whole string included; `root` alone will fail authentication.
   * the **host** — `gateway01.<region>.prod.aws.tidbcloud.com`
   * the **port** — `4000` (TiDB's port, not MySQL's `3306`)
4. **The schema is created for you.** TiDB does not create an application
   schema for you, but the container does: on first start
   `docker/adomin-init-db.php` runs `CREATE DATABASE IF NOT EXISTS adomin`
   and imports the tables (§3). To create it by hand instead, open **DB
   Editor** → new SQL tab (with no schema selected) and run
   `CREATE DATABASE adomin;` — the system schemas (`sys`, `mysql`,
   `information_schema`, `performance_schema`) are server-owned metadata and
   cannot hold the app's tables. `SHOW DATABASES;` lists what you have.
5. TLS is mandatory on this host: the gateway refuses an unencrypted connection, which in
   this application means setting `DB_SSL=1` (§4) — nothing is detected or guessed for you.

---

## 3. Import the schema (automatic — manual only if you disable it)

**On Render/Docker you do not import by hand.** On first start the container
(`docker/adomin-init-db.php`, enabled by `ADOMIN_AUTO_IMPORT=1`) creates the
schema and imports both files below, then verifies 58 tables in the logs.
This section is the manual fallback for `ADOMIN_AUTO_IMPORT=0`, for a VPS
without the container, and for understanding what the container runs.

Two imports, in this order, **into the schema you created**:

| File | Contents |
|---|---|
| `database/provision.sql` | creates the schema itself, and prints what it made — §3.4, the file to copy per project |
| `database.sql` | the application's 57 tables and their seed data — 50 KB, **186 SQL statements**: 57 `CREATE TABLE`, 11 `INSERT`, 116 `ALTER` (a primary key and an `AUTO_INCREMENT` counter per table, 3 unique keys, two `ADD COLUMN`s at the very end), 2 `SET`. It has **no foreign keys**, no fulltext indexes and no partitions, which is what makes it importable as it stands |
| `database/adomin-updates.sql` | five plain statements: Kenya defaults (KES currency, `KSh` symbol) plus what this fork expects — `firms_users.logo_url`, the `invoices.uniqid` index, `invoice_share_views` |

### 3.0 TiDB cannot add `AUTO_INCREMENT` with `ALTER TABLE` — the container repairs it

The dump creates each table without an auto-increment counter and then adds it:

```sql
ALTER TABLE `clients` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
```

MySQL accepts that; TiDB does not, and offers no alternative — the attribute can only be set in
`CREATE TABLE`:

```
ERROR 8200 (HY000): Unsupported modify column: can't set auto_increment
```

All 57 of those statements fail on TiDB (they are statements 132–188 of the dump, one per table).
Nothing errors out, so the import still ends with 58 tables — and every primary key is left with no
counter, which makes the **first insert into any table** fail with
`Field 'id' doesn't have a default value`. In practice: the site loads, registration and login do
not work.

`docker/adomin-init-db.php` handles it in two places:

* during the import it recognises the TiDB error (`8200` on an `ALTER TABLE … AUTO_INCREMENT`
  statement) and counts it as a repair rather than a failure, so the log is not 57 alarming lines;
* afterwards `adomin_init_repair_auto_increment()` asks `information_schema.COLUMNS` which tables
  have an `id` without the attribute, and rebuilds exactly those: `CREATE TABLE <t>_adomin_old` with
  the attribute, `INSERT … SELECT *`, `DROP TABLE <t>`, `RENAME TABLE`. If a step fails the copy is
  dropped again, so the schema is never left half-migrated. The whole check is one query when the
  schema is healthy, so it also runs on every start, including on an installation imported by hand.

Running it by hand (manual import into TiDB, or a schema the container cannot reach):

```bash
php database/tidb-fix-increments.php --check   # report only, exit 1 if broken
php database/tidb-fix-increments.php           # rebuild the affected tables
```

`/status.php` reports the same thing as *Primary keys missing AUTO_INCREMENT*.

`database/import/01-of-09.sql` … `09-of-09.sql` are the same 186 statements, cut only between
statements, for a console that iterates but cannot swallow 50 KB at once. Nothing is rewritten in
them — the SQL text of the nine parts, read in order, is the SQL text of the dump, which the
generator asserts. **They do not help a console that drops statements** (§3.3), so the desktop
client is the route this project needs and those nine files are the fallback for other hosts.

### 3.1 With a MySQL client, if you have one

```bash
mysql --default-character-set=utf8mb4 --ssl-mode=REQUIRED \
      -h gateway01.<region>.prod.aws.tidbcloud.com -P 4000 \
      -u '<prefix>.root' -p --database=adomin < database.sql

mysql --default-character-set=utf8mb4 --ssl-mode=REQUIRED \
      -h gateway01.<region>.prod.aws.tidbcloud.com -P 4000 \
      -u '<prefix>.root' -p --database=adomin < database/adomin-updates.sql
```
(Replace `<prefix>.root` with the exact username from the TiDB Connect
dialog — older clusters show `root@<cluster-id>` instead.)

The client splits the script, so the size of the file and the one comment line mentioned below
do not matter there.

### 3.2 In a browser console (TiDB's SQL editor, phpMyAdmin, Adminer)

Neither file contains a `USE` statement, because the schema name differs per installation, so
**select the schema first** — the dropdown or the tree, in the tab you paste into. With nothing
selected, every statement fails with `No database selected`, which reads like a broken migration
but is not.

1. New SQL tab → schema dropdown → `adomin`.
2. Paste `database/import/01-of-09.sql`, **select all of it** (Ctrl+A / Cmd+A), Run.
3. Repeat for `02` … `09`, in order, then `database/adomin-updates.sql`.
4. Verify with the count query in `database/import/README.md`: 57 tables.

If that count does not move, or lands on a number matching only the first few statements of a
part, the console is batching and discarding the rest of the paste. Cutting the script smaller
does not fix that; §3.3 is the way out, and it is what this project's host needed.

**Why select-all matters more than anything else on this page.** A console with the cursor inside
the text runs *only the statement under the cursor*. It answers `Query OK` in 0 ms, and nothing
else happens — no error, no table, no hint that the other 185 statements were never sent. This
is the single most common way an import "succeeds" and the app still 500s, and it is indistinguishable
from success unless you count tables afterwards.

**Why the dump is cut into nine pieces.** A browser console handles a 50 KB script badly in three
specific ways, all of them worth knowing because they look like SQL errors:

| | |
|---|---|
| Size | several consoles cap a paste or a script well below 50 KB, and some parse the whole thing before running any of it, so one line they dislike cancels all 186 statements and imports nothing |
| `;` inside a comment | `database.sql` line 1277 is a comment whose text ends with a semicolon (`-- Migration: currency/exchange-rate snapshot on invoices (for existing installs;`). A console that splits on `;` without parsing cuts the comment in half there and hands itself a fragment that is not a statement. The parts drop standalone comment lines, which is why they exist in that form |
| executable comments | the dump's seven `/*!40101 SET NAMES utf8mb4 */;` lines are MySQL version gates, not statements; a thin parser tries to parse them. Part 01 replaces their only real effect with a plain `SET NAMES utf8mb4;` |

**Two messages that are expected, not failures:**

- the last two statements of part 09 (and of `database.sql` itself) are the dump's own migration
  block: `ALTER TABLE invoices ADD exchange_rate …` and `ALTER TABLE invoices_firms ADD
  base_currency …`. On a *fresh* schema both columns are already in the `CREATE TABLE`s at lines
  263 and 322, so those two `ALTER`s answer `Duplicate column name 'exchange_rate'` /
  `'base_currency'`. Nothing follows them. The block exists for databases upgraded from an older
  dump — see the comment at line 1277.
- `adomin-updates.sql` may answer `Duplicate column name 'logo_url'` / `Duplicate key name
  'idx_invoices_uniqid'` if it was run before. Same meaning: already applied, continue.

### 3.3 A desktop client: what this import actually needs (DBeaver, ~10 minutes)

A browser console is the wrong tool for 186 statements, and here that is measured rather than
theorised: TiDB Cloud's SQL editor executed **8 of the 17 statements** in one paste of
`database/import/01-of-09.sql` and answered `Query OK` for the lot. The three `SET` lines plus
`admin_users` (`CREATE` + `INSERT`), `clients`, `currencies` — which is why the table count read
exactly **3** afterwards, and why two later failures said `Table 'adomin.protocols_languages'
doesn't exist`: the console resumed mid-file, at the cursor, so an `INSERT` was sent whose
`CREATE` had never been. Any client that iterates over the statements works; DBeaver Community is
free and cross-platform.

**New database connection → MySQL.** Not the MariaDB entry and not any TiDB entry: the MySQL JDBC
driver is what TiDB answers.

| Field | Value |
|---|---|
| Host | `gateway01.<region>.prod.aws.tidbcloud.com` |
| Port | `4000` |
| Username | `<prefix>.root` — the whole string from the Connect dialog, dot included (older clusters: `root@<cluster-id>`) |
| Password | the database password from §2.3 |
| Database | `adomin`, or blank until §3.4 has created it |
| SSL tab | **Use SSL**, mode `REQUIRED`. No CA file, no verification — the same choice `DB_SSL=1` makes for the app, and the only one the gateway accepts |

If Test Connection says *Download missing driver files*, allow it — a one-off fetch. `Access denied
for user 'root'` means the username was truncated to `root` instead of the full
`<prefix>.root` string from the dialog, which is the single most common thing in this paragraph.

**Then import**, one file at a time: open it with File → Open and press **Alt+X** (Execute script),
not Ctrl+Enter.

1. `database/provision.sql` — only if the schema does not exist yet (§3.4).
2. `database.sql` — 186 statements, and the progress bar counts them, so a stall is visible instead
   of silent.
3. `database/adomin-updates.sql` — five statements.
4. The count query at the end of §3.2, which must read **58** tables and the two columns/index it
   asks about.

**Two dialogs not to chase.**

* DBeaver pauses on a statement error and offers *Abort / Ignore / Ignore all*. On a fresh schema
  the last two statements of `database.sql` are its own migration block (`ADD exchange_rate`,
  `ADD base_currency`) and both columns are already in the `CREATE TABLE`s at lines 263 and 322 —
  so **Ignore all**, and the import is finished: nothing follows those two.
* `(1681) Integer display width is deprecated` is a note about `int(11)` in the dump, not an
  error, and it will repeat. The app neither reads nor writes those widths.

**Why Alt+X and not Ctrl+Enter**: the same trap that cost the last hour — Ctrl+Enter runs only the
statement under the cursor, in every client, on every machine.

### 3.4 More than one project: one schema each, on the same cluster

`database/provision.sql` is the streamlined version of "create me a database". Three statements —
`CREATE DATABASE IF NOT EXISTS`, `USE`, and a count that prints what it made — and the name
appears twice in it, which is the only thing to edit per project. That name is also what goes into
`DB_NAME` on that project's host:

```
adomin      → DB_NAME=adomin
shop_ke     → DB_NAME=shop_ke
adomin_dev  → DB_NAME=adomin_dev
```

Then import `database.sql` and `database/adomin-updates.sql` into it, and give each project its own
Render service pointing at its own schema. The image, the branch and `render.yaml` are otherwise
identical, so a second project is 6 environment values, not a second deployment process. Two limits
to know before growing:

* **One cluster, one user.** A Starter cluster gives you a single credential, so every schema on it
  is writable by that one password: a `DB_NAME` typo on one service writes into another project's
  tables. For a client you would rather not have that, give them their own cluster (or a paid plan,
  where you can create a user per schema and grant it only its own).
* **750 instance-hours is per account, not per service.** Three free services share that budget,
  so they sleep more often; that is fine while you are the only user, and it is the first thing to
  pay for when it annoys someone else.

**Linking them up later** — three readings of that, cheapest first, and the first is usually what
people actually want:

| Goal | How |
|---|---|
| Several companies billed from one login, one database | nothing to build. The application is already multi-company: `firms_users` holds one row per company, everything else is keyed by `for_user` (`BRIEF.md` §3.1). One schema, many companies |
| One report across projects | qualified names on the same server: `SELECT … FROM shop_ke.invoices UNION ALL SELECT … FROM adomin.invoices`. MySQL-family schemas in one instance join freely, so this is a view or a script, not a migration |
| One account that switches between projects | a merge of `users` plus every `for_user` value in 57 tables, with id collisions to resolve per table. Real work, and it grows worse with each project. Prefer the first row |

**What is safe to re-run.** `database/adomin-updates.sql` is: the KES insert tests for the row
first (`NOT EXISTS`) and writes no `id`, because `currencies.id` is a real primary key and the
dump leaves its `AUTO_INCREMENT` counter at 115 - the next free one, which a hardcoded value
would race with. The `UPDATE` sets the same values, the table is created `IF NOT EXISTS`, and the
two `ALTER`s report the duplicate messages above.

`database.sql` is **not**: it is a plain dump with `CREATE TABLE` and `INSERT` and no
`DROP TABLE IF EXISTS`, so running it twice reports `Table '…' already exists` for all 57 tables
and duplicates its seed rows (`currencies` in particular has no key to stop a second copy). Run
it once, against an empty schema; if it must be redone, drop the schema and start from §2.4.

---

## 4. Create the Render service

1. Push to GitHub on the **default branch** (`master`). `render.yaml` has no
   branch pin, so Render follows the default branch.
2. Render → **New + → Blueprint** → choose the repository. Render reads
   `render.yaml`: `runtime: docker`, `plan: free`, `region: frankfurt`,
   `autoDeploy: true`, `healthCheckPath: /`, and every `ADOMIN_*` default.
3. The Blueprint form prompts for the values marked `sync: false` — the secrets, which
   deliberately are not in the repository. Copy them exactly from the TiDB
   Connect dialog (§2):

   | Key | Value |
   |---|---|
   | `DB_HOST` | `gateway01.<region>.prod.aws.tidbcloud.com` |
   | `DB_USER` | `<prefix>.root` (older clusters: `root@<cluster-id>`) |
   | `DB_PASS` | the database password from §2 |
   | `DB_NAME` | `adomin` (created automatically) |
   | `DB_PORT` | `4000` (already the default in `render.yaml`) |
   | `DB_SSL` | `1` (already the default in `render.yaml`) |

   Prefer these five `DB_*` fields over a single `DATABASE_URL`. A URL must
   percent-encode special characters in the credentials (`@` → `%40`, etc.),
   which is the most common cause of an "access denied" that looks like a
   wrong password. If you do use it, the form is
   `mysql://<user>:<password>@host:4000/adomin?ssl-mode=REQUIRED` with the
   user and password percent-encoded. Where both are set, the individual
   `DB_*` values win, so a partial override is possible.
4. **Apply**. The first build takes 2–4 minutes (Docker image: PHP 7.4 + Apache + the
   extensions in the header of this file, dompdf, no `wkhtmltopdf`); later builds are cached
   and take seconds. On first start the container creates the schema and
   imports the tables (§3) — watch for `Adomin DB: Done. Schema adomin now
   has 58 tables` in the logs.

You can add or change these at any time under **Render → your service → Environment →
Edit → Add**. Changing any of them triggers a restart, not a rebuild.

---

## 5. Verify the deployment

Read top to bottom; each step is the precondition for the next.

1. **Render → Log tail.** A good start looks like this:
   ```
   ==> Running docker/adomin-start.sh…
   Adomin: Apache listening on 0.0.0.0:10000
   Adomin: initialising database (if needed)...
   Adomin DB: Connecting to gateway01...:4000 as <prefix>.root / db adomin (TLS on)...
   Adomin DB: Schema adomin currently has 0 tables.
   Adomin DB: database.sql: 186 statements to run.
   Adomin DB: Done. Schema adomin now has 58 tables (expected 58).
   AH00163: Apache/2.4.54 (Debian) configured -- resuming normal operations
   "GET / HTTP/1.1" 200 ...
   ```
   `200` on `GET /` is the whole test. Any `500` there means the app reached PHP but could not
   use the database — read the `Adomin DB:` lines first (they name the cause), then go to §9.
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
| **Every page 500**, ~1 KB generic body, build and Apache look fine | CodeIgniter's *production* error page for a fatal error: the app reached PHP but cannot use the database — no `DB_NAME` schema, no tables in it, TLS refused, or the username is not the full `<prefix>.root` string from the Connect dialog | open `/status.php`; it names the cause. §10 |
| **Every page 500 with an empty body** | an uncaught exception before output (CodeIgniter's exception template emits nothing on its own) — most often the `languages` lookup during boot | fixed in `application/libraries/Language.php`: the lookup is guarded, the fallback is the configured language, and the reason is logged. Redeploy from the current branch; then `/status.php` |
| A **form POST** 500s while every GET works | the CSRF token was absent from the body (CodeIgniter answers 403; a gzipping proxy records it as a small 500), or a fatal happened in the handler | forms now render the token server-side; a fatal writes `Adomin FATAL` to the log and `/status.php` shows it. §10 |
| Site loads, **register/login fails** with `Field 'id' doesn't have a default value` | TiDB rejected the dump's `ALTER TABLE … AUTO_INCREMENT` block, so no primary key has a counter | automatic on restart with `ADOMIN_AUTO_IMPORT=1`; otherwise `php database/tidb-fix-increments.php` (§3.0) |
| `No database selected` on every statement while importing | the schema was not selected in the editor (manual import only — the container creates the schema automatically) | select it first — neither file has a `USE` statement (§3) |
| `Parsing sql failed, Please check if sql syntax is correct …  near "CREATE TABLE …"` | the console pre-parses the whole script and choked — on an `adomin-updates.sql` from before `641f47c`, whose `PREPARE`/`EXECUTE` guards some parsers cannot read, or on the `;` at the end of the comment on `database.sql` line 1277 — and it blames the statement *after* the thing it disliked | pull the current files, and import through `database/import/` (§3.2), which has neither construct |
| `Query OK` (or `Duration 0 ms`) and the table count does not move, or moves by far fewer tables than the file contains | the console ran a prefix of the paste — the statement under the cursor, or about 8 statements of a selected block in TiDB's editor — and reported the whole paste as if it had run it. Nothing was truncated in the SQL; the statements were never sent | verify by counting tables after every run, and for the import itself use a client that iterates over statements (§3.3). Select-all helps some consoles; it did not help this one |
| `Unknown database 'adomin'` | the schema was never created, or `DB_NAME` is spelled differently | the container creates it on next start; if it persists, `SHOW DATABASES;` in the SQL editor, then fix `DB_NAME` (§2) |
| `SSL connection error: plaintext connections are disallowed` / `Connections using insecure transport are prohibited` | TLS off on a host that requires it | `DB_SSL=1` (§4) |
| `Access denied for user 'root'` | username truncated to `root` instead of the full `<prefix>.root` (or `root@<cluster-id>`) from the Connect dialog; inside a URL, `@` must also be percent-encoded | copy `DB_USER` exactly from the dialog (§2); as a URL, encode special characters (`@` → `%40`) — or prefer the five `DB_*` fields (§4) |
| PDFs unstyled, or the logo is missing from the PDF but visible on screen | `application/libraries/dompdf/lib/fonts/` not writable, or a logo that only exists on a disposable disk | the image already `chmod`s that directory; check the mount, and use **Logo URL** (§5.3) |
| "Uploads vanish after a deploy" | free instance has no persistent disk | by design. Database external, files on object storage, or a VPS volume (§7) |
| Requests are uniformly slow (~1.5–2 s on top of everything) | Render free tier is in Singapore and the database region was chosen in another continent | recreate the cluster in the Render region (free, new cluster) or move the Render service to the DB's region. Measure before assuming code |

---

## 10. When a page 500s, get the real error

**Two faults are already handled for you.** A POST that fails CSRF protection answers with
CodeIgniter's *"The action you have requested is not allowed."* page (403, which a proxy that gzips
makes look like a small 500 in the access log); every form that posts now carries a
server-rendered token (`adomin_csrf_field()` in `application/helpers/adomin_helper.php`), so the
token no longer depends on the footer script running. And a fatal error — the thing that used to
produce a blank response — now writes `Adomin FATAL: <message> | <file>:<line>` to stderr
(Render → Logs) **and** to `application/logs/fatal-YYYY-MM-DD.php`, and shows a readable page
instead of Apache's default 500 (`application/adomin_fatal_guard.php`, registered from `index.php`
before the framework loads). `/status.php` with `STATUS_TOKEN` prints those log lines under
*Recent errors* — the only way to read them on the free tier, which has no shell.

**A POST that fails with an empty body is a different animal.** If `POST /login` answers
500 with ~551 bytes and no `Adomin FATAL` line, PHP wrote nothing and Apache served its stock
error page: it did not crash, and it is not the CSRF rejection page (1,133 bytes, status 403).
`/status.php?token=…` → **Form submissions (POST)** runs the four things a POST needs and a GET
does not — session directory, `session_start()`, a file write, a database write — and names the
first failure. The image also pins `log_errors`/`error_log` to stderr, so PHP's own messages and
any `Adomin HTTP 5xx with no error message` note land in Render → Logs.

**Sizes are worth memorising.** CodeIgniter's CSRF rejection page is 1,133 bytes and deflates to
569 — 551 bytes plus the 18-byte gzip header and trailer, which is exactly what Render's access log
records for the failing `POST /login`. The same page is served at status 403, but it comes from
`show_error()`, so a proxy can easily record it as a 500. `GET /` carrying a token proves the
server renders tokens; only the **cookie↔token pairing** decides whether a POST passes, and that
pairing is what `/status.php?token=…&posttest=1` now checks from inside the instance.

**There is no user account in a fresh install, only an admin one.** `database.sql` seeds
`admin_users` (username `admin`, password `admin`) and **nothing** in `users` — and the landing page
posts to `/login`, which authenticates against `users`/`employees` only. So on a fresh deployment the
public login can never succeed until someone registers through `/registration`, and admin lives at
`/admin` with a different form. Check `/status.php` → *Admin accounts* (1 is expected) and remember
`users` is empty until the first sign-up.

**Start with `/status.php`.** It is a standalone page (no CodeIgniter, so it works when the app does
not boot) that connects with the same environment parsing and TLS handling as the container, then
reports: PHP version and extensions, whether the database is configured/reachable, TLS mode, whether
the schema exists, the table count, tables whose `id` lacks `AUTO_INCREMENT`, and whether the
`languages`/`currencies` seed rows and the `admin_users` account exist. It prints one sentence
saying what to do next. Host, user, TLS CA and the exact MySQL error are shown only with
`STATUS_TOKEN` set and `?token=…` supplied; the password is never displayed. `?format=json` for
machine consumption.

Two failure modes it separates, which used to look identical (a blank 500):

* **PHP fatal** — CodeIgniter's production handler prints a *1 KB* generic page and hides the
  message. Nothing in the browser, nothing in the log tail.
* **Uncaught exception** (fatal) — and, before the fix in `application/libraries/Language.php`, a
  failing `languages` query during boot: the exception was uncaught, so the response had no body at
  all. The library now logs the reason, falls back to the configured default language and lets the
  request render, so the login page appears even when the database is down.

The app runs with `ENVIRONMENT = 'production'` (default in `index.php`), which is correct for
customers: errors go to the log file, and the visitor gets a generic page. To read the actual
message, use the framework's own switch — nothing custom is needed:

1. On Render, set `APP_ENV=development` (Environment → Add), save (restart).
   `index.php` reads `APP_ENV` and only allows `development` / `testing` /
   `production`, defaulting to `production`.
2. Reload the page. CodeIgniter now prints the exception and, because `db_debug` is
   `TRUE` outside production, the literal MySQL error text and the failed SQL.
   The container's `Adomin DB:` log lines (Render → Logs) show the same
   connection error without needing this step at all.
3. Read `application/logs/log-*.php` — the same detail is written there on *every* request
   in production too, which is the place to look when you cannot spare a visible error page.
   On Render you reach it through **Render → SSH** (free tier: no shell), so the practical
   route is to deploy where you can read files (§7), or copy the file from a local run.
4. **Set `APP_ENV=production` again.** A development error page on a public host discloses paths, SQL and
   configuration to anyone who trips an error.

The four causes account for almost every case, and §9 covers all four.

---

## 11. Configuration reference

Everything is optional and read from the environment; `application/config/adomin.php` holds
the defaults, and an environment variable always overrides the file. Set them in
**Render → Environment** (or `export` for a shell, or the `env:` list in a `docker run`).

| Variable | Default | Meaning |
|---|---|---|
| `DB_HOST` `DB_USER` `DB_PASS` `DB_NAME` `DB_PORT` | `localhost` `root` `toor` `test` `3306` | connection. The defaults are the file's original values, used only when no variable and no URL is set — so a local install behaves as it always did, and a hosted one never reads a credential from the repository |
| `DATABASE_URL` / `DB_URL` / `MYSQL_URL` / `CLEARDB_DATABASE_URL` | – | one connection string instead of the five; `mysql://` only |
| `DB_SSL` | off | `1` (or `REQUIRED` / `true` / `on`) turns TLS on for the `mysqli` connection. Explicit by design — no host is detected or guessed. **Required on TiDB Serverless** |
| `DB_SSL_CA` | system bundle | path to the CA file, used when TLS is on |
| `DB_SSL_VERIFY` | `0` | `1` additionally requires the server certificate to match the host name. Keep at `0` for TiDB/Aiven gateways whose certificate does not match the gateway hostname; that is encryption without identity pinning, like `mysql --ssl-mode=REQUIRED` |
| `APP_ENV` | `production` | `development` shows PHP/MySQL errors in the browser (debugging only, §10) |
| `ADOMIN_AUTO_IMPORT` | `1` | `1` creates the schema and imports `database.sql` + `database/adomin-updates.sql` on first start when the schema is empty, and repairs primary keys that TiDB would not give an `AUTO_INCREMENT` counter (§3.0); `0` disables all of that (manual import only, §3, and run `database/tidb-fix-increments.php` by hand) |
| `ADOMIN_FIX_AUTO_INCREMENT` | `1` | `1` checks `information_schema` on every start and rebuilds tables whose primary key lost its `AUTO_INCREMENT` counter (§3.0); `0` skips that check and leaves the schema alone |
| `STATUS_TOKEN` | – | when set, `/status.php?token=<value>` shows host, TLS mode, the exact database error and the initialiser log. Without it the page reports health only |
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
- [ ] `APP_ENV=production` on Render (§10 reverted if you debugged with `development`)
- [ ] `DB_*` set on Render (or `DATABASE_URL`), `DB_SSL=1` for hosts that require TLS, secrets kept out of `render.yaml`
- [ ] Logs show `58 tables` (container imported both SQL files into **your** schema automatically; manual import only if `ADOMIN_AUTO_IMPORT=0`) and no table is listed as still missing `AUTO_INCREMENT`
- [ ] `/status.php` reports **ready**, 58 of 58 tables, `AUTO_INCREMENT`: none (§10)
- [ ] `application/logs/` writable and a `log-*.php` file appears — if nothing is written there, error logs are the next thing to hunt
- [ ] `application/libraries/dompdf/lib/fonts/` writable (first render parses the DejaVu metrics into it)
- [ ] HTTPS through the proxy verified: assets load, and the PDF's URLs are `https://`
- [ ] KES / 16 % VAT / M-Pesa set in *Settings → Invoices*; `database/adomin-updates.sql` applied
- [ ] Tested after the first deploy: PDF download (original and copy), protocol / movement / warranty PDF, logo upload **and** `logo_url`, send-via-link open and revoke, accept/refuse, `ADOMIN_ANIMATIONS=0`
- [ ] MySQL backed up (a TiDB export or `mysqldump`); `attachments/` understood to be disposable on the free tier
- [ ] Keep-alive decided: 750 free hours ≈ one always-on service, so pinging it every 10 minutes spends the month's budget

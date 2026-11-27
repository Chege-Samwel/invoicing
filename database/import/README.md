# database/import - the schema, in paste-sized parts

For a console that iterates over the statements in a script. TiDB Cloud's SQL editor is not one:
measured here, it ran 8 of the 17 statements in a paste of part 01, said `Query OK`, and left 3
tables behind. If your console behaves that way, use a client instead - `DEPLOYMENT.md` 3.3 has
the ten-minute DBeaver setup.

`database.sql` holds 186 SQL statements. A database console in a browser (TiDB Cloud's SQL
editor, and several others) handles a 50 KB script badly: some cap what you can paste, some
parse the whole script before running any of it so one line it dislikes cancels all 186, and
one line of the dump is a comment that ends with a semicolon (line 1277), which is where a
console splitting on `;` cuts a comment in half. These parts are the same SQL, cut only
between statements, so each paste is small and none of them depends on a construct a thin
parser has to understand.

| | |
|---|---|
| Run | `01-of-09.sql` through `09-of-09.sql` in order, then `../adomin-updates.sql` |
| Before each paste | select your schema - no file here has a `USE` statement |
| Before each Run | select all of the pasted text, or the console runs only the statement under the cursor and says "Query OK" |
| These files | generated, one-way. `database.sql` is the canonical file and is not modified by this |
| After | the count query below must read 57 tables in your schema |

The expected `Duplicate column name 'exchange_rate'` at the very end of the last part, and
why it is not a failure, is written in that part's header.

**TiDB: the `AUTO_INCREMENT` block in part 09 is expected to fail there.** The dump gives each
table its counter with `ALTER TABLE ... MODIFY id ... AUTO_INCREMENT`, which TiDB refuses (error
`8200`) because the attribute can only be set in `CREATE TABLE`. The tables are still created
with all 57 counted, but no primary key can generate an id, so the first insert fails with
`Field 'id' doesn't have a default value` (registration and login break). After importing here,
repair them once:

```bash
php database/tidb-fix-increments.php          # --check to only list the tables
```

or restart the container with `ADOMIN_AUTO_IMPORT=1`, which performs the same repair. Details in
`DEPLOYMENT.md` section 3.0.

```sql
SELECT DATABASE() AS schema_in_use,
       (SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()) AS tables_here;
```

When the schema is installed and the app is running, this directory can be deleted: it is a
first-install convenience, not something the application reads.

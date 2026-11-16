# Namecheap shared-hosting deployment checklist

Prepared for 123test on 2026-11-16. This is a deployment runbook; hosting configuration and launch checks have not been performed.

## 1. Confirm the hosting environment

- [ ] Confirm the assigned server's database product/version with Namecheap. The project targets **MySQL 8.4**; Namecheap currently lists **MariaDB 11.4.9** for shared hosting. Treat MariaDB support as unverified until the import and functional checks below pass. PHP 8.4 and rewrite support are advertised, but confirm your account's actual settings. [Namecheap server software](https://www.namecheap.com/support/knowledgebase/article.aspx/129/22/what-version-of-the-software-is-used-on-your-servers/)
- [ ] Select **PHP 8.4** for the domain in cPanel. `composer.json` requires it even though the framework entry point permits older PHP. Check the SSH/CLI version separately with `php -v`.
- [ ] Enable `intl`, `mbstring`, `mysqli`, `fileinfo`, `openssl`, `session`, `ctype`, and GD with JPEG/PNG/WebP support. Run `composer check-platform-reqs --no-dev` on the host as well; the application needs upload/image extensions beyond Composer's declared requirements.
- [ ] In phpMyAdmin, run these read-only checks:

  ```sql
  SELECT VERSION(), @@default_storage_engine, @@character_set_database,
         @@collation_database, @@session.time_zone, @@max_allowed_packet;
  SHOW COLLATION WHERE Collation = 'utf8mb4_0900_ai_ci';
  SELECT JSON_TYPE('{"ok":true}'), JSON_VALID('[]');
  ```

  Require InnoDB, utf8mb4, JSON functions, and the requested collation. MariaDB 11.4.5+ maps `utf8mb4_0900_ai_ci` to a newer collation, and its JSON type is a LONGTEXT alias, so successful import alone does not establish MySQL-equivalent behavior. If the collation is unavailable or application checks fail, resolve compatibility or choose hosting with the project's MySQL target before launch. [MariaDB collations](https://mariadb.com/docs/server/reference/data-types/string-data-types/character-sets/supported-character-sets-and-collations), [MariaDB JSON](https://mariadb.com/docs/server/reference/data-types/string-data-types/json)

## 2. Prepare a deployment copy

- [ ] Back up the local database and `writable/uploads/quiz-media/` together. Decide whether production starts empty or retains the local teachers, quizzes, and results.
- [ ] Run the relevant automated tests locally before packaging. Keep development dependencies in the development workspace. The preparation run found two unrelated view assertions failing in the full suite; review the [recorded verification results](SCHEMA_COMPARISON.md#verification-performed) before launch.
- [ ] In a separate release copy, install the locked production dependencies:

  ```bash
  composer install --no-dev --optimize-autoloader
  composer check-platform-reqs --no-dev
  ```

  Use `composer.lock`; do not update dependencies as part of the upload. SSH/Composer can also be configured on Namecheap. [Namecheap Composer instructions](https://www.namecheap.com/support/knowledgebase/article.aspx/9977/29/how-to-install-composer-on-shared-servers/)
- [ ] Include `app/`, production `vendor/`, `spark`, Composer manifests, public assets (including the pinned Vue file), and the writable directory structure. No Node/Vite build or production runtime is required.
- [ ] Exclude the local `.env`, development sessions, caches, logs, tests, IDE files, version-control metadata, and SQL dumps from the deployed release. Transfer chosen SQL privately to phpMyAdmin, not to the web root. The backup contains account hashes and student information.

## 3. Keep the complete project in the domain folder

- [ ] Upload the project with its existing directory structure to the domain's folder inside `public_html`. For example (replace `CPUSER` and `example.com`):

  ```text
  /home/CPUSER/public_html/example.com/
      .htaccess
      .env
      app/
      vendor/
      writable/
      public/
          index.php
          .htaccess
          css/, js/, fonts/, images/, ...
      spark
      composer.json
      composer.lock
  ```

- [ ] Confirm cPanel > Domains maps `example.com` to `/home/CPUSER/public_html/example.com/`. The domain name serves the application at `https://example.com/`; its filesystem folder name does not belong in `app.baseURL`. Keep `public/index.php`, `spark`, and `app/Config/Paths.php` paths unchanged.
- [ ] In the **deployment copy**, replace the project-root `.htaccess` with the following rules. They block private paths and send application URLs and assets through `public/`. Keep these rules in the domain folder, not in the shared parent `public_html/.htaccess`:

  ```apache
  Options -Indexes
  RewriteEngine On

  # Deny hidden paths, except public ACME/certificate validation files.
  RewriteRule (^|/)\.(?!well-known(?:/|$)) - [F,L]

  # Deny private application directories before forwarding requests.
  RewriteRule ^(?:app|vendor|writable|docs|tests|build)(?:/|$) - [F,L,NC]

  # Forward to public once, including requests for existing root files.
  RewriteRule ^(?!public(?:/|$))(.*)$ public/$1 [L]
  ```

  Do not add a root-level exception for existing files/directories: it could expose Composer manifests, SQL dumps, or application files. The current repository root rules only forward requests; the protection above must be applied before launch. Hosting rewrite support and permission to use these directives are required.
- [ ] In the **deployed** `public/.htaccess`, remove the two existing redirect blocks headed **Redirect Trailing Slashes** and **Rewrite "www.example.com -> example.com"** (their conditions and rules). Keep the remaining front-controller and authorization-header rules. Configure canonical hostname/HTTPS redirects at the domain level before internal forwarding; the production HTTPS setting below also remains enabled. CI4 documents this adjustment for the project-in-document-root layout. [CodeIgniter deployment](https://codeigniter.com/user_guide/installation/deployment.html)
- [ ] Put an additional `.htaccess` containing `Require all denied` inside each deployed private directory: `app/`, `vendor/`, `writable/`, and any retained `docs/`, `tests/`, or `build/`. These rules deny HTTP access while allowing PHP filesystem includes and private media delivery through the application. Preserve the existing denial rules in `app/.htaccess`. Do this in the release copy; do not modify local Composer-managed vendor files.
- [ ] Upload both root and public `.htaccess` files with hidden files visible in File Manager. Verify the domain, `/login`, `/css/teacher.css`, and API routes work without `/public` appearing in generated links or redirects. Check access through the parent site's `/example.com/` path too if that alias is reachable; parent-site rewrite rules must not bypass the domain folder's protections.
- [ ] Keep application directories/files readable by the account's PHP process; make `writable/cache`, `writable/logs`, `writable/session`, and `writable/uploads` writable by that process. Start with account-owned directories `0750`/files `0640` for private data and adjust only as required by hosting. Do not use `0777`.

## 4. Import the database and media

- [ ] Create an empty database and a dedicated database user in cPanel; assign the application's required privileges to that database. Use cPanel's full prefixed database/user names in `.env`.
- [ ] Choose **one** import path in phpMyAdmin:
  - **Empty launch:** import `docs/schema.sql` once into the empty database.
  - **Keep existing data:** import a fresh complete backup once into the empty database. Do not import `schema.sql` first. The supplied `db-backup.sql` omits table options; set the destination database default collation to `utf8mb4_0900_ai_ci` and confirm the session's default engine is InnoDB before importing it. Prefer a new export including engine/charset options and complete constraints.
- [ ] Check the phpMyAdmin import result for errors. Verify all five tables, their indexes, and foreign keys. Run the read-only table-option queries in [the schema comparison](SCHEMA_COMPARISON.md).
- [ ] If retaining data, copy **all** referenced private media to `writable/uploads/quiz-media/`, preserving relative paths and IDs. Include files retained for historical attempt snapshots, not just current quiz images. Start with empty production session/cache/log directories.
- [ ] If an existing database has the old canonical checks, review the optional incremental SQL in [the schema comparison](SCHEMA_COMPARISON.md). A database already matching this backup needs no constraint update. Never import the full schema over existing tables.

## 5. Configure production privately

- [ ] Create `/home/CPUSER/public_html/example.com/.env` in the project root shown above. Replace these example values on the host; never put real credentials in documentation or PHP configuration:

  ```ini
  CI_ENVIRONMENT = production
  app.baseURL = 'https://example.com/'
  app.forceGlobalSecureRequests = true
  cookie.secure = true
  cookie.httponly = true
  database.default.hostname = localhost
  database.default.port = 3306
  database.default.database = 'CPANEL_DATABASE'
  database.default.username = 'CPANEL_DATABASE_USER'
  database.default.password = 'REPLACE_PRIVATELY'
  database.default.DBDriver = MySQLi
  database.default.DBPrefix = ''
  database.default.charset = utf8mb4
  database.default.DBCollat = utf8mb4_0900_ai_ci
  database.default.DBDebug = false
  ```

- [ ] Generate the production signing key with `php spark key:generate` from the domain project directory. Confirm `encryption.key` is populated in the private `.env`; keep it stable and back it up privately. Missing keys prevent admission tickets and signed media from working. Use a new key for a new deployment; preserve an existing production key during later releases.
- [ ] Restrict `.env` permissions to the account (for example `0600` if PHP runs as that account). Never upload the local `.env` wholesale.
- [ ] Set `upload_max_filesize` to at least `20M` and `post_max_size` above it, for example `24M`. Confirm the host's request-body limits and PHP memory limit support image decoding. Application limits remain **5 MiB per image**, **20 MiB per audio file**, and **16 megapixels per image**.
- [ ] Keep the application timezone as `Asia/Tashkent` and verify persisted timestamps remain UTC. Do not change timestamp interpretation to match the hosting server's timezone.
- [ ] Use the production file cache for throttling and writable file sessions. Confirm host cleanup does not purge the application's session files earlier than the configured one-year session lifetime.
- [ ] From the deployed project directory, run `php spark cache:clear`, `php spark routes`, and `php spark filter:check POST /login`. Clear any previously enabled config/FileLocator caches using the commands supported by the locked framework before checking `.env` changes.
- [ ] The current MVP has no required application cron worker or implemented password-reset mail flow. Do not assume those planned capabilities exist; arrange independent backups and log retention.

## 6. HTTPS and functional launch checks

- [ ] Point the domain's DNS to the assigned host, install a valid SSL certificate, and choose one canonical hostname matching `app.baseURL` (with a trailing slash). Verify HTTP redirects to HTTPS without loops, including any `www` redirect. Use a staging hostname or a local hosts-file override before public cutover.
- [ ] Confirm `/.env`, `/.git/config`, `/docs/db-backup.sql`, `/app/Config/Database.php`, `/vendor/autoload.php`, `/writable/uploads/`, `/composer.json`, and `/spark` return denied/not-found responses and never expose private content. Check `/public/.env` and the parent-site alias too if reachable. Remove uploaded archives and diagnostic scripts.
- [ ] Check registration, login, browser restart persistence, account changes, and logout. Confirm Secure/HttpOnly session cookies, rejected invalid CSRF requests, and no production debug toolbar or stack traces.
- [ ] Create a quiz, save Draft/Active changes, upload JPEG/PNG/WebP and audio, and reopen it. Check signed media delivery and denied invalid media links.
- [ ] Start a student attempt, answer/skip, disconnect after the player has loaded, continue, reconnect, and finish. Confirm one authoritative result in the teacher report. Check timer expiry, intentional exit, reload recovery, and result reopening within the cookie grace period. New page loads still require connectivity.
- [ ] Check public teacher pages and access control: another teacher cannot edit quizzes or read private results. Confirm API PUT/POST requests and required custom headers are accepted by hosting rules. Investigate specific ModSecurity failures instead of disabling protection globally.
- [ ] Verify sensitive/authenticated pages, player APIs, and signed media are excluded from any hosting/CDN full-page cache. Review private production logs for launch errors without exposing credentials or student payloads.

## 7. Backups and later releases

- [ ] Schedule private database and media backups, retain a previous working release, and test restoration into an isolated database/location. Do not rely solely on the hosting plan's backups.
- [ ] For later releases, back up first, preserve production `.env`/signing key and uploads, apply only documented incremental SQL, and deploy matching PHP/assets together. Prefer a quiet deployment window so active quiz attempts can synchronize.
- [ ] Monitor storage/inode use, logs, database growth, and cPanel resource usage. Include private media and the persistent session directory in capacity planning.

Designers perform visual and responsive-layout verification separately.

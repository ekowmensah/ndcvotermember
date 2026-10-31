# Security Phase 4 — operations and recovery

No database migration, Apache restart, backup, restore, key generation or scheduled task was executed. Existing APP_URL, credentials, APP_KEY, records and reset backups are untouched. User view-page redesign remains deferred.

## System health

Administration > System health is read-only and administrator-only. It checks required PHP extensions, session settings, error display, storage writability, selected migration structures, Viewer database default and legacy unlinked account count. It flags a root DB account, but a non-root name is not treated as proof of limited privileges. Checks never display credentials/keys/raw errors or retrieve voter/contact/photo contents.

Structural checks do not replace complete migration/integrity checks. HTTP access protection and backups are always marked manual/unverified: a readable .htaccess file does not prove Apache applies it, and a file on disk does not prove off-machine recovery.

## Current Laragon deployment

Root .htaccess now denies docs in addition to existing private directories. Private source/config/database/docs/script roots also contain Require all denied, giving a second layer independent of mod_rewrite when Apache permits authorization overrides. Existing URLs are retained. The maintenance message is generic rather than saying the system is being reset.

Manually verify, while signed out AND signed in, that these cannot be downloaded: `/.env`, `/.local-credentials`, `/.git/config`, `/config/Config.php`, `/services/security/SystemHealth.php`, `/storage/php-error.log`, `/database/26_branch_membership_workflow_mysql8.sql`, `/docs/security/phase_4_operations.md`, `/bin/security/backup_crypto.php`. Add the application base path when using localhost subdirectory URLs. Expect 403/404 with no private contents. Confirm login, assets and normal routes still work. Do not put SQL exports, keys or backup archives under www.

## Optional isolated document root

`public/index.php` delegates to the existing front controller. `docs/security/deployment/laragon-vhost.conf.example` shows an optional virtual host exposing public/ and static assets only. It is NOT installed automatically. Save your existing Apache configuration first, adapt the host/path, add the local host entry through Laragon and configure TLS for remote use. If you adopt this host, deliberately set APP_URL to its actual root and restart Apache manually. Changing APP_URL invalidates/requires renewed sign-ins and previews; complete or cancel pending imports first.

The example's HTTP host is for local development. Do not assume APP_URL=https alone configures TLS. Keep MySQL/phpMyAdmin local or otherwise explicitly restricted. Use a dedicated database account with necessary runtime data privileges; migration/admin credentials should be separate. Review actual SHOW GRANTS manually rather than assuming the app username is sufficient. Metadata readiness queries need metadata visibility for the application schema.

## Backup procedure using phpMyAdmin

1. Choose a maintenance window. Stop application writes/active imports. An authorized operator can create `storage/maintenance.flag` to block application requests; remove it only after maintenance. This alone does not stop a request already running or direct database writes: allow in-flight operations to finish and coordinate staff.
2. In phpMyAdmin select the application database and export ALL tables using Custom > SQL, structure AND data. Include binary data as hexadecimal (photos, IPs and lookup keys), and enable a consistent transaction export where supported. Export routines/triggers if present. Avoid incomplete page/table-only exports.
3. Save outside the web root, e.g. `C:\laragon\backups\ndcvotermember-current\`. Keep the existing reset backup directory unchanged. Record UTC date, application revision and last applied migration with the backup.
4. Preserve the matching source/configuration snapshot, particularly the ORIGINAL APP_KEY and database schema. DB data cannot recover encrypted contact fields without APP_KEY. Store .env only in a protected encrypted archive, never in email/source control/public folders. A backup encryption key is separate from APP_KEY.
5. Encrypt the export/config archive using the offline helper below or an existing organization-approved encrypted backup tool. Restrict NTFS ACLs for working plaintext and keys. Windows chmod/umask is not a substitute for NTFS permissions.
6. Keep encrypted off-machine copies and a separately protected backup key. Establish a manual/managed recurring schedule and retention appropriate to operational needs (for example daily copies, weekly retained copies). No scheduler or database exporter is configured by this phase. Assign an owner to check successful backups.
7. Validate recovery in an isolated database/application before retiring any plaintext source. Remove maintenance mode when ready. Do not delete originals or old backups automatically; use your retention procedure.

## Offline encryption helper

`bin/security/backup_crypto.php` is CLI-only and does not read .env or connect to MySQL. It encrypts any manually exported SQL/archive using sodium authenticated streaming with a final authenticated marker, bounded memory and an independent random 32-byte key. It refuses project-root paths, existing output files, key/output in the same directory, wrong-key/corrupt/truncated files and trailing data. A failed operation removes only the newly created partial output. It never overwrites/deletes the input. Keep ALL selected paths outside Laragon www, even though its built-in path check specifically protects this application root.

Create the destination directories and restrict their NTFS permissions yourself before use. Use new filenames for each backup. Do not run commands against the protected reset-backup folder.

```powershell
$backupPhp = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe'
# Run keygen ONCE. Keep this key separate from the backup files.
& $backupPhp .\bin\security\backup_crypto.php --mode=keygen --key-file=C:\laragon\keys\ndc-backup.key
# Encrypt a manual phpMyAdmin export; choose actual filenames.
& $backupPhp .\bin\security\backup_crypto.php --mode=encrypt --key-file=C:\laragon\keys\ndc-backup.key --input=C:\laragon\backups\ndcvotermember-current\export.sql --output=C:\laragon\backups\ndcvotermember-current\export.sql.ndcbk
# Recover to a NEW plaintext file for an isolated manual restore.
& $backupPhp .\bin\security\backup_crypto.php --mode=decrypt --key-file=C:\laragon\keys\ndc-backup.key --input=C:\laragon\backups\ndcvotermember-current\export.sql.ndcbk --output=C:\laragon\backups\ndcvotermember-current\recovered.sql
```

A successful encryption is not proof of DB consistency. Preserve and securely escrow the key; losing it makes these backups unrecoverable. Do not post keys or decrypted exports in chat. No cryptographic round-trip was executed during development because this phase permits syntax checks only; manually verify a round-trip and reject wrong-key/truncated-file copies before operational use.

## Isolated restore rehearsal

Create a NEW empty recovery database in phpMyAdmin; never import a rehearsal over production. Import the decrypted full SQL export. Restore the matching source snapshot into a separate nonpublic/isolated application folder, configure the recovery DB credentials and separate APP_URL, and retain the matching original APP_KEY. Keep the copy away from ordinary staff and external integrations.

Using real recovered records, manually verify counts, membership numbers/Party IDs, contact decryption, photos, station-derived branches, residential towns, executive history, wing enrollment and nomination decisions. Confirm administrator sign-in and scope restrictions. Record restore date/result without copying private records into the report. Mark recovery incomplete if any part fails. Before any actual production restore, obtain a fresh pre-restore backup and explicitly plan maintenance/cutover; this phase does not authorize overwriting the live database.

## Manual acceptance

- Admin sees System health; Viewer/Registrar/restricted executives receive forbidden for direct URL. No keys/credentials/raw errors in HTML.
- Missing migration structures show action needed; backup/HTTP checks remain manual, never an unearned green pass.
- Private-path requests return no content; normal application routes/assets and nomination permissions remain usable.
- Optional isolated-host setup is tested only if deliberately installed; root deployment continues to work otherwise.
- Offline helper is exercised only by the operator with chosen files: round-trip identical bytes, wrong key/truncation/trailing bytes fail and leave no new recovered output, existing outputs never overwritten. DB remains untouched.

Next work must follow user testing. MFA/recovery, finer ordinary-staff permissions and broader data-quality/refactoring ideas remain outstanding; they are not represented as completed by these operational controls.

# Polling station CSV duplicate identity

Polling station CSV preview uses the official Station ID (`station_code`) as its duplicate key. It never uses a matching station name as evidence that two IDs identify the same station. IDs remain text, retaining leading zeros; case/accent equivalence follows the existing MySQL unique-ID collation.

- Repeated Station ID in the file: all occurrences are Duplicate Station ID rows, even when names differ. No arbitrary first/last occurrence is selected.
- ID already saved globally, same name and selected constituency: Existing Station ID / skip. Existing branch assignments and voter links remain unchanged.
- ID already saved with a different name or constituency: Database conflict requiring review. Uploads do not update or move that station.
- Distinct IDs sharing a name: allowed, including within the same constituency. Import the complete `database/22_polling_station_shared_names_mysql8.sql` file in phpMyAdmin after location migrations 02–05. It removes name-only/per-constituency name uniqueness while preserving the global official-ID unique key, foreign-key support and all existing records/links. It is safe to replay and inserts no sample stations.

The upload page and preview state the identity rule. Review category counts/filter options/table badges distinguish repeated IDs from existing-ID skips. Confirmation re-runs the same analysis under locks, compares the saved plan signature and remains atomic.

Refresh any pending preview after importing migration 22. Old analyses cannot be committed against changed rules; signature validation asks for a refreshed review. The application never runs the migration automatically. Station creation/detail editing and station uploads show migration guidance until shared-name support and a global unique Station ID key are present; branch assignment and existing linked records remain available.

Manual add/edit and CSV creation follow the same identity rule. Station selectors include official IDs so identical names can be distinguished. A voter CSV with `polling_station_code` resolves that specific station, including when another station shares its name. If a name-only voter row matches several stations in its constituency, preview requires `polling_station_code`; it never guesses the first match. Unique-name legacy voter CSV rows remain supported.

Manual acceptance in Laragon/phpMyAdmin: import 22 and replay it after adding your own stations; verify all records/links remain. Add/edit/upload distinct IDs with the same name in one constituency: permit. Try repeated same/different names under one ID, case/accent variants, leading-zero versus non-leading-zero IDs, globally existing IDs in another constituency and mixed valid/problem rows. Confirm preview creates nothing and explicit confirmation imports both same-name/different-ID stations. Check name-only ambiguous voter rows reject, code-qualified rows select the correct station even when a matching name exists earlier, and unique-name legacy rows still work. Run `database/phpmyadmin_shared_station_name_checks.sql` manually. Voter CSV duplicate grouping remains by voter ID.

Automatic verification is PHP syntax/static only. No upload, database or browser tests were run automatically.

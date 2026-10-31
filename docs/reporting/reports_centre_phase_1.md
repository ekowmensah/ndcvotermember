# Reports Centre — first phase

Open Reports > Reports Centre. No migration, sample records or database changes are required. PHP syntax/static checks only were run; testing remains manual in Laragon/phpMyAdmin.

## Reports available

- Voter register: saved name parts/full name, voter ID, gender, current membership/Party ID/Sys No., canonical electoral locations/station ID and recorded life status.
- Membership register: Party ID and preserved Sys No., voter identity, gender, active/inactive membership, joining date, canonical electoral locations and life status.
- Electoral location summary: group permitted people by Region, Constituency, Branch or Polling station; counts for voters/members/nonmembers/marked deceased and filtered membership coverage. Grouping uses canonical IDs plus labels, so equal station names remain separate. A legacy station without an official ID is labelled System # rather than inventing an official code. A missing station produces an unassigned bucket; empty directory locations are omitted.

Members are included in voters. Marked deceased is a saved flag; others are labelled Not marked deceased rather than confirmed alive. Filters change the matching cohort, so a membership-only location filter produces 100% coverage of that cohort; it is not overall constituency conversion.

Date filters use voter creation date for voter/location reports, membership joined_on for membership reports, through the inclusive selected end day (UTC). Reports show current saved status/identity, not exact membership as of the historical date. There is no unsupported historical reconstruction.

## Filtering and output

Search by saved name/voter ID/Party ID/Sys No.; filter by scoped electoral geography, gender, life status, membership or member status, date range and location grouping. Detailed filters are collapsed initially; applied criteria stay visible. Native selects get locally vendored search enhancement. Preview pages contain 25/50/100 rows; full matching totals and scope/date basis appear above output.

Preview, print and CSV share PeopleReports::prepare and a read transaction, so criteria/cohort/grouping agree at generation time. Data can change between separate requests: these are fresh snapshots, not archived immutable reports. Print/CSV cover the whole matching result, capped at 5,000 output rows rather than the preview page. Oversized outputs return guidance while preserving valid filters. Invalid filters return safely to the hub. No exported files are saved to disk by the web application.

Shared ReportOutput handles print, UTF-8 CSV BOM, formula neutralization and generation audit events. Existing executive/wing CSV authorization remains unchanged. New peopleCsv has a separate permission gate then the same private writer, so granting executive people reports never opens old unrestricted reports.

Contacts, DOB, encrypted addresses, notes and photos are excluded from SELECT/output regardless of contact permission in this phase. Identifiers remain text; spreadsheet users must import their columns as Text to preserve leading zeros. User content is escaped in preview/print and spreadsheet formulas are neutralized in CSV. There are no bulk write controls.

## Authorization

All reports require sign-in. Every source query, count, location dropdown and grouped summary uses ExecutiveAccess::condition for restricted accounts. Filters can narrow but never expand office scope. Pending/scheduled/ended/cancelled/no-grant offices remain ineffective. Wider Viewer reads do not change editing permissions; these reports only add reading basic report fields.

Ordinary admin/registrar CSV uses their existing role authority. Ordinary Viewer may preview/print but not download CSV. Executive CSV additionally requires enabled access and the explicit can_export grant. Preset-managed accounts remain export-off by default; this phase does not silently grant CSV. Direct unauthorized POSTs are rejected before report preparation. POSTs require global CSRF. Printing follows the existing Viewer print policy and records generation, not physical printer success.

The hub links existing executive/wing reporting only for ordinary staff; executive users do not receive links to forbidden modules. The sidebar Reports group expands on report routes; existing categories/routes are preserved.

## Manual acceptance checklist

1. Open the hub as admin, registrar, Viewer and restricted executive. Verify role/scope-specific navigation, local assets, light/dark/mobile layouts and zero-data states. No migration prompt should appear when existing location/Party ID/lifecycle structures are ready.
2. Preview all three kinds. Compare full matching totals with manual phpMyAdmin scoped counts, taking membership as subset and soft-deletion into account. Unknown names stay blank; IDs preserve leading zeros.
3. Apply search, location, gender, life and membership/status/date filters. Pagination retains them; Reset clears only report criteria and never scope. Print/CSV use all matching rows, not just the current page. Applied criteria and UTC generation time are visible.
4. Compare location rollups at each level to matching people totals. Same-name/different-ID stations must stay distinct. Station-derived branch assignment updates counts on the next request. Unassigned/legacy records remain only when scope permits them; empty unused directory rows are not invented.
5. Voter/location date filter is created_at; membership date filter is joined_on. Check inclusive last day and invalid/reversed/future dates. Verify the screen does not describe this as historical membership.
6. As executive, forge outside-region/constituency/branch/station IDs and direct report/print URLs: no outside people, counts or dropdown names. Ending/cancelling/disabling access must remove its contribution on subsequent requests.
7. Ordinary Viewer and executive without export grant cannot POST CSV. With explicit enabled grant, executive CSV remains scoped. Invalid CSRF must download/write nothing. Existing wing/executive report permissions must remain unchanged.
8. Compare preview/print/CSV columns and totals using unchanged data. CSV has BOM/formula protection; import ID columns as text. More than 5,000 rows refuses full output and preserves filters. A summary with fewer than 5,000 grouped rows may cover more underlying people; the cap concerns output rows.
9. Inspect SQL/HTML/output for contacts, DOB, address ciphertext/plaintext, images or notes: none. Verify report.csv_generated/report.print_generated contains kind/count/actor rather than record data.

Later reporting phases: nominations/outcomes, actionable data quality and executive directory integration, then residential/background/wing/upload/security expansions. Do not build these as completed reports until authorized.

# Wing reports and shared report output

No new migration is required. Wing enrollments, eligibility suggestions and person-linked participation history now offer Print matching results and Download matching CSV. Migration 20 must already be imported; executive reports retain their existing migration requirements.

## Usage and permissions

Open a wing directory, Eligibility suggestions or a person's full participation history. Apply filters, then use the report controls. Reports include the entire matching result set in the same order as the directory, irrespective of the currently visible page. The limit is 5,000 matching records; larger requests return to the relevant directory with a message to narrow filters before any report/download output starts.

Signed-in staff, including viewers, can print records they can browse. CSV downloads require admin/registrar and a CSRF-protected POST. Forged viewer CSV requests fail access checks; printing does not grant management access. Reports do not enroll anyone or modify wing/executive history. No saved report files are left in storage.

Print opens a standalone NDC-branded report with visible named criteria, matching count, UTC generation time, Back to results and Print / Save PDF. Criteria are always visible in print, including residential region/district filters, enrollment/review state and person/wing filters. Landscape formatting, repeated table headings, readable zero-result states, keyboard focus and horizontal scrolling on narrow screens are shared with executive reports.

CSV output is UTF-8 with a BOM. Formula-looking cell values are prefixed with an apostrophe. Import Party ID and Sys No. columns as text in spreadsheet software to preserve leading zeros; CSV itself does not carry spreadsheet column types.

## Report contents

- Enrollment directory: current displayed name/Party ID, saved system number, gender, electoral constituency/derived branch, effective participation dates, ending reason, party/life status, review note and the original name at enrollment. Missing person records are labelled unavailable. Locations reflect current station assignments, not historical wing location snapshots.
- Eligibility suggestions: name, identifiers, gender, age, constituency/branch and an explicit note that enrollment has not been confirmed. Suggestions use the same recorded eligibility rules and do not auto-enroll anyone.
- Participation history: original enrollment name/system number, wing, effective dates, ending reason, recording/ending actors and recorded timestamps. History remains person-linked across party membership periods and does not substitute a current name for the original saved identity.

No phone, email, street address, raw date of birth, notes, encrypted contact/address values or photo bytes are exported. History now loads only the person identity needed for the page/report, instead of retrieving the full shared-person record and its encrypted contacts. The enrollment report's eligibility evaluation uses the existing directory projection internally; raw birth dates are not exported.

## Refactoring

Feature-specific report preparation remains under services/membership/WingReportService.php and services/executives/ReportService.php. Shared CSV output and size guards live under services/reporting/ReportOutput.php. The standalone print template and assets live under views/shared/reports and assets/css/shared / assets/js/shared. Retired executive-specific print template/assets were removed after updating references; executive report routes, permissions, criteria, columns and limits are preserved.

Report queries reuse the actual directory services in a read transaction with an export flag. Filters/order are shared; there is no separately maintained report query. Size checks run before the full row query and again before output. Recoverable validation/database errors flash a message and return to the directory; authorization and not-found errors retain normal HTTP handling.

## Manual acceptance in Laragon/phpMyAdmin

1. With your own data, filter each wing directory by search, constituency/branch, residential scope, enrollment state and review. Print and export from page 2 or later: total/order must match the entire matching set, not just that page.
2. Check candidates and person history, including history across party re-enrollment. Person/wing/state filters must appear in the report criteria, and Back to results must restore them. Review zero results: CSV contains headers only and print shows a no-results row.
3. Check Youth 50+ and general Needs review reports against dashboard drill-downs. Report generation must not end any enrollment or executive role, create audit enrollment changes or consume new membership numbers.
4. Verify original name/system number after editing a member's current name or Party ID. Ended enrollment and person-history reports must preserve their documented distinction between current display and saved identity.
5. Inspect UTF-8 names, leading-zero identifiers imported as text, commas, quotes/newlines and formula-like content. Confirm no phone/email/address, birth-date column, private notes or photo content is included.
6. Viewer: print succeeds, CSV controls are absent, forged CSV POST is forbidden. Admin/registrar: expired/missing CSRF is rejected. Missing migration 20 and more than 5,000 matches must return guidance without partial CSV output.
7. Recheck executive current/scheduled/completed/cancelled print and CSV reports after the shared-output refactor, including organization filters, expected end dates, report criteria and authorization.
8. Check mobile scrolling, focus visibility, NDC logo proportions, browser printing/PDF, repeated table headings and visible criteria. Run database/runtime checks manually only.

Automatic verification is PHP syntax/static only. No database, browser or migration execution was performed automatically.

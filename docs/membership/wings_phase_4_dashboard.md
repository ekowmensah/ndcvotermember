# Membership wings — dashboard statistics

Phase 4 adds a Membership wings section to Overview. It uses migration 20 for enrollment statistics and migration 21 for wing executive statistics. No further migration, data backfill, automatic enrollment or background ending is added.

## Counts and drill-downs

Each wing shows:

- Enrolled members: open wing enrollments, including members whose enrollment needs review. Inactive/deceased status does not silently end enrollment.
- Eligible, not enrolled: active, nondeceased members meeting that wing's recorded eligibility criteria, without an open enrollment in the selected wing or an enrollment that ended today. This uses the same shared SQL condition as Eligibility suggestions.
- Needs review: open enrollments with ineligible recorded details, inactive/missing party membership or deceased status. Each enrollment counts once even if several issues apply.
- Ended enrollments: historical enrollment records, including prior membership periods. This is a count of records, not distinct people.
- Youth aged 50+: open Youth enrollments whose recorded age is at least 50. This is a subset of Needs review and links to the new age-out review filter. Missing birth dates appear under general Needs review, not age 50+.
- Executive service: current appointments, scheduled appointments and completed service terms within that wing organization. Counts represent appointments, not distinct executives. Cancelled reservations are excluded.

A member can be in both wings and hold several executive roles. Never add these categories to infer unique people. No percentage or total claims to make them mutually exclusive.

## Residential scope

Every metric follows the dashboard's selected residential region/district and excludes deleted voter records. Ended records whose person has been purged are retained in storage but excluded from this current-person dashboard.

Drill-down links preserve residential scope. The wing directories and Eligibility suggestions now validate and apply that scope server-side; searches, filtering and pagination retain it through hidden scope fields. Applied filter chips show the actual residential names and allow removing scope explicitly. Reset clears all directory filters, including residential scope. Wing directory toolbar links preserve scope when moving to suggestions, review or wing executives.

The dashboard's Ended enrollments link adds active_people=1 so the directory excludes deleted/unlinked people in the same way as the panel. The ordinary ended directory remains a broader historical view when that filter is removed. Executive service uses the same scoped HistoryService dashboard implementation as the existing executive panel, with Organization filtering added; completed-term links likewise preserve active_people=1.

The implementation uses aggregate metadata queries only. It retrieves no encrypted contacts/addresses, date-of-birth values, member notes or photo bytes for these panels. Birth dates are evaluated in SQL to establish age; the aggregate response contains only counts. New services live under services/membership; the panel lives under views/dashboard/partials/wings.php.

## Manual acceptance in Laragon/phpMyAdmin

1. Before migration 20, Overview must show wing setup guidance without querying the absent enrollment table. After 20 but before 21, enrollment counts work while executive rows show migration guidance. After 21, scope-specific executive counts appear. No new migration is required for Phase 4.
2. With your own records, compare each Enrolled, Eligible, Needs review, Ended and Youth 50+ count with its linked directory's full matching total. Check zero states and more than one result page. No link should count only the first 25 rows.
3. Repeat for all residential locations, a region and a district. Search in the resulting directory, change electoral filters, use pagination and remove one applied chip; ensure the residential filters persist until deliberately removed/reset.
4. Enroll a person in both wings: each wing should show them once. End/rejoin a wing: the current count remains at most one, historical records increase, and eligibility suggestions exclude same-day endings until tomorrow.
5. Confirm current Youth ages 18–49 are not age-out flagged; age 50+ is flagged without ending enrollment. Missing DOB, inactive membership and deceased status must appear under Needs review. Multiple issues on one enrollment must count once. Female Women's members are unaffected by Youth age limits.
6. Remove party membership after closing executive roles: wing enrollments move from open to ended. Move the nonmember voter to Deleted voters and verify these dashboard records disappear; follow Ended enrollments to confirm its filtered total matches. Restore and check that history reappears but wing enrollment is not reopened.
7. Appoint the same title/location independently in Main Party and the wings. Current/scheduled/completed counts must remain organization-specific. Follow executive links and compare totals. Cancellation must not count as completed service.
8. Viewer accounts can read the panel and drill-downs but still cannot enroll or appoint. Check mobile, dark theme, dashboard print and active sidebar behavior. No visual badge or count should imply automatic eligibility enrollment or automatic service closure.

Automatic verification is PHP syntax/static only. Database and browser acceptance remain manual; no live data or schema was modified by development checks.

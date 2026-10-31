# Executives — Phase 4: Hall of service and dashboard

No new migration. Existing migrations through 18 are required; no demo data is added. This phase reads existing appointments and does not alter historical records.

## Hall of service

Executives → Hall of service. Shows completed terms with historical names, Party ID/Sys No., title, level, jurisdiction, appointment type, service period, end reason and a link to full term details/chain/notes. Historical identity comes from saved appointment snapshots, including after membership removal or permanent voter deletion. Any thumbnail is the current available photo, not an archived photo snapshot.

Cancelled reservations and open/scheduled appointments are excluded. The archive is a factual service record, not a curated award list; removed/resigned/deceased terms remain visible with their recorded end reason. Nothing implies that a cancelled reservation was served.

Search preserved names/IDs/titles/jurisdictions. Collapsible filters include service year (any service during that year), position, level, appointment type, end reason and electoral region/constituency/branch/station. Order latest/oldest completion, historical name or historical position. Results are paginated 25 per page. Applied filter chips remain visible while collapsed.

All four summary cards follow the full matching set, not the page: completed terms, distinct membership numbers represented, distinct positions and organizational levels. Memberships represented is intentionally not labelled distinct people: one person may have had more than one membership across re-enrollment.

The default archive preserves all historical service, including people later deleted. The People records filter can restrict it to records still in the active voter directory. Residential filters from dashboard links follow current saved person addresses, not historical residence. Their chips can be removed individually; Clear returns to the complete archive.

## Dashboard

Executive service panel:
- Current appointments: started, not ended/cancelled.
- Scheduled appointments: future start, not ended/cancelled.
- Completed terms: actual ended service, excluding cancellations.
- Expected end within 30 days: current appointments with expected date today through today+30 days.
- Overdue end review: current appointments whose expected end is before today.
- Current appointments and distinct people by organizational level.

All panel values and links follow the main dashboard residential region/district selection and exclude deleted or permanently removed voter records. The Hall of service link includes the same active-people/residential criteria so its completed count matches. Cancelled reservations never inflate service totals. Due/overdue links carry the review criteria into the appointments list, which preserves them across searches, tabs and pagination and exposes removable chips.

Counts are appointments, not unique executives, except the explicitly labelled people column. Membership/life status remains separate: an inactive/deceased person with an unclosed office remains flagged for human lifecycle review, never silently removed. No vacancy totals are inferred without an established-office roster.

Read access follows the existing viewer/admin/registrar model. No archive write actions are added; existing management pages enforce their roles and confirmations. Historical identifiers are read-only. Notes/contact/photo bytes are not selected in archive list or dashboard statistics.

## Manual acceptance checklist

Use your own Laragon/phpMyAdmin records; no database/browser automation is run.

1. With no completed terms, verify zero counts/empty states. Existing current/scheduled/cancelled records must not appear in Hall of service.
2. End one of your terms via the existing workflow. Confirm the archive's identity, dates, end reason and term links match the saved snapshots.
3. Verify a same-day linked replacement produces one completed predecessor and one open successor, not duplicate served/current entries. Its history links remain intact.
4. Correct a current member's name/Party ID or rename a directory location/title. Check archived terms retain their original recorded labels/IDs while current profiles use updated values.
5. Search historical name, leading-zero ID, title and jurisdiction. Check all filters, chips, sort orders, clear and pagination. Summary cards must count the entire filtered result.
6. Choose a service year that falls inside a multi-year completed term. It should match even if neither start nor actual end was in that year.
7. Compare dashboard executive counts against appointments/current/scheduled/completed data, then select a residential region/district. Click each count and ensure matching filters and totals are retained after search and pagination.
8. Test expected end today, +30 days, +31 days, yesterday and blank. Today/+30 belong in due; yesterday in overdue; +31/blank in neither. Ended/cancelled/scheduled records must not appear in current due reviews.
9. Give one member different offices: appointment count grows while distinct people at that level stays one. Hall metrics count membership numbers, not claimed unique people.
10. After all offices close, remove/recreate membership or merge a duplicate voter through existing workflows. Verify history snapshots/links persist and person-linked timelines remain correct. Archive should also retain terms after permanent voter deletion, without a broken photo.
11. Compare all-archive versus active-people filter. Deleted people remain in the former but are excluded from dashboard scope and the latter.
12. Viewer can browse archive, filters and detail, but cannot access new mutation permissions. Check mobile, dark theme and keyboard use of searchable dropdowns.

Only PHP syntax/static checks are automated. Please report amendments after testing before extending the executive module further.

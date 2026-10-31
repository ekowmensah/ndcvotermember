# Executives — Phase 1: position titles and applicable levels

One position title is defined once. Choose the levels where it applies: National, Region, Constituency, Electoral area (branch), Polling station. New titles default to all five levels; at least one must remain selected. Duplicate titles are blocked globally, including inactive titles. Applicable levels can be edited during this phase.

## phpMyAdmin setup

Import migration 15 if it has not been applied, followed by the COMPLETE database/16_executive_position_levels_mysql8.sql file. No app migration executes automatically.

Migration 16 consolidates same-name legacy positions into one title using the database collation, combines their levels, retains the earliest ID/name/description/creator and lowest display order, and makes the title active if any legacy entry was active. Original rows remain unchanged in executive_positions; executive_position_legacy_map records their canonical title IDs. The active directory uses executive_position_titles and executive_position_levels.

The migration records completion transactionally. Reimporting preserves subsequent edits rather than recopying old settings. Polling station is an available level; migrated titles retain their prior levels until you enable more. Review consolidated descriptions and statuses because these are now shared by all selected levels.

## Manual checks

1. Import 16 after 15; verify existing titles/levels are retained. Same-title legacy rows should appear as one title with combined levels; inspect the preserved legacy table and mapping if needed.
2. Add your own title with all five levels. Confirm one row with five badges, including Polling station.
3. Edit that title and change its applicable levels. Confirm level filters match the selected set.
4. Submit no selected levels or an invalid level: reject without changing title or levels; recover entered selections.
5. Try a duplicate title with a different level selection: reject. Reactivate an inactive title instead.
6. Check display order, description, global active/inactive state, search, collapsed filters, chips and pagination.
7. Open two edit tabs; save in one, then submit the old version. The stale update must fail without overwriting levels.
8. Cancel without writes. Viewer may browse but cannot add/edit/POST; admin/registrar may manage.
9. Reimport migration 16 after changing levels/status/name and verify no edits are reset.
10. Check audit events include before/after levels. Saving unchanged data should not create an event.

Only syntax/static checks were run automatically; database and browser verification remain manual in phpMyAdmin/Laragon.

## Next phases — await user instruction

2. Member appointments: choose a position, one supported level, the exact electoral jurisdiction (including polling station), member and service dates/type. Prevent overlapping single-holder offices.
3. End/replace appointments and per-member executive timeline; preserve historical labels and member lifecycle rules. Later level changes must not invalidate existing appointments.
4. Executive history/Hall of service, filters, dashboard and optional curated recognition.

Remain in Phase 1 until the user finishes testing and requests the next phase.
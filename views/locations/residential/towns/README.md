# Town editing and merging

Admin/registrar: Residential towns → Edit town. Edit the name, residential region and optional district. A district must belong to the selected region. No new migration is required.

Saving renames/reclassifies the town and synchronizes every linked voter, including members, atomically. When another town matches the new name and residential scope under the database collation, a server-rendered confirmation prompt appears before any writes. Only Confirm merge authorizes that specific destination and linked-person snapshot. After confirmation, the existing town survives with its ID and saved spelling; linked people transfer to it and the source duplicate is deleted. Equal names in different scopes remain separate. Memberships, photos, private addresses and contact fields are preserved. Source people versions advance; edits and merges are audited.

Manual checks in Laragon/phpMyAdmin, using your own records:
- Rename a town and confirm linked voter/member profiles and filters show its new spelling.
- Rename/reclassify into an existing town in the same region/district. Verify one town survives, all people are linked correctly, and no voter/member is lost.
- Check equal names in different districts stay separate; test merging unassigned towns.
- Check case/accent-equivalent names follow the database collation and retain destination spelling.
- Change region and confirm district resets; cross-region district submissions must fail.
- Submit an outdated editor after rename or merge; it must not overwrite changes or recreate the removed town.
- Keep a linked person editor or CSV preview open during a change; its old version must be rejected.
- Cancel must not write; viewers cannot edit. Check successful merges in the audit log.
- Check the first duplicate save changes nothing and displays the destination, linked-person count, Confirm merge and Cancel merge. Cancel must preserve both towns. Confirm must merge once; changing inputs or linked records requires a fresh confirmation.

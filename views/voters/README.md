# Voter profile and editor

Existing nonmembers open a read-only profile at voter&id=ID. Edit voter opens voter&id=ID&edit=1 for admin/registrar. Add voter retains its existing form. Enrolled people, active or inactive, redirect to their member profile and keep the member-only update flow.

Presentation data lives in services/voters/ProfileService.php; show.php composes identity, details and polling partials. The voter-specific design uses a horizontal identity banner, numbered detail sections and a polling/enrollment side panel. Styles and print/copy controls live under assets/css/voters/profile.css and assets/js/voters/profile.js independently of member profiles. Private contact values are displayed only in the authenticated profile, never added to list queries or browser storage. Optional follow-up links are manager-only.

Manual checks in Laragon with your own records:
- Open voter links from lists; confirm photo/placeholder, exact voter ID, names, contact fields and separate residential/electoral details.
- Check empty fields, legacy full names, missing station and station without a branch; branch remains derived and read-only.
- Edit and cancel without writes. Successful saves return to the profile; validation failures retain version-matching inputs in the editor. Re-select uploaded photos after failed saves as before.
- Check viewer can view/print but has no editing/enrollment actions and cannot open edit mode.
- Mark as member opens enrollment review without creating a membership. After enrollment, voter URLs redirect to the member profile.
- Check mobile layout, dark theme, keyboard navigation, print and copy fallback.
- Check stale edits after town corrections/merges and enrollment still fail safely.

No migration or demo data is required. Syntax-only checks are automated; database/browser checks remain manual.

The add/edit form uses its own numbered navigation sidebar, contextual header, photo panel and top/bottom save controls. Scoped assets are assets/css/voters/editor.css and assets/js/voters/editor.js. Shared person fields and existing input names, CSRF, record versions, encrypted recovery and address/photo handlers remain intact. Manual checks should include section navigation, required-field focus, unsaved-change status, both save buttons and mobile dropdown visibility.
Polling station is required on manual personal saves and CSV added/updated rows. Manually check missing/invalid station rejection, official-code-only CSV parent derivation, cross-parent mismatch rejection, station without branch acceptance, missing-station preview exclusion, and unchanged legacy records until edited. Existing missing stations can be found with the Missing polling station directory filter.

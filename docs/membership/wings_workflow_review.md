# Wing workflow review amendments

No new migration or data changes are required.

The enrollment editor now shows the earliest allowed joining date, incorporating party membership start, the Youth 18th birthday and the day after any previous inclusive enrollment end. A person whose previous enrollment ended today sees the next available date instead of an unusable enrollment form. Member-profile eligibility guidance uses the same enrollment windows. The server continues to validate eligibility, revisions and dates under person/member locks.

When current or scheduled executive roles block leaving a wing, the editor explains the blocker and links to the relevant service actions instead of offering an ending form that will fail. After roles are closed, the date field's minimum includes the latest actual end of service linked to that enrollment. The server's locked exit guard remains authoritative for changes in another tab. Open-role queries are shared between the editor and action links rather than repeated separately for each wing.

Recoverable wing report errors now revalidate return filters through the directory services. Valid filters are preserved for oversized reports. Invalid or deleted scope/filter values fall back to a valid base directory instead of causing a second failing page. Permissions, missing-person HTTP responses and CSRF behavior are unchanged.

Manual acceptance: check joining on/after the 18th birthday; same-day ending/rejoining; a prior enrollment ending yesterday; past party membership dates; open executive role blockers; closed-service end minima; and a role created after the editor was opened. Verify the normal save still rejects tampered/stale dates. Check oversized-report return filters and invalid/deleted location filters. No history is automatically ended, corrected or deleted.

Only PHP syntax/static checks were performed automatically. Database/browser acceptance remains manual in Laragon/phpMyAdmin.

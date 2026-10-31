'use strict';
document.querySelectorAll('.member-workspace').forEach(workspace => {
 workspace.querySelectorAll('[data-member-print]').forEach(button => button.addEventListener('click', () => window.print()));
 const copyStatus = workspace.querySelector('[data-member-copy-status]');
 workspace.querySelectorAll('[data-member-copy]').forEach(button => {
  button.addEventListener('click', async () => {
   try {
    if (!navigator.clipboard) throw new Error('Clipboard unavailable');
    await navigator.clipboard.writeText(button.dataset.memberCopy);
    copyStatus.textContent = 'Identifier copied.';
   } catch (_) {
    copyStatus.textContent = 'Clipboard unavailable. Select the identifier text and copy it.';
   }
  });
 });
 const form = workspace.querySelector('[data-member-form]');
 if (form) {
  const status = form.querySelector('[data-member-save-status]');
  const changed = () => { status.textContent = 'Unsaved changes. Save to update the member and shared voter details.'; };
  form.addEventListener('input', changed);
  form.addEventListener('change', changed);
 }
});
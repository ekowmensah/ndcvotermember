'use strict';
document.querySelectorAll('.voter-profile').forEach(profile => {
 profile.querySelectorAll('[data-voter-print]').forEach(button => button.addEventListener('click', () => window.print()));
 const status = profile.querySelector('[data-voter-copy-status]');
 profile.querySelectorAll('[data-voter-copy]').forEach(button => button.addEventListener('click', async () => {
  try {
   await navigator.clipboard.writeText(button.dataset.voterCopy);
   status.textContent = 'Voter ID copied.';
  } catch (_) {
   status.textContent = 'Select the voter ID text to copy it.';
  }
 }));
});
'use strict';
document.querySelectorAll('[data-staff-account-form]').forEach(form => {
 if (form.elements.mode?.value !== 'create') return;
 const member = form.elements.member_id;
 const name = form.elements.name;
 const email = form.elements.email;
 const password = form.elements.password;
 const confirmation = form.elements.password_confirmation;
 const memberStatus = form.querySelector('[data-member-details-status]');
 const passwordStatus = form.querySelector('[data-password-status]');
 const generate = form.querySelector('[data-generate-password]');
 const toggle = form.querySelector('[data-toggle-password]');
 let pending;
 let selection = 0;
 member?.addEventListener('change', async () => {
  pending?.abort();
  const request = ++selection;
  name.value = ''; email.value = ''; password.value = ''; confirmation.value = '';
  passwordStatus.textContent = 'Enter or generate a password for the selected member.';
  if (!member.value) { memberStatus.textContent = 'Select a member to fill their name and available email.'; return; }
  pending = new AbortController();
  memberStatus.textContent = 'Loading member details…';
  try {
   const target = new URL(form.dataset.memberDetailsUrl, location.href);
   target.searchParams.set('id', member.value);
   const response = await fetch(target, {signal: pending.signal, credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}});
   if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error('unavailable');
   const details = await response.json();
   if (request !== selection) return;
   name.value = Array.from(String(details.name || '')).slice(0, 150).join('');
   email.value = String(details.email || '');
   memberStatus.textContent = email.value ? 'Member name and email filled. Review before saving.' : 'Member name filled. No email is recorded; enter a unique sign-in email.';
  } catch (error) {
   if (request === selection && error.name !== 'AbortError') memberStatus.textContent = 'Could not load member details. Enter the details manually or refresh your session.';
  }
 });
 const randomIndex = max => {
  const range = 256 - (256 % max);
  const bytes = new Uint8Array(1);
  do { window.crypto.getRandomValues(bytes); } while (bytes[0] >= range);
  return bytes[0] % max;
 };
 if (!window.crypto?.getRandomValues) {
  generate.disabled = true;
  passwordStatus.textContent = 'Secure generation is unavailable in this browser. Enter a unique password manually.';
 }
 generate?.addEventListener('click', () => {
  const groups = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%&*+-=?'];
  const alphabet = groups.join('');
  const chars = groups.map(group => group[randomIndex(group.length)]);
  while (chars.length < 20) chars.push(alphabet[randomIndex(alphabet.length)]);
  for (let i = chars.length - 1; i > 0; i--) { const j = randomIndex(i + 1); [chars[i], chars[j]] = [chars[j], chars[i]]; }
  password.value = chars.join(''); confirmation.value = password.value;
  passwordStatus.textContent = 'A 20-character password was generated and both fields filled. Show or copy it to share securely.';
 });
 toggle?.addEventListener('click', () => {
  const visible = password.type === 'password';
  password.type = visible ? 'text' : 'password'; confirmation.type = password.type;
  toggle.textContent = visible ? 'Hide password' : 'Show password'; toggle.setAttribute('aria-pressed', String(visible));
 });
 form.querySelector('[data-copy-password]')?.addEventListener('click', async () => {
  if (!password.value) { passwordStatus.textContent = 'Enter or generate a password first.'; return; }
  try { await navigator.clipboard.writeText(password.value); passwordStatus.textContent = 'Password copied. Share it privately; it will not be recoverable after saving.'; }
  catch { passwordStatus.textContent = 'Clipboard unavailable. Use Show password and copy it manually.'; }
 });
});

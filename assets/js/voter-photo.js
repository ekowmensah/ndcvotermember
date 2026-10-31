'use strict';
document.querySelectorAll('[data-photo-editor]').forEach(editor => {
 const input = editor.querySelector('[data-photo-input]');
 if (!input) return;
 const image = editor.querySelector('[data-photo-preview]');
 const placeholder = editor.querySelector('[data-photo-placeholder]');
 const remove = editor.querySelector('[data-photo-remove]');
 const status = editor.querySelector('[data-photo-status]');
 const original = image.getAttribute('src');
 let revision = 0;
 const display = source => {
  image.hidden = !source;
  placeholder.hidden = !!source;
  if (source) image.src = source;
  else image.removeAttribute('src');
 };
 input.addEventListener('change', () => {
  const current = ++revision;
  const file = input.files[0];
  status.textContent = '';
  if (!file) { display(remove && remove.checked ? null : original); return; }
  if (file.size > 2 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
   status.textContent = 'Choose a JPEG, PNG or WebP photo up to 2 MB.';
   input.value = '';
   display(remove && remove.checked ? null : original);
   return;
  }
  if (remove) remove.checked = false;
  const reader = new FileReader();
  reader.onload = () => {
   if (revision !== current) return;
   display(reader.result);
   status.textContent = 'New photo selected. Save the record to apply it.';
  };
  reader.onerror = () => { if (revision === current) { input.value = ''; display(original); status.textContent = 'Could not preview this file. Select another photo.'; } };
  reader.readAsDataURL(file);
 });
 if (remove) remove.addEventListener('change', () => {
  revision++;
  input.value = '';
  display(remove.checked ? null : original);
  status.textContent = remove.checked ? 'Photo will be removed when you save the record.' : '';
 });
});

'use strict';
document.querySelectorAll('form[data-confirm]').forEach(form => {
  form.addEventListener('submit', event => { if (!window.confirm(form.dataset.confirm)) event.preventDefault(); });
});
const themeButton = document.querySelector('.theme-toggle');
if (themeButton) {
  try { document.documentElement.dataset.bsTheme = localStorage.getItem('membership-theme') === 'dark' ? 'dark' : 'light'; } catch (_) {}
  themeButton.addEventListener('click', () => {
    const theme = document.documentElement.dataset.bsTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.bsTheme = theme;
    try { localStorage.setItem('membership-theme', theme); } catch (_) {}
  });
}

document.querySelectorAll('[data-directory-filters]').forEach(form => {
 const constituency = form.querySelector('[name="constituency_filter"]');
 const branch = form.querySelector('[name="branch_filter"]');
 if (!constituency || !branch) return;
 const branches = JSON.parse(form.dataset.branches);
 constituency.addEventListener('change', () => {
  branch.replaceChildren(new Option('All branches', ''));
  branches.filter(item => !constituency.value || String(item.constituency_id) === constituency.value)
   .forEach(item => branch.add(new Option(item.name, item.id)));
 });
});
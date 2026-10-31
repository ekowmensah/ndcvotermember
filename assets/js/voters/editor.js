'use strict';
document.querySelectorAll('[data-voter-editor]').forEach(editor => {
 const form = editor.querySelector('form');
 const status = editor.querySelector('[data-voter-editor-status]');
 if (!form || !status) return;
 const changed = () => { status.textContent = 'Unsaved changes'; };
 form.addEventListener('input', changed);
 form.addEventListener('change', changed);
 const links = Array.from(editor.querySelectorAll('.voter-editor-nav a'));
 const activate = link => links.forEach(item => {
  item.classList.toggle('active', item === link);
  if (item === link) item.setAttribute('aria-current', 'location');
  else item.removeAttribute('aria-current');
 });
 links.forEach(link => link.addEventListener('click', () => activate(link)));
 form.addEventListener('invalid', event => {
  const section = event.target.closest('section[id]');
  const link = section && links.find(item => item.hash === '#' + section.id);
  if (link) activate(link);
 }, true);
 if ('IntersectionObserver' in window) {
  const observer = new IntersectionObserver(entries => {
   entries.filter(entry => entry.isIntersecting).forEach(entry => {
    const link = links.find(item => item.hash === '#' + entry.target.id);
    if (link) activate(link);
   });
  }, {rootMargin: '-100px 0px -50% 0px', threshold: 0});
  form.querySelectorAll('section.card[id]').forEach(section => observer.observe(section));
 }
 if (links.length) activate(links.find(link => link.hash === window.location.hash) || links[0]);
});
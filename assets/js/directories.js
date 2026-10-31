'use strict';
document.querySelectorAll('[data-directory-searchable]').forEach(form => {
 const field = name => form.querySelector('[name="' + name + '"]');
 const render = (select, options, current = '') => {
  if (!select) return;
  if (select.tomselect) {
   const control = select.tomselect;
   control.clear(true); control.clearOptions(); control.addOptions(options);
   control.setValue(current, true); control.refreshOptions(false);
  } else {
   select.replaceChildren(...options.map(item => new Option(item.text, item.value)));
   select.value = current;
  }
 };
 const sync = name => {
  const select = field(name);
  if (!select || !select.tomselect) return;
  const current = select.value;
  const options = Array.from(select.options).map(item => ({value: item.value, text: item.textContent}));
  render(select, options, current);
 };
 if (typeof TomSelect !== 'undefined') {
  form.querySelectorAll('select').forEach(select => {
   if (select.tomselect || (!/region|district|town|constituency|branch|parent_id/.test(select.name) && select.options.length < 8)) return;
   const disabled = select.matches(':disabled');
   const control = new TomSelect(select, {
    plugins: ['dropdown_input'], create: false, maxItems: 1, allowEmptyOption: true,
    searchField: ['text'], maxOptions: 200, closeAfterSelect: true
   });
   control.control_input.setAttribute('placeholder', 'Type to search...');
   control.control_input.setAttribute('aria-label', 'Search ' + (select.labels[0]?.textContent || 'locations'));
   if (disabled) control.disable();
  });
 }
 // Earlier native handlers rebuild dependent options. Refresh the enhanced controls afterwards.
 const dependencies = {
  residential_region_filter: ['residential_district_filter', 'residential_town_filter'],
  residential_district_filter: ['residential_town_filter'],
  constituency_filter: ['branch_filter'],
  branch_filter: [],
  region_filter: form.hasAttribute('data-location-filters') ? ['constituency_filter'] : []
 };
 Object.entries(dependencies).forEach(([name, targets]) => {
  const source = field(name);
  if (!source) return;
  source.addEventListener('change', () => {
   targets.forEach(sync);
   if (name === 'branch_filter' || name === 'constituency_filter' || (name === 'region_filter' && form.hasAttribute('data-location-filters'))) {
    const station = field('polling_station_filter');
    if (station) station.value = '';
    const hiddenBranch = form.querySelector('input[type="hidden"][name="branch_filter"]');
    if (hiddenBranch) hiddenBranch.value = '';
   }
  });
 });
 const electoralRegion = field('electoral_region_filter');
 if (electoralRegion) {
  const constituencies = JSON.parse(form.dataset.peopleConstituencies || '[]');
  const branches = JSON.parse(form.dataset.branches || '[]');
  electoralRegion.addEventListener('change', () => {
   const eligible = constituencies.filter(item => !electoralRegion.value || String(item.region_id) === electoralRegion.value);
   const ids = new Set(eligible.map(item => String(item.id)));
   render(field('constituency_filter'), [{value: '', text: 'All constituencies'}, ...eligible.map(item => ({value: String(item.id), text: item.name}))]);
   render(field('branch_filter'), [{value: '', text: 'All branches'}, ...branches.filter(item => ids.has(String(item.constituency_id))).map(item => ({value: String(item.id), text: item.name}))]);
   const station = field('polling_station_filter');
   if (station) station.value = '';
  });
 }
});
document.querySelectorAll('[data-directory-workspace]').forEach(workspace => {
 const table = workspace.querySelector('[data-directory-table]');
 const menu = workspace.querySelector('[data-column-options]');
 if (!table || !menu) return;
 const storageKey = 'directory-columns-' + workspace.dataset.directoryWorkspace;
 let hidden = [];
 try { const saved = JSON.parse(localStorage.getItem(storageKey) || '[]'); if (Array.isArray(saved)) hidden = saved.filter(item => typeof item === 'string'); } catch (_) {}
 const persist = () => { try { localStorage.setItem(storageKey, JSON.stringify(hidden)); } catch (_) {} };
 const controls = [];
 table.querySelectorAll('thead th').forEach((heading, index) => {
  if (!heading.hasAttribute('data-column-optional')) return;
  const name = heading.textContent.trim();
  const label = document.createElement('label');
  const input = document.createElement('input');
  input.type = 'checkbox'; input.className = 'form-check-input'; input.checked = !hidden.includes(name);
  const apply = () => {
   table.querySelectorAll('tr').forEach(row => { if (row.children[index]) row.children[index].classList.toggle('directory-column-hidden', !input.checked); });
  };
  input.addEventListener('change', () => {
   hidden = hidden.filter(item => item !== name);
   if (!input.checked) hidden.push(name);
   apply(); persist();
  });
  label.append(input, document.createTextNode(name)); menu.append(label);
  controls.push({input, apply}); apply();
 });
 const reset = document.createElement('button');
 reset.type = 'button'; reset.className = 'btn btn-sm btn-light mt-2'; reset.textContent = 'Show all columns';
 reset.addEventListener('click', () => { hidden = []; controls.forEach(item => { item.input.checked = true; item.apply(); }); persist(); });
 menu.append(reset);
});
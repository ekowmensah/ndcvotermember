'use strict';
document.querySelectorAll('[data-executive-appointment]').forEach(form => {
 const positions = JSON.parse(form.dataset.positionLevels);
 const catalog = JSON.parse(form.dataset.electoralCatalog);
 const saved = JSON.parse(form.dataset.selectedScope);
 const names = ['position_id', 'organization_level', 'region_id', 'constituency_id', 'branch_id', 'polling_station_id'];
 const fields = Object.fromEntries(names.map(name => [name, form.elements.namedItem(name)]));
 const labels = {national: 'National', region: 'Region', constituency: 'Constituency', branch: 'Electoral area (branch)', polling_station: 'Polling station'};
 const replace = (name, rows, current = '') => {
  const select = fields[name];
  if (select.tomselect) select.tomselect.destroy();
  select.replaceChildren(new Option('Choose...', ''));
  rows.forEach(row => select.add(new Option((row.station_code ? row.station_code + ' · ' : '') + row.name, row.id)));
  select.value = String(current || '');
  if (select.selectedIndex < 0) select.value = '';
 };
 const enhance = () => {
  if (typeof TomSelect === 'undefined') return;
  names.forEach(name => {
   const select = fields[name];
   if (select.disabled || select.tomselect) return;
   new TomSelect(select, {plugins: ['dropdown_input'], create: false, maxItems: 1, allowEmptyOption: true, maxOptions: 200});
  });
 };
 const scope = (initial = false) => {
  const level = fields.organization_level.value;
  const required = {
   region_id: level !== '' && level !== 'national',
   constituency_id: ['constituency', 'branch', 'polling_station'].includes(level),
   branch_id: level === 'branch', polling_station_id: level === 'polling_station'
  };
  Object.entries(required).forEach(([name, visible]) => {
   const select = fields[name];
   if (select.tomselect) select.tomselect.destroy();
   select.disabled = !visible; select.required = visible;
   form.querySelector('[data-scope-field="' + name + '"]').hidden = !visible;
   select.value = visible && initial ? String(saved[name] || '') : '';
  });
  refresh('region', initial);
 };
 const refresh = (changed, initial = false) => {
  if (changed === 'region') {
   replace('constituency_id', catalog.constituencies.filter(row => String(row.region_id) === fields.region_id.value), initial ? saved.constituency_id : '');
  }
  replace('branch_id', catalog.electoral_areas.filter(row => String(row.constituency_id) === fields.constituency_id.value), initial ? saved.branch_id : '');
  replace('polling_station_id', catalog.polling_stations.filter(row => String(row.constituency_id) === fields.constituency_id.value), initial ? saved.polling_station_id : '');
  enhance();
 };
 const levels = initial => {
  const allowed = (positions[fields.position_id.value] || '').split(',');
  replace('organization_level', Object.entries(labels).filter(([id]) => allowed.includes(id)).map(([id, name]) => ({id, name})), initial ? saved.organization_level : '');
  scope(initial);
 };
 fields.position_id.required = true; fields.organization_level.required = true;
 fields.position_id.addEventListener('change', () => levels(false));
 fields.organization_level.addEventListener('change', () => scope(false));
 fields.region_id.addEventListener('change', () => refresh('region'));
 fields.constituency_id.addEventListener('change', () => refresh('constituency'));
 levels(true);
 const start = form.elements.namedItem('starts_on');
 const expected = form.elements.namedItem('expected_ends_on');
 const constrainDates = () => { expected.min = start.value || ''; };
 start.addEventListener('change', constrainDates); constrainDates();
});

document.querySelectorAll('[data-executive-replacement]').forEach(form => {
 const ending = form.elements.namedItem('replacement_ended_on');
 const start = form.elements.namedItem('starts_on');
 const expected = form.elements.namedItem('expected_ends_on');
 const update = () => {
  const date = new Date(ending.value + 'T00:00:00Z');
  if (!Number.isNaN(date.getTime())) {
   // A linked replacement may start on this same handover date.
   start.min = date.toISOString().slice(0, 10);
  } else start.min = '';
  expected.min = start.value || '';
 };
 ending.addEventListener('change', update);
 start.addEventListener('change', update);
 update();
});

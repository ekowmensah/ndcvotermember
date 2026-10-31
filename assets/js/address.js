'use strict';
document.querySelectorAll('[data-address-picker]').forEach(picker => {
 const catalog = JSON.parse(picker.dataset.addressCatalog);
 const region = picker.querySelector('[data-address-region]');
 const district = picker.querySelector('[data-address-district]');
 const town = picker.querySelector('[data-town-choice]');
 const newTown = picker.querySelector('[data-address-town]');
 const status = picker.querySelector('[data-town-status]');
 const tags = picker.querySelector('[data-popular-towns]');
 const tagStatus = picker.querySelector('[data-popular-town-status]');
 const enabled = !region.matches(':disabled');
 const normalize = value => value.trim().replace(/\s+/g, ' ');
 const key = value => normalize(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
 const towns = () => (catalog.towns || []).filter(item => (item.region_name || '') === region.value && (item.district_name || '') === district.value);
 const matches = (name, query) => key(name).includes(key(query));
 const render = (select, rows, current) => {
  const options = [{value: '', text: 'Not assigned'}, ...rows];
  const selected = options.some(item => item.value === current) ? current : '';
  if (select.tomselect) {
   const control = select.tomselect;
   control.clear(true); control.clearOptions(); control.addOptions(options);
   control.setValue(selected, true); control.refreshOptions(false);
  } else {
   select.replaceChildren(...options.map(item => new Option(item.text, item.value)));
   select.value = selected;
  }
 };
 const refreshTags = () => {
  tags.replaceChildren();
  const parent = catalog.districts.find(item => item.name === district.value && item.region_name === region.value);
  if (!parent) {
   tagStatus.textContent = 'Select a district to see its popular towns.';
   return;
  }
  const popular = towns().filter(item => String(item.district_id) === String(parent.id) && Number(item.voter_count) > 0)
   .sort((a, b) => Number(b.voter_count) - Number(a.voter_count) || a.name.localeCompare(b.name)).slice(0, 12);
  const maximum = Math.max(1, ...popular.map(item => Number(item.voter_count)));
  popular.forEach(item => {
   const button = document.createElement('button');
   button.type = 'button'; button.className = 'town-tag';
   button.textContent = item.name;
   button.style.fontSize = (0.85 + 0.4 * Math.log1p(Number(item.voter_count)) / Math.log1p(maximum)) + 'rem';
   button.title = Number(item.voter_count) + ' voter(s) in this town';
   button.setAttribute('aria-pressed', String(town.value === String(item.id)));
   button.disabled = !enabled;
   button.addEventListener('click', () => {
    if (town.tomselect) town.tomselect.setValue(String(item.id));
    else { town.value = String(item.id); town.dispatchEvent(new Event('change', {bubbles: true})); }
   });
   tags.append(button);
  });
  tagStatus.textContent = popular.length
   ? 'Towns in ' + parent.name + '. Larger names have more voters. Click a name to select it.'
   : 'No towns with saved voters in this district yet. Type a town name above.';
 };
 const refreshMode = () => {
  const adding = town.value === 'new';
  newTown.disabled = !enabled || !adding;
  status.textContent = adding ? 'New town: ' + newTown.value + '. Added only when you save the voter.'
   : town.value ? 'Saved town selected.' : 'Type to find a saved town. Add a town appears only when no matching name is found.';
  refreshTags();
 };
 const renderDistricts = () => {
  const parent = catalog.regions.find(item => item.name === region.value);
  render(district, catalog.districts.filter(item => parent && String(item.region_id) === String(parent.id)).map(item => ({value: item.name, text: item.name})), district.value);
 };
 const renderTowns = () => {
  const rows = towns().map(item => ({value: String(item.id), text: item.name}));
  if (town.value === 'new' && newTown.value) rows.push({value: 'new', text: newTown.value});
  render(town, rows, town.value); refreshMode();
 };
 region.addEventListener('change', () => {
  district.value = ''; town.value = ''; newTown.value = '';
  renderDistricts(); renderTowns();
 });
 district.addEventListener('change', () => { town.value = ''; newTown.value = ''; renderTowns(); });
 town.addEventListener('change', refreshMode);
 renderDistricts(); renderTowns();
 if (typeof TomSelect !== 'undefined') {
  [region, district, town].forEach(select => {
   const settings = {
    plugins: ['dropdown_input'], create: false, maxItems: 1,
    allowEmptyOption: true, searchField: ['text'], maxOptions: 200, closeAfterSelect: true
   };
   if (select === town) {
    settings.score = query => option => option.value === '' ? 0 : (matches(option.text, query) ? 1 : 0);
    settings.createFilter = input => {
     const name = normalize(input);
     return enabled && name.length > 0 && name.length <= 180 && !towns().some(item => matches(item.name, name));
    };
    settings.create = (input, callback) => {
     const name = normalize(input);
     newTown.value = name;
     if (town.tomselect.options.new) town.tomselect.removeOption('new', true);
     callback({value: 'new', text: name});
    };
    settings.render = {option_create: (data, escape) => '<div class="create">Add town &quot;' + escape(normalize(data.input)) + '&quot;</div>'};
   }
   const control = new TomSelect(select, settings);
   control.control_input.setAttribute('placeholder', 'Type to search...');
   control.control_input.setAttribute('aria-label', 'Search ' + (select.labels[0]?.textContent || 'options'));
   if (!enabled) control.disable();
  });
 }
 refreshMode();
});
document.querySelectorAll('[data-residential-filters]').forEach(form => {
 const region = form.querySelector('[name="residential_region_filter"]');
 const district = form.querySelector('[name="residential_district_filter"]');
 if (!region || !district) return;
 const districts = JSON.parse(form.dataset.residentialFilters);
 const town = form.querySelector('[name="residential_town_filter"]');
 const towns = JSON.parse(form.dataset.residentialTowns || '[]');
 const refreshTowns = () => {
  if (!town) return;
  town.replaceChildren(new Option('All saved towns', ''));
  towns.filter(item => (!region.value || String(item.residential_region_id) === region.value) && (!district.value || String(item.district_id) === district.value))
   .forEach(item => { const context = [item.district_name, item.region_name].filter(Boolean).join(' / '); town.add(new Option(item.name + ' / ' + (context || 'Location not recorded'), item.id)); });
 };
 region.addEventListener('change', () => {
  district.replaceChildren(new Option('All residential districts', ''));
  districts.filter(item => !region.value || String(item.region_id) === region.value)
   .forEach(item => district.add(new Option(item.name + ' / ' + item.region_name, item.id)));
  refreshTowns();
 });
 district.addEventListener('change', refreshTowns);
});

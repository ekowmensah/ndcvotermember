'use strict';
document.querySelectorAll('[data-location-picker]').forEach(picker => {
 const catalog = JSON.parse(picker.dataset.locationCatalog);
 const keys = ['region', 'constituency', 'polling_station'];
 const fields = Object.fromEntries(keys.map(key => [key, picker.querySelector(`[data-location="${key}"]`)]));
 const rebuild = (key, rows, value) => {
  fields[key].replaceChildren(new Option('Not assigned', ''));
  rows.forEach(row => fields[key].add(new Option((row.station_code ? row.station_code + ' · ' : '') + (['region', 'constituency'].includes(key) && row.shortname ? row.shortname + ' · ' : '') + row.name, row.id)));
  fields[key].value = value || '';
  if (fields[key].selectedIndex < 0) fields[key].value = '';
 };
 const refresh = changed => {
  const previous = Object.fromEntries(keys.map(key => [key, fields[key].value]));
  if (changed === 'region') previous.constituency = previous.polling_station = '';
  if (changed === 'constituency') previous.polling_station = '';
  rebuild('constituency', catalog.constituencies.filter(row => String(row.region_id) === fields.region.value), previous.constituency);
  const stations = catalog.polling_stations.filter(row => String(row.constituency_id) === fields.constituency.value);

  // All constituency stations remain available, including those with no branch.
  rebuild('polling_station', stations, previous.polling_station);
  const station = stations.find(row => String(row.id) === fields.polling_station.value);
  const branch = station && catalog.electoral_areas.find(row => String(row.id) === String(station.electoral_area_id));
  picker.querySelector('[data-branch-display]').value = branch ? branch.name : 'Branch not assigned';
 };
 keys.forEach(key => fields[key].addEventListener('change', () => refresh(key)));
 refresh(null);
});

document.querySelectorAll('[data-location-filters]').forEach(form => {
 const region = form.querySelector('[name="region_filter"]');
 const constituency = form.querySelector('[name="constituency_filter"]');
 if (!region || !constituency) return;
 const rows = JSON.parse(form.dataset.constituencies);
 region.addEventListener('change', () => {
  constituency.replaceChildren(new Option('All constituencies', ''));
  rows.filter(row => !region.value || String(row.region_id) === region.value)
   .forEach(row => constituency.add(new Option(row.shortname + ' · ' + row.name, row.id)));
 });
});

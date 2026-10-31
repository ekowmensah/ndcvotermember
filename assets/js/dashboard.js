'use strict';
document.querySelectorAll('[data-dashboard-filters]').forEach(form => {
 const region = form.querySelector('[name="residential_region_filter"]');
 const district = form.querySelector('[name="residential_district_filter"]');
 const districts = JSON.parse(form.dataset.districts);
 region.addEventListener('change', () => {
  const options = [{value: '', text: 'All residential districts'}, ...districts
   .filter(item => !region.value || String(item.region_id) === region.value)
   .map(item => ({value: String(item.id), text: item.name + ' / ' + item.region_name}))];
  if (district.tomselect) {
   const control = district.tomselect;
   control.clear(true); control.clearOptions(); control.addOptions(options);
   control.setValue('', true); control.refreshOptions(false);
  } else {
   district.replaceChildren(...options.map(item => new Option(item.text, item.value)));
  }
 });
 if (typeof TomSelect !== 'undefined') {
  [region, district].forEach(select => {
   const disabled = select.matches(':disabled');
   const control = new TomSelect(select, {
    plugins: ['dropdown_input'], create: false, maxItems: 1,
    allowEmptyOption: true, searchField: ['text'], maxOptions: 200, closeAfterSelect: true
   });
   control.control_input.setAttribute('placeholder', 'Type to search...');
   control.control_input.setAttribute('aria-label', 'Search ' + select.labels[0].textContent);
   if (disabled) control.disable();
  });
 }
});
document.querySelectorAll('[data-dashboard-print]').forEach(button => button.addEventListener('click', () => window.print()));

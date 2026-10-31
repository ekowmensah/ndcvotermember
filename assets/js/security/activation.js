'use strict';
const activationField = document.querySelector('[data-activation-token]');
if (activationField) {
 const token = new URLSearchParams(location.hash.slice(1)).get('token');
 if (token && /^[a-f0-9]{64}$/.test(token)) activationField.value = token;
 if (location.hash) history.replaceState(null, '', location.pathname + location.search);
}

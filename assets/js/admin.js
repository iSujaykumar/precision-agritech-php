document.querySelectorAll('form').forEach(function (form) {
  form.addEventListener('submit', function (event) {
    var button = event.submitter;
    var message = button && button.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });
});
document.querySelectorAll('[data-print]').forEach(function (button) {
  button.addEventListener('click', function () {
    window.print();
  });
});
var toggle = document.querySelector('.admin-toggle');
var nav = document.querySelector('.admin-nav');
if (toggle && nav) {
  toggle.addEventListener('click', function () {
    var open = nav.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
}
var seed = document.querySelector('[data-seed-paise]');
var trayLive = document.querySelector('[data-tray-live]');
if (seed && trayLive) {
  var showTray = function () {
    var paise = parseInt(seed.value || '0', 10);
    if (!paise || paise < 1) {
      trayLive.textContent = 'Type the rate per seedling in paise. 425 means Rs 4.25, and the tray of 150 is Rs 637.50.';
      return;
    }
    var exact = paise * 150;
    var rupees = Math.floor((exact + 50) / 100);
    var whole = Math.floor(exact / 100);
    var rest = exact % 100;
    trayLive.textContent = 'Price per tray (150 seedlings): Rs ' + whole.toLocaleString('en-IN') + (rest ? '.' + String(rest).padStart(2, '0') : '') + '. The shop price saves as Rs ' + rupees.toLocaleString('en-IN') + '.';
  };
  seed.addEventListener('input', showTray);
  showTray();
}

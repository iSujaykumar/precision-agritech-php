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

document.querySelectorAll('.step[data-step]').forEach(function (button) {
  button.addEventListener('click', function () {
    var cell = button.parentElement;
    if (!cell) return;
    var input = cell.querySelector('input[type="number"]');
    if (!input) return;
    var current = parseInt(input.value || '0', 10);
    if (isNaN(current)) current = 0;
    var next = button.getAttribute('data-step') === 'minus' ? current - 1 : current + 1;
    if (next < 0) next = 0;
    input.value = String(next);
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });
});
var dirty = false;
document.querySelectorAll('form[method="post"]').forEach(function (form) {
  form.addEventListener('input', function () { dirty = true; });
  form.addEventListener('submit', function (event) {
    if (event.defaultPrevented) return;
    dirty = false;
    var buttons = form.querySelectorAll('[type="submit"]');
    window.setTimeout(function () {
      buttons.forEach(function (button) { button.disabled = true; });
    }, 0);
  });
});
window.addEventListener('beforeunload', function (event) {
  if (!dirty) return;
  event.preventDefault();
  event.returnValue = '';
});
document.querySelectorAll('.admin-group').forEach(function (group) {
  group.addEventListener('toggle', function () {
    if (!group.open) return;
    document.querySelectorAll('.admin-group').forEach(function (other) {
      if (other !== group) other.open = false;
    });
  });
});
var finder = document.querySelector('[data-finder]');
var drop = document.querySelector('[data-finder-drop]');
var finderData = document.getElementById('desk-finder-data');
if (finder && drop && finderData) {
  var jobs = [];
  try { jobs = JSON.parse(finderData.textContent || '[]'); } catch (err) { jobs = []; }
  function paint() {
    var q = finder.value.toLowerCase().trim();
    drop.innerHTML = '';
    if (q === '') { drop.hidden = true; return; }
    var hits = jobs.filter(function (job) { return String(job[0]).toLowerCase().indexOf(q) !== -1; }).slice(0, 8);
    if (!hits.length) { drop.hidden = true; return; }
    hits.forEach(function (job) {
      var link = document.createElement('a');
      link.href = job[1];
      link.textContent = job[0];
      drop.appendChild(link);
    });
    drop.hidden = false;
  }
  finder.addEventListener('input', paint);
  document.addEventListener('pointerdown', function (event) {
    if (!drop.contains(event.target) && event.target !== finder) drop.hidden = true;
  });
}
var settingsSearch = document.querySelector('[data-settings-search]');
if (settingsSearch) {
  settingsSearch.addEventListener('input', function () {
    var q = settingsSearch.value.toLowerCase().trim();
    document.querySelectorAll('[data-setting]').forEach(function (block) {
      var hay = (block.getAttribute('data-setting') || '') + ' ' + (block.textContent || '');
      block.hidden = q !== '' && hay.toLowerCase().indexOf(q) === -1;
    });
  });
}

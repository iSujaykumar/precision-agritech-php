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

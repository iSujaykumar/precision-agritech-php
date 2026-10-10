(function () {
  var button = document.querySelector("[data-resend]");
  if (!button) return;
  var left = parseInt(button.getAttribute("data-resend") || "0", 10);
  if (isNaN(left) || left < 0) left = 0;
  var label = button.getAttribute("data-label") || "Resend code";

  function paint() {
    if (left > 0) {
      button.disabled = true;
      button.textContent = "Resend code (" + left + "s)";
      return;
    }
    button.disabled = false;
    button.textContent = label;
  }

  paint();
  if (left < 1) return;
  var timer = window.setInterval(function () {
    left -= 1;
    paint();
    if (left < 1) window.clearInterval(timer);
  }, 1000);
})();

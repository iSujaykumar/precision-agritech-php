(function () {
  var form = document.getElementById("checkout-form");
  var button = document.getElementById("place-order");
  if (!form || !button) return;
  form.addEventListener("submit", function () {
    button.disabled = true;
    button.textContent = "Placing order...";
  });
  window.addEventListener("pageshow", function () {
    button.disabled = false;
    button.textContent = "Place order";
  });
})();

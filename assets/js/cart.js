(function () {
  document.querySelectorAll("[data-cart-row]").forEach(function (form) {
    var qty = form.querySelector("[data-qty]");
    if (!qty) return;
    var timer = null;
    form.querySelectorAll("[data-step]").forEach(function (button) {
      button.addEventListener("click", function () {
        var current = parseInt(qty.value, 10);
        if (isNaN(current) || current < 1) current = 1;
        var max = parseInt(qty.getAttribute("max") || "50", 10);
        var next = button.getAttribute("data-step") === "minus" ? current - 1 : current + 1;
        if (next < 1) next = 1;
        if (next > max) next = max;
        qty.value = String(next);
        window.clearTimeout(timer);
        timer = window.setTimeout(function () { form.submit(); }, 350);
      });
    });
  });
})();

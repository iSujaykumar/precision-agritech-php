(function () {
  document.querySelectorAll("[data-copy]").forEach(function (button) {
    button.addEventListener("click", function () {
      var text = button.getAttribute("data-copy") || "";
      var done = function () { button.textContent = "Copied"; };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done);
        return;
      }
      var field = document.createElement("textarea");
      field.value = text;
      document.body.appendChild(field);
      field.select();
      try { document.execCommand("copy"); done(); } catch (err) {}
      field.remove();
    });
  });
  document.querySelectorAll("[data-print]").forEach(function (button) {
    button.addEventListener("click", function () { window.print(); });
  });
})();

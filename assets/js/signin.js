(function () {
  var form = document.getElementById("signin");
  if (!form) return;
  function sync() {
    var mode = "email";
    var chosen = form.querySelector('input[name="mode"]:checked');
    if (chosen) mode = chosen.value;
    form.querySelector(".field-email").hidden = mode !== "email";
    form.querySelector(".field-mobile").hidden = mode === "email";
    form.querySelector(".field-password").hidden = mode === "otp";
  }
  form.addEventListener("change", sync);
  sync();
})();

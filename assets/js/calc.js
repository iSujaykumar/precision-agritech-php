(function () {
  function money(paise) {
    var sign = paise < 0 ? "-" : "";
    paise = Math.abs(paise);
    var rupees = Math.floor(paise / 100);
    var rest = paise % 100;
    var text = rupees.toLocaleString("en-IN");
    if (rest) {
      text += "." + String(rest).padStart(2, "0");
    }
    return sign + "Rs " + text;
  }
  function render(form) {
    var qty = parseInt(form.querySelector("[data-calc-qty]").value, 10);
    if (!qty || qty < 1) qty = 1;
    var plant = form.querySelector("[data-calc-plant]");
    var paise = plant ? parseInt(plant.value, 10) : parseInt(form.getAttribute("data-paise") || "0", 10);
    var tray = 150;
    if (plant && plant.selectedOptions[0]) {
      tray = parseInt(plant.selectedOptions[0].getAttribute("data-tray") || "150", 10);
    } else if (form.getAttribute("data-tray")) {
      tray = parseInt(form.getAttribute("data-tray"), 10);
    }
    if (!tray || tray < 1) tray = 150;
    var trays = Math.ceil(qty / tray);
    var billed = trays * tray;
    var total = paise * billed;
    var minInr = parseInt(form.getAttribute("data-min-inr") || "5000", 10);
    var minSeed = parseInt(form.getAttribute("data-min-seed") || "5000", 10);
    var percent = parseInt(form.getAttribute("data-percent") || "10", 10);
    var basis = form.getAttribute("data-basis") || "rupees";
    var on = form.getAttribute("data-on") === "1";
    var rupees = Math.floor(total / 100);
    var reached = basis === "seedlings" ? billed >= minSeed : rupees >= minInr;
    var note = trays + " tray" + (trays === 1 ? "" : "s") + " (" + billed + " seedlings). ";
    if (billed !== qty) {
      note += "Rounded up to a full tray. ";
    }
    note += "Price per tray " + money(paise * tray) + ". Total " + money(total) + ". ";
    if (!on) {
      note += "The bulk discount is off.";
    } else if (reached) {
      var off = Math.floor((total * percent + 50) / 100);
      note += percent + "% bulk discount would apply (" + money(off) + " off the plants). Packing and transport are extra.";
    } else {
      var shortInr = Math.max(0, minInr - rupees);
      var shortSeed = Math.max(0, minSeed - billed);
      note += "Add Rs " + shortInr.toLocaleString("en-IN") + " more (or " + shortSeed.toLocaleString("en-IN") + " more seedlings) to get " + percent + "% off.";
    }
    var out = form.querySelector("[data-calc-out]");
    if (out) out.textContent = note;
  }
  document.querySelectorAll("[data-calc]").forEach(function (form) {
    render(form);
    form.addEventListener("input", function () { render(form); });
    form.addEventListener("change", function () { render(form); });
  });
  document.querySelectorAll("form").forEach(function (form) {
    form.addEventListener("submit", function () {
      var button = form.querySelector("[type=submit]");
      if (button) button.disabled = true;
    });
  });
})();

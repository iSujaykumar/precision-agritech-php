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
  function chosen(form) {
    var plant = form.querySelector("[data-calc-plant]");
    var option = plant && plant.selectedOptions ? plant.selectedOptions[0] : null;
    var paise = option ? parseInt(option.getAttribute("data-paise") || option.value || "0", 10) : parseInt(form.getAttribute("data-paise") || "0", 10);
    var tray = option ? parseInt(option.getAttribute("data-tray") || "150", 10) : parseInt(form.getAttribute("data-tray") || "150", 10);
    if (!tray || tray < 1) tray = 150;
    return {
      paise: paise || 0,
      tray: tray,
      slug: option ? option.getAttribute("data-slug") || "" : "",
      offer: option ? option.getAttribute("data-offer") === "1" : false,
      name: option ? option.getAttribute("data-name") || option.textContent : ""
    };
  }
  function render(form) {
    var qtyInput = form.querySelector("[data-calc-qty]");
    var qty = qtyInput ? parseInt(qtyInput.value, 10) : 150;
    if (!qty || qty < 1) qty = 1;
    var pick = chosen(form);
    var trays = Math.ceil(qty / pick.tray);
    var billed = trays * pick.tray;
    var total = pick.paise * billed;
    var minInr = parseInt(form.getAttribute("data-min-inr") || "5000", 10);
    var minSeed = parseInt(form.getAttribute("data-min-seed") || "5000", 10);
    var percent = parseInt(form.getAttribute("data-percent") || "10", 10);
    var basis = form.getAttribute("data-basis") || "rupees";
    var on = form.getAttribute("data-on") === "1";
    if (pick.offer && form.getAttribute("data-offer-bulk") !== "1") on = false;
    var rupees = Math.floor(total / 100);
    var reached = basis === "seedlings" ? billed >= minSeed : rupees >= minInr;
    var note = trays + " tray" + (trays === 1 ? "" : "s") + " (" + billed + " seedlings). ";
    if (billed !== qty) note += "Rounded up to a full tray. ";
    note += "Price per seedling " + money(pick.paise) + ". Price per tray " + money(pick.paise * pick.tray) + ". Total " + money(total) + ". ";
    if (!on) {
      note += "The bulk discount is off for this plant.";
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
    var slug = form.querySelector("[data-calc-slug]");
    var trayField = form.querySelector("[data-calc-trays]");
    if (slug) slug.value = pick.slug;
    if (trayField) trayField.value = String(trays);
    var wa = form.querySelector("[data-calc-wa]");
    if (wa) {
      var base = wa.getAttribute("data-wa-base") || "";
      var text = "Hello Precision Agritech. Plant: " + pick.name + ". Seedlings: " + qty + ". Trays wanted: " + trays + ".";
      wa.href = base + "?text=" + encodeURIComponent(text);
    }
    form.setAttribute("data-trays", String(trays));
  }
  document.querySelectorAll("[data-calc]").forEach(function (form) {
    render(form);
    form.addEventListener("input", function () { render(form); });
    form.addEventListener("change", function () { render(form); });
    var search = form.querySelector("[data-calc-search]");
    var plant = form.querySelector("[data-calc-plant]");
    if (search && plant) {
      search.addEventListener("input", function () {
        var q = search.value.toLowerCase();
        Array.prototype.forEach.call(plant.options, function (option) {
          var show = q === "" || option.textContent.toLowerCase().indexOf(q) !== -1;
          option.hidden = !show;
          option.disabled = !show;
        });
        if (plant.selectedOptions[0] && plant.selectedOptions[0].disabled) {
          var next = Array.prototype.find.call(plant.options, function (option) { return !option.disabled; });
          if (next) plant.value = next.value;
        }
        render(form);
      });
    }
    form.addEventListener("submit", function (event) {
      render(form);
      if (!form.querySelector("[data-calc-slug]") || form.querySelector("[data-calc-slug]").value === "") {
        event.preventDefault();
      }
    });
  });
  var panel = document.getElementById("calc-panel");
  var lastFocus = null;
  function openPanel(opener) {
    if (!panel || panel.classList.contains("calc-page")) return;
    lastFocus = opener || document.activeElement;
    panel.hidden = false;
    document.querySelectorAll("[data-calc-open]").forEach(function (button) {
      button.setAttribute("aria-expanded", "true");
    });
    var field = panel.querySelector("[data-calc-search]") || panel.querySelector("input, select, button");
    if (field) field.focus();
  }
  function closePanel() {
    if (!panel || panel.classList.contains("calc-page")) return;
    panel.hidden = true;
    document.querySelectorAll("[data-calc-open]").forEach(function (button) {
      button.setAttribute("aria-expanded", "false");
    });
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  document.querySelectorAll("[data-calc-open]").forEach(function (button) {
    button.addEventListener("click", function () { openPanel(button); });
  });
  document.querySelectorAll("[data-calc-close]").forEach(function (button) {
    button.addEventListener("click", closePanel);
  });
  function focusables(root) {
    return Array.prototype.filter.call(root.querySelectorAll("a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled])"), function (node) {
      return !node.hidden && node.offsetParent !== null;
    });
  }
  document.addEventListener("keydown", function (event) {
    if (!panel || panel.hidden || panel.classList.contains("calc-page")) return;
    if (event.key === "Escape") {
      closePanel();
      return;
    }
    if (event.key !== "Tab") return;
    var items = focusables(panel);
    if (!items.length) return;
    var first = items[0];
    var last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
  document.addEventListener("pointerdown", function (event) {
    if (!panel || panel.hidden || panel.classList.contains("calc-page")) return;
    if (panel.contains(event.target)) return;
    if (event.target.closest && event.target.closest("[data-calc-open]")) return;
    closePanel();
  });
  document.querySelectorAll('form[method="post"]').forEach(function (form) {
    form.addEventListener("submit", function (event) {
      if (event.defaultPrevented) return;
      var button = form.querySelector("[type=submit]");
      if (button) window.setTimeout(function () { button.disabled = true; }, 0);
    });
  });
  if (window.visualViewport && panel && !panel.classList.contains("calc-page")) {
    var liftForKeyboard = function () {
      if (panel.hidden || window.matchMedia("(min-width: 701px)").matches) {
        panel.style.bottom = "";
        panel.style.maxHeight = "";
        return;
      }
      var inset = Math.max(0, window.innerHeight - window.visualViewport.height - window.visualViewport.offsetTop);
      if (inset > 40) {
        panel.style.bottom = Math.round(inset) + "px";
        panel.style.maxHeight = Math.max(160, Math.round(window.visualViewport.height - 8)) + "px";
      } else {
        panel.style.bottom = "";
        panel.style.maxHeight = "";
      }
    };
    window.visualViewport.addEventListener("resize", liftForKeyboard);
    window.visualViewport.addEventListener("scroll", liftForKeyboard);
  }
})();

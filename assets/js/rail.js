(function () {
  function amount(rail) {
    var card = rail.querySelector(".card:not([hidden])") || rail.querySelector(".card");
    if (!card) return Math.max(160, rail.clientWidth * 0.8);
    var styles = window.getComputedStyle(rail);
    var gap = parseFloat(styles.columnGap || styles.gap || "16");
    if (!gap || gap < 0) gap = 16;
    return card.getBoundingClientRect().width + gap;
  }

  document.querySelectorAll(".rail-wrap").forEach(function (wrap) {
    var rail = wrap.querySelector(".rail");
    var prev = wrap.querySelector(".rail-prev");
    var next = wrap.querySelector(".rail-next");
    if (!rail || !prev || !next) return;

    function sync() {
      var max = rail.scrollWidth - rail.clientWidth;
      var atStart = rail.scrollLeft <= 2;
      var atEnd = rail.scrollLeft >= max - 2;
      prev.hidden = atStart;
      next.hidden = atEnd || max <= 2;
      prev.disabled = atStart;
      next.disabled = atEnd || max <= 2;
    }

    function move(direction) {
      rail.scrollBy({ left: direction * amount(rail), behavior: "smooth" });
    }

    prev.addEventListener("click", function () { move(-1); });
    next.addEventListener("click", function () { move(1); });
    rail.addEventListener("scroll", sync, { passive: true });
    rail.addEventListener("keydown", function (event) {
      if (event.key === "ArrowRight") {
        move(1);
        event.preventDefault();
      } else if (event.key === "ArrowLeft") {
        move(-1);
        event.preventDefault();
      }
    });
    window.addEventListener("resize", sync);
    sync();
  });

  document.querySelectorAll("[data-browse-chips]").forEach(function (bar) {
    var section = bar.closest("section");
    if (!section) return;
    bar.querySelectorAll("[data-cat]").forEach(function (chip) {
      chip.addEventListener("click", function () {
        var cat = chip.getAttribute("data-cat");
        bar.querySelectorAll("[data-cat]").forEach(function (other) {
          other.setAttribute("aria-pressed", other === chip ? "true" : "false");
        });
        section.querySelectorAll(".rail .card").forEach(function (card) {
          var show = cat === "all" || card.getAttribute("data-cat") === cat;
          card.hidden = !show;
        });
        var rail = section.querySelector(".rail");
        if (rail) rail.scrollLeft = 0;
        window.dispatchEvent(new Event("resize"));
      });
    });
  });
})();

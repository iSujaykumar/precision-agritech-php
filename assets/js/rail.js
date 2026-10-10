(function () {
  function amount(rail) {
    var card = rail.querySelector(".card");
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
})();

(function () {
  var button = document.querySelector(".nav-toggle:not(.admin-toggle)");
  var nav = document.getElementById("site-nav");
  if (!button || !nav) return;
  function setOpen(open) {
    nav.classList.toggle("is-open", open);
    button.setAttribute("aria-expanded", open ? "true" : "false");
    button.textContent = open ? "Close" : "Menu";
    document.body.classList.toggle("nav-open", open);
  }
  button.addEventListener("click", function () {
    setOpen(!nav.classList.contains("is-open"));
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && nav.classList.contains("is-open")) {
      setOpen(false);
      button.focus();
    }
  });
})();

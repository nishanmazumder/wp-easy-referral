document.addEventListener("DOMContentLoaded", function () {
  var t = document.querySelectorAll("[data-wpref-tabs]");
  if (!t.length) {
    return;
  }
  t.forEach(function (t) {
    var a = t.querySelectorAll(".wpref-tab-btn"),
      e = t.querySelectorAll(".wpref-tab-panel");
    function i(t) {
      a.forEach(function (a) {
        var e = a.getAttribute("data-tab-target") === t;
        a.classList.toggle("is-active", e);
        a.setAttribute("aria-selected", e ? "true" : "false");
      });
      e.forEach(function (a) {
        a.classList.toggle("is-active", a.id === "wpref-panel-" + t);
      });
    }
    a.forEach(function (t) {
      t.addEventListener("click", function () {
        i(t.getAttribute("data-tab-target"));
      });
    });
  });
});

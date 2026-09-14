(function () {
  "use strict";

  /* Troque pelo WhatsApp comercial (DDI+DDD+número, só dígitos). */
  var WA = "5511999999999";

  var head = document.getElementById("topo");
  var hamb = document.querySelector(".hamb");
  var menu = document.getElementById("menu-m");
  if (hamb && menu && head) {
    hamb.addEventListener("click", function () {
      var open = head.classList.toggle("is-open");
      hamb.setAttribute("aria-expanded", open ? "true" : "false");
      menu.hidden = !open;
    });
    menu.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        head.classList.remove("is-open");
        hamb.setAttribute("aria-expanded", "false");
        menu.hidden = true;
      });
    });
  }

  var terms = {
    salao: { clients: "Clientes", "clients-l": "clientes", "client-l": "cliente", "appointments-l": "agendamentos", "appointment-l": "agendamento" },
    clinica: { clients: "Pacientes", "clients-l": "pacientes", "client-l": "paciente", "appointments-l": "consultas", "appointment-l": "consulta" },
    oficina: { clients: "Clientes", "clients-l": "clientes", "client-l": "cliente", "appointments-l": "ordens", "appointment-l": "ordem" },
    consultoria: { clients: "Clientes", "clients-l": "clientes", "client-l": "cliente", "appointments-l": "reuniões", "appointment-l": "reunião" }
  };

  document.querySelectorAll(".chip").forEach(function (chip) {
    chip.addEventListener("click", function () {
      document.querySelectorAll(".chip").forEach(function (c) {
        c.setAttribute("aria-pressed", c === chip ? "true" : "false");
      });
      var map = terms[chip.getAttribute("data-seg")] || terms.salao;
      document.querySelectorAll("[data-term]").forEach(function (el) {
        var key = el.getAttribute("data-term");
        if (map[key]) el.textContent = map[key];
      });
    });
  });

  var tabs = document.querySelectorAll(".tour-nav [role='tab']");
  var panels = document.querySelectorAll("[data-panel]");
  tabs.forEach(function (tab) {
    tab.addEventListener("click", function () {
      tabs.forEach(function (t) { t.setAttribute("aria-selected", t === tab ? "true" : "false"); });
      var id = tab.getAttribute("aria-controls");
      panels.forEach(function (p) { p.hidden = p.id !== id; });
    });
  });

  document.querySelectorAll(".faq-item").forEach(function (item) {
    var btn = item.querySelector("button");
    if (!btn) return;
    btn.addEventListener("click", function () {
      var open = item.classList.contains("open");
      document.querySelectorAll(".faq-item").forEach(function (other) {
        other.classList.remove("open");
        var b = other.querySelector("button");
        var mark = b && b.querySelector("span:last-child");
        if (b) b.setAttribute("aria-expanded", "false");
        if (mark) mark.textContent = "+";
      });
      if (!open) {
        item.classList.add("open");
        btn.setAttribute("aria-expanded", "true");
        var s = btn.querySelector("span:last-child");
        if (s) s.textContent = "−";
      }
    });
  });

  var docs = document.querySelectorAll("[data-doc]");
  var dpanels = document.querySelectorAll("[data-doc-panel]");
  docs.forEach(function (btn) {
    btn.addEventListener("click", function () {
      docs.forEach(function (b) { b.setAttribute("aria-pressed", b === btn ? "true" : "false"); });
      var id = btn.getAttribute("data-doc");
      dpanels.forEach(function (p) { p.hidden = p.getAttribute("data-doc-panel") !== id; });
    });
  });

  var ticking = false;
  window.addEventListener("scroll", function () {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(function () {
      if (head) head.classList.toggle("is-scrolled", window.scrollY > 8);
      ticking = false;
    });
  }, { passive: true });

  var form = document.getElementById("form-teste");
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var empresa = (document.getElementById("empresa").value || "").trim();
      var whats = (document.getElementById("whats").value || "").trim();
      var msg = "Olá, quero o teste grátis de 30 dias do FirestepCRM.";
      if (empresa) msg += " Empresa: " + empresa + ".";
      if (whats) msg += " Meu WhatsApp: " + whats + ".";
      window.location.href = "https://wa.me/" + WA + "?text=" + encodeURIComponent(msg);
    });
  }
})();

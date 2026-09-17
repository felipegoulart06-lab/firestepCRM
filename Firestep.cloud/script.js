(function () {
  "use strict";

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

  document.querySelectorAll("[data-interesse]").forEach(function (link) {
    link.addEventListener("click", function () {
      var sel = document.getElementById("interesse");
      var val = link.getAttribute("data-interesse");
      if (sel && val) sel.value = val;
    });
  });

  var form = document.getElementById("form-teste");
  if (form) {
    var HOOK = "https://crm.firestep.cloud/api/webhooks/aba15e1aaaa7f4b382a5e79f156e886c";
    var statusEl = document.getElementById("form-status");
    var submitBtn = document.getElementById("form-enviar");
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var interesseEl = document.getElementById("interesse");
      var interesse = interesseEl ? interesseEl.value : "completo";
      var interesseLabel = {
        completo: "Site + FirestepCRM",
        templates: "Website / template",
        crm: "FirestepCRM",
        sistema: "Sistema",
        catalogo: "Catálogo",
        bio: "Link na bio",
        outro: "Ainda não sei"
      }[interesse] || interesse;
      var nome = (document.getElementById("nome").value || "").trim();
      var email = (document.getElementById("email").value || "").trim();
      var whats = (document.getElementById("whats").value || "").trim();
      var empresa = (document.getElementById("empresa").value || "").trim();
      var segmento = ((document.getElementById("segmento") || {}).value || "").trim();
      var mensagem = ((document.getElementById("mensagem") || {}).value || "").trim();
      if (!nome || !email || !whats) {
        showStatus("Preencha nome, e-mail e WhatsApp.", false);
        return;
      }
      var msg = "Interesse Firestep: " + interesseLabel + ".";
      if (empresa) msg += " Empresa: " + empresa + ".";
      if (segmento) msg += " Segmento: " + segmento + ".";
      msg += " WhatsApp: " + whats + ".";
      if (mensagem) msg += " Pedido: " + mensagem;
      var payload = {
        type: "request",
        name: nome,
        email: email,
        phone: whats,
        whatsapp: whats,
        company: empresa,
        segment: segmento,
        interest: interesse,
        source: "Website",
        utm_source: "firestep.cloud",
        utm_medium: "landing",
        utm_campaign: "solucao-completa",
        message: msg
      };
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Enviando…";
      }
      fetch(HOOK, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      }).then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, data: data };
        }).catch(function () {
          return { ok: res.ok, data: {} };
        });
      }).then(function (out) {
        if (out.ok && out.data && out.data.ok) {
          form.reset();
          showStatus("Recebemos sua solicitação. Em breve falamos com você.", true);
        } else {
          showStatus((out.data && out.data.error) || "Não foi possível enviar. Tente de novo.", false);
        }
      }).catch(function () {
        showStatus("Falha de rede. Confira a conexão e tente de novo.", false);
      }).finally(function () {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = "Enviar solicitação";
        }
      });
    });
    function showStatus(text, ok) {
      if (!statusEl) return;
      statusEl.hidden = false;
      statusEl.textContent = text;
      statusEl.classList.toggle("is-ok", !!ok);
      statusEl.classList.toggle("is-err", !ok);
    }
  }
})();

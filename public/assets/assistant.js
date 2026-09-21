(function () {
  const FLOW = () => window.FS_ASSIST_FLOW;
  const csrf = () => document.querySelector('meta[name="csrf"]')?.getAttribute("content") || "";
  const iconChat =
    '<svg class="ico" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>';
  const iconX =
    '<svg class="ico" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>';

  function md(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>");
  }

  function root() {
    return document.getElementById("fs-assist");
  }

  function setBadge(n) {
    const b = root()?.querySelector(".fs-assist-badge");
    if (!b) return;
    if (!n) {
      b.hidden = true;
      b.textContent = "";
      return;
    }
    b.hidden = false;
    b.textContent = n > 9 ? "9+" : String(n);
  }

  function appendMsg(kind, text) {
    const log = root()?.querySelector(".fs-assist-log");
    if (!log) return;
    const el = document.createElement("div");
    el.className = "fs-assist-msg " + kind;
    el.innerHTML = md(text);
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
  }

  function showChoices(items) {
    const box = root()?.querySelector(".fs-assist-choices");
    const form = root()?.querySelector(".fs-assist-form");
    if (!box) return;
    box.innerHTML = "";
    box.hidden = !items || !items.length;
    if (form) form.hidden = true;
    (items || []).forEach(function (item) {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "btn btn-ghost";
      btn.textContent = item.label;
      btn.addEventListener("click", function () {
        appendMsg("me", item.label);
        go(item.next);
      });
      box.appendChild(btn);
    });
  }

  function showInput(cfg) {
    const box = root()?.querySelector(".fs-assist-choices");
    const form = root()?.querySelector(".fs-assist-form");
    if (box) {
      box.hidden = true;
      box.innerHTML = "";
    }
    if (!form) return;
    form.hidden = false;
    const input = form.querySelector("input");
    const btn = form.querySelector("button");
    if (input) {
      input.placeholder = cfg.placeholder || "Digite sua dúvida...";
      input.value = "";
      input.focus();
    }
    if (btn) btn.textContent = cfg.button || "Enviar";
    form.dataset.next = cfg.next || "encerrar";
  }

  function go(id) {
    const flow = FLOW();
    const g = flow && flow.groups ? flow.groups[id] : null;
    if (!g) return;
    root().dataset.group = id;
    (g.texts || []).forEach(function (t) {
      appendMsg("bot", t);
    });
    if (g.input) showInput(g.input);
    else showChoices(g.choices || []);
  }

  function renderInbox(threads) {
    const log = root()?.querySelector(".fs-assist-log");
    if (!log) return;
    log.innerHTML = "";
    if (!threads || !threads.length) {
      appendMsg("sys", "Nenhuma dúvida enviada ainda. Use o menu do assistente e a opção “Minha dúvida não está aqui”.");
      return;
    }
    threads.forEach(function (t) {
      appendMsg("me", t.question || "");
      if (t.answer) appendMsg("bot", "Admin Master:\n" + t.answer);
      else appendMsg("sys", "Aguardando resposta do Admin Master.");
    });
  }

  async function loadInbox() {
    try {
      const res = await fetch("/app/assistente/conversas", {
        credentials: "same-origin",
        headers: { Accept: "application/json" },
      });
      if (!res.ok) return;
      const data = await res.json();
      setBadge(data.answered || 0);
      if (root()?.dataset.tab === "inbox") renderInbox(data.threads || []);
    } catch (_) {}
  }

  async function sendQuestion(text) {
    const body = new URLSearchParams();
    body.set("_csrf", csrf());
    body.set("question", text);
    const res = await fetch("/app/assistente/duvida", {
      method: "POST",
      credentials: "same-origin",
      headers: { Accept: "application/json", "Content-Type": "application/x-www-form-urlencoded" },
      body,
    });
    const data = await res.json().catch(function () {
      return { ok: false };
    });
    return data;
  }

  function setOpen(on) {
    const el = root();
    if (!el) return;
    el.classList.toggle("is-open", on);
    const panel = el.querySelector(".fs-assist-panel");
    if (panel) panel.hidden = !on;
    const fab = el.querySelector(".fs-assist-fab");
    if (fab) fab.setAttribute("aria-expanded", on ? "true" : "false");
    if (on && el.dataset.tab !== "inbox" && !el.querySelector(".fs-assist-log")?.childElementCount) {
      go(FLOW().start || "menu");
    }
  }

  function mount() {
    if (!FLOW() || !FLOW().groups) return;
    if (!document.querySelector(".wrap .sidebar") || location.pathname.startsWith("/master")) return;
    if (root()) return;
    const wrap = document.createElement("div");
    wrap.id = "fs-assist";
    wrap.className = "fs-assist";
    wrap.innerHTML =
      '<button type="button" class="fs-assist-fab" aria-label="Abrir assistente" aria-expanded="false">' +
      '<span class="fs-assist-ping" aria-hidden="true"></span>' +
      '<span class="fs-assist-badge" hidden></span>' +
      iconChat +
      "</button>" +
      '<div class="fs-assist-panel card" hidden role="dialog" aria-label="Assistente do sistema">' +
      '<div class="fs-assist-head">' +
      "<div><b>Assistente</b><span>FirestepCRM</span></div>" +
      '<div class="fs-assist-tabs">' +
      '<button type="button" data-tab="guide" class="is-on">Guia</button>' +
      '<button type="button" data-tab="inbox">Dúvidas</button>' +
      "</div>" +
      '<button type="button" class="fs-assist-x" aria-label="Fechar">' +
      iconX +
      "</button></div>" +
      '<div class="fs-assist-log"></div>' +
      '<div class="fs-assist-choices"></div>' +
      '<form class="fs-assist-form" hidden>' +
      '<input class="input" name="question" maxlength="2000" autocomplete="off">' +
      '<button class="btn btn-primary" type="submit">Enviar</button>' +
      "</form></div>";
    document.body.appendChild(wrap);

    wrap.querySelector(".fs-assist-fab").addEventListener("click", function (e) {
      e.stopPropagation();
      setOpen(!wrap.classList.contains("is-open"));
    });
    wrap.querySelector(".fs-assist-x").addEventListener("click", function () {
      setOpen(false);
    });
    wrap.querySelectorAll(".fs-assist-tabs button").forEach(function (btn) {
      btn.addEventListener("click", function () {
        wrap.querySelectorAll(".fs-assist-tabs button").forEach(function (b) {
          b.classList.toggle("is-on", b === btn);
        });
        wrap.dataset.tab = btn.dataset.tab;
        const log = wrap.querySelector(".fs-assist-log");
        if (btn.dataset.tab === "inbox") {
          wrap.querySelector(".fs-assist-choices").hidden = true;
          wrap.querySelector(".fs-assist-form").hidden = true;
          setBadge(0);
          loadInbox();
        } else {
          if (log) log.innerHTML = "";
          go(FLOW().start || "menu");
        }
      });
    });
    wrap.querySelector(".fs-assist-form").addEventListener("submit", function (e) {
      e.preventDefault();
      const input = wrap.querySelector(".fs-assist-form input");
      const text = (input?.value || "").trim();
      if (!text) return;
      appendMsg("me", text);
      input.value = "";
      wrap.querySelector(".fs-assist-form").hidden = true;
      sendQuestion(text).then(function (data) {
        if (!data || !data.ok) {
          appendMsg("sys", data?.error || "Não foi possível enviar. Tente de novo.");
          showInput({ placeholder: "Digite sua dúvida...", button: "Enviar", next: "encerrar" });
          return;
        }
        go("encerrar");
      });
    });
    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      if (!wrap.classList.contains("is-open")) return;
      if (document.querySelector(".overlay:not([hidden]), .fx-overlay:not([hidden]), .token-modal:not([hidden])")) return;
      setOpen(false);
    });
    loadInbox();
    setInterval(loadInbox, 25000);
  }

  window.fsAssistMount = mount;
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", mount);
  else mount();
})();

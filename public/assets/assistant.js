(function () {
  const FLOW = () => window.FS_ASSIST_FLOW;
  const csrf = () => document.querySelector("meta[name=csrf]")?.getAttribute("content") || "";
  const photo = '<img src="/assets/priscila.jpg" alt="Priscila" width="58" height="58">';
  const iconX =
    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>';
  const iconSend =
    '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.4 20.6 21 12 3.4 3.4 3 10.2 15 12 3 13.8z"/></svg>';

  let playId = 0;

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

  function logEl() {
    return root()?.querySelector(".fs-assist-log");
  }

  function wait(ms) {
    return new Promise(function (resolve) {
      setTimeout(resolve, ms);
    });
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

  function scrollLog() {
    const log = logEl();
    if (log) log.scrollTop = log.scrollHeight;
  }

  function appendMsg(kind, text, animate) {
    const log = logEl();
    if (!log) return null;
    const row = document.createElement("div");
    row.className = "fs-assist-row " + kind;
    const el = document.createElement("div");
    el.className = "fs-assist-msg " + kind + (animate === false ? "" : " is-in");
    el.innerHTML = md(text);
    row.appendChild(el);
    log.appendChild(row);
    scrollLog();
    return el;
  }

  function showTyping() {
    const log = logEl();
    if (!log) return null;
    const row = document.createElement("div");
    row.className = "fs-assist-row bot fs-assist-typing-row";
    row.innerHTML = '<div class="fs-assist-msg bot fs-assist-typing" aria-label="Digitando"><i></i><i></i><i></i></div>';
    log.appendChild(row);
    scrollLog();
    return row;
  }

  function hideTyping() {
    root()?.querySelectorAll(".fs-assist-typing-row").forEach(function (el) {
      el.remove();
    });
  }

  function typeDelay(text) {
    const n = String(text || "").length;
    return Math.min(1400, Math.max(420, 280 + n * 12));
  }

  const CHOICE_MAX = 5;

  function addChoiceBtn(box, item, i, isNav) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.textContent = item.label;
    btn.style.animationDelay = i * 45 + "ms";
    if (isNav) btn.className = "is-nav";
    btn.addEventListener("click", function () {
      if (root()?.dataset.busy === "1") return;
      if (item.pageDelta) {
        box._page = Math.max(0, (box._page || 0) + item.pageDelta);
        renderChoicePage();
        return;
      }
      appendMsg("me", item.label);
      box.hidden = true;
      box.innerHTML = "";
      go(item.next);
    });
    box.appendChild(btn);
  }

  function choiceSlice(all, page) {
    let i = 0;
    let p = 0;
    while (i < all.length) {
      const prev = p > 0;
      const left = all.length - i;
      const slots = CHOICE_MAX - (prev ? 1 : 0);
      let take = left;
      if (left > slots) take = Math.max(1, slots - 1);
      if (p === page) {
        return { start: i, take: take, prev: prev, next: i + take < all.length };
      }
      i += take;
      p += 1;
    }
    return { start: 0, take: Math.min(CHOICE_MAX, all.length), prev: false, next: false };
  }

  function renderChoicePage() {
    const box = root()?.querySelector(".fs-assist-choices");
    if (!box) return;
    const all = box._all || [];
    box.innerHTML = "";
    if (all.length <= CHOICE_MAX) {
      all.forEach(function (item, i) {
        addChoiceBtn(box, item, i, false);
      });
      return;
    }
    const slice = choiceSlice(all, box._page || 0);
    let i = 0;
    if (slice.prev) {
      addChoiceBtn(box, { label: "Opções anteriores", pageDelta: -1 }, i++, true);
    }
    all.slice(slice.start, slice.start + slice.take).forEach(function (item) {
      addChoiceBtn(box, item, i++, false);
    });
    if (slice.next) {
      addChoiceBtn(box, { label: "Mais opções", pageDelta: 1 }, i++, true);
    }
  }

  function showChoices(items) {
    const box = root()?.querySelector(".fs-assist-choices");
    const form = root()?.querySelector(".fs-assist-form");
    if (!box) return;
    box.innerHTML = "";
    const all = items || [];
    box.hidden = !all.length;
    if (form) form.hidden = true;
    if (!all.length) return;
    box._all = all;
    box._page = 0;
    renderChoicePage();
  }

  function maskWa(v) {
    const d = String(v || "").replace(/\D/g, "").slice(0, 11);
    let o = "";
    if (d.length > 0) o = "(" + d.slice(0, Math.min(2, d.length));
    if (d.length >= 2) o += ") ";
    if (d.length >= 3) o += d.slice(2, 3);
    if (d.length >= 4) o += " " + d.slice(3, 7);
    if (d.length >= 7) o += "-" + d.slice(7, 11);
    return o;
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
    form.dataset.kind = cfg.kind || "";
    const input = form.querySelector("input");
    if (input) {
      input.placeholder = cfg.placeholder || "Escreva aqui…";
      input.value = "";
      input.maxLength = cfg.kind === "whatsapp" ? 16 : 2000;
      input.setAttribute("inputmode", cfg.kind === "whatsapp" ? "numeric" : "text");
      input.setAttribute("autocomplete", cfg.kind === "whatsapp" ? "tel" : "off");
      setTimeout(function () {
        input.focus();
      }, 80);
    }
    form.dataset.next = cfg.next || "encerrar";
  }

  async function go(id) {
    const flow = FLOW();
    const g = flow && flow.groups ? flow.groups[id] : null;
    if (!g) return;
    const token = ++playId;
    const el = root();
    if (el) {
      el.dataset.group = id;
      el.dataset.busy = "1";
    }
    const box = el?.querySelector(".fs-assist-choices");
    const form = el?.querySelector(".fs-assist-form");
    if (box) {
      box.hidden = true;
      box.innerHTML = "";
    }
    if (form) form.hidden = true;
    const texts = g.texts || [];
    for (let i = 0; i < texts.length; i++) {
      if (token !== playId) return;
      const typing = showTyping();
      await wait(typeDelay(texts[i]));
      if (token !== playId) {
        typing?.remove();
        return;
      }
      hideTyping();
      appendMsg("bot", texts[i]);
      await wait(i === texts.length - 1 ? 120 : 220);
    }
    if (token !== playId) return;
    if (el) el.dataset.busy = "0";
    if (g.input) showInput(g.input);
    else showChoices(g.choices || []);
  }

  function renderInbox(threads) {
    const log = logEl();
    if (!log) return;
    log.innerHTML = "";
    if (!threads || !threads.length) {
      appendMsg("sys", "Quando você mandar uma dúvida, ela aparece aqui — e a resposta também.", false);
      return;
    }
    threads.slice().reverse().forEach(function (t) {
      appendMsg("me", t.question || "", false);
      if (t.answer) appendMsg("bot", t.answer, false);
      else appendMsg("sys", "Ainda sem resposta. Assim que o suporte responder, chega aqui.", false);
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

  async function sendHandoff(phone) {
    const body = new URLSearchParams();
    body.set("_csrf", csrf());
    body.set("whatsapp", phone);
    const res = await fetch("/app/assistente/atendimento", {
      method: "POST",
      credentials: "same-origin",
      headers: { Accept: "application/json", "Content-Type": "application/x-www-form-urlencoded" },
      body,
    });
    return res.json().catch(function () {
      return { ok: false };
    });
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
    return res.json().catch(function () {
      return { ok: false };
    });
  }

  function setOpen(on) {
    const el = root();
    if (!el) return;
    el.classList.toggle("is-open", on);
    const panel = el.querySelector(".fs-assist-panel");
    if (panel) panel.hidden = !on;
    const fab = el.querySelector(".fs-assist-fab");
    if (fab) fab.setAttribute("aria-expanded", on ? "true" : "false");
    if (on && el.dataset.tab !== "inbox" && !logEl()?.childElementCount) {
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
      '<button type="button" class="fs-assist-fab" aria-label="Falar com a Priscila" aria-expanded="false">' +
      '<span class="fs-assist-badge" hidden></span>' +
      photo +
      "</button>" +
      '<div class="fs-assist-panel" hidden role="dialog" aria-label="Conversa com a Priscila">' +
      '<div class="fs-assist-head">' +
      '<div class="fs-assist-ava">' + photo + "</div>" +
      '<div class="fs-assist-who"><b>Priscila</b><span>online agora</span></div>' +
      '<div class="fs-assist-tabs">' +
      '<button type="button" data-tab="guide" class="is-on">Chat</button>' +
      '<button type="button" data-tab="inbox">Dúvidas</button>' +
      "</div>" +
      '<button type="button" class="fs-assist-x" aria-label="Fechar">' + iconX + "</button>" +
      "</div>" +
      '<div class="fs-assist-log"></div>' +
      '<div class="fs-assist-choices"></div>' +
      '<form class="fs-assist-form" hidden>' +
      '<input class="input" name="question" maxlength="2000" autocomplete="off" placeholder="Escreva aqui…">' +
      '<button class="btn btn-primary" type="submit" aria-label="Enviar">' + iconSend + "</button>" +
      "</form></div>";
    document.body.appendChild(wrap);

    wrap.querySelector(".fs-assist-fab").addEventListener("click", function (e) {
      e.stopPropagation();
      setOpen(true);
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
        playId += 1;
        hideTyping();
        const log = wrap.querySelector(".fs-assist-log");
        if (btn.dataset.tab === "inbox") {
          wrap.querySelector(".fs-assist-choices").hidden = true;
          wrap.querySelector(".fs-assist-form").hidden = true;
          setBadge(0);
          loadInbox();
        } else if (log) {
          log.innerHTML = "";
          go(FLOW().start || "menu");
        }
      });
    });
    wrap.querySelector(".fs-assist-form input").addEventListener("input", function () {
      const form = wrap.querySelector(".fs-assist-form");
      if (form?.dataset.kind !== "whatsapp") return;
      this.value = maskWa(this.value);
    });
    wrap.querySelector(".fs-assist-form").addEventListener("submit", function (e) {
      e.preventDefault();
      if (wrap.dataset.busy === "1") return;
      const form = wrap.querySelector(".fs-assist-form");
      const input = form.querySelector("input");
      const kind = form.dataset.kind || "";
      let text = (input?.value || "").trim();
      if (kind === "whatsapp") {
        text = maskWa(text);
        input.value = text;
        if (!/^\(\d{2}\) \d \d{4}-\d{4}$/.test(text)) {
          appendMsg("sys", "Use o formato (00) 0 0000-0000.");
          showInput({ placeholder: "(00) 0 0000-0000", next: "atendimento_ok", kind: "whatsapp" });
          return;
        }
      }
      if (!text) return;
      appendMsg("me", text);
      input.value = "";
      form.hidden = true;
      wrap.dataset.busy = "1";
      const typing = showTyping();
      const req = kind === "whatsapp" ? sendHandoff(text) : sendQuestion(text);
      req.then(function (data) {
        typing?.remove();
        wrap.dataset.busy = "0";
        if (!data || !data.ok) {
          appendMsg("sys", data?.error || "Não deu para enviar. Tenta de novo.");
          if (kind === "whatsapp") {
            showInput({ placeholder: "(00) 0 0000-0000", next: "atendimento_ok", kind: "whatsapp" });
          } else {
            showInput({ placeholder: "Escreva aqui…", button: "Enviar", next: "encerrar" });
          }
          return;
        }
        go(kind === "whatsapp" ? "atendimento_ok" : "encerrar");
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

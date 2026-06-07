(function () {
  const root = document.getElementById("supportChatNotifications");

  if (!root) {
    return;
  }

  const wsAuthUrl = root.dataset.wsAuthUrl || "";
  const attendUrl = (root.dataset.attendUrl || "").replace(/\/$/, "");
  const badge = document.getElementById("supportChatNotificationBadge");
  const list = document.getElementById("supportChatNotificationList");
  const empty = document.getElementById("supportChatNotificationEmpty");
  const clearButton = document.getElementById("supportChatNotificationClear");
  const toggle = root.querySelector(".support-chat-notification-toggle");

  let socket = null;
  let authenticated = false;
  let reconnectTimer = null;
  let reconnectDelay = 1200;
  let count = 0;
  const notifications = [];

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function parseLocalDateTime(value) {
    const text = String(value || "").trim();
    if (!text) {
      return null;
    }

    if (/[zZ]|[+-]\d{2}:?\d{2}$/.test(text)) {
      return new Date(text.replace(" ", "T"));
    }

    const match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
    if (match) {
      return new Date(
        Number(match[1]),
        Number(match[2]) - 1,
        Number(match[3]),
        Number(match[4] || 0),
        Number(match[5] || 0),
        Number(match[6] || 0)
      );
    }

    return new Date(text.replace(" ", "T"));
  }

  function formatTime(value) {
    const date = value ? parseLocalDateTime(value) : new Date();

    if (!date || Number.isNaN(date.getTime())) {
      return "";
    }

    return new Intl.DateTimeFormat("pt-BR", {
      hour: "2-digit",
      minute: "2-digit",
    }).format(date);
  }

  async function fetchJson(url) {
    const response = await fetch(url, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });
    const payload = await response.json();

    if (!response.ok) {
      throw new Error(payload.message || "Falha ao autenticar notificações.");
    }

    return payload;
  }

  function render() {
    if (!badge || !list || !empty || !toggle) {
      return;
    }

    badge.hidden = count <= 0;
    badge.textContent = count > 99 ? "99+" : String(count);
    toggle.classList.toggle("has-new-message", count > 0);
    empty.hidden = notifications.length > 0;

    list.innerHTML = notifications.slice(0, 6).map((item) => {
      const href = item.chatId > 0 ? `${attendUrl}/${encodeURIComponent(item.chatId)}` : attendUrl;

      return `
        <a class="support-chat-notification-list-item" href="${href}">
          <span class="support-chat-notification-title">
            <span>${escapeHtml(item.senderName || "Cliente")}</span>
            <span class="support-chat-notification-time">${escapeHtml(formatTime(item.created))}</span>
          </span>
          <span class="support-chat-notification-message">${escapeHtml(item.message || "Nova mensagem no suporte.")}</span>
        </a>
      `;
    }).join("");
  }

  function pushNotification(payload) {
    const message = payload.message || {};

    if (message.sender_role !== "customer") {
      return;
    }

    count++;
    notifications.unshift({
      chatId: Number(payload.chat_id || message.support_chat_id || 0),
      senderName: message.sender_name || "Cliente",
      message: message.message_text || "",
      created: message.created || null,
    });

    notifications.splice(8);
    render();
  }

  function scheduleReconnect() {
    if (reconnectTimer) {
      window.clearTimeout(reconnectTimer);
    }

    reconnectTimer = window.setTimeout(() => {
      connect();
    }, reconnectDelay);

    reconnectDelay = Math.min(reconnectDelay * 1.7, 10000);
  }

  async function connect() {
    try {
      const auth = await fetchJson(wsAuthUrl);
      socket = new WebSocket(auth.websocketUrl);
      authenticated = false;

      socket.addEventListener("open", () => {
        socket.send(JSON.stringify({
          type: "authenticate",
          token: auth.token,
        }));
      });

      socket.addEventListener("message", (event) => {
        let payload = null;

        try {
          payload = JSON.parse(event.data);
        } catch (error) {
          return;
        }

        if (!payload || !payload.type) {
          return;
        }

        if (payload.type === "auth_ok") {
          authenticated = true;
          reconnectDelay = 1200;
          return;
        }

        if (authenticated && payload.type === "message_created") {
          pushNotification(payload);
        }
      });

      socket.addEventListener("close", () => {
        authenticated = false;
        scheduleReconnect();
      });

      socket.addEventListener("error", () => {
        authenticated = false;
      });
    } catch (error) {
      scheduleReconnect();
    }
  }

  if (clearButton) {
    clearButton.addEventListener("click", (event) => {
      event.preventDefault();
      count = 0;
      notifications.splice(0);
      render();
    });
  }

  render();
  connect();
})();

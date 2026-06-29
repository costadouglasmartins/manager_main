document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector(".support-chat-record-page");

  if (!root) {
    return;
  }

  const eventsUrl = root.dataset.eventsUrl || "";
  const isActive = root.dataset.active === "1";

  const elements = {
    status: document.getElementById("supportChatRecordStatus"),
    connection: document.getElementById("supportChatRecordConnection"),
    meta: document.getElementById("supportChatRecordMeta"),
    attendant: document.getElementById("supportChatRecordAttendant"),
    lastMessage: document.getElementById("supportChatRecordLastMessage"),
    closedAtWrap: document.getElementById("supportChatRecordClosedAtWrap"),
    closedAt: document.getElementById("supportChatRecordClosedAt"),
    liveLabel: document.getElementById("supportChatRecordLiveLabel"),
    messages: document.getElementById("supportChatRecordMessages"),
    typing: document.getElementById("supportChatRecordTyping"),
  };

  let eventSource = null;
  let lastMessageSignature = "";

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

  function formatDate(value) {
    if (!value) {
      return "";
    }

    const date = parseLocalDateTime(value);

    if (!date || Number.isNaN(date.getTime())) {
      return value;
    }

    return new Intl.DateTimeFormat("pt-BR", {
      dateStyle: "short",
      timeStyle: "short",
    }).format(date);
  }

  function statusLabel(status) {
    switch (status) {
      case "pending":
        return "Pendente";
      case "open":
        return "Em aberto";
      case "closed":
        return "Finalizado";
      default:
        return status || "";
    }
  }

  function roleLabel(role) {
    switch (role) {
      case "attendant":
        return "Atendente";
      case "system":
        return "Sistema";
      default:
        return "Cliente";
    }
  }

  function setConnection(label, cssClass) {
    if (!elements.connection) {
      return;
    }

    elements.connection.textContent = label;
    elements.connection.classList.remove("is-live", "is-connecting", "is-offline", "is-closed");
    elements.connection.classList.add(cssClass);
  }

  function updateStatusBadge(status) {
    if (!elements.status) {
      return;
    }

    elements.status.textContent = statusLabel(status);
    elements.status.classList.remove("status-pending", "status-open", "status-closed");
    elements.status.classList.add(`status-${status || "pending"}`);
  }

  function messageSignature(messages) {
    return messages.map((message) => `${message.id || ""}:${message.created || ""}`).join("|");
  }

  function shouldStickToBottom() {
    if (!elements.messages) {
      return false;
    }

    return elements.messages.scrollHeight - elements.messages.scrollTop - elements.messages.clientHeight < 140;
  }

  function renderMessages(messages) {
    if (!elements.messages) {
      return;
    }

    const signature = messageSignature(messages);
    if (signature === lastMessageSignature) {
      return;
    }

    const stickToBottom = shouldStickToBottom() || lastMessageSignature === "";
    lastMessageSignature = signature;

    if (!messages.length) {
      elements.messages.innerHTML = '<p class="support-chat-record-empty text-center text-muted py-4 mb-0">Nenhuma mensagem registrada para esta conversa.</p>';
    } else {
      elements.messages.innerHTML = messages.map((message) => {
        const role = String(message.sender_role || "customer");
        const label = roleLabel(role);

        return `
          <article class="support-chat-record-message is-${escapeHtml(role)}" data-message-id="${Number(message.id || 0)}">
            <div class="support-chat-record-bubble">
              <header>
                <strong>${escapeHtml(message.sender_name || label)}</strong>
                <span>${escapeHtml(label)}</span>
              </header>
              <p>${escapeHtml(message.message_text || "").replace(/\n/g, "<br>")}</p>
              <time>${escapeHtml(formatDate(message.created))}</time>
            </div>
          </article>
        `;
      }).join("");
    }

    if (stickToBottom) {
      elements.messages.scrollTop = elements.messages.scrollHeight;
    }
  }

  function renderTyping(users) {
    if (!elements.typing) {
      return;
    }

    if (!users.length) {
      elements.typing.hidden = true;
      elements.typing.textContent = "";
      return;
    }

    const names = users.map((user) => user.user_name || roleLabel(user.role)).filter(Boolean);
    elements.typing.hidden = false;
    elements.typing.textContent = names.length === 1
      ? `${names[0]} está digitando`
      : `${names.join(", ")} estão digitando`;
  }

  function renderSnapshot(payload) {
    const chat = payload.chat || {};
    const messages = payload.messages || [];
    const typingUsers = payload.typing_users || [];
    const tenantName = chat.tenant_name || `Tenant ${chat.tenant_database_number || ""}`;
    const customerName = chat.customer_name || chat.customer_username || `Usuário #${chat.customer_user_id || ""}`;
    const attendantName = chat.attendant_name || chat.attendant_username || "Sem atendente";
    const status = chat.status || "pending";

    updateStatusBadge(status);

    if (elements.meta) {
      elements.meta.textContent = `${customerName} · ${tenantName}`;
    }

    if (elements.attendant) {
      elements.attendant.textContent = attendantName;
    }

    if (elements.lastMessage) {
      elements.lastMessage.textContent = formatDate(chat.last_message_at || chat.created || "");
    }

    if (elements.closedAtWrap && elements.closedAt) {
      const closedAt = chat.closed_at || "";
      elements.closedAtWrap.hidden = closedAt === "";
      elements.closedAt.textContent = formatDate(closedAt);
    }

    if (elements.liveLabel) {
      elements.liveLabel.textContent = status === "closed" ? "Arquivo" : "Ao vivo";
    }

    renderMessages(messages);
    renderTyping(status === "closed" ? [] : typingUsers);

    if (status === "closed") {
      setConnection("Histórico finalizado", "is-closed");
      closeEventSource();
    } else {
      setConnection("Atualizando ao vivo", "is-live");
    }
  }

  function closeEventSource() {
    if (eventSource) {
      eventSource.close();
      eventSource = null;
    }
  }

  if (!isActive) {
    setConnection("Histórico finalizado", "is-closed");
    return;
  }

  if (!eventsUrl || !window.EventSource) {
    setConnection("Atualização automática indisponível", "is-offline");
    return;
  }

  setConnection("Conectando atualização...", "is-connecting");
  eventSource = new EventSource(eventsUrl);

  eventSource.addEventListener("snapshot", (event) => {
    try {
      renderSnapshot(JSON.parse(event.data));
    } catch (error) {
      setConnection("Falha ao atualizar", "is-offline");
    }
  });

  eventSource.addEventListener("deleted", () => {
    setConnection("Chat excluído", "is-offline");
    closeEventSource();
  });

  eventSource.addEventListener("complete", () => {
    setConnection("Histórico finalizado", "is-closed");
    closeEventSource();
  });

  eventSource.addEventListener("error", () => {
    if (eventSource) {
      setConnection("Reconectando atualização...", "is-connecting");
    }
  });

  window.addEventListener("beforeunload", closeEventSource);
});

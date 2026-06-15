document.addEventListener("DOMContentLoaded", () => {
  const root = document.querySelector(".support-admin-page");

  if (!root) {
    return;
  }

  const bootstrapUrl = root.dataset.bootstrapUrl || "";
  const chatUrlBase = (root.dataset.chatUrlBase || "").replace(/\/$/, "");
  const wsAuthUrl = root.dataset.wsAuthUrl || "";
  const initialChatId = Number(root.dataset.initialChatId || 0);

  const elements = {
    refreshButton: document.getElementById("supportAdminRefreshButton"),
    chatList: document.getElementById("supportAdminChatList"),
    title: document.getElementById("supportAdminChatTitle"),
    meta: document.getElementById("supportAdminChatMeta"),
    messages: document.getElementById("supportAdminMessages"),
    typing: document.getElementById("supportAdminTyping"),
    form: document.getElementById("supportAdminComposer"),
    input: document.getElementById("supportAdminMessageInput"),
    sendButton: document.getElementById("supportAdminSendButton"),
    closeButton: document.getElementById("supportAdminCloseButton"),
    assignButton: document.getElementById("supportAdminAssignButton"),
    attendantSelect: document.getElementById("supportAdminAttendantSelect"),
    connection: document.getElementById("supportAdminConnectionStatus"),
  };

  const state = {
    bootstrap: null,
    currentChat: null,
    socket: null,
    socketAuthenticated: false,
    reconnectTimer: null,
    reconnectDelay: 1200,
    typingTimer: null,
    lastTypingState: false,
    selectedChatId: initialChatId > 0 ? initialChatId : null,
  };

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

  function setConnectionStatus(label, isOnline) {
    elements.connection.textContent = label;
    elements.connection.classList.toggle("text-success", Boolean(isOnline));
    elements.connection.classList.toggle("text-muted", !isOnline);
  }

  function setComposerState(enabled) {
    elements.input.disabled = !enabled;
    elements.sendButton.disabled = !enabled;
  }

  function selectedChatIsOpen() {
    return state.currentChat && state.currentChat.chat && state.currentChat.chat.status !== "closed";
  }

  function updateActionStates() {
    const hasChat = Boolean(state.currentChat && state.currentChat.chat);
    const isClosed = !hasChat || state.currentChat.chat.status === "closed";

    elements.closeButton.disabled = !hasChat || isClosed || !state.socketAuthenticated;
    elements.assignButton.disabled = !hasChat || isClosed || !state.socketAuthenticated;
    elements.attendantSelect.disabled = !hasChat || isClosed;
    setComposerState(hasChat && !isClosed && state.socketAuthenticated);
  }

  function fillAttendants() {
    const attendants = (state.bootstrap && state.bootstrap.attendants) || [];
    const selectedId = state.currentChat && state.currentChat.chat ? String(state.currentChat.chat.attendant_user_id || "") : "";

    elements.attendantSelect.innerHTML = '<option value="">Selecionar atendente</option>';

    attendants.forEach((attendant) => {
      const option = document.createElement("option");
      option.value = String(attendant.id);
      option.textContent = attendant.name || attendant.username || ("Usuário #" + attendant.id);
      if (String(attendant.id) === selectedId) {
        option.selected = true;
      }
      elements.attendantSelect.appendChild(option);
    });
  }

  function renderChatList() {
    const chats = (state.bootstrap && state.bootstrap.chats) || [];

    if (!chats.length) {
      elements.chatList.innerHTML = '<div class="support-admin-empty"><p class="text-muted mb-0">Nenhum atendimento encontrado.</p></div>';
      return;
    }

    elements.chatList.innerHTML = chats.map((chat) => {
      const activeClass = Number(chat.id) === Number(state.selectedChatId) ? " is-active" : "";
      const attendantName = chat.attendant_name || "Sem atendente";
      const tenantName = chat.tenant_name || ("Tenant " + (chat.tenant_database_number || ""));

      return `
        <button type="button" class="support-admin-chat-item${activeClass}" data-chat-id="${Number(chat.id)}">
          <div class="support-admin-chat-item-top">
            <strong>${escapeHtml(chat.customer_name || chat.customer_username || ("Usuário #" + chat.customer_user_id))}</strong>
            <span class="support-admin-status status-${escapeHtml(chat.status)}">${escapeHtml(statusLabel(chat.status))}</span>
          </div>
          <div class="support-admin-chat-item-bottom">
            <span class="support-admin-chat-item-tenant">${escapeHtml(tenantName)}</span>
            <span>${escapeHtml(attendantName)}</span>
          </div>
          <div class="support-admin-chat-item-bottom">
            <span>Chat #${Number(chat.id)}</span>
            <span>${escapeHtml(formatDate(chat.last_message_at || chat.created))}</span>
          </div>
        </button>
      `;
    }).join("");

    elements.chatList.querySelectorAll("[data-chat-id]").forEach((button) => {
      button.addEventListener("click", () => {
        const chatId = Number(button.dataset.chatId || 0);
        if (chatId > 0) {
          loadChat(chatId);
        }
      });
    });
  }

  function renderMessages() {
    if (!state.currentChat || !state.currentChat.chat) {
      elements.title.textContent = "Selecione um chat";
      elements.meta.textContent = "Nenhum atendimento carregado.";
      elements.messages.innerHTML = '<div class="support-admin-empty"><p class="text-muted mb-0">Abra um atendimento para acompanhar a conversa.</p></div>';
      fillAttendants();
      updateActionStates();
      return;
    }

    const chat = state.currentChat.chat;
    const messages = state.currentChat.messages || [];
    const bootstrapUserId = Number((state.bootstrap && state.bootstrap.currentUser && state.bootstrap.currentUser.id) || 0);
    const customerName = chat.customer_name || chat.customer_username || ("Usuário #" + chat.customer_user_id);
    const tenantName = chat.tenant_name || ("Tenant " + (chat.tenant_database_number || ""));
    const attendantName = chat.attendant_name || "Sem atendente";

    elements.title.textContent = customerName;
    elements.meta.textContent = `${tenantName} | ${attendantName} | ${statusLabel(chat.status)}`;

    if (!messages.length) {
      elements.messages.innerHTML = '<div class="support-admin-empty"><p class="text-muted mb-0">Nenhuma mensagem ainda.</p></div>';
    } else {
      elements.messages.innerHTML = messages.map((message) => {
        const isSelf = Number(message.sender_user_id || 0) === bootstrapUserId && message.sender_role === "attendant";
        const cssClass = isSelf ? "is-self" : "is-other";

        return `
          <div class="support-admin-message ${cssClass}">
            <div class="support-admin-message-meta">
              ${escapeHtml(message.sender_name || message.sender_role)} | ${escapeHtml(formatDate(message.created))}
            </div>
            <div class="support-admin-message-bubble">${escapeHtml(message.message_text || "")}</div>
          </div>
        `;
      }).join("");
    }

    elements.messages.scrollTop = elements.messages.scrollHeight;
    fillAttendants();
    updateActionStates();
  }

  async function fetchJson(url, options) {
    const response = await fetch(url, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      ...options,
    });

    const payload = await response.json();

    if (!response.ok) {
      throw new Error(payload.message || "Request failed");
    }

    return payload;
  }

  async function refreshBootstrap(keepSelection = true) {
    state.bootstrap = await fetchJson(bootstrapUrl);
    renderChatList();

    if (!keepSelection) {
      state.selectedChatId = null;
      state.currentChat = null;
      renderMessages();
      return;
    }

    if (state.selectedChatId) {
      const exists = (state.bootstrap.chats || []).some((chat) => Number(chat.id) === Number(state.selectedChatId));
      if (exists) {
        await loadChat(state.selectedChatId, false);
        return;
      }
    }

    const firstOpen = (state.bootstrap.chats || [])[0];
    if (firstOpen) {
      await loadChat(firstOpen.id, false);
    } else {
      state.currentChat = null;
      renderMessages();
    }
  }

  async function loadChat(chatId, shouldRefreshList = true) {
    state.selectedChatId = Number(chatId);
    if (shouldRefreshList) {
      renderChatList();
    }

    state.currentChat = await fetchJson(`${chatUrlBase}/${encodeURIComponent(chatId)}`);
    renderMessages();
    window.dispatchEvent(new CustomEvent("supportChatNotifications:refresh"));
  }

  function sendSocketMessage(payload) {
    if (!state.socket || state.socket.readyState !== WebSocket.OPEN || !state.socketAuthenticated) {
      return false;
    }

    state.socket.send(JSON.stringify(payload));
    return true;
  }

  function scheduleReconnect() {
    if (state.reconnectTimer) {
      window.clearTimeout(state.reconnectTimer);
    }

    state.reconnectTimer = window.setTimeout(() => {
      connectSocket();
    }, state.reconnectDelay);

    state.reconnectDelay = Math.min(state.reconnectDelay * 1.7, 10000);
  }

  async function connectSocket() {
    try {
      const auth = await fetchJson(wsAuthUrl);
      const socket = new WebSocket(auth.websocketUrl);

      state.socket = socket;
      state.socketAuthenticated = false;
      setConnectionStatus("Conectando...", false);
      updateActionStates();

      socket.addEventListener("open", () => {
        socket.send(JSON.stringify({
          type: "authenticate",
          token: auth.token,
        }));
      });

      socket.addEventListener("message", async (event) => {
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
          state.socketAuthenticated = true;
          state.reconnectDelay = 1200;
          setConnectionStatus("Conectado", true);
          updateActionStates();
          return;
        }

        if (payload.type === "typing") {
          if (state.currentChat && state.currentChat.chat && Number(payload.chat_id) === Number(state.currentChat.chat.id)) {
            elements.typing.textContent = payload.is_typing ? `${payload.user_name || "Cliente"} está digitando...` : "";
          }
          return;
        }

        if (payload.type === "chat_closed" || payload.type === "message_created" || payload.type === "chat_assigned" || payload.type === "chat_created") {
          const currentId = state.currentChat && state.currentChat.chat ? Number(state.currentChat.chat.id) : null;
          await refreshBootstrap(true);
          if (currentId && Number(payload.chat_id) === currentId) {
            await loadChat(currentId, false);
          }
          return;
        }

        if (payload.type === "error") {
          setConnectionStatus(payload.message || "Erro de conexão", false);
        }
      });

      socket.addEventListener("close", () => {
        state.socketAuthenticated = false;
        setConnectionStatus("Reconectando...", false);
        updateActionStates();
        scheduleReconnect();
      });

      socket.addEventListener("error", () => {
        setConnectionStatus("Falha na conexão", false);
      });
    } catch (error) {
      setConnectionStatus("Falha na conexão", false);
      scheduleReconnect();
    }
  }

  function sendTyping(isTyping) {
    if (!selectedChatIsOpen()) {
      return;
    }

    if (state.lastTypingState === isTyping) {
      return;
    }

    state.lastTypingState = isTyping;

    sendSocketMessage({
      type: "typing",
      chat_id: Number(state.currentChat.chat.id),
      is_typing: Boolean(isTyping),
    });
  }

  elements.refreshButton.addEventListener("click", async () => {
    await refreshBootstrap(true);
  });

  elements.form.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (!selectedChatIsOpen()) {
      return;
    }

    const message = elements.input.value.trim();
    if (!message) {
      return;
    }

    const sent = sendSocketMessage({
      type: "send_message",
      chat_id: Number(state.currentChat.chat.id),
      message,
    });

    if (!sent) {
      return;
    }

    elements.input.value = "";
    sendTyping(false);
  });

  elements.input.addEventListener("input", () => {
    const hasText = elements.input.value.trim() !== "";
    sendTyping(hasText);

    if (state.typingTimer) {
      window.clearTimeout(state.typingTimer);
    }

    state.typingTimer = window.setTimeout(() => {
      sendTyping(false);
    }, 900);
  });

  elements.assignButton.addEventListener("click", () => {
    if (!selectedChatIsOpen()) {
      return;
    }

    const attendantId = Number(elements.attendantSelect.value || 0);
    if (attendantId <= 0) {
      return;
    }

    sendSocketMessage({
      type: "assign_chat",
      chat_id: Number(state.currentChat.chat.id),
      attendant_user_id: attendantId,
    });
  });

  elements.closeButton.addEventListener("click", () => {
    if (!selectedChatIsOpen()) {
      return;
    }

    sendSocketMessage({
      type: "close_chat",
      chat_id: Number(state.currentChat.chat.id),
    });
  });

  refreshBootstrap(true).catch(() => {
    setConnectionStatus("Falha ao carregar chats", false);
  });

  connectSocket();
});

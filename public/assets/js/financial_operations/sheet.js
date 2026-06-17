document.addEventListener("DOMContentLoaded", () => {
  const page = document.querySelector(".financial-sheet-page");
  const sheet = document.querySelector(".financial-sheet-table");
  const modalElement = document.getElementById("financialSheetOperationModal");
  const form = document.getElementById("financialSheetOperationForm");

  if (!page || !sheet || !modalElement || !form) {
    return;
  }

  const modal = window.bootstrap ? new bootstrap.Modal(modalElement) : null;
  const entryList = document.getElementById("financialSheetEntryList");
  const status = document.getElementById("financialSheetModalStatus");
  const title = document.getElementById("financialSheetOperationModalTitle");
  const subtitle = document.getElementById("financialSheetOperationModalSubtitle");
  const saveButton = document.getElementById("financialSheetSaveButton");
  const fullEditLink = document.getElementById("financialSheetFullEditLink");
  const operationIdInput = document.getElementById("sheet_operation_id");
  const paymentIdInput = document.getElementById("sheet_payment_id");
  const categoryInput = document.getElementById("sheet_financial_operation_category_id");
  const operationTypeInput = document.getElementById("sheet_type");
  const totalValueInput = document.getElementById("sheet_total_value");
  const paymentFields = document.getElementById("financialSheetPaymentFields");
  const paymentTypeInput = document.getElementById("sheet_payment_type");
  const paymentValueInput = document.getElementById("sheet_payment_value");
  const paymentDateInput = document.getElementById("sheet_payment_date");
  const dueDateInput = document.getElementById("sheet_due_date");
  const paymentControls = paymentFields ? paymentFields.querySelectorAll("input, select") : [];
  const moneyFormatter = new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL",
  });
  const state = {
    entries: [],
    shouldReload: false,
  };

  function formatMoney(value) {
    const numberValue = Number.parseFloat(value || "0");
    return moneyFormatter.format(Number.isFinite(numberValue) ? numberValue : 0);
  }

  function formatDate(value) {
    if (!value) {
      return "-";
    }

    const parts = value.split("-");
    if (parts.length !== 3) {
      return value;
    }

    return `${parts[2]}/${parts[1]}/${parts[0]}`;
  }

  function setStatus(type, message) {
    if (!status) {
      return;
    }

    status.className = "alert";
    if (!message) {
      status.classList.add("d-none");
      status.textContent = "";
      return;
    }

    status.classList.add(`alert-${type}`);
    status.textContent = message;
  }

  function setFormVisible(visible) {
    form.classList.toggle("d-none", !visible);
    if (saveButton) {
      saveButton.classList.toggle("d-none", !visible);
      saveButton.disabled = !visible;
    }
    if (fullEditLink) {
      fullEditLink.classList.toggle("d-none", !visible);
    }
  }

  function syncPaymentFields(hasPayment) {
    if (paymentFields) {
      paymentFields.classList.toggle("d-none", !hasPayment);
    }

    paymentControls.forEach((control) => {
      control.disabled = !hasPayment;
      control.required = hasPayment && control.id !== "sheet_payment_date";
    });

    totalValueInput.readOnly = hasPayment;
  }

  function resetModal() {
    state.entries = [];
    form.reset();
    if (entryList) {
      entryList.innerHTML = "";
    }
    setStatus("", "");
    setFormVisible(false);
    syncPaymentFields(false);
  }

  function buildEntryLabel(entry) {
    const value = entry.payment_id ? entry.payment_value : entry.operation_total_value;
    const date = entry.due_date || entry.sheet_date;
    const pieces = [`#${entry.operation_id}`, formatMoney(value)];

    if (date) {
      pieces.push(formatDate(date));
    }

    return pieces.join(" - ");
  }

  function renderEntryList() {
    if (!entryList) {
      return;
    }

    entryList.innerHTML = "";

    state.entries.forEach((entry, index) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "financial-sheet-entry-option";
      button.dataset.entryIndex = String(index);

      const label = document.createElement("strong");
      label.textContent = buildEntryLabel(entry);

      const meta = document.createElement("span");
      meta.textContent = entry.payment_id ? `Parcela #${entry.payment_id}` : "Operação sem parcelas";

      button.append(label, meta);
      entryList.appendChild(button);
    });
  }

  function selectEntry(index) {
    const entry = state.entries[index];
    if (!entry) {
      return;
    }

    entryList.querySelectorAll(".financial-sheet-entry-option").forEach((button) => {
      button.classList.toggle("active", Number.parseInt(button.dataset.entryIndex || "-1", 10) === index);
    });

    operationIdInput.value = entry.operation_id || "";
    paymentIdInput.value = entry.payment_id || "";
    categoryInput.value = entry.financial_operation_category_id || "";
    operationTypeInput.value = entry.operation_type || "";
    totalValueInput.value = entry.operation_total_value || "0.00";
    paymentTypeInput.value = entry.payment_type || "";
    paymentValueInput.value = entry.payment_value || "";
    paymentDateInput.value = entry.payment_date_input || "";
    dueDateInput.value = entry.due_date || "";

    const hasPayment = Boolean(entry.payment_id);
    syncPaymentFields(hasPayment);
    setFormVisible(true);

    if (fullEditLink) {
      const baseUrl = (page.dataset.sheetEditBaseUrl || "").replace(/\/$/, "");
      fullEditLink.href = `${baseUrl}/${encodeURIComponent(entry.operation_id)}`;
    }
  }

  async function loadEntries(trigger) {
    const operationsUrl = page.dataset.sheetOperationsUrl || "";
    if (!operationsUrl || !modal) {
      return;
    }

    resetModal();

    const cell = trigger.closest("td, th");
    if (cell) {
      sheet.querySelectorAll(".financial-sheet-cell-selected").forEach((selectedCell) => {
        selectedCell.classList.remove("financial-sheet-cell-selected");
      });
      cell.classList.add("financial-sheet-cell-selected");
    }

    const monthLabel = trigger.dataset.sheetMonthLabel || "";
    const categoryLabel = trigger.dataset.sheetCategoryLabel || "";
    title.textContent = "Editar lançamento";
    subtitle.textContent = [categoryLabel, monthLabel].filter(Boolean).join(" - ");
    setStatus("info", "Carregando lançamentos...");
    modal.show();

    const params = new URLSearchParams({
      year: trigger.dataset.sheetYear || "",
      type: trigger.dataset.sheetType || "",
      category_id: trigger.dataset.sheetCategoryId || "",
    });
    if (trigger.dataset.sheetMonth) {
      params.set("month", trigger.dataset.sheetMonth);
    }

    try {
      const response = await fetch(`${operationsUrl}?${params.toString()}`, {
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      });
      const payload = await response.json();

      if (!response.ok) {
        throw new Error(payload.message || "Não foi possível carregar os lançamentos.");
      }

      state.entries = Array.isArray(payload.data) ? payload.data : [];
      renderEntryList();

      if (state.entries.length === 0) {
        setStatus("warning", "Nenhum lançamento foi encontrado para este valor.");
        return;
      }

      setStatus("", "");
      selectEntry(0);
    } catch (error) {
      setStatus("danger", error.message || "Não foi possível carregar os lançamentos.");
    }
  }

  sheet.addEventListener("click", (event) => {
    const trigger = event.target.closest("[data-sheet-entry='1']");
    const cell = event.target.closest("td, th");

    if (!cell || !sheet.contains(cell)) {
      return;
    }

    if (!trigger) {
      sheet.querySelectorAll(".financial-sheet-cell-selected").forEach((selectedCell) => {
        selectedCell.classList.remove("financial-sheet-cell-selected");
      });
      cell.classList.add("financial-sheet-cell-selected");
      return;
    }

    loadEntries(trigger);
  });

  if (entryList) {
    entryList.addEventListener("click", (event) => {
      const trigger = event.target.closest(".financial-sheet-entry-option");
      if (!trigger) {
        return;
      }

      selectEntry(Number.parseInt(trigger.dataset.entryIndex || "0", 10));
    });
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    const operationId = operationIdInput.value;
    const updateBaseUrl = (page.dataset.sheetUpdateBaseUrl || "").replace(/\/$/, "");
    if (!operationId || !updateBaseUrl) {
      setStatus("danger", "Não foi possível identificar o lançamento.");
      return;
    }

    if (saveButton) {
      saveButton.disabled = true;
    }
    setStatus("info", "Salvando alterações...");

    try {
      const response = await fetch(`${updateBaseUrl}/${encodeURIComponent(operationId)}`, {
        method: "POST",
        body: new FormData(form),
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      });
      const payload = await response.json();

      if (!response.ok) {
        throw new Error(payload.message || "Não foi possível salvar as alterações.");
      }

      state.shouldReload = true;
      setStatus("success", "Alterações salvas. Atualizando a planilha...");
      window.setTimeout(() => {
        modal.hide();
      }, 450);
    } catch (error) {
      setStatus("danger", error.message || "Não foi possível salvar as alterações.");
      if (saveButton) {
        saveButton.disabled = false;
      }
    }
  });

  modalElement.addEventListener("hidden.bs.modal", () => {
    if (state.shouldReload) {
      window.location.reload();
      return;
    }

    resetModal();
  });
});

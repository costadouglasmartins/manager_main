document.addEventListener("DOMContentLoaded", () => {
  const page = document.querySelector(".financial-sheet-page");
  const sheet = document.querySelector(".financial-sheet-table");
  const createModalElement = document.getElementById("financialSheetCreateModal");
  const createForm = document.getElementById("financialOperationCreateForm");
  const modalElement = document.getElementById("financialSheetOperationModal");
  const form = document.getElementById("financialSheetOperationForm");

  if (!page || !sheet || !createModalElement || !createForm || !modalElement || !form) {
    return;
  }

  if (window.bootstrap && window.bootstrap.Popover) {
    document.querySelectorAll(".financial-sheet-category-label[data-bs-toggle='popover']").forEach((element) => {
      window.bootstrap.Popover.getOrCreateInstance(element);
    });
  }

  const createModal = window.bootstrap ? new bootstrap.Modal(createModalElement) : null;
  const modal = window.bootstrap ? new bootstrap.Modal(modalElement) : null;
  const createModalTitle = document.getElementById("financialSheetCreateModalTitle");
  const createModalSubtitle = document.getElementById("financialSheetCreateModalSubtitle");
  const createPeriodNotice = document.getElementById("financialOperationCreatePeriodNotice");
  const entryList = document.getElementById("financialSheetEntryList");
  const status = document.getElementById("financialSheetModalStatus");
  const title = document.getElementById("financialSheetOperationModalTitle");
  const subtitle = document.getElementById("financialSheetOperationModalSubtitle");
  const selectedValue = document.getElementById("financialSheetOperationSelectedValue");
  const saveButton = document.getElementById("financialSheetSaveButton");
  const fullEditLink = document.getElementById("financialSheetFullEditLink");
  const createBaseUrl = (page.dataset.sheetCreateBaseUrl || "").replace(/\/$/, "");
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
  const createCategoryInput = document.getElementById("financial_operation_category_id");
  const createTypeInput = document.getElementById("type");
  const createTotalInput = document.getElementById("total_value");
  const createGenerateInstallmentsInput = document.getElementById("generate_installments");
  const createRecurrenceFields = document.getElementById("recurrenceFields");
  const createInstallmentValueInput = document.getElementById("installment_value");
  const createPaymentTypeInput = document.getElementById("payment_type");
  const createStartDateInput = document.getElementById("recurrence_start_date");
  const createEndDateInput = document.getElementById("recurrence_end_date");
  const createRecurrenceControls = createForm.querySelectorAll("[data-recurrence-field]");
  const createSaveButton = document.getElementById("financialSheetCreateSaveButton");
  const moneyFormatter = new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL",
  });
  const canEditSheetOperations = page.dataset.canEditSheetOperations === "1";
  const state = {
    entries: [],
    createContext: null,
    shouldReload: false,
  };

  function formatMoney(value) {
    const numberValue = Number.parseFloat(value || "0");
    return moneyFormatter.format(Number.isFinite(numberValue) ? numberValue : 0);
  }

  function formatSheetMoney(cents) {
    return Number(cents / 100).toLocaleString("pt-BR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function parseSheetMoney(value) {
    const normalized = String(value || "")
      .replace(/\s/g, "")
      .replace(/[R$]/g, "")
      .replace(/\./g, "")
      .replace(",", ".")
      .replace(/[^0-9.-]/g, "");

    const parsed = Number.parseFloat(normalized);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function centsFromValue(value) {
    return Math.round(parseSheetMoney(value) * 100);
  }

  function renderEditableEntryCell(cents, data, isPaid = false) {
    const label = formatSheetMoney(cents);

    if (!canEditSheetOperations || cents === 0) {
      return isPaid ? `<span class="financial-sheet-entry-paid">${label}</span>` : label;
    }

    const attributes = [
      'type="button"',
      'class="financial-sheet-entry-edit"',
      'data-sheet-entry="1"',
      'aria-label="Editar lançamento"',
      'title="Editar lançamento"',
    ];

    Object.entries(data).forEach(([name, value]) => {
      attributes.push(`data-${name.replace(/_/g, "-")}="${escapeHtml(value)}"`);
    });

    const valueClasses = ["financial-sheet-entry-value"];
    if (isPaid) {
      valueClasses.push("financial-sheet-entry-paid");
    }

    return `<span class="financial-sheet-entry"><button ${attributes.join(" ")}><i class="bi bi-pencil-square"></i></button><span class="${valueClasses.join(" ")}">${label}</span></span>`;
  }

  function updateMoneyCell(cell, cents, negative = false) {
    if (!cell) {
      return;
    }

    cell.textContent = formatSheetMoney(cents);
    cell.classList.toggle("financial-sheet-negative", negative);
  }

  function updateSummaryMetric(metricElement, cents, prefix = "R$ ") {
    if (!metricElement) {
      return;
    }

    metricElement.textContent = `${prefix}${formatSheetMoney(cents)}`;
  }

  function formatPercent(value) {
    if (value === null) {
      return "-";
    }

    return value.toFixed(2).replace(".", ",");
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
    if (selectedValue) {
      selectedValue.textContent = "";
    }
  }

  function resetCreateModal() {
    if (createForm) {
      createForm.reset();
    }
    if (createPeriodNotice) {
      createPeriodNotice.classList.add("d-none");
      createPeriodNotice.textContent = "";
    }
    if (createModalTitle) {
      createModalTitle.textContent = "Nova operação financeira";
    }
    if (createModalSubtitle) {
      createModalSubtitle.textContent = "";
    }
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
    const cellValueLabel = trigger.closest("span")?.querySelector(".financial-sheet-entry-value")?.textContent || "";
    title.textContent = "Editar lançamento";
    subtitle.textContent = [categoryLabel, monthLabel].filter(Boolean).join(" - ");
    if (selectedValue) {
      selectedValue.textContent = cellValueLabel ? `Valor da célula: ${cellValueLabel}` : "";
    }
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

  function openCreateForm(trigger) {
    if (!createModal || !createForm) {
      return;
    }

    const sheetYear = Number.parseInt(trigger.dataset.sheetYear || "0", 10);
    const sheetMonth = Number.parseInt(trigger.dataset.sheetMonth || "0", 10);
    const sheetType = trigger.dataset.sheetType || "";
    const sheetCategoryId = trigger.dataset.sheetCategoryId || "";
    const sheetCategoryLabel = trigger.dataset.sheetCategoryLabel || "";
    const sheetMonthLabel = trigger.dataset.sheetMonthLabel || "";

    state.createContext = {
      trigger,
      year: sheetYear,
      month: sheetMonth,
      type: sheetType,
      categoryId: sheetCategoryId,
    };

    resetCreateModal();
    if (createCategoryInput) {
      createCategoryInput.value = sheetCategoryId;
    }
    if (createTypeInput) {
      createTypeInput.value = sheetType;
    }
    if (createGenerateInstallmentsInput) {
      createGenerateInstallmentsInput.checked = true;
      createGenerateInstallmentsInput.dispatchEvent(new Event("change", { bubbles: true }));
    }

    if (Number.isFinite(sheetYear) && sheetYear >= 2000 && sheetMonth >= 1 && sheetMonth <= 12) {
      const startDate = new Date(sheetYear, sheetMonth - 1, 1);
      const endDate = new Date(sheetYear, sheetMonth, 0);
      const formatDate = (date) => `${String(date.getDate()).padStart(2, "0")}/${String(date.getMonth() + 1).padStart(2, "0")}/${date.getFullYear()}`;

      if (createModalTitle) {
        createModalTitle.textContent = "Nova operação financeira";
      }
      if (createModalSubtitle) {
        createModalSubtitle.textContent = [sheetCategoryLabel, sheetMonthLabel].filter(Boolean).join(" - ");
      }
      if (createPeriodNotice) {
        createPeriodNotice.textContent = `Nova operação para ${sheetMonthLabel}/${sheetYear}.`;
        createPeriodNotice.classList.remove("d-none");
      }
      if (createStartDateInput) {
        createStartDateInput.value = formatDate(startDate);
        createStartDateInput.dispatchEvent(new Event("input", { bubbles: true }));
      }
      if (createEndDateInput) {
        createEndDateInput.value = formatDate(endDate);
        createEndDateInput.dispatchEvent(new Event("input", { bubbles: true }));
      }
      if (createPaymentTypeInput && !createPaymentTypeInput.value) {
        createPaymentTypeInput.value = "PIX";
      }
    }

    if (createTotalInput) {
      createTotalInput.value = "";
    }
    if (createInstallmentValueInput) {
      createInstallmentValueInput.value = "";
    }
    if (createSaveButton) {
      createSaveButton.disabled = false;
    }

    createModal.show();
  }

  sheet.addEventListener("click", (event) => {
    const trigger = event.target.closest("[data-sheet-entry='1']");
    const createTrigger = event.target.closest("[data-sheet-create='1']");
    const cell = event.target.closest("td, th");

    if (!cell || !sheet.contains(cell)) {
      return;
    }

    if (!trigger && !createTrigger) {
      sheet.querySelectorAll(".financial-sheet-cell-selected").forEach((selectedCell) => {
        selectedCell.classList.remove("financial-sheet-cell-selected");
      });
      cell.classList.add("financial-sheet-cell-selected");
      return;
    }

    if (createTrigger) {
      openCreateForm(createTrigger);
      return;
    }

    loadEntries(trigger);
  });

  window.addEventListener("financial-operation:create-success", (event) => {
    if (!state.createContext) {
      return;
    }

    const payload = event.detail?.payload || {};
    const formData = event.detail?.formData || {};
    const trigger = state.createContext.trigger;
    const monthNumber = Number.parseInt(state.createContext.month || "0", 10);
    const year = Number.parseInt(state.createContext.year || "0", 10);
    const type = state.createContext.type;
    const categoryId = state.createContext.categoryId;
    const amountSource = formData.generate_installments === "1"
      ? (formData.installment_value || payload.total_value || formData.total_value || "0")
      : (payload.total_value || formData.total_value || "0");
    const amountCents = centsFromValue(amountSource);
    const cell = sheet.querySelector(
      `tr[data-sheet-row="1"][data-sheet-type="${type}"][data-sheet-category-id="${categoryId}"] td:nth-of-type(${monthNumber})`
    );
    const row = cell ? cell.closest("tr[data-sheet-row='1']") : null;
    const incomeTotalsRow = sheet.querySelector(".financial-sheet-income-total");
    const expenseTotalsRow = sheet.querySelector(".financial-sheet-expense-total");
    const balanceRow = sheet.querySelector(".financial-sheet-balance-row");
    const percentRow = sheet.querySelector(".financial-sheet-percent-row");
    const summaryCards = page.querySelector(".financial-sheet-summary");

    if (!cell || !row || !incomeTotalsRow || !expenseTotalsRow || !balanceRow || !percentRow || !summaryCards) {
      state.createContext = null;
      return;
    }

    const monthCells = row.querySelectorAll("td");
    const totalCell = monthCells[monthCells.length - 1];
    const rowMonthCell = monthCells[monthNumber - 1];
    const incomeMonthCell = incomeTotalsRow.querySelectorAll("td")[monthNumber - 1];
    const incomeTotalCell = incomeTotalsRow.querySelector("td:last-child");
    const expenseMonthCell = expenseTotalsRow.querySelectorAll("td")[monthNumber - 1];
    const expenseTotalCell = expenseTotalsRow.querySelector("td:last-child");
    const balanceMonthCell = balanceRow.querySelectorAll("td")[monthNumber - 1];
    const balanceTotalCell = balanceRow.querySelector("td:last-child");
    const percentMonthCell = percentRow.querySelectorAll("td")[monthNumber - 1];
    const percentTotalCell = percentRow.querySelector("td:last-child");
    const incomeSummaryCard = summaryCards.children[0]?.querySelector("strong");
    const expenseSummaryCard = summaryCards.children[1]?.querySelector("strong");
    const balanceSummaryCard = summaryCards.children[2]?.querySelector("strong");
    const percentSummaryCard = summaryCards.children[3]?.querySelector("strong");

    if (!rowMonthCell || !totalCell || !incomeMonthCell || !incomeTotalCell || !expenseMonthCell || !expenseTotalCell || !balanceMonthCell || !balanceTotalCell || !percentMonthCell || !percentTotalCell) {
      state.createContext = null;
      return;
    }

    const currentRowTotal = centsFromValue(totalCell.textContent);
    const currentIncomeMonth = centsFromValue(incomeMonthCell.textContent);
    const currentIncomeTotal = centsFromValue(incomeTotalCell.textContent);
    const currentExpenseMonth = centsFromValue(expenseMonthCell.textContent);
    const currentExpenseTotal = centsFromValue(expenseTotalCell.textContent);
    const currentBalanceMonth = centsFromValue(balanceMonthCell.textContent);
    const currentBalanceTotal = centsFromValue(balanceTotalCell.textContent);

    const newRowTotal = currentRowTotal + amountCents;
    const newIncomeMonth = type === "CREDIT" ? currentIncomeMonth + amountCents : currentIncomeMonth;
    const newIncomeTotal = type === "CREDIT" ? currentIncomeTotal + amountCents : currentIncomeTotal;
    const newExpenseMonth = type === "DEBIT" ? currentExpenseMonth + amountCents : currentExpenseMonth;
    const newExpenseTotal = type === "DEBIT" ? currentExpenseTotal + amountCents : currentExpenseTotal;
    const newBalanceMonth = newIncomeMonth - newExpenseMonth;
    const newBalanceTotal = newIncomeTotal - newExpenseTotal;
    const newPercentMonth = newIncomeMonth > 0 ? (newExpenseMonth / newIncomeMonth) * 100 : null;
    const newPercentTotal = newIncomeTotal > 0 ? (newExpenseTotal / newIncomeTotal) * 100 : null;

    rowMonthCell.innerHTML = renderEditableEntryCell(amountCents, {
      sheet_year: year,
      sheet_type: type,
      sheet_category_id: categoryId,
      sheet_category_label: trigger.dataset.sheetCategoryLabel || "",
      sheet_month: monthNumber,
      sheet_month_label: trigger.dataset.sheetMonthLabel || "",
    });
    totalCell.textContent = formatSheetMoney(newRowTotal);
    updateMoneyCell(incomeMonthCell, newIncomeMonth);
    updateMoneyCell(incomeTotalCell, newIncomeTotal);
    updateMoneyCell(expenseMonthCell, newExpenseMonth);
    updateMoneyCell(expenseTotalCell, newExpenseTotal);
    updateMoneyCell(balanceMonthCell, newBalanceMonth, newBalanceMonth < 0);
    updateMoneyCell(balanceTotalCell, newBalanceTotal, newBalanceTotal < 0);
    percentMonthCell.textContent = formatPercent(newPercentMonth);
    percentTotalCell.textContent = formatPercent(newPercentTotal);

    if (incomeSummaryCard) {
      incomeSummaryCard.textContent = `R$ ${formatSheetMoney(type === "CREDIT" ? newIncomeTotal : currentIncomeTotal)}`;
    }
    if (expenseSummaryCard) {
      expenseSummaryCard.textContent = `R$ ${formatSheetMoney(type === "DEBIT" ? newExpenseTotal : currentExpenseTotal)}`;
    }
    if (balanceSummaryCard) {
      balanceSummaryCard.textContent = `R$ ${formatSheetMoney(newBalanceTotal)}`;
      balanceSummaryCard.closest(".financial-sheet-metric")?.classList.toggle("financial-sheet-metric-negative", newBalanceTotal < 0);
      balanceSummaryCard.closest(".financial-sheet-metric")?.classList.toggle("financial-sheet-metric-balance", newBalanceTotal >= 0);
    }
    if (percentSummaryCard) {
      percentSummaryCard.textContent = newPercentTotal === null ? "-" : `${formatPercent(newPercentTotal)}%`;
    }

    state.createContext = null;
    if (createModal) {
      window.setTimeout(() => {
        createModal.hide();
      }, 350);
    }
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

  createModalElement.addEventListener("hidden.bs.modal", () => {
    state.createContext = null;
    resetCreateModal();
  });
});

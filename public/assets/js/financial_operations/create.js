document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("financialOperationCreateForm");

  if (!form) {
    return;
  }

  const totalInput = document.getElementById("total_value");
  const recurrenceInput = document.getElementById("generate_installments");
  const recurrenceFields = document.getElementById("recurrenceFields");
  const installmentValueInput = document.getElementById("installment_value");
  const paymentTypeInput = document.getElementById("payment_type");
  const startDateInput = document.getElementById("recurrence_start_date");
  const endDateInput = document.getElementById("recurrence_end_date");
  const preview = document.getElementById("recurrencePreview");
  const installmentsPreview = document.getElementById("recurrenceInstallments");
  const recurrenceControls = form.querySelectorAll("[data-recurrence-field]");
  const inlineStatus = document.getElementById("financialOperationCreateStatus");
  const ajaxSubmit = form.dataset.ajaxSubmit === "1";
  const moneyFormatter = new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL",
  });

  /**
   * Builds and validates a local date.
   * @param {number} year Year.
   * @param {number} month Month from 1 to 12.
   * @param {number} day Day of month.
   * @returns {Date|null}
   */
  function buildLocalDate(year, month, day) {
    const date = new Date(year, month - 1, day);
    if (
      date.getFullYear() !== year
      || date.getMonth() !== month - 1
      || date.getDate() !== day
    ) {
      return null;
    }

    return date;
  }

  /**
   * Parses a date input as a local date.
   * @param {string} value Date in YYYY-MM-DD or DD/MM/YYYY format.
   * @returns {Date|null}
   */
  function parseDate(value) {
    if (!value) {
      return null;
    }

    const normalizedValue = value.trim();
    const isoMatch = normalizedValue.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (isoMatch) {
      return buildLocalDate(
        Number.parseInt(isoMatch[1], 10),
        Number.parseInt(isoMatch[2], 10),
        Number.parseInt(isoMatch[3], 10)
      );
    }

    const brMatch = normalizedValue.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (brMatch) {
      return buildLocalDate(
        Number.parseInt(brMatch[3], 10),
        Number.parseInt(brMatch[2], 10),
        Number.parseInt(brMatch[1], 10)
      );
    }

    return null;
  }

  /**
   * Formats a local date as YYYY-MM-DD.
   * @param {Date} date Date to format.
   * @returns {string}
   */
  function formatDateInput(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
  }

  /**
   * Returns a comparable month index for a date.
   * @param {Date} date Date to index.
   * @returns {number}
   */
  function getMonthIndex(date) {
    return (date.getFullYear() * 12) + date.getMonth();
  }

  /**
   * Formats a local date for the user interface.
   * @param {Date} date Date to format.
   * @returns {string}
   */
  function formatDateDisplay(date) {
    const day = String(date.getDate()).padStart(2, "0");
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const year = date.getFullYear();

    return `${day}/${month}/${year}`;
  }

  /**
   * Formats a text field value as DD/MM/YYYY while the user types.
   * @param {string} value Raw input value.
   * @returns {string}
   */
  function formatDateTextValue(value) {
    const digits = String(value || "").replace(/\D/g, "").slice(0, 8);
    if (digits.length > 4) {
      return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
    }
    if (digits.length > 2) {
      return `${digits.slice(0, 2)}/${digits.slice(2)}`;
    }

    return digits;
  }

  /**
   * Parses a money input value.
   * @param {string} value Money input value.
   * @returns {number}
   */
  function parseMoney(value) {
    return Number.parseFloat(String(value || "0").replace(",", "."));
  }

  /**
   * Updates the inline submit status, when available.
   * @param {string} type Alert type.
   * @param {string} message Message to show.
   */
  function setInlineStatus(type, message) {
    if (!inlineStatus) {
      return;
    }

    inlineStatus.className = "alert";
    if (!message) {
      inlineStatus.classList.add("d-none");
      inlineStatus.textContent = "";
      return;
    }

    inlineStatus.classList.add(`alert-${type}`);
    inlineStatus.textContent = message;
  }

  /**
   * Adds months while preserving the preferred day where possible.
   * @param {Date} date Base date.
   * @param {number} months Number of months to add.
   * @param {number} preferredDay Preferred day of month.
   * @returns {Date}
   */
  function addMonthsKeepingPreferredDay(date, months, preferredDay) {
    const monthIndex = date.getMonth() + months;
    const target = new Date(date.getFullYear(), monthIndex, 1);
    const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate();

    target.setDate(Math.min(preferredDay, lastDay));
    return target;
  }

  /**
   * Calculates Gregorian Easter Sunday.
   * @param {number} year Year.
   * @returns {Date}
   */
  function getEasterDate(year) {
    const a = year % 19;
    const b = Math.floor(year / 100);
    const c = year % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7;
    const m = Math.floor((a + 11 * h + 22 * l) / 451);
    const month = Math.floor((h + l - 7 * m + 114) / 31);
    const day = ((h + l - 7 * m + 114) % 31) + 1;

    return new Date(year, month - 1, day);
  }

  /**
   * Returns Brazilian national holidays and common movable holidays.
   * @param {number} year Year.
   * @returns {Set<string>}
   */
  function getBrazilianNationalHolidays(year) {
    const fixed = ["01-01", "04-21", "05-01", "09-07", "10-12", "11-02", "11-15", "11-20", "12-25"];
    const holidays = new Set(fixed.map((dayMonth) => `${year}-${dayMonth}`));
    const easter = getEasterDate(year);

    [-48, -47, -2, 60].forEach((offset) => {
      const date = new Date(easter);
      date.setDate(date.getDate() + offset);
      holidays.add(formatDateInput(date));
    });

    return holidays;
  }

  /**
   * Checks whether a date is a business day.
   * @param {Date} date Date to check.
   * @returns {boolean}
   */
  function isBusinessDay(date) {
    const day = date.getDay();
    if (day === 0 || day === 6) {
      return false;
    }

    return !getBrazilianNationalHolidays(date.getFullYear()).has(formatDateInput(date));
  }

  /**
   * Moves a date forward to the next business day.
   * @param {Date} date Candidate date.
   * @returns {Date}
   */
  function moveToBusinessDay(date) {
    const nextDate = new Date(date);

    while (!isBusinessDay(nextDate)) {
      nextDate.setDate(nextDate.getDate() + 1);
    }

    return nextDate;
  }

  /**
   * Builds due dates for the selected monthly range.
   * @returns {Date[]}
   */
  function buildDueDates() {
    const startDate = parseDate(startDateInput.value);
    let endDate = parseDate(endDateInput.value);

    if (!startDate || !endDate || endDate < startDate) {
      return [];
    }

    const maxEndDate = new Date(startDate);
    maxEndDate.setFullYear(maxEndDate.getFullYear() + 5);

    if (endDate > maxEndDate) {
      endDate = maxEndDate;
      endDateInput.value = formatDateDisplay(maxEndDate);
    }

    const preferredDay = startDate.getDate();
    const endMonthIndex = getMonthIndex(endDate);
    const dueDates = [];

    for (let monthOffset = 0; monthOffset <= 60; monthOffset++) {
      const candidateDate = addMonthsKeepingPreferredDay(startDate, monthOffset, preferredDay);
      if (getMonthIndex(candidateDate) > endMonthIndex) {
        break;
      }

      dueDates.push(moveToBusinessDay(candidateDate));
    }

    return dueDates;
  }

  /**
   * Clears generated installments from the preview.
   */
  function clearInstallmentsPreview() {
    if (!installmentsPreview) {
      return;
    }

    installmentsPreview.innerHTML = "";
    installmentsPreview.classList.add("d-none");
  }

  /**
   * Renders generated installments in the preview area.
   * @param {Date[]} dueDates Generated due dates.
   * @param {number} installmentValue Installment amount.
   */
  function renderInstallmentsPreview(dueDates, installmentValue) {
    if (!installmentsPreview) {
      return;
    }

    clearInstallmentsPreview();

    if (!recurrenceInput.checked || dueDates.length === 0 || !Number.isFinite(installmentValue) || installmentValue <= 0) {
      return;
    }

    const title = document.createElement("div");
    title.className = "financial-operation-recurrence-installments-title";
    title.textContent = "Parcelas geradas";

    const list = document.createElement("div");
    list.className = "financial-operation-recurrence-installments-list";

    const paymentTypeLabel = paymentTypeInput.options[paymentTypeInput.selectedIndex]?.textContent.trim() || "";

    dueDates.forEach((dueDate, index) => {
      const row = document.createElement("div");
      row.className = "financial-operation-recurrence__installment";

      const number = document.createElement("span");
      number.className = "financial-operation-recurrence__installment-number";
      number.textContent = String(index + 1).padStart(2, "0");

      const date = document.createElement("span");
      date.textContent = formatDateDisplay(dueDate);

      const type = document.createElement("span");
      type.textContent = paymentTypeLabel;

      const value = document.createElement("strong");
      value.textContent = moneyFormatter.format(installmentValue);

      row.append(number, date, type, value);
      list.append(row);
    });

    installmentsPreview.append(title, list);
    installmentsPreview.classList.remove("d-none");
  }

  /**
   * Syncs custom validation for recurrence fields.
   * @param {Date[]} dueDates Generated due dates.
   * @param {number} installmentValue Installment amount.
   */
  function syncRecurrenceValidation(dueDates, installmentValue) {
    installmentValueInput.setCustomValidity("");
    paymentTypeInput.setCustomValidity("");
    startDateInput.setCustomValidity("");
    endDateInput.setCustomValidity("");

    if (!recurrenceInput.checked) {
      return;
    }

    const startDate = parseDate(startDateInput.value);
    const endDate = parseDate(endDateInput.value);

    if (!Number.isFinite(installmentValue) || installmentValue <= 0) {
      installmentValueInput.setCustomValidity("Informe um valor de parcela maior que zero.");
    }

    if (!paymentTypeInput.value) {
      paymentTypeInput.setCustomValidity("Informe o tipo do pagamento.");
    }

    if (!startDate) {
      startDateInput.setCustomValidity("Informe a data inicial.");
    }

    if (!endDate) {
      endDateInput.setCustomValidity("Informe a data final.");
    } else if (startDate && endDate < startDate) {
      endDateInput.setCustomValidity("A data final deve ser igual ou posterior à data inicial.");
    } else if (startDate && dueDates.length === 0) {
      endDateInput.setCustomValidity("A faixa selecionada não gerou parcelas.");
    }
  }

  /**
   * Updates total value and preview text.
   * @returns {boolean} Whether recurrence data is valid.
   */
  function updatePreview() {
    const previewLabel = preview.querySelector("span");

    if (!recurrenceInput.checked) {
      if (previewLabel) {
        previewLabel.textContent = "Informe a faixa para calcular as parcelas mensais.";
      }
      clearInstallmentsPreview();
      syncRecurrenceValidation([], 0);
      return true;
    }

    const installmentValue = parseMoney(installmentValueInput.value);
    const dueDates = buildDueDates();
    const total = dueDates.length * installmentValue;
    const isValid = Number.isFinite(installmentValue)
      && installmentValue > 0
      && paymentTypeInput.value !== ""
      && dueDates.length > 0;

    syncRecurrenceValidation(dueDates, installmentValue);

    if (isValid && Number.isFinite(total) && total > 0) {
      totalInput.value = total.toFixed(2);
    } else {
      totalInput.value = "";
    }

    if (!previewLabel) {
      return isValid;
    }

    if (!isValid) {
      previewLabel.textContent = "Informe valor, data inicial e data final para calcular.";
      clearInstallmentsPreview();
      return false;
    }

    previewLabel.textContent = `${dueDates.length} parcela(s) de ${moneyFormatter.format(installmentValue)}. Total: ${moneyFormatter.format(total)}.`;
    renderInstallmentsPreview(dueDates, installmentValue);
    return true;
  }

  /**
   * Syncs recurrence field state.
   */
  function syncRecurrence() {
    const enabled = recurrenceInput.checked;

    recurrenceFields.classList.toggle("d-none", !enabled);
    totalInput.readOnly = enabled;

    recurrenceControls.forEach((field) => {
      field.disabled = !enabled;
      field.required = enabled;
    });

    updatePreview();
  }

  recurrenceInput.addEventListener("change", syncRecurrence);
  installmentValueInput.addEventListener("input", updatePreview);
  paymentTypeInput.addEventListener("change", updatePreview);
  startDateInput.addEventListener("input", () => {
    startDateInput.value = formatDateTextValue(startDateInput.value);
    syncRecurrence();
  });
  endDateInput.addEventListener("input", () => {
    endDateInput.value = formatDateTextValue(endDateInput.value);
    updatePreview();
  });
  startDateInput.addEventListener("change", syncRecurrence);
  endDateInput.addEventListener("change", updatePreview);
  form.addEventListener("submit", async (event) => {
    if (!updatePreview()) {
      event.preventDefault();
      form.reportValidity();
      return;
    }

    if (!ajaxSubmit) {
      return;
    }

    event.preventDefault();

    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
      submitButton.disabled = true;
    }
    setInlineStatus("info", "Salvando operação...");

    try {
      const response = await fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        headers: {
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
      });
      const payload = await response.json();

      if (!response.ok) {
        throw new Error(payload.message || "Não foi possível cadastrar a operação financeira.");
      }

      window.dispatchEvent(new CustomEvent("financial-operation:create-success", {
        detail: {
          payload,
          formData: Object.fromEntries(new FormData(form).entries()),
        },
      }));
      setInlineStatus("success", "Operação cadastrada com sucesso.");
    } catch (error) {
      setInlineStatus("danger", error.message || "Não foi possível cadastrar a operação financeira.");
      if (submitButton) {
        submitButton.disabled = false;
      }
    }
  });

  syncRecurrence();
});

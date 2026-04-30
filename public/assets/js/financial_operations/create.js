document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("financialOperationCreateForm");

  if (!form) {
    return;
  }

  const totalInput = document.getElementById("total_value");
  const recurrenceInput = document.getElementById("generate_installments");
  const recurrenceFields = document.getElementById("recurrenceFields");
  const installmentValueInput = document.getElementById("installment_value");
  const startDateInput = document.getElementById("recurrence_start_date");
  const endDateInput = document.getElementById("recurrence_end_date");
  const preview = document.getElementById("recurrencePreview");
  const recurrenceControls = form.querySelectorAll("[data-recurrence-field]");
  const moneyFormatter = new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL",
  });

  /**
   * Parses a date input as a local date.
   * @param {string} value Date in YYYY-MM-DD format.
   * @returns {Date|null}
   */
  function parseDate(value) {
    if (!value) {
      return null;
    }

    const parts = value.split("-").map((part) => Number.parseInt(part, 10));
    if (parts.length !== 3 || parts.some((part) => !Number.isFinite(part))) {
      return null;
    }

    return new Date(parts[0], parts[1] - 1, parts[2]);
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
      endDateInput.value = formatDateInput(maxEndDate);
    }

    const preferredDay = startDate.getDate();
    const dueDates = [];

    for (let monthOffset = 0; monthOffset <= 60; monthOffset++) {
      const candidateDate = addMonthsKeepingPreferredDay(startDate, monthOffset, preferredDay);
      if (candidateDate > endDate) {
        break;
      }

      dueDates.push(moveToBusinessDay(candidateDate));
    }

    return dueDates;
  }

  /**
   * Updates total value and preview text.
   */
  function updatePreview() {
    if (!recurrenceInput.checked) {
      preview.querySelector("span").textContent = "Informe a faixa para calcular as parcelas mensais.";
      return;
    }

    const installmentValue = Number.parseFloat(installmentValueInput.value || "0");
    const dueDates = buildDueDates();
    const total = dueDates.length * installmentValue;

    if (Number.isFinite(total) && total > 0) {
      totalInput.value = total.toFixed(2);
    } else {
      totalInput.value = "";
    }

    const previewLabel = preview.querySelector("span");
    if (!previewLabel) {
      return;
    }

    if (!Number.isFinite(installmentValue) || installmentValue <= 0 || dueDates.length === 0) {
      previewLabel.textContent = "Informe valor, data inicial e data final para calcular.";
      return;
    }

    previewLabel.textContent = `${dueDates.length} parcela(s) de ${moneyFormatter.format(installmentValue)}. Total: ${moneyFormatter.format(total)}.`;
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

    if (enabled && startDateInput.value) {
      const startDate = parseDate(startDateInput.value);
      if (startDate) {
        const maxEndDate = new Date(startDate);
        maxEndDate.setFullYear(maxEndDate.getFullYear() + 5);
        endDateInput.min = startDateInput.value;
        endDateInput.max = formatDateInput(maxEndDate);
      }
    } else {
      endDateInput.removeAttribute("min");
      endDateInput.removeAttribute("max");
    }

    updatePreview();
  }

  recurrenceInput.addEventListener("change", syncRecurrence);
  installmentValueInput.addEventListener("input", updatePreview);
  startDateInput.addEventListener("change", syncRecurrence);
  endDateInput.addEventListener("change", updatePreview);

  syncRecurrence();
});

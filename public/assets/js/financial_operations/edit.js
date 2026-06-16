document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("financialOperationEditForm");

  if (!form) {
    return;
  }

  const categoryInput = document.getElementById("financial_operation_category_id");
  const subcategoryInput = document.getElementById("financial_operation_subcategory_id");
  const totalInput = document.getElementById("total_value");
  const recurrenceInput = document.getElementById("generate_installments");
  const recurrenceFields = document.getElementById("recurrenceFields");
  const recurrenceWarning = document.getElementById("recurrenceWarning");
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
   * Shows only subcategories that belong to the selected category.
   */
  function syncSubcategoryOptions() {
    if (!categoryInput || !subcategoryInput) {
      return;
    }

    const categoryId = categoryInput.value;
    subcategoryInput.disabled = categoryId === "";

    Array.from(subcategoryInput.options).forEach((option) => {
      if (option.value === "") {
        option.hidden = false;
        option.disabled = false;
        return;
      }

      const belongsToCategory = option.dataset.categoryId === categoryId;
      option.hidden = !belongsToCategory;
      option.disabled = !belongsToCategory;
    });

    if (subcategoryInput.selectedOptions.length > 0 && subcategoryInput.selectedOptions[0].disabled) {
      subcategoryInput.value = "";
    }
  }

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
   * Returns a comparable month index for a date.
   * @param {Date} date Date to index.
   * @returns {number}
   */
  function getMonthIndex(date) {
    return (date.getFullYear() * 12) + date.getMonth();
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
   * Updates total value and preview text.
   */
  function updatePreview() {
    const previewLabel = preview.querySelector("span");

    if (!recurrenceInput.checked) {
      if (previewLabel) {
        previewLabel.textContent = "Ative para substituir os pagamentos por uma nova recorrência mensal.";
      }
      return;
    }

    const installmentValue = Number.parseFloat(installmentValueInput.value || "0");
    const dueDates = buildDueDates();
    const total = dueDates.length * installmentValue;

    if (Number.isFinite(total) && total > 0) {
      totalInput.value = total.toFixed(2);
    }

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
    if (recurrenceWarning) {
      recurrenceWarning.classList.toggle("d-none", !enabled);
    }
    totalInput.readOnly = enabled;

    recurrenceControls.forEach((field) => {
      field.disabled = !enabled;
      field.required = enabled;
    });

    updatePreview();
  }

  recurrenceInput.addEventListener("change", syncRecurrence);
  if (categoryInput) {
    categoryInput.addEventListener("change", syncSubcategoryOptions);
  }
  installmentValueInput.addEventListener("input", updatePreview);
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

  syncSubcategoryOptions();
  syncRecurrence();
});

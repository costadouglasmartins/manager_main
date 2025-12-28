/**
 * Popover
 */
const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]')
const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl))

/**
 * Applies a mask in CPF's and CNPJ's
 * @param {String} value 
 * @returns String formatted
 */
function formatCpfCnpj(value) 
  {
  const digits = String(value).replace(/\D/g, "").slice(0, 14);
  const pattern = digits.length <= 11 ? "###.###.###-##" : "##.###.###/####-##";
  let out = "";
  let di = 0;
  for (let i = 0; i < pattern.length && di < digits.length; i++) 
    {
    const p = pattern[i];
    if (p === "#") 
      { 
      out += digits[di++]; 
      } 
    else 
      {
      out += p;
      }
    }
  return out;
  }

/**
 * Validates a CPF or a CNPJ
 * @param {String} value 
 * @returns Boolean
 */
function isValidCpfCnpj(value) 
  {
  const digits = String(value).replace(/\D/g, "");
  const isRepeated = (s) => /^(\d)\1+$/.test(s);

  const isValidCPF = (cpf) => {
    if (cpf.length !== 11) return false;
    if (isRepeated(cpf)) return false;

    let sum = 0;
    for (let i = 0; i < 9; i++) sum += Number(cpf[i]) * (10 - i);
    let dv1 = (sum * 10) % 11;
    if (dv1 === 10) dv1 = 0;
    if (dv1 !== Number(cpf[9])) return false;

    sum = 0;
    for (let i = 0; i < 10; i++) sum += Number(cpf[i]) * (11 - i);
    let dv2 = (sum * 10) % 11;
    if (dv2 === 10) dv2 = 0;
    return dv2 === Number(cpf[10]);
  };

  const isValidCNPJ = (cnpj) => {
    if (cnpj.length !== 14) return false;
    if (isRepeated(cnpj)) return false;

    const calcDv = (base) => {
      const weights = base.length === 12
        ? [5,4,3,2,9,8,7,6,5,4,3,2]
        : [6,5,4,3,2,9,8,7,6,5,4,3,2];

      let sum = 0;
      for (let i = 0; i < weights.length; i++) sum += Number(base[i]) * weights[i];
      const mod = sum % 11;
      return mod < 2 ? 0 : (11 - mod);
    };

    const base12 = cnpj.slice(0, 12);
    const dv1 = calcDv(base12);
    if (dv1 !== Number(cnpj[12])) return false;

    const base13 = cnpj.slice(0, 13);
    const dv2 = calcDv(base13);
    return dv2 === Number(cnpj[13]);
  };

  if (digits.length === 11) return isValidCPF(digits);
  if (digits.length === 14) return isValidCNPJ(digits);
  return false;
  }
function formatDatePtBr(value) {
  const digits = String(value).replace(/\D/g, "").slice(0, 8); // DDMMYYYY
    const pattern = "##/##/####";

    let out = "";
    let di = 0;

    for (let i = 0; i < pattern.length && di < digits.length; i++) {
      const p = pattern[i];
      if (p === "#") out += digits[di++];
      else out += p;
    }

    return out;
  }

  function isValidDatePtBr(value) {
    const digits = String(value).replace(/\D/g, "");
    if (digits.length !== 8) return false; // exige DDMMYYYY completo

    const day = Number(digits.slice(0, 2));
    const month = Number(digits.slice(2, 4));
    const year = Number(digits.slice(4, 8));

    if (year < 1000 || year > 9999) return false;
    if (month < 1 || month > 12) return false;

    const isLeapYear = (y) => (y % 4 === 0 && y % 100 !== 0) || (y % 400 === 0);

    const daysInMonth = (m, y) => {
      if (m === 2) return isLeapYear(y) ? 29 : 28;
      if ([4, 6, 9, 11].includes(m)) return 30;
      return 31;
    };

    const maxDay = daysInMonth(month, year);
    return day >= 1 && day <= maxDay;
  }

  document.querySelectorAll(".mask-date").forEach((el) => {
    el.addEventListener("input", () => {
      el.value = formatDatePtBr(el.value);

      // valida "de verdade" só quando completar 10 chars (DD/MM/AAAA)
      if (el.value.length === 10) {
        el.setCustomValidity(isValidDatePtBr(el.value) ? "" : "Data inválida.");
      } else {
        el.setCustomValidity(""); // não acusa erro durante digitação parcial
      }
    });

    el.addEventListener("blur", () => {
      // no blur, valida mesmo se já estiver completo
      if (el.value.length === 10) {
        el.setCustomValidity(isValidDatePtBr(el.value) ? "" : "Data inválida.");
      } else if (el.value.length > 0) {
        el.setCustomValidity("Data incompleta. Use DD/MM/AAAA.");
      } else {
        el.setCustomValidity("");
      }
    });
  });
// Aplica em todos os inputs com a classe:
document.querySelectorAll(".mask-cpf-cnpj").forEach((el) => {
  el.addEventListener("input", () => {
    el.value = formatCpfCnpj(el.value);
  });

  el.addEventListener("blur", () => {
  const ok = isValidCpfCnpj(el.value);
  el.style.outline = ok ? "2px dotted green" : "2px dotted red";
  });
});
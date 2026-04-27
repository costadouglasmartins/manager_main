// -----------------------------------------------------------------------
// Máscara CEP (aplicada em linhas dinâmicas via delegação)
// -----------------------------------------------------------------------
function applyCepMask(value)
  {
    const digits = value.replace(/\D/g, "").slice(0, 8);
    return digits.replace(/(\d{5})(\d{1,3})$/, "$1-$2");
  }

document.addEventListener("input", function (e)
  {
    if (e.target && e.target.classList.contains("mask-cep"))
      {
        const el   = e.target;
        const pos  = el.selectionStart;
        const prev = el.value;
        el.value   = applyCepMask(el.value);
        const diff = el.value.length - prev.length;
        el.setSelectionRange(pos + diff, pos + diff);
      }
  });

// -----------------------------------------------------------------------
// Máscara CPF / CNPJ
// -----------------------------------------------------------------------
function applyCpfCnpjMask(value)
  {
    const digits = value.replace(/\D/g, "").slice(0, 14);
    if (digits.length <= 11)
      {
        return digits
          .replace(/(\d{3})(\d)/, "$1.$2")
          .replace(/(\d{3})(\d)/, "$1.$2")
          .replace(/(\d{3})(\d{1,2})$/, "$1-$2");
      }
    return digits
      .replace(/(\d{2})(\d)/, "$1.$2")
      .replace(/(\d{3})(\d)/, "$1.$2")
      .replace(/(\d{3})(\d)/, "$1/$2")
      .replace(/(\d{4})(\d{1,2})$/, "$1-$2");
  }

document.querySelectorAll(".mask-cpf-cnpj").forEach(function (el)
  {
    el.addEventListener("input", function ()
      {
        const pos  = el.selectionStart;
        const prev = el.value;
        el.value   = applyCpfCnpjMask(el.value);
        const diff = el.value.length - prev.length;
        el.setSelectionRange(pos + diff, pos + diff);
      });
  });
(function (window, document)
  {
  "use strict";

  function onlyDigits(value)
    {
    return String(value || "").replace(/\D/g, "");
    }

  function formatZipCode(value)
    {
    const digits = onlyDigits(value).slice(0, 8);
    return digits.replace(/(\d{5})(\d{1,3})$/, "$1-$2");
    }

  function findElement(selector)
    {
    if (!selector)
      {
      return null;
      }

    try
      {
      return document.querySelector(selector);
      }
    catch (err)
      {
      return null;
      }
    }

  function setFieldValue(selector, value, shouldDispatch)
    {
    const field = findElement(selector);
    if (!field)
      {
      return;
      }

    field.value = value || "";

    if (shouldDispatch)
      {
      field.dispatchEvent(new Event("input", { bubbles: true }));
      field.dispatchEvent(new Event("change", { bubbles: true }));
      }
    }

  function setStatus(input, message, type)
    {
    const status = findElement(input.dataset.viacepStatus || "");
    if (!status)
      {
      return;
      }

    status.textContent = message || "";
    status.className = "form-text";

    if (type === "error")
      {
      status.classList.add("text-danger");
      return;
      }

    if (type === "success")
      {
      status.classList.add("text-success");
      return;
      }

    if (type === "warning")
      {
      status.classList.add("text-warning");
      return;
      }

    status.classList.add("text-muted");
    }

  async function lookupZipCode(zipCode, options)
    {
    const settings = options || {};
    const endpoint = settings.endpoint || window.ViaCepLookup.endpoint || "";
    const digits = onlyDigits(zipCode);

    if (digits.length !== 8)
      {
      throw new Error("Informe um CEP válido com 8 dígitos.");
      }

    if (!endpoint)
      {
      throw new Error("Endpoint de consulta de CEP não configurado.");
      }

    const url = new URL(endpoint, window.location.origin);
    url.searchParams.set("zip_code", digits);

    const response = await fetch(url.toString(), {
      method: "GET",
      credentials: "same-origin",
      headers: {
        "Accept": "application/json",
        "X-Requested-With": "XMLHttpRequest"
      }
    });

    const payload = await response.json();
    if (!response.ok)
      {
      throw new Error(payload.message || "Não foi possível consultar o CEP.");
      }

    return payload.data || payload;
    }

  function fillAddressFields(input, data)
    {
    input.value = data.zip_code || formatZipCode(input.value);

    setFieldValue(input.dataset.viacepStreet, data.street || "", true);
    setFieldValue(input.dataset.viacepComplement, data.complement || "", true);
    setFieldValue(input.dataset.viacepNeighborhood, data.neighborhood_label || data.neighborhood || "", true);
    setFieldValue(input.dataset.viacepNeighborhoodId, data.neighborhood_id || "", true);
    setFieldValue(input.dataset.viacepCity, data.city || "", true);
    setFieldValue(input.dataset.viacepState, data.state || "", true);

    input.dispatchEvent(new CustomEvent("viacep:found", { detail: data }));
    }

  async function runLookup(input)
    {
    const digits = onlyDigits(input.value);

    if (digits.length === 0)
      {
      setStatus(input, "", "muted");
      return;
      }

    if (digits.length !== 8)
      {
      setStatus(input, "Informe um CEP válido com 8 dígitos.", "error");
      return;
      }

    setStatus(input, "Consultando CEP...", "muted");

    try
      {
      const data = await lookupZipCode(digits, {
        endpoint: input.dataset.viacepEndpoint || ""
      });

      fillAddressFields(input, data);

      if (data.neighborhood && !data.neighborhood_id && input.dataset.viacepNeighborhoodId)
        {
        setStatus(input, "CEP encontrado. Selecione o bairro na lista antes de salvar.", "warning");
        return;
        }

      setStatus(input, "Endereço preenchido pelo CEP.", "success");
      }
    catch (err)
      {
      setStatus(input, err.message || "Não foi possível consultar o CEP.", "error");
      input.dispatchEvent(new CustomEvent("viacep:error", { detail: err }));
      }
    }

  function bindZipInput(input)
    {
    if (input.dataset.viacepBound === "1")
      {
      return;
      }

    input.dataset.viacepBound = "1";

    input.addEventListener("blur", function ()
      {
      runLookup(input);
      });

    input.addEventListener("keydown", function (event)
      {
      if (event.key === "Enter")
        {
        event.preventDefault();
        runLookup(input);
        }
      });
    }

  function bindAddressLookup(root)
    {
    const scope = root || document;
    scope.querySelectorAll('[data-viacep="zip"]').forEach(bindZipInput);
    }

  window.ViaCepLookup = {
    endpoint: "",
    bindAddressLookup: bindAddressLookup,
    formatZipCode: formatZipCode,
    lookupZipCode: lookupZipCode,
    onlyDigits: onlyDigits
  };

  document.addEventListener("DOMContentLoaded", function ()
    {
    bindAddressLookup(document);
    });
  })(window, document);

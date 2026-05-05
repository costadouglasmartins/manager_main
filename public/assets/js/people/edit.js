(function ()
  {
  const form = document.getElementById("personEditForm");
  if (!form)
    {
    return;
    }

  function addFromTemplate(templateId, containerId, counterName, initializer)
    {
    const template = document.getElementById(templateId);
    const container = document.getElementById(containerId);
    if (!template || !container)
      {
      return;
      }

    let index = 0;
    if (counterName === "address")
      {
      index = addressCounter++;
      }
    else if (counterName === "phone")
      {
      index = phoneCounter++;
      }
    else if (counterName === "email")
      {
      index = emailCounter++;
      }
    else
      {
      index = tenantLinkCounter++;
      }

    const html = template.innerHTML.split("__INDEX__").join(index);
    const wrapper = document.createElement("div");
    wrapper.innerHTML = html.trim();
    const block = wrapper.firstElementChild;
    container.appendChild(block);

    if (initializer)
      {
      initializer(block);
      }
    }

  function clearList(listEl)
    {
    listEl.innerHTML = "";
    listEl.style.display = "none";
    }

  function initMiniAutocomplete(endpoint, inputEl, listEl, onSelect, extraOption)
    {
    if (!endpoint || !inputEl || !listEl)
      {
      return;
      }

    let abort = null;

    inputEl.addEventListener("input", async function ()
      {
      const term = inputEl.value.trim();
      if (term.length < 1)
        {
        clearList(listEl);
        return;
        }

      if (abort)
        {
        abort.abort();
        }
      abort = new AbortController();

      try
        {
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set("term", term);
        const res = await fetch(url.toString(), {
          method: "GET",
          credentials: "same-origin",
          headers: {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest"},
          signal: abort.signal
        });
        const data = await res.json();
        const items = data.items || [];
        listEl.innerHTML = "";

        items.forEach(function (item)
          {
          const li = document.createElement("li");
          li.className = "list-group-item list-group-item-action py-1 px-2";
          li.textContent = item.label;
          li.addEventListener("mousedown", function (e)
            {
            e.preventDefault();
            onSelect(item);
            clearList(listEl);
            inputEl.value = item.name || item.label;
            });
          listEl.appendChild(li);
          });

        if (extraOption)
          {
          const li = document.createElement("li");
          li.className = "list-group-item list-group-item-action py-1 px-2 text-primary fw-semibold";
          li.textContent = extraOption.label(term);
          li.addEventListener("mousedown", function (e)
            {
            e.preventDefault();
            clearList(listEl);
            extraOption.onClick(term);
            });
          listEl.appendChild(li);
          }

        listEl.style.display = listEl.children.length > 0 ? "block" : "none";
        }
      catch (err)
        {
        if (err && err.name !== "AbortError")
          {
          console.error("miniAutocomplete fetch error:", err);
          }
        }
      });

    document.addEventListener("click", function (e)
      {
      if (e.target !== inputEl && !listEl.contains(e.target))
        {
        clearList(listEl);
        }
      });
    }

  function initAddressAutocomplete(block)
    {
    const hiddenId = block.querySelector(".addr-neighborhood-id");
    const textInput = block.querySelector(".addr-neighborhood-input");
    const resultsList = block.querySelector(".addr-neighborhood-results");
    const createPanel = block.querySelector(".addr-create-panel");
    const newNameInput = block.querySelector(".addr-new-neighborhood-name");
    const newCityId = block.querySelector(".addr-new-city-id");
    const newCityInput = block.querySelector(".addr-new-city-input");
    const newCityList = block.querySelector(".addr-new-city-results");
    const btnSave = block.querySelector(".addr-btn-save-neighborhood");
    const btnCancel = block.querySelector(".addr-btn-cancel-neighborhood");

    if (!hiddenId || !textInput || !resultsList || !createPanel)
      {
      return;
      }

    function showCreatePanel(term)
      {
      createPanel.classList.remove("d-none");
      newNameInput.value = term;
      newCityId.value = "";
      newCityInput.value = "";
      textInput.value = "";
      hiddenId.value = "";
      }

    function hideCreatePanel()
      {
      createPanel.classList.add("d-none");
      newNameInput.value = "";
      newCityId.value = "";
      newCityInput.value = "";
      }

    initMiniAutocomplete(
      PERSON_NEIGHBORHOOD_SEARCH_URL,
      textInput,
      resultsList,
      function (item)
        {
        hiddenId.value = item.id;
        textInput.value = item.label;
        hideCreatePanel();
        },
      {
        label: function (term) { return "+ Criar bairro: \"" + term + "\""; },
        onClick: function (term) { showCreatePanel(term); }
      }
    );

    initMiniAutocomplete(
      PERSON_CITY_SEARCH_URL,
      newCityInput,
      newCityList,
      function (item)
        {
        newCityId.value = item.id;
        newCityInput.value = item.label;
        },
      null
    );

    btnSave.addEventListener("click", async function ()
      {
      const name = newNameInput.value.trim();
      const cityId = newCityId.value.trim();

      if (!name || !cityId)
        {
        return;
        }

      btnSave.disabled = true;
      btnSave.textContent = "Salvando...";

      try
        {
        const body = new URLSearchParams({name: name, city_id: cityId});
        const res = await fetch(PERSON_NEIGHBORHOOD_CREATE_URL, {
          method: "POST",
          credentials: "same-origin",
          headers: {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest", "Content-Type": "application/x-www-form-urlencoded"},
          body: body.toString()
        });
        const data = await res.json();

        if (data.error)
          {
          alert("Erro: " + data.error);
          return;
          }

        hiddenId.value = data.id;
        textInput.value = data.name;
        hideCreatePanel();
        }
      catch (err)
        {
        alert("Erro de comunicação ao salvar bairro.");
        console.error(err);
        }
      finally
        {
        btnSave.disabled = false;
        btnSave.textContent = "Salvar";
        }
      });

    btnCancel.addEventListener("click", function ()
      {
      hideCreatePanel();
      });
    }

  document.querySelectorAll("[data-address-item]").forEach(initAddressAutocomplete);

  document.getElementById("btnAddAddress")?.addEventListener("click", function ()
    {
    addFromTemplate("addressTemplate", "addressesContainer", "address", initAddressAutocomplete);
    });

  document.getElementById("btnAddPhone")?.addEventListener("click", function ()
    {
    addFromTemplate("phoneTemplate", "phonesContainer", "phone");
    });

  document.getElementById("btnAddEmail")?.addEventListener("click", function ()
    {
    addFromTemplate("emailTemplate", "emailsContainer", "email");
    });

  document.getElementById("btnAddTenantLink")?.addEventListener("click", function ()
    {
    addFromTemplate("tenantLinkTemplate", "tenantLinksContainer", "tenantLink");
    });

  document.addEventListener("click", function (e)
    {
    const btn = e.target instanceof HTMLElement ? e.target.closest("[data-remove]") : null;
    if (!btn)
      {
      return;
      }

    const block = btn.closest("[data-repeat-item]");
    if (!block)
      {
      return;
      }

    const deleteInput = block.querySelector("input[name$='[_delete]']");
    if (block.getAttribute("data-existing") === "1" && deleteInput)
      {
      deleteInput.value = "1";
      block.classList.add("d-none");
      return;
      }

    block.remove();
    });

  function applyCepMask(value)
    {
    const digits = value.replace(/\D/g, "").slice(0, 8);
    return digits.replace(/(\d{5})(\d{1,3})$/, "$1-$2");
    }

  function applyIntlPhoneMask(value)
    {
    const hasExplicitPlus = String(value || "").trim().startsWith("+");
    const digits = String(value || "").replace(/\D/g, "").slice(0, 14);
    if (!digits)
      {
      return "";
      }

    let countryCode = "55";
    let nationalNumber = digits;

    if (digits === "55" && hasExplicitPlus)
      {
      countryCode = "55";
      nationalNumber = "";
      }
    else if (digits.startsWith("55") && (digits.length > 11 || (hasExplicitPlus && digits.length > 2)))
      {
      countryCode = "55";
      nationalNumber = digits.slice(2, 13);
      }
    else if (hasExplicitPlus && digits.length > 11)
      {
      const countryLength = Math.min(3, digits.length - 11);
      countryCode = digits.slice(0, countryLength);
      nationalNumber = digits.slice(countryLength, countryLength + 11);
      }
    else
      {
      nationalNumber = digits.slice(0, 11);
      }

    const areaCode = nationalNumber.slice(0, 2);
    const subscriber = nationalNumber.slice(2);
    let formatted = "+" + countryCode;

    if (areaCode)
      {
      formatted += "(" + areaCode;
      if (areaCode.length === 2)
        {
        formatted += ")";
        }
      }

    if (subscriber)
      {
      const firstPartLength = subscriber.length > 8 ? 5 : 4;
      const firstPart = subscriber.slice(0, firstPartLength);
      const secondPart = subscriber.slice(firstPartLength, firstPartLength + 4);

      formatted += firstPart;
      if (secondPart)
        {
        formatted += "-" + secondPart;
        }
      }

    return formatted;
    }

  function applyMaskedValue(el, maskCallback)
    {
    const pos = el.selectionStart;
    const prev = el.value;
    el.value = maskCallback(el.value);
    const diff = el.value.length - prev.length;
    const nextPos = Math.max(0, (pos || el.value.length) + diff);
    el.setSelectionRange(nextPos, nextPos);
    }

  document.addEventListener("input", function (e)
    {
    if (e.target && e.target.classList.contains("mask-cep"))
      {
      applyMaskedValue(e.target, applyCepMask);
      }

    if (e.target && e.target.classList.contains("mask-phone-intl"))
      {
      applyMaskedValue(e.target, applyIntlPhoneMask);
      }
    });

  form.addEventListener("submit", function (e)
    {
    const password = document.getElementById("userPassword");
    const password2 = document.getElementById("userPassword2");

    if (password && password2)
      {
      password2.setCustomValidity(password.value === password2.value ? "" : "As senhas não coincidem.");
      }

    if (!form.checkValidity())
      {
      e.preventDefault();
      e.stopPropagation();
      form.classList.add("was-validated");
      }
    });
  })();

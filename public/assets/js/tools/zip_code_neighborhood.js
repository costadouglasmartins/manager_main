(function ()
	{
		const app = document.getElementById("zipCodeNeighborhoodApp");
		if (!app)
			{
				return;
			}

		const form = document.getElementById("zipCodeNeighborhoodForm");
		const input = document.getElementById("zipCodeNeighborhoodInput");
		const clearButton = document.getElementById("zipCodeNeighborhoodClear");
		const compareButton = document.getElementById("zipCodeNeighborhoodCompare");
		const saveButton = document.getElementById("zipCodeNeighborhoodSave");
		const message = document.getElementById("zipCodeNeighborhoodMessage");
		const comparisonRoot = document.getElementById("zipCodeNeighborhoodComparison");
		const jsonOutput = document.getElementById("zipCodeNeighborhoodJson");
		const compareUrl = app.dataset.compareUrl || "";
		const saveUrl = app.dataset.saveUrl || "";

		const fields = [
			{key: "neighborhood", label: "Bairro"},
			{key: "city", label: "Cidade"},
			{key: "state", label: "Estado"},
			{key: "street", label: "Logradouro"},
			{key: "complement", label: "Complemento"},
			{key: "state_name", label: "Nome do estado"},
			{key: "region", label: "Região"},
			{key: "ddd", label: "DDD"},
			{key: "ibge", label: "IBGE"},
			{key: "siafi", label: "SIAFI"}
		];

		function onlyDigits(value)
			{
				return String(value || "").replace(/\D+/g, "");
			}

		function formatZipCode(value)
			{
				const digits = onlyDigits(value);
				if (digits.length <= 5)
					{
						return digits;
					}

				return digits.slice(0, 5) + "-" + digits.slice(5, 8);
			}

		function setMessage(type, text)
			{
				message.className = "alert zip-code-neighborhood-message alert-" + type;
				message.textContent = text;
			}

		function setLoading(isLoading)
			{
				compareButton.disabled = isLoading;
				compareButton.innerHTML = isLoading
					? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Comparando'
					: '<i class="bi bi-search me-1"></i>Comparar';

				saveButton.disabled = isLoading;
				if (!isLoading)
					{
						saveButton.innerHTML = '<i class="bi bi-save2 me-1"></i>Salvar seleção';
					}
			}

		function renderJson(payload)
			{
				jsonOutput.textContent = JSON.stringify(payload, null, 2);
			}

		function getProviderBadgeClass(providerKey)
			{
				return providerKey === "viacep" ? "text-bg-primary" : "text-bg-success";
			}

		function getProviderTitle(providerKey)
			{
				return providerKey === "viacep" ? "ViaCEP" : "BrasilAPI";
			}

		function getProviderData(result)
			{
				return result && result.success && result.data ? result.data : {};
			}

		function getCityLookup(provider)
			{
				return provider && provider.city_lookup ? provider.city_lookup : {exists: false, id: null, label: "-"};
			}

		function createFieldRow(fieldKey, fieldLabel, providerKey, providerResult, defaultSelection)
			{
				const providerData = getProviderData(providerResult);
				const value = providerData[fieldKey] || "";
				const fieldId = providerKey + "_" + fieldKey;
				const checked = defaultSelection === providerKey && value !== "";
				const row = document.createElement("div");
				row.className = "zip-code-neighborhood-field-row";

				const left = document.createElement("div");
				const label = document.createElement("span");
				label.className = "zip-code-neighborhood-field-label";
				label.textContent = fieldLabel;

				const fieldValue = document.createElement("strong");
				fieldValue.className = "zip-code-neighborhood-field-value";
				fieldValue.textContent = value || "-";

				left.appendChild(label);
				left.appendChild(fieldValue);

				const right = document.createElement("div");
				right.className = "zip-code-neighborhood-field-check form-check";

				const checkbox = document.createElement("input");
				checkbox.className = "form-check-input";
				checkbox.type = "checkbox";
				checkbox.name = "selection[" + fieldKey + "]";
				checkbox.value = providerKey;
				checkbox.id = fieldId;
				checkbox.checked = checked;
				checkbox.dataset.fieldKey = fieldKey;
				checkbox.dataset.providerKey = providerKey;
				checkbox.disabled = value === "";

				checkbox.addEventListener("change", function ()
					{
						if (!checkbox.checked)
							{
								return;
							}

						const group = form.querySelectorAll('input[data-field-key="' + fieldKey + '"]');
						group.forEach(function (item)
							{
								if (item !== checkbox)
									{
										item.checked = false;
									}
							});

						updateCityCreateState();
					});

				const checkboxLabel = document.createElement("label");
				checkboxLabel.className = "form-check-label";
				checkboxLabel.setAttribute("for", fieldId);
				checkboxLabel.textContent = "Usar";

				right.appendChild(checkbox);
				right.appendChild(checkboxLabel);
				row.appendChild(left);
				row.appendChild(right);

				return row;
			}

		function createProviderCard(providerKey, providerResult, defaultSelection)
			{
				const provider = providerResult || {};
				const providerData = getProviderData(provider);
				const cityLookup = getCityLookup(provider);
				const card = document.createElement("article");
				card.className = "zip-code-neighborhood-provider";

				const header = document.createElement("div");
				header.className = "zip-code-neighborhood-provider-header";

				const titleWrap = document.createElement("div");
				const title = document.createElement("strong");
				title.className = "zip-code-neighborhood-provider-title";
				title.textContent = getProviderTitle(providerKey);

				const status = document.createElement("small");
				status.className = "text-secondary d-block";
				status.textContent = provider.success ? "Consulta realizada com sucesso" : (provider.message || "Consulta indisponível");

				titleWrap.appendChild(title);
				titleWrap.appendChild(status);

				const badge = document.createElement("span");
				badge.className = "badge " + getProviderBadgeClass(providerKey);
				badge.textContent = provider.success ? "OK" : "Falhou";

				header.appendChild(titleWrap);
				header.appendChild(badge);
				card.appendChild(header);

				const meta = document.createElement("div");
				meta.className = "zip-code-neighborhood-meta";

				const metaItems = [
					["CEP", providerData.zip_code || "-"],
					["Cidade", providerData.city || "-"],
					["Estado", (providerData.state || "-") + (providerData.state_name ? " - " + providerData.state_name : "")],
					["Bairro", providerData.neighborhood || "-"],
					["Cidade na base", cityLookup.exists ? ("Sim, ID " + cityLookup.id) : "Não encontrada"]
				];

				metaItems.forEach(function (item)
					{
						const metaCard = document.createElement("div");
						metaCard.className = "zip-code-neighborhood-meta-card";

						const small = document.createElement("small");
						small.textContent = item[0];

						const strong = document.createElement("strong");
						strong.textContent = item[1];

						metaCard.appendChild(small);
						metaCard.appendChild(strong);
						meta.appendChild(metaCard);
					});

				card.appendChild(meta);

				const fieldList = document.createElement("div");
				fieldList.className = "zip-code-neighborhood-field-list";

				fields.forEach(function (field)
					{
						fieldList.appendChild(createFieldRow(field.key, field.label, providerKey, provider, defaultSelection[field.key] || ""));
					});

				card.appendChild(fieldList);
				return card;
			}

		function updateCityCreateState()
			{
				const selectedCity = form.querySelector('input[name="selection[city]"]:checked');
				if (!selectedCity)
					{
						saveButton.classList.add("d-none");
						return;
					}

				saveButton.classList.remove("d-none");

				const providerKey = selectedCity.value;
				const providerCard = comparisonRoot.querySelector('[data-provider-key="' + providerKey + '"]');
				const cityExists = providerCard ? providerCard.dataset.cityExists === "1" : false;
				const createCity = document.getElementById("createCitySwitch");
				createCity.disabled = cityExists;
				if (cityExists)
					{
						createCity.checked = false;
					}
			}

		function renderComparison(payload)
			{
				comparisonRoot.innerHTML = "";
				if (!payload || !payload.providers)
					{
						saveButton.classList.add("d-none");
						return;
					}

				const providers = payload.providers;
				const defaults = payload.defaults || {};
				const grid = document.createElement("div");
				grid.className = "zip-code-neighborhood-grid";

				["viacep", "brasilapi"].forEach(function (providerKey)
					{
						const providerCard = createProviderCard(providerKey, providers[providerKey] || {}, defaults);
						providerCard.dataset.providerKey = providerKey;
						providerCard.dataset.cityExists = getCityLookup(providers[providerKey] || {}).exists ? "1" : "0";
						grid.appendChild(providerCard);
					});

				comparisonRoot.appendChild(grid);
				updateCityCreateState();
			}

		function bindZipMask()
			{
				input.addEventListener("input", function ()
					{
						const formatted = formatZipCode(input.value);
						if (formatted !== input.value)
							{
								input.value = formatted;
							}
					});
			}

		function submitCompare()
			{
				const zipCode = onlyDigits(input.value);
				if (zipCode.length !== 8)
					{
						setMessage("warning", "Informe um CEP válido com 8 dígitos.");
						input.focus();
						return;
					}

				setLoading(true);
				setMessage("info", "Comparando ViaCEP e BrasilAPI...");
				renderJson({});
				renderComparison(null);

				fetch(compareUrl, {
					method: "POST",
					headers: {
						"Accept": "application/json",
						"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
						"X-Requested-With": "XMLHttpRequest"
					},
					body: new URLSearchParams({zip_code: zipCode})
				})
					.then(function (response)
						{
							return response.json().then(function (payload)
								{
									return {ok: response.ok, payload: payload};
								});
						})
					.then(function (response)
						{
							const result = response.payload || {};
							renderJson(result);

							if (result.success)
								{
									renderComparison(result.data || {});
									setMessage("success", result.message || "CEP comparado com sucesso.");
									return;
								}

							setMessage(response.ok ? "warning" : "danger", result.message || "Não foi possível comparar o CEP.");
						})
					.catch(function ()
						{
							setMessage("danger", "Não foi possível comparar o CEP agora.");
							renderJson({error: "request_failed"});
						})
					.finally(function ()
						{
							setLoading(false);
						});
			}

		function submitSave()
			{
				const zipCode = onlyDigits(input.value);
				if (zipCode.length !== 8)
					{
						setMessage("warning", "Informe um CEP válido com 8 dígitos.");
						return;
					}

				const formData = new FormData();
				formData.append("zip_code", zipCode);
				formData.append("create_city", document.getElementById("createCitySwitch").checked ? "1" : "0");

				const selectedInputs = form.querySelectorAll('input[name^="selection["]:checked');
				selectedInputs.forEach(function (fieldInput)
					{
						formData.append(fieldInput.name, fieldInput.value);
					});

				setLoading(true);
				setMessage("info", "Salvando cidade e bairro selecionados...");

				fetch(saveUrl, {
					method: "POST",
					headers: {
						"Accept": "application/json",
						"X-Requested-With": "XMLHttpRequest"
					},
					body: formData
				})
					.then(function (response)
						{
							return response.json().then(function (payload)
								{
									return {ok: response.ok, payload: payload};
								});
						})
					.then(function (response)
						{
							const result = response.payload || {};
							renderJson(result);
							if (result.success)
								{
									setMessage("success", result.message || "Dados salvos com sucesso.");
									return;
								}

							setMessage(response.ok ? "warning" : "danger", result.message || "Não foi possível salvar a seleção.");
						})
					.catch(function ()
						{
							setMessage("danger", "Não foi possível salvar os dados agora.");
							renderJson({error: "request_failed"});
						})
					.finally(function ()
						{
							setLoading(false);
						});
			}

		form.addEventListener("submit", function (event)
			{
				event.preventDefault();
				submitCompare();
			});

		saveButton.addEventListener("click", function ()
			{
				submitSave();
			});

		clearButton.addEventListener("click", function ()
			{
				input.value = "";
				document.getElementById("createCitySwitch").checked = false;
				document.getElementById("createCitySwitch").disabled = false;
				renderComparison(null);
				renderJson({});
				setMessage("secondary", "Informe um CEP para comparar os dados dos dois serviços.");
				saveButton.classList.add("d-none");
				input.focus();
			});

		document.getElementById("createCitySwitch").addEventListener("change", function ()
			{
				if (this.disabled)
					{
						this.checked = false;
					}
			});

		bindZipMask();
	})();

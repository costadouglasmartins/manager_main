(function ()
	{
		const app = document.getElementById("zipCodeSearchApp");
		if (!app)
			{
				return;
			}

		const form = document.getElementById("zipCodeSearchForm");
		const input = document.getElementById("zipCodeInput");
		const clearButton = document.getElementById("zipCodeClear");
		const submitButton = document.getElementById("zipCodeSubmit");
		const message = document.getElementById("zipCodeMessage");
		const summary = document.getElementById("zipCodeSummary");
		const jsonOutput = document.getElementById("zipCodeJson");
		const searchUrl = app.dataset.searchUrl || "";

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
				message.className = "alert zip-code-search-message alert-" + type;
				message.textContent = text;
			}

		function setLoading(isLoading)
			{
				submitButton.disabled = isLoading;
				submitButton.innerHTML = isLoading
					? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Pesquisando'
					: '<i class="bi bi-search me-1"></i>Pesquisar';
			}

		function renderJson(payload)
			{
				jsonOutput.textContent = JSON.stringify(payload, null, 2);
			}

		function createSummaryCard(title, entries, accentClass)
			{
				const card = document.createElement("article");
				card.className = "zip-code-search-summary-card";

				const header = document.createElement("div");
				header.className = "zip-code-search-summary-header";

				const heading = document.createElement("strong");
				heading.textContent = title;

				const badge = document.createElement("span");
				badge.className = "badge " + accentClass;
				badge.textContent = title;

				header.appendChild(heading);
				header.appendChild(badge);
				card.appendChild(header);

				entries.forEach(function (entry)
					{
						const row = document.createElement("div");
						row.className = "zip-code-search-summary-row";

						const label = document.createElement("small");
						label.textContent = entry.label;

						const value = document.createElement("strong");
						value.textContent = entry.value;

						row.appendChild(label);
						row.appendChild(value);
						card.appendChild(row);
					});

				return card;
			}

		function renderSummary(data)
			{
				summary.innerHTML = "";
				summary.classList.toggle("d-none", !data);

				if (!data)
					{
						return;
					}

				const source = String(data.source || "").toUpperCase() || "-";
				const saved = data.saved || {};

				summary.appendChild(createSummaryCard("Lookup", [
					{label: "Fonte", value: source},
					{label: "CEP", value: data.zip_code || "-"},
					{label: "Região", value: data.region || "-"}
				], "text-bg-primary"));

				summary.appendChild(createSummaryCard("Endereço", [
					{label: "Logradouro", value: data.street || "-"},
					{label: "Bairro", value: data.neighborhood || "-"},
					{label: "Complemento", value: data.complement || "-"}
				], "text-bg-success"));

				summary.appendChild(createSummaryCard("Localização", [
					{label: "Cidade", value: data.city || "-"},
					{label: "Estado", value: (data.state || "-") + (data.state_name ? " - " + data.state_name : "")},
					{label: "DDD / IBGE", value: (data.ddd || "-") + " / " + (data.ibge || "-")}
				], "text-bg-secondary"));

				summary.appendChild(createSummaryCard("Base", [
					{label: "Estado", value: saved.state ? (saved.state.id + (saved.state.created ? " (novo)" : " (existente)")) : "-"},
					{label: "Cidade", value: saved.city ? (saved.city.id + (saved.city.created ? " (novo)" : " (existente)")) : "-"},
					{label: "Bairro", value: saved.neighborhood ? (saved.neighborhood.id + (saved.neighborhood.created ? " (novo)" : " (existente)")) : "Não salvo"}
				], "text-bg-warning"));
			}

		input.addEventListener("input", function ()
			{
				const formatted = formatZipCode(input.value);
				if (formatted !== input.value)
					{
						input.value = formatted;
					}
			});

		form.addEventListener("submit", function (event)
			{
				event.preventDefault();

				const zipCode = onlyDigits(input.value);
				if (zipCode.length !== 8)
					{
						setMessage("warning", "Informe um CEP válido com 8 dígitos.");
						input.focus();
						return;
					}

				setLoading(true);
				setMessage("info", "Consultando ViaCEP e, se necessário, BrasilAPI...");
				renderSummary(null);
				renderJson({});

				fetch(searchUrl, {
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
							const data = result.data || {};
							renderJson(result);
							renderSummary(result.success ? data : null);

							if (result.success)
								{
									setMessage("success", result.message || "CEP encontrado.");
									return;
								}

							setMessage(response.ok ? "warning" : "danger", result.message || "Não foi possível consultar o CEP.");
						})
					.catch(function ()
						{
							setMessage("danger", "Não foi possível consultar o CEP agora.");
							renderJson({error: "request_failed"});
						})
					.finally(function ()
						{
							setLoading(false);
						});
			});

		clearButton.addEventListener("click", function ()
			{
				input.value = "";
				renderSummary(null);
				renderJson({});
				setMessage("secondary", "Informe um CEP para consultar os dados e salvar o bairro na base.");
				input.focus();
			});
	})();

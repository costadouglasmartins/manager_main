(function ()
	{
		const app = document.getElementById("emailValidationApp");
		if (!app)
			{
				return;
			}

		const form = document.getElementById("emailValidationForm");
		const input = document.getElementById("emailValidationInput");
		const clearButton = document.getElementById("emailValidationClear");
		const submitButton = document.getElementById("emailValidationSubmit");
		const message = document.getElementById("emailValidationMessage");
		const summary = document.getElementById("emailValidationSummary");
		const jsonOutput = document.getElementById("emailValidationJson");
		const validationUrl = app.dataset.validationUrl || "";

		function setMessage(type, text)
			{
				message.className = "alert email-validation-message alert-" + type;
				message.textContent = text;
			}

		function setLoading(isLoading)
			{
				submitButton.disabled = isLoading;
				submitButton.innerHTML = isLoading
					? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Validando'
					: '<i class="bi bi-search me-1"></i>Validar MX';
			}

		function renderSummary(data)
			{
				summary.innerHTML = "";
				summary.classList.toggle("d-none", !data);
				if (!data)
					{
						return;
					}

				const items = [
					["Sintaxe", data.syntax_valid ? "Válida" : "Inválida", data.syntax_valid ? "text-bg-success" : "text-bg-danger"],
					["Domínio", data.domain_valid ? (data.domain || "-") : "Inválido", data.domain_valid ? "text-bg-success" : "text-bg-danger"],
					["MX", data.mx_valid ? "Encontrado" : "Não encontrado", data.mx_valid ? "text-bg-success" : "text-bg-warning"]
				];

				items.forEach(function (item)
					{
						const card = document.createElement("div");
						card.className = "email-validation-summary-item";

						const label = document.createElement("small");
						label.textContent = item[0];

						const value = document.createElement("strong");
						value.textContent = item[1];

						const badge = document.createElement("span");
						badge.className = "badge " + item[2];
						badge.textContent = item[0];

						card.appendChild(label);
						card.appendChild(value);
						card.appendChild(badge);
						summary.appendChild(card);
					});
			}

		function renderJson(payload)
			{
				jsonOutput.textContent = JSON.stringify(payload, null, 2);
			}

		form.addEventListener("submit", function (event)
			{
				event.preventDefault();

				const email = input.value.trim();
				if (email === "")
					{
						setMessage("warning", "Informe um endereço de e-mail.");
						input.focus();
						return;
					}

				setLoading(true);
				setMessage("info", "Consultando DNS do domínio...");
				renderSummary(null);
				renderJson({});

				fetch(validationUrl, {
					method: "POST",
					headers: {
						"Accept": "application/json",
						"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
						"X-Requested-With": "XMLHttpRequest"
					},
					body: new URLSearchParams({email: email})
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
							const data = response.payload.data || {};
							renderJson(response.payload);
							renderSummary(data);

							if (data.valid)
								{
									setMessage("success", data.message || "MX encontrado.");
									return;
								}

							setMessage(response.ok ? "warning" : "danger", data.message || "Não foi possível validar o MX.");
						})
					.catch(function ()
						{
							setMessage("danger", "Não foi possível consultar o validador agora.");
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
				setMessage("secondary", "Informe um e-mail para consultar os registros MX do domínio.");
				input.focus();
			});
	})();

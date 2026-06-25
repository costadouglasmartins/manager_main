(function ()
	{
		"use strict";

		const root = document.getElementById("webAddressCrawlerResultsApp");
		if (!root)
			{
				return;
			}

		const importUrl = root.getAttribute("data-import-url") || "";
		const importButton = document.getElementById("webCrawlerImportButton");
		const importMessage = document.getElementById("webCrawlerImportMessage");

		let filters = {};
		try
			{
				filters = JSON.parse(root.getAttribute("data-filters") || "{}") || {};
			}
		catch (error)
			{
				filters = {};
			}

		function setMessage(type, text)
			{
				if (!importMessage)
					{
						return;
					}

				if (!text)
					{
						importMessage.classList.add("d-none");
						importMessage.textContent = "";
						return;
					}

				importMessage.className = "alert mb-0 ms-auto alert-" + (type || "info");
				importMessage.textContent = text;
			}

		function setLoading(isLoading)
			{
				if (!importButton)
					{
						return;
					}

				importButton.disabled = isLoading;
				importButton.innerHTML = isLoading
					? '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Importando...'
					: '<i class="bi bi-people me-1"></i>Importar registros filtrados';
			}

		async function importProspects()
			{
				if (!window.confirm("Importar para o cadastro de pessoas todos os registros do filtro atual que possuem CNPJ e ainda não foram importados?"))
					{
						return;
					}

				setLoading(true);
				setMessage("info", "Importando registros filtrados...");

				try
					{
						const response = await fetch(importUrl, {
							method: "POST",
							headers: {
								"Content-Type": "application/json",
								"Accept": "application/json",
								"X-Requested-With": "XMLHttpRequest"
							},
							body: JSON.stringify({ filters: filters })
						});

						const payload = await response.json().catch(function ()
							{
								return {};
							});

						if (!response.ok || !payload.success)
							{
								setMessage("danger", (payload && payload.message) || "Não foi possível importar os registros.");
								setLoading(false);
								return;
							}

						setMessage("success", payload.message || "Importação concluída.");
						setLoading(false);
						window.setTimeout(function ()
							{
								window.location.reload();
							}, 1500);
					}
				catch (error)
					{
						setMessage("danger", "Erro de comunicação ao importar os registros.");
						setLoading(false);
					}
			}

		if (importButton)
			{
				importButton.addEventListener("click", importProspects);
			}
	})();

(function ()
	{
	const app = document.getElementById("databaseManagerApp");
	if (!app)
		{
		return;
		}

	const catalogElement = document.getElementById("databaseManagerCatalog");
	const form = document.getElementById("databaseManagerForm");
	const rangeFields = document.getElementById("rangeFields");
	const targetPreviewList = document.getElementById("targetPreviewList");
	const selectionBadge = document.getElementById("selectionBadge");
	const selectionTitle = document.getElementById("selectionTitle");
	const selectionRange = document.getElementById("selectionRange");
	const selectionEmpty = document.getElementById("selectionEmpty");
	const databaseStartInput = document.getElementById("databaseStart");
	const databaseEndInput = document.getElementById("databaseEnd");
	const sqlCommandInput = document.getElementById("sqlCommand");
	const executeButton = document.getElementById("executeButton");
	const executionPanel = document.getElementById("executionPanel");
	const executionSubtitle = document.getElementById("executionSubtitle");
	const successBadge = document.getElementById("successBadge");
	const errorBadge = document.getElementById("errorBadge");
	const progressLabel = document.getElementById("progressLabel");
	const progressMeta = document.getElementById("progressMeta");
	const progressBar = document.getElementById("progressBar");
	const executionAlert = document.getElementById("executionAlert");
	const executionLog = document.getElementById("executionLog");
	const logCount = document.getElementById("logCount");

	let catalog = [];
	try
		{
		catalog = JSON.parse(catalogElement?.textContent || "[]");
		}
	catch (error)
		{
		catalog = [];
		}

	const state = {
		successCount: 0,
		errorCount: 0,
		logEntries: 0,
	};

	function normalizeDatabaseNumber(value)
		{
		const digits = String(value || "").replace(/\D/g, "");
		if (!digits)
			{
			return null;
			}

		const trimmed = digits.replace(/^0+/, "") || "0";
		return trimmed.padStart(4, "0");
		}

	function currentScope()
		{
		return form.querySelector('input[name="target_scope"]:checked')?.value || "all_tenants";
		}

	function resolveTargets()
		{
		const scope = currentScope();

		if (scope === "all_tenants")
			{
			return catalog.slice();
			}

		const start = normalizeDatabaseNumber(databaseStartInput.value);
		const end = normalizeDatabaseNumber(databaseEndInput.value) || start;

		if (!start)
			{
			return [];
			}

		const startOrder = parseInt(start, 10);
		const endOrder = parseInt(end, 10);
		const low = Math.min(startOrder, endOrder);
		const high = Math.max(startOrder, endOrder);

		return catalog.filter((item) => item.database_order >= low && item.database_order <= high);
		}

	function createStatusBadge(item)
		{
		if (!item.tenant_status)
			{
			return '<span class="badge text-bg-secondary">Sem tenant</span>';
			}

		if (item.tenant_status === "active")
			{
			return '<span class="badge text-bg-success">Ativo</span>';
			}

		if (item.tenant_status === "suspended")
			{
			return '<span class="badge text-bg-danger">Suspenso</span>';
			}

		return '<span class="badge text-bg-warning">Pendente</span>';
		}

	function renderPreview()
		{
		const scope = currentScope();
		const targets = resolveTargets();
		const start = normalizeDatabaseNumber(databaseStartInput.value);
		const end = normalizeDatabaseNumber(databaseEndInput.value) || start;
		let orderedStart = start;
		let orderedEnd = end;

		if (start && end)
			{
			const low = Math.min(parseInt(start, 10), parseInt(end, 10));
			const high = Math.max(parseInt(start, 10), parseInt(end, 10));
			const width = Math.max(start.length, end.length, 4);
			orderedStart = String(low).padStart(width, "0");
			orderedEnd = String(high).padStart(width, "0");
			}

		rangeFields.classList.toggle("d-none", scope !== "range");
		selectionBadge.textContent = `${targets.length} banco${targets.length === 1 ? "" : "s"}`;
		selectionTitle.textContent = scope === "all_tenants"
			? "Todos os bancos dos tenants"
			: (start && end && start === end ? `Banco específico ${start}` : "Intervalo personalizado");

		if (scope === "all_tenants")
			{
			selectionRange.textContent = "Catálogo completo";
			}
		else if (start && end)
			{
			selectionRange.textContent = `manager_${orderedStart} até manager_${orderedEnd}`;
			}
		else
			{
			selectionRange.textContent = "Aguardando faixa";
			}

		targetPreviewList.innerHTML = "";
		selectionEmpty.classList.toggle("d-none", targets.length > 0);

		targets.forEach((item) =>
			{
			const element = document.createElement("div");
			element.className = "list-group-item";
			element.innerHTML = `
				<div class="d-flex justify-content-between align-items-start gap-3">
					<div>
						<strong>${item.database_name}</strong>
						<div class="text-muted small">${item.tenant_name || "Sem tenant vinculado"}</div>
					</div>
					${createStatusBadge(item)}
				</div>
				<div class="target-meta">
					<span class="badge text-bg-light border">Banco #${item.database_number}</span>
					${item.tenant_id ? `<span class="badge text-bg-light border">Tenant ${item.tenant_id}</span>` : ""}
					${item.tenant_fantasy ? `<span class="badge text-bg-light border">${item.tenant_fantasy}</span>` : ""}
				</div>
			`;
			targetPreviewList.appendChild(element);
			});
		}

	function setExecutionAlert(type, message)
		{
		executionAlert.className = `alert alert-${type} mb-4`;
		executionAlert.textContent = message;
		}

	function resetExecutionPanel()
		{
		state.successCount = 0;
		state.errorCount = 0;
		state.logEntries = 0;
		successBadge.textContent = "0 sucesso";
		errorBadge.textContent = "0 erro";
		logCount.textContent = "0 eventos";
		progressLabel.textContent = "Preparando execução";
		progressMeta.textContent = "0%";
		progressBar.style.width = "0%";
		progressBar.textContent = "0%";
		progressBar.setAttribute("aria-valuenow", "0");
		progressBar.classList.add("progress-bar-striped", "progress-bar-animated");
		executionSubtitle.textContent = "Conectando ao servidor e iniciando o lote.";
		executionLog.innerHTML = "";
		setExecutionAlert("info", "Aguardando o primeiro retorno do servidor.");
		executionPanel.classList.remove("d-none");
		executionPanel.scrollIntoView({ behavior: "smooth", block: "start" });
		}

	function appendLog(type, title, message, meta)
		{
		state.logEntries += 1;
		logCount.textContent = `${state.logEntries} evento${state.logEntries === 1 ? "" : "s"}`;

		const item = document.createElement("div");
		item.className = `list-group-item list-group-item-${type}`;
		item.innerHTML = `
			<div class="log-title">
				<strong>${title}</strong>
				<small>${meta || ""}</small>
			</div>
			<p class="mb-0 mt-2">${message}</p>
		`;

		executionLog.prepend(item);
		}

	function updateProgress(percent, label, meta)
		{
		const safePercent = Math.max(0, Math.min(100, percent));
		progressLabel.textContent = label;
		progressMeta.textContent = `${safePercent}%`;
		progressBar.style.width = `${safePercent}%`;
		progressBar.textContent = `${safePercent}%`;
		progressBar.setAttribute("aria-valuenow", String(safePercent));

		if (meta)
			{
			executionSubtitle.textContent = meta;
			}
		}

	function handleStreamEvent(payload)
		{
		if (!payload)
			{
			return;
			}

		if (!payload.type)
			{
			if (payload.message)
				{
				progressBar.classList.remove("progress-bar-animated");
				setExecutionAlert("danger", payload.message);
				appendLog("danger", "Resposta do servidor", payload.message, payload.ability || "erro");
				}
			return;
			}

		if (payload.type === "validation_error")
			{
			progressBar.classList.remove("progress-bar-animated");
			setExecutionAlert("warning", payload.message || "Não foi possível iniciar a execução.");
			appendLog("danger", "Validação do lote", payload.message || "Falha de validação.", "pré-execução");
			return;
			}

		if (payload.type === "started")
			{
			updateProgress(0, "Lote iniciado", `Processando ${payload.total} banco(s).`);
			setExecutionAlert("primary", `Execução iniciada em ${payload.started_at}.`);
			appendLog("info", "Lote iniciado", payload.sql_preview || "Script recebido pelo servidor.", `${payload.total} banco(s)`);
			return;
			}

		if (payload.type === "progress")
			{
			if (payload.success)
				{
				state.successCount += 1;
				}
			else
				{
				state.errorCount += 1;
				}

			successBadge.textContent = `${state.successCount} sucesso`;
			errorBadge.textContent = `${state.errorCount} erro`;

			updateProgress(
				payload.percent || 0,
				`${payload.current}/${payload.total} bancos concluídos`,
				`Último banco: ${payload.database_name}`
			);

			const title = payload.success
				? `${payload.database_name} concluído`
				: `${payload.database_name} com erro`;
			const message = payload.tenant_name
				? `${payload.tenant_name}: ${payload.message}`
				: payload.message;
			const meta = `${payload.duration_ms} ms`;

			appendLog(payload.success ? "success" : "danger", title, message, meta);
			setExecutionAlert(
				payload.success ? "success" : "danger",
				payload.success
					? `Banco ${payload.database_name} executado com sucesso.`
					: `Falha ao executar em ${payload.database_name}.`
			);
			return;
			}

		if (payload.type === "summary")
			{
			updateProgress(100, "Execução finalizada", `Fim do lote em ${payload.finished_at}.`);
			progressBar.classList.remove("progress-bar-animated");
			setExecutionAlert(
				payload.error_count > 0 ? "warning" : "success",
				`Lote encerrado com ${payload.success_count} sucesso(s) e ${payload.error_count} erro(s).`
			);
			appendLog(
				payload.error_count > 0 ? "info" : "success",
				"Resumo final",
				`${payload.total} banco(s) processados, ${payload.success_count} com sucesso e ${payload.error_count} com erro.`,
				"concluído"
			);
			}
		}

	async function consumeStream(response)
		{
		if (!response.body || !response.body.getReader)
			{
			const text = await response.text();
			text.split("\n").filter(Boolean).forEach((line) =>
				{
				try
					{
					handleStreamEvent(JSON.parse(line));
					}
				catch (error)
					{
					}
				});
			return;
			}

		const reader = response.body.getReader();
		const decoder = new TextDecoder();
		let buffer = "";

		while (true)
			{
			const { value, done } = await reader.read();

			if (done)
				{
				break;
				}

			buffer += decoder.decode(value, { stream: true });
			const lines = buffer.split("\n");
			buffer = lines.pop() || "";

			lines.forEach((line) =>
				{
				if (!line.trim())
					{
					return;
					}

				try
					{
					handleStreamEvent(JSON.parse(line));
					}
				catch (error)
					{
					appendLog("danger", "Falha ao interpretar retorno", line, "parser");
					}
				});
			}

		if (buffer.trim())
			{
			try
				{
				handleStreamEvent(JSON.parse(buffer));
				}
			catch (error)
				{
				}
			}
		}

	async function handleSubmit(event)
		{
		event.preventDefault();
		resetExecutionPanel();

		const formData = new FormData(form);

		if (!String(sqlCommandInput.value || "").trim())
			{
			setExecutionAlert("warning", "Informe um comando SQL para executar.");
			appendLog("danger", "Campo obrigatório", "O comando SQL está vazio.", "validação");
			return;
			}

		if (currentScope() === "range" && !normalizeDatabaseNumber(databaseStartInput.value))
			{
			setExecutionAlert("warning", "Informe o banco inicial para usar intervalo ou banco específico.");
			appendLog("danger", "Campo obrigatório", "Banco inicial não informado.", "validação");
			return;
			}

		executeButton.disabled = true;
		executeButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Executando lote';

		try
			{
			const response = await fetch(app.dataset.executeUrl, {
				method: "POST",
				body: formData,
				headers: {
					"X-Requested-With": "XMLHttpRequest"
				}
			});

			await consumeStream(response);
			}
		catch (error)
			{
			progressBar.classList.remove("progress-bar-animated");
			setExecutionAlert("danger", "A conexão com o servidor falhou durante a execução.");
			appendLog("danger", "Erro de conexão", error.message || "Falha inesperada na requisição.", "fetch");
			}
		finally
			{
			executeButton.disabled = false;
			executeButton.innerHTML = '<i class="bi bi-lightning-charge-fill me-2"></i>Executar lote SQL';
			}
		}

	form.querySelectorAll('input[name="target_scope"]').forEach((input) =>
		{
		input.addEventListener("change", renderPreview);
		});

	[databaseStartInput, databaseEndInput].forEach((input) =>
		{
		input.addEventListener("input", () =>
			{
			input.value = String(input.value || "").replace(/\D/g, "");
			renderPreview();
			});
		});

	form.addEventListener("submit", handleSubmit);
	renderPreview();
	})();

(function ()
	{
		"use strict";

		const root = document.getElementById("serpApiWebCrawlerApp");
		if (!root)
			{
				return;
			}

		const streamUrl = root.getAttribute("data-stream-url") || "";
		const hasApiKey = root.getAttribute("data-has-api-key") === "1";
		const form = document.getElementById("serpApiWebCrawlerForm");
		const startButton = document.getElementById("serpApiStartButton");
		const stopButton = document.getElementById("serpApiStopButton");
		const clearButton = document.getElementById("serpApiClearButton");
		const message = document.getElementById("serpApiMessage");
		const resultsBody = document.getElementById("serpApiResultsBody");
		const executionQueryLimitInput = document.getElementById("serpApiExecutionQueryLimit");
		const remainingCount = document.getElementById("serpApiRemainingCount");
		const usedCount = document.getElementById("serpApiUsedCount");
		const limitCount = document.getElementById("serpApiLimitCount");
		const queryCount = document.getElementById("serpApiQueryCount");
		const foundCount = document.getElementById("serpApiFoundCount");
		const responseTime = document.getElementById("serpApiResponseTime");
		const httpStatus = document.getElementById("serpApiHttpStatus");
		const statusText = document.getElementById("serpApiStatusText");

		let eventSource = null;
		let hasResults = false;

		const statusLabels = {
			running: "Em andamento",
			completed: "Concluído",
			failed: "Falhou",
			stopped: "Interrompido",
			idle: "Aguardando início"
		};

		const eventMessages = {
			connected: "Servidor conectado.",
			started: "Consulta preparada.",
			quota: "Consulta contabilizada na quota mensal.",
			progress: "Resposta recebida. Processando resultados.",
			summary: "Consulta finalizada.",
			validation_error: "Erro de validação.",
			schema_missing: "Schema SQL ausente.",
			quota_exhausted: "Quota mensal esgotada.",
			api_error: "Erro retornado pela SerpAPI.",
			error: "Erro inesperado.",
			info: "Aguardando retorno do servidor."
		};

		function escapeHtml(value)
			{
				return String(value === null || value === undefined ? "" : value)
					.replace(/&/g, "&amp;")
					.replace(/</g, "&lt;")
					.replace(/>/g, "&gt;")
					.replace(/"/g, "&quot;")
					.replace(/'/g, "&#39;");
			}

		function parsePayload(event)
			{
				try
					{
						return JSON.parse(event.data) || {};
					}
				catch (error)
					{
						return {};
					}
			}

		function numericMetric(value)
			{
				const normalized = String(value === null || value === undefined ? "" : value).replace(/\./g, "").replace(",", ".");
				const number = Number(normalized);
				return Number.isFinite(number) ? number : 0;
			}

		function formatInteger(value)
			{
				return numericMetric(value).toLocaleString("pt-BR", {
					maximumFractionDigits: 0
				});
			}

		function initialUsage()
			{
				try
					{
						return JSON.parse(root.getAttribute("data-initial-usage") || "{}") || {};
					}
				catch (error)
					{
						return {};
					}
			}

		function setMessage(type, text)
			{
				if (!text)
					{
						message.className = "alert d-none mb-0";
						message.textContent = "";
						return;
					}

				message.className = "alert alert-" + (type || "info") + " mb-0";
				message.textContent = text;
			}

		function setLoading(isLoading)
			{
				startButton.disabled = isLoading;
				stopButton.classList.toggle("d-none", !isLoading);
				startButton.innerHTML = isLoading
					? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Pesquisando'
					: '<i class="bi bi-play-fill me-1"></i>Pesquisar';
			}

		function updateUsage(usage)
			{
				usage = usage || {};
				remainingCount.textContent = formatInteger(usage.remaining || 0);
				usedCount.textContent = formatInteger(usage.used || 0);
				limitCount.textContent = formatInteger(usage.limit || 0);
			}

		function updateStats(stats)
			{
				stats = stats || {};
				queryCount.textContent = formatInteger(stats.query_count || 0);
				foundCount.textContent = formatInteger(stats.found_total || 0);
				responseTime.textContent = formatInteger(stats.response_time_ms || 0) + " ms";
				httpStatus.textContent = stats.http_status ? String(stats.http_status) : "-";

				if (stats.status)
					{
						statusText.textContent = statusLabels[stats.status] || stats.status;
					}

				if (stats.usage)
					{
						updateUsage(stats.usage);
					}
			}

		function clearResults()
			{
				hasResults = false;
				resultsBody.innerHTML = '<div class="list-group-item text-secondary serpapi-webcrawler-results-empty">'
					+ '<div class="d-flex align-items-center gap-2"><i class="bi bi-hourglass-split"></i>'
					+ '<span>Aguardando o primeiro retorno do servidor.</span></div></div>';
			}

		function appendFeedItem(item)
			{
				if (!hasResults)
					{
						resultsBody.innerHTML = "";
						hasResults = true;
					}

				resultsBody.appendChild(item);
				resultsBody.scrollTop = resultsBody.scrollHeight;
			}

		function serverEventMeta(payload)
			{
				const parts = [];
				const stats = payload.stats || {};
				const usage = payload.usage || {};

				if (payload.search_id)
					{
						parts.push("Consulta #" + formatInteger(payload.search_id));
					}

				if (stats.query_count !== undefined)
					{
						parts.push("Solicitações: " + formatInteger(stats.query_count));
					}

				if (stats.found_total !== undefined)
					{
						parts.push("Resultados: " + formatInteger(stats.found_total));
					}

				if (stats.http_status)
					{
						parts.push("HTTP: " + String(stats.http_status));
					}

				if (usage.remaining !== undefined)
					{
						parts.push("Restantes: " + formatInteger(usage.remaining));
					}

				return parts.join(" · ");
			}

		function appendServerEvent(eventName, payload, variant)
			{
				payload = payload || {};
				const item = document.createElement("div");
				const normalizedEventName = String(eventName || "info").replace(/[^a-z0-9_-]/gi, "_");
				const eventVariant = variant || "info";
				const messageText = payload.message || eventMessages[eventName] || eventMessages.info;
				const meta = serverEventMeta(payload);
				const payloadJson = JSON.stringify(payload, null, 2);
				const details = payloadJson && payloadJson !== "{}"
					? '<details class="small mt-2"><summary>Dados do evento</summary><pre class="bg-body-tertiary border rounded p-2 mt-2 mb-0">' + escapeHtml(payloadJson) + "</pre></details>"
					: "";

				item.className = "list-group-item serpapi-webcrawler-server-event serpapi-webcrawler-server-event-" + normalizedEventName + " serpapi-webcrawler-server-event-" + eventVariant;
				item.innerHTML = '<div class="d-flex flex-wrap align-items-start gap-2">'
					+ '<span class="badge text-bg-' + escapeHtml(eventVariant === "danger" ? "danger" : eventVariant === "warning" ? "warning" : eventVariant === "success" ? "success" : "info") + '">' + escapeHtml(eventName) + "</span>"
					+ '<div class="flex-grow-1 min-w-0">'
					+ '<div class="fw-semibold text-break">' + escapeHtml(messageText) + "</div>"
					+ (meta ? '<div class="small text-secondary text-break">' + escapeHtml(meta) + "</div>" : "")
					+ details
					+ "</div>"
					+ '<small class="text-secondary ms-auto">' + escapeHtml(new Date().toLocaleTimeString("pt-BR")) + "</small>"
					+ "</div>";

				appendFeedItem(item);
			}

		function appendResult(result)
			{
				const title = result.title || result.link || "Resultado";
				const source = result.source || result.displayed_link || "";
				const link = result.link || "";
				const thumbnail = result.thumbnail
					? '<img class="serpapi-webcrawler-result-thumbnail" src="' + escapeHtml(result.thumbnail) + '" alt="">'
					: "";
				const item = document.createElement("div");
				item.className = "list-group-item";
				item.innerHTML = '<div class="d-flex gap-3">'
					+ thumbnail
					+ '<div class="flex-grow-1 min-w-0">'
					+ '<div class="d-flex flex-wrap justify-content-between align-items-start gap-2">'
					+ '<div class="fw-semibold text-break">' + escapeHtml(title) + "</div>"
					+ '<span class="badge text-bg-success">Resultado #' + formatInteger(result.position || 0) + "</span>"
					+ "</div>"
					+ (link ? '<a class="small text-break d-block" href="' + escapeHtml(link) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(link) + "</a>" : "")
					+ (source ? '<div class="small text-secondary text-break">' + escapeHtml(source) + "</div>" : "")
					+ (result.date ? '<div class="small text-secondary">' + escapeHtml(result.date) + "</div>" : "")
					+ (result.snippet ? '<div class="small text-secondary text-break mt-1">' + escapeHtml(result.snippet) + "</div>" : "")
					+ "</div>"
					+ "</div>";

				appendFeedItem(item);
			}

		function streamRequestUrl()
			{
				const data = new FormData(form);
				const params = new URLSearchParams();
				data.forEach(function (value, key)
					{
						params.append(key, value);
					});

				return streamUrl + "?" + params.toString();
			}

		function validateForm()
			{
				if (!hasApiKey)
					{
						setMessage("warning", "Configure a chave da SerpAPI antes de executar consultas.");
						return false;
					}

				const query = (form.elements.query.value || "").trim();
				if (query === "")
					{
						setMessage("warning", "Informe uma consulta para pesquisar.");
						return false;
					}

				if (executionQueryLimitInput && numericMetric(executionQueryLimitInput.value) <= 0)
					{
						setMessage("warning", "Informe ao menos 1 consulta para esta execução.");
						return false;
					}

				if (numericMetric(remainingCount.textContent) <= 0)
					{
						setMessage("warning", "Limite mensal de consultas esgotado.");
						return false;
					}

				return true;
			}

		function closeStream()
			{
				if (eventSource)
					{
						eventSource.close();
						eventSource = null;
					}

				setLoading(false);
			}

		function startStream()
			{
				if (!window.EventSource)
					{
						setMessage("danger", "Seu navegador não suporta atualização em tempo real via SSE.");
						return;
					}

				if (!validateForm())
					{
						return;
					}

				closeStream();
				clearResults();
				setMessage("", "");
				setLoading(true);
				appendServerEvent("info", {
					message: "Conectando ao servidor e aguardando o primeiro evento SSE."
				}, "info");
				updateStats({
					query_count: 0,
					found_total: 0,
					response_time_ms: 0,
					http_status: 0,
					status: "running"
				});

				eventSource = new EventSource(streamRequestUrl());

				eventSource.addEventListener("connected", function (event)
					{
						const payload = parsePayload(event);
						updateStats(payload.stats || {});
						updateUsage(payload.usage || {});
						appendServerEvent("connected", payload, "info");
						setMessage("info", payload.message || "Servidor conectado.");
					});

				eventSource.addEventListener("started", function (event)
					{
						const payload = parsePayload(event);
						updateStats(payload.stats || {});
						updateUsage(payload.usage || {});
						appendServerEvent("started", payload, "info");
						setMessage("info", payload.message || "Consulta iniciada.");
					});

				eventSource.addEventListener("quota", function (event)
					{
						const payload = parsePayload(event);
						updateStats(payload.stats || {});
						updateUsage(payload.usage || {});
						appendServerEvent("quota", payload, "info");
					});

				eventSource.addEventListener("progress", function (event)
					{
						const payload = parsePayload(event);
						updateStats(payload.stats || {});
						updateUsage(payload.usage || {});
						appendServerEvent("progress", payload, "info");
					});

				eventSource.addEventListener("result", function (event)
					{
						const payload = parsePayload(event);
						appendResult(payload.result || {});
						updateStats(payload.stats || {});
						updateUsage(payload.usage || {});
					});

				eventSource.addEventListener("summary", function (event)
					{
						const payload = parsePayload(event);
						updateStats(payload.stats || {});
						updateUsage(payload.usage || {});
						appendServerEvent("summary", payload, "success");
						setMessage("success", payload.message || "Consulta finalizada.");
						closeStream();
					});

				["validation_error", "schema_missing", "quota_exhausted", "api_error", "error"].forEach(function (eventName)
					{
						eventSource.addEventListener(eventName, function (event)
							{
								const payload = parsePayload(event);
								const type = eventName === "validation_error" || eventName === "quota_exhausted" ? "warning" : "danger";
								updateStats(payload.stats || {});
								updateUsage(payload.usage || {});
								appendServerEvent(eventName, payload, type);
								setMessage(type, payload.message || "Não foi possível executar a consulta.");
								closeStream();
							});
					});

				eventSource.onerror = function ()
					{
						if (eventSource)
							{
								appendServerEvent("error", {
									message: "A conexão em tempo real foi encerrada antes do término."
								}, "danger");
								setMessage("danger", "A conexão em tempo real foi encerrada antes do término.");
								closeStream();
							}
					};
			}

		form.addEventListener("submit", function (event)
			{
				event.preventDefault();
				startStream();
			});

		clearButton.addEventListener("click", function ()
			{
				closeStream();
				form.reset();
				clearResults();
				setMessage("", "");
				updateStats({ status: "idle" });
				updateUsage(initialUsage());
				statusText.textContent = "Aguardando início";
			});

		stopButton.addEventListener("click", function ()
			{
				closeStream();
				setMessage("warning", "Consulta interrompida na tela.");
				statusText.textContent = "Interrompido";
			});

		updateUsage(initialUsage());
	})();

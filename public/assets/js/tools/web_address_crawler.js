(function ()
	{
		"use strict";

		const root = document.getElementById("webAddressCrawlerApp");
		if (!root)
			{
				return;
			}

		const streamUrl = root.getAttribute("data-stream-url") || "";
		const form = document.getElementById("webCrawlerForm");
		const startButton = document.getElementById("webCrawlerStartButton");
		const stopButton = document.getElementById("webCrawlerStopButton");
		const clearButton = document.getElementById("webCrawlerClearButton");
		const message = document.getElementById("webCrawlerMessage");
		const resultsBody = document.getElementById("webCrawlerResultsBody");
		const concurrencyInput = document.getElementById("webCrawlerConcurrency");
		const concurrencyLabel = document.getElementById("webCrawlerConcurrencyLabel");
		const delayInput = document.getElementById("webCrawlerDelay");
		const delayLabel = document.getElementById("webCrawlerDelayLabel");

		const progressText = document.getElementById("webCrawlerProgressText");
		const progressBar = document.getElementById("webCrawlerProgressBar");
		const targetCount = document.getElementById("webCrawlerTargetCount");
		const processedCount = document.getElementById("webCrawlerProcessedCount");
		const foundCount = document.getElementById("webCrawlerFoundCount");
		const matchedCount = document.getElementById("webCrawlerMatchedCount");
		const failedCount = document.getElementById("webCrawlerFailedCount");
		const statusText = document.getElementById("webCrawlerStatusText");

		let eventSource = null;
		let hasResults = false;

		const statusLabels = {
			running: "Em andamento",
			completed: "Concluído",
			failed: "Falhou",
			stopped: "Interrompido",
			idle: "Aguardando início"
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

		function setMessage(type, text)
			{
				if (!text)
					{
						message.classList.add("d-none");
						message.textContent = "";
						return;
					}

				message.className = "alert mb-0 alert-" + (type || "info");
				message.textContent = text;
			}

		function setLoading(isLoading)
			{
				startButton.disabled = isLoading;
				stopButton.classList.toggle("d-none", !isLoading);
				root.classList.toggle("is-loading", isLoading);
			}

		function updateStats(stats)
			{
				stats = stats || {};
				const target = Number(stats.target_total || 0);
				const processed = Number(stats.processed_total || 0);
				const percent = target > 0 ? Math.min(100, Math.round((processed / target) * 100)) : 0;

				progressText.textContent = percent + "%";
				progressBar.style.width = percent + "%";
				targetCount.textContent = target;
				processedCount.textContent = processed;
				foundCount.textContent = Number(stats.found_total || 0);
				matchedCount.textContent = Number(stats.matched_total || 0);
				failedCount.textContent = Number(stats.failed_total || 0);

				if (stats.status)
					{
						statusText.textContent = statusLabels[stats.status] || stats.status;
					}
			}

		function clearResults()
			{
				hasResults = false;
				resultsBody.innerHTML = '<div class="list-group-item text-secondary web-address-crawler-results-empty">'
					+ '<div class="d-flex align-items-center gap-2"><i class="bi bi-hourglass-split"></i>'
					+ '<span>Aguardando início da pesquisa.</span></div></div>';
			}

		function buildBadges(keywords)
			{
				if (!Array.isArray(keywords) || keywords.length === 0)
					{
						return "";
					}

				return '<div class="d-flex flex-wrap gap-1 mt-1">'
					+ keywords.map(function (keyword)
						{
							return '<span class="badge text-bg-primary">' + escapeHtml(keyword) + "</span>";
						}).join("")
					+ "</div>";
			}

		function buildContactLine(result)
			{
				const parts = [];
				if (result.phone)
					{
						parts.push('<span><i class="bi bi-telephone me-1"></i>' + escapeHtml(result.phone) + "</span>");
					}
				if (result.email)
					{
						parts.push('<span><i class="bi bi-envelope me-1"></i>' + escapeHtml(result.email) + "</span>");
					}
				if (result.document)
					{
						parts.push('<span><i class="bi bi-card-text me-1"></i>' + escapeHtml(result.document) + "</span>");
					}

				const location = [result.city, result.state_acronym].filter(Boolean).join(" / ");
				if (location)
					{
						parts.push('<span><i class="bi bi-geo me-1"></i>' + escapeHtml(location) + "</span>");
					}

				if (parts.length === 0)
					{
						return "";
					}

				return '<div class="d-flex flex-wrap gap-3 small text-secondary mt-1">' + parts.join("") + "</div>";
			}

		function appendResult(result)
			{
				if (!hasResults)
					{
						resultsBody.innerHTML = "";
						hasResults = true;
					}

				const title = result.business_name || result.website || result.url || "Registro";
				const newBadge = result.is_new
					? '<span class="badge text-bg-success">Novo</span>'
					: '<span class="badge text-bg-light text-dark">Repetido</span>';

				const item = document.createElement("div");
				item.className = "list-group-item";
				item.innerHTML = '<div class="d-flex justify-content-between align-items-start gap-2">'
					+ '<div class="fw-semibold text-break">' + escapeHtml(title) + "</div>"
					+ newBadge
					+ "</div>"
					+ '<a class="small text-break d-block" href="' + escapeHtml(result.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(result.url) + "</a>"
					+ buildContactLine(result)
					+ buildBadges(result.matched_keywords)
					+ (result.snippet ? '<div class="small text-secondary text-break mt-1">' + escapeHtml(result.snippet) + "</div>" : "");

				resultsBody.appendChild(item);
				resultsBody.scrollTop = resultsBody.scrollHeight;
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
				const keywords = (form.elements.keywords.value || "").trim();
				if (keywords === "")
					{
						setMessage("warning", "Informe ao menos uma palavra-chave para a pesquisa.");
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
				updateStats({
					target_total: 0,
					processed_total: 0,
					found_total: 0,
					matched_total: 0,
					failed_total: 0,
					status: "running"
				});

				eventSource = new EventSource(streamRequestUrl());

				eventSource.addEventListener("started", function (event)
					{
						const payload = parsePayload(event);
						updateStats(payload.stats || {});
						setMessage("info", payload.message || "Pesquisa iniciada.");
					});

				eventSource.addEventListener("progress", function (event)
					{
						const payload = parsePayload(event);
						updateStats(payload.stats || {});
					});

				eventSource.addEventListener("found", function (event)
					{
						const payload = parsePayload(event);
						appendResult(payload.result || {});
						updateStats(payload.stats || {});
					});

				eventSource.addEventListener("blocked", function (event)
					{
						const payload = parsePayload(event);
						setMessage("warning", payload.message || "O DuckDuckGo limitou as requisições.");
					});

				eventSource.addEventListener("summary", function (event)
					{
						const payload = parsePayload(event);
						updateStats(payload.stats || {});
						setMessage("success", payload.message || "Pesquisa finalizada.");
						closeStream();
					});

				["validation_error", "schema_missing", "error"].forEach(function (eventName)
					{
						eventSource.addEventListener(eventName, function (event)
							{
								const payload = parsePayload(event);
								setMessage(eventName === "validation_error" ? "warning" : "danger", payload.message || "Não foi possível executar a pesquisa.");
								closeStream();
							});
					});

				eventSource.onerror = function ()
					{
						if (eventSource)
							{
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
				updateConcurrencyLabel();
				updateDelayLabel();
				clearResults();
				setMessage("", "");
				updateStats({ status: "idle" });
				statusText.textContent = "Aguardando início";
			});

		stopButton.addEventListener("click", function ()
			{
				closeStream();
				setMessage("warning", "Pesquisa interrompida na tela.");
				statusText.textContent = "Interrompido";
			});

		function updateConcurrencyLabel()
			{
				concurrencyLabel.textContent = concurrencyInput.value;
			}

		function updateDelayLabel()
			{
				delayLabel.textContent = delayInput.value + " ms";
			}

		concurrencyInput.addEventListener("input", updateConcurrencyLabel);
		delayInput.addEventListener("input", updateDelayLabel);

		updateConcurrencyLabel();
		updateDelayLabel();
	})();

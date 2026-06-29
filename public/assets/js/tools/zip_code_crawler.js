(function ()
	{
		const app = document.getElementById("zipCodeCrawlerApp");
		if (!app)
			{
				return;
			}

		const form = document.getElementById("zipCodeCrawlerForm");
		const providerModeInput = document.getElementById("crawlerProviderMode");
		const stateInput = document.getElementById("crawlerStateAcronym");
		const startInput = document.getElementById("crawlerStartZipCode");
		const endInput = document.getElementById("crawlerEndZipCode");
		const stateRangeSummary = document.getElementById("crawlerStateRangeSummary");
		const delayModeInput = document.getElementById("crawlerDelayMode");
		const delayInput = document.getElementById("crawlerDelayMs");
		const delayLabel = document.getElementById("crawlerDelayLabel");
		const concurrencyInput = document.getElementById("crawlerConcurrency");
		const concurrencyLabel = document.getElementById("crawlerConcurrencyLabel");
		const clearButton = document.getElementById("crawlerClearButton");
		const stopButton = document.getElementById("crawlerStopButton");
		const startButton = document.getElementById("crawlerStartButton");
		const message = document.getElementById("crawlerMessage");
		const resultsBody = document.getElementById("crawlerResultsBody");
		const resultsInfo = document.getElementById("crawlerResultsInfo");
		const resultsTopButton = document.getElementById("crawlerResultsTopButton");
		const resultsBottomButton = document.getElementById("crawlerResultsBottomButton");
		const processedCount = document.getElementById("crawlerProcessedCount");
		const foundCount = document.getElementById("crawlerFoundCount");
		const failedCount = document.getElementById("crawlerFailedCount");
		const lookupRateCount = document.getElementById("crawlerLookupRateCount");
		const statusText = document.getElementById("crawlerStatusText");
		const monitorCard = document.querySelector(".zip-code-crawler-monitor-card");
		const streamUrl = app.dataset.streamUrl || "";
		const stopUrl = app.dataset.stopUrl || "";
		const stateOptions = parseStateOptions();
		const stateOptionRegistry = buildStateOptionRegistry(stateOptions);

		let eventSource = null;
		let activeRunId = "";
		let crawlerStoppedByUser = false;
		let streamStartedAt = 0;
		let rateTimer = null;
		let resultsStickToBottom = true;
		let queuedResults = [];
		let resultsFlushScheduled = false;
		let latestStats = {
			processed: 0,
			found: 0,
			failed: 0,
			looked_up: 0,
			lookup_rate: 0
		};

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

		function generateRunId()
			{
				return Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 12);
			}

		function requestCrawlerStop(runId)
			{
				if (!stopUrl || !runId)
					{
						return;
					}

				const body = new URLSearchParams();
				body.set("run_id", runId);

				fetch(stopUrl, {
					method: "POST",
					headers: {
						"Accept": "application/json",
						"Content-Type": "application/x-www-form-urlencoded",
						"X-Requested-With": "XMLHttpRequest"
					},
					body: body.toString(),
					keepalive: true
				}).catch(function ()
					{
					});
			}

		function formatInteger(value)
			{
				return numericMetric(value).toLocaleString("pt-BR", {
					maximumFractionDigits: 0
				});
			}

		function parseStateOptions()
			{
				try
					{
						const options = JSON.parse(app.dataset.stateOptions || "[]");
						return Array.isArray(options) ? options : [];
					}
				catch (error)
					{
						return [];
					}
			}

		function buildStateOptionRegistry(options)
			{
				const registry = new Map();
				options.forEach(function (option)
					{
						if (option && option.acronym)
							{
								registry.set(String(option.acronym).toUpperCase(), option);
							}
					});

				return registry;
			}

		function getSelectedStateOption()
			{
				if (!stateInput)
					{
						return null;
					}

				return stateOptionRegistry.get(String(stateInput.value || "").toUpperCase()) || null;
			}

		function calculateZipCodeRangeCount()
			{
				const stateOption = getSelectedStateOption();
				return stateOption ? Number(stateOption.total || 0) : 0;
			}

		function formatRangeList(ranges)
			{
				if (!Array.isArray(ranges) || ranges.length === 0)
					{
						return "";
					}

				return ranges.map(function (range)
					{
						return (range.start_zip_code || "-") + " até " + (range.end_zip_code || "-");
					}).join("; ");
			}

		function updateStateRangeSummary()
			{
				const stateOption = getSelectedStateOption();
				if (!stateOption)
					{
						if (startInput)
							{
								startInput.value = "";
							}
						if (endInput)
							{
								endInput.value = "";
							}
						stateRangeSummary.textContent = "Selecione um estado para carregar a faixa de CEP.";
						return;
					}

				const ranges = Array.isArray(stateOption.ranges) ? stateOption.ranges : [];
				const firstRange = ranges[0] || {};
				const lastRange = ranges[ranges.length - 1] || {};

				if (startInput)
					{
						startInput.value = firstRange.start_digits || "";
					}
				if (endInput)
					{
						endInput.value = lastRange.end_digits || "";
					}

				stateRangeSummary.textContent = (ranges.length > 1 ? "Faixas não contínuas: " : "Faixa: ")
					+ formatRangeList(ranges)
					+ " · "
					+ formatInteger(stateOption.total || 0)
					+ " CEPs";
			}

		function setMessage(type, text)
			{
				if (!message)
					{
						return;
					}

				message.className = "alert zip-code-crawler-message alert-" + type;
				message.textContent = text;
			}

		function setLoading(isLoading)
			{
				app.classList.toggle("is-busy", isLoading);
				startButton.disabled = isLoading;
				startButton.innerHTML = isLoading
					? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Pesquisando'
					: '<i class="bi bi-play-fill me-1"></i>Iniciar';
				stopButton.classList.toggle("d-none", !isLoading);
			}

		function updateDelayLabel()
			{
				const isRandomDelay = delayModeInput.value === "random";
				delayInput.disabled = isRandomDelay;
				delayLabel.textContent = isRandomDelay ? "250-750 ms" : delayInput.value + " ms";
			}

		function intervalSummary(delayMode, delayMs)
			{
				return delayMode === "random" ? "randômico 250-750ms" : "fixo " + delayMs + "ms";
			}

		function updateConcurrencyLabel()
			{
				concurrencyLabel.textContent = concurrencyInput.value;
			}

		function numericMetric(value)
			{
				const number = Number(value);
				return Number.isFinite(number) ? number : 0;
			}

		function getLookedUpCount(payload)
			{
				if (payload && Object.prototype.hasOwnProperty.call(payload, "looked_up"))
					{
						return numericMetric(payload.looked_up);
					}

				return numericMetric(payload ? payload.processed : 0);
			}

		function formatLookupRate(value)
			{
				return value.toLocaleString("pt-BR", {
					minimumFractionDigits: 2,
					maximumFractionDigits: 2
				});
			}

		function getLookupRate(payload)
			{
				if (payload && Object.prototype.hasOwnProperty.call(payload, "lookup_rate"))
					{
						return numericMetric(payload.lookup_rate);
					}

				const elapsedMs = streamStartedAt > 0 ? Date.now() - streamStartedAt : 0;
				const lookedUp = getLookedUpCount(payload || latestStats);
				return elapsedMs > 0 ? lookedUp / (elapsedMs / 1000) : 0;
			}

		function formatClockTimeFromTimestamp(timestamp)
			{
				const seconds = Number(timestamp || 0);
				if (!Number.isFinite(seconds) || seconds <= 0)
					{
						return "";
					}

				return new Date(seconds * 1000).toLocaleTimeString("pt-BR", {
					hour: "2-digit",
					minute: "2-digit",
					second: "2-digit"
				});
			}

		function formatElapsedMs(value)
			{
				const milliseconds = numericMetric(value);
				if (milliseconds < 1000)
					{
						return formatInteger(milliseconds) + " ms";
					}

				return (milliseconds / 1000).toLocaleString("pt-BR", {
					minimumFractionDigits: 2,
					maximumFractionDigits: 2
				}) + " s";
			}

		function cacheStatusText(payload)
			{
				const stage = String(payload.stage || "");
				const sizeText = String(payload.formatted_size || "");
				const fileText = String(payload.file || "zip_code_crawler_not_found.txt");

				if (stage === "reading_main")
					{
						return "Lendo arquivo principal "
							+ fileText
							+ (sizeText ? " (" + sizeText + ")" : "")
							+ ".";
					}

				if (stage === "main_loaded")
					{
						return "Arquivo principal carregado: "
							+ formatInteger(payload.count || 0)
							+ " CEP(s) em "
							+ formatElapsedMs(payload.elapsed_ms || 0)
							+ ".";
					}

				if (stage === "reading_journal")
					{
						return "Lendo CEPs pendentes de sincronização do TXT.";
					}

				if (stage === "journal_loaded")
					{
						return "Pendências carregadas: "
							+ formatInteger(payload.count || 0)
							+ " CEP(s); total em memória: "
							+ formatInteger(payload.total_count || 0)
							+ ".";
					}

				if (stage === "normalization_pending")
					{
						return "Cache TXT carregado; sincronização em ordem decrescente será feita sem bloquear o início da pesquisa.";
					}

				if (stage === "loaded")
					{
						return "Leitura concluída: "
							+ formatInteger(payload.count || 0)
							+ " CEP(s) carregado(s) de "
							+ fileText
							+ (sizeText ? " (" + sizeText + ")" : "")
							+ " em "
							+ formatElapsedMs(payload.elapsed_ms || 0)
							+ ".";
					}

				return "Lendo "
					+ fileText
					+ (sizeText ? " (" + sizeText + ")" : "")
					+ " para evitar consultas repetidas.";
			}

		function updateLookupRate(payload)
			{
				if (!lookupRateCount)
					{
						return;
					}

				lookupRateCount.textContent = formatLookupRate(getLookupRate(payload || latestStats));
			}

		function stopRateTimer()
			{
				if (rateTimer)
					{
						window.clearInterval(rateTimer);
						rateTimer = null;
					}
			}

		function startRateTimer()
			{
				stopRateTimer();
				rateTimer = window.setInterval(function ()
					{
						updateLookupRate(latestStats);
					}, 1000);
			}

		function updateStats(payload)
			{
				latestStats = {
					processed: numericMetric(payload.processed),
					found: numericMetric(payload.found),
					failed: numericMetric(payload.failed),
					looked_up: getLookedUpCount(payload),
					lookup_rate: getLookupRate(payload)
				};

				processedCount.textContent = String(latestStats.processed);
				foundCount.textContent = String(latestStats.found);
				failedCount.textContent = String(latestStats.failed);
				updateLookupRate(latestStats);
			}

		function updateProgress(position, total)
			{
				return total > 0 ? Math.round((position / total) * 100) : 0;
			}

		function updateMonitorHeight()
			{
				if (!monitorCard)
					{
						return;
					}

				const appRect = app.getBoundingClientRect();
				const cardRect = monitorCard.getBoundingClientRect();
				const isSideBySideLayout = cardRect.left > appRect.left + 24;
				if (!isSideBySideLayout)
					{
						monitorCard.style.removeProperty("--zip-code-crawler-monitor-height");
						return;
					}

				const availableHeight = Math.max(640, window.innerHeight - cardRect.top - 16);
				monitorCard.style.setProperty("--zip-code-crawler-monitor-height", availableHeight + "px");
			}

		function clearResults()
			{
				resultsStickToBottom = true;
				queuedResults = [];
				resultsFlushScheduled = false;
				clearResultsInfo();
				resultsBody.innerHTML = '<div class="list-group-item text-secondary zip-code-crawler-results-empty"><div class="d-flex align-items-center gap-2"><i class="bi bi-hourglass-split"></i><span>Aguardando início - selecione um estado e execute o crawler.</span></div></div>';
			}

		function clearQueuedResults()
			{
				queuedResults = [];
				resultsFlushScheduled = false;
			}

		function isResultsScrolledToBottom()
			{
				return resultsBody.scrollHeight - resultsBody.scrollTop - resultsBody.clientHeight <= 12;
			}

		function updateResultsStickiness()
			{
				resultsStickToBottom = isResultsScrolledToBottom();
			}

		function scrollResultsToBottom()
			{
				window.requestAnimationFrame(function ()
					{
						resultsBody.scrollTop = resultsBody.scrollHeight;
						resultsStickToBottom = true;
					});
			}

		function scrollResultsToTop()
			{
				window.requestAnimationFrame(function ()
					{
						resultsBody.scrollTop = 0;
						resultsStickToBottom = false;
					});
			}

		function appendResultsElement(element, immediate)
			{
				const shouldStick = resultsStickToBottom || isResultsScrolledToBottom();
				resultsBody.appendChild(element);
				if (shouldStick)
					{
						scrollResultsToBottom();
					}
			}

		function flushQueuedResults()
			{
				resultsFlushScheduled = false;

				if (queuedResults.length === 0)
					{
						return;
					}

				const shouldStick = resultsStickToBottom || isResultsScrolledToBottom();
				const fragment = document.createDocumentFragment();
				const batch = queuedResults.splice(0, 120);

				batch.forEach(function (element)
					{
						fragment.appendChild(element);
					});

				resultsBody.appendChild(fragment);
				if (shouldStick)
					{
						scrollResultsToBottom();
					}

				if (queuedResults.length > 0)
					{
						scheduleQueuedResultsFlush();
					}
			}

		function scheduleQueuedResultsFlush()
			{
				if (resultsFlushScheduled)
					{
						return;
					}

				resultsFlushScheduled = true;
				window.requestAnimationFrame(flushQueuedResults);
			}

		function queueResultsElement(element)
			{
				queuedResults.push(element);
				scheduleQueuedResultsFlush();
			}

		function nowTimestamp()
			{
				const d = new Date();
				return [
					String(d.getHours()).padStart(2, "0"),
					String(d.getMinutes()).padStart(2, "0"),
					String(d.getSeconds()).padStart(2, "0")
				].join(":");
			}

		function clearIdleLine()
			{
				const idleLine = resultsBody.querySelector(".zip-code-crawler-results-empty");
				if (idleLine)
					{
						idleLine.remove();
					}
			}

		function systemListGroupClass(type)
			{
				switch (String(type || "").toUpperCase())
					{
						case "DONE":
							return "list-group-item-success";
						case "ERROR":
							return "list-group-item-danger";
						case "WARN":
						case "STOP":
							return "list-group-item-warning";
						default:
							return "list-group-item-info";
					}
			}

		function buildSystemLine(type, text)
			{
				const line = document.createElement("div");
				line.className = "list-group-item " + systemListGroupClass(type);

				const header = document.createElement("div");
				header.className = "d-flex flex-wrap align-items-center gap-2";

				const statusSpan = document.createElement("span");
				statusSpan.className = "badge text-bg-secondary";
				statusSpan.textContent = type;

				const tsSpan = document.createElement("small");
				tsSpan.className = "text-body-secondary font-monospace";
				tsSpan.textContent = nowTimestamp();

				const textSpan = document.createElement("span");
				textSpan.className = "small text-body-secondary flex-grow-1";
				textSpan.textContent = text;

				header.appendChild(statusSpan);
				header.appendChild(tsSpan);
				header.appendChild(textSpan);
				line.appendChild(header);
				return line;
			}

		function appendSystemLine(type, text)
			{
				clearIdleLine();
				appendResultsElement(buildSystemLine(type, text));
			}

		function setResultsInfo(type, text)
			{
				if (!resultsInfo)
					{
						return;
					}

				resultsInfo.innerHTML = "";
				resultsInfo.appendChild(buildSystemLine(type, text));
				resultsInfo.classList.remove("d-none");
			}

		function clearResultsInfo()
			{
				if (!resultsInfo)
					{
						return;
					}

				resultsInfo.innerHTML = "";
				resultsInfo.classList.add("d-none");
			}

		function providerLabel(source)
			{
				const normalized = String(source || "").toLowerCase();
				if (normalized === "viacep")
					{
						return "ViaCEP";
					}

				if (normalized === "brasilapi")
					{
						return "BrasilAPI";
					}

				return source ? String(source).toUpperCase() : "Webservice";
			}

		function attemptStatusText(attempt)
			{
				if (attempt && attempt.success)
					{
						return "ok";
					}

				const error = String((attempt && attempt.error) || "");
				if (error === "zip_code_not_found")
					{
						return "não encontrado";
					}

				if (error === "invalid_response")
					{
						return "resposta inválida";
					}

				if (error === "invalid_zip_code")
					{
						return "CEP inválido";
					}

				if (error === "provider_temporarily_disabled")
					{
						return "pausado";
					}

				return "falhou";
			}

		function attemptDiagnosticText(attempt)
			{
				const diagnostics = attempt && attempt.diagnostics && typeof attempt.diagnostics === "object"
					? attempt.diagnostics
					: null;

				if (!diagnostics)
					{
						return "";
					}

				if (String((attempt && attempt.error) || "") === "provider_temporarily_disabled")
					{
						const disabledUntil = formatClockTimeFromTimestamp(diagnostics.disabled_until_timestamp)
							|| String(diagnostics.disabled_until || "");
						return disabledUntil ? "retoma às " + disabledUntil : "";
					}

				const curlErrorNumber = Number(diagnostics.curl_errno || 0);
				const curlError = String(diagnostics.curl_error || "");
				if (curlErrorNumber > 0)
					{
						return "curl " + curlErrorNumber + (curlError ? ": " + curlError : "");
					}

				const httpStatus = Number(diagnostics.http_status || 0);
				if (httpStatus > 0 && httpStatus !== Number((attempt && attempt.status) || 0))
					{
						return "HTTP " + httpStatus;
					}

				return "";
			}

		function formatAttemptSummary(attempts)
			{
				if (!Array.isArray(attempts) || !attempts.length)
					{
						return "";
					}

				const labels = attempts.map(function (attempt)
					{
						const status = Number((attempt && attempt.status) || 0);
						const statusText = status > 0 ? " (" + status + ")" : "";
						const diagnosticText = attemptDiagnosticText(attempt);
						const diagnosticSuffix = diagnosticText ? " - " + diagnosticText : "";

						return providerLabel(attempt && attempt.source) + " " + attemptStatusText(attempt) + statusText + diagnosticSuffix;
					});

				return "Consultas: " + labels.join(" · ");
			}

		function buildAjaxReturnDetails(payload)
			{
				const details = document.createElement("details");
				details.className = "zip-code-crawler-ajax-return mt-2";

				const summary = document.createElement("summary");
				summary.className = "small text-body-secondary";
				summary.textContent = "Retorno";

				const pre = document.createElement("pre");
				pre.className = "bg-body-tertiary border rounded p-2 mt-2 mb-0 small font-monospace overflow-auto";
				pre.textContent = "";

				details.appendChild(summary);
				details.appendChild(pre);
				details.addEventListener("toggle", function ()
					{
						if (details.open && pre.textContent === "")
							{
								pre.textContent = JSON.stringify(payload, null, 2);
							}
					});

				return details;
			}

		function appendResultRow(payload)
			{
				const result = payload.result || {};
				const data = result.data || {};
				const databaseStatus = data.database_status && typeof data.database_status === "object"
					? data.database_status
					: {};
				const autoSave = data.auto_save && typeof data.auto_save === "object"
					? data.auto_save
					: {};
				const zipCode = payload.zip_code || "";
				const isFound = !!result.success;
				const autoSaveEnabled = isFound && !!autoSave.enabled;
				const autoSaveStatus = String(autoSave.status || "");
				const autoSaveExisting = autoSaveEnabled && autoSaveStatus === "already_exists";
				const alreadyInDatabase = isFound && (!!databaseStatus.already_exists || autoSaveExisting);
				const autoSaveSaved = autoSaveEnabled && !!autoSave.success && autoSaveStatus === "saved" && !!autoSave.created_new_record;
				const autoSaveFailed = autoSaveEnabled && !!autoSave.attempted && !autoSave.success;
				const isSkipped = !isFound && (!!data.skipped || result.source === "cache" || result.error === "zip_code_already_not_found");
				const attemptSummary = formatAttemptSummary(data.attempts);

				const statusText = autoSaveSaved
					? "SAVED"
					: (alreadyInDatabase ? "DB" : (autoSaveFailed ? "SAVE!" : (isFound ? "FOUND" : (isSkipped ? "CACHE" : "MISS"))));
				const statusClass = autoSaveSaved
					? "text-bg-success"
					: (alreadyInDatabase ? "text-bg-warning" : (autoSaveFailed ? "text-bg-danger" : (isFound ? "text-bg-info" : (isSkipped ? "text-bg-secondary" : "text-bg-danger"))));
				const rowClass = autoSaveSaved
					? "list-group-item-success"
					: (alreadyInDatabase ? "list-group-item-warning" : (autoSaveFailed ? "list-group-item-danger" : (isFound ? "list-group-item-info" : (isSkipped ? "list-group-item-secondary" : "list-group-item-danger"))));
				const sourceText = autoSaveSaved
					? "[AUTO]"
					: (alreadyInDatabase ? "[DB]" : (result.source === "cache" ? "[TXT]" : (result.source ? "[" + String(result.source).toUpperCase() + "]" : "[-]")));
				const saveStatusText = autoSaveSaved ? "SALVO" : (alreadyInDatabase ? "CADASTRADO" : (autoSaveFailed ? "ERRO" : ""));
				const saveStatusClass = autoSaveSaved
					? "text-bg-success"
					: (alreadyInDatabase ? "text-bg-warning" : "text-bg-danger");

				const line = document.createElement("div");
				line.className = "list-group-item " + rowClass;

				const header = document.createElement("div");
				header.className = "d-flex flex-wrap align-items-center gap-2";

				const statusSpan = document.createElement("span");
				statusSpan.className = "badge rounded-pill " + statusClass;
				statusSpan.textContent = statusText;

				const zipSpan = document.createElement("span");
				zipSpan.className = "font-monospace fw-semibold text-body";
				zipSpan.textContent = zipCode || "-";

				const sourceSpan = document.createElement("span");
				sourceSpan.className = "badge text-bg-secondary";
				sourceSpan.textContent = sourceText;

				const tsSpan = document.createElement("small");
				tsSpan.className = "text-body-secondary font-monospace ms-auto";
				tsSpan.textContent = "[" + nowTimestamp() + "]";

				header.appendChild(statusSpan);
				header.appendChild(zipSpan);
				header.appendChild(sourceSpan);

				if (saveStatusText !== "")
					{
						const saveStatus = document.createElement("span");
						saveStatus.className = "badge rounded-pill " + saveStatusClass;
						saveStatus.textContent = saveStatusText;
						header.appendChild(saveStatus);
					}

				header.appendChild(tsSpan);

				const locationSpan = document.createElement("span");
				locationSpan.className = "d-block small text-body-secondary mt-2 zip-code-crawler-result-detail";
				if (isFound)
					{
						const parts = [data.neighborhood, data.city, data.state].filter(Boolean);
						const locationText = parts.length ? parts.join(" · ") : "-";
						const detailText = autoSave.message || (alreadyInDatabase ? databaseStatus.message : "");
						const locationDetail = detailText
							? locationText + " · " + detailText
							: locationText;
						locationSpan.textContent = attemptSummary
							? locationDetail + " · " + attemptSummary
							: locationDetail;
					}
				else
					{
						if (isSkipped)
							{
								locationSpan.textContent = "Ignorado pelo arquivo de CEPs não encontrados.";
							}
						else
							{
								locationSpan.textContent = [
									attemptSummary,
									result.message || "CEP não encontrado",
								].filter(Boolean).join(" · ");
							}
					}

				line.appendChild(header);
				line.appendChild(locationSpan);
				line.appendChild(buildAjaxReturnDetails(payload));

				clearIdleLine();
				queueResultsElement(line);
			}

		function openStream()
			{
				const selectedState = getSelectedStateOption();
				const stateAcronym = selectedState ? String(selectedState.acronym || "").toUpperCase() : "";
				const delayMode = delayModeInput.value || "fixed";
				const delayMs = String(delayInput.value || "500");
				const concurrency = String(concurrencyInput.value || "3");
				const maxResults = String(calculateZipCodeRangeCount() || "100");
				const providerMode = providerModeInput.value || "fallback";
				const delayText = intervalSummary(delayMode, delayMs);

				if (!selectedState || stateAcronym === "")
					{
						setMessage("warning", "Selecione um estado para iniciar a pesquisa.");
						return;
					}

				if (eventSource)
					{
						crawlerStoppedByUser = true;
						requestCrawlerStop(activeRunId);
						eventSource.close();
					}

				activeRunId = generateRunId();
				crawlerStoppedByUser = false;
				clearResults();
				stopRateTimer();
				streamStartedAt = 0;
				updateStats({processed: 0, found: 0, failed: 0, looked_up: 0});
				updateProgress(0, 1);
				setLoading(true);
				setMessage("info", "Iniciando webcrawler com intervalo " + delayText + " entre consultas...");
				statusText.textContent = "Conectando...";
				updateMonitorHeight();

				const url = new URL(streamUrl, window.location.origin);
				url.searchParams.set("state_acronym", stateAcronym);
				url.searchParams.set("delay_mode", delayMode);
				url.searchParams.set("delay_ms", delayMs);
				url.searchParams.set("concurrency", concurrency);
				url.searchParams.set("max_results", maxResults);
				url.searchParams.set("provider_mode", providerMode);
				url.searchParams.set("save_city", "1");
				url.searchParams.set("save_neighborhood", "1");
				url.searchParams.set("create_city", "0");
				url.searchParams.set("run_id", activeRunId);

				eventSource = new EventSource(url.toString());

				eventSource.addEventListener("cache", function (event)
					{
						if (crawlerStoppedByUser)
							{
								return;
							}

						const payload = JSON.parse(event.data || "{}");
						const text = cacheStatusText(payload);
						const isDone = String(payload.stage || "") === "loaded";

						statusText.textContent = isDone ? "TXT carregado" : "Lendo TXT";
						setMessage(isDone ? "success" : "info", text);
						setResultsInfo(isDone ? "CACHE" : "TXT", text);
						appendSystemLine(isDone ? "CACHE" : "TXT", text);
					});

				eventSource.addEventListener("start", function (event)
					{
						if (crawlerStoppedByUser)
							{
								return;
							}

						const payload = JSON.parse(event.data || "{}");
						streamStartedAt = Date.now();
						updateStats({processed: 0, found: 0, failed: 0, looked_up: 0});
						startRateTimer();
						statusText.textContent = "Em execução";
						const ranges = payload.ranges || selectedState.ranges || [];
						const rangeText = formatRangeList(ranges);
						const cacheCount = Number(payload.not_found_cache_count || 0);
						const activeConcurrency = String(payload.concurrency || concurrency);
						const activeDelayText = intervalSummary(payload.delay_mode || delayMode, payload.delay_ms || delayMs);
						const autoSaveText = payload.auto_save_neighborhood ? "salvamento automático ativo" : "salvamento automático desativado";
						const viaCepPaused = payload.viacep_available === false;
						const viaCepDisabledUntil = formatClockTimeFromTimestamp(payload.viacep_disabled_until_timestamp)
							|| String(payload.viacep_disabled_until || "");
						const viaCepText = viaCepPaused ? " ViaCEP pausado temporariamente; usando fallback quando disponível." : "";
						setMessage(viaCepPaused ? "warning" : "info", "Pesquisa iniciada para " + formatInteger(payload.total || 0) + " CEPs em " + stateAcronym + " usando " + (payload.provider_mode || providerMode).toUpperCase() + ". Paralelo: " + activeConcurrency + ". " + autoSaveText + ". Cache TXT: " + formatInteger(cacheCount) + " CEP(s)." + viaCepText);
						updateProgress(0, payload.total || 1);
						setResultsInfo("INIT", "Pesquisa iniciada | UF: " + stateAcronym + " | " + formatInteger(payload.total || 0) + " CEPs | faixas: " + rangeText + " | cache TXT: " + formatInteger(cacheCount) + " CEPs | webservice: " + (payload.provider_mode || providerMode).toUpperCase() + " | consultas simultâneas: " + activeConcurrency + " | intervalo: " + activeDelayText + " | " + autoSaveText);
						if (viaCepPaused)
							{
								appendSystemLine("WARN", "ViaCEP pausado temporariamente por falhas de conexão" + (viaCepDisabledUntil ? " até " + viaCepDisabledUntil : "") + ".");
							}
					});

				eventSource.addEventListener("progress", function (event)
					{
						if (crawlerStoppedByUser)
							{
								return;
							}

						const payload = JSON.parse(event.data || "{}");
						updateStats(payload);
						updateProgress(payload.position || 0, payload.total || 1);
						statusText.textContent = "Processando " + (payload.zip_code || "-");
						appendResultRow(payload);
					});

				eventSource.addEventListener("done", function (event)
					{
						if (crawlerStoppedByUser)
							{
								return;
							}

						const payload = JSON.parse(event.data || "{}");
						updateStats(payload);
						stopRateTimer();
						updateProgress(payload.total || 1, payload.total || 1);
						statusText.textContent = "Concluído";
						setMessage("success", "Pesquisa concluída com " + (payload.found || 0) + " novo(s) salvo(s).");
						appendSystemLine("DONE", "Pesquisa concluída | " + (payload.found || 0) + " novo(s) salvo(s) | " + (payload.failed || 0) + " falha(s) de consulta | " + (payload.looked_up || 0) + " consulta(s) em webservices");
						setLoading(false);
						eventSource.close();
						eventSource = null;
						activeRunId = "";
						crawlerStoppedByUser = false;
					});

				eventSource.addEventListener("error", function ()
					{
						if (!eventSource)
							{
								return;
							}

						if (crawlerStoppedByUser)
							{
								return;
							}

						setMessage("danger", "A conexão da pesquisa foi interrompida.");
						statusText.textContent = "Interrompido";
						appendSystemLine("ERROR", "Conexão interrompida - verifique a rede e tente novamente");
						stopRateTimer();
						setLoading(false);
						eventSource.close();
						eventSource = null;
						activeRunId = "";
					});
			}

		startInput.addEventListener("input", function ()
			{
				startInput.value = formatZipCode(startInput.value);
			});

		endInput.addEventListener("input", function ()
			{
				endInput.value = formatZipCode(endInput.value);
			});

		if (stateInput)
			{
				stateInput.addEventListener("change", updateStateRangeSummary);
			}

		delayModeInput.addEventListener("change", updateDelayLabel);
		delayInput.addEventListener("input", updateDelayLabel);
		concurrencyInput.addEventListener("input", updateConcurrencyLabel);
		resultsBody.addEventListener("scroll", updateResultsStickiness, {passive: true});
		resultsTopButton.addEventListener("click", scrollResultsToTop);
		resultsBottomButton.addEventListener("click", scrollResultsToBottom);
		window.addEventListener("resize", updateMonitorHeight, {passive: true});

		form.addEventListener("submit", function (event)
			{
				event.preventDefault();
				openStream();
			});

		stopButton.addEventListener("click", function ()
			{
				const runId = activeRunId;
				crawlerStoppedByUser = true;
				requestCrawlerStop(runId);

				if (eventSource)
					{
						eventSource.close();
						eventSource = null;
					}

				activeRunId = "";
				clearQueuedResults();
				appendSystemLine("STOP", "Pesquisa interrompida pelo usuário");
				setMessage("warning", "Pesquisa interrompida pelo usuário.");
				statusText.textContent = "Parado";
				stopRateTimer();
				setLoading(false);
			});

		clearButton.addEventListener("click", function ()
			{
				const runId = activeRunId;
				if (runId)
					{
						crawlerStoppedByUser = true;
						requestCrawlerStop(runId);
					}

				if (eventSource)
					{
						eventSource.close();
						eventSource = null;
					}

				activeRunId = "";
				stopRateTimer();
				streamStartedAt = 0;
				if (stateInput)
					{
						stateInput.value = "";
					}
				startInput.value = "";
				endInput.value = "";
				delayModeInput.value = "fixed";
				delayInput.value = "500";
				concurrencyInput.value = "3";
				providerModeInput.value = "fallback";
				updateDelayLabel();
				updateConcurrencyLabel();
				updateStateRangeSummary();
				clearResults();
				updateStats({processed: 0, found: 0, failed: 0, looked_up: 0});
				updateProgress(0, 1);
				statusText.textContent = "Aguardando início";
				setMessage("secondary", "Selecione um estado e inicie a pesquisa.");
				setLoading(false);
				if (stateInput)
					{
						stateInput.focus();
					}
			});

		updateStateRangeSummary();
		updateDelayLabel();
		updateConcurrencyLabel();
		updateMonitorHeight();
	})();

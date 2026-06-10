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
		const delayInput = document.getElementById("crawlerDelayMs");
		const delayLabel = document.getElementById("crawlerDelayLabel");
		const concurrencyInput = document.getElementById("crawlerConcurrency");
		const concurrencyLabel = document.getElementById("crawlerConcurrencyLabel");
		const maxResultsInput = document.getElementById("crawlerMaxResults");
		const clearButton = document.getElementById("crawlerClearButton");
		const stopButton = document.getElementById("crawlerStopButton");
		const startButton = document.getElementById("crawlerStartButton");
		const message = document.getElementById("crawlerMessage");
		const progressBar = document.getElementById("crawlerProgressBar");
		const resultsBody = document.getElementById("crawlerResultsBody");
		const processedCount = document.getElementById("crawlerProcessedCount");
		const foundCount = document.getElementById("crawlerFoundCount");
		const failedCount = document.getElementById("crawlerFailedCount");
		const lookupRateCount = document.getElementById("crawlerLookupRateCount");
		const statusText = document.getElementById("crawlerStatusText");
		const streamUrl = app.dataset.streamUrl || "";
		const stateOptions = parseStateOptions();
		const stateOptionRegistry = buildStateOptionRegistry(stateOptions);

		let eventSource = null;
		let streamStartedAt = 0;
		let rateTimer = null;
		let latestStats = {
			processed: 0,
			found: 0,
			failed: 0,
			looked_up: 0
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

		function updateMaxResultsFromRange()
			{
				const total = calculateZipCodeRangeCount();
				if (total > 0)
					{
						maxResultsInput.value = String(total);
					}
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
						maxResultsInput.value = "100";
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

				updateMaxResultsFromRange();
				stateRangeSummary.textContent = (ranges.length > 1 ? "Faixas não contínuas: " : "Faixa: ")
					+ formatRangeList(ranges)
					+ " · "
					+ String(stateOption.total || 0)
					+ " CEPs";
			}

		function setMessage(type, text)
			{
				message.className = "alert zip-code-crawler-message alert-" + type;
				message.textContent = text;
			}

		function setLoading(isLoading)
			{
				startButton.disabled = isLoading;
				startButton.innerHTML = isLoading
					? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Pesquisando'
					: '<i class="bi bi-play-fill me-1"></i>Iniciar';
				stopButton.classList.toggle("d-none", !isLoading);
			}

		function updateDelayLabel()
			{
				delayLabel.textContent = delayInput.value + " ms";
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

		function updateLookupRate(payload)
			{
				if (!lookupRateCount)
					{
						return;
					}

				const elapsedMs = streamStartedAt > 0 ? Date.now() - streamStartedAt : 0;
				const lookedUp = getLookedUpCount(payload || latestStats);
				const rate = elapsedMs > 0 ? lookedUp / (elapsedMs / 1000) : 0;
				lookupRateCount.textContent = formatLookupRate(rate);
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
					looked_up: getLookedUpCount(payload)
				};

				processedCount.textContent = String(latestStats.processed);
				foundCount.textContent = String(latestStats.found);
				failedCount.textContent = String(latestStats.failed);
				updateLookupRate(latestStats);
			}

		function updateProgress(position, total)
			{
				const percentage = total > 0 ? Math.round((position / total) * 100) : 0;
				progressBar.style.width = percentage + "%";
				progressBar.textContent = percentage + "%";
			}

		function clearResults()
			{
				resultsBody.innerHTML = '<div class="crawler-console-line crawler-console-line--idle"><span class="ccl-prompt">›</span><span class="ccl-text">Aguardando início — selecione um estado e execute o crawler.</span><span class="crawler-console-cursor" aria-hidden="true"></span></div>';
			}

		function scrollResultsToBottom()
			{
				resultsBody.scrollTop = resultsBody.scrollHeight;
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
				const idleLine = resultsBody.querySelector(".crawler-console-line--idle");
				if (idleLine)
					{
						idleLine.remove();
					}
			}

		function appendSystemLine(type, text)
			{
				clearIdleLine();

				const line = document.createElement("div");
				line.className = "crawler-console-line crawler-console-line--system";

				const tsSpan = document.createElement("span");
				tsSpan.className = "ccl-ts";
				tsSpan.textContent = "[" + nowTimestamp() + "]";

				const statusSpan = document.createElement("span");
				statusSpan.className = "ccl-status ccl-status--system";
				statusSpan.textContent = type;

				const textSpan = document.createElement("span");
				textSpan.className = "ccl-system-text";
				textSpan.textContent = text;

				line.appendChild(tsSpan);
				line.appendChild(statusSpan);
				line.appendChild(textSpan);
				resultsBody.appendChild(line);
				scrollResultsToBottom();
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

				const line = document.createElement("div");
				line.className = "crawler-console-line "
					+ (isFound ? "crawler-console-line--found" : "crawler-console-line--notfound")
					+ (alreadyInDatabase ? " crawler-console-line--existing" : "");

				const selectControl = document.createElement("span");
				selectControl.className = "crawler-console-checkbox crawler-console-checkbox--empty";

				const tsSpan = document.createElement("span");
				tsSpan.className = "ccl-ts";
				tsSpan.textContent = "[" + nowTimestamp() + "]";

				const statusSpan = document.createElement("span");
				statusSpan.className = "ccl-status " + (autoSaveSaved ? "ccl-status--found" : (autoSaveFailed ? "ccl-status--notfound" : (alreadyInDatabase ? "ccl-status--database" : (isFound ? "ccl-status--found" : "ccl-status--notfound"))));
				statusSpan.textContent = autoSaveSaved ? "SAVED" : (alreadyInDatabase ? "DB" : (autoSaveFailed ? "SAVE!" : (isFound ? "FOUND" : (isSkipped ? "CACHE" : "MISS "))));

				const zipSpan = document.createElement("span");
				zipSpan.className = "ccl-zip";
				zipSpan.textContent = zipCode || "-";

				const arrowSpan = document.createElement("span");
				arrowSpan.className = "ccl-arrow";
				arrowSpan.textContent = "›";

				const sourceSpan = document.createElement("span");
				sourceSpan.className = "ccl-source";
				sourceSpan.textContent = autoSaveSaved
					? "[AUTO]"
					: (alreadyInDatabase ? "[DB]" : (result.source === "cache" ? "[TXT]" : (result.source ? "[" + String(result.source).toUpperCase() + "]" : "[-]")));

				const locationSpan = document.createElement("span");
				locationSpan.className = "ccl-location";
				if (isFound)
					{
						const parts = [data.neighborhood, data.city, data.state].filter(Boolean);
						const locationText = parts.length ? parts.join(" · ") : "-";
						const detailText = autoSave.message || (alreadyInDatabase ? databaseStatus.message : "");
						locationSpan.textContent = detailText
							? locationText + " · " + detailText
							: locationText;
					}
				else
					{
						locationSpan.textContent = isSkipped
							? "Ignorado pelo arquivo de CEPs não encontrados."
							: (result.message || "CEP não encontrado");
					}

				const saveStatus = document.createElement("span");
				saveStatus.className = "ccl-save-status";
				saveStatus.textContent = autoSaveSaved ? "SALVO" : (alreadyInDatabase ? "CADASTRADO" : (autoSaveFailed ? "ERRO" : ""));

				const actionsSpan = document.createElement("span");
				actionsSpan.className = "ccl-actions";
				actionsSpan.appendChild(saveStatus);

				line.appendChild(selectControl);
				line.appendChild(tsSpan);
				line.appendChild(statusSpan);
				line.appendChild(zipSpan);
				line.appendChild(arrowSpan);
				line.appendChild(sourceSpan);
				line.appendChild(locationSpan);
				line.appendChild(actionsSpan);

				clearIdleLine();
				resultsBody.appendChild(line);
				scrollResultsToBottom();
			}

		function openStream()
			{
				const selectedState = getSelectedStateOption();
				const stateAcronym = selectedState ? String(selectedState.acronym || "").toUpperCase() : "";
				const delayMs = String(delayInput.value || "500");
				const concurrency = String(concurrencyInput.value || "3");
				const maxResults = String(maxResultsInput.value || calculateZipCodeRangeCount() || "100");
				const providerMode = providerModeInput.value || "fallback";

				if (!selectedState || stateAcronym === "")
					{
						setMessage("warning", "Selecione um estado para iniciar a pesquisa.");
						return;
					}

				if (eventSource)
					{
						eventSource.close();
					}

				clearResults();
				stopRateTimer();
				streamStartedAt = 0;
				updateStats({processed: 0, found: 0, failed: 0, looked_up: 0});
				updateProgress(0, 1);
				setLoading(true);
				setMessage("info", "Iniciando webcrawler com atraso controlado entre consultas...");
				statusText.textContent = "Conectando...";

				const url = new URL(streamUrl, window.location.origin);
				url.searchParams.set("state_acronym", stateAcronym);
				url.searchParams.set("delay_ms", delayMs);
				url.searchParams.set("concurrency", concurrency);
				url.searchParams.set("max_results", maxResults);
				url.searchParams.set("provider_mode", providerMode);
				url.searchParams.set("save_city", "1");
				url.searchParams.set("save_neighborhood", "1");
				url.searchParams.set("create_city", "0");

				eventSource = new EventSource(url.toString());

				eventSource.addEventListener("start", function (event)
					{
						const payload = JSON.parse(event.data || "{}");
						streamStartedAt = Date.now();
						updateStats({processed: 0, found: 0, failed: 0, looked_up: 0});
						startRateTimer();
						statusText.textContent = "Em execução";
						const ranges = payload.ranges || selectedState.ranges || [];
						const rangeText = formatRangeList(ranges);
						const cacheCount = Number(payload.not_found_cache_count || 0);
						const activeConcurrency = String(payload.concurrency || concurrency);
						const autoSaveText = payload.auto_save_neighborhood ? "salvamento automático ativo" : "salvamento automático desativado";
						setMessage("info", "Pesquisa iniciada para " + (payload.total || 0) + " CEPs em " + stateAcronym + " usando " + (payload.provider_mode || providerMode).toUpperCase() + ". Paralelo: " + activeConcurrency + ". " + autoSaveText + ". Cache TXT: " + cacheCount + " CEP(s).");
						updateProgress(0, payload.total || 1);
						appendSystemLine("INIT", "scanner ativo | UF: " + stateAcronym + " | " + (payload.total || 0) + " CEPs | faixas: " + rangeText + " | cache TXT: " + cacheCount + " CEPs | provider: " + (payload.provider_mode || providerMode).toUpperCase() + " | paralelo: " + activeConcurrency + " | rodada: " + delayMs + "ms | " + autoSaveText);
					});

				eventSource.addEventListener("progress", function (event)
					{
						const payload = JSON.parse(event.data || "{}");
						updateStats(payload);
						updateProgress(payload.position || 0, payload.total || 1);
						statusText.textContent = "Processando " + (payload.zip_code || "-");
						appendResultRow(payload);
					});

				eventSource.addEventListener("done", function (event)
					{
						const payload = JSON.parse(event.data || "{}");
						updateStats(payload);
						stopRateTimer();
						updateProgress(payload.total || 1, payload.total || 1);
						statusText.textContent = "Concluído";
						setMessage("success", "Pesquisa concluída com " + (payload.found || 0) + " novo(s) salvo(s).");
						appendSystemLine("DONE", "scan completo | " + (payload.found || 0) + " novo(s) salvo(s) | " + (payload.failed || 0) + " não encontrado(s) | " + (payload.looked_up || 0) + " consulta(s) em webservices");
						setLoading(false);
						eventSource.close();
						eventSource = null;
						scrollResultsToBottom();
					});

				eventSource.addEventListener("error", function ()
					{
						if (!eventSource)
							{
								return;
							}

						setMessage("danger", "A conexão da pesquisa foi interrompida.");
						statusText.textContent = "Interrompido";
						appendSystemLine("ERROR", "conexão interrompida — verifique a rede e tente novamente");
						stopRateTimer();
						setLoading(false);
						eventSource.close();
						eventSource = null;
					});
			}

		startInput.addEventListener("input", function ()
			{
				startInput.value = formatZipCode(startInput.value);
				updateMaxResultsFromRange();
			});

		endInput.addEventListener("input", function ()
			{
				endInput.value = formatZipCode(endInput.value);
				updateMaxResultsFromRange();
			});

		if (stateInput)
			{
				stateInput.addEventListener("change", updateStateRangeSummary);
			}

		delayInput.addEventListener("input", updateDelayLabel);
		concurrencyInput.addEventListener("input", updateConcurrencyLabel);

		form.addEventListener("submit", function (event)
			{
				event.preventDefault();
				updateMaxResultsFromRange();
				openStream();
			});

		stopButton.addEventListener("click", function ()
			{
				if (eventSource)
					{
						eventSource.close();
						eventSource = null;
					}

				appendSystemLine("STOP", "pesquisa interrompida pelo usuário");
				setMessage("warning", "Pesquisa interrompida pelo usuário.");
				statusText.textContent = "Parado";
				stopRateTimer();
				setLoading(false);
			});

		clearButton.addEventListener("click", function ()
			{
				if (eventSource)
					{
						eventSource.close();
						eventSource = null;
					}

				stopRateTimer();
				streamStartedAt = 0;
				if (stateInput)
					{
						stateInput.value = "";
					}
				startInput.value = "";
				endInput.value = "";
				delayInput.value = "500";
				concurrencyInput.value = "3";
				maxResultsInput.value = "100";
				providerModeInput.value = "fallback";
				maxResultsInput.value = "100";
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
	})();

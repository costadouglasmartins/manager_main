(function ()
	{
		const app = document.getElementById("ipRangeWebCrawlerApp");
		if (!app)
			{
				return;
			}

		const form = document.getElementById("ipRangeWebCrawlerForm");
		const rangeModeInput = document.getElementById("ipCrawlerRangeMode");
		const startIpInput = document.getElementById("ipCrawlerStartIp");
		const ipQuantityInput = document.getElementById("ipCrawlerIpQuantity");
		const manualRangesInput = document.getElementById("ipCrawlerManualRanges");
		const countryCodeInput = document.getElementById("ipCrawlerCountryCode");
		const importCountryButton = document.getElementById("ipCrawlerImportCountryButton");
		const countryHint = document.getElementById("ipCrawlerCountryHint");
		const maxIpsInput = document.getElementById("ipCrawlerMaxIps");
		const maxIpsHint = document.getElementById("ipCrawlerMaxIpsHint");
		const timeoutInput = document.getElementById("ipCrawlerTimeoutMs");
		const timeoutLabel = document.getElementById("ipCrawlerTimeoutLabel");
		const concurrencyInput = document.getElementById("ipCrawlerConcurrency");
		const concurrencyLabel = document.getElementById("ipCrawlerConcurrencyLabel");
		const skipExistingInput = document.getElementById("ipCrawlerSkipExisting");
		const message = document.getElementById("ipCrawlerMessage");
		const clearButton = document.getElementById("ipCrawlerClearButton");
		const stopButton = document.getElementById("ipCrawlerStopButton");
		const startButton = document.getElementById("ipCrawlerStartButton");
		const progressText = document.getElementById("ipCrawlerProgressText");
		const progressBar = document.getElementById("ipCrawlerProgressBar");
		const progressRoot = progressBar ? progressBar.closest(".progress") : null;
		const targetCount = document.getElementById("ipCrawlerTargetCount");
		const processedCount = document.getElementById("ipCrawlerProcessedCount");
		const foundCount = document.getElementById("ipCrawlerFoundCount");
		const matchedCount = document.getElementById("ipCrawlerMatchedCount");
		const failedCount = document.getElementById("ipCrawlerFailedCount");
		const statusText = document.getElementById("ipCrawlerStatusText");
		const skippedBadge = document.getElementById("ipCrawlerSkippedBadge");
		const resultsBody = document.getElementById("ipCrawlerResultsBody");
		const resultsInfo = document.getElementById("ipCrawlerResultsInfo");
		const resultsTopButton = document.getElementById("ipCrawlerResultsTopButton");
		const resultsBottomButton = document.getElementById("ipCrawlerResultsBottomButton");
		const streamUrl = app.dataset.streamUrl || "";
		const importCountryUrl = app.dataset.importCountryUrl || "";
		let eventSource = null;
		let resultsStickToBottom = true;
		let foundResults = 0;
		let maxIpsDirty = false;

		function numericMetric(value)
			{
				const number = Number(value);
				return Number.isFinite(number) ? number : 0;
			}

		function formatInteger(value)
			{
				return numericMetric(value).toLocaleString("pt-BR", {
					maximumFractionDigits: 0
				});
			}

		function escapeHtml(value)
			{
				return String(value || "")
					.replace(/&/g, "&amp;")
					.replace(/</g, "&lt;")
					.replace(/>/g, "&gt;")
					.replace(/"/g, "&quot;")
					.replace(/'/g, "&#039;");
			}

		function setMessage(type, text)
			{
				if (!message)
					{
						return;
					}

				if (!text)
					{
						message.className = "alert d-none mb-0";
						message.textContent = "";
						return;
					}

				message.className = "alert alert-" + type + " mb-0";
				message.textContent = text;
			}

		function setLoading(isLoading)
			{
				app.classList.toggle("is-busy", isLoading);
				startButton.disabled = isLoading;
				if (importCountryButton)
					{
						importCountryButton.disabled = isLoading;
					}
				startButton.innerHTML = isLoading
					? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Pesquisando'
					: '<i class="bi bi-play-fill me-1"></i>Iniciar';
				stopButton.classList.toggle("d-none", !isLoading);
			}

		function setCountryImportLoading(isLoading)
			{
				if (!importCountryButton)
					{
						return;
					}

				importCountryButton.disabled = isLoading;
				importCountryButton.innerHTML = isLoading
					? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Importando'
					: '<i class="bi bi-cloud-arrow-down me-1"></i>Importar faixas';
			}

		function updateModePanels()
			{
				const mode = rangeModeInput.value || "amount";
				document.querySelectorAll("[data-mode-panel]").forEach(function (panel)
					{
						panel.classList.toggle("d-none", panel.dataset.modePanel !== mode);
					});

				maxIpsInput.disabled = mode === "amount";
				if (mode !== "country")
					{
						updateMaxIpsHint(0);
					}
				updateCountryHint();
			}

		function selectedCountryOption()
			{
				if (!countryCodeInput)
					{
						return null;
					}

				const selectedIndex = countryCodeInput.selectedIndex;
				if (selectedIndex < 0)
					{
						return null;
					}

				return countryCodeInput.options[selectedIndex] || null;
			}

		function updateCountryHint()
			{
				if (!countryHint || !countryCodeInput || (rangeModeInput.value || "amount") !== "country")
					{
						return;
					}

				const option = selectedCountryOption();
				if (!option || !option.value)
					{
						countryHint.innerHTML = "Selecione um país para verificar se já existem faixas carregadas.";
						updateMaxIpsHint(0);
						return;
					}

				const ipTotal = numericMetric(option.dataset.ipTotal);
				const processedTotal = numericMetric(option.dataset.processedTotal);
				const remainingTotal = numericMetric(option.dataset.remainingTotal);
				const availableTotal = remainingTotal > 0 ? remainingTotal : Math.max(0, ipTotal - processedTotal);
				if (ipTotal <= 0)
					{
						countryHint.innerHTML = "Este país ainda não possui faixas em <code>webcrawler_ip_ranges</code>.";
						updateMaxIpsHint(0);
						return;
					}

				if (availableTotal <= 0)
					{
						countryHint.textContent = formatInteger(ipTotal) + " IPs já foram consumidos por resultados salvos para este país.";
						updateMaxIpsHint(0);
						applyRecommendedMaxIps(0, true);
						return;
					}

				countryHint.textContent = formatInteger(availableTotal) + " IPs disponíveis para este país após descontar resultados salvos.";
				updateMaxIpsHint(availableTotal);
				applyRecommendedMaxIps(availableTotal);
			}

		function recommendedMaxIps(ipTotal)
			{
				return Math.max(0, numericMetric(ipTotal));
			}

		function applyRecommendedMaxIps(ipTotal, force)
			{
				if (!maxIpsInput)
					{
						return;
					}

				const recommendedValue = recommendedMaxIps(ipTotal);
				const currentValue = numericMetric(maxIpsInput.value);

				if (force || !maxIpsDirty || currentValue <= 500)
					{
						maxIpsInput.value = String(recommendedValue);
					}
			}

		function updateMaxIpsHint(ipTotal)
			{
				if (!maxIpsHint)
					{
						return;
					}

				if (numericMetric(ipTotal) <= 0)
					{
					maxIpsHint.textContent = "Cada IP gera 2 alvos padrão: porta 80 e porta 443. Deixe em branco para calcular automaticamente o total disponível.";
						return;
					}

				const recommendedValue = recommendedMaxIps(ipTotal);
				const targetEstimate = recommendedValue * 2;
				maxIpsHint.textContent = "Sugestão automática para este país: " + formatInteger(recommendedValue) + " IPs (" + formatInteger(targetEstimate) + " alvos).";
			}

		function rebuildCountryOptions(countryOptions, selectedCountryCode)
			{
				if (!countryCodeInput || !Array.isArray(countryOptions))
					{
						return;
					}

				const previousValue = selectedCountryCode || countryCodeInput.value || "";
				countryCodeInput.innerHTML = '<option value="">Selecione o país</option>';

				countryOptions.forEach(function (countryOption)
					{
						if (!countryOption || !countryOption.country_code)
							{
								return;
							}

						const option = document.createElement("option");
						const ipTotal = numericMetric(countryOption.ip_total);
						const processedTotal = numericMetric(countryOption.processed_total);
						const remainingTotal = numericMetric(countryOption.remaining_total);
						const hasRanges = !!countryOption.has_ranges;
						option.value = String(countryOption.country_code);
						option.dataset.ipTotal = String(ipTotal);
						option.dataset.processedTotal = String(processedTotal);
						option.dataset.remainingTotal = String(remainingTotal);
						option.dataset.hasRanges = hasRanges ? "1" : "0";
						option.textContent = String(countryOption.country_name || countryOption.country_code)
							+ " (" + String(countryOption.country_code) + ") - "
							+ formatInteger(remainingTotal > 0 ? remainingTotal : Math.max(0, ipTotal - processedTotal)) + " IPs disponíveis"
							+ (hasRanges ? "" : " - sem faixas importadas");
						countryCodeInput.appendChild(option);
					});

				countryCodeInput.value = previousValue;
				if (countryCodeInput.value !== previousValue)
					{
						countryCodeInput.value = "";
					}

				updateCountryHint();
			}

		async function importCountryRanges()
			{
				if (!importCountryUrl)
					{
						setMessage("danger", "A rota de importação de países não está disponível.");
						return;
					}

				const countryCode = String(countryCodeInput.value || "").trim();
				if (!countryCode)
					{
						setMessage("warning", "Selecione um país antes de importar as faixas.");
						countryCodeInput.focus();
						return;
					}

				setCountryImportLoading(true);
				setMessage("info", "Importando faixas IPv4 do país selecionado.");

				try
					{
						const response = await fetch(importCountryUrl, {
							method: "POST",
							headers: {
								"Accept": "application/json",
								"Content-Type": "application/json",
								"X-Requested-With": "XMLHttpRequest"
							},
							body: JSON.stringify({
								country_code: countryCode
							})
						});
						const payload = await response.json().catch(function ()
							{
								return {};
							});

						if (!response.ok || !payload.success)
							{
								setMessage("danger", payload.message || "Não foi possível importar as faixas do país.");
								updateCountryHint();
								return;
							}

						rebuildCountryOptions(payload.country_options || [], payload.country_code || countryCode);
						maxIpsDirty = false;
						if (payload.stats && Object.prototype.hasOwnProperty.call(payload.stats, "ip_total"))
							{
								applyRecommendedMaxIps(payload.stats.ip_total, true);
								updateMaxIpsHint(payload.stats.ip_total);
							}
						setMessage("success", payload.message || "Faixas importadas com sucesso.");
					}
				catch (error)
					{
						setMessage("danger", "Falha ao importar as faixas do país.");
					}
				finally
					{
						setCountryImportLoading(false);
					}
			}

		function updateTimeoutLabel()
			{
				timeoutLabel.textContent = formatInteger(timeoutInput.value) + " ms";
			}

		function updateConcurrencyLabel()
			{
				concurrencyLabel.textContent = formatInteger(concurrencyInput.value);
			}

		function parsePayload(event)
			{
				try
					{
						return JSON.parse(event.data || "{}");
					}
				catch (error)
					{
						return {};
					}
			}

		function isAtBottom()
			{
				if (!resultsBody)
					{
						return true;
					}

				return (resultsBody.scrollHeight - resultsBody.scrollTop - resultsBody.clientHeight) < 24;
			}

		function scrollResultsToBottom()
			{
				if (!resultsBody)
					{
						return;
					}

				resultsBody.scrollTop = resultsBody.scrollHeight;
				resultsStickToBottom = true;
				hideResultsInfo();
			}

		function hideResultsInfo()
			{
				if (!resultsInfo)
					{
						return;
					}

				resultsInfo.classList.add("d-none");
				resultsInfo.textContent = "";
			}

		function showResultsInfo(text)
			{
				if (!resultsInfo)
					{
						return;
					}

				resultsInfo.classList.remove("d-none");
				resultsInfo.innerHTML = '<button class="list-group-item list-group-item-action text-primary" type="button">' + escapeHtml(text) + "</button>";
				const button = resultsInfo.querySelector("button");
				if (button)
					{
						button.addEventListener("click", scrollResultsToBottom);
					}
			}

		function clearResults()
			{
				foundResults = 0;
				resultsStickToBottom = true;
				hideResultsInfo();
				resultsBody.innerHTML = '<div class="list-group-item text-secondary ip-range-webcrawler-results-empty"><div class="d-flex align-items-center gap-2"><i class="bi bi-hourglass-split"></i><span>Aguardando início da pesquisa.</span></div></div>';
			}

		function updateStats(stats)
			{
				const targetTotal = numericMetric(stats.target_total);
				const processedTotal = numericMetric(stats.processed_total);
				const skippedTotal = numericMetric(stats.skipped_total);
				const handledTotal = numericMetric(stats.handled_total || (processedTotal + skippedTotal));
				const percent = targetTotal > 0 ? Math.min(100, Math.round((handledTotal / targetTotal) * 100)) : 0;

				targetCount.textContent = formatInteger(targetTotal);
				processedCount.textContent = formatInteger(processedTotal);
				foundCount.textContent = formatInteger(stats.found_total);
				matchedCount.textContent = formatInteger(stats.matched_total);
				failedCount.textContent = formatInteger(stats.failed_total);
				statusText.textContent = String(stats.status || "running") === "completed" ? "Finalizado" : "Executando";
				skippedBadge.textContent = formatInteger(skippedTotal) + " ignorados";
				progressText.textContent = percent + "%";
				progressBar.style.width = percent + "%";
				progressBar.setAttribute("aria-valuenow", String(percent));
				if (progressRoot)
					{
						progressRoot.setAttribute("aria-valuenow", String(percent));
					}
			}

		function resultBadges(result)
			{
				const badges = ['<span class="badge text-bg-success">HTTP 200</span>'];
				if (result.keyword_matched)
					{
						badges.push('<span class="badge text-bg-warning">match</span>');
					}

				if (result.server_header)
					{
						badges.push('<span class="badge text-bg-secondary">' + escapeHtml(result.server_header) + "</span>");
					}

				return badges.join(" ");
			}

		function appendResult(result)
			{
				const shouldStick = resultsStickToBottom && isAtBottom();
				const empty = resultsBody.querySelector(".ip-range-webcrawler-results-empty");
				if (empty)
					{
						empty.remove();
					}

				foundResults++;

				const item = document.createElement("div");
				const title = result.title ? result.title : result.url;
				const keywords = Array.isArray(result.matched_keywords) && result.matched_keywords.length > 0
					? '<div class="small text-warning-emphasis mt-1">Palavras: ' + escapeHtml(result.matched_keywords.join(", ")) + "</div>"
					: "";
				const snippet = result.snippet
					? '<div class="small text-secondary ip-range-webcrawler-result-detail mt-1">' + escapeHtml(result.snippet) + "</div>"
					: "";
				const redirect = result.redirect_url
					? '<div class="small text-secondary ip-range-webcrawler-result-detail mt-1">Redirecionou para ' + escapeHtml(result.redirect_url) + "</div>"
					: "";

				item.className = "list-group-item ip-range-webcrawler-result-item";
				item.innerHTML = '<div class="d-flex flex-column flex-lg-row gap-2 justify-content-between">'
					+ '<div class="ip-range-webcrawler-result-detail">'
					+ '<div class="fw-semibold">' + escapeHtml(title) + "</div>"
					+ '<a class="small" href="' + escapeHtml(result.url) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(result.url) + "</a>"
					+ snippet
					+ keywords
					+ redirect
					+ "</div>"
					+ '<div class="text-lg-end flex-shrink-0">'
					+ '<div class="d-flex flex-wrap justify-content-lg-end gap-1 mb-1">' + resultBadges(result) + "</div>"
					+ '<small class="text-secondary">' + formatInteger(result.response_time_ms) + " ms</small>"
					+ "</div>"
					+ "</div>";

				resultsBody.appendChild(item);

				if (shouldStick)
					{
						scrollResultsToBottom();
					}
				else
					{
						showResultsInfo(formatInteger(foundResults) + " resultados salvos. Clique para acompanhar o final.");
					}
			}

		function validateForm()
			{
				const mode = rangeModeInput.value || "amount";
				if (mode === "amount" && !String(startIpInput.value || "").trim())
					{
						setMessage("warning", "Informe o IP inicial.");
						startIpInput.focus();
						return false;
					}

				if (mode === "amount" && numericMetric(ipQuantityInput.value) <= 0)
					{
						setMessage("warning", "Informe uma quantidade válida de IPs.");
						ipQuantityInput.focus();
						return false;
					}

				if (mode === "manual" && !String(manualRangesInput.value || "").trim())
					{
						setMessage("warning", "Informe ao menos um IP, faixa ou bloco CIDR.");
						manualRangesInput.focus();
						return false;
					}

				if (mode === "country" && !String(countryCodeInput.value || "").trim())
					{
						setMessage("warning", "Selecione um país com faixas importadas.");
						countryCodeInput.focus();
						return false;
					}

				if (mode === "country")
					{
						const option = selectedCountryOption();
						if (option && option.value && numericMetric(option.dataset.ipTotal) <= 0)
							{
								setMessage("warning", "O país selecionado ainda não possui faixas de IP importadas.");
								countryCodeInput.focus();
								return false;
							}
					}

				return true;
			}

		function streamRequestUrl()
			{
				const formData = new FormData(form);
				const params = new URLSearchParams();

				formData.forEach(function (value, key)
					{
						params.set(key, value);
					});

				params.set("skip_existing", skipExistingInput.checked ? "1" : "0");

				return streamUrl + (streamUrl.indexOf("?") === -1 ? "?" : "&") + params.toString();
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
					skipped_total: 0,
					handled_total: 0,
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
				maxIpsDirty = false;
				updateModePanels();
				updateTimeoutLabel();
				updateConcurrencyLabel();
				clearResults();
				setMessage("", "");
				updateStats({
					target_total: 0,
					processed_total: 0,
					skipped_total: 0,
					handled_total: 0,
					found_total: 0,
					matched_total: 0,
					failed_total: 0,
					status: "idle"
				});
				statusText.textContent = "Aguardando início";
			});

		stopButton.addEventListener("click", function ()
			{
				closeStream();
				setMessage("warning", "Pesquisa interrompida na tela.");
				statusText.textContent = "Interrompido";
			});

		resultsBody.addEventListener("scroll", function ()
			{
				resultsStickToBottom = isAtBottom();
				if (resultsStickToBottom)
					{
						hideResultsInfo();
					}
			});

		resultsTopButton.addEventListener("click", function ()
			{
				resultsBody.scrollTop = 0;
				resultsStickToBottom = false;
			});

		resultsBottomButton.addEventListener("click", scrollResultsToBottom);
		if (importCountryButton)
			{
				importCountryButton.addEventListener("click", importCountryRanges);
			}
		rangeModeInput.addEventListener("change", updateModePanels);
		countryCodeInput.addEventListener("change", updateCountryHint);
		maxIpsInput.addEventListener("input", function ()
			{
				maxIpsDirty = true;
			});
		timeoutInput.addEventListener("input", updateTimeoutLabel);
		concurrencyInput.addEventListener("input", updateConcurrencyLabel);

		updateModePanels();
		updateTimeoutLabel();
		updateConcurrencyLabel();
	})();

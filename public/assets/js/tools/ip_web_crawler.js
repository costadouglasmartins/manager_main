(function ()
	{
		const app = document.getElementById("ipWebCrawlerApp");
		if (!app)
			{
				return;
			}

		const form = document.getElementById("ipWebCrawlerForm");
		const rangeModeInput = document.getElementById("ipCrawlerRangeMode");
		const startIpInput = document.getElementById("ipCrawlerStartIp");
		const quantityInput = document.getElementById("ipCrawlerQuantity");
		const endIpInput = document.getElementById("ipCrawlerEndIp");
		const cidrInput = document.getElementById("ipCrawlerCidr");
		const portsInput = document.getElementById("ipCrawlerPorts");
		const countryCodeInput = document.getElementById("ipCrawlerCountryCode");
		const customCountryCodeInput = document.getElementById("ipCrawlerCustomCountryCode");
		const countryRangeSelect = document.getElementById("ipCrawlerCountryRangeSelect");
		const countrySummary = document.getElementById("ipCrawlerCountrySummary");
		const loadCountryButton = document.getElementById("ipCrawlerLoadCountryButton");
		const refreshCountryButton = document.getElementById("ipCrawlerRefreshCountryButton");
		const userAgentPresetInput = document.getElementById("ipCrawlerUserAgentPreset");
		const userAgentInput = document.getElementById("ipCrawlerUserAgent");
		const generateRangeButton = document.getElementById("ipCrawlerGenerateRangeButton");
		const rangePreview = document.getElementById("ipCrawlerRangePreview");
		const clearButton = document.getElementById("ipCrawlerClearButton");
		const stopButton = document.getElementById("ipCrawlerStopButton");
		const startButton = document.getElementById("ipCrawlerStartButton");
		const message = document.getElementById("ipCrawlerMessage");
		const resultsBody = document.getElementById("ipCrawlerResultsBody");
		const resultsBottomButton = document.getElementById("ipCrawlerResultsBottomButton");
		const processedCount = document.getElementById("ipCrawlerProcessedCount");
		const foundCount = document.getElementById("ipCrawlerFoundCount");
		const skippedCount = document.getElementById("ipCrawlerSkippedCount");
		const failedCount = document.getElementById("ipCrawlerFailedCount");
		const statusText = document.getElementById("ipCrawlerStatusText");
		const progress = document.getElementById("ipCrawlerProgress");
		const progressBar = document.getElementById("ipCrawlerProgressBar");
		const progressText = document.getElementById("ipCrawlerProgressText");
		const streamUrl = app.dataset.streamUrl || "";
		const countryRangesUrl = app.dataset.countryRangesUrl || "";

		let eventSource = null;
		let currentRange = null;
		let shouldFollowResults = true;
		const maxTargetsPerRun = 2000;
		const resultsBottomThreshold = 24;

		function setMessage(type, text)
			{
				message.className = "alert ip-web-crawler-message alert-" + type;
				message.textContent = text;
			}

		function setLoading(isLoading)
			{
				app.classList.toggle("is-busy", isLoading);
				startButton.disabled = isLoading;
				stopButton.classList.toggle("d-none", !isLoading);
				startButton.innerHTML = isLoading
					? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Pesquisando'
					: '<i class="bi bi-play-fill me-1"></i>Iniciar';
			}

		function formatInteger(value)
			{
				return Number(value || 0).toLocaleString("pt-BR", {
					maximumFractionDigits: 0
				});
			}

		function escapeHtml(value)
			{
				const div = document.createElement("div");
				div.textContent = String(value || "");
				return div.innerHTML;
			}

		function ipToLong(ip)
			{
				const parts = String(ip || "").trim().split(".");
				if (parts.length !== 4)
					{
						return null;
					}

				let value = 0;
				for (let index = 0; index < parts.length; index++)
					{
						if (!/^\d+$/.test(parts[index]))
							{
								return null;
							}

						const octet = Number(parts[index]);
						if (!Number.isInteger(octet) || octet < 0 || octet > 255)
							{
								return null;
							}

						value = (value * 256) + octet;
					}

				return value;
			}

		function longToIp(value)
			{
				if (!Number.isInteger(value) || value < 0 || value > 4294967295)
					{
						return "";
					}

				return [
					Math.floor(value / 16777216) % 256,
					Math.floor(value / 65536) % 256,
					Math.floor(value / 256) % 256,
					value % 256
				].join(".");
			}

		function parsePorts()
			{
				const ports = new Set();
				String(portsInput.value || "").split(/[\s,;]+/).forEach(function (chunk)
					{
						if (!chunk)
							{
								return;
							}

						const rangeMatch = chunk.match(/^(\d{1,5})-(\d{1,5})$/);
						if (rangeMatch)
							{
								const start = Math.max(1, Math.min(65535, Number(rangeMatch[1])));
								const end = Math.max(1, Math.min(65535, Number(rangeMatch[2])));
								for (let port = Math.min(start, end); port <= Math.max(start, end) && ports.size < 20; port++)
									{
										ports.add(port);
									}
								return;
							}

						const port = Number(chunk.replace(/\D+/g, ""));
						if (Number.isInteger(port) && port >= 1 && port <= 65535 && ports.size < 20)
							{
								ports.add(port);
							}
					});

				return Array.from(ports);
			}

		function selectedProtocols()
			{
				return Array.from(form.querySelectorAll("input[name='protocols[]']:checked")).map(function (input)
					{
						return input.value;
					});
			}

		function targetMultiplier()
			{
				return Math.max(1, parsePorts().length) * Math.max(1, selectedProtocols().length);
			}

		function maxIpsPerCountryRange()
			{
				return Math.max(1, Math.floor(maxTargetsPerRun / targetMultiplier()));
			}

		function targetTotalForRange(range)
			{
				return range && range.valid ? range.total * targetMultiplier() : 0;
			}

		function calculateRange()
			{
				const mode = rangeModeInput.value;

				if (mode === "cidr")
					{
						const match = String(cidrInput.value || "").trim().match(/^([0-9.]+)\/([0-9]{1,2})$/);
						if (!match)
							{
								return {valid: false, message: "Informe um CIDR válido."};
							}

						const base = ipToLong(match[1]);
						const prefix = Number(match[2]);
						if (base === null || !Number.isInteger(prefix) || prefix < 0 || prefix > 32)
							{
								return {valid: false, message: "Informe um CIDR válido."};
							}

						const mask = prefix === 0 ? 0 : (0xFFFFFFFF << (32 - prefix)) >>> 0;
						const network = (base & mask) >>> 0;
						const broadcast = (network | (~mask >>> 0)) >>> 0;
						const startLong = prefix <= 30 ? network + 1 : network;
						const endLong = prefix <= 30 ? broadcast - 1 : broadcast;
						const total = endLong - startLong + 1;

						if (total <= 0 || total > 1000)
							{
								return {valid: false, message: "A faixa excede 1.000 IPs úteis."};
							}

						return {valid: true, startIp: longToIp(startLong), endIp: longToIp(endLong), total: total};
					}

				const startLong = ipToLong(startIpInput.value);
				if (startLong === null)
					{
						return {valid: false, message: "Informe um IP inicial válido."};
					}

				if (mode === "manual")
					{
						const endLong = ipToLong(endIpInput.value);
						if (endLong === null || endLong < startLong)
							{
								return {valid: false, message: "Informe um IP final válido."};
							}

						const total = endLong - startLong + 1;
						if (total > 1000)
							{
								return {valid: false, message: "A faixa excede 1.000 IPs."};
							}

						return {valid: true, startIp: longToIp(startLong), endIp: longToIp(endLong), total: total};
					}

				const quantity = Math.max(1, Math.min(1000, Number(quantityInput.value || 1)));
				const endLong = startLong + quantity - 1;
				if (endLong > 4294967295)
					{
						return {valid: false, message: "A quantidade ultrapassa o limite IPv4."};
					}

				return {valid: true, startIp: longToIp(startLong), endIp: longToIp(endLong), total: quantity};
			}

		function updateRangeMode()
			{
				const mode = rangeModeInput.value;
				document.querySelectorAll(".ip-crawler-range-mode").forEach(function (element)
					{
						const modes = String(element.dataset.rangeMode || "").split(/\s+/);
						element.classList.toggle("d-none", !modes.includes(mode));
					});

				endIpInput.readOnly = mode === "amount";
				if (mode !== "manual")
					{
						endIpInput.classList.add("bg-body-tertiary");
					}
				else
					{
						endIpInput.classList.remove("bg-body-tertiary");
					}
			}

		function renderRangePreview(range)
			{
				const targetTotal = targetTotalForRange(range);

				if (!range.valid)
					{
						rangePreview.className = "badge text-bg-warning ip-web-crawler-range-preview";
						rangePreview.textContent = range.message || "Faixa inválida";
						currentRange = null;
						return;
					}

				if (rangeModeInput.value === "amount")
					{
						endIpInput.value = range.endIp;
					}

				rangePreview.className = "badge " + (targetTotal > maxTargetsPerRun ? "text-bg-warning" : "text-bg-primary") + " ip-web-crawler-range-preview";
				rangePreview.textContent = range.startIp + " até " + range.endIp + " · " + formatInteger(range.total) + " IPs · " + formatInteger(targetTotal) + " alvos";
				currentRange = range;
			}

		function generateRange()
			{
				renderRangePreview(calculateRange());
			}

		function setCountryLoading(isLoading)
			{
				loadCountryButton.disabled = isLoading;
				refreshCountryButton.disabled = isLoading;
				loadCountryButton.innerHTML = isLoading
					? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Carregando'
					: '<i class="bi bi-cloud-arrow-down me-1"></i>Carregar';
			}

		function renderCountryRanges(payload)
			{
				const ranges = Array.isArray(payload.ranges) ? payload.ranges : [];
				countryRangeSelect.innerHTML = "";
				countryRangeSelect.classList.toggle("d-none", ranges.length === 0);
				refreshCountryButton.classList.toggle("d-none", ranges.length === 0);

				if (!ranges.length)
					{
						countrySummary.textContent = payload.message || "Nenhuma faixa disponível.";
						return;
					}

				ranges.forEach(function (range, index)
					{
						const option = document.createElement("option");
						option.value = String(index);
						option.dataset.startIp = range.start_ip || "";
						option.dataset.endIp = range.end_ip || "";
						option.dataset.addressCount = String(range.address_count || 0);
						option.textContent = (range.start_ip || "-") + " até " + (range.end_ip || "-") + " · " + formatInteger(range.address_count || 0) + " IPs · " + (range.source_registry || "");
						countryRangeSelect.appendChild(option);
					});

				countrySummary.textContent = (payload.country_name || payload.country_code || "País") + " · " + formatInteger(payload.returned_ranges || ranges.length) + " faixas carregadas";
				applySelectedCountryRange();
			}

		function selectedCountryCode()
			{
				const customCode = String(customCountryCodeInput.value || "").trim().toUpperCase();
				return customCode || String(countryCodeInput.value || "").trim().toUpperCase();
			}

		function loadCountryRanges(refresh)
			{
				const countryCode = selectedCountryCode();
				if (!countryCode)
					{
						countrySummary.textContent = "Selecione um país.";
						return;
					}

				const params = new URLSearchParams({
					country_code: countryCode,
					limit: "250",
					refresh: refresh ? "1" : "0",
					max_ips_per_range: String(maxIpsPerCountryRange())
				});

				setCountryLoading(true);
				countrySummary.textContent = "Carregando faixas com até " + formatInteger(maxIpsPerCountryRange()) + " IPs por execução...";

				fetch(countryRangesUrl + "?" + params.toString(), {
					headers: {
						"Accept": "application/json",
						"X-Requested-With": "XMLHttpRequest"
					}
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
							if (!response.ok || !response.payload.success)
								{
									countryRangeSelect.classList.add("d-none");
									refreshCountryButton.classList.add("d-none");
									countrySummary.textContent = response.payload.message || "Não foi possível carregar as faixas.";
									return;
								}

							renderCountryRanges(response.payload);
						})
					.catch(function ()
						{
							countryRangeSelect.classList.add("d-none");
							refreshCountryButton.classList.add("d-none");
							countrySummary.textContent = "Não foi possível carregar as faixas agora.";
						})
					.finally(function ()
						{
							setCountryLoading(false);
						});
			}

		function applySelectedCountryRange()
			{
				const selectedOption = countryRangeSelect.options[countryRangeSelect.selectedIndex];
				if (!selectedOption)
					{
						return;
					}

				rangeModeInput.value = "manual";
				updateRangeMode();
				startIpInput.value = selectedOption.dataset.startIp || "";
				endIpInput.value = selectedOption.dataset.endIp || "";
				generateRange();
			}

		function updateUserAgentPreset()
			{
				const value = userAgentPresetInput.value;
				if (value === "custom")
					{
						userAgentInput.readOnly = false;
						userAgentInput.classList.remove("bg-body-tertiary");
						userAgentInput.focus();
						return;
					}

				userAgentInput.value = value;
				userAgentInput.readOnly = true;
				userAgentInput.classList.add("bg-body-tertiary");
			}

		function buildStreamParams()
			{
				const params = new URLSearchParams();
				const data = new FormData(form);

				data.forEach(function (value, key)
					{
						if (key !== "protocols[]")
							{
								params.append(key, value);
							}
					});

				selectedProtocols().forEach(function (protocol)
					{
						params.append("protocols[]", protocol);
					});

				params.set("skip_cached_failures", document.getElementById("ipCrawlerSkipCachedFailures").checked ? "1" : "0");
				return params;
			}

		function resetStats()
			{
				processedCount.textContent = "0";
				foundCount.textContent = "0";
				skippedCount.textContent = "0";
				failedCount.textContent = "0";
				statusText.textContent = "Aguardando";
				updateProgress(0, 0);
			}

		function updateProgress(processed, total)
			{
				const safeProcessed = Math.max(0, Number(processed || 0));
				const safeTotal = Math.max(0, Number(total || 0));
				const percent = safeTotal > 0 ? Math.min(100, Math.round((safeProcessed / safeTotal) * 100)) : 0;

				progressBar.style.width = percent + "%";
				progressBar.textContent = percent >= 8 ? percent + "%" : "";
				progressText.textContent = percent + "%";
				progress.setAttribute("aria-valuenow", String(percent));
			}

		function updateStats(stats)
			{
				if (!stats)
					{
						return;
					}

				processedCount.textContent = formatInteger(stats.processed_targets || 0) + "/" + formatInteger(stats.target_total || 0);
				foundCount.textContent = formatInteger(stats.found_targets || 0);
				skippedCount.textContent = formatInteger(stats.skipped_targets || 0);
				failedCount.textContent = formatInteger(stats.failed_targets || 0);
				updateProgress(stats.processed_targets || 0, stats.target_total || 0);
			}

		function clearResults()
			{
				resultsBody.innerHTML = '<div class="list-group-item text-secondary ip-web-crawler-results-empty"><div class="d-flex align-items-center gap-2"><i class="bi bi-hourglass-split"></i><span>Aguardando início.</span></div></div>';
				shouldFollowResults = true;
				scrollResultsToBottom();
				updateResultsFollowState();
			}

		function isResultsAtBottom()
			{
				return (resultsBody.scrollHeight - resultsBody.scrollTop - resultsBody.clientHeight) <= resultsBottomThreshold;
			}

		function updateResultsFollowState()
			{
				resultsBody.classList.toggle("is-scroll-paused", !shouldFollowResults);
				resultsBottomButton.classList.toggle("is-attention", !shouldFollowResults);
			}

		function scrollResultsToBottom()
			{
				resultsBody.scrollTop = resultsBody.scrollHeight;
			}

		function appendResult(className, html)
			{
				const followResults = shouldFollowResults || isResultsAtBottom();
				const empty = resultsBody.querySelector(".ip-web-crawler-results-empty");
				if (empty)
					{
						empty.remove();
					}

				const item = document.createElement("div");
				item.className = "list-group-item " + className;
				item.innerHTML = html;
				resultsBody.appendChild(item);

				if (followResults)
					{
						shouldFollowResults = true;
						scrollResultsToBottom();
					}

				updateResultsFollowState();
			}

		function renderTargetLine(payload, badge, label)
			{
				appendResult("", '<div class="d-flex flex-wrap gap-2 align-items-center"><span class="badge ' + badge + '">' + escapeHtml(label) + '</span><strong>' + escapeHtml(payload.base_url || "") + '</strong><small class="text-secondary ms-auto">' + escapeHtml(payload.message || "") + '</small></div>');
			}

		function renderPage(payload)
			{
				const badge = payload.matched ? "text-bg-success" : (payload.status === "failed" ? "text-bg-danger" : "text-bg-secondary");
				const label = payload.matched ? "match" : (payload.http_status || payload.status || "ok");
				let matchesHtml = "";

				if (Array.isArray(payload.matches) && payload.matches.length)
					{
						matchesHtml = '<div class="ip-web-crawler-match-list mt-2">' + payload.matches.map(function (match)
							{
								return '<div class="small"><span class="badge text-bg-light border text-body me-1">' + escapeHtml(match.term) + '</span>' + escapeHtml(match.snippet) + '</div>';
							}).join("") + "</div>";
					}

				appendResult("", '<div class="d-flex flex-wrap gap-2 align-items-center"><span class="badge ' + badge + '">' + escapeHtml(label) + '</span><strong class="ip-web-crawler-url">' + escapeHtml(payload.url || "") + '</strong><small class="text-secondary ms-auto">' + escapeHtml(payload.title || payload.message || "") + '</small></div>' + matchesHtml);
			}

		function closeStream(status)
			{
				if (eventSource)
					{
						eventSource.close();
						eventSource = null;
					}

				setLoading(false);
				statusText.textContent = status || "Finalizado";
			}

		function startStream()
			{
				if (eventSource)
					{
						eventSource.close();
					}

				eventSource = new EventSource(streamUrl + "?" + buildStreamParams().toString());
				eventSource.addEventListener("validation_error", function (event)
					{
						const payload = JSON.parse(event.data || "{}");
						setMessage("warning", payload.message || "Não foi possível iniciar a pesquisa.");
						closeStream("Validação");
					});
				eventSource.addEventListener("started", function (event)
					{
						const payload = JSON.parse(event.data || "{}");
						statusText.textContent = "Rodando";
						updateProgress(0, payload.total_targets || 0);
						setMessage("info", "Pesquisa iniciada com " + formatInteger(payload.total_targets || 0) + " alvos.");
						renderTargetLine(payload, "text-bg-primary", "scan #" + (payload.scan_id || ""));
					});
				eventSource.addEventListener("target_skipped", function (event)
					{
						const payload = JSON.parse(event.data || "{}");
						updateStats(payload.stats);
						renderTargetLine(payload, "text-bg-warning", "ignorado");
					});
				eventSource.addEventListener("target_started", function (event)
					{
						const payload = JSON.parse(event.data || "{}");
						renderTargetLine(payload, "text-bg-info", "alvo");
					});
				eventSource.addEventListener("target", function (event)
					{
						const payload = JSON.parse(event.data || "{}");
						updateStats(payload.stats);
						renderTargetLine(payload, "text-bg-danger", payload.status || "falha");
					});
				eventSource.addEventListener("page", function (event)
					{
						const payload = JSON.parse(event.data || "{}");
						updateStats(payload.stats);
						renderPage(payload);
					});
				eventSource.addEventListener("target_finished", function (event)
					{
						const payload = JSON.parse(event.data || "{}");
						updateStats(payload.stats);
					});
				eventSource.addEventListener("summary", function (event)
					{
						const payload = JSON.parse(event.data || "{}");
						updateStats(payload.stats);
						setMessage(payload.status === "completed" ? "success" : "danger", payload.message || "Pesquisa finalizada.");
						closeStream(payload.status === "completed" ? "Concluído" : "Falha");
					});
				eventSource.onerror = function ()
					{
						setMessage("danger", "A conexão de progresso foi encerrada.");
						closeStream("Encerrado");
					};
			}

		rangeModeInput.addEventListener("change", function ()
			{
				updateRangeMode();
				generateRange();
			});

		[startIpInput, quantityInput, endIpInput, cidrInput, portsInput].forEach(function (input)
			{
				input.addEventListener("input", generateRange);
			});

		form.querySelectorAll("input[name='protocols[]']").forEach(function (input)
			{
				input.addEventListener("change", generateRange);
			});

		generateRangeButton.addEventListener("click", generateRange);

		resultsBottomButton.addEventListener("click", function ()
			{
				shouldFollowResults = true;
				scrollResultsToBottom();
				updateResultsFollowState();
			});

		resultsBody.addEventListener("scroll", function ()
			{
				shouldFollowResults = isResultsAtBottom();
				updateResultsFollowState();
			});

		stopButton.addEventListener("click", function ()
			{
				setMessage("warning", "Pesquisa interrompida nesta tela.");
				closeStream("Parado");
			});

		clearButton.addEventListener("click", function ()
			{
				form.reset();
				updateRangeMode();
				updateUserAgentPreset();
				currentRange = null;
				rangePreview.className = "badge text-bg-secondary ip-web-crawler-range-preview";
				rangePreview.textContent = "Nenhuma faixa gerada";
				countryRangeSelect.classList.add("d-none");
				refreshCountryButton.classList.add("d-none");
				customCountryCodeInput.value = "";
				countrySummary.textContent = "Selecione um país para carregar faixas IPv4.";
				resetStats();
				clearResults();
				setMessage("secondary", "Configure a pesquisa e gere a faixa de IPs.");
				startIpInput.focus();
			});

		form.addEventListener("submit", function (event)
			{
				event.preventDefault();
				const range = calculateRange();
				renderRangePreview(range);

				if (!range.valid)
					{
						setMessage("warning", range.message || "Revise a faixa de IPs.");
						return;
					}

				if (!selectedProtocols().length)
					{
						setMessage("warning", "Selecione ao menos um protocolo.");
						return;
					}

				if (!parsePorts().length)
					{
						setMessage("warning", "Informe ao menos uma porta válida.");
						return;
					}

				if (targetTotalForRange(range) > maxTargetsPerRun)
					{
						setMessage("warning", "Esta execução teria " + formatInteger(targetTotalForRange(range)) + " alvos. Reduza a faixa, as portas ou os protocolos para no máximo " + formatInteger(maxTargetsPerRun) + " alvos.");
						return;
					}

				currentRange = range;
				resetStats();
				clearResults();
				setLoading(true);
				setMessage("info", "Abrindo stream de pesquisa...");
				startStream();
			});

		updateRangeMode();
		updateUserAgentPreset();
		generateRange();

		loadCountryButton.addEventListener("click", function ()
			{
				loadCountryRanges(false);
			});

		refreshCountryButton.addEventListener("click", function ()
			{
				loadCountryRanges(true);
			});

		countryRangeSelect.addEventListener("change", applySelectedCountryRange);
		userAgentPresetInput.addEventListener("change", updateUserAgentPreset);
		customCountryCodeInput.addEventListener("input", function ()
			{
				customCountryCodeInput.value = customCountryCodeInput.value.toUpperCase().replace(/[^A-Z]/g, "").slice(0, 2);
			});
	})();

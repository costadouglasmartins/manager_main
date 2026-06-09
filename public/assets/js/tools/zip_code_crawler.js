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
		const maxResultsInput = document.getElementById("crawlerMaxResults");
		const saveCityInput = document.getElementById("saveCitySwitch");
		const saveNeighborhoodInput = document.getElementById("saveNeighborhoodSwitch");
		const createCityInput = document.getElementById("createCitySwitch");
		const clearButton = document.getElementById("crawlerClearButton");
		const stopButton = document.getElementById("crawlerStopButton");
		const startButton = document.getElementById("crawlerStartButton");
		const message = document.getElementById("crawlerMessage");
		const progressBar = document.getElementById("crawlerProgressBar");
		const resultsBody = document.getElementById("crawlerResultsBody");
		const selectAllResultsInput = document.getElementById("crawlerSelectAllResults");
		const batchSaveButton = document.getElementById("crawlerBatchSaveButton");
		const processedCount = document.getElementById("crawlerProcessedCount");
		const foundCount = document.getElementById("crawlerFoundCount");
		const failedCount = document.getElementById("crawlerFailedCount");
		const statusText = document.getElementById("crawlerStatusText");
		const streamUrl = app.dataset.streamUrl || "";
		const saveUrl = app.dataset.saveUrl || "";
		const batchSaveUrl = app.dataset.batchSaveUrl || "";
		const stateOptions = parseStateOptions();
		const stateOptionRegistry = buildStateOptionRegistry(stateOptions);

		let eventSource = null;
		let resultRegistry = new Map();
		let selectedZipCodes = new Set();

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

		function updateStats(payload)
			{
				processedCount.textContent = String(payload.processed || 0);
				foundCount.textContent = String(payload.found || 0);
				failedCount.textContent = String(payload.failed || 0);
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
				resultRegistry = new Map();
				selectedZipCodes = new Set();
				if (selectAllResultsInput)
					{
						selectAllResultsInput.checked = false;
						selectAllResultsInput.indeterminate = false;
					}
				if (batchSaveButton)
					{
						batchSaveButton.disabled = true;
					}
			}

		function scrollResultsToBottom()
			{
				resultsBody.scrollTop = resultsBody.scrollHeight;
			}

		function updateBatchControls()
			{
				const selectedCount = getCheckedResultCheckboxes().length;
				if (batchSaveButton)
					{
						batchSaveButton.disabled = selectedCount === 0;
						batchSaveButton.textContent = selectedCount > 0
							? "Salvar selecionados (" + selectedCount + ")"
							: "Salvar selecionados";
					}

				if (selectAllResultsInput)
					{
						const selectableCount = Array.from(resultRegistry.values()).filter(function (item)
							{
								return item.canSave;
							}).length;
						selectAllResultsInput.checked = selectableCount > 0 && selectedCount === selectableCount;
						selectAllResultsInput.indeterminate = selectedCount > 0 && selectedCount < selectableCount;
					}
			}

		function getCheckedResultCheckboxes()
			{
				return Array.from(resultsBody.querySelectorAll('input[type="checkbox"][data-zip-code]:checked'));
			}

		function setSelectedZipCode(zipCode, isSelected)
			{
				if (isSelected)
					{
						selectedZipCodes.add(zipCode);
					}
				else
					{
						selectedZipCodes.delete(zipCode);
					}

				updateBatchControls();
			}

		function buildBatchItems()
			{
				return getCheckedResultCheckboxes().map(function (checkbox)
					{
						const zipCode = checkbox.dataset.zipCode || "";
						const entry = resultRegistry.get(zipCode);
						return entry ? {
							lookup_data: entry.lookupData,
							zip_code: zipCode
						} : null;
					}).filter(function (item)
					{
						return item !== null;
					});
			}

		function getSaveOptions()
			{
				return {
					save_city: saveCityInput.checked,
					save_neighborhood: saveNeighborhoodInput.checked,
					create_city: createCityInput.checked
				};
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

		function saveCrawlerResult(payload, button, statusNode, onSaved)
			{
				const lookupData = (payload.result && payload.result.data) ? payload.result.data : {};
				const options = getSaveOptions();

				if (!options.save_city && !options.save_neighborhood)
					{
						setMessage("warning", "Marque pelo menos um campo para salvar antes de gravar um resultado.");
						return;
					}

				button.disabled = true;
				statusNode.textContent = "salvando...";

				fetch(saveUrl, {
					method: "POST",
					headers: {
						"Content-Type": "application/json",
						"Accept": "application/json"
					},
					body: JSON.stringify({
						lookup_data: lookupData,
						save_city: options.save_city,
						save_neighborhood: options.save_neighborhood,
						create_city: options.create_city
					})
				})
					.then(function (response)
						{
							return response.json().then(function (data)
								{
									return {
										ok: response.ok,
										status: response.status,
										data: data
									};
								});
						})
					.then(function (payloadResponse)
						{
							const responseData = payloadResponse.data || {};
							if (!payloadResponse.ok || !responseData.success)
								{
									throw new Error(responseData.message || "Falha ao salvar o resultado.");
								}

							statusNode.textContent = "✓ SALVO";
							button.disabled = true;
							button.textContent = "[SALVO]";
							if (typeof onSaved === "function")
								{
									onSaved(payload, responseData);
								}
							setMessage("success", responseData.message || "Resultado salvo com sucesso.");
						})
					.catch(function (error)
						{
							button.disabled = false;
							statusNode.textContent = "ERRO";
							setMessage("danger", error.message || "Não foi possível salvar o resultado.");
						});
			}

		function saveSelectedResults()
			{
				const items = buildBatchItems();
				const options = getSaveOptions();

				if (!items.length)
					{
						setMessage("warning", "Selecione ao menos um resultado para salvar.");
						return;
					}

				if (!options.save_city && !options.save_neighborhood)
					{
						setMessage("warning", "Marque pelo menos um campo para salvar antes de gravar os resultados selecionados.");
						return;
					}

				batchSaveButton.disabled = true;
				batchSaveButton.textContent = "Salvando...";
				setMessage("info", "Salvando " + items.length + " resultado(s) selecionado(s)...");

				fetch(batchSaveUrl, {
					method: "POST",
					headers: {
						"Content-Type": "application/json",
						"Accept": "application/json"
					},
					body: JSON.stringify({
						items: items,
						save_city: options.save_city,
						save_neighborhood: options.save_neighborhood,
						create_city: options.create_city
					})
				})
					.then(function (response)
						{
							return response.json().then(function (data)
								{
									return {
										ok: response.ok,
										status: response.status,
										data: data
									};
								});
						})
					.then(function (payloadResponse)
						{
							const responseData = payloadResponse.data || {};
							if (!payloadResponse.ok || !responseData.success)
								{
									throw new Error(responseData.message || "Falha ao salvar os resultados selecionados.");
								}

							const summary = responseData.data || {};
							const savedCount = summary.saved_count || 0;
							const failedCount = summary.failed_count || 0;
							const resultList = summary.results || [];

							resultList.forEach(function (result, index)
								{
									const item = items[index];
									if (!item)
										{
											return;
										}

									const registryItem = resultRegistry.get(item.zip_code);
									if (registryItem && result && result.success)
										{
											registryItem.saved = true;
											registryItem.saveStatusNode.textContent = "✓ SALVO";
											registryItem.saveButton.disabled = true;
											registryItem.saveButton.textContent = "[SALVO]";
										}
								});

							selectedZipCodes.clear();
							getCheckedResultCheckboxes().forEach(function (input)
								{
									input.checked = false;
								});
							if (selectAllResultsInput)
								{
									selectAllResultsInput.checked = false;
									selectAllResultsInput.indeterminate = false;
								}

							updateBatchControls();
							setMessage(failedCount === 0
								? "success"
								: "warning",
								failedCount === 0
									? "Salvamento concluído com " + savedCount + " resultado(s)."
									: "Salvamento concluído com " + savedCount + " sucesso(s) e " + failedCount + " falha(s).");
						})
					.catch(function (error)
						{
							setMessage("danger", error.message || "Não foi possível salvar os resultados selecionados.");
						})
					.finally(function ()
						{
							updateBatchControls();
						});
			}

		function appendResultRow(payload)
			{
				const result = payload.result || {};
				const data = result.data || {};
				const zipCode = payload.zip_code || "";
				const canSave = !!result.success && !!zipCode;
				const isFound = !!result.success;
				const isSkipped = !isFound && (!!data.skipped || result.source === "cache" || result.error === "zip_code_already_not_found");

				const line = document.createElement("div");
				line.className = "crawler-console-line " + (isFound ? "crawler-console-line--found" : "crawler-console-line--notfound");

				const selectCheckbox = document.createElement("input");
				selectCheckbox.className = "form-check-input crawler-console-checkbox";
				selectCheckbox.type = "checkbox";
				selectCheckbox.dataset.zipCode = zipCode;
				selectCheckbox.disabled = !canSave;

				const tsSpan = document.createElement("span");
				tsSpan.className = "ccl-ts";
				tsSpan.textContent = "[" + nowTimestamp() + "]";

				const statusSpan = document.createElement("span");
				statusSpan.className = "ccl-status " + (isFound ? "ccl-status--found" : "ccl-status--notfound");
				statusSpan.textContent = isFound ? "FOUND" : (isSkipped ? "CACHE" : "MISS ");

				const zipSpan = document.createElement("span");
				zipSpan.className = "ccl-zip";
				zipSpan.textContent = zipCode || "-";

				const arrowSpan = document.createElement("span");
				arrowSpan.className = "ccl-arrow";
				arrowSpan.textContent = "›";

				const sourceSpan = document.createElement("span");
				sourceSpan.className = "ccl-source";
				sourceSpan.textContent = result.source === "cache" ? "[TXT]" : (result.source ? "[" + String(result.source).toUpperCase() + "]" : "[-]");

				const locationSpan = document.createElement("span");
				locationSpan.className = "ccl-location";
				if (isFound)
					{
						const parts = [data.neighborhood, data.city, data.state].filter(Boolean);
						locationSpan.textContent = parts.length ? parts.join(" · ") : "-";
					}
				else
					{
						locationSpan.textContent = isSkipped
							? "Ignorado pelo arquivo de CEPs não encontrados."
							: (result.message || "CEP não encontrado");
					}

				const saveButton = document.createElement("button");
				saveButton.className = "btn-console-save";
				saveButton.type = "button";
				saveButton.textContent = "[SAVE]";

				if (!isFound)
					{
						saveButton.disabled = true;
					}

				const saveStatus = document.createElement("span");
				saveStatus.className = "ccl-save-status";

				const actionsSpan = document.createElement("span");
				actionsSpan.className = "ccl-actions";
				actionsSpan.appendChild(saveButton);
				actionsSpan.appendChild(saveStatus);

				if (isFound)
					{
						saveButton.addEventListener("click", function ()
							{
								saveCrawlerResult(payload, saveButton, saveStatus, function ()
									{
										const registryItem = resultRegistry.get(zipCode);
										if (registryItem)
											{
												registryItem.saved = true;
											}
									});
							});
					}

				line.appendChild(selectCheckbox);
				line.appendChild(tsSpan);
				line.appendChild(statusSpan);
				line.appendChild(zipSpan);
				line.appendChild(arrowSpan);
				line.appendChild(sourceSpan);
				line.appendChild(locationSpan);
				line.appendChild(actionsSpan);

				resultRegistry.set(zipCode, {
					payload: payload,
					lookupData: data,
					canSave: canSave,
					selectedCheckbox: selectCheckbox,
					saveButton: saveButton,
					saveStatusNode: saveStatus,
					saved: false
				});

				if (canSave)
					{
						selectCheckbox.addEventListener("change", function ()
							{
								setSelectedZipCode(zipCode, selectCheckbox.checked);
							});
					}

				clearIdleLine();
				resultsBody.appendChild(line);
				scrollResultsToBottom();
			}

		function openStream()
			{
				const selectedState = getSelectedStateOption();
				const stateAcronym = selectedState ? String(selectedState.acronym || "").toUpperCase() : "";
				const delayMs = String(delayInput.value || "500");
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
				updateStats({processed: 0, found: 0, failed: 0});
				updateProgress(0, 1);
				setLoading(true);
				setMessage("info", "Iniciando webcrawler com atraso controlado entre consultas...");
				statusText.textContent = "Conectando...";

				const url = new URL(streamUrl, window.location.origin);
				url.searchParams.set("state_acronym", stateAcronym);
				url.searchParams.set("delay_ms", delayMs);
				url.searchParams.set("max_results", maxResults);
				url.searchParams.set("provider_mode", providerMode);

				eventSource = new EventSource(url.toString());

				eventSource.addEventListener("start", function (event)
					{
						const payload = JSON.parse(event.data || "{}");
						statusText.textContent = "Em execução";
						const ranges = payload.ranges || selectedState.ranges || [];
						const rangeText = formatRangeList(ranges);
						const cacheCount = Number(payload.not_found_cache_count || 0);
						setMessage("info", "Pesquisa iniciada para " + (payload.total || 0) + " CEPs em " + stateAcronym + " usando " + (payload.provider_mode || providerMode).toUpperCase() + ". Cache TXT: " + cacheCount + " CEP(s).");
						updateProgress(0, payload.total || 1);
						appendSystemLine("INIT", "scanner ativo | UF: " + stateAcronym + " | " + (payload.total || 0) + " CEPs | faixas: " + rangeText + " | cache TXT: " + cacheCount + " CEPs | provider: " + (payload.provider_mode || providerMode).toUpperCase() + " | delay: " + delayMs + "ms");
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
						updateProgress(payload.total || 1, payload.total || 1);
						statusText.textContent = "Concluído";
						setMessage("success", "Pesquisa concluída com " + (payload.found || 0) + " CEP(s) encontrados.");
						appendSystemLine("DONE", "scan completo | " + (payload.found || 0) + " encontrado(s) | " + (payload.failed || 0) + " não encontrado(s)");
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

		if (selectAllResultsInput)
			{
				selectAllResultsInput.addEventListener("change", function ()
					{
						Array.from(resultRegistry.entries()).forEach(function (entry)
							{
								const zipCode = entry[0];
								const resultEntry = entry[1];
								if (!resultEntry.canSave)
									{
										return;
									}

								resultEntry.selectedCheckbox.checked = selectAllResultsInput.checked;
								setSelectedZipCode(zipCode, selectAllResultsInput.checked);
							});
					});
			}

		if (batchSaveButton)
			{
				batchSaveButton.addEventListener("click", saveSelectedResults);
				batchSaveButton.disabled = true;
			}

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
				setLoading(false);
			});

		clearButton.addEventListener("click", function ()
			{
				if (eventSource)
					{
						eventSource.close();
						eventSource = null;
					}

				if (stateInput)
					{
						stateInput.value = "";
					}
				startInput.value = "";
				endInput.value = "";
				delayInput.value = "500";
				maxResultsInput.value = "100";
				providerModeInput.value = "fallback";
				saveCityInput.checked = true;
				saveNeighborhoodInput.checked = true;
				createCityInput.checked = false;
				maxResultsInput.value = "100";
				updateDelayLabel();
				updateStateRangeSummary();
				clearResults();
				updateStats({processed: 0, found: 0, failed: 0});
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
	})();

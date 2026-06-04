(function ()
	{
		const app = document.getElementById("zipCodeCrawlerApp");
		if (!app)
			{
				return;
			}

		const form = document.getElementById("zipCodeCrawlerForm");
		const providerModeInput = document.getElementById("crawlerProviderMode");
		const startInput = document.getElementById("crawlerStartZipCode");
		const endInput = document.getElementById("crawlerEndZipCode");
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

		function calculateZipCodeRangeCount()
			{
				const startDigits = onlyDigits(startInput.value);
				const endDigits = onlyDigits(endInput.value);
				if (startDigits.length !== 8 || endDigits.length !== 8)
					{
						return 0;
					}

				const startNumber = parseInt(startDigits, 10);
				const endNumber = parseInt(endDigits, 10);
				if (Number.isNaN(startNumber) || Number.isNaN(endNumber))
					{
						return 0;
					}

				return Math.abs(endNumber - startNumber) + 1;
			}

		function updateMaxResultsFromRange()
			{
				const total = calculateZipCodeRangeCount();
				if (total > 0)
					{
						maxResultsInput.value = String(total);
					}
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
				resultsBody.innerHTML = '<tr><td colspan="7" class="text-center text-secondary">Nenhuma pesquisa em andamento.</td></tr>';
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
				const tableWrap = resultsBody.closest(".zip-code-crawler-table-wrap");
				if (!tableWrap)
					{
						return;
					}

				tableWrap.scrollTop = tableWrap.scrollHeight;
			}

		function updateBatchControls()
			{
				const selectedCount = getCheckedResultCheckboxes().length;
				if (batchSaveButton)
					{
						batchSaveButton.disabled = selectedCount === 0;
						batchSaveButton.innerHTML = selectedCount > 0
							? '<i class="bi bi-save2 me-1"></i>Salvar selecionados (' + selectedCount + ')'
							: '<i class="bi bi-save2 me-1"></i>Salvar selecionados';
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

		function getSelectableResultEntries()
			{
				return Array.from(resultRegistry.entries()).filter(function (entry)
					{
						return entry[1].canSave;
					});
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

		function createSaveStatusBadge(type, text)
			{
				const badge = document.createElement("span");
				badge.className = "badge text-bg-" + type;
				badge.textContent = text;
				return badge;
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
				statusNode.textContent = "Salvando...";

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

							statusNode.innerHTML = "";
							statusNode.appendChild(createSaveStatusBadge("success", "Salvo"));
							button.disabled = true;
							button.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Salvo';
							if (typeof onSaved === "function")
								{
									onSaved(payload, responseData);
								}
							setMessage("success", responseData.message || "Resultado salvo com sucesso.");
						})
					.catch(function (error)
						{
							button.disabled = false;
							statusNode.textContent = "Erro";
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
				batchSaveButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Salvando';
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
											registryItem.saveStatusNode.innerHTML = "";
											registryItem.saveStatusNode.appendChild(createSaveStatusBadge("success", "Salvo"));
											registryItem.saveButton.disabled = true;
											registryItem.saveButton.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Salvo';
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
							batchSaveButton.innerHTML = '<i class="bi bi-save2 me-1"></i>Salvar selecionados';
						});
			}

		function appendResultRow(payload)
			{
				const result = payload.result || {};
				const data = result.data || {};
				const zipCode = payload.zip_code || "";
				const row = document.createElement("tr");
				const canSave = !!result.success && !!zipCode;

				const selectCell = document.createElement("td");
				const selectCheckbox = document.createElement("input");
				selectCheckbox.className = "form-check-input";
				selectCheckbox.type = "checkbox";
				selectCheckbox.dataset.zipCode = zipCode;
				selectCheckbox.disabled = !canSave;
				selectCheckbox.checked = false;
				selectCell.appendChild(selectCheckbox);

				const zipCell = document.createElement("td");
				zipCell.textContent = zipCode || "-";

				const resultCell = document.createElement("td");
				resultCell.innerHTML = result.success
					? '<span class="zip-code-crawler-result-success">Encontrado</span>'
					: '<span class="zip-code-crawler-result-failure">Não encontrado</span>';

				const sourceCell = document.createElement("td");
				sourceCell.textContent = result.source ? String(result.source).toUpperCase() : "-";

				const locationCell = document.createElement("td");
				locationCell.textContent = [
					data.neighborhood || "-",
					data.city || "-",
					data.state || "-"
				].join(" - ");

				const detailsCell = document.createElement("td");
				detailsCell.className = "zip-code-crawler-row-muted";
				const details = [];
				if (result.message)
					{
						details.push(result.message);
					}
				detailsCell.textContent = details.length ? details.join(" | ") : "-";

				const saveCell = document.createElement("td");
				saveCell.className = "zip-code-crawler-row-actions";

				const saveStatus = document.createElement("div");
				saveStatus.className = "zip-code-crawler-row-save-status";
				saveStatus.textContent = "Não salvo";

				const saveButton = document.createElement("button");
				saveButton.className = "btn btn-sm btn-outline-primary";
				saveButton.type = "button";
				saveButton.innerHTML = '<i class="bi bi-save2 me-1"></i>Salvar';

				if (!result.success)
					{
						saveButton.disabled = true;
						saveButton.title = "Somente resultados encontrados podem ser salvos.";
					}
				else
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

				saveCell.appendChild(saveButton);
				saveCell.appendChild(saveStatus);

				if (canSave)
					{
						selectCheckbox.addEventListener("change", function ()
							{
								if (selectCheckbox.checked)
									{
										selectedZipCodes.add(zipCode);
									}
								else
									{
										selectedZipCodes.delete(zipCode);
									}

								setSelectedZipCode(zipCode, selectCheckbox.checked);
							});
					}

				resultRegistry.set(zipCode, {
					payload: payload,
					lookupData: data,
					canSave: canSave,
					selectedCheckbox: selectCheckbox,
					saveButton: saveButton,
					saveStatusNode: saveStatus,
					saved: false
				});

				row.appendChild(zipCell);
				row.appendChild(selectCell);
				row.appendChild(resultCell);
				row.appendChild(sourceCell);
				row.appendChild(locationCell);
				row.appendChild(detailsCell);
				row.appendChild(saveCell);

				if (resultsBody.querySelector("td[colspan]"))
					{
						resultsBody.innerHTML = "";
					}

				resultsBody.appendChild(row);
				scrollResultsToBottom();
			}

		function openStream()
			{
				const startZipCode = onlyDigits(startInput.value);
				const endZipCode = onlyDigits(endInput.value);
				const delayMs = String(delayInput.value || "500");
				const maxResults = String(maxResultsInput.value || calculateZipCodeRangeCount() || "100");
				const providerMode = providerModeInput.value || "fallback";

				if (startZipCode.length !== 8 || endZipCode.length !== 8)
					{
						setMessage("warning", "Informe CEP inicial e final válidos com 8 dígitos.");
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
				url.searchParams.set("start_zip_code", startZipCode);
				url.searchParams.set("end_zip_code", endZipCode);
				url.searchParams.set("delay_ms", delayMs);
				url.searchParams.set("max_results", maxResults);
				url.searchParams.set("provider_mode", providerMode);

				eventSource = new EventSource(url.toString());

				eventSource.addEventListener("start", function (event)
					{
						const payload = JSON.parse(event.data || "{}");
						statusText.textContent = "Em execução";
						setMessage("info", "Pesquisa iniciada para " + (payload.total || 0) + " CEPs usando " + (payload.provider_mode || providerMode).toUpperCase() + ".");
						updateProgress(0, payload.total || 1);
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
				clearResults();
				updateStats({processed: 0, found: 0, failed: 0});
				updateProgress(0, 1);
				statusText.textContent = "Aguardando início";
				setMessage("secondary", "Defina um intervalo de CEP e inicie a pesquisa.");
				setLoading(false);
				startInput.focus();
			});

		updateDelayLabel();
	})();

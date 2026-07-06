(function ()
	{
		const form = document.getElementById("signupForm");

		if (!form)
			{
				return;
			}

		const TOTAL_STEPS = 6;
		const STORAGE_KEY = "peopleCreateFormStateV1";

		const steps = [
			{n: 1, title: "Etapa 1 de 6", subtitle: "Pessoa"},
			{n: 2, title: "Etapa 2 de 6", subtitle: "Usuário"},
			{n: 3, title: "Etapa 3 de 6", subtitle: "Tenant"},
			{n: 4, title: "Etapa 4 de 6", subtitle: "Endereços"},
			{n: 5, title: "Etapa 5 de 6", subtitle: "Contatos"},
			{n: 6, title: "Etapa 6 de 6", subtitle: "Revisão"}
		];

		let currentStep = 1;
		let isSubmittingAfterAsyncValidation = false;

		const stepTitle    = document.getElementById("stepTitle");
		const stepSubtitle = document.getElementById("stepSubtitle");
		const stepBadge    = document.getElementById("stepBadge");
		const progressBar  = document.getElementById("progressBar");

		const btnBack   = document.getElementById("btnBack");
		const btnNext   = document.getElementById("btnNext");
		const btnSubmit = document.getElementById("btnSubmit");

		const personName = document.getElementById("personName");
		const personFantasy = document.getElementById("personFantasy");
		const personType = document.getElementById("personType");
		const personDocument = document.getElementById("personDocument");
		const userUsername = document.getElementById("userUsername");
		const userType = document.getElementById("userType");
		const userPassword  = document.getElementById("userPassword");
		const userPassword2 = document.getElementById("userPassword2");
		const personDocumentFeedback = personDocument && personDocument.parentElement
			? personDocument.parentElement.querySelector(".invalid-feedback")
			: null;
		const personDocumentDefaultFeedback = personDocumentFeedback
			? personDocumentFeedback.textContent
			: "Informe o documento.";

		const addressesContainer = document.getElementById("addressesContainer");
		const phonesContainer    = document.getElementById("phonesContainer");
		const emailsContainer    = document.getElementById("emailsContainer");

		const addressTemplate = document.getElementById("addressTemplate");
		const phoneTemplate   = document.getElementById("phoneTemplate");
		const emailTemplate   = document.getElementById("emailTemplate");

		const btnAddAddress = document.getElementById("btnAddAddress");
		const btnAddPhone   = document.getElementById("btnAddPhone");
		const btnAddEmail   = document.getElementById("btnAddEmail");

		const reviewBody = document.getElementById("reviewBody");

		// Tenant elements
		const tenantMode            = document.getElementById("tenantMode");
		const tenantNewFields       = document.getElementById("tenantNewFields");
		const requiredIds = [
			"stepTitle", "stepSubtitle", "stepBadge", "progressBar",
			"btnBack", "btnNext", "btnSubmit",
			"personName", "personFantasy", "personType", "personDocument", "userUsername", "userType",
			"addressesContainer", "phonesContainer", "emailsContainer",
			"addressTemplate", "phoneTemplate", "emailTemplate",
			"btnAddAddress", "btnAddPhone", "btnAddEmail",
			"reviewBody", "userPassword", "userPassword2",
			"tenantMode", "tenantNewFields"
		];

		const missing = requiredIds.filter((id) => !document.getElementById(id));
		if (missing.length)
			{
				console.warn("create.js: elementos ausentes no DOM:", missing);
				return;
			}

		// -----------------------------------------------------------------------
		// Username suggestion
		// -----------------------------------------------------------------------
		let lastSuggestedUsername = "";
		let usernameEditedManually = false;
		let fantasyEditedManually = false;

		function buildUsernameSuggestion(fullName)
			{
				const parts = String(fullName || "")
					.normalize("NFD")
					.replace(/[\u0300-\u036f]/g, "")
					.toLowerCase()
					.replace(/[^a-z0-9\s]+/g, " ")
					.trim()
					.split(/\s+/)
					.filter(Boolean);

				if (parts.length === 0)
					{
						return "";
					}

				const firstName = parts[0];
				const lastName = parts.length > 1 ? parts[parts.length - 1] : "";

				return lastName && lastName !== firstName
					? firstName + "." + lastName
					: firstName;
			}

		function updateUsernameSuggestion()
			{
				if (usernameEditedManually)
					{
						return;
					}

				lastSuggestedUsername = buildUsernameSuggestion(personName.value);
				userUsername.value = lastSuggestedUsername;
			}

		function syncFantasyName()
			{
				if (fantasyEditedManually)
					{
						return;
					}

				personFantasy.value = personName.value;
			}

		userUsername.addEventListener("input", function ()
			{
				usernameEditedManually = userUsername.value.trim() !== "" && userUsername.value !== lastSuggestedUsername;
			});

		personFantasy.addEventListener("input", function ()
			{
				fantasyEditedManually = personFantasy.value.trim() !== "" && personFantasy.value !== personName.value;
			});

		personName.addEventListener("input", function ()
			{
				updateUsernameSuggestion();
				syncFantasyName();
			});
		personName.addEventListener("blur", updateUsernameSuggestion);

		// -----------------------------------------------------------------------
		// Person document validation
		// -----------------------------------------------------------------------
		let personDocumentCheckController = null;
		let personDocumentCheckTimer = null;
		let personDocumentCheckSequence = 0;
		let personDocumentCheckCache = {
			key: "",
			exists: false
		};

		function setPersonDocumentFeedback(message)
			{
				if (personDocumentFeedback)
					{
						personDocumentFeedback.textContent = message;
					}
			}

		function normalizePersonDocumentValue(value)
			{
				return String(value || "").toUpperCase().replace(/[^A-Z0-9]/g, "");
			}

		function getPersonDocumentCheckKey()
			{
				return personType.value + ":" + normalizePersonDocumentValue(personDocument.value);
			}

		function abortPersonDocumentCheck()
			{
				if (personDocumentCheckTimer)
					{
						clearTimeout(personDocumentCheckTimer);
						personDocumentCheckTimer = null;
					}

				if (personDocumentCheckController)
					{
						personDocumentCheckController.abort();
						personDocumentCheckController = null;
					}
			}

		function applyPersonDocumentAvailability(exists)
			{
				if (exists)
					{
						const message = "Este documento já está cadastrado.";
						personDocument.setCustomValidity(message);
						setPersonDocumentFeedback(message);
						return false;
					}

				personDocument.setCustomValidity("");
				setPersonDocumentFeedback(personDocumentDefaultFeedback);
				return true;
			}

		function validatePersonDocumentFormat()
			{
				const type = personType.value;
				const value = personDocument.value.trim();

				personDocument.setCustomValidity("");

				if (!value || !type)
					{
						setPersonDocumentFeedback(personDocumentDefaultFeedback);
						return true;
					}

				let isValid = true;
				let message = personDocumentDefaultFeedback;

				if (type === "individual")
					{
						isValid = typeof validateCpf === "function" && validateCpf(value);
						message = "Informe um CPF válido.";
					}
				else if (type === "company")
					{
						const isCurrentCnpjValid = typeof validateCnpj === "function" && validateCnpj(value);
						const isNewCnpjValid = typeof validateCnpjAlphanumeric === "function" && validateCnpjAlphanumeric(value);
						isValid = isCurrentCnpjValid || isNewCnpjValid;
						message = "Informe um CNPJ válido.";
					}

				if (!isValid)
					{
						personDocument.setCustomValidity(message);
						setPersonDocumentFeedback(message);
						return false;
					}

				setPersonDocumentFeedback(personDocumentDefaultFeedback);
				return true;
			}

		async function validatePersonDocumentAvailability(force)
			{
				abortPersonDocumentCheck();

				if (!validatePersonDocumentFormat())
					{
						return false;
					}

				const normalizedDocument = normalizePersonDocumentValue(personDocument.value);
				if (!personType.value || !normalizedDocument)
					{
						return true;
					}

				const checkKey = getPersonDocumentCheckKey();
				if (!force && personDocumentCheckCache.key === checkKey)
					{
						return applyPersonDocumentAvailability(personDocumentCheckCache.exists);
					}

				const controller = new AbortController();
				const sequence = personDocumentCheckSequence + 1;
				personDocumentCheckSequence = sequence;
				personDocumentCheckController = controller;

				try
					{
						const url = new URL(PERSON_DOCUMENT_EXISTS_URL, window.location.origin);
						url.searchParams.set("document", normalizedDocument);

						const response = await fetch(url.toString(), {
							method: "GET",
							credentials: "same-origin",
							headers: {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest"},
							signal: controller.signal
						});

						if (!response.ok)
							{
								throw new Error("Document check failed with status " + response.status);
							}

						const data = await response.json();
						if (sequence !== personDocumentCheckSequence || checkKey !== getPersonDocumentCheckKey())
							{
								return true;
							}

						personDocumentCheckCache = {
							key: checkKey,
							exists: !!data.exists
						};

						return applyPersonDocumentAvailability(personDocumentCheckCache.exists);
					}
				catch (err)
					{
						if (err && err.name === "AbortError")
							{
								return true;
							}

						console.error("documentExists fetch error:", err);
						const message = "Não foi possível validar se o documento já existe.";
						personDocument.setCustomValidity(message);
						setPersonDocumentFeedback(message);
						return false;
					}
				finally
					{
						if (personDocumentCheckController === controller)
							{
								personDocumentCheckController = null;
							}
					}
			}

		function queuePersonDocumentAvailabilityCheck()
			{
				abortPersonDocumentCheck();
				personDocumentCheckCache.key = "";

				if (!validatePersonDocumentFormat())
					{
						return;
					}

				personDocumentCheckTimer = setTimeout(function ()
					{
						validatePersonDocumentAvailability(false);
					}, 500);
			}

		personType.addEventListener("change", queuePersonDocumentAvailabilityCheck);
		personDocument.addEventListener("input", queuePersonDocumentAvailabilityCheck);
		personDocument.addEventListener("blur", function ()
			{
				validatePersonDocumentAvailability(false);
			});

		// -----------------------------------------------------------------------
		// Unique field validation
		// -----------------------------------------------------------------------
		const fieldFeedbackDefaults = new WeakMap();
		const uniqueFieldStates = new WeakMap();

		const usernameUniqueConfig = {
			endpoint: PERSON_USERNAME_EXISTS_URL,
			param: "username",
			normalize: function (value) { return String(value || "").trim().toLowerCase(); },
			duplicateMessage: "Este nome de usuário já está cadastrado.",
			failureMessage: "Não foi possível validar se o nome de usuário já existe."
		};

		const phoneNumberUniqueConfig = {
			endpoint: PERSON_PHONE_NUMBER_EXISTS_URL,
			param: "phone_number",
			normalize: function (value) { return String(value || "").replace(/\D/g, ""); },
			duplicateMessage: "Este telefone já está cadastrado.",
			localDuplicateMessage: "Este telefone foi informado mais de uma vez.",
			failureMessage: "Não foi possível validar se o telefone já existe."
		};

		const emailUniqueConfig = {
			endpoint: PERSON_EMAIL_EXISTS_URL,
			param: "email",
			normalize: function (value) { return String(value || "").trim().toLowerCase(); },
			duplicateMessage: "Este e-mail já está cadastrado.",
			localDuplicateMessage: "Este e-mail foi informado mais de uma vez.",
			failureMessage: "Não foi possível validar se o e-mail já existe."
		};

		function getFieldFeedback(el)
			{
				return el && el.parentElement
					? el.parentElement.querySelector(".invalid-feedback")
					: null;
			}

		function getDefaultFieldFeedback(el)
			{
				if (fieldFeedbackDefaults.has(el))
					{
						return fieldFeedbackDefaults.get(el);
					}

				const feedback = getFieldFeedback(el);
				const defaultMessage = feedback ? feedback.textContent : "";
				fieldFeedbackDefaults.set(el, defaultMessage);
				return defaultMessage;
			}

		function setFieldFeedback(el, message)
			{
				const feedback = getFieldFeedback(el);
				if (feedback)
					{
						feedback.textContent = message;
					}
			}

		function resetFieldFeedback(el)
			{
				setFieldFeedback(el, getDefaultFieldFeedback(el));
			}

		function setUniqueFieldValidity(el, message)
			{
				el.setCustomValidity(message);
				setFieldFeedback(el, message);
				return false;
			}

		function getUniqueFieldState(el)
			{
				if (!uniqueFieldStates.has(el))
					{
						uniqueFieldStates.set(el, {
							timer: null,
							controller: null,
							sequence: 0,
							cacheKey: "",
							exists: false
						});
					}

				return uniqueFieldStates.get(el);
			}

		function abortUniqueFieldCheck(el)
			{
				const state = getUniqueFieldState(el);
				if (state.timer)
					{
						clearTimeout(state.timer);
						state.timer = null;
					}

				if (state.controller)
					{
						state.controller.abort();
						state.controller = null;
					}
			}

		function getUniqueFieldCheckKey(el, config)
			{
				return config.endpoint + ":" + config.normalize(el.value);
			}

		function applyUniqueFieldAvailability(el, config, exists)
			{
				if (exists)
					{
						return setUniqueFieldValidity(el, config.duplicateMessage);
					}

				el.setCustomValidity("");
				resetFieldFeedback(el);
				return true;
			}

		async function validateUniqueFieldAvailability(el, config, force)
			{
				abortUniqueFieldCheck(el);
				el.setCustomValidity("");
				resetFieldFeedback(el);

				const normalizedValue = config.normalize(el.value);
				if (!normalizedValue)
					{
						return true;
					}

				if (!el.validity.valid)
					{
						return false;
					}

				const state = getUniqueFieldState(el);
				const checkKey = getUniqueFieldCheckKey(el, config);
				if (!force && state.cacheKey === checkKey)
					{
						return applyUniqueFieldAvailability(el, config, state.exists);
					}

				const controller = new AbortController();
				const sequence = state.sequence + 1;
				state.sequence = sequence;
				state.controller = controller;

				try
					{
						const url = new URL(config.endpoint, window.location.origin);
						url.searchParams.set(config.param, normalizedValue);

						const response = await fetch(url.toString(), {
							method: "GET",
							credentials: "same-origin",
							headers: {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest"},
							signal: controller.signal
						});

						if (!response.ok)
							{
								throw new Error("Unique field check failed with status " + response.status);
							}

						const data = await response.json();
						if (state.sequence !== sequence || checkKey !== getUniqueFieldCheckKey(el, config))
							{
								return true;
							}

						state.cacheKey = checkKey;
						state.exists = !!data.exists;
						return applyUniqueFieldAvailability(el, config, state.exists);
					}
				catch (err)
					{
						if (err && err.name === "AbortError")
							{
								return true;
							}

						console.error("unique field fetch error:", err);
						return setUniqueFieldValidity(el, config.failureMessage);
					}
				finally
					{
						if (state.controller === controller)
							{
								state.controller = null;
							}
					}
			}

		function queueUniqueFieldAvailabilityCheck(el, config)
			{
				const state = getUniqueFieldState(el);
				abortUniqueFieldCheck(el);
				state.cacheKey = "";

				el.setCustomValidity("");
				resetFieldFeedback(el);

				const normalizedValue = config.normalize(el.value);
				if (!normalizedValue || !el.validity.valid)
					{
						return;
					}

				state.timer = setTimeout(function ()
					{
						validateUniqueFieldAvailability(el, config, false);
					}, 500);
			}

		function getPhoneNumberFields()
			{
				return Array.from(phonesContainer.querySelectorAll("input[name^='phones['][name$='[number]']"));
			}

		function getEmailAddressFields()
			{
				return Array.from(emailsContainer.querySelectorAll("input[name^='emails['][name$='[address]']"));
			}

		function validateLocalUniqueFields(fields, config)
			{
				const seen = new Map();
				let ok = true;

				fields.forEach(function (el)
					{
						const normalizedValue = config.normalize(el.value);
						if (!normalizedValue)
							{
								return;
							}

						if (seen.has(normalizedValue))
							{
								setUniqueFieldValidity(el, config.localDuplicateMessage);
								setUniqueFieldValidity(seen.get(normalizedValue), config.localDuplicateMessage);
								ok = false;
								return;
							}

						seen.set(normalizedValue, el);
					});

				return ok;
			}

		async function validateUniqueFieldList(fields, config, force)
			{
				fields.forEach(function (el)
					{
						abortUniqueFieldCheck(el);
						el.setCustomValidity("");
						resetFieldFeedback(el);
					});

				if (!validateLocalUniqueFields(fields, config))
					{
						return false;
					}

				let ok = true;
				for (const el of fields)
					{
						if (!await validateUniqueFieldAvailability(el, config, force))
							{
								ok = false;
							}
					}

				return ok;
			}

		function getUniqueConfigForElement(el)
			{
				if (el === userUsername)
					{
						return usernameUniqueConfig;
					}
				if (el.matches("input[name^='phones['][name$='[number]']"))
					{
						return phoneNumberUniqueConfig;
					}
				if (el.matches("input[name^='emails['][name$='[address]']"))
					{
						return emailUniqueConfig;
					}

				return null;
			}

		userUsername.addEventListener("input", function ()
			{
				queueUniqueFieldAvailabilityCheck(userUsername, usernameUniqueConfig);
			});

		userUsername.addEventListener("blur", function ()
			{
				validateUniqueFieldAvailability(userUsername, usernameUniqueConfig, false);
			});

		document.addEventListener("input", function (e)
			{
				if (!(e.target instanceof HTMLInputElement))
					{
						return;
					}

				const config = getUniqueConfigForElement(e.target);
				if (config && e.target !== userUsername)
					{
						queueUniqueFieldAvailabilityCheck(e.target, config);
					}
			});

		document.addEventListener("blur", function (e)
			{
				if (!(e.target instanceof HTMLInputElement))
					{
						return;
					}

				const config = getUniqueConfigForElement(e.target);
				if (config && e.target !== userUsername)
					{
						validateUniqueFieldAvailability(e.target, config, false);
					}
			}, true);

		// -----------------------------------------------------------------------
		// Contador de endereços
		// -----------------------------------------------------------------------
		let addressCounter = 0;
		let phoneCounter = 0;
		let emailCounter = 0;

		function getFormState()
			{
				const fields = {};
				Array.from(form.querySelectorAll("input[name], select[name], textarea[name]")).forEach(function (el)
					{
						const name = el.name;
						if (!name)
							{
								return;
							}
						if (el.type === "checkbox")
							{
								fields[name] = !!el.checked;
								return;
							}
						if (el.type === "radio")
							{
								if (el.checked)
									{
										fields[name] = el.value;
									}
								return;
							}
						fields[name] = el.value;
					});

				return {
					currentStep: currentStep,
					fields: fields
				};
			}

		function saveFormState()
			{
				try
					{
						sessionStorage.setItem(STORAGE_KEY, JSON.stringify(getFormState()));
					}
				catch (err)
					{
						console.warn("Could not persist create form state:", err);
					}
			}

		function clearFormState()
			{
				try
					{
						sessionStorage.removeItem(STORAGE_KEY);
					}
				catch (err)
					{
						console.warn("Could not clear create form state:", err);
					}
			}

		function ensureDynamicRows(fieldNames, prefix, addRowFn)
			{
				let maxIndex = -1;
				fieldNames.forEach(function (name)
					{
						const match = name.match(new RegExp("^" + prefix + "\\\\[(\\\\d+)\\\\]"));
						if (!match)
							{
								return;
							}
						const idx = parseInt(match[1], 10);
						if (!Number.isNaN(idx))
							{
								maxIndex = Math.max(maxIndex, idx);
							}
					});

				const requiredCount = maxIndex + 1;
				for (let i = 1; i < requiredCount; i += 1)
					{
						addRowFn();
					}
			}

		function restoreFormState()
			{
				let raw;
				try
					{
						raw = sessionStorage.getItem(STORAGE_KEY);
					}
				catch (err)
					{
						console.warn("Could not read persisted create form state:", err);
						return;
					}

				if (!raw)
					{
						return;
					}

				let state;
				try
					{
						state = JSON.parse(raw);
					}
				catch (_err)
					{
						clearFormState();
						return;
					}

				if (!state || typeof state !== "object" || !state.fields || typeof state.fields !== "object")
					{
						clearFormState();
						return;
					}

				const fieldNames = Object.keys(state.fields);
				ensureDynamicRows(fieldNames, "addresses", addAddressRow);
				ensureDynamicRows(fieldNames, "phones", addPhoneRow);
				ensureDynamicRows(fieldNames, "emails", addEmailRow);

				fieldNames.forEach(function (name)
					{
						const value = state.fields[name];
						const controls = form.querySelectorAll("[name=\"" + CSS.escape(name) + "\"]");
						if (!controls.length)
							{
								return;
							}

						controls.forEach(function (el)
							{
								if (el.type === "checkbox")
									{
										el.checked = !!value;
										return;
									}
								if (el.type === "radio")
									{
										el.checked = String(el.value) === String(value);
										return;
									}
								el.value = value == null ? "" : String(value);
							});
					});

				updateTenantPanels(true);

				const targetStep = Math.min(TOTAL_STEPS, Math.max(1, parseInt(state.currentStep, 10) || 1));
				showStep(targetStep);
				if (targetStep === TOTAL_STEPS)
					{
						buildReview();
					}
			}

		// -----------------------------------------------------------------------
		// Mini-autocomplete genérico (reutilizado para bairro e cidade)
		// endpoint : URL da busca (retorna { items: [{id, label}] })
		// inputEl  : <input type="text">
		// listEl   : <ul> de resultados
		// onSelect : callback({ id, label })
		// extraOption : { label, onClick } — opção extra ao final da lista (opcional)
		// -----------------------------------------------------------------------
		function initMiniAutocomplete(endpoint, inputEl, listEl, onSelect, extraOption)
			{
				let abort = null;

				function clearList()
					{
						listEl.innerHTML = "";
						listEl.style.display = "none";
					}

				function renderList(items, term)
					{
						listEl.innerHTML = "";

						items.forEach(function (item)
							{
								const li = document.createElement("li");
								li.className = "list-group-item list-group-item-action py-1 px-2";
								li.textContent = item.label;
								li.addEventListener("mousedown", function (e)
									{
										e.preventDefault();
										const displayValue = item.label || item.name || "";
										onSelect(item);
										clearList();
										inputEl.value = displayValue;
									});
								listEl.appendChild(li);
							});

						// Opção extra (ex.: "Criar bairro")
						if (extraOption)
							{
								const liExtra = document.createElement("li");
								liExtra.className = "list-group-item list-group-item-action py-1 px-2 text-primary fw-semibold";
								liExtra.textContent = extraOption.label(term);
								liExtra.addEventListener("mousedown", function (e)
									{
										e.preventDefault();
										clearList();
										extraOption.onClick(term);
									});
								listEl.appendChild(liExtra);
							}

						if (listEl.children.length > 0)
							{
								listEl.style.display = "block";
							}
					}

				inputEl.addEventListener("input", async function ()
					{
						const term = inputEl.value.trim();
						if (term.length < 1)
							{
								clearList();
								return;
							}

						if (abort)
							{
								abort.abort();
							}
						abort = new AbortController();

						try
							{
								const url = new URL(endpoint, window.location.origin);
								url.searchParams.set("term", term);
								const res  = await fetch(url.toString(), {
									method: "GET",
									credentials: "same-origin",
									headers: {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest"},
									signal: abort.signal
								});
								const data = await res.json();
								renderList(data.items || [], term);
							}
						catch (err)
							{
								if (err && err.name !== "AbortError")
									{
										console.error("miniAutocomplete fetch error:", err);
									}
							}
					});

				document.addEventListener("click", function (e)
					{
						if (e.target !== inputEl && !listEl.contains(e.target))
							{
								clearList();
							}
					});
			}

		// -----------------------------------------------------------------------
		// Inicializa o autocomplete de bairro dentro de um bloco de endereço
		// -----------------------------------------------------------------------
		function initAddressNeighborhoodAutocomplete(block)
			{
				const hiddenId    = block.querySelector(".addr-neighborhood-id");
				const textInput   = block.querySelector(".addr-neighborhood-input");
				const resultsList = block.querySelector(".addr-neighborhood-results");
				const createPanel = block.querySelector(".addr-create-panel");
				const newNameInput  = block.querySelector(".addr-new-neighborhood-name");
				const newCityId     = block.querySelector(".addr-new-city-id");
				const newCityInput  = block.querySelector(".addr-new-city-input");
				const newCityList   = block.querySelector(".addr-new-city-results");
				const btnSave       = block.querySelector(".addr-btn-save-neighborhood");
				const btnCancel     = block.querySelector(".addr-btn-cancel-neighborhood");

				function buildNeighborhoodLabel(neighborhoodName, cityLabel)
					{
						const parts = [neighborhoodName, cityLabel]
							.map(function (value) { return String(value || "").trim(); })
							.filter(Boolean);
						return parts.join(" — ");
					}

				function showCreatePanel(term)
					{
						createPanel.classList.remove("d-none");
						newNameInput.value = term;
						newCityId.value    = "";
						newCityInput.value = "";
						textInput.value    = "";
						hiddenId.value     = "";
					}

				function hideCreatePanel()
					{
						createPanel.classList.add("d-none");
						newNameInput.value = "";
						newCityId.value    = "";
						newCityInput.value = "";
					}

				textInput.addEventListener("input", function ()
					{
						hiddenId.value = "";
					});

				newCityInput.addEventListener("input", function ()
					{
						newCityId.value = "";
					});

				// Autocomplete do bairro com opção "Criar"
				initMiniAutocomplete(
					PERSON_NEIGHBORHOOD_SEARCH_URL,
					textInput,
					resultsList,
					function onSelect(item)
						{
							hiddenId.value   = item.id;
							textInput.value  = item.label;
							hideCreatePanel();
						},
					{
						label: function (term) { return "+ Criar bairro: \"" + term + "\""; },
						onClick: function (term) { showCreatePanel(term); }
					}
				);

				// Autocomplete de cidade dentro do painel de criação
				initMiniAutocomplete(
					PERSON_CITY_SEARCH_URL,
					newCityInput,
					newCityList,
					function onSelect(item)
						{
							newCityId.value    = item.id;
							newCityInput.value = item.label;
						},
					null
				);

				// Salvar novo bairro via AJAX
				btnSave.addEventListener("click", async function ()
					{
						const name   = newNameInput.value.trim();
						const cityId = newCityId.value.trim();

						if (!name)
							{
								newNameInput.focus();
								newNameInput.classList.add("is-invalid");
								return;
							}
						newNameInput.classList.remove("is-invalid");

						if (!cityId)
							{
								newCityInput.focus();
								newCityInput.classList.add("is-invalid");
								return;
							}
						newCityInput.classList.remove("is-invalid");

						btnSave.disabled    = true;
						btnSave.textContent = "Salvando...";

						try
							{
								const body = new URLSearchParams({name: name, city_id: cityId});
								const res  = await fetch(PERSON_NEIGHBORHOOD_CREATE_URL, {
									method: "POST",
									credentials: "same-origin",
									headers: {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest", "Content-Type": "application/x-www-form-urlencoded"},
									body: body.toString()
								});
								const data = await res.json();

								if (data.error)
									{
										alert("Erro: " + data.error);
										return;
									}

								hiddenId.value  = data.id;
								textInput.value = data.label || buildNeighborhoodLabel(name, newCityInput.value);
								hideCreatePanel();
							}
						catch (err)
							{
								alert("Erro de comunicação ao salvar bairro.");
								console.error(err);
							}
						finally
							{
								btnSave.disabled    = false;
								btnSave.textContent = "Salvar";
							}
					});

				// Cancelar criação
				btnCancel.addEventListener("click", function ()
					{
						hideCreatePanel();
					});
			}

		function getAddressInput(block, fieldName)
			{
				if (!block)
					{
						return null;
					}

				return block.querySelector("input[name^='addresses['][name$='[" + fieldName + "]']");
			}

		function onlyZipCodeDigits(value)
			{
				return String(value || "").replace(/\D/g, "");
			}

		function formatZipCodeValue(value)
			{
				const digits = onlyZipCodeDigits(value).slice(0, 8);
				return digits.replace(/(\d{5})(\d{1,3})$/, "$1-$2");
			}

		function setControlValue(el, value, shouldDispatch)
			{
				if (!el)
					{
						return;
					}

				el.value = value || "";

				if (shouldDispatch)
					{
						el.dispatchEvent(new Event("input", {bubbles: true}));
						el.dispatchEvent(new Event("change", {bubbles: true}));
					}
			}

		function getAddressVisibleInput(block, fieldName)
			{
				if (fieldName === "neighborhood_name")
					{
						return block ? block.querySelector(".addr-neighborhood-input") : null;
					}

				return getAddressInput(block, fieldName);
			}

		function addressFeedbackClassName(fieldName)
			{
				return "addr-" + String(fieldName || "").replace(/[^a-z0-9_-]+/gi, "-") + "-feedback";
			}

		function getAddressFieldFeedback(block, fieldName)
			{
				const className = addressFeedbackClassName(fieldName);
				let feedback = block ? block.querySelector("." + className) : null;
				if (feedback)
					{
						return feedback;
					}

				const input = getAddressVisibleInput(block, fieldName);
				if (!input)
					{
						return null;
					}

				feedback = document.createElement("div");
				feedback.className = "form-text addr-field-feedback " + className;
				input.insertAdjacentElement("afterend", feedback);
				return feedback;
			}

		function setAddressFieldFeedback(block, fieldName, message, type)
			{
				const feedback = getAddressFieldFeedback(block, fieldName);
				if (!feedback)
					{
						return;
					}

				feedback.textContent = message || "";
				feedback.className = "form-text addr-field-feedback " + addressFeedbackClassName(fieldName);

				if (type === "success")
					{
						feedback.classList.add("text-success");
						return;
					}

				if (type === "warning")
					{
						feedback.classList.add("text-warning");
						return;
					}

				if (type === "error")
					{
						feedback.classList.add("text-danger");
						return;
					}

				feedback.classList.add("text-muted");
			}

		function clearAddressFieldFeedbacks(block)
			{
				["street", "number", "observations", "neighborhood_name"].forEach(function (fieldName)
					{
						const feedback = block ? block.querySelector("." + addressFeedbackClassName(fieldName)) : null;
						if (feedback)
							{
								feedback.textContent = "";
								feedback.className = "form-text addr-field-feedback " + addressFeedbackClassName(fieldName);
							}
					});
			}

		function waitForZipCodeAutocompleteStep()
			{
				return new Promise(function (resolve)
					{
						setTimeout(resolve, 45);
					});
			}

		function getAddressZipCodeFeedback(block)
			{
				let feedback = block.querySelector(".addr-zip-code-feedback");
				if (feedback)
					{
						return feedback;
					}

				const zipCodeInput = getAddressInput(block, "zip_code");
				if (!zipCodeInput)
					{
						return null;
					}

				feedback = document.createElement("div");
				feedback.className = "form-text addr-zip-code-feedback";
				zipCodeInput.insertAdjacentElement("afterend", feedback);
				return feedback;
			}

		function setAddressZipCodeFeedback(block, message, type)
			{
				const feedback = getAddressZipCodeFeedback(block);
				if (!feedback)
					{
						return;
					}

				feedback.textContent = message || "";
				feedback.className = "form-text addr-zip-code-feedback";

				if (type === "error")
					{
						feedback.classList.add("text-danger");
						return;
					}

				if (type === "success")
					{
						feedback.classList.add("text-success");
						return;
					}

				if (type === "warning")
					{
						feedback.classList.add("text-warning");
						return;
					}

				feedback.classList.add("text-muted");
			}

		function getSavedZipCodeRecord(data, key)
			{
				if (!data || !data.saved || !data.saved[key] || typeof data.saved[key] !== "object")
					{
						return null;
					}

				return data.saved[key];
			}

		function buildZipCodeNeighborhoodLabel(data)
			{
				if (data.neighborhood_label)
					{
						return data.neighborhood_label;
					}

				const neighborhood = getSavedZipCodeRecord(data, "neighborhood");
				const city = getSavedZipCodeRecord(data, "city");
				const state = getSavedZipCodeRecord(data, "state");
				const neighborhoodName = neighborhood && neighborhood.name ? neighborhood.name : data.neighborhood;

				if (!neighborhoodName)
					{
						return "";
					}

				return [
					neighborhoodName,
					city && city.name ? city.name : data.city,
					state && state.acronym ? state.acronym : data.state
				].map(function (value)
					{
						return String(value || "").trim();
					})
					.filter(Boolean)
					.join(" — ");
			}

		function normalizeZipCodeLookupData(payload)
			{
				const data = Object.assign({}, payload && payload.data ? payload.data : (payload || {}));
				const neighborhood = getSavedZipCodeRecord(data, "neighborhood");

				if (!data.neighborhood_id && neighborhood && neighborhood.id)
					{
						data.neighborhood_id = neighborhood.id;
					}

				data.neighborhood_label = buildZipCodeNeighborhoodLabel(data);
				return data;
			}

		async function searchNeighborhoodAutocompleteItems(term, data)
			{
				const url = new URL(PERSON_NEIGHBORHOOD_SEARCH_URL, window.location.origin);
				url.searchParams.set("term", term);
				url.searchParams.set("limit", "50");

				if (data.city)
					{
						url.searchParams.set("city_name", data.city);
					}

				if (data.state)
					{
						url.searchParams.set("state_acronym", data.state);
					}

				const response = await fetch(url.toString(), {
					method: "GET",
					credentials: "same-origin",
					headers: {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest"}
				});

				if (!response.ok)
					{
						throw new Error("Não foi possível pesquisar o bairro no autocomplete.");
					}

				const payload = await response.json();
				return Array.isArray(payload.items) ? payload.items : [];
			}

		function renderAddressNeighborhoodResults(block, items)
			{
				const neighborhoodInput = block.querySelector(".addr-neighborhood-input");
				const resultsList = block.querySelector(".addr-neighborhood-results");
				if (!neighborhoodInput || !resultsList)
					{
						return;
					}

				resultsList.innerHTML = "";
				items.forEach(function (item)
					{
						const li = document.createElement("li");
						li.className = "list-group-item list-group-item-action py-1 px-2";
						li.textContent = item.label;
						li.addEventListener("mousedown", function (event)
							{
								event.preventDefault();
								selectAddressNeighborhoodItem(block, item);
							});
						resultsList.appendChild(li);
					});

				resultsList.style.display = items.length > 0 ? "block" : "none";
			}

		function clearAddressNeighborhoodResults(block)
			{
				const resultsList = block.querySelector(".addr-neighborhood-results");
				if (!resultsList)
					{
						return;
					}

				resultsList.innerHTML = "";
				resultsList.style.display = "none";
			}

		function selectAddressNeighborhoodItem(block, item)
			{
				const neighborhoodId = block.querySelector(".addr-neighborhood-id");
				const neighborhoodInput = block.querySelector(".addr-neighborhood-input");
				const createPanel = block.querySelector(".addr-create-panel");

				setControlValue(neighborhoodInput, item.label || item.name || "", false);
				setControlValue(neighborhoodId, item.id || "", true);
				clearAddressNeighborhoodResults(block);

				if (createPanel)
					{
						createPanel.classList.add("d-none");
					}

				if (typeof saveFormState === "function")
					{
						saveFormState();
					}
			}

		async function autoSelectAddressNeighborhood(block, data)
			{
				const neighborhoodInput = block.querySelector(".addr-neighborhood-input");
				const neighborhoodId = block.querySelector(".addr-neighborhood-id");
				const neighborhood = getSavedZipCodeRecord(data, "neighborhood");
				const neighborhoodName = String((neighborhood && neighborhood.name) || data.neighborhood || "").trim();

				if (!neighborhoodInput || !neighborhoodId || !neighborhoodName)
					{
						return false;
					}

				neighborhoodId.value = "";

				let lastItems = [];
				for (let i = 1; i <= neighborhoodName.length; i += 1)
					{
						const term = neighborhoodName.slice(0, i);
						setControlValue(neighborhoodInput, term, false);

						lastItems = await searchNeighborhoodAutocompleteItems(term, data);
						renderAddressNeighborhoodResults(block, lastItems);

						if (lastItems.length === 1)
							{
								selectAddressNeighborhoodItem(block, lastItems[0]);
								return true;
							}

						await waitForZipCodeAutocompleteStep();
					}

				if (data.neighborhood_id)
					{
						const item = lastItems.find(function (candidate)
							{
								return String(candidate.id || "") === String(data.neighborhood_id);
							});

						selectAddressNeighborhoodItem(block, item || {
							id: data.neighborhood_id,
							label: data.neighborhood_label || neighborhoodName,
							name: neighborhoodName
						});
						return true;
					}

				clearAddressNeighborhoodResults(block);
				return false;
			}

		async function searchAddressZipCode(zipCode)
			{
				if (typeof PERSON_ZIP_CODE_SEARCH_URL === "undefined" || !PERSON_ZIP_CODE_SEARCH_URL)
					{
						throw new Error("Endpoint de consulta de CEP não configurado.");
					}

				const digits = onlyZipCodeDigits(zipCode);
				const url = new URL(PERSON_ZIP_CODE_SEARCH_URL, window.location.origin);
				url.searchParams.set("zip_code", digits);

				const response = await fetch(url.toString(), {
					method: "GET",
					credentials: "same-origin",
					headers: {"Accept": "application/json", "X-Requested-With": "XMLHttpRequest"}
				});

				const payload = await response.json();
				if (!response.ok || (payload && payload.success === false))
					{
						throw new Error((payload && payload.message) || "Não foi possível consultar o CEP.");
					}

				return normalizeZipCodeLookupData(payload);
			}

		async function fillAddressFromZipCode(block, data)
			{
				const zipCodeInput = getAddressInput(block, "zip_code");
				const streetInput = getAddressInput(block, "street");

				setControlValue(zipCodeInput, data.zip_code || formatZipCodeValue(zipCodeInput ? zipCodeInput.value : ""), false);
				setControlValue(streetInput, data.street || "", true);

				return await autoSelectAddressNeighborhood(block, data);
			}

		function setAddressFieldFeedbacksAfterZipCodeLookup(block, data, neighborhoodWasSelected)
			{
				const filledMessage = "Endereço preenchido pelo CEP.";
				const pendingMessage = "Pendente de preenchimento.";

				setAddressFieldFeedback(
					block,
					"street",
					data.street ? filledMessage : pendingMessage,
					data.street ? "success" : "muted"
				);

				setAddressFieldFeedback(
					block,
					"neighborhood_name",
					neighborhoodWasSelected ? filledMessage : pendingMessage,
					neighborhoodWasSelected ? "success" : "muted"
				);

				setAddressFieldFeedback(block, "number", pendingMessage, "muted");
				setAddressFieldFeedback(block, "observations", pendingMessage, "muted");
			}

		function initAddressZipCodeLookup(block)
			{
				const zipCodeInput = getAddressInput(block, "zip_code");
				if (!zipCodeInput || zipCodeInput.dataset.zipCodeLookupBound === "1")
					{
						return;
					}

				zipCodeInput.dataset.zipCodeLookupBound = "1";

				let lookupTimer = null;
				let lastLookupDigits = "";

				function clearLookupTimer()
					{
						if (lookupTimer)
							{
								clearTimeout(lookupTimer);
								lookupTimer = null;
							}
					}

				async function runLookup(force)
					{
						clearLookupTimer();

						const digits = onlyZipCodeDigits(zipCodeInput.value);
						if (digits.length === 0)
							{
								lastLookupDigits = "";
								setAddressZipCodeFeedback(block, "", "muted");
								clearAddressFieldFeedbacks(block);
								return;
							}

						if (digits.length !== 8)
							{
								if (force)
									{
										setAddressZipCodeFeedback(block, "Informe um CEP válido com 8 dígitos.", "error");
									}
								return;
							}

						if (!force && lastLookupDigits === digits)
							{
								return;
							}

						lastLookupDigits = digits;
						setAddressZipCodeFeedback(block, "Consultando CEP...", "muted");
						clearAddressFieldFeedbacks(block);

						try
							{
								const data = await searchAddressZipCode(digits);
								const neighborhoodWasSelected = await fillAddressFromZipCode(block, data);
								setAddressFieldFeedbacksAfterZipCodeLookup(block, data, neighborhoodWasSelected);

								if (data.neighborhood && !neighborhoodWasSelected)
									{
										setAddressZipCodeFeedback(block, "CEP encontrado. Selecione ou cadastre o bairro antes de salvar.", "warning");
										return;
									}

								setAddressZipCodeFeedback(block, "Endereço preenchido pelo CEP.", "success");
							}
						catch (err)
							{
								setAddressZipCodeFeedback(block, err.message || "Não foi possível consultar o CEP.", "error");
								lastLookupDigits = "";
								console.error("zip code lookup error:", err);
							}
					}

				zipCodeInput.addEventListener("input", function ()
					{
						clearLookupTimer();

						const digits = onlyZipCodeDigits(zipCodeInput.value);
						if (digits.length === 0)
							{
								lastLookupDigits = "";
								setAddressZipCodeFeedback(block, "", "muted");
								clearAddressFieldFeedbacks(block);
								return;
							}

						if (digits.length !== 8)
							{
								lastLookupDigits = "";
								return;
							}

						lookupTimer = setTimeout(function ()
							{
								runLookup(false);
							}, 400);
					});

				zipCodeInput.addEventListener("blur", function ()
					{
						runLookup(false);
					});

				zipCodeInput.addEventListener("keydown", function (event)
					{
						if (event.key === "Enter")
							{
								event.preventDefault();
								runLookup(true);
							}
					});
			}

		// -----------------------------------------------------------------------
		// Tenant: alternar painéis conforme modo selecionado
		// -----------------------------------------------------------------------
		function updateTenantPanels(forceDefaultMode)
			{
				const isRootUser = userType.value === "root";
				const tenantCreateOption = tenantMode.querySelector('option[value="new"]');

				tenantMode.disabled = isRootUser;

				if (tenantCreateOption)
					{
						tenantCreateOption.disabled = isRootUser;
						tenantCreateOption.hidden = isRootUser;
					}

				if (isRootUser)
					{
						tenantMode.value = "none";
					}
				else if (forceDefaultMode === true && userType.value !== "")
					{
						tenantMode.value = "new";
					}
				else if (tenantMode.value !== "none" && tenantMode.value !== "new")
					{
						tenantMode.value = "new";
					}

				const mode = tenantMode.value;
				tenantNewFields.classList.toggle("d-none", mode !== "new");
			}

		tenantMode.addEventListener("change", function ()
			{
				updateTenantPanels(false);
			});
		userType.addEventListener("change", function ()
			{
				updateTenantPanels(true);
			});

		// -----------------------------------------------------------------------
		// Navegação de etapas
		// -----------------------------------------------------------------------
		function showStep(step)
			{
				currentStep = step;

				form.querySelectorAll("section[data-step]").forEach((sec) =>
					{
						const n = parseInt(sec.getAttribute("data-step"), 10);
						sec.classList.toggle("d-none", n !== currentStep);
					});

				const meta = steps.find((s) => s.n === currentStep);
				stepTitle.textContent    = meta.title;
				stepSubtitle.textContent = meta.subtitle;
				stepBadge.textContent    = currentStep + "/" + TOTAL_STEPS;

				const pct = Math.round((currentStep / TOTAL_STEPS) * 100);
				progressBar.style.width = pct + "%";

				document.querySelectorAll("[data-step-jump]").forEach((b) =>
					{
						const n = parseInt(b.getAttribute("data-step-jump"), 10);
						if (n === currentStep)
							{
								b.classList.remove("btn-outline-secondary");
								b.classList.add("btn-primary");
								b.setAttribute("aria-current", "step");
							}
						else
							{
								b.classList.remove("btn-primary");
								b.classList.add("btn-outline-secondary");
								b.removeAttribute("aria-current");
							}
					});

				btnBack.disabled = currentStep === 1;
				btnNext.classList.toggle("d-none", currentStep === TOTAL_STEPS);
				btnSubmit.classList.toggle("d-none", currentStep !== TOTAL_STEPS);

				const activeSection = form.querySelector('section[data-step="' + currentStep + '"]');
				const firstControl = activeSection
					? activeSection.querySelector("input:not([type='hidden']):not([disabled]), select:not([disabled]), textarea:not([disabled])")
					: null;
				if (firstControl)
					{
						window.requestAnimationFrame(function ()
							{
								firstControl.focus({preventScroll: true});
							});
					}
			}

		// -----------------------------------------------------------------------
		// Validação da etapa atual
		// -----------------------------------------------------------------------
		function getCurrentStepRequiredControls()
			{
				const section = form.querySelector('section[data-step="' + currentStep + '"]');
				if (!section)
					{
						return [];
					}
				return Array.from(section.querySelectorAll("input, select, textarea"))
					.filter((el) => el.hasAttribute("required"));
			}

		function validateStep3()
			{
				// Validação do tenant
				const mode = tenantMode.value;

				if (userType.value === "root" && mode !== "none")
					{
						showAlert("Usuário Root não pode ser associado a tenant.");
						return false;
					}

				return true;
			}

		function validateStep4()
			{
				// Pelo menos um endereço com zip_code, street e number preenchidos
				const blocks = Array.from(addressesContainer.querySelectorAll("[data-remove='address']"))
					.map((btn) => btn.closest(".border"))
					.filter(Boolean);

				if (blocks.length === 0)
					{
						showAlert("Adicione ao menos um endereço.");
						return false;
					}

				for (let i = 0; i < blocks.length; i++)
					{
						const zipCode  = blocks[i].querySelector("input[name^='addresses['][name$='[zip_code]']");
						const street   = blocks[i].querySelector("input[name^='addresses['][name$='[street]']");
						const number   = blocks[i].querySelector("input[name^='addresses['][name$='[number]']");
						const neighId = blocks[i].querySelector(".addr-neighborhood-id");

						if (!zipCode || !zipCode.value.trim())
							{
								showAlert("Endereço " + (i + 1) + ": informe o CEP.");
								return false;
							}
						if (!street || !street.value.trim())
							{
								showAlert("Endereço " + (i + 1) + ": informe o logradouro.");
								return false;
							}
						if (!number || !number.value.trim())
							{
								showAlert("Endereço " + (i + 1) + ": informe o número.");
								return false;
							}
						if (!neighId || !neighId.value.trim())
							{
								showAlert("Endereço " + (i + 1) + ": selecione ou cadastre o bairro.");
								return false;
							}
					}

				return true;
			}

		async function validateStep5()
			{
				// Pelo menos um telefone preenchido
				const phoneBlocks = Array.from(phonesContainer.querySelectorAll("[data-remove='phone']"))
					.map((btn) => btn.closest(".border"))
					.filter(Boolean);

				if (phoneBlocks.length === 0)
					{
						showAlert("Adicione ao menos um telefone.");
						return false;
					}

				for (let i = 0; i < phoneBlocks.length; i++)
					{
						const num = phoneBlocks[i].querySelector("input[name^='phones['][name$='[number]']");
						if (!num || !num.value.trim())
							{
								showAlert("Telefone " + (i + 1) + ": informe o número.");
								return false;
							}
					}

				// Pelo menos um e-mail preenchido
				const emailBlocks = Array.from(emailsContainer.querySelectorAll("[data-remove='email']"))
					.map((btn) => btn.closest(".border"))
					.filter(Boolean);

				if (emailBlocks.length === 0)
					{
						showAlert("Adicione ao menos um e-mail.");
						return false;
					}

				for (let i = 0; i < emailBlocks.length; i++)
					{
						const addr = emailBlocks[i].querySelector("input[name^='emails['][name$='[address]']");
						if (!addr || !addr.value.trim())
							{
								showAlert("E-mail " + (i + 1) + ": informe o endereço de e-mail.");
								return false;
							}
					}

				const phoneNumbersAreAvailable = await validateUniqueFieldList(getPhoneNumberFields(), phoneNumberUniqueConfig, true);
				const emailsAreAvailable = await validateUniqueFieldList(getEmailAddressFields(), emailUniqueConfig, true);
				form.classList.add("was-validated");

				return phoneNumbersAreAvailable && emailsAreAvailable;
			}

		async function validateCurrentStep()
			{
				if (currentStep === 1)
					{
						await validatePersonDocumentAvailability(true);
					}

				// Validação customizada: senhas (etapa 2)
				if (currentStep === 2)
					{
						await validateUniqueFieldAvailability(userUsername, usernameUniqueConfig, true);

						if (userPassword.value !== userPassword2.value)
							{
								userPassword2.setCustomValidity("As senhas não coincidem.");
							}
						else
							{
								userPassword2.setCustomValidity("");
							}
					}

				// Validações customizadas por etapa
				if (currentStep === 3)
					{
						return validateStep3();
					}

				if (currentStep === 4)
					{
						return validateStep4();
					}

				if (currentStep === 5)
					{
						return await validateStep5();
					}

				const requiredControls = getCurrentStepRequiredControls();
				let ok = true;

				requiredControls.forEach((el) =>
					{
						if (!el.checkValidity())
							{
								ok = false;
							}
					});

				form.classList.add("was-validated");
				return ok;
			}

		// -----------------------------------------------------------------------
		// Alerta inline simples
		// -----------------------------------------------------------------------
		function showAlert(msg)
			{
				const section = form.querySelector('section[data-step="' + currentStep + '"]');
				if (!section)
					{
						alert(msg);
						return;
					}

				let alertEl = section.querySelector(".js-step-alert");
				if (!alertEl)
					{
						alertEl = document.createElement("div");
						alertEl.className = "alert alert-danger mt-2 js-step-alert";
						alertEl.setAttribute("role", "alert");
						section.prepend(alertEl);
					}

				alertEl.textContent = msg;
				alertEl.classList.remove("d-none");

				setTimeout(() =>
					{
						alertEl.classList.add("d-none");
					}, 4000);
			}

		// -----------------------------------------------------------------------
		// Helpers
		// -----------------------------------------------------------------------
		function escapeHtml(str)
			{
				return String(str)
					.replaceAll("&", "&amp;")
					.replaceAll("<", "&lt;")
					.replaceAll(">", "&gt;")
					.replaceAll('"', "&quot;")
					.replaceAll("'", "&#039;");
			}

		function normalizeValue(el)
			{
				if (!el)
					{
						return "";
					}
				if (el.type === "checkbox")
					{
						return el.checked ? "Sim" : "Não";
					}
				if (el.tagName === "SELECT")
					{
						return el.value
							? (el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : el.value)
							: "";
					}
				return (el.value || "").trim();
			}

		// -----------------------------------------------------------------------
		// Adicionar linhas dinâmicas
		// -----------------------------------------------------------------------
		function addAddressRow()
			{
				const idx = addressCounter++;
				const node = addressTemplate.content.cloneNode(true);
				node.querySelectorAll("[name]").forEach(function (el)
					{
						el.name = el.name.replace("addresses[]", "addresses[" + idx + "]");
					});

				// Captura referência ao bloco antes do appendChild (DocumentFragment esvazia após append)
				const block = node.querySelector(".border");

				addressesContainer.appendChild(node);

				// Inicializa o autocomplete de bairro no bloco recém-inserido
				if (block)
					{
						initAddressZipCodeLookup(block);
						initAddressNeighborhoodAutocomplete(block);
					}
			}

		function addPhoneRow()
			{
				const idx = phoneCounter++;
				const node = phoneTemplate.content.cloneNode(true);
				node.querySelectorAll("[name]").forEach(function (el)
					{
						el.name = el.name.replace("phones[]", "phones[" + idx + "]");
					});
				phonesContainer.appendChild(node);
			}

		function addEmailRow()
			{
				const idx = emailCounter++;
				const node = emailTemplate.content.cloneNode(true);
				node.querySelectorAll("[name]").forEach(function (el)
					{
						el.name = el.name.replace("emails[]", "emails[" + idx + "]");
					});
				emailsContainer.appendChild(node);
			}

		// -----------------------------------------------------------------------
		// Revisão
		// -----------------------------------------------------------------------
		function buildReview()
			{
				reviewBody.innerHTML = "";

				const rows = [];

				// Pessoa
				rows.push(["Pessoa / Nome",           normalizeValue(document.getElementById("personName"))]);
				rows.push(["Pessoa / Nome fantasia",   normalizeValue(document.getElementById("personFantasy"))]);
				rows.push(["Pessoa / Tipo",            normalizeValue(document.getElementById("personType"))]);
				rows.push(["Pessoa / Documento",       normalizeValue(document.getElementById("personDocument"))]);
				rows.push(["Pessoa / Nascimento",      normalizeValue(document.getElementById("personBirthdate"))]);

				// Usuário
				rows.push(["Usuário / Username",       normalizeValue(document.getElementById("userUsername"))]);
				rows.push(["Usuário / Tipo de acesso", normalizeValue(document.getElementById("userType"))]);

				// Tenant
				const tMode = tenantMode.value;
				if (tMode === "new")
					{
						rows.push(["Tenant / Modo", "Criar novo"]);
						rows.push(["Tenant / Situação", normalizeValue(document.getElementById("tenantStatus"))]);
						rows.push(["Tenant / N. Banco", normalizeValue(document.getElementById("tenantDatabaseNumber"))]);
						rows.push(["Tenant / Observações", normalizeValue(document.getElementById("tenantObservations"))]);
					}
				else
					{
						rows.push(["Tenant", "Não associado"]);
					}

				// Endereços
				const addressBlocks = Array.from(
					addressesContainer.querySelectorAll("[data-remove='address']")
				).map((btn) => btn.closest(".border")).filter(Boolean);

				if (addressBlocks.length)
					{
						addressBlocks.forEach((block, i)  =>
							{
								const n       = i + 1;
								const zipCode = block.querySelector("input[name^='addresses['][name$='[zip_code]']");
								const street  = block.querySelector("input[name^='addresses['][name$='[street]']");
								const number  = block.querySelector("input[name^='addresses['][name$='[number]']");
								const obs        = block.querySelector("input[name^='addresses['][name$='[observations]']");
								const neighInput = block.querySelector(".addr-neighborhood-input");

								rows.push(["Endereço " + n + " / CEP",         normalizeValue(zipCode)]);
								rows.push(["Endereço " + n + " / Logradouro",  normalizeValue(street)]);
								rows.push(["Endereço " + n + " / Número",      normalizeValue(number)]);
								rows.push(["Endereço " + n + " / Complemento", normalizeValue(obs)]);
								rows.push(["Endereço " + n + " / Bairro",      normalizeValue(neighInput)]);
							});
					}
				else
					{
						rows.push(["Endereços", "—"]);
					}

				// Telefones
				const phoneBlocks = Array.from(
					phonesContainer.querySelectorAll("[data-remove='phone']")
				).map((btn) => btn.closest(".border")).filter(Boolean);

				if (phoneBlocks.length)
					{
						phoneBlocks.forEach((block, i) =>
							{
								const number = block.querySelector("input[name^='phones['][name$='[number]']");
								const notes  = block.querySelector("input[name^='phones['][name$='[notes]']");
								rows.push(["Telefone " + (i + 1) + " / Número",      normalizeValue(number)]);
								rows.push(["Telefone " + (i + 1) + " / Observações", normalizeValue(notes)]);
							});
					}
				else
					{
						rows.push(["Telefones", "—"]);
					}

				// E-mails
				const emailBlocks = Array.from(
					emailsContainer.querySelectorAll("[data-remove='email']")
				).map((btn) => btn.closest(".border")).filter(Boolean);

				if (emailBlocks.length)
					{
						emailBlocks.forEach((block, i) =>
							{
								const address  = block.querySelector("input[name^='emails['][name$='[address]']");
								const desc     = block.querySelector("input[name^='emails['][name$='[description]']");
								const isLogin  = block.querySelector("select[name^='emails['][name$='[is_login]']");
								rows.push(["E-mail " + (i + 1) + " / Endereço",  normalizeValue(address)]);
								rows.push(["E-mail " + (i + 1) + " / Descrição", normalizeValue(desc)]);
								rows.push(["E-mail " + (i + 1) + " / Login?",    normalizeValue(isLogin)]);
							});
					}
				else
					{
						rows.push(["E-mails", "—"]);
					}

				rows.forEach((r) =>
					{
						const label = r[0];
						const val   = r[1] ? r[1] : "—";
						const tr    = document.createElement("tr");
						tr.innerHTML =
							"<td class='text-body-secondary'>" + escapeHtml(label) + "</td>" +
							"<td class='fw-semibold'>"          + escapeHtml(val)   + "</td>";
						reviewBody.appendChild(tr);
					});
			}

		// -----------------------------------------------------------------------
		// Remove dynamic rows with delegated clicks
		// -----------------------------------------------------------------------
		document.addEventListener("click", function (e)
			{
				const target = e.target;
				if (!(target instanceof HTMLElement))
					{
						return;
					}
				const removeButton = target.closest("[data-remove]");
				if (!removeButton)
					{
						return;
					}
				const block = removeButton.closest(".border");
				if (block)
					{
						block.remove();
					}
			});

		// -----------------------------------------------------------------------
		// Eventos de adição
		// -----------------------------------------------------------------------
		btnAddAddress.addEventListener("click", function ()
			{
				addAddressRow();
			});

		btnAddPhone.addEventListener("click", function ()
			{
				addPhoneRow();
			});

		btnAddEmail.addEventListener("click", function ()
			{
				addEmailRow();
			});

		// -----------------------------------------------------------------------
		// Botões de navegação por salto
		// -----------------------------------------------------------------------
		document.querySelectorAll("[data-step-jump]").forEach((btn) =>
			{
				btn.addEventListener("click", async function ()
					{
						const to = parseInt(btn.getAttribute("data-step-jump"), 10);

						if (to <= currentStep)
							{
								form.classList.remove("was-validated");
								showStep(to);
								return;
							}

						if (await validateCurrentStep())
							{
								form.classList.remove("was-validated");
								showStep(to);
								if (to === TOTAL_STEPS)
									{
										buildReview();
									}
							}
					});
			});

		// -----------------------------------------------------------------------
		// Botão Voltar
		// -----------------------------------------------------------------------
		btnBack.addEventListener("click", function ()
			{
				form.classList.remove("was-validated");
				showStep(Math.max(1, currentStep - 1));
				saveFormState();
			});

		// -----------------------------------------------------------------------
		// Botão Continuar
		// -----------------------------------------------------------------------
		btnNext.addEventListener("click", async function ()
			{
				if (!await validateCurrentStep())
					{
						return;
					}
				form.classList.remove("was-validated");
				const next = Math.min(TOTAL_STEPS, currentStep + 1);
				showStep(next);
				if (next === TOTAL_STEPS)
					{
						buildReview();
					}
				saveFormState();
			});

		// -----------------------------------------------------------------------
		// Submissão final
		// -----------------------------------------------------------------------
		form.addEventListener("submit", async function (e)
			{
				if (isSubmittingAfterAsyncValidation)
					{
						clearFormState();
						return;
					}

				e.preventDefault();
				e.stopPropagation();

				const documentIsAvailable = await validatePersonDocumentAvailability(true);
				if (!documentIsAvailable)
					{
						showStep(1);
						form.classList.add("was-validated");
						showAlert("Verifique o documento informado.");
						return;
					}

				const usernameIsAvailable = await validateUniqueFieldAvailability(userUsername, usernameUniqueConfig, true);
				if (!usernameIsAvailable)
					{
						showStep(2);
						form.classList.add("was-validated");
						showAlert("Verifique o nome de usuário informado.");
						return;
					}

				const phoneNumbersAreAvailable = await validateUniqueFieldList(getPhoneNumberFields(), phoneNumberUniqueConfig, true);
				const emailsAreAvailable = await validateUniqueFieldList(getEmailAddressFields(), emailUniqueConfig, true);
				if (!phoneNumbersAreAvailable || !emailsAreAvailable)
					{
						showStep(5);
						form.classList.add("was-validated");
						showAlert("Verifique os contatos informados.");
						return;
					}

				if (!form.checkValidity())
					{
						form.classList.add("was-validated");
						return;
					}

				// Formulário válido — submit nativo
				clearFormState();
				isSubmittingAfterAsyncValidation = true;
				if (typeof form.requestSubmit === "function")
					{
						form.requestSubmit();
					}
				else
					{
						form.submit();
					}
			});

		form.addEventListener("input", saveFormState);
		form.addEventListener("change", saveFormState);

		// -----------------------------------------------------------------------
		// Init — pré-adiciona uma linha de cada seção para o usuário já ver os campos
		// -----------------------------------------------------------------------
		addAddressRow();
		addPhoneRow();
		addEmailRow();
		showStep(1);
		restoreFormState();
		updateTenantPanels(false);
	})();

(function ()
	{
		const form = document.getElementById("signupForm");

		if (!form)
			{
				return;
			}

		const TOTAL_STEPS = 5;

		const steps = [
			{n: 1, title: "Etapa 1 de 5", subtitle: "Pessoa"},
			{n: 2, title: "Etapa 2 de 5", subtitle: "Usuário"},
			{n: 3, title: "Etapa 3 de 5", subtitle: "Endereços"},
			{n: 4, title: "Etapa 4 de 5", subtitle: "Contatos"},
			{n: 5, title: "Etapa 5 de 5", subtitle: "Revisão"}
		];

		let currentStep = 1;

		const stepTitle    = document.getElementById("stepTitle");
		const stepSubtitle = document.getElementById("stepSubtitle");
		const stepBadge    = document.getElementById("stepBadge");
		const progressBar  = document.getElementById("progressBar");

		const btnBack   = document.getElementById("btnBack");
		const btnNext   = document.getElementById("btnNext");
		const btnSubmit = document.getElementById("btnSubmit");

		const userPassword  = document.getElementById("userPassword");
		const userPassword2 = document.getElementById("userPassword2");

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

		const requiredIds = [
			"stepTitle", "stepSubtitle", "stepBadge", "progressBar",
			"btnBack", "btnNext", "btnSubmit",
			"addressesContainer", "phonesContainer", "emailsContainer",
			"addressTemplate", "phoneTemplate", "emailTemplate",
			"btnAddAddress", "btnAddPhone", "btnAddEmail",
			"reviewBody", "userPassword", "userPassword2"
		];

		const missing = requiredIds.filter((id) => !document.getElementById(id));
		if (missing.length)
			{
				console.warn("create.js: elementos ausentes no DOM:", missing);
				return;
			}

		// -----------------------------------------------------------------------
		// Contador de endereços
		// -----------------------------------------------------------------------
		let addressCounter = 0;

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
										onSelect(item);
										clearList();
										inputEl.value = item.label;
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
								textInput.value = data.name;
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
							}
						else
							{
								b.classList.remove("btn-primary");
								b.classList.add("btn-outline-secondary");
							}
					});

				btnBack.disabled = currentStep === 1;
				btnNext.classList.toggle("d-none", currentStep === TOTAL_STEPS);
				btnSubmit.classList.toggle("d-none", currentStep !== TOTAL_STEPS);
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
						const zipCode  = blocks[i].querySelector("input[name='addresses[][zip_code]']");
						const street   = blocks[i].querySelector("input[name='addresses[][street]']");
						const number   = blocks[i].querySelector("input[name='addresses[][number]']");
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

		function validateStep4()
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
						const num = phoneBlocks[i].querySelector("input[name='phones[][number]']");
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
						const addr = emailBlocks[i].querySelector("input[name='emails[][address]']");
						if (!addr || !addr.value.trim())
							{
								showAlert("E-mail " + (i + 1) + ": informe o endereço de e-mail.");
								return false;
							}
					}

				return true;
			}

		function validateCurrentStep()
			{
				// Validação customizada: senhas (etapa 2)
				if (currentStep === 2)
					{
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
				addressCounter++;
				const node = addressTemplate.content.cloneNode(true);

				// Captura referência ao bloco antes do appendChild (DocumentFragment esvazia após append)
				const block = node.querySelector(".border");

				addressesContainer.appendChild(node);

				// Inicializa o autocomplete de bairro no bloco recém-inserido
				if (block)
					{
						initAddressNeighborhoodAutocomplete(block);
					}
			}

		function addPhoneRow()
			{
				const node = phoneTemplate.content.cloneNode(true);
				phonesContainer.appendChild(node);
			}

		function addEmailRow()
			{
				const node = emailTemplate.content.cloneNode(true);
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

				// Endereços
				const addressBlocks = Array.from(
					addressesContainer.querySelectorAll("[data-remove='address']")
				).map((btn) => btn.closest(".border")).filter(Boolean);

				if (addressBlocks.length)
					{
						addressBlocks.forEach((block, i)  =>
							{
								const n       = i + 1;
								const zipCode = block.querySelector("input[name='addresses[][zip_code]']");
								const street  = block.querySelector("input[name='addresses[][street]']");
								const number  = block.querySelector("input[name='addresses[][number]']");
								const obs        = block.querySelector("input[name='addresses[][observations]']");
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
								const number = block.querySelector("input[name='phones[][number]']");
								const notes  = block.querySelector("input[name='phones[][notes]']");
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
								const address  = block.querySelector("input[name='emails[][address]']");
								const desc     = block.querySelector("input[name='emails[][description]']");
								const isLogin  = block.querySelector("select[name='emails[][is_login]']");
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
		// Eventos de remoção (delegação)
		// -----------------------------------------------------------------------
		document.addEventListener("click", function (e)
			{
				const target = e.target;
				if (!(target instanceof HTMLElement))
					{
						return;
					}
				const type = target.getAttribute("data-remove");
				if (!type)
					{
						return;
					}
				const block = target.closest(".border");
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
				btn.addEventListener("click", function ()
					{
						const to = parseInt(btn.getAttribute("data-step-jump"), 10);

						if (to <= currentStep)
							{
								form.classList.remove("was-validated");
								showStep(to);
								return;
							}

						if (validateCurrentStep())
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
			});

		// -----------------------------------------------------------------------
		// Botão Continuar
		// -----------------------------------------------------------------------
		btnNext.addEventListener("click", function ()
			{
				if (!validateCurrentStep())
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
			});

		// -----------------------------------------------------------------------
		// Submissão final
		// -----------------------------------------------------------------------
		form.addEventListener("submit", function (e)
			{
				if (!form.checkValidity())
					{
						e.preventDefault();
						e.stopPropagation();
						form.classList.add("was-validated");
						return;
					}
				// Formulário válido — submit nativo
			});

		// -----------------------------------------------------------------------
		// Máscara CPF / CNPJ
		// -----------------------------------------------------------------------
		function applyCpfCnpjMask(value)
			{
				const digits = value.replace(/\D/g, "").slice(0, 14);
				if (digits.length <= 11)
					{
						return digits
							.replace(/(\d{3})(\d)/, "$1.$2")
							.replace(/(\d{3})(\d)/, "$1.$2")
							.replace(/(\d{3})(\d{1,2})$/, "$1-$2");
					}
				return digits
					.replace(/(\d{2})(\d)/, "$1.$2")
					.replace(/(\d{3})(\d)/, "$1.$2")
					.replace(/(\d{3})(\d)/, "$1/$2")
					.replace(/(\d{4})(\d{1,2})$/, "$1-$2");
			}

		document.querySelectorAll(".mask-cpf-cnpj").forEach(function (el)
			{
				el.addEventListener("input", function ()
					{
						const pos  = el.selectionStart;
						const prev = el.value;
						el.value   = applyCpfCnpjMask(el.value);
						const diff = el.value.length - prev.length;
						el.setSelectionRange(pos + diff, pos + diff);
					});
			});

		// -----------------------------------------------------------------------
		// Máscara CEP (aplicada em linhas dinâmicas via delegação)
		// -----------------------------------------------------------------------
		function applyCepMask(value)
			{
				const digits = value.replace(/\D/g, "").slice(0, 8);
				return digits.replace(/(\d{5})(\d{1,3})$/, "$1-$2");
			}

		document.addEventListener("input", function (e)
			{
				if (e.target && e.target.classList.contains("mask-cep"))
					{
						const el   = e.target;
						const pos  = el.selectionStart;
						const prev = el.value;
						el.value   = applyCepMask(el.value);
						const diff = el.value.length - prev.length;
						el.setSelectionRange(pos + diff, pos + diff);
					}
			});

		// -----------------------------------------------------------------------
		// Init — pré-adiciona uma linha de cada seção para o usuário já ver os campos
		// -----------------------------------------------------------------------
		addAddressRow();
		addPhoneRow();
		addEmailRow();
		showStep(1);
	})();

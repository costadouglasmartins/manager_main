(function ()
	{
		const form = document.getElementById("signupForm");

		if (!form)
			{
				return;
			}

		const TOTAL_STEPS = 4;

		const steps = [
			{n: 1, title: "Etapa 1 de 4", subtitle: "Pessoa"},
			{n: 2, title: "Etapa 2 de 4", subtitle: "Usuário"},
			{n: 3, title: "Etapa 3 de 4", subtitle: "Contatos"},
			{n: 4, title: "Etapa 4 de 4", subtitle: "Revisão"}
		];

		let currentStep = 1;

		const stepTitle = document.getElementById("stepTitle");
		const stepSubtitle = document.getElementById("stepSubtitle");
		const stepBadge = document.getElementById("stepBadge");
		const progressBar = document.getElementById("progressBar");

		const btnBack = document.getElementById("btnBack");
		const btnNext = document.getElementById("btnNext");
		const btnSubmit = document.getElementById("btnSubmit");

		const userPassword = document.getElementById("userPassword");
		const userPassword2 = document.getElementById("userPassword2");

		const phonesContainer = document.getElementById("phonesContainer");
		const emailsContainer = document.getElementById("emailsContainer");
		const phoneTemplate = document.getElementById("phoneTemplate");
		const emailTemplate = document.getElementById("emailTemplate");

		const btnAddPhone = document.getElementById("btnAddPhone");
		const btnAddEmail = document.getElementById("btnAddEmail");

		const reviewBody = document.getElementById("reviewBody");

		const requiredIds = [
			"stepTitle", "stepSubtitle", "stepBadge", "progressBar",
			"btnBack", "btnNext", "btnSubmit",
			"phonesContainer", "emailsContainer", "phoneTemplate", "emailTemplate",
			"btnAddPhone", "btnAddEmail", "reviewBody",
			"userPassword", "userPassword2"
		];

		const missing = requiredIds.filter((id) => !document.getElementById(id));
		if (missing.length)
			{
				console.warn("create.js: elementos ausentes no DOM:", missing);
				return;
			}

		function showStep(step)
			{
				currentStep = step;

				form.querySelectorAll("section[data-step]").forEach((sec) => {
					const n = parseInt(sec.getAttribute("data-step"), 10);
					sec.classList.toggle("d-none", n !== currentStep);
				});

				const meta = steps.find((s) => s.n === currentStep);
				stepTitle.textContent = meta.title;
				stepSubtitle.textContent = meta.subtitle;
				stepBadge.textContent = currentStep + "/" + TOTAL_STEPS;

				const pct = Math.round((currentStep / TOTAL_STEPS) * 100);
				progressBar.style.width = pct + "%";

				document.querySelectorAll("[data-step-jump]").forEach((b) => {
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

		function validateCurrentStep()
			{
				// Custom validation: password match (Step 2)
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

				const requiredControls = getCurrentStepRequiredControls();
				let ok = true;

				requiredControls.forEach((el) => {
					if (!el.checkValidity())
						{
							ok = false;
						}
				});

				form.classList.add("was-validated");
				return ok;
			}

		function escapeHtml(str)
			{
				return String(str)
				.replaceAll("&", "&amp;")
				.replaceAll("<", "&lt;")
				.replaceAll(">", "&gt;")
				.replaceAll('"', "&quot;")
				.replaceAll("'", "&#039;");
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
						return el.value ? (el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : el.value) : "";
					}
				return (el.value || "").trim();
			}

		function buildReview()
			{
				reviewBody.innerHTML = "";

				const rows = [];

				// Pessoa
				rows.push(["Pessoa / Nome", normalizeValue(document.getElementById("personName"))]);
				rows.push(["Pessoa / Nome fantasia", normalizeValue(document.getElementById("personFantasy"))]);
				rows.push(["Pessoa / Tipo", normalizeValue(document.getElementById("personType"))]);
				rows.push(["Pessoa / Documento", normalizeValue(document.getElementById("personDocument"))]);
				rows.push(["Pessoa / Nascimento", normalizeValue(document.getElementById("personBirthdate"))]);

				// Usuário
				rows.push(["Usuário / Username", normalizeValue(document.getElementById("userUsername"))]);
				rows.push(["Usuário / Tipo de acesso", normalizeValue(document.getElementById("userType"))]);

				// Telefones
				const phoneBlocks = Array.from(phonesContainer.querySelectorAll("[data-remove='phone']")).map((btn) => btn.closest(".border"));
				if (phoneBlocks.length)
					{
						phoneBlocks.forEach((block, idx) => {
							const number = block.querySelector("input[name='phones[][number]']");
							const notes = block.querySelector("input[name='phones[][notes]']");
							rows.push(["Telefone " + (idx + 1) + " / Número", normalizeValue(number)]);
							rows.push(["Telefone " + (idx + 1) + " / Observações", normalizeValue(notes)]);
						});
					}
				else
					{
						rows.push(["Telefones", "—"]);
					}

				// E-mails
				const emailBlocks = Array.from(emailsContainer.querySelectorAll("[data-remove='email']")).map((btn) => btn.closest(".border"));
				if (emailBlocks.length)
					{
						emailBlocks.forEach((block, idx) => {
							const address = block.querySelector("input[name='emails[][address]']");
							const desc = block.querySelector("input[name='emails[][description]']");
							rows.push(["E-mail " + (idx + 1) + " / Endereço", normalizeValue(address)]);
							rows.push(["E-mail " + (idx + 1) + " / Descrição", normalizeValue(desc)]);
						});
					}
				else
					{
						rows.push(["E-mails", "—"]);
					}

				rows.forEach((r) => {
					const label = r[0];
					const val = r[1] ? r[1] : "—";
					const tr = document.createElement("tr");
					tr.innerHTML =
					"<td class='text-body-secondary'>" + escapeHtml(label) + "</td>" +
					"<td class='fw-semibold'>" + escapeHtml(val) + "</td>";
					reviewBody.appendChild(tr);
				});
			}

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

		btnAddPhone.addEventListener("click", function ()
			{
				addPhoneRow();
			});

		btnAddEmail.addEventListener("click", function ()
			{
				addEmailRow();
			});

		document.querySelectorAll("[data-step-jump]").forEach((btn) => {
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

		btnBack.addEventListener("click", function ()
			{
				form.classList.remove("was-validated");
				showStep(Math.max(1, currentStep - 1));
			});

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

		form.addEventListener("submit", function (e)
			{
				if (!form.checkValidity())
					{
						e.preventDefault();
						e.stopPropagation();
						form.classList.add("was-validated");
						return;
					}
				// Form is valid — allow native submit
			});

		showStep(1);
	})();

(function ()
	{
		const app = document.getElementById("documentToolApp");

		if (!app)
			{
				return;
			}

		const generatorForm = document.getElementById("documentGeneratorForm");
		const generatorType = document.getElementById("generatorType");
		const generatorFormatted = document.getElementById("generatorFormatted");
		const generatedDocument = document.getElementById("generatedDocument");
		const copyGeneratedDocument = document.getElementById("copyGeneratedDocument");
		const clearGeneratedDocument = document.getElementById("clearGeneratedDocument");
		const cnpjNewOptions = document.getElementById("cnpjNewOptions");
		const cnpjNewBranches = document.getElementById("cnpjNewBranches");
		const decreaseBranches = document.getElementById("decreaseBranches");
		const increaseBranches = document.getElementById("increaseBranches");
		const cnpjNewGeneratedList = document.getElementById("cnpjNewGeneratedList");

		const validatorForm = document.getElementById("documentValidatorForm");
		const validatorType = document.getElementById("validatorType");
		const validatorDocument = document.getElementById("validatorDocument");
		const validationResult = document.getElementById("validationResult");
		const clearValidationDocument = document.getElementById("clearValidationDocument");
		const MIN_BRANCHES = 0;
		const MAX_BRANCHES = 99;

		const documentTypes = {
			cpf: {
				label: "CPF",
				placeholder: "000.000.000-00",
				generate: generateCpf,
				validate: validateCpf,
				format: formatCpf
			},
			cnpj_current: {
				label: "CNPJ atual",
				placeholder: "00.000.000/0000-00",
				generate: generateCnpj,
				validate: validateCnpj,
				format: formatCnpj
			},
			cnpj_new: {
				label: "CNPJ novo",
				placeholder: "AA.AAA.AAA/AAAA-00",
				generate: generateCnpjAlphanumeric,
				validate: validateCnpjAlphanumeric,
				format: formatCnpjAlphanumeric
			}
		};

		function formatCpf(value)
			{
				const digits = onlyDigits(value);
				if (digits.length !== 11)
					{
						return value;
					}

				return digits.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
			}

		function formatCnpj(value)
			{
				const digits = onlyDigits(value);
				if (digits.length !== 14)
					{
						return value;
					}

				return digits.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5");
			}

		function formatCnpjAlphanumeric(value)
			{
				const normalized = String(value || "").toUpperCase().replace(/[^A-Z0-9]/g, "");
				if (normalized.length !== 14)
					{
						return value;
					}

				return normalized.replace(/([A-Z0-9]{2})([A-Z0-9]{3})([A-Z0-9]{3})([A-Z0-9]{4})([A-Z0-9]{2})/, "$1.$2.$3/$4-$5");
			}

		function getSelectedType()
			{
				return documentTypes[generatorType.value] ? generatorType.value : "cpf";
			}

		function isCnpjNewGenerator()
			{
				return getSelectedType() === "cnpj_new";
			}

		function setCnpjNewOptionsVisibility()
			{
				if (!cnpjNewOptions)
					{
						return;
					}

				cnpjNewOptions.classList.toggle("d-none", !isCnpjNewGenerator());
			}

		function normalizeBranchQuantity(value)
			{
				const parsed = parseInt(String(value || "0"), 10);
				if (Number.isNaN(parsed))
					{
						return MIN_BRANCHES;
					}

				return Math.min(MAX_BRANCHES, Math.max(MIN_BRANCHES, parsed));
			}

		function setBranchQuantity(value)
			{
				if (!cnpjNewBranches)
					{
						return MIN_BRANCHES;
					}

				const normalized = normalizeBranchQuantity(value);
				cnpjNewBranches.value = String(normalized);
				return normalized;
			}

		function generateCnpjAlphanumericRoot()
			{
				for (let attempt = 0; attempt < 100; attempt += 1)
					{
						let root = "";
						for (let i = 0; i < 8; i += 1)
							{
								root += randomAlphanumericChar();
							}

						if (!areAllCharsEqual(root) && /[A-Z]/.test(root))
							{
								return root;
							}
					}

				return "A1B2C3D4";
			}

		function generateBranchCode(usedBranchCodes)
			{
				for (let attempt = 0; attempt < 200; attempt += 1)
					{
						let branchCode = "";
						for (let i = 0; i < 4; i += 1)
							{
								branchCode += randomAlphanumericChar();
							}

						if (branchCode !== "0001" && /[A-Z]/.test(branchCode) && !usedBranchCodes.has(branchCode))
							{
								usedBranchCodes.add(branchCode);
								return branchCode;
							}
					}

				const fallback = "A" + String(usedBranchCodes.size).padStart(3, "0").slice(-3);
				usedBranchCodes.add(fallback);
				return fallback;
			}

		function buildCnpjAlphanumeric(root, branchCode, formatted)
			{
				const base = root + branchCode;
				const digit1 = calculateCnpjAlphanumericDigit(base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
				const digit2 = calculateCnpjAlphanumericDigit(base + String(digit1), [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
				const cnpj = base + String(digit1) + String(digit2);

				return formatted ? formatCnpjAlphanumeric(cnpj) : cnpj;
			}

		function generateCnpjAlphanumericFamily(branchQuantity, formatted)
			{
				const root = generateCnpjAlphanumericRoot();
				const usedBranchCodes = new Set(["0001"]);
				const documents = [{
					label: "MATRIZ 1",
					value: buildCnpjAlphanumeric(root, "0001", formatted)
				}];

				for (let i = 1; i <= branchQuantity; i += 1)
					{
						documents.push({
							label: "FILIAL " + i,
							value: buildCnpjAlphanumeric(root, generateBranchCode(usedBranchCodes), formatted)
						});
					}

				return documents;
			}

		function renderCnpjNewGeneratedList(documents)
			{
				if (!cnpjNewGeneratedList)
					{
						return;
					}

				cnpjNewGeneratedList.innerHTML = "";
				cnpjNewGeneratedList.classList.toggle("d-none", !documents.length);

				documents.forEach(function (documentItem)
					{
						const row = document.createElement("div");
						row.className = "document-branch-row";

						const value = document.createElement("span");
						value.className = "document-branch-value";
						value.textContent = documentItem.value;

						const actions = document.createElement("div");
						actions.className = "document-branch-actions";

						const label = document.createElement("span");
						label.className = documentItem.label.toLowerCase().indexOf("matriz") === 0
							? "badge text-bg-primary document-branch-label"
							: "badge text-bg-secondary document-branch-label";
						label.textContent = documentItem.label;

						const copyButton = document.createElement("button");
						copyButton.className = "btn btn-outline-primary btn-sm document-branch-copy";
						copyButton.type = "button";
						copyButton.dataset.copyDocument = documentItem.value;
						copyButton.title = "Copiar " + documentItem.label.toLowerCase();
						copyButton.setAttribute("aria-label", "Copiar " + documentItem.label.toLowerCase());
						copyButton.innerHTML = '<i class="bi bi-clipboard"></i>';

						actions.appendChild(label);
						actions.appendChild(copyButton);
						row.appendChild(value);
						row.appendChild(actions);
						cnpjNewGeneratedList.appendChild(row);
					});
			}

		function setValidationResult(type, title, detail)
			{
				validationResult.className = "alert mb-0 document-tool-result alert-" + type;
				validationResult.innerHTML = "<strong>" + title + "</strong><small>" + detail + "</small>";
			}

		function resolveValidationType(value)
			{
				if (validatorType.value !== "auto")
					{
						return documentTypes[validatorType.value] ? validatorType.value : null;
					}

				const digits = onlyDigits(value);
				const alphanumeric = String(value || "").toUpperCase().replace(/[^A-Z0-9]/g, "");

				if (digits.length === 11 && alphanumeric === digits)
					{
						return "cpf";
					}

				if (digits.length === 14 && alphanumeric === digits)
					{
						return "cnpj_current";
					}

				if (alphanumeric.length === 14)
					{
						return "cnpj_new";
					}

				return null;
			}

		function validateDocument()
			{
				const value = validatorDocument.value.trim();

				if (value === "")
					{
						setValidationResult("secondary", "Informe um documento para validar.", "CPF, CNPJ atual e CNPJ novo são aceitos.");
						return;
					}

				const typeKey = resolveValidationType(value);
				if (!typeKey)
					{
						setValidationResult("warning", "Tipo não identificado.", "Selecione o tipo manualmente ou confira a quantidade de caracteres.");
						return;
					}

				const type = documentTypes[typeKey];
				const isValid = type.validate(value);
				const formatted = type.format(value);

				if (isValid)
					{
						setValidationResult("success", type.label + " válido.", "Formato normalizado: " + formatted);
						return;
					}

				setValidationResult("danger", type.label + " inválido.", "Revise o número informado e tente novamente.");
			}

		function generateDocument()
			{
				const type = documentTypes[getSelectedType()];

				if (isCnpjNewGenerator())
					{
						const branchQuantity = setBranchQuantity(cnpjNewBranches ? cnpjNewBranches.value : 0);
						const documents = generateCnpjAlphanumericFamily(branchQuantity, generatorFormatted.checked);
						generatedDocument.value = documents[0].value;
						renderCnpjNewGeneratedList(branchQuantity > 1 ? documents : []);
						generatedDocument.focus();
						generatedDocument.select();
						return;
					}

				generatedDocument.value = type.generate(generatorFormatted.checked);
				renderCnpjNewGeneratedList([]);
				generatedDocument.focus();
				generatedDocument.select();
			}

		function copyValue(input)
			{
				const value = input.value.trim();

				if (value === "")
					{
						return;
					}

				if (navigator.clipboard && navigator.clipboard.writeText)
					{
						navigator.clipboard.writeText(value).catch(function ()
							{
								input.select();
								document.execCommand("copy");
							});
						return;
					}

				input.select();
				document.execCommand("copy");
			}

		generatorForm.addEventListener("submit", function (event)
			{
				event.preventDefault();
				generateDocument();
			});

		clearGeneratedDocument.addEventListener("click", function ()
			{
				generatedDocument.value = "";
				renderCnpjNewGeneratedList([]);
				generatorType.focus();
			});

		copyGeneratedDocument.addEventListener("click", function ()
			{
				copyValue(generatedDocument);
			});

		generatorType.addEventListener("change", function ()
			{
				setCnpjNewOptionsVisibility();
				renderCnpjNewGeneratedList([]);
				generatedDocument.value = "";
			});

		if (cnpjNewBranches)
			{
				cnpjNewBranches.addEventListener("input", function ()
					{
						setBranchQuantity(cnpjNewBranches.value);
					});
			}

		if (decreaseBranches && cnpjNewBranches)
			{
				decreaseBranches.addEventListener("click", function ()
					{
						setBranchQuantity(setBranchQuantity(cnpjNewBranches.value) - 1);
					});
			}

		if (increaseBranches && cnpjNewBranches)
			{
				increaseBranches.addEventListener("click", function ()
					{
						setBranchQuantity(setBranchQuantity(cnpjNewBranches.value) + 1);
					});
			}

		if (cnpjNewGeneratedList)
			{
				cnpjNewGeneratedList.addEventListener("click", function (event)
					{
						const copyButton = event.target.closest("[data-copy-document]");
						if (!copyButton)
							{
								return;
							}

						const temporaryInput = document.createElement("input");
						temporaryInput.value = copyButton.dataset.copyDocument || "";
						document.body.appendChild(temporaryInput);
						copyValue(temporaryInput);
						document.body.removeChild(temporaryInput);
					});
			}

		validatorForm.addEventListener("submit", function (event)
			{
				event.preventDefault();
				validateDocument();
			});

		validatorDocument.addEventListener("input", function ()
			{
				validatorDocument.value = validatorDocument.value.toUpperCase();
			});

		validatorType.addEventListener("change", function ()
			{
				const type = validatorType.value === "auto" ? documentTypes.cpf : documentTypes[validatorType.value];
				validatorDocument.placeholder = type ? type.placeholder : documentTypes.cpf.placeholder;
			});

		clearValidationDocument.addEventListener("click", function ()
			{
				validatorDocument.value = "";
				validatorType.value = "auto";
				validatorDocument.placeholder = documentTypes.cpf.placeholder;
				setValidationResult("secondary", "Informe um documento para validar.", "CPF, CNPJ atual e CNPJ novo são aceitos.");
				validatorDocument.focus();
			});

		setCnpjNewOptionsVisibility();
		setBranchQuantity(cnpjNewBranches ? cnpjNewBranches.value : 1);
		generateDocument();
	})();

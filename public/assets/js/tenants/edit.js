(function ()
	{
	const form = document.getElementById("tenantEditForm");
	if (!form)
		{
		return;
		}

	const addLinkedPerson = document.getElementById("tenantAddLinkedPerson");
	const linkedPersonFields = Array.from(document.querySelectorAll("[data-linked-person-field]"));
	const linkedPassword = document.getElementById("linkedUserPassword");
	const linkedPasswordConfirm = document.getElementById("linkedUserPasswordConfirm");

	function syncLinkedPersonFields()
		{
		const enabled = !!(addLinkedPerson && addLinkedPerson.checked);

		linkedPersonFields.forEach(function (field)
			{
			field.disabled = !enabled;
			if (field.dataset.required === "1")
				{
				field.required = enabled;
				}
			});

		validateLinkedPassword();
		}

	function validateLinkedPassword()
		{
		if (!linkedPassword || !linkedPasswordConfirm || linkedPassword.disabled)
			{
			if (linkedPasswordConfirm)
				{
				linkedPasswordConfirm.setCustomValidity("");
				}
			return;
			}

		if (linkedPassword.value !== "" && linkedPasswordConfirm.value !== "" && linkedPassword.value !== linkedPasswordConfirm.value)
			{
			linkedPasswordConfirm.setCustomValidity("A confirmação de senha não confere.");
			return;
			}

		linkedPasswordConfirm.setCustomValidity("");
		}

	if (addLinkedPerson)
		{
		addLinkedPerson.addEventListener("change", syncLinkedPersonFields);
		syncLinkedPersonFields();
		}

	if (linkedPassword)
		{
		linkedPassword.addEventListener("input", validateLinkedPassword);
		}

	if (linkedPasswordConfirm)
		{
		linkedPasswordConfirm.addEventListener("input", validateLinkedPassword);
		}

	document.querySelectorAll("[data-tenant-user-remove]").forEach(function (checkbox)
		{
		const row = checkbox.closest("[data-tenant-user-row]");
		checkbox.addEventListener("change", function ()
			{
			if (row)
				{
				row.classList.toggle("table-danger", checkbox.checked);
				}
			});
		});

	form.addEventListener("submit", function (event)
		{
		validateLinkedPassword();

		if (!form.checkValidity())
			{
			event.preventDefault();
			event.stopPropagation();
			}

		form.classList.add("was-validated");
		});
	})();

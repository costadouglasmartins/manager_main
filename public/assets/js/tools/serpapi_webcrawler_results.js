(function ()
	{
		"use strict";

		const root = document.getElementById("serpApiWebCrawlerResultsApp");
		if (!root)
			{
				return;
			}

		const selectAll = document.getElementById("serpApiSelectAllResults");
		const bulkForm = document.getElementById("serpApiBulkDeleteForm");
		const bulkButton = document.getElementById("serpApiBulkDeleteButton");
		const selectedCount = document.getElementById("serpApiSelectedCount");
		const checkboxes = Array.prototype.slice.call(document.querySelectorAll(".serpapi-result-checkbox"));
		const deleteModal = document.getElementById("serpApiDeleteConfirmModal");
		const deleteModalTitle = document.getElementById("serpApiDeleteConfirmTitle");
		const deleteModalMessage = document.getElementById("serpApiDeleteConfirmMessage");
		const deleteModalConfirmButton = document.getElementById("serpApiDeleteConfirmButton");

		let pendingDeleteForm = null;

		function selectedCheckboxes()
			{
				return checkboxes.filter(function (checkbox)
					{
						return checkbox.checked;
					});
			}

		function syncSelectionState()
			{
				const selectedTotal = selectedCheckboxes().length;
				const hasRows = checkboxes.length > 0;

				if (selectedCount)
					{
						selectedCount.textContent = String(selectedTotal);
					}

				if (bulkButton)
					{
						bulkButton.disabled = selectedTotal === 0;
					}

				if (selectAll)
					{
						selectAll.disabled = !hasRows;
						selectAll.checked = hasRows && selectedTotal === checkboxes.length;
						selectAll.indeterminate = selectedTotal > 0 && selectedTotal < checkboxes.length;
					}
			}

		function submitPendingDelete()
			{
				if (!pendingDeleteForm)
					{
						return;
					}

				if (deleteModalConfirmButton)
					{
						deleteModalConfirmButton.disabled = true;
						deleteModalConfirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Excluindo...';
					}

				pendingDeleteForm.submit();
			}

		function showDeleteModal(title, message, form)
			{
				pendingDeleteForm = form;

				if (deleteModalTitle)
					{
						deleteModalTitle.textContent = title;
					}

				if (deleteModalMessage)
					{
						deleteModalMessage.textContent = message;
					}

				if (deleteModalConfirmButton)
					{
						deleteModalConfirmButton.disabled = false;
						deleteModalConfirmButton.innerHTML = '<i class="bi bi-trash me-1" aria-hidden="true"></i>Excluir';
					}

				if (deleteModal && window.bootstrap && window.bootstrap.Modal)
					{
						window.bootstrap.Modal.getOrCreateInstance(deleteModal).show();
					}
			}

		if (selectAll)
			{
				selectAll.addEventListener("change", function ()
					{
						checkboxes.forEach(function (checkbox)
							{
								checkbox.checked = selectAll.checked;
							});
						syncSelectionState();
					});
			}

		checkboxes.forEach(function (checkbox)
			{
				checkbox.addEventListener("change", syncSelectionState);
			});

		if (bulkForm)
			{
				bulkForm.addEventListener("submit", function (event)
					{
						const selectedTotal = selectedCheckboxes().length;
						event.preventDefault();

						if (selectedTotal === 0)
							{
								return;
							}

						showDeleteModal(
							"Excluir resultados selecionados",
							"Tem certeza que deseja excluir " + selectedTotal + " resultado(s) selecionado(s)? Esta ação não poderá ser desfeita.",
							bulkForm
						);
					});
			}

		document.querySelectorAll(".serpapi-result-delete-form").forEach(function (form)
			{
				form.addEventListener("submit", function (event)
					{
						const submitter = event.submitter || form.querySelector("[data-serpapi-delete-single]");
						const resultId = submitter ? submitter.getAttribute("data-result-id") : "";
						const suffix = resultId ? " #" + resultId : "";

						event.preventDefault();
						showDeleteModal(
							"Excluir resultado",
							"Tem certeza que deseja excluir o resultado" + suffix + "? Esta ação não poderá ser desfeita.",
							form
						);
					});
			});

		if (deleteModalConfirmButton)
			{
				deleteModalConfirmButton.addEventListener("click", submitPendingDelete);
			}

		if (deleteModal)
			{
				deleteModal.addEventListener("hidden.bs.modal", function ()
					{
						pendingDeleteForm = null;

						if (deleteModalConfirmButton)
							{
								deleteModalConfirmButton.disabled = false;
								deleteModalConfirmButton.innerHTML = '<i class="bi bi-trash me-1" aria-hidden="true"></i>Excluir';
							}
					});
			}

		syncSelectionState();
	})();

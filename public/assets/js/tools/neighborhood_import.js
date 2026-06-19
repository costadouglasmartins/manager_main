document.addEventListener("DOMContentLoaded", function () {
	const input = document.getElementById("xlsxFile");
	const fileName = document.getElementById("xlsxFileName");
	const app = document.getElementById("neighborhoodImportApp");
	const confirmButton = document.getElementById("neighborhoodImportConfirmButton");
	const statusText = document.getElementById("neighborhoodImportStatusText");
	const liveStatus = document.getElementById("neighborhoodImportLiveStatus");
	const liveBody = document.getElementById("neighborhoodImportLiveBody");
	const processedCount = document.getElementById("neighborhoodImportProcessedCount");
	const importedCount = document.getElementById("neighborhoodImportImportedCount");
	const createdCitiesCount = document.getElementById("neighborhoodImportCreatedCitiesCount");
	const createdNeighborhoodsCount = document.getElementById("neighborhoodImportCreatedNeighborhoodsCount");
	const failedCount = document.getElementById("neighborhoodImportFailedCount");
	let eventSource = null;

	if (input && fileName)
		{
		input.addEventListener("change", function () {
			const selectedFile = input.files && input.files[0] ? input.files[0].name : "";
			fileName.textContent = selectedFile ? "Arquivo selecionado: " + selectedFile : "";
			fileName.classList.toggle("d-none", !selectedFile);
		});
		}

	if (!app || !confirmButton)
		{
		return;
		}

	function setStats(payload)
		{
		processedCount.textContent = String(payload.processed || 0);
		importedCount.textContent = String(payload.imported || 0);
		createdCitiesCount.textContent = String(payload.cities_created || 0);
		createdNeighborhoodsCount.textContent = String(payload.neighborhoods_created || 0);
		failedCount.textContent = String(payload.failed || 0);
		}

	function clearLiveBody()
		{
		if (!liveBody)
			{
			return;
			}

		liveBody.innerHTML = "";
		}

	function appendLiveRow(payload)
		{
		if (!liveBody)
			{
			return;
			}

		const row = document.createElement("tr");
		const isError = !!payload.error;
		const status = isError ? "Falha" : "Importado";
		const action = payload.city_created ? "Cidade criada e bairro salvo" : "Bairro salvo";

		row.innerHTML = ""
			+ "<td>" + String(payload.line || "") + "</td>"
			+ "<td>" + escapeHtml(payload.state_acronym || "") + "</td>"
			+ "<td>" + escapeHtml(payload.city_name || "") + "</td>"
			+ "<td>" + escapeHtml(payload.neighborhood_name || "") + "</td>"
			+ "<td><span class=\"badge " + (isError ? "text-bg-danger" : "text-bg-success") + "\">" + status + "</span></td>"
			+ "<td>" + escapeHtml(isError ? (payload.message || "Falha ao importar.") : (payload.message || action)) + "</td>";

		liveBody.prepend(row);
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

	function finishStream()
		{
		if (eventSource)
			{
			eventSource.close();
			eventSource = null;
			}

		confirmButton.disabled = true;
		confirmButton.classList.add("disabled");
		confirmButton.innerHTML = '<i class="bi bi-check2-all me-1"></i>Importação concluída';
		confirmButton.removeAttribute("id");
		}

	confirmButton.addEventListener("click", function () {
		if (eventSource)
			{
			return;
			}

		const streamUrl = app.dataset.streamUrl || "";
		if (!streamUrl)
			{
			return;
			}

		clearLiveBody();
		setStats({processed: 0, imported: 0, cities_created: 0, neighborhoods_created: 0, failed: 0});
		confirmButton.disabled = true;
		confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Importando';
		statusText.textContent = "Iniciando importação...";
		liveStatus.textContent = "Conectando";

		eventSource = new EventSource(streamUrl);

		eventSource.addEventListener("start", function (event) {
			const payload = JSON.parse(event.data || "{}");
			setStats(payload);
			statusText.textContent = "Importação iniciada para " + String(payload.total || 0) + " registro(s).";
			liveStatus.textContent = "Em execução";
		});

		eventSource.addEventListener("progress", function (event) {
			const payload = JSON.parse(event.data || "{}");
			setStats(payload);
			liveStatus.textContent = "Processando linha " + String(payload.line || "-");
			appendLiveRow(payload);
		});

		eventSource.addEventListener("done", function (event) {
			const payload = JSON.parse(event.data || "{}");
			setStats(payload);
			statusText.textContent = "Importação finalizada.";
			liveStatus.textContent = "Concluído";
			finishStream();
		});

		eventSource.addEventListener("error", function () {
			statusText.textContent = "A conexão da importação foi interrompida.";
			liveStatus.textContent = "Interrompido";
			confirmButton.disabled = false;
			confirmButton.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Tentar novamente';

			if (eventSource)
				{
				eventSource.close();
				eventSource = null;
				}
		});
	});
});

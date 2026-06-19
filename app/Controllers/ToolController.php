<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\NeighborhoodImport;
use App\Models\State;
use App\Services\EmailMxValidatorService;
use App\Services\ZipCodeCrawlerService;
use App\Services\ZipCodeNeighborhoodService;
use App\Services\ZipCodeSearchService;

class ToolController extends Controller
	{
	/**
	 * Render the document tool page.
	 * @return void
	 **/
	public function documents(): void
		{
		$this->requireAuth();

		$this->view('tools/documents', [
			'title' => 'Gerador e validador de documentos',
		]);
		}

	/**
	 * Render the email validation page or return validation data.
	 * @return void
	 **/
	public function email_validation(): void
		{
		$this->requireAuth();

		if ($_SERVER['REQUEST_METHOD'] === 'POST' || $this->wantsJson())
			{
			$validator = new EmailMxValidatorService();
			$result = $validator->validate($this->readEmailValidationInput());
			$status = !empty($result['input_valid']) ? 200 : 422;

			$this->json(['data' => $result], $status);
			}

		$this->view('tools/email_validation', [
			'title' => 'Validador de e-mail',
			]);
		}

	/**
	 * Render the zip code search page.
	 * @return void
	 **/
	public function zipCodeSearch(): void
		{
		$this->requireAuth();

		$this->view('tools/zip_code_search', [
			'title' => 'Pesquisa de CEP',
		]);
		}

	/**
	 * Render the zip code neighborhood comparison page.
	 * @return void
	 **/
	public function zipCodeNeighborhoodSearch(): void
		{
		$this->requireAuth();

		$this->view('tools/zip_code_neighborhood', [
			'title' => 'Bairro e cidade por CEP',
		]);
		}

	/**
	 * Render the zip code crawler page.
	 * @return void
	 **/
	public function zipCodeCrawler(): void
		{
		$this->requireAuth();

		$this->view('tools/zip_code_crawler', [
			'title' => 'Webcrawler de CEPs',
			'zipCodeStateOptions' => $this->readZipCodeCrawlerStateOptions(),
		]);
		}

	/**
	 * Render the neighborhood XLSX import page and process uploads.
	 * @return void
	 **/
	public function neighborhoodImport(): void
		{
		$this->requireAuth();

		$previewResult = null;
		$confirmationResult = null;
		$selectedStateAcronym = '';

		if ($_SERVER['REQUEST_METHOD'] === 'POST')
			{
			try
				{
				$model = new NeighborhoodImport();
				$uploadedFile = $this->readNeighborhoodImportUpload();
				$selectedStateAcronym = $this->readNeighborhoodImportStateAcronym();
				$previewResult = $model->analyzeXlsx($uploadedFile['tmp_name'], $selectedStateAcronym);
				$this->storeNeighborhoodImportPreview($previewResult);
				}
			catch (\RuntimeException $exception)
				{
				$this->clearNeighborhoodImportPreview();
				$selectedStateAcronym = $this->readNeighborhoodImportStateAcronym(false);
				$previewResult = [
					'success' => false,
					'message' => $exception->getMessage(),
					'summary' => [],
					'rows_to_import' => [],
					'rows_not_imported' => [],
					'selected_state_acronym' => $selectedStateAcronym,
				];
				}
			}
		else
			{
			$previewResult = $this->readStoredNeighborhoodImportPreview();
			if (is_array($previewResult))
				{
				$selectedStateAcronym = (string) ($previewResult['selected_state_acronym'] ?? '');
				}
			}

		$this->view('tools/neighborhood_import', [
			'title' => 'Importador de bairros',
			'previewResult' => $previewResult,
			'confirmationResult' => $confirmationResult,
			'stateOptions' => $this->readNeighborhoodImportStateOptions(),
			'selectedStateAcronym' => $selectedStateAcronym,
		]);
		}

	/**
	 * Stream the neighborhood import progress via Server-Sent Events.
	 * @return void
	 **/
	public function streamNeighborhoodImport(): void
		{
		$this->requireAuth();

		$previewRows = $this->readNeighborhoodImportPreviewRows();
		$this->clearNeighborhoodImportPreview();

		ignore_user_abort(true);
		set_time_limit(0);

		header('Content-Type: text/event-stream; charset=utf-8');
		header('Cache-Control: no-cache, no-transform');
		header('Connection: keep-alive');
		header('X-Accel-Buffering: no');

		while (ob_get_level() > 0)
			{
			ob_end_flush();
			}

		ob_implicit_flush(true);
		session_write_close();

		$model = new NeighborhoodImport();

		foreach ($model->streamImport($previewRows) as $payload)
			{
			echo 'event: ' . ($payload['event'] ?? 'message') . "\n";
			echo 'data: ' . json_encode($payload['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
			flush();

			if (connection_aborted())
				{
				break;
				}
			}

		exit;
		}

	/**
	 * Search a zip code and return the lookup result.
	 * @return void
	 **/
	public function searchZipCode(): void
		{
		$this->requireAuth();

		$service = new ZipCodeSearchService();
		$result = $service->searchAndSave($this->readZipCodeSearchInput());
		$status = (int) ($result['status'] ?? 500);

		$this->json($result, $status);
		}

	/**
	 * Compare zip code neighborhood lookup providers.
	 * @return void
	 **/
	public function compareZipCodeNeighborhood(): void
		{
		$this->requireAuth();

		$service = new ZipCodeNeighborhoodService();
		$result = $service->compareLookups($this->readZipCodeSearchInput());
		$status = (int) ($result['status'] ?? 500);

		$this->json($result, $status);
		}

	/**
	 * Save the selected zip code neighborhood lookup data.
	 * @return void
	 **/
	public function saveZipCodeNeighborhood(): void
		{
		$this->requireAuth();

		$service = new ZipCodeNeighborhoodService();
		$result = $service->saveSelection(
			$this->readZipCodeSearchInput(),
			$this->readZipCodeNeighborhoodSelectionInput(),
			$this->readCreateCityFlag()
		);
		$status = (int) ($result['status'] ?? 500);

		$this->json($result, $status);
		}

	/**
	 * Stream zip code crawler progress through Server-Sent Events.
	 * @return void
	 **/
	public function streamZipCodeCrawler(): void
		{
		$this->requireAuth();

		$startZipCode = $this->normalizeZipCodeInput((string) ($_GET['start_zip_code'] ?? ''));
		$endZipCode = $this->normalizeZipCodeInput((string) ($_GET['end_zip_code'] ?? ''));
		$stateAcronym = $this->readCrawlerStateAcronym((string) ($_GET['state_acronym'] ?? ($_GET['state'] ?? '')));
		$delayMode = $this->readCrawlerDelayMode((string) ($_GET['delay_mode'] ?? 'fixed'));
		$delayMs = $this->readPositiveInt((string) ($_GET['delay_ms'] ?? '500'), 500, 0, 10000);
		$maxResults = $this->readPositiveInt((string) ($_GET['max_results'] ?? '100'), 100, 1, PHP_INT_MAX);
		$providerMode = $this->readCrawlerProviderMode((string) ($_GET['provider_mode'] ?? 'fallback'));
		$concurrency = $this->readPositiveInt((string) ($_GET['concurrency'] ?? '3'), 3, 1, 100);
		$autoSaveCity = array_key_exists('save_city', $_GET) ? $this->readBooleanFlag($_GET['save_city']) : true;
		$autoSaveNeighborhood = array_key_exists('save_neighborhood', $_GET) ? $this->readBooleanFlag($_GET['save_neighborhood']) : true;
		$createCity = $this->readBooleanFlag($_GET['create_city'] ?? null);

		ignore_user_abort(true);
		set_time_limit(0);

		header('Content-Type: text/event-stream; charset=utf-8');
		header('Cache-Control: no-cache, no-transform');
		header('Connection: keep-alive');
		header('X-Accel-Buffering: no');

		while (ob_get_level() > 0)
			{
			ob_end_flush();
			}

		ob_implicit_flush(true);
		session_write_close();

		$service = new ZipCodeCrawlerService();
		$stream = $stateAcronym !== ''
			? $service->crawlState($stateAcronym, $delayMs, $maxResults, $providerMode, $concurrency, $autoSaveCity, $autoSaveNeighborhood, $createCity, $delayMode)
			: $service->crawl($startZipCode, $endZipCode, $delayMs, $maxResults, $providerMode, $concurrency, $autoSaveCity, $autoSaveNeighborhood, $createCity, $delayMode);

		foreach ($stream as $payload)
			{
			echo 'event: ' . ($payload['event'] ?? 'message') . "\n";
			echo 'data: ' . json_encode($payload['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
			flush();

			if (connection_aborted())
				{
				break;
				}
			}

		exit;
		}

	/**
	 * Save a single zip code crawler result.
	 * @return void
	 **/
	public function saveZipCodeCrawlerResult(): void
		{
		$this->requireAuth();

		$payload = $this->readZipCodeCrawlerSaveInput();
		$lookupData = $payload['lookup_data'];

		if (!is_array($lookupData) || empty($lookupData))
			{
			$this->json([
				'success' => false,
				'status'  => 422,
				'error'   => 'invalid_lookup_data',
				'message' => 'Os dados do CEP são obrigatórios para salvar.',
			], 422);
			}

		$service = new ZipCodeNeighborhoodService();
		$result = $service->persistLookupData(
			$lookupData,
			$payload['save_city'],
			$payload['save_neighborhood'],
			$payload['create_city']
		);
		$status = (int) ($result['status'] ?? 500);

		$this->json($result, $status);
		}

	/**
	 * Save selected zip code crawler results in batch.
	 * @return void
	 **/
	public function saveZipCodeCrawlerResults(): void
		{
		$this->requireAuth();

		$payload = $this->readZipCodeCrawlerBatchSaveInput();
		$items = $payload['items'];

		if (!is_array($items) || empty($items))
			{
			$this->json([
				'success' => false,
				'status'  => 422,
				'error'   => 'invalid_items',
				'message' => 'Selecione ao menos um resultado para salvar.',
			], 422);
			}

		$service = new ZipCodeNeighborhoodService();
		$result = $service->persistLookupDataBatch(
			$items,
			$payload['save_city'],
			$payload['save_neighborhood'],
			$payload['create_city']
		);
		$status = (int) ($result['status'] ?? 500);

		$this->json([
			'success' => !empty($result['success']),
			'status'  => $status,
			'message' => (string) ($result['message'] ?? ''),
			'data'    => $result['data'] ?? [],
		], $status);
		}

	/**
	 * Read the email validation input value from the request.
	 * @return string
	 **/
	private function readEmailValidationInput(): string
		{
		if (isset($_POST['email']))
			{
			return trim((string) $_POST['email']);
			}

		if (isset($_GET['email']))
			{
			return trim((string) $_GET['email']);
			}

		$rawBody = (string) file_get_contents('php://input');
		if ($rawBody === '')
			{
			return '';
			}

		$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
		if (strpos($contentType, 'application/json') !== false)
			{
			$payload = json_decode($rawBody, true);
			if (is_array($payload) && isset($payload['email']))
				{
				return trim((string) $payload['email']);
				}
			}

		parse_str($rawBody, $parsedBody);
		if (is_array($parsedBody) && isset($parsedBody['email']))
			{
			return trim((string) $parsedBody['email']);
			}

		return '';
		}

	/**
	 * Read the zip code search input value from the request.
	 * @return string
	 **/
	private function readZipCodeSearchInput(): string
		{
		if (isset($_POST['zip_code']))
			{
			return trim((string) $_POST['zip_code']);
			}

		if (isset($_POST['cep']))
			{
			return trim((string) $_POST['cep']);
			}

		if (isset($_GET['zip_code']))
			{
			return trim((string) $_GET['zip_code']);
			}

		if (isset($_GET['cep']))
			{
			return trim((string) $_GET['cep']);
			}

		$rawBody = (string) file_get_contents('php://input');
		if ($rawBody === '')
			{
			return '';
			}

		$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
		if (strpos($contentType, 'application/json') !== false)
			{
			$payload = json_decode($rawBody, true);
			if (is_array($payload))
				{
				if (isset($payload['zip_code']))
					{
					return trim((string) $payload['zip_code']);
					}

				if (isset($payload['cep']))
					{
					return trim((string) $payload['cep']);
					}
				}
			}

		parse_str($rawBody, $parsedBody);
		if (is_array($parsedBody))
			{
			if (isset($parsedBody['zip_code']))
				{
				return trim((string) $parsedBody['zip_code']);
				}

			if (isset($parsedBody['cep']))
				{
				return trim((string) $parsedBody['cep']);
				}
			}

		return '';
		}

	/**
	 * Read the zip code neighborhood selection values from the request.
	 * @return array
	 **/
	private function readZipCodeNeighborhoodSelectionInput(): array
		{
		$selection = [];

		if (isset($_POST['selection']) && is_array($_POST['selection']))
			{
			foreach ($_POST['selection'] as $field => $provider)
				{
				$selection[(string) $field] = trim((string) $provider);
				}
			}
		elseif (isset($_GET['selection']) && is_array($_GET['selection']))
			{
			foreach ($_GET['selection'] as $field => $provider)
				{
				$selection[(string) $field] = trim((string) $provider);
				}
			}

		$rawBody = (string) file_get_contents('php://input');
		if ($rawBody !== '')
			{
			$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
			if (strpos($contentType, 'application/json') !== false)
				{
				$payload = json_decode($rawBody, true);
				if (is_array($payload) && isset($payload['selection']) && is_array($payload['selection']))
					{
					foreach ($payload['selection'] as $field => $provider)
						{
						$selection[(string) $field] = trim((string) $provider);
						}
					}
				}
			else
				{
				parse_str($rawBody, $parsedBody);
				if (is_array($parsedBody) && isset($parsedBody['selection']) && is_array($parsedBody['selection']))
					{
					foreach ($parsedBody['selection'] as $field => $provider)
						{
						$selection[(string) $field] = trim((string) $provider);
						}
					}
				}
			}

		return $selection;
		}

	/**
	 * Validate the uploaded XLSX import file payload.
	 *
	 * @return array<string, mixed>
	 */
	private function readNeighborhoodImportUpload(): array
		{
		$file = $_FILES['xlsx_file'] ?? null;
		if (!is_array($file))
			{
			throw new \RuntimeException('Selecione um arquivo XLSX para importar.');
			}

		$error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
		if ($error !== UPLOAD_ERR_OK)
			{
			if ($error === UPLOAD_ERR_NO_FILE)
				{
				throw new \RuntimeException('Selecione um arquivo XLSX para importar.');
				}

			throw new \RuntimeException('O upload do arquivo XLSX falhou. Tente novamente.');
			}

		$name = (string) ($file['name'] ?? '');
		$extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
		if ($extension !== 'xlsx')
			{
			throw new \RuntimeException('Envie um arquivo no formato XLSX.');
			}

		$tmpName = (string) ($file['tmp_name'] ?? '');
		if ($tmpName === '' || !is_uploaded_file($tmpName))
			{
			throw new \RuntimeException('O arquivo enviado é inválido.');
			}

		return $file;
		}

	/**
	 * Read and validate the selected state acronym for neighborhood import.
	 *
	 * @param bool $required Whether an empty value should throw an exception.
	 * @return string
	 */
	private function readNeighborhoodImportStateAcronym(bool $required = true): string
		{
		$value = strtoupper(trim((string) ($_POST['state_acronym'] ?? '')));
		if ($value === '' && $required)
			{
			throw new \RuntimeException('Selecione um estado para gerar a prévia da importação.');
			}

		return $value;
		}

	/**
	 * Return state options used by the neighborhood import filter.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function readNeighborhoodImportStateOptions(): array
		{
		$stateModel = new State();
		$states = $stateModel->getAll();
		$options = [];

		foreach ($states as $state)
			{
			if (strcasecmp((string) ($state['country_name'] ?? ''), 'Brasil') !== 0)
				{
				continue;
				}

			$acronym = strtoupper(trim((string) ($state['acronym'] ?? '')));
			if ($acronym === '')
				{
				continue;
				}

			$options[] = [
				'acronym' => $acronym,
				'name' => (string) ($state['name'] ?? $acronym),
			];
			}

		usort($options, static function (array $a, array $b): int {
			return strcmp($a['name'], $b['name']);
		});

		return $options;
		}

	/**
	 * Persist the import preview in session until confirmation.
	 *
	 * @param array<string, mixed> $preview
	 * @return void
	 */
	private function storeNeighborhoodImportPreview(array $preview): void
		{
		$_SESSION['manager_main']['neighborhood_import_preview'] = $preview;
		}

	/**
	 * Read the stored import preview from session.
	 *
	 * @return array<string, mixed>|null
	 */
	private function readStoredNeighborhoodImportPreview(): ?array
		{
		$preview = $_SESSION['manager_main']['neighborhood_import_preview'] ?? null;
		return is_array($preview) ? $preview : null;
		}

	/**
	 * Read preview rows stored for import confirmation.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function readNeighborhoodImportPreviewRows(): array
		{
		$preview = $this->readStoredNeighborhoodImportPreview();
		if (!is_array($preview))
			{
			throw new \RuntimeException('Nenhuma prévia de importação foi encontrada. Envie o arquivo novamente.');
			}

		$rows = $preview['rows_to_import'] ?? [];
		if (!is_array($rows))
			{
			throw new \RuntimeException('A prévia de importação está inválida. Envie o arquivo novamente.');
			}

		return $rows;
		}

	/**
	 * Clear the import preview stored in session.
	 *
	 * @return void
	 */
	private function clearNeighborhoodImportPreview(): void
		{
		unset($_SESSION['manager_main']['neighborhood_import_preview']);
		}

	/**
	 * Read the create city flag from the request.
	 * @return bool
	 **/
	private function readCreateCityFlag(): bool
		{
		$value = $_POST['create_city'] ?? ($_GET['create_city'] ?? null);

		if ($value === null)
			{
			$rawBody = (string) file_get_contents('php://input');
			if ($rawBody !== '')
				{
				$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
				if (strpos($contentType, 'application/json') !== false)
					{
					$payload = json_decode($rawBody, true);
					if (is_array($payload) && array_key_exists('create_city', $payload))
						{
						$value = $payload['create_city'];
						}
					}
				}
			}

		if (is_bool($value))
			{
			return $value;
			}

		$value = strtolower(trim((string) $value));
		return in_array($value, ['1', 'true', 'yes', 'on'], true);
		}

	/**
	 * Read crawler provider mode.
	 * @param string $value Provider mode value.
	 * @return string
	 **/
	private function readCrawlerProviderMode(string $value): string
		{
		$value = strtolower(trim($value));
		if (!in_array($value, ['viacep', 'brasilapi', 'fallback'], true))
			{
			return 'fallback';
			}

		return $value;
		}

	/**
	 * Read crawler delay mode.
	 * @param string $value Delay mode value.
	 * @return string
	 **/
	private function readCrawlerDelayMode(string $value): string
		{
		$value = strtolower(trim($value));
		return in_array($value, ['fixed', 'random'], true) ? $value : 'fixed';
		}

	/**
	 * Read and normalize the selected crawler state acronym.
	 * @param string $value Raw state acronym value.
	 * @return string
	 **/
	private function readCrawlerStateAcronym(string $value): string
		{
		$value = strtoupper(trim($value));
		return preg_match('/^[A-Z]{2}$/', $value) ? $value : '';
		}

	/**
	 * Read Brazilian state options for the zip code crawler.
	 * @return array<int, array<string, mixed>>
	 **/
	private function readZipCodeCrawlerStateOptions(): array
		{
		$stateModel = new State();
		$states = $stateModel->search(
			['country_id' => 1],
			100,
			0,
			['sort' => 'name', 'direction' => 'asc']
		);
		$options = [];

		foreach ($states as $state)
			{
			$acronym = strtoupper((string) ($state['acronym'] ?? ''));
			$ranges = ZipCodeCrawlerService::zipCodeRangesForState($acronym);
			if (empty($ranges))
				{
				continue;
				}

			$options[] = [
				'id' => (int) ($state['id'] ?? 0),
				'name' => (string) ($state['name'] ?? ''),
				'acronym' => $acronym,
				'ranges' => ZipCodeCrawlerService::zipCodeRangeOptionsForState($acronym),
				'total' => ZipCodeCrawlerService::countZipCodeRanges($ranges),
			];
			}

		return $options;
		}

	/**
	 * Read a boolean-like flag.
	 * @param mixed $value Raw flag value.
	 * @return bool
	 **/
	private function readBooleanFlag($value): bool
		{
		if (is_bool($value))
			{
			return $value;
			}

		if ($value === null)
			{
			return false;
			}

		$value = strtolower(trim((string) $value));
		return in_array($value, ['1', 'true', 'yes', 'on'], true);
		}

	/**
	 * Read the zip code crawler save input from the request.
	 * @return array
	 **/
	private function readZipCodeCrawlerSaveInput(): array
		{
		$lookupData = [];
		$saveCity = false;
		$saveNeighborhood = false;
		$createCity = false;

		$rawBody = (string) file_get_contents('php://input');
		if ($rawBody !== '')
			{
			$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
			if (strpos($contentType, 'application/json') !== false)
				{
				$payload = json_decode($rawBody, true);
				if (is_array($payload))
					{
					if (isset($payload['lookup_data']) && is_array($payload['lookup_data']))
						{
						$lookupData = $payload['lookup_data'];
						}

					$saveCity = $this->readBooleanFlag($payload['save_city'] ?? null);
					$saveNeighborhood = $this->readBooleanFlag($payload['save_neighborhood'] ?? null);
					$createCity = $this->readBooleanFlag($payload['create_city'] ?? null);
					}
				}
			else
				{
				parse_str($rawBody, $parsedBody);
				if (is_array($parsedBody))
					{
					if (isset($parsedBody['lookup_data']) && is_array($parsedBody['lookup_data']))
						{
						$lookupData = $parsedBody['lookup_data'];
						}

					$saveCity = $this->readBooleanFlag($parsedBody['save_city'] ?? null);
					$saveNeighborhood = $this->readBooleanFlag($parsedBody['save_neighborhood'] ?? null);
					$createCity = $this->readBooleanFlag($parsedBody['create_city'] ?? null);
					}
				}
			}

		if (isset($_POST['lookup_data']) && is_array($_POST['lookup_data']))
			{
			$lookupData = $_POST['lookup_data'];
			}

		if (isset($_POST['save_city']))
			{
			$saveCity = $this->readBooleanFlag($_POST['save_city']);
			}

		if (isset($_POST['save_neighborhood']))
			{
			$saveNeighborhood = $this->readBooleanFlag($_POST['save_neighborhood']);
			}

		if (isset($_POST['create_city']))
			{
			$createCity = $this->readBooleanFlag($_POST['create_city']);
			}

		return [
			'lookup_data' => $lookupData,
			'save_city' => $saveCity,
			'save_neighborhood' => $saveNeighborhood,
			'create_city' => $createCity,
		];
		}

	/**
	 * Read the zip code crawler batch save input from the request.
	 * @return array
	 **/
	private function readZipCodeCrawlerBatchSaveInput(): array
		{
		$items = [];
		$saveCity = false;
		$saveNeighborhood = false;
		$createCity = false;

		$rawBody = (string) file_get_contents('php://input');
		if ($rawBody !== '')
			{
			$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
			if (strpos($contentType, 'application/json') !== false)
				{
				$payload = json_decode($rawBody, true);
				if (is_array($payload))
					{
					if (isset($payload['items']) && is_array($payload['items']))
						{
						$items = $payload['items'];
						}

					$saveCity = $this->readBooleanFlag($payload['save_city'] ?? null);
					$saveNeighborhood = $this->readBooleanFlag($payload['save_neighborhood'] ?? null);
					$createCity = $this->readBooleanFlag($payload['create_city'] ?? null);
					}
				}
			else
				{
				parse_str($rawBody, $parsedBody);
				if (is_array($parsedBody))
					{
					if (isset($parsedBody['items']) && is_array($parsedBody['items']))
						{
						$items = $parsedBody['items'];
						}

					$saveCity = $this->readBooleanFlag($parsedBody['save_city'] ?? null);
					$saveNeighborhood = $this->readBooleanFlag($parsedBody['save_neighborhood'] ?? null);
					$createCity = $this->readBooleanFlag($parsedBody['create_city'] ?? null);
					}
				}
			}

		if (isset($_POST['items']) && is_array($_POST['items']))
			{
			$items = $_POST['items'];
			}

		if (isset($_POST['save_city']))
			{
			$saveCity = $this->readBooleanFlag($_POST['save_city']);
			}

		if (isset($_POST['save_neighborhood']))
			{
			$saveNeighborhood = $this->readBooleanFlag($_POST['save_neighborhood']);
			}

		if (isset($_POST['create_city']))
			{
			$createCity = $this->readBooleanFlag($_POST['create_city']);
			}

		return [
			'items' => $items,
			'save_city' => $saveCity,
			'save_neighborhood' => $saveNeighborhood,
			'create_city' => $createCity,
		];
		}

	/**
	 * Normalize a zip code input value.
	 * @param string $value Raw zip code value.
	 * @return string
	 **/
	private function normalizeZipCodeInput(string $value): string
		{
		return preg_replace('/\D+/', '', $value) ?? '';
		}

	/**
	 * Read a positive integer with bounds.
	 * @param string $value Raw numeric value.
	 * @param int $default Default value when parsing fails.
	 * @param int $min Minimum accepted value.
	 * @param int $max Maximum accepted value.
	 * @return int
	 **/
	private function readPositiveInt(string $value, int $default, int $min, int $max): int
		{
		$parsed = (int) preg_replace('/\D+/', '', $value);
		if ($parsed <= 0)
			{
			$parsed = $default;
			}

		if ($parsed < $min)
			{
			$parsed = $min;
			}

		if ($parsed > $max)
			{
			$parsed = $max;
			}

		return $parsed;
		}
	}

?>

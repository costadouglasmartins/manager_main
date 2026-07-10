<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\State;
use App\Services\EmailMxValidatorService;
use App\Services\IpRangeWebCrawlerService;
use App\Services\WebAddressCrawlerService;
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
	 * Render the IP range webcrawler page.
	 * @return void
	 **/
	public function ipRangeWebCrawler(): void
		{
		$this->requireAuth();

		$service = new IpRangeWebCrawlerService();

		$this->view('tools/ip_range_webcrawler', [
			'title' => 'Webcrawler de IPs',
			'ipRangeCountryOptions' => $service->countryRangeOptions(),
			'userAgentOptions' => IpRangeWebCrawlerService::userAgentOptions(),
		]);
		}

	/**
	 * Render the IP range webcrawler report.
	 * @return void
	 **/
	public function ipRangeWebCrawlerReport(): void
		{
		$this->requireAuth();

		$service = new IpRangeWebCrawlerService();
		$filters = $this->readIpRangeWebCrawlerReportFilters();
		$page = $this->readIpRangeWebCrawlerReportPage();
		$perPage = 20;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest($this->ipRangeWebCrawlerReportSortableColumns(), 'checked_at', 'desc');
		$results = $service->searchResults($filters, $perPage, $offset, $sort);
		$total = $service->countResults($filters);
		$totalPages = max(1, (int) ceil($total / $perPage));
		$summary = $service->reportSummary($filters);
		$countryOptions = $service->countryRangeOptions();
		$queryFilters = http_build_query($filters);

		$this->view('tools/ip_range_webcrawler_report', [
			'title' => 'Relatório do webcrawler de IPs',
			'filters' => $filters,
			'results' => $results,
			'summary' => $summary,
			'page' => $page,
			'perPage' => $perPage,
			'totalPages' => $totalPages,
			'total' => $total,
			'sort' => $sort,
			'paginationQuery' => sortQueryString($queryFilters, $sort),
			'ipRangeCountryOptions' => $countryOptions,
		]);
		}

	/**
	 * Render the web address prospect crawler page.
	 * @return void
	 **/
	public function webAddressCrawler(): void
		{
		$this->requireAuth();

		$this->view('tools/web_address_crawler', [
			'title' => 'Webcrawler de endereços',
			'stateOptions' => $this->readBrazilStateOptions(),
			'userAgentOptions' => WebAddressCrawlerService::userAgentOptions(),
			'maxResults' => WebAddressCrawlerService::maxResults(),
		]);
		}

	/**
	 * Render the prospect results listing with filters and pagination.
	 * @return void
	 **/
	public function webAddressCrawlerResults(): void
		{
		$this->requireAuth();

		$service = new WebAddressCrawlerService();
		$filters = $this->readWebAddressCrawlerResultsFilters();
		$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
		$perPage = 20;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest($this->webAddressCrawlerResultsSortableColumns(), 'checked_at', 'desc');
		$results = $service->searchResults($filters, $perPage, $offset, $sort);
		$total = $service->countResults($filters);
		$totalPages = max(1, (int) ceil($total / $perPage));
		$summary = $service->reportSummary($filters);
		$queryFilters = http_build_query($filters);

		$this->view('tools/web_address_crawler_results', [
			'title' => 'Registros encontrados',
			'filters' => $filters,
			'results' => $results,
			'summary' => $summary,
			'page' => $page,
			'perPage' => $perPage,
			'totalPages' => $totalPages,
			'total' => $total,
			'sort' => $sort,
			'paginationQuery' => sortQueryString($queryFilters, $sort),
			'stateOptions' => $this->readBrazilStateOptions(),
		]);
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
		$concurrency = $this->readPositiveInt((string) ($_GET['concurrency'] ?? '3'), 3, 1, 500);
		$autoSaveCity = array_key_exists('save_city', $_GET) ? $this->readBooleanFlag($_GET['save_city']) : true;
		$autoSaveNeighborhood = array_key_exists('save_neighborhood', $_GET) ? $this->readBooleanFlag($_GET['save_neighborhood']) : true;
		$createCity = $this->readBooleanFlag($_GET['create_city'] ?? null);
		$runId = (string) ($_GET['run_id'] ?? '');

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
		$service->setRunId($runId);
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
	 * Request a running zip code crawler stream to stop.
	 * @return void
	 **/
	public function stopZipCodeCrawler(): void
		{
		$this->requireAuth();

		$runId = $this->readZipCodeCrawlerRunId();
		if ($runId === '' || !ZipCodeCrawlerService::requestStop($runId))
			{
			$this->json([
				'success' => false,
				'status' => 422,
				'message' => 'Não foi possível solicitar a parada do crawler.',
			], 422);
			}

		$this->json([
			'success' => true,
			'status' => 200,
			'message' => 'Parada solicitada.',
		]);
		}

	/**
	 * Stream IP range webcrawler progress through Server-Sent Events.
	 * @return void
	 **/
	public function streamIpRangeWebCrawler(): void
		{
		$this->requireAuth();

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

		$service = new IpRangeWebCrawlerService();

		foreach ($service->stream($this->readIpRangeWebCrawlerInput()) as $payload)
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
	 * Stream web address prospect crawler progress through Server-Sent Events.
	 * @return void
	 **/
	public function streamWebAddressCrawler(): void
		{
		$this->requireAuth();

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

		$service = new WebAddressCrawlerService();

		foreach ($service->stream($this->readWebAddressCrawlerInput()) as $payload)
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
	 * Import the filtered prospect results into the people registry.
	 * @return void
	 **/
	public function importProspects(): void
		{
		$this->requireAuth();

		$user = $_SESSION['manager_main']['user'] ?? [];
		$service = new WebAddressCrawlerService();
		$result = $service->importByFilters($this->readWebAddressCrawlerImportFilters(), (int) ($user['id'] ?? 0));
		$status = (int) ($result['status'] ?? 500);

		$this->json($result, $status);
		}

	/**
	 * Import IP ranges for one country and return refreshed options.
	 * @return void
	 **/
	public function importIpRangeCountryRanges(): void
		{
		$this->requireAuth();

		$service = new IpRangeWebCrawlerService();
		$result = $service->importCountryRanges($this->readIpRangeCountryCodeInput());
		$status = (int) ($result['status'] ?? 500);

		$this->json($result, $status);
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
	 * Read the IP range webcrawler input from the request.
	 * @return array<string, mixed>
	 **/
	private function readIpRangeWebCrawlerInput(): array
		{
		$user = $_SESSION['manager_main']['user'] ?? [];

		return [
			'user_id' => (int) ($user['id'] ?? 0),
			'range_mode' => trim((string) ($_GET['range_mode'] ?? 'amount')),
			'start_ip' => trim((string) ($_GET['start_ip'] ?? '')),
			'end_ip' => trim((string) ($_GET['end_ip'] ?? '')),
			'ip_quantity' => $this->readPositiveInt((string) ($_GET['ip_quantity'] ?? '100'), 100, 1, IpRangeWebCrawlerService::maxIpsPerExecution()),
			'manual_ranges' => trim((string) ($_GET['manual_ranges'] ?? '')),
			'country_code' => strtoupper(trim((string) ($_GET['country_code'] ?? ''))),
			'max_ips' => $this->readPositiveInt((string) ($_GET['max_ips'] ?? '0'), 0, 0, IpRangeWebCrawlerService::maxIpsPerExecution()),
			'keywords' => trim((string) ($_GET['keywords'] ?? '')),
			'user_agent' => trim((string) ($_GET['user_agent'] ?? '')),
			'timeout_ms' => $this->readPositiveInt((string) ($_GET['timeout_ms'] ?? '3000'), 3000, 500, 15000),
			'concurrency' => $this->readPositiveInt((string) ($_GET['concurrency'] ?? '10'), 10, 1, 200),
			'skip_existing' => array_key_exists('skip_existing', $_GET) ? $this->readBooleanFlag($_GET['skip_existing']) : true,
		];
		}

	/**
	 * Read the country code used to import country IP ranges.
	 * @return string
	 **/
	private function readIpRangeCountryCodeInput(): string
		{
		if (isset($_POST['country_code']))
			{
			return strtoupper(trim((string) $_POST['country_code']));
			}

		if (isset($_GET['country_code']))
			{
			return strtoupper(trim((string) $_GET['country_code']));
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
			if (is_array($payload) && isset($payload['country_code']))
				{
				return strtoupper(trim((string) $payload['country_code']));
				}
			}

		parse_str($rawBody, $parsedBody);
		if (is_array($parsedBody) && isset($parsedBody['country_code']))
			{
			return strtoupper(trim((string) $parsedBody['country_code']));
			}

		return '';
		}

	/**
	 * Read filters for the IP range webcrawler report.
	 * @return array<string, string>
	 **/
	private function readIpRangeWebCrawlerReportFilters(): array
		{
		return [
			'execution_id' => trim((string) ($_GET['execution_id'] ?? '')),
			'country_code' => strtoupper(trim((string) ($_GET['country_code'] ?? ''))),
			'range_mode' => trim((string) ($_GET['range_mode'] ?? '')),
			'protocol' => strtolower(trim((string) ($_GET['protocol'] ?? ''))),
			'port' => trim((string) ($_GET['port'] ?? '')),
			'http_status' => trim((string) ($_GET['http_status'] ?? '')),
			'scan_status' => trim((string) ($_GET['scan_status'] ?? '')),
			'keyword_matched' => trim((string) ($_GET['keyword_matched'] ?? '')),
			'term' => trim((string) ($_GET['term'] ?? '')),
			'date_from' => trim((string) ($_GET['date_from'] ?? '')),
			'date_to' => trim((string) ($_GET['date_to'] ?? '')),
		];
		}

	/**
	 * Read the current page for the IP range webcrawler report.
	 * @return int
	 **/
	private function readIpRangeWebCrawlerReportPage(): int
		{
		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		return $page > 0 ? $page : 1;
		}

	/**
	 * Return allowed sort columns for the IP range webcrawler report.
	 * @return array<string, string>
	 **/
	private function ipRangeWebCrawlerReportSortableColumns(): array
		{
		return [
			'id' => 'r.id',
			'execution_id' => 'r.execution_id',
			'country_code' => 'e.country_code',
			'ip_address' => 'r.ip_address',
			'protocol' => 'r.protocol',
			'port' => 'r.port',
			'http_status' => 'r.http_status',
			'scan_status' => 'r.scan_status',
			'keyword_matched' => 'r.keyword_matched',
			'response_time_ms' => 'r.response_time_ms',
			'checked_at' => 'r.checked_at',
			'title' => 'r.title',
		];
		}

	/**
	 * Read the web address crawler input from the request.
	 * @return array<string, mixed>
	 **/
	private function readWebAddressCrawlerInput(): array
		{
		$user = $_SESSION['manager_main']['user'] ?? [];

		return [
			'user_id' => (int) ($user['id'] ?? 0),
			'keywords' => trim((string) ($_GET['keywords'] ?? '')),
			'state_acronym' => strtoupper(trim((string) ($_GET['state_acronym'] ?? ($_GET['state'] ?? '')))),
			'city' => trim((string) ($_GET['city'] ?? '')),
			'zip_code' => trim((string) ($_GET['zip_code'] ?? '')),
			'max_results' => $this->readPositiveInt((string) ($_GET['max_results'] ?? '50'), 50, 1, WebAddressCrawlerService::maxResults()),
			'concurrency' => $this->readPositiveInt((string) ($_GET['concurrency'] ?? '5'), 5, 1, 20),
			'delay_ms' => $this->readPositiveInt((string) ($_GET['delay_ms'] ?? '1000'), 1000, 100, 10000),
			'user_agent' => trim((string) ($_GET['user_agent'] ?? '')),
		];
		}

	/**
	 * Read filters for the prospect results listing.
	 * @return array<string, string>
	 **/
	private function readWebAddressCrawlerResultsFilters(): array
		{
		return [
			'search_id' => trim((string) ($_GET['search_id'] ?? '')),
			'state_acronym' => strtoupper(trim((string) ($_GET['state_acronym'] ?? ''))),
			'city' => trim((string) ($_GET['city'] ?? '')),
			'import_status' => trim((string) ($_GET['import_status'] ?? '')),
			'has_phone' => trim((string) ($_GET['has_phone'] ?? '')),
			'has_email' => trim((string) ($_GET['has_email'] ?? '')),
			'has_document' => trim((string) ($_GET['has_document'] ?? '')),
			'term' => trim((string) ($_GET['term'] ?? '')),
			'date_from' => trim((string) ($_GET['date_from'] ?? '')),
			'date_to' => trim((string) ($_GET['date_to'] ?? '')),
		];
		}

	/**
	 * Read filters used to import the filtered prospect results.
	 * @return array<string, string>
	 **/
	private function readWebAddressCrawlerImportFilters(): array
		{
		$source = $_POST;
		$rawBody = (string) file_get_contents('php://input');
		if ($rawBody !== '')
			{
			$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
			if (strpos($contentType, 'application/json') !== false)
				{
				$payload = json_decode($rawBody, true);
				if (is_array($payload))
					{
					$source = is_array($payload['filters'] ?? null) ? $payload['filters'] : $payload;
					}
				}
			}

		return [
			'search_id' => trim((string) ($source['search_id'] ?? '')),
			'state_acronym' => strtoupper(trim((string) ($source['state_acronym'] ?? ''))),
			'city' => trim((string) ($source['city'] ?? '')),
			'import_status' => trim((string) ($source['import_status'] ?? '')),
			'has_phone' => trim((string) ($source['has_phone'] ?? '')),
			'has_email' => trim((string) ($source['has_email'] ?? '')),
			'has_document' => trim((string) ($source['has_document'] ?? '')),
			'term' => trim((string) ($source['term'] ?? '')),
			'date_from' => trim((string) ($source['date_from'] ?? '')),
			'date_to' => trim((string) ($source['date_to'] ?? '')),
		];
		}

	/**
	 * Return allowed sort columns for the prospect results listing.
	 * @return array<string, string>
	 **/
	private function webAddressCrawlerResultsSortableColumns(): array
		{
		return [
			'id' => 'r.id',
			'business_name' => 'r.business_name',
			'city' => 'r.city',
			'state_acronym' => 'r.state_acronym',
			'import_status' => 'r.import_status',
			'phone' => 'r.phone',
			'email' => 'r.email',
			'checked_at' => 'r.checked_at',
		];
		}

	/**
	 * Return Brazilian state options for the prospect crawler filters.
	 * @return array<int, array<string, string>>
	 **/
	private function readBrazilStateOptions(): array
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
			if ($acronym === '')
				{
				continue;
				}

			$options[] = [
				'acronym' => $acronym,
				'name' => (string) ($state['name'] ?? $acronym),
			];
			}

		return $options;
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
	 * Read the zip code crawler run id from the request.
	 * @return string
	 **/
	private function readZipCodeCrawlerRunId(): string
		{
		$value = (string) ($_POST['run_id'] ?? ($_GET['run_id'] ?? ''));
		if ($value === '')
			{
			$rawBody = (string) file_get_contents('php://input');
			if ($rawBody !== '')
				{
				$payload = json_decode($rawBody, true);
				if (is_array($payload))
					{
					$value = (string) ($payload['run_id'] ?? '');
					}
				else
					{
					parse_str($rawBody, $parsedBody);
					$value = (string) ($parsedBody['run_id'] ?? '');
					}
				}
			}

		$value = preg_replace('/[^a-zA-Z0-9_-]+/', '', $value) ?? '';
		return substr($value, 0, 80);
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

	/**
	 * Read a non-negative integer with an upper bound.
	 * @param string $value Raw numeric value.
	 * @param int $default Default value when parsing fails.
	 * @param int $max Maximum accepted value.
	 * @return int
	 **/
	private function readNonNegativeInt(string $value, int $default, int $max): int
		{
		if (!preg_match('/^\d+$/', trim($value)))
			{
			$parsed = $default;
			}
		else
			{
			$parsed = (int) trim($value);
			}

		if ($parsed < 0)
			{
			$parsed = 0;
			}

		if ($parsed > $max)
			{
			$parsed = $max;
			}

		return $parsed;
		}
	}

?>

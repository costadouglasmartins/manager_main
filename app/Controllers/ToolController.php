<?php

namespace App\Controllers;

use App\Core\Authorization;
use App\Core\Controller;
use App\Models\State;
use App\Services\EmailMxValidatorService;
use App\Services\IpCountryRangeService;
use App\Services\IpWebCrawlerService;
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
	 * Render the IP webcrawler page.
	 * @return void
	 **/
	public function ipWebCrawler(): void
		{
		$this->authorizeIpWebCrawler('tool.ipWebCrawler');

		$this->view('tools/ip_web_crawler', [
			'title' => 'Webcrawler por IP',
			'ipCountryOptions' => IpCountryRangeService::countryOptions(),
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
		$concurrency = $this->readPositiveInt((string) ($_GET['concurrency'] ?? '3'), 3, 1, 5);
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
	 * Stream IP webcrawler progress through Server-Sent Events.
	 * @return void
	 **/
	public function streamIpWebCrawler(): void
		{
		$this->authorizeIpWebCrawler('tool.streamIpWebCrawler');

		$userId = (int) ($_SESSION['manager_main']['user']['id'] ?? 0);

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

		$service = new IpWebCrawlerService();
		foreach ($service->crawl($this->readIpWebCrawlerInput(), $userId) as $payload)
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
	 * Return IPv4 ranges for the selected country.
	 * @return void
	 **/
	public function ipWebCrawlerCountryRanges(): void
		{
		$this->authorizeIpWebCrawler('tool.ipWebCrawler');

		$countryCode = trim((string) ($_GET['country_code'] ?? ($_POST['country_code'] ?? '')));
		$limit = $this->readPositiveInt((string) ($_GET['limit'] ?? ($_POST['limit'] ?? '250')), 250, 1, 250);
		$refresh = $this->readBooleanFlag($_GET['refresh'] ?? ($_POST['refresh'] ?? null));
		$maxIpsPerRange = $this->readPositiveInt((string) ($_GET['max_ips_per_range'] ?? ($_POST['max_ips_per_range'] ?? '1000')), 1000, 1, 1000);

		try
			{
			$service = new IpCountryRangeService();
			$result = $service->loadCountryRanges($countryCode, $limit, $refresh, $maxIpsPerRange);
			$this->json($result, !empty($result['success']) ? 200 : 422);
			}
		catch (\Throwable $e)
			{
			$this->json([
				'success' => false,
				'message' => 'Não foi possível carregar as faixas de IPs por país. Aplique o schema SQL do webcrawler e tente novamente.',
				'error' => 'country_ranges_failed',
			], 500);
			}
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
	 * Authorize access to the IP webcrawler tool.
	 * @param string $ability Ability required for non-root users.
	 * @return void
	 **/
	private function authorizeIpWebCrawler(string $ability): void
		{
		$this->requireAuth();

		if (Authorization::isRoot() || Authorization::can($ability))
			{
			return;
			}

		if ($this->wantsJson() || stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/event-stream') !== false)
			{
			$this->json([
				'error' => 'forbidden',
				'message' => 'You do not have permission to access this resource',
				'ability' => $ability,
			], 403);
			}

		http_response_code(403);
		$this->view('errors/403', ['title' => 'Access denied', 'ability' => $ability]);
		exit;
		}

	/**
	 * Read IP webcrawler input values from the request.
	 * @return array<string, mixed>
	 **/
	private function readIpWebCrawlerInput(): array
		{
		$protocols = $_GET['protocols'] ?? ($_POST['protocols'] ?? []);

		return [
			'range_mode' => trim((string) ($_GET['range_mode'] ?? ($_POST['range_mode'] ?? 'amount'))),
			'start_ip' => trim((string) ($_GET['start_ip'] ?? ($_POST['start_ip'] ?? ''))),
			'end_ip' => trim((string) ($_GET['end_ip'] ?? ($_POST['end_ip'] ?? ''))),
			'ip_quantity' => trim((string) ($_GET['ip_quantity'] ?? ($_POST['ip_quantity'] ?? '1'))),
			'cidr' => trim((string) ($_GET['cidr'] ?? ($_POST['cidr'] ?? ''))),
			'ports' => trim((string) ($_GET['ports'] ?? ($_POST['ports'] ?? '80,443'))),
			'protocols' => $protocols,
			'start_paths' => trim((string) ($_GET['start_paths'] ?? ($_POST['start_paths'] ?? '/'))),
			'search_terms' => trim((string) ($_GET['search_terms'] ?? ($_POST['search_terms'] ?? ''))),
			'user_agent' => trim((string) ($_GET['user_agent'] ?? ($_POST['user_agent'] ?? ''))),
			'robots_mode' => trim((string) ($_GET['robots_mode'] ?? ($_POST['robots_mode'] ?? 'respect'))),
			'skip_cached_failures' => $_GET['skip_cached_failures'] ?? ($_POST['skip_cached_failures'] ?? '1'),
			'timeout_ms' => trim((string) ($_GET['timeout_ms'] ?? ($_POST['timeout_ms'] ?? '3000'))),
			'delay_ms' => trim((string) ($_GET['delay_ms'] ?? ($_POST['delay_ms'] ?? '250'))),
			'max_depth' => trim((string) ($_GET['max_depth'] ?? ($_POST['max_depth'] ?? '1'))),
			'max_pages_per_target' => trim((string) ($_GET['max_pages_per_target'] ?? ($_POST['max_pages_per_target'] ?? '10'))),
		];
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

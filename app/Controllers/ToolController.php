<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\EmailMxValidatorService;
use App\Services\ZipCodeCrawlerService;
use App\Services\ZipCodeNeighborhoodService;
use App\Services\ZipCodeSearchService;

class ToolController extends Controller
	{
	/*
	|--------------------------------------------------------------------------
	| Document Tool
	|--------------------------------------------------------------------------
	*/
	public function documents(): void
		{
		$this->requireAuth();

		$this->view('tools/documents', [
			'title' => 'Gerador e validador de documentos',
		]);
		}

	/*
	|--------------------------------------------------------------------------
	| E-mail Validation Tool
	|--------------------------------------------------------------------------
	*/
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

	/*
	|--------------------------------------------------------------------------
	| CEP Search Page
	|--------------------------------------------------------------------------
	*/
	public function zipCodeSearch(): void
		{
		$this->requireAuth();

		$this->view('tools/zip_code_search', [
			'title' => 'Pesquisa de CEP',
		]);
		}

	/*
	|--------------------------------------------------------------------------
	| Neighborhood Comparison Page
	|--------------------------------------------------------------------------
	*/
	public function zipCodeNeighborhoodSearch(): void
		{
		$this->requireAuth();

		$this->view('tools/zip_code_neighborhood', [
			'title' => 'Bairro e cidade por CEP',
		]);
		}

	/**
	 * Render the zip code crawler page.
	 */
	public function zipCodeCrawler(): void
		{
		$this->requireAuth();

		$this->view('tools/zip_code_crawler', [
			'title' => 'Webcrawler de CEPs',
		]);
		}

	/*
	|--------------------------------------------------------------------------
	| CEP Search Endpoint
	|--------------------------------------------------------------------------
	*/
	public function searchZipCode(): void
		{
		$this->requireAuth();

		$service = new ZipCodeSearchService();
		$result = $service->searchAndSave($this->readZipCodeSearchInput());
		$status = (int) ($result['status'] ?? 500);

		$this->json($result, $status);
		}

	/*
	|--------------------------------------------------------------------------
	| Neighborhood Comparison Endpoint
	|--------------------------------------------------------------------------
	*/
	public function compareZipCodeNeighborhood(): void
		{
		$this->requireAuth();

		$service = new ZipCodeNeighborhoodService();
		$result = $service->compareLookups($this->readZipCodeSearchInput());
		$status = (int) ($result['status'] ?? 500);

		$this->json($result, $status);
		}

	/*
	|--------------------------------------------------------------------------
	| Neighborhood Save Endpoint
	|--------------------------------------------------------------------------
	*/
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
	 */
	public function streamZipCodeCrawler(): void
		{
		$this->requireAuth();

		$startZipCode = $this->normalizeZipCodeInput((string) ($_GET['start_zip_code'] ?? ''));
		$endZipCode = $this->normalizeZipCodeInput((string) ($_GET['end_zip_code'] ?? ''));
		$delayMs = $this->readPositiveInt((string) ($_GET['delay_ms'] ?? '500'), 500, 0, 10000);
		$maxResults = $this->readPositiveInt((string) ($_GET['max_results'] ?? '100'), 100, 1, PHP_INT_MAX);
		$providerMode = $this->readCrawlerProviderMode((string) ($_GET['provider_mode'] ?? 'fallback'));

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

		foreach ($service->crawl($startZipCode, $endZipCode, $delayMs, $maxResults, $providerMode) as $payload)
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

	/*
	|--------------------------------------------------------------------------
	| Zip Code Crawler Save Endpoint
	|--------------------------------------------------------------------------
	*/
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

	/*
	|--------------------------------------------------------------------------
	| Zip Code Crawler Batch Save Endpoint
	|--------------------------------------------------------------------------
	*/
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

	/*
	|--------------------------------------------------------------------------
	| E-mail Validation Input Reader
	|--------------------------------------------------------------------------
	*/
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

	/*
	|--------------------------------------------------------------------------
	| CEP Search Input Reader
	|--------------------------------------------------------------------------
	*/
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

	/*
	|--------------------------------------------------------------------------
	| Neighborhood Selection Input Reader
	|--------------------------------------------------------------------------
	*/
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

	/*
	|--------------------------------------------------------------------------
	| Create City Flag Reader
	|--------------------------------------------------------------------------
	*/
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
	 *
	 * @param string $value Provider mode value.
	 * @return string
	 */
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
	 * Read a boolean-like flag.
	 *
	 * @param mixed $value Raw flag value.
	 * @return bool
	 */
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

	/*
	|--------------------------------------------------------------------------
	| Zip Code Crawler Save Input Reader
	|--------------------------------------------------------------------------
	*/
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

	/*
	|--------------------------------------------------------------------------
	| Zip Code Crawler Batch Save Input Reader
	|--------------------------------------------------------------------------
	*/
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
	 *
	 * @param string $value Raw zip code value.
	 * @return string
	 */
	private function normalizeZipCodeInput(string $value): string
		{
		return preg_replace('/\D+/', '', $value) ?? '';
		}

	/**
	 * Read a positive integer with bounds.
	 *
	 * @param string $value Raw numeric value.
	 * @param int $default Default value when parsing fails.
	 * @param int $min Minimum accepted value.
	 * @param int $max Maximum accepted value.
	 * @return int
	 */
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

<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Brazilian zip code crawler service with provider fallback.
 */
class ZipCodeCrawlerService
	{
	private const NOT_FOUND_FILE_NAME = 'zip_code_crawler_not_found.txt';
	private const STATE_ZIP_CODE_RANGES = [
		'AC' => [
			['start' => '69900000', 'end' => '69999999'],
		],
		'AL' => [
			['start' => '57000000', 'end' => '57999999'],
		],
		'AP' => [
			['start' => '68900000', 'end' => '68999999'],
		],
		'AM' => [
			['start' => '69000000', 'end' => '69299999'],
			['start' => '69400000', 'end' => '69899999'],
		],
		'BA' => [
			['start' => '40000000', 'end' => '48999999'],
		],
		'CE' => [
			['start' => '60000000', 'end' => '63999999'],
		],
		'DF' => [
			['start' => '70000000', 'end' => '72799999'],
			['start' => '73000000', 'end' => '73699999'],
		],
		'ES' => [
			['start' => '29000000', 'end' => '29999999'],
		],
		'GO' => [
			['start' => '72800000', 'end' => '72999999'],
			['start' => '73700000', 'end' => '76799999'],
		],
		'MA' => [
			['start' => '65000000', 'end' => '65999999'],
		],
		'MT' => [
			['start' => '78000000', 'end' => '78899999'],
		],
		'MS' => [
			['start' => '79000000', 'end' => '79999999'],
		],
		'MG' => [
			['start' => '30000000', 'end' => '39999999'],
		],
		'PA' => [
			['start' => '66000000', 'end' => '68899999'],
		],
		'PB' => [
			['start' => '58000000', 'end' => '58999999'],
		],
		'PR' => [
			['start' => '80000000', 'end' => '87999999'],
		],
		'PE' => [
			['start' => '50000000', 'end' => '56999999'],
		],
		'PI' => [
			['start' => '64000000', 'end' => '64999999'],
		],
		'RJ' => [
			['start' => '20000000', 'end' => '28999999'],
		],
		'RN' => [
			['start' => '59000000', 'end' => '59999999'],
		],
		'RS' => [
			['start' => '90000000', 'end' => '99999999'],
		],
		'RO' => [
			['start' => '76800000', 'end' => '76999999'],
		],
		'RR' => [
			['start' => '69300000', 'end' => '69399999'],
		],
		'SC' => [
			['start' => '88000000', 'end' => '89999999'],
		],
		'SP' => [
			['start' => '01000000', 'end' => '19999999'],
		],
		'SE' => [
			['start' => '49000000', 'end' => '49999999'],
		],
		'TO' => [
			['start' => '77000000', 'end' => '77999999'],
		],
	];

	private ViaCepService $viaCepService;
	private BrasilApiService $brasilApiService;
	private string $notFoundFilePath;
	private ?array $notFoundZipCodes = null;
	private ?PDO $db = null;

	/**
	 * Build the crawler service.
	 * @param string|null $notFoundFilePath Path used to persist zip codes not found.
	 **/
	public function __construct(?string $notFoundFilePath = null)
		{
		$this->viaCepService = new ViaCepService();
		$this->brasilApiService = new BrasilApiService();
		$this->notFoundFilePath = $notFoundFilePath ?: $this->defaultNotFoundFilePath();
		}

	/**
	 * Return configured zip code ranges by Brazilian state acronym.
	 * @return array<string, array<int, array{start: string, end: string}>>
	 **/
	public static function stateZipCodeRanges(): array
		{
		return self::STATE_ZIP_CODE_RANGES;
		}

	/**
	 * Return formatted zip code range options for a Brazilian state.
	 * @param string $stateAcronym State acronym.
	 * @return array<int, array<string, mixed>>
	 **/
	public static function zipCodeRangeOptionsForState(string $stateAcronym): array
		{
		$ranges = self::zipCodeRangesForState($stateAcronym);
		$options = [];

		foreach ($ranges as $range)
			{
			$options[] = [
				'start_digits' => $range['start'],
				'end_digits' => $range['end'],
				'start_zip_code' => self::formatZipCodeDigits($range['start']),
				'end_zip_code' => self::formatZipCodeDigits($range['end']),
				'total' => ((int) $range['end'] - (int) $range['start']) + 1,
			];
			}

		return $options;
		}

	/**
	 * Return the total number of zip codes covered by the ranges.
	 * @param array<int, array{start: string, end: string}> $ranges Zip code ranges.
	 * @return int
	 **/
	public static function countZipCodeRanges(array $ranges): int
		{
		$total = 0;

		foreach ($ranges as $range)
			{
			$total += ((int) $range['end'] - (int) $range['start']) + 1;
			}

		return $total;
		}

	/**
	 * Return configured zip code ranges for a Brazilian state.
	 * @param string $stateAcronym State acronym.
	 * @return array<int, array{start: string, end: string}>
	 **/
	public static function zipCodeRangesForState(string $stateAcronym): array
		{
		$stateAcronym = strtoupper(trim($stateAcronym));
		return self::STATE_ZIP_CODE_RANGES[$stateAcronym] ?? [];
		}

	/**
	 * Crawl a zip code range and yield progress payloads.
	 *
	 * @param string $startZipCode Start zip code value.
	 * @param string $endZipCode End zip code value.
	 * @param int $delayMs Delay between lookups in milliseconds.
	 * @param int $maxResults Maximum number of records to process.
	 * @param string $providerMode Provider selection mode.
	 * @return \Generator<int, array<string, mixed>, mixed, void>
	 */
	public function crawl(string $startZipCode, string $endZipCode, int $delayMs, int $maxResults, string $providerMode = 'fallback'): \Generator
		{
		$startDigits = $this->normalizeZipCode($startZipCode);
		$endDigits = $this->normalizeZipCode($endZipCode);

		if (strlen($startDigits) !== 8 || strlen($endDigits) !== 8)
			{
			yield $this->event('error', [
				'message' => 'Informe CEPs válidos com 8 dígitos.',
			]);
			return;
			}

		$startNumber = (int) $startDigits;
		$endNumber = (int) $endDigits;

		if ($startNumber > $endNumber)
			{
			$tmp = $startNumber;
			$startNumber = $endNumber;
			$endNumber = $tmp;
			}

		yield from $this->crawlRanges(
			[
				[
					'start' => str_pad((string) $startNumber, 8, '0', STR_PAD_LEFT),
					'end' => str_pad((string) $endNumber, 8, '0', STR_PAD_LEFT),
				],
			],
			$delayMs,
			$maxResults,
			$providerMode
		);
		}

	/**
	 * Crawl the configured zip code ranges for a Brazilian state.
	 * @param string $stateAcronym State acronym.
	 * @param int $delayMs Delay between lookups in milliseconds.
	 * @param int $maxResults Maximum number of records to process.
	 * @param string $providerMode Provider selection mode.
	 * @return \Generator<int, array<string, mixed>, mixed, void>
	 **/
	public function crawlState(string $stateAcronym, int $delayMs, int $maxResults, string $providerMode = 'fallback'): \Generator
		{
		$stateAcronym = strtoupper(trim($stateAcronym));
		$ranges = self::zipCodeRangesForState($stateAcronym);

		if (empty($ranges))
			{
			yield $this->event('error', [
				'message' => 'Selecione um estado brasileiro com faixa de CEP configurada.',
			]);
			return;
			}

		yield from $this->crawlRanges($ranges, $delayMs, $maxResults, $providerMode, $stateAcronym);
		}

	/**
	 * Crawl one or more zip code ranges and yield progress payloads.
	 * @param array<int, array{start: string, end: string}> $ranges Zip code ranges.
	 * @param int $delayMs Delay between lookups in milliseconds.
	 * @param int $maxResults Maximum number of records to process.
	 * @param string $providerMode Provider selection mode.
	 * @param string|null $stateAcronym Selected state acronym.
	 * @return \Generator<int, array<string, mixed>, mixed, void>
	 **/
	private function crawlRanges(array $ranges, int $delayMs, int $maxResults, string $providerMode = 'fallback', ?string $stateAcronym = null): \Generator
		{
		$delayMs = max(0, $delayMs);
		$totalAvailable = self::countZipCodeRanges($ranges);
		$totalToProcess = min($totalAvailable, max(1, $maxResults));
		$rangeOptions = $this->formatRangeEventData($ranges);
		$notFoundZipCodes = $this->loadNotFoundZipCodes();

		yield $this->event('start', [
			'state' => $stateAcronym,
			'start_zip_code' => $rangeOptions[0]['start_zip_code'] ?? '',
			'end_zip_code' => $rangeOptions[count($rangeOptions) - 1]['end_zip_code'] ?? '',
			'ranges' => $rangeOptions,
			'range_count' => count($rangeOptions),
			'total' => $totalToProcess,
			'total_available' => $totalAvailable,
			'not_found_file' => self::NOT_FOUND_FILE_NAME,
			'not_found_cache_count' => count($notFoundZipCodes),
			'delay_ms' => $delayMs,
			'provider_mode' => $this->normalizeProviderMode($providerMode),
		]);

		$processed = 0;
		$found = 0;
		$failed = 0;
		$skipped = 0;

		foreach ($ranges as $range)
			{
			$startNumber = (int) $range['start'];
			$endNumber = (int) $range['end'];

			for ($currentNumber = $startNumber; $currentNumber <= $endNumber; $currentNumber++)
				{
				if ($processed >= $totalToProcess)
					{
					break 2;
					}

				$zipCode = $this->formatZipCodeNumber($currentNumber);

				if ($this->hasRememberedNotFoundZipCode($zipCode))
					{
					$processed++;
					$failed++;
					$skipped++;

					yield $this->event('progress', [
						'position' => $processed,
						'total' => $totalToProcess,
						'found' => $found,
						'failed' => $failed,
						'skipped' => $skipped,
						'zip_code' => $zipCode,
						'state' => $stateAcronym,
						'result' => $this->buildSkippedNotFoundResult($zipCode),
					]);

					$this->waitBetweenLookups($delayMs);
					continue;
					}

				$result = $this->lookupByMode($zipCode, $providerMode);

				$processed++;
				if (!empty($result['success']))
					{
					$found++;
					}
				else
					{
					$failed++;
					if ($this->shouldRememberNotFoundZipCode($result))
						{
						$this->rememberNotFoundZipCode($zipCode);
						}
					}

				yield $this->event('progress', [
					'position' => $processed,
					'total' => $totalToProcess,
					'found' => $found,
					'failed' => $failed,
					'skipped' => $skipped,
					'zip_code' => $zipCode,
					'state' => $stateAcronym,
					'result' => $result,
				]);

				$this->waitBetweenLookups($delayMs);
				}
			}

		yield $this->event('done', [
			'state' => $stateAcronym,
			'processed' => $processed,
			'found' => $found,
			'failed' => $failed,
			'skipped' => $skipped,
			'total' => $totalToProcess,
			'total_available' => $totalAvailable,
		]);
		}

	/**
	 * Lookup a zip code using ViaCEP first and BrasilAPI as fallback.
	 *
	 * @param string $zipCode Zip code to search.
	 * @return array<string, mixed>
	 */
	private function lookupByMode(string $zipCode, string $providerMode): array
		{
		$providerMode = $this->normalizeProviderMode($providerMode);

		if ($providerMode === 'viacep')
			{
			return $this->lookupViaCepOnly($zipCode);
			}

		if ($providerMode === 'brasilapi')
			{
			return $this->lookupBrasilApiOnly($zipCode);
			}

		return $this->lookupWithFallback($zipCode);
		}

	/**
	 * Lookup a zip code using ViaCEP only.
	 *
	 * @param string $zipCode Zip code to search.
	 * @return array<string, mixed>
	 */
	private function lookupViaCepOnly(string $zipCode): array
		{
		$result = $this->viaCepService->lookup($zipCode);
		$attempt = $this->attemptPayload($result, 'viacep');

		if (!empty($result['success']))
			{
			return $this->buildSuccessResult('viacep', $result['data'] ?? [], [$attempt]);
			}

		return [
			'success' => false,
			'status'  => (int) ($result['status'] ?? 502),
			'source'  => 'viacep',
			'error'   => (string) ($result['error'] ?? 'lookup_failed'),
			'message' => (string) ($result['message'] ?? 'Não foi possível consultar o CEP no ViaCEP.'),
			'data'    => [
				'zip_code' => $zipCode,
				'attempts' => [$attempt],
			],
		];
		}

	/**
	 * Lookup a zip code using BrasilAPI only.
	 *
	 * @param string $zipCode Zip code to search.
	 * @return array<string, mixed>
	 */
	private function lookupBrasilApiOnly(string $zipCode): array
		{
		$result = $this->brasilApiService->lookup($zipCode);
		$attempt = $this->attemptPayload($result, 'brasilapi');

		if (!empty($result['success']))
			{
			return $this->buildSuccessResult('brasilapi', $result['data'] ?? [], [$attempt]);
			}

		return [
			'success' => false,
			'status'  => (int) ($result['status'] ?? 502),
			'source'  => 'brasilapi',
			'error'   => (string) ($result['error'] ?? 'lookup_failed'),
			'message' => (string) ($result['message'] ?? 'Não foi possível consultar o CEP na BrasilAPI.'),
			'data'    => [
				'zip_code' => $zipCode,
				'attempts' => [$attempt],
			],
		];
		}

	/**
	 * Lookup a zip code using ViaCEP first and BrasilAPI as fallback.
	 *
	 * @param string $zipCode Zip code to search.
	 * @return array<string, mixed>
	 */
	private function lookupWithFallback(string $zipCode): array
		{
		$viaCepResult = $this->viaCepService->lookup($zipCode);
		$viaCepAttempt = $this->attemptPayload($viaCepResult, 'viacep');

		if (!empty($viaCepResult['success']))
			{
			return $this->buildSuccessResult('viacep', $viaCepResult['data'] ?? [], [$viaCepAttempt]);
			}

		$brasilApiResult = $this->brasilApiService->lookup($zipCode);
		$brasilApiAttempt = $this->attemptPayload($brasilApiResult, 'brasilapi');

		if (!empty($brasilApiResult['success']))
			{
			return $this->buildSuccessResult('brasilapi', $brasilApiResult['data'] ?? [], [$viaCepAttempt, $brasilApiAttempt]);
			}

		$attempts = [$viaCepAttempt, $brasilApiAttempt];
		$isNotFound = $this->attemptsIndicateNotFound($attempts);

		return [
			'success' => false,
			'status' => $isNotFound ? 404 : (int) ($brasilApiResult['status'] ?? $viaCepResult['status'] ?? 502),
			'source' => '',
			'error' => $isNotFound ? 'zip_code_not_found' : 'lookup_failed',
			'message' => $isNotFound ? 'CEP não encontrado nos webservices.' : $this->firstErrorMessage($viaCepResult, $brasilApiResult),
			'data' => [
				'zip_code' => $zipCode,
				'attempts' => $attempts,
			],
		];
		}

	/**
	 * Build a skipped payload for a zip code already marked as not found.
	 * @param string $zipCode Zip code skipped by the crawler.
	 * @return array<string, mixed>
	 **/
	private function buildSkippedNotFoundResult(string $zipCode): array
		{
		return [
			'success' => false,
			'status' => 404,
			'source' => 'cache',
			'error' => 'zip_code_already_not_found',
			'message' => 'CEP já marcado como não encontrado; consulta ignorada.',
			'data' => [
				'zip_code' => $zipCode,
				'skipped' => true,
			],
		];
		}

	/**
	 * Decide whether a failed lookup should be persisted as not found.
	 * @param array<string, mixed> $result Lookup result payload.
	 * @return bool
	 **/
	private function shouldRememberNotFoundZipCode(array $result): bool
		{
		if (!empty($result['success']))
			{
			return false;
			}

		if ($this->isNotFoundSignal((string) ($result['error'] ?? ''), (int) ($result['status'] ?? 0)))
			{
			return true;
			}

		$attempts = $result['data']['attempts'] ?? [];
		if (!is_array($attempts))
			{
			return false;
			}

		return $this->attemptsIndicateNotFound($attempts);
		}

	/**
	 * Check whether all provider attempts indicate a not found zip code.
	 * @param array<int, mixed> $attempts Provider attempt payloads.
	 * @return bool
	 **/
	private function attemptsIndicateNotFound(array $attempts): bool
		{
		if (empty($attempts))
			{
			return false;
			}

		foreach ($attempts as $attempt)
			{
			if (!is_array($attempt))
				{
				return false;
				}

			if (!$this->isNotFoundSignal((string) ($attempt['error'] ?? ''), (int) ($attempt['status'] ?? 0)))
				{
				return false;
				}
			}

		return true;
		}

	/**
	 * Check whether a provider failure means the zip code does not exist.
	 * @param string $error Provider error key.
	 * @param int $status Provider response status.
	 * @return bool
	 **/
	private function isNotFoundSignal(string $error, int $status): bool
		{
		return $error === 'zip_code_not_found' || $status === 404;
		}

	/**
	 * Check whether the zip code is already persisted as not found.
	 * @param string $zipCode Zip code to check.
	 * @return bool
	 **/
	private function hasRememberedNotFoundZipCode(string $zipCode): bool
		{
		$digits = $this->normalizeZipCode($zipCode);
		if (strlen($digits) !== 8)
			{
			return false;
			}

		$notFoundZipCodes = $this->loadNotFoundZipCodes();
		return isset($notFoundZipCodes[$digits]);
		}

	/**
	 * Persist a zip code as not found.
	 * @param string $zipCode Zip code to persist.
	 * @return void
	 **/
	private function rememberNotFoundZipCode(string $zipCode): void
		{
		$digits = $this->normalizeZipCode($zipCode);
		if (strlen($digits) !== 8)
			{
			return;
			}

		$directory = dirname($this->notFoundFilePath);
		if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory))
			{
			return;
			}

		$handle = @fopen($this->notFoundFilePath, 'c+');
		if (!is_resource($handle))
			{
			return;
			}

		if (!flock($handle, LOCK_EX))
			{
			fclose($handle);
			return;
			}

		try
			{
			$contents = stream_get_contents($handle);
			$contents = is_string($contents) ? $contents : '';
			$notFoundZipCodes = $this->parseNotFoundZipCodeContents($contents);
			$notFoundZipCodes[$digits] = true;

			$sortedContents = $this->serializeNotFoundZipCodes($notFoundZipCodes);
			rewind($handle);
			ftruncate($handle, 0);
			$written = fwrite($handle, $sortedContents) !== false;

			if ($written)
				{
				$this->notFoundZipCodes = $notFoundZipCodes;
				}
			}
		finally
			{
			flock($handle, LOCK_UN);
			fclose($handle);
			}
		}

	/**
	 * Load zip codes previously persisted as not found.
	 * @return array<string, bool>
	 **/
	private function loadNotFoundZipCodes(): array
		{
		if ($this->notFoundZipCodes !== null)
			{
			return $this->notFoundZipCodes;
			}

		if (!is_file($this->notFoundFilePath))
			{
			$this->notFoundZipCodes = [];
			return $this->notFoundZipCodes;
			}

		$contents = @file_get_contents($this->notFoundFilePath);
		$this->notFoundZipCodes = $this->parseNotFoundZipCodeContents(is_string($contents) ? $contents : '');
		return $this->notFoundZipCodes;
		}

	/**
	 * Parse zip code file contents into a lookup set.
	 * @param string $contents Raw file contents.
	 * @return array<string, bool>
	 **/
	private function parseNotFoundZipCodeContents(string $contents): array
		{
		$notFoundZipCodes = [];
		$lines = preg_split('/\R+/', $contents) ?: [];

		foreach ($lines as $line)
			{
			$digits = $this->normalizeZipCode((string) $line);
			if (strlen($digits) === 8)
				{
				$notFoundZipCodes[$digits] = true;
				}
			}

		return $notFoundZipCodes;
		}

	/**
	 * Serialize zip codes in ascending order, one per line.
	 * @param array<string, bool> $notFoundZipCodes Parsed zip code set.
	 * @return string
	 **/
	private function serializeNotFoundZipCodes(array $notFoundZipCodes): string
		{
		$zipCodes = array_keys($notFoundZipCodes);
		sort($zipCodes, SORT_STRING);

		return empty($zipCodes) ? '' : implode(PHP_EOL, $zipCodes) . PHP_EOL;
		}

	/**
	 * Resolve the default not found zip code file path.
	 * @return string
	 **/
	private function defaultNotFoundFilePath(): string
		{
		$rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : dirname(__DIR__, 2);
		return rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . self::NOT_FOUND_FILE_NAME;
		}

	/**
	 * Wait before the next lookup using the selected delay.
	 * @param int $delayMs Delay in milliseconds.
	 * @return void
	 **/
	private function waitBetweenLookups(int $delayMs): void
		{
		if ($delayMs <= 0)
			{
			return;
			}

		usleep($delayMs * 1000);
		}

	/**
	 * Format range data for SSE and frontend options.
	 * @param array<int, array{start: string, end: string}> $ranges Zip code ranges.
	 * @return array<int, array<string, mixed>>
	 **/
	private function formatRangeEventData(array $ranges): array
		{
		$rangeData = [];

		foreach ($ranges as $range)
			{
			$rangeData[] = [
				'start_digits' => $range['start'],
				'end_digits' => $range['end'],
				'start_zip_code' => self::formatZipCodeDigits($range['start']),
				'end_zip_code' => self::formatZipCodeDigits($range['end']),
				'total' => ((int) $range['end'] - (int) $range['start']) + 1,
			];
			}

		return $rangeData;
		}

	/**
	 * Format an eight-digit zip code value.
	 * @param string $digits Zip code digits.
	 * @return string
	 **/
	private static function formatZipCodeDigits(string $digits): string
		{
		$digits = preg_replace('/\D+/', '', $digits) ?? '';
		if (strlen($digits) !== 8)
			{
			return $digits;
			}

		return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digits) ?? $digits;
		}

	/**
	 * Normalize the provider mode value.
	 *
	 * @param string $providerMode Raw provider mode.
	 * @return string
	 */
	private function normalizeProviderMode(string $providerMode): string
		{
		$providerMode = strtolower(trim($providerMode));
		if (!in_array($providerMode, ['viacep', 'brasilapi', 'fallback'], true))
			{
			return 'fallback';
			}

		return $providerMode;
		}

	/**
	 * Build a success payload for a lookup result.
	 *
	 * @param string $source Provider source key.
	 * @param array<string, mixed> $data Normalized payload.
	 * @param array<int, array<string, mixed>> $attempts Provider attempts.
	 * @return array<string, mixed>
	 */
	private function buildSuccessResult(string $source, array $data, array $attempts): array
		{
		$data['source'] = $source;
		$data['attempts'] = $attempts;
		$data['database_status'] = $this->buildDatabaseStatus($data);

		return [
			'success' => true,
			'status' => 200,
			'source' => $source,
			'message' => 'CEP localizado com sucesso.',
			'data' => $data,
		];
		}

	/**
	 * Build the local database status for a found zip code payload.
	 * @param array<string, mixed> $data Normalized provider payload.
	 * @return array<string, mixed>
	 **/
	private function buildDatabaseStatus(array $data): array
		{
		$cityName = trim((string) ($data['city'] ?? ''));
		$stateAcronym = strtoupper(trim((string) ($data['state'] ?? '')));
		$neighborhoodName = trim((string) ($data['neighborhood'] ?? ''));
		$status = $this->emptyDatabaseStatus();

		if ($cityName === '' || $stateAcronym === '')
			{
			$status['checked'] = false;
			$status['message'] = 'Cidade ou estado ausente no retorno do serviço.';
			return $status;
			}

		try
			{
			$state = $this->findStateByAcronym($stateAcronym);
			if (!$state)
				{
				$status['message'] = 'Estado não encontrado na base.';
				return $status;
				}

			$status['state_id'] = (int) $state['id'];

			$city = $this->findCityByNameAndStateId($cityName, (int) $state['id']);
			if (!$city)
				{
				$status['message'] = 'Cidade ainda não cadastrada.';
				return $status;
				}

			$status['city_exists'] = true;
			$status['city_id'] = (int) $city['id'];

			if ($neighborhoodName === '')
				{
				$status['already_exists'] = true;
				$status['message'] = 'Cidade já cadastrada; o serviço não retornou bairro.';
				return $status;
				}

			$neighborhood = $this->findNeighborhoodByNameAndCityId($neighborhoodName, (int) $city['id']);
			if (!$neighborhood)
				{
				$status['message'] = 'Bairro ainda não cadastrado para a cidade.';
				return $status;
				}

			$status['already_exists'] = true;
			$status['neighborhood_exists'] = true;
			$status['neighborhood_id'] = (int) $neighborhood['id'];
			$status['message'] = 'Cidade e bairro já cadastrados na base.';
			return $status;
			}
		catch (\Throwable $e)
			{
			$status['checked'] = false;
			$status['message'] = 'Não foi possível verificar a base local.';
			return $status;
			}
		}

	/**
	 * Return the default local database status shape.
	 * @return array<string, mixed>
	 **/
	private function emptyDatabaseStatus(): array
		{
		return [
			'checked' => true,
			'already_exists' => false,
			'city_exists' => false,
			'neighborhood_exists' => false,
			'state_id' => null,
			'city_id' => null,
			'neighborhood_id' => null,
			'message' => '',
		];
		}

	/**
	 * Return the manager database connection.
	 * @return PDO
	 **/
	private function db(): PDO
		{
		if ($this->db === null)
			{
			$this->db = Database::getInstance();
			}

		return $this->db;
		}

	/**
	 * Find a state by acronym.
	 * @param string $acronym State acronym.
	 * @return array<string, mixed>|null
	 **/
	private function findStateByAcronym(string $acronym): ?array
		{
		$acronym = strtoupper(trim($acronym));
		if ($acronym === '')
			{
			return null;
			}

		$stmt = $this->db()->prepare("SELECT State.id, State.name, State.acronym, State.country_id
		                              FROM states AS State
		                              WHERE UPPER(State.acronym) = :acronym
		                              LIMIT 1");
		$stmt->execute(['acronym' => $acronym]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
		}

	/**
	 * Find a city by name and state id.
	 * @param string $cityName City name.
	 * @param int $stateId State id.
	 * @return array<string, mixed>|null
	 **/
	private function findCityByNameAndStateId(string $cityName, int $stateId): ?array
		{
		$cityName = trim($cityName);
		if ($cityName === '' || $stateId <= 0)
			{
			return null;
			}

		$stmt = $this->db()->prepare("SELECT City.id, City.name, City.state_id, State.name AS state_name, State.acronym AS state_acronym
		                              FROM cities AS City
		                              INNER JOIN states AS State ON State.id = City.state_id
		                              WHERE City.state_id = :state_id
		                                AND LOWER(TRIM(City.name)) = LOWER(TRIM(:city_name))
		                              LIMIT 1");
		$stmt->execute([
			'state_id' => $stateId,
			'city_name' => $cityName,
		]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
		}

	/**
	 * Find a neighborhood by name and city id.
	 * @param string $neighborhoodName Neighborhood name.
	 * @param int $cityId City id.
	 * @return array<string, mixed>|null
	 **/
	private function findNeighborhoodByNameAndCityId(string $neighborhoodName, int $cityId): ?array
		{
		$neighborhoodName = trim($neighborhoodName);
		if ($neighborhoodName === '' || $cityId <= 0)
			{
			return null;
			}

		$stmt = $this->db()->prepare("SELECT Neighborhood.id, Neighborhood.name, Neighborhood.city_id
		                              FROM neighborhoods AS Neighborhood
		                              WHERE Neighborhood.city_id = :city_id
		                                AND LOWER(TRIM(Neighborhood.name)) = LOWER(TRIM(:neighborhood_name))
		                              LIMIT 1");
		$stmt->execute([
			'city_id' => $cityId,
			'neighborhood_name' => $neighborhoodName,
		]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
		}

	/**
	 * Build a normalized provider attempt record.
	 *
	 * @param array<string, mixed> $result Provider result.
	 * @param string $source Provider source key.
	 * @return array<string, mixed>
	 */
	private function attemptPayload(array $result, string $source): array
		{
		return [
			'source' => $source,
			'success' => !empty($result['success']),
			'status' => (int) ($result['status'] ?? 0),
			'error' => (string) ($result['error'] ?? ''),
			'message' => (string) ($result['message'] ?? ''),
		];
		}

	/**
	 * Normalize a zip code value.
	 *
	 * @param string $value Raw zip code value.
	 * @return string
	 */
	private function normalizeZipCode(string $value): string
		{
		return preg_replace('/\D+/', '', $value) ?? '';
		}

	/**
	 * Format a zip code value.
	 *
	 * @param string $value Numeric zip code value.
	 * @return string
	 */
	private function formatZipCode(string $value): string
		{
		$digits = $this->normalizeZipCode($value);
		if (strlen($digits) !== 8)
			{
			return $value;
			}

		return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digits) ?? $digits;
		}

	/**
	 * Format a numeric zip code preserving leading zeroes.
	 * @param int $value Numeric zip code value.
	 * @return string
	 **/
	private function formatZipCodeNumber(int $value): string
		{
		return $this->formatZipCode(str_pad((string) $value, 8, '0', STR_PAD_LEFT));
		}

	/**
	 * Create an SSE event payload.
	 *
	 * @param string $event Event name.
	 * @param array<string, mixed> $data Event payload.
	 * @return array<string, mixed>
	 */
	private function event(string $event, array $data): array
		{
		return [
			'event' => $event,
			'data' => $data,
		];
		}

	/**
	 * Resolve the best error message from both providers.
	 *
	 * @param array<string, mixed> $viaCepResult ViaCEP result payload.
	 * @param array<string, mixed> $brasilApiResult BrasilAPI result payload.
	 * @return string
	 */
	private function firstErrorMessage(array $viaCepResult, array $brasilApiResult): string
		{
		if (!empty($viaCepResult['message']))
			{
			return (string) $viaCepResult['message'];
			}

		if (!empty($brasilApiResult['message']))
			{
			return (string) $brasilApiResult['message'];
			}

		return 'Não foi possível consultar o CEP.';
		}
	}

?>

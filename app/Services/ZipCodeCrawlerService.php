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
	private const NOT_FOUND_JOURNAL_FILE_NAME = 'zip_code_crawler_not_found_pending.txt';
	private const NOT_FOUND_FLUSH_THRESHOLD = 5;
	private const DEFAULT_CONCURRENCY = 3;
	private const MAX_CONCURRENCY = 5;
	private const HTTP_TIMEOUT_SECONDS = 5;
	private const VIACEP_BASE_URL = 'https://viacep.com.br/ws/';
	private const BRASILAPI_BASE_URL = 'https://brasilapi.com.br/api/cep/v1/';
	private const STATE_NAMES = [
		'AC' => 'Acre',
		'AL' => 'Alagoas',
		'AM' => 'Amazonas',
		'AP' => 'Amapá',
		'BA' => 'Bahia',
		'CE' => 'Ceará',
		'DF' => 'Distrito Federal',
		'ES' => 'Espírito Santo',
		'GO' => 'Goiás',
		'MA' => 'Maranhão',
		'MG' => 'Minas Gerais',
		'MS' => 'Mato Grosso do Sul',
		'MT' => 'Mato Grosso',
		'PA' => 'Pará',
		'PB' => 'Paraíba',
		'PE' => 'Pernambuco',
		'PI' => 'Piauí',
		'PR' => 'Paraná',
		'RJ' => 'Rio de Janeiro',
		'RN' => 'Rio Grande do Norte',
		'RO' => 'Rondônia',
		'RR' => 'Roraima',
		'RS' => 'Rio Grande do Sul',
		'SC' => 'Santa Catarina',
		'SE' => 'Sergipe',
		'SP' => 'São Paulo',
		'TO' => 'Tocantins',
	];
	private const STATE_REGIONS = [
		'AC' => 'Norte',
		'AP' => 'Norte',
		'AM' => 'Norte',
		'PA' => 'Norte',
		'RO' => 'Norte',
		'RR' => 'Norte',
		'TO' => 'Norte',
		'AL' => 'Nordeste',
		'BA' => 'Nordeste',
		'CE' => 'Nordeste',
		'MA' => 'Nordeste',
		'PB' => 'Nordeste',
		'PE' => 'Nordeste',
		'PI' => 'Nordeste',
		'RN' => 'Nordeste',
		'SE' => 'Nordeste',
		'DF' => 'Centro-Oeste',
		'GO' => 'Centro-Oeste',
		'MT' => 'Centro-Oeste',
		'MS' => 'Centro-Oeste',
		'ES' => 'Sudeste',
		'MG' => 'Sudeste',
		'RJ' => 'Sudeste',
		'SP' => 'Sudeste',
		'PR' => 'Sul',
		'RS' => 'Sul',
		'SC' => 'Sul',
	];
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
	private string $notFoundJournalFilePath;
	private ?array $notFoundZipCodes = null;
	private bool $notFoundZipCodesDirty = false;
	private int $pendingNotFoundZipCodeWrites = 0;
	private ?PDO $db = null;
	private ?ZipCodeNeighborhoodService $zipCodeNeighborhoodService = null;
	private array $stateDatabaseCaches = [];

	/**
	 * Build the crawler service.
	 * @param string|null $notFoundFilePath Path used to persist zip codes not found.
	 **/
	public function __construct(?string $notFoundFilePath = null)
		{
		$this->viaCepService = new ViaCepService();
		$this->brasilApiService = new BrasilApiService();
		$this->notFoundFilePath = $notFoundFilePath ?: $this->defaultNotFoundFilePath();
		$this->notFoundJournalFilePath = $notFoundFilePath
			? $notFoundFilePath . '.pending'
			: $this->defaultNotFoundJournalFilePath();
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
	 * @param int $concurrency Number of simultaneous webservice lookups.
	 * @param bool $autoSaveCity Whether city persistence is enabled.
	 * @param bool $autoSaveNeighborhood Whether neighborhood persistence is enabled.
	 * @param bool $createCity Whether missing cities may be created.
	 * @return \Generator<int, array<string, mixed>, mixed, void>
	 */
	public function crawl(string $startZipCode, string $endZipCode, int $delayMs, int $maxResults, string $providerMode = 'fallback', int $concurrency = self::DEFAULT_CONCURRENCY, bool $autoSaveCity = true, bool $autoSaveNeighborhood = true, bool $createCity = false): \Generator
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
			$providerMode,
			null,
			$concurrency,
			$autoSaveCity,
			$autoSaveNeighborhood,
			$createCity
		);
		}

	/**
	 * Crawl the configured zip code ranges for a Brazilian state.
	 * @param string $stateAcronym State acronym.
	 * @param int $delayMs Delay between lookups in milliseconds.
	 * @param int $maxResults Maximum number of records to process.
	 * @param string $providerMode Provider selection mode.
	 * @param int $concurrency Number of simultaneous webservice lookups.
	 * @param bool $autoSaveCity Whether city persistence is enabled.
	 * @param bool $autoSaveNeighborhood Whether neighborhood persistence is enabled.
	 * @param bool $createCity Whether missing cities may be created.
	 * @return \Generator<int, array<string, mixed>, mixed, void>
	 **/
	public function crawlState(string $stateAcronym, int $delayMs, int $maxResults, string $providerMode = 'fallback', int $concurrency = self::DEFAULT_CONCURRENCY, bool $autoSaveCity = true, bool $autoSaveNeighborhood = true, bool $createCity = false): \Generator
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

		yield from $this->crawlRanges($ranges, $delayMs, $maxResults, $providerMode, $stateAcronym, $concurrency, $autoSaveCity, $autoSaveNeighborhood, $createCity);
		}

	/**
	 * Crawl one or more zip code ranges and yield progress payloads.
	 * @param array<int, array{start: string, end: string}> $ranges Zip code ranges.
	 * @param int $delayMs Delay between lookups in milliseconds.
	 * @param int $maxResults Maximum number of records to process.
	 * @param string $providerMode Provider selection mode.
	 * @param string|null $stateAcronym Selected state acronym.
	 * @param int $concurrency Number of simultaneous webservice lookups.
	 * @param bool $autoSaveCity Whether city persistence is enabled.
	 * @param bool $autoSaveNeighborhood Whether neighborhood persistence is enabled.
	 * @param bool $createCity Whether missing cities may be created.
	 * @return \Generator<int, array<string, mixed>, mixed, void>
	 **/
	private function crawlRanges(array $ranges, int $delayMs, int $maxResults, string $providerMode = 'fallback', ?string $stateAcronym = null, int $concurrency = self::DEFAULT_CONCURRENCY, bool $autoSaveCity = true, bool $autoSaveNeighborhood = true, bool $createCity = false): \Generator
		{
		$delayMs = max(0, $delayMs);
		$concurrency = $this->normalizeConcurrency($concurrency);
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
			'not_found_journal_file' => self::NOT_FOUND_JOURNAL_FILE_NAME,
			'not_found_cache_count' => count($notFoundZipCodes),
			'delay_ms' => $delayMs,
			'concurrency' => $concurrency,
			'looked_up' => 0,
			'auto_save_city' => $autoSaveCity,
			'auto_save_neighborhood' => $autoSaveNeighborhood,
			'create_city' => $createCity,
			'provider_mode' => $this->normalizeProviderMode($providerMode),
		]);

		$stats = [
			'processed' => 0,
			'found' => 0,
			'failed' => 0,
			'skipped' => 0,
			'looked_up' => 0,
		];
		$lookupBatch = [];

		try
			{
			foreach ($ranges as $range)
				{
				$startNumber = (int) $range['start'];
				$endNumber = (int) $range['end'];

				for ($currentNumber = $startNumber; $currentNumber <= $endNumber; $currentNumber++)
					{
					if ($stats['processed'] + count($lookupBatch) >= $totalToProcess)
						{
						break 2;
						}

					$zipCode = $this->formatZipCodeNumber($currentNumber);

					if ($this->hasRememberedNotFoundZipCode($zipCode))
						{
							if (!empty($lookupBatch))
								{
								yield from $this->processLookupBatch($lookupBatch, $providerMode, $delayMs, $stats, $totalToProcess, $stateAcronym, $autoSaveCity, $autoSaveNeighborhood, $createCity);
								$lookupBatch = [];
								}

						$stats['processed']++;
						$stats['failed']++;
						$stats['skipped']++;

						yield $this->event('progress', [
							'position' => $stats['processed'],
							'total' => $totalToProcess,
							'found' => $stats['found'],
							'failed' => $stats['failed'],
							'skipped' => $stats['skipped'],
							'looked_up' => $stats['looked_up'],
							'zip_code' => $zipCode,
							'state' => $stateAcronym,
							'result' => $this->buildSkippedNotFoundResult($zipCode),
						]);

						continue;
						}

					$lookupBatch[] = $zipCode;
					if (count($lookupBatch) >= $concurrency)
						{
						yield from $this->processLookupBatch($lookupBatch, $providerMode, $delayMs, $stats, $totalToProcess, $stateAcronym, $autoSaveCity, $autoSaveNeighborhood, $createCity);
						$lookupBatch = [];
						}
					}
				}

			if (!empty($lookupBatch))
				{
				yield from $this->processLookupBatch($lookupBatch, $providerMode, $delayMs, $stats, $totalToProcess, $stateAcronym, $autoSaveCity, $autoSaveNeighborhood, $createCity);
				}
			}
		finally
			{
			$this->flushNotFoundZipCodes();
			}

		yield $this->event('done', [
			'state' => $stateAcronym,
			'processed' => $stats['processed'],
			'found' => $stats['found'],
			'failed' => $stats['failed'],
			'skipped' => $stats['skipped'],
			'looked_up' => $stats['looked_up'],
			'total' => $totalToProcess,
			'total_available' => $totalAvailable,
			'concurrency' => $concurrency,
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
	 * Lookup a batch of zip codes and yield progress payloads in input order.
	 * @param array<int, string> $zipCodes Zip codes to search.
	 * @param string $providerMode Provider selection mode.
	 * @param int $delayMs Delay between lookup batches in milliseconds.
	 * @param array<string, int> $stats Mutable crawler counters.
	 * @param int $totalToProcess Total records to process.
	 * @param string|null $stateAcronym Selected state acronym.
	 * @param bool $autoSaveCity Whether city persistence is enabled.
	 * @param bool $autoSaveNeighborhood Whether neighborhood persistence is enabled.
	 * @param bool $createCity Whether missing cities may be created.
	 * @return \Generator<int, array<string, mixed>, mixed, void>
	 **/
	private function processLookupBatch(array $zipCodes, string $providerMode, int $delayMs, array &$stats, int $totalToProcess, ?string $stateAcronym, bool $autoSaveCity, bool $autoSaveNeighborhood, bool $createCity): \Generator
		{
		if (empty($zipCodes))
			{
			return;
			}

		$lookupStartedAt = microtime(true);
		$results = $this->lookupBatchByMode($zipCodes, $providerMode);
		$stats['looked_up'] += count($zipCodes);

		foreach ($zipCodes as $zipCode)
			{
			$result = $results[$zipCode] ?? $this->buildLookupFailureResult($zipCode);
			$result = $this->autoSaveLookupResult($result, $autoSaveCity, $autoSaveNeighborhood, $createCity);

			$stats['processed']++;
			if (empty($result['success']))
				{
				$stats['failed']++;
				if ($this->shouldRememberNotFoundZipCode($result, $providerMode))
					{
					$this->rememberNotFoundZipCode($zipCode);
					}
				}
			elseif ($this->lookupResultSavedNewRecord($result))
				{
				$stats['found']++;
				}

			yield $this->event('progress', [
				'position' => $stats['processed'],
				'total' => $totalToProcess,
				'found' => $stats['found'],
				'failed' => $stats['failed'],
				'skipped' => $stats['skipped'],
				'looked_up' => $stats['looked_up'],
				'zip_code' => $zipCode,
				'state' => $stateAcronym,
				'result' => $result,
			]);
			}

		$this->waitRemainingLookupDelay($delayMs, $lookupStartedAt);
		}

	/**
	 * Lookup multiple zip codes using the selected provider mode.
	 * @param array<int, string> $zipCodes Zip codes to search.
	 * @param string $providerMode Provider selection mode.
	 * @return array<string, array<string, mixed>>
	 **/
	private function lookupBatchByMode(array $zipCodes, string $providerMode): array
		{
		$providerMode = $this->normalizeProviderMode($providerMode);
		$zipCodes = array_values($zipCodes);

		if (count($zipCodes) <= 1 || !function_exists('curl_multi_init'))
			{
			$results = [];
			foreach ($zipCodes as $zipCode)
				{
				$results[$zipCode] = $this->lookupByMode($zipCode, $providerMode);
				}

			return $results;
			}

		if ($providerMode === 'viacep' || $providerMode === 'brasilapi')
			{
			return $this->lookupProviderBatch($zipCodes, $providerMode);
			}

		return $this->lookupFallbackBatch($zipCodes);
		}

	/**
	 * Lookup multiple zip codes using one provider.
	 * @param array<int, string> $zipCodes Zip codes to search.
	 * @param string $provider Provider key.
	 * @return array<string, array<string, mixed>>
	 **/
	private function lookupProviderBatch(array $zipCodes, string $provider): array
		{
		$rawResults = $this->lookupRawProviderBatch($zipCodes, $provider);
		$results = [];

		foreach ($zipCodes as $zipCode)
			{
			$rawResult = $rawResults[$zipCode] ?? $this->providerFailure($provider, 'lookup_failed', $this->providerLookupFailedMessage($provider), 502);
			$attempt = $this->attemptPayload($rawResult, $provider);

			if (!empty($rawResult['success']))
				{
				$results[$zipCode] = $this->buildSuccessResult($provider, $rawResult['data'] ?? [], [$attempt]);
				continue;
				}

			$results[$zipCode] = [
				'success' => false,
				'status' => (int) ($rawResult['status'] ?? 502),
				'source' => $provider,
				'error' => (string) ($rawResult['error'] ?? 'lookup_failed'),
				'message' => (string) ($rawResult['message'] ?? $this->providerLookupFailedMessage($provider)),
				'data' => [
					'zip_code' => $zipCode,
					'attempts' => [$attempt],
				],
			];
			}

		return $results;
		}

	/**
	 * Lookup multiple zip codes using ViaCEP first and BrasilAPI as fallback.
	 * @param array<int, string> $zipCodes Zip codes to search.
	 * @return array<string, array<string, mixed>>
	 **/
	private function lookupFallbackBatch(array $zipCodes): array
		{
		$viaCepResults = $this->lookupRawProviderBatch($zipCodes, 'viacep');
		$results = [];
		$fallbackZipCodes = [];
		$viaCepAttempts = [];

		foreach ($zipCodes as $zipCode)
			{
			$viaCepResult = $viaCepResults[$zipCode] ?? $this->providerFailure('viacep', 'lookup_failed', $this->providerLookupFailedMessage('viacep'), 502);
			$viaCepAttempt = $this->attemptPayload($viaCepResult, 'viacep');
			$viaCepAttempts[$zipCode] = $viaCepAttempt;

			if (!empty($viaCepResult['success']))
				{
				$results[$zipCode] = $this->buildSuccessResult('viacep', $viaCepResult['data'] ?? [], [$viaCepAttempt]);
				continue;
				}

			$fallbackZipCodes[] = $zipCode;
			}

		if (empty($fallbackZipCodes))
			{
			return $results;
			}

		$brasilApiResults = $this->lookupRawProviderBatch($fallbackZipCodes, 'brasilapi');
		foreach ($fallbackZipCodes as $zipCode)
			{
			$brasilApiResult = $brasilApiResults[$zipCode] ?? $this->providerFailure('brasilapi', 'lookup_failed', $this->providerLookupFailedMessage('brasilapi'), 502);
			$brasilApiAttempt = $this->attemptPayload($brasilApiResult, 'brasilapi');

			if (!empty($brasilApiResult['success']))
				{
				$results[$zipCode] = $this->buildSuccessResult('brasilapi', $brasilApiResult['data'] ?? [], [$viaCepAttempts[$zipCode], $brasilApiAttempt]);
				continue;
				}

			$results[$zipCode] = $this->buildFallbackFailureResult($zipCode, $viaCepAttempts[$zipCode], $brasilApiAttempt, $brasilApiResult);
			}

		return $results;
		}

	/**
	 * Run raw provider HTTP requests concurrently.
	 * @param array<int, string> $zipCodes Zip codes to search.
	 * @param string $provider Provider key.
	 * @return array<string, array<string, mixed>>
	 **/
	private function lookupRawProviderBatch(array $zipCodes, string $provider): array
		{
		if (!function_exists('curl_multi_init'))
			{
			$results = [];
			foreach ($zipCodes as $zipCode)
				{
				$results[$zipCode] = $provider === 'viacep'
					? $this->viaCepService->lookup($zipCode)
					: $this->brasilApiService->lookup($zipCode);
				}

			return $results;
			}

		$multiHandle = curl_multi_init();
		if ($multiHandle === false)
			{
			$results = [];
			foreach ($zipCodes as $zipCode)
				{
				$results[$zipCode] = $provider === 'viacep'
					? $this->viaCepService->lookup($zipCode)
					: $this->brasilApiService->lookup($zipCode);
				}

			return $results;
			}

		$handles = [];
		$results = [];

		foreach ($zipCodes as $zipCode)
			{
			$url = $this->providerUrl($provider, $zipCode);
			$handle = curl_init($url);
			if ($handle === false)
				{
				$results[$zipCode] = $this->providerFailure($provider, 'lookup_failed', $this->providerLookupFailedMessage($provider), 502);
				continue;
				}

			curl_setopt_array($handle, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => self::HTTP_TIMEOUT_SECONDS,
				CURLOPT_TIMEOUT => self::HTTP_TIMEOUT_SECONDS,
				CURLOPT_HTTPHEADER => ['Accept: application/json'],
			]);

			curl_multi_add_handle($multiHandle, $handle);
			$handles[] = [
				'zip_code' => $zipCode,
				'handle' => $handle,
			];
			}

		do
			{
			$status = curl_multi_exec($multiHandle, $running);
			if ($running)
				{
				$selected = curl_multi_select($multiHandle, 0.2);
				if ($selected <= 0)
					{
					usleep(10000);
					}
				}
			}
		while ($running && $status === CURLM_OK);

		foreach ($handles as $item)
			{
			$handle = $item['handle'];
			$zipCode = (string) $item['zip_code'];
			$body = curl_multi_getcontent($handle);
			$httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
			$errorNumber = curl_errno($handle);

			$results[$zipCode] = $this->parseProviderHttpResponse(
				$provider,
				$zipCode,
				$httpStatus,
				$errorNumber,
				is_string($body) ? $body : null
			);

			curl_multi_remove_handle($multiHandle, $handle);
			curl_close($handle);
			}

		curl_multi_close($multiHandle);
		return $results;
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
	 * Parse a provider HTTP response into the normalized raw result shape.
	 * @param string $provider Provider key.
	 * @param string $zipCode Zip code searched.
	 * @param int $httpStatus HTTP status code.
	 * @param int $errorNumber cURL error number.
	 * @param string|null $body Response body.
	 * @return array<string, mixed>
	 **/
	private function parseProviderHttpResponse(string $provider, string $zipCode, int $httpStatus, int $errorNumber, ?string $body): array
		{
		if ($errorNumber !== 0 || $httpStatus < 200 || $httpStatus >= 300 || $body === null)
			{
			if ($provider === 'brasilapi' && $httpStatus === 404)
				{
				return $this->providerFailure($provider, 'zip_code_not_found', 'CEP não encontrado na BrasilAPI.', 404);
				}

			return $this->providerFailure($provider, 'lookup_failed', $this->providerLookupFailedMessage($provider), 502);
			}

		$data = json_decode($body, true);
		if (!is_array($data))
			{
			return $this->providerFailure($provider, 'invalid_response', $this->providerInvalidResponseMessage($provider), 502);
			}

		if ($provider === 'viacep')
			{
			if (!empty($data['erro']))
				{
				return $this->providerFailure($provider, 'zip_code_not_found', 'CEP não encontrado no ViaCEP.', 404);
				}

			return [
				'success' => true,
				'status' => 200,
				'source' => 'viacep',
				'data' => $this->normalizeViaCepData($data),
			];
			}

		$normalized = $this->normalizeBrasilApiData($data);
		if ($normalized['zip_code'] === '')
			{
			return $this->providerFailure($provider, 'zip_code_not_found', 'CEP não encontrado na BrasilAPI.', 404);
			}

		return [
			'success' => true,
			'status' => 200,
			'source' => 'brasilapi',
			'data' => $normalized,
		];
		}

	/**
	 * Build a generic lookup failure result.
	 * @param string $zipCode Zip code searched.
	 * @return array<string, mixed>
	 **/
	private function buildLookupFailureResult(string $zipCode): array
		{
		return [
			'success' => false,
			'status' => 502,
			'source' => '',
			'error' => 'lookup_failed',
			'message' => 'Não foi possível consultar o CEP.',
			'data' => [
				'zip_code' => $zipCode,
				'attempts' => [],
			],
		];
		}

	/**
	 * Build a fallback failure result from provider attempts.
	 * @param string $zipCode Zip code searched.
	 * @param array<string, mixed> $viaCepAttempt ViaCEP attempt payload.
	 * @param array<string, mixed> $brasilApiAttempt BrasilAPI attempt payload.
	 * @param array<string, mixed> $brasilApiResult Raw BrasilAPI result.
	 * @return array<string, mixed>
	 **/
	private function buildFallbackFailureResult(string $zipCode, array $viaCepAttempt, array $brasilApiAttempt, array $brasilApiResult): array
		{
		$attempts = [$viaCepAttempt, $brasilApiAttempt];
		$isNotFound = $this->attemptsIndicateNotFound($attempts);
		$status = (int) (
			($brasilApiAttempt['status'] ?? 0)
			?: ($viaCepAttempt['status'] ?? 0)
			?: ($brasilApiResult['status'] ?? 0)
			?: 502
		);

		return [
			'success' => false,
			'status' => $isNotFound ? 404 : $status,
			'source' => '',
			'error' => $isNotFound ? 'zip_code_not_found' : 'lookup_failed',
			'message' => $isNotFound ? 'CEP não encontrado nos webservices.' : $this->firstAttemptMessage($attempts),
			'data' => [
				'zip_code' => $zipCode,
				'attempts' => $attempts,
			],
		];
		}

	/**
	 * Build a provider failure payload.
	 * @param string $provider Provider key.
	 * @param string $error Error key.
	 * @param string $message Error message.
	 * @param int $status Response status.
	 * @return array<string, mixed>
	 **/
	private function providerFailure(string $provider, string $error, string $message, int $status): array
		{
		return [
			'success' => false,
			'status' => $status,
			'source' => $provider,
			'error' => $error,
			'message' => $message,
		];
		}

	/**
	 * Build a provider URL for a zip code.
	 * @param string $provider Provider key.
	 * @param string $zipCode Zip code searched.
	 * @return string
	 **/
	private function providerUrl(string $provider, string $zipCode): string
		{
		$digits = $this->normalizeZipCode($zipCode);
		if ($provider === 'viacep')
			{
			return self::VIACEP_BASE_URL . $digits . '/json/';
			}

		return self::BRASILAPI_BASE_URL . $digits;
		}

	/**
	 * Return a provider lookup failure message.
	 * @param string $provider Provider key.
	 * @return string
	 **/
	private function providerLookupFailedMessage(string $provider): string
		{
		return $provider === 'viacep'
			? 'Não foi possível consultar o CEP no ViaCEP agora.'
			: 'Não foi possível consultar o CEP na BrasilAPI agora.';
		}

	/**
	 * Return a provider invalid response message.
	 * @param string $provider Provider key.
	 * @return string
	 **/
	private function providerInvalidResponseMessage(string $provider): string
		{
		return $provider === 'viacep'
			? 'A resposta do ViaCEP é inválida.'
			: 'A resposta da BrasilAPI é inválida.';
		}

	/**
	 * Normalize ViaCEP data.
	 * @param array<string, mixed> $data Raw ViaCEP data.
	 * @return array<string, string>
	 **/
	private function normalizeViaCepData(array $data): array
		{
		$state = strtoupper((string) ($data['uf'] ?? ''));

		return [
			'zip_code' => (string) ($data['cep'] ?? ''),
			'street' => (string) ($data['logradouro'] ?? ''),
			'complement' => (string) ($data['complemento'] ?? ''),
			'neighborhood' => (string) ($data['bairro'] ?? ''),
			'city' => (string) ($data['localidade'] ?? ''),
			'state' => $state,
			'state_name' => (string) ($data['estado'] ?? $state),
			'region' => (string) ($data['regiao'] ?? ''),
			'ibge' => (string) ($data['ibge'] ?? ''),
			'ddd' => (string) ($data['ddd'] ?? ''),
			'siafi' => (string) ($data['siafi'] ?? ''),
		];
		}

	/**
	 * Normalize BrasilAPI data.
	 * @param array<string, mixed> $data Raw BrasilAPI data.
	 * @return array<string, string>
	 **/
	private function normalizeBrasilApiData(array $data): array
		{
		$state = strtoupper((string) ($data['state'] ?? ''));

		return [
			'zip_code' => $this->formatZipCodeOrEmpty((string) ($data['cep'] ?? '')),
			'street' => (string) ($data['street'] ?? ''),
			'complement' => '',
			'neighborhood' => (string) ($data['neighborhood'] ?? ''),
			'city' => (string) ($data['city'] ?? ''),
			'state' => $state,
			'state_name' => self::STATE_NAMES[$state] ?? $state,
			'region' => self::STATE_REGIONS[$state] ?? '',
			'ibge' => (string) ($data['city_ibge_code'] ?? ''),
			'ddd' => (string) ($data['ddd'] ?? ''),
			'siafi' => '',
		];
		}

	/**
	 * Persist a found lookup result automatically when configured.
	 * @param array<string, mixed> $result Lookup result.
	 * @param bool $autoSaveCity Whether city persistence is enabled.
	 * @param bool $autoSaveNeighborhood Whether neighborhood persistence is enabled.
	 * @param bool $createCity Whether missing cities may be created.
	 * @return array<string, mixed>
	 **/
	private function autoSaveLookupResult(array $result, bool $autoSaveCity, bool $autoSaveNeighborhood, bool $createCity): array
		{
		if (empty($result['success']))
			{
			return $result;
			}

		if (!$autoSaveCity && !$autoSaveNeighborhood)
			{
			$result['data']['auto_save'] = [
				'enabled' => false,
				'attempted' => false,
				'success' => false,
				'skipped' => true,
				'created_new_record' => false,
				'message' => 'Salvamento automático desativado.',
			];
			return $result;
			}

		$data = is_array($result['data'] ?? null) ? $result['data'] : [];
		$databaseStatus = is_array($data['database_status'] ?? null) ? $data['database_status'] : [];
		if (!empty($databaseStatus['already_exists']))
			{
			$result['data']['auto_save'] = [
				'enabled' => true,
				'attempted' => false,
				'success' => true,
				'skipped' => true,
				'status' => 'already_exists',
				'created_new_record' => false,
				'message' => (string) ($databaseStatus['message'] ?? 'Cidade e bairro já cadastrados na base.'),
			];
			return $result;
			}

		if ($autoSaveNeighborhood && trim((string) ($data['neighborhood'] ?? '')) === '')
			{
			$result['data']['auto_save'] = [
				'enabled' => true,
				'attempted' => false,
				'success' => false,
				'skipped' => true,
				'status' => 'missing_neighborhood',
				'created_new_record' => false,
				'message' => 'O serviço não retornou bairro para salvar automaticamente.',
			];
			return $result;
			}

		$saveResult = $this->zipCodeNeighborhoodService()->persistLookupData(
			$data,
			$autoSaveCity,
			$autoSaveNeighborhood,
			$createCity
		);
		$saveSucceeded = !empty($saveResult['success']);
		$createdNewRecord = $saveSucceeded && $this->saveResultCreatedNewRecord($saveResult);
		$status = $saveSucceeded
			? ($createdNewRecord ? 'saved' : 'already_exists')
			: 'failed';

		$result['data']['auto_save'] = [
			'enabled' => true,
			'attempted' => true,
			'success' => $saveSucceeded,
			'skipped' => $saveSucceeded && !$createdNewRecord,
			'status' => $status,
			'created_new_record' => $createdNewRecord,
			'message' => (string) ($saveResult['message'] ?? ($saveSucceeded ? 'Bairro salvo automaticamente.' : 'Não foi possível salvar automaticamente.')),
			'result' => $saveResult['data'] ?? [],
		];

		if ($saveSucceeded)
			{
			$result['data']['database_status'] = $this->buildDatabaseStatus($result['data']);
			}

		return $result;
		}

	/**
	 * Check whether a lookup result created a new database record.
	 * @param array<string, mixed> $result Lookup result.
	 * @return bool
	 **/
	private function lookupResultSavedNewRecord(array $result): bool
		{
		$autoSave = $result['data']['auto_save'] ?? null;
		if (!is_array($autoSave))
			{
			return false;
			}

		return !empty($autoSave['success']) && !empty($autoSave['created_new_record']);
		}

	/**
	 * Check whether a persistence result created a new city or neighborhood.
	 * @param array<string, mixed> $saveResult Persistence result.
	 * @return bool
	 **/
	private function saveResultCreatedNewRecord(array $saveResult): bool
		{
		$data = is_array($saveResult['data'] ?? null) ? $saveResult['data'] : [];
		return !empty($data['city_created']) || !empty($data['neighborhood_created']);
		}

	/**
	 * Return the zip code neighborhood persistence service.
	 * @return ZipCodeNeighborhoodService
	 **/
	private function zipCodeNeighborhoodService(): ZipCodeNeighborhoodService
		{
		if ($this->zipCodeNeighborhoodService === null)
			{
			$this->zipCodeNeighborhoodService = new ZipCodeNeighborhoodService();
			}

		return $this->zipCodeNeighborhoodService;
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
	 * @param string $providerMode Provider selection mode.
	 * @return bool
	 **/
	private function shouldRememberNotFoundZipCode(array $result, string $providerMode): bool
		{
		if (!empty($result['success']))
			{
			return false;
			}

		$attempts = $result['data']['attempts'] ?? [];
		if (is_array($attempts) && !empty($attempts))
			{
			return $this->attemptsCanConfirmGlobalNotFound($attempts, $providerMode)
				&& $this->attemptsIndicateNotFound($attempts);
			}

		return $this->normalizeProviderMode($providerMode) === 'fallback'
			&& $this->isNotFoundSignal((string) ($result['error'] ?? ''), (int) ($result['status'] ?? 0));
		}
    
	/**
	 * Check whether the attempts can safely feed the global not found cache.
	 * @param array<int, mixed> $attempts Provider attempt payloads.
	 * @param string $providerMode Provider selection mode.
	 * @return bool
	 **/
	private function attemptsCanConfirmGlobalNotFound(array $attempts, string $providerMode): bool
		{
		if ($this->normalizeProviderMode($providerMode) !== 'fallback')
			{
			return false;
			}

		return $this->attemptsIncludeProvider($attempts, 'viacep')
			&& $this->attemptsIncludeProvider($attempts, 'brasilapi');
		}

	/**
	 * Check whether provider attempts include a specific provider.
	 * @param array<int, mixed> $attempts Provider attempt payloads.
	 * @param string $provider Provider key.
	 * @return bool
	 **/
	private function attemptsIncludeProvider(array $attempts, string $provider): bool
		{
		foreach ($attempts as $attempt)
			{
			if (!is_array($attempt))
				{
				continue;
				}

			if (strtolower((string) ($attempt['source'] ?? '')) === $provider)
				{
				return true;
				}
			}

		return false;
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

		$notFoundZipCodes = $this->loadNotFoundZipCodes();
		if (isset($notFoundZipCodes[$digits]))
			{
			return;
			}

		$this->notFoundZipCodes[$digits] = true;
		$this->notFoundZipCodesDirty = true;
		$this->pendingNotFoundZipCodeWrites++;
		$this->appendNotFoundZipCodeJournal($digits);

		if ($this->pendingNotFoundZipCodeWrites >= self::NOT_FOUND_FLUSH_THRESHOLD)
			{
			$this->flushNotFoundZipCodes();
			}
		}

	/**
	 * Flush pending not found zip codes to disk in ascending order.
	 * @return void
	 **/
	private function flushNotFoundZipCodes(): void
		{
		if (
			(!$this->notFoundZipCodesDirty || $this->notFoundZipCodes === null)
			&& !$this->fileHasContents($this->notFoundJournalFilePath)
		)
			{
			return;
			}

		$directory = dirname($this->notFoundFilePath);
		if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory))
			{
			return;
			}

		$journalHandle = @fopen($this->notFoundJournalFilePath, 'c+');
		if (!is_resource($journalHandle))
			{
			return;
			}

		if (!flock($journalHandle, LOCK_EX))
			{
			fclose($journalHandle);
			return;
			}

		$handle = @fopen($this->notFoundFilePath, 'c+');
		if (!is_resource($handle))
			{
			flock($journalHandle, LOCK_UN);
			fclose($journalHandle);
			return;
			}

		if (!flock($handle, LOCK_EX))
			{
			fclose($handle);
			flock($journalHandle, LOCK_UN);
			fclose($journalHandle);
			return;
			}

		try
			{
			$contents = stream_get_contents($handle);
			$contents = is_string($contents) ? $contents : '';
			rewind($journalHandle);
			$journalContents = stream_get_contents($journalHandle);
			$journalContents = is_string($journalContents) ? $journalContents : '';
			$notFoundZipCodes = $this->parseNotFoundZipCodeContents($contents);
			$journalZipCodes = $this->parseNotFoundZipCodeContents($journalContents);

			foreach ($journalZipCodes as $digits => $exists)
				{
				if ($exists)
					{
					$notFoundZipCodes[$digits] = true;
					}
				}

			$memoryNotFoundZipCodes = is_array($this->notFoundZipCodes) ? $this->notFoundZipCodes : [];
			foreach ($memoryNotFoundZipCodes as $digits => $exists)
				{
				if ($exists)
					{
					$notFoundZipCodes[$digits] = true;
					}
				}

			$sortedContents = $this->serializeNotFoundZipCodes($notFoundZipCodes);
			rewind($handle);
			ftruncate($handle, 0);
			$written = fwrite($handle, $sortedContents) !== false;

			if ($written)
				{
				$this->notFoundZipCodes = $notFoundZipCodes;
				$this->notFoundZipCodesDirty = false;
				$this->pendingNotFoundZipCodeWrites = 0;
				rewind($journalHandle);
				ftruncate($journalHandle, 0);
				fflush($journalHandle);
				}
			}
		finally
			{
			flock($handle, LOCK_UN);
			fclose($handle);
			flock($journalHandle, LOCK_UN);
			fclose($journalHandle);
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

		if (!is_file($this->notFoundFilePath) && !is_file($this->notFoundJournalFilePath))
			{
			$this->notFoundZipCodes = [];
			return $this->notFoundZipCodes;
			}

		$mainFileNeedsRewrite = false;
		$this->notFoundZipCodes = $this->loadNotFoundZipCodeFile($this->notFoundFilePath, $mainFileNeedsRewrite);

		$journalHasContents = $this->fileHasContents($this->notFoundJournalFilePath);
		$journalNeedsRewrite = false;
		$journalZipCodes = $journalHasContents
			? $this->loadNotFoundZipCodeFile($this->notFoundJournalFilePath, $journalNeedsRewrite)
			: [];

		foreach ($journalZipCodes as $digits => $exists)
			{
			if ($exists)
				{
				$this->notFoundZipCodes[$digits] = true;
				}
			}

		if ($mainFileNeedsRewrite || $journalHasContents || $journalNeedsRewrite)
			{
			$this->notFoundZipCodesDirty = true;
			$this->flushNotFoundZipCodes();
			}

		return $this->notFoundZipCodes;
		}

	/**
	 * Append a not found zip code to the recovery journal immediately.
	 * @param string $digits Normalized zip code digits.
	 * @return void
	 **/
	private function appendNotFoundZipCodeJournal(string $digits): void
		{
		$directory = dirname($this->notFoundJournalFilePath);
		if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory))
			{
			return;
			}

		$handle = @fopen($this->notFoundJournalFilePath, 'ab');
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
			fwrite($handle, $digits . PHP_EOL);
			fflush($handle);
			if (function_exists('fsync'))
				{
				@fsync($handle);
				}
			}
		finally
			{
			flock($handle, LOCK_UN);
			fclose($handle);
			}
		}

	/**
	 * Check whether a file exists and has content.
	 * @param string $path File path.
	 * @return bool
	 **/
	private function fileHasContents(string $path): bool
		{
		if (!is_file($path))
			{
			return false;
			}

		clearstatcache(true, $path);
		return ((int) @filesize($path)) > 0;
		}

	/**
	 * Load a not found zip code file using a streaming parser.
	 * @param string $path File path.
	 * @param bool $needsRewrite Whether the file should be rewritten normalized.
	 * @return array<string, bool>
	 **/
	private function loadNotFoundZipCodeFile(string $path, bool &$needsRewrite): array
		{
		$needsRewrite = false;
		$notFoundZipCodes = [];

		if (!is_file($path))
			{
			return $notFoundZipCodes;
			}

		$handle = @fopen($path, 'rb');
		if (!is_resource($handle))
			{
			return $notFoundZipCodes;
			}

		$previousDigits = null;

		try
			{
			while (($line = fgets($handle)) !== false)
				{
				$rawLine = rtrim($line, "\r\n");
				$digits = $this->normalizeNotFoundZipCodeLine($rawLine);

				if ($digits === '')
					{
					if (trim($rawLine) !== '')
						{
						$needsRewrite = true;
						}

					continue;
					}

				if ($rawLine !== $digits || isset($notFoundZipCodes[$digits]))
					{
					$needsRewrite = true;
					}

				if ($previousDigits !== null && strcmp($digits, $previousDigits) < 0)
					{
					$needsRewrite = true;
					}

				$notFoundZipCodes[$digits] = true;
				$previousDigits = $digits;
				}
			}
		finally
			{
			fclose($handle);
			}

		return $notFoundZipCodes;
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
			$digits = $this->normalizeNotFoundZipCodeLine((string) $line);
			if ($digits !== '')
				{
				$notFoundZipCodes[$digits] = true;
				}
			}

		return $notFoundZipCodes;
		}

	/**
	 * Normalize one not found zip code file line.
	 * @param string $line Raw file line.
	 * @return string
	 **/
	private function normalizeNotFoundZipCodeLine(string $line): string
		{
		$line = trim($line);
		if ($line === '')
			{
			return '';
			}

		if (strlen($line) === 8 && ctype_digit($line))
			{
			return $line;
			}

		$digits = preg_replace('/\D+/', '', $line) ?? '';
		return strlen($digits) === 8 ? $digits : '';
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
	 * Resolve the default not found zip code journal file path.
	 * @return string
	 **/
	private function defaultNotFoundJournalFilePath(): string
		{
		$rootPath = defined('ROOT_PATH') ? (string) ROOT_PATH : dirname(__DIR__, 2);
		return rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . self::NOT_FOUND_JOURNAL_FILE_NAME;
		}

	/**
	 * Wait only the remaining time before the next webservice lookup.
	 * @param int $delayMs Delay in milliseconds.
	 * @param float $lookupStartedAt Timestamp captured before the lookup.
	 * @return void
	 **/
	private function waitRemainingLookupDelay(int $delayMs, float $lookupStartedAt): void
		{
		if ($delayMs <= 0)
			{
			return;
			}

		$elapsedMs = (int) round((microtime(true) - $lookupStartedAt) * 1000);
		$remainingMs = $delayMs - $elapsedMs;
		if ($remainingMs <= 0)
			{
			return;
			}

		usleep($remainingMs * 1000);
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
	 * Normalize the concurrency value.
	 * @param int $concurrency Raw concurrency value.
	 * @return int
	 **/
	private function normalizeConcurrency(int $concurrency): int
		{
		return min(self::MAX_CONCURRENCY, max(1, $concurrency));
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
			$cache = $this->loadStateDatabaseCache($stateAcronym);
			if (empty($cache['checked']))
				{
				$status['checked'] = false;
				$status['message'] = (string) ($cache['message'] ?? 'Não foi possível verificar a base local.');
				return $status;
				}

			$state = $cache['state'] ?? null;
			if (!$state)
				{
				$status['message'] = 'Estado não encontrado na base.';
				return $status;
				}

			$status['state_id'] = (int) $state['id'];

			$cityKey = $this->normalizeLookupKey($cityName);
			$city = $cache['cities'][$cityKey] ?? null;
			if (!$city)
				{
				$freshCity = $this->findCityByNameAndStateId($cityName, (int) $state['id']);
				if ($freshCity)
					{
					$city = $this->cacheCityDatabaseRow($stateAcronym, $freshCity);
					}
				}

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

			$neighborhoodKey = $this->normalizeLookupKey($neighborhoodName);
			$neighborhood = $city['neighborhoods'][$neighborhoodKey] ?? null;
			if (!$neighborhood)
				{
				$freshNeighborhood = $this->findNeighborhoodByNameAndCityId($neighborhoodName, (int) $city['id']);
				if ($freshNeighborhood)
					{
					$neighborhood = $this->cacheNeighborhoodDatabaseRow($stateAcronym, $cityKey, $freshNeighborhood);
					}
				}

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
	 * Load cities and neighborhoods for one state into memory.
	 * @param string $stateAcronym State acronym.
	 * @return array<string, mixed>
	 **/
	private function loadStateDatabaseCache(string $stateAcronym): array
		{
		$stateAcronym = strtoupper(trim($stateAcronym));
		if ($stateAcronym === '')
			{
			return [
				'checked' => false,
				'message' => 'Estado ausente para verificação local.',
				'state' => null,
				'cities' => [],
			];
			}

		if (isset($this->stateDatabaseCaches[$stateAcronym]))
			{
			return $this->stateDatabaseCaches[$stateAcronym];
			}

		$cache = [
			'checked' => true,
			'message' => '',
			'state' => null,
			'cities' => [],
			'city_count' => 0,
			'neighborhood_count' => 0,
		];

		try
			{
			$stmt = $this->db()->prepare("SELECT State.id AS state_id, State.name AS state_name, State.acronym AS state_acronym,
			                                     City.id AS city_id, City.name AS city_name,
			                                     Neighborhood.id AS neighborhood_id, Neighborhood.name AS neighborhood_name
			                              FROM states AS State
			                              LEFT JOIN cities AS City ON City.state_id = State.id
			                              LEFT JOIN neighborhoods AS Neighborhood ON Neighborhood.city_id = City.id
			                              WHERE UPPER(State.acronym) = :acronym
			                              ORDER BY City.name ASC, Neighborhood.name ASC");
			$stmt->execute(['acronym' => $stateAcronym]);

			while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false)
				{
				if ($cache['state'] === null)
					{
					$cache['state'] = [
						'id' => (int) $row['state_id'],
						'name' => (string) $row['state_name'],
						'acronym' => (string) $row['state_acronym'],
					];
					}

				if (empty($row['city_id']))
					{
					continue;
					}

				$cityKey = $this->normalizeLookupKey((string) $row['city_name']);
				if (!isset($cache['cities'][$cityKey]))
					{
					$cache['cities'][$cityKey] = [
						'id' => (int) $row['city_id'],
						'name' => (string) $row['city_name'],
						'neighborhoods' => [],
					];
					}

				if (!empty($row['neighborhood_id']))
					{
					$neighborhoodKey = $this->normalizeLookupKey((string) $row['neighborhood_name']);
					$cache['cities'][$cityKey]['neighborhoods'][$neighborhoodKey] = [
						'id' => (int) $row['neighborhood_id'],
						'name' => (string) $row['neighborhood_name'],
						'city_id' => (int) $row['city_id'],
					];
					$cache['neighborhood_count']++;
					}
				}

			$cache['city_count'] = count($cache['cities']);
			}
		catch (\Throwable $e)
			{
			$cache['checked'] = false;
			$cache['message'] = 'Não foi possível carregar cidades e bairros da base local.';
			}

		$this->stateDatabaseCaches[$stateAcronym] = $cache;
		return $cache;
		}

	/**
	 * Add a city row to the state database cache.
	 * @param string $stateAcronym State acronym.
	 * @param array<string, mixed> $city City row.
	 * @return array<string, mixed>
	 **/
	private function cacheCityDatabaseRow(string $stateAcronym, array $city): array
		{
		$stateAcronym = strtoupper(trim($stateAcronym));
		$cityKey = $this->normalizeLookupKey((string) ($city['name'] ?? ''));
		if ($stateAcronym === '' || $cityKey === '')
			{
			return [];
			}

		if (!isset($this->stateDatabaseCaches[$stateAcronym]))
			{
			$this->stateDatabaseCaches[$stateAcronym] = [
				'checked' => true,
				'message' => '',
				'state' => null,
				'cities' => [],
				'city_count' => 0,
				'neighborhood_count' => 0,
			];
			}

		if (!isset($this->stateDatabaseCaches[$stateAcronym]['cities'][$cityKey]))
			{
			$this->stateDatabaseCaches[$stateAcronym]['cities'][$cityKey] = [
				'id' => (int) $city['id'],
				'name' => (string) $city['name'],
				'neighborhoods' => [],
			];
			$this->stateDatabaseCaches[$stateAcronym]['city_count'] = count($this->stateDatabaseCaches[$stateAcronym]['cities']);
			}

		return $this->stateDatabaseCaches[$stateAcronym]['cities'][$cityKey];
		}

	/**
	 * Add a neighborhood row to the state database cache.
	 * @param string $stateAcronym State acronym.
	 * @param string $cityKey Normalized city cache key.
	 * @param array<string, mixed> $neighborhood Neighborhood row.
	 * @return array<string, mixed>
	 **/
	private function cacheNeighborhoodDatabaseRow(string $stateAcronym, string $cityKey, array $neighborhood): array
		{
		$stateAcronym = strtoupper(trim($stateAcronym));
		$neighborhoodKey = $this->normalizeLookupKey((string) ($neighborhood['name'] ?? ''));
		if ($stateAcronym === '' || $cityKey === '' || $neighborhoodKey === '')
			{
			return [];
			}

		$this->stateDatabaseCaches[$stateAcronym]['cities'][$cityKey]['neighborhoods'][$neighborhoodKey] = [
			'id' => (int) $neighborhood['id'],
			'name' => (string) $neighborhood['name'],
			'city_id' => (int) $neighborhood['city_id'],
		];
		$this->stateDatabaseCaches[$stateAcronym]['neighborhood_count']++;

		return $this->stateDatabaseCaches[$stateAcronym]['cities'][$cityKey]['neighborhoods'][$neighborhoodKey];
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
	 * Normalize a text value for in-memory lookups.
	 * @param string $value Raw text value.
	 * @return string
	 **/
	private function normalizeLookupKey(string $value): string
		{
		$value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);
		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
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
	 * Format a zip code or return an empty value when invalid.
	 * @param string $value Raw zip code value.
	 * @return string
	 **/
	private function formatZipCodeOrEmpty(string $value): string
		{
		$digits = $this->normalizeZipCode($value);
		if (strlen($digits) !== 8)
			{
			return '';
			}

		return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digits) ?? '';
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

	/**
	 * Resolve the first available error message from provider attempts.
	 * @param array<int, array<string, mixed>> $attempts Provider attempt payloads.
	 * @return string
	 **/
	private function firstAttemptMessage(array $attempts): string
		{
		foreach ($attempts as $attempt)
			{
			if (!empty($attempt['message']))
				{
				return (string) $attempt['message'];
				}
			}

		return 'Não foi possível consultar o CEP.';
		}
	}

?>

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
	private const NOT_FOUND_RESUME_WINDOW = 5;
	private const DEFAULT_CONCURRENCY = 3;
	private const MAX_CONCURRENCY = 5;
	private const RANDOM_DELAY_MIN_MS = 250;
	private const RANDOM_DELAY_MAX_MS = 750;
	private const VIACEP_TRANSIENT_FAILURE_THRESHOLD = 1;
	private const VIACEP_COOLDOWN_SECONDS = 300;
	private const VIACEP_PROBE_ZIP_CODE = '01001000';
	private const DEFAULT_TIMEZONE = 'America/Sao_Paulo';
	private const HTTP_TIMEOUT_SECONDS = 5;
	private const HTTP_USER_AGENT = 'Manager ZipCodeCrawler/1.0';
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
	private array $activeNotFoundZipCodes = [];
	private int $viaCepTransientFailures = 0;
	private float $viaCepDisabledUntil = 0.0;
	private array $viaCepDisabledDiagnostics = [];

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
	 * Return zip code ranges ordered from the highest range to the lowest.
	 * @param array<int, array{start: string, end: string}> $ranges Zip code ranges.
	 * @return array<int, array{start: string, end: string}>
	 **/
	private function descendingZipCodeRanges(array $ranges): array
		{
		usort($ranges, static function (array $left, array $right): int
			{
			return strcmp($right['end'], $left['end']);
			});

		return array_values($ranges);
		}

	/**
	 * Iterate zip codes covered by ranges up to a processing limit.
	 * @param array<int, array{start: string, end: string}> $ranges Zip code ranges.
	 * @param int $limit Maximum number of zip codes to iterate.
	 * @return \Generator<int, string, mixed, void>
	 **/
	private function iterateZipCodeRanges(array $ranges, int $limit): \Generator
		{
		$iterated = 0;
		foreach ($ranges as $range)
			{
			$startNumber = (int) $range['start'];
			$endNumber = (int) $range['end'];

			for ($currentNumber = $endNumber; $currentNumber >= $startNumber; $currentNumber--)
				{
				if ($iterated >= $limit)
					{
					return;
					}

				$iterated++;
				yield $this->formatZipCodeNumber($currentNumber);
				}
			}
		}

	/**
	 * Resolve the restart zip code from the last not found records in range.
	 * @param array<string, bool> $notFoundZipCodes Preloaded not found zip code cache.
	 * @param array<int, array{start: string, end: string}> $ranges Zip code ranges.
	 * @return string|null
	 **/
	private function resolveNotFoundResumeStart(array $notFoundZipCodes, array $ranges): ?string
		{
		if (empty($notFoundZipCodes))
			{
			return null;
			}

		$zipCodes = array_keys($notFoundZipCodes);
		rsort($zipCodes, SORT_STRING);
		$zipCodesInRange = [];

		foreach ($zipCodes as $zipCode)
			{
			if ($this->zipCodeDigitsInRanges($zipCode, $ranges))
				{
				$zipCodesInRange[] = $zipCode;
				}
			}

		if (empty($zipCodesInRange))
			{
			return null;
			}

		$tail = array_slice($zipCodesInRange, -self::NOT_FOUND_RESUME_WINDOW);
		return (string) ($tail[0] ?? '');
		}

	/**
	 * Check whether normalized zip code digits are covered by any range.
	 * @param string $zipCodeDigits Normalized zip code digits.
	 * @param array<int, array{start: string, end: string}> $ranges Zip code ranges.
	 * @return bool
	 **/
	private function zipCodeDigitsInRanges(string $zipCodeDigits, array $ranges): bool
		{
		if (strlen($zipCodeDigits) !== 8)
			{
			return false;
			}

		foreach ($ranges as $range)
			{
			if ($zipCodeDigits >= $range['start'] && $zipCodeDigits <= $range['end'])
				{
				return true;
				}
			}

		return false;
		}

	/**
	 * Rotate ranges so processing starts from a specific zip code without dropping earlier gaps.
	 * @param array<int, array{start: string, end: string}> $ranges Zip code ranges.
	 * @param string $startDigits Normalized zip code digits.
	 * @return array<int, array{start: string, end: string}>
	 **/
	private function rotateZipCodeRangesFrom(array $ranges, string $startDigits): array
		{
		if (strlen($startDigits) !== 8)
			{
			return $this->descendingZipCodeRanges($ranges);
			}

		$ranges = $this->descendingZipCodeRanges($ranges);
		$rotatedRanges = [];
		$previousRanges = [];
		$foundStart = false;

		foreach ($ranges as $range)
			{
			if (!$foundStart && $startDigits >= $range['start'] && $startDigits <= $range['end'])
				{
				$rotatedRanges[] = [
					'start' => $range['start'],
					'end' => $startDigits,
				];

				if ($startDigits < $range['end'])
					{
					$previousRanges[] = [
						'start' => str_pad((string) (((int) $startDigits) + 1), 8, '0', STR_PAD_LEFT),
						'end' => $range['end'],
					];
					}

				$foundStart = true;
				continue;
				}

			if ($foundStart)
				{
				$rotatedRanges[] = $range;
				continue;
				}

			$previousRanges[] = $range;
			}

		if (!$foundStart)
			{
			return $this->descendingZipCodeRanges($ranges);
			}

		return array_values(array_merge($rotatedRanges, $previousRanges));
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
	 * @param string $delayMode Delay mode.
	 * @return \Generator<int, array<string, mixed>, mixed, void>
	 */
	public function crawl(string $startZipCode, string $endZipCode, int $delayMs, int $maxResults, string $providerMode = 'fallback', int $concurrency = self::DEFAULT_CONCURRENCY, bool $autoSaveCity = true, bool $autoSaveNeighborhood = true, bool $createCity = false, string $delayMode = 'fixed'): \Generator
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
			$createCity,
			$delayMode
		);
		}

	/**
	 * Crawl the configured zip code ranges for a Brazilian state.
	 * @param string $stateAcronym State acronym.
	 * @param int $delayMs Delay between lookups in milliseconds.
	 * @param int $maxResults Legacy limit ignored for state crawls.
	 * @param string $providerMode Provider selection mode.
	 * @param int $concurrency Number of simultaneous webservice lookups.
	 * @param bool $autoSaveCity Whether city persistence is enabled.
	 * @param bool $autoSaveNeighborhood Whether neighborhood persistence is enabled.
	 * @param bool $createCity Whether missing cities may be created.
	 * @param string $delayMode Delay mode.
	 * @return \Generator<int, array<string, mixed>, mixed, void>
	 **/
	public function crawlState(string $stateAcronym, int $delayMs, int $maxResults, string $providerMode = 'fallback', int $concurrency = self::DEFAULT_CONCURRENCY, bool $autoSaveCity = true, bool $autoSaveNeighborhood = true, bool $createCity = false, string $delayMode = 'fixed'): \Generator
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

		yield from $this->crawlRanges($ranges, $delayMs, self::countZipCodeRanges($ranges), $providerMode, $stateAcronym, $concurrency, $autoSaveCity, $autoSaveNeighborhood, $createCity, $delayMode);
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
	 * @param string $delayMode Delay mode.
	 * @return \Generator<int, array<string, mixed>, mixed, void>
	 **/
	private function crawlRanges(array $ranges, int $delayMs, int $maxResults, string $providerMode = 'fallback', ?string $stateAcronym = null, int $concurrency = self::DEFAULT_CONCURRENCY, bool $autoSaveCity = true, bool $autoSaveNeighborhood = true, bool $createCity = false, string $delayMode = 'fixed'): \Generator
		{
		$delayMs = max(0, $delayMs);
		$delayMode = $this->normalizeDelayMode($delayMode);
		$providerMode = $this->normalizeProviderMode($providerMode);
		$concurrency = $this->normalizeConcurrency($concurrency);

		$notFoundZipCodes = yield from $this->loadNotFoundZipCodesWithProgress();
		$this->activeNotFoundZipCodes = $notFoundZipCodes;

		$this->prepareViaCepAvailability($providerMode);
		$resumeStartDigits = $this->resolveNotFoundResumeStart($notFoundZipCodes, $ranges);
		$crawlRanges = $resumeStartDigits === null
			? $this->descendingZipCodeRanges($ranges)
			: $this->rotateZipCodeRangesFrom($ranges, $resumeStartDigits);
		$totalAvailable = self::countZipCodeRanges($crawlRanges);
		$totalToProcess = min($totalAvailable, max(1, $maxResults));
		$rangeOptions = $this->formatRangeEventData($crawlRanges);

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
			'not_found_resume_window' => self::NOT_FOUND_RESUME_WINDOW,
			'resume_start_zip_code' => $resumeStartDigits === null ? '' : self::formatZipCodeDigits($resumeStartDigits),
			'delay_mode' => $delayMode,
			'delay_ms' => $delayMs,
			'random_delay_min_ms' => self::RANDOM_DELAY_MIN_MS,
			'random_delay_max_ms' => self::RANDOM_DELAY_MAX_MS,
			'concurrency' => $concurrency,
			'looked_up' => 0,
			'lookup_elapsed_ms' => 0,
			'lookup_rate' => 0.0,
			'auto_save_city' => $autoSaveCity,
			'auto_save_neighborhood' => $autoSaveNeighborhood,
			'create_city' => $createCity,
			'provider_mode' => $providerMode,
			'viacep_available' => !$this->isViaCepTemporarilyDisabled(),
			'viacep_disabled_until' => $this->viaCepDisabledUntilText(),
			'viacep_disabled_until_timestamp' => $this->viaCepDisabledUntilTimestamp(),
		]);

		$stats = [
			'processed' => 0,
			'found' => 0,
			'failed' => 0,
			'skipped' => 0,
			'looked_up' => 0,
			'lookup_elapsed_ms' => 0,
		];
		$lookupBatch = [];

		try
			{
			foreach ($this->iterateZipCodeRanges($crawlRanges, $totalToProcess) as $zipCode)
				{
				if (!$this->isZipCodeInNotFoundCache($zipCode, $notFoundZipCodes))
					{
					continue;
					}

				$stats['processed']++;
				$stats['failed']++;
				$stats['skipped']++;

				yield $this->event('progress', [
					'position' => $stats['processed'],
					'processed' => $stats['processed'],
					'total' => $totalToProcess,
					'found' => $stats['found'],
					'failed' => $stats['failed'],
					'skipped' => $stats['skipped'],
					'looked_up' => $stats['looked_up'],
					'lookup_elapsed_ms' => $stats['lookup_elapsed_ms'],
					'lookup_rate' => $this->lookupRateForStats($stats),
					'zip_code' => $zipCode,
					'state' => $stateAcronym,
					'result' => $this->buildSkippedNotFoundResult($zipCode),
				]);
				}

			foreach ($this->iterateZipCodeRanges($crawlRanges, $totalToProcess) as $zipCode)
				{
				if ($this->isZipCodeInNotFoundCache($zipCode, $notFoundZipCodes))
					{
					continue;
					}

				$lookupBatch[] = $zipCode;
				if (count($lookupBatch) >= $concurrency)
					{
					yield from $this->processLookupBatch($lookupBatch, $notFoundZipCodes, $providerMode, $delayMs, $delayMode, $stats, $totalToProcess, $stateAcronym, $autoSaveCity, $autoSaveNeighborhood, $createCity);
					$lookupBatch = [];
					}
				}

			if (!empty($lookupBatch))
				{
				yield from $this->processLookupBatch($lookupBatch, $notFoundZipCodes, $providerMode, $delayMs, $delayMode, $stats, $totalToProcess, $stateAcronym, $autoSaveCity, $autoSaveNeighborhood, $createCity);
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
			'lookup_elapsed_ms' => $stats['lookup_elapsed_ms'],
			'lookup_rate' => $this->lookupRateForStats($stats),
			'total' => $totalToProcess,
			'total_available' => $totalAvailable,
			'concurrency' => $concurrency,
			'delay_mode' => $delayMode,
			'delay_ms' => $delayMs,
			'resume_start_zip_code' => $resumeStartDigits === null ? '' : self::formatZipCodeDigits($resumeStartDigits),
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
		if ($this->isZipCodeInNotFoundCache($zipCode, $this->activeNotFoundZipCodes))
			{
			return $this->buildSkippedNotFoundResult($zipCode);
			}

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
	 * @param array<string, bool> $notFoundZipCodes Preloaded not found zip code cache.
	 * @param string $providerMode Provider selection mode.
	 * @param int $delayMs Delay between lookup batches in milliseconds.
	 * @param string $delayMode Delay mode.
	 * @param array<string, int|float> $stats Mutable crawler counters.
	 * @param int $totalToProcess Total records to process.
	 * @param string|null $stateAcronym Selected state acronym.
	 * @param bool $autoSaveCity Whether city persistence is enabled.
	 * @param bool $autoSaveNeighborhood Whether neighborhood persistence is enabled.
	 * @param bool $createCity Whether missing cities may be created.
	 * @return \Generator<int, array<string, mixed>, mixed, void>
	 **/
	private function processLookupBatch(array $zipCodes, array &$notFoundZipCodes, string $providerMode, int $delayMs, string $delayMode, array &$stats, int $totalToProcess, ?string $stateAcronym, bool $autoSaveCity, bool $autoSaveNeighborhood, bool $createCity): \Generator
		{
		if (empty($zipCodes))
			{
			return;
			}

		$lookupZipCodes = [];
		$skippedResults = [];

		foreach ($zipCodes as $zipCode)
			{
			if ($this->isZipCodeInNotFoundCache($zipCode, $notFoundZipCodes))
				{
				$skippedResults[$zipCode] = $this->buildSkippedNotFoundResult($zipCode);
				continue;
				}

			$lookupZipCodes[] = $zipCode;
			}

		$lookupStartedAt = microtime(true);
		$results = [];
		$roundDelayMs = 0;
		if (!empty($lookupZipCodes))
			{
			$roundDelayMs = $this->resolveLookupDelayMs($delayMs, $delayMode);
			$results = $this->lookupBatchByMode($lookupZipCodes, $providerMode);
			$lookupElapsedMs = (int) max(1, round((microtime(true) - $lookupStartedAt) * 1000));
			$stats['looked_up'] += count($lookupZipCodes);
			$stats['lookup_elapsed_ms'] += $lookupElapsedMs;
			}

		foreach ($zipCodes as $zipCode)
			{
			$result = $skippedResults[$zipCode] ?? null;

			$stats['processed']++;
			if ($result !== null)
				{
				$stats['failed']++;
				$stats['skipped']++;
				}
			else
				{
				$result = $results[$zipCode] ?? $this->buildLookupFailureResult($zipCode);
				$result = $this->autoSaveLookupResult($result, $autoSaveCity, $autoSaveNeighborhood, $createCity);

				if (empty($result['success']))
					{
					$stats['failed']++;
					if ($this->shouldRememberNotFoundZipCode($result, $providerMode))
						{
						$this->rememberNotFoundZipCode($zipCode);
						$this->addZipCodeToNotFoundCache($zipCode, $notFoundZipCodes);
						}
					}
				elseif ($this->lookupResultSavedNewRecord($result))
					{
					$stats['found']++;
					}
				}

			yield $this->event('progress', [
				'position' => $stats['processed'],
				'processed' => $stats['processed'],
				'total' => $totalToProcess,
				'found' => $stats['found'],
				'failed' => $stats['failed'],
				'skipped' => $stats['skipped'],
				'looked_up' => $stats['looked_up'],
				'lookup_elapsed_ms' => $stats['lookup_elapsed_ms'],
				'lookup_rate' => $this->lookupRateForStats($stats),
				'delay_mode' => $delayMode,
				'round_delay_ms' => $roundDelayMs,
				'zip_code' => $zipCode,
				'state' => $stateAcronym,
				'result' => $result,
			]);
			}

		if (!empty($lookupZipCodes))
			{
			$this->waitRemainingLookupDelay($roundDelayMs, $lookupStartedAt);
			}
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
		$skippedResults = [];
		$lookupZipCodes = [];

		foreach ($zipCodes as $zipCode)
			{
			if ($this->isZipCodeInNotFoundCache($zipCode, $this->activeNotFoundZipCodes))
				{
				$skippedResults[$zipCode] = $this->buildSkippedNotFoundResult($zipCode);
				continue;
				}

			$lookupZipCodes[] = $zipCode;
			}

		if (empty($lookupZipCodes))
			{
			return $skippedResults;
			}

		if (count($lookupZipCodes) <= 1 || !function_exists('curl_multi_init'))
			{
			$results = $skippedResults;
			foreach ($lookupZipCodes as $zipCode)
				{
				$results[$zipCode] = $this->lookupByMode($zipCode, $providerMode);
				}

			return $results;
			}

		if ($providerMode === 'viacep' || $providerMode === 'brasilapi')
			{
			return $skippedResults + $this->lookupProviderBatch($lookupZipCodes, $providerMode);
			}

		return $skippedResults + $this->lookupFallbackBatch($lookupZipCodes);
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
		if ($provider === 'viacep')
			{
			return $this->lookupRawProviderSequentialBatch($zipCodes, $provider);
			}

		if (!function_exists('curl_multi_init'))
			{
			return $this->lookupRawProviderSequentialBatch($zipCodes, $provider);
			}

		$multiHandle = curl_multi_init();
		if ($multiHandle === false)
			{
			return $this->lookupRawProviderSequentialBatch($zipCodes, $provider);
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

			$options = [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => self::HTTP_TIMEOUT_SECONDS,
				CURLOPT_TIMEOUT => self::HTTP_TIMEOUT_SECONDS,
				CURLOPT_USERAGENT => self::HTTP_USER_AGENT,
				CURLOPT_HTTPHEADER => ['Accept: application/json'],
			];

			if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4'))
				{
				$options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
				}

			curl_setopt_array($handle, $options);

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
			$errorMessage = $errorNumber !== 0 ? curl_error($handle) : '';

			if ($httpStatus === 0 && $errorNumber === 0)
				{
				$results[$zipCode] = $this->lookupProviderSequential($zipCode, $provider);
				}
			else
				{
				$results[$zipCode] = $this->parseProviderHttpResponse(
					$provider,
					$zipCode,
					$httpStatus,
					$errorNumber,
					$errorMessage,
					is_string($body) ? $body : null
				);
				}

			curl_multi_remove_handle($multiHandle, $handle);
			if (PHP_VERSION_ID < 80500)
				{
				curl_close($handle);
				}
			}

		curl_multi_close($multiHandle);
		return $results;
		}

	/**
	 * Run provider HTTP requests sequentially.
	 * @param array<int, string> $zipCodes Zip codes to search.
	 * @param string $provider Provider key.
	 * @return array<string, array<string, mixed>>
	 **/
	private function lookupRawProviderSequentialBatch(array $zipCodes, string $provider): array
		{
		$results = [];
		foreach ($zipCodes as $zipCode)
			{
			$results[$zipCode] = $this->lookupProviderSequential($zipCode, $provider);
			}

		return $results;
		}

	/**
	 * Lookup one zip code through the selected provider service.
	 * @param string $zipCode Zip code to search.
	 * @param string $provider Provider key.
	 * @return array<string, mixed>
	 **/
	private function lookupProviderSequential(string $zipCode, string $provider): array
		{
		if ($provider !== 'viacep')
			{
			return $this->brasilApiService->lookup($zipCode);
			}

		if ($this->isViaCepTemporarilyDisabled())
			{
			return $this->providerTemporarilyDisabledFailure('viacep', $zipCode);
			}

		$result = $this->viaCepService->lookup($zipCode);
		$this->recordViaCepLookupResult($result);
		return $result;
		}

	/**
	 * Probe ViaCEP once before a crawl that may use it.
	 * @param string $providerMode Selected provider mode.
	 * @return void
	 **/
	private function prepareViaCepAvailability(string $providerMode): void
		{
		if ($providerMode === 'brasilapi' || $this->isViaCepTemporarilyDisabled())
			{
			return;
			}

		$result = $this->viaCepService->lookup(self::VIACEP_PROBE_ZIP_CODE);
		$this->recordViaCepLookupResult($result);
		}

	/**
	 * Check whether ViaCEP is in temporary cooldown.
	 * @return bool
	 **/
	private function isViaCepTemporarilyDisabled(): bool
		{
		if ($this->viaCepDisabledUntil <= 0)
			{
			return false;
			}

		if (microtime(true) < $this->viaCepDisabledUntil)
			{
			return true;
			}

		$this->viaCepDisabledUntil = 0.0;
		$this->viaCepTransientFailures = 0;
		$this->viaCepDisabledDiagnostics = [];
		return false;
		}

	/**
	 * Record a ViaCEP result and pause future calls when transport keeps failing.
	 * @param array<string, mixed> $result ViaCEP lookup result.
	 * @return void
	 **/
	private function recordViaCepLookupResult(array $result): void
		{
		if (!empty($result['success']))
			{
			$this->viaCepTransientFailures = 0;
			return;
			}

		if (!$this->isTransientProviderFailure($result))
			{
			$this->viaCepTransientFailures = 0;
			return;
			}

		$this->viaCepTransientFailures++;
		if ($this->viaCepTransientFailures >= self::VIACEP_TRANSIENT_FAILURE_THRESHOLD)
			{
			$diagnostics = is_array($result['diagnostics'] ?? null) ? $result['diagnostics'] : [];
			$this->disableViaCepTemporarily($diagnostics);
			}
		}

	/**
	 * Pause ViaCEP calls for the current crawler request.
	 * @param array<string, mixed> $diagnostics Last transport failure diagnostics.
	 * @return void
	 **/
	private function disableViaCepTemporarily(array $diagnostics = []): void
		{
		$this->viaCepDisabledUntil = microtime(true) + self::VIACEP_COOLDOWN_SECONDS;
		$this->viaCepDisabledDiagnostics = $diagnostics;
		}

	/**
	 * Build a provider failure for a temporarily disabled provider.
	 * @param string $provider Provider key.
	 * @param string $zipCode Zip code that would be searched.
	 * @return array<string, mixed>
	 **/
	private function providerTemporarilyDisabledFailure(string $provider, string $zipCode): array
		{
		$provider = strtolower($provider);
		$digits = $this->normalizeZipCode($zipCode);
		$diagnostics = [
			'request_zip_code' => $digits,
			'request_url' => $digits === '' ? '' : $this->providerUrl($provider, $digits),
			'http_status' => 0,
			'curl_errno' => 0,
			'curl_error' => '',
			'cooldown_seconds' => self::VIACEP_COOLDOWN_SECONDS,
			'disabled_until' => $this->viaCepDisabledUntilText(),
			'disabled_until_timestamp' => $this->viaCepDisabledUntilTimestamp(),
		];

		if (!empty($this->viaCepDisabledDiagnostics))
			{
			$diagnostics['last_failure'] = $this->viaCepDisabledDiagnostics;
			}

		return $this->providerFailure($provider, 'provider_temporarily_disabled', 'ViaCEP pausado temporariamente por falhas de conexão.', 0, $diagnostics);
		}

	/**
	 * Check whether a provider failure came from a transient transport problem.
	 * @param array<string, mixed> $result Provider lookup result.
	 * @return bool
	 **/
	private function isTransientProviderFailure(array $result): bool
		{
		if (!empty($result['success']) || (string) ($result['error'] ?? '') === 'provider_temporarily_disabled')
			{
			return false;
			}

		$diagnostics = is_array($result['diagnostics'] ?? null) ? $result['diagnostics'] : [];
		$curlErrorNumber = (int) ($diagnostics['curl_errno'] ?? 0);
		$httpStatus = (int) ($diagnostics['http_status'] ?? 0);
		$status = (int) ($result['status'] ?? 0);

		if (in_array($curlErrorNumber, [6, 7, 28, 35, 52, 56], true))
			{
			return true;
			}

		return $status === 0 || $status >= 500 || $httpStatus >= 500 || ($httpStatus === 0 && $status >= 500);
		}

	/**
	 * Return the formatted ViaCEP cooldown deadline.
	 * @return string
	 **/
	private function viaCepDisabledUntilText(): string
		{
		$timestamp = $this->viaCepDisabledUntilTimestamp();
		if ($timestamp <= 0)
			{
			return '';
			}

		return (new \DateTimeImmutable('@' . $timestamp))
			->setTimezone($this->applicationTimeZone())
			->format('H:i:s');
		}

	/**
	 * Return the ViaCEP cooldown deadline as a Unix timestamp.
	 * @return int
	 **/
	private function viaCepDisabledUntilTimestamp(): int
		{
		if (!$this->isViaCepTemporarilyDisabled())
			{
			return 0;
			}

		return (int) ceil($this->viaCepDisabledUntil);
		}

	/**
	 * Return the application timezone used for server formatted times.
	 * @return \DateTimeZone
	 **/
	private function applicationTimeZone(): \DateTimeZone
		{
		$timezoneName = (string) (getenv('APP_TIMEZONE') ?: self::DEFAULT_TIMEZONE);

		try
			{
			return new \DateTimeZone($timezoneName);
			}
		catch (\Exception $exception)
			{
			return new \DateTimeZone(self::DEFAULT_TIMEZONE);
			}
		}

	/**
	 * Lookup a zip code using ViaCEP only.
	 *
	 * @param string $zipCode Zip code to search.
	 * @return array<string, mixed>
	 */
	private function lookupViaCepOnly(string $zipCode): array
		{
		$result = $this->lookupProviderSequential($zipCode, 'viacep');
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
		$viaCepResult = $this->lookupProviderSequential($zipCode, 'viacep');
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
		$hasNotFound = $isNotFound || $this->attemptsIncludeNotFound($attempts);

		return [
			'success' => false,
			'status' => $hasNotFound ? 404 : (int) ($brasilApiResult['status'] ?? $viaCepResult['status'] ?? 502),
			'source' => '',
			'error' => $hasNotFound ? 'zip_code_not_found' : 'lookup_failed',
			'message' => $hasNotFound ? 'CEP não encontrado nos webservices consultados.' : $this->firstErrorMessage($viaCepResult, $brasilApiResult),
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
	 * @param string $errorMessage cURL error message.
	 * @param string|null $body Response body.
	 * @return array<string, mixed>
	 **/
	private function parseProviderHttpResponse(string $provider, string $zipCode, int $httpStatus, int $errorNumber, string $errorMessage, ?string $body): array
		{
		if ($errorNumber !== 0 || $httpStatus < 200 || $httpStatus >= 300 || $body === null)
			{
			if ($provider === 'brasilapi' && $httpStatus === 404)
				{
				return $this->providerFailure($provider, 'zip_code_not_found', 'CEP não encontrado na BrasilAPI.', 404);
				}

			return $this->providerFailure(
				$provider,
				'lookup_failed',
				$this->providerLookupFailedMessage($provider),
				502,
				$this->providerFailureDiagnostics($provider, $zipCode, $httpStatus, $errorNumber, $errorMessage)
			);
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
		$hasNotFound = $isNotFound || $this->attemptsIncludeNotFound($attempts);
		$status = (int) (
			($brasilApiAttempt['status'] ?? 0)
			?: ($viaCepAttempt['status'] ?? 0)
			?: ($brasilApiResult['status'] ?? 0)
			?: 502
		);

		return [
			'success' => false,
			'status' => $hasNotFound ? 404 : $status,
			'source' => '',
			'error' => $hasNotFound ? 'zip_code_not_found' : 'lookup_failed',
			'message' => $hasNotFound ? 'CEP não encontrado nos webservices consultados.' : $this->firstAttemptMessage($attempts),
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
	 * @param array<string, mixed> $diagnostics Provider transport diagnostics.
	 * @return array<string, mixed>
	 **/
	private function providerFailure(string $provider, string $error, string $message, int $status, array $diagnostics = []): array
		{
		$payload = [
			'success' => false,
			'status' => $status,
			'source' => $provider,
			'error' => $error,
			'message' => $message,
		];

		if (!empty($diagnostics))
			{
			$payload['diagnostics'] = $diagnostics;
			}

		return $payload;
		}

	/**
	 * Build provider transport diagnostics.
	 * @param string $provider Provider key.
	 * @param string $zipCode Zip code searched.
	 * @param int $httpStatus HTTP status code.
	 * @param int $errorNumber cURL error number.
	 * @param string $errorMessage cURL error message.
	 * @return array<string, mixed>
	 **/
	private function providerFailureDiagnostics(string $provider, string $zipCode, int $httpStatus, int $errorNumber, string $errorMessage): array
		{
		$digits = $this->normalizeZipCode($zipCode);
		return [
			'request_zip_code' => $digits,
			'request_url' => $this->providerUrl($provider, $digits),
			'http_status' => $httpStatus,
			'curl_errno' => $errorNumber,
			'curl_error' => $errorMessage,
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

		if ((string) ($result['error'] ?? '') === 'zip_code_already_not_found')
			{
			return false;
			}

		$providerMode = $this->normalizeProviderMode($providerMode);
		$attempts = $result['data']['attempts'] ?? [];
		if (is_array($attempts) && !empty($attempts))
			{
			if ($providerMode === 'fallback')
				{
				return $this->attemptsCanConfirmGlobalNotFound($attempts, $providerMode)
					&& ($this->attemptsIndicateNotFound($attempts) || $this->attemptsIncludeNotFound($attempts));
				}

			return $this->attemptsIncludeProvider($attempts, $providerMode)
				&& $this->attemptsIndicateNotFound($attempts);
			}

		return $this->isProviderModeSource($providerMode, (string) ($result['source'] ?? ''))
			&& $this->isNotFoundSignal((string) ($result['error'] ?? ''), (int) ($result['status'] ?? 0));
		}

	/**
	 * Check whether a result source matches the selected provider mode.
	 * @param string $providerMode Selected provider mode.
	 * @param string $source Result source key.
	 * @return bool
	 **/
	private function isProviderModeSource(string $providerMode, string $source): bool
		{
		return $providerMode === 'fallback' || strtolower($source) === $providerMode;
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
	 * Check whether at least one provider attempt indicates a not found zip code.
	 * @param array<int, mixed> $attempts Provider attempt payloads.
	 * @return bool
	 **/
	private function attemptsIncludeNotFound(array $attempts): bool
		{
		foreach ($attempts as $attempt)
			{
			if (!is_array($attempt))
				{
				continue;
				}

			if ($this->isNotFoundSignal((string) ($attempt['error'] ?? ''), (int) ($attempt['status'] ?? 0)))
				{
				return true;
				}
			}

		return false;
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
	 * Check whether a zip code exists in an already loaded not found cache.
	 * @param string $zipCode Zip code to check.
	 * @param array<string, bool> $notFoundZipCodes Preloaded not found zip code cache.
	 * @return bool
	 **/
	private function isZipCodeInNotFoundCache(string $zipCode, array $notFoundZipCodes): bool
		{
		$digits = $this->normalizeZipCode($zipCode);
		return strlen($digits) === 8 && isset($notFoundZipCodes[$digits]);
		}

	/**
	 * Add a zip code to the already loaded not found cache.
	 * @param string $zipCode Zip code to add.
	 * @param array<string, bool> $notFoundZipCodes Preloaded not found zip code cache.
	 * @return void
	 **/
	private function addZipCodeToNotFoundCache(string $zipCode, array &$notFoundZipCodes): void
		{
		$digits = $this->normalizeZipCode($zipCode);
		if (strlen($digits) !== 8)
			{
			return;
			}

		$notFoundZipCodes[$digits] = true;
		$this->activeNotFoundZipCodes[$digits] = true;
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
	 * Flush pending not found zip codes to disk in descending order.
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
			}

		return $this->notFoundZipCodes;
		}

	/**
	 * Load not found zip codes and report cache progress through SSE events.
	 * @return \Generator<int, array<string, mixed>, mixed, array<string, bool>>
	 **/
	private function loadNotFoundZipCodesWithProgress(): \Generator
		{
		$startedAt = microtime(true);
		$cacheFileSize = $this->notFoundCacheFileSize();

		yield $this->cacheEvent('reading', 'running', [
			'percent' => $cacheFileSize > 0 ? 0 : 100,
			'message' => 'Lendo arquivo de CEPs não encontrados...',
		]);

		if ($this->notFoundZipCodes !== null)
			{
			yield $this->cacheEvent('loaded', 'done', [
				'percent' => 100,
				'count' => count($this->notFoundZipCodes),
				'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
				'message' => 'Arquivo de CEPs não encontrados já estava carregado em memória.',
			]);

			return $this->notFoundZipCodes;
			}

		if (!is_file($this->notFoundFilePath) && !is_file($this->notFoundJournalFilePath))
			{
			$this->notFoundZipCodes = [];
			yield $this->cacheEvent('loaded', 'done', [
				'percent' => 100,
				'count' => 0,
				'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
				'message' => 'Nenhum arquivo de CEPs não encontrados foi localizado.',
			]);

			return $this->notFoundZipCodes;
			}

		$mainStartedAt = microtime(true);
		yield $this->cacheEvent('reading_main', 'running', [
			'percent' => 20,
			'message' => 'Lendo arquivo principal de CEPs não encontrados...',
		]);

		$mainFileNeedsRewrite = false;
		$this->notFoundZipCodes = $this->loadNotFoundZipCodeFile($this->notFoundFilePath, $mainFileNeedsRewrite);

		yield $this->cacheEvent('main_loaded', 'running', [
			'percent' => 65,
			'count' => count($this->notFoundZipCodes),
			'elapsed_ms' => $this->elapsedMilliseconds($mainStartedAt),
			'message' => 'Arquivo principal de CEPs não encontrados carregado.',
		]);

		$journalHasContents = $this->fileHasContents($this->notFoundJournalFilePath);
		$journalNeedsRewrite = false;
		if ($journalHasContents)
			{
			$journalStartedAt = microtime(true);
			yield $this->cacheEvent('reading_journal', 'running', [
				'percent' => 75,
				'message' => 'Lendo CEPs pendentes de sincronização...',
			]);

			$journalZipCodes = $this->loadNotFoundZipCodeFile($this->notFoundJournalFilePath, $journalNeedsRewrite);
			foreach ($journalZipCodes as $digits => $exists)
				{
				if ($exists)
					{
					$this->notFoundZipCodes[$digits] = true;
					}
				}

			yield $this->cacheEvent('journal_loaded', 'running', [
				'percent' => 90,
				'count' => count($journalZipCodes),
				'total_count' => count($this->notFoundZipCodes),
				'elapsed_ms' => $this->elapsedMilliseconds($journalStartedAt),
				'message' => 'CEPs pendentes de sincronização carregados.',
			]);
			}

		if ($mainFileNeedsRewrite || $journalHasContents || $journalNeedsRewrite)
			{
			$this->notFoundZipCodesDirty = true;
			yield $this->cacheEvent('normalization_pending', 'running', [
				'percent' => 95,
				'count' => count($this->notFoundZipCodes),
				'message' => 'Cache carregado; sincronização do TXT será feita sem bloquear o início da pesquisa.',
			]);
			}

		yield $this->cacheEvent('loaded', 'done', [
			'percent' => 100,
			'count' => count($this->notFoundZipCodes),
			'elapsed_ms' => $this->elapsedMilliseconds($startedAt),
			'message' => 'Arquivo de CEPs não encontrados carregado.',
		]);

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

		if (!is_file($path))
			{
			return [];
			}

		$contents = @file_get_contents($path);
		if (!is_string($contents) || $contents === '')
			{
			return [];
			}

		$notFoundZipCodes = $this->loadNormalizedNotFoundZipCodeContents($contents, $needsRewrite);
		if (!$needsRewrite || !empty($notFoundZipCodes))
			{
			return $notFoundZipCodes;
			}

		return $this->parseNotFoundZipCodeContents($contents);
		}

	/**
	 * Load already normalized not found zip code contents using the fast path.
	 * @param string $contents Raw file contents.
	 * @param bool $needsRewrite Whether the file should be rewritten normalized.
	 * @return array<string, bool>
	 **/
	private function loadNormalizedNotFoundZipCodeContents(string $contents, bool &$needsRewrite): array
		{
		$needsRewrite = false;
		$normalizedContents = str_replace(["\r\n", "\r"], "\n", $contents);
		if ($normalizedContents !== $contents)
			{
			$needsRewrite = true;
			return [];
			}

		$contents = rtrim($contents, "\n");

		if ($contents === '')
			{
			return [];
			}

		$newLineCount = substr_count($contents, "\n");
		$lineCount = $newLineCount + 1;
		$expectedLength = ($lineCount * 8) + $newLineCount;
		if (strspn($contents, "0123456789\n") !== strlen($contents) || strlen($contents) !== $expectedLength)
			{
			$needsRewrite = true;
			return [];
			}

		$lines = explode("\n", $contents);
		$notFoundZipCodes = array_fill_keys($lines, true);
		if (count($lines) > 1 && strcmp($lines[0], $lines[count($lines) - 1]) < 0)
			{
			$needsRewrite = true;
			}

		if (count($notFoundZipCodes) !== count($lines))
			{
			$needsRewrite = true;
			}

		return $notFoundZipCodes;
		}

	/**
	 * Return the total size of not found cache files.
	 * @return int
	 **/
	private function notFoundCacheFileSize(): int
		{
		return $this->safeFileSize($this->notFoundFilePath) + $this->safeFileSize($this->notFoundJournalFilePath);
		}

	/**
	 * Return a file size without raising warnings.
	 * @param string $path File path.
	 * @return int
	 **/
	private function safeFileSize(string $path): int
		{
		if (!is_file($path))
			{
			return 0;
			}

		clearstatcache(true, $path);
		$size = @filesize($path);
		return is_int($size) ? max(0, $size) : 0;
		}

	/**
	 * Format a byte count for display.
	 * @param int $bytes Byte count.
	 * @return string
	 **/
	private function formatByteCount(int $bytes): string
		{
		if ($bytes < 1024)
			{
			return $bytes . ' B';
			}

		if ($bytes < 1048576)
			{
			return number_format($bytes / 1024, 1, ',', '.') . ' KB';
			}

		return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
		}

	/**
	 * Build a cache progress SSE event.
	 * @param string $stage Cache loading stage.
	 * @param string $status Stage status.
	 * @param array<string, mixed> $data Additional event data.
	 * @return array<string, mixed>
	 **/
	private function cacheEvent(string $stage, string $status, array $data = []): array
		{
		$cacheFileSize = $this->notFoundCacheFileSize();
		return $this->event('cache', array_merge([
			'stage' => $stage,
			'status' => $status,
			'file' => self::NOT_FOUND_FILE_NAME,
			'journal_file' => self::NOT_FOUND_JOURNAL_FILE_NAME,
			'bytes' => $cacheFileSize,
			'formatted_size' => $this->formatByteCount($cacheFileSize),
		], $data));
		}

	/**
	 * Return elapsed milliseconds since a timestamp.
	 * @param float $startedAt Start timestamp from microtime.
	 * @return int
	 **/
	private function elapsedMilliseconds(float $startedAt): int
		{
		return (int) max(1, round((microtime(true) - $startedAt) * 1000));
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
	 * Serialize zip codes in descending order, one per line.
	 * @param array<string, bool> $notFoundZipCodes Parsed zip code set.
	 * @return string
	 **/
	private function serializeNotFoundZipCodes(array $notFoundZipCodes): string
		{
		$zipCodes = array_keys($notFoundZipCodes);
		rsort($zipCodes, SORT_STRING);

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
	 * Calculate the zip code lookup rate using active service time.
	 * @param array<string, int|float> $stats Mutable crawler counters.
	 * @return float
	 **/
	private function lookupRateForStats(array $stats): float
		{
		$lookedUp = (int) ($stats['looked_up'] ?? 0);
		$lookupElapsedMs = (int) ($stats['lookup_elapsed_ms'] ?? 0);
		if ($lookedUp <= 0 || $lookupElapsedMs <= 0)
			{
			return 0.0;
			}

		return $lookedUp / ($lookupElapsedMs / 1000);
		}

	/**
	 * Resolve the delay to use for the current lookup round.
	 * @param int $delayMs Fixed delay in milliseconds.
	 * @param string $delayMode Delay mode.
	 * @return int
	 **/
	private function resolveLookupDelayMs(int $delayMs, string $delayMode): int
		{
		if ($this->normalizeDelayMode($delayMode) === 'random')
			{
			return mt_rand(self::RANDOM_DELAY_MIN_MS, self::RANDOM_DELAY_MAX_MS);
			}

		return max(0, $delayMs);
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
				'start_digits' => $range['end'],
				'end_digits' => $range['start'],
				'start_zip_code' => self::formatZipCodeDigits($range['end']),
				'end_zip_code' => self::formatZipCodeDigits($range['start']),
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
	 * Normalize the delay mode value.
	 * @param string $delayMode Raw delay mode.
	 * @return string
	 **/
	private function normalizeDelayMode(string $delayMode): string
		{
		$delayMode = strtolower(trim($delayMode));
		return in_array($delayMode, ['fixed', 'random'], true) ? $delayMode : 'fixed';
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
		$zipCode = (string) ($data['zip_code'] ?? '');
		if ($this->isZipCodeInNotFoundCache($zipCode, $this->activeNotFoundZipCodes))
			{
			return $this->buildSkippedNotFoundResult($zipCode);
			}

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
		$attempt = [
			'source' => $source,
			'success' => !empty($result['success']),
			'status' => (int) ($result['status'] ?? 0),
			'error' => (string) ($result['error'] ?? ''),
			'message' => (string) ($result['message'] ?? ''),
		];

		if (is_array($result['diagnostics'] ?? null))
			{
			$attempt['diagnostics'] = $result['diagnostics'];
			}

		return $attempt;
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

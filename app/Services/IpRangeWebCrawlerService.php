<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Country;
use Generator;
use PDO;
use PDOException;
use Throwable;

/**
 * Crawls IPv4 ranges over HTTP and HTTPS and stores reachable pages.
 **/
class IpRangeWebCrawlerService
	{
	private const EXECUTIONS_TABLE = 'webcrawler_ip_range_executions';
	private const RESULTS_TABLE = 'webcrawler_ip_range_results';
	private const RANGES_TABLE = 'webcrawler_ip_ranges';
	private const DEFAULT_MAX_IPS_PER_EXECUTION = PHP_INT_MAX;
	private const MAX_CONCURRENCY = 200;
	private const MAX_RESPONSE_BYTES = 1048576;
	private const DEFAULT_TIMEOUT_MS = 3000;
	private const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';
	private const COUNTRY_IMPORT_TIMEOUT_SECONDS = 20;
	private const COUNTRY_SOURCE_URLS = [
		'arin' => 'https://ftp.arin.net/pub/stats/arin/delegated-arin-extended-latest',
		'ripencc' => 'https://ftp.ripe.net/pub/stats/ripencc/delegated-ripencc-extended-latest',
		'apnic' => 'https://ftp.apnic.net/stats/apnic/delegated-apnic-extended-latest',
		'lacnic' => 'https://ftp.lacnic.net/pub/stats/lacnic/delegated-lacnic-extended-latest',
		'afrinic' => 'https://ftp.afrinic.net/pub/stats/afrinic/delegated-afrinic-extended-latest',
	];
	private const COUNTRY_NAMES = [
		'AR' => 'Argentina',
		'AU' => 'Austrália',
		'BR' => 'Brasil',
		'CA' => 'Canadá',
		'CL' => 'Chile',
		'CN' => 'China',
		'DE' => 'Alemanha',
		'ES' => 'Espanha',
		'FR' => 'França',
		'GB' => 'Reino Unido',
		'IN' => 'Índia',
		'IT' => 'Itália',
		'JP' => 'Japão',
		'MX' => 'México',
		'NL' => 'Países Baixos',
		'PT' => 'Portugal',
		'US' => 'Estados Unidos',
	];
	private const COUNTRY_CODE_ALIASES = [
		'USA' => 'US',
		'EUA' => 'US',
	];

	/**
	 * Database connection used by the crawler.
	 * @var PDO
	 **/
	private $db;

	/**
	 * Prepared statement used to check previously saved URLs.
	 * @var mixed
	 **/
	private $existingUrlStmt = null;

	/**
	 * Cached result table columns.
	 * @var array<string, bool>|null
	 **/
	private $resultTableColumns = null;

	/**
	 * Build the service.
	 * @param PDO|null $db Optional database connection.
	 * @return void
	 **/
	public function __construct(?PDO $db = null)
		{
		$this->db = $db ?: Database::getInstance();
		}

	/**
	 * Return common user agents offered by the tool form.
	 * @return array<int, array<string, string>>
	 **/
	public static function userAgentOptions(): array
		{
		return [
			[
				'label' => 'Chrome Windows',
				'value' => self::DEFAULT_USER_AGENT,
			],
			[
				'label' => 'Chrome Android',
				'value' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36',
			],
			[
				'label' => 'Safari macOS',
				'value' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15',
			],
			[
				'label' => 'Microsoft Edge',
				'value' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 Edg/126.0',
			],
			[
				'label' => 'Firefox Linux',
				'value' => 'Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0',
			],
			[
				'label' => 'Googlebot',
				'value' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
			],
			[
				'label' => 'Bingbot',
				'value' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
			],
		];
		}

	/**
	 * Return the configured maximum IPs per execution.
	 * @return int
	 **/
	public static function maxIpsPerExecution(): int
		{
		$configured = getenv('IP_RANGE_WEBCRAWLER_MAX_IPS');
		if ($configured === false || trim((string) $configured) === '')
			{
			$configured = getenv('APP_IP_RANGE_WEBCRAWLER_MAX_IPS');
			}

		$maxIps = (int) ($configured !== false && trim((string) $configured) !== '' ? $configured : self::DEFAULT_MAX_IPS_PER_EXECUTION);

		return $maxIps > 0 ? $maxIps : self::DEFAULT_MAX_IPS_PER_EXECUTION;
		}

	/**
	 * Return countries with imported IP ranges.
	 * @return array<int, array<string, mixed>>
	 **/
	public function countryRangeOptions(): array
		{
		try
			{
			$rangeOptions = [];
			if ($this->tableExists(self::RANGES_TABLE))
				{
				$stmt = $this->db->query("SELECT IpRange.country_code,
				                                COALESCE(NULLIF(IpRange.country_name, ''), IpRange.country_code) AS country_name,
				                                COUNT(IpRange.id) AS range_total,
				                                COALESCE(SUM(IpRange.ip_count), 0) AS ip_total
				                         FROM webcrawler_ip_ranges AS IpRange
				                         WHERE IpRange.active = 1
				                         GROUP BY IpRange.country_code,
				                                  IpRange.country_name
				                         ORDER BY country_name ASC,
				                                  IpRange.country_code ASC");
				$rangeOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
				}

			$processedCountryTotals = [];
			if ($this->tableExists(self::RESULTS_TABLE) && $this->tableExists(self::RANGES_TABLE))
				{
				$failedResultCondition = $this->failedResultSqlCondition('WebcrawlerResult');
				$stmt = $this->db->query("SELECT IpRange.country_code,
				                                COUNT(DISTINCT WebcrawlerResult.ip_number) AS processed_total
				                         FROM webcrawler_ip_ranges AS IpRange
				                         INNER JOIN webcrawler_ip_range_results AS WebcrawlerResult
				                           ON WebcrawlerResult.ip_number BETWEEN IpRange.start_ip_number AND IpRange.end_ip_number
				                         WHERE IpRange.active = 1
				                           AND {$failedResultCondition}
				                         GROUP BY IpRange.country_code");
				foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $processedRow)
					{
					$countryCode = strtoupper(trim((string) ($processedRow['country_code'] ?? '')));
					if ($countryCode === '')
						{
						continue;
						}

					$processedCountryTotals[$countryCode] = (int) ($processedRow['processed_total'] ?? 0);
					}
				}

			$catalogOptions = $this->countryCatalogOptions();
			$knownOptions = $this->knownCountryOptions();
			if (empty($catalogOptions) && empty($knownOptions))
				{
				return $rangeOptions;
				}

			$mergedOptions = [];
			foreach ($knownOptions as $knownOption)
				{
				$countryCode = (string) $knownOption['country_code'];
				$mergedOptions[$countryCode] = $knownOption;
				}

			foreach ($catalogOptions as $catalogOption)
				{
				$countryCode = (string) $catalogOption['country_code'];
				$mergedOptions[$countryCode] = $catalogOption;
				}

			foreach ($rangeOptions as $rangeOption)
				{
				$countryCode = strtoupper(trim((string) ($rangeOption['country_code'] ?? '')));
				if ($countryCode === '')
					{
					continue;
					}

				$mergedOptions[$countryCode] = [
					'country_code' => $countryCode,
					'country_name' => (string) ($rangeOption['country_name'] ?? $countryCode),
					'range_total' => (int) ($rangeOption['range_total'] ?? 0),
					'ip_total' => (int) ($rangeOption['ip_total'] ?? 0),
					'processed_total' => (int) ($processedCountryTotals[$countryCode] ?? 0),
					'remaining_total' => max(0, ((int) ($rangeOption['ip_total'] ?? 0)) - ((int) ($processedCountryTotals[$countryCode] ?? 0))),
					'has_ranges' => ((int) ($rangeOption['range_total'] ?? 0)) > 0,
				];
				}

			usort($mergedOptions, static function (array $left, array $right): int {
				$leftHasRanges = !empty($left['has_ranges']) ? 1 : 0;
				$rightHasRanges = !empty($right['has_ranges']) ? 1 : 0;
				if ($leftHasRanges !== $rightHasRanges)
					{
					return $rightHasRanges <=> $leftHasRanges;
					}

				$nameComparison = strcmp((string) ($left['country_name'] ?? ''), (string) ($right['country_name'] ?? ''));
				if ($nameComparison !== 0)
					{
					return $nameComparison;
					}

				return strcmp((string) ($left['country_code'] ?? ''), (string) ($right['country_code'] ?? ''));
			});

			return array_values($mergedOptions);
			}
		catch (Throwable $exception)
			{
			return [];
			}
		}

	/**
	 * Search stored crawler results using report filters.
	 * @param array<string, mixed> $filters Report filters.
	 * @param int $limit Maximum rows.
	 * @param int $offset Result offset.
	 * @param array<string, mixed>|null $sort Sort state.
	 * @return array<int, array<string, mixed>>
	 **/
	public function searchResults(array $filters = [], int $limit = 20, int $offset = 0, ?array $sort = null): array
		{
		[$whereSql, $params] = $this->buildReportWhere($filters);
		$orderBy = $this->reportOrderBy($sort);
		$selectColumns = [
			'r.id',
			'r.execution_id',
			'r.user_id',
			'e.country_code',
			'e.range_mode',
			'e.start_ip',
			'e.end_ip',
			'e.keywords AS execution_keywords',
			'e.user_agent',
			'e.status AS execution_status',
			'e.started_at',
			'e.finished_at',
			'r.ip_address',
			'r.ip_number',
			'r.protocol',
			'r.port',
			'r.url',
			'r.http_status',
		];

		if ($this->hasResultColumn('scan_status'))
			{
			$selectColumns[] = 'r.scan_status';
			}
		else
			{
			$selectColumns[] = "'not_found' AS scan_status";
			}

		if ($this->hasResultColumn('curl_errno'))
			{
			$selectColumns[] = 'r.curl_errno';
			}
		else
			{
			$selectColumns[] = '0 AS curl_errno';
			}

		if ($this->hasResultColumn('curl_error'))
			{
			$selectColumns[] = 'r.curl_error';
			}
		else
			{
			$selectColumns[] = 'NULL AS curl_error';
			}

		$selectColumns = array_merge($selectColumns, [
			'r.title',
			'r.page_description',
			'r.content_type',
			'r.content_length',
			'r.body_size',
			'r.keyword_matched',
			'r.matched_keywords',
			'r.snippet',
			'r.response_time_ms',
			'r.redirect_url',
			'r.server_header',
			'r.checked_at',
			'r.created',
		]);

		$sql = "SELECT
					" . implode(",\n\t\t\t\t\t", $selectColumns) . "
				FROM webcrawler_ip_range_results AS r
				LEFT JOIN webcrawler_ip_range_executions AS e
					ON e.id = r.execution_id
				{$whereSql}
				ORDER BY {$orderBy}
				LIMIT :limit
				OFFSET :offset";
		$stmt = $this->db->prepare($sql);

		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value);
			}

		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
		$stmt->execute();

		return array_map([$this, 'normalizeReportRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
		}

	/**
	 * Count stored crawler results using report filters.
	 * @param array<string, mixed> $filters Report filters.
	 * @return int
	 **/
	public function countResults(array $filters = []): int
		{
		[$whereSql, $params] = $this->buildReportWhere($filters);
		$stmt = $this->db->prepare("SELECT COUNT(*) AS total
		                            FROM webcrawler_ip_range_results AS r
		                            LEFT JOIN webcrawler_ip_range_executions AS e
		                              ON e.id = r.execution_id
		                            {$whereSql}");

		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value);
			}

		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Build aggregate counters for the report filters.
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<string, int>
	 **/
	public function reportSummary(array $filters = []): array
		{
		[$whereSql, $params] = $this->buildReportWhere($filters);
		$foundTotalSql = $this->hasResultColumn('scan_status')
			? "COALESCE(SUM(CASE WHEN r.scan_status = 'found' THEN 1 ELSE 0 END), 0) AS found_total,"
			: "COALESCE(SUM(CASE WHEN r.http_status = 200 THEN 1 ELSE 0 END), 0) AS found_total,";
		$notFoundTotalSql = $this->hasResultColumn('scan_status')
			? "COALESCE(SUM(CASE WHEN r.scan_status = 'not_found' THEN 1 ELSE 0 END), 0) AS not_found_total,"
			: "COALESCE(SUM(CASE WHEN r.http_status <> 200 THEN 1 ELSE 0 END), 0) AS not_found_total,";
		$timeoutTotalSql = $this->hasResultColumn('scan_status')
			? "COALESCE(SUM(CASE WHEN r.scan_status = 'timeout' THEN 1 ELSE 0 END), 0) AS timeout_total,"
			: "0 AS timeout_total,";
		$errorTotalSql = $this->hasResultColumn('scan_status')
			? "COALESCE(SUM(CASE WHEN r.scan_status = 'error' THEN 1 ELSE 0 END), 0) AS error_total,"
			: "0 AS error_total,";
		$stmt = $this->db->prepare("SELECT
		                                COUNT(*) AS total,
		                                COUNT(DISTINCT r.execution_id) AS execution_total,
		                                COUNT(DISTINCT r.ip_number) AS ip_total,
		                                {$foundTotalSql}
		                                {$notFoundTotalSql}
		                                {$timeoutTotalSql}
		                                {$errorTotalSql}
		                                COALESCE(SUM(CASE WHEN r.keyword_matched = 1 THEN 1 ELSE 0 END), 0) AS matched_total,
		                                COALESCE(SUM(CASE WHEN r.protocol = 'http' THEN 1 ELSE 0 END), 0) AS http_total,
		                                COALESCE(SUM(CASE WHEN r.protocol = 'https' THEN 1 ELSE 0 END), 0) AS https_total,
		                                COALESCE(SUM(CASE WHEN r.http_status = 200 THEN 1 ELSE 0 END), 0) AS status_200_total
		                             FROM webcrawler_ip_range_results AS r
		                             LEFT JOIN webcrawler_ip_range_executions AS e
		                               ON e.id = r.execution_id
		                             {$whereSql}");

		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value);
			}

		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

		return [
			'total' => (int) ($row['total'] ?? 0),
			'execution_total' => (int) ($row['execution_total'] ?? 0),
			'ip_total' => (int) ($row['ip_total'] ?? 0),
			'found_total' => (int) ($row['found_total'] ?? 0),
			'not_found_total' => (int) ($row['not_found_total'] ?? 0),
			'timeout_total' => (int) ($row['timeout_total'] ?? 0),
			'error_total' => (int) ($row['error_total'] ?? 0),
			'matched_total' => (int) ($row['matched_total'] ?? 0),
			'http_total' => (int) ($row['http_total'] ?? 0),
			'https_total' => (int) ($row['https_total'] ?? 0),
			'status_200_total' => (int) ($row['status_200_total'] ?? 0),
		];
		}

	/**
	 * Import country IPv4 ranges from RIR delegated sources.
	 * @param string $countryCode Raw country code.
	 * @return array<string, mixed>
	 **/
	public function importCountryRanges(string $countryCode): array
		{
		$countryCode = $this->normalizeCountryCode($countryCode);
		if ($countryCode === '')
			{
			return [
				'success' => false,
				'status' => 422,
				'message' => 'Selecione um país com código ISO válido.',
			];
			}

		if (!$this->tableExists(self::RANGES_TABLE))
			{
			return [
				'success' => false,
				'status' => 422,
				'message' => 'A tabela webcrawler_ip_ranges não existe. Importe o schema SQL do webcrawler de IPs antes de carregar faixas.',
			];
			}

		$rowsToImport = [];
		$loadedSources = 0;

		foreach (self::COUNTRY_SOURCE_URLS as $sourceKey => $sourceUrl)
			{
			$body = $this->fetchCountrySource($sourceUrl);
			if ($body === '')
				{
				continue;
				}

			$loadedSources++;
			foreach ($this->parseCountrySource($countryCode, $sourceKey, $sourceUrl, $body) as $rangeRow)
				{
				$rowsToImport[] = $rangeRow;
				}
			}

		if ($loadedSources === 0)
			{
			return [
				'success' => false,
				'status' => 502,
				'message' => 'Não foi possível baixar as faixas IPv4 dos registries neste momento.',
			];
			}

		if (empty($rowsToImport))
			{
			return [
				'success' => false,
				'status' => 404,
				'message' => 'Nenhuma faixa IPv4 foi encontrada para o país selecionado nas fontes consultadas.',
			];
			}

		$this->persistImportedCountryRanges($countryCode, $rowsToImport);
		$countryStats = $this->countryRangeStats($countryCode);

		return [
			'success' => true,
			'status' => 200,
			'message' => 'Faixas IPv4 importadas com sucesso.',
			'country_code' => $countryCode,
			'country_name' => $this->countryName($countryCode),
			'imported_ranges' => count($rowsToImport),
			'stats' => $countryStats,
			'country_options' => $this->countryRangeOptions(),
		];
		}

	/**
	 * Return fallback country options from the catalog.
	 * @return array<int, array<string, mixed>>
	 **/
	private function countryCatalogOptions(): array
		{
		$countryModel = new Country();
		$catalogRows = $countryModel->getAll();
		$options = [];

		foreach ($catalogRows as $catalogRow)
			{
			$countryCode = $this->normalizeCountryCode((string) ($catalogRow['acronym'] ?? ''));
			if ($countryCode === '')
				{
				continue;
				}

			$options[] = [
				'country_code' => $countryCode,
				'country_name' => (string) ($catalogRow['name'] ?? $countryCode),
				'range_total' => 0,
				'ip_total' => 0,
				'has_ranges' => false,
			];
			}

		return $options;
		}

	/**
	 * Return known importable country options.
	 * @return array<int, array<string, mixed>>
	 **/
	private function knownCountryOptions(): array
		{
		$options = [];

		foreach (self::COUNTRY_NAMES as $countryCode => $countryName)
			{
			$options[] = [
				'country_code' => $countryCode,
				'country_name' => $countryName,
				'range_total' => 0,
				'ip_total' => 0,
				'has_ranges' => false,
			];
			}

		return $options;
		}

	/**
	 * Stream crawler events for the requested IP range.
	 * @param array<string, mixed> $input Request input.
	 * @return Generator<int, array<string, mixed>>
	 **/
	public function stream(array $input): Generator
		{
		$executionId = 0;

		try
			{
			if (!extension_loaded('curl'))
				{
				yield $this->event('validation_error', [
					'message' => 'A extensão cURL do PHP precisa estar habilitada para executar o crawler.',
				]);
				return;
				}

			$this->assertRequiredTables();

			$plan = $this->buildPlan($input);
			$executionId = $this->createExecution($plan);
			$stats = $this->initialStats($plan);

			yield $this->event('started', [
				'execution_id' => $executionId,
				'plan' => $this->publicPlan($plan),
				'stats' => $stats,
				'message' => 'Pesquisa iniciada.',
			]);

			foreach ($this->crawlTargets($plan, $executionId, $stats) as $payload)
				{
				yield $payload;
				}

			$stats['status'] = 'completed';
			$stats['handled_total'] = $stats['processed_total'] + $stats['skipped_total'];
			$this->updateExecution($executionId, $stats, 'completed', 'Pesquisa finalizada.');

			yield $this->event('summary', [
				'execution_id' => $executionId,
				'stats' => $stats,
				'message' => 'Pesquisa finalizada.',
			]);
			}
		catch (\UnexpectedValueException $exception)
			{
			if ($executionId > 0)
				{
				$this->markExecutionAsFailed($executionId, 'Schema SQL do webcrawler de IPs ausente.');
				}

			yield $this->event('schema_missing', [
				'message' => 'Não foi possível registrar a pesquisa. Importe o schema SQL do webcrawler de IPs antes de executar a ferramenta.',
			]);
			}
		catch (\InvalidArgumentException $exception)
			{
			if ($executionId > 0)
				{
				$this->markExecutionAsFailed($executionId, $exception->getMessage());
				}

			yield $this->event('validation_error', [
				'message' => $exception->getMessage(),
			]);
			}
		catch (PDOException $exception)
			{
			if ($executionId > 0)
				{
				$this->markExecutionAsFailed($executionId, 'Erro de banco de dados durante a pesquisa.');
				}

			yield $this->event($this->isMissingTableException($exception) ? 'schema_missing' : 'error', [
				'message' => $this->isMissingTableException($exception)
					? 'Não foi possível registrar a pesquisa. Importe o schema SQL do webcrawler de IPs antes de executar a ferramenta.'
					: 'Erro de banco de dados durante a pesquisa.',
			]);
			}
		catch (Throwable $exception)
			{
			if ($executionId > 0)
				{
				$this->markExecutionAsFailed($executionId, $exception->getMessage());
				}

			yield $this->event('error', [
				'message' => 'Não foi possível executar o crawler: ' . $exception->getMessage(),
			]);
			}
		}

	/**
	 * Crawl targets with curl_multi and stream progress events.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @param int $executionId Execution identifier.
	 * @param array<string, int|float|string> $stats Mutable execution counters.
	 * @return Generator<int, array<string, mixed>>
	 **/
	private function crawlTargets(array $plan, int $executionId, array &$stats): Generator
		{
		$multi = curl_multi_init();
		if ($multi === false)
			{
			throw new \RuntimeException('Não foi possível iniciar o gerenciador de conexões cURL.');
			}

		$targetIterator = $this->targetIterator($plan);
		$targetIterator->rewind();
		$targetsEnded = false;
		$handles = [];
		$lastPersistedHandledTotal = 0;

		while (!$targetsEnded || !empty($handles))
			{
			while (!$targetsEnded && count($handles) < (int) $plan['concurrency'])
				{
				if (!$targetIterator->valid())
					{
					$targetsEnded = true;
					break;
					}

				$target = $targetIterator->current();
				$targetIterator->next();

				if ($this->targetAlreadyBlocked($target))
					{
					$stats['skipped_total']++;
					$stats['handled_total'] = $stats['processed_total'] + $stats['skipped_total'];

					if (($stats['handled_total'] % 25) === 0)
						{
						$this->updateExecution($executionId, $stats, 'running', 'Pesquisa em andamento.');
						yield $this->event('progress', [
							'execution_id' => $executionId,
							'stats' => $stats,
						]);
						}

					continue;
					}

				if (!empty($plan['skip_existing']) && $this->targetAlreadyProcessed($target))
					{
					$stats['skipped_total']++;
					$stats['handled_total'] = $stats['processed_total'] + $stats['skipped_total'];

					if (($stats['handled_total'] % 25) === 0)
						{
						$this->updateExecution($executionId, $stats, 'running', 'Pesquisa em andamento.');
						yield $this->event('progress', [
							'execution_id' => $executionId,
							'stats' => $stats,
						]);
						}

					continue;
					}

				$context = $this->createCurlContext($target, $plan);
				curl_multi_add_handle($multi, $context['handle']);
				$handles[$context['key']] = $context;
				}

			if (empty($handles))
				{
				continue;
				}

			do
				{
				$multiStatus = curl_multi_exec($multi, $running);
				}
			while ($multiStatus === CURLM_CALL_MULTI_PERFORM);

			while ($done = curl_multi_info_read($multi))
				{
				$curl = $done['handle'];
				$key = $this->curlHandleKey($curl);
				$context = $handles[$key] ?? null;

				if ($context === null)
					{
					curl_multi_remove_handle($multi, $curl);
					curl_close($curl);
					continue;
					}

				$result = $this->buildResult($context, (int) ($done['result'] ?? CURLE_OK));
				$result['scan_status'] = $this->scanStatusForResult($result);

				curl_multi_remove_handle($multi, $curl);
				curl_close($curl);
				unset($handles[$key]);

				$stats['processed_total']++;
				$stats['handled_total'] = $stats['processed_total'] + $stats['skipped_total'];
				$stats['current_url'] = $result['url'];

				if ((string) $result['scan_status'] === 'found')
					{
					$this->saveResult($executionId, $plan, $result);
					$stats['found_total']++;

					if (!empty($result['keyword_matched']))
						{
						$stats['matched_total']++;
						}

					yield $this->event('found', [
						'execution_id' => $executionId,
						'result' => $this->publicResult($result),
						'stats' => $stats,
					]);
					}
				elseif ((string) $result['scan_status'] === 'error')
					{
					$this->saveResult($executionId, $plan, $result);
					$stats['failed_total']++;
					}
				elseif ((string) $result['scan_status'] === 'not_found')
					{
					$this->saveResult($executionId, $plan, $result);
					}

				if (($stats['handled_total'] - $lastPersistedHandledTotal) >= 10 || (string) $result['scan_status'] === 'found')
					{
					$lastPersistedHandledTotal = (int) $stats['handled_total'];
					$this->updateExecution($executionId, $stats, 'running', 'Pesquisa em andamento.');

					yield $this->event('progress', [
						'execution_id' => $executionId,
						'stats' => $stats,
					]);
					}
				}

			if (!empty($handles))
				{
				$selectResult = curl_multi_select($multi, 0.2);
				if ($selectResult === -1)
					{
					usleep(100000);
					}
				}
			}

		curl_multi_close($multi);
		}

	/**
	 * Create a curl handle context for one target.
	 * @param array<string, mixed> $target Target URL data.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @return array<string, mixed>
	 **/
	private function createCurlContext(array $target, array $plan): array
		{
		$body = '';
		$headers = [];
		$url = (string) $target['url'];
		$curl = curl_init($url);

		if ($curl === false)
			{
			throw new \RuntimeException('Não foi possível preparar a conexão para ' . $url . '.');
			}

		curl_setopt_array($curl, [
			CURLOPT_CONNECTTIMEOUT_MS => (int) $plan['timeout_ms'],
			CURLOPT_TIMEOUT_MS => (int) $plan['timeout_ms'],
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 2,
			CURLOPT_USERAGENT => (string) $plan['user_agent'],
			CURLOPT_HTTPHEADER => [
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: pt-BR,pt;q=0.9,en;q=0.8',
				'Cache-Control: no-cache',
			],
			CURLOPT_ENCODING => '',
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_NOSIGNAL => true,
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$headers): int {
				$trimmedHeader = trim($headerLine);
				if ($trimmedHeader !== '' && count($headers) < 80)
					{
					$headers[] = $trimmedHeader;
					}

				return strlen($headerLine);
			},
			CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use (&$body): int {
				$currentSize = strlen($body);
				$availableBytes = self::MAX_RESPONSE_BYTES - $currentSize;

				if ($availableBytes > 0)
					{
					$body .= substr($chunk, 0, $availableBytes);
					}

				return strlen($chunk);
			},
		]);

		if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS'))
			{
			curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
			}

		if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS'))
			{
			curl_setopt($curl, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
			}

		if (defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4'))
			{
			curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
			}

		if (defined('CURLOPT_HTTP_VERSION') && defined('CURL_HTTP_VERSION_1_1'))
			{
			curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			}

		return [
			'key' => $this->curlHandleKey($curl),
			'handle' => $curl,
			'target' => $target,
			'keywords' => (array) $plan['keywords'],
			'body' => &$body,
			'headers' => &$headers,
			'started_at' => microtime(true),
		];
		}

	/**
	 * Build the stored result payload from a finished curl context.
	 * @param array<string, mixed> $context Curl context.
	 * @param int $curlResult Curl result code.
	 * @return array<string, mixed>
	 **/
	private function buildResult(array $context, int $curlResult): array
		{
		$curl = $context['handle'];
		$target = $context['target'];
		$body = (string) ($context['body'] ?? '');
		$headers = is_array($context['headers'] ?? null) ? $context['headers'] : [];
		$contentType = (string) (curl_getinfo($curl, CURLINFO_CONTENT_TYPE) ?: '');
		$httpStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		$effectiveUrl = (string) (curl_getinfo($curl, CURLINFO_EFFECTIVE_URL) ?: $target['url']);
		$responseTimeMs = (int) round(((float) curl_getinfo($curl, CURLINFO_TOTAL_TIME)) * 1000);
		$title = $this->extractTitle($body);
		$description = $this->extractDescription($body);
		$matchedKeywords = $httpStatus === 200 ? $this->matchedKeywords($body, $title, (array) ($context['keywords'] ?? [])) : [];

		return [
			'ip_address' => (string) $target['ip_address'],
			'ip_number' => (int) $target['ip_number'],
			'protocol' => (string) $target['protocol'],
			'port' => (int) $target['port'],
			'url' => (string) $target['url'],
			'url_hash' => (string) $target['url_hash'],
			'http_status' => $httpStatus,
			'title' => $title,
			'page_description' => $description,
			'content_type' => $contentType,
			'content_length' => $this->contentLengthFromHeaders($headers, strlen($body)),
			'body_size' => strlen($body),
			'keyword_matched' => !empty($matchedKeywords),
			'matched_keywords' => $matchedKeywords,
			'snippet' => $this->snippetForKeywords($body, $title, $matchedKeywords),
			'response_time_ms' => $responseTimeMs,
			'redirect_url' => $effectiveUrl !== (string) $target['url'] ? $effectiveUrl : '',
			'server_header' => $this->serverHeader($headers),
			'curl_errno' => $curlResult,
			'curl_error' => $curlResult === CURLE_OK ? '' : curl_error($curl),
			'checked_at' => date('Y-m-d H:i:s'),
		];
		}

	/**
	 * Build and validate a crawler plan from request input.
	 * @param array<string, mixed> $input Raw request input.
	 * @return array<string, mixed>
	 **/
	private function buildPlan(array $input): array
		{
		$rangeMode = strtolower(trim((string) ($input['range_mode'] ?? 'amount')));
		if (!in_array($rangeMode, ['amount', 'manual', 'country'], true))
			{
			$rangeMode = 'amount';
			}

		$maxIps = (int) ($input['max_ips'] ?? 0);
		if ($maxIps <= 0)
			{
			$maxIps = self::maxIpsPerExecution();
			}
		$keywords = $this->keywordsFromInput((string) ($input['keywords'] ?? ''));
		$userAgent = $this->normalizeUserAgent((string) ($input['user_agent'] ?? ''));
		$timeoutMs = $this->clampInt((int) ($input['timeout_ms'] ?? self::DEFAULT_TIMEOUT_MS), 500, 15000);
		$concurrency = $this->clampInt((int) ($input['concurrency'] ?? 10), 1, self::MAX_CONCURRENCY);
		$ranges = [];
		$countryCode = '';

		if ($rangeMode === 'amount')
			{
			$startIp = trim((string) ($input['start_ip'] ?? ''));
			$startNumber = $this->ipv4ToNumber($startIp);
			$quantity = $this->clampInt((int) ($input['ip_quantity'] ?? 100), 1, self::maxIpsPerExecution());
			$endNumber = min(4294967295, $startNumber + $quantity - 1);
			$ranges[] = [
				'start' => $startNumber,
				'end' => $endNumber,
			];
			}
		elseif ($rangeMode === 'manual')
			{
			$ranges = $this->manualRanges((string) ($input['manual_ranges'] ?? ''), $maxIps);
			}
		else
			{
			$countryCode = $this->normalizeCountryCode((string) ($input['country_code'] ?? ''));
			if ($countryCode === '')
				{
				throw new \InvalidArgumentException('Selecione um país com código ISO de duas letras.');
				}

			if (!$this->tableExists(self::RANGES_TABLE))
				{
				throw new \InvalidArgumentException('Importe o schema SQL do webcrawler de IPs antes de usar faixas por país.');
				}

			if ($maxIps <= 0)
				{
				$maxIps = $this->countryRemainingIpTotal($countryCode);
				}

			if ($maxIps <= 0)
				{
				throw new \InvalidArgumentException('Não há IPs restantes disponíveis para o país selecionado.');
				}

			$ranges = $this->countryRanges($countryCode, $maxIps);
			}

		$generatedIpCount = $this->countRangeIps($ranges);
		if ($generatedIpCount <= 0)
			{
			throw new \InvalidArgumentException('Nenhum IP válido foi encontrado para a pesquisa.');
			}

		return [
			'user_id' => max(0, (int) ($input['user_id'] ?? 0)),
			'range_mode' => $rangeMode,
			'country_code' => $countryCode,
			'ranges' => $ranges,
			'start_ip' => $this->numberToIpv4((int) $ranges[0]['start']),
			'end_ip' => $this->numberToIpv4((int) $ranges[count($ranges) - 1]['end']),
			'generated_ip_count' => $generatedIpCount,
			'target_total' => $generatedIpCount * 2,
			'keywords' => $keywords,
			'keywords_text' => implode(', ', $keywords),
			'user_agent' => $userAgent,
			'timeout_ms' => $timeoutMs,
			'concurrency' => $concurrency,
			'skip_existing' => !empty($input['skip_existing']),
		];
		}

	/**
	 * Parse manual IPv4 input values.
	 * @param string $value Manual input.
	 * @param int $limit Maximum amount of IPs.
	 * @return array<int, array<string, int>>
	 **/
	private function manualRanges(string $value, int $limit): array
		{
		$value = trim($value);
		if ($value === '')
			{
			throw new \InvalidArgumentException('Informe ao menos um IP, uma faixa ou um bloco CIDR.');
			}

		$ranges = [];
		$remaining = $limit;
		$items = preg_split('/[\r\n,;]+/', $value) ?: [];

		foreach ($items as $item)
			{
			if ($remaining <= 0)
				{
				break;
				}

			$item = preg_replace('/\s+/', '', trim((string) $item)) ?? '';
			if ($item === '')
				{
				continue;
				}

			if (strpos($item, '/') !== false)
				{
				$range = $this->cidrRange($item);
				}
			elseif (strpos($item, '-') !== false)
				{
				$parts = explode('-', $item, 2);
				$start = $this->ipv4ToNumber($parts[0] ?? '');
				$end = $this->ipv4ToNumber($parts[1] ?? '');
				$range = [
					'start' => min($start, $end),
					'end' => max($start, $end),
				];
				}
			else
				{
				$ipNumber = $this->ipv4ToNumber($item);
				$range = [
					'start' => $ipNumber,
					'end' => $ipNumber,
				];
				}

			$count = ((int) $range['end'] - (int) $range['start']) + 1;
			if ($count > $remaining)
				{
				$range['end'] = ((int) $range['start']) + $remaining - 1;
				$count = $remaining;
				}

			$ranges[] = $range;
			$remaining -= $count;
			}

		if (empty($ranges))
			{
			throw new \InvalidArgumentException('Nenhum IP válido foi encontrado no campo de faixas.');
			}

		return $ranges;
		}

	/**
	 * Load country ranges from the database.
	 * @param string $countryCode ISO country code.
	 * @param int $limit Maximum amount of IPs.
	 * @return array<int, array<string, int>>
	 **/
	private function countryRanges(string $countryCode, int $limit): array
		{
		$countryCode = $this->normalizeCountryCode($countryCode);
		$stmt = $this->db->prepare("SELECT IpRange.start_ip,
		                                   IpRange.end_ip,
		                                   IpRange.start_ip_number,
		                                   IpRange.end_ip_number
		                            FROM webcrawler_ip_ranges AS IpRange
		                            WHERE IpRange.country_code = :country_code
		                              AND IpRange.active = 1
		                            ORDER BY IpRange.start_ip_number ASC,
		                                     IpRange.id ASC");
		$stmt->execute(['country_code' => $countryCode]);

		$ranges = [];
		$remaining = $limit;

		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
			{
			if ($remaining <= 0)
				{
				break;
				}

			$start = isset($row['start_ip_number']) ? (int) $row['start_ip_number'] : $this->ipv4ToNumber((string) ($row['start_ip'] ?? ''));
			$end = isset($row['end_ip_number']) ? (int) $row['end_ip_number'] : $this->ipv4ToNumber((string) ($row['end_ip'] ?? ''));

			if ($end < $start)
				{
				continue;
				}

			$cursor = $start;
			foreach ($this->blockedIpNumbersInRange($start, $end) as $blockedIpNumber)
				{
				if ($blockedIpNumber < $cursor)
					{
					continue;
					}

				if ($blockedIpNumber > $end)
					{
					break;
					}

				if ($blockedIpNumber > $cursor)
					{
					$count = min($blockedIpNumber - $cursor, $remaining);
					$ranges[] = [
						'start' => $cursor,
						'end' => $cursor + $count - 1,
					];
					$remaining -= $count;

					if ($remaining <= 0)
						{
						break 2;
						}
					}

				$cursor = $blockedIpNumber + 1;
				if ($cursor > $end)
					{
					break;
					}
				}

			if ($remaining > 0 && $cursor <= $end)
				{
				$count = min(($end - $cursor) + 1, $remaining);
				$ranges[] = [
					'start' => $cursor,
					'end' => $cursor + $count - 1,
				];
				$remaining -= $count;
				}
			}

		if (empty($ranges))
			{
			throw new \InvalidArgumentException('Nenhuma faixa de IP ativa foi encontrada para o país selecionado.');
			}

		return $ranges;
		}

	/**
	 * Return IP numbers already blocked by previous failed results inside a range.
	 * @param int $start Start IP number.
	 * @param int $end End IP number.
	 * @return array<int, int>
	 **/
	private function blockedIpNumbersInRange(int $start, int $end): array
		{
		if (!$this->tableExists(self::RESULTS_TABLE))
			{
			return [];
			}

		$failedResultCondition = $this->failedResultSqlCondition('WebcrawlerResult');
		$stmt = $this->db->prepare("SELECT DISTINCT WebcrawlerResult.ip_number
		                            FROM webcrawler_ip_range_results AS WebcrawlerResult
		                            WHERE WebcrawlerResult.ip_number BETWEEN :start_ip_number AND :end_ip_number
		                              AND {$failedResultCondition}
		                            ORDER BY WebcrawlerResult.ip_number ASC");
		$stmt->execute([
			'start_ip_number' => $start,
			'end_ip_number' => $end,
		]);

		return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
		}

	/**
	 * Persist imported rows for a country.
	 * @param string $countryCode Country code.
	 * @param array<int, array<string, mixed>> $rowsToImport Imported rows.
	 * @return void
	 **/
	private function persistImportedCountryRanges(string $countryCode, array $rowsToImport): void
		{
		$now = date('Y-m-d H:i:s');
		$uniqueRows = [];

		foreach ($rowsToImport as $rowToImport)
			{
			$rangeKey = (string) $rowToImport['start_ip_number'] . ':' . (string) $rowToImport['end_ip_number'];
			$uniqueRows[$rangeKey] = $rowToImport;
			}

		$this->db->beginTransaction();

		try
			{
			$deleteStmt = $this->db->prepare("DELETE FROM webcrawler_ip_ranges
			                                  WHERE country_code = :country_code");
			$deleteStmt->execute([
				'country_code' => $countryCode,
			]);

			$insertStmt = $this->db->prepare("INSERT INTO webcrawler_ip_ranges
				(`country_code`, `country_name`, `start_ip`, `end_ip`, `start_ip_number`, `end_ip_number`, `ip_count`, `source`, `source_reference`, `active`, `created`, `modified`)
				VALUES
				(:country_code, :country_name, :start_ip, :end_ip, :start_ip_number, :end_ip_number, :ip_count, :source, :source_reference, :active, :created, :modified)
				ON DUPLICATE KEY UPDATE
					`country_name` = VALUES(`country_name`),
					`ip_count` = VALUES(`ip_count`),
					`source` = VALUES(`source`),
					`source_reference` = VALUES(`source_reference`),
					`active` = VALUES(`active`),
					`modified` = VALUES(`modified`)");

			foreach ($uniqueRows as $rowToImport)
				{
				$insertStmt->execute([
					'country_code' => $countryCode,
					'country_name' => (string) $rowToImport['country_name'],
					'start_ip' => (string) $rowToImport['start_ip'],
					'end_ip' => (string) $rowToImport['end_ip'],
					'start_ip_number' => (int) $rowToImport['start_ip_number'],
					'end_ip_number' => (int) $rowToImport['end_ip_number'],
					'ip_count' => (int) $rowToImport['ip_count'],
					'source' => (string) $rowToImport['source'],
					'source_reference' => (string) $rowToImport['source_reference'],
					'active' => 1,
					'created' => $now,
					'modified' => $now,
				]);
				}

			$this->db->commit();
			}
		catch (Throwable $exception)
			{
			$this->db->rollBack();
			throw $exception;
			}
		}

	/**
	 * Parse delegated country ranges from one source body.
	 * @param string $countryCode Country code.
	 * @param string $sourceKey Source key.
	 * @param string $sourceUrl Source URL.
	 * @param string $body Source body.
	 * @return array<int, array<string, mixed>>
	 **/
	private function parseCountrySource(string $countryCode, string $sourceKey, string $sourceUrl, string $body): array
		{
		$rows = [];

		foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line)
			{
			if ($line === '' || $line[0] === '#')
				{
				continue;
				}

			$parts = explode('|', $line);
			if (count($parts) < 7)
				{
				continue;
				}

			if ($this->normalizeCountryCode((string) ($parts[1] ?? '')) !== $countryCode)
				{
				continue;
				}

			if (strtolower((string) ($parts[2] ?? '')) !== 'ipv4')
				{
				continue;
				}

			$status = strtolower((string) ($parts[6] ?? ''));
			if (!in_array($status, ['allocated', 'assigned'], true))
				{
				continue;
				}

			try
				{
				$startIpNumber = $this->ipv4ToNumber((string) ($parts[3] ?? ''));
				}
			catch (\InvalidArgumentException $exception)
				{
				continue;
				}

			$ipCount = (int) ($parts[4] ?? 0);
			if ($ipCount <= 0)
				{
				continue;
				}

			$endIpNumber = $startIpNumber + $ipCount - 1;
			if ($endIpNumber > 4294967295)
				{
				continue;
				}

			$sourceDate = $this->normalizeSourceDate((string) ($parts[5] ?? ''));
			$rows[] = [
				'country_name' => $this->countryName($countryCode),
				'start_ip' => $this->numberToIpv4($startIpNumber),
				'end_ip' => $this->numberToIpv4($endIpNumber),
				'start_ip_number' => $startIpNumber,
				'end_ip_number' => $endIpNumber,
				'ip_count' => $ipCount,
				'source' => $sourceKey,
				'source_reference' => $sourceUrl . ($sourceDate !== null ? '|' . $sourceDate : ''),
			];
			}

		return $rows;
		}

	/**
	 * Fetch a remote delegated file.
	 * @param string $sourceUrl Source URL.
	 * @return string
	 **/
	private function fetchCountrySource(string $sourceUrl): string
		{
		if (function_exists('curl_init'))
			{
			$handle = curl_init($sourceUrl);
			if ($handle === false)
				{
				return '';
				}

			curl_setopt_array($handle, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => self::COUNTRY_IMPORT_TIMEOUT_SECONDS,
				CURLOPT_TIMEOUT => self::COUNTRY_IMPORT_TIMEOUT_SECONDS,
				CURLOPT_USERAGENT => 'Manager IpRangeCountryLoader/1.0',
			]);

			$body = curl_exec($handle);
			$status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
			curl_close($handle);

			return $status >= 200 && $status < 300 && is_string($body) ? $body : '';
			}

		$context = stream_context_create([
			'http' => [
				'timeout' => self::COUNTRY_IMPORT_TIMEOUT_SECONDS,
				'header' => "User-Agent: Manager IpRangeCountryLoader/1.0\r\n",
			],
		]);
		$body = @file_get_contents($sourceUrl, false, $context);

		return is_string($body) ? $body : '';
		}

	/**
	 * Normalize a crawler report row.
	 * @param array<string, mixed> $row Raw database row.
	 * @return array<string, mixed>
	 **/
	private function normalizeReportRow(array $row): array
		{
		$matchedKeywords = [];
		if (!empty($row['matched_keywords']))
			{
			$decodedKeywords = json_decode((string) $row['matched_keywords'], true);
			if (is_array($decodedKeywords))
				{
				$matchedKeywords = array_values(array_filter(array_map(static function ($value): string {
					return trim((string) $value);
				}, $decodedKeywords), static function (string $value): bool {
					return $value !== '';
				}));
				}
			}

		$row['matched_keywords'] = $matchedKeywords;
		$row['keyword_matched'] = !empty($row['keyword_matched']);
		$row['scan_status'] = (string) ($row['scan_status'] ?? '');
		if ($row['scan_status'] === '')
			{
			$row['scan_status'] = ((int) ($row['http_status'] ?? 0) === 200) ? 'found' : 'not_found';
			}
		$row['curl_errno'] = (int) ($row['curl_errno'] ?? 0);
		$row['curl_error'] = (string) ($row['curl_error'] ?? '');
		$row['http_status'] = (int) ($row['http_status'] ?? 0);
		$row['port'] = (int) ($row['port'] ?? 0);
		$row['response_time_ms'] = (int) ($row['response_time_ms'] ?? 0);
		$row['content_length'] = (int) ($row['content_length'] ?? 0);
		$row['body_size'] = (int) ($row['body_size'] ?? 0);
		$row['ip_number'] = (int) ($row['ip_number'] ?? 0);

		return $row;
		}

	/**
	 * Return aggregated stats for one imported country.
	 * @param string $countryCode Country code.
	 * @return array<string, int|string>
	 **/
	private function countryRangeStats(string $countryCode): array
		{
		$stmt = $this->db->prepare("SELECT COUNT(IpRange.id) AS range_total,
		                                   COALESCE(SUM(IpRange.ip_count), 0) AS ip_total
		                            FROM webcrawler_ip_ranges AS IpRange
		                            WHERE IpRange.country_code = :country_code
		                              AND IpRange.active = 1");
		$stmt->execute([
			'country_code' => $countryCode,
		]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

		return [
			'country_code' => $countryCode,
			'range_total' => (int) ($row['range_total'] ?? 0),
			'ip_total' => (int) ($row['ip_total'] ?? 0),
		];
		}

	/**
	 * Return the remaining IP count for one country after saved results are discounted.
	 * @param string $countryCode Country code.
	 * @return int
	 **/
	private function countryRemainingIpTotal(string $countryCode): int
		{
		$countryCode = $this->normalizeCountryCode($countryCode);
		if ($countryCode === '' || !$this->tableExists(self::RANGES_TABLE))
			{
			return 0;
			}

		$stmt = $this->db->prepare("SELECT COALESCE(SUM(IpRange.ip_count), 0) AS ip_total
		                            FROM webcrawler_ip_ranges AS IpRange
		                            WHERE IpRange.country_code = :country_code
		                              AND IpRange.active = 1");
		$stmt->execute(['country_code' => $countryCode]);
		$totalIps = (int) $stmt->fetchColumn();

		$processedCountryTotal = 0;
		if ($this->tableExists(self::RESULTS_TABLE))
			{
			$failedResultCondition = $this->failedResultSqlCondition('WebcrawlerResult');
			$stmt = $this->db->prepare("SELECT COUNT(DISTINCT WebcrawlerResult.ip_number) AS processed_total
			                            FROM webcrawler_ip_ranges AS IpRange
			                            INNER JOIN webcrawler_ip_range_results AS WebcrawlerResult
			                              ON WebcrawlerResult.ip_number BETWEEN IpRange.start_ip_number AND IpRange.end_ip_number
			                            WHERE IpRange.country_code = :country_code
			                              AND IpRange.active = 1
			                              AND {$failedResultCondition}");
			$stmt->execute(['country_code' => $countryCode]);
			$processedCountryTotal = (int) $stmt->fetchColumn();
			}

		return max(0, $totalIps - $processedCountryTotal);
		}

	/**
	 * Build the WHERE clause for report filters.
	 * @param array<string, mixed> $filters Report filters.
	 * @return array{0: string, 1: array<string, mixed>}
	 **/
	private function buildReportWhere(array $filters): array
		{
		$where = [];
		$params = [];
		$executionId = $this->clampInt((int) ($filters['execution_id'] ?? 0), 0, PHP_INT_MAX);
		$countryCode = $this->normalizeCountryCode((string) ($filters['country_code'] ?? ''));
		$rangeMode = trim((string) ($filters['range_mode'] ?? ''));
		$protocol = strtolower(trim((string) ($filters['protocol'] ?? '')));
		$port = $this->clampInt((int) ($filters['port'] ?? 0), 0, 65535);
		$httpStatus = $this->clampInt((int) ($filters['http_status'] ?? 0), 0, 999);
		$scanStatus = trim((string) ($filters['scan_status'] ?? ''));
		$keywordMatched = trim((string) ($filters['keyword_matched'] ?? ''));
		$term = trim((string) ($filters['term'] ?? ''));
		$dateFrom = trim((string) ($filters['date_from'] ?? ''));
		$dateTo = trim((string) ($filters['date_to'] ?? ''));

		if ($executionId > 0)
			{
			$where[] = 'r.execution_id = :execution_id';
			$params['execution_id'] = $executionId;
			}

		if ($countryCode !== '')
			{
			$where[] = 'e.country_code = :country_code';
			$params['country_code'] = $countryCode;
			}

		if ($rangeMode !== '' && in_array($rangeMode, ['amount', 'manual', 'country'], true))
			{
			$where[] = 'e.range_mode = :range_mode';
			$params['range_mode'] = $rangeMode;
			}

		if (in_array($protocol, ['http', 'https'], true))
			{
			$where[] = 'r.protocol = :protocol';
			$params['protocol'] = $protocol;
			}

		if ($port > 0)
			{
			$where[] = 'r.port = :port';
			$params['port'] = $port;
			}

		if ($httpStatus > 0)
			{
			$where[] = 'r.http_status = :http_status';
			$params['http_status'] = $httpStatus;
			}

		if (in_array($scanStatus, ['found', 'not_found', 'timeout', 'error', 'skipped'], true))
			{
			if ($this->hasResultColumn('scan_status'))
				{
				$where[] = 'r.scan_status = :scan_status';
				$params['scan_status'] = $scanStatus;
				}
			elseif ($scanStatus === 'found')
				{
				$where[] = 'r.http_status = 200';
				}
			elseif ($scanStatus === 'not_found')
				{
				$where[] = 'r.http_status <> 200';
				}
			}

		if ($keywordMatched === '1' || strtolower($keywordMatched) === 'yes')
			{
			$where[] = 'r.keyword_matched = 1';
			}
		elseif ($keywordMatched === '0' || strtolower($keywordMatched) === 'no')
			{
			$where[] = 'r.keyword_matched = 0';
			}

		if ($term !== '')
			{
			$where[] = '(r.ip_address LIKE :term_like
			          OR r.url LIKE :term_like
			          OR r.title LIKE :term_like
			          OR r.page_description LIKE :term_like
			          OR r.snippet LIKE :term_like
			          OR r.server_header LIKE :term_like
			          OR e.user_agent LIKE :term_like
			          OR e.keywords LIKE :term_like)';
			$params['term_like'] = '%' . $term . '%';
			}

		if ($dateFrom !== '')
			{
			$where[] = 'DATE(r.checked_at) >= :date_from';
			$params['date_from'] = $dateFrom;
			}

		if ($dateTo !== '')
			{
			$where[] = 'DATE(r.checked_at) <= :date_to';
			$params['date_to'] = $dateTo;
			}

		$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

		return [$whereSql, $params];
		}

	/**
	 * Return a safe ORDER BY clause for the report.
	 * @param array<string, mixed>|null $sort Sort state.
	 * @return string
	 **/
	private function reportOrderBy(?array $sort): string
		{
		$allowedColumns = [
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

		return sortOrderBy($sort, $allowedColumns, 'checked_at', 'desc');
		}

	/**
	 * Build a CIDR range.
	 * @param string $cidr CIDR block.
	 * @return array<string, int>
	 **/
	private function cidrRange(string $cidr): array
		{
		if (!preg_match('/^(.+)\/(\d{1,2})$/', $cidr, $matches))
			{
			throw new \InvalidArgumentException('Bloco CIDR inválido: ' . $cidr . '.');
			}

		$ipNumber = $this->ipv4ToNumber((string) $matches[1]);
		$prefix = (int) $matches[2];

		if ($prefix < 0 || $prefix > 32)
			{
			throw new \InvalidArgumentException('Prefixo CIDR inválido: ' . $cidr . '.');
			}

		$size = (int) (2 ** (32 - $prefix));
		$mask = $prefix === 0 ? 0 : (0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF;
		$start = $ipNumber & $mask;
		$end = min(4294967295, $start + $size - 1);

		return [
			'start' => $start,
			'end' => $end,
		];
		}

	/**
	 * Create an execution record.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @return int Execution id.
	 **/
	private function createExecution(array $plan): int
		{
		$stmt = $this->db->prepare("INSERT INTO webcrawler_ip_range_executions
			(`user_id`, `range_mode`, `country_code`, `start_ip`, `end_ip`, `generated_ip_count`, `target_total`, `keywords`, `user_agent`, `timeout_ms`, `concurrency`, `skip_existing`, `status`, `started_at`, `created`, `modified`)
			VALUES
			(:user_id, :range_mode, :country_code, :start_ip, :end_ip, :generated_ip_count, :target_total, :keywords, :user_agent, :timeout_ms, :concurrency, :skip_existing, 'running', :started_at, :created, :modified)");
		$now = date('Y-m-d H:i:s');
		$stmt->execute([
			'user_id' => ((int) $plan['user_id'] > 0 ? (int) $plan['user_id'] : null),
			'range_mode' => (string) $plan['range_mode'],
			'country_code' => (string) $plan['country_code'] !== '' ? (string) $plan['country_code'] : null,
			'start_ip' => (string) $plan['start_ip'],
			'end_ip' => (string) $plan['end_ip'],
			'generated_ip_count' => (int) $plan['generated_ip_count'],
			'target_total' => (int) $plan['target_total'],
			'keywords' => (string) $plan['keywords_text'] !== '' ? (string) $plan['keywords_text'] : null,
			'user_agent' => (string) $plan['user_agent'],
			'timeout_ms' => (int) $plan['timeout_ms'],
			'concurrency' => (int) $plan['concurrency'],
			'skip_existing' => !empty($plan['skip_existing']) ? 1 : 0,
			'started_at' => $now,
			'created' => $now,
			'modified' => $now,
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Update an execution record with progress counters.
	 * @param int $executionId Execution id.
	 * @param array<string, int|float|string> $stats Execution counters.
	 * @param string $status Execution status.
	 * @param string $message Status message.
	 * @return void
	 **/
	private function updateExecution(int $executionId, array $stats, string $status, string $message): void
		{
		$finishedStatuses = ['completed', 'failed', 'stopped'];
		$finishedSql = in_array($status, $finishedStatuses, true) ? ', `finished_at` = :finished_at' : '';
		$stmt = $this->db->prepare("UPDATE webcrawler_ip_range_executions
		                            SET `processed_total` = :processed_total,
		                                `skipped_total` = :skipped_total,
		                                `found_total` = :found_total,
		                                `matched_total` = :matched_total,
		                                `failed_total` = :failed_total,
		                                `status` = :status,
		                                `message` = :message,
		                                `modified` = :modified
		                                {$finishedSql}
		                            WHERE `id` = :id");
		$params = [
			'processed_total' => (int) ($stats['processed_total'] ?? 0),
			'skipped_total' => (int) ($stats['skipped_total'] ?? 0),
			'found_total' => (int) ($stats['found_total'] ?? 0),
			'matched_total' => (int) ($stats['matched_total'] ?? 0),
			'failed_total' => (int) ($stats['failed_total'] ?? 0),
			'status' => $status,
			'message' => $message,
			'modified' => date('Y-m-d H:i:s'),
			'id' => $executionId,
		];

		if ($finishedSql !== '')
			{
			$params['finished_at'] = date('Y-m-d H:i:s');
			}

		$stmt->execute($params);
		}

	/**
	 * Mark an execution as failed.
	 * @param int $executionId Execution id.
	 * @param string $message Failure message.
	 * @return void
	 **/
	private function markExecutionAsFailed(int $executionId, string $message): void
		{
		try
			{
			$this->updateExecution($executionId, [
				'processed_total' => 0,
				'skipped_total' => 0,
				'found_total' => 0,
				'matched_total' => 0,
				'failed_total' => 0,
			], 'failed', $message);
			}
		catch (Throwable $exception)
			{
			}
		}

	/**
	 * Save a crawler result for a target, including non-found responses.
	 * @param int $executionId Execution id.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @param array<string, mixed> $result Result data.
	 * @return void
	 **/
	private function saveResult(int $executionId, array $plan, array $result): void
		{
		$hasScanStatus = $this->hasResultColumn('scan_status');
		$hasCurlErrno = $this->hasResultColumn('curl_errno');
		$hasCurlError = $this->hasResultColumn('curl_error');
		$columns = [
			'`execution_id`',
			'`user_id`',
			'`ip_address`',
			'`ip_number`',
			'`protocol`',
			'`port`',
			'`url`',
			'`url_hash`',
			'`http_status`',
		];
		$values = [
			':execution_id',
			':user_id',
			':ip_address',
			':ip_number',
			':protocol',
			':port',
			':url',
			':url_hash',
			':http_status',
		];
		$updates = [
			'`execution_id` = VALUES(`execution_id`)',
			'`user_id` = VALUES(`user_id`)',
			'`http_status` = VALUES(`http_status`)',
			'`title` = VALUES(`title`)',
			'`page_description` = VALUES(`page_description`)',
			'`content_type` = VALUES(`content_type`)',
			'`content_length` = VALUES(`content_length`)',
			'`body_size` = VALUES(`body_size`)',
			'`keyword_matched` = VALUES(`keyword_matched`)',
			'`matched_keywords` = VALUES(`matched_keywords`)',
			'`snippet` = VALUES(`snippet`)',
			'`response_time_ms` = VALUES(`response_time_ms`)',
			'`redirect_url` = VALUES(`redirect_url`)',
			'`server_header` = VALUES(`server_header`)',
			'`checked_at` = VALUES(`checked_at`)',
			'`modified` = VALUES(`modified`)',
		];

		if ($hasScanStatus)
			{
			$columns[] = '`scan_status`';
			$values[] = ':scan_status';
			$updates[] = '`scan_status` = VALUES(`scan_status`)';
			}

		if ($hasCurlErrno)
			{
			$columns[] = '`curl_errno`';
			$values[] = ':curl_errno';
			$updates[] = '`curl_errno` = VALUES(`curl_errno`)';
			}

		if ($hasCurlError)
			{
			$columns[] = '`curl_error`';
			$values[] = ':curl_error';
			$updates[] = '`curl_error` = VALUES(`curl_error`)';
			}

		$columns = array_merge($columns, [
			'`title`',
			'`page_description`',
			'`content_type`',
			'`content_length`',
			'`body_size`',
			'`keyword_matched`',
			'`matched_keywords`',
			'`snippet`',
			'`response_time_ms`',
			'`redirect_url`',
			'`server_header`',
			'`checked_at`',
			'`created`',
			'`modified`',
		]);
		$values = array_merge($values, [
			':title',
			':page_description',
			':content_type',
			':content_length',
			':body_size',
			':keyword_matched',
			':matched_keywords',
			':snippet',
			':response_time_ms',
			':redirect_url',
			':server_header',
			':checked_at',
			':created',
			':modified',
		]);

		$sql = "INSERT INTO webcrawler_ip_range_results
			(" . implode(', ', $columns) . ")
			VALUES
			(" . implode(', ', $values) . ")";
		if (!empty($updates))
			{
			$sql .= "
			ON DUPLICATE KEY UPDATE
				" . implode(",\n\t\t\t\t", $updates);
			}

		$stmt = $this->db->prepare($sql);
		$now = date('Y-m-d H:i:s');
		$scanStatus = (string) ($result['scan_status'] ?? ($this->scanStatusForResult($result)));
		$params = [
			'execution_id' => $executionId,
			'user_id' => ((int) $plan['user_id'] > 0 ? (int) $plan['user_id'] : null),
			'ip_address' => (string) $result['ip_address'],
			'ip_number' => (int) $result['ip_number'],
			'protocol' => (string) $result['protocol'],
			'port' => (int) $result['port'],
			'url' => (string) $result['url'],
			'url_hash' => (string) $result['url_hash'],
			'http_status' => (int) $result['http_status'],
			'title' => $this->limitString((string) $result['title'], 255),
			'page_description' => $this->limitString((string) $result['page_description'], 500),
			'content_type' => $this->limitString((string) $result['content_type'], 255),
			'content_length' => (int) $result['content_length'],
			'body_size' => (int) $result['body_size'],
			'keyword_matched' => !empty($result['keyword_matched']) ? 1 : 0,
			'matched_keywords' => !empty($result['matched_keywords']) ? json_encode($result['matched_keywords'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
			'snippet' => (string) $result['snippet'] !== '' ? (string) $result['snippet'] : null,
			'response_time_ms' => (int) $result['response_time_ms'],
			'redirect_url' => (string) $result['redirect_url'] !== '' ? (string) $result['redirect_url'] : null,
			'server_header' => (string) $result['server_header'] !== '' ? $this->limitString((string) $result['server_header'], 255) : null,
			'checked_at' => (string) $result['checked_at'],
			'created' => $now,
			'modified' => $now,
		];

		if ($hasScanStatus)
			{
			$params['scan_status'] = $scanStatus;
			}

		if ($hasCurlErrno)
			{
			$params['curl_errno'] = (int) ($result['curl_errno'] ?? 0);
			}

		if ($hasCurlError)
			{
			$params['curl_error'] = (string) ($result['curl_error'] ?? '');
			}

		$stmt->execute($params);
		}

	/**
	 * Check whether a target was already stored.
	 * @param array<string, mixed> $target Target URL data.
	 * @return bool
	 **/
	private function targetAlreadyProcessed(array $target): bool
		{
		if ($this->existingUrlStmt === null)
			{
			$targetCondition = "(WebcrawlerResult.url_hash = :url_hash
			                    OR (WebcrawlerResult.ip_number = :ip_number
			                        AND WebcrawlerResult.protocol = :protocol
			                        AND WebcrawlerResult.port = :port))";
			$statusCondition = $this->hasResultColumn('scan_status')
				? " AND WebcrawlerResult.scan_status IN ('found','not_found','error')"
				: '';

			$this->existingUrlStmt = $this->db->prepare("SELECT WebcrawlerResult.id
			                                            FROM webcrawler_ip_range_results AS WebcrawlerResult
			                                            WHERE {$targetCondition}{$statusCondition}
			                                            LIMIT 1");
			}

		$this->existingUrlStmt->execute([
			'url_hash' => (string) ($target['url_hash'] ?? ''),
			'ip_number' => (int) ($target['ip_number'] ?? 0),
			'protocol' => (string) ($target['protocol'] ?? ''),
			'port' => (int) ($target['port'] ?? 0),
		]);

		return (bool) $this->existingUrlStmt->fetchColumn();
		}

	/**
	 * Check whether a target was already blocked by a not_found or error result.
	 * @param array<string, mixed> $target Target URL data.
	 * @return bool
	 **/
	private function targetAlreadyBlocked(array $target): bool
		{
		if (!$this->tableExists(self::RESULTS_TABLE))
			{
			return false;
			}

		$failedResultCondition = $this->failedResultSqlCondition('WebcrawlerResult');
		$stmt = $this->db->prepare("SELECT WebcrawlerResult.id
		                            FROM webcrawler_ip_range_results AS WebcrawlerResult
		                            WHERE WebcrawlerResult.ip_number = :ip_number
		                              AND WebcrawlerResult.protocol = :protocol
		                              AND WebcrawlerResult.port = :port
		                              AND {$failedResultCondition}
		                            LIMIT 1");
		$stmt->execute([
			'ip_number' => (int) ($target['ip_number'] ?? 0),
			'protocol' => (string) ($target['protocol'] ?? ''),
			'port' => (int) ($target['port'] ?? 0),
		]);

		return (bool) $stmt->fetchColumn();
		}

	/**
	 * Return the SQL condition that identifies failed stored crawler results.
	 * @param string $alias SQL table alias.
	 * @return string
	 **/
	private function failedResultSqlCondition(string $alias): string
		{
		if ($this->hasResultColumn('scan_status'))
			{
			return "({$alias}.scan_status IN ('not_found', 'error') OR (({$alias}.scan_status IS NULL OR {$alias}.scan_status = '') AND {$alias}.http_status <> 200))";
			}

		return "{$alias}.http_status <> 200";
		}

	/**
	 * Generate target URLs for all configured ranges.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @return Generator<int, array<string, mixed>>
	 **/
	private function targetIterator(array $plan): Generator
		{
		foreach ((array) $plan['ranges'] as $range)
			{
			$start = (int) $range['start'];
			$end = (int) $range['end'];

			for ($ipNumber = $start; $ipNumber <= $end; $ipNumber++)
				{
				$ipAddress = $this->numberToIpv4($ipNumber);
				foreach ([['http', 80], ['https', 443]] as $definition)
					{
					$protocol = (string) $definition[0];
					$port = (int) $definition[1];
					$url = $protocol . '://' . $ipAddress . ':' . $port . '/';

					yield [
						'ip_address' => $ipAddress,
						'ip_number' => $ipNumber,
						'protocol' => $protocol,
						'port' => $port,
						'url' => $url,
						'url_hash' => hash('sha256', $url),
					];
					}
				}
			}
		}

	/**
	 * Build the initial stats payload.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @return array<string, int|float|string>
	 **/
	private function initialStats(array $plan): array
		{
		return [
			'target_total' => (int) $plan['target_total'],
			'processed_total' => 0,
			'skipped_total' => 0,
			'handled_total' => 0,
			'found_total' => 0,
			'matched_total' => 0,
			'failed_total' => 0,
			'status' => 'running',
			'current_url' => '',
			'started_at' => microtime(true),
		];
		}

	/**
	 * Return a public plan subset for SSE clients.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @return array<string, mixed>
	 **/
	private function publicPlan(array $plan): array
		{
		return [
			'range_mode' => (string) $plan['range_mode'],
			'country_code' => (string) $plan['country_code'],
			'start_ip' => (string) $plan['start_ip'],
			'end_ip' => (string) $plan['end_ip'],
			'generated_ip_count' => (int) $plan['generated_ip_count'],
			'target_total' => (int) $plan['target_total'],
			'keywords' => (array) $plan['keywords'],
			'timeout_ms' => (int) $plan['timeout_ms'],
			'concurrency' => (int) $plan['concurrency'],
			'skip_existing' => !empty($plan['skip_existing']),
		];
		}

	/**
	 * Return a public result subset for SSE clients.
	 * @param array<string, mixed> $result Result data.
	 * @return array<string, mixed>
	 **/
	private function publicResult(array $result): array
		{
		return [
			'ip_address' => (string) $result['ip_address'],
			'protocol' => (string) $result['protocol'],
			'port' => (int) $result['port'],
			'url' => (string) $result['url'],
			'http_status' => (int) $result['http_status'],
			'scan_status' => (string) ($result['scan_status'] ?? ''),
			'curl_errno' => (int) ($result['curl_errno'] ?? 0),
			'curl_error' => (string) ($result['curl_error'] ?? ''),
			'title' => (string) $result['title'],
			'content_type' => (string) $result['content_type'],
			'keyword_matched' => !empty($result['keyword_matched']),
			'matched_keywords' => (array) $result['matched_keywords'],
			'snippet' => (string) $result['snippet'],
			'response_time_ms' => (int) $result['response_time_ms'],
			'redirect_url' => (string) $result['redirect_url'],
			'server_header' => (string) $result['server_header'],
			'checked_at' => (string) $result['checked_at'],
		];
		}

	/**
	 * Build a Server-Sent Event payload.
	 * @param string $event Event name.
	 * @param array<string, mixed> $data Event data.
	 * @return array<string, mixed>
	 **/
	private function event(string $event, array $data): array
		{
		return [
			'event' => $event,
			'data' => $data,
		];
		}

	/**
	 * Assert the database tables required to save executions and results.
	 * @return void
	 **/
	private function assertRequiredTables(): void
		{
		foreach ([self::EXECUTIONS_TABLE, self::RESULTS_TABLE] as $table)
			{
			if (!$this->tableExists($table))
				{
				throw new \UnexpectedValueException('Missing table: ' . $table);
				}
			}
		}

	/**
	 * Check if a table exists in the current database.
	 * @param string $table Table name.
	 * @return bool
	 **/
	private function tableExists(string $table): bool
		{
		$stmt = $this->db->prepare("SELECT COUNT(*) AS total
		                            FROM information_schema.tables
		                            WHERE table_schema = DATABASE()
		                              AND table_name = :table_name");
		$stmt->execute(['table_name' => $table]);

		return ((int) $stmt->fetchColumn()) > 0;
		}

	/**
	 * Return the column map for the crawler results table.
	 * @return array<string, bool>
	 **/
	private function resultTableColumns(): array
		{
		if ($this->resultTableColumns !== null)
			{
			return $this->resultTableColumns;
			}

		$this->resultTableColumns = [];
		if (!$this->tableExists(self::RESULTS_TABLE))
			{
			return $this->resultTableColumns;
			}

		$stmt = $this->db->prepare("SELECT column_name
		                            FROM information_schema.columns
		                            WHERE table_schema = DATABASE()
		                              AND table_name = :table_name");
		$stmt->execute(['table_name' => self::RESULTS_TABLE]);

		foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $columnName)
			{
			$this->resultTableColumns[(string) $columnName] = true;
			}

		return $this->resultTableColumns;
		}

	/**
	 * Check whether the crawler results table has one column.
	 * @param string $column Column name.
	 * @return bool
	 **/
	private function hasResultColumn(string $column): bool
		{
		$columns = $this->resultTableColumns();
		return !empty($columns[$column]);
		}

	/**
	 * Determine whether a database exception indicates a missing table.
	 * @param PDOException $exception Database exception.
	 * @return bool
	 **/
	private function isMissingTableException(PDOException $exception): bool
		{
		return $exception->getCode() === '42S02' || strpos($exception->getMessage(), 'Base table or view not found') !== false || strpos($exception->getMessage(), 'Missing table:') !== false;
		}

	/**
	 * Normalize a source date from delegated files.
	 * @param string $value Raw source date.
	 * @return string|null
	 **/
	private function normalizeSourceDate(string $value): ?string
		{
		if (!preg_match('/^\d{8}$/', $value))
			{
			return null;
			}

		return substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
		}

	/**
	 * Normalize a country code and map known aliases.
	 * @param string $countryCode Raw country code.
	 * @return string
	 **/
	private function normalizeCountryCode(string $countryCode): string
		{
		$countryCode = strtoupper(trim($countryCode));
		if (isset(self::COUNTRY_CODE_ALIASES[$countryCode]))
			{
			$countryCode = self::COUNTRY_CODE_ALIASES[$countryCode];
			}

		return preg_match('/^[A-Z]{2}$/', $countryCode) ? $countryCode : '';
		}

	/**
	 * Return the display name for a country code.
	 * @param string $countryCode Country code.
	 * @return string
	 **/
	private function countryName(string $countryCode): string
		{
		return self::COUNTRY_NAMES[$countryCode] ?? $countryCode;
		}

	/**
	 * Convert an IPv4 address to an unsigned integer.
	 * @param string $ipAddress IPv4 address.
	 * @return int Unsigned IPv4 number.
	 **/
	private function ipv4ToNumber(string $ipAddress): int
		{
		$ipAddress = trim($ipAddress);
		if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)
			{
			throw new \InvalidArgumentException('IP inválido: ' . ($ipAddress !== '' ? $ipAddress : 'vazio') . '.');
			}

		return (int) sprintf('%u', ip2long($ipAddress));
		}

	/**
	 * Convert an unsigned integer to an IPv4 address.
	 * @param int $ipNumber Unsigned IPv4 number.
	 * @return string IPv4 address.
	 **/
	private function numberToIpv4(int $ipNumber): string
		{
		return long2ip($ipNumber);
		}

	/**
	 * Clamp an integer between min and max values.
	 * @param int $value Raw value.
	 * @param int $min Minimum value.
	 * @param int $max Maximum value.
	 * @return int Clamped value.
	 **/
	private function clampInt(int $value, int $min, int $max): int
		{
		if ($value < $min)
			{
			return $min;
			}

		if ($value > $max)
			{
			return $max;
			}

		return $value;
		}

	/**
	 * Determine how one crawl result should be stored.
	 * @param array<string, mixed> $result Result data.
	 * @return string
	 **/
	private function scanStatusForResult(array $result): string
		{
		$curlErrno = (int) ($result['curl_errno'] ?? 0);
		$httpStatus = (int) ($result['http_status'] ?? 0);

		if ($curlErrno === CURLE_OPERATION_TIMEDOUT)
			{
			return 'timeout';
			}

		if ($curlErrno !== 0 || $httpStatus === 0)
			{
			return 'error';
			}

		if ($httpStatus === 200)
			{
			return 'found';
			}

		return 'not_found';
		}

	/**
	 * Count IPs across normalized ranges.
	 * @param array<int, array<string, int>> $ranges Ranges.
	 * @return int IP count.
	 **/
	private function countRangeIps(array $ranges): int
		{
		$total = 0;
		foreach ($ranges as $range)
			{
			$total += ((int) $range['end'] - (int) $range['start']) + 1;
			}

		return $total;
		}

	/**
	 * Normalize keywords from text input.
	 * @param string $value Raw keywords.
	 * @return array<int, string>
	 **/
	private function keywordsFromInput(string $value): array
		{
		$items = preg_split('/[\r\n,;]+/', $value) ?: [];
		$keywords = [];

		foreach ($items as $item)
			{
			$item = trim((string) $item);
			if ($item === '')
				{
				continue;
				}

			$keywords[$item] = $item;
			}

		return array_values($keywords);
		}

	/**
	 * Normalize the selected user agent.
	 * @param string $value Raw user agent.
	 * @return string User agent.
	 **/
	private function normalizeUserAgent(string $value): string
		{
		$value = trim($value);
		if ($value === '')
			{
			return self::DEFAULT_USER_AGENT;
			}

		return $this->limitString($value, 255);
		}

	/**
	 * Extract the page title.
	 * @param string $body Response body.
	 * @return string Title.
	 **/
	private function extractTitle(string $body): string
		{
		if (!preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches))
			{
			return '';
			}

		$title = html_entity_decode(strip_tags((string) $matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$title = preg_replace('/\s+/', ' ', trim($title)) ?? '';

		return $this->limitString($title, 255);
		}

	/**
	 * Extract the page meta description.
	 * @param string $body Response body.
	 * @return string Page description.
	 **/
	private function extractDescription(string $body): string
		{
		if (!preg_match('/<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/is', $body, $matches)
			&& !preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']description["\'][^>]*>/is', $body, $matches))
			{
			return '';
			}

		$description = html_entity_decode((string) $matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$description = preg_replace('/\s+/', ' ', trim($description)) ?? '';

		return $this->limitString($description, 500);
		}

	/**
	 * Find matched keywords in response content.
	 * @param string $body Response body.
	 * @param string $title Page title.
	 * @param array<int, string> $keywords Keywords.
	 * @return array<int, string>
	 **/
	private function matchedKeywords(string $body, string $title, array $keywords): array
		{
		if (empty($keywords))
			{
			return [];
			}

		$haystack = $title . "\n" . strip_tags($body);
		$matches = [];

		foreach ($keywords as $keyword)
			{
			if ($keyword !== '' && stripos($haystack, $keyword) !== false)
				{
				$matches[] = $keyword;
				}
			}

		return $matches;
		}

	/**
	 * Build a compact text snippet around the first matched keyword.
	 * @param string $body Response body.
	 * @param string $title Page title.
	 * @param array<int, string> $matchedKeywords Matched keywords.
	 * @return string Snippet.
	 **/
	private function snippetForKeywords(string $body, string $title, array $matchedKeywords): string
		{
		$text = preg_replace('/\s+/', ' ', trim($title . ' ' . strip_tags($body))) ?? '';
		if ($text === '')
			{
			return '';
			}

		if (empty($matchedKeywords))
			{
			return $this->limitString($text, 260);
			}

		$position = stripos($text, (string) $matchedKeywords[0]);
		if ($position === false)
			{
			return $this->limitString($text, 260);
			}

		$start = max(0, $position - 90);
		$snippet = substr($text, $start, 260);

		return $this->limitString($snippet, 260);
		}

	/**
	 * Read content length from response headers.
	 * @param array<int, string> $headers Response headers.
	 * @param int $fallback Fallback length.
	 * @return int Content length.
	 **/
	private function contentLengthFromHeaders(array $headers, int $fallback): int
		{
		foreach ($headers as $header)
			{
			if (stripos((string) $header, 'Content-Length:') === 0)
				{
				return max(0, (int) trim(substr((string) $header, 15)));
				}
			}

		return $fallback;
		}

	/**
	 * Read server header from response headers.
	 * @param array<int, string> $headers Response headers.
	 * @return string Server header.
	 **/
	private function serverHeader(array $headers): string
		{
		foreach ($headers as $header)
			{
			if (stripos((string) $header, 'Server:') === 0)
				{
				return trim(substr((string) $header, 7));
				}
			}

		return '';
		}

	/**
	 * Build a stable array key for curl handles.
	 * @param mixed $curl Curl handle.
	 * @return string Stable key.
	 **/
	private function curlHandleKey($curl): string
		{
		if (is_object($curl))
			{
			return 'o' . spl_object_id($curl);
			}

		if (is_resource($curl))
			{
			return 'r' . get_resource_id($curl);
			}

		return md5((string) $curl);
		}

	/**
	 * Limit a string without requiring mbstring.
	 * @param string $value String value.
	 * @param int $limit Maximum length.
	 * @return string Limited string.
	 **/
	private function limitString(string $value, int $limit): string
		{
		$value = trim($value);
		if ($value === '')
			{
			return '';
			}

		if (function_exists('mb_substr'))
			{
			return mb_substr($value, 0, $limit, 'UTF-8');
			}

		return substr($value, 0, $limit);
		}
	}

?>

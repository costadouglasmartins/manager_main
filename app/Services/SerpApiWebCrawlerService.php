<?php

namespace App\Services;

use App\Core\Database;
use Generator;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Runs SerpApi searches with an internal monthly quota and SSE-ready payloads.
 **/
class SerpApiWebCrawlerService
	{
	private const SEARCHES_TABLE = 'serpapi_searches';
	private const RESULTS_TABLE = 'serpapi_search_results';
	private const USAGE_TABLE = 'serpapi_usage_monthly';
	private const API_ENDPOINT = 'https://serpapi.com/search.json';
	private const API_KEY = 'ffbf2388b779e3e2c48842b753127594f8d037e0dfcd113254d3201f3fb277a0';
	private const DEFAULT_MONTHLY_LIMIT = 250;
	private const MAX_RESULTS = 100;
	private const DEFAULT_TIMEOUT_SECONDS = 20;
	private const QUOTA_KEY = 'default';

	/**
	 * Database connection used by the service.
	 * @var PDO
	 **/
	private $db;

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
	 * Return search type options for the tool form.
	 * @return array<int, array<string, string>>
	 **/
	public static function searchTypeOptions(): array
		{
		return [
			['label' => 'Google Web', 'value' => 'web'],
			['label' => 'Google Notícias', 'value' => 'news'],
			['label' => 'Google Imagens', 'value' => 'images'],
		];
		}

	/**
	 * Return country options for Google localization.
	 * @return array<int, array<string, string>>
	 **/
	public static function countryOptions(): array
		{
		return [
			['label' => 'Brasil', 'value' => 'br'],
			['label' => 'Estados Unidos', 'value' => 'us'],
			['label' => 'Argentina', 'value' => 'ar'],
			['label' => 'Chile', 'value' => 'cl'],
			['label' => 'Espanha', 'value' => 'es'],
			['label' => 'Portugal', 'value' => 'pt'],
			['label' => 'Reino Unido', 'value' => 'uk'],
			['label' => 'Global', 'value' => ''],
		];
		}

	/**
	 * Return language options for Google results.
	 * @return array<int, array<string, string>>
	 **/
	public static function languageOptions(): array
		{
		return [
			['label' => 'Português', 'value' => 'pt'],
			['label' => 'Inglês', 'value' => 'en'],
			['label' => 'Espanhol', 'value' => 'es'],
			['label' => 'Francês', 'value' => 'fr'],
			['label' => 'Alemão', 'value' => 'de'],
		];
		}

	/**
	 * Return the fixed monthly query limit.
	 * @return int
	 **/
	public static function monthlyLimit(): int
		{
		return self::DEFAULT_MONTHLY_LIMIT;
		}

	/**
	 * Return the fixed monthly query limit.
	 * @return int
	 **/
	public static function maxMonthlyLimit(): int
		{
		return self::monthlyLimit();
		}

	/**
	 * Return the maximum number of results accepted by one search.
	 * @return int
	 **/
	public static function maxResults(): int
		{
		return self::MAX_RESULTS;
		}

	/**
	 * Check if the SerpApi key is configured.
	 * @return bool
	 **/
	public static function hasApiKey(): bool
		{
		return self::apiKeyFromEnvironment() !== '';
		}

	/**
	 * Return the current internal monthly usage snapshot.
	 * @param int|null $monthlyLimit Optional internal monthly limit.
	 * @return array<string, mixed>
	 **/
	public function usageSnapshot(?int $monthlyLimit = null): array
		{
		$period = $this->currentPeriod();
		$limit = $this->normalizeMonthlyLimit($monthlyLimit);

		try
			{
			if (!$this->tableExists(self::USAGE_TABLE))
				{
				return $this->usagePayload($period, $limit, 0, false);
				}

			$this->ensureUsageRow($period, $limit, $monthlyLimit !== null);
			$stmt = $this->db->prepare("SELECT UsageMonth.limit_count,
			                                   UsageMonth.used_count
			                            FROM serpapi_usage_monthly AS UsageMonth
			                            WHERE UsageMonth.usage_period = :usage_period
			                              AND UsageMonth.quota_key = :quota_key
			                            LIMIT 1");
			$stmt->execute([
				'usage_period' => $period,
				'quota_key' => self::QUOTA_KEY,
			]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

			return $this->usagePayload($period, (int) ($row['limit_count'] ?? $limit), (int) ($row['used_count'] ?? 0), true);
			}
		catch (Throwable $exception)
			{
			return $this->usagePayload($period, $limit, 0, false);
			}
		}

	/**
	 * Stream a SerpApi search execution.
	 * @param array<string, mixed> $input Request input.
	 * @return Generator<int, array<string, mixed>>
	 **/
	public function stream(array $input): Generator
		{
		$searchId = 0;
		$stats = $this->initialStats($this->usageSnapshot());

		try
			{
			yield $this->event('connected', [
				'message' => 'Servidor conectado. Validando configuração e parâmetros.',
				'usage' => $stats['usage'],
				'stats' => $stats,
			]);

			if (!extension_loaded('curl'))
				{
				yield $this->event('validation_error', [
					'message' => 'A extensão cURL do PHP precisa estar habilitada para consultar a SerpAPI.',
					'usage' => $stats['usage'],
				]);
				return;
				}

			$this->assertRequiredTables();

			$apiKey = self::apiKeyFromEnvironment();
			if ($apiKey === '')
				{
				yield $this->event('validation_error', [
					'message' => 'Configure SERPAPI_API_KEY ou APP_SERPAPI_API_KEY antes de executar a ferramenta.',
					'usage' => $this->usageSnapshot(),
				]);
				return;
				}

			$plan = $this->buildPlan($input);
			$currentUsage = $this->usageSnapshot((int) $plan['monthly_limit']);
			$stats = $this->initialStats($currentUsage);
			$plannedQueryCount = (int) $plan['planned_query_count'];

			if ((int) $plan['execution_query_limit'] < 1)
				{
				yield $this->event('validation_error', [
					'message' => 'O limite de consultas desta execução precisa ser maior que zero.',
					'usage' => $currentUsage,
					'stats' => $stats,
				]);
				return;
				}

			if ((int) $plan['execution_query_limit'] < $plannedQueryCount)
				{
				yield $this->event('validation_error', [
					'message' => 'O limite manual desta execução é menor que a quantidade de consultas planejadas.',
					'usage' => $currentUsage,
					'stats' => $stats,
				]);
				return;
				}

			if ((int) ($currentUsage['remaining'] ?? 0) < $plannedQueryCount)
				{
				yield $this->event('quota_exhausted', [
					'message' => 'Limite mensal de consultas da SerpAPI esgotado para este mês.',
					'usage' => $currentUsage,
					'stats' => $stats,
				]);
				return;
				}

			$searchId = $this->createSearch($plan, $currentUsage);

			yield $this->event('started', [
				'search_id' => $searchId,
				'plan' => $this->publicPlan($plan),
				'stats' => $stats,
				'usage' => $currentUsage,
				'message' => 'Consulta preparada.',
			]);

			$reservedUsage = $this->reserveQuota((int) $plan['monthly_limit'], $plannedQueryCount);
			$stats['usage'] = $reservedUsage;
			$stats['query_count'] = $plannedQueryCount;
			$this->updateSearch($searchId, $stats, 'running', 'Consulta enviada para a SerpAPI.');

			yield $this->event('quota', [
				'search_id' => $searchId,
				'stats' => $stats,
				'usage' => $reservedUsage,
				'message' => 'Consulta contabilizada.',
			]);

			$response = $this->requestSearch($plan, $apiKey);
			$stats['response_time_ms'] = (int) $response['response_time_ms'];
			$stats['http_status'] = (int) $response['http_status'];

			$this->saveSearchResponse($searchId, $response);

			if (empty($response['success']))
				{
				$stats['failed_total'] = 1;
				$this->updateSearch($searchId, $stats, 'failed', (string) $response['message']);
				yield $this->event('api_error', [
					'search_id' => $searchId,
					'stats' => $stats,
					'usage' => $reservedUsage,
					'message' => (string) $response['message'],
				]);
				return;
				}

			$results = $this->extractResults((array) $response['payload'], $plan);
			$stats['target_total'] = count($results);

			yield $this->event('progress', [
				'search_id' => $searchId,
				'stats' => $stats,
				'usage' => $reservedUsage,
			]);

			foreach ($results as $result)
				{
				$this->saveResult($searchId, $plan, $result);
				$stats['processed_total']++;
				$stats['found_total']++;

				yield $this->event('result', [
					'search_id' => $searchId,
					'result' => $this->publicResult($result),
					'stats' => $stats,
					'usage' => $reservedUsage,
				]);
			}

			$stats['status'] = 'completed';
			$this->updateSearch($searchId, $stats, 'completed', 'Consulta finalizada.');

			yield $this->event('summary', [
				'search_id' => $searchId,
				'stats' => $stats,
				'usage' => $reservedUsage,
				'message' => 'Consulta finalizada.',
			]);
			}
		catch (\UnexpectedValueException $exception)
			{
			if ($searchId > 0)
				{
				$this->markSearchAsFailed($searchId, 'Schema SQL da SerpAPI ausente.');
				}

			yield $this->event('schema_missing', [
				'message' => 'Crie as tabelas da SerpAPI antes de executar a ferramenta.',
				'usage' => $this->usageSnapshot(),
			]);
			}
		catch (\InvalidArgumentException $exception)
			{
			if ($searchId > 0)
				{
				$this->markSearchAsFailed($searchId, $exception->getMessage());
				}

			yield $this->event('validation_error', [
				'message' => $exception->getMessage(),
				'usage' => $this->usageSnapshot(),
			]);
			}
		catch (PDOException $exception)
			{
			if ($searchId > 0)
				{
				$this->markSearchAsFailed($searchId, 'Erro de banco de dados durante a consulta.');
				}

			yield $this->event($this->isMissingTableException($exception) ? 'schema_missing' : 'error', [
				'message' => $this->isMissingTableException($exception)
					? 'Crie as tabelas da SerpAPI antes de executar a ferramenta.'
					: 'Erro de banco de dados durante a consulta.',
				'usage' => $this->usageSnapshot(),
			]);
			}
		catch (Throwable $exception)
			{
			if ($searchId > 0)
				{
				$this->markSearchAsFailed($searchId, $exception->getMessage());
				}

			yield $this->event('error', [
				'message' => 'Não foi possível executar a consulta: ' . $exception->getMessage(),
				'usage' => $this->usageSnapshot(),
			]);
			}
		}

	/**
	 * Search stored SerpApi results using report filters.
	 * @param array<string, mixed> $filters Report filters.
	 * @param int $limit Maximum rows.
	 * @param int $offset Result offset.
	 * @param array<string, mixed>|null $sort Sort state.
	 * @return array<int, array<string, mixed>>
	 **/
	public function searchResults(array $filters = [], int $limit = 20, int $offset = 0, ?array $sort = null): array
		{
		if (!$this->tableExists(self::RESULTS_TABLE) || !$this->tableExists(self::SEARCHES_TABLE))
			{
			return [];
			}

		[$whereSql, $params] = $this->reportWhere($filters);
		$orderBy = $this->reportOrderBy($sort);
		$sql = "SELECT Result.id,
		               Result.search_id,
		               Result.search_type,
		               Result.engine,
		               Result.position,
		               Result.title,
		               Result.link,
		               Result.displayed_link,
		               Result.source,
		               Result.snippet,
		               Result.thumbnail,
		               Result.result_date,
		               Result.result_type,
		               Result.checked_at,
		               Search.query,
		               Search.location,
		               Search.country_code,
		               Search.language_code
		        FROM serpapi_search_results AS Result
		        INNER JOIN serpapi_searches AS Search ON Search.id = Result.search_id
		        {$whereSql}
		        ORDER BY {$orderBy}
		        LIMIT :limit OFFSET :offset";
		$stmt = $this->db->prepare($sql);
		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value);
			}
		$stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
		$stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

	/**
	 * Count stored SerpApi results using report filters.
	 * @param array<string, mixed> $filters Report filters.
	 * @return int
	 **/
	public function countResults(array $filters = []): int
		{
		if (!$this->tableExists(self::RESULTS_TABLE) || !$this->tableExists(self::SEARCHES_TABLE))
			{
			return 0;
			}

		[$whereSql, $params] = $this->reportWhere($filters);
		$stmt = $this->db->prepare("SELECT COUNT(Result.id)
		                            FROM serpapi_search_results AS Result
		                            INNER JOIN serpapi_searches AS Search ON Search.id = Result.search_id
		                            {$whereSql}");
		$stmt->execute($params);

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Return summary counters for stored SerpApi results.
	 * @param array<string, mixed> $filters Report filters.
	 * @return array<string, int>
	 **/
	public function reportSummary(array $filters = []): array
		{
		if (!$this->tableExists(self::RESULTS_TABLE) || !$this->tableExists(self::SEARCHES_TABLE))
			{
			return [
				'total' => 0,
				'web_total' => 0,
				'news_total' => 0,
				'images_total' => 0,
			];
			}

		[$whereSql, $params] = $this->reportWhere($filters);
		$stmt = $this->db->prepare("SELECT COUNT(Result.id) AS total,
		                                   SUM(CASE WHEN Result.search_type = 'web' THEN 1 ELSE 0 END) AS web_total,
		                                   SUM(CASE WHEN Result.search_type = 'news' THEN 1 ELSE 0 END) AS news_total,
		                                   SUM(CASE WHEN Result.search_type = 'images' THEN 1 ELSE 0 END) AS images_total
		                            FROM serpapi_search_results AS Result
		                            INNER JOIN serpapi_searches AS Search ON Search.id = Result.search_id
		                            {$whereSql}");
		$stmt->execute($params);
		$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

		return [
			'total' => (int) ($row['total'] ?? 0),
			'web_total' => (int) ($row['web_total'] ?? 0),
			'news_total' => (int) ($row['news_total'] ?? 0),
			'images_total' => (int) ($row['images_total'] ?? 0),
		];
		}

	/**
	 * Delete stored SerpApi result rows by id.
	 * @param array<int, int> $ids Result ids.
	 * @return int Deleted row count.
	 **/
	public function deleteResultsByIds(array $ids): int
		{
		if (!$this->tableExists(self::RESULTS_TABLE))
			{
			return 0;
			}

		$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function (int $id): bool {
			return $id > 0;
		})));
		if (empty($ids))
			{
			return 0;
			}

		$placeholders = [];
		$params = [];
		foreach ($ids as $index => $id)
			{
			$placeholder = ':id_' . $index;
			$placeholders[] = $placeholder;
			$params[$placeholder] = $id;
			}

		$stmt = $this->db->prepare('DELETE FROM serpapi_search_results WHERE id IN (' . implode(', ', $placeholders) . ')');
		foreach ($params as $placeholder => $id)
			{
			$stmt->bindValue($placeholder, $id, PDO::PARAM_INT);
			}
		$stmt->execute();

		return $stmt->rowCount();
		}

	/**
	 * Build and validate a normalized search plan.
	 * @param array<string, mixed> $input Raw request input.
	 * @return array<string, mixed>
	 **/
	private function buildPlan(array $input): array
		{
		$query = $this->limitString(preg_replace('/\s+/', ' ', trim((string) ($input['query'] ?? ''))) ?? '', 300);
		if ($query === '')
			{
			throw new \InvalidArgumentException('Informe uma consulta para pesquisar na SerpAPI.');
			}

		$searchType = strtolower(trim((string) ($input['search_type'] ?? 'web')));
		if (!in_array($searchType, ['web', 'news', 'images'], true))
			{
			$searchType = 'web';
			}

		$countryCode = strtolower(trim((string) ($input['country_code'] ?? 'br')));
		if ($countryCode !== '' && !preg_match('/^[a-z]{2}$/', $countryCode))
			{
			$countryCode = 'br';
			}

		$languageCode = strtolower(trim((string) ($input['language_code'] ?? 'pt')));
		if (!preg_match('/^[a-z]{2}$/', $languageCode))
			{
			$languageCode = 'pt';
			}

		$device = strtolower(trim((string) ($input['device'] ?? 'desktop')));
		if (!in_array($device, ['desktop', 'mobile', 'tablet'], true))
			{
			$device = 'desktop';
			}

		$safeSearch = strtolower(trim((string) ($input['safe_search'] ?? 'active')));
		if (!in_array($safeSearch, ['active', 'off'], true))
			{
			$safeSearch = 'active';
			}

		$resultCount = $this->clampInt((int) ($input['result_count'] ?? 10), 1, self::MAX_RESULTS);
		$startOffset = $this->clampInt((int) ($input['start_offset'] ?? 0), 0, 900);
		$monthlyLimit = self::monthlyLimit();
		$executionQueryLimit = $this->normalizeExecutionQueryLimit((int) ($input['execution_query_limit'] ?? 1), $monthlyLimit);
		$plannedQueryCount = 1;

		return [
			'user_id' => (int) ($input['user_id'] ?? 0),
			'query' => $query,
			'search_type' => $searchType,
			'engine' => $this->engineForSearchType($searchType),
			'location' => $this->limitString(trim((string) ($input['location'] ?? '')), 255),
			'country_code' => $countryCode,
			'language_code' => $languageCode,
			'device' => $device,
			'safe_search' => $safeSearch,
			'result_count' => $resultCount,
			'start_offset' => $startOffset,
			'monthly_limit' => $monthlyLimit,
			'execution_query_limit' => $executionQueryLimit,
			'planned_query_count' => $plannedQueryCount,
		];
		}

	/**
	 * Return the SerpApi engine for one search type.
	 * @param string $searchType Search type selected by the user.
	 * @return string SerpApi engine name.
	 **/
	private function engineForSearchType(string $searchType): string
		{
		if ($searchType === 'news')
			{
			return 'google_news';
			}

		if ($searchType === 'images')
			{
			return 'google_images';
			}

		return 'google';
		}

	/**
	 * Create a search execution row.
	 * @param array<string, mixed> $plan Normalized plan.
	 * @param array<string, mixed> $usage Current usage snapshot.
	 * @return int New search id.
	 **/
	private function createSearch(array $plan, array $usage): int
		{
		$now = date('Y-m-d H:i:s');
		$requestParams = $this->requestParams($plan, 'configured');
		$stmt = $this->db->prepare("INSERT INTO serpapi_searches
			(`user_id`, `search_type`, `engine`, `query`, `location`, `country_code`, `language_code`, `device`, `safe_search`, `result_count`, `start_offset`, `request_params_json`, `usage_period`, `usage_limit`, `usage_used`, `usage_remaining`, `status`, `started_at`, `created`, `modified`)
			VALUES
			(:user_id, :search_type, :engine, :query, :location, :country_code, :language_code, :device, :safe_search, :result_count, :start_offset, :request_params_json, :usage_period, :usage_limit, :usage_used, :usage_remaining, 'running', :started_at, :created, :modified)");
		$stmt->execute([
			'user_id' => ((int) $plan['user_id'] > 0 ? (int) $plan['user_id'] : null),
			'search_type' => (string) $plan['search_type'],
			'engine' => (string) $plan['engine'],
			'query' => (string) $plan['query'],
			'location' => (string) $plan['location'] !== '' ? (string) $plan['location'] : null,
			'country_code' => (string) $plan['country_code'] !== '' ? (string) $plan['country_code'] : null,
			'language_code' => (string) $plan['language_code'],
			'device' => (string) $plan['device'],
			'safe_search' => (string) $plan['safe_search'],
			'result_count' => (int) $plan['result_count'],
			'start_offset' => (int) $plan['start_offset'],
			'request_params_json' => json_encode($requestParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'usage_period' => (string) ($usage['period'] ?? $this->currentPeriod()),
			'usage_limit' => (int) ($usage['limit'] ?? self::monthlyLimit()),
			'usage_used' => (int) ($usage['used'] ?? 0),
			'usage_remaining' => (int) ($usage['remaining'] ?? 0),
			'started_at' => $now,
			'created' => $now,
			'modified' => $now,
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Update a search row with progress counters.
	 * @param int $searchId Search id.
	 * @param array<string, mixed> $stats Execution counters.
	 * @param string $status Execution status.
	 * @param string $message Status message.
	 * @return void
	 **/
	private function updateSearch(int $searchId, array $stats, string $status, string $message): void
		{
		$usage = is_array($stats['usage'] ?? null) ? $stats['usage'] : null;
		$finishedStatuses = ['completed', 'failed', 'stopped'];
		$finishedSql = in_array($status, $finishedStatuses, true) ? ', `finished_at` = :finished_at' : '';
		$usageSql = $usage !== null
			? "`usage_limit` = :usage_limit,
		                                `usage_used` = :usage_used,
		                                `usage_remaining` = :usage_remaining,"
			: '';
		$stmt = $this->db->prepare("UPDATE serpapi_searches
		                            SET `query_count` = :query_count,
		                                `result_total` = :result_total,
		                                `processed_total` = :processed_total,
		                                `failed_total` = :failed_total,
		                                `status` = :status,
		                                `message` = :message,
		                                {$usageSql}
		                                `response_time_ms` = :response_time_ms,
		                                `http_status` = :http_status,
		                                `modified` = :modified
		                                {$finishedSql}
		                            WHERE `id` = :id");
		$params = [
			'query_count' => (int) ($stats['query_count'] ?? 0),
			'result_total' => (int) ($stats['found_total'] ?? 0),
			'processed_total' => (int) ($stats['processed_total'] ?? 0),
			'failed_total' => (int) ($stats['failed_total'] ?? 0),
			'status' => $status,
			'message' => $this->limitString($message, 500),
			'response_time_ms' => (int) ($stats['response_time_ms'] ?? 0),
			'http_status' => (int) ($stats['http_status'] ?? 0),
			'modified' => date('Y-m-d H:i:s'),
			'id' => $searchId,
		];

		if ($usage !== null)
			{
			$params['usage_limit'] = (int) ($usage['limit'] ?? self::monthlyLimit());
			$params['usage_used'] = (int) ($usage['used'] ?? 0);
			$params['usage_remaining'] = (int) ($usage['remaining'] ?? 0);
			}

		if ($finishedSql !== '')
			{
			$params['finished_at'] = date('Y-m-d H:i:s');
			}

		$stmt->execute($params);
		}

	/**
	 * Save raw SerpApi response metadata on the search row.
	 * @param int $searchId Search id.
	 * @param array<string, mixed> $response Response payload.
	 * @return void
	 **/
	private function saveSearchResponse(int $searchId, array $response): void
		{
		$payload = is_array($response['payload'] ?? null) ? $response['payload'] : [];
		$metadata = is_array($payload['search_metadata'] ?? null) ? $payload['search_metadata'] : [];
		$stmt = $this->db->prepare("UPDATE serpapi_searches
		                            SET `response_metadata_json` = :response_metadata_json,
		                                `raw_response_json` = :raw_response_json,
		                                `response_time_ms` = :response_time_ms,
		                                `http_status` = :http_status,
		                                `modified` = :modified
		                            WHERE `id` = :id");
		$stmt->execute([
			'response_metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'raw_response_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
			'response_time_ms' => (int) ($response['response_time_ms'] ?? 0),
			'http_status' => (int) ($response['http_status'] ?? 0),
			'modified' => date('Y-m-d H:i:s'),
			'id' => $searchId,
		]);
		}

	/**
	 * Save one normalized SerpApi result.
	 * @param int $searchId Search id.
	 * @param array<string, mixed> $plan Normalized plan.
	 * @param array<string, mixed> $result Normalized result.
	 * @return void
	 **/
	private function saveResult(int $searchId, array $plan, array $result): void
		{
		$now = date('Y-m-d H:i:s');
		$stmt = $this->db->prepare("INSERT INTO serpapi_search_results
			(`search_id`, `user_id`, `search_type`, `engine`, `position`, `title`, `link`, `displayed_link`, `source`, `snippet`, `thumbnail`, `result_date`, `result_type`, `result_hash`, `raw_result_json`, `checked_at`, `created`, `modified`)
			VALUES
			(:search_id, :user_id, :search_type, :engine, :position, :title, :link, :displayed_link, :source, :snippet, :thumbnail, :result_date, :result_type, :result_hash, :raw_result_json, :checked_at, :created, :modified)
			ON DUPLICATE KEY UPDATE
				`title` = VALUES(`title`),
				`link` = VALUES(`link`),
				`displayed_link` = VALUES(`displayed_link`),
				`source` = VALUES(`source`),
				`snippet` = VALUES(`snippet`),
				`thumbnail` = VALUES(`thumbnail`),
				`result_date` = VALUES(`result_date`),
				`raw_result_json` = VALUES(`raw_result_json`),
				`modified` = VALUES(`modified`)");
		$stmt->execute([
			'search_id' => $searchId,
			'user_id' => ((int) $plan['user_id'] > 0 ? (int) $plan['user_id'] : null),
			'search_type' => (string) $plan['search_type'],
			'engine' => (string) $plan['engine'],
			'position' => (int) ($result['position'] ?? 0),
			'title' => (string) ($result['title'] ?? ''),
			'link' => (string) ($result['link'] ?? ''),
			'displayed_link' => (string) ($result['displayed_link'] ?? ''),
			'source' => (string) ($result['source'] ?? ''),
			'snippet' => (string) ($result['snippet'] ?? ''),
			'thumbnail' => (string) ($result['thumbnail'] ?? ''),
			'result_date' => (string) ($result['date'] ?? ''),
			'result_type' => (string) ($result['result_type'] ?? ''),
			'result_hash' => (string) ($result['result_hash'] ?? ''),
			'raw_result_json' => json_encode($result['raw'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
			'checked_at' => $now,
			'created' => $now,
			'modified' => $now,
		]);
		}

	/**
	 * Request one search from SerpApi.
	 * @param array<string, mixed> $plan Normalized plan.
	 * @param string $apiKey Private SerpApi key.
	 * @return array<string, mixed>
	 **/
	private function requestSearch(array $plan, string $apiKey): array
		{
		$params = $this->requestParams($plan, $apiKey);
		$url = self::API_ENDPOINT . '?' . http_build_query($params);
		$handle = curl_init($url);
		if ($handle === false)
			{
			throw new RuntimeException('Não foi possível inicializar a chamada para a SerpAPI.');
			}

		curl_setopt_array($handle, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => self::DEFAULT_TIMEOUT_SECONDS,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_HTTPHEADER => ['Accept: application/json'],
			CURLOPT_USERAGENT => 'Manager SerpApiWebCrawler/1.0',
		]);

		$started = microtime(true);
		$body = curl_exec($handle);
		$responseTimeMs = (int) round((microtime(true) - $started) * 1000);
		$httpStatus = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
		$curlErrno = (int) curl_errno($handle);
		$curlError = (string) curl_error($handle);
		curl_close($handle);

		if ($curlErrno !== 0 || !is_string($body))
			{
			return [
				'success' => false,
				'http_status' => $httpStatus,
				'response_time_ms' => $responseTimeMs,
				'payload' => [],
				'message' => $curlError !== '' ? $curlError : 'Falha de rede ao consultar a SerpAPI.',
			];
			}

		$payload = json_decode($body, true);
		if (!is_array($payload))
			{
			return [
				'success' => false,
				'http_status' => $httpStatus,
				'response_time_ms' => $responseTimeMs,
				'payload' => ['raw' => $this->limitString($body, 2000)],
				'message' => 'A SerpAPI retornou uma resposta que não é JSON válido.',
			];
			}

		$errorMessage = trim((string) ($payload['error'] ?? ''));
		$success = $httpStatus >= 200 && $httpStatus < 300 && $errorMessage === '';

		return [
			'success' => $success,
			'http_status' => $httpStatus,
			'response_time_ms' => $responseTimeMs,
			'payload' => $payload,
			'message' => $success ? 'Consulta concluída.' : ($errorMessage !== '' ? $errorMessage : 'A SerpAPI recusou a consulta.'),
		];
		}

	/**
	 * Build SerpApi request parameters.
	 * @param array<string, mixed> $plan Normalized plan.
	 * @param string $apiKey Private SerpApi key or placeholder.
	 * @return array<string, mixed>
	 **/
	private function requestParams(array $plan, string $apiKey): array
		{
		$params = [
			'engine' => (string) $plan['engine'],
			'q' => (string) $plan['query'],
			'api_key' => $apiKey,
			'hl' => (string) $plan['language_code'],
			'num' => (int) $plan['result_count'],
			'device' => (string) $plan['device'],
			'safe' => (string) $plan['safe_search'],
		];

		if ((string) $plan['country_code'] !== '')
			{
			$params['gl'] = (string) $plan['country_code'];
			}

		if ((string) $plan['location'] !== '')
			{
			$params['location'] = (string) $plan['location'];
			}

		if ((int) $plan['start_offset'] > 0)
			{
			$params['start'] = (int) $plan['start_offset'];
			}

		return $params;
		}

	/**
	 * Extract normalized result rows from a SerpApi payload.
	 * @param array<string, mixed> $payload SerpApi JSON payload.
	 * @param array<string, mixed> $plan Normalized plan.
	 * @return array<int, array<string, mixed>>
	 **/
	private function extractResults(array $payload, array $plan): array
		{
		$collection = 'organic_results';
		if ((string) $plan['search_type'] === 'news')
			{
			$collection = 'news_results';
			}
		elseif ((string) $plan['search_type'] === 'images')
			{
			$collection = 'images_results';
			}

		$rawResults = is_array($payload[$collection] ?? null) ? $payload[$collection] : [];
		$results = [];

		foreach ($rawResults as $index => $rawResult)
			{
			if (!is_array($rawResult))
				{
				continue;
				}

			$result = $this->normalizeResult($rawResult, (string) $plan['search_type'], $index + 1);
			if ((string) $result['title'] === '' && (string) $result['link'] === '')
				{
				continue;
				}

			$results[] = $result;
		}

		return $results;
		}

	/**
	 * Normalize one SerpApi result row.
	 * @param array<string, mixed> $rawResult Raw SerpApi result.
	 * @param string $searchType Search type selected by the user.
	 * @param int $fallbackPosition Position used when the API omits it.
	 * @return array<string, mixed>
	 **/
	private function normalizeResult(array $rawResult, string $searchType, int $fallbackPosition): array
		{
		$link = (string) ($rawResult['link'] ?? '');
		if ($searchType === 'images' && $link === '')
			{
			$link = (string) ($rawResult['original'] ?? '');
			}

		$source = '';
		if (is_array($rawResult['source'] ?? null))
			{
			$source = (string) ($rawResult['source']['name'] ?? '');
			}
		else
			{
			$source = (string) ($rawResult['source'] ?? '');
			}

		$displayedLinkValue = '';
		if (isset($rawResult['displayed_link']))
			{
			$displayedLinkValue = (string) $rawResult['displayed_link'];
			}
		elseif (isset($rawResult['source']) && !is_array($rawResult['source']))
			{
			$displayedLinkValue = (string) $rawResult['source'];
			}

		$position = (int) ($rawResult['position'] ?? $rawResult['rank'] ?? $fallbackPosition);
		$title = $this->limitString((string) ($rawResult['title'] ?? ''), 500);
		$snippet = $this->limitString((string) ($rawResult['snippet'] ?? ''), 1000);
		$displayedLink = $this->limitString($displayedLinkValue, 500);
		$thumbnail = $this->limitString((string) ($rawResult['thumbnail'] ?? ''), 1000);
		$date = $this->limitString((string) ($rawResult['date'] ?? ''), 100);
		$resultHash = sha1($searchType . '|' . strtolower(trim($link)) . '|' . strtolower(trim($title)) . '|' . $position);

		return [
			'position' => $position,
			'title' => $title,
			'link' => $this->limitString($link, 1000),
			'displayed_link' => $displayedLink,
			'source' => $this->limitString($source, 255),
			'snippet' => $snippet,
			'thumbnail' => $thumbnail,
			'date' => $date,
			'result_type' => $searchType,
			'result_hash' => $resultHash,
			'raw' => $rawResult,
		];
		}

	/**
	 * Reserve monthly queries in a transaction.
	 * @param int $monthlyLimit Monthly quota limit.
	 * @param int $queryCount Query count to reserve.
	 * @return array<string, mixed> Updated usage snapshot.
	 **/
	private function reserveQuota(int $monthlyLimit, int $queryCount = 1): array
		{
		$period = $this->currentPeriod();
		$limit = $this->normalizeMonthlyLimit($monthlyLimit);
		$queryCount = max(1, $queryCount);

		$this->db->beginTransaction();
		try
			{
			$this->ensureUsageRow($period, $limit, true);
			$stmt = $this->db->prepare("SELECT UsageMonth.id,
			                                   UsageMonth.limit_count,
			                                   UsageMonth.used_count
			                            FROM serpapi_usage_monthly AS UsageMonth
			                            WHERE UsageMonth.usage_period = :usage_period
			                              AND UsageMonth.quota_key = :quota_key
			                            FOR UPDATE");
			$stmt->execute([
				'usage_period' => $period,
				'quota_key' => self::QUOTA_KEY,
			]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if (!$row)
				{
				throw new RuntimeException('Não foi possível carregar a quota mensal da SerpAPI.');
				}

			$rowLimit = max(1, (int) ($row['limit_count'] ?? $limit));
			$used = (int) ($row['used_count'] ?? 0);
			if (($rowLimit - $used) < $queryCount)
				{
				$this->db->commit();
				throw new \InvalidArgumentException('Limite mensal de consultas da SerpAPI esgotado para este mês.');
				}

			$used += $queryCount;
			$update = $this->db->prepare("UPDATE serpapi_usage_monthly
			                              SET `limit_count` = :limit_count,
			                                  `used_count` = :used_count,
			                                  `modified` = :modified
			                              WHERE `id` = :id");
			$update->execute([
				'limit_count' => $rowLimit,
				'used_count' => $used,
				'modified' => date('Y-m-d H:i:s'),
				'id' => (int) $row['id'],
			]);
			$this->db->commit();

			return $this->usagePayload($period, $rowLimit, $used, true);
			}
		catch (Throwable $exception)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			throw $exception;
			}
		}

	/**
	 * Ensure the monthly usage row exists.
	 * @param string $period Period in YYYY-MM format.
	 * @param int $limit Monthly limit.
	 * @param bool $updateLimit Whether an existing row should receive the new limit.
	 * @return void
	 **/
	private function ensureUsageRow(string $period, int $limit, bool $updateLimit = true): void
		{
		$now = date('Y-m-d H:i:s');
		$duplicateSql = $updateLimit
			? "`limit_count` = VALUES(`limit_count`), `modified` = VALUES(`modified`)"
			: "`modified` = `modified`";
		$stmt = $this->db->prepare("INSERT INTO serpapi_usage_monthly
			(`usage_period`, `quota_key`, `limit_count`, `used_count`, `created`, `modified`)
			VALUES
			(:usage_period, :quota_key, :limit_count, 0, :created, :modified)
			ON DUPLICATE KEY UPDATE
				{$duplicateSql}");
		$stmt->execute([
			'usage_period' => $period,
			'quota_key' => self::QUOTA_KEY,
			'limit_count' => $limit,
			'created' => $now,
			'modified' => $now,
		]);
		}

	/**
	 * Return the usage payload shared with the UI.
	 * @param string $period Period in YYYY-MM format.
	 * @param int $limit Monthly limit.
	 * @param int $used Used query count.
	 * @param bool $schemaAvailable Whether usage table is available.
	 * @return array<string, mixed>
	 **/
	private function usagePayload(string $period, int $limit, int $used, bool $schemaAvailable): array
		{
		$used = max(0, $used);
		$limit = max(1, $limit);

		return [
			'period' => $period,
			'limit' => $limit,
			'used' => $used,
			'remaining' => max(0, $limit - $used),
			'schema_available' => $schemaAvailable,
		];
		}

	/**
	 * Build the initial execution counters.
	 * @param array<string, mixed> $usage Current usage snapshot.
	 * @return array<string, mixed>
	 **/
	private function initialStats(array $usage): array
		{
		return [
			'query_count' => 0,
			'target_total' => 0,
			'processed_total' => 0,
			'found_total' => 0,
			'failed_total' => 0,
			'response_time_ms' => 0,
			'http_status' => 0,
			'status' => 'running',
			'usage' => $usage,
		];
		}

	/**
	 * Return a public search plan subset.
	 * @param array<string, mixed> $plan Normalized plan.
	 * @return array<string, mixed>
	 **/
	private function publicPlan(array $plan): array
		{
		return [
			'query' => (string) $plan['query'],
			'search_type' => (string) $plan['search_type'],
			'engine' => (string) $plan['engine'],
			'location' => (string) $plan['location'],
			'country_code' => (string) $plan['country_code'],
			'language_code' => (string) $plan['language_code'],
			'device' => (string) $plan['device'],
			'safe_search' => (string) $plan['safe_search'],
			'result_count' => (int) $plan['result_count'],
			'start_offset' => (int) $plan['start_offset'],
			'monthly_limit' => (int) $plan['monthly_limit'],
			'execution_query_limit' => (int) $plan['execution_query_limit'],
			'planned_query_count' => (int) $plan['planned_query_count'],
		];
		}

	/**
	 * Return a public result subset.
	 * @param array<string, mixed> $result Normalized result.
	 * @return array<string, mixed>
	 **/
	private function publicResult(array $result): array
		{
		return [
			'position' => (int) ($result['position'] ?? 0),
			'title' => (string) ($result['title'] ?? ''),
			'link' => (string) ($result['link'] ?? ''),
			'displayed_link' => (string) ($result['displayed_link'] ?? ''),
			'source' => (string) ($result['source'] ?? ''),
			'snippet' => (string) ($result['snippet'] ?? ''),
			'thumbnail' => (string) ($result['thumbnail'] ?? ''),
			'date' => (string) ($result['date'] ?? ''),
			'result_type' => (string) ($result['result_type'] ?? ''),
		];
		}

	/**
	 * Build report WHERE SQL and params.
	 * @param array<string, mixed> $filters Report filters.
	 * @return array{0:string,1:array<string, mixed>}
	 **/
	private function reportWhere(array $filters): array
		{
		$where = [];
		$params = [];

		$searchId = trim((string) ($filters['search_id'] ?? ''));
		if ($searchId !== '' && ctype_digit($searchId))
			{
			$where[] = 'Result.search_id = :search_id';
			$params['search_id'] = (int) $searchId;
			}

		$searchType = trim((string) ($filters['search_type'] ?? ''));
		if (in_array($searchType, ['web', 'news', 'images'], true))
			{
			$where[] = 'Result.search_type = :search_type';
			$params['search_type'] = $searchType;
			}

		$countryCode = strtolower(trim((string) ($filters['country_code'] ?? '')));
		if ($countryCode !== '' && preg_match('/^[a-z]{2}$/', $countryCode))
			{
			$where[] = 'Search.country_code = :country_code';
			$params['country_code'] = $countryCode;
			}

		$term = trim((string) ($filters['term'] ?? ''));
		if ($term !== '')
			{
			$where[] = '(Result.title LIKE :term_like
			          OR Result.link LIKE :term_like
			          OR Result.source LIKE :term_like
			          OR Result.snippet LIKE :term_like
			          OR Search.query LIKE :term_like
			          OR Search.location LIKE :term_like)';
			$params['term_like'] = '%' . $term . '%';
			}

		$dateFrom = trim((string) ($filters['date_from'] ?? ''));
		if ($dateFrom !== '')
			{
			$where[] = 'DATE(Result.checked_at) >= :date_from';
			$params['date_from'] = $dateFrom;
			}

		$dateTo = trim((string) ($filters['date_to'] ?? ''));
		if ($dateTo !== '')
			{
			$where[] = 'DATE(Result.checked_at) <= :date_to';
			$params['date_to'] = $dateTo;
			}

		return [!empty($where) ? 'WHERE ' . implode(' AND ', $where) : '', $params];
		}

	/**
	 * Return a safe ORDER BY clause for the report.
	 * @param array<string, mixed>|null $sort Sort state.
	 * @return string
	 **/
	private function reportOrderBy(?array $sort): string
		{
		$allowedColumns = [
			'id' => 'Result.id',
			'search_id' => 'Result.search_id',
			'search_type' => 'Result.search_type',
			'position' => 'Result.position',
			'title' => 'Result.title',
			'source' => 'Result.source',
			'checked_at' => 'Result.checked_at',
		];

		return sortOrderBy($sort, $allowedColumns, 'checked_at', 'desc');
		}

	/**
	 * Mark a search execution as failed.
	 * @param int $searchId Search id.
	 * @param string $message Failure message.
	 * @return void
	 **/
	private function markSearchAsFailed(int $searchId, string $message): void
		{
		try
			{
			$this->updateSearch($searchId, [], 'failed', $message);
			}
		catch (Throwable $exception)
			{
			}
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
	 * Assert the database tables required by the service.
	 * @return void
	 **/
	private function assertRequiredTables(): void
		{
		foreach ([self::SEARCHES_TABLE, self::RESULTS_TABLE, self::USAGE_TABLE] as $table)
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
	 * Determine whether a PDO exception is a missing table error.
	 * @param PDOException $exception Database exception.
	 * @return bool
	 **/
	private function isMissingTableException(PDOException $exception): bool
		{
		return $exception->getCode() === '42S02'
			|| strpos($exception->getMessage(), 'Base table or view not found') !== false
			|| strpos($exception->getMessage(), 'Missing table:') !== false;
		}

	/**
	 * Return the current month period key.
	 * @return string Period in YYYY-MM format.
	 **/
	private function currentPeriod(): string
		{
		return date('Y-m');
		}

	/**
	 * Clamp an integer between min and max.
	 * @param int $value Raw value.
	 * @param int $min Minimum value.
	 * @param int $max Maximum value.
	 * @return int Clamped value.
	 **/
	private function clampInt(int $value, int $min, int $max): int
		{
		return max($min, min($max, $value));
		}

	/**
	 * Normalize a user-defined monthly limit within the configured ceiling.
	 * @param int|null $value Raw monthly limit.
	 * @return int Normalized monthly limit.
	 **/
	private function normalizeMonthlyLimit(?int $value): int
		{
		$max = self::maxMonthlyLimit();
		$limit = $value !== null && $value > 0 ? $value : $max;

		return $this->clampInt($limit, 1, $max);
		}

	/**
	 * Normalize a manual per-execution query limit.
	 * @param int $value Raw query limit.
	 * @param int $monthlyLimit Current monthly limit.
	 * @return int Normalized query limit.
	 **/
	private function normalizeExecutionQueryLimit(int $value, int $monthlyLimit): int
		{
		return $this->clampInt($value, 0, max(1, $monthlyLimit));
		}

	/**
	 * Limit a string to a maximum length using multibyte when available.
	 * @param string $value Raw value.
	 * @param int $limit Maximum length.
	 * @return string Limited value.
	 **/
	private function limitString(string $value, int $limit): string
		{
		if ($limit <= 0)
			{
			return '';
			}

		if (function_exists('mb_substr'))
			{
			return mb_substr($value, 0, $limit);
			}

		return substr($value, 0, $limit);
		}

	/**
	 * Read the SerpApi key from environment variables.
	 * @return string Configured key or empty string.
	 **/
	private static function apiKeyFromEnvironment(): string
		{
		if (trim(self::API_KEY) !== '')
			{
			return trim(self::API_KEY);
			}

		$apiKey = getenv('SERPAPI_API_KEY');
		if ($apiKey === false || trim((string) $apiKey) === '')
			{
			$apiKey = getenv('APP_SERPAPI_API_KEY');
			}

		return trim((string) ($apiKey ?: ''));
		}
	}

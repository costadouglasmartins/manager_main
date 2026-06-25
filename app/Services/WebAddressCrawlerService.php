<?php

namespace App\Services;

use App\Core\Database;
use Generator;
use PDO;
use PDOException;
use Throwable;

/**
 * Crawls the web for client prospecting addresses using DuckDuckGo search.
 *
 * The crawler turns the user search terms into DuckDuckGo queries, fetches the
 * resulting pages and extracts prospect data (business name, phone, e-mail,
 * address, CNPJ). Every finding is stored in webcrawler_prospect_results so it
 * can later be reviewed, filtered and imported into the people registry.
 **/
class WebAddressCrawlerService
	{
	private const SEARCHES_TABLE = 'webcrawler_prospect_searches';
	private const RESULTS_TABLE = 'webcrawler_prospect_results';
	private const MAX_RESPONSE_BYTES = 1048576;
	private const MAX_RESULTS = 500;
	private const MAX_CONCURRENCY = 20;
	private const MAX_DELAY_MS = 10000;
	private const MAX_DDG_PAGES = 4;
	private const DDG_RESULTS_PER_PAGE = 20;
	private const DDG_ENDPOINT = 'https://html.duckduckgo.com/html/';
	private const DEFAULT_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 ManagerProspector/1.0';

	/**
	 * Database connection used by the crawler.
	 * @var PDO
	 **/
	private $db;

	/**
	 * Cached robots.txt decisions keyed by host.
	 * @var array<string, bool>
	 **/
	private $robotsCache = [];

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
			['label' => 'Manager Prospector', 'value' => self::DEFAULT_USER_AGENT],
			['label' => 'Chrome Windows', 'value' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36'],
			['label' => 'Chrome Android', 'value' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Mobile Safari/537.36'],
			['label' => 'Safari macOS', 'value' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15'],
			['label' => 'Firefox Linux', 'value' => 'Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0'],
		];
		}

	/**
	 * Return the maximum results accepted per execution.
	 * @return int
	 **/
	public static function maxResults(): int
		{
		return self::MAX_RESULTS;
		}

	/**
	 * Stream crawler events for the requested search terms.
	 * @param array<string, mixed> $input Request input.
	 * @return Generator<int, array<string, mixed>>
	 **/
	public function stream(array $input): Generator
		{
		$searchId = 0;

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
			$searchId = $this->createSearch($plan);
			$stats = $this->initialStats();

			yield $this->event('started', [
				'search_id' => $searchId,
				'plan' => $this->publicPlan($plan),
				'stats' => $stats,
				'message' => 'Pesquisa iniciada.',
			]);

			foreach ($this->crawl($plan, $searchId, $stats) as $payload)
				{
				yield $payload;
				}

			$stats['status'] = 'completed';
			$this->updateSearch($searchId, $stats, 'completed', 'Pesquisa finalizada.');

			yield $this->event('summary', [
				'search_id' => $searchId,
				'stats' => $stats,
				'message' => 'Pesquisa finalizada.',
			]);
			}
		catch (\UnexpectedValueException $exception)
			{
			if ($searchId > 0)
				{
				$this->markSearchAsFailed($searchId, 'Schema SQL do prospector ausente.');
				}

			yield $this->event('schema_missing', [
				'message' => 'Não foi possível registrar a pesquisa. Crie as tabelas do prospector antes de executar a ferramenta.',
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
			]);
			}
		catch (PDOException $exception)
			{
			if ($searchId > 0)
				{
				$this->markSearchAsFailed($searchId, 'Erro de banco de dados durante a pesquisa.');
				}

			yield $this->event($this->isMissingTableException($exception) ? 'schema_missing' : 'error', [
				'message' => $this->isMissingTableException($exception)
					? 'Não foi possível registrar a pesquisa. Crie as tabelas do prospector antes de executar a ferramenta.'
					: 'Erro de banco de dados durante a pesquisa.',
			]);
			}
		catch (Throwable $exception)
			{
			if ($searchId > 0)
				{
				$this->markSearchAsFailed($searchId, $exception->getMessage());
				}

			yield $this->event('error', [
				'message' => 'Não foi possível executar o crawler: ' . $exception->getMessage(),
			]);
			}
		}

	/**
	 * Collect candidate URLs and crawl each page, streaming progress events.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @param int $searchId Search execution id.
	 * @param array<string, int|string> $stats Mutable execution counters.
	 * @return Generator<int, array<string, mixed>>
	 **/
	private function crawl(array $plan, int $searchId, array &$stats): Generator
		{
		$maxResults = (int) $plan['max_results'];
		$seen = [];
		$candidates = [];

		// Gather candidate URLs from DuckDuckGo, one query at a time.
		foreach ((array) $plan['queries'] as $query)
			{
			if (count($candidates) >= $maxResults)
				{
				break;
				}

			$remaining = $maxResults - count($candidates);
			$search = $this->duckDuckGoSearch((string) $query, $remaining, $plan);

			if (!empty($search['blocked']))
				{
				yield $this->event('blocked', [
					'search_id' => $searchId,
					'query' => (string) $query,
					'stats' => $stats,
					'message' => 'O DuckDuckGo limitou as requisições. Reduza o ritmo ou tente novamente mais tarde.',
				]);
				}

			foreach ($search['urls'] as $url)
				{
				$hash = $this->urlHash($url);
				if (isset($seen[$hash]))
					{
					continue;
					}

				$seen[$hash] = true;
				$candidates[] = [
					'url' => $url,
					'url_hash' => $hash,
					'source_query' => (string) $query,
				];

				if (count($candidates) >= $maxResults)
					{
					break;
					}
				}

			$stats['query_count']++;
			$this->updateSearch($searchId, $stats, 'running', 'Coletando URLs.');
			yield $this->event('progress', ['search_id' => $searchId, 'stats' => $stats]);

			$this->delay($plan);
			}

		$stats['target_total'] = count($candidates);
		yield $this->event('progress', ['search_id' => $searchId, 'stats' => $stats]);

		if (empty($candidates))
			{
			return;
			}

		// Fetch the candidate pages in concurrency-sized batches.
		foreach (array_chunk($candidates, max(1, (int) $plan['concurrency'])) as $chunk)
			{
			$allowed = [];
			foreach ($chunk as $candidate)
				{
				if ($this->isAllowedByRobots((string) $candidate['url'], $plan))
					{
					$allowed[] = $candidate;
					continue;
					}

				$stats['processed_total']++;
				$stats['failed_total']++;
				}

			foreach ($this->fetchPages($allowed, $plan) as $response)
				{
				$stats['processed_total']++;

				if ((int) $response['errno'] !== 0 || (int) $response['http_status'] < 200 || (int) $response['http_status'] >= 400 || (string) $response['body'] === '')
					{
					$stats['failed_total']++;
					continue;
					}

				$result = $this->buildResult($response, $plan);
				$inserted = $this->saveResult($searchId, $plan, $result);

				if ($inserted)
					{
					$stats['found_total']++;
					}

				if (!empty($result['matched_keywords']))
					{
					$stats['matched_total']++;
					}

				yield $this->event('found', [
					'search_id' => $searchId,
					'result' => $this->publicResult($result, $inserted),
					'stats' => $stats,
				]);
				}

			$this->updateSearch($searchId, $stats, 'running', 'Pesquisando páginas.');
			yield $this->event('progress', ['search_id' => $searchId, 'stats' => $stats]);

			$this->delay($plan);
			}
		}

	/**
	 * Query DuckDuckGo and return candidate result URLs.
	 * @param string $query Search query.
	 * @param int $limit Maximum URLs to collect.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @return array{blocked: bool, urls: array<int, string>}
	 **/
	private function duckDuckGoSearch(string $query, int $limit, array $plan): array
		{
		$query = trim($query);
		if ($query === '' || $limit < 1)
			{
			return ['blocked' => false, 'urls' => []];
			}

		$urls = [];
		$blocked = false;
		$pages = min(self::MAX_DDG_PAGES, (int) ceil($limit / self::DDG_RESULTS_PER_PAGE));

		for ($page = 0; $page < $pages; $page++)
			{
			$endpoint = self::DDG_ENDPOINT . '?' . http_build_query([
				'q' => $query,
				'kl' => 'br-pt',
				's' => $page * self::DDG_RESULTS_PER_PAGE,
			]);

			$handle = $this->createGetHandle($endpoint, $plan);
			if ($handle === null)
				{
				break;
				}

			$body = curl_exec($handle);
			$httpStatus = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
			$errno = curl_errno($handle);

			if ($errno !== 0 || !is_string($body) || $body === '')
				{
				break;
				}

			if (in_array($httpStatus, [202, 403, 429], true) || stripos($body, 'anomaly') !== false || stripos($body, 'unusual traffic') !== false)
				{
				$blocked = true;
				break;
				}

			$pageUrls = $this->extractDuckDuckGoUrls($body);
			if (empty($pageUrls))
				{
				break;
				}

			foreach ($pageUrls as $pageUrl)
				{
				$urls[] = $pageUrl;
				}

			if (count($pageUrls) < self::DDG_RESULTS_PER_PAGE)
				{
				break;
				}

			if ($page + 1 < $pages)
				{
				$this->delay($plan);
				}
			}

		$urls = array_values(array_unique($urls));

		return [
			'blocked' => $blocked,
			'urls' => array_slice($urls, 0, $limit),
		];
		}

	/**
	 * Extract external result URLs from a DuckDuckGo HTML response.
	 * @param string $body Response body.
	 * @return array<int, string>
	 **/
	private function extractDuckDuckGoUrls(string $body): array
		{
		$hrefs = [];

		if (preg_match_all('/<a[^>]+class="[^"]*result__a[^"]*"[^>]+href="([^"]+)"/i', $body, $matches))
			{
			$hrefs = $matches[1];
			}

		if (empty($hrefs) && preg_match_all('/href="([^"]*uddg=[^"]+)"/i', $body, $matches))
			{
			$hrefs = $matches[1];
			}

		$urls = [];
		foreach ($hrefs as $href)
			{
			$url = $this->resolveDuckDuckGoHref((string) $href);
			if ($url !== '')
				{
				$urls[] = $url;
				}
			}

		return array_values(array_unique($urls));
		}

	/**
	 * Resolve a DuckDuckGo href into a clean external URL.
	 * @param string $href Raw href value.
	 * @return string Clean URL or an empty string when invalid.
	 **/
	private function resolveDuckDuckGoHref(string $href): string
		{
		$href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		if ($href === '')
			{
			return '';
			}

		if (strpos($href, '//') === 0)
			{
			$href = 'https:' . $href;
			}

		if (strpos($href, 'uddg=') !== false)
			{
			$queryString = (string) (parse_url($href, PHP_URL_QUERY) ?? '');
			parse_str($queryString, $params);
			if (!empty($params['uddg']))
				{
				$href = (string) $params['uddg'];
				}
			}

		if (!preg_match('#^https?://#i', $href))
			{
			return '';
			}

		$host = strtolower((string) (parse_url($href, PHP_URL_HOST) ?? ''));
		if ($host === '' || strpos($host, 'duckduckgo.com') !== false)
			{
			return '';
			}

		return $this->limitString($href, 767);
		}

	/**
	 * Fetch a batch of pages concurrently with curl_multi.
	 * @param array<int, array<string, mixed>> $targets Target descriptors.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @return array<int, array<string, mixed>>
	 **/
	private function fetchPages(array $targets, array $plan): array
		{
		$responses = [];
		if (empty($targets))
			{
			return $responses;
			}

		$multi = curl_multi_init();
		$handles = [];

		foreach ($targets as $target)
			{
			$handle = $this->createGetHandle((string) $target['url'], $plan);
			if ($handle === null)
				{
				continue;
				}

			curl_multi_add_handle($multi, $handle);
			$handles[] = ['handle' => $handle, 'target' => $target];
			}

		if (empty($handles))
			{
			curl_multi_close($multi);
			return $responses;
			}

		do
			{
			$status = curl_multi_exec($multi, $running);
			if ($running)
				{
				curl_multi_select($multi, 1.0);
				}
			}
		while ($running && $status === CURLM_OK);

		foreach ($handles as $entry)
			{
			$handle = $entry['handle'];
			$body = curl_multi_getcontent($handle);
			$responses[] = [
				'target' => $entry['target'],
				'body' => $this->limitBytes(is_string($body) ? $body : '', self::MAX_RESPONSE_BYTES),
				'http_status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
				'effective_url' => (string) (curl_getinfo($handle, CURLINFO_EFFECTIVE_URL) ?: $entry['target']['url']),
				'content_type' => (string) (curl_getinfo($handle, CURLINFO_CONTENT_TYPE) ?: ''),
				'response_time_ms' => (int) round(((float) curl_getinfo($handle, CURLINFO_TOTAL_TIME)) * 1000),
				'errno' => curl_errno($handle),
			];

			curl_multi_remove_handle($multi, $handle);
			}

		curl_multi_close($multi);

		return $responses;
		}

	/**
	 * Create a configured cURL GET handle.
	 * @param string $url Target URL.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @return \CurlHandle|resource|null
	 **/
	private function createGetHandle(string $url, array $plan)
		{
		$handle = curl_init($url);
		if ($handle === false)
			{
			return null;
			}

		curl_setopt_array($handle, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT_MS => (int) $plan['timeout_ms'],
			CURLOPT_TIMEOUT_MS => (int) $plan['timeout_ms'],
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 3,
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
			CURLOPT_BUFFERSIZE => 16384,
		]);

		if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS'))
			{
			curl_setopt($handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
			}

		return $handle;
		}

	/**
	 * Build the stored prospect payload from a fetched page response.
	 * @param array<string, mixed> $response Page response data.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @return array<string, mixed>
	 **/
	private function buildResult(array $response, array $plan): array
		{
		$body = (string) $response['body'];
		$target = (array) $response['target'];
		$url = (string) ($response['effective_url'] !== '' ? $response['effective_url'] : $target['url']);
		$title = $this->extractTitle($body);
		$matchedKeywords = $this->matchedKeywords($body, $title, (array) $plan['match_keywords']);
		$host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
		$address = $this->extractAddress($body);

		return [
			'business_name' => $this->extractBusinessName($body, $title, $host),
			'website' => $this->limitString($host, 255),
			'url' => $this->limitString($url, 767),
			'url_hash' => (string) ($target['url_hash'] ?? $this->urlHash($url)),
			'document' => $this->extractDocument($body),
			'street' => $address['street'],
			'number' => $address['number'],
			'neighborhood' => $address['neighborhood'],
			'city' => $address['city'] !== '' ? $address['city'] : (string) $plan['city'],
			'state_acronym' => $address['state_acronym'] !== '' ? $address['state_acronym'] : (string) $plan['state_acronym'],
			'zip_code' => $this->extractZipCode($body),
			'phone' => $this->extractPhone($body),
			'email' => $this->extractEmail($body),
			'matched_keywords' => $matchedKeywords,
			'snippet' => $this->snippetForKeywords($body, $title, $matchedKeywords),
			'source_query' => (string) ($target['source_query'] ?? ''),
			'checked_at' => date('Y-m-d H:i:s'),
		];
		}

	/**
	 * Persist a prospect result, deduplicating by URL hash.
	 * @param int $searchId Search execution id.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @param array<string, mixed> $result Result data.
	 * @return bool True when a new row was inserted.
	 **/
	private function saveResult(int $searchId, array $plan, array $result): bool
		{
		$sql = "INSERT INTO webcrawler_prospect_results
			(`search_id`, `user_id`, `business_name`, `website`, `url`, `url_hash`, `document`, `street`, `number`, `neighborhood`, `city`, `state_acronym`, `zip_code`, `phone`, `email`, `matched_keywords`, `snippet`, `source_query`, `result_hash`, `import_status`, `checked_at`, `created`, `modified`)
			VALUES
			(:search_id, :user_id, :business_name, :website, :url, :url_hash, :document, :street, :number, :neighborhood, :city, :state_acronym, :zip_code, :phone, :email, :matched_keywords, :snippet, :source_query, :result_hash, 'pending', :checked_at, :created, :modified)
			ON DUPLICATE KEY UPDATE
				`modified` = VALUES(`modified`)";
		$stmt = $this->db->prepare($sql);
		$now = date('Y-m-d H:i:s');
		$stmt->execute([
			'search_id' => $searchId,
			'user_id' => ((int) $plan['user_id'] > 0 ? (int) $plan['user_id'] : null),
			'business_name' => (string) $result['business_name'] !== '' ? $this->limitString((string) $result['business_name'], 255) : null,
			'website' => (string) $result['website'] !== '' ? $this->limitString((string) $result['website'], 255) : null,
			'url' => (string) $result['url'],
			'url_hash' => (string) $result['url_hash'],
			'document' => (string) $result['document'] !== '' ? (string) $result['document'] : null,
			'street' => (string) $result['street'] !== '' ? $this->limitString((string) $result['street'], 255) : null,
			'number' => (string) $result['number'] !== '' ? $this->limitString((string) $result['number'], 20) : null,
			'neighborhood' => (string) $result['neighborhood'] !== '' ? $this->limitString((string) $result['neighborhood'], 255) : null,
			'city' => (string) $result['city'] !== '' ? $this->limitString((string) $result['city'], 255) : null,
			'state_acronym' => (string) $result['state_acronym'] !== '' ? $this->limitString((string) $result['state_acronym'], 2) : null,
			'zip_code' => (string) $result['zip_code'] !== '' ? $this->limitString((string) $result['zip_code'], 20) : null,
			'phone' => (string) $result['phone'] !== '' ? $this->limitString((string) $result['phone'], 40) : null,
			'email' => (string) $result['email'] !== '' ? $this->limitString((string) $result['email'], 255) : null,
			'matched_keywords' => !empty($result['matched_keywords']) ? json_encode($result['matched_keywords'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
			'snippet' => (string) $result['snippet'] !== '' ? $this->limitString((string) $result['snippet'], 500) : null,
			'source_query' => (string) $result['source_query'] !== '' ? $this->limitString((string) $result['source_query'], 500) : null,
			'result_hash' => (string) $result['url_hash'],
			'checked_at' => (string) $result['checked_at'],
			'created' => $now,
			'modified' => $now,
		]);

		return $stmt->rowCount() === 1;
		}

	/**
	 * Search stored prospect results using listing filters.
	 * @param array<string, mixed> $filters Listing filters.
	 * @param int $limit Maximum rows.
	 * @param int $offset Result offset.
	 * @param array<string, mixed>|null $sort Sort state.
	 * @return array<int, array<string, mixed>>
	 **/
	public function searchResults(array $filters = [], int $limit = 20, int $offset = 0, ?array $sort = null): array
		{
		[$whereSql, $params] = $this->buildReportWhere($filters);
		$orderBy = $this->reportOrderBy($sort);
		$sql = "SELECT
					r.id,
					r.search_id,
					r.business_name,
					r.website,
					r.url,
					r.document,
					r.street,
					r.number,
					r.neighborhood,
					r.city,
					r.state_acronym,
					r.zip_code,
					r.phone,
					r.email,
					r.matched_keywords,
					r.snippet,
					r.source_query,
					r.import_status,
					r.person_id,
					r.imported_at,
					r.checked_at
				FROM webcrawler_prospect_results AS r
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
	 * Count stored prospect results using listing filters.
	 * @param array<string, mixed> $filters Listing filters.
	 * @return int
	 **/
	public function countResults(array $filters = []): int
		{
		[$whereSql, $params] = $this->buildReportWhere($filters);
		$stmt = $this->db->prepare("SELECT COUNT(*) AS total
		                            FROM webcrawler_prospect_results AS r
		                            {$whereSql}");

		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value);
			}

		$stmt->execute();

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Build aggregate counters for the listing filters.
	 * @param array<string, mixed> $filters Listing filters.
	 * @return array<string, int>
	 **/
	public function reportSummary(array $filters = []): array
		{
		[$whereSql, $params] = $this->buildReportWhere($filters);
		$stmt = $this->db->prepare("SELECT
		                                COUNT(*) AS total,
		                                COALESCE(SUM(CASE WHEN r.import_status = 'imported' THEN 1 ELSE 0 END), 0) AS imported_total,
		                                COALESCE(SUM(CASE WHEN r.import_status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_total,
		                                COALESCE(SUM(CASE WHEN r.document IS NOT NULL AND r.document <> '' THEN 1 ELSE 0 END), 0) AS document_total,
		                                COALESCE(SUM(CASE WHEN r.phone IS NOT NULL AND r.phone <> '' THEN 1 ELSE 0 END), 0) AS phone_total,
		                                COALESCE(SUM(CASE WHEN r.email IS NOT NULL AND r.email <> '' THEN 1 ELSE 0 END), 0) AS email_total
		                             FROM webcrawler_prospect_results AS r
		                             {$whereSql}");

		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value);
			}

		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

		return [
			'total' => (int) ($row['total'] ?? 0),
			'imported_total' => (int) ($row['imported_total'] ?? 0),
			'pending_total' => (int) ($row['pending_total'] ?? 0),
			'document_total' => (int) ($row['document_total'] ?? 0),
			'phone_total' => (int) ($row['phone_total'] ?? 0),
			'email_total' => (int) ($row['email_total'] ?? 0),
		];
		}

	/**
	 * Import the filtered prospect results into the people registry.
	 *
	 * Only results that carry a valid 14 digit CNPJ and are not yet imported are
	 * eligible. Each prospect is imported in its own transaction so a single
	 * failure does not roll back the whole batch.
	 *
	 * @param array<string, mixed> $filters Listing filters.
	 * @param int $userId Current user id used to stamp the imported rows.
	 * @param int $limit Maximum prospects processed per call.
	 * @return array<string, mixed>
	 **/
	public function importByFilters(array $filters, int $userId = 0, int $limit = 500): array
		{
		$this->assertRequiredTables();

		$importFilters = $filters;
		$importFilters['import_status'] = 'pending';
		$importFilters['has_document'] = '1';

		[$whereSql, $params] = $this->buildReportWhere($importFilters);
		$limit = $this->clampInt($limit, 1, 1000);

		$stmt = $this->db->prepare("SELECT
		                                r.id,
		                                r.business_name,
		                                r.website,
		                                r.url,
		                                r.document,
		                                r.street,
		                                r.number,
		                                r.neighborhood,
		                                r.city,
		                                r.state_acronym,
		                                r.zip_code,
		                                r.phone,
		                                r.email
		                            FROM webcrawler_prospect_results AS r
		                            {$whereSql}
		                            ORDER BY r.id ASC
		                            LIMIT :limit");

		foreach ($params as $key => $value)
			{
			$stmt->bindValue(':' . $key, $value);
			}

		$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$imported = 0;
		$linked = 0;
		$skipped = 0;
		$failed = 0;

		foreach ($rows as $row)
			{
			try
				{
				$outcome = $this->importProspectRow($row, $userId);
				if ($outcome === 'imported')
					{
					$imported++;
					}
				elseif ($outcome === 'linked')
					{
					$linked++;
					}
				else
					{
					$skipped++;
					}
				}
			catch (Throwable $exception)
				{
				$failed++;
				}
			}

		$processed = count($rows);
		$remaining = max(0, $this->countResults($importFilters) - $processed);

		return [
			'success' => true,
			'status' => 200,
			'message' => $this->buildImportMessage($imported, $linked, $skipped, $failed, $remaining),
			'data' => [
				'imported' => $imported,
				'linked' => $linked,
				'skipped' => $skipped,
				'failed' => $failed,
				'processed' => $processed,
				'remaining' => $remaining,
			],
		];
		}

	/**
	 * Import a single prospect row into the people registry.
	 * @param array<string, mixed> $row Prospect result row.
	 * @param int $userId Current user id.
	 * @return string Outcome identifier (imported, linked, skipped).
	 **/
	private function importProspectRow(array $row, int $userId): string
		{
		$document = preg_replace('/\D+/', '', (string) ($row['document'] ?? '')) ?? '';
		if (strlen($document) !== 14)
			{
			return 'skipped';
			}

		$existingPersonId = $this->findPersonIdByDocument($document);
		if ($existingPersonId > 0)
			{
			$this->markResultImported((int) $row['id'], $existingPersonId);
			return 'linked';
			}

		$this->db->beginTransaction();

		try
			{
			$name = (string) ($row['business_name'] ?? '');
			if ($name === '')
				{
				$name = (string) ($row['website'] ?? 'Prospect');
				}

			$now = date('Y-m-d H:i:s');
			$personStmt = $this->db->prepare("INSERT INTO people
				(`name`, `fantasy`, `type`, `document`, `active`, `birthdate`, `created`, `modified`)
				VALUES
				(:name, :fantasy, 'company', :document, 1, NULL, :created, :modified)");
			$personStmt->execute([
				'name' => $this->limitString($name, 255),
				'fantasy' => $this->limitString($name, 255),
				'document' => $document,
				'created' => $now,
				'modified' => $now,
			]);
			$personId = (int) $this->db->lastInsertId();

			$phone = preg_replace('/\D+/', '', (string) ($row['phone'] ?? '')) ?? '';
			if ($phone !== '')
				{
				$phoneStmt = $this->db->prepare("INSERT INTO phone_numbers
					(`phone_number`, `observations`, `person_id`, `created`, `modified`)
					VALUES
					(:phone_number, 'Prospecção web', :person_id, :created, :modified)");
				$phoneStmt->execute([
					'phone_number' => $this->limitString($phone, 255),
					'person_id' => $personId,
					'created' => $now,
					'modified' => $now,
				]);
				}

			$email = strtolower(trim((string) ($row['email'] ?? '')));
			if ($email !== '')
				{
				$emailStmt = $this->db->prepare("INSERT INTO emails
					(`email`, `observations`, `is_login`, `person_id`, `created`, `modified`)
					VALUES
					(:email, 'Prospecção web', 0, :person_id, :created, :modified)");
				$emailStmt->execute([
					'email' => $this->limitString($email, 255),
					'person_id' => $personId,
					'created' => $now,
					'modified' => $now,
				]);
				}

			$this->importAddress($row, $personId, $now);

			$this->markResultImported((int) $row['id'], $personId);

			$this->db->commit();

			return 'imported';
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
	 * Persist a prospect address when the location can be resolved.
	 * @param array<string, mixed> $row Prospect result row.
	 * @param int $personId Owner person id.
	 * @param string $now Timestamp used for created/modified.
	 * @return void
	 **/
	private function importAddress(array $row, int $personId, string $now): void
		{
		$street = trim((string) ($row['street'] ?? ''));
		$zipCode = trim((string) ($row['zip_code'] ?? ''));
		$cityName = trim((string) ($row['city'] ?? ''));
		$neighborhoodName = trim((string) ($row['neighborhood'] ?? ''));
		$stateAcronym = strtoupper(trim((string) ($row['state_acronym'] ?? '')));

		if ($street === '' || $zipCode === '' || $cityName === '' || $neighborhoodName === '' || $stateAcronym === '')
			{
			return;
			}

		$stateId = $this->findStateIdByAcronym($stateAcronym);
		if ($stateId <= 0)
			{
			return;
			}

		$cityId = $this->findOrCreateCity($cityName, $stateId, $now);
		$neighborhoodId = $this->findOrCreateNeighborhood($neighborhoodName, $cityId, $now);

		$addressStmt = $this->db->prepare("INSERT INTO addresses
			(`zip_code`, `street`, `number`, `observations`, `person_id`, `neighborhood_id`, `created`, `modified`)
			VALUES
			(:zip_code, :street, :number, 'Prospecção web', :person_id, :neighborhood_id, :created, :modified)");
		$addressStmt->execute([
			'zip_code' => $this->limitString($zipCode, 45),
			'street' => $this->limitString($street, 255),
			'number' => $this->limitString((string) ($row['number'] ?? ''), 10),
			'person_id' => $personId,
			'neighborhood_id' => $neighborhoodId,
			'created' => $now,
			'modified' => $now,
		]);
		}

	/**
	 * Mark a prospect result as imported and link the created person.
	 * @param int $resultId Prospect result id.
	 * @param int $personId Linked person id.
	 * @return void
	 **/
	private function markResultImported(int $resultId, int $personId): void
		{
		$now = date('Y-m-d H:i:s');
		$stmt = $this->db->prepare("UPDATE webcrawler_prospect_results
		                            SET `import_status` = 'imported',
		                                `person_id` = :person_id,
		                                `imported_at` = :imported_at,
		                                `modified` = :modified
		                            WHERE `id` = :id");
		$stmt->execute([
			'person_id' => $personId,
			'imported_at' => $now,
			'modified' => $now,
			'id' => $resultId,
		]);
		}

	/**
	 * Find a person id by normalized document.
	 * @param string $document Normalized CNPJ digits.
	 * @return int Person id or zero when not found.
	 **/
	private function findPersonIdByDocument(string $document): int
		{
		$stmt = $this->db->prepare("SELECT id FROM people WHERE document = :document LIMIT 1");
		$stmt->execute(['document' => $document]);

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Find a state id by acronym within Brazil.
	 * @param string $acronym State acronym.
	 * @return int State id or zero when not found.
	 **/
	private function findStateIdByAcronym(string $acronym): int
		{
		$stmt = $this->db->prepare("SELECT id FROM states WHERE acronym = :acronym AND country_id = 1 LIMIT 1");
		$stmt->execute(['acronym' => $acronym]);

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Find or create a city by name and state.
	 * @param string $name City name.
	 * @param int $stateId State id.
	 * @param string $now Timestamp used for created/modified.
	 * @return int City id.
	 **/
	private function findOrCreateCity(string $name, int $stateId, string $now): int
		{
		$stmt = $this->db->prepare("SELECT id FROM cities WHERE name = :name AND state_id = :state_id LIMIT 1");
		$stmt->execute(['name' => $name, 'state_id' => $stateId]);
		$cityId = (int) $stmt->fetchColumn();
		if ($cityId > 0)
			{
			return $cityId;
			}

		$insert = $this->db->prepare("INSERT INTO cities
			(`name`, `state_id`, `created`, `modified`)
			VALUES
			(:name, :state_id, :created, :modified)");
		$insert->execute([
			'name' => $this->limitString($name, 255),
			'state_id' => $stateId,
			'created' => $now,
			'modified' => $now,
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Find or create a neighborhood by name and city.
	 * @param string $name Neighborhood name.
	 * @param int $cityId City id.
	 * @param string $now Timestamp used for created/modified.
	 * @return int Neighborhood id.
	 **/
	private function findOrCreateNeighborhood(string $name, int $cityId, string $now): int
		{
		$stmt = $this->db->prepare("SELECT id FROM neighborhoods WHERE name = :name AND city_id = :city_id LIMIT 1");
		$stmt->execute(['name' => $name, 'city_id' => $cityId]);
		$neighborhoodId = (int) $stmt->fetchColumn();
		if ($neighborhoodId > 0)
			{
			return $neighborhoodId;
			}

		$insert = $this->db->prepare("INSERT INTO neighborhoods
			(`name`, `city_id`, `created`, `modified`)
			VALUES
			(:name, :city_id, :created, :modified)");
		$insert->execute([
			'name' => $this->limitString($name, 255),
			'city_id' => $cityId,
			'created' => $now,
			'modified' => $now,
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Build the import outcome message.
	 * @param int $imported Newly imported count.
	 * @param int $linked Linked to existing person count.
	 * @param int $skipped Skipped count.
	 * @param int $failed Failed count.
	 * @param int $remaining Remaining eligible count.
	 * @return string
	 **/
	private function buildImportMessage(int $imported, int $linked, int $skipped, int $failed, int $remaining): string
		{
		$parts = [];
		$parts[] = $imported . ' importado(s)';
		if ($linked > 0)
			{
			$parts[] = $linked . ' vinculado(s) a cadastro existente';
			}
		if ($skipped > 0)
			{
			$parts[] = $skipped . ' ignorado(s) sem CNPJ';
			}
		if ($failed > 0)
			{
			$parts[] = $failed . ' com falha';
			}
		if ($remaining > 0)
			{
			$parts[] = $remaining . ' restante(s) para uma nova importação';
			}

		return implode(', ', $parts) . '.';
		}

	/**
	 * Build a safe WHERE clause for the prospect listing.
	 * @param array<string, mixed> $filters Listing filters.
	 * @return array{0: string, 1: array<string, mixed>}
	 **/
	private function buildReportWhere(array $filters): array
		{
		$where = [];
		$params = [];
		$searchId = $this->clampInt((int) ($filters['search_id'] ?? 0), 0, PHP_INT_MAX);
		$stateAcronym = strtoupper(trim((string) ($filters['state_acronym'] ?? '')));
		$city = trim((string) ($filters['city'] ?? ''));
		$importStatus = trim((string) ($filters['import_status'] ?? ''));
		$hasPhone = trim((string) ($filters['has_phone'] ?? ''));
		$hasEmail = trim((string) ($filters['has_email'] ?? ''));
		$hasDocument = trim((string) ($filters['has_document'] ?? ''));
		$term = trim((string) ($filters['term'] ?? ''));
		$dateFrom = trim((string) ($filters['date_from'] ?? ''));
		$dateTo = trim((string) ($filters['date_to'] ?? ''));

		if ($searchId > 0)
			{
			$where[] = 'r.search_id = :search_id';
			$params['search_id'] = $searchId;
			}

		if (preg_match('/^[A-Z]{2}$/', $stateAcronym))
			{
			$where[] = 'r.state_acronym = :state_acronym';
			$params['state_acronym'] = $stateAcronym;
			}

		if ($city !== '')
			{
			$where[] = 'r.city LIKE :city_like';
			$params['city_like'] = '%' . $city . '%';
			}

		if (in_array($importStatus, ['pending', 'imported', 'discarded'], true))
			{
			$where[] = 'r.import_status = :import_status';
			$params['import_status'] = $importStatus;
			}

		if ($hasPhone === '1')
			{
			$where[] = "r.phone IS NOT NULL AND r.phone <> ''";
			}

		if ($hasEmail === '1')
			{
			$where[] = "r.email IS NOT NULL AND r.email <> ''";
			}

		if ($hasDocument === '1')
			{
			$where[] = "r.document IS NOT NULL AND r.document <> ''";
			}

		if ($term !== '')
			{
			$where[] = '(r.business_name LIKE :term_like
			          OR r.url LIKE :term_like
			          OR r.email LIKE :term_like
			          OR r.phone LIKE :term_like
			          OR r.street LIKE :term_like
			          OR r.neighborhood LIKE :term_like
			          OR r.snippet LIKE :term_like
			          OR r.source_query LIKE :term_like)';
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
	 * Return a safe ORDER BY clause for the listing.
	 * @param array<string, mixed>|null $sort Sort state.
	 * @return string
	 **/
	private function reportOrderBy(?array $sort): string
		{
		$allowedColumns = [
			'id' => 'r.id',
			'business_name' => 'r.business_name',
			'city' => 'r.city',
			'state_acronym' => 'r.state_acronym',
			'import_status' => 'r.import_status',
			'phone' => 'r.phone',
			'email' => 'r.email',
			'checked_at' => 'r.checked_at',
		];

		return sortOrderBy($sort, $allowedColumns, 'checked_at', 'desc');
		}

	/**
	 * Normalize a listing row for display.
	 * @param array<string, mixed> $row Raw database row.
	 * @return array<string, mixed>
	 **/
	private function normalizeReportRow(array $row): array
		{
		$matchedKeywords = [];
		if (!empty($row['matched_keywords']))
			{
			$decoded = json_decode((string) $row['matched_keywords'], true);
			if (is_array($decoded))
				{
				$matchedKeywords = array_values(array_filter(array_map(static function ($value): string {
					return trim((string) $value);
				}, $decoded), static function (string $value): bool {
					return $value !== '';
				}));
				}
			}

		$row['matched_keywords'] = $matchedKeywords;
		$row['person_id'] = (int) ($row['person_id'] ?? 0);

		return $row;
		}

	/**
	 * Build and validate a crawler plan from request input.
	 * @param array<string, mixed> $input Raw request input.
	 * @return array<string, mixed>
	 **/
	private function buildPlan(array $input): array
		{
		$keywordsText = trim((string) ($input['keywords'] ?? ''));
		if ($keywordsText === '')
			{
			throw new \InvalidArgumentException('Informe ao menos uma palavra-chave para a pesquisa.');
			}

		$stateAcronym = strtoupper(trim((string) ($input['state_acronym'] ?? '')));
		if ($stateAcronym !== '' && !preg_match('/^[A-Z]{2}$/', $stateAcronym))
			{
			$stateAcronym = '';
			}

		$city = trim((string) ($input['city'] ?? ''));
		$zipCode = preg_replace('/\D+/', '', (string) ($input['zip_code'] ?? '')) ?? '';

		$queries = $this->buildQueries($keywordsText, $city, $stateAcronym, $zipCode);
		if (empty($queries))
			{
			throw new \InvalidArgumentException('Informe ao menos uma palavra-chave para a pesquisa.');
			}

		return [
			'user_id' => (int) ($input['user_id'] ?? 0),
			'keywords_text' => $this->limitString($keywordsText, 500),
			'match_keywords' => $this->keywordsFromInput($keywordsText),
			'queries' => $queries,
			'state_acronym' => $stateAcronym,
			'city' => $city,
			'zip_code' => $zipCode,
			'max_results' => $this->clampInt((int) ($input['max_results'] ?? 50), 1, self::MAX_RESULTS),
			'concurrency' => $this->clampInt((int) ($input['concurrency'] ?? 5), 1, self::MAX_CONCURRENCY),
			'delay_ms' => $this->clampInt((int) ($input['delay_ms'] ?? 1000), 0, self::MAX_DELAY_MS),
			'timeout_ms' => $this->clampInt((int) ($input['timeout_ms'] ?? 8000), 1000, 30000),
			'user_agent' => $this->normalizeUserAgent((string) ($input['user_agent'] ?? '')),
		];
		}

	/**
	 * Build the DuckDuckGo queries from keywords and location filters.
	 * @param string $keywordsText Raw keywords text.
	 * @param string $city City filter.
	 * @param string $stateAcronym State acronym filter.
	 * @param string $zipCode Zip code filter.
	 * @return array<int, string>
	 **/
	private function buildQueries(string $keywordsText, string $city, string $stateAcronym, string $zipCode): array
		{
		$location = trim(implode(' ', array_filter([$city, $stateAcronym, $zipCode], static function (string $value): bool {
			return $value !== '';
		})));

		$queries = [];
		foreach (preg_split('/\r\n|\r|\n/', $keywordsText) as $line)
			{
			$line = trim((string) $line);
			if ($line === '')
				{
				continue;
				}

			$queries[] = $this->limitString(trim($line . ($location !== '' ? ' ' . $location : '')), 200);
			}

		return array_values(array_unique($queries));
		}

	/**
	 * Split keyword input into individual match terms.
	 * @param string $value Raw keyword text.
	 * @return array<int, string>
	 **/
	private function keywordsFromInput(string $value): array
		{
		$parts = preg_split('/[\r\n,;]+/', $value) ?: [];
		$keywords = [];
		foreach ($parts as $part)
			{
			$part = trim((string) $part);
			if ($part !== '')
				{
				$keywords[] = $part;
				}
			}

		return array_values(array_unique($keywords));
		}

	/**
	 * Create a search execution record.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @return int New search id.
	 **/
	private function createSearch(array $plan): int
		{
		$now = date('Y-m-d H:i:s');
		$stmt = $this->db->prepare("INSERT INTO webcrawler_prospect_searches
			(`user_id`, `keywords`, `state_acronym`, `city`, `zip_code`, `max_results`, `concurrency`, `delay_ms`, `user_agent`, `status`, `started_at`, `created`, `modified`)
			VALUES
			(:user_id, :keywords, :state_acronym, :city, :zip_code, :max_results, :concurrency, :delay_ms, :user_agent, 'running', :started_at, :created, :modified)");
		$stmt->execute([
			'user_id' => ((int) $plan['user_id'] > 0 ? (int) $plan['user_id'] : null),
			'keywords' => (string) $plan['keywords_text'],
			'state_acronym' => (string) $plan['state_acronym'] !== '' ? (string) $plan['state_acronym'] : null,
			'city' => (string) $plan['city'] !== '' ? (string) $plan['city'] : null,
			'zip_code' => (string) $plan['zip_code'] !== '' ? (string) $plan['zip_code'] : null,
			'max_results' => (int) $plan['max_results'],
			'concurrency' => (int) $plan['concurrency'],
			'delay_ms' => (int) $plan['delay_ms'],
			'user_agent' => (string) $plan['user_agent'],
			'started_at' => $now,
			'created' => $now,
			'modified' => $now,
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Update a search execution with progress counters.
	 * @param int $searchId Search id.
	 * @param array<string, int|string> $stats Execution counters.
	 * @param string $status Execution status.
	 * @param string $message Status message.
	 * @return void
	 **/
	private function updateSearch(int $searchId, array $stats, string $status, string $message): void
		{
		$finishedStatuses = ['completed', 'failed', 'stopped'];
		$finishedSql = in_array($status, $finishedStatuses, true) ? ', `finished_at` = :finished_at' : '';
		$stmt = $this->db->prepare("UPDATE webcrawler_prospect_searches
		                            SET `query_count` = :query_count,
		                                `processed_total` = :processed_total,
		                                `found_total` = :found_total,
		                                `matched_total` = :matched_total,
		                                `failed_total` = :failed_total,
		                                `status` = :status,
		                                `message` = :message,
		                                `modified` = :modified
		                                {$finishedSql}
		                            WHERE `id` = :id");
		$params = [
			'query_count' => (int) ($stats['query_count'] ?? 0),
			'processed_total' => (int) ($stats['processed_total'] ?? 0),
			'found_total' => (int) ($stats['found_total'] ?? 0),
			'matched_total' => (int) ($stats['matched_total'] ?? 0),
			'failed_total' => (int) ($stats['failed_total'] ?? 0),
			'status' => $status,
			'message' => $this->limitString($message, 500),
			'modified' => date('Y-m-d H:i:s'),
			'id' => $searchId,
		];

		if ($finishedSql !== '')
			{
			$params['finished_at'] = date('Y-m-d H:i:s');
			}

		$stmt->execute($params);
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
	 * Build the initial execution counters.
	 * @return array<string, int|string>
	 **/
	private function initialStats(): array
		{
		return [
			'query_count' => 0,
			'target_total' => 0,
			'processed_total' => 0,
			'found_total' => 0,
			'matched_total' => 0,
			'failed_total' => 0,
			'status' => 'running',
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
			'keywords' => (string) $plan['keywords_text'],
			'queries' => (array) $plan['queries'],
			'state_acronym' => (string) $plan['state_acronym'],
			'city' => (string) $plan['city'],
			'zip_code' => (string) $plan['zip_code'],
			'max_results' => (int) $plan['max_results'],
			'concurrency' => (int) $plan['concurrency'],
			'delay_ms' => (int) $plan['delay_ms'],
		];
		}

	/**
	 * Return a public result subset for SSE clients.
	 * @param array<string, mixed> $result Result data.
	 * @param bool $inserted True when the row was newly inserted.
	 * @return array<string, mixed>
	 **/
	private function publicResult(array $result, bool $inserted): array
		{
		return [
			'business_name' => (string) $result['business_name'],
			'website' => (string) $result['website'],
			'url' => (string) $result['url'],
			'document' => (string) $result['document'],
			'street' => (string) $result['street'],
			'number' => (string) $result['number'],
			'neighborhood' => (string) $result['neighborhood'],
			'city' => (string) $result['city'],
			'state_acronym' => (string) $result['state_acronym'],
			'zip_code' => (string) $result['zip_code'],
			'phone' => (string) $result['phone'],
			'email' => (string) $result['email'],
			'matched_keywords' => (array) $result['matched_keywords'],
			'snippet' => (string) $result['snippet'],
			'is_new' => $inserted,
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
	 * Sleep for the configured delay between requests.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @return void
	 **/
	private function delay(array $plan): void
		{
		$delayMs = (int) ($plan['delay_ms'] ?? 0);
		if ($delayMs > 0)
			{
			usleep($delayMs * 1000);
			}
		}

	/**
	 * Check whether a URL is allowed by the host robots.txt for any agent.
	 * @param string $url Target URL.
	 * @param array<string, mixed> $plan Normalized crawler plan.
	 * @return bool
	 **/
	private function isAllowedByRobots(string $url, array $plan): bool
		{
		$host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
		$scheme = strtolower((string) (parse_url($url, PHP_URL_SCHEME) ?? 'https'));
		if ($host === '')
			{
			return false;
			}

		if (array_key_exists($host, $this->robotsCache))
			{
			return $this->robotsCache[$host];
			}

		$allowed = true;
		$handle = $this->createGetHandle($scheme . '://' . $host . '/robots.txt', $plan);
		if ($handle !== null)
			{
			$body = curl_exec($handle);
			$httpStatus = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

			if (is_string($body) && $body !== '' && $httpStatus >= 200 && $httpStatus < 400)
				{
				$allowed = !$this->robotsDisallowsRoot($body);
				}
			}

		$this->robotsCache[$host] = $allowed;

		return $allowed;
		}

	/**
	 * Detect a wholesale crawl block in a robots.txt body.
	 * @param string $body robots.txt content.
	 * @return bool True when the root path is disallowed for all agents.
	 **/
	private function robotsDisallowsRoot(string $body): bool
		{
		$appliesToAll = false;
		foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line)
			{
			$line = trim((string) $line);
			if ($line === '' || strpos($line, '#') === 0)
				{
				continue;
				}

			if (preg_match('/^user-agent:\s*(.+)$/i', $line, $matches))
				{
				$appliesToAll = trim($matches[1]) === '*';
				continue;
				}

			if ($appliesToAll && preg_match('/^disallow:\s*(.*)$/i', $line, $matches))
				{
				if (trim($matches[1]) === '/')
					{
					return true;
					}
				}
			}

		return false;
		}

	/**
	 * Extract the page title.
	 * @param string $body Response body.
	 * @return string
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
	 * @return string
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
	 * Extract a likely business name from the page.
	 * @param string $body Response body.
	 * @param string $title Page title.
	 * @param string $host Page host.
	 * @return string
	 **/
	private function extractBusinessName(string $body, string $title, string $host): string
		{
		if (preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)["\']/is', $body, $matches))
			{
			$name = html_entity_decode(trim((string) $matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			if ($name !== '')
				{
				return $this->limitString($name, 255);
				}
			}

		if ($title !== '')
			{
			$name = preg_split('/\s+[|\-–—]\s+/', $title)[0] ?? $title;
			return $this->limitString(trim((string) $name), 255);
			}

		return $this->limitString($host, 255);
		}

	/**
	 * Extract the first e-mail address from the page.
	 * @param string $body Response body.
	 * @return string
	 **/
	private function extractEmail(string $body): string
		{
		if (!preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $body, $matches))
			{
			return '';
			}

		foreach ($matches[0] as $candidate)
			{
			$email = strtolower(trim((string) $candidate));
			if (preg_match('/\.(png|jpe?g|gif|svg|webp|css|js)$/i', $email))
				{
				continue;
				}

			if (strpos($email, 'example.') !== false || strpos($email, 'sentry') !== false || strpos($email, 'wixpress') !== false)
				{
				continue;
				}

			return $this->limitString($email, 255);
			}

		return '';
		}

	/**
	 * Extract the first Brazilian phone number from the page.
	 * @param string $body Response body.
	 * @return string Normalized phone digits.
	 **/
	private function extractPhone(string $body): string
		{
		$text = strip_tags($body);
		if (!preg_match_all('/(?:\+?55\s?)?(?:\(?\d{2}\)?\s?)?\d{4,5}[-\s]?\d{4}/', $text, $matches))
			{
			return '';
			}

		foreach ($matches[0] as $candidate)
			{
			$digits = preg_replace('/\D+/', '', (string) $candidate) ?? '';
			$length = strlen($digits);
			if ($length >= 10 && $length <= 13)
				{
				return $digits;
				}
			}

		return '';
		}

	/**
	 * Extract the first Brazilian zip code from the page.
	 * @param string $body Response body.
	 * @return string Formatted zip code.
	 **/
	private function extractZipCode(string $body): string
		{
		$text = strip_tags($body);
		if (!preg_match('/\b(\d{5})-?(\d{3})\b/', $text, $matches))
			{
			return '';
			}

		return $matches[1] . '-' . $matches[2];
		}

	/**
	 * Extract the first CNPJ document from the page.
	 * @param string $body Response body.
	 * @return string Normalized CNPJ digits.
	 **/
	private function extractDocument(string $body): string
		{
		$text = strip_tags($body);
		if (!preg_match('/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/', $text, $matches))
			{
			return '';
			}

		$digits = preg_replace('/\D+/', '', (string) $matches[0]) ?? '';

		return strlen($digits) === 14 ? $digits : '';
		}

	/**
	 * Extract best-effort address parts from the page.
	 * @param string $body Response body.
	 * @return array{street: string, number: string, neighborhood: string, city: string, state_acronym: string}
	 **/
	private function extractAddress(string $body): array
		{
		$text = preg_replace('/\s+/', ' ', strip_tags($body)) ?? '';
		$street = '';
		$number = '';

		if (preg_match('/((?:Rua|Avenida|Av\.?|Travessa|Alameda|Rodovia|Estrada|Praça|Pra\x{00E7}a)\s+[^,<\n]{3,80}?)(?:,?\s*n?[\x{00BA}\x{00B0}]?\s*(\d{1,6}))?/iu', $text, $matches))
			{
			$street = trim((string) ($matches[1] ?? ''));
			$number = trim((string) ($matches[2] ?? ''));
			}

		return [
			'street' => $this->limitString($street, 255),
			'number' => $this->limitString($number, 20),
			'neighborhood' => '',
			'city' => '',
			'state_acronym' => '',
		];
		}

	/**
	 * Find matched keywords in the page content.
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

		return array_values(array_unique($matches));
		}

	/**
	 * Build a compact snippet around the first matched keyword.
	 * @param string $body Response body.
	 * @param string $title Page title.
	 * @param array<int, string> $matchedKeywords Matched keywords.
	 * @return string
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
			return $this->limitString($text, 300);
			}

		$position = stripos($text, (string) $matchedKeywords[0]);
		if ($position === false)
			{
			return $this->limitString($text, 300);
			}

		$start = max(0, $position - 90);

		return $this->limitString(substr($text, $start, 300), 300);
		}

	/**
	 * Normalize the user agent value.
	 * @param string $value Raw user agent.
	 * @return string
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
	 * Build the SHA1 hash used to deduplicate URLs.
	 * @param string $url Target URL.
	 * @return string
	 **/
	private function urlHash(string $url): string
		{
		return sha1(strtolower(trim($url)));
		}

	/**
	 * Assert the database tables required by the crawler.
	 * @return void
	 **/
	private function assertRequiredTables(): void
		{
		foreach ([self::SEARCHES_TABLE, self::RESULTS_TABLE] as $table)
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
	 * Limit a string to a maximum length using multibyte when available.
	 * @param string $value Raw value.
	 * @param int $limit Maximum length.
	 * @return string
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

	/**
	 * Limit a raw body to a maximum number of bytes.
	 * @param string $value Raw body.
	 * @param int $limit Maximum bytes.
	 * @return string
	 **/
	private function limitBytes(string $value, int $limit): string
		{
		if (strlen($value) <= $limit)
			{
			return $value;
			}

		return substr($value, 0, $limit);
		}

	/**
	 * Clamp an integer between min and max values.
	 * @param int $value Raw value.
	 * @param int $min Minimum value.
	 * @param int $max Maximum value.
	 * @return int
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
	}

?>

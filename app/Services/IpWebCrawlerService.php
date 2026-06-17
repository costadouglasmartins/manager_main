<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Crawls HTTP/HTTPS pages exposed by IPv4 targets and records scan history.
 **/
class IpWebCrawlerService
	{
	private const DEFAULT_USER_AGENT = 'Manager IpWebCrawler/1.0';
	private const MAX_IPS = 1000;
	private const MAX_PORTS = 20;
	private const MAX_TARGETS = 2000;
	private const MAX_TERMS = 10;
	private const MAX_PATHS = 20;
	private const MAX_DEPTH = 2;
	private const MAX_PAGES_PER_TARGET = 30;
	private const MIN_TIMEOUT_MS = 500;
	private const MAX_TIMEOUT_MS = 15000;
	private const MAX_DELAY_MS = 10000;
	private const DEFAULT_RETRY_HOURS = 24;
	private const TIMEOUT_RETRY_HOURS = 6;
	private const REFUSED_RETRY_HOURS = 24;
	private const SKIPPABLE_TARGET_STATUSES = ['not_found', 'timeout', 'connection_refused', 'http_error', 'error'];

	private PDO $db;

	/**
	 * Build the IP webcrawler service.
	 * @param PDO|null $db Optional database connection.
	 **/
	public function __construct(?PDO $db = null)
		{
		$this->db = $db ?: Database::getInstance();
		}

	/**
	 * Crawl the selected targets and stream progress payloads.
	 * @param array<string, mixed> $input Request input values.
	 * @param int $userId Authenticated user identifier.
	 * @return \Generator<int, array<string, mixed>>
	 **/
	public function crawl(array $input, int $userId): \Generator
		{
		$settings = $this->normalizeInput($input);
		if (!$settings['valid'])
			{
			yield $this->event('validation_error', [
				'message' => $settings['message'],
			]);
			return;
			}

		$targets = $this->buildTargets($settings);
		if (empty($targets))
			{
			yield $this->event('validation_error', [
				'message' => 'Nenhum alvo foi gerado para a pesquisa.',
			]);
			return;
			}

		if (count($targets) > self::MAX_TARGETS)
			{
			yield $this->event('validation_error', [
				'message' => 'A combinação de IPs, protocolos e portas excede o limite de ' . self::MAX_TARGETS . ' alvos por execução.',
			]);
			return;
			}

		try
			{
			$scanId = $this->createScan($userId, $settings, count($targets));
			}
		catch (Throwable $e)
			{
			yield $this->event('validation_error', [
				'message' => 'Não foi possível registrar a pesquisa. Aplique o schema SQL do webcrawler antes de executar a ferramenta.',
			]);
			return;
			}

		$stats = $this->emptyStats($scanId, count($targets));
		$startedAt = microtime(true);

		yield $this->event('started', [
			'scan_id' => $scanId,
			'total_targets' => count($targets),
			'start_ip' => $settings['start_ip'],
			'end_ip' => $settings['end_ip'],
			'ip_total' => $settings['ip_total'],
			'ports' => $settings['ports'],
			'protocols' => $settings['protocols'],
			'started_at' => date('c'),
		]);

		try
			{
			foreach ($targets as $target)
				{
				$stats['processed_targets']++;

				if (!empty($settings['skip_cached_failures']))
					{
					$cachedFailure = $this->readCachedFailure($target);
					if ($cachedFailure !== null)
						{
						$stats['skipped_targets']++;
						$this->updateScanStats($scanId, $stats, 'running');
						yield $this->event('target_skipped', $this->targetPayload($target, [
							'scan_id' => $scanId,
							'status' => 'skipped',
							'message' => 'Alvo ignorado por cache de falha até ' . (string) $cachedFailure['retry_after'] . '.',
							'retry_after' => $cachedFailure['retry_after'],
							'stats' => $this->statsPayload($stats, $startedAt),
						]));
						continue;
						}
					}

				$targetId = $this->findOrCreateTarget($target);
				$robots = $this->readRobots($target, $settings);

				if (!$robots['target_allowed'])
					{
					$stats['failed_targets']++;
					$this->recordTargetResult($targetId, [
						'last_status' => 'robots_blocked',
						'http_status' => (int) ($robots['http_status'] ?? 0),
						'robots_status' => (string) ($robots['robots_status'] ?? 'blocked'),
						'robots_txt_hash' => (string) ($robots['robots_txt_hash'] ?? ''),
						'crawl_delay_ms' => (int) ($robots['crawl_delay_ms'] ?? 0),
						'last_error' => (string) ($robots['message'] ?? 'Robots.txt bloqueou a pesquisa.'),
						'retry_after' => null,
					]);
					$this->updateScanStats($scanId, $stats, 'running');
					yield $this->event('target', $this->targetPayload($target, [
						'scan_id' => $scanId,
						'target_id' => $targetId,
						'status' => 'robots_blocked',
						'message' => (string) ($robots['message'] ?? 'Robots.txt bloqueou a pesquisa.'),
						'stats' => $this->statsPayload($stats, $startedAt),
					]));
					continue;
					}

				yield $this->event('target_started', $this->targetPayload($target, [
					'scan_id' => $scanId,
					'target_id' => $targetId,
					'robots_status' => $robots['robots_status'],
					'crawl_delay_ms' => $robots['crawl_delay_ms'],
				]));

				foreach ($this->crawlTarget($scanId, $targetId, $target, $settings, $robots, $stats, $startedAt) as $payload)
					{
					yield $payload;
					}

				$this->updateScanStats($scanId, $stats, 'running');
				$this->pauseAfterTarget((int) $settings['delay_ms'], (int) ($robots['crawl_delay_ms'] ?? 0));
				}

			$this->updateScanStats($scanId, $stats, 'completed', 'Pesquisa concluída.');
			yield $this->event('summary', [
				'scan_id' => $scanId,
				'status' => 'completed',
				'message' => 'Pesquisa concluída.',
				'stats' => $this->statsPayload($stats, $startedAt),
				'finished_at' => date('c'),
			]);
			}
		catch (Throwable $e)
			{
			$this->updateScanStats($scanId, $stats, 'failed', $e->getMessage());
			yield $this->event('summary', [
				'scan_id' => $scanId,
				'status' => 'failed',
				'message' => $e->getMessage(),
				'stats' => $this->statsPayload($stats, $startedAt),
				'finished_at' => date('c'),
			]);
			}
		}

	/**
	 * Normalize and validate the crawler input.
	 * @param array<string, mixed> $input Request input values.
	 * @return array<string, mixed> Normalized settings.
	 **/
	private function normalizeInput(array $input): array
		{
		$rangeMode = $this->normalizeChoice((string) ($input['range_mode'] ?? 'amount'), ['amount', 'cidr', 'manual'], 'amount');
		$terms = $this->normalizeSearchTerms((string) ($input['search_terms'] ?? ''));
		$ports = $this->normalizePorts((string) ($input['ports'] ?? '80,443'));
		$protocols = $this->normalizeProtocols($input['protocols'] ?? ['http', 'https']);
		$paths = $this->normalizePaths((string) ($input['start_paths'] ?? '/'));
		$robotsMode = $this->normalizeChoice((string) ($input['robots_mode'] ?? 'respect'), ['respect', 'require', 'ignore'], 'respect');
		$userAgent = $this->normalizeUserAgent((string) ($input['user_agent'] ?? ''));
		$timeoutMs = $this->boundInt((string) ($input['timeout_ms'] ?? '3000'), 3000, self::MIN_TIMEOUT_MS, self::MAX_TIMEOUT_MS);
		$delayMs = $this->boundInt((string) ($input['delay_ms'] ?? '250'), 250, 0, self::MAX_DELAY_MS);
		$maxDepth = $this->boundInt((string) ($input['max_depth'] ?? '1'), 1, 0, self::MAX_DEPTH);
		$maxPagesPerTarget = $this->boundInt((string) ($input['max_pages_per_target'] ?? '10'), 10, 1, self::MAX_PAGES_PER_TARGET);
		$skipCachedFailures = $this->normalizeBoolean($input['skip_cached_failures'] ?? true);
		$range = $this->normalizeIpRange($rangeMode, $input);

		if (!$range['valid'])
			{
			return ['valid' => false, 'message' => $range['message']];
			}

		if (empty($ports))
			{
			return ['valid' => false, 'message' => 'Informe ao menos uma porta válida.'];
			}

		if (empty($protocols))
			{
			return ['valid' => false, 'message' => 'Selecione ao menos um protocolo.'];
			}

		if (empty($paths))
			{
			return ['valid' => false, 'message' => 'Informe ao menos um caminho inicial.'];
			}

		return [
			'valid' => true,
			'message' => '',
			'range_mode' => $rangeMode,
			'start_ip' => $range['start_ip'],
			'end_ip' => $range['end_ip'],
			'start_long' => $range['start_long'],
			'end_long' => $range['end_long'],
			'ip_total' => $range['ip_total'],
			'search_terms' => $terms,
			'search_hash' => $this->searchHash($terms),
			'ports' => $ports,
			'protocols' => $protocols,
			'start_paths' => $paths,
			'robots_mode' => $robotsMode,
			'user_agent' => $userAgent,
			'timeout_ms' => $timeoutMs,
			'delay_ms' => $delayMs,
			'max_depth' => $maxDepth,
			'max_pages_per_target' => $maxPagesPerTarget,
			'skip_cached_failures' => $skipCachedFailures,
		];
		}

	/**
	 * Build the generated target combinations.
	 * @param array<string, mixed> $settings Normalized crawler settings.
	 * @return array<int, array<string, mixed>>
	 **/
	private function buildTargets(array $settings): array
		{
		$targets = [];

		for ($ipLong = (int) $settings['start_long']; $ipLong <= (int) $settings['end_long']; $ipLong++)
			{
			$ipAddress = long2ip($ipLong);
			if (!is_string($ipAddress) || $ipAddress === '')
				{
				continue;
				}

			foreach ($settings['protocols'] as $protocol)
				{
				foreach ($settings['ports'] as $port)
					{
					$targets[] = [
						'ip_address' => $ipAddress,
						'protocol' => (string) $protocol,
						'port' => (int) $port,
					];
					}
				}
			}

		return $targets;
		}

	/**
	 * Crawl a single protocol/IP/port target.
	 * @param int $scanId Scan identifier.
	 * @param int $targetId Target identifier.
	 * @param array<string, mixed> $target Target values.
	 * @param array<string, mixed> $settings Crawler settings.
	 * @param array<string, mixed> $robots Robots rules.
	 * @param array<string, int> $stats Mutable scan statistics.
	 * @param float $startedAt Unix timestamp with microseconds.
	 * @return \Generator<int, array<string, mixed>>
	 **/
	private function crawlTarget(int $scanId, int $targetId, array $target, array $settings, array $robots, array &$stats, float $startedAt): \Generator
		{
		$queue = [];
		$visited = [];
		$targetMatched = false;
		$targetOnline = false;
		$lastFailure = null;
		$lastHttpStatus = 0;
		$pagesChecked = 0;
		$robotsBlockedPages = 0;

		foreach ($settings['start_paths'] as $path)
			{
			$queue[] = ['path' => $path, 'depth' => 0];
			}

		while (!empty($queue) && $pagesChecked < (int) $settings['max_pages_per_target'])
			{
			$current = array_shift($queue);
			$path = $this->normalizePath((string) ($current['path'] ?? '/'));
			$depth = (int) ($current['depth'] ?? 0);

			if (isset($visited[$path]))
				{
				continue;
				}

			$visited[$path] = true;

			if (!$this->isPathAllowedByRobots($path, $robots))
				{
				$robotsBlockedPages++;
				$pageId = $this->recordPage($scanId, $targetId, $this->buildUrl($target, $path), $path, $depth, [
					'http_status' => 0,
					'content_type' => '',
					'title' => '',
					'matched' => false,
					'match_count' => 0,
					'content_length' => 0,
					'duration_ms' => 0,
					'error_message' => 'Bloqueado por robots.txt.',
				]);
				yield $this->event('page', $this->pagePayload($target, $scanId, $targetId, $pageId, $path, [
					'status' => 'robots_blocked',
					'message' => 'Caminho bloqueado por robots.txt.',
					'stats' => $this->statsPayload($stats, $startedAt),
				]));
				continue;
				}

			$pagesChecked++;
			$url = $this->buildUrl($target, $path);
			$response = $this->fetchUrl($url, (int) $settings['timeout_ms'], (string) $settings['user_agent']);
			$lastHttpStatus = (int) ($response['http_status'] ?? 0);

			if ($lastHttpStatus > 0)
				{
				$targetOnline = true;
				}
			else
				{
				$lastFailure = $this->classifyTransportFailure($response);
				}

			$matches = [];
			$title = '';
			$contentType = (string) ($response['content_type'] ?? '');
			$body = (string) ($response['body'] ?? '');
			$canInspectBody = $this->isInspectableContentType($contentType) && $body !== '';

			if ($canInspectBody)
				{
				$title = $this->extractTitle($body);
				$matches = $this->findMatches($body, $settings['search_terms']);
				}

			$pageId = $this->recordPage($scanId, $targetId, $url, $path, $depth, [
				'http_status' => $lastHttpStatus,
				'content_type' => $contentType,
				'title' => $title,
				'matched' => !empty($matches),
				'match_count' => count($matches),
				'content_length' => strlen($body),
				'duration_ms' => (int) ($response['duration_ms'] ?? 0),
				'error_message' => (string) ($response['error_message'] ?? ''),
			]);

			if (!empty($matches))
				{
				$targetMatched = true;
				$stats['matched_pages']++;
				$this->recordMatches($scanId, $targetId, $pageId, $matches);
				}

			yield $this->event('page', $this->pagePayload($target, $scanId, $targetId, $pageId, $path, [
				'status' => $lastHttpStatus > 0 ? 'checked' : 'failed',
				'http_status' => $lastHttpStatus,
				'content_type' => $contentType,
				'title' => $title,
				'matched' => !empty($matches),
				'matches' => $matches,
				'duration_ms' => (int) ($response['duration_ms'] ?? 0),
				'message' => (string) ($response['error_message'] ?? ''),
				'stats' => $this->statsPayload($stats, $startedAt),
			]));

			if ($canInspectBody && $depth < (int) $settings['max_depth'] && $lastHttpStatus >= 200 && $lastHttpStatus < 300)
				{
				foreach ($this->extractInternalLinks($body, $path, $target) as $nextPath)
					{
					if (!isset($visited[$nextPath]) && count($queue) < (int) $settings['max_pages_per_target'])
						{
						$queue[] = ['path' => $nextPath, 'depth' => $depth + 1];
						}
					}
				}
			}

		$targetStatus = $targetOnline ? 'online' : (string) ($lastFailure['status'] ?? 'not_found');
		if (!$targetOnline && $pagesChecked === 0 && $robotsBlockedPages > 0)
			{
			$targetStatus = 'robots_blocked';
			$lastFailure = ['message' => 'Todos os caminhos iniciais foram bloqueados por robots.txt.'];
			}

		if ($targetMatched)
			{
			$stats['found_targets']++;
			}
		elseif (!$targetOnline)
			{
			$stats['failed_targets']++;
			}

		$this->recordTargetResult($targetId, [
			'last_status' => $targetStatus,
			'http_status' => $lastHttpStatus,
			'robots_status' => (string) ($robots['robots_status'] ?? 'unknown'),
			'robots_txt_hash' => (string) ($robots['robots_txt_hash'] ?? ''),
			'crawl_delay_ms' => (int) ($robots['crawl_delay_ms'] ?? 0),
			'last_error' => (string) ($lastFailure['message'] ?? ''),
			'retry_after' => ($targetOnline || $targetStatus === 'robots_blocked') ? null : $this->retryAfterForFailure((string) ($lastFailure['status'] ?? 'not_found')),
			'title' => '',
		]);

		yield $this->event('target_finished', $this->targetPayload($target, [
			'scan_id' => $scanId,
			'target_id' => $targetId,
			'status' => $targetStatus,
			'matched' => $targetMatched,
			'pages_checked' => $pagesChecked,
			'stats' => $this->statsPayload($stats, $startedAt),
		]));
		}

	/**
	 * Read robots.txt rules for a target.
	 * @param array<string, mixed> $target Target values.
	 * @param array<string, mixed> $settings Crawler settings.
	 * @return array<string, mixed> Robots state and rules.
	 **/
	private function readRobots(array $target, array $settings): array
		{
		if ((string) $settings['robots_mode'] === 'ignore')
			{
			return [
				'target_allowed' => true,
				'robots_status' => 'ignored',
				'robots_txt_hash' => '',
				'crawl_delay_ms' => 0,
				'rules' => [],
			];
			}

		$response = $this->fetchUrl($this->buildUrl($target, '/robots.txt'), (int) $settings['timeout_ms'], (string) $settings['user_agent']);
		$httpStatus = (int) ($response['http_status'] ?? 0);
		$body = (string) ($response['body'] ?? '');

		if ($httpStatus === 404)
			{
			return [
				'target_allowed' => true,
				'robots_status' => 'not_found',
				'http_status' => 404,
				'robots_txt_hash' => '',
				'crawl_delay_ms' => 0,
				'rules' => [],
			];
			}

		if ($httpStatus < 200 || $httpStatus >= 300 || $body === '')
			{
			$requiresRobots = (string) $settings['robots_mode'] === 'require';
			return [
				'target_allowed' => !$requiresRobots,
				'robots_status' => 'unavailable',
				'http_status' => $httpStatus,
				'robots_txt_hash' => '',
				'crawl_delay_ms' => 0,
				'rules' => [],
				'message' => $requiresRobots ? 'Robots.txt indisponível no modo obrigatório.' : 'Robots.txt indisponível; crawler liberado pelo modo respeitar.',
			];
			}

		$parsed = $this->parseRobots($body, (string) $settings['user_agent']);
		return [
			'target_allowed' => true,
			'robots_status' => 'allowed',
			'http_status' => $httpStatus,
			'robots_txt_hash' => hash('sha256', $body),
			'crawl_delay_ms' => $parsed['crawl_delay_ms'],
			'rules' => $parsed['rules'],
		];
		}

	/**
	 * Parse robots.txt rules for this crawler and wildcard user agents.
	 * @param string $body Robots.txt contents.
	 * @return array<string, mixed> Parsed rules and crawl delay.
	 **/
	private function parseRobots(string $body, string $userAgent): array
		{
		$rules = [];
		$crawlDelayMs = 0;
		$isActiveGroup = false;

		foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line)
			{
			$line = trim(preg_replace('/#.*/', '', (string) $line));
			if ($line === '' || strpos($line, ':') === false)
				{
				continue;
				}

			[$field, $value] = array_map('trim', explode(':', $line, 2));
			$field = strtolower($field);

			if ($field === 'user-agent')
				{
				$agent = strtolower($value);
				$isActiveGroup = $this->robotsUserAgentMatches($agent, $userAgent);
				continue;
				}

			if (!$isActiveGroup)
				{
				continue;
				}

			if (($field === 'allow' || $field === 'disallow') && $value !== '')
				{
				$rules[] = [
					'type' => $field,
					'path' => $this->normalizePath($value),
					'length' => strlen($value),
				];
				}

			if ($field === 'crawl-delay')
				{
				$crawlDelayMs = min(self::MAX_DELAY_MS, max(0, (int) round(((float) $value) * 1000)));
				}
			}

		return [
			'rules' => $rules,
			'crawl_delay_ms' => $crawlDelayMs,
		];
		}

	/**
	 * Check whether a path is allowed by parsed robots rules.
	 * @param string $path URL path.
	 * @param array<string, mixed> $robots Parsed robots state.
	 * @return bool True when the path may be requested.
	 **/
	private function isPathAllowedByRobots(string $path, array $robots): bool
		{
		$rules = $robots['rules'] ?? [];
		if (!is_array($rules) || empty($rules))
			{
			return true;
			}

		$path = $this->normalizePath($path);
		$matchedRule = null;

		foreach ($rules as $rule)
			{
			$rulePath = (string) ($rule['path'] ?? '');
			if ($rulePath === '' || !str_starts_with($path, $rulePath))
				{
				continue;
				}

			if ($matchedRule === null || (int) ($rule['length'] ?? 0) >= (int) ($matchedRule['length'] ?? 0))
				{
				$matchedRule = $rule;
				}
			}

		if ($matchedRule === null)
			{
			return true;
			}

		return (string) ($matchedRule['type'] ?? '') === 'allow';
		}

	/**
	 * Check whether a robots user-agent token matches the selected request user agent.
	 * @param string $robotsAgent Robots user-agent value.
	 * @param string $userAgent Selected request user agent.
	 * @return bool True when the robots group applies.
	 **/
	private function robotsUserAgentMatches(string $robotsAgent, string $userAgent): bool
		{
		$robotsAgent = strtolower(trim($robotsAgent));
		$userAgent = strtolower(trim($userAgent));

		if ($robotsAgent === '*')
			{
			return true;
			}

		if ($robotsAgent === '')
			{
			return false;
			}

		return str_contains($userAgent, $robotsAgent);
		}

	/**
	 * Fetch a URL with cURL or a stream fallback.
	 * @param string $url URL to request.
	 * @param int $timeoutMs Request timeout in milliseconds.
	 * @param string $userAgent Request user agent.
	 * @return array<string, mixed> Transport response.
	 **/
	private function fetchUrl(string $url, int $timeoutMs, string $userAgent): array
		{
		if (function_exists('curl_init'))
			{
			return $this->fetchUrlWithCurl($url, $timeoutMs, $userAgent);
			}

		return $this->fetchUrlWithStream($url, $timeoutMs, $userAgent);
		}

	/**
	 * Fetch a URL with cURL.
	 * @param string $url URL to request.
	 * @param int $timeoutMs Request timeout in milliseconds.
	 * @param string $userAgent Request user agent.
	 * @return array<string, mixed> Transport response.
	 **/
	private function fetchUrlWithCurl(string $url, int $timeoutMs, string $userAgent): array
		{
		$startedAt = microtime(true);
		$handle = curl_init($url);
		if ($handle === false)
			{
			return [
				'http_status' => 0,
				'body' => '',
				'content_type' => '',
				'duration_ms' => 0,
				'curl_errno' => 0,
				'error_message' => 'Não foi possível iniciar cURL.',
			];
			}

		curl_setopt_array($handle, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
			CURLOPT_TIMEOUT_MS => $timeoutMs,
			CURLOPT_USERAGENT => $userAgent,
			CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,text/plain;q=0.8,*/*;q=0.4'],
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
		]);

		$body = curl_exec($handle);
		$httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
		$contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
		$errorNumber = curl_errno($handle);
		$errorMessage = $errorNumber !== 0 ? curl_error($handle) : '';
		if (PHP_VERSION_ID < 80500)
			{
			curl_close($handle);
			}

		return [
			'http_status' => $httpStatus,
			'body' => is_string($body) ? $body : '',
			'content_type' => $contentType,
			'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
			'curl_errno' => $errorNumber,
			'error_message' => $errorMessage,
		];
		}

	/**
	 * Fetch a URL with PHP streams.
	 * @param string $url URL to request.
	 * @param int $timeoutMs Request timeout in milliseconds.
	 * @param string $userAgent Request user agent.
	 * @return array<string, mixed> Transport response.
	 **/
	private function fetchUrlWithStream(string $url, int $timeoutMs, string $userAgent): array
		{
		$startedAt = microtime(true);
		$context = stream_context_create([
			'http' => [
				'ignore_errors' => true,
				'method' => 'GET',
				'timeout' => max(1, (int) ceil($timeoutMs / 1000)),
				'header' => "Accept: text/html,application/xhtml+xml,text/plain;q=0.8,*/*;q=0.4\r\nUser-Agent: " . $userAgent . "\r\n",
			],
			'ssl' => [
				'verify_peer' => false,
				'verify_peer_name' => false,
			],
		]);

		$body = @file_get_contents($url, false, $context);
		$headers = $http_response_header ?? [];

		return [
			'http_status' => $this->statusFromHeaders($headers),
			'body' => is_string($body) ? $body : '',
			'content_type' => $this->contentTypeFromHeaders($headers),
			'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
			'curl_errno' => 0,
			'error_message' => is_string($body) ? '' : 'Falha de transporte.',
		];
		}

	/**
	 * Extract the status code from HTTP response headers.
	 * @param array<int, string> $headers Response headers.
	 * @return int HTTP status code or zero.
	 **/
	private function statusFromHeaders(array $headers): int
		{
		$statusLine = (string) ($headers[0] ?? '');
		if (preg_match('/\s(\d{3})\s/', $statusLine, $matches))
			{
			return (int) $matches[1];
			}

		return 0;
		}

	/**
	 * Extract the content type from HTTP response headers.
	 * @param array<int, string> $headers Response headers.
	 * @return string Content type or empty string.
	 **/
	private function contentTypeFromHeaders(array $headers): string
		{
		foreach ($headers as $header)
			{
			if (stripos($header, 'content-type:') === 0)
				{
				return trim(substr($header, strlen('content-type:')));
				}
			}

		return '';
		}

	/**
	 * Normalize IP range inputs for each range mode.
	 * @param string $rangeMode Selected range mode.
	 * @param array<string, mixed> $input Request input values.
	 * @return array<string, mixed> Normalized range payload.
	 **/
	private function normalizeIpRange(string $rangeMode, array $input): array
		{
		if ($rangeMode === 'cidr')
			{
			return $this->normalizeCidrRange((string) ($input['cidr'] ?? ''));
			}

		$startIp = trim((string) ($input['start_ip'] ?? ''));
		$startLong = $this->ipToLong($startIp);
		if ($startLong === null)
			{
			return ['valid' => false, 'message' => 'Informe um IP inicial IPv4 válido.'];
			}

		if ($rangeMode === 'manual')
			{
			$endIp = trim((string) ($input['end_ip'] ?? ''));
			$endLong = $this->ipToLong($endIp);
			if ($endLong === null || $endLong < $startLong)
				{
				return ['valid' => false, 'message' => 'Informe um IP final IPv4 válido e maior ou igual ao inicial.'];
				}
			}
		else
			{
			$quantity = $this->boundInt((string) ($input['ip_quantity'] ?? '1'), 1, 1, self::MAX_IPS);
			$endLong = $startLong + $quantity - 1;
			if ($endLong > 4294967295)
				{
				return ['valid' => false, 'message' => 'A quantidade ultrapassa o limite do espaço IPv4.'];
				}
			}

		$ipTotal = $endLong - $startLong + 1;
		if ($ipTotal > self::MAX_IPS)
			{
			return ['valid' => false, 'message' => 'A faixa excede o limite de ' . self::MAX_IPS . ' IPs por execução.'];
			}

		return [
			'valid' => true,
			'start_ip' => long2ip($startLong),
			'end_ip' => long2ip($endLong),
			'start_long' => $startLong,
			'end_long' => $endLong,
			'ip_total' => $ipTotal,
		];
		}

	/**
	 * Normalize a CIDR range.
	 * @param string $cidr CIDR value.
	 * @return array<string, mixed> Normalized range payload.
	 **/
	private function normalizeCidrRange(string $cidr): array
		{
		$cidr = trim($cidr);
		if (!preg_match('/^([0-9.]+)\/([0-9]{1,2})$/', $cidr, $matches))
			{
			return ['valid' => false, 'message' => 'Informe um CIDR IPv4 válido.'];
			}

		$baseLong = $this->ipToLong($matches[1]);
		$prefix = (int) $matches[2];
		if ($baseLong === null || $prefix < 0 || $prefix > 32)
			{
			return ['valid' => false, 'message' => 'Informe um CIDR IPv4 válido.'];
			}

		$mask = $prefix === 0 ? 0 : (0xFFFFFFFF << (32 - $prefix)) & 0xFFFFFFFF;
		$network = $baseLong & $mask;
		$broadcast = $network | (~$mask & 0xFFFFFFFF);
		$startLong = $prefix <= 30 ? $network + 1 : $network;
		$endLong = $prefix <= 30 ? $broadcast - 1 : $broadcast;
		$ipTotal = $endLong - $startLong + 1;

		if ($ipTotal <= 0 || $ipTotal > self::MAX_IPS)
			{
			return ['valid' => false, 'message' => 'O CIDR informado excede o limite de ' . self::MAX_IPS . ' IPs úteis por execução.'];
			}

		return [
			'valid' => true,
			'start_ip' => long2ip($startLong),
			'end_ip' => long2ip($endLong),
			'start_long' => $startLong,
			'end_long' => $endLong,
			'ip_total' => $ipTotal,
		];
		}

	/**
	 * Normalize search terms.
	 * @param string $value Raw terms.
	 * @return array<int, string> Terms list.
	 **/
	private function normalizeSearchTerms(string $value): array
		{
		$items = preg_split('/[\r\n,]+/', $value) ?: [];
		$terms = [];

		foreach ($items as $item)
			{
			$term = trim((string) $item);
			if ($term === '')
				{
				continue;
				}

			$terms[$term] = mb_substr($term, 0, 120);
			if (count($terms) >= self::MAX_TERMS)
				{
				break;
				}
			}

		return array_values($terms);
		}

	/**
	 * Normalize port input supporting commas and ranges.
	 * @param string $value Raw ports value.
	 * @return array<int, int> Port list.
	 **/
	private function normalizePorts(string $value): array
		{
		$ports = [];
		foreach (preg_split('/[\s,;]+/', $value) ?: [] as $chunk)
			{
			$chunk = trim((string) $chunk);
			if ($chunk === '')
				{
				continue;
				}

			if (preg_match('/^(\d{1,5})-(\d{1,5})$/', $chunk, $matches))
				{
				$start = max(1, min(65535, (int) $matches[1]));
				$end = max(1, min(65535, (int) $matches[2]));
				for ($port = min($start, $end); $port <= max($start, $end); $port++)
					{
					$ports[$port] = $port;
					if (count($ports) >= self::MAX_PORTS)
						{
						break 2;
						}
					}
				continue;
				}

			$port = (int) preg_replace('/\D+/', '', $chunk);
			if ($port >= 1 && $port <= 65535)
				{
				$ports[$port] = $port;
				}

			if (count($ports) >= self::MAX_PORTS)
				{
				break;
				}
			}

		sort($ports);
		return array_values($ports);
		}

	/**
	 * Normalize selected protocols.
	 * @param mixed $value Raw protocol value.
	 * @return array<int, string> Protocol list.
	 **/
	private function normalizeProtocols($value): array
		{
		$rawProtocols = is_array($value) ? $value : preg_split('/[\s,;]+/', (string) $value);
		$protocols = [];

		foreach ($rawProtocols ?: [] as $protocol)
			{
			$protocol = strtolower(trim((string) $protocol));
			if (in_array($protocol, ['http', 'https'], true))
				{
				$protocols[$protocol] = $protocol;
				}
			}

		return array_values($protocols);
		}

	/**
	 * Normalize starting paths.
	 * @param string $value Raw path list.
	 * @return array<int, string> Path list.
	 **/
	private function normalizePaths(string $value): array
		{
		$paths = [];
		foreach (preg_split('/[\r\n,]+/', $value) ?: [] as $path)
			{
			$path = $this->normalizePath((string) $path);
			$paths[$path] = $path;
			if (count($paths) >= self::MAX_PATHS)
				{
				break;
				}
			}

		return array_values($paths);
		}

	/**
	 * Normalize a URL path.
	 * @param string $path Raw path.
	 * @return string Normalized path.
	 **/
	private function normalizePath(string $path): string
		{
		$path = trim($path);
		if ($path === '' || $path[0] !== '/')
			{
			$path = '/' . ltrim($path, '/');
			}

		return mb_substr($path, 0, 1024);
		}

	/**
	 * Convert an IPv4 address to an unsigned integer.
	 * @param string $ipAddress IPv4 address.
	 * @return int|null Unsigned integer or null.
	 **/
	private function ipToLong(string $ipAddress): ?int
		{
		$ipAddress = trim($ipAddress);
		if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)
			{
			return null;
			}

		return (int) sprintf('%u', ip2long($ipAddress));
		}

	/**
	 * Build a URL for a target and path.
	 * @param array<string, mixed> $target Target values.
	 * @param string $path URL path.
	 * @return string URL.
	 **/
	private function buildUrl(array $target, string $path): string
		{
		$protocol = (string) $target['protocol'];
		$port = (int) $target['port'];
		$defaultPort = $protocol === 'https' ? 443 : 80;
		$host = (string) $target['ip_address'];
		$portPart = $port === $defaultPort ? '' : ':' . $port;

		return $protocol . '://' . $host . $portPart . $this->normalizePath($path);
		}

	/**
	 * Determine whether a content type can be inspected as text.
	 * @param string $contentType HTTP content type.
	 * @return bool True when content may be searched.
	 **/
	private function isInspectableContentType(string $contentType): bool
		{
		$contentType = strtolower($contentType);
		return $contentType === '' || str_contains($contentType, 'text/') || str_contains($contentType, 'html') || str_contains($contentType, 'json') || str_contains($contentType, 'xml');
		}

	/**
	 * Extract the title from HTML.
	 * @param string $body Response body.
	 * @return string Page title.
	 **/
	private function extractTitle(string $body): string
		{
		if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $matches))
			{
			return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
			}

		return '';
		}

	/**
	 * Find configured terms in a response body.
	 * @param string $body Response body.
	 * @param array<int, string> $terms Search terms.
	 * @return array<int, array<string, mixed>> Matches.
	 **/
	private function findMatches(string $body, array $terms): array
		{
		if (empty($terms))
			{
			return [];
			}

		$text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
		$matches = [];

		foreach ($terms as $term)
			{
			$offset = 0;
			$foundForTerm = 0;
			while (($position = stripos($text, $term, $offset)) !== false)
				{
				$snippetStart = max(0, $position - 80);
				$snippet = trim(substr($text, $snippetStart, 220));
				$matches[] = [
					'term' => $term,
					'position' => $position,
					'snippet' => $snippet,
				];
				$offset = $position + max(1, strlen($term));
				$foundForTerm++;
				if ($foundForTerm >= 3 || count($matches) >= 15)
					{
					break;
					}
				}
			}

		return $matches;
		}

	/**
	 * Extract links that still point to the same IP target.
	 * @param string $body Response body.
	 * @param string $currentPath Current path.
	 * @param array<string, mixed> $target Target values.
	 * @return array<int, string> Internal paths.
	 **/
	private function extractInternalLinks(string $body, string $currentPath, array $target): array
		{
		$links = [];
		if (!preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\']/i', $body, $matches))
			{
			return [];
			}

		foreach ($matches[1] as $href)
			{
			$path = $this->pathFromHref((string) $href, $currentPath, $target);
			if ($path !== null)
				{
				$links[$path] = $path;
				}
			}

		return array_values($links);
		}

	/**
	 * Convert a link href into a same-target path.
	 * @param string $href Link href.
	 * @param string $currentPath Current path.
	 * @param array<string, mixed> $target Target values.
	 * @return string|null Path or null when external/unsupported.
	 **/
	private function pathFromHref(string $href, string $currentPath, array $target): ?string
		{
		$href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
		if ($href === '' || preg_match('/^(mailto|tel|javascript):/i', $href))
			{
			return null;
			}

		$href = preg_replace('/#.*$/', '', $href) ?? '';
		if ($href === '')
			{
			return null;
			}

		if (preg_match('/^https?:\/\//i', $href))
			{
			$parts = parse_url($href);
			if (!is_array($parts))
				{
				return null;
				}

			$port = isset($parts['port']) ? (int) $parts['port'] : ((string) ($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);
			if (($parts['host'] ?? '') !== $target['ip_address'] || (string) ($parts['scheme'] ?? '') !== $target['protocol'] || $port !== (int) $target['port'])
				{
				return null;
				}

			return $this->normalizePath((string) ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : ''));
			}

		if (str_starts_with($href, '//'))
			{
			return null;
			}

		if (str_starts_with($href, '/'))
			{
			return $this->normalizePath($href);
			}

		$baseDirectory = rtrim(dirname($currentPath), '/');
		return $this->normalizePath(($baseDirectory === '' ? '' : $baseDirectory) . '/' . $href);
		}

	/**
	 * Classify a failed transport response.
	 * @param array<string, mixed> $response Transport response.
	 * @return array<string, string> Failure status and message.
	 **/
	private function classifyTransportFailure(array $response): array
		{
		$errorNumber = (int) ($response['curl_errno'] ?? 0);
		$errorMessage = (string) ($response['error_message'] ?? '');

		if ($errorNumber === 28)
			{
			return ['status' => 'timeout', 'message' => $errorMessage !== '' ? $errorMessage : 'Timeout na conexão.'];
			}

		if (in_array($errorNumber, [7, 52, 56], true))
			{
			return ['status' => 'connection_refused', 'message' => $errorMessage !== '' ? $errorMessage : 'Conexão recusada ou encerrada.'];
			}

		return ['status' => 'not_found', 'message' => $errorMessage !== '' ? $errorMessage : 'Serviço HTTP/HTTPS não encontrado.'];
		}

	/**
	 * Build retry datetime for a failed target.
	 * @param string $status Failure status.
	 * @return string Retry datetime.
	 **/
	private function retryAfterForFailure(string $status): string
		{
		$hours = match ($status)
			{
			'timeout' => self::TIMEOUT_RETRY_HOURS,
			'connection_refused' => self::REFUSED_RETRY_HOURS,
			default => self::DEFAULT_RETRY_HOURS,
			};

		return date('Y-m-d H:i:s', time() + ($hours * 3600));
		}

	/**
	 * Pause between targets using configured delay and robots crawl-delay.
	 * @param int $delayMs Configured delay.
	 * @param int $crawlDelayMs Robots crawl delay.
	 * @return void
	 **/
	private function pauseAfterTarget(int $delayMs, int $crawlDelayMs): void
		{
		$pauseMs = max($delayMs, $crawlDelayMs);
		if ($pauseMs > 0)
			{
			usleep($pauseMs * 1000);
			}
		}

	/**
	 * Create a scan record.
	 * @param int $userId User identifier.
	 * @param array<string, mixed> $settings Normalized settings.
	 * @param int $targetTotal Total target combinations.
	 * @return int Created scan identifier.
	 **/
	private function createScan(int $userId, array $settings, int $targetTotal): int
		{
		$stmt = $this->db->prepare("INSERT INTO webcrawler_scans (
			user_id,
			search_terms,
			search_hash,
			range_mode,
			start_ip,
			end_ip,
			ip_quantity,
			ports,
			protocols,
			start_paths,
			robots_mode,
			user_agent,
			skip_cached_failures,
			timeout_ms,
			delay_ms,
			max_depth,
			max_pages_per_target,
			target_total,
			status,
			started_at,
			created,
			modified
		) VALUES (
			:user_id,
			:search_terms,
			:search_hash,
			:range_mode,
			:start_ip,
			:end_ip,
			:ip_quantity,
			:ports,
			:protocols,
			:start_paths,
			:robots_mode,
			:user_agent,
			:skip_cached_failures,
			:timeout_ms,
			:delay_ms,
			:max_depth,
			:max_pages_per_target,
			:target_total,
			:status,
			:started_at,
			:created,
			:modified
		)");
		$now = date('Y-m-d H:i:s');
		$stmt->execute([
			'user_id' => $userId > 0 ? $userId : null,
			'search_terms' => implode("\n", $settings['search_terms']),
			'search_hash' => $settings['search_hash'],
			'range_mode' => $settings['range_mode'],
			'start_ip' => $settings['start_ip'],
			'end_ip' => $settings['end_ip'],
			'ip_quantity' => $settings['ip_total'],
			'ports' => implode(',', $settings['ports']),
			'protocols' => implode(',', $settings['protocols']),
			'start_paths' => implode("\n", $settings['start_paths']),
			'robots_mode' => $settings['robots_mode'],
			'user_agent' => $settings['user_agent'],
			'skip_cached_failures' => !empty($settings['skip_cached_failures']) ? 1 : 0,
			'timeout_ms' => $settings['timeout_ms'],
			'delay_ms' => $settings['delay_ms'],
			'max_depth' => $settings['max_depth'],
			'max_pages_per_target' => $settings['max_pages_per_target'],
			'target_total' => $targetTotal,
			'status' => 'running',
			'started_at' => $now,
			'created' => $now,
			'modified' => $now,
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Find an existing target or create it.
	 * @param array<string, mixed> $target Target values.
	 * @return int Target identifier.
	 **/
	private function findOrCreateTarget(array $target): int
		{
		$stmt = $this->db->prepare("SELECT Target.id
		                            FROM webcrawler_targets AS Target
		                            WHERE Target.protocol = :protocol
		                              AND Target.ip_address = :ip_address
		                              AND Target.port = :port
		                            LIMIT 1");
		$stmt->execute([
			'protocol' => $target['protocol'],
			'ip_address' => $target['ip_address'],
			'port' => $target['port'],
		]);
		$id = (int) ($stmt->fetchColumn() ?: 0);
		if ($id > 0)
			{
			return $id;
			}

		$now = date('Y-m-d H:i:s');
		$insert = $this->db->prepare("INSERT INTO webcrawler_targets (
			protocol,
			ip_address,
			port,
			last_status,
			robots_status,
			fail_count,
			created,
			modified
		) VALUES (
			:protocol,
			:ip_address,
			:port,
			:last_status,
			:robots_status,
			:fail_count,
			:created,
			:modified
		)");
		$insert->execute([
			'protocol' => $target['protocol'],
			'ip_address' => $target['ip_address'],
			'port' => $target['port'],
			'last_status' => 'pending',
			'robots_status' => 'unknown',
			'fail_count' => 0,
			'created' => $now,
			'modified' => $now,
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Read a cached failed target that should not be retried yet.
	 * @param array<string, mixed> $target Target values.
	 * @return array<string, mixed>|null Cached failure or null.
	 **/
	private function readCachedFailure(array $target): ?array
		{
		$stmt = $this->db->prepare("SELECT Target.id,
		                                   Target.last_status,
		                                   Target.retry_after,
		                                   Target.last_error
		                            FROM webcrawler_targets AS Target
		                            WHERE Target.protocol = :protocol
		                              AND Target.ip_address = :ip_address
		                              AND Target.port = :port
		                              AND Target.retry_after IS NOT NULL
		                              AND Target.retry_after > :now
		                            LIMIT 1");
		$stmt->execute([
			'protocol' => $target['protocol'],
			'ip_address' => $target['ip_address'],
			'port' => $target['port'],
			'now' => date('Y-m-d H:i:s'),
		]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$row || !in_array((string) $row['last_status'], self::SKIPPABLE_TARGET_STATUSES, true))
			{
			return null;
			}

		return $row;
		}

	/**
	 * Update target status metadata.
	 * @param int $targetId Target identifier.
	 * @param array<string, mixed> $data Target result data.
	 * @return void
	 **/
	private function recordTargetResult(int $targetId, array $data): void
		{
		$lastStatus = (string) ($data['last_status'] ?? 'error');
		$shouldIncrementFailure = in_array($lastStatus, self::SKIPPABLE_TARGET_STATUSES, true);
		$stmt = $this->db->prepare("UPDATE webcrawler_targets AS Target
		                            SET Target.last_status = :last_status,
		                                Target.http_status = :http_status,
		                                Target.title = :title,
		                                Target.robots_status = :robots_status,
		                                Target.robots_checked_at = :robots_checked_at,
		                                Target.robots_txt_hash = :robots_txt_hash,
		                                Target.crawl_delay_ms = :crawl_delay_ms,
		                                Target.fail_count = CASE WHEN :should_increment_failure = 1 THEN Target.fail_count + 1 ELSE 0 END,
		                                Target.retry_after = :retry_after,
		                                Target.last_checked_at = :last_checked_at,
		                                Target.last_error = :last_error,
		                                Target.modified = :modified
		                            WHERE Target.id = :id");
		$now = date('Y-m-d H:i:s');
		$stmt->execute([
			'id' => $targetId,
			'last_status' => $lastStatus,
			'http_status' => (int) ($data['http_status'] ?? 0) ?: null,
			'title' => $this->nullableString((string) ($data['title'] ?? ''), 255),
			'robots_status' => (string) ($data['robots_status'] ?? 'unknown'),
			'robots_checked_at' => $now,
			'robots_txt_hash' => $this->nullableString((string) ($data['robots_txt_hash'] ?? ''), 64),
			'crawl_delay_ms' => (int) ($data['crawl_delay_ms'] ?? 0),
			'should_increment_failure' => $shouldIncrementFailure ? 1 : 0,
			'retry_after' => $data['retry_after'] ?? null,
			'last_checked_at' => $now,
			'last_error' => $this->nullableString((string) ($data['last_error'] ?? ''), 500),
			'modified' => $now,
		]);
		}

	/**
	 * Record a page checked by the crawler.
	 * @param int $scanId Scan identifier.
	 * @param int $targetId Target identifier.
	 * @param string $url Checked URL.
	 * @param string $path Checked path.
	 * @param int $depth Crawl depth.
	 * @param array<string, mixed> $data Page result data.
	 * @return int Page identifier.
	 **/
	private function recordPage(int $scanId, int $targetId, string $url, string $path, int $depth, array $data): int
		{
		$stmt = $this->db->prepare("INSERT INTO webcrawler_pages (
			scan_id,
			target_id,
			url,
			path,
			depth,
			http_status,
			content_type,
			title,
			matched,
			match_count,
			content_length,
			duration_ms,
			checked_at,
			error_message,
			created
		) VALUES (
			:scan_id,
			:target_id,
			:url,
			:path,
			:depth,
			:http_status,
			:content_type,
			:title,
			:matched,
			:match_count,
			:content_length,
			:duration_ms,
			:checked_at,
			:error_message,
			:created
		)");
		$now = date('Y-m-d H:i:s');
		$stmt->execute([
			'scan_id' => $scanId,
			'target_id' => $targetId,
			'url' => mb_substr($url, 0, 2048),
			'path' => mb_substr($path, 0, 1024),
			'depth' => $depth,
			'http_status' => (int) ($data['http_status'] ?? 0) ?: null,
			'content_type' => $this->nullableString((string) ($data['content_type'] ?? ''), 255),
			'title' => $this->nullableString((string) ($data['title'] ?? ''), 255),
			'matched' => !empty($data['matched']) ? 1 : 0,
			'match_count' => (int) ($data['match_count'] ?? 0),
			'content_length' => (int) ($data['content_length'] ?? 0),
			'duration_ms' => (int) ($data['duration_ms'] ?? 0),
			'checked_at' => $now,
			'error_message' => $this->nullableString((string) ($data['error_message'] ?? ''), 500),
			'created' => $now,
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Record page matches.
	 * @param int $scanId Scan identifier.
	 * @param int $targetId Target identifier.
	 * @param int $pageId Page identifier.
	 * @param array<int, array<string, mixed>> $matches Page matches.
	 * @return void
	 **/
	private function recordMatches(int $scanId, int $targetId, int $pageId, array $matches): void
		{
		$stmt = $this->db->prepare("INSERT INTO webcrawler_matches (
			scan_id,
			target_id,
			page_id,
			search_term,
			snippet,
			position,
			created
		) VALUES (
			:scan_id,
			:target_id,
			:page_id,
			:search_term,
			:snippet,
			:position,
			:created
		)");
		$now = date('Y-m-d H:i:s');

		foreach ($matches as $match)
			{
			$stmt->execute([
				'scan_id' => $scanId,
				'target_id' => $targetId,
				'page_id' => $pageId,
				'search_term' => mb_substr((string) ($match['term'] ?? ''), 0, 255),
				'snippet' => mb_substr((string) ($match['snippet'] ?? ''), 0, 1000),
				'position' => (int) ($match['position'] ?? 0),
				'created' => $now,
			]);
			}
		}

	/**
	 * Update scan progress counters.
	 * @param int $scanId Scan identifier.
	 * @param array<string, int> $stats Scan statistics.
	 * @param string $status Scan status.
	 * @param string $message Optional status message.
	 * @return void
	 **/
	private function updateScanStats(int $scanId, array $stats, string $status, string $message = ''): void
		{
		$stmt = $this->db->prepare("UPDATE webcrawler_scans AS Scan
		                            SET Scan.processed_targets = :processed_targets,
		                                Scan.found_targets = :found_targets,
		                                Scan.skipped_targets = :skipped_targets,
		                                Scan.failed_targets = :failed_targets,
		                                Scan.matched_pages = :matched_pages,
		                                Scan.status = :status,
		                                Scan.message = :message,
		                                Scan.finished_at = CASE WHEN :finished = 1 THEN :finished_at ELSE Scan.finished_at END,
		                                Scan.modified = :modified
		                            WHERE Scan.id = :id");
		$isFinished = in_array($status, ['completed', 'failed', 'stopped'], true);
		$now = date('Y-m-d H:i:s');
		$stmt->execute([
			'id' => $scanId,
			'processed_targets' => (int) $stats['processed_targets'],
			'found_targets' => (int) $stats['found_targets'],
			'skipped_targets' => (int) $stats['skipped_targets'],
			'failed_targets' => (int) $stats['failed_targets'],
			'matched_pages' => (int) $stats['matched_pages'],
			'status' => $status,
			'message' => $this->nullableString($message, 500),
			'finished' => $isFinished ? 1 : 0,
			'finished_at' => $now,
			'modified' => $now,
		]);
		}

	/**
	 * Build empty scan statistics.
	 * @param int $scanId Scan identifier.
	 * @param int $targetTotal Target total.
	 * @return array<string, int> Statistics.
	 **/
	private function emptyStats(int $scanId, int $targetTotal): array
		{
		return [
			'scan_id' => $scanId,
			'target_total' => $targetTotal,
			'processed_targets' => 0,
			'found_targets' => 0,
			'skipped_targets' => 0,
			'failed_targets' => 0,
			'matched_pages' => 0,
		];
		}

	/**
	 * Build a statistics payload.
	 * @param array<string, int> $stats Statistics.
	 * @param float $startedAt Start timestamp.
	 * @return array<string, mixed> Statistics payload.
	 **/
	private function statsPayload(array $stats, float $startedAt): array
		{
		$elapsed = max(0.001, microtime(true) - $startedAt);
		return $stats + [
			'targets_per_second' => round(((int) $stats['processed_targets']) / $elapsed, 2),
			'elapsed_ms' => (int) round($elapsed * 1000),
		];
		}

	/**
	 * Build a target event payload.
	 * @param array<string, mixed> $target Target values.
	 * @param array<string, mixed> $extra Extra payload fields.
	 * @return array<string, mixed> Target payload.
	 **/
	private function targetPayload(array $target, array $extra = []): array
		{
		return [
			'ip_address' => $target['ip_address'],
			'protocol' => $target['protocol'],
			'port' => $target['port'],
			'base_url' => $this->buildUrl($target, '/'),
		] + $extra;
		}

	/**
	 * Build a page event payload.
	 * @param array<string, mixed> $target Target values.
	 * @param int $scanId Scan identifier.
	 * @param int $targetId Target identifier.
	 * @param int $pageId Page identifier.
	 * @param string $path Page path.
	 * @param array<string, mixed> $extra Extra payload fields.
	 * @return array<string, mixed> Page payload.
	 **/
	private function pagePayload(array $target, int $scanId, int $targetId, int $pageId, string $path, array $extra = []): array
		{
		return [
			'scan_id' => $scanId,
			'target_id' => $targetId,
			'page_id' => $pageId,
			'ip_address' => $target['ip_address'],
			'protocol' => $target['protocol'],
			'port' => $target['port'],
			'url' => $this->buildUrl($target, $path),
			'path' => $path,
		] + $extra;
		}

	/**
	 * Build an SSE event payload.
	 * @param string $event Event name.
	 * @param array<string, mixed> $data Event data.
	 * @return array<string, mixed> Stream payload.
	 **/
	private function event(string $event, array $data): array
		{
		return [
			'event' => $event,
			'data' => $data,
		];
		}

	/**
	 * Normalize a boolean-like value.
	 * @param mixed $value Raw value.
	 * @return bool Boolean flag.
	 **/
	private function normalizeBoolean($value): bool
		{
		if (is_bool($value))
			{
			return $value;
			}

		return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
		}

	/**
	 * Normalize the selected request user agent.
	 * @param string $value Raw user agent.
	 * @return string User agent used in HTTP requests.
	 **/
	private function normalizeUserAgent(string $value): string
		{
		$value = trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');
		if ($value === '')
			{
			return self::DEFAULT_USER_AGENT;
			}

		return mb_substr($value, 0, 512);
		}

	/**
	 * Normalize a choice value.
	 * @param string $value Raw value.
	 * @param array<int, string> $allowed Allowed values.
	 * @param string $default Default value.
	 * @return string Normalized value.
	 **/
	private function normalizeChoice(string $value, array $allowed, string $default): string
		{
		$value = strtolower(trim($value));
		return in_array($value, $allowed, true) ? $value : $default;
		}

	/**
	 * Read a bounded integer from text.
	 * @param string $value Raw integer value.
	 * @param int $default Default value.
	 * @param int $min Minimum value.
	 * @param int $max Maximum value.
	 * @return int Bounded integer.
	 **/
	private function boundInt(string $value, int $default, int $min, int $max): int
		{
		$parsed = (int) preg_replace('/\D+/', '', $value);
		if ($parsed <= 0)
			{
			$parsed = $default;
			}

		return max($min, min($max, $parsed));
		}

	/**
	 * Create the normalized search hash.
	 * @param array<int, string> $terms Search terms.
	 * @return string Search hash.
	 **/
	private function searchHash(array $terms): string
		{
		$normalizedTerms = array_map(static fn (string $term): string => mb_strtolower(trim($term)), $terms);
		sort($normalizedTerms);

		return hash('sha256', implode("\n", $normalizedTerms));
		}

	/**
	 * Convert an empty string to null and truncate text.
	 * @param string $value Raw value.
	 * @param int $maxLength Max length.
	 * @return string|null Nullable truncated value.
	 **/
	private function nullableString(string $value, int $maxLength): ?string
		{
		$value = trim($value);
		if ($value === '')
			{
			return null;
			}

		return mb_substr($value, 0, $maxLength);
		}
	}

?>

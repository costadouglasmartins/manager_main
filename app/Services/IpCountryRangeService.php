<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

/**
 * Loads and caches IPv4 country ranges from delegated RIR statistics.
 **/
class IpCountryRangeService
	{
	private const USER_AGENT = 'Manager IpCountryRangeLoader/1.0';
	private const REQUEST_TIMEOUT_SECONDS = 20;
	private const MAX_RESPONSE_RANGES = 250;
	private const MAX_CRAWLER_RANGE_SIZE = 1000;
	private const MIN_CRAWLER_RANGE_SIZE = 1;
	private const SOURCE_URLS = [
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

	private PDO $db;

	/**
	 * Build the country range service.
	 * @param PDO|null $db Optional database connection.
	 **/
	public function __construct(?PDO $db = null)
		{
		$this->db = $db ?: Database::getInstance();
		}

	/**
	 * Return country options used by the crawler form.
	 * @return array<int, array<string, string>> Country options.
	 **/
	public static function countryOptions(): array
		{
		$options = [];

		foreach (self::COUNTRY_NAMES as $code => $name)
			{
			$options[] = [
				'code' => $code,
				'name' => $name,
			];
			}

		return $options;
		}

	/**
	 * Load country ranges from cache or refresh them from RIR delegated files.
	 * @param string $countryCode ISO 3166-1 alpha-2 country code.
	 * @param int $limit Maximum number of ranges returned.
	 * @param bool $refresh Whether to force a remote refresh.
	 * @param int $maxRangeSize Maximum number of IPs per returned crawler range.
	 * @return array<string, mixed> Country ranges payload.
	 **/
	public function loadCountryRanges(string $countryCode, int $limit = self::MAX_RESPONSE_RANGES, bool $refresh = false, int $maxRangeSize = self::MAX_CRAWLER_RANGE_SIZE): array
		{
		$countryCode = $this->normalizeCountryCode($countryCode);
		if ($countryCode === '')
			{
			return [
				'success' => false,
				'message' => 'Informe um país válido.',
				'country_code' => '',
				'ranges' => [],
			];
			}

		$limit = max(1, min(self::MAX_RESPONSE_RANGES, $limit));
		$maxRangeSize = max(self::MIN_CRAWLER_RANGE_SIZE, min(self::MAX_CRAWLER_RANGE_SIZE, $maxRangeSize));
		$imported = 0;
		$fromCache = true;

		if ($refresh || !$this->hasCachedRanges($countryCode))
			{
			$fromCache = false;
			$imported = $this->refreshCountryRanges($countryCode);
			}

		$ranges = $this->readCrawlerSizedRanges($countryCode, $limit, $maxRangeSize);

		return [
			'success' => true,
			'message' => empty($ranges) ? 'Nenhuma faixa IPv4 encontrada para o país informado.' : 'Faixas carregadas.',
			'country_code' => $countryCode,
			'country_name' => $this->countryName($countryCode),
			'from_cache' => $fromCache,
			'imported_ranges' => $imported,
			'returned_ranges' => count($ranges),
			'max_range_size' => $maxRangeSize,
			'ranges' => $ranges,
		];
		}

	/**
	 * Check whether ranges are already cached for a country.
	 * @param string $countryCode Country code.
	 * @return bool True when cached ranges exist.
	 **/
	private function hasCachedRanges(string $countryCode): bool
		{
		$stmt = $this->db->prepare("SELECT COUNT(*)
		                            FROM webcrawler_country_ip_ranges AS RangeItem
		                            WHERE RangeItem.country_code = :country_code
		                              AND RangeItem.active = 1");
		$stmt->execute(['country_code' => $countryCode]);

		return (int) $stmt->fetchColumn() > 0;
		}

	/**
	 * Refresh cached ranges for a country using all delegated RIR files.
	 * @param string $countryCode Country code.
	 * @return int Number of imported ranges.
	 **/
	private function refreshCountryRanges(string $countryCode): int
		{
		$now = date('Y-m-d H:i:s');
		$totalImported = 0;
		$sourceBodies = [];

		foreach (self::SOURCE_URLS as $registry => $url)
			{
			$body = $this->fetchSource($url);
			if ($body !== '')
				{
				$sourceBodies[$registry] = $body;
				}
			}

		if (empty($sourceBodies))
			{
			return 0;
			}

		$this->db->beginTransaction();

		try
			{
			$deactivateStmt = $this->db->prepare("UPDATE webcrawler_country_ip_ranges AS RangeItem
			                                      SET RangeItem.active = 0,
			                                          RangeItem.modified = :modified
			                                      WHERE RangeItem.country_code = :country_code");
			$deactivateStmt->execute([
				'country_code' => $countryCode,
				'modified' => $now,
			]);

			foreach ($sourceBodies as $registry => $body)
				{
				$totalImported += $this->importDelegatedFile($countryCode, $registry, $body, $now);
				}

			$this->db->commit();
			}
		catch (Throwable $e)
			{
			$this->db->rollBack();
			throw $e;
			}

		return $totalImported;
		}

	/**
	 * Import delegated IPv4 records for a country.
	 * @param string $countryCode Country code.
	 * @param string $registry Source registry.
	 * @param string $body Delegated file body.
	 * @param string $now Current datetime.
	 * @return int Imported ranges.
	 **/
	private function importDelegatedFile(string $countryCode, string $registry, string $body, string $now): int
		{
		$insertStmt = $this->db->prepare("INSERT INTO webcrawler_country_ip_ranges (
			country_code,
			country_name,
			source_registry,
			start_ip,
			end_ip,
			start_long,
			end_long,
			address_count,
			status,
			active,
			source_date,
			created,
			modified
		) VALUES (
			:country_code,
			:country_name,
			:source_registry,
			:start_ip,
			:end_ip,
			:start_long,
			:end_long,
			:address_count,
			:status,
			:active,
			:source_date,
			:created,
			:modified
		) ON DUPLICATE KEY UPDATE
			country_name = VALUES(country_name),
			end_ip = VALUES(end_ip),
			end_long = VALUES(end_long),
			address_count = VALUES(address_count),
			status = VALUES(status),
			active = VALUES(active),
			source_date = VALUES(source_date),
			modified = VALUES(modified)");

		$imported = 0;

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

			if (strtoupper((string) $parts[1]) !== $countryCode || strtolower((string) $parts[2]) !== 'ipv4')
				{
				continue;
				}

			$status = strtolower((string) $parts[6]);
			if (!in_array($status, ['allocated', 'assigned'], true))
				{
				continue;
				}

			$startLong = $this->ipToLong((string) $parts[3]);
			$addressCount = (int) $parts[4];
			if ($startLong === null || $addressCount <= 0)
				{
				continue;
				}

			$endLong = $startLong + $addressCount - 1;
			if ($endLong > 4294967295)
				{
				continue;
				}

			$insertStmt->execute([
				'country_code' => $countryCode,
				'country_name' => $this->countryName($countryCode),
				'source_registry' => $registry,
				'start_ip' => long2ip($startLong),
				'end_ip' => long2ip($endLong),
				'start_long' => $startLong,
				'end_long' => $endLong,
				'address_count' => $addressCount,
				'status' => $status,
				'active' => 1,
				'source_date' => $this->normalizeSourceDate((string) $parts[5]),
				'created' => $now,
				'modified' => $now,
			]);
			$imported++;
			}

		return $imported;
		}

	/**
	 * Read cached ranges split into crawler-sized chunks.
	 * @param string $countryCode Country code.
	 * @param int $limit Maximum number of chunks.
	 * @param int $maxRangeSize Maximum number of IPs per chunk.
	 * @return array<int, array<string, mixed>> Range chunks.
	 **/
	private function readCrawlerSizedRanges(string $countryCode, int $limit, int $maxRangeSize): array
		{
		$stmt = $this->db->prepare("SELECT RangeItem.id,
		                                   RangeItem.country_code,
		                                   RangeItem.country_name,
		                                   RangeItem.source_registry,
		                                   RangeItem.start_ip,
		                                   RangeItem.end_ip,
		                                   RangeItem.start_long,
		                                   RangeItem.end_long,
		                                   RangeItem.address_count,
		                                   RangeItem.source_date
		                            FROM webcrawler_country_ip_ranges AS RangeItem
		                            WHERE RangeItem.country_code = :country_code
		                              AND RangeItem.active = 1
		                            ORDER BY RangeItem.start_long ASC");
		$stmt->execute(['country_code' => $countryCode]);

		$ranges = [];
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			{
			$startLong = (int) $row['start_long'];
			$endLong = (int) $row['end_long'];

			while ($startLong <= $endLong && count($ranges) < $limit)
				{
				$chunkEndLong = min($endLong, $startLong + $maxRangeSize - 1);
				$addressCount = $chunkEndLong - $startLong + 1;

				$ranges[] = [
					'id' => (int) $row['id'],
					'country_code' => (string) $row['country_code'],
					'country_name' => (string) $row['country_name'],
					'source_registry' => (string) $row['source_registry'],
					'start_ip' => long2ip($startLong),
					'end_ip' => long2ip($chunkEndLong),
					'address_count' => $addressCount,
					'source_range_start' => (string) $row['start_ip'],
					'source_range_end' => (string) $row['end_ip'],
					'source_address_count' => (int) $row['address_count'],
					'source_date' => (string) ($row['source_date'] ?? ''),
				];

				$startLong = $chunkEndLong + 1;
				}

			if (count($ranges) >= $limit)
				{
				break;
				}
			}

		return $ranges;
		}

	/**
	 * Fetch a delegated file.
	 * @param string $url Source URL.
	 * @return string Response body or empty string.
	 **/
	private function fetchSource(string $url): string
		{
		if (function_exists('curl_init'))
			{
			$handle = curl_init($url);
			if ($handle === false)
				{
				return '';
				}

			curl_setopt_array($handle, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => self::REQUEST_TIMEOUT_SECONDS,
				CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT_SECONDS,
				CURLOPT_USERAGENT => self::USER_AGENT,
			]);

			$body = curl_exec($handle);
			$status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
			if (PHP_VERSION_ID < 80500)
				{
				curl_close($handle);
				}

			return $status >= 200 && $status < 300 && is_string($body) ? $body : '';
			}

		$context = stream_context_create([
			'http' => [
				'timeout' => self::REQUEST_TIMEOUT_SECONDS,
				'header' => "User-Agent: " . self::USER_AGENT . "\r\n",
			],
		]);
		$body = @file_get_contents($url, false, $context);

		return is_string($body) ? $body : '';
		}

	/**
	 * Normalize a country code.
	 * @param string $countryCode Raw country code.
	 * @return string Normalized country code or empty string.
	 **/
	private function normalizeCountryCode(string $countryCode): string
		{
		$countryCode = strtoupper(trim($countryCode));
		return preg_match('/^[A-Z]{2}$/', $countryCode) ? $countryCode : '';
		}

	/**
	 * Return the known country name.
	 * @param string $countryCode Country code.
	 * @return string Country name.
	 **/
	private function countryName(string $countryCode): string
		{
		return self::COUNTRY_NAMES[$countryCode] ?? $countryCode;
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
	 * Normalize source date from delegated files.
	 * @param string $value Raw date.
	 * @return string|null SQL date or null.
	 **/
	private function normalizeSourceDate(string $value): ?string
		{
		if (!preg_match('/^\d{8}$/', $value))
			{
			return null;
			}

		return substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
		}
	}

?>

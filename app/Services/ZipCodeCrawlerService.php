<?php

namespace App\Services;

/**
 * Brazilian zip code crawler service with provider fallback.
 */
class ZipCodeCrawlerService
	{
	private const NOT_FOUND_FILE_NAME = 'zip_code_crawler_not_found.txt';

	private ViaCepService $viaCepService;
	private BrasilApiService $brasilApiService;
	private string $notFoundFilePath;
	private ?array $notFoundZipCodes = null;

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
		$delayMs = max(0, $delayMs);

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

		$totalAvailable = $endNumber - $startNumber + 1;
		$totalToProcess = min($totalAvailable, max(1, $maxResults));

		yield $this->event('start', [
			'start_zip_code' => $this->formatZipCodeNumber($startNumber),
			'end_zip_code' => $this->formatZipCodeNumber($endNumber),
			'total' => $totalToProcess,
			'delay_ms' => $delayMs,
			'provider_mode' => $this->normalizeProviderMode($providerMode),
		]);

		$processed = 0;
		$found = 0;
		$failed = 0;
		$skipped = 0;

		for ($index = 0; $index < $totalToProcess; $index++)
			{
			$currentNumber = $startNumber + $index;
			if ($currentNumber > $endNumber)
				{
				break;
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
				'result' => $result,
			]);

			$this->waitBetweenLookups($delayMs);
			}

		yield $this->event('done', [
			'processed' => $processed,
			'found' => $found,
			'failed' => $failed,
			'skipped' => $skipped,
			'total' => $totalToProcess,
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
		if (strlen($digits) !== 8 || $this->hasRememberedNotFoundZipCode($digits))
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
			$written = true;

			if (!isset($notFoundZipCodes[$digits]))
				{
				fseek($handle, 0, SEEK_END);
				$separator = ($contents !== '' && !in_array(substr($contents, -1), ["\n", "\r"], true)) ? PHP_EOL : '';
				$written = fwrite($handle, $separator . $digits . PHP_EOL) !== false;
				}

			if ($written)
				{
				$notFoundZipCodes[$digits] = true;
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
	 * Resolve the default not found zip code file path.
	 * @return string
	 **/
	private function defaultNotFoundFilePath(): string
		{
		$publicPath = defined('PUBLIC_PATH') ? (string) PUBLIC_PATH : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
		return rtrim($publicPath, '/\\') . DIRECTORY_SEPARATOR . self::NOT_FOUND_FILE_NAME;
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

		return [
			'success' => true,
			'status' => 200,
			'source' => $source,
			'message' => 'CEP localizado com sucesso.',
			'data' => $data,
		];
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

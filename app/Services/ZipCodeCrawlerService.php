<?php

namespace App\Services;

/**
 * Brazilian zip code crawler service with provider fallback.
 */
class ZipCodeCrawlerService
	{
	private ViaCepService $viaCepService;
	private BrasilApiService $brasilApiService;

	/**
	 * Build the crawler service.
	 */
	public function __construct()
		{
		$this->viaCepService = new ViaCepService();
		$this->brasilApiService = new BrasilApiService();
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

		$totalAvailable = $endNumber - $startNumber + 1;
		$totalToProcess = min($totalAvailable, max(1, $maxResults));

		yield $this->event('start', [
			'start_zip_code' => $this->formatZipCode((string) $startNumber),
			'end_zip_code' => $this->formatZipCode((string) $endNumber),
			'total' => $totalToProcess,
			'delay_ms' => max(0, $delayMs),
			'provider_mode' => $this->normalizeProviderMode($providerMode),
		]);

		$processed = 0;
		$found = 0;
		$failed = 0;

		for ($index = 0; $index < $totalToProcess; $index++)
			{
			$currentNumber = $startNumber + $index;
			if ($currentNumber > $endNumber)
				{
				break;
				}

			$zipCode = $this->formatZipCode((string) $currentNumber);
			$result = $this->lookupByMode($zipCode, $providerMode);

			$processed++;
			if (!empty($result['success']))
				{
				$found++;
				}
			else
				{
				$failed++;
				}

			yield $this->event('progress', [
				'position' => $processed,
				'total' => $totalToProcess,
				'found' => $found,
				'failed' => $failed,
				'zip_code' => $zipCode,
				'result' => $result,
			]);

			if ($delayMs > 0)
				{
				usleep($delayMs * 1000);
				}
			}

		yield $this->event('done', [
			'processed' => $processed,
			'found' => $found,
			'failed' => $failed,
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

		return [
			'success' => false,
			'status' => (int) ($brasilApiResult['status'] ?? $viaCepResult['status'] ?? 502),
			'source' => '',
			'error' => 'lookup_failed',
			'message' => $this->firstErrorMessage($viaCepResult, $brasilApiResult),
			'data' => [
				'zip_code' => $zipCode,
				'attempts' => [$viaCepAttempt, $brasilApiAttempt],
			],
		];
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

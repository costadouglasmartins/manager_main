<?php

namespace App\Services;

/**
 * BrasilAPI postal code lookup service.
 */
class BrasilApiService
	{
	private const BASE_URL = 'https://brasilapi.com.br/api/cep/v1/';
	private const TIMEOUT_SECONDS = 5;

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

	/*
	|--------------------------------------------------------------------------
	| Lookup
	|--------------------------------------------------------------------------
	*/
	public function lookup(string $zipCode): array
		{
		$digits = $this->onlyDigits($zipCode);

		if (strlen($digits) !== 8)
			{
			return $this->failure('invalid_zip_code', 'Informe um CEP válido com 8 dígitos.', 422);
			}

		$response = $this->fetch(self::BASE_URL . $digits);
		if (!$response['ok'])
			{
			if ((int) ($response['status'] ?? 0) === 404)
				{
				return $this->failure('zip_code_not_found', 'CEP não encontrado na BrasilAPI.', 404);
				}

			return $this->failure('lookup_failed', 'Não foi possível consultar o CEP na BrasilAPI agora.', 502);
			}

		$data = json_decode((string) $response['body'], true);
		if (!is_array($data))
			{
			return $this->failure('invalid_response', 'A resposta da BrasilAPI é inválida.', 502);
			}

		$normalized = $this->normalize($data);
		if ($normalized['zip_code'] === '')
			{
			return $this->failure('zip_code_not_found', 'CEP não encontrado na BrasilAPI.', 404);
			}

		return [
			'success' => true,
			'status'  => 200,
			'source'  => 'brasilapi',
			'data'    => $normalized,
		];
		}

	/*
	|--------------------------------------------------------------------------
	| Input Normalization
	|--------------------------------------------------------------------------
	*/
	private function onlyDigits(string $value): string
		{
		return preg_replace('/\D+/', '', $value) ?? '';
		}

	/*
	|--------------------------------------------------------------------------
	| HTTP Transport
	|--------------------------------------------------------------------------
	*/
	private function fetch(string $url): array
		{
		if (function_exists('curl_init'))
			{
			return $this->fetchWithCurl($url);
			}

		return $this->fetchWithStream($url);
		}

	/*
	|--------------------------------------------------------------------------
	| cURL Transport
	|--------------------------------------------------------------------------
	*/
	private function fetchWithCurl(string $url): array
		{
		$ch = curl_init($url);
		if ($ch === false)
			{
			return ['ok' => false, 'status' => 0, 'body' => null];
			}

		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SECONDS,
			CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
			CURLOPT_HTTPHEADER     => ['Accept: application/json'],
		]);

		$body = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_errno($ch);
		curl_close($ch);

		return [
			'ok'     => ($error === 0 && $status >= 200 && $status < 300 && is_string($body)),
			'status' => $status,
			'body'   => is_string($body) ? $body : null,
		];
		}

	/*
	|--------------------------------------------------------------------------
	| Stream Transport
	|--------------------------------------------------------------------------
	*/
	private function fetchWithStream(string $url): array
		{
		$context = stream_context_create([
			'http' => [
				'ignore_errors' => true,
				'method'        => 'GET',
				'timeout'       => self::TIMEOUT_SECONDS,
				'header'        => "Accept: application/json\r\n",
			],
		]);

		$body = @file_get_contents($url, false, $context);
		$status = $this->statusFromHeaders($http_response_header ?? []);

		return [
			'ok'     => ($status >= 200 && $status < 300 && is_string($body)),
			'status' => $status,
			'body'   => is_string($body) ? $body : null,
		];
		}

	/*
	|--------------------------------------------------------------------------
	| Response Status
	|--------------------------------------------------------------------------
	*/
	private function statusFromHeaders(array $headers): int
		{
		$statusLine = $headers[0] ?? '';
		if (preg_match('/\s(\d{3})\s/', $statusLine, $matches))
			{
			return (int) $matches[1];
			}

		return 0;
		}

	/*
	|--------------------------------------------------------------------------
	| Response Normalization
	|--------------------------------------------------------------------------
	*/
	private function normalize(array $data): array
		{
		$state = strtoupper((string) ($data['state'] ?? ''));

		return [
			'zip_code'     => $this->formatZipCode((string) ($data['cep'] ?? '')),
			'street'       => (string) ($data['street'] ?? ''),
			'complement'   => '',
			'neighborhood' => (string) ($data['neighborhood'] ?? ''),
			'city'         => (string) ($data['city'] ?? ''),
			'state'        => $state,
			'state_name'   => self::STATE_NAMES[$state] ?? $state,
			'region'       => $this->regionFromState($state),
			'ibge'         => (string) ($data['city_ibge_code'] ?? ''),
			'ddd'          => (string) ($data['ddd'] ?? ''),
			'siafi'        => '',
		];
		}

	/*
	|--------------------------------------------------------------------------
	| Zip Code Formatting
	|--------------------------------------------------------------------------
	*/
	private function formatZipCode(string $value): string
		{
		$digits = $this->onlyDigits($value);
		if (strlen($digits) !== 8)
			{
			return '';
			}

		return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digits) ?? $digits;
		}

	/*
	|--------------------------------------------------------------------------
	| Region Mapping
	|--------------------------------------------------------------------------
	*/
	private function regionFromState(string $state): string
		{
		$regions = [
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

		return $regions[$state] ?? '';
		}

	/*
	|--------------------------------------------------------------------------
	| Failure Payload
	|--------------------------------------------------------------------------
	*/
	private function failure(string $error, string $message, int $status): array
		{
		return [
			'success' => false,
			'status'  => $status,
			'source'  => 'brasilapi',
			'error'   => $error,
			'message' => $message,
		];
		}
	}

?>

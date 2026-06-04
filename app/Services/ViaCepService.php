<?php

namespace App\Services;

/**
 * ViaCEP postal code lookup service.
 */
class ViaCepService
	{
	private const BASE_URL = 'https://viacep.com.br/ws/';
	private const TIMEOUT_SECONDS = 5;

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

		$response = $this->fetch(self::BASE_URL . $digits . '/json/');
		if (!$response['ok'])
			{
			return $this->failure('lookup_failed', 'Não foi possível consultar o CEP no ViaCEP agora.', 502);
			}

		$data = json_decode((string) $response['body'], true);
		if (!is_array($data))
			{
			return $this->failure('invalid_response', 'A resposta do ViaCEP é inválida.', 502);
			}

		if (!empty($data['erro']))
			{
			return $this->failure('zip_code_not_found', 'CEP não encontrado no ViaCEP.', 404);
			}

		return [
			'success' => true,
			'status'  => 200,
			'source'  => 'viacep',
			'data'    => $this->normalize($data),
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
		$state = strtoupper((string) ($data['uf'] ?? ''));

		return [
			'zip_code'     => (string) ($data['cep'] ?? ''),
			'street'       => (string) ($data['logradouro'] ?? ''),
			'complement'   => (string) ($data['complemento'] ?? ''),
			'neighborhood' => (string) ($data['bairro'] ?? ''),
			'city'         => (string) ($data['localidade'] ?? ''),
			'state'        => $state,
			'state_name'   => (string) ($data['estado'] ?? $state),
			'region'       => (string) ($data['regiao'] ?? ''),
			'ibge'         => (string) ($data['ibge'] ?? ''),
			'ddd'          => (string) ($data['ddd'] ?? ''),
			'siafi'        => (string) ($data['siafi'] ?? ''),
		];
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
			'source'  => 'viacep',
			'error'   => $error,
			'message' => $message,
		];
		}
	}

?>

<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\EmailMxValidatorService;
use App\Services\ZipCodeSearchService;

class ToolController extends Controller
	{
	/*
	|--------------------------------------------------------------------------
	| Document Tool
	|--------------------------------------------------------------------------
	*/
	public function documents(): void
		{
		$this->requireAuth();

		$this->view('tools/documents', [
			'title' => 'Gerador e validador de documentos',
		]);
		}

	/*
	|--------------------------------------------------------------------------
	| E-mail Validation Tool
	|--------------------------------------------------------------------------
	*/
	public function email_validation(): void
		{
		$this->requireAuth();

		if ($_SERVER['REQUEST_METHOD'] === 'POST' || $this->wantsJson())
			{
			$validator = new EmailMxValidatorService();
			$result = $validator->validate($this->readEmailValidationInput());
			$status = !empty($result['input_valid']) ? 200 : 422;

			$this->json(['data' => $result], $status);
			}

		$this->view('tools/email_validation', [
			'title' => 'Validador de e-mail',
			]);
		}

	/*
	|--------------------------------------------------------------------------
	| CEP Search Page
	|--------------------------------------------------------------------------
	*/
	public function zipCodeSearch(): void
		{
		$this->requireAuth();

		$this->view('tools/zip_code_search', [
			'title' => 'Pesquisa de CEP',
		]);
		}

	/*
	|--------------------------------------------------------------------------
	| CEP Search Endpoint
	|--------------------------------------------------------------------------
	*/
	public function searchZipCode(): void
		{
		$this->requireAuth();

		$service = new ZipCodeSearchService();
		$result = $service->searchAndSave($this->readZipCodeSearchInput());
		$status = (int) ($result['status'] ?? 500);

		$this->json($result, $status);
		}

	/*
	|--------------------------------------------------------------------------
	| E-mail Validation Input Reader
	|--------------------------------------------------------------------------
	*/
	private function readEmailValidationInput(): string
		{
		if (isset($_POST['email']))
			{
			return trim((string) $_POST['email']);
			}

		if (isset($_GET['email']))
			{
			return trim((string) $_GET['email']);
			}

		$rawBody = (string) file_get_contents('php://input');
		if ($rawBody === '')
			{
			return '';
			}

		$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
		if (strpos($contentType, 'application/json') !== false)
			{
			$payload = json_decode($rawBody, true);
			if (is_array($payload) && isset($payload['email']))
				{
				return trim((string) $payload['email']);
				}
			}

		parse_str($rawBody, $parsedBody);
		if (is_array($parsedBody) && isset($parsedBody['email']))
			{
			return trim((string) $parsedBody['email']);
			}

		return '';
		}

	/*
	|--------------------------------------------------------------------------
	| CEP Search Input Reader
	|--------------------------------------------------------------------------
	*/
	private function readZipCodeSearchInput(): string
		{
		if (isset($_POST['zip_code']))
			{
			return trim((string) $_POST['zip_code']);
			}

		if (isset($_POST['cep']))
			{
			return trim((string) $_POST['cep']);
			}

		if (isset($_GET['zip_code']))
			{
			return trim((string) $_GET['zip_code']);
			}

		if (isset($_GET['cep']))
			{
			return trim((string) $_GET['cep']);
			}

		$rawBody = (string) file_get_contents('php://input');
		if ($rawBody === '')
			{
			return '';
			}

		$contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
		if (strpos($contentType, 'application/json') !== false)
			{
			$payload = json_decode($rawBody, true);
			if (is_array($payload))
				{
				if (isset($payload['zip_code']))
					{
					return trim((string) $payload['zip_code']);
					}

				if (isset($payload['cep']))
					{
					return trim((string) $payload['cep']);
					}
				}
			}

		parse_str($rawBody, $parsedBody);
		if (is_array($parsedBody))
			{
			if (isset($parsedBody['zip_code']))
				{
				return trim((string) $parsedBody['zip_code']);
				}

			if (isset($parsedBody['cep']))
				{
				return trim((string) $parsedBody['cep']);
				}
			}

		return '';
		}
	}

?>

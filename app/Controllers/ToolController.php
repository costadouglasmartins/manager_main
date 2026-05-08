<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\EmailMxValidatorService;

class ToolController extends Controller
	{
	/**
	 * Render the CPF and CNPJ generator and validator tool.
	 */
	public function documents(): void
		{
		$this->requireAuth();

		$this->view('tools/documents', [
			'title' => 'Gerador e validador de documentos',
		]);
		}

	/**
	 * Render the e-mail validator tool or return MX validation as JSON.
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

	/**
	 * Read the e-mail address sent by the validation form.
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
	}

?>
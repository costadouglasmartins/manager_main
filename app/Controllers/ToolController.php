<?php

namespace App\Controllers;

use App\Core\Controller;

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
	}

?>

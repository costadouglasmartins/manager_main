<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Email;
use App\Services\ProtectedEditAuthorizationService;

class EmailController extends Controller
	{

	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('email.index');

		$email = new Email();

		$filters = [
			'id'          => isset($_GET['id'])          ? trim($_GET['id'])          : '',
			'email'       => isset($_GET['email'])       ? trim($_GET['email'])       : '',
			'person_id'   => isset($_GET['person_id'])   ? trim($_GET['person_id'])   : '',
			'person_name' => isset($_GET['person_name']) ? trim($_GET['person_name']) : '',
		];

		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest(Email::sortableColumns(), 'id', 'desc');

		$emails = $email->search($filters, $perPage, $offset, $sort);
		$total = $email->count($filters);
		$totalPages = (int) ceil($total / $perPage);
		if ($totalPages < 1)
			{
			$totalPages = 1;
			}

		if ($this->wantsJson())
			{
			$this->json([
				'filters'    => $filters,
				'page'       => $page,
				'perPage'    => $perPage,
				'total'      => $total,
				'totalPages' => $totalPages,
				'sort'       => $sort,
				'data'       => $emails,
			]);
			}

		$this->view('emails/index', [
			'emails'     => $emails,
			'title'      => 'Lista de e-mails',
			'filters'    => $filters,
			'page'       => $page,
			'totalPages' => $totalPages,
			'total'      => $total,
			'sort'       => $sort,
		]);
		}

	/**
	 * Create method
	 */
	public function create()
		{
		$this->requirePermission('email.create');
		$this->view('emails/create', ['title' => 'Novo e-mail']);
		}

	/**
	 * Store method
	 */
	public function store()
		{
		$this->requirePermission('email.store');
		$email = new Email();

		try
			{
			$email->create($_POST);
			setFlashMessage('success', 'E-mail cadastrado com sucesso.');
			$this->redirect('email');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível cadastrar o e-mail. Verifique os dados e tente novamente.');
			$this->redirect('email/create');
			}
		}

	/**
	 * Edit method
	 * @param Integer $id Email id
	 */
	public function edit($id)
		{
		$this->requirePermission('email.edit');
		$emailId = (int) $id;
		$editAuthorization = new ProtectedEditAuthorizationService();
		if (!$editAuthorization->isAuthorized('email', $emailId))
			{
			setFlashMessage('danger', 'Informe a senha do usuário ID 1 para editar o e-mail desta pessoa protegida.');
			$this->redirect('email');
			}

		$email = new Email();
		$data = $email->find($emailId);
		$this->view('emails/edit', ['title' => 'Editar e-mail', 'email' => $data]);
		}

	/**
	 * Update method
	 * @param Integer $id Email id
	 */
	public function update($id)
		{
		$this->requirePermission('email.update');
		$emailId = (int) $id;
		$editAuthorization = new ProtectedEditAuthorizationService();
		if (!$editAuthorization->isAuthorized('email', $emailId))
			{
			setFlashMessage('danger', 'A autorização para editar este e-mail protegido é inválida ou expirou.');
			$this->redirect('email');
			}

		$email = new Email();

		try
			{
			$email->update($emailId, $_POST);
			$editAuthorization->revoke('email', $emailId);
			setFlashMessage('success', 'E-mail atualizado com sucesso.');
			$this->redirect('email');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível atualizar o e-mail. Verifique os dados e tente novamente.');
			$this->redirect('email/edit/' . $emailId);
			}
		}

	/**
	 * Verifies user 1's password before opening a protected email edit screen.
	 * @param int|string $id Email ID.
	 * @return void
	 **/
	public function authorizeProtectedEdit($id): void
		{
		$this->requirePermission('email.edit');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->redirect('email');
			}

		$emailId = (int) $id;
		$editAuthorization = new ProtectedEditAuthorizationService();
		if (!$editAuthorization->isProtected('email', $emailId))
			{
			$this->redirect('email/edit/' . $emailId);
			}

		$password = (string) ($_POST['authorization_password'] ?? '');
		if ($editAuthorization->authorize('email', $emailId, $password))
			{
			unset($password);
			$this->redirect('email/edit/' . $emailId);
			}

		unset($password);
		setFlashMessage('danger', 'Senha inválida ou limite de tentativas atingido.');
		$this->redirect('email');
		}

	/**
	 * Informations method
	 * @param Integer $id Email id
	 */
	public function informations($id)
		{
		$this->requirePermission('email.informations');
		$email = new Email();
		$data = $email->findAllByEmailId($id);
		$this->view('emails/informations', ['title' => 'Informações sobre o e-mail', 'email' => $data['email']]);
		}

	/**
	 * Delete method
	 * @param Integer $id Record id
	 */
	public function delete($id)
		{
		$this->requirePermission('email.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$email = new Email();
			try {
				$email->delete($id);
				setFlashMessage('success', 'E-mail excluído com sucesso.');
				$this->redirect('email');
			} catch (\Exception $e) {
				setFlashMessage('danger', 'Não foi possível excluir o e-mail.');
				$this->redirect('email');
			}
		}
		}
	}

?>

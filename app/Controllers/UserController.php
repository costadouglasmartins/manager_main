<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\RememberLogin;
use App\Models\User;

class UserController extends Controller
	{

	/**
	 * Index method
	 */
	public function index()
		{
		$this->requirePermission('user.index');

		$user = new User();

		// Filters from query string
		$filters = [
			'id'				=> isset($_GET['id'])				? trim($_GET['id'])				: '',
			'username'	=> isset($_GET['username'])	? trim($_GET['username'])			: '',
			'type'	    => isset($_GET['type'])     ? trim($_GET['type']) : '',
			'active'		=> isset($_GET['active'])		? trim($_GET['active'])		: '',
		];

		// Current page (?page=2)
		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		// Records per page
		$perPage = 10;
		$offset = ($page - 1) * $perPage;
		$sort = sortRequest(User::sortableColumns(), 'id', 'desc');

		// Paginated search with filters
		$users = $user->search($filters, $perPage, $offset, $sort);

		// Total records for pagination with the same filters
		$total = $user->count($filters);
		$totalPages = (int) ceil($total / $perPage);
		if ($totalPages < 1)
			{
			$totalPages = 1;
			}

		$this->view('users/index', [
			'users'		    => $users,
			'title'				=> 'Lista de usuários',
			'filters'			=> $filters,
			'page'				=> $page,
			'totalPages'	=> $totalPages,
			'total'				=> $total,
			'sort'				=> $sort,
		]);
		}

	/**
	 * Edit method
	 * @param Integer $id User id
	 */
	public function edit($id)
		{
		$this->requirePermission('user.edit');
		$user = new User();
		$data = $user->find($id);

		// Load all tenants for the dropdown (all statuses)
		$tenantModel = new \App\Models\Tenant();
		$tenants = $tenantModel->getAll();

		$this->view('users/edit', [
			'user'    => $data,
			'tenants' => $tenants,
      'title'   => 'Editar usuário'
			]);
		}

	/**
	 * Update method
	 * @param Integer $id User id
	 */
	public function update($id)
		{
		$this->requirePermission('user.update');
		$user = new User();

		try
			{
			$user->update($id, $_POST);
			setFlashMessage('success', 'Usuário atualizado com sucesso.');
			$this->redirect('user');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível atualizar o usuário. Verifique os dados e tente novamente.');
			$this->redirect('user/edit/' . $id);
			}
		}

	/**
	 * Login method
	 */
	public function login()
		{
		// Redirect to home when user is already logged in
		if (!empty($_SESSION['manager_main']['user']))
			{
			$this->redirect('/');
			exit;
			}

		// Process login attempt on POST requests
		if ($_SERVER['REQUEST_METHOD'] === 'POST')
			{
			$email = isset($_POST['email']) ? trim($_POST['email']) : '';
			$password = isset($_POST['password']) ? $_POST['password'] : '';

			$userModel = new \App\Models\User();
			$user = $userModel->authenticate($email, $password);

			if ($user)
				{
				\App\Core\Authorization::clearCache();
				RememberLogin::storeUserInSession($user);

				if (!empty($_POST['remember_me']))
					{
					RememberLogin::rememberUser($user);
					}
				else
					{
					RememberLogin::forgetCurrentDevice();
					}

				$this->redirect('/');
				exit;
				}
			else
				{
				$error = 'Email ou senha inválidos.';
				$this->view('users/login', ['error' => $error, 'title' => 'Login']);
				return;
				}
			}

		// Render login form on GET requests
		$this->view('users/login', ['title' => 'Login']);
		}

	/**
	 * Logout method
	 */
	public function logout()
		{
		\App\Core\Authorization::clearCache();
		RememberLogin::forgetCurrentDevice();
		// Destroy session and redirect
		$_SESSION = [];
		if (ini_get("session.use_cookies"))
			{
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000,
				$params["path"], $params["domain"],
				$params["secure"], $params["httponly"]
			);
			}
		session_destroy();
		$this->redirect('user/login');
		exit;
		}

  /**
   * Informations method
   * @param Integer $id User id
   */
  public function informations($id)
		{
		$this->requirePermission('user.informations');
		$user = new User();
		$data = $user->find($id);
		$this->view('users/informations', ['title' => 'Informações sobre o(a) usuário(a)', 'user' => $data]);
		}

	/**
	 * Delete method
	 * @param Integer $id User id
	 */
	public function delete($id)
		{
		$this->requirePermission('user.delete');

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$user = new User();
			try {
				$user->delete($id);
				setFlashMessage('success', 'Usuário excluído com sucesso.');
				$this->redirect('user');
			} catch (\Exception $e) {
				setFlashMessage('danger', 'Não foi possível excluir o usuário.');
				$this->redirect('user');
			}
		}
		}
	}

?>

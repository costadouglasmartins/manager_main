<?php

namespace App\Controllers;

use App\Core\Controller;
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

		// Filtros vindos da query string
		$filters = [
			'id'				=> isset($_GET['id'])				? trim($_GET['id'])				: '',
			'username'	=> isset($_GET['username'])	? trim($_GET['username'])			: '',
			'email'		  => isset($_GET['email'])	  ? trim($_GET['email'])	: '',
			'type'	    => isset($_GET['type'])     ? trim($_GET['type']) : '',
			'active'		=> isset($_GET['active'])		? trim($_GET['active'])		: '',
		];

		// Página atual (?page=2)
		$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
		if ($page < 1)
			{
			$page = 1;
			}

		// Registros por página
		$perPage = 10;
		$offset = ($page - 1) * $perPage;

		// Busca paginada com filtros
		$users = $user->search($filters, $perPage, $offset);

		// Total para paginação (com os mesmos filtros)
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
		$this->view('users/edit', ['user' => $data]);
		}

	/**
	 * Update method
	 * @param Integer $id User id
	 */
	public function update($id)
		{
		$this->requirePermission('user.update');
		$user = new User();
		$user->update($id, $_POST);
		$this->redirect('user');
		}

	/**
	 * Login method
	 */
	public function login()
		{
		// Se já logado, redireciona para home
		if (!empty($_SESSION['manager_main']['user']))
			{
			$this->redirect('/');
			exit;
			}

		// Se for POST, processa tentativa de login
		if ($_SERVER['REQUEST_METHOD'] === 'POST')
			{
			$email = isset($_POST['email']) ? trim($_POST['email']) : '';
			$password = isset($_POST['password']) ? $_POST['password'] : '';

			$userModel = new \App\Models\User();
			$user = $userModel->authenticate($email, $password);

			if ($user)
				{
				\App\Core\Authorization::clearCache();
				$_SESSION['manager_main']['user'] = [
					'id'				=> $user['id'],
					'username'	=> $user['username'],
					'email'			=> $user['email'],
					'type'			=> $user['type'],
					'name'			=> $user['name']
				];
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

		// GET: mostra formulário de login
		$this->view('users/login', ['title' => 'Login']);
		}

	/**
	 * Logout method
	 */
	public function logout()
		{
		\App\Core\Authorization::clearCache();
		// Destroi sessão e redireciona
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
		$this->view('users/informations', ['user' => $data]);
		}
	}

?>
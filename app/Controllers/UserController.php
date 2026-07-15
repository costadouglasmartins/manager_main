<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\RememberLogin;
use App\Models\User;
use App\Services\ProtectedEditAuthorizationService;
use App\Services\UserDeletionService;

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
		$userId = (int) $id;
		$editAuthorization = new ProtectedEditAuthorizationService();
		if (!$editAuthorization->isAuthorized('user', $userId))
			{
			setFlashMessage('danger', 'Informe a senha do usuário ID 1 para editar este usuário protegido.');
			$this->redirect('user');
			}

		$user = new User();
		$data = $user->find($userId);

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
		$userId = (int) $id;
		$editAuthorization = new ProtectedEditAuthorizationService();
		if (!$editAuthorization->isAuthorized('user', $userId))
			{
			setFlashMessage('danger', 'A autorização para editar este usuário protegido é inválida ou expirou.');
			$this->redirect('user');
			}

		$user = new User();

		try
			{
			$user->update($userId, $_POST);
			$editAuthorization->revoke('user', $userId);
			setFlashMessage('success', 'Usuário atualizado com sucesso.');
			$this->redirect('user');
			}
		catch (\Throwable $e)
			{
			setFlashMessage('danger', 'Não foi possível atualizar o usuário. Verifique os dados e tente novamente.');
			$this->redirect('user/edit/' . $userId);
			}
		}

	/**
	 * Verifies user 1's password before opening a protected user edit screen.
	 * @param int|string $id Protected user ID.
	 * @return void
	 **/
	public function authorizeProtectedEdit($id): void
		{
		$this->requirePermission('user.edit');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->redirect('user');
			}

		$userId = (int) $id;
		$editAuthorization = new ProtectedEditAuthorizationService();
		if (!$editAuthorization->isProtected('user', $userId))
			{
			$this->redirect('user/edit/' . $userId);
			}

		$password = (string) ($_POST['authorization_password'] ?? '');
		if ($editAuthorization->authorize('user', $userId, $password))
			{
			unset($password);
			$this->redirect('user/edit/' . $userId);
			}

		unset($password);
		setFlashMessage('danger', 'Senha inválida ou limite de tentativas atingido.');
		$this->redirect('user');
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
	 * Returns the row-by-row deletion manifest used by the confirmation modal.
	 *
	 * @param int|string $id User ID.
	 * @return void
	 */
	public function deletionImpact($id): void
		{
		$this->requirePermission('user.delete');

		if ($_SERVER['REQUEST_METHOD'] !== 'GET')
			{
			$this->json(['error' => 'method_not_allowed', 'message' => 'Método não permitido.'], 405, ['Allow' => 'GET']);
			}

		$userId = (int) $id;
		if ($userId <= 0)
			{
			$this->json(['error' => 'invalid_user', 'message' => 'Usuário inválido.'], 422);
			}
		if (in_array($userId, User::PROTECTED_USER_IDS, true))
			{
			$this->json(['error' => 'protected_user', 'message' => 'Este usuário é protegido e não pode ser excluído.'], 403);
			}

		try
			{
			$this->json(
				(new UserDeletionService())->impact($userId),
				200,
				['Cache-Control' => 'no-store, no-cache, must-revalidate']
			);
			}
		catch (\RuntimeException $e)
			{
			$status = $e->getMessage() === 'Usuário não encontrado.' ? 404 : 500;
			$this->json([
				'error' => $status === 404 ? 'user_not_found' : 'impact_unavailable',
				'message' => $status === 404 ? 'Usuário não encontrado.' : 'Não foi possível consultar os registros associados ao usuário.',
			], $status);
			}
		catch (\Throwable $e)
			{
			error_log('UserController::deletionImpact error: ' . $e->getMessage());
			$this->json(['error' => 'impact_unavailable', 'message' => 'Não foi possível consultar os registros associados ao usuário.'], 500);
			}
		}

	/**
	 * Deletes the user and every record confirmed in the deletion manifest.
	 * @param Integer $id User id
	 */
	public function delete($id)
		{
		$this->requirePermission('user.delete');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST')
			{
			$this->redirect('user');
			}

		$userId = (int) $id;
		if ($userId <= 0)
			{
			setFlashMessage('danger', 'Usuário inválido.');
			$this->redirect('user');
			}
		if (in_array($userId, User::PROTECTED_USER_IDS, true))
			{
			setFlashMessage('danger', 'Os usuários de ID 1 e 2 são protegidos e não podem ser excluídos.');
			$this->redirect('user');
			}
		if ((string) ($_POST['confirm_full_deletion'] ?? '') !== '1')
			{
			setFlashMessage('danger', 'A exclusão integral do usuário não foi confirmada.');
			$this->redirect('user');
			}

		try
			{
			(new UserDeletionService())->delete($userId);
			setFlashMessage('success', 'Usuário, pessoa e registros associados excluídos com sucesso. Nenhuma base de dados foi removida.');
			}
		catch (\Throwable $e)
			{
			error_log('UserController::delete error for user ' . $userId . ': ' . $e->getMessage());
			setFlashMessage('danger', 'Não foi possível excluir o usuário e seus registros associados.');
			}

		$this->redirect('user');
		}
	}

?>

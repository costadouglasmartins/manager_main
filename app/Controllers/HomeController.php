<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Address;
use App\Models\City;
use App\Models\Email;
use App\Models\Person;
use App\Models\PhoneNumber;
use App\Models\Tenant;
use App\Models\User;

class HomeController extends Controller
	{

	/**
	 * Render the home dashboard.
	 */
	public function index()
		{
		$this->requireAuth();
		$this->view('home/index', [
			'title'     => 'Dashboard',
			'dashboard' => $this->buildDashboardData(),
		]);
		}

	/**
	 * Build the data used by the home dashboard.
	 *
	 * @return array<string, mixed>
	 */
	private function buildDashboardData(): array
		{
		$personModel = new Person();
		$userModel = new User();
		$tenantModel = new Tenant();
		$emailModel = new Email();
		$phoneModel = new PhoneNumber();
		$addressModel = new Address();
		$cityModel = new City();

		$peopleTotal = $personModel->count();
		$peopleActive = $personModel->count(['active' => '1']);
		$peopleInactive = $personModel->count(['active' => '0']);
		$peopleIndividual = $personModel->count(['type' => 'individual']);
		$peopleCompany = $personModel->count(['type' => 'company']);

		$usersTotal = $userModel->count();
		$usersActive = $userModel->count(['active' => '1']);
		$usersAdmin = $userModel->count(['type' => 'admin']);
		$usersRoot = $userModel->count(['type' => 'root']);

		$tenantsTotal = $tenantModel->count();
		$tenantsActive = $tenantModel->count(['status' => 'active']);
		$tenantsPending = $tenantModel->count(['status' => 'pending']);
		$tenantsSuspended = $tenantModel->count(['status' => 'suspended']);

		$emailsTotal = $emailModel->count();
		$phonesTotal = $phoneModel->count();
		$addressesTotal = $addressModel->count();
		$citiesTotal = $cityModel->count();

		return [
			'people' => [
				'total'      => $peopleTotal,
				'active'     => $peopleActive,
				'inactive'   => $peopleInactive,
				'individual' => $peopleIndividual,
				'company'    => $peopleCompany,
			],
			'users' => [
				'total'  => $usersTotal,
				'active' => $usersActive,
				'admin'  => $usersAdmin,
				'root'   => $usersRoot,
			],
			'tenants' => [
				'total'     => $tenantsTotal,
				'active'    => $tenantsActive,
				'pending'   => $tenantsPending,
				'suspended' => $tenantsSuspended,
			],
			'contacts' => [
				'emails'    => $emailsTotal,
				'phones'    => $phonesTotal,
				'addresses' => $addressesTotal,
			],
			'locations' => [
				'cities' => $citiesTotal,
			],
			'recentPeople'  => $personModel->search([], 5, 0),
			'recentTenants' => $tenantModel->search([], 5, 0),
		];
		}
	}

?>

<?php

namespace App\Controllers;

use App\Core\Controller;

class HomeController extends Controller
	{

	/**
	 * Render the home dashboard.
	 */
	public function index()
		{
		//$this->requireAuth();
		$this->view('home/index', ['title' => 'Home']);
		}
	}

?>

<?php

namespace App\Controllers;

use App\Core\Controller;

class HomeController extends Controller
	{

	public function index()
		{
		$this->requireAuth();
		$this->view('home/index', ['title' => 'Início']);
		}
	}

?>
<?php
namespace App\Core;

use App\Core\Database;

class Model
  {
	/**
	 * Protected db
	 * @var $db
	 */
  protected $db;

	/**
	 * Construct method
	 */
  public function __construct()
    {
    $this->db = Database::getInstance();
    }
  }
?>
<?php
namespace App\Core;

use App\Core\Database;

class Model
  {
	use AuditsChanges;

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

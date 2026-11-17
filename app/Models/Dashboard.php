<?php

namespace App\Models;

use App\Core\Model;
use App\Services\UserPresenceService;
use DateTimeImmutable;
use PDO;

/**
 * Read-only aggregate queries used by the administrative dashboard.
 */
class Dashboard extends Model
	{
	/**
	 * Counts distinct active tenants with an active user marked as connected.
	 * Away and busy users remain logged in; offline users are excluded.
	 * @return int Number of tenants with a recorded connected presence.
	 **/
	public function getLoggedInTenantCount(): int
		{
		// Count the selected tenant once, regardless of how many users are connected.
		$stmt = $this->db->prepare("SELECT COUNT(DISTINCT Tenant.id) AS total
		                            FROM tenants AS Tenant
		                            INNER JOIN user_presences AS UserPresence ON UserPresence.tenant_id = Tenant.id
		                            INNER JOIN users AS User ON User.id = UserPresence.user_id
		                            WHERE Tenant.status = :tenant_status
		                              AND User.active = :user_active
		                              AND User.type IN (:admin_type, :user_type)
		                              AND UserPresence.status IN (:online_status, :away_status, :busy_status)");
		$stmt->execute([
			'tenant_status' => 'active',
			'user_active' => 1,
			'admin_type' => 'admin',
			'user_type' => 'user',
			'online_status' => UserPresenceService::STATUS_ONLINE,
			'away_status' => UserPresenceService::STATUS_AWAY,
			'busy_status' => UserPresenceService::STATUS_BUSY,
		]);

		return (int) $stmt->fetchColumn();
		}

	/**
	 * Returns monthly registration totals for the main administrative entities.
	 *
	 * Empty months are kept in the result so the chart always has a continuous
	 * timeline.
	 *
	 * @return array<int, array<string, int|string>>
	 */
	public function getMonthlyRegistrations(int $months = 6): array
		{
		$months = max(2, min(12, $months));
		$firstMonth = (new DateTimeImmutable('first day of this month'))->modify('-' . ($months - 1) . ' months');
		$monthNames = [
			1 => 'Jan',
			2 => 'Fev',
			3 => 'Mar',
			4 => 'Abr',
			5 => 'Mai',
			6 => 'Jun',
			7 => 'Jul',
			8 => 'Ago',
			9 => 'Set',
			10 => 'Out',
			11 => 'Nov',
			12 => 'Dez',
		];

		$series = [];
		for ($index = 0; $index < $months; $index++)
			{
			$month = $firstMonth->modify('+' . $index . ' months');
			$period = $month->format('Y-m');
			$series[$period] = [
				'period' => $period,
				'label' => $monthNames[(int) $month->format('n')],
				'year' => (int) $month->format('Y'),
				'people' => 0,
				'users' => 0,
				'tenants' => 0,
			];
			}

		$tables = [
			'people' => 'people',
			'users' => 'users',
			'tenants' => 'tenants',
		];

		foreach ($tables as $key => $table)
			{
			$sql = "SELECT DATE_FORMAT(created, '%Y-%m') AS period,
			               COUNT(*) AS total
			        FROM {$table}
			        WHERE created >= :start_date
			        GROUP BY DATE_FORMAT(created, '%Y-%m')
			        ORDER BY period ASC";
			$stmt = $this->db->prepare($sql);
			$stmt->execute(['start_date' => $firstMonth->format('Y-m-01 00:00:00')]);

			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
				{
				$period = (string) ($row['period'] ?? '');
				if (isset($series[$period]))
					{
					$series[$period][$key] = (int) ($row['total'] ?? 0);
					}
				}
			}

		return array_values($series);
		}
	}


<?php

namespace App\Models;

use App\Core\Model;
use DateTimeImmutable;
use PDO;

/**
 * Read-only aggregate queries used by the administrative dashboard.
 */
class Dashboard extends Model
	{
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


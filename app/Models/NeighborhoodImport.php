<?php

namespace App\Models;

use App\Core\Model;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class NeighborhoodImport extends Model
	{
	private const ALL_STATES_FILTER = 'TODOS';

	/**
	 * Analyze an XLSX file and classify rows before import.
	 *
	 * @param string $filePath Uploaded XLSX temporary path.
	 * @return array<string, mixed>
	 */
	public function analyzeXlsx(string $filePath, string $selectedStateAcronym): array
		{
		$selectedStateAcronym = strtoupper(trim($selectedStateAcronym));
		if ($selectedStateAcronym === '')
			{
			throw new RuntimeException('Selecione um estado para gerar a prévia da importação.');
			}
		$importAllStates = $selectedStateAcronym === self::ALL_STATES_FILTER;

		if (!is_file($filePath) || !is_readable($filePath))
			{
			throw new RuntimeException('O arquivo enviado não pôde ser lido.');
			}

		try
			{
			$spreadsheet = IOFactory::load($filePath);
			}
		catch (Throwable $exception)
			{
			throw new RuntimeException('Não foi possível abrir o arquivo XLSX informado.');
			}

		$sheet = $spreadsheet->getActiveSheet();
		$rows = $sheet->toArray(null, true, true, false);

		if (empty($rows))
			{
			throw new RuntimeException('O arquivo XLSX está vazio.');
			}

		$headerIndexes = $this->resolveHeaderIndexes((array) $rows[0]);
		$stateIndex = $this->loadStateIndex();
		$cityIndex = $this->loadCityIndex();
		$existingNeighborhoods = $this->loadNeighborhoodIndex();

		if (!$importAllStates && !isset($stateIndex[$selectedStateAcronym]))
			{
			throw new RuntimeException('O estado selecionado não foi encontrado em `states.acronym`.');
			}

		$summary = [
			'total_rows' => max(0, count($rows) - 1),
			'selected_state_rows' => 0,
			'ignored_by_state_filter' => 0,
			'ready_to_import' => 0,
			'not_imported' => 0,
			'cities_to_create' => 0,
			'neighborhoods_to_create' => 0,
			'already_exists' => 0,
			'duplicate_in_file' => 0,
			'state_not_found' => 0,
			'city_ambiguous' => 0,
			'incomplete_rows' => 0,
			'empty_rows' => 0,
		];

		$rowsToImport = [];
		$rowsNotImported = [];
		$pendingKeys = [];
		$pendingCityKeys = [];

		foreach ($rows as $index => $row)
			{
			if ($index === 0)
				{
				continue;
				}

			$lineNumber = $index + 1;
			$stateAcronym = strtoupper($this->cleanCellValue((string) ($row[$headerIndexes['sigla_uf']] ?? '')));
			$cityName = $this->cleanCellValue((string) ($row[$headerIndexes['nm_mun']] ?? ''));
			$neighborhoodName = $this->cleanCellValue((string) ($row[$headerIndexes['nm_bairro']] ?? ''));

			if ($stateAcronym === '' && $cityName === '' && $neighborhoodName === '')
				{
				$summary['empty_rows']++;
				$summary['not_imported']++;
				$rowsNotImported[] = $this->buildIssue($lineNumber, $stateAcronym, $cityName, $neighborhoodName, 'Linha vazia.');
				continue;
				}

			if (!$importAllStates && $stateAcronym !== $selectedStateAcronym)
				{
				$summary['ignored_by_state_filter']++;
				continue;
				}

			$summary['selected_state_rows']++;

			if ($stateAcronym === '' || $cityName === '' || $neighborhoodName === '')
				{
				$summary['incomplete_rows']++;
				$summary['not_imported']++;
				$rowsNotImported[] = $this->buildIssue($lineNumber, $stateAcronym, $cityName, $neighborhoodName, 'Linha incompleta: informe UF, município e bairro.');
				continue;
				}

			if (!isset($stateIndex[$stateAcronym]))
				{
				$summary['state_not_found']++;
				$summary['not_imported']++;
				$rowsNotImported[] = $this->buildIssue($lineNumber, $stateAcronym, $cityName, $neighborhoodName, 'UF não encontrada em `states.acronym`.');
				continue;
				}

			$state = $stateIndex[$stateAcronym];
			$cityKey = $this->buildCityKey((int) $state['id'], $cityName);
			$cityCandidates = $cityIndex[$cityKey] ?? [];

			if (count($cityCandidates) > 1)
				{
				$summary['city_ambiguous']++;
				$summary['not_imported']++;
				$rowsNotImported[] = $this->buildIssue($lineNumber, $stateAcronym, $cityName, $neighborhoodName, 'Município ambíguo: existe mais de um cadastro com mesmo nome e UF.');
				continue;
				}

			$cityExists = count($cityCandidates) === 1;
			$cityId = $cityExists ? (int) ($cityCandidates[0]['id'] ?? 0) : 0;
			$neighborhoodKey = $cityExists
				? $cityId . '|' . $this->normalizeLookupKey($neighborhoodName)
				: 'new|' . $cityKey . '|' . $this->normalizeLookupKey($neighborhoodName);

			if ($cityExists && isset($existingNeighborhoods[$neighborhoodKey]))
				{
				$summary['already_exists']++;
				$summary['not_imported']++;
				$rowsNotImported[] = $this->buildIssue($lineNumber, $stateAcronym, $cityName, $neighborhoodName, 'Bairro já existe para este município.');
				continue;
				}

			if (isset($pendingKeys[$neighborhoodKey]))
				{
				$summary['duplicate_in_file']++;
				$summary['not_imported']++;
				$rowsNotImported[] = $this->buildIssue($lineNumber, $stateAcronym, $cityName, $neighborhoodName, 'Bairro duplicado no próprio arquivo.');
				continue;
				}

			$pendingKeys[$neighborhoodKey] = true;
			if (!$cityExists && !isset($pendingCityKeys[$cityKey]))
				{
				$pendingCityKeys[$cityKey] = true;
				$summary['cities_to_create']++;
				}

			$summary['neighborhoods_to_create']++;
			$rowsToImport[] = [
				'line' => $lineNumber,
				'state_id' => (int) $state['id'],
				'state_acronym' => (string) $state['acronym'],
				'city_id' => $cityId,
				'city_name' => $cityName,
				'city_exists' => $cityExists,
				'neighborhood_name' => $neighborhoodName,
				'will_create_city' => !$cityExists,
			];
			}

		$summary['ready_to_import'] = count($rowsToImport);

		return [
			'success' => true,
			'message' => $summary['ready_to_import'] > 0
				? 'Prévia gerada. Revise os registros antes de confirmar a importação.'
				: 'Prévia gerada, mas não há bairros aptos para importação.',
			'summary' => $summary,
			'rows_to_import' => $rowsToImport,
			'rows_not_imported' => $rowsNotImported,
			'selected_state_acronym' => $selectedStateAcronym,
		];
		}

	/**
	 * Stream the import process with live progress events.
	 *
	 * @param array<int, array<string, mixed>> $rows
	 * @return \Generator<int, array<string, mixed>, mixed, void>
	 */
	public function streamImport(array $rows): \Generator
		{
		$total = count($rows);
		$stats = [
			'processed' => 0,
			'imported' => 0,
			'cities_created' => 0,
			'neighborhoods_created' => 0,
			'failed' => 0,
		];

		yield $this->event('start', [
			'total' => $total,
			'processed' => 0,
			'imported' => 0,
			'cities_created' => 0,
			'neighborhoods_created' => 0,
			'failed' => 0,
		]);

		if ($total === 0)
			{
			yield $this->event('done', $stats + ['total' => 0]);
			return;
			}

		$cityIdCache = [];

		foreach ($rows as $row)
			{
			$stats['processed']++;

			try
				{
				$cityResult = $this->ensureCityForRow($row, $cityIdCache);
				$neighborhoodResult = $this->createNeighborhoodIfMissing((string) $row['neighborhood_name'], (int) $cityResult['city_id']);

				$stats['imported']++;
				if (!empty($neighborhoodResult['created']))
					{
					$stats['neighborhoods_created']++;
					}
				if (!empty($cityResult['created']))
					{
					$stats['cities_created']++;
					}

				yield $this->event('progress', $stats + [
					'total' => $total,
					'line' => (int) ($row['line'] ?? 0),
					'state_acronym' => (string) ($row['state_acronym'] ?? ''),
					'city_name' => (string) ($row['city_name'] ?? ''),
					'neighborhood_name' => (string) ($row['neighborhood_name'] ?? ''),
					'city_created' => !empty($cityResult['created']),
					'city_id' => (int) $cityResult['city_id'],
					'neighborhood_id' => (int) $neighborhoodResult['neighborhood_id'],
					'message' => !empty($neighborhoodResult['created'])
						? (!empty($cityResult['created']) ? 'Cidade criada e bairro importado com sucesso.' : 'Bairro importado com sucesso.')
						: 'Bairro já existia e foi reaproveitado.',
				]);
				}
			catch (Throwable $exception)
				{
				$stats['failed']++;

				yield $this->event('progress', $stats + [
					'total' => $total,
					'line' => (int) ($row['line'] ?? 0),
					'state_acronym' => (string) ($row['state_acronym'] ?? ''),
					'city_name' => (string) ($row['city_name'] ?? ''),
					'neighborhood_name' => (string) ($row['neighborhood_name'] ?? ''),
					'city_created' => false,
					'error' => true,
					'message' => 'Falha ao importar o registro: ' . $exception->getMessage(),
				]);
				}
			}

		yield $this->event('done', $stats + ['total' => $total]);
		}

	/**
	 * Resolve the required header column indexes.
	 *
	 * @param array<int, mixed> $headerRow
	 * @return array<string, int>
	 */
	private function resolveHeaderIndexes(array $headerRow): array
		{
		$indexes = [];

		foreach ($headerRow as $index => $value)
			{
			$key = $this->normalizeHeaderName((string) $value);
			if ($key !== '')
				{
				$indexes[$key] = (int) $index;
				}
			}

		if (!isset($indexes['nm_bairro']) || !isset($indexes['nm_mun']) || !isset($indexes['sigla_uf']))
			{
			throw new RuntimeException('O arquivo deve conter as colunas `nm_bairro`, `nm_mun` e `sigla_uf` no cabeçalho.');
			}

		return [
			'nm_bairro' => $indexes['nm_bairro'],
			'nm_mun' => $indexes['nm_mun'],
			'sigla_uf' => $indexes['sigla_uf'],
		];
		}

	/**
	 * Load states indexed by acronym.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function loadStateIndex(): array
		{
		$stmt = $this->db->query("SELECT State.id, State.acronym
		                          FROM states AS State
		                          INNER JOIN countries AS Country ON Country.id = State.country_id
		                          WHERE Country.acronym = 'BR'");
		$index = [];

		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row)
			{
			$acronym = strtoupper(trim((string) ($row['acronym'] ?? '')));
			if ($acronym === '')
				{
				continue;
				}

			$index[$acronym] = [
				'id' => (int) ($row['id'] ?? 0),
				'acronym' => $acronym,
			];
			}

		return $index;
		}

	/**
	 * Load cities indexed by state and normalized city name.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	private function loadCityIndex(): array
		{
		$sql = "SELECT City.id, City.name, City.state_id, State.acronym AS state_acronym
		        FROM cities AS City
		        LEFT JOIN states AS State ON State.id = City.state_id";
		$stmt = $this->db->query($sql);

		$index = [];
		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row)
			{
			$key = $this->buildCityKey((int) ($row['state_id'] ?? 0), (string) ($row['name'] ?? ''));
			if ($key === '')
				{
				continue;
				}

			$index[$key][] = [
				'id' => (int) ($row['id'] ?? 0),
				'name' => (string) ($row['name'] ?? ''),
				'state_id' => (int) ($row['state_id'] ?? 0),
				'state_acronym' => (string) ($row['state_acronym'] ?? ''),
			];
			}

		return $index;
		}

	/**
	 * Load existing neighborhoods indexed by city and normalized neighborhood name.
	 *
	 * @return array<string, bool>
	 */
	private function loadNeighborhoodIndex(): array
		{
		$sql = "SELECT Neighborhood.city_id, Neighborhood.name
		        FROM neighborhoods AS Neighborhood";
		$stmt = $this->db->query($sql);

		$index = [];
		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row)
			{
			$key = (int) ($row['city_id'] ?? 0) . '|' . $this->normalizeLookupKey((string) ($row['name'] ?? ''));
			$index[$key] = true;
			}

		return $index;
		}

	/**
	 * Ensure the city exists and return its id.
	 *
	 * @param array<string, mixed> $row
	 * @param array<string, int> $cache
	 * @return array<string, mixed>
	 */
	private function ensureCityForRow(array $row, array &$cache): array
		{
		$stateId = (int) ($row['state_id'] ?? 0);
		$cityName = (string) ($row['city_name'] ?? '');
		$cityKey = $this->buildCityKey($stateId, $cityName);

		if (isset($cache[$cityKey]))
			{
			return ['city_id' => $cache[$cityKey], 'created' => false];
			}

		$existingId = (int) ($row['city_id'] ?? 0);
		if ($existingId > 0)
			{
			$cache[$cityKey] = $existingId;
			return ['city_id' => $existingId, 'created' => false];
			}

		$stmt = $this->db->prepare("SELECT id FROM cities WHERE state_id = :state_id AND `name` = :name ORDER BY id ASC LIMIT 1");
		$stmt->execute([
			'state_id' => $stateId,
			'name' => $cityName,
		]);
		$cityId = (int) ($stmt->fetchColumn() ?: 0);

		if ($cityId > 0)
			{
			$cache[$cityKey] = $cityId;
			return ['city_id' => $cityId, 'created' => false];
			}

		$stmt = $this->db->prepare("INSERT INTO cities (`name`, `description`, `state_id`, `created`, `modified`)
		                            VALUES (:name, :description, :state_id, :created, :modified)");
		$timestamp = date('Y-m-d H:i:s');
		$stmt->execute([
			'name' => $cityName,
			'description' => null,
			'state_id' => $stateId,
			'created' => $timestamp,
			'modified' => $timestamp,
		]);

		$cityId = (int) $this->db->lastInsertId();
		$cache[$cityKey] = $cityId;

		return ['city_id' => $cityId, 'created' => true];
		}

	/**
	 * Create a neighborhood row.
	 *
	 * @param string $name
	 * @param int $cityId
	 * @return int
	 */
	private function createNeighborhoodIfMissing(string $name, int $cityId): array
		{
		$lookup = $this->db->prepare("SELECT id FROM neighborhoods WHERE city_id = :city_id AND `name` = :name ORDER BY id ASC LIMIT 1");
		$lookup->execute([
			'city_id' => $cityId,
			'name' => $name,
		]);
		$existingId = (int) ($lookup->fetchColumn() ?: 0);

		if ($existingId > 0)
			{
			return [
				'neighborhood_id' => $existingId,
				'created' => false,
			];
			}

		$stmt = $this->db->prepare("INSERT INTO neighborhoods (`name`, `city_id`, `created`, `modified`)
		                            VALUES (:name, :city_id, :created, :modified)");
		$timestamp = date('Y-m-d H:i:s');
		$stmt->execute([
			'name' => $name,
			'city_id' => $cityId,
			'created' => $timestamp,
			'modified' => $timestamp,
		]);

		return [
			'neighborhood_id' => (int) $this->db->lastInsertId(),
			'created' => true,
		];
		}

	/**
	 * Normalize a text value used in lookups.
	 *
	 * @param string $value
	 * @return string
	 */
	private function normalizeLookupKey(string $value): string
		{
		$value = $this->cleanCellValue($value);
		$value = $this->removeAccents($value);

		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
		}

	/**
	 * Normalize header names.
	 *
	 * @param string $value
	 * @return string
	 */
	private function normalizeHeaderName(string $value): string
		{
		$value = $this->normalizeLookupKey($value);
		$value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
		return trim($value, '_');
		}

	/**
	 * Build a unique city key using state and city.
	 *
	 * @param int $stateId
	 * @param string $cityName
	 * @return string
	 */
	private function buildCityKey(int $stateId, string $cityName): string
		{
		if ($stateId <= 0)
			{
			return '';
			}

		return $stateId . '|' . $this->normalizeLookupKey($cityName);
		}

	/**
	 * Remove extra whitespace from imported cells.
	 *
	 * @param string $value
	 * @return string
	 */
	private function cleanCellValue(string $value): string
		{
		$value = trim($value);
		return preg_replace('/\s+/', ' ', $value) ?? $value;
		}

	/**
	 * Remove accents to improve matches against existing data.
	 *
	 * @param string $value
	 * @return string
	 */
	private function removeAccents(string $value): string
		{
		$converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		return is_string($converted) && $converted !== '' ? $converted : $value;
		}

	/**
	 * Build a row issue payload for the UI.
	 *
	 * @param int $line
	 * @param string $stateAcronym
	 * @param string $cityName
	 * @param string $neighborhoodName
	 * @param string $reason
	 * @return array<string, mixed>
	 */
	private function buildIssue(int $line, string $stateAcronym, string $cityName, string $neighborhoodName, string $reason): array
		{
		return [
			'line' => $line,
			'state_acronym' => $stateAcronym,
			'city_name' => $cityName,
			'neighborhood_name' => $neighborhoodName,
			'reason' => $reason,
		];
		}

	/**
	 * Format a stream payload.
	 *
	 * @param string $event
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function event(string $event, array $data): array
		{
		return [
			'event' => $event,
			'data' => $data,
		];
		}
	}

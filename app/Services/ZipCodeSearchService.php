<?php

namespace App\Services;

use App\Core\Model;
use PDO;

/**
 * Zip code search and persistence service.
 */
class ZipCodeSearchService extends Model
	{
	private $viaCepService;
	private $brasilApiService;

	/**
	 * Initialize the service dependencies used for postal code lookups.
	 * @return void Service constructor.
	 **/
	public function __construct()
		{
		parent::__construct();
		$this->viaCepService = new ViaCepService();
		$this->brasilApiService = new BrasilApiService();
		}

	/**
	 * Search for a postal code and persist the related location records.
	 * @param string $zipCode Postal code informed by the caller.
	 * @return array Lookup and persistence result payload.
	 **/
	public function searchAndSave(string $zipCode): array
		{
		$normalizedZipCode = $this->normalizeZipCode($zipCode);
		if (strlen($normalizedZipCode) !== 8)
			{
			return $this->failure('invalid_zip_code', 'Informe um CEP válido com 8 dígitos.', 422);
			}

		$lookup = $this->lookupWithFallback($normalizedZipCode);
		if (empty($lookup['success']))
			{
			return $lookup;
			}

		$data = $lookup['data'];

		try
			{
			$this->db->beginTransaction();
			$saved = $this->persistLookup($data);
			$this->db->commit();

			$data['saved'] = $saved;

			return [
				'success' => true,
				'status'  => 200,
				'source'  => $lookup['source'] ?? '',
				'message' => $this->buildSuccessMessage($saved),
				'data'    => $data,
			];
			}
		catch (\Exception $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			$data['saved'] = [
				'success' => false,
				'error'   => 'save_failed',
				'message' => 'Não foi possível salvar os dados do CEP na base agora.',
			];

			return [
				'success' => false,
				'status'  => 500,
				'source'  => $lookup['source'] ?? '',
				'error'   => 'save_failed',
				'message' => 'Não foi possível salvar os dados do CEP na base agora.',
				'data'    => $data,
			];
			}
		}

	/**
	 * Look up a postal code using ViaCEP first and BrasilAPI as fallback.
	 * @param string $zipCode Normalized postal code with digits only.
	 * @return array Lookup result with provider metadata and attempts.
	 **/
	private function lookupWithFallback(string $zipCode): array
		{
		$attempts = [];

		$viaCepResult = $this->viaCepService->lookup($zipCode);
		$attempts[] = $this->attemptPayload($viaCepResult, 'ViaCEP');
		if (!empty($viaCepResult['success']))
			{
			return [
				'success' => true,
				'status'  => 200,
				'source'  => 'viacep',
				'message' => 'CEP localizado com sucesso no ViaCEP.',
				'data'    => $this->appendLookupMetadata($viaCepResult['data'], 'viacep', $attempts),
			];
			}

		$brasilApiResult = $this->brasilApiService->lookup($zipCode);
		$attempts[] = $this->attemptPayload($brasilApiResult, 'BrasilAPI');
		if (!empty($brasilApiResult['success']))
			{
			return [
				'success' => true,
				'status'  => 200,
				'source'  => 'brasilapi',
				'message' => 'CEP localizado com sucesso na BrasilAPI.',
				'data'    => $this->appendLookupMetadata($brasilApiResult['data'], 'brasilapi', $attempts),
			];
			}

		$lastError = !empty($brasilApiResult['message'])
			? (string) $brasilApiResult['message']
			: (!empty($viaCepResult['message']) ? (string) $viaCepResult['message'] : 'Não foi possível consultar o CEP.');

		return [
			'success' => false,
			'status'  => (int) ($brasilApiResult['status'] ?? $viaCepResult['status'] ?? 502),
			'source'  => '',
			'error'   => 'lookup_failed',
			'message' => $lastError,
			'data'    => [
				'attempts' => $attempts,
			],
		];
		}

	/**
	 * Append source metadata and attempt history to the lookup data.
	 * @param array $data Normalized lookup data.
	 * @param string $source Provider identifier used for the successful lookup.
	 * @param array $attempts Lookup attempts performed before success.
	 * @return array Lookup data enriched with metadata fields.
	 **/
	private function appendLookupMetadata(array $data, string $source, array $attempts): array
		{
		$data['source'] = $source;
		$data['attempts'] = $attempts;
		return $data;
		}

	/**
	 * Build a summarized payload for a single lookup attempt.
	 * @param array $result Raw lookup result returned by a provider service.
	 * @param string $label Human-readable provider label.
	 * @return array Attempt payload used by the response contract.
	 **/
	private function attemptPayload(array $result, string $label): array
		{
		return [
			'label'   => $label,
			'source'  => (string) ($result['source'] ?? ''),
			'success' => !empty($result['success']),
			'status'  => (int) ($result['status'] ?? 0),
			'error'   => (string) ($result['error'] ?? ''),
			'message' => (string) ($result['message'] ?? ''),
		];
		}

	/**
	 * Persist the normalized lookup data into country, state, city and neighborhood records.
	 * @param array $data Normalized lookup data to be persisted.
	 * @return array Information about the reused or created records.
	 **/
	private function persistLookup(array $data): array
		{
		$countryId = $this->findOrCreateBrazilCountry();
		$state = $this->findOrCreateState(
			(string) ($data['state'] ?? ''),
			(string) ($data['state_name'] ?? ''),
			$countryId
		);
		$city = $this->findOrCreateCity(
			(string) ($data['city'] ?? ''),
			(int) $state['id']
		);

		$neighborhoodName = trim((string) ($data['neighborhood'] ?? ''));
		$neighborhood = null;
		if ($neighborhoodName !== '')
			{
			$neighborhood = $this->findOrCreateNeighborhood($neighborhoodName, (int) $city['id']);
			}

		return [
			'country' => [
				'id'      => $countryId,
				'created' => false,
			],
			'state' => $state,
			'city' => $city,
			'neighborhood' => $neighborhood,
		];
		}

	/**
	 * Find the Brazil country record or create it when missing.
	 * @return int Identifier of the Brazil country record.
	 **/
	private function findOrCreateBrazilCountry(): int
		{
		$stmt = $this->db->prepare("SELECT Country.id FROM countries AS Country WHERE Country.acronym = :acronym OR Country.`name` = :name LIMIT 1");
		$stmt->execute([
			'acronym' => 'BR',
			'name'    => 'Brasil',
		]);

		$countryId = (int) $stmt->fetchColumn();
		if ($countryId > 0)
			{
			return $countryId;
			}

		$stmt = $this->db->prepare("INSERT INTO countries (`name`, acronym, created, modified) VALUES (:name, :acronym, :created, :modified)");
		$stmt->execute([
			'name'     => 'Brasil',
			'acronym'  => 'BR',
			'created'  => date('Y-m-d H:i:s'),
			'modified' => date('Y-m-d H:i:s'),
		]);

		return (int) $this->db->lastInsertId();
		}

	/**
	 * Find a state by acronym or create it for the given country.
	 * @param string $acronym State acronym returned by the provider.
	 * @param string $name State name returned by the provider.
	 * @param int $countryId Country identifier that owns the state.
	 * @return array State data including creation status.
	 **/
	private function findOrCreateState(string $acronym, string $name, int $countryId): array
		{
		$acronym = strtoupper(trim($acronym));
		$name = trim($name);

		$stmt = $this->db->prepare("SELECT State.id, State.`name`, State.acronym, State.country_id FROM states AS State WHERE State.country_id = :country_id AND State.acronym = :acronym LIMIT 1");
		$stmt->execute([
			'country_id' => $countryId,
			'acronym'    => $acronym,
		]);

		$existing = $stmt->fetch(PDO::FETCH_ASSOC);
		if (is_array($existing) && !empty($existing))
			{
			$existing['created'] = false;
			return $existing;
			}

		$stmt = $this->db->prepare("INSERT INTO states (`name`, acronym, country_id, created, modified) VALUES (:name, :acronym, :country_id, :created, :modified)");
		$stmt->execute([
			'name'       => $name !== '' ? $name : $acronym,
			'acronym'    => $acronym,
			'country_id' => $countryId,
			'created'    => date('Y-m-d H:i:s'),
			'modified'   => date('Y-m-d H:i:s'),
		]);

		return [
			'id'         => (int) $this->db->lastInsertId(),
			'name'       => $name !== '' ? $name : $acronym,
			'acronym'    => $acronym,
			'country_id' => $countryId,
			'created'    => true,
		];
		}

	/**
	 * Find a city by name and state or create it when missing.
	 * @param string $name City name returned by the provider.
	 * @param int $stateId State identifier that owns the city.
	 * @return array City data including creation status.
	 **/
	private function findOrCreateCity(string $name, int $stateId): array
		{
		$name = trim($name);

		$stmt = $this->db->prepare("SELECT City.id, City.`name`, City.state_id FROM cities AS City WHERE City.state_id = :state_id AND LOWER(TRIM(City.`name`)) = LOWER(TRIM(:name)) LIMIT 1");
		$stmt->execute([
			'state_id' => $stateId,
			'name'     => $name,
		]);

		$existing = $stmt->fetch(PDO::FETCH_ASSOC);
		if (is_array($existing) && !empty($existing))
			{
			$existing['created'] = false;
			return $existing;
			}

		$stmt = $this->db->prepare("INSERT INTO cities (`name`, description, state_id, created, modified) VALUES (:name, :description, :state_id, :created, :modified)");
		$stmt->execute([
			'name'        => $name,
			'description' => null,
			'state_id'    => $stateId,
			'created'     => date('Y-m-d H:i:s'),
			'modified'    => date('Y-m-d H:i:s'),
		]);

		return [
			'id'       => (int) $this->db->lastInsertId(),
			'name'     => $name,
			'state_id' => $stateId,
			'created'  => true,
		];
		}

	/**
	 * Find a neighborhood by name and city or create it when missing.
	 * @param string $name Neighborhood name returned by the provider.
	 * @param int $cityId City identifier that owns the neighborhood.
	 * @return array Neighborhood data including creation status.
	 **/
	private function findOrCreateNeighborhood(string $name, int $cityId): array
		{
		$name = trim($name);

		$stmt = $this->db->prepare("SELECT Neighborhood.id, Neighborhood.`name`, Neighborhood.city_id FROM neighborhoods AS Neighborhood WHERE Neighborhood.city_id = :city_id AND LOWER(TRIM(Neighborhood.`name`)) = LOWER(TRIM(:name)) LIMIT 1");
		$stmt->execute([
			'city_id' => $cityId,
			'name'    => $name,
		]);

		$existing = $stmt->fetch(PDO::FETCH_ASSOC);
		if (is_array($existing) && !empty($existing))
			{
			$existing['created'] = false;
			return $existing;
			}

		$stmt = $this->db->prepare("INSERT INTO neighborhoods (`name`, city_id, created, modified) VALUES (:name, :city_id, :created, :modified)");
		$stmt->execute([
			'name'     => $name,
			'city_id'  => $cityId,
			'created'  => date('Y-m-d H:i:s'),
			'modified' => date('Y-m-d H:i:s'),
		]);

		return [
			'id'       => (int) $this->db->lastInsertId(),
			'name'     => $name,
			'city_id'  => $cityId,
			'created'  => true,
		];
		}

	/**
	 * Build the success message based on the records created during persistence.
	 * @param array $saved Saved record metadata returned by persistence.
	 * @return string Success message for the final response.
	 **/
	private function buildSuccessMessage(array $saved): string
		{
		$neighborhoodCreated = !empty($saved['neighborhood']['created']);
		$cityCreated = !empty($saved['city']['created']);
		$stateCreated = !empty($saved['state']['created']);

		if ($neighborhoodCreated)
			{
			return 'CEP consultado com sucesso e bairro salvo na base.';
			}

		if ($cityCreated || $stateCreated)
			{
			return 'CEP consultado com sucesso e localização relacionada atualizada na base.';
			}

		return 'CEP consultado com sucesso e registros já existentes reaproveitados.';
		}

	/**
	 * Remove every non-digit character from the provided postal code.
	 * @param string $value Raw postal code value.
	 * @return string Postal code containing digits only.
	 **/
	private function normalizeZipCode(string $value): string
		{
		return preg_replace('/\D+/', '', $value) ?? '';
		}

	/**
	 * Build the standardized failure payload returned by the service.
	 * @param string $error Error identifier for the failure scenario.
	 * @param string $message User-facing error message.
	 * @param int $status HTTP status code associated with the failure.
	 * @return array Failure payload following the service response contract.
	 **/
	private function failure(string $error, string $message, int $status): array
		{
		return [
			'success' => false,
			'status'  => $status,
			'error'   => $error,
			'message' => $message,
			'data'    => [
				'attempts' => [],
			],
		];
		}
	}

?>

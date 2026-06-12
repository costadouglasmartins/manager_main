<?php

namespace App\Services;

use App\Core\Model;
use PDO;

/**
 * Zip code neighborhood comparison and persistence service.
 */
class ZipCodeNeighborhoodService extends Model
	{
	private $viaCepService;
	private $brasilApiService;

	/**
	 * Initialize the provider services used for postal code comparison.
	 * @return void Service constructor.
	 **/
	public function __construct()
		{
		parent::__construct();
		$this->viaCepService = new ViaCepService();
		$this->brasilApiService = new BrasilApiService();
		}

	/**
	 * Compare postal code results returned by ViaCEP and BrasilAPI.
	 * @param string $zipCode Postal code informed by the caller.
	 * @return array Comparison payload with provider-specific data.
	 **/
	public function compareLookups(string $zipCode): array
		{
		$normalizedZipCode = $this->normalizeZipCode($zipCode);
		if (strlen($normalizedZipCode) !== 8)
			{
			return $this->failure('invalid_zip_code', 'Informe um CEP válido com 8 dígitos.', 422);
			}

		$viaCepResult = $this->viaCepService->lookup($normalizedZipCode);
		$brasilApiResult = $this->brasilApiService->lookup($normalizedZipCode);

		$providers = [
			'viacep' => $this->decorateProviderResult($viaCepResult, 'viacep'),
			'brasilapi' => $this->decorateProviderResult($brasilApiResult, 'brasilapi'),
		];

		if (empty($providers['viacep']['success']) && empty($providers['brasilapi']['success']))
			{
			return [
				'success' => false,
				'status'  => (int) ($brasilApiResult['status'] ?? $viaCepResult['status'] ?? 502),
				'error'   => 'lookup_failed',
				'message' => $this->firstErrorMessage($viaCepResult, $brasilApiResult),
				'data'    => [
					'zip_code'  => $normalizedZipCode,
					'providers' => $providers,
				],
			];
			}

		return [
			'success' => true,
			'status'  => 200,
			'message' => 'CEP consultado com ambos os serviços.',
			'data'    => [
				'zip_code'  => $normalizedZipCode,
				'providers' => $providers,
				'defaults'  => $this->buildDefaults($providers),
			],
		];
		}

	/**
	 * Save the selected city and neighborhood values chosen from the provider comparison.
	 * @param string $zipCode Postal code associated with the selected data.
	 * @param array $selection Provider choices for each selectable field.
	 * @param bool $createCity True when a missing city may be created.
	 * @return array Save result payload with created or reused records.
	 **/
	public function saveSelection(string $zipCode, array $selection, bool $createCity): array
		{
		$comparison = $this->compareLookups($zipCode);
		if (empty($comparison['success']))
			{
			return $comparison;
			}

		$providers = $comparison['data']['providers'] ?? [];
		$selectedValues = $this->buildSelectedValues($providers, $selection);

		if (($selectedValues['city'] ?? '') === '')
			{
			return $this->failure('city_required', 'Escolha uma cidade para salvar.', 422, $comparison['data']);
			}

		if (($selectedValues['state'] ?? '') === '')
			{
			return $this->failure('state_required', 'Escolha um estado para salvar.', 422, $comparison['data']);
			}

		if (($selectedValues['neighborhood'] ?? '') === '')
			{
			return $this->failure('neighborhood_required', 'Escolha um bairro para salvar.', 422, $comparison['data']);
			}

		try
			{
			$this->db->beginTransaction();

			$state = $this->findStateByAcronym((string) $selectedValues['state']);
			if (!$state)
				{
				throw new \RuntimeException('State not found.');
				}

			$city = $this->findCityByNameAndStateId((string) $selectedValues['city'], (int) $state['id']);
			$cityCreated = false;
			if (!$city)
				{
				if (!$createCity)
					{
					$this->db->rollBack();
					return $this->failure('city_not_found', 'A cidade não existe na base. Marque a opção para criar a cidade.', 422, $comparison['data']);
					}

				$city = $this->createCity((string) $selectedValues['city'], (int) $state['id']);
				$cityCreated = true;
				}

			$neighborhood = $this->findNeighborhoodByNameAndCityId((string) $selectedValues['neighborhood'], (int) $city['id']);
			$neighborhoodCreated = false;
			if (!$neighborhood)
				{
				$neighborhood = $this->createNeighborhood((string) $selectedValues['neighborhood'], (int) $city['id']);
				$neighborhoodCreated = true;
				}

			$this->db->commit();

			return [
				'success' => true,
				'status'  => 200,
				'message' => $this->buildSaveMessage($cityCreated, $neighborhoodCreated),
				'data'    => [
					'zip_code'     => $this->normalizeZipCode($zipCode),
					'selection'    => $selectedValues,
					'state'        => $state,
					'city'         => $city,
					'city_created' => $cityCreated,
					'neighborhood' => $neighborhood,
					'neighborhood_created' => $neighborhoodCreated,
					'providers'    => $providers,
				],
			];
			}
		catch (\Exception $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			return $this->failure('save_failed', 'Não foi possível salvar os dados na base agora.', 500, $comparison['data']);
			}
		}

	/**
	 * Persist lookup data directly using the configured save options.
	 * @param array $lookupData Lookup data returned by a provider.
	 * @param bool $saveCity True when the city should be saved.
	 * @param bool $saveNeighborhood True when the neighborhood should be saved.
	 * @param bool $createCity True when a missing city may be created.
	 * @return array Persistence result payload.
	 **/
	public function persistLookupData(array $lookupData, bool $saveCity, bool $saveNeighborhood, bool $createCity): array
		{
		$normalized = $this->normalizePersistedLookupData($lookupData);
		if (($normalized['city'] ?? '') === '')
			{
			return $this->failure('city_required', 'A cidade retornada pelo serviço é obrigatória para salvar.', 422, ['lookup_data' => $lookupData]);
			}

		if (($normalized['state'] ?? '') === '')
			{
			return $this->failure('state_required', 'O estado retornado pelo serviço é obrigatório para salvar.', 422, ['lookup_data' => $lookupData]);
			}

		if (!$saveCity && !$saveNeighborhood)
			{
			return [
				'success' => true,
				'status'  => 200,
				'message' => 'Salvamento desativado.',
				'data'    => [
					'lookup' => $normalized,
					'saved'  => [
						'city_saved' => false,
						'neighborhood_saved' => false,
						'city_created' => false,
						'neighborhood_created' => false,
					],
				],
			];
			}

		try
			{
			$this->db->beginTransaction();

			$state = $this->findStateByAcronym((string) $normalized['state']);
			if (!$state)
				{
				$this->db->rollBack();
				return $this->failure('state_not_found', 'O estado retornado pelo serviço não foi encontrado na base.', 422, ['lookup_data' => $lookupData]);
				}

			$city = $this->findCityByNameAndStateId((string) $normalized['city'], (int) $state['id']);
			$cityCreated = false;
			if (!$city)
				{
				if (!$createCity)
					{
					$this->db->rollBack();
					return $this->failure('city_not_found', 'A cidade não existe na base. Habilite a criação de cidade para salvar.', 422, ['lookup_data' => $lookupData]);
					}

				$city = $this->createCity((string) $normalized['city'], (int) $state['id']);
				$cityCreated = true;
				}

			$neighborhood = null;
			$neighborhoodCreated = false;
			if ($saveNeighborhood && (string) ($normalized['neighborhood'] ?? '') !== '')
				{
				$neighborhood = $this->findNeighborhoodByNameAndCityId((string) $normalized['neighborhood'], (int) $city['id']);
				if (!$neighborhood)
					{
					$neighborhood = $this->createNeighborhood((string) $normalized['neighborhood'], (int) $city['id']);
					$neighborhoodCreated = true;
					}
				}

			$this->db->commit();

			return [
				'success' => true,
				'status'  => 200,
				'message' => $this->buildPersistMessage($cityCreated, $neighborhoodCreated, $saveCity, $saveNeighborhood),
				'data'    => [
					'lookup' => $normalized,
					'state'  => $state,
					'city'   => $city,
					'city_created' => $cityCreated,
					'city_saved' => $saveCity,
					'neighborhood' => $neighborhood,
					'neighborhood_created' => $neighborhoodCreated,
					'neighborhood_saved' => $saveNeighborhood,
				],
			];
			}
		catch (\Exception $e)
			{
			if ($this->db->inTransaction())
				{
				$this->db->rollBack();
				}

			return $this->failure('save_failed', 'Não foi possível salvar os dados na base agora.', 500, ['lookup_data' => $lookupData]);
			}
		}

	/**
	 * Persist multiple lookup results using the configured save options.
	 * @param array $items Lookup items to be processed in batch.
	 * @param bool $saveCity True when cities should be saved.
	 * @param bool $saveNeighborhood True when neighborhoods should be saved.
	 * @param bool $createCity True when missing cities may be created.
	 * @return array Batch persistence result payload.
	 **/
	public function persistLookupDataBatch(array $items, bool $saveCity, bool $saveNeighborhood, bool $createCity): array
		{
		$results = [];
		$savedCount = 0;
		$failedCount = 0;

		foreach ($items as $item)
			{
			$lookupData = is_array($item) ? ($item['lookup_data'] ?? null) : null;
			if (!is_array($lookupData) || empty($lookupData))
				{
				$results[] = [
					'success' => false,
					'status'  => 422,
					'message' => 'Dados do CEP inválidos.',
					'data'    => $item,
				];
				$failedCount++;
				continue;
				}

			$result = $this->persistLookupData($lookupData, $saveCity, $saveNeighborhood, $createCity);
			if (!empty($result['success']))
				{
				$savedCount++;
				}
			else
				{
				$failedCount++;
				}

			$results[] = $result;
			}

		return [
			'success' => $savedCount > 0,
			'status'  => $failedCount === 0 ? 200 : 207,
			'message' => $failedCount === 0
				? 'Resultados selecionados salvos com sucesso.'
				: ($savedCount > 0 ? 'Alguns resultados foram salvos e outros falharam.' : 'Nenhum resultado pôde ser salvo.'),
			'data'    => [
				'saved_count' => $savedCount,
				'failed_count' => $failedCount,
				'results' => $results,
			],
		];
		}

	/**
	 * Build the default provider selection for each supported field.
	 * @param array $providers Provider comparison payloads.
	 * @return array Default provider key for each field.
	 **/
	private function buildDefaults(array $providers): array
		{
		$fields = ['neighborhood', 'city', 'state', 'street', 'complement', 'state_name', 'region', 'ddd', 'ibge', 'siafi'];
		$defaults = [];

		foreach ($fields as $field)
			{
			$defaults[$field] = $this->preferredProviderForField($providers, $field);
			}

		return $defaults;
		}

	/**
	 * Resolve the selected values from the provider comparison and user choices.
	 * @param array $providers Provider comparison payloads.
	 * @param array $selection Provider choices for each supported field.
	 * @return array Selected field values grouped by field name.
	 **/
	private function buildSelectedValues(array $providers, array $selection): array
		{
		$fields = ['neighborhood', 'city', 'state', 'street', 'complement', 'state_name', 'region', 'ddd', 'ibge', 'siafi'];
		$values = [];

		foreach ($fields as $field)
			{
			$providerKey = (string) ($selection[$field] ?? $this->preferredProviderForField($providers, $field));
			if ($providerKey === '' || !isset($providers[$providerKey]) || empty($providers[$providerKey]['success']))
				{
				continue;
				}

			$values[$field] = (string) ($providers[$providerKey]['data'][$field] ?? '');
		}

		return $values;
		}

	/**
	 * Decorate a provider lookup result with comparison-specific metadata.
	 * @param array $result Raw provider lookup result.
	 * @param string $providerKey Internal provider identifier.
	 * @return array Decorated provider payload for the comparison response.
	 **/
	private function decorateProviderResult(array $result, string $providerKey): array
		{
		$decorated = [
			'success' => !empty($result['success']),
			'status'  => (int) ($result['status'] ?? 0),
			'source'  => $providerKey,
			'error'   => (string) ($result['error'] ?? ''),
			'message' => (string) ($result['message'] ?? ''),
			'data'    => [],
			'city_lookup' => null,
		];

		if (empty($result['success']) || empty($result['data']) || !is_array($result['data']))
			{
			return $decorated;
			}

		$data = $result['data'];
		$cityLookup = $this->findCityByNameAndStateAcronym((string) ($data['city'] ?? ''), (string) ($data['state'] ?? ''));

		$decorated['data'] = $data;
		$decorated['city_lookup'] = $cityLookup ? [
			'exists' => true,
			'id'     => (int) $cityLookup['id'],
			'name'   => (string) $cityLookup['name'],
			'label'  => (string) $cityLookup['name'] . ' - ' . (string) ($cityLookup['state_name'] ?? ''),
		] : [
			'exists' => false,
			'id'     => null,
			'name'   => (string) ($data['city'] ?? ''),
			'label'  => (string) ($data['city'] ?? '') . ' - ' . (string) ($data['state_name'] ?? ''),
		];

		return $decorated;
		}

	/**
	 * Resolve the preferred provider for a specific field.
	 * @param array $providers Provider comparison payloads.
	 * @param string $field Field name that needs a preferred provider.
	 * @return string Provider key preferred for the informed field.
	 **/
	private function preferredProviderForField(array $providers, string $field): string
		{
		$providerOrder = ['viacep', 'brasilapi'];

		if ($field === 'city')
			{
			foreach ($providerOrder as $providerKey)
				{
				if (!empty($providers[$providerKey]['success']) && !empty($providers[$providerKey]['city_lookup']['exists']))
					{
					return $providerKey;
					}
				}
			}

		foreach ($providerOrder as $providerKey)
			{
			if (!empty($providers[$providerKey]['success']) && !empty($providers[$providerKey]['data'][$field]))
				{
				return $providerKey;
				}
			}

		foreach ($providerOrder as $providerKey)
			{
			if (!empty($providers[$providerKey]['success']))
				{
				return $providerKey;
				}
			}

		return '';
		}

	/**
	 * Find a state record by its acronym.
	 * @param string $acronym State acronym used for the lookup.
	 * @return array|null State record when found or null otherwise.
	 **/
	private function findStateByAcronym(string $acronym): ?array
		{
		$acronym = strtoupper(trim($acronym));
		if ($acronym === '')
			{
			return null;
			}

		$stmt = $this->db->prepare("SELECT State.id, State.name, State.acronym, State.country_id
		                            FROM states AS State
		                            WHERE UPPER(State.acronym) = :acronym
		                            LIMIT 1");
		$stmt->execute(['acronym' => $acronym]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
		}

	/**
	 * Find a city by name within a specific state.
	 * @param string $cityName City name used for the lookup.
	 * @param int $stateId State identifier that owns the city.
	 * @return array|null City record when found or null otherwise.
	 **/
	private function findCityByNameAndStateId(string $cityName, int $stateId): ?array
		{
		$cityName = trim($cityName);
		if ($cityName === '' || $stateId <= 0)
			{
			return null;
			}

		$stmt = $this->db->prepare("SELECT City.id, City.name, City.state_id, State.name AS state_name, State.acronym AS state_acronym
		                            FROM cities AS City
		                            INNER JOIN states AS State ON State.id = City.state_id
		                            WHERE City.state_id = :state_id
		                              AND LOWER(TRIM(City.name)) = LOWER(TRIM(:city_name))
		                            LIMIT 1");
		$stmt->execute([
			'state_id'   => $stateId,
			'city_name'  => $cityName,
		]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
		}

	/**
	 * Find a city by name using the state acronym as the owner reference.
	 * @param string $cityName City name used for the lookup.
	 * @param string $stateAcronym State acronym used to resolve the state.
	 * @return array|null City record when found or null otherwise.
	 **/
	private function findCityByNameAndStateAcronym(string $cityName, string $stateAcronym): ?array
		{
		$state = $this->findStateByAcronym($stateAcronym);
		if (!$state)
			{
			return null;
			}

		return $this->findCityByNameAndStateId($cityName, (int) $state['id']);
		}

	/**
	 * Create a city record for the informed state.
	 * @param string $cityName City name to be created.
	 * @param int $stateId State identifier that owns the city.
	 * @return array Created city data.
	 **/
	private function createCity(string $cityName, int $stateId): array
		{
		$stmt = $this->db->prepare("INSERT INTO cities (`name`, description, state_id, created, modified)
		                            VALUES (:name, :description, :state_id, :created, :modified)");
		$stmt->execute([
			'name'        => $cityName,
			'description' => null,
			'state_id'    => $stateId,
			'created'     => date('Y-m-d H:i:s'),
			'modified'    => date('Y-m-d H:i:s'),
		]);

		return [
			'id'       => (int) $this->db->lastInsertId(),
			'name'     => $cityName,
			'state_id' => $stateId,
			'created'  => true,
		];
		}

	/**
	 * Find a neighborhood by name within a specific city.
	 * @param string $neighborhoodName Neighborhood name used for the lookup.
	 * @param int $cityId City identifier that owns the neighborhood.
	 * @return array|null Neighborhood record when found or null otherwise.
	 **/
	private function findNeighborhoodByNameAndCityId(string $neighborhoodName, int $cityId): ?array
		{
		$neighborhoodName = trim($neighborhoodName);
		if ($neighborhoodName === '' || $cityId <= 0)
			{
			return null;
			}

		$stmt = $this->db->prepare("SELECT Neighborhood.id, Neighborhood.name, Neighborhood.city_id
		                            FROM neighborhoods AS Neighborhood
		                            WHERE Neighborhood.city_id = :city_id
		                              AND LOWER(TRIM(Neighborhood.name)) = LOWER(TRIM(:neighborhood_name))
		                            LIMIT 1");
		$stmt->execute([
			'city_id' => $cityId,
			'neighborhood_name' => $neighborhoodName,
		]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		return $row ?: null;
		}

	/**
	 * Create a neighborhood record for the informed city.
	 * @param string $neighborhoodName Neighborhood name to be created.
	 * @param int $cityId City identifier that owns the neighborhood.
	 * @return array Created neighborhood data.
	 **/
	private function createNeighborhood(string $neighborhoodName, int $cityId): array
		{
		$stmt = $this->db->prepare("INSERT INTO neighborhoods (`name`, city_id, created, modified)
		                            VALUES (:name, :city_id, :created, :modified)");
		$stmt->execute([
			'name'     => $neighborhoodName,
			'city_id'  => $cityId,
			'created'  => date('Y-m-d H:i:s'),
			'modified' => date('Y-m-d H:i:s'),
		]);

		return [
			'id'       => (int) $this->db->lastInsertId(),
			'name'     => $neighborhoodName,
			'city_id'  => $cityId,
			'created'  => true,
		];
		}

	/**
	 * Build the save message for the manual selection workflow.
	 * @param bool $cityCreated True when the city was created.
	 * @param bool $neighborhoodCreated True when the neighborhood was created.
	 * @return string Message describing the save outcome.
	 **/
	private function buildSaveMessage(bool $cityCreated, bool $neighborhoodCreated): string
		{
		if ($cityCreated && $neighborhoodCreated)
			{
			return 'Cidade e bairro salvos com sucesso.';
			}

		if ($cityCreated)
			{
			return 'Cidade salva com sucesso e bairro reaproveitado.';
			}

		if ($neighborhoodCreated)
			{
			return 'Bairro salvo com sucesso.';
			}

		return 'Cidade e bairro já existiam na base.';
		}

	/**
	 * Build the persistence message for automated lookup saving.
	 * @param bool $cityCreated True when the city was created.
	 * @param bool $neighborhoodCreated True when the neighborhood was created.
	 * @param bool $saveCity True when city saving is enabled.
	 * @param bool $saveNeighborhood True when neighborhood saving is enabled.
	 * @return string Message describing the persistence outcome.
	 **/
	private function buildPersistMessage(bool $cityCreated, bool $neighborhoodCreated, bool $saveCity, bool $saveNeighborhood): string
		{
		if (!$saveCity && !$saveNeighborhood)
			{
			return 'Salvamento desativado.';
			}

		if ($saveCity && $saveNeighborhood)
			{
			if ($cityCreated && $neighborhoodCreated)
				{
				return 'Cidade e bairro salvos com sucesso.';
				}

			if ($cityCreated)
				{
				return 'Cidade salva com sucesso e bairro reaproveitado.';
				}

			if ($neighborhoodCreated)
				{
				return 'Bairro salvo com sucesso e cidade reaproveitada.';
				}

			return 'Cidade e bairro já existiam na base.';
			}

		if ($saveCity)
			{
			return $cityCreated ? 'Cidade salva com sucesso.' : 'Cidade já existia na base.';
			}

		if ($saveNeighborhood)
			{
			return $neighborhoodCreated ? 'Bairro salvo com sucesso.' : 'Bairro já existia na base.';
			}

		return 'Salvamento desativado.';
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
	 * Normalize persisted lookup data into the expected internal structure.
	 * @param array $lookupData Lookup data informed to the persistence flow.
	 * @return array Normalized lookup data used by the service.
	 **/
	private function normalizePersistedLookupData(array $lookupData): array
		{
		return [
			'zip_code'     => (string) ($lookupData['zip_code'] ?? ''),
			'street'       => (string) ($lookupData['street'] ?? ''),
			'complement'   => (string) ($lookupData['complement'] ?? ''),
			'neighborhood' => (string) ($lookupData['neighborhood'] ?? ''),
			'city'         => (string) ($lookupData['city'] ?? ''),
			'state'        => strtoupper((string) ($lookupData['state'] ?? '')),
			'state_name'   => (string) ($lookupData['state_name'] ?? ''),
			'region'       => (string) ($lookupData['region'] ?? ''),
			'ibge'         => (string) ($lookupData['ibge'] ?? ''),
			'ddd'          => (string) ($lookupData['ddd'] ?? ''),
			'siafi'        => (string) ($lookupData['siafi'] ?? ''),
		];
		}

	/**
	 * Resolve the first available error message from both providers.
	 * @param array $viaCepResult Lookup result returned by ViaCEP.
	 * @param array $brasilApiResult Lookup result returned by BrasilAPI.
	 * @return string First available error message.
	 **/
	private function firstErrorMessage(array $viaCepResult, array $brasilApiResult): string
		{
		if (!empty($viaCepResult['message']))
			{
			return (string) $viaCepResult['message'];
			}

		if (!empty($brasilApiResult['message']))
			{
			return (string) $brasilApiResult['message'];
			}

		return 'Não foi possível consultar o CEP.';
		}

	/**
	 * Build the standardized failure payload returned by the service.
	 * @param string $error Error identifier for the failure scenario.
	 * @param string $message User-facing error message.
	 * @param int $status HTTP status code associated with the failure.
	 * @param array $data Optional response data included in the failure payload.
	 * @return array Failure payload following the service response contract.
	 **/
	private function failure(string $error, string $message, int $status, array $data = []): array
		{
		return [
			'success' => false,
			'status'  => $status,
			'error'   => $error,
			'message' => $message,
			'data'    => $data,
		];
		}
	}

?>

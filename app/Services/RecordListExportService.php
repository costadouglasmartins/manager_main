<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Bank;
use App\Models\City;
use App\Models\Country;
use App\Models\Email;
use App\Models\FinancialOperation;
use App\Models\FinancialOperationCategory;
use App\Models\FinancialOperationPayment;
use App\Models\Log;
use App\Models\Neighborhood;
use App\Models\Person;
use App\Models\PhoneNumber;
use App\Models\State;
use App\Models\SupportChat;
use App\Models\Tenant;
use App\Models\User;

/**
 * Provides shared definitions, filters, sorting, and row formatting for list exports.
 */
class RecordListExportService
	{
	/**
	 * Checks whether a resource has a configured list export.
	 *
	 * @param string $resource Resource key derived from the controller route.
	 * @return bool
	 */
	public function supports(string $resource): bool
		{
		return isset($this->definitions()[$resource]);
		}

	/**
	 * Builds the shared export payload used by concrete export formats.
	 *
	 * @param string $resource Resource key derived from the controller route.
	 * @param array<string, mixed> $query Query string values.
	 * @return array{definition: array<string, mixed>, filters: array<string, string>, sort: array<string, string>|null, total: int, rows: array<int, array<string, mixed>>}
	 */
	protected function buildExportPayload(string $resource, array $query = []): array
		{
		$definition = $this->definition($resource);
		$filters = $this->extractFilters($query, $definition['filters'] ?? []);
		$sort = $this->extractSort($query, $definition);

		$modelClass = $definition['model'];
		$model = new $modelClass();
		$countMethod = $definition['countMethod'] ?? 'count';
		$searchMethod = $definition['searchMethod'] ?? 'search';

		if (!method_exists($model, $countMethod) || !method_exists($model, $searchMethod))
			{
			throw new \RuntimeException('Export model contract is invalid.');
			}

		$total = (int) $model->{$countMethod}($filters);
		$rows = [];
		if ($total > 0)
			{
			$limit = max(1, $total);
			$rows = !empty($definition['sortableColumns'])
				? $model->{$searchMethod}($filters, $limit, 0, $sort)
				: $model->{$searchMethod}($filters, $limit, 0);
			}

		return [
			'definition' => $definition,
			'filters' => $filters,
			'sort' => $sort,
			'total' => $total,
			'rows' => $rows,
		];
		}

	/**
	 * Returns all supported resource definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function definitions(): array
		{
		return [
			'address' => $this->standardDefinition(Address::class, 'Lista de endereços', 'enderecos', ['id', 'street', 'zip_code', 'person_id', 'person_name'], [Address::class, 'sortableColumns'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'CEP', 'field' => 'zip_code'],
				['label' => 'Logradouro', 'field' => 'street'],
				['label' => 'Número', 'field' => 'number'],
				['label' => 'Pessoa', 'field' => 'person_name'],
			]),
			'bank' => $this->standardDefinition(Bank::class, 'Lista de bancos', 'bancos', ['id', 'number', 'name', 'active'], [Bank::class, 'sortableColumns'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Número', 'field' => 'number'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Status', 'callback' => fn (array $row): string => $this->activeLabel($row['active'] ?? null)],
			]),
			'city' => $this->standardDefinition(City::class, 'Lista de cidades', 'cidades', ['id', 'name', 'country_id', 'country_name', 'state_id', 'state_name'], [City::class, 'sortableColumns'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Estado', 'field' => 'state_name'],
				['label' => 'País', 'field' => 'country_name'],
			]),
			'country' => $this->standardDefinition(Country::class, 'Lista de países', 'paises', ['id', 'name', 'acronym'], [Country::class, 'sortableColumns'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Sigla', 'field' => 'acronym'],
			]),
			'email' => $this->standardDefinition(Email::class, 'Lista de e-mails', 'emails', ['id', 'email', 'person_id', 'person_name'], [Email::class, 'sortableColumns'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'E-mail', 'field' => 'email'],
				['label' => 'Pessoa', 'field' => 'person_name'],
				['label' => 'Observações', 'field' => 'observations'],
			]),
			'financial_operation' => $this->standardDefinition(FinancialOperation::class, 'Lista de operações financeiras', 'operacoes_financeiras', ['id', 'financial_operation_category_id', 'category_name', 'user_id', 'user_name', 'type', 'total_value'], [FinancialOperation::class, 'sortableColumns'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Categoria', 'callback' => fn (array $row): string => $this->categoryLabel($row)],
				['label' => 'Usuário', 'callback' => fn (array $row): string => $this->userLabel($row)],
				['label' => 'Tipo', 'callback' => fn (array $row): string => $this->optionLabel($row['type'] ?? '', FinancialOperation::typeOptions())],
				['label' => 'Valor total', 'callback' => fn (array $row): string => $this->money($row['total_value'] ?? 0), 'align' => 'right'],
				['label' => 'Criado', 'callback' => fn (array $row): string => $this->dateTime($row['created'] ?? '')],
			]),
			'financial_operation_category' => $this->standardDefinition(FinancialOperationCategory::class, 'Lista de categorias financeiras', 'categorias_financeiras', ['id', 'name', 'description'], [FinancialOperationCategory::class, 'sortableColumns'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Descrição', 'field' => 'description'],
			]),
			'financial_operation_payment' => $this->standardDefinition(FinancialOperationPayment::class, 'Lista de pagamentos financeiros', 'pagamentos_financeiros', ['id', 'financial_operation_id', 'financial_operation_type', 'type', 'payment_date', 'due_date_start', 'due_date_end', 'due_date'], [FinancialOperationPayment::class, 'sortableColumns'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Operação', 'callback' => fn (array $row): string => $this->operationLabel($row)],
				['label' => 'Tipo', 'callback' => fn (array $row): string => $this->optionLabel($row['type'] ?? '', FinancialOperationPayment::typeOptions())],
				['label' => 'Valor', 'callback' => fn (array $row): string => $this->money($row['value'] ?? 0), 'align' => 'right'],
				['label' => 'Pagamento', 'callback' => fn (array $row): string => $this->dateTime($row['payment_date'] ?? '')],
				['label' => 'Vencimento', 'callback' => fn (array $row): string => $this->date($row['due_date'] ?? '')],
			]),
			'log' => [
				'model' => Log::class,
				'title' => 'Logs do sistema',
				'filename' => 'logs_sistema',
				'filters' => ['id', 'tenant', 'module', 'action', 'entity_name', 'entity_id', 'performed_by', 'route', 'date_from', 'date_to'],
				'sortableColumns' => [Log::class, 'sortableColumns'],
				'defaultSort' => 'occurred_at',
				'defaultDirection' => 'desc',
				'orientation' => 'landscape',
				'columns' => [
					['label' => '#', 'field' => 'id', 'align' => 'right'],
					['label' => 'Data e hora', 'callback' => fn (array $row): string => $this->dateTime($row['occurred_at'] ?? '')],
					['label' => 'Tenant', 'callback' => fn (array $row): string => $this->logTenantLabel($row)],
					['label' => 'Módulo', 'field' => 'module'],
					['label' => 'Ação', 'callback' => fn (array $row): string => $this->logActionLabel($row['action'] ?? '')],
					['label' => 'Entidade', 'field' => 'entity_name'],
					['label' => 'Registro', 'field' => 'entity_id'],
					['label' => 'Usuário', 'callback' => fn (array $row): string => $this->logUserLabel($row)],
					['label' => 'Rota', 'field' => 'route'],
				],
			],
			'neighborhood' => $this->standardDefinition(Neighborhood::class, 'Lista de bairros', 'bairros', ['id', 'name', 'country_id', 'state_id', 'city_id'], [Neighborhood::class, 'sortableColumns'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Cidade', 'field' => 'city_name'],
				['label' => 'Estado', 'field' => 'state_name'],
			]),
			'person' => $this->standardDefinition(Person::class, 'Lista de pessoas', 'pessoas', ['id', 'name', 'type', 'document', 'birthdate', 'active'], [Person::class, 'sortableColumns'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Documento', 'callback' => fn (array $row): string => \maskCpfCnpj((string) ($row['document'] ?? ''))],
				['label' => 'Data de nascimento', 'callback' => fn (array $row): string => $this->date($row['birthdate'] ?? '')],
				['label' => 'Tipo', 'callback' => fn (array $row): string => $this->personTypeLabel($row['type'] ?? '')],
				['label' => 'Status', 'callback' => fn (array $row): string => $this->activeLabel($row['active'] ?? null)],
			]),
			'phone_number' => $this->standardDefinition(PhoneNumber::class, 'Lista de telefones', 'telefones', ['id', 'phone_number', 'person_id', 'person_name'], [PhoneNumber::class, 'sortableColumns'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Telefone', 'callback' => fn (array $row): string => \maskPhoneBr((string) ($row['phone_number'] ?? ''))],
				['label' => 'Pessoa', 'field' => 'person_name'],
				['label' => 'Observações', 'field' => 'observations'],
			]),
			'state' => $this->standardDefinition(State::class, 'Lista de estados', 'estados', ['id', 'name', 'acronym', 'country_id', 'country_name'], [State::class, 'sortableColumns'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Nome', 'field' => 'name'],
				['label' => 'Sigla', 'field' => 'acronym'],
				['label' => 'País', 'field' => 'country_name'],
			]),
			'tenant' => $this->standardDefinition(Tenant::class, 'Lista de tenants', 'tenants', ['id', 'name', 'database_number', 'status'], [Tenant::class, 'sortableColumns'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Pessoa associada', 'callback' => fn (array $row): string => (string) ($row['associated_person_name'] ?? ('Tenant #' . (int) ($row['id'] ?? 0)))],
				['label' => 'Banco', 'callback' => fn (array $row): string => 'manager_' . (string) ($row['database_number'] ?? '')],
				['label' => 'Situação', 'callback' => fn (array $row): string => $this->tenantStatusLabel($row['status'] ?? '')],
			]),
			'user' => $this->standardDefinition(User::class, 'Lista de usuários', 'usuarios', ['id', 'username', 'type', 'active'], [User::class, 'sortableColumns'], [
				['label' => '#', 'field' => 'id', 'align' => 'right'],
				['label' => 'Usuário', 'field' => 'username'],
				['label' => 'Tipo', 'callback' => fn (array $row): string => $this->userTypeLabel($row['type'] ?? '')],
				['label' => 'Status', 'callback' => fn (array $row): string => $this->activeLabel($row['active'] ?? null)],
			]),
			'support_chat' => [
				'model' => SupportChat::class,
				'title' => 'Chats de suporte',
				'filename' => 'chats_suporte',
				'filters' => ['date_from', 'date_to', 'status', 'participant'],
				'searchMethod' => 'searchChats',
				'countMethod' => 'countChats',
				'orientation' => 'landscape',
				'columns' => [
					['label' => '#', 'field' => 'id', 'align' => 'right'],
					['label' => 'Data', 'callback' => fn (array $row): string => $this->dateTime($row['created'] ?? '')],
					['label' => 'Situação', 'callback' => fn (array $row): string => $this->supportStatusLabel($row['status'] ?? '')],
					['label' => 'Cliente', 'callback' => fn (array $row): string => $this->personUsernameLabel($row['customer_name'] ?? '', $row['customer_username'] ?? '')],
					['label' => 'Tenant', 'callback' => fn (array $row): string => $this->supportTenantLabel($row)],
					['label' => 'Atendente', 'callback' => fn (array $row): string => $this->personUsernameLabel($row['attendant_name'] ?? '', $row['attendant_username'] ?? 'Sem atendente')],
					['label' => 'Última mensagem', 'callback' => fn (array $row): string => $this->dateTime($row['last_message_at'] ?? ($row['created'] ?? ''))],
				],
			],
		];
		}

	/**
	 * Builds a standard model-backed definition.
	 *
	 * @param string $modelClass Model class name.
	 * @param string $title Export title.
	 * @param string $filename Filename base.
	 * @param array<int, string> $filters Accepted filter keys.
	 * @param array<int, string> $sortableColumns Sortable column provider.
	 * @param array<int, array<string, mixed>> $columns Export columns.
	 * @return array<string, mixed>
	 */
	private function standardDefinition(
		string $modelClass,
		string $title,
		string $filename,
		array $filters,
		array $sortableColumns,
		array $columns
	): array
		{
		return [
			'model' => $modelClass,
			'title' => $title,
			'filename' => $filename,
			'filters' => $filters,
			'sortableColumns' => $sortableColumns,
			'defaultSort' => 'id',
			'defaultDirection' => 'desc',
			'orientation' => 'landscape',
			'columns' => $columns,
		];
		}

	/**
	 * Returns one resource definition.
	 *
	 * @param string $resource Resource key.
	 * @return array<string, mixed>
	 */
	private function definition(string $resource): array
		{
		$definitions = $this->definitions();
		if (!isset($definitions[$resource]))
			{
			throw new \InvalidArgumentException('List export is not configured for this resource.');
			}

		return $definitions[$resource];
		}

	/**
	 * Extracts only configured filter keys from the request query.
	 *
	 * @param array<string, mixed> $query Request query.
	 * @param array<int, string> $keys Accepted filter keys.
	 * @return array<string, string>
	 */
	private function extractFilters(array $query, array $keys): array
		{
		$filters = [];
		foreach ($keys as $key)
			{
			$filters[$key] = isset($query[$key]) ? trim((string) $query[$key]) : '';
			}

		return $filters;
		}

	/**
	 * Extracts the current sort state when the list supports sorting.
	 *
	 * @param array<string, mixed> $query Request query.
	 * @param array<string, mixed> $definition Resource definition.
	 * @return array<string, string>|null
	 */
	private function extractSort(array $query, array $definition): ?array
		{
		if (empty($definition['sortableColumns']) || !is_callable($definition['sortableColumns']))
			{
			return null;
			}

		$columns = call_user_func($definition['sortableColumns']);
		return \sortRequest(
			$columns,
			(string) ($definition['defaultSort'] ?? 'id'),
			(string) ($definition['defaultDirection'] ?? 'desc'),
			$query
		);
		}

	/**
	 * Resolves a formatted column value for one row.
	 *
	 * @param array<string, mixed> $column Column definition.
	 * @param array<string, mixed> $row Row data.
	 * @return string
	 */
	protected function columnValue(array $column, array $row): string
		{
		if (!empty($column['callback']) && is_callable($column['callback']))
			{
			return $this->stringValue(call_user_func($column['callback'], $row));
			}

		$field = (string) ($column['field'] ?? '');
		return $this->stringValue($field !== '' ? ($row[$field] ?? '') : '');
		}

	/**
	 * Builds a safe timestamped filename.
	 *
	 * @param string $base Filename base.
	 * @param string $extension Filename extension.
	 * @return string
	 */
	protected function filename(string $base, string $extension = 'pdf'): string
		{
		$base = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $base) ?: 'registros';
		$extension = preg_replace('/[^a-zA-Z0-9]+/', '', $extension) ?: 'pdf';
		return strtolower(trim($base, '_')) . '_' . date('Ymd_His') . '.' . strtolower($extension);
		}

	/**
	 * Converts technical filter keys to readable labels.
	 */
	protected function humanLabel(string $key): string
		{
		$labels = [
			'id' => 'Código',
			'name' => 'Nome',
			'number' => 'Número',
			'acronym' => 'Sigla',
			'active' => 'Status',
			'status' => 'Situação',
			'type' => 'Tipo',
			'document' => 'Documento',
			'birthdate' => 'Data de nascimento',
			'zip_code' => 'CEP',
			'street' => 'Logradouro',
			'person_id' => 'Pessoa',
			'person_name' => 'Pessoa',
			'country_id' => 'País',
			'country_name' => 'País',
			'state_id' => 'Estado',
			'state_name' => 'Estado',
			'city_id' => 'Cidade',
			'database_number' => 'Banco',
			'username' => 'Usuário',
			'email' => 'E-mail',
			'description' => 'Descrição',
			'financial_operation_category_id' => 'Categoria',
			'category_name' => 'Categoria',
			'user_id' => 'Usuário',
			'user_name' => 'Usuário',
			'total_value' => 'Valor total',
			'financial_operation_id' => 'Operação',
			'financial_operation_type' => 'Tipo da operação',
			'payment_date' => 'Pagamento',
			'due_date' => 'Vencimento',
			'due_date_start' => 'Vencimento inicial',
			'due_date_end' => 'Vencimento final',
			'date_from' => 'Data inicial',
			'date_to' => 'Data final',
			'participant' => 'Participante',
			'tenant' => 'Tenant',
			'module' => 'Módulo',
			'action' => 'Ação',
			'entity_name' => 'Entidade',
			'entity_id' => 'Registro',
			'performed_by' => 'Usuário',
			'route' => 'Rota',
		];

		return $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
		}

	/**
	 * Formats any scalar value as a string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function stringValue($value): string
		{
		if ($value === null)
			{
			return '';
			}

		if (is_bool($value))
			{
			return $value ? 'Sim' : 'Não';
			}

		if (is_array($value))
			{
			return implode(', ', array_map([$this, 'stringValue'], $value));
			}

		return (string) $value;
		}

	private function activeLabel($value): string
		{
		return ((string) $value === '1') ? 'Ativo' : 'Inativo';
		}

	private function tenantStatusLabel($value): string
		{
		return [
			'active' => 'Ativo',
			'suspended' => 'Suspenso',
			'pending' => 'Pendente',
		][(string) $value] ?? (string) $value;
		}

	private function supportStatusLabel($value): string
		{
		return [
			'pending' => 'Pendente',
			'open' => 'Em aberto',
			'closed' => 'Finalizado',
		][(string) $value] ?? (string) $value;
		}

	private function userTypeLabel($value): string
		{
		return [
			'root' => 'ROOT',
			'admin' => 'ADMIN',
			'user' => 'USUÁRIO(A)',
		][(string) $value] ?? (string) $value;
		}

	private function personTypeLabel($value): string
		{
		return [
			'individual' => 'Pessoa física',
			'company' => 'Pessoa jurídica',
		][(string) $value] ?? (string) $value;
		}

	private function optionLabel($value, array $options): string
		{
		return (string) ($options[(string) $value] ?? $value);
		}

	private function money($value): string
		{
		return 'R$ ' . number_format((float) $value, 2, ',', '.');
		}

	private function date($value): string
		{
		return \maskDateBr($this->stringValue($value)) ?? '';
		}

	private function dateTime($value): string
		{
		return \maskDateTimeBr($this->stringValue($value));
		}

	private function categoryLabel(array $row): string
		{
		$label = trim((string) ($row['category_name'] ?? ''));
		if (!empty($row['category_description']))
			{
			$label .= ' - ' . trim((string) $row['category_description']);
			}

		return $label;
		}

	private function userLabel(array $row): string
		{
		return $this->personUsernameLabel($row['user_name'] ?? '', $row['username'] ?? '');
		}

	private function personUsernameLabel($name, $username): string
		{
		$label = trim((string) $name);
		$username = trim((string) $username);
		if ($username !== '')
			{
			$label .= ($label !== '' ? ' ' : '') . '(' . $username . ')';
			}

		return $label;
		}

	private function operationLabel(array $row): string
		{
		$label = '#' . (int) ($row['financial_operation_id'] ?? 0);
		$operationType = $this->optionLabel($row['financial_operation_type'] ?? '', FinancialOperation::typeOptions());
		if ($operationType !== '')
			{
			$label .= ' - ' . $operationType;
			}
		if (!empty($row['financial_operation_category_name']))
			{
			$label .= ' - ' . (string) $row['financial_operation_category_name'];
			}

		return $label;
		}

	private function supportTenantLabel(array $row): string
		{
		$tenantName = trim((string) ($row['tenant_name'] ?? ''));
		if ($tenantName !== '')
			{
			return $tenantName;
			}

		$databaseNumber = trim((string) ($row['tenant_database_number'] ?? ''));
		return $databaseNumber !== '' ? 'Tenant ' . $databaseNumber : '';
		}

	/**
	 * Returns the tenant label used in log exports.
	 *
	 * @param array<string, mixed> $row Export row.
	 * @return string
	 */
	private function logTenantLabel(array $row): string
		{
		$tenantName = trim((string) ($row['tenant_name_snapshot'] ?? ''));
		if ($tenantName !== '')
			{
			return $tenantName;
			}

		$tenantDatabase = trim((string) ($row['tenant_database'] ?? ''));
		return $tenantDatabase !== '' ? $tenantDatabase : 'Sem tenant';
		}

	/**
	 * Returns the user label used in log exports.
	 *
	 * @param array<string, mixed> $row Export row.
	 * @return string
	 */
	private function logUserLabel(array $row): string
		{
		$userName = trim((string) ($row['performed_by_name'] ?? ''));
		if ($userName !== '')
			{
			return $userName;
			}

		$username = trim((string) ($row['performed_by_username'] ?? ''));
		return $username !== '' ? $username : 'Sistema';
		}

	/**
	 * Returns the localized action label used in log exports.
	 *
	 * @param string $action Raw action value.
	 * @return string
	 */
	private function logActionLabel(string $action): string
		{
		return Log::actionLabels()[$action] ?? $action;
		}
	}

?>

<?php
// Expected variables:
// $permissions, $selectedUserId, $selectedUser, $userPermissionIds, $effectiveAbilities

function permGroup(string $ability): string
	{
	$pos = strpos($ability, '.');
	if ($pos === false)
		{
		return 'outros';
		}
	return substr($ability, 0, $pos);
	}

function permGroupLabel(string $group): string
	{
	return ucwords(str_replace(['_', '-'], ' ', $group));
	}

function permSlug(string $value): string
	{
	$slug = strtolower($value);
	$slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
	$slug = trim($slug, '-');

	return $slug !== '' ? $slug : 'item';
	}

function permSystemBadgeClass(string $system): string
	{
	return $system === 'manager' ? 'text-bg-success' : 'text-bg-primary';
	}

function permSystemIcon(string $system): string
	{
	return $system === 'manager' ? 'bi-diagram-3' : 'bi-grid-3x3-gap';
	}

// Organiza: [system][module] => [rows]
$bySystem = [
	'manager_main' => [],
	'manager'      => [],
];

foreach ($permissions as $p)
	{
	$ability = (string) $p['ability'];
	$system  = (string) ($p['system'] ?? 'manager_main');
	if (!isset($bySystem[$system]))
		{
		$bySystem[$system] = [];
		}

	$group = permGroup($ability);
	if (!isset($bySystem[$system][$group]))
		{
		$bySystem[$system][$group] = [];
		}

	$bySystem[$system][$group][] = $p;
	}

foreach ($bySystem as $sys => $_groups)
	{
	ksort($bySystem[$sys]);
	}

// Deriva o sistema de origem do usuário selecionado (manager_main se central, manager se pertence a tenant).
$userOriginSystem = null;
if (!empty($selectedUser))
	{
	$userOriginSystem = $selectedUser['origin_system']
		?? (empty($selectedUser['tenant_id']) ? 'manager_main' : 'manager');
	}

if ($userOriginSystem === null && (int) ($selectedUserId ?? 0) > 0)
	{
	$userOriginSystem = 'manager_main';
	}

$userPermissionLookup = [];
foreach ($userPermissionIds as $permissionId)
	{
	$userPermissionLookup[(int) $permissionId] = true;
	}

$systemStats = [];
$totalCatalogPermissions = 0;
$totalGrantedPermissions = 0;
$totalModules = 0;
$totalSystems = 0;

foreach ($bySystem as $systemName => $groups)
	{
	if (empty($groups))
		{
		continue;
		}

	$totalSystems += 1;
	$systemTotal = 0;
	$systemChecked = 0;
	$groupStats = [];

	foreach ($groups as $groupName => $items)
		{
		$groupTotal = count($items);
		$groupChecked = 0;

		foreach ($items as $item)
			{
			if (isset($userPermissionLookup[(int) $item['id']]))
				{
				$groupChecked += 1;
				}
			}

		$groupStats[$groupName] = [
			'total'   => $groupTotal,
			'checked' => $groupChecked,
		];

		$systemTotal += $groupTotal;
		$systemChecked += $groupChecked;
		$totalModules += 1;
		}

	$systemStats[$systemName] = [
		'total'      => $systemTotal,
		'checked'    => $systemChecked,
		'groups'     => count($groups),
		'groupStats' => $groupStats,
	];

	$totalCatalogPermissions += $systemTotal;
	$totalGrantedPermissions += $systemChecked;
	}

$syncCount = null;
if (isset($_GET['synced']))
	{
	$syncCount = (int) $_GET['synced'];
	}

$created = null;
if (isset($_GET['created']))
	{
	$created = (int) $_GET['created'];
	}
?>
<!-- CSS -->
<link rel="stylesheet" href="<?= url('assets/css/permissions/index.css?v=1'); ?>">
<!-- /CSS -->
<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Permissões</li>
  </ol>
</nav>

<h5 class="mb-3">Gerenciar permissões (por usuário)</h5>

<?php if ($syncCount !== null): ?>
  <div class="alert <?= ($syncCount > 0 ? 'alert-success' : 'alert-warning'); ?> py-2">
    <?= ($syncCount > 0 ? ('Sincronização concluída: ' . (int) $syncCount . ' permissões adicionadas ao catálogo.') : 'Sincronização concluída: nenhuma permissão nova foi encontrada.'); ?>
  </div>
<?php endif; ?>

<?php if ($created !== null): ?>
  <div class="alert <?= ($created === 1 ? 'alert-success' : 'alert-danger'); ?> py-2">
    <?= ($created === 1 ? 'Permissão adicionada ao catálogo.' : 'Não foi possível adicionar (já existe ou ability inválida).'); ?>
  </div>
<?php endif; ?>

<div class="permissions-shell">
  <div class="card shadow-sm border-0 permission-toolbar-card mb-4">
    <div class="card-body p-3 p-lg-4">
      <div class="row g-4 align-items-start">
        <div class="col-12 col-xl-7">
          <div class="d-flex align-items-start gap-3 mb-3">
            <div class="permission-system-icon">
              <i class="bi bi-shield-lock"></i>
            </div>
            <div>
              <span class="permission-kicker">Painel de acesso</span>
              <div class="fw-semibold mb-1">Busque um usuário e distribua acessos por módulo</div>
              <p class="text-muted mb-0">
                O catálogo agora aparece em acordeão por sistema e cada permissão fica em um card clicável,
                facilitando leitura rápida no desktop e no mobile.
              </p>
            </div>
          </div>

          <label for="userSearchInput" class="form-label fw-semibold mb-1">Buscar usuário</label>
          <small class="text-muted d-block mb-2">Digite nome, e-mail ou username para abrir o catálogo do usuário.</small>
          <div class="position-relative" id="userSearchWrapper">
            <input
              type="text"
              id="userSearchInput"
              class="form-control"
              placeholder="Digite o nome, e-mail ou username do usuário..."
              autocomplete="off"
            >
            <div id="userSearchResults" class="list-group position-absolute w-100 shadow-sm permission-results" style="z-index:1050; max-height:300px; overflow-y:auto; display:none;"></div>
          </div>
        </div>

        <div class="col-12 col-xl-5">
          <div class="border rounded-4 p-3 h-100 bg-body">
            <span class="permission-kicker">Ações rápidas</span>
            <div class="d-flex flex-wrap gap-2 mb-3">
              <form method="POST" action="<?= url('permission/sync'); ?>" class="d-inline">
                <input type="hidden" name="selected_user_id" value="<?= (int) ($selectedUserId ?? 0); ?>">
                <button class="btn btn-primary" type="submit">
                  Sincronizar permissões
                </button>
              </form>

              <a href="<?= url('permission/orphaned'); ?>" class="btn btn-warning">
                Permissões órfãs
              </a>

              <button class="btn btn-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#addPermCatalog" aria-expanded="false" aria-controls="addPermCatalog">
                Adicionar manualmente
              </button>
            </div>

            <div class="row g-3">
              <div class="col-6">
                <div class="permission-stat-card p-3">
                  <span class="permission-stat-label">Catálogo</span>
                  <span class="permission-stat-value"><?= (int) $totalCatalogPermissions; ?></span>
                  <small class="text-muted">permissões catalogadas</small>
                </div>
              </div>
              <div class="col-6">
                <div class="permission-stat-card p-3">
                  <span class="permission-stat-label">Módulos</span>
                  <span class="permission-stat-value"><?= (int) $totalModules; ?></span>
                  <small class="text-muted">grupos organizados</small>
                </div>
              </div>
              <div class="col-12">
                <div class="permission-stat-card p-3">
                  <span class="permission-stat-label">Sistemas</span>
                  <span class="permission-stat-value"><?= (int) $totalSystems; ?></span>
                  <small class="text-muted">catálogos separados por origem</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="collapse mb-4" id="addPermCatalog">
    <div class="card shadow-sm border-0">
      <div class="card-body p-3">
        <form method="POST" action="<?= url('permission/create'); ?>" class="row g-2 align-items-end">
          <input type="hidden" name="selected_user_id" value="<?= (int) ($selectedUserId ?? 0); ?>">
          <div class="col-12 col-md-3">
            <label class="form-label">Sistema</label>
            <select class="form-select form-select-sm" name="system" required>
              <option value="manager_main">manager_main</option>
              <option value="manager">manager</option>
            </select>
          </div>
          <div class="col-12 col-md-3">
            <label class="form-label">Nova ability</label>
            <input class="form-control form-control-sm" name="ability" placeholder="ex.: report.index" required>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label">Descrição</label>
            <input class="form-control form-control-sm" name="description" placeholder="Opcional">
          </div>
          <div class="col-12 col-md-2">
            <button class="btn btn-sm btn-primary w-100" type="submit">Adicionar</button>
          </div>
          <div class="col-12">
            <small class="text-muted">
              Dica: depois de criar a ability, conceda-a ao usuário abaixo para efetivar o acesso.
            </small>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php if ($selectedUser): ?>
    <div class="card shadow-sm border-0 permission-user-card mb-4">
      <div class="card-body p-3 p-lg-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
          <div>
            <span class="permission-kicker">Usuário selecionado</span>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <span class="fs-5 fw-semibold"><?= htmlspecialchars((string) ($selectedUser['name'] ?? $selectedUser['username'])); ?></span>
              <span class="badge text-bg-info"><?= htmlspecialchars((string) $selectedUser['type']); ?></span>
              <span class="badge <?= permSystemBadgeClass((string) $userOriginSystem); ?>" title="Sistema de origem do usuário">
                <?= htmlspecialchars((string) $userOriginSystem); ?>
              </span>
            </div>
            <div class="text-muted">
              <?= htmlspecialchars((string) $selectedUser['email']); ?>
              <?php if (!empty($selectedUser['username']) && ($selectedUser['username'] !== ($selectedUser['name'] ?? ''))): ?>
                <span class="ms-2">(<?= htmlspecialchars($selectedUser['username']); ?>)</span>
              <?php endif; ?>
            </div>
          </div>

          <a href="<?= url('permission'); ?>" class="btn btn-sm btn-outline-secondary" title="Limpar seleção">
            Limpar seleção
          </a>
        </div>

        <div class="row g-3 mt-1">
          <div class="col-12 col-sm-6 col-xl-4">
            <div class="permission-stat-card p-3">
              <span class="permission-stat-label">Concedidas</span>
              <span class="permission-stat-value" data-total-granted-count><?= (int) $totalGrantedPermissions; ?></span>
              <small class="text-muted">permissões marcadas agora</small>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-xl-4">
            <div class="permission-stat-card p-3">
              <span class="permission-stat-label">Catálogo</span>
              <span class="permission-stat-value"><?= (int) $totalCatalogPermissions; ?></span>
              <small class="text-muted">itens disponíveis no painel</small>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-xl-4">
            <div class="permission-stat-card p-3">
              <span class="permission-stat-label">Módulos</span>
              <span class="permission-stat-value"><?= (int) $totalModules; ?></span>
              <small class="text-muted">agrupamentos no acordeão</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if ((int) $selectedUserId <= 0): ?>
    <div class="card shadow-sm border-0 permission-empty-card">
      <div class="card-body py-5 px-4">
        <div class="permission-empty-icon">
          <i class="bi bi-person-badge"></i>
        </div>
        <div class="fw-semibold mb-2">Selecione um usuário para começar</div>
        <div class="text-muted">
          Depois disso, o catálogo de permissões aparece organizado por sistema, módulo e cards clicáveis.
        </div>
      </div>
    </div>
  <?php else: ?>

    <?php
    // Toggle de filtro por sistema.
    // Se o usuário é do manager_main, por padrão o bloco "manager" fica oculto (e vice-versa).
    // O checkbox abaixo permite ao operador exibir também as permissões do outro sistema.
    $otherSystem = ($userOriginSystem === 'manager') ? 'manager_main' : 'manager';
    $selectedUserType = (string) ($selectedUser['type'] ?? '');
    $canShowOtherSystemToggle = !($otherSystem === 'manager_main' && $selectedUserType !== 'root');
    ?>

    <div class="card shadow-sm border-0 permission-filter-card mb-4">
      <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
          <div class="fw-semibold mb-1">Visualização do catálogo</div>
          <div class="text-muted small">
            Abra ou feche todos os módulos de uma vez e, quando permitido, exiba também o catálogo complementar.
          </div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <div class="btn-group" role="group" aria-label="Controles de acordeão">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-permission-accordion-action="expand">
              Abrir todos
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-permission-accordion-action="collapse">
              Fechar todos
            </button>
          </div>
          <?php if ($canShowOtherSystemToggle): ?>
            <span class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" role="switch" id="toggleOtherSystem">
              <label class="form-check-label" for="toggleOtherSystem">
                Mostrar também permissões do sistema
                <span class="badge <?= permSystemBadgeClass($otherSystem); ?>"><?= htmlspecialchars($otherSystem); ?></span>
              </label>
            </span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <form method="POST" action="<?= url('permission/saveUser'); ?>">
      <input type="hidden" name="user_id" value="<?= (int) $selectedUserId; ?>">

      <?php foreach ($bySystem as $systemName => $groups): ?>
        <?php
          if (empty($groups))
            {
            continue;
            }

          $systemStat = $systemStats[$systemName] ?? ['total' => 0, 'checked' => 0, 'groups' => 0, 'groupStats' => []];

          // Oculta por padrão o bloco do "outro" sistema; o toggle acima mostra.
          $isOtherSystem = ($systemName !== $userOriginSystem);
          $sectionStyle = $isOtherSystem ? 'display:none;' : '';
        ?>
        <section class="mb-4 perm-system-block" data-system="<?= htmlspecialchars($systemName); ?>" style="<?= $sectionStyle; ?>">
          <div class="card shadow-sm border-0 permission-system-card" data-system="<?= htmlspecialchars($systemName); ?>">
            <div class="card-header py-3 px-3 px-lg-4">
              <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                  <div class="permission-system-icon">
                    <i class="bi <?= permSystemIcon($systemName); ?>"></i>
                  </div>
                  <div>
                    <div class="fw-semibold d-flex flex-wrap align-items-center gap-2">
                      Sistema
                      <span class="badge <?= permSystemBadgeClass($systemName); ?>"><?= htmlspecialchars($systemName); ?></span>
                    </div>
                    <div class="permission-system-meta">
                      <?= (int) $systemStat['groups']; ?> módulo(s) organizados em acordeão
                    </div>
                  </div>
                </div>

                <div class="d-flex flex-wrap align-items-center gap-2">
                  <span class="badge text-bg-light border">
                    <span data-system-selected-count="<?= htmlspecialchars($systemName); ?>"><?= (int) $systemStat['checked']; ?></span>/<?= (int) $systemStat['total']; ?> concedidas
                  </span>
                  <span class="form-check form-switch mb-0">
                    <input class="form-check-input system-toggle-checkbox" type="checkbox" id="toggle-system-<?= htmlspecialchars(permSlug($systemName)); ?>" data-system-toggle="<?= htmlspecialchars($systemName); ?>">
                    <label class="form-check-label small" for="toggle-system-<?= htmlspecialchars(permSlug($systemName)); ?>">
                      Marcar/desmarcar todas
                    </label>
                  </span>
                </div>
              </div>
            </div>

            <div class="card-body p-3 p-lg-4">
              <div class="accordion permission-accordion" id="accordion-<?= htmlspecialchars(permSlug($systemName)); ?>">
                <?php foreach ($groups as $groupName => $items): ?>
                  <?php
                    $groupStat = $systemStat['groupStats'][$groupName] ?? ['total' => count($items), 'checked' => 0];
                    $groupKey = $systemName . '::' . $groupName;
                    $groupSlug = permSlug($systemName . '-' . $groupName);
                    $headingId = 'heading-' . $groupSlug;
                    $collapseId = 'collapse-' . $groupSlug;
                    $isGroupOpen = false;
                  ?>
                  <div class="accordion-item" data-group-section="<?= htmlspecialchars($groupKey); ?>">
                    <h2 class="accordion-header" id="<?= htmlspecialchars($headingId); ?>">
                      <button class="accordion-button <?= ($isGroupOpen ? '' : 'collapsed'); ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($collapseId); ?>" aria-expanded="<?= ($isGroupOpen ? 'true' : 'false'); ?>" aria-controls="<?= htmlspecialchars($collapseId); ?>">
                        <span class="d-flex flex-wrap align-items-center gap-2 w-100 pe-3">
                          <span><?= htmlspecialchars(permGroupLabel($groupName)); ?></span>
                          <span class="badge text-bg-secondary"><?= (int) count($items); ?> permissões</span>
                          <span class="badge text-bg-light border">
                            <span data-group-selected-count="<?= htmlspecialchars($groupKey); ?>"><?= (int) $groupStat['checked']; ?></span>/<?= (int) $groupStat['total']; ?> selecionadas
                          </span>
                        </span>
                      </button>
                    </h2>
                    <div id="<?= htmlspecialchars($collapseId); ?>" class="accordion-collapse collapse <?= ($isGroupOpen ? 'show' : ''); ?>" aria-labelledby="<?= htmlspecialchars($headingId); ?>">
                      <div class="accordion-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                          <div class="text-muted small">
                            Prefixo <code><?= htmlspecialchars($groupName); ?>.*</code> com controle individual por ação.
                          </div>
                          <span class="form-check mb-0">
                            <input class="form-check-input group-toggle-checkbox" type="checkbox" id="toggle-group-<?= htmlspecialchars($groupSlug); ?>" data-system-toggle="<?= htmlspecialchars($systemName); ?>" data-group-toggle="<?= htmlspecialchars($groupName); ?>">
                            <label class="form-check-label small" for="toggle-group-<?= htmlspecialchars($groupSlug); ?>">
                              Marcar/desmarcar módulo
                            </label>
                          </span>
                        </div>

                        <div class="row g-3">
                          <?php foreach ($items as $p): ?>
                            <?php
                              $pid = (int) $p['id'];
                              $checked = isset($userPermissionLookup[$pid]);
                              $psys = (string) ($p['system'] ?? 'manager_main');
                              $description = trim((string) ($p['description'] ?? ''));
                              $inputId = 'perm-' . $pid;
                            ?>
                            <div class="col-12 col-md-6 col-xxl-4">
                              <label class="permission-choice">
                                <input
                                  id="<?= htmlspecialchars($inputId); ?>"
                                  class="form-check-input permission-checkbox permission-choice-input"
                                  type="checkbox"
                                  name="permission_ids[]"
                                  value="<?= $pid; ?>"
                                  data-permission-system="<?= htmlspecialchars($systemName); ?>"
                                  data-permission-group="<?= htmlspecialchars($groupName); ?>"
                                  <?= ($checked ? 'checked' : ''); ?>
                                >
                                <span class="permission-choice-body">
                                  <span class="permission-choice-meta">
                                    <span class="badge <?= permSystemBadgeClass($psys); ?>"><?= htmlspecialchars($psys); ?></span>
                                    <span class="badge rounded-pill permission-state-badge <?= ($checked ? 'text-bg-success' : 'text-bg-light border'); ?>">
                                      <?= ($checked ? 'Concedida' : 'Disponível'); ?>
                                    </span>
                                  </span>
                                  <span class="permission-choice-title">
                                    <code><?= htmlspecialchars($p['ability']); ?></code>
                                  </span>
                                  <span class="permission-choice-description<?= ($description === '' ? ' permission-empty-description' : ''); ?>">
                                    <?= htmlspecialchars($description !== '' ? $description : 'Sem descrição informada.'); ?>
                                  </span>
                                  <span class="permission-choice-footer">
                                    <span>Acesso individual</span>
                                    <span class="permission-choice-hint"><?= ($checked ? 'Clique para remover' : 'Clique para conceder'); ?></span>
                                  </span>
                                </span>
                              </label>
                            </div>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </section>
      <?php endforeach; ?>

      <div class="d-flex flex-wrap gap-2 align-items-center mt-4">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i> Salvar permissões
        </button>
        <a class="btn btn-secondary" href="<?= url('permission?user_id=' . (int) $selectedUserId); ?>">
          Recarregar
        </a>
        <span class="badge text-bg-light border ms-sm-auto px-3 py-2">
          <span data-total-granted-count><?= (int) $totalGrantedPermissions; ?></span> permissão(ões) marcada(s)
        </span>
        <button class="btn btn-dark" type="button" data-bs-toggle="collapse" data-bs-target="#effectivePerms" aria-expanded="false" aria-controls="effectivePerms">
          Ver permissões ativas
        </button>
      </div>

      <div class="collapse mt-3" id="effectivePerms">
        <div class="card shadow-sm border-0 permission-effective-card">
          <div class="card-body p-3">
            <div class="fw-semibold mb-2">Permissões ativas do usuário</div>
            <?php if (empty($effectiveAbilities)): ?>
              <div class="text-muted">Nenhuma permissão ativa encontrada.</div>
            <?php else: ?>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($effectiveAbilities as $ab): ?>
                  <span class="badge text-bg-secondary"><code class="text-white"><?= htmlspecialchars($ab); ?></code></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </form>

  <?php endif; ?>
</div>

<script>
(function() {
  var input = document.getElementById('userSearchInput');
  var resultsDiv = document.getElementById('userSearchResults');
  var debounceTimer = null;
  var baseUrl = '<?= url("permission/searchUsers"); ?>';
  var permUrl = '<?= url("permission"); ?>';

  function updatePermissionChoiceState(permissionCheckbox) {
    var choice = permissionCheckbox.closest('.permission-choice');
    if (!choice) {
      return;
    }

    var stateBadge = choice.querySelector('.permission-state-badge');
    if (stateBadge) {
      stateBadge.textContent = permissionCheckbox.checked ? 'Concedida' : 'Disponível';
      stateBadge.className = 'badge rounded-pill permission-state-badge ' + (permissionCheckbox.checked ? 'text-bg-success' : 'text-bg-light border');
    }

    var hint = choice.querySelector('.permission-choice-hint');
    if (hint) {
      hint.textContent = permissionCheckbox.checked ? 'Clique para remover' : 'Clique para conceder';
    }
  }

  function countChecked(checkboxes) {
    var checkedCount = 0;

    checkboxes.forEach(function(checkbox) {
      if (checkbox.checked) {
        checkedCount += 1;
      }
    });

    return checkedCount;
  }

  function getSystemPermissionCheckboxes(systemName) {
    return document.querySelectorAll('.permission-checkbox[data-permission-system="' + systemName + '"]');
  }

  function getGroupPermissionCheckboxes(systemName, groupName) {
    return document.querySelectorAll('.permission-checkbox[data-permission-system="' + systemName + '"][data-permission-group="' + groupName + '"]');
  }

  function syncBulkToggleState(toggleCheckbox, permissionCheckboxes) {
    if (!toggleCheckbox) {
      return;
    }

    if (permissionCheckboxes.length === 0) {
      toggleCheckbox.checked = false;
      toggleCheckbox.indeterminate = false;
      toggleCheckbox.disabled = true;
      return;
    }

    var checkedCount = countChecked(permissionCheckboxes);
    toggleCheckbox.disabled = false;
    toggleCheckbox.checked = checkedCount === permissionCheckboxes.length;
    toggleCheckbox.indeterminate = checkedCount > 0 && checkedCount < permissionCheckboxes.length;
  }

  function refreshSelectionMetrics() {
    var permissionCheckboxes = document.querySelectorAll('.permission-checkbox');
    var groupToggleCheckboxes = document.querySelectorAll('.group-toggle-checkbox');
    var systemToggleCheckboxes = document.querySelectorAll('.system-toggle-checkbox');
    var totalGrantedCount = countChecked(permissionCheckboxes);

    permissionCheckboxes.forEach(function(permissionCheckbox) {
      updatePermissionChoiceState(permissionCheckbox);
    });

    groupToggleCheckboxes.forEach(function(groupToggle) {
      var systemName = groupToggle.getAttribute('data-system-toggle');
      var groupName = groupToggle.getAttribute('data-group-toggle');
      var groupKey = systemName + '::' + groupName;
      var groupCheckboxes = getGroupPermissionCheckboxes(systemName, groupName);
      var checkedCount = countChecked(groupCheckboxes);
      var groupCountTargets = document.querySelectorAll('[data-group-selected-count="' + groupKey + '"]');

      groupCountTargets.forEach(function(target) {
        target.textContent = checkedCount;
      });

      syncBulkToggleState(groupToggle, groupCheckboxes);
    });

    systemToggleCheckboxes.forEach(function(systemToggle) {
      var systemName = systemToggle.getAttribute('data-system-toggle');
      var systemCheckboxes = getSystemPermissionCheckboxes(systemName);
      var checkedCount = countChecked(systemCheckboxes);
      var systemCountTargets = document.querySelectorAll('[data-system-selected-count="' + systemName + '"]');

      systemCountTargets.forEach(function(target) {
        target.textContent = checkedCount;
      });

      syncBulkToggleState(systemToggle, systemCheckboxes);
    });

    document.querySelectorAll('[data-total-granted-count]').forEach(function(target) {
      target.textContent = totalGrantedCount;
    });
  }

  function setAccordionState(expand) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Collapse) {
      return;
    }

    document.querySelectorAll('.permission-accordion .accordion-collapse').forEach(function(collapseElement) {
      var collapseInstance = bootstrap.Collapse.getOrCreateInstance(collapseElement, { toggle: false });

      if (expand) {
        collapseInstance.show();
        return;
      }

      collapseInstance.hide();
    });
  }

  if (input) {
    input.addEventListener('input', function() {
      clearTimeout(debounceTimer);
      var q = input.value.trim();

      if (q.length < 2) {
        resultsDiv.style.display = 'none';
        resultsDiv.innerHTML = '';
        return;
      }

      debounceTimer = setTimeout(function() {
        fetch(baseUrl + '?q=' + encodeURIComponent(q), {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
          .then(function(r) { return r.json(); })
          .then(function(users) {
            resultsDiv.innerHTML = '';

            if (users.length === 0) {
              resultsDiv.innerHTML = '<div class="list-group-item text-muted">Nenhum usuário encontrado.</div>';
              resultsDiv.style.display = 'block';
              return;
            }

            users.forEach(function(u) {
              var item = document.createElement('a');
              item.href = permUrl + '?user_id=' + u.id;
              item.className = 'list-group-item list-group-item-action py-2';

              var nameSpan = document.createElement('span');
              nameSpan.className = 'fw-semibold';
              nameSpan.textContent = u.name || u.username;

              var badge = document.createElement('span');
              badge.className = 'badge text-bg-info ms-2';
              badge.textContent = u.type;

              var emailDiv = document.createElement('div');
              emailDiv.className = 'small text-muted';
              emailDiv.textContent = u.email;

              if (u.username && u.username !== (u.name || '')) {
                emailDiv.textContent += ' (' + u.username + ')';
              }

              item.appendChild(nameSpan);
              item.appendChild(badge);
              item.appendChild(emailDiv);
              resultsDiv.appendChild(item);
            });

            resultsDiv.style.display = 'block';
          })
          .catch(function() {
            resultsDiv.style.display = 'none';
          });
      }, 300);
    });

    input.addEventListener('focus', function() {
      if (resultsDiv.innerHTML.trim() !== '') {
        resultsDiv.style.display = 'block';
      }
    });
  }

  document.addEventListener('click', function(e) {
    var wrapper = document.getElementById('userSearchWrapper');
    if (wrapper && !wrapper.contains(e.target)) {
      resultsDiv.style.display = 'none';
    }
  });

  var toggleOtherSystem = document.getElementById('toggleOtherSystem');
  if (toggleOtherSystem) {
    var userOrigin = <?= json_encode((string) ($userOriginSystem ?? '')); ?>;
    toggleOtherSystem.addEventListener('change', function() {
      document.querySelectorAll('.perm-system-block').forEach(function(block) {
        var systemName = block.getAttribute('data-system');

        if (systemName === userOrigin) {
          block.style.display = '';
          return;
        }

        block.style.display = toggleOtherSystem.checked ? '' : 'none';
      });
    });
  }

  document.querySelectorAll('.system-toggle-checkbox').forEach(function(systemToggle) {
    systemToggle.addEventListener('change', function() {
      var systemName = systemToggle.getAttribute('data-system-toggle');
      var permissionCheckboxes = getSystemPermissionCheckboxes(systemName);

      permissionCheckboxes.forEach(function(permissionCheckbox) {
        permissionCheckbox.checked = systemToggle.checked;
      });

      systemToggle.indeterminate = false;
      refreshSelectionMetrics();
    });
  });

  document.querySelectorAll('.group-toggle-checkbox').forEach(function(groupToggle) {
    groupToggle.addEventListener('change', function() {
      var systemName = groupToggle.getAttribute('data-system-toggle');
      var groupName = groupToggle.getAttribute('data-group-toggle');
      var permissionCheckboxes = getGroupPermissionCheckboxes(systemName, groupName);

      permissionCheckboxes.forEach(function(permissionCheckbox) {
        permissionCheckbox.checked = groupToggle.checked;
      });

      groupToggle.indeterminate = false;
      refreshSelectionMetrics();
    });
  });

  document.querySelectorAll('.permission-checkbox').forEach(function(permissionCheckbox) {
    permissionCheckbox.addEventListener('change', function() {
      refreshSelectionMetrics();
    });
  });

  document.querySelectorAll('[data-permission-accordion-action]').forEach(function(actionButton) {
    actionButton.addEventListener('click', function() {
      var action = actionButton.getAttribute('data-permission-accordion-action');
      setAccordionState(action === 'expand');
    });
  });

  refreshSelectionMetrics();
})();
</script>

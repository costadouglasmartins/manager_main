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

// Organize: [system][module] => [rows]
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

// Derive the selected user's source system (manager_main for central users, manager for tenant users).
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

$effectivePermissionsBySystem = [];
foreach ($bySystem as $systemName => $_groups)
	{
	$effectivePermissionsBySystem[$systemName] = [];
	}

foreach ($permissions as $permissionRow)
	{
	$permissionId = (int) $permissionRow['id'];
	if (!isset($userPermissionLookup[$permissionId]))
		{
		continue;
		}

	$permissionSystem = (string) ($permissionRow['system'] ?? 'manager_main');
	if (!isset($effectivePermissionsBySystem[$permissionSystem]))
		{
		$effectivePermissionsBySystem[$permissionSystem] = [];
		}

	$effectivePermissionsBySystem[$permissionSystem][] = (string) $permissionRow['ability'];
	}

$hasEffectivePermissionsBySystem = false;
foreach ($effectivePermissionsBySystem as $systemName => $abilities)
	{
	sort($effectivePermissionsBySystem[$systemName], SORT_NATURAL | SORT_FLAG_CASE);
	if (!empty($effectivePermissionsBySystem[$systemName]))
		{
		$hasEffectivePermissionsBySystem = true;
		}
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
<link rel="stylesheet" href="<?= url('assets/css/permissions/index.css?v=4'); ?>">
<!-- /CSS -->
<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Permissões</li>
  </ol>
</nav>

<header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3" aria-hidden="true"><i class="bi bi-shield-lock fs-4"></i></span>
    <div><p class="small fw-semibold text-primary text-uppercase mb-1">Segurança</p><h1 class="h3 fw-bold mb-1">Permissões por usuário</h1><p class="text-body-secondary mb-0">Consulte, distribua e sincronize permissões dos sistemas.</p></div>
  </div>
</header>

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

<div class="permissions-shell" data-user-search-url="<?= htmlspecialchars(url('permission/searchUsers'), ENT_QUOTES, 'UTF-8'); ?>" data-permission-url="<?= htmlspecialchars(url('permission'), ENT_QUOTES, 'UTF-8'); ?>" data-user-origin-system="<?= htmlspecialchars((string) ($userOriginSystem ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
  <div class="card shadow-sm border-0 permission-header-card mb-3">
    <div class="card-body p-3 p-lg-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
      <div>
        <span class="permission-kicker">Painel de acesso</span>
        <h6 class="mb-1">Distribuição de permissões por usuário</h6>
        <p class="text-muted mb-0">Selecione um usuário, revise os módulos por sistema e aplique os acessos necessários.</p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <span class="badge text-bg-light border px-3 py-2"><strong><?= (int) $totalCatalogPermissions; ?></strong> permissões</span>
        <span class="badge text-bg-light border px-3 py-2"><strong><?= (int) $totalModules; ?></strong> módulos</span>
        <span class="badge text-bg-light border px-3 py-2"><strong><?= (int) $totalSystems; ?></strong> sistemas</span>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-12 col-xl-7">
      <div class="card shadow-sm border-0 permission-search-card h-100">
        <div class="card-body p-3 p-lg-4">
          <label for="userSearchInput" class="form-label fw-semibold mb-1">Buscar usuário</label>
          <small class="text-muted d-block mb-3">Digite nome, e-mail ou username para abrir o catálogo.</small>
          <div class="position-relative" id="userSearchWrapper">
            <input type="text" id="userSearchInput" class="form-control form-control-lg" placeholder="Digite o nome, e-mail ou username do usuário..." autocomplete="off">
            <div id="userSearchResults" class="list-group position-absolute w-100 shadow-sm permission-results"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-5">
      <div class="card shadow-sm border-0 permission-actions-card h-100">
        <div class="card-body p-3 p-lg-4">
          <span class="permission-kicker">Ações rápidas</span>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <form method="POST" action="<?= url('permission/sync'); ?>" class="d-inline">
              <input type="hidden" name="selected_user_id" value="<?= (int) ($selectedUserId ?? 0); ?>">
              <button class="btn btn-primary" type="submit">Sincronizar permissões</button>
            </form>
            <a href="<?= url('permission/orphaned'); ?>" class="btn btn-outline-warning">Permissões órfãs</a>
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#addPermCatalog" aria-expanded="false" aria-controls="addPermCatalog">Adicionar manualmente</button>
          </div>

          <?php if (!empty($systemStats)): ?>
            <div class="permission-system-summary" aria-label="Resumo por sistema">
              <div class="permission-system-summary-title">Resumo por sistema</div>
              <?php foreach ($systemStats as $summarySystemName => $summaryStat): ?>
                <div class="permission-system-summary-row" data-system="<?= htmlspecialchars($summarySystemName); ?>">
                  <span class="permission-system-summary-name">
                    <span class="permission-system-summary-icon" aria-hidden="true"><i class="bi <?= permSystemIcon($summarySystemName); ?>"></i></span>
                    <span class="badge <?= permSystemBadgeClass($summarySystemName); ?>"><?= htmlspecialchars($summarySystemName); ?></span>
                  </span>
                  <span><strong><?= (int) $summaryStat['groups']; ?></strong> módulos</span>
                  <span><strong><?= (int) $summaryStat['total']; ?></strong> permissões</span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
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
    // System filter toggle.
    // If the user is from manager_main, the manager block starts hidden (and vice versa).
    // The checkbox below lets the operator show permissions from the other system too.
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

          // Hide the other system by default; the toggle above shows it.
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
                                <input id="<?= htmlspecialchars($inputId); ?>" class="form-check-input permission-checkbox permission-choice-input" type="checkbox" name="permission_ids[]" value="<?= $pid; ?>" data-permission-system="<?= htmlspecialchars($systemName); ?>" data-permission-group="<?= htmlspecialchars($groupName); ?>" <?= ($checked ? 'checked' : ''); ?>>
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
            <?php if (!$hasEffectivePermissionsBySystem): ?>
              <div class="text-muted">Nenhuma permissão ativa encontrada.</div>
            <?php else: ?>
              <div class="permission-effective-systems">
                <?php foreach ($effectivePermissionsBySystem as $systemName => $abilities): ?>
                  <?php if (empty($abilities)) { continue; } ?>
                  <section class="permission-effective-system" data-system="<?= htmlspecialchars($systemName); ?>">
                    <div class="permission-effective-system-header">
                      <span class="badge <?= permSystemBadgeClass($systemName); ?>"><?= htmlspecialchars($systemName); ?></span>
                      <span class="text-muted small"><?= count($abilities); ?> permissão(ões)</span>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                      <?php foreach ($abilities as $ability): ?>
                        <span class="badge text-bg-secondary"><code class="text-white"><?= htmlspecialchars($ability); ?></code></span>
                      <?php endforeach; ?>
                    </div>
                  </section>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </form>

  <?php endif; ?>
</div>
<script src="<?= url('assets/js/permissions/index.js?v=1'); ?>"></script>

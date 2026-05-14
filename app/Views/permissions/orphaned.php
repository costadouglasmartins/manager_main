<?php
// Expected variables: $orphaned, $stats, $title

function permSystemBadgeClassOrphaned(string $system): string
	{
	return $system === 'manager' ? 'text-bg-success' : 'text-bg-primary';
	}

function permSystemIconOrphaned(string $system): string
	{
	return $system === 'manager' ? 'bi-diagram-3' : 'bi-grid-3x3-gap';
	}

$deleted = null;
if (isset($_GET['deleted']))
	{
	$deleted = (int) $_GET['deleted'];
	}

$usersAffected = null;
if (isset($_GET['users_affected']))
	{
	$usersAffected = (int) $_GET['users_affected'];
	}

$error = null;
if (isset($_GET['error']))
	{
	$error = (string) $_GET['error'];
	}

$totalOrphaned = (int) ($stats['total_orphaned'] ?? count($orphaned));
$totalUserLinks = 0;
$permissionsWithUsers = 0;

foreach ($orphaned as $perm)
	{
	$permId = (int) $perm['id'];
	$userCount = (int) ($stats['permissions'][$permId]['user_count'] ?? 0);
	$totalUserLinks += $userCount;

	if ($userCount > 0)
		{
		$permissionsWithUsers += 1;
		}
	}

$permissionsWithoutUsers = max(0, $totalOrphaned - $permissionsWithUsers);
?>

<!-- CSS -->
<link rel="stylesheet" href="<?= url('assets/css/permissions/orphaned.css?v=1'); ?>">
<!-- /CSS -->

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('permission'); ?>">Permissões</a></li>
    <li class="breadcrumb-item active" aria-current="page">Permissões Órfãs</li>
  </ol>
</nav>

<div class="orphaned-permissions-shell">
  <section class="orphaned-hero mb-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3">
      <div class="d-flex align-items-start gap-3">
        <div class="orphaned-hero-icon" aria-hidden="true">
          <i class="bi bi-shield-exclamation"></i>
        </div>
        <div>
          <span class="orphaned-kicker">Auditoria de acesso</span>
          <h5 class="mb-2">Permissões Órfãs</h5>
          <p class="text-muted mb-0">
            Revise permissões que não existem mais no código e remova os vínculos do catálogo quando estiver tudo conferido.
          </p>
        </div>
      </div>

      <a href="<?= url('permission'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
        Voltar
      </a>
    </div>
  </section>

  <?php if ($deleted === 1): ?>
    <div class="alert alert-success py-2 orphaned-alert">
      <div class="d-flex gap-2">
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
        <div>
          <strong>Permissão órfã removida com sucesso!</strong>
          <?php if ($usersAffected !== null && $usersAffected > 0): ?>
            <br><small><?= $usersAffected; ?> usuário(s) teve/tiveram esta permissão removida.</small>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php elseif ($deleted === 0): ?>
    <div class="alert alert-danger py-2 orphaned-alert">
      <div class="d-flex gap-2">
        <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
        <div>
          <strong>Erro ao remover permissão.</strong>
          <?php if ($error === 'not_orphaned'): ?>
            Esta permissão não é órfã ou não existe.
          <?php elseif ($error === 'exception'): ?>
            Erro ao processar a operação. Tente novamente.
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
      <div class="orphaned-stat-card">
        <span class="orphaned-stat-label">Órfãs</span>
        <span class="orphaned-stat-value"><?= $totalOrphaned; ?></span>
        <small class="text-muted">permissões fora do código</small>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="orphaned-stat-card orphaned-stat-card-warning">
        <span class="orphaned-stat-label">Vínculos ativos</span>
        <span class="orphaned-stat-value"><?= $totalUserLinks; ?></span>
        <small class="text-muted">atribuição(ões) impactadas</small>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="orphaned-stat-card orphaned-stat-card-success">
        <span class="orphaned-stat-label">Sem vínculos</span>
        <span class="orphaned-stat-value"><?= $permissionsWithoutUsers; ?></span>
        <small class="text-muted">prontas para remoção limpa</small>
      </div>
    </div>
  </div>

  <?php if ($totalOrphaned === 0): ?>
    <section class="orphaned-empty-state">
      <div class="orphaned-empty-icon" aria-hidden="true">
        <i class="bi bi-shield-check"></i>
      </div>
      <h6 class="mb-2">Nenhuma permissão órfã encontrada</h6>
      <p class="text-muted mb-0">Todas as permissões no catálogo existem no código.</p>
    </section>
  <?php else: ?>
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-2 mb-3">
      <div>
        <span class="orphaned-kicker">Catálogo pendente</span>
        <h6 class="mb-0"><?= $totalOrphaned; ?> permissão(ões) órfã(s)</h6>
      </div>
      <?php if ($permissionsWithUsers > 0): ?>
        <span class="badge text-bg-warning orphaned-summary-badge">
          <i class="bi bi-person-exclamation me-1" aria-hidden="true"></i>
          <?= $permissionsWithUsers; ?> com usuário(s)
        </span>
      <?php else: ?>
        <span class="badge text-bg-success orphaned-summary-badge">
          <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
          Nenhum vínculo ativo
        </span>
      <?php endif; ?>
    </div>

    <div class="orphaned-permission-list">
      <?php foreach ($orphaned as $perm): ?>
        <?php
          $permId = (int) $perm['id'];
          $userCount = (int) ($stats['permissions'][$permId]['user_count'] ?? 0);
          $psys = (string) ($perm['system'] ?? 'manager_main');
          $description = trim((string) ($perm['description'] ?? ''));
          $hasUsers = $userCount > 0;
        ?>
        <article class="orphaned-permission-card<?= ($hasUsers ? ' orphaned-permission-card-warning' : ''); ?>" data-system="<?= htmlspecialchars($psys, ENT_QUOTES, 'UTF-8'); ?>">
          <div class="orphaned-permission-system">
            <span class="orphaned-system-icon" aria-hidden="true">
              <i class="bi <?= permSystemIconOrphaned($psys); ?>"></i>
            </span>
            <span class="badge <?= permSystemBadgeClassOrphaned($psys); ?>"><?= htmlspecialchars($psys); ?></span>
          </div>

          <div class="orphaned-permission-main">
            <code class="orphaned-ability"><?= htmlspecialchars((string) $perm['ability']); ?></code>
            <span class="orphaned-description<?= ($description === '' ? ' orphaned-description-empty' : ''); ?>">
              <?= htmlspecialchars($description !== '' ? $description : 'Sem descrição informada.'); ?>
            </span>
          </div>

          <div class="orphaned-user-impact">
            <?php if ($hasUsers): ?>
              <span class="orphaned-impact-number text-danger"><?= $userCount; ?></span>
              <span class="orphaned-impact-label">usuário(s)</span>
            <?php else: ?>
              <span class="orphaned-impact-number text-success">0</span>
              <span class="orphaned-impact-label">vínculos</span>
            <?php endif; ?>
          </div>

          <div class="orphaned-permission-action">
            <button
              class="btn btn-sm btn-danger orphaned-remove-button"
              data-bs-toggle="modal"
              data-bs-target="#deleteModal"
              data-id="<?= $permId; ?>"
              data-ability="<?= htmlspecialchars((string) $perm['ability'], ENT_QUOTES, 'UTF-8'); ?>"
              data-user-count="<?= $userCount; ?>"
              title="Deletar permissão órfã"
            >
              <i class="bi bi-trash3" aria-hidden="true"></i>
              <span>Remover</span>
            </button>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content orphaned-modal-content">
      <div class="modal-header">
        <div>
          <span class="orphaned-kicker">Confirmação</span>
          <h5 class="modal-title" id="deleteModalTitle">Remover permissão órfã</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">Esta ação remove a permissão do catálogo e de todos os usuários vinculados.</p>

        <div class="orphaned-modal-ability mb-3">
          <code id="abilityName" class="fw-semibold"></code>
        </div>

        <div id="affectedUsersSection" class="d-none">
          <div class="alert alert-danger mb-0">
            <div class="d-flex gap-2">
              <i class="bi bi-person-exclamation" aria-hidden="true"></i>
              <div>
                <strong>Usuários afetados: <span id="userCountText"></span></strong>
                <p class="mb-0 small" id="affectedUsersList"></p>
              </div>
            </div>
          </div>
        </div>

        <div id="noUsersSection" class="d-none">
          <div class="alert alert-info mb-0">
            <div class="d-flex gap-2">
              <i class="bi bi-info-circle" aria-hidden="true"></i>
              <small>Nenhum usuário possui esta permissão no momento.</small>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form method="POST" action="#" id="deleteForm" data-delete-url-base="<?= url('permission/deleteOrphaned') ?>">
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-trash3 me-1" aria-hidden="true"></i>
            Remover Permissão
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="<?= url('assets/js/permissions/orphaned.js?v=1'); ?>"></script>

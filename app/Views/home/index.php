<?php
if (!function_exists('homeDashboardCount'))
	{
	function homeDashboardCount(array $source, string $key): int
		{
		return (int) ($source[$key] ?? 0);
		}
	}

if (!function_exists('homeDashboardPercent'))
	{
	function homeDashboardPercent(int $value, int $total): int
		{
		if ($total <= 0)
			{
			return 0;
			}

		return (int) round(($value / $total) * 100);
		}
	}

if (!function_exists('homeDashboardCan'))
	{
	function homeDashboardCan(string $ability): bool
		{
		return function_exists('authCan') ? authCan($ability) : true;
		}
	}

if (!function_exists('homeDashboardDate'))
	{
	function homeDashboardDate(?string $date): string
		{
		if (empty($date))
			{
			return '-';
			}

		$timestamp = strtotime($date);
		if ($timestamp === false)
			{
			return '-';
			}

		return date('d/m/Y', $timestamp);
		}
	}

if (!function_exists('homeDashboardTenantBadgeClass'))
	{
	function homeDashboardTenantBadgeClass(string $status): string
		{
		switch ($status)
			{
			case 'active':
				return 'home-status-badge home-status-active';
			case 'suspended':
				return 'home-status-badge home-status-suspended';
			default:
				return 'home-status-badge home-status-pending';
			}
		}
	}

if (!function_exists('homeDashboardTenantStatusLabel'))
	{
	function homeDashboardTenantStatusLabel(string $status): string
		{
		switch ($status)
			{
			case 'active':
				return 'Ativo';
			case 'suspended':
				return 'Suspenso';
			default:
				return 'Pendente';
			}
		}
	}

$dashboard = $dashboard ?? [];
$people = $dashboard['people'] ?? [];
$users = $dashboard['users'] ?? [];
$tenants = $dashboard['tenants'] ?? [];
$contacts = $dashboard['contacts'] ?? [];
$locations = $dashboard['locations'] ?? [];
$recentPeople = $dashboard['recentPeople'] ?? [];
$recentTenants = $dashboard['recentTenants'] ?? [];

$peopleTotal = homeDashboardCount($people, 'total');
$peopleActive = homeDashboardCount($people, 'active');
$peopleInactive = homeDashboardCount($people, 'inactive');
$peopleIndividual = homeDashboardCount($people, 'individual');
$peopleCompany = homeDashboardCount($people, 'company');

$usersTotal = homeDashboardCount($users, 'total');
$usersActive = homeDashboardCount($users, 'active');
$privilegedUsers = homeDashboardCount($users, 'admin') + homeDashboardCount($users, 'root');

$tenantsTotal = homeDashboardCount($tenants, 'total');
$tenantsActive = homeDashboardCount($tenants, 'active');
$tenantsPending = homeDashboardCount($tenants, 'pending');
$tenantsSuspended = homeDashboardCount($tenants, 'suspended');

$emailsTotal = homeDashboardCount($contacts, 'emails');
$phonesTotal = homeDashboardCount($contacts, 'phones');
$addressesTotal = homeDashboardCount($contacts, 'addresses');
$contactTotal = $emailsTotal + $phonesTotal + $addressesTotal;
$citiesTotal = homeDashboardCount($locations, 'cities');

$contactAverage = $peopleTotal > 0 ? $contactTotal / $peopleTotal : 0;
$tenantActivePercent = homeDashboardPercent($tenantsActive, $tenantsTotal);
$peopleActivePercent = homeDashboardPercent($peopleActive, $peopleTotal);
$usersActivePercent = homeDashboardPercent($usersActive, $usersTotal);
$privilegedPercent = homeDashboardPercent($privilegedUsers, $usersTotal);
$contactDensityPercent = min(100, (int) round($contactAverage * 25));

$peopleChart = [
	['label' => 'Pessoas ativas', 'value' => $peopleActive, 'icon' => 'bi-check2-circle', 'tone' => 'active'],
	['label' => 'Pessoas inativas', 'value' => $peopleInactive, 'icon' => 'bi-pause-circle', 'tone' => 'muted'],
	['label' => 'Pessoas físicas', 'value' => $peopleIndividual, 'icon' => 'bi-person', 'tone' => 'primary'],
	['label' => 'Pessoas jurídicas', 'value' => $peopleCompany, 'icon' => 'bi-buildings', 'tone' => 'amber'],
];
$peopleChartMax = max(1, $peopleActive, $peopleInactive, $peopleIndividual, $peopleCompany);

$contactChart = [
	['label' => 'E-mails', 'value' => $emailsTotal, 'icon' => 'bi-envelope', 'tone' => 'primary'],
	['label' => 'Telefones', 'value' => $phonesTotal, 'icon' => 'bi-telephone', 'tone' => 'active'],
	['label' => 'Endereços', 'value' => $addressesTotal, 'icon' => 'bi-signpost-split', 'tone' => 'amber'],
];
$contactChartMax = max(1, $emailsTotal, $phonesTotal, $addressesTotal);

$tenantBase = max(1, $tenantsTotal);
$tenantActiveAngle = homeDashboardPercent($tenantsActive, $tenantBase) * 3.6;
$tenantPendingAngle = $tenantActiveAngle + (homeDashboardPercent($tenantsPending, $tenantBase) * 3.6);
$tenantSuspendedAngle = $tenantPendingAngle + (homeDashboardPercent($tenantsSuspended, $tenantBase) * 3.6);
?>

<!-- Css -->
<link rel="stylesheet" href="<?= url('assets/css/home/index.css?v=4'); ?>">
<!-- /Css -->

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item active" aria-current="page">Início</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<section class="home-dashboard container-fluid px-0">
  <div class="home-hero home-card-accent-primary mb-4">
    <div class="row g-3 align-items-center">
      <div class="col-12">
        <span class="home-kicker">Visão geral</span>
        <h1 class="home-title">Dashboard operacional</h1>
        <p class="home-subtitle mb-0">
          Acompanhe cadastros, tenants, usuários e canais de contato em uma tela única.
        </p>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4 align-items-stretch">
    <aside class="col-12 col-lg-4 col-xl-3 d-lg-flex">
      <div class="home-panel home-panel-menu home-card-accent-primary w-100">
        <div class="home-panel-header mb-3">
          <div>
            <span class="home-kicker">Ações rápidas</span>
            <h2 class="home-panel-title">Menu operacional</h2>
          </div>
        </div>
        <nav class="nav nav-pills flex-column gap-2 home-side-menu" aria-label="Ações do dashboard">
          <?php if (homeDashboardCan('person.create')): ?>
            <a class="btn btn-primary text-start" href="<?= url('person/create'); ?>">
              <i class="bi bi-person-plus me-2"></i>Nova pessoa
            </a>
          <?php endif; ?>
          <?php if (homeDashboardCan('person.index')): ?>
            <a class="btn btn-outline-primary text-start" href="<?= url('person'); ?>">
              <i class="bi bi-people me-2"></i>Pessoas
            </a>
          <?php endif; ?>
          <?php if (homeDashboardCan('tenant.index')): ?>
            <a class="btn btn-outline-primary text-start" href="<?= url('tenant'); ?>">
              <i class="bi bi-database me-2"></i>Tenants
            </a>
          <?php endif; ?>
        </nav>
      </div>
    </aside>

    <div class="col-12 col-lg-8 col-xl-9 d-flex">
      <div class="row g-3 w-100">
        <div class="col-12 col-md-6 col-xl-3">
          <div class="home-kpi home-kpi-primary home-card-accent-primary" style="--kpi-progress: <?= $peopleActivePercent; ?>%;">
            <div class="home-kpi-top">
              <div class="home-kpi-icon"><i class="bi bi-people-fill"></i></div>
              <span class="home-kpi-pill"><?= $peopleActivePercent; ?>%</span>
            </div>
            <span class="home-kpi-label">Pessoas ativas</span>
            <strong class="home-kpi-value home-counter" data-target="<?= $peopleActive; ?>"><?= number_format($peopleActive, 0, ',', '.'); ?></strong>
            <span class="home-kpi-meta"><?= $peopleActivePercent; ?>% do cadastro total</span>
            <span class="home-kpi-track"><span></span></span>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="home-kpi home-kpi-active home-card-accent-active" style="--kpi-progress: <?= $tenantActivePercent; ?>%;">
            <div class="home-kpi-top">
              <div class="home-kpi-icon"><i class="bi bi-database-check"></i></div>
              <span class="home-kpi-pill"><?= $tenantActivePercent; ?>%</span>
            </div>
            <span class="home-kpi-label">Tenants ativos</span>
            <strong class="home-kpi-value home-counter" data-target="<?= $tenantsActive; ?>"><?= number_format($tenantsActive, 0, ',', '.'); ?></strong>
            <span class="home-kpi-meta"><?= $tenantActivePercent; ?>% operacionais</span>
            <span class="home-kpi-track"><span></span></span>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="home-kpi home-kpi-indigo home-card-accent-indigo" style="--kpi-progress: <?= $usersActivePercent; ?>%;">
            <div class="home-kpi-top">
              <div class="home-kpi-icon"><i class="bi bi-person-gear"></i></div>
              <span class="home-kpi-pill"><?= $usersActivePercent; ?>%</span>
            </div>
            <span class="home-kpi-label">Usuários ativos</span>
            <strong class="home-kpi-value home-counter" data-target="<?= $usersActive; ?>"><?= number_format($usersActive, 0, ',', '.'); ?></strong>
            <span class="home-kpi-meta"><?= $privilegedPercent; ?>% com perfil admin/root</span>
            <span class="home-kpi-track"><span></span></span>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="home-kpi home-kpi-amber home-card-accent-amber" style="--kpi-progress: <?= $contactDensityPercent; ?>%;">
            <div class="home-kpi-top">
              <div class="home-kpi-icon"><i class="bi bi-broadcast-pin"></i></div>
              <span class="home-kpi-pill"><?= number_format($contactAverage, 1, ',', '.'); ?>/pessoa</span>
            </div>
            <span class="home-kpi-label">Canais de contato</span>
            <strong class="home-kpi-value home-counter" data-target="<?= $contactTotal; ?>"><?= number_format($contactTotal, 0, ',', '.'); ?></strong>
            <span class="home-kpi-meta"><?= number_format($contactAverage, 1, ',', '.'); ?> por pessoa</span>
            <span class="home-kpi-track"><span></span></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-8">
      <div class="home-panel home-card-accent-primary h-100">
        <div class="home-panel-header">
          <div>
            <span class="home-kicker">Cadastros</span>
            <h2 class="home-panel-title">Distribuição de pessoas</h2>
          </div>
          <span class="home-panel-count"><?= number_format($peopleTotal, 0, ',', '.'); ?> total</span>
        </div>

        <div class="home-chart-list">
          <?php foreach ($peopleChart as $row): ?>
            <?php $barPercent = homeDashboardPercent((int) $row['value'], $peopleChartMax); ?>
            <div class="home-bar-row home-chart-<?= htmlspecialchars($row['tone'], ENT_QUOTES, 'UTF-8'); ?>" data-chart-row style="--bar-value: <?= $barPercent; ?>%;">
              <div class="home-bar-meta">
                <span class="home-bar-icon"><i class="bi <?= htmlspecialchars($row['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                <span><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                <strong><?= number_format((int) $row['value'], 0, ',', '.'); ?></strong>
              </div>
              <div class="home-bar-track">
                <span class="home-bar-fill"></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="home-panel home-card-accent-active h-100">
        <div class="home-panel-header">
          <div>
            <span class="home-kicker">Tenants</span>
            <h2 class="home-panel-title">Status operacional</h2>
          </div>
        </div>

        <div class="home-donut-wrap">
          <div class="home-donut" style="--tenant-active: <?= $tenantActiveAngle; ?>deg; --tenant-pending: <?= $tenantPendingAngle; ?>deg; --tenant-suspended: <?= $tenantSuspendedAngle; ?>deg;" role="img" aria-label="Distribuição de status de tenants">
            <span><?= number_format($tenantsTotal, 0, ',', '.'); ?></span>
            <small>tenants</small>
          </div>
        </div>

        <div class="home-legend">
          <span><i class="home-dot home-dot-active"></i>Ativos: <?= number_format($tenantsActive, 0, ',', '.'); ?></span>
          <span><i class="home-dot home-dot-warning"></i>Pendentes: <?= number_format($tenantsPending, 0, ',', '.'); ?></span>
          <span><i class="home-dot home-dot-danger"></i>Suspensos: <?= number_format($tenantsSuspended, 0, ',', '.'); ?></span>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12 col-xl-4">
      <div class="home-panel home-card-accent-amber h-100">
        <div class="home-panel-header">
          <div>
            <span class="home-kicker">Contato</span>
            <h2 class="home-panel-title">Canais cadastrados</h2>
          </div>
        </div>

        <div class="home-contact-stack">
          <?php foreach ($contactChart as $row): ?>
            <?php $contactPercent = homeDashboardPercent((int) $row['value'], $contactChartMax); ?>
            <div class="home-contact-item home-chart-<?= htmlspecialchars($row['tone'], ENT_QUOTES, 'UTF-8'); ?>" data-chart-row style="--bar-value: <?= $contactPercent; ?>%;">
              <div>
                <i class="bi <?= htmlspecialchars($row['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                <span><?= htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <strong><?= number_format((int) $row['value'], 0, ',', '.'); ?></strong>
              <div class="home-mini-track"><span></span></div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="home-location-note">
          <i class="bi bi-geo-alt"></i>
          <span><?= number_format($citiesTotal, 0, ',', '.'); ?> cidades dão suporte aos endereços cadastrados.</span>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="home-panel home-card-accent-indigo h-100">
        <div class="home-panel-header">
          <div>
            <span class="home-kicker">Últimos registros</span>
            <h2 class="home-panel-title">Pessoas recentes</h2>
          </div>
          <?php if (homeDashboardCan('person.index')): ?>
            <a href="<?= url('person'); ?>" class="btn btn-sm btn-outline-primary home-panel-action">Ver todas</a>
          <?php endif; ?>
        </div>

        <div class="home-list">
          <?php if (!empty($recentPeople)): ?>
            <?php foreach ($recentPeople as $person): ?>
              <a class="home-list-item" href="<?= url('person/informations/' . (int) $person['id']); ?>">
                <span class="home-avatar"><?= strtoupper(substr((string) ($person['name'] ?? 'P'), 0, 1)); ?></span>
                <span>
                  <strong><?= htmlspecialchars($person['name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></strong>
                  <small>
                    <?= (($person['type'] ?? '') === 'company') ? 'Pessoa jurídica' : 'Pessoa física'; ?>
                    · <?= homeDashboardDate($person['created'] ?? null); ?>
                  </small>
                </span>
              </a>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="home-empty">Nenhuma pessoa cadastrada ainda.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="home-panel home-card-accent-rose h-100">
        <div class="home-panel-header">
          <div>
            <span class="home-kicker">Infraestrutura</span>
            <h2 class="home-panel-title">Tenants recentes</h2>
          </div>
          <?php if (homeDashboardCan('tenant.index')): ?>
            <a href="<?= url('tenant'); ?>" class="btn btn-sm btn-outline-primary home-panel-action">Ver todos</a>
          <?php endif; ?>
        </div>

        <div class="home-list">
          <?php if (!empty($recentTenants)): ?>
            <?php foreach ($recentTenants as $tenant): ?>
              <a class="home-list-item" href="<?= url('tenant/informations/' . (int) $tenant['id']); ?>">
                <span class="home-avatar home-avatar-tenant"><i class="bi bi-database"></i></span>
                <span>
                  <strong><?= htmlspecialchars($tenant['associated_person_name'] ?? ('Tenant #' . (int) ($tenant['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></strong>
                  <small>
                    <code>manager_<?= htmlspecialchars($tenant['database_number'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></code>
                    · <?= homeDashboardDate($tenant['created'] ?? null); ?>
                  </small>
                </span>
                <span class="badge <?= homeDashboardTenantBadgeClass((string) ($tenant['status'] ?? 'pending')); ?>">
                  <?= homeDashboardTenantStatusLabel((string) ($tenant['status'] ?? 'pending')); ?>
                </span>
              </a>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="home-empty">Nenhum tenant cadastrado ainda.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- Js -->
<script src="<?= url('assets/js/home/index.js?v=4'); ?>"></script>
<!-- /Js -->

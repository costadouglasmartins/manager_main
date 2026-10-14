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
		return $total > 0 ? (int) round(($value / $total) * 100) : 0;
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
		$timestamp = !empty($date) ? strtotime($date) : false;
		return $timestamp !== false ? date('d/m/Y', $timestamp) : '-';
		}
	}

if (!function_exists('homeDashboardInitial'))
	{
	function homeDashboardInitial(string $name): string
		{
		$name = trim($name);
		if ($name === '')
			{
			return '?';
			}

		$initial = function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1);
		return function_exists('mb_strtoupper') ? mb_strtoupper($initial, 'UTF-8') : strtoupper($initial);
		}
	}

if (!function_exists('homeDashboardTenantBadgeClass'))
	{
	function homeDashboardTenantBadgeClass(string $status): string
		{
		switch ($status)
			{
			case 'active':
				return 'text-bg-success';
			case 'suspended':
				return 'text-bg-danger';
			default:
				return 'text-bg-warning';
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

if (!function_exists('homeDashboardTrendCoordinates'))
	{
	/**
	 * @return array<int, array{x: string, y: string, value: int, label: string}>
	 */
	function homeDashboardTrendCoordinates(array $rows, string $key, int $maximum): array
		{
		$count = count($rows);
		$left = 38.0;
		$right = 682.0;
		$top = 24.0;
		$bottom = 210.0;
		$step = $count > 1 ? ($right - $left) / ($count - 1) : 0.0;
		$maximum = max(1, $maximum);
		$coordinates = [];

		foreach ($rows as $index => $row)
			{
			$value = max(0, (int) ($row[$key] ?? 0));
			$x = $left + ($step * $index);
			$y = $bottom - (($value / $maximum) * ($bottom - $top));
			$coordinates[] = [
				'x' => number_format($x, 2, '.', ''),
				'y' => number_format($y, 2, '.', ''),
				'value' => $value,
				'label' => (string) ($row['label'] ?? ''),
			];
			}

		return $coordinates;
		}
	}

$dashboard = $dashboard ?? [];
$people = $dashboard['people'] ?? [];
$users = $dashboard['users'] ?? [];
$tenants = $dashboard['tenants'] ?? [];
$contacts = $dashboard['contacts'] ?? [];
$locations = $dashboard['locations'] ?? [];
$support = $dashboard['support'] ?? [];
$activity = $dashboard['activity'] ?? [];
$monthlyRegistrations = is_array($dashboard['monthlyRegistrations'] ?? null) ? $dashboard['monthlyRegistrations'] : [];
$recentPeople = $dashboard['recentPeople'] ?? [];
$recentTenants = $dashboard['recentTenants'] ?? [];

$peopleTotal = homeDashboardCount($people, 'total');
$peopleActive = homeDashboardCount($people, 'active');
$peopleInactive = homeDashboardCount($people, 'inactive');
$peopleIndividual = homeDashboardCount($people, 'individual');
$peopleCompany = homeDashboardCount($people, 'company');
$peopleActivePercent = homeDashboardPercent($peopleActive, $peopleTotal);

$usersTotal = homeDashboardCount($users, 'total');
$usersActive = homeDashboardCount($users, 'active');
$usersInactive = max(0, $usersTotal - $usersActive);
$privilegedUsers = homeDashboardCount($users, 'admin') + homeDashboardCount($users, 'root');
$usersActivePercent = homeDashboardPercent($usersActive, $usersTotal);

$tenantsTotal = homeDashboardCount($tenants, 'total');
$tenantsActive = homeDashboardCount($tenants, 'active');
$tenantsPending = homeDashboardCount($tenants, 'pending');
$tenantsSuspended = homeDashboardCount($tenants, 'suspended');
$tenantActivePercent = homeDashboardPercent($tenantsActive, $tenantsTotal);
$tenantPendingPercent = homeDashboardPercent($tenantsPending, $tenantsTotal);
$tenantSuspendedPercent = max(0, 100 - $tenantActivePercent - $tenantPendingPercent);

$emailsTotal = homeDashboardCount($contacts, 'emails');
$phonesTotal = homeDashboardCount($contacts, 'phones');
$addressesTotal = homeDashboardCount($contacts, 'addresses');
$contactTotal = $emailsTotal + $phonesTotal + $addressesTotal;
$contactAverage = $peopleTotal > 0 ? $contactTotal / $peopleTotal : 0;
$citiesTotal = homeDashboardCount($locations, 'cities');

$supportAvailable = !empty($support['available']);
$supportPending = homeDashboardCount($support, 'pending');
$supportOpen = homeDashboardCount($support, 'open');
$supportClosed = homeDashboardCount($support, 'closed');
$supportTotal = $supportPending + $supportOpen + $supportClosed;
$supportQueue = $supportPending + $supportOpen;
$supportClosedPercent = homeDashboardPercent($supportClosed, $supportTotal);

$activityAvailable = !empty($activity['available']);
$activityLastSevenDays = homeDashboardCount($activity, 'last_seven_days');
$canViewTenants = homeDashboardCan('tenant.index');
$canViewUsers = homeDashboardCan('user.index');

$healthParts = [];
if ($peopleTotal > 0)
	{
	$healthParts[] = $peopleActivePercent;
	}
if ($usersTotal > 0)
	{
	$healthParts[] = $usersActivePercent;
	}
if ($tenantsTotal > 0)
	{
	$healthParts[] = $tenantActivePercent;
	}
$healthScore = !empty($healthParts) ? (int) round(array_sum($healthParts) / count($healthParts)) : 0;
$healthTone = $healthScore >= 80 ? 'success' : ($healthScore >= 60 ? 'warning' : 'danger');

$attentionCount = $tenantsPending + $tenantsSuspended + $usersInactive + ($supportAvailable ? $supportPending : 0);
$attentionBase = $tenantsTotal + $usersTotal + ($supportAvailable ? $supportTotal : 0);
$attentionPercent = homeDashboardPercent($attentionCount, $attentionBase);

$trendMaximum = 1;
foreach ($monthlyRegistrations as $row)
	{
	$trendMaximum = max(
		$trendMaximum,
		(int) ($row['people'] ?? 0),
		(int) ($row['users'] ?? 0),
		(int) ($row['tenants'] ?? 0)
	);
	}

$trendCoordinates = [];
$trendPoints = [];
foreach (['people', 'users', 'tenants'] as $seriesKey)
	{
	$trendCoordinates[$seriesKey] = homeDashboardTrendCoordinates($monthlyRegistrations, $seriesKey, $trendMaximum);
	$trendPoints[$seriesKey] = implode(' ', array_map(
		static fn(array $point): string => $point['x'] . ',' . $point['y'],
		$trendCoordinates[$seriesKey]
	));
	}

$currentMonth = !empty($monthlyRegistrations) ? $monthlyRegistrations[count($monthlyRegistrations) - 1] : [];
$previousMonth = count($monthlyRegistrations) > 1 ? $monthlyRegistrations[count($monthlyRegistrations) - 2] : [];
$currentMonthLabel = !empty($currentMonth)
	? (string) ($currentMonth['label'] ?? '') . '/' . substr((string) ($currentMonth['year'] ?? ''), -2)
	: 'Mês atual';
$currentTenantRegistrations = (int) ($currentMonth['tenants'] ?? 0);
$tenantRegistrationDelta = $currentTenantRegistrations - (int) ($previousMonth['tenants'] ?? 0);

$quickActions = [];
if (homeDashboardCan('tenant.create'))
	{
	$quickActions[] = ['label' => 'Novo tenant', 'icon' => 'bi-database-add', 'url' => url('tenant/create'), 'primary' => true];
	}
if ($canViewTenants)
	{
	$quickActions[] = ['label' => 'Ver tenants', 'icon' => 'bi-database', 'url' => url('tenant'), 'primary' => false];
	}
if (homeDashboardCan('person.index'))
	{
	$quickActions[] = ['label' => 'Pessoas', 'icon' => 'bi-people', 'url' => url('person'), 'primary' => false];
	}
if ($canViewUsers)
	{
	$quickActions[] = ['label' => 'Usuários', 'icon' => 'bi-person-gear', 'url' => url('user'), 'primary' => false];
	}
if ($supportAvailable)
	{
	$quickActions[] = ['label' => 'Suporte', 'icon' => 'bi-headset', 'url' => url('support_chat'), 'primary' => false];
	}
if (homeDashboardCan('permission.manage'))
	{
	$quickActions[] = ['label' => 'Permissões', 'icon' => 'bi-shield-lock', 'url' => url('permission'), 'primary' => false];
	}

$contactRows = [
	['label' => 'E-mails', 'value' => $emailsTotal, 'icon' => 'bi-envelope', 'tone' => 'primary'],
	['label' => 'Telefones', 'value' => $phonesTotal, 'icon' => 'bi-telephone', 'tone' => 'success'],
	['label' => 'Endereços', 'value' => $addressesTotal, 'icon' => 'bi-geo-alt', 'tone' => 'warning'],
];
$contactMaximum = max(1, $emailsTotal, $phonesTotal, $addressesTotal);
?>

<link rel="stylesheet" href="<?= url('assets/css/home/index.css?v=5'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item active" aria-current="page">Início</li>
  </ol>
</nav>

<section class="home-dashboard container-fluid px-0" aria-labelledby="homeDashboardTitle">
  <header class="home-hero mb-4">
    <div class="row g-4 align-items-center">
      <div class="col-12 col-xl-7">
        <span class="home-eyebrow"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i> Central administrativa</span>
        <h1 class="home-title" id="homeDashboardTitle">Visão geral do manager_main</h1>
        <p class="home-subtitle mb-0">
          Acompanhe a operação dos tenants, o crescimento dos cadastros e os pontos que precisam de atenção.
        </p>
      </div>
      <div class="col-12 col-xl-5">
        <div class="home-hero-summary">
          <div>
            <span>Saúde operacional</span>
            <strong><?= $healthScore; ?>%</strong>
          </div>
          <progress class="home-progress home-progress-<?= $healthTone; ?>" max="100" value="<?= $healthScore; ?>" aria-label="Saúde operacional em <?= $healthScore; ?>%"></progress>
          <small><i class="bi bi-clock-history" aria-hidden="true"></i> Atualizado em <?= date('d/m/Y'); ?> às <?= date('H:i'); ?></small>
        </div>
      </div>
    </div>
  </header>

  <?php if (!empty($quickActions)): ?>
    <nav class="home-quick-actions mb-4" aria-label="Ações rápidas">
      <span class="home-quick-label">Acesso rápido</span>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($quickActions as $action): ?>
          <a class="btn <?= !empty($action['primary']) ? 'btn-primary' : 'btn-outline-secondary'; ?> d-inline-flex align-items-center gap-2" href="<?= htmlspecialchars($action['url'], ENT_QUOTES, 'UTF-8'); ?>">
            <i class="bi <?= htmlspecialchars($action['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
            <span><?= htmlspecialchars($action['label'], ENT_QUOTES, 'UTF-8'); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </nav>
  <?php endif; ?>

  <div class="row g-3 g-xl-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
      <article class="home-kpi home-kpi-primary h-100">
        <div class="home-kpi-heading">
          <span class="home-kpi-icon"><i class="bi bi-database-check" aria-hidden="true"></i></span>
          <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis"><?= $tenantActivePercent; ?>% ativos</span>
        </div>
        <span class="home-kpi-label">Tenants ativos</span>
        <strong class="home-kpi-value home-counter" data-target="<?= $tenantsActive; ?>"><?= number_format($tenantsActive, 0, ',', '.'); ?></strong>
        <span class="home-kpi-meta"><?= number_format($tenantsTotal, 0, ',', '.'); ?> no total · <?= number_format($currentTenantRegistrations, 0, ',', '.'); ?> novos em <?= htmlspecialchars($currentMonthLabel, ENT_QUOTES, 'UTF-8'); ?></span>
        <progress class="home-progress home-progress-primary" max="100" value="<?= $tenantActivePercent; ?>" aria-label="<?= $tenantActivePercent; ?>% dos tenants estão ativos"></progress>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="home-kpi home-kpi-success h-100">
        <div class="home-kpi-heading">
          <span class="home-kpi-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></span>
          <span class="badge rounded-pill bg-success-subtle text-success-emphasis"><?= $peopleActivePercent; ?>% ativos</span>
        </div>
        <span class="home-kpi-label">Pessoas ativas</span>
        <strong class="home-kpi-value home-counter" data-target="<?= $peopleActive; ?>"><?= number_format($peopleActive, 0, ',', '.'); ?></strong>
        <span class="home-kpi-meta"><?= number_format($peopleIndividual, 0, ',', '.'); ?> físicas · <?= number_format($peopleCompany, 0, ',', '.'); ?> jurídicas</span>
        <progress class="home-progress home-progress-success" max="100" value="<?= $peopleActivePercent; ?>" aria-label="<?= $peopleActivePercent; ?>% das pessoas estão ativas"></progress>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="home-kpi home-kpi-info h-100">
        <div class="home-kpi-heading">
          <span class="home-kpi-icon"><i class="bi bi-person-gear" aria-hidden="true"></i></span>
          <span class="badge rounded-pill bg-info-subtle text-info-emphasis"><?= $usersActivePercent; ?>% ativos</span>
        </div>
        <span class="home-kpi-label">Usuários ativos</span>
        <strong class="home-kpi-value home-counter" data-target="<?= $usersActive; ?>"><?= number_format($usersActive, 0, ',', '.'); ?></strong>
        <span class="home-kpi-meta"><?= number_format($privilegedUsers, 0, ',', '.'); ?> perfis administrativos · <?= number_format($usersInactive, 0, ',', '.'); ?> inativos</span>
        <progress class="home-progress home-progress-info" max="100" value="<?= $usersActivePercent; ?>" aria-label="<?= $usersActivePercent; ?>% dos usuários estão ativos"></progress>
      </article>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <article class="home-kpi home-kpi-warning h-100">
        <div class="home-kpi-heading">
          <span class="home-kpi-icon"><i class="bi bi-exclamation-diamond" aria-hidden="true"></i></span>
          <span class="badge rounded-pill <?= $attentionCount > 0 ? 'text-bg-warning' : 'text-bg-success'; ?>"><?= $attentionCount > 0 ? 'Revisar' : 'Tudo em dia'; ?></span>
        </div>
        <span class="home-kpi-label">Pontos de atenção</span>
        <strong class="home-kpi-value home-counter" data-target="<?= $attentionCount; ?>"><?= number_format($attentionCount, 0, ',', '.'); ?></strong>
        <span class="home-kpi-meta"><?= number_format($tenantsPending + $tenantsSuspended, 0, ',', '.'); ?> tenants · <?= number_format($usersInactive, 0, ',', '.'); ?> usuários<?= $supportAvailable ? ' · ' . number_format($supportPending, 0, ',', '.') . ' chats' : ''; ?></span>
        <progress class="home-progress home-progress-warning" max="100" value="<?= min(100, $attentionPercent); ?>" aria-label="<?= $attentionPercent; ?>% da base exige atenção"></progress>
      </article>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-8">
      <article class="home-panel h-100">
        <div class="home-panel-header">
          <div>
            <span class="home-eyebrow">Crescimento</span>
            <h2 class="home-panel-title">Novos cadastros nos últimos 6 meses</h2>
            <p class="home-panel-subtitle mb-0">Comparativo mensal entre pessoas, usuários e tenants.</p>
          </div>
          <span class="home-panel-chip"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= htmlspecialchars($currentMonthLabel, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>

        <div class="home-trend-totals" aria-label="Totais do mês atual">
          <span><i class="home-dot home-dot-people"></i><strong><?= number_format((int) ($currentMonth['people'] ?? 0), 0, ',', '.'); ?></strong> pessoas</span>
          <span><i class="home-dot home-dot-users"></i><strong><?= number_format((int) ($currentMonth['users'] ?? 0), 0, ',', '.'); ?></strong> usuários</span>
          <span><i class="home-dot home-dot-tenants"></i><strong><?= number_format($currentTenantRegistrations, 0, ',', '.'); ?></strong> tenants</span>
          <span class="home-trend-delta <?= $tenantRegistrationDelta >= 0 ? 'is-positive' : 'is-negative'; ?>">
            <i class="bi <?= $tenantRegistrationDelta >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right'; ?>" aria-hidden="true"></i>
            <?= $tenantRegistrationDelta >= 0 ? '+' : ''; ?><?= number_format($tenantRegistrationDelta, 0, ',', '.'); ?> tenants vs. mês anterior
          </span>
        </div>

        <div class="home-trend-chart-wrap">
          <svg class="home-trend-chart" viewBox="0 0 720 250" role="img" aria-labelledby="homeTrendTitle homeTrendDescription">
            <title id="homeTrendTitle">Evolução mensal de cadastros</title>
            <desc id="homeTrendDescription">Linhas com o total de pessoas, usuários e tenants criados em cada um dos últimos seis meses.</desc>
            <?php foreach ([24, 86, 148, 210] as $gridY): ?>
              <line class="home-chart-grid" x1="38" y1="<?= $gridY; ?>" x2="682" y2="<?= $gridY; ?>"></line>
            <?php endforeach; ?>
            <?php foreach (['people', 'users', 'tenants'] as $seriesKey): ?>
              <g class="home-trend-series home-series-<?= $seriesKey; ?>" data-chart-series="<?= $seriesKey; ?>">
                <polyline class="home-trend-line" points="<?= htmlspecialchars($trendPoints[$seriesKey], ENT_QUOTES, 'UTF-8'); ?>"></polyline>
                <?php foreach ($trendCoordinates[$seriesKey] as $point): ?>
                  <circle class="home-trend-point" cx="<?= $point['x']; ?>" cy="<?= $point['y']; ?>" r="5" tabindex="0" aria-label="<?= htmlspecialchars($point['label'], ENT_QUOTES, 'UTF-8'); ?>: <?= (int) $point['value']; ?> <?= $seriesKey === 'people' ? 'pessoas' : ($seriesKey === 'users' ? 'usuários' : 'tenants'); ?>">
                    <title><?= htmlspecialchars($point['label'], ENT_QUOTES, 'UTF-8'); ?>: <?= number_format((int) $point['value'], 0, ',', '.'); ?></title>
                  </circle>
                <?php endforeach; ?>
              </g>
            <?php endforeach; ?>
          </svg>
          <div class="home-trend-months" aria-hidden="true">
            <?php foreach ($monthlyRegistrations as $row): ?>
              <span><?= htmlspecialchars((string) ($row['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><small><?= (int) ($row['year'] ?? 0); ?></small></span>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="home-chart-controls" aria-label="Séries exibidas no gráfico">
          <button class="home-chart-toggle js-home-series-toggle" type="button" data-series="people" aria-pressed="true"><i class="home-dot home-dot-people"></i>Pessoas</button>
          <button class="home-chart-toggle js-home-series-toggle" type="button" data-series="users" aria-pressed="true"><i class="home-dot home-dot-users"></i>Usuários</button>
          <button class="home-chart-toggle js-home-series-toggle" type="button" data-series="tenants" aria-pressed="true"><i class="home-dot home-dot-tenants"></i>Tenants</button>
        </div>
      </article>
    </div>

    <div class="col-12 col-xl-4">
      <article class="home-panel h-100">
        <div class="home-panel-header">
          <div>
            <span class="home-eyebrow">Tenants</span>
            <h2 class="home-panel-title">Status da operação</h2>
            <p class="home-panel-subtitle mb-0">Distribuição atual da infraestrutura.</p>
          </div>
          <?php if ($canViewTenants): ?>
            <a class="btn btn-sm btn-outline-primary" href="<?= url('tenant'); ?>">Ver lista</a>
          <?php endif; ?>
        </div>

        <div class="home-donut-layout">
          <div class="home-donut-chart" role="img" aria-label="<?= $tenantActivePercent; ?>% ativos, <?= $tenantPendingPercent; ?>% pendentes e <?= $tenantSuspendedPercent; ?>% suspensos">
            <svg viewBox="0 0 120 120" aria-hidden="true">
              <circle class="home-donut-track" cx="60" cy="60" r="48" pathLength="100"></circle>
              <circle class="home-donut-segment home-donut-active" cx="60" cy="60" r="48" pathLength="100" stroke-dasharray="<?= $tenantActivePercent; ?> <?= 100 - $tenantActivePercent; ?>" stroke-dashoffset="0"></circle>
              <circle class="home-donut-segment home-donut-pending" cx="60" cy="60" r="48" pathLength="100" stroke-dasharray="<?= $tenantPendingPercent; ?> <?= 100 - $tenantPendingPercent; ?>" stroke-dashoffset="-<?= $tenantActivePercent; ?>"></circle>
              <circle class="home-donut-segment home-donut-suspended" cx="60" cy="60" r="48" pathLength="100" stroke-dasharray="<?= $tenantSuspendedPercent; ?> <?= 100 - $tenantSuspendedPercent; ?>" stroke-dashoffset="-<?= $tenantActivePercent + $tenantPendingPercent; ?>"></circle>
            </svg>
            <div><strong class="home-counter" data-target="<?= $tenantsTotal; ?>"><?= number_format($tenantsTotal, 0, ',', '.'); ?></strong><span>tenants</span></div>
          </div>

          <div class="home-status-list">
            <div><i class="home-dot home-dot-active"></i><span>Ativos</span><strong><?= number_format($tenantsActive, 0, ',', '.'); ?></strong><small><?= $tenantActivePercent; ?>%</small></div>
            <div><i class="home-dot home-dot-pending"></i><span>Pendentes</span><strong><?= number_format($tenantsPending, 0, ',', '.'); ?></strong><small><?= $tenantPendingPercent; ?>%</small></div>
            <div><i class="home-dot home-dot-suspended"></i><span>Suspensos</span><strong><?= number_format($tenantsSuspended, 0, ',', '.'); ?></strong><small><?= $tenantSuspendedPercent; ?>%</small></div>
          </div>
        </div>
      </article>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-12 col-lg-6 col-xxl-4">
      <article class="home-panel h-100">
        <div class="home-panel-header">
          <div>
            <span class="home-eyebrow">Pulso operacional</span>
            <h2 class="home-panel-title">Saúde dos cadastros</h2>
          </div>
          <span class="home-health-score home-health-<?= $healthTone; ?>"><?= $healthScore; ?>%</span>
        </div>

        <div class="home-health-list">
          <div>
            <span><i class="bi bi-database-check" aria-hidden="true"></i> Tenants ativos</span><strong><?= $tenantActivePercent; ?>%</strong>
            <progress class="home-progress home-progress-primary" max="100" value="<?= $tenantActivePercent; ?>"></progress>
          </div>
          <div>
            <span><i class="bi bi-person-check" aria-hidden="true"></i> Usuários ativos</span><strong><?= $usersActivePercent; ?>%</strong>
            <progress class="home-progress home-progress-info" max="100" value="<?= $usersActivePercent; ?>"></progress>
          </div>
          <div>
            <span><i class="bi bi-people" aria-hidden="true"></i> Pessoas ativas</span><strong><?= $peopleActivePercent; ?>%</strong>
            <progress class="home-progress home-progress-success" max="100" value="<?= $peopleActivePercent; ?>"></progress>
          </div>
        </div>

        <?php if ($activityAvailable): ?>
          <a class="home-inline-stat" href="<?= url('log'); ?>">
            <span><i class="bi bi-activity" aria-hidden="true"></i> Eventos de auditoria em 7 dias</span>
            <strong class="home-counter" data-target="<?= $activityLastSevenDays; ?>"><?= number_format($activityLastSevenDays, 0, ',', '.'); ?></strong>
          </a>
        <?php endif; ?>
      </article>
    </div>

    <div class="col-12 col-lg-6 col-xxl-4">
      <article class="home-panel h-100">
        <div class="home-panel-header">
          <div>
            <span class="home-eyebrow">Prioridades</span>
            <h2 class="home-panel-title">O que precisa de atenção</h2>
          </div>
          <span class="home-panel-chip"><?= number_format($attentionCount, 0, ',', '.'); ?> itens</span>
        </div>

        <div class="home-attention-list">
          <?php if ($attentionCount === 0): ?>
            <div class="home-all-clear">
              <i class="bi bi-check2-circle" aria-hidden="true"></i>
              <div><strong>Operação em dia</strong><span>Não há pendências nos indicadores monitorados.</span></div>
            </div>
          <?php else: ?>
            <?php if ($tenantsPending > 0): ?>
              <?php if ($canViewTenants): ?><a href="<?= url('tenant?status=pending'); ?>"><?php else: ?><div class="home-attention-item"><?php endif; ?>
                <span class="home-attention-icon text-bg-warning"><i class="bi bi-hourglass-split"></i></span><span><strong>Tenants pendentes</strong><small>Aguardando ativação ou revisão</small></span><b><?= number_format($tenantsPending, 0, ',', '.'); ?></b>
              <?php if ($canViewTenants): ?></a><?php else: ?></div><?php endif; ?>
            <?php endif; ?>
            <?php if ($tenantsSuspended > 0): ?>
              <?php if ($canViewTenants): ?><a href="<?= url('tenant?status=suspended'); ?>"><?php else: ?><div class="home-attention-item"><?php endif; ?>
                <span class="home-attention-icon text-bg-danger"><i class="bi bi-database-x"></i></span><span><strong>Tenants suspensos</strong><small>Requerem análise administrativa</small></span><b><?= number_format($tenantsSuspended, 0, ',', '.'); ?></b>
              <?php if ($canViewTenants): ?></a><?php else: ?></div><?php endif; ?>
            <?php endif; ?>
            <?php if ($usersInactive > 0): ?>
              <?php if ($canViewUsers): ?><a href="<?= url('user?active=0'); ?>"><?php else: ?><div class="home-attention-item"><?php endif; ?>
                <span class="home-attention-icon text-bg-secondary"><i class="bi bi-person-slash"></i></span><span><strong>Usuários inativos</strong><small>Contas fora da operação</small></span><b><?= number_format($usersInactive, 0, ',', '.'); ?></b>
              <?php if ($canViewUsers): ?></a><?php else: ?></div><?php endif; ?>
            <?php endif; ?>
            <?php if ($supportAvailable && $supportPending > 0): ?>
              <a href="<?= url('support_chat?status=pending'); ?>"><span class="home-attention-icon text-bg-info"><i class="bi bi-chat-left-dots"></i></span><span><strong>Chats aguardando</strong><small>Solicitações ainda não atendidas</small></span><b><?= number_format($supportPending, 0, ',', '.'); ?></b></a>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <?php if ($supportAvailable): ?>
          <div class="home-support-summary">
            <span><strong><?= number_format($supportQueue, 0, ',', '.'); ?></strong> na fila</span>
            <span><strong><?= number_format($supportClosed, 0, ',', '.'); ?></strong> encerrados</span>
            <span><strong><?= $supportClosedPercent; ?>%</strong> concluídos</span>
          </div>
        <?php endif; ?>
      </article>
    </div>

    <div class="col-12 col-xxl-4">
      <article class="home-panel h-100">
        <div class="home-panel-header">
          <div>
            <span class="home-eyebrow">Cobertura</span>
            <h2 class="home-panel-title">Canais de contato</h2>
            <p class="home-panel-subtitle mb-0"><?= number_format($contactAverage, 1, ',', '.'); ?> registros por pessoa.</p>
          </div>
          <span class="home-panel-chip"><?= number_format($contactTotal, 0, ',', '.'); ?> total</span>
        </div>

        <div class="home-contact-list">
          <?php foreach ($contactRows as $contactRow): ?>
            <?php $contactPercent = homeDashboardPercent((int) $contactRow['value'], $contactMaximum); ?>
            <div>
              <span class="home-contact-icon bg-<?= htmlspecialchars($contactRow['tone'], ENT_QUOTES, 'UTF-8'); ?>-subtle text-<?= htmlspecialchars($contactRow['tone'], ENT_QUOTES, 'UTF-8'); ?>-emphasis"><i class="bi <?= htmlspecialchars($contactRow['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
              <span><strong><?= htmlspecialchars($contactRow['label'], ENT_QUOTES, 'UTF-8'); ?></strong><small><?= $contactPercent; ?>% do maior canal</small></span>
              <b><?= number_format((int) $contactRow['value'], 0, ',', '.'); ?></b>
              <progress class="home-progress home-progress-<?= htmlspecialchars($contactRow['tone'], ENT_QUOTES, 'UTF-8'); ?>" max="100" value="<?= $contactPercent; ?>"></progress>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="home-location-note"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i><span><strong><?= number_format($citiesTotal, 0, ',', '.'); ?> cidades</strong> disponíveis para compor os endereços.</span></div>
      </article>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12 col-xl-6">
      <article class="home-panel h-100">
        <div class="home-panel-header">
          <div><span class="home-eyebrow">Cadastros recentes</span><h2 class="home-panel-title">Últimas pessoas</h2></div>
          <?php if (homeDashboardCan('person.index')): ?><a href="<?= url('person'); ?>" class="btn btn-sm btn-outline-primary">Ver todas</a><?php endif; ?>
        </div>
        <div class="home-record-list">
          <?php if (!empty($recentPeople)): ?>
            <?php foreach ($recentPeople as $person): ?>
              <?php $personName = (string) ($person['name'] ?? 'Pessoa'); ?>
              <?php if (homeDashboardCan('person.informations')): ?><a class="home-record" href="<?= url('person/informations/' . (int) $person['id']); ?>"><?php else: ?><div class="home-record"><?php endif; ?>
                <span class="home-avatar"><?= htmlspecialchars(homeDashboardInitial($personName), ENT_QUOTES, 'UTF-8'); ?></span>
                <span><strong><?= htmlspecialchars($personName, ENT_QUOTES, 'UTF-8'); ?></strong><small><?= (($person['type'] ?? '') === 'company') ? 'Pessoa jurídica' : 'Pessoa física'; ?> · cadastrada em <?= homeDashboardDate($person['created'] ?? null); ?></small></span>
                <span class="badge rounded-pill <?= !empty($person['active']) ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'; ?>"><?= !empty($person['active']) ? 'Ativa' : 'Inativa'; ?></span>
              <?php if (homeDashboardCan('person.informations')): ?></a><?php else: ?></div><?php endif; ?>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="home-empty"><i class="bi bi-inbox" aria-hidden="true"></i><span>Nenhuma pessoa cadastrada ainda.</span></div>
          <?php endif; ?>
        </div>
      </article>
    </div>

    <div class="col-12 col-xl-6">
      <article class="home-panel h-100">
        <div class="home-panel-header">
          <div><span class="home-eyebrow">Infraestrutura recente</span><h2 class="home-panel-title">Últimos tenants</h2></div>
          <?php if ($canViewTenants): ?><a href="<?= url('tenant'); ?>" class="btn btn-sm btn-outline-primary">Ver todos</a><?php endif; ?>
        </div>
        <div class="home-record-list">
          <?php if (!empty($recentTenants)): ?>
            <?php foreach ($recentTenants as $tenant): ?>
              <?php $tenantName = (string) ($tenant['associated_person_name'] ?? ('Tenant #' . (int) ($tenant['id'] ?? 0))); ?>
              <?php if (homeDashboardCan('tenant.informations')): ?><a class="home-record" href="<?= url('tenant/informations/' . (int) $tenant['id']); ?>"><?php else: ?><div class="home-record"><?php endif; ?>
                <span class="home-avatar home-avatar-tenant"><i class="bi bi-database" aria-hidden="true"></i></span>
                <span><strong><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8'); ?></strong><small>manager_<?= htmlspecialchars((string) ($tenant['database_number'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?> · criado em <?= homeDashboardDate($tenant['created'] ?? null); ?></small></span>
                <span class="badge rounded-pill <?= homeDashboardTenantBadgeClass((string) ($tenant['status'] ?? 'pending')); ?>"><?= homeDashboardTenantStatusLabel((string) ($tenant['status'] ?? 'pending')); ?></span>
              <?php if (homeDashboardCan('tenant.informations')): ?></a><?php else: ?></div><?php endif; ?>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="home-empty"><i class="bi bi-database" aria-hidden="true"></i><span>Nenhum tenant cadastrado ainda.</span></div>
          <?php endif; ?>
        </div>
      </article>
    </div>
  </div>
</section>

<script src="<?= url('assets/js/home/index.js?v=5'); ?>"></script>

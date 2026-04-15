<?php
$catalog = $catalog ?? [];
$stats = $stats ?? [
	'total_databases' => 0,
	'linked_tenants' => 0,
	'active_tenants' => 0,
	'unlinked_databases' => 0,
];
$report = $report ?? null;

$pageStyles = '<link rel="stylesheet" href="' . url('assets/css/database-manager.css?v=1') . '">';
$pageScripts = '<script src="' . url('assets/js/database-manager.js?v=1') . '"></script>';

$catalogJson = json_encode(
	$catalog,
	JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>

<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Database manager</li>
  </ol>
</nav>

<section class="dm-hero card border-0 shadow-sm overflow-hidden mb-4">
  <div class="card-body p-4 p-lg-5">
    <div class="row g-4 align-items-center">
      <div class="col-12 col-xl-7">
        <span class="badge rounded-pill text-bg-light text-dark dm-kicker mb-3">Módulo operacional</span>
        <h1 class="h3 mb-3">Database manager para execução SQL em lote</h1>
        <p class="mb-4 text-white-50">
          Execute scripts em todos os bancos dos tenants ou use <strong>banco inicial</strong> e
          <strong>banco final</strong> para um intervalo ou um banco específico. O progresso é
          atualizado em tempo real durante a execução.
        </p>
        <div class="d-flex flex-wrap gap-2">
          <span class="dm-chip"><i class="bi bi-database-check me-2"></i><?= (int) $stats['total_databases'] ?> bancos reconhecidos</span>
          <span class="dm-chip"><i class="bi bi-buildings me-2"></i><?= (int) $stats['linked_tenants'] ?> vinculados a tenants</span>
          <span class="dm-chip"><i class="bi bi-lightning-charge me-2"></i><?= (int) $stats['active_tenants'] ?> tenants ativos</span>
        </div>
      </div>
      <div class="col-12 col-xl-5">
        <div class="dm-hero-panel">
          <div class="row g-3">
            <div class="col-12 col-sm-6">
              <div class="dm-stat-card">
                <small>Total monitorado</small>
                <strong><?= (int) $stats['total_databases'] ?></strong>
                <span>Bases disponíveis para execução</span>
              </div>
            </div>
            <div class="col-12 col-sm-6">
              <div class="dm-stat-card">
                <small>Sem vínculo</small>
                <strong><?= (int) $stats['unlinked_databases'] ?></strong>
                <span>Bases sem tenant cadastrado</span>
              </div>
            </div>
            <div class="col-12">
              <div class="dm-stat-band">
                <i class="bi bi-shield-lock"></i>
                <div>
                  <strong>Execução controlada</strong>
                  <span>Cada banco é processado individualmente para isolar erros e manter o rastreio.</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="row g-4" id="databaseManagerApp" data-execute-url="<?= url('database_manager/execute'); ?>">
  <div class="col-12">
    <?php if (!empty($report)): ?>
      <div class="card border-0 shadow-sm dm-panel mb-4">
        <div class="card-body p-4">
          <div class="alert alert-<?= htmlspecialchars($report['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?> mb-4">
            <strong><?= htmlspecialchars($report['title'] ?? 'Resultado', ENT_QUOTES, 'UTF-8') ?>:</strong>
            <?= htmlspecialchars($report['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
          </div>

          <?php if (!empty($report['results']) && is_array($report['results'])): ?>
            <div class="d-flex flex-wrap gap-2 mb-3">
              <span class="badge text-bg-success"><?= (int) ($report['success_count'] ?? 0) ?> sucesso</span>
              <span class="badge text-bg-danger"><?= (int) ($report['error_count'] ?? 0) ?> erro</span>
            </div>

            <div class="list-group dm-log-list">
              <?php foreach ($report['results'] as $item): ?>
                <div class="list-group-item <?= !empty($item['success']) ? 'list-group-item-success' : 'list-group-item-danger' ?>">
                  <div class="log-title">
                    <strong><?= htmlspecialchars($item['database_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= (int) ($item['duration_ms'] ?? 0) ?> ms</small>
                  </div>
                  <p class="mb-0 mt-2">
                    <?php if (!empty($item['tenant_name'])): ?>
                      <?= htmlspecialchars($item['tenant_name'], ENT_QUOTES, 'UTF-8') ?>:
                    <?php endif; ?>
                    <?= htmlspecialchars($item['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                  </p>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm dm-panel d-none" id="executionPanel">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h2 class="h5 mb-1">Acompanhamento da execução</h2>
            <p class="text-muted mb-0" id="executionSubtitle">Aguardando início do lote.</p>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-success" id="successBadge">0 sucesso</span>
            <span class="badge text-bg-danger" id="errorBadge">0 erro</span>
          </div>
        </div>

        <div class="dm-progress-shell mb-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <strong id="progressLabel">Pronto para executar</strong>
            <span class="text-muted" id="progressMeta">0%</span>
          </div>
          <div class="progress" role="progressbar" aria-label="Progresso da execução" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
            <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%">0%</div>
          </div>
        </div>

        <div id="executionAlert" class="alert alert-info mb-4">
          O processamento será exibido aqui assim que o servidor iniciar o lote.
        </div>

        <div class="dm-log-shell">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h6 mb-0">Log de execução</h3>
            <small class="text-muted" id="logCount">0 eventos</small>
          </div>
          <div class="list-group dm-log-list" id="executionLog"></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-8">
    <form id="databaseManagerForm" class="card border-0 shadow-sm dm-panel" action="<?= url('database_manager/execute'); ?>" method="POST">
      <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
          <div>
            <h2 class="h5 mb-1">Orquestrar execução</h2>
            <p class="text-muted mb-0">Escolha o alcance do script e acompanhe o processamento por banco.</p>
          </div>
          <span class="badge rounded-pill text-bg-primary-subtle text-primary-emphasis px-3 py-2">
            <i class="bi bi-terminal me-2"></i>Execução SQL em lote
          </span>
        </div>

        <div class="dm-scope-grid mb-4">
          <input class="btn-check" type="radio" name="target_scope" id="targetScopeAll" value="all_tenants" checked>
          <label class="dm-scope-card" for="targetScopeAll">
            <span class="dm-scope-icon"><i class="bi bi-stack"></i></span>
            <strong>Todos os bancos dos tenants</strong>
            <small>Usa todas as bases `manager_####` encontradas no catálogo atual.</small>
          </label>

          <input class="btn-check" type="radio" name="target_scope" id="targetScopeRange" value="range">
          <label class="dm-scope-card" for="targetScopeRange">
            <span class="dm-scope-icon"><i class="bi bi-sliders2"></i></span>
            <strong>Intervalo ou banco específico</strong>
            <small>Preencha banco inicial e banco final. Se ambos forem iguais, executa em uma única base.</small>
          </label>
        </div>

        <div id="rangeFields" class="row g-3 mb-4 d-none">
          <div class="col-12 col-md-6">
            <label class="form-label" for="databaseStart">Banco inicial</label>
            <div class="input-group input-group-lg">
              <span class="input-group-text">manager_</span>
              <input class="form-control" id="databaseStart" name="database_start" type="text" inputmode="numeric" placeholder="0001" autocomplete="off">
            </div>
            <div class="form-text">Aceita apenas números. O sistema completa com zeros à esquerda.</div>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="databaseEnd">Banco final</label>
            <div class="input-group input-group-lg">
              <span class="input-group-text">manager_</span>
              <input class="form-control" id="databaseEnd" name="database_end" type="text" inputmode="numeric" placeholder="0005" autocomplete="off">
            </div>
            <div class="form-text">Deixe igual ao inicial para executar em um banco específico.</div>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label d-flex justify-content-between align-items-center" for="sqlCommand">
            <span>Comando SQL</span>
            <span class="text-muted small">Scripts com múltiplas instruções são aceitos</span>
          </label>
          <textarea
            class="form-control dm-sql-editor font-monospace"
            id="sqlCommand"
            name="sql_command"
            rows="12"
            placeholder="Exemplo:
UPDATE settings
SET value = 'ativo'
WHERE `key` = 'feature_flag';"
          ></textarea>
          <div class="form-text">
            Evite usar <code>USE</code> e <code>DELIMITER</code>. O módulo escolhe automaticamente o banco de cada execução.
          </div>
        </div>

        <div class="dm-note-grid mb-4">
          <div class="dm-note-card">
            <i class="bi bi-play-circle"></i>
            <div>
              <strong>Quando começa</strong>
              <span>A barra entra em execução assim que o servidor confirma o início do lote.</span>
            </div>
          </div>
          <div class="dm-note-card">
            <i class="bi bi-activity"></i>
            <div>
              <strong>Durante o lote</strong>
              <span>Cada banco concluído atualiza o progresso e adiciona uma linha no log.</span>
            </div>
          </div>
          <div class="dm-note-card">
            <i class="bi bi-flag"></i>
            <div>
              <strong>Quando termina</strong>
              <span>O resumo mostra quantos bancos passaram e quantos retornaram erro.</span>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
          <a href="<?= url('database_manager'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-counterclockwise me-2"></i>Resetar tela
          </a>
          <button type="submit" class="btn btn-primary btn-lg px-4" id="executeButton">
            <i class="bi bi-lightning-charge-fill me-2"></i>Executar lote SQL
          </button>
        </div>
      </div>
    </form>
  </div>

  <div class="col-12 col-xl-4">
    <div class="card border-0 shadow-sm dm-panel h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
          <div>
            <h2 class="h5 mb-1">Preview dos alvos</h2>
            <p class="text-muted mb-0">A seleção é recalculada enquanto você preenche os campos.</p>
          </div>
          <span class="badge rounded-pill text-bg-secondary" id="selectionBadge">0 bancos</span>
        </div>

        <div class="dm-preview-summary mb-3">
          <div>
            <small>Seleção atual</small>
            <strong id="selectionTitle">Todos os bancos dos tenants</strong>
          </div>
          <div>
            <small>Faixa considerada</small>
            <strong id="selectionRange">Catálogo completo</strong>
          </div>
        </div>

        <div id="selectionEmpty" class="alert alert-warning d-none mb-3">
          Nenhum banco encontrado para os critérios atuais.
        </div>

        <div class="list-group dm-target-list mb-4" id="targetPreviewList"></div>

        <div class="dm-mini-help">
          <h3 class="h6 mb-2">Dicas rápidas</h3>
          <ul class="mb-0">
            <li>Use o mesmo número em inicial e final para um banco específico.</li>
            <li>Os nomes reais dos bancos seguem o padrão <code>manager_####</code>.</li>
            <li>Erros em um banco não interrompem automaticamente os próximos.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<script type="application/json" id="databaseManagerCatalog"><?= $catalogJson ?></script>

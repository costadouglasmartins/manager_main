<?php
$catalog = $catalog ?? [];
$stats = $stats ?? [
	'total_databases' => 0,
	'linked_tenants' => 0,
	'active_tenants' => 0,
	'unlinked_databases' => 0,
];
$report = $report ?? null;

$catalogJson = json_encode(
	$catalog,
	JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<link rel="stylesheet" href="<?= url('assets/css/database_manager/index.css?v=1'); ?>">

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Database manager</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<header class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
  <div class="d-flex align-items-center gap-3">
    <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary p-3" aria-hidden="true"><i class="bi bi-database-gear fs-4"></i></span>
    <div><p class="small fw-semibold text-primary text-uppercase mb-1">Administração</p><h1 class="h3 fw-bold mb-1">Database manager</h1><p class="text-body-secondary mb-0">Execute comandos SQL em bases selecionadas com acompanhamento em tempo real.</p></div>
  </div>
</header>

<div class="alert alert-secondary mb-4">
  Execute comandos SQL em todos os bancos <code>manager_####</code> encontrados ou informe
  <strong>banco inicial</strong> e <strong>banco final</strong> para trabalhar com intervalo ou banco específico.
  O progresso e os resultados continuam disponíveis na própria tela.
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
  <span class="badge text-bg-primary"><?= (int) $stats['total_databases'] ?> bancos reconhecidos</span>
  <span class="badge text-bg-secondary"><?= (int) $stats['linked_tenants'] ?> vinculados a tenants</span>
  <span class="badge text-bg-success"><?= (int) $stats['active_tenants'] ?> tenants ativos</span>
  <span class="badge text-bg-warning"><?= (int) $stats['unlinked_databases'] ?> sem vínculo</span>
</div>

<div class="row g-4" id="databaseManagerApp" data-execute-url="<?= url('database_manager/execute'); ?>">
  <div class="col-12">
    <?php if (!empty($report)): ?>
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <div class="alert alert-<?= htmlspecialchars($report['type'] ?? 'info', ENT_QUOTES, 'UTF-8') ?> mb-3">
            <strong><?= htmlspecialchars($report['title'] ?? 'Resultado', ENT_QUOTES, 'UTF-8') ?>:</strong>
            <?= htmlspecialchars($report['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
          </div>

          <?php if (!empty($report['results']) && is_array($report['results'])): ?>
            <div class="d-flex flex-wrap gap-2 mb-3">
              <span class="badge text-bg-success"><?= (int) ($report['success_count'] ?? 0) ?> sucesso</span>
              <span class="badge text-bg-danger"><?= (int) ($report['error_count'] ?? 0) ?> erro</span>
            </div>

            <div class="list-group" style="max-height: 420px; overflow-y: auto;">
              <?php foreach ($report['results'] as $item): ?>
                <div class="list-group-item <?= !empty($item['success']) ? 'list-group-item-success' : 'list-group-item-danger' ?>">
                  <div class="d-flex justify-content-between align-items-start gap-3">
                    <strong><?= htmlspecialchars($item['database_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                    <small class="text-muted"><?= (int) ($item['duration_ms'] ?? 0) ?> ms</small>
                  </div>
                  <div class="mt-2">
                    <?php if (!empty($item['tenant_name'])): ?>
                      <?= htmlspecialchars($item['tenant_name'], ENT_QUOTES, 'UTF-8') ?>:
                    <?php endif; ?>
                    <?= htmlspecialchars($item['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="col-12 col-xl-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">Execução SQL em lote</h6>

        <form id="databaseManagerForm" action="<?= url('database_manager/execute'); ?>" method="POST" class="row g-3">
          <div class="col-12">
            <div class="border rounded p-3">
              <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="target_scope" id="targetScopeAll" value="all_tenants" checked>
                <label class="form-check-label" for="targetScopeAll">
                  Todos os bancos dos tenants
                  <small class="text-muted d-block">Usa todas as bases <code>manager_####</code> encontradas no catálogo atual.</small>
                </label>
              </div>

              <div class="form-check">
                <input class="form-check-input" type="radio" name="target_scope" id="targetScopeRange" value="range">
                <label class="form-check-label" for="targetScopeRange">
                  Intervalo ou banco específico
                  <small class="text-muted d-block">Preencha banco inicial e banco final. Se ambos forem iguais, executa em uma única base.</small>
                </label>
              </div>
            </div>
          </div>

          <div id="rangeFields" class="col-12 d-none">
            <div class="row g-3">
              <div class="col-12 col-md-6">
                <label class="form-label" for="databaseStart">Banco inicial</label>
                <div class="input-group">
                  <span class="input-group-text">manager_</span>
                  <input class="form-control" id="databaseStart" name="database_start" type="text" inputmode="numeric" placeholder="0001" autocomplete="off">
                </div>
                <div class="form-text">Aceita apenas números. O sistema completa com zeros à esquerda.</div>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label" for="databaseEnd">Banco final</label>
                <div class="input-group">
                  <span class="input-group-text">manager_</span>
                  <input class="form-control" id="databaseEnd" name="database_end" type="text" inputmode="numeric" placeholder="0005" autocomplete="off">
                </div>
                <div class="form-text">Deixe igual ao inicial para executar em um banco específico.</div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label" for="sqlCommand">Comando SQL</label>
            <textarea class="form-control font-monospace" id="sqlCommand" name="sql_command" rows="12" placeholder="Exemplo: UPDATE settings SET value = 'ativo' WHERE `key` = 'feature_flag';"></textarea>
            <div class="form-text">
              Evite usar <code>USE</code> e <code>DELIMITER</code>. O módulo escolhe automaticamente o banco de cada execução.
            </div>
          </div>

          <div class="col-12">
            <div class="card shadow-sm d-none" id="executionPanel">
              <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                  <div>
                    <h6 class="mb-1">Retorno SQL em tempo real</h6>
                    <p class="text-muted mb-0" id="executionSubtitle">Aguardando início do lote.</p>
                  </div>
                  <div class="d-flex flex-wrap gap-2">
                    <span class="badge text-bg-success" id="successBadge">0 sucesso</span>
                    <span class="badge text-bg-danger" id="errorBadge">0 erro</span>
                  </div>
                </div>

                <div class="mb-3">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong id="progressLabel">Pronto para executar</strong>
                    <span class="text-muted" id="progressMeta">0%</span>
                  </div>
                  <div class="progress" role="progressbar" aria-label="Progresso da execução" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width: 0%">0%</div>
                  </div>
                </div>

                <div id="executionAlert" class="alert alert-info mb-3">
                  O processamento será exibido aqui assim que o servidor iniciar o lote.
                </div>

                <div class="border rounded p-3 mb-3">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Respostas dos bancos</h6>
                    <small class="text-muted" id="sqlResponseCount">0 retornos</small>
                  </div>
                  <div id="sqlResponseEmpty" class="alert alert-light border mb-0">
                    As mensagens e tabelas de SELECT aparecerão aqui conforme cada banco terminar.
                  </div>
                  <div class="d-flex flex-column gap-3" id="sqlResponseResults"></div>
                </div>

                <div class="border rounded p-3">
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Log de execução</h6>
                    <small class="text-muted" id="logCount">0 eventos</small>
                  </div>
                  <div class="list-group" id="executionLog" style="max-height: 420px; overflow-y: auto;"></div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="alert alert-light border mb-0">
              <strong>Dicas de uso</strong>
              <ul class="mb-0 mt-2">
                <li>Use o mesmo número em inicial e final para um banco específico.</li>
                <li>Cada banco é processado individualmente para isolar erros e manter o rastreio.</li>
                <li>Se o JavaScript não responder, o backend ainda executa e devolve um resumo na própria tela.</li>
              </ul>
            </div>
          </div>

          <div class="col-12 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <a href="<?= url('database_manager'); ?>" class="btn btn-secondary">
              Resetar tela
            </a>
            <button type="submit" class="btn btn-primary" id="executeButton">
              Executar lote SQL
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
          <h6 class="mb-0">Preview dos alvos</h6>
          <span class="badge text-bg-secondary" id="selectionBadge">0 bancos</span>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-12">
            <div class="border rounded p-3">
              <small class="text-muted d-block">Seleção atual</small>
              <strong id="selectionTitle">Todos os bancos dos tenants</strong>
            </div>
          </div>
          <div class="col-12">
            <div class="border rounded p-3">
              <small class="text-muted d-block">Faixa considerada</small>
              <strong id="selectionRange">Catálogo completo</strong>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3">
          <span class="badge text-bg-primary"><?= (int) $stats['total_databases'] ?> total</span>
          <span class="badge text-bg-secondary"><?= (int) $stats['linked_tenants'] ?> com tenant</span>
          <span class="badge text-bg-success"><?= (int) $stats['active_tenants'] ?> ativos</span>
        </div>

        <div id="selectionEmpty" class="alert alert-warning d-none mb-3">
          Nenhum banco encontrado para os critérios atuais.
        </div>

        <div class="list-group mb-3" id="targetPreviewList" style="max-height: 360px; overflow-y: auto;"></div>

        <div class="alert alert-light border mb-0">
          <strong>Observações</strong>
          <ul class="mb-0 mt-2">
            <li>Os nomes reais dos bancos seguem o padrão <code>manager_####</code>.</li>
            <li>Bases sem vínculo continuam aparecendo no catálogo se existirem no servidor.</li>
            <li>Erros em um banco não interrompem automaticamente os próximos.</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<script type="application/json" id="databaseManagerCatalog"><?= $catalogJson ?></script>
<script src="<?= url('assets/js/database_manager/index.js?v=3'); ?>"></script>

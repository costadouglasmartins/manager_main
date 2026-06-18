<?php
$zipCodeStateOptions = $zipCodeStateOptions ?? [];
$zipCodeStateOptionsJson = htmlspecialchars(json_encode($zipCodeStateOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES, 'UTF-8');
?>

<!-- CSS -->
<link rel="stylesheet" href="<?= url('assets/css/tools/zip_code_crawler.css?v=12'); ?>">
<!-- /CSS -->

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Webcrawler de CEPs</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<section class="zip-code-crawler-tool" id="zipCodeCrawlerApp" data-stream-url="<?= url('tool/streamZipCodeCrawler'); ?>" data-state-options="<?= $zipCodeStateOptionsJson; ?>">
  <div class="zip-code-crawler-busy-overlay" aria-hidden="true"></div>
  <div class="row g-4">
    <div class="col-12 col-xxl-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="zip-code-crawler-icon text-bg-primary"><i class="bi bi-radar"></i></span>
            <div>
              <h1 class="zip-code-crawler-title mb-0">Webcrawler de CEPs</h1>
              <small class="text-secondary">Consulta sequencial com ViaCEP e BrasilAPI, com salvamento automático por resultado.</small>
            </div>
          </div>

          <form id="zipCodeCrawlerForm" class="row g-3" novalidate>
            <div class="col-12">
              <label class="form-label" for="crawlerProviderMode">Webservice</label>
              <select class="form-select" id="crawlerProviderMode" name="provider_mode">
                <option value="fallback">Falha automática entre serviços</option>
                <option value="viacep">ViaCEP</option>
                <option value="brasilapi">BrasilAPI</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label" for="crawlerStateAcronym">Estado</label>
              <select class="form-select" id="crawlerStateAcronym" name="state_acronym" required>
                <option value="">Selecione o estado</option>
                <?php foreach ($zipCodeStateOptions as $stateOption): ?>
                  <option value="<?= htmlspecialchars((string) $stateOption['acronym'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $stateOption['name'], ENT_QUOTES, 'UTF-8'); ?> (<?= htmlspecialchars((string) $stateOption['acronym'], ENT_QUOTES, 'UTF-8'); ?>)</option>
                <?php endforeach; ?>
              </select>
              <input id="crawlerStartZipCode" name="start_zip_code" type="hidden">
              <input id="crawlerEndZipCode" name="end_zip_code" type="hidden">
              <div class="form-text" id="crawlerStateRangeSummary">Selecione um estado para carregar a faixa de CEP.</div>
            </div>

            <div class="col-12">
              <label class="form-label" for="crawlerDelayMode">Tipo de intervalo</label>
              <select class="form-select" id="crawlerDelayMode" name="delay_mode">
                <option value="fixed">Fixo</option>
                <option value="random">Randômico (250-750 ms)</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label d-flex justify-content-between align-items-center" for="crawlerDelayMs">
                <span>Intervalo entre rodadas</span>
                <strong id="crawlerDelayLabel">500 ms</strong>
              </label>
              <input class="form-range" id="crawlerDelayMs" name="delay_ms" type="range" min="250" max="5000" step="250" value="500">
            </div>

            <div class="col-12">
              <label class="form-label d-flex justify-content-between align-items-center" for="crawlerConcurrency">
                <span>Consultas simultâneas</span>
                <strong id="crawlerConcurrencyLabel">3</strong>
              </label>
              <input class="form-range" id="crawlerConcurrency" name="concurrency" type="range" min="1" max="10" step="1" value="3">
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 justify-content-between align-items-center zip-code-crawler-actions">
              <button class="btn btn-secondary" type="button" id="crawlerClearButton">
                <i class="bi bi-eraser me-1"></i>Limpar
              </button>
              <div class="d-flex gap-2">
                <button class="btn btn-danger d-none" type="button" id="crawlerStopButton">
                  <i class="bi bi-stop-fill me-1"></i>Parar
                </button>
                <button class="btn btn-primary" type="submit" id="crawlerStartButton">
                  <i class="bi bi-play-fill me-1"></i>Iniciar
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xxl-8">
      <div class="card shadow-sm zip-code-crawler-monitor-card">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="zip-code-crawler-icon text-bg-success"><i class="bi bi-graph-up-arrow"></i></span>
            <h2 class="zip-code-crawler-title mb-0">Acompanhamento em tempo real</h2>
          </div>

          <div class="zip-code-crawler-stats mb-3">
            <div class="zip-code-crawler-stat-card"><small>Processados</small><strong id="crawlerProcessedCount">0</strong></div>
            <div class="zip-code-crawler-stat-card"><small>Novos salvos</small><strong id="crawlerFoundCount">0</strong></div>
            <div class="zip-code-crawler-stat-card"><small>Falhas</small><strong id="crawlerFailedCount">0</strong></div>
            <div class="zip-code-crawler-stat-card"><small>CEPs/seg</small><strong id="crawlerLookupRateCount">0,00</strong></div>
            <div class="zip-code-crawler-stat-card"><small>Status</small><strong id="crawlerStatusText">Aguardando início</strong></div>
          </div>

          <div class="border rounded overflow-hidden zip-code-crawler-results d-flex flex-column">
            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary">
              <i class="bi bi-list-check text-primary"></i>
              <span class="fw-semibold">Resultados</span>
              <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="Navegação dos resultados">
                <button class="btn btn-outline-secondary" type="button" id="crawlerResultsTopButton" title="Ir para o início">
                  <i class="bi bi-arrow-up" aria-hidden="true"></i>
                  <span class="visually-hidden">Ir para o início</span>
                </button>
                <button class="btn btn-outline-secondary" type="button" id="crawlerResultsBottomButton" title="Ir para o último consultado">
                  <i class="bi bi-arrow-down" aria-hidden="true"></i>
                  <span class="visually-hidden">Ir para o último consultado</span>
                </button>
              </div>
            </div>
            <div class="list-group list-group-flush zip-code-crawler-results-info d-none" id="crawlerResultsInfo" aria-live="polite"></div>
            <div class="list-group list-group-flush zip-code-crawler-results-body" id="crawlerResultsBody" role="log" aria-live="polite" aria-relevant="additions">
              <div class="list-group-item text-secondary zip-code-crawler-results-empty">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-hourglass-split"></i>
                  <span>Aguardando início - selecione um estado e execute o crawler.</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="<?= url('assets/js/tools/zip_code_crawler.js?v=28'); ?>"></script>

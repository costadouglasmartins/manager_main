<?php
$stateOptions = $stateOptions ?? [];
$userAgentOptions = $userAgentOptions ?? [];
$maxResults = $maxResults ?? 500;
?>

<!-- CSS -->
<link rel="stylesheet" href="<?= url('assets/css/tools/web_address_crawler.css?v=1'); ?>">
<!-- /CSS -->

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Webcrawler de endereços</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<section class="web-address-crawler-tool" id="webAddressCrawlerApp" data-stream-url="<?= url('tool/streamWebAddressCrawler'); ?>" data-results-url="<?= url('tool/webAddressCrawlerResults'); ?>">
  <div class="row g-4">
    <div class="col-12 col-xxl-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="web-address-crawler-icon text-bg-primary"><i class="bi bi-geo-alt"></i></span>
            <div class="flex-grow-1">
              <h1 class="web-address-crawler-title mb-0">Webcrawler de endereços</h1>
              <small class="text-secondary">Prospecção de clientes por palavras-chave via DuckDuckGo.</small>
            </div>
          </div>

          <form id="webCrawlerForm" class="row g-3" novalidate>
            <div class="col-12">
              <label class="form-label" for="webCrawlerKeywords">Palavras-chave</label>
              <textarea class="form-control" id="webCrawlerKeywords" name="keywords" rows="3" placeholder="padaria&#10;clínica odontológica&#10;escritório de contabilidade"></textarea>
              <div class="form-text">Uma busca por linha. A localização escolhida abaixo é adicionada a cada busca.</div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="webCrawlerState">Estado</label>
              <select class="form-select" id="webCrawlerState" name="state_acronym">
                <option value="">Todos</option>
                <?php foreach ($stateOptions as $stateOption): ?>
                  <option value="<?= htmlspecialchars((string) $stateOption['acronym'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $stateOption['name'], ENT_QUOTES, 'UTF-8'); ?> (<?= htmlspecialchars((string) $stateOption['acronym'], ENT_QUOTES, 'UTF-8'); ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="webCrawlerCity">Cidade</label>
              <input class="form-control" id="webCrawlerCity" name="city" type="text" autocomplete="off" placeholder="Florianópolis">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="webCrawlerZipCode">CEP</label>
              <input class="form-control" id="webCrawlerZipCode" name="zip_code" type="text" inputmode="numeric" autocomplete="off" placeholder="88000-000">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="webCrawlerMaxResults">Limite de resultados</label>
              <input class="form-control" id="webCrawlerMaxResults" name="max_results" type="number" min="1" max="<?= (int) $maxResults; ?>" step="1" value="50">
            </div>

            <div class="col-12">
              <label class="form-label" for="webCrawlerUserAgent">User Agent</label>
              <select class="form-select" id="webCrawlerUserAgent" name="user_agent">
                <?php foreach ($userAgentOptions as $userAgentOption): ?>
                  <option value="<?= htmlspecialchars((string) $userAgentOption['value'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $userAgentOption['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label d-flex justify-content-between align-items-center" for="webCrawlerConcurrency">
                <span>Consultas simultâneas</span>
                <strong id="webCrawlerConcurrencyLabel">5</strong>
              </label>
              <input class="form-range" id="webCrawlerConcurrency" name="concurrency" type="range" min="1" max="20" step="1" value="5">
            </div>

            <div class="col-12">
              <label class="form-label d-flex justify-content-between align-items-center" for="webCrawlerDelay">
                <span>Intervalo entre requisições</span>
                <strong id="webCrawlerDelayLabel">1000 ms</strong>
              </label>
              <input class="form-range" id="webCrawlerDelay" name="delay_ms" type="range" min="250" max="10000" step="250" value="1000">
            </div>

            <div class="col-12">
              <div class="alert alert-info d-none mb-0" id="webCrawlerMessage" role="status"></div>
            </div>

            <div class="col-12 d-flex flex-nowrap gap-2 align-items-center web-address-crawler-actions">
              <button class="btn btn-sm btn-secondary text-truncate" type="button" id="webCrawlerClearButton">
                <i class="bi bi-eraser me-1"></i>Limpar
              </button>
              <button class="btn btn-sm btn-danger text-truncate d-none" type="button" id="webCrawlerStopButton">
                <i class="bi bi-stop-fill me-1"></i>Parar
              </button>
              <button class="btn btn-sm btn-primary text-truncate" onclick="window.open('<?= url('tool/webAddressCrawlerResults'); ?>', '_blank')" type="button" id="webCrawlerResultsButton">
                <i class="bi bi-list-check me-1"></i>Registros
              </button>
              <button class="btn btn-sm btn-primary text-truncate" type="submit" id="webCrawlerStartButton">
                <i class="bi bi-play-fill me-1"></i>Iniciar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xxl-8">
      <div class="card shadow-sm web-address-crawler-monitor-card">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="web-address-crawler-icon text-bg-success"><i class="bi bi-activity"></i></span>
            <h2 class="web-address-crawler-title mb-0">Acompanhamento em tempo real</h2>
          </div>

          <div class="web-address-crawler-progress mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <small class="text-secondary">Progresso</small>
              <strong id="webCrawlerProgressText">0%</strong>
            </div>
            <div class="progress" role="progressbar" aria-label="Progresso da pesquisa" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
              <div class="progress-bar" id="webCrawlerProgressBar" style="width: 0%"></div>
            </div>
          </div>

          <div class="web-address-crawler-stats mb-3">
            <div class="web-address-crawler-stat"><small>Alvos</small><strong id="webCrawlerTargetCount">0</strong></div>
            <div class="web-address-crawler-stat"><small>Processados</small><strong id="webCrawlerProcessedCount">0</strong></div>
            <div class="web-address-crawler-stat"><small>Encontrados</small><strong id="webCrawlerFoundCount">0</strong></div>
            <div class="web-address-crawler-stat"><small>Matches</small><strong id="webCrawlerMatchedCount">0</strong></div>
            <div class="web-address-crawler-stat"><small>Falhas</small><strong id="webCrawlerFailedCount">0</strong></div>
            <div class="web-address-crawler-stat"><small>Status</small><strong id="webCrawlerStatusText">Aguardando início</strong></div>
          </div>

          <div class="border rounded overflow-hidden web-address-crawler-results d-flex flex-column">
            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary">
              <i class="bi bi-list-check text-primary"></i>
              <span class="fw-semibold">Registros salvos</span>
              <a class="btn btn-sm btn-outline-primary ms-auto" href="<?= url('tool/webAddressCrawlerResults'); ?>" target="_blank">
                <i class="bi bi-box-arrow-up-right me-1"></i>Abrir registros
              </a>
            </div>
            <div class="list-group list-group-flush web-address-crawler-results-body" id="webCrawlerResultsBody" role="log" aria-live="polite" aria-relevant="additions">
              <div class="list-group-item text-secondary web-address-crawler-results-empty">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-hourglass-split"></i>
                  <span>Aguardando início da pesquisa.</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="<?= url('assets/js/tools/web_address_crawler.js?v=1'); ?>"></script>

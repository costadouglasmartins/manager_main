<?php
$serpApiUsage = $serpApiUsage ?? ['period' => date('Y-m'), 'limit' => 250, 'used' => 0, 'remaining' => 250, 'schema_available' => false];
$serpApiHasKey = !empty($serpApiHasKey);
$serpApiSearchTypeOptions = $serpApiSearchTypeOptions ?? [];
$serpApiCountryOptions = $serpApiCountryOptions ?? [];
$serpApiLanguageOptions = $serpApiLanguageOptions ?? [];
$serpApiMaxResults = $serpApiMaxResults ?? 100;
$serpApiQueryLimitMax = $serpApiQueryLimitMax ?? 250;
$serpApiUsageJson = htmlspecialchars(json_encode($serpApiUsage, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES, 'UTF-8');
?>

<!-- CSS -->
<link rel="stylesheet" href="<?= url('assets/css/tools/serpapi_webcrawler.css?v=2'); ?>">
<!-- /CSS -->

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">SERPAPI Webcrawler</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<section class="serpapi-webcrawler-tool" id="serpApiWebCrawlerApp" data-stream-url="<?= url('tool/streamSerpApiWebCrawler'); ?>" data-results-url="<?= url('tool/serpApiWebCrawlerResults'); ?>" data-initial-usage="<?= $serpApiUsageJson; ?>" data-has-api-key="<?= $serpApiHasKey ? '1' : '0'; ?>">
  <div class="row g-4">
    <div class="col-12 col-xxl-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="serpapi-webcrawler-icon text-bg-primary"><i class="bi bi-search"></i></span>
            <div class="flex-grow-1">
              <h1 class="serpapi-webcrawler-title mb-0">SERPAPI Webcrawler</h1>
              <small class="text-secondary">Busca Google via SerpAPI com limite mensal interno.</small>
            </div>
          </div>

          <div class="serpapi-webcrawler-quota mb-3">
            <div>
              <small class="text-secondary d-block">Consultas restantes</small>
              <strong id="serpApiRemainingCount"><?= number_format((int) ($serpApiUsage['remaining'] ?? 0), 0, ',', '.'); ?></strong>
            </div>
            <div>
              <small class="text-secondary d-block">Usadas no mês</small>
              <strong id="serpApiUsedCount"><?= number_format((int) ($serpApiUsage['used'] ?? 0), 0, ',', '.'); ?></strong>
            </div>
            <div>
              <small class="text-secondary d-block">Limite</small>
              <strong id="serpApiLimitCount"><?= number_format((int) ($serpApiUsage['limit'] ?? 250), 0, ',', '.'); ?></strong>
            </div>
          </div>

          <?php if (!$serpApiHasKey): ?>
            <div class="alert alert-warning" role="alert">Configure <code>SERPAPI_API_KEY</code> ou <code>APP_SERPAPI_API_KEY</code> antes de executar consultas.</div>
          <?php endif; ?>

          <?php if (empty($serpApiUsage['schema_available'])): ?>
            <div class="alert alert-warning" role="alert">As tabelas da SERPAPI ainda não estão disponíveis no banco atual.</div>
          <?php endif; ?>

          <form id="serpApiWebCrawlerForm" class="row g-3" novalidate>
            <div class="col-12">
              <label class="form-label" for="serpApiQuery">Consulta</label>
              <input class="form-control" id="serpApiQuery" name="query" type="text" autocomplete="off" placeholder="clínica odontológica Florianópolis" required>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="serpApiSearchType">Tipo de busca</label>
              <select class="form-select" id="serpApiSearchType" name="search_type">
                <?php foreach ($serpApiSearchTypeOptions as $searchTypeOption): ?>
                  <option value="<?= htmlspecialchars((string) $searchTypeOption['value'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $searchTypeOption['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="serpApiResultCount">Resultados</label>
              <input class="form-control" id="serpApiResultCount" name="result_count" type="number" min="1" max="<?= (int) $serpApiMaxResults; ?>" step="1" value="10">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="serpApiExecutionQueryLimit">Consultas nesta execução</label>
              <input class="form-control" id="serpApiExecutionQueryLimit" name="execution_query_limit" type="number" min="1" max="<?= (int) $serpApiQueryLimitMax; ?>" step="1" value="1">
              <div class="form-text">Teto manual de chamadas SerpAPI permitidas nesta execução.</div>
            </div>

            <div class="col-12">
              <label class="form-label" for="serpApiLocation">Localização</label>
              <input class="form-control" id="serpApiLocation" name="location" type="text" autocomplete="off" placeholder="Florianópolis, Santa Catarina, Brazil">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="serpApiCountryCode">País</label>
              <select class="form-select" id="serpApiCountryCode" name="country_code">
                <?php foreach ($serpApiCountryOptions as $countryOption): ?>
                  <option value="<?= htmlspecialchars((string) $countryOption['value'], ENT_QUOTES, 'UTF-8'); ?>" <?= (string) $countryOption['value'] === 'br' ? 'selected' : ''; ?>><?= htmlspecialchars((string) $countryOption['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="serpApiLanguageCode">Idioma</label>
              <select class="form-select" id="serpApiLanguageCode" name="language_code">
                <?php foreach ($serpApiLanguageOptions as $languageOption): ?>
                  <option value="<?= htmlspecialchars((string) $languageOption['value'], ENT_QUOTES, 'UTF-8'); ?>" <?= (string) $languageOption['value'] === 'pt' ? 'selected' : ''; ?>><?= htmlspecialchars((string) $languageOption['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="serpApiDevice">Dispositivo</label>
              <select class="form-select" id="serpApiDevice" name="device">
                <option value="desktop">Desktop</option>
                <option value="mobile">Mobile</option>
                <option value="tablet">Tablet</option>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label" for="serpApiSafeSearch">Safe Search</label>
              <select class="form-select" id="serpApiSafeSearch" name="safe_search">
                <option value="active">Ativo</option>
                <option value="off">Desligado</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label" for="serpApiStartOffset">Offset inicial</label>
              <input class="form-control" id="serpApiStartOffset" name="start_offset" type="number" min="0" max="900" step="10" value="0">
              <div class="form-text">Posição inicial da SERP. Use 0 para a primeira página, 10 para começar pelo 11º resultado, 20 pelo 21º, e assim por diante.</div>
            </div>

            <div class="col-12">
              <div class="alert alert-info d-none mb-0" id="serpApiMessage" role="status"></div>
            </div>

            <div class="col-12 d-flex flex-nowrap gap-2 align-items-center serpapi-webcrawler-actions">
              <button class="btn btn-sm btn-secondary text-truncate" type="button" id="serpApiClearButton">
                <i class="bi bi-eraser me-1"></i>Limpar
              </button>
              <button class="btn btn-sm btn-danger text-truncate d-none" type="button" id="serpApiStopButton">
                <i class="bi bi-stop-fill me-1"></i>Parar
              </button>
              <button class="btn btn-sm btn-primary text-truncate" onclick="window.open('<?= url('tool/serpApiWebCrawlerResults'); ?>', '_blank')" type="button" id="serpApiResultsButton">
                <i class="bi bi-list-check me-1"></i>Resultados
              </button>
              <button class="btn btn-sm btn-primary text-truncate" type="submit" id="serpApiStartButton">
                <i class="bi bi-play-fill me-1"></i>Pesquisar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xxl-8">
      <div class="card shadow-sm serpapi-webcrawler-monitor-card">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="serpapi-webcrawler-icon text-bg-success"><i class="bi bi-activity"></i></span>
            <h2 class="serpapi-webcrawler-title mb-0">Acompanhamento em tempo real</h2>
          </div>

          <div class="serpapi-webcrawler-stats mb-3">
            <div class="serpapi-webcrawler-stat"><small>Solicitações</small><strong id="serpApiQueryCount">0</strong></div>
            <div class="serpapi-webcrawler-stat"><small>Resultados</small><strong id="serpApiFoundCount">0</strong></div>
            <div class="serpapi-webcrawler-stat"><small>Tempo</small><strong id="serpApiResponseTime">0 ms</strong></div>
            <div class="serpapi-webcrawler-stat"><small>HTTP</small><strong id="serpApiHttpStatus">-</strong></div>
            <div class="serpapi-webcrawler-stat"><small>Status</small><strong id="serpApiStatusText">Aguardando início</strong></div>
          </div>

          <div class="border rounded overflow-hidden serpapi-webcrawler-results d-flex flex-column">
            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary">
              <i class="bi bi-list-check text-primary"></i>
              <span class="fw-semibold">Retornos do servidor e resultados</span>
              <a class="btn btn-sm btn-outline-primary ms-auto" href="<?= url('tool/serpApiWebCrawlerResults'); ?>" target="_blank">
                <i class="bi bi-box-arrow-up-right me-1"></i>Abrir resultados
              </a>
            </div>
            <div class="list-group list-group-flush serpapi-webcrawler-results-body" id="serpApiResultsBody" role="log" aria-live="polite" aria-relevant="additions">
              <div class="list-group-item text-secondary serpapi-webcrawler-results-empty">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-hourglass-split"></i>
                  <span>Aguardando o primeiro retorno do servidor.</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="<?= url('assets/js/tools/serpapi_webcrawler.js?v=5'); ?>"></script>

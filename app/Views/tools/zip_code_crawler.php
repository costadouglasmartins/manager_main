<!-- CSS -->
<link rel="stylesheet" href="<?= url('assets/css/tools/zip_code_crawler.css?v=1'); ?>">
<!-- /CSS -->

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Webcrawler de CEPs</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<section class="zip-code-crawler-tool" id="zipCodeCrawlerApp" data-stream-url="<?= url('tool/streamZipCodeCrawler'); ?>" data-save-url="<?= url('tool/saveZipCodeCrawlerResult'); ?>" data-batch-save-url="<?= url('tool/saveZipCodeCrawlerResults'); ?>">
  <div class="row g-4">
    <div class="col-12 col-xxl-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="zip-code-crawler-icon text-bg-primary"><i class="bi bi-radar"></i></span>
            <div>
              <h1 class="zip-code-crawler-title mb-0">Webcrawler de CEPs</h1>
              <small class="text-secondary">Consulta sequencial com ViaCEP e BrasilAPI, com salvamento manual por resultado.</small>
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
              <label class="form-label" for="crawlerStartZipCode">CEP inicial</label>
              <input class="form-control" id="crawlerStartZipCode" name="start_zip_code" type="text" inputmode="numeric" autocomplete="off" maxlength="9" placeholder="00000-000" required>
            </div>

            <div class="col-12">
              <label class="form-label" for="crawlerEndZipCode">CEP final</label>
              <input class="form-control" id="crawlerEndZipCode" name="end_zip_code" type="text" inputmode="numeric" autocomplete="off" maxlength="9" placeholder="00000-000" required>
            </div>

            <div class="col-12">
              <label class="form-label d-flex justify-content-between align-items-center" for="crawlerDelayMs">
                <span>Intervalo entre consultas</span>
                <strong id="crawlerDelayLabel">500 ms</strong>
              </label>
              <input class="form-range" id="crawlerDelayMs" name="delay_ms" type="range" min="250" max="5000" step="250" value="500">
            </div>

            <div class="col-12">
              <label class="form-label" for="crawlerMaxResults">Máximo de CEPs</label>
              <input class="form-control" id="crawlerMaxResults" name="max_results" type="number" min="1" step="1" value="100" readonly>
            </div>

            <div class="col-12">
              <div class="border rounded p-3">
                <div class="mb-2">
                  <strong class="d-block">Campos para salvar manualmente</strong>
                  <small class="text-secondary">A pesquisa não grava nada sozinha. Marque os campos desejados e salve cada resultado manualmente.</small>
                </div>
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" id="saveCitySwitch" name="save_city" type="checkbox" checked>
                  <label class="form-check-label" for="saveCitySwitch">Salvar cidade</label>
                </div>
                <div class="form-check form-switch mb-2">
                  <input class="form-check-input" id="saveNeighborhoodSwitch" name="save_neighborhood" type="checkbox" checked>
                  <label class="form-check-label" for="saveNeighborhoodSwitch">Salvar bairro</label>
                </div>
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input" id="createCitySwitch" name="create_city" type="checkbox">
                  <label class="form-check-label" for="createCitySwitch">Criar cidade quando necessário</label>
                </div>
              </div>
            </div>

            <div class="col-12">
              <div class="alert alert-warning mb-0">
                O atraso entre consultas ajuda a reduzir riscos de bloqueio por validações automáticas.
              </div>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 justify-content-between align-items-center">
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
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="zip-code-crawler-icon text-bg-success"><i class="bi bi-graph-up-arrow"></i></span>
            <h2 class="zip-code-crawler-title mb-0">Acompanhamento em tempo real</h2>
          </div>

          <div class="zip-code-crawler-stats mb-3">
            <div class="zip-code-crawler-stat-card"><small>Processados</small><strong id="crawlerProcessedCount">0</strong></div>
            <div class="zip-code-crawler-stat-card"><small>Encontrados</small><strong id="crawlerFoundCount">0</strong></div>
            <div class="zip-code-crawler-stat-card"><small>Falhas</small><strong id="crawlerFailedCount">0</strong></div>
            <div class="zip-code-crawler-stat-card"><small>Status</small><strong id="crawlerStatusText">Aguardando início</strong></div>
          </div>

          <div class="progress mb-3" role="progressbar" aria-label="Progresso da pesquisa">
            <div class="progress-bar" id="crawlerProgressBar" style="width: 0%;">0%</div>
          </div>

          <div class="alert alert-secondary zip-code-crawler-message" id="crawlerMessage" role="status" aria-live="polite">
            Defina um intervalo de CEP e inicie a pesquisa.
          </div>

          <div class="table-responsive zip-code-crawler-table-wrap">
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
              <div class="form-check">
                <input class="form-check-input" id="crawlerSelectAllResults" type="checkbox">
                <label class="form-check-label" for="crawlerSelectAllResults">Selecionar todos os resultados encontrados</label>
              </div>
              <button class="btn btn-success" type="button" id="crawlerBatchSaveButton"><i class="bi bi-save2 me-1"></i>Salvar selecionados</button>
            </div>
            <table class="table table-sm table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>Selecionar</th>
                  <th>CEP</th>
                  <th>Resultado</th>
                  <th>Fonte</th>
                  <th>Localização</th>
                  <th>Detalhes</th>
                  <th>Salvar</th>
                </tr>
              </thead>
              <tbody id="crawlerResultsBody">
                <tr><td colspan="7" class="text-center text-secondary">Nenhuma pesquisa em andamento.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="<?= url('assets/js/tools/zip_code_crawler.js?v=1'); ?>"></script>

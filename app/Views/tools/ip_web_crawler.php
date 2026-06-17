<?php
$ipCountryOptions = $ipCountryOptions ?? [];
?>

<!-- CSS -->
<link rel="stylesheet" href="<?= url('assets/css/tools/ip_web_crawler.css?v=3'); ?>">
<!-- /CSS -->

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Webcrawler por IP</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<section class="ip-web-crawler-tool" id="ipWebCrawlerApp" data-stream-url="<?= url('tool/streamIpWebCrawler'); ?>" data-country-ranges-url="<?= url('tool/ipWebCrawlerCountryRanges'); ?>">
  <div class="ip-web-crawler-busy-overlay" aria-hidden="true"></div>
  <div class="row g-4">
    <div class="col-12 col-xxl-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="ip-web-crawler-icon text-bg-primary"><i class="bi bi-router-fill"></i></span>
            <div>
              <h1 class="ip-web-crawler-title mb-0">Webcrawler por IP</h1>
              <small class="text-secondary">Pesquisa HTTP/HTTPS com cache de falhas e robots.txt.</small>
            </div>
          </div>

          <form id="ipWebCrawlerForm" class="row g-3" novalidate>
            <div class="col-12">
              <label class="form-label" for="ipCrawlerSearchTerms">Pesquisa</label>
              <textarea class="form-control" id="ipCrawlerSearchTerms" name="search_terms" rows="2" placeholder="termo, outro termo"></textarea>
            </div>

            <div class="col-12">
              <label class="form-label" for="ipCrawlerRangeMode">Modo da faixa</label>
              <select class="form-select" id="ipCrawlerRangeMode" name="range_mode">
                <option value="amount">IP inicial + quantidade</option>
                <option value="cidr">CIDR</option>
                <option value="manual">Faixa manual</option>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label" for="ipCrawlerCountryCode">País</label>
              <div class="input-group">
                <select class="form-select" id="ipCrawlerCountryCode" name="country_code">
                  <?php foreach ($ipCountryOptions as $countryOption): ?>
                    <option value="<?= htmlspecialchars((string) $countryOption['code'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $countryOption['name'], ENT_QUOTES, 'UTF-8'); ?> (<?= htmlspecialchars((string) $countryOption['code'], ENT_QUOTES, 'UTF-8'); ?>)</option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-primary" type="button" id="ipCrawlerLoadCountryButton"><i class="bi bi-cloud-arrow-down me-1"></i>Carregar</button>
              </div>
              <label class="form-label mt-2" for="ipCrawlerCustomCountryCode">Código ISO</label>
              <input class="form-control" id="ipCrawlerCustomCountryCode" type="text" maxlength="2" autocomplete="off" placeholder="BR">
              <div class="d-flex gap-2 align-items-center mt-2">
                <select class="form-select d-none" id="ipCrawlerCountryRangeSelect" aria-label="Faixas carregadas por país"></select>
                <button class="btn btn-outline-secondary d-none" type="button" id="ipCrawlerRefreshCountryButton" title="Atualizar faixas"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i><span class="visually-hidden">Atualizar faixas</span></button>
              </div>
              <div class="form-text" id="ipCrawlerCountrySummary">Selecione um país para carregar faixas IPv4.</div>
            </div>

            <div class="col-12 ip-crawler-range-mode" data-range-mode="amount">
              <label class="form-label" for="ipCrawlerStartIp">IP inicial</label>
              <input class="form-control" id="ipCrawlerStartIp" name="start_ip" type="text" inputmode="numeric" autocomplete="off" placeholder="192.168.0.10">
            </div>

            <div class="col-12 col-sm-6 ip-crawler-range-mode" data-range-mode="amount">
              <label class="form-label" for="ipCrawlerQuantity">Quantidade de IPs</label>
              <input class="form-control" id="ipCrawlerQuantity" name="ip_quantity" type="number" min="1" max="1000" step="1" value="1">
            </div>

            <div class="col-12 col-sm-6 ip-crawler-range-mode" data-range-mode="amount manual">
              <label class="form-label" for="ipCrawlerEndIp">IP final</label>
              <input class="form-control" id="ipCrawlerEndIp" name="end_ip" type="text" inputmode="numeric" autocomplete="off" placeholder="192.168.0.59">
            </div>

            <div class="col-12 ip-crawler-range-mode d-none" data-range-mode="cidr">
              <label class="form-label" for="ipCrawlerCidr">CIDR</label>
              <input class="form-control" id="ipCrawlerCidr" name="cidr" type="text" inputmode="numeric" autocomplete="off" placeholder="192.168.0.0/24">
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
              <button class="btn btn-outline-primary" type="button" id="ipCrawlerGenerateRangeButton"><i class="bi bi-magic me-1"></i>Gerar faixa</button>
              <span class="badge text-bg-secondary ip-web-crawler-range-preview" id="ipCrawlerRangePreview">Nenhuma faixa gerada</span>
            </div>

            <div class="col-12">
              <label class="form-label" for="ipCrawlerPorts">Portas</label>
              <input class="form-control" id="ipCrawlerPorts" name="ports" type="text" autocomplete="off" value="80,443" placeholder="80,443,8080-8082">
            </div>

            <div class="col-12">
              <label class="form-label d-block">Protocolos</label>
              <div class="btn-group w-100" role="group" aria-label="Protocolos">
                <input class="btn-check" id="ipCrawlerProtocolHttp" name="protocols[]" type="checkbox" value="http" checked>
                <label class="btn btn-outline-secondary" for="ipCrawlerProtocolHttp">HTTP</label>
                <input class="btn-check" id="ipCrawlerProtocolHttps" name="protocols[]" type="checkbox" value="https" checked>
                <label class="btn btn-outline-secondary" for="ipCrawlerProtocolHttps">HTTPS</label>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label" for="ipCrawlerStartPaths">Caminhos iniciais</label>
              <textarea class="form-control" id="ipCrawlerStartPaths" name="start_paths" rows="2">/</textarea>
            </div>

            <div class="col-12">
              <label class="form-label" for="ipCrawlerUserAgentPreset">User-Agent</label>
              <select class="form-select" id="ipCrawlerUserAgentPreset">
                <option value="Manager IpWebCrawler/1.0">Manager IpWebCrawler/1.0</option>
                <option value="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36">Chrome Windows</option>
                <option value="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36 Edg/125.0.0.0">Edge Windows</option>
                <option value="Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15">Safari macOS</option>
                <option value="Mozilla/5.0 (X11; Linux x86_64; rv:126.0) Gecko/20100101 Firefox/126.0">Firefox Linux</option>
                <option value="Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Mobile Safari/537.36">Chrome Android</option>
                <option value="Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1">Safari iPhone</option>
                <option value="curl/8.7.1">curl</option>
                <option value="Wget/1.21.4">Wget</option>
                <option value="custom">Personalizado</option>
              </select>
              <input class="form-control mt-2 bg-body-tertiary" id="ipCrawlerUserAgent" name="user_agent" type="text" autocomplete="off" value="Manager IpWebCrawler/1.0" readonly>
            </div>

            <div class="col-12">
              <label class="form-label" for="ipCrawlerRobotsMode">Robots.txt</label>
              <select class="form-select" id="ipCrawlerRobotsMode" name="robots_mode">
                <option value="respect">Respeitar quando disponível</option>
                <option value="require">Exigir robots.txt disponível</option>
                <option value="ignore">Ignorar robots.txt</option>
              </select>
            </div>

            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" id="ipCrawlerSkipCachedFailures" name="skip_cached_failures" type="checkbox" value="1" checked>
                <label class="form-check-label" for="ipCrawlerSkipCachedFailures">Não repetir alvos com falha recente</label>
              </div>
            </div>

            <div class="col-12 col-sm-6">
              <label class="form-label" for="ipCrawlerTimeoutMs">Timeout</label>
              <select class="form-select" id="ipCrawlerTimeoutMs" name="timeout_ms">
                <option value="1500">1,5s</option>
                <option value="3000" selected>3s</option>
                <option value="5000">5s</option>
                <option value="10000">10s</option>
              </select>
            </div>

            <div class="col-12 col-sm-6">
              <label class="form-label" for="ipCrawlerDelayMs">Intervalo</label>
              <select class="form-select" id="ipCrawlerDelayMs" name="delay_ms">
                <option value="0">Sem intervalo</option>
                <option value="250" selected>250ms</option>
                <option value="500">500ms</option>
                <option value="1000">1s</option>
              </select>
            </div>

            <div class="col-12 col-sm-6">
              <label class="form-label" for="ipCrawlerMaxDepth">Profundidade</label>
              <select class="form-select" id="ipCrawlerMaxDepth" name="max_depth">
                <option value="0">0</option>
                <option value="1" selected>1</option>
                <option value="2">2</option>
              </select>
            </div>

            <div class="col-12 col-sm-6">
              <label class="form-label" for="ipCrawlerMaxPages">Páginas por alvo</label>
              <input class="form-control" id="ipCrawlerMaxPages" name="max_pages_per_target" type="number" min="1" max="30" step="1" value="10">
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 justify-content-between align-items-center ip-web-crawler-actions">
              <button class="btn btn-secondary" type="button" id="ipCrawlerClearButton"><i class="bi bi-eraser me-1"></i>Limpar</button>
              <div class="d-flex gap-2">
                <button class="btn btn-danger d-none" type="button" id="ipCrawlerStopButton"><i class="bi bi-stop-fill me-1"></i>Parar</button>
                <button class="btn btn-primary" type="submit" id="ipCrawlerStartButton"><i class="bi bi-play-fill me-1"></i>Iniciar</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xxl-8">
      <div class="card shadow-sm ip-web-crawler-monitor-card">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="ip-web-crawler-icon text-bg-success"><i class="bi bi-activity"></i></span>
            <h2 class="ip-web-crawler-title mb-0">Acompanhamento</h2>
          </div>

          <div class="ip-web-crawler-stats mb-3">
            <div class="ip-web-crawler-stat-card"><small>Processados</small><strong id="ipCrawlerProcessedCount">0</strong></div>
            <div class="ip-web-crawler-stat-card"><small>Com match</small><strong id="ipCrawlerFoundCount">0</strong></div>
            <div class="ip-web-crawler-stat-card"><small>Ignorados</small><strong id="ipCrawlerSkippedCount">0</strong></div>
            <div class="ip-web-crawler-stat-card"><small>Falhas</small><strong id="ipCrawlerFailedCount">0</strong></div>
            <div class="ip-web-crawler-stat-card"><small>Status</small><strong id="ipCrawlerStatusText">Aguardando</strong></div>
          </div>

          <div class="ip-web-crawler-progress mb-3">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
              <span class="fw-semibold">Progresso</span>
              <span class="text-secondary" id="ipCrawlerProgressText">0%</span>
            </div>
            <div class="progress" role="progressbar" aria-label="Progresso da pesquisa" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" id="ipCrawlerProgress">
              <div class="progress-bar" id="ipCrawlerProgressBar" style="width: 0%"></div>
            </div>
          </div>

          <div class="alert alert-secondary ip-web-crawler-message" id="ipCrawlerMessage" role="status" aria-live="polite">Configure a pesquisa e gere a faixa de IPs.</div>

          <div class="border rounded overflow-hidden ip-web-crawler-results d-flex flex-column">
            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary">
              <i class="bi bi-list-check text-primary"></i>
              <span class="fw-semibold">Resultados</span>
              <button class="btn btn-sm btn-outline-secondary ms-auto ip-web-crawler-results-bottom-button" type="button" id="ipCrawlerResultsBottomButton" title="Ir para o último resultado"><i class="bi bi-arrow-down" aria-hidden="true"></i><span class="visually-hidden">Ir para o último resultado</span></button>
            </div>
            <div class="list-group list-group-flush ip-web-crawler-results-body" id="ipCrawlerResultsBody" role="log" aria-live="polite" aria-relevant="additions">
              <div class="list-group-item text-secondary ip-web-crawler-results-empty">
                <div class="d-flex align-items-center gap-2">
                  <i class="bi bi-hourglass-split"></i>
                  <span>Aguardando início.</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="<?= url('assets/js/tools/ip_web_crawler.js?v=4'); ?>"></script>

<?php
$ipRangeCountryOptions = $ipRangeCountryOptions ?? [];
$userAgentOptions = $userAgentOptions ?? [];
?>

<!-- CSS -->
<link rel="stylesheet" href="<?= url('assets/css/tools/ip_range_webcrawler.css?v=1'); ?>">
<!-- /CSS -->

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Webcrawler de IPs</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<section class="ip-range-webcrawler-tool" id="ipRangeWebCrawlerApp" data-stream-url="<?= url('tool/streamIpRangeWebCrawler'); ?>" data-import-country-url="<?= url('tool/importIpRangeCountryRanges'); ?>">
  <div class="ip-range-webcrawler-busy-overlay" aria-hidden="true"></div>
  <div class="row g-4">
    <div class="col-12 col-xxl-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="ip-range-webcrawler-icon text-bg-primary"><i class="bi bi-hdd-network"></i></span>
            <div class="flex-grow-1">
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                  <h1 class="ip-range-webcrawler-title mb-0">Webcrawler de IPs</h1>
                  <small class="text-secondary">Pesquisa HTTP/HTTPS por faixas IPv4.</small>
                </div>
                <a class="btn btn-outline-primary btn-sm" href="<?= url('tool/ipRangeWebCrawlerReport'); ?>">
                  <i class="bi bi-file-earmark-text me-1"></i>Relatório
                </a>
              </div>
            </div>
          </div>

          <form id="ipRangeWebCrawlerForm" class="row g-3" novalidate>
            <div class="col-12">
              <label class="form-label" for="ipCrawlerRangeMode">Origem dos IPs</label>
              <select class="form-select" id="ipCrawlerRangeMode" name="range_mode">
                <option value="amount">Quantidade a partir de IP</option>
                <option value="manual">Texto livre</option>
                <option value="country">País importado</option>
              </select>
            </div>

            <div class="col-12 ip-range-webcrawler-mode-panel" data-mode-panel="amount">
              <div class="row g-3">
                <div class="col-12 col-md-7">
                  <label class="form-label" for="ipCrawlerStartIp">IP inicial</label>
                  <input class="form-control" id="ipCrawlerStartIp" name="start_ip" type="text" inputmode="decimal" autocomplete="off" placeholder="192.0.2.1">
                </div>
                <div class="col-12 col-md-5">
                  <label class="form-label" for="ipCrawlerIpQuantity">Quantidade</label>
                  <input class="form-control" id="ipCrawlerIpQuantity" name="ip_quantity" type="number" min="1" step="1" value="100">
                </div>
              </div>
            </div>

            <div class="col-12 ip-range-webcrawler-mode-panel d-none" data-mode-panel="manual">
              <label class="form-label" for="ipCrawlerManualRanges">IPs, faixas ou CIDR</label>
              <textarea class="form-control" id="ipCrawlerManualRanges" name="manual_ranges" rows="5" placeholder="192.0.2.10&#10;192.0.2.20-192.0.2.30&#10;198.51.100.0/30"></textarea>
            </div>

            <div class="col-12 ip-range-webcrawler-mode-panel d-none" data-mode-panel="country">
              <label class="form-label" for="ipCrawlerCountryCode">País</label>
              <div class="input-group">
                <select class="form-select" id="ipCrawlerCountryCode" name="country_code">
                  <option value="">Selecione o país</option>
                  <?php foreach ($ipRangeCountryOptions as $countryOption): ?>
                    <option value="<?= htmlspecialchars((string) $countryOption['country_code'], ENT_QUOTES, 'UTF-8'); ?>" data-ip-total="<?= (int) ($countryOption['ip_total'] ?? 0); ?>" data-processed-total="<?= (int) ($countryOption['processed_total'] ?? 0); ?>" data-remaining-total="<?= (int) ($countryOption['remaining_total'] ?? 0); ?>" data-has-ranges="<?= !empty($countryOption['has_ranges']) ? '1' : '0'; ?>"><?= htmlspecialchars((string) $countryOption['country_name'], ENT_QUOTES, 'UTF-8'); ?> (<?= htmlspecialchars((string) $countryOption['country_code'], ENT_QUOTES, 'UTF-8'); ?>) - <?= number_format((float) ($countryOption['remaining_total'] ?? $countryOption['ip_total'] ?? 0), 0, ',', '.'); ?> IPs disponíveis<?= empty($countryOption['has_ranges']) ? ' - sem faixas importadas' : ''; ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-primary" id="ipCrawlerImportCountryButton" type="button">
                  <i class="bi bi-cloud-arrow-down me-1"></i>Importar faixas
                </button>
              </div>
              <div class="form-text" id="ipCrawlerCountryHint">
                <?php if (empty($ipRangeCountryOptions)): ?>
                  Importe faixas na tabela <code>webcrawler_ip_ranges</code> para habilitar países.
                <?php else: ?>
                  Países sem faixas importadas ficam listados com <code>0 IPs</code> apenas como referência de cadastro.
                <?php endif; ?>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label" for="ipCrawlerMaxIps">Limite de IPs da execução</label>
              <input class="form-control" id="ipCrawlerMaxIps" name="max_ips" type="number" min="0" step="1" value="" placeholder="Deixe em branco para calcular o total">
              <div class="form-text" id="ipCrawlerMaxIpsHint">Cada IP gera 2 alvos padrão: porta 80 e porta 443. Se ficar em branco, o crawler usa o total calculado pelas faixas informadas.</div>
            </div>

            <div class="col-12">
              <label class="form-label" for="ipCrawlerKeywords">Palavras-chave</label>
              <textarea class="form-control" id="ipCrawlerKeywords" name="keywords" rows="3" placeholder="admin, login, contato"></textarea>
            </div>

            <div class="col-12">
              <label class="form-label" for="ipCrawlerUserAgent">User Agent</label>
              <select class="form-select" id="ipCrawlerUserAgent" name="user_agent">
                <?php foreach ($userAgentOptions as $userAgentOption): ?>
                  <option value="<?= htmlspecialchars((string) $userAgentOption['value'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $userAgentOption['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label d-flex justify-content-between align-items-center" for="ipCrawlerTimeoutMs">
                <span>Timeout por alvo</span>
                <strong id="ipCrawlerTimeoutLabel">3000 ms</strong>
              </label>
              <input class="form-range" id="ipCrawlerTimeoutMs" name="timeout_ms" type="range" min="500" max="15000" step="500" value="3000">
            </div>

            <div class="col-12">
              <label class="form-label d-flex justify-content-between align-items-center" for="ipCrawlerConcurrency">
                <span>Consultas simultâneas</span>
                <strong id="ipCrawlerConcurrencyLabel">10</strong>
              </label>
              <input class="form-range" id="ipCrawlerConcurrency" name="concurrency" type="range" min="1" max="200" step="1" value="10">
            </div>

            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" id="ipCrawlerSkipExisting" name="skip_existing" type="checkbox" value="1" checked>
                <label class="form-check-label" for="ipCrawlerSkipExisting">Ignorar URLs já salvas</label>
              </div>
            </div>

            <div class="col-12">
              <div class="alert alert-info d-none mb-0" id="ipCrawlerMessage" role="status"></div>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 justify-content-between align-items-center ip-range-webcrawler-actions">
              <button class="btn btn-secondary" type="button" id="ipCrawlerClearButton">
                <i class="bi bi-eraser me-1"></i>Limpar
              </button>
              <div class="d-flex gap-2">
                <button class="btn btn-danger d-none" type="button" id="ipCrawlerStopButton">
                  <i class="bi bi-stop-fill me-1"></i>Parar
                </button>
                <button class="btn btn-primary" type="submit" id="ipCrawlerStartButton">
                  <i class="bi bi-play-fill me-1"></i>Iniciar
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xxl-8">
      <div class="card shadow-sm ip-range-webcrawler-monitor-card">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="ip-range-webcrawler-icon text-bg-success"><i class="bi bi-activity"></i></span>
            <h2 class="ip-range-webcrawler-title mb-0">Acompanhamento em tempo real</h2>
          </div>

          <div class="ip-range-webcrawler-progress mb-3">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <small class="text-secondary">Progresso</small>
              <strong id="ipCrawlerProgressText">0%</strong>
            </div>
            <div class="progress" role="progressbar" aria-label="Progresso da pesquisa" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
              <div class="progress-bar" id="ipCrawlerProgressBar" style="width: 0%"></div>
            </div>
          </div>

          <div class="ip-range-webcrawler-stats mb-3">
            <div class="ip-range-webcrawler-stat"><small>Alvos</small><strong id="ipCrawlerTargetCount">0</strong></div>
            <div class="ip-range-webcrawler-stat"><small>Processados</small><strong id="ipCrawlerProcessedCount">0</strong></div>
            <div class="ip-range-webcrawler-stat"><small>HTTP 200</small><strong id="ipCrawlerFoundCount">0</strong></div>
            <div class="ip-range-webcrawler-stat"><small>Matches</small><strong id="ipCrawlerMatchedCount">0</strong></div>
            <div class="ip-range-webcrawler-stat"><small>Falhas</small><strong id="ipCrawlerFailedCount">0</strong></div>
            <div class="ip-range-webcrawler-stat"><small>Status</small><strong id="ipCrawlerStatusText">Aguardando início</strong></div>
          </div>

          <div class="border rounded overflow-hidden ip-range-webcrawler-results d-flex flex-column">
            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-body-tertiary">
              <i class="bi bi-list-check text-primary"></i>
              <span class="fw-semibold">Resultados salvos</span>
              <span class="badge text-bg-secondary ms-auto" id="ipCrawlerSkippedBadge">0 ignorados</span>
              <div class="btn-group btn-group-sm" role="group" aria-label="Navegação dos resultados">
                <button class="btn btn-outline-secondary" type="button" id="ipCrawlerResultsTopButton" title="Ir para o início">
                  <i class="bi bi-arrow-up" aria-hidden="true"></i>
                  <span class="visually-hidden">Ir para o início</span>
                </button>
                <button class="btn btn-outline-secondary" type="button" id="ipCrawlerResultsBottomButton" title="Ir para o último resultado">
                  <i class="bi bi-arrow-down" aria-hidden="true"></i>
                  <span class="visually-hidden">Ir para o último resultado</span>
                </button>
              </div>
            </div>
            <div class="list-group list-group-flush ip-range-webcrawler-results-info d-none" id="ipCrawlerResultsInfo" aria-live="polite"></div>
            <div class="list-group list-group-flush ip-range-webcrawler-results-body" id="ipCrawlerResultsBody" role="log" aria-live="polite" aria-relevant="additions">
              <div class="list-group-item text-secondary ip-range-webcrawler-results-empty">
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

<script src="<?= url('assets/js/tools/ip_range_webcrawler.js?v=3'); ?>"></script>

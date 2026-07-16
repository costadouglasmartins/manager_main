<?php
$filters = $filters ?? [
	'execution_id' => '',
	'country_code' => '',
	'range_mode' => '',
	'protocol' => '',
	'port' => '',
	'http_status' => '',
	'scan_status' => '',
	'keyword_matched' => '',
	'term' => '',
	'date_from' => '',
	'date_to' => '',
];
$results = $results ?? [];
$summary = $summary ?? [];
$page = $page ?? 1;
$totalPages = $totalPages ?? 1;
$total = $total ?? (is_array($results) ? count($results) : 0);
$sort = $sort ?? ['sort' => 'checked_at', 'direction' => 'desc'];
$ipRangeCountryOptions = $ipRangeCountryOptions ?? [];

$queryFilters = http_build_query([
	'execution_id' => $filters['execution_id'],
	'country_code' => $filters['country_code'],
	'range_mode' => $filters['range_mode'],
	'protocol' => $filters['protocol'],
	'port' => $filters['port'],
	'http_status' => $filters['http_status'],
	'scan_status' => $filters['scan_status'],
	'keyword_matched' => $filters['keyword_matched'],
	'term' => $filters['term'],
	'date_from' => $filters['date_from'],
	'date_to' => $filters['date_to'],
]);
$paginationQuery = sortQueryString($queryFilters, $sort);
?>

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('tool/ipRangeWebCrawler'); ?>">Webcrawler de IPs</a></li>
    <li class="breadcrumb-item active" aria-current="page">Relatório</li>
  </ol>
</nav>

<form action="<?= url('tool/ipRangeWebCrawlerReport'); ?>" class="index-search-card mb-4" method="get">
  <div class="row g-3 align-items-center">
    <div class="col-12 col-lg-9">
      <div class="row g-3">
        <div class="col-12 col-md-6 col-lg-3">
          <input class="form-control" id="execution_id" name="execution_id" type="text" placeholder="Execução" value="<?= htmlspecialchars($filters['execution_id'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <select class="form-select" id="country_code" name="country_code">
            <option value="">Todos os países</option>
            <?php foreach ($ipRangeCountryOptions as $countryOption): ?>
              <option value="<?= htmlspecialchars((string) $countryOption['country_code'], ENT_QUOTES, 'UTF-8'); ?>" <?= strtoupper((string) $filters['country_code']) === strtoupper((string) $countryOption['country_code']) ? 'selected' : ''; ?>>
                <?= htmlspecialchars((string) $countryOption['country_name'], ENT_QUOTES, 'UTF-8'); ?> (<?= htmlspecialchars((string) $countryOption['country_code'], ENT_QUOTES, 'UTF-8'); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <select class="form-select" id="range_mode" name="range_mode">
            <option value="">Qualquer origem</option>
            <option value="amount" <?= $filters['range_mode'] === 'amount' ? 'selected' : ''; ?>>Quantidade</option>
            <option value="manual" <?= $filters['range_mode'] === 'manual' ? 'selected' : ''; ?>>Texto livre</option>
            <option value="country" <?= $filters['range_mode'] === 'country' ? 'selected' : ''; ?>>País importado</option>
          </select>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <select class="form-select" id="protocol" name="protocol">
            <option value="">HTTP e HTTPS</option>
            <option value="http" <?= $filters['protocol'] === 'http' ? 'selected' : ''; ?>>HTTP</option>
            <option value="https" <?= $filters['protocol'] === 'https' ? 'selected' : ''; ?>>HTTPS</option>
          </select>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <input class="form-control" id="port" name="port" type="number" min="1" max="65535" placeholder="Porta" value="<?= htmlspecialchars($filters['port'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <input class="form-control" id="http_status" name="http_status" type="number" min="0" max="599" placeholder="Status HTTP" value="<?= htmlspecialchars($filters['http_status'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <select class="form-select" id="scan_status" name="scan_status">
            <option value="">Todos os status</option>
            <option value="found" <?= $filters['scan_status'] === 'found' ? 'selected' : ''; ?>>Encontrados</option>
            <option value="not_found" <?= $filters['scan_status'] === 'not_found' ? 'selected' : ''; ?>>Não encontrados</option>
            <option value="timeout" <?= $filters['scan_status'] === 'timeout' ? 'selected' : ''; ?>>Timeout</option>
            <option value="error" <?= $filters['scan_status'] === 'error' ? 'selected' : ''; ?>>Erro</option>
          </select>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <select class="form-select" id="keyword_matched" name="keyword_matched">
            <option value="">Match e sem match</option>
            <option value="1" <?= $filters['keyword_matched'] === '1' ? 'selected' : ''; ?>>Somente match</option>
            <option value="0" <?= $filters['keyword_matched'] === '0' ? 'selected' : ''; ?>>Sem match</option>
          </select>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <input class="form-control" id="date_from" name="date_from" type="date" value="<?= htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <input class="form-control" id="date_to" name="date_to" type="date" value="<?= htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-12">
          <input class="form-control" id="term" name="term" type="text" placeholder="Pesquisar em IP, URL, título, snippet, cabeçalhos ou user agent" value="<?= htmlspecialchars($filters['term'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="index-search-actions">
        <button type="submit" class="btn btn-primary btn-sm index-search-action" aria-label="Filtrar" title="Filtrar"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Filtrar</span></button>
        <a href="<?= url('tool/ipRangeWebCrawlerReport'); ?>" class="btn btn-outline-secondary btn-sm index-search-action" aria-label="Limpar filtros" title="Limpar filtros"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i><span class="visually-hidden">Limpar filtros</span></a>
        <a href="<?= url('tool/ipRangeWebCrawler'); ?>" class="btn btn-outline-primary btn-sm index-search-action" aria-label="Voltar ao crawler" title="Voltar ao crawler"><i class="bi bi-arrow-left" aria-hidden="true"></i><span class="visually-hidden">Voltar ao crawler</span></a>
      </div>
    </div>
  </div>
</form>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-2">
    <div class="border rounded p-3 h-100">
      <small class="text-secondary d-block">Registros</small>
      <strong class="fs-4"><?= (int) ($summary['total'] ?? $total); ?></strong>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="border rounded p-3 h-100">
      <small class="text-secondary d-block">Execuções</small>
      <strong class="fs-4"><?= (int) ($summary['execution_total'] ?? 0); ?></strong>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="border rounded p-3 h-100">
      <small class="text-secondary d-block">IPs únicos</small>
      <strong class="fs-4"><?= (int) ($summary['ip_total'] ?? 0); ?></strong>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="border rounded p-3 h-100">
      <small class="text-secondary d-block">Matches</small>
      <strong class="fs-4"><?= (int) ($summary['matched_total'] ?? 0); ?></strong>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="border rounded p-3 h-100">
      <small class="text-secondary d-block">Encontrados</small>
      <strong class="fs-4"><?= (int) ($summary['found_total'] ?? 0); ?></strong>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="border rounded p-3 h-100">
      <small class="text-secondary d-block">Não encontrados</small>
      <strong class="fs-4"><?= (int) ($summary['not_found_total'] ?? 0); ?></strong>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="border rounded p-3 h-100">
      <small class="text-secondary d-block">Timeout / Erro</small>
      <strong class="fs-4"><?= (int) ($summary['timeout_total'] ?? 0) + (int) ($summary['error_total'] ?? 0); ?></strong>
    </div>
  </div>
</div>

<div class="table-responsive">
  <table class="table table-striped table-hover align-middle">
    <thead>
      <tr>
        <?= sortableTh('tool/ipRangeWebCrawlerReport', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('tool/ipRangeWebCrawlerReport', 'execution_id', 'Execução', $sort, $queryFilters) ?>
        <?= sortableTh('tool/ipRangeWebCrawlerReport', 'country_code', 'País', $sort, $queryFilters) ?>
        <?= sortableTh('tool/ipRangeWebCrawlerReport', 'ip_address', 'Endereço', $sort, $queryFilters) ?>
        <?= sortableTh('tool/ipRangeWebCrawlerReport', 'protocol', 'Protocolo', $sort, $queryFilters) ?>
        <?= sortableTh('tool/ipRangeWebCrawlerReport', 'http_status', 'HTTP', $sort, $queryFilters) ?>
        <?= sortableTh('tool/ipRangeWebCrawlerReport', 'scan_status', 'Status', $sort, $queryFilters) ?>
        <?= sortableTh('tool/ipRangeWebCrawlerReport', 'keyword_matched', 'Match', $sort, $queryFilters) ?>
        <?= sortableTh('tool/ipRangeWebCrawlerReport', 'response_time_ms', 'Tempo', $sort, $queryFilters) ?>
        <?= sortableTh('tool/ipRangeWebCrawlerReport', 'checked_at', 'Verificado em', $sort, $queryFilters) ?>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($results)): ?>
        <?php foreach ($results as $row): ?>
          <tr>
            <td style="width:1%;"><?= (int) $row['id']; ?></td>
            <td style="width:1%;" class="text-nowrap">
              #<?= (int) $row['execution_id']; ?>
              <?php if (!empty($row['range_mode'])): ?>
                <div><small class="text-secondary"><?= htmlspecialchars((string) $row['range_mode'], ENT_QUOTES, 'UTF-8'); ?></small></div>
              <?php endif; ?>
            </td>
            <td style="width:1%;" class="text-nowrap">
              <?= htmlspecialchars((string) ($row['country_code'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>
            </td>
            <td>
              <div class="fw-semibold">
                <a href="<?= htmlspecialchars((string) $row['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                  <?= htmlspecialchars((string) $row['ip_address'], ENT_QUOTES, 'UTF-8'); ?>:<?= (int) $row['port']; ?>
                </a>
              </div>
              <small class="text-secondary text-break d-block"><?= htmlspecialchars((string) $row['url'], ENT_QUOTES, 'UTF-8'); ?></small>
              <?php if (!empty($row['redirect_url'])): ?>
                <small class="text-warning d-block text-break">Redirecionou para: <?= htmlspecialchars((string) $row['redirect_url'], ENT_QUOTES, 'UTF-8'); ?></small>
              <?php endif; ?>
            </td>
            <td style="width:1%;" class="text-nowrap">
              <span class="badge text-bg-secondary"><?= htmlspecialchars(strtoupper((string) $row['protocol']), ENT_QUOTES, 'UTF-8'); ?></span>
              <div><small class="text-secondary">Porta <?= (int) $row['port']; ?></small></div>
            </td>
            <td style="width:1%;" class="text-nowrap">
              <span class="badge <?= ((int) $row['http_status'] === 200 ? 'text-bg-success' : 'text-bg-secondary'); ?>"><?= (int) $row['http_status']; ?></span>
            </td>
            <td style="width:1%;" class="text-nowrap">
              <?php
              $scanStatus = (string) ($row['scan_status'] ?? '');
              $scanStatusLabel = [
                'found' => 'Encontrado',
                'not_found' => 'Não encontrado',
                'timeout' => 'Timeout',
                'error' => 'Erro',
                'skipped' => 'Ignorado',
              ][$scanStatus] ?? ($scanStatus !== '' ? ucfirst(str_replace('_', ' ', $scanStatus)) : '-');
              $scanStatusClass = [
                'found' => 'text-bg-success',
                'not_found' => 'text-bg-secondary',
                'timeout' => 'text-bg-warning',
                'error' => 'text-bg-danger',
                'skipped' => 'text-bg-light text-dark',
              ][$scanStatus] ?? 'text-bg-light text-dark';
              ?>
              <span class="badge <?= $scanStatusClass; ?>"><?= htmlspecialchars($scanStatusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
              <?php if (!empty($row['curl_errno'])): ?>
                <div><small class="text-secondary">cURL <?= (int) $row['curl_errno']; ?></small></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($row['keyword_matched'])): ?>
                <span class="badge text-bg-success mb-1">Match</span>
                <div class="d-flex flex-wrap gap-1">
                  <?php foreach ((array) ($row['matched_keywords'] ?? []) as $keyword): ?>
                    <span class="badge text-bg-primary"><?= htmlspecialchars((string) $keyword, ENT_QUOTES, 'UTF-8'); ?></span>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <span class="badge text-bg-light text-dark">Sem match</span>
              <?php endif; ?>
              <div class="mt-2">
                <?php if (!empty($row['title'])): ?>
                  <div class="fw-semibold text-break"><?= htmlspecialchars((string) $row['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>
                <?php if (!empty($row['snippet'])): ?>
                  <small class="text-secondary text-break d-block"><?= htmlspecialchars((string) $row['snippet'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
                <?php if (!empty($row['content_type'])): ?>
                  <small class="text-secondary d-block"><?= htmlspecialchars((string) $row['content_type'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
              </div>
            </td>
            <td style="width:1%;" class="text-nowrap">
              <?= (int) $row['response_time_ms']; ?> ms
            </td>
            <td style="width:1%;" class="text-nowrap">
              <?= !empty($row['checked_at']) ? htmlspecialchars(date('d/m/Y H:i:s', strtotime((string) $row['checked_at'])), ENT_QUOTES, 'UTF-8') : '-'; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="10" class="text-center">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="text-muted"><?= (int) $total; ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('tool/ipRangeWebCrawlerReport', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
<?php endif; ?>

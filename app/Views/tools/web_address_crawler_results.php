<?php
$filters = $filters ?? [
	'search_id' => '',
	'state_acronym' => '',
	'city' => '',
	'import_status' => '',
	'has_phone' => '',
	'has_email' => '',
	'has_document' => '',
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
$stateOptions = $stateOptions ?? [];

$queryFilters = http_build_query($filters);
$paginationQuery = sortQueryString($queryFilters, $sort);

$statusLabels = [
	'pending' => 'Pendente',
	'imported' => 'Importado',
	'discarded' => 'Descartado',
];
$statusClasses = [
	'pending' => 'text-bg-secondary',
	'imported' => 'text-bg-success',
	'discarded' => 'text-bg-light text-dark',
];
?>

<!-- CSS -->
<link rel="stylesheet" href="<?= url('assets/css/tools/web_address_crawler_results.css?v=1'); ?>">
<!-- /CSS -->

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('tool/webAddressCrawler'); ?>">Webcrawler de endereços</a></li>
    <li class="breadcrumb-item active" aria-current="page">Registros encontrados</li>
  </ol>
</nav>

<section id="webAddressCrawlerResultsApp" data-import-url="<?= url('tool/importProspects'); ?>" data-filters="<?= htmlspecialchars(json_encode($filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>">

<form action="<?= url('tool/webAddressCrawlerResults'); ?>" class="index-search-card mb-4" method="get">
  <div class="row g-3 align-items-center">
    <div class="col-12 col-lg-9">
      <div class="row g-3">
        <div class="col-12 col-md-6 col-lg-3">
          <input class="form-control" id="search_id" name="search_id" type="number" min="1" placeholder="Pesquisa #" value="<?= htmlspecialchars($filters['search_id'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <select class="form-select" id="state_acronym" name="state_acronym">
            <option value="">Todos os estados</option>
            <?php foreach ($stateOptions as $stateOption): ?>
              <option value="<?= htmlspecialchars((string) $stateOption['acronym'], ENT_QUOTES, 'UTF-8'); ?>" <?= strtoupper((string) $filters['state_acronym']) === strtoupper((string) $stateOption['acronym']) ? 'selected' : ''; ?>><?= htmlspecialchars((string) $stateOption['name'], ENT_QUOTES, 'UTF-8'); ?> (<?= htmlspecialchars((string) $stateOption['acronym'], ENT_QUOTES, 'UTF-8'); ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <input class="form-control" id="city" name="city" type="text" placeholder="Cidade" value="<?= htmlspecialchars($filters['city'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <select class="form-select" id="import_status" name="import_status">
            <option value="">Todos os status</option>
            <option value="pending" <?= $filters['import_status'] === 'pending' ? 'selected' : ''; ?>>Pendentes</option>
            <option value="imported" <?= $filters['import_status'] === 'imported' ? 'selected' : ''; ?>>Importados</option>
            <option value="discarded" <?= $filters['import_status'] === 'discarded' ? 'selected' : ''; ?>>Descartados</option>
          </select>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <select class="form-select" id="has_phone" name="has_phone">
            <option value="">Telefone: todos</option>
            <option value="1" <?= $filters['has_phone'] === '1' ? 'selected' : ''; ?>>Somente com telefone</option>
          </select>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <select class="form-select" id="has_email" name="has_email">
            <option value="">E-mail: todos</option>
            <option value="1" <?= $filters['has_email'] === '1' ? 'selected' : ''; ?>>Somente com e-mail</option>
          </select>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <select class="form-select" id="has_document" name="has_document">
            <option value="">CNPJ: todos</option>
            <option value="1" <?= $filters['has_document'] === '1' ? 'selected' : ''; ?>>Somente com CNPJ</option>
          </select>
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <input class="form-control" id="date_from" name="date_from" type="date" value="<?= htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-12 col-md-6 col-lg-3">
          <input class="form-control" id="date_to" name="date_to" type="date" value="<?= htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-12">
          <input class="form-control" id="term" name="term" type="text" placeholder="Pesquisar em nome, URL, e-mail, telefone, endereço ou snippet" value="<?= htmlspecialchars($filters['term'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button type="submit" class="btn btn-primary w-100">Filtrar</button>
        <a href="<?= url('tool/webAddressCrawlerResults'); ?>" class="btn btn-secondary w-100">Limpar</a>
        <a href="<?= url('tool/webAddressCrawler'); ?>" class="btn btn-outline-primary w-100">Voltar ao crawler</a>
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
      <small class="text-secondary d-block">Pendentes</small>
      <strong class="fs-4"><?= (int) ($summary['pending_total'] ?? 0); ?></strong>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="border rounded p-3 h-100">
      <small class="text-secondary d-block">Importados</small>
      <strong class="fs-4"><?= (int) ($summary['imported_total'] ?? 0); ?></strong>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="border rounded p-3 h-100">
      <small class="text-secondary d-block">Com CNPJ</small>
      <strong class="fs-4"><?= (int) ($summary['document_total'] ?? 0); ?></strong>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="border rounded p-3 h-100">
      <small class="text-secondary d-block">Com telefone</small>
      <strong class="fs-4"><?= (int) ($summary['phone_total'] ?? 0); ?></strong>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="border rounded p-3 h-100">
      <small class="text-secondary d-block">Com e-mail</small>
      <strong class="fs-4"><?= (int) ($summary['email_total'] ?? 0); ?></strong>
    </div>
  </div>
</div>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <button class="btn btn-success" type="button" id="webCrawlerImportButton">
    <i class="bi bi-people me-1"></i>Importar registros filtrados
  </button>
  <span class="text-secondary small">Importa para o cadastro de pessoas os registros do filtro atual que possuem CNPJ e ainda não foram importados.</span>
  <div class="alert alert-info d-none mb-0 ms-auto" id="webCrawlerImportMessage" role="status"></div>
</div>

<div class="table-responsive">
  <table class="table table-striped table-hover align-middle">
    <thead>
      <tr>
        <?= sortableTh('tool/webAddressCrawlerResults', 'id', '#', $sort, $queryFilters) ?>
        <?= sortableTh('tool/webAddressCrawlerResults', 'business_name', 'Empresa', $sort, $queryFilters) ?>
        <th scope="col">Contato</th>
        <th scope="col">Endereço</th>
        <th scope="col">Match</th>
        <?= sortableTh('tool/webAddressCrawlerResults', 'import_status', 'Status', $sort, $queryFilters) ?>
        <?= sortableTh('tool/webAddressCrawlerResults', 'checked_at', 'Encontrado em', $sort, $queryFilters) ?>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($results)): ?>
        <?php foreach ($results as $row): ?>
          <?php
          $rowStatus = (string) ($row['import_status'] ?? 'pending');
          $location = array_filter([
            trim((string) ($row['street'] ?? '') . ' ' . (string) ($row['number'] ?? '')),
            (string) ($row['neighborhood'] ?? ''),
            trim((string) ($row['city'] ?? '') . (!empty($row['state_acronym']) ? ' / ' . (string) $row['state_acronym'] : '')),
            (string) ($row['zip_code'] ?? ''),
          ], static function (string $value): bool {
            return trim($value) !== '';
          });
          ?>
          <tr>
            <td style="width:1%;"><?= (int) $row['id']; ?></td>
            <td>
              <div class="fw-semibold text-break"><?= htmlspecialchars((string) ($row['business_name'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
              <a class="small text-break d-block" href="<?= htmlspecialchars((string) $row['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) $row['url'], ENT_QUOTES, 'UTF-8'); ?></a>
              <?php if (!empty($row['document'])): ?>
                <small class="text-secondary d-block"><i class="bi bi-card-text me-1"></i><?= htmlspecialchars(maskCpfCnpj((string) $row['document']), ENT_QUOTES, 'UTF-8'); ?></small>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($row['phone'])): ?>
                <div class="text-nowrap"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars(maskPhoneBr((string) $row['phone']), ENT_QUOTES, 'UTF-8'); ?></div>
              <?php endif; ?>
              <?php if (!empty($row['email'])): ?>
                <div class="text-break"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8'); ?></div>
              <?php endif; ?>
              <?php if (empty($row['phone']) && empty($row['email'])): ?>
                <span class="text-secondary">-</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($location)): ?>
                <small class="text-break d-block"><?= htmlspecialchars(implode(' · ', $location), ENT_QUOTES, 'UTF-8'); ?></small>
              <?php else: ?>
                <span class="text-secondary">-</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($row['matched_keywords'])): ?>
                <div class="d-flex flex-wrap gap-1">
                  <?php foreach ((array) $row['matched_keywords'] as $keyword): ?>
                    <span class="badge text-bg-primary"><?= htmlspecialchars((string) $keyword, ENT_QUOTES, 'UTF-8'); ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($row['snippet'])): ?>
                <small class="text-secondary text-break d-block mt-1"><?= htmlspecialchars((string) $row['snippet'], ENT_QUOTES, 'UTF-8'); ?></small>
              <?php endif; ?>
            </td>
            <td style="width:1%;" class="text-nowrap">
              <span class="badge <?= $statusClasses[$rowStatus] ?? 'text-bg-secondary'; ?>"><?= htmlspecialchars($statusLabels[$rowStatus] ?? ucfirst($rowStatus), ENT_QUOTES, 'UTF-8'); ?></span>
            </td>
            <td style="width:1%;" class="text-nowrap">
              <?= !empty($row['checked_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $row['checked_at'])), ENT_QUOTES, 'UTF-8') : '-'; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" class="text-center">Nenhum registro encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="text-muted"><?= (int) $total; ?> registro(s) encontrado(s)</p>

<?php if ($totalPages > 1): ?>
  <?= paginationLinks('tool/webAddressCrawlerResults', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima') ?>
<?php endif; ?>

</section>

<script src="<?= url('assets/js/tools/web_address_crawler_results.js?v=1'); ?>"></script>

<?php
$filters = $filters ?? [
	'search_id' => '',
	'search_type' => '',
	'country_code' => '',
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
$serpApiSearchTypeOptions = $serpApiSearchTypeOptions ?? [];
$serpApiCountryOptions = $serpApiCountryOptions ?? [];

$queryFilters = http_build_query($filters);
$paginationQuery = sortQueryString($queryFilters, $sort);
$returnParams = $filters;
if (!empty($sort['sort']))
	{
	$returnParams['sort'] = (string) $sort['sort'];
	}
if (!empty($sort['direction']))
	{
	$returnParams['direction'] = (string) $sort['direction'];
	}
if ((int) $page > 1)
	{
	$returnParams['page'] = (int) $page;
	}
$returnQuery = http_build_query($returnParams);
$returnPath = 'tool/serpApiWebCrawlerResults' . ($returnQuery !== '' ? '?' . $returnQuery : '');
$deleteAction = url('tool/deleteSerpApiWebCrawlerResults');

$searchTypeLabels = [
	'web' => 'Web',
	'news' => 'Notícias',
	'images' => 'Imagens',
];
?>

<!-- CSS -->
<link rel="stylesheet" href="<?= url('assets/css/tools/serpapi_webcrawler_results.css?v=2'); ?>">
<!-- /CSS -->

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('tool/serpApiWebCrawler'); ?>">SERPAPI Webcrawler</a></li>
    <li class="breadcrumb-item active" aria-current="page">Resultados SERPAPI</li>
  </ol>
</nav>

<section id="serpApiWebCrawlerResultsApp" class="serpapi-webcrawler-results-page">
  <form action="<?= url('tool/serpApiWebCrawlerResults'); ?>" class="index-search-card mb-4" method="get">
    <div class="row g-3 align-items-center">
      <div class="col-12 col-lg-9">
        <div class="row g-3">
          <div class="col-12 col-md-6 col-lg-3">
            <input class="form-control" id="search_id" name="search_id" type="number" min="1" placeholder="Consulta #" value="<?= htmlspecialchars($filters['search_id'], ENT_QUOTES, 'UTF-8'); ?>">
          </div>

          <div class="col-12 col-md-6 col-lg-3">
            <select class="form-select" id="search_type" name="search_type">
              <option value="">Todos os tipos</option>
              <?php foreach ($serpApiSearchTypeOptions as $searchTypeOption): ?>
                <option value="<?= htmlspecialchars((string) $searchTypeOption['value'], ENT_QUOTES, 'UTF-8'); ?>" <?= (string) $filters['search_type'] === (string) $searchTypeOption['value'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) $searchTypeOption['label'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-md-6 col-lg-3">
            <select class="form-select" id="country_code" name="country_code">
              <option value="">Todos os países</option>
              <?php foreach ($serpApiCountryOptions as $countryOption): ?>
                <?php if ((string) $countryOption['value'] === ''): ?>
                  <?php continue; ?>
                <?php endif; ?>
                <option value="<?= htmlspecialchars((string) $countryOption['value'], ENT_QUOTES, 'UTF-8'); ?>" <?= (string) $filters['country_code'] === (string) $countryOption['value'] ? 'selected' : ''; ?>><?= htmlspecialchars((string) $countryOption['label'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-md-6 col-lg-3">
            <input class="form-control" id="date_from" name="date_from" type="date" value="<?= htmlspecialchars($filters['date_from'], ENT_QUOTES, 'UTF-8'); ?>">
          </div>

          <div class="col-12 col-md-6 col-lg-3">
            <input class="form-control" id="date_to" name="date_to" type="date" value="<?= htmlspecialchars($filters['date_to'], ENT_QUOTES, 'UTF-8'); ?>">
          </div>

          <div class="col-12 col-lg-9">
            <input class="form-control" id="term" name="term" type="text" placeholder="Pesquisar em consulta, título, link, fonte, localização ou snippet" value="<?= htmlspecialchars($filters['term'], ENT_QUOTES, 'UTF-8'); ?>">
          </div>
        </div>
      </div>

      <div class="col-12 col-lg-3">
        <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
          <button type="submit" class="btn btn-primary w-100">Filtrar</button>
          <a href="<?= url('tool/serpApiWebCrawlerResults'); ?>" class="btn btn-secondary w-100">Limpar</a>
          <a href="<?= url('tool/serpApiWebCrawler'); ?>" class="btn btn-outline-primary w-100">Voltar ao crawler</a>
        </div>
      </div>
    </div>
  </form>

  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
      <div class="serpapi-webcrawler-results-summary">
        <small class="text-secondary d-block">Resultados</small>
        <strong class="fs-4"><?= (int) ($summary['total'] ?? $total); ?></strong>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="serpapi-webcrawler-results-summary">
        <small class="text-secondary d-block">Web</small>
        <strong class="fs-4"><?= (int) ($summary['web_total'] ?? 0); ?></strong>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="serpapi-webcrawler-results-summary">
        <small class="text-secondary d-block">Notícias</small>
        <strong class="fs-4"><?= (int) ($summary['news_total'] ?? 0); ?></strong>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="serpapi-webcrawler-results-summary">
        <small class="text-secondary d-block">Imagens</small>
        <strong class="fs-4"><?= (int) ($summary['images_total'] ?? 0); ?></strong>
      </div>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-hover align-middle">
      <thead>
        <tr>
          <th scope="col" style="width:1%;" class="text-center">
            <input class="form-check-input" id="serpApiSelectAllResults" type="checkbox" aria-label="Selecionar todos os resultados desta página">
          </th>
          <?= sortableTh('tool/serpApiWebCrawlerResults', 'id', '#', $sort, $queryFilters); ?>
          <?= sortableTh('tool/serpApiWebCrawlerResults', 'search_id', 'Consulta', $sort, $queryFilters); ?>
          <?= sortableTh('tool/serpApiWebCrawlerResults', 'search_type', 'Tipo', $sort, $queryFilters); ?>
          <?= sortableTh('tool/serpApiWebCrawlerResults', 'position', 'Posição', $sort, $queryFilters); ?>
          <?= sortableTh('tool/serpApiWebCrawlerResults', 'title', 'Resultado', $sort, $queryFilters); ?>
          <?= sortableTh('tool/serpApiWebCrawlerResults', 'source', 'Fonte', $sort, $queryFilters); ?>
          <?= sortableTh('tool/serpApiWebCrawlerResults', 'checked_at', 'Consultado em', $sort, $queryFilters); ?>
          <th scope="col" style="width:1%;" class="text-nowrap text-end">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($results)): ?>
          <?php foreach ($results as $row): ?>
            <?php
            $searchType = (string) ($row['search_type'] ?? 'web');
            $location = array_filter([
            	(string) ($row['location'] ?? ''),
            	!empty($row['country_code']) ? strtoupper((string) $row['country_code']) : '',
            	(string) ($row['language_code'] ?? ''),
            ], static function (string $value): bool {
            	return trim($value) !== '';
            });
            $sourceDisplay = trim((string) ($row['source'] ?? ''));
            if ($sourceDisplay === '')
            	{
            	$sourceDisplay = trim((string) ($row['displayed_link'] ?? '-'));
            	}
            ?>
            <tr>
              <td style="width:1%;" class="text-center">
                <input class="form-check-input serpapi-result-checkbox" type="checkbox" name="result_ids[]" value="<?= (int) $row['id']; ?>" form="serpApiBulkDeleteForm" aria-label="Selecionar resultado #<?= (int) $row['id']; ?>">
              </td>
              <td style="width:1%;"><?= (int) $row['id']; ?></td>
              <td style="width:1%;">
                <span class="badge text-bg-secondary">#<?= (int) $row['search_id']; ?></span>
              </td>
              <td style="width:1%;" class="text-nowrap"><?= htmlspecialchars($searchTypeLabels[$searchType] ?? ucfirst($searchType), ENT_QUOTES, 'UTF-8'); ?></td>
              <td style="width:1%;"><?= (int) ($row['position'] ?? 0); ?></td>
              <td>
                <div class="fw-semibold text-break"><?= htmlspecialchars((string) ($row['title'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($row['link'])): ?>
                  <a class="small text-break d-block" href="<?= htmlspecialchars((string) $row['link'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) $row['link'], ENT_QUOTES, 'UTF-8'); ?></a>
                <?php endif; ?>
                <?php if (!empty($row['snippet'])): ?>
                  <small class="text-secondary text-break d-block mt-1"><?= htmlspecialchars((string) $row['snippet'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
                <small class="text-secondary d-block mt-1">Consulta: <?= htmlspecialchars((string) ($row['query'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
              </td>
              <td>
                <div class="text-break"><?= htmlspecialchars($sourceDisplay !== '' ? $sourceDisplay : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($location)): ?>
                  <small class="text-secondary d-block"><?= htmlspecialchars(implode(' · ', $location), ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
                <?php if (!empty($row['result_date'])): ?>
                  <small class="text-secondary d-block"><?= htmlspecialchars((string) $row['result_date'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
              </td>
              <td style="width:1%;" class="text-nowrap">
                <?= !empty($row['checked_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $row['checked_at'])), ENT_QUOTES, 'UTF-8') : '-'; ?>
              </td>
              <td style="width:1%;" class="text-nowrap text-end">
                <form action="<?= $deleteAction; ?>" class="d-inline serpapi-result-delete-form" method="post">
                  <input type="hidden" name="result_ids[]" value="<?= (int) $row['id']; ?>">
                  <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($returnPath, ENT_QUOTES, 'UTF-8'); ?>">
                  <button class="btn btn-sm btn-outline-danger" type="submit" data-serpapi-delete-single data-result-id="<?= (int) $row['id']; ?>" title="Excluir resultado #<?= (int) $row['id']; ?>">
                    <i class="bi bi-trash" aria-hidden="true"></i>
                    <span class="visually-hidden">Excluir resultado #<?= (int) $row['id']; ?></span>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="9" class="text-center">Nenhum resultado encontrado.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <form action="<?= $deleteAction; ?>" class="serpapi-webcrawler-results-bulk-actions" id="serpApiBulkDeleteForm" method="post">
    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($returnPath, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="d-flex flex-column flex-md-row gap-2 align-items-md-center justify-content-between">
      <span class="text-secondary">
        <strong id="serpApiSelectedCount">0</strong> resultado(s) selecionado(s)
      </span>
      <button class="btn btn-danger" id="serpApiBulkDeleteButton" type="submit">
        <i class="bi bi-trash me-1" aria-hidden="true"></i>Excluir selecionados
      </button>
    </div>
  </form>

  <p class="text-muted"><?= (int) $total; ?> resultado(s) encontrado(s)</p>

  <?php if ($totalPages > 1): ?>
    <?= paginationLinks('tool/serpApiWebCrawlerResults', $page, $totalPages, $paginationQuery, 2, 'Anterior', 'Próxima'); ?>
  <?php endif; ?>
</section>

<div class="modal fade" id="serpApiDeleteConfirmModal" tabindex="-1" aria-labelledby="serpApiDeleteConfirmTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="serpApiDeleteConfirmTitle">Confirmar exclusão</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <p class="mb-0" id="serpApiDeleteConfirmMessage">Tem certeza que deseja excluir os resultados selecionados?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="serpApiDeleteConfirmButton">
          <i class="bi bi-trash me-1" aria-hidden="true"></i>Excluir
        </button>
      </div>
    </div>
  </div>
</div>

<script src="<?= url('assets/js/tools/serpapi_webcrawler_results.js?v=2'); ?>"></script>

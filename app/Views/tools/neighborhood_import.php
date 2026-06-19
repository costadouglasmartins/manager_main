<?php
$previewResult = $previewResult ?? null;
$confirmationResult = $confirmationResult ?? null;
$stateOptions = $stateOptions ?? [];
$selectedStateAcronym = strtoupper((string) ($selectedStateAcronym ?? ($previewResult['selected_state_acronym'] ?? '')));
$selectedStateLabel = $selectedStateAcronym === 'TODOS' ? 'TODOS' : $selectedStateAcronym;
$summary = is_array($previewResult['summary'] ?? null) ? $previewResult['summary'] : [];
$rowsToImport = is_array($previewResult['rows_to_import'] ?? null) ? $previewResult['rows_to_import'] : [];
$rowsNotImported = is_array($previewResult['rows_not_imported'] ?? null) ? $previewResult['rows_not_imported'] : [];
$visibleRowsToImport = array_slice($rowsToImport, 0, 200);
$visibleRowsNotImported = array_slice($rowsNotImported, 0, 200);
?>

<link rel="stylesheet" href="<?= url('assets/css/tools/neighborhood_import.css?v=1'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Importador de bairros</li>
  </ol>
</nav>

<section class="neighborhood-import-tool">
  <div class="row g-4" id="neighborhoodImportApp" data-stream-url="<?= url('tool/streamNeighborhoodImport'); ?>">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="neighborhood-import-icon text-bg-success"><i class="bi bi-filetype-xlsx"></i></span>
            <div>
              <h1 class="neighborhood-import-title mb-0">Importador de bairros por XLSX</h1>
              <small class="text-secondary">Lê `nm_bairro`, `nm_mun` e `sigla_uf`, compara com `states`, `cities` e mostra uma prévia antes de salvar em `neighborhoods`.</small>
            </div>
          </div>

          <form action="<?= url('tool/neighborhoodImport'); ?>" class="row g-3" enctype="multipart/form-data" method="post">
            <div class="col-12 col-lg-5">
              <label class="form-label" for="xlsxFile">Arquivo XLSX</label>
              <input class="form-control" id="xlsxFile" name="xlsx_file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
              <div class="form-text">A primeira aba deve conter um cabeçalho com as colunas `nm_bairro`, `nm_mun` e `sigla_uf`.</div>
              <div class="small text-secondary mt-2 d-none" id="xlsxFileName"></div>
            </div>

            <div class="col-12 col-lg-3">
              <label class="form-label" for="stateAcronym">Estado</label>
              <select class="form-select" id="stateAcronym" name="state_acronym" required>
                <option value="">Selecione</option>
                <option value="TODOS" <?= $selectedStateAcronym === 'TODOS' ? 'selected' : ''; ?>>TODOS</option>
                <?php foreach ($stateOptions as $stateOption): ?>
                  <?php $stateOptionAcronym = strtoupper((string) ($stateOption['acronym'] ?? '')); ?>
                  <option value="<?= htmlspecialchars($stateOptionAcronym, ENT_QUOTES, 'UTF-8'); ?>" <?= $selectedStateAcronym === $stateOptionAcronym ? 'selected' : ''; ?>>
                    <?= htmlspecialchars((string) ($stateOption['name'] ?? $stateOptionAcronym), ENT_QUOTES, 'UTF-8'); ?> (<?= htmlspecialchars($stateOptionAcronym, ENT_QUOTES, 'UTF-8'); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Escolha um estado ou TODOS para analisar todas as linhas a partir da linha 2.</div>
            </div>

            <div class="col-12 col-lg-4 d-flex align-items-end justify-content-lg-end">
              <input type="hidden" name="import_action" value="preview">
              <button class="btn btn-success w-100" type="submit">
                <i class="bi bi-search me-1"></i>Gerar prévia
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php if (is_array($confirmationResult)): ?>
      <div class="col-12">
        <div class="alert alert-<?= !empty($confirmationResult['success']) ? 'success' : 'danger'; ?> mb-0" role="alert">
          <?= htmlspecialchars((string) ($confirmationResult['message'] ?? 'Processamento concluído.'), ENT_QUOTES, 'UTF-8'); ?>
          <?php if (!empty($confirmationResult['inserted'])): ?>
            <strong class="ms-1"><?= (int) $confirmationResult['inserted']; ?> registro(s) importado(s).</strong>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (is_array($previewResult)): ?>
      <div class="col-12">
        <div class="alert alert-<?= !empty($previewResult['success']) ? 'info' : 'danger'; ?> mb-0" role="alert">
          <?= htmlspecialchars((string) ($previewResult['message'] ?? 'Processamento concluído.'), ENT_QUOTES, 'UTF-8'); ?>
        </div>
      </div>

      <div class="col-12">
          <div class="neighborhood-import-summary">
          <div class="neighborhood-import-summary-card">
            <small>Linhas lidas</small>
            <strong><?= (int) ($summary['total_rows'] ?? 0); ?></strong>
          </div>
          <div class="neighborhood-import-summary-card">
            <small><?= $selectedStateAcronym === 'TODOS' ? 'Linhas analisadas' : 'Linhas do estado' . ($selectedStateLabel !== '' ? ' ' . htmlspecialchars($selectedStateLabel, ENT_QUOTES, 'UTF-8') : ''); ?></small>
            <strong><?= (int) ($summary['selected_state_rows'] ?? 0); ?></strong>
          </div>
          <div class="neighborhood-import-summary-card">
            <small>Ignoradas pelo filtro</small>
            <strong><?= (int) ($summary['ignored_by_state_filter'] ?? 0); ?></strong>
          </div>
          <div class="neighborhood-import-summary-card">
            <small>Serão importados</small>
            <strong><?= (int) ($summary['ready_to_import'] ?? 0); ?></strong>
          </div>
          <div class="neighborhood-import-summary-card">
            <small>Não serão importados</small>
            <strong><?= (int) ($summary['not_imported'] ?? 0); ?></strong>
          </div>
          <div class="neighborhood-import-summary-card">
            <small>Cidades que serão criadas</small>
            <strong><?= (int) ($summary['cities_to_create'] ?? 0); ?></strong>
          </div>
          <div class="neighborhood-import-summary-card">
            <small>Bairros que serão criados</small>
            <strong><?= (int) ($summary['neighborhoods_to_create'] ?? 0); ?></strong>
          </div>
          <div class="neighborhood-import-summary-card">
            <small>Já existentes</small>
            <strong><?= (int) ($summary['already_exists'] ?? 0); ?></strong>
          </div>
          <div class="neighborhood-import-summary-card">
            <small>Duplicados no arquivo</small>
            <strong><?= (int) ($summary['duplicate_in_file'] ?? 0); ?></strong>
          </div>
          <div class="neighborhood-import-summary-card">
            <small>Cidade ambígua</small>
            <strong><?= (int) ($summary['city_ambiguous'] ?? 0); ?></strong>
          </div>
          <div class="neighborhood-import-summary-card">
            <small>Linhas incompletas</small>
            <strong><?= (int) ($summary['incomplete_rows'] ?? 0); ?></strong>
          </div>
          <div class="neighborhood-import-summary-card">
            <small>Linhas vazias</small>
            <strong><?= (int) ($summary['empty_rows'] ?? 0); ?></strong>
          </div>
        </div>
      </div>

      <?php if (!empty($rowsToImport)): ?>
        <div class="col-12">
          <div class="card shadow-sm">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
              <div>
                <h2 class="h5 mb-1">Confirmação de importação</h2>
                <p class="text-secondary mb-0">Revise a prévia abaixo. Ao confirmar, somente os registros marcados para importação serão salvos em `neighborhoods`.</p>
              </div>
              <div class="d-flex flex-column align-items-lg-end gap-2">
                <button class="btn btn-primary" type="button" id="neighborhoodImportConfirmButton">
                  <i class="bi bi-check2-circle me-1"></i>Confirmar importação
                </button>
                <small class="text-secondary" id="neighborhoodImportStatusText">Aguardando confirmação.</small>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-flex align-items-center gap-2 mb-3">
              <span class="neighborhood-import-icon text-bg-primary"><i class="bi bi-activity"></i></span>
              <h2 class="neighborhood-import-title mb-0">Importação em tempo real</h2>
            </div>

            <div class="neighborhood-import-summary neighborhood-import-summary-live mb-3">
              <div class="neighborhood-import-summary-card">
                <small>Processados</small>
                <strong id="neighborhoodImportProcessedCount">0</strong>
              </div>
              <div class="neighborhood-import-summary-card">
                <small>Importados</small>
                <strong id="neighborhoodImportImportedCount">0</strong>
              </div>
              <div class="neighborhood-import-summary-card">
                <small>Cidades criadas</small>
                <strong id="neighborhoodImportCreatedCitiesCount">0</strong>
              </div>
              <div class="neighborhood-import-summary-card">
                <small>Bairros criados</small>
                <strong id="neighborhoodImportCreatedNeighborhoodsCount">0</strong>
              </div>
              <div class="neighborhood-import-summary-card">
                <small>Falhas</small>
                <strong id="neighborhoodImportFailedCount">0</strong>
              </div>
              <div class="neighborhood-import-summary-card">
                <small>Status</small>
                <strong id="neighborhoodImportLiveStatus">Aguardando confirmação</strong>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th>Linha</th>
                    <th>UF</th>
                    <th>Município</th>
                    <th>Bairro</th>
                    <th>Situação</th>
                    <th>Mensagem</th>
                  </tr>
                </thead>
                <tbody id="neighborhoodImportLiveBody">
                  <tr>
                    <td colspan="6" class="text-secondary">Aguardando início da importação.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <h2 class="h5 mb-3">Registros que serão importados</h2>
            <?php if (!empty($rowsToImport)): ?>
              <?php if (count($rowsToImport) > count($visibleRowsToImport)): ?>
                <p class="text-secondary small">Exibindo os primeiros <?= count($visibleRowsToImport); ?> registros aptos para importação.</p>
              <?php endif; ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Linha</th>
                      <th>UF</th>
                      <th>Município</th>
                      <th>Bairro</th>
                      <th>Ação</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($visibleRowsToImport as $row): ?>
                      <tr>
                        <td><?= (int) ($row['line'] ?? 0); ?></td>
                        <td><?= htmlspecialchars((string) ($row['state_acronym'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) ($row['city_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) ($row['neighborhood_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= !empty($row['will_create_city']) ? 'Criar cidade + bairro' : 'Criar bairro'; ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-secondary mb-0">Nenhum registro está apto para importação nesta prévia.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-6">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <h2 class="h5 mb-3">Registros que não serão importados</h2>
            <?php if (!empty($rowsNotImported)): ?>
              <?php if (count($rowsNotImported) > count($visibleRowsNotImported)): ?>
                <p class="text-secondary small">Exibindo os primeiros <?= count($visibleRowsNotImported); ?> registros não importados.</p>
              <?php endif; ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th>Linha</th>
                      <th>UF</th>
                      <th>Município</th>
                      <th>Bairro</th>
                      <th>Motivo</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($visibleRowsNotImported as $row): ?>
                      <tr>
                        <td><?= (int) ($row['line'] ?? 0); ?></td>
                        <td><?= htmlspecialchars((string) ($row['state_acronym'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) ($row['city_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) ($row['neighborhood_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?= htmlspecialchars((string) ($row['reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-secondary mb-0">Nenhum registro foi descartado nesta prévia.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<script src="<?= url('assets/js/tools/neighborhood_import.js?v=1'); ?>"></script>

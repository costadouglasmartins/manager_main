<!-- CSS -->
<link rel="stylesheet" href="<?= url('assets/css/tools/zip_code_neighborhood.css?v=1'); ?>">
<!-- /CSS -->

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Bairro e cidade por CEP</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<section class="zip-code-neighborhood-tool" id="zipCodeNeighborhoodApp" data-compare-url="<?= url('tool/compareZipCodeNeighborhood'); ?>" data-save-url="<?= url('tool/saveZipCodeNeighborhood'); ?>">
  <div class="row g-4">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="zip-code-neighborhood-icon text-bg-primary"><i class="bi bi-diagram-3-fill"></i></span>
            <div>
              <h1 class="zip-code-neighborhood-title mb-0">Comparar ViaCEP e BrasilAPI</h1>
              <small class="text-secondary">Escolha campo por campo o que será salvo em `cities` e `neighborhoods`.</small>
            </div>
          </div>

          <form id="zipCodeNeighborhoodForm" class="row g-3" novalidate>
            <div class="col-12 col-lg-4">
              <label class="form-label" for="zipCodeNeighborhoodInput">CEP</label>
              <input class="form-control" id="zipCodeNeighborhoodInput" name="zip_code" type="text" inputmode="numeric" autocomplete="off" maxlength="9" placeholder="00000-000" required>
            </div>
            <div class="col-12 col-lg-8 d-flex align-items-end justify-content-between flex-wrap gap-2">
              <div class="form-check form-switch mb-0">
                <input class="form-check-input" id="createCitySwitch" name="create_city" type="checkbox">
                <label class="form-check-label" for="createCitySwitch">Criar cidade se ela não existir</label>
              </div>
              <div class="d-flex gap-2">
                <button class="btn btn-secondary" type="button" id="zipCodeNeighborhoodClear">
                  <i class="bi bi-eraser me-1"></i>Limpar
                </button>
                <button class="btn btn-primary" type="submit" id="zipCodeNeighborhoodCompare">
                  <i class="bi bi-search me-1"></i>Comparar
                </button>
                <button class="btn btn-success d-none" type="button" id="zipCodeNeighborhoodSave">
                  <i class="bi bi-save2 me-1"></i>Salvar seleção
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="alert alert-secondary zip-code-neighborhood-message" id="zipCodeNeighborhoodMessage" role="status" aria-live="polite">
        Informe um CEP para comparar os dados dos dois serviços.
      </div>
    </div>

    <div class="col-12" id="zipCodeNeighborhoodComparison"></div>

    <div class="col-12">
      <label class="form-label" for="zipCodeNeighborhoodJson">JSON retornado</label>
      <pre class="zip-code-neighborhood-json mb-0" id="zipCodeNeighborhoodJson">{}</pre>
    </div>
  </div>
</section>

<script src="<?= url('assets/js/tools/zip_code_neighborhood.js?v=1'); ?>"></script>

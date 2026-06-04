<!-- CSS -->
<link rel="stylesheet" href="<?= url('assets/css/tools/zip_code_search.css?v=1'); ?>">
<!-- /CSS -->

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Pesquisa de CEP</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<section class="zip-code-search-tool" id="zipCodeSearchApp" data-search-url="<?= url('tool/searchZipCode'); ?>">
  <div class="row g-4">
    <div class="col-12 col-xl-5">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="zip-code-search-icon text-bg-primary"><i class="bi bi-geo-alt-fill"></i></span>
            <div>
              <h1 class="zip-code-search-title mb-0">Pesquisa de CEP</h1>
              <small class="text-secondary">ViaCEP + BrasilAPI com fallback automático</small>
            </div>
          </div>

          <form id="zipCodeSearchForm" class="row g-3" novalidate>
            <div class="col-12">
              <label class="form-label" for="zipCodeInput">CEP</label>
              <input class="form-control" id="zipCodeInput" name="zip_code" type="text" inputmode="numeric" autocomplete="off" maxlength="9" placeholder="00000-000" required>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 justify-content-between align-items-center">
              <button class="btn btn-secondary" type="button" id="zipCodeClear">
                <i class="bi bi-eraser me-1"></i>Limpar
              </button>
              <button class="btn btn-primary" type="submit" id="zipCodeSubmit">
                <i class="bi bi-search me-1"></i>Pesquisar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-7">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="zip-code-search-icon text-bg-success"><i class="bi bi-braces"></i></span>
            <h2 class="zip-code-search-title mb-0">Resultado</h2>
          </div>

          <div class="alert alert-secondary zip-code-search-message" id="zipCodeMessage" role="status" aria-live="polite">
            Informe um CEP para consultar os dados e salvar o bairro na base.
          </div>

          <div class="zip-code-search-summary d-none" id="zipCodeSummary"></div>

          <label class="form-label" for="zipCodeJson">JSON retornado</label>
          <pre class="zip-code-search-json mb-0" id="zipCodeJson">{}</pre>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="<?= url('assets/js/tools/zip_code_search.js?v=1'); ?>"></script>

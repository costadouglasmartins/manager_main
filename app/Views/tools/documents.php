<!-- CSS -->
<link rel="stylesheet" href="<?= url('assets/css/tools/documents.css?v=3'); ?>">
<!-- /CSS -->

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">CPF e CNPJ</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<section class="document-tool" id="documentToolApp">
  <div class="row g-4">
    <div class="col-12 col-xl-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="document-tool-icon text-bg-primary"><i class="bi bi-magic"></i></span>
            <h2 class="document-tool-section-title mb-0">Gerar documento</h2>
          </div>

          <form id="documentGeneratorForm" class="row g-3" novalidate>
            <div class="col-12">
              <label class="form-label" for="generatorType">Tipo</label>
              <select class="form-select" id="generatorType">
                <option value="cpf">CPF</option>
                <option value="cnpj_current">CNPJ atual</option>
                <option value="cnpj_new">CNPJ novo</option>
              </select>
            </div>

            <div class="col-12">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="generatorFormatted" checked>
                <label class="form-check-label" for="generatorFormatted">Gerar com máscara</label>
              </div>
            </div>

            <div class="col-12 d-none" id="cnpjNewOptions">
              <div class="document-branch-options">
                <div class="document-branch-options-header">
                  <div>
                    <span class="document-tool-kicker text-body-secondary">CNPJ novo</span>
                    <strong>Estabelecimentos</strong>
                  </div>
                  <span class="badge text-bg-primary">MATRIZ 1</span>
                </div>

                <div class="document-branch-stepper" aria-label="Quantidade de filiais">
                  <label class="form-label mb-0" for="cnpjNewBranches">Filiais</label>
                  <div class="input-group">
                    <button class="btn btn-outline-primary" type="button" id="decreaseBranches" aria-label="Diminuir quantidade de filiais">
                      <i class="bi bi-dash-lg"></i>
                    </button>
                    <input class="form-control text-center" id="cnpjNewBranches" type="number" min="0" max="99" step="1" value="1" inputmode="numeric">
                    <button class="btn btn-outline-primary" type="button" id="increaseBranches" aria-label="Aumentar quantidade de filiais">
                      <i class="bi bi-plus-lg"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label" for="generatedDocument">Resultado</label>
              <div class="input-group">
                <input class="form-control document-tool-output" id="generatedDocument" type="text" readonly aria-live="polite">
                <button class="btn btn-outline-secondary" type="button" id="copyGeneratedDocument" title="Copiar documento" aria-label="Copiar documento">
                  <i class="bi bi-clipboard"></i>
                </button>
              </div>
            </div>

            <div class="col-12 d-none" id="cnpjNewGeneratedList" aria-live="polite"></div>

            <div class="col-12 d-flex flex-wrap gap-2 justify-content-between align-items-center">
              <button class="btn btn-secondary" type="button" id="clearGeneratedDocument">
                <i class="bi bi-eraser me-1"></i>Limpar
              </button>
              <button class="btn btn-primary" type="submit">
                <i class="bi bi-stars me-1"></i>Gerar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="document-tool-icon text-bg-success"><i class="bi bi-shield-check"></i></span>
            <h2 class="document-tool-section-title mb-0">Validar documento</h2>
          </div>

          <form id="documentValidatorForm" class="row g-3" novalidate>
            <div class="col-12 col-lg-4">
              <label class="form-label" for="validatorType">Tipo</label>
              <select class="form-select" id="validatorType">
                <option value="auto">Detectar automaticamente</option>
                <option value="cpf">CPF</option>
                <option value="cnpj_current">CNPJ atual</option>
                <option value="cnpj_new">CNPJ novo</option>
              </select>
            </div>

            <div class="col-12 col-lg-8">
              <label class="form-label" for="validatorDocument">Documento</label>
              <input class="form-control document-tool-output" id="validatorDocument" type="text" autocomplete="off" placeholder="000.000.000-00">
            </div>

            <div class="col-12">
              <div class="alert alert-secondary mb-0 document-tool-result" id="validationResult" role="status" aria-live="polite">
                Informe um documento para validar.
              </div>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 justify-content-between align-items-center">
              <button class="btn btn-secondary" type="button" id="clearValidationDocument">
                <i class="bi bi-eraser me-1"></i>Limpar
              </button>
              <button class="btn btn-success" type="submit">
                <i class="bi bi-check2-circle me-1"></i>Validar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="/assets/helpers/helper.js?v=2"></script>
<script src="<?= url('assets/js/tools/documents.js?v=3'); ?>"></script>

<!-- CSS -->
<link rel="stylesheet" href="<?= url('assets/css/tools/email_validation.css?v=1'); ?>">
<!-- /CSS -->

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Validador de e-mail</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<section class="email-validation-tool" id="emailValidationApp" data-validation-url="<?= url('tool/email_validation'); ?>">
  <div class="row g-4">
    <div class="col-12 col-xl-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-3">
            <span class="email-validation-icon text-bg-primary"><i class="bi bi-envelope-check-fill"></i></span>
            <h1 class="email-validation-title mb-0">Validar MX do e-mail</h1>
          </div>

          <form id="emailValidationForm" class="row g-3" novalidate>
            <div class="col-12">
              <label class="form-label" for="emailValidationInput">Endereço de e-mail</label>
              <input class="form-control" id="emailValidationInput" name="email" type="email" autocomplete="off" placeholder="nome@dominio.com.br" required>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 justify-content-between align-items-center">
              <button class="btn btn-secondary" type="button" id="emailValidationClear">
                <i class="bi bi-eraser me-1"></i>Limpar
              </button>
              <button class="btn btn-primary" type="submit" id="emailValidationSubmit">
                <i class="bi bi-search me-1"></i>Validar MX
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
            <span class="email-validation-icon text-bg-success"><i class="bi bi-braces"></i></span>
            <h2 class="email-validation-title mb-0">Resultado</h2>
          </div>

          <div class="alert alert-secondary email-validation-message" id="emailValidationMessage" role="status" aria-live="polite">
            Informe um e-mail para consultar os registros MX do domínio.
          </div>

          <div class="email-validation-summary d-none" id="emailValidationSummary"></div>

          <label class="form-label" for="emailValidationJson">JSON retornado</label>
          <pre class="email-validation-json mb-0" id="emailValidationJson">{}</pre>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="<?= url('assets/js/tools/email_validation.js?v=1'); ?>"></script>

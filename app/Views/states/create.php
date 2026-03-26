
<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('state'); ?>">Estados</a></li>
    <li class="breadcrumb-item active" aria-current="page">Estado - criar</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<!-- Estado -->
<h5>Criar estado</h5>
<form action="<?= url('state/store/'); ?>" method="POST">
  <div class="row">

    <div class="mb-2 col-md-6 col-lg-8">
      <label class="form-label" for="name">Nome</label>
      <input autocomplete="off" class="form-control" id="name" name="name" maxlength="255" type="text">
    </div>

    <div class="mb-2 col-md-6 col-lg-4">
      <label class="form-label" for="acronym">Sigla</label>
      <input autocomplete="off" class="form-control" id="acronym" name="acronym" maxlength="2" type="text">
    </div>

    <div class="mb-2 col-md-6 col-lg-12 position-relative">
      <label class="form-label" for="countryName">País</label>
      <input type="hidden" id="country_id" name="country_id" value="">
      <input id="countryName" type="text" class="form-control js-autocomplete" autocomplete="off"
        placeholder="Digite o nome do país..."
        data-endpoint="<?= url('country/autocomplete'); ?>"
        data-results="#countryResults"
        data-hidden="#country_id"
        data-minlen="1"
        data-param="term">
      <ul id="countryResults" class="list-group mt-1 w-100" style="overflow:auto; max-height:240px; position:absolute; z-index:1050;"></ul>
    </div>

  </div>
  <br>
  <div class="row">
    <div class="col-lg-12">
      <div class="d-flex flex-column gap-2 align-items-center align-items-lg-end">
        <button class="btn btn-primary" type="submit">Salvar</button>
      </div>
    </div>
  </div>
</form>
<!-- /Estado -->

<script src="<?= url('assets/js/autocomplete.js?v=2'); ?>"></script>


<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('neighborhood'); ?>">Bairros</a></li>
    <li class="breadcrumb-item active" aria-current="page">Bairro - criar</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<!-- Bairro -->
<form action="<?= url('neighborhood/store/'); ?>" method="POST">
  <div class="row">

    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="name">Nome</label>
      <input autocomplete="off" class="form-control" id="name" name="name" maxlength="255" type="text">
    </div>

    <div class="mb-2 col-md-6 col-lg-12 position-relative">
      <label class="form-label" for="cityName">Cidade</label>
      <input type="hidden" id="city_id" name="city_id" value="">
      <input id="cityName" type="text" class="form-control js-autocomplete" autocomplete="off"
        placeholder="Digite o nome da cidade..."
        data-endpoint="<?= url('city/autocomplete'); ?>"
        data-results="#cityResults"
        data-hidden="#city_id"
        data-minlen="1"
        data-param="term">
      <ul id="cityResults" class="list-group mt-1 w-100"></ul>
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
<!-- /Bairro -->

<script src="/assets/autocomplete/autocomplete.js?v=4"></script>

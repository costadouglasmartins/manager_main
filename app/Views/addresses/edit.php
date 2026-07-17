
<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('address'); ?>">Endereços</a></li>
    <li class="breadcrumb-item active" aria-current="page">Endereço #<?= (int) $address['id']; ?> - editar</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<!-- Endereço -->
<h5>Editar endereço</h5>
<form action="<?= url('address/update/' . $address['id']); ?>" method="POST">
  <div class="row">

    <div class="mb-2 col-md-6 col-lg-12 position-relative">
      <label class="form-label" for="personName">Pessoa</label>
      <input type="hidden" id="person_id" name="person_id" value="<?= (int) $address['person_id']; ?>">
      <input id="personName" type="text" class="form-control js-autocomplete" autocomplete="off" value="<?= htmlspecialchars($address['person_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Digite o nome da pessoa..." data-endpoint="<?= url('person/autocomplete'); ?>" data-results="#personResults" data-hidden="#person_id" data-minlen="1" data-param="term">
      <ul id="personResults" class="list-group mt-1 w-100"></ul>
    </div> 

    <div class="mb-2 col-md-6 col-lg-3">
      <label class="form-label" for="zip_code">CEP</label>
      <input autocomplete="off" class="form-control" id="zip_code" name="zip_code" maxlength="45" type="text" value="<?= htmlspecialchars($address['zip_code'], ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="mb-2 col-md-6 col-lg-3">
      <label class="form-label" for="street">Logradouro</label>
      <input autocomplete="off" class="form-control" id="street" name="street" maxlength="255" type="text" value="<?= htmlspecialchars($address['street'], ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="mb-2 col-md-6 col-lg-3">
      <label class="form-label" for="observations">Observações</label>
      <input autocomplete="off" class="form-control" id="observations" name="observations" maxlength="255" type="text" value="<?= htmlspecialchars($address['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="mb-2 col-md-6 col-lg-3">
      <label class="form-label" for="number">Número</label>
      <input autocomplete="off" class="form-control" id="number" name="number" maxlength="10" type="text" value="<?= htmlspecialchars($address['number'], ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="mb-2 col-md-6 col-lg-12 position-relative">
      <label class="form-label" for="neighborhoodName">Bairro</label>
      <input type="hidden" id="neighborhood_id" name="neighborhood_id" value="<?= (int) $address['neighborhood_id']; ?>">
      <input id="neighborhoodName" type="text" class="form-control js-autocomplete" autocomplete="off" value="<?= htmlspecialchars($address['neighborhood_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Digite o nome do bairro..." data-endpoint="<?= url('neighborhood/autocomplete'); ?>" data-results="#neighborhoodResults" data-hidden="#neighborhood_id" data-minlen="1" data-param="term">
      <ul id="neighborhoodResults" class="list-group mt-1 w-100"></ul>
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
<!-- /Endereço -->

<script src="/assets/autocomplete/autocomplete.js?v=4"></script>

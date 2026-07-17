
<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('city'); ?>">Cidades</a></li>
    <li class="breadcrumb-item active" aria-current="page">Cidade - <?= htmlspecialchars($city['name'], ENT_QUOTES, 'UTF-8'); ?> - editar</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<!-- Cidade -->
<h5>Editar cidade</h5>
<form action="<?= url('city/update/' . $city['id']); ?>" method="POST">
  <div class="row">

    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="name">Nome</label>
      <input autocomplete="off" class="form-control" id="name" name="name" maxlength="255" type="text" value="<?= htmlspecialchars($city['name'], ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="description">Descrição</label>
      <input autocomplete="off" class="form-control" id="description" name="description" maxlength="255" type="text" value="<?= htmlspecialchars($city['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="mb-2 col-md-6 col-lg-12 position-relative">
      <label class="form-label" for="stateName">Estado</label>
      <input type="hidden" id="state_id" name="state_id" value="<?= (int) $city['state_id']; ?>">
      <input id="stateName" type="text" class="form-control js-autocomplete" autocomplete="off"
        value="<?= htmlspecialchars($city['state_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
        placeholder="Digite o nome do estado..."
        data-endpoint="<?= url('state/autocomplete'); ?>"
        data-results="#stateResults"
        data-hidden="#state_id"
        data-minlen="1"
        data-param="term">
      <ul id="stateResults" class="list-group mt-1 w-100"></ul>
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
<!-- /Cidade -->

<script src="/assets/autocomplete/autocomplete.js?v=4"></script>

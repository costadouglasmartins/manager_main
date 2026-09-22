
<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('phone_number'); ?>">Telefones</a></li>
    <li class="breadcrumb-item active" aria-current="page">Telefone - <?= htmlspecialchars($phone_number['phone_number'], ENT_QUOTES, 'UTF-8'); ?> - editar</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<!-- Telefone -->
<h5>Editar telefone</h5>
<form action="<?= url('phone_number/update/' . $phone_number['id']); ?>" method="POST">
  <div class="row">

    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="phone_number">Telefone</label>
      <input autocomplete="off" class="form-control" id="phone_number" name="phone_number" maxlength="255" type="text" value="<?= htmlspecialchars($phone_number['phone_number'], ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="observations">Observações</label>
      <input autocomplete="off" class="form-control" id="observations" name="observations" maxlength="500" type="text" value="<?= htmlspecialchars($phone_number['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="mb-2 col-md-6 col-lg-12 position-relative">
      <label class="form-label" for="personName">Pessoa</label>
      <input type="hidden" id="person_id" name="person_id" value="<?= (int) ($phone_number['person_id'] ?? 0); ?>">
      <input id="personName" type="text" class="form-control js-autocomplete" autocomplete="off"
        value="<?= htmlspecialchars($phone_number['person_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
        placeholder="Digite o nome da pessoa..."
        data-endpoint="<?= url('person/autocomplete'); ?>"
        data-results="#personResults"
        data-hidden="#person_id"
        data-minlen="1"
        data-param="term">
      <ul id="personResults" class="list-group mt-1 w-100"></ul>
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
<!-- /Telefone -->

<script src="/manager_assets/autocomplete/autocomplete.js?v=4"></script>

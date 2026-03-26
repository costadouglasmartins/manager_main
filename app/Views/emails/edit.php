
<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('email'); ?>">E-mails</a></li>
    <li class="breadcrumb-item active" aria-current="page">E-mail - <?= htmlspecialchars($email['email'], ENT_QUOTES, 'UTF-8'); ?> - editar</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<!-- E-mail -->
<h5>Editar e-mail</h5>
<form action="<?= url('email/update/' . $email['id']); ?>" method="POST">
  <div class="row">

    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="email">E-mail</label>
      <input autocomplete="off" class="form-control" id="email" name="email" maxlength="255" type="email" value="<?= htmlspecialchars($email['email'], ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    
    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="type">Utilizar para login</label>
      <select class="form-select" id="type" name="type">
        <option value="" <?= !$email['is_login'] ? 'selected' : ''; ?>>Selecione...</option>
        <option value="0" <?= $email['is_login'] === 0 ? 'selected' : ''; ?>>Não</option>
        <option value="1" <?= $email['is_login'] === 1 ? 'selected' : ''; ?>>Sim</option>
      </select>
    </div>

    <div class="mb-2 col-md-6 col-lg-12">
      <label class="form-label" for="observations">Observações</label>
      <input autocomplete="off" class="form-control" id="observations" name="observations" maxlength="500" type="text" value="<?= htmlspecialchars($email['observations'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="mb-2 col-md-6 col-lg-12 position-relative">
      <label class="form-label" for="personName">Pessoa</label>
      <input type="hidden" id="person_id" name="person_id" value="<?= (int) ($email['person_id'] ?? 0); ?>">
      <input id="personName" type="text" class="form-control js-autocomplete" autocomplete="off"
        value="<?= htmlspecialchars($email['person_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
        placeholder="Digite o nome da pessoa..."
        data-endpoint="<?= url('person/autocomplete'); ?>"
        data-results="#personResults"
        data-hidden="#person_id"
        data-minlen="1"
        data-param="term">
      <ul id="personResults" class="list-group mt-1 w-100" style="overflow:auto; max-height:240px; position:absolute; z-index:1050;"></ul>
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
<!-- /E-mail -->

<script src="<?= url('assets/js/autocomplete.js?v=2'); ?>"></script>

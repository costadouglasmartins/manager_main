<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('person'); ?>">Pessoas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Editar - <?= htmlspecialchars($person['name']); ?></li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Editar pessoa</h5>
<form action="<?= url('person/update/' . $person['id']); ?>" method="POST">
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="row g-3">

        <!-- Nome -->
        <div class="col-12 col-md-6">
          <label class="form-label" for="name">Nome completo</label>
          <input autocomplete="off" class="form-control" id="name" name="name" maxlength="255" type="text" value="<?= htmlspecialchars($person['name']); ?>">
        </div>

        <!-- Nome fantasia -->
        <div class="col-12 col-md-6">
          <label class="form-label" for="fantasy">Nome fantasia</label>
          <input autocomplete="off" class="form-control" id="fantasy" name="fantasy" maxlength="255" type="text" value="<?= htmlspecialchars($person['fantasy'] ?? ''); ?>">
        </div>

        <!-- Tipo -->
        <div class="col-12 col-md-4">
          <label class="form-label" for="type">Tipo de pessoa</label>
          <select autocomplete="off" class="form-select" id="type" name="type">
            <option value="">Selecione...</option>
            <option value="individual" <?= ($person['type'] === 'individual' ? 'selected' : ''); ?>>Pessoa física</option>
            <option value="company" <?= ($person['type'] === 'company' ? 'selected' : ''); ?>>Pessoa jurídica</option>
          </select>
        </div>

        <!-- Documento -->
        <div class="col-12 col-md-4">
          <label class="form-label" for="document">Documento (CPF/CNPJ)</label>
          <input autocomplete="off" class="mask-cpf-cnpj form-control" id="document" name="document" maxlength="18" type="text" value="<?= maskCpfCnpj($person['document']); ?>">
        </div>

        <!-- Data de nascimento -->
        <div class="col-12 col-md-4">
          <label class="form-label" for="birthdate">Data de nascimento</label>
          <input autocomplete="off" class="form-control mask-date" id="birthdate" name="birthdate" type="text" value="<?= maskDateBr($person['birthdate']); ?>">
        </div>

        <!-- Ativo -->
        <div class="col-12 col-md-4">
          <label class="form-label" for="active">Situação</label>
          <select autocomplete="off" class="form-select" id="active" name="active">
            <option value="">Selecione...</option>
            <option value="1" <?= ((int)$person['active'] === 1 ? 'selected' : ''); ?>>Ativo</option>
            <option value="0" <?= ((int)$person['active'] === 0 ? 'selected' : ''); ?>>Inativo</option>
          </select>
        </div>

      </div>
    </div>
    <div class="card-footer bg-body d-flex justify-content-end gap-2">
      <a href="<?= url('person'); ?>" class="btn btn-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit">Salvar</button>
    </div>
  </div>
</form>

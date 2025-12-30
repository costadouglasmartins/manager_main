<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('company'); ?>">Empresas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Informações sobre <?= $company['name']; ?></li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<h5>Dados gerais da empresa</h5>
<div class="table-responsive mb-2">
  <table class="table table-striped table-hover">
    <thead>
      <tr>
        <th colspan="8"><?= $company['name'] . ' / ' . $company['fantasy']?></th>
      </tr>
      <tr>
        <th class="text-center">#</th>
        <th class="text-center">Nome</th>
        <th class="text-center">Fantasia</th>
        <th class="text-center">CNPJ</th>
        <th class="text-center">Banco de dados</th>
        <th class="text-center">Ativa</th>
        <th class="text-center">Criada</th>
        <th class="text-center">Modificada</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td class="text-center"><?= $company['id']; ?></td>
        <td class="text-center"><?= $company['name']; ?></td>
        <td class="text-center"><?= $company['fantasy']; ?></td>
        <td class="text-center"><?= maskCpfCnpj($company['document']); ?></td>
        <td class="text-center"><?= $company['database']; ?></td>
        <td class="text-center"><span class="badge text-bg-<?= ($company['active'] == 1 ? 'success' : 'secondary'); ?>"><?= ($company['active'] == 1 ? 'Ativa' : 'Inativa'); ?></span></td>
        <td class="text-center"><?= date('d/m/Y H:i:s', strtotime($company['created'])); ?></td>
        <td class="text-center"><?= date('d/m/Y H:i:s', strtotime($company['modified'])); ?></td>
      </tr>
    </tbody>
  </table>
</div>

<!-- PEOPLE + USER (ACCORDION, NO CARDS) -->
<div class="accordion" id="peopleAccordion">

  <!-- Person 101 -->
  <?php foreach($people as $key => $person): ?>
  <?php $open = ($key === 0); ?>

    <div class="accordion-item">
      <h2 class="accordion-header" id="p<?= $key; ?>h">
        <button class="accordion-button <?= $open ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#p<?= $key; ?>" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="p<?= $key; ?>">
          <div class="w-100 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <span class="badge text-bg-secondary"># <?= $person['person_id']; ?></span>
              <span class="fw-semibold"><?= $person['person_name']; ?></span>
              <span class="badge text-bg-<?= ($person['person_active'] == 1 ? 'success' : 'secondary'); ?>"><?= ($person['person_active'] == 1 ? 'Ativo(a)' : 'Inativo(a)'); ?></span>
              <span class="text-secondary small">tipo: <?= $person['person_type']; ?></span>
            </div>
            <div class="text-secondary small">documento: <?= maskCpfCnpj($person['person_document']); ?>  <?= (!empty($person['person_birthdate']) && $person['person_birthdate'] !== '' ? '• data de nascimento: ' . date('d/m/Y', strtotime($person['person_birthdate'])) : '-'); ?></div>
          </div>
        </button>
      </h2>

      <div id="p<?= $key; ?>" class="accordion-collapse collapse <?= $open ? 'show' : '' ?>" data-bs-parent="#peopleAccordion">
        <div class="accordion-body ps-0 pe-0">
          <div class="row">
            <!-- USER TABLE (1:1) -->
            <div class="col-12 col-lg-12">
              <div class="table-responsive">
                <table class="table table-striped table-hover">
                  <thead>
                    <tr><th colspan="9">Usuário(a)</th></tr>
                    <tr>
                      <th class="text-center">#</th>
                      <th class="text-center">Usuário(a)</th>
                      <th class="text-center">E-mail</th>
                      <th class="text-center">Tipo</th>
                      <th class="text-center">Ativo</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td class="text-center"><?= $person['user_id']; ?></td>
                      <td class="text-center"><?= $person['user_name']; ?></td>
                      <td class="text-center"><?= $person['user_email']; ?></td>
                      <td class="text-center"><?= $person['user_type']; ?></td>
                      <td class="text-center"><span class="badge text-bg-<?= ($person['user_active'] == 1 ? 'success' : 'secondary'); ?>"><?= ($person['user_active'] == 1 ? 'Ativo(a)' : 'Inativo(a)'); ?></span></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="col-12">
              <div class="d-flex flex-wrap gap-2 justify-content-end">
                <a class="btn btn-primary" href="<?= url('person/edit/' . $person['person_id']); ?>">Editar pessoa</a>
                <a class="btn btn-primary" href="<?= url('user/edit/' . $person['user_id']); ?>">Editar usuário(a)</a>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  <?php endforeach; ?>
</div>
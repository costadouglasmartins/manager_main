<?php
$tenantUsers = $tenantUsers ?? [];
$e = static function ($value): string
  {
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
  };

$statusLabels = [
  'active'    => 'Ativo',
  'suspended' => 'Suspenso',
  'pending'   => 'Pendente',
];

$roleLabels = [
  'owner' => 'Owner',
  'admin' => 'Admin',
  'user'  => 'Usuário',
];

$userTypeLabels = [
  'root'  => 'Root',
  'admin' => 'Administrador',
  'user'  => 'Usuário',
];

$canAddUser = authCan('tenant.addUser');
$canUpdateUser = authCan('tenant.updateUser');
$canRemoveUser = authCan('tenant.removeUser');
$defaultNewUserRole = empty($tenantUsers) ? 'owner' : 'user';
?>

<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('tenant'); ?>">Tenants</a></li>
    <li class="breadcrumb-item active" aria-current="page">Editar tenant</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<form action="<?= url('tenant/update/' . (int) $tenant['id']); ?>" method="POST" id="tenantEditForm" class="needs-validation" novalidate>
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
      <h5 class="mb-1">Editar tenant #<?= (int) $tenant['id']; ?></h5>
      <div class="text-body-secondary">
        <code>manager_<?= $e($tenant['database_number'] ?? ''); ?></code>
      </div>
    </div>
    <span class="badge text-bg-secondary"><?= count($tenantUsers); ?> usuário(s) vinculado(s)</span>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Dados do tenant</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-12 col-md-4">
          <label class="form-label">Pessoa associada</label>
          <input class="form-control" type="text" value="<?= $e($tenant['associated_person_name'] ?? ('Tenant #' . (int) $tenant['id'])); ?>" disabled>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label">Banco de dados</label>
          <input class="form-control" type="text" value="manager_<?= $e($tenant['database_number'] ?? ''); ?>" disabled>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label" for="status">Situação</label>
          <select autocomplete="off" class="form-select" id="status" name="status" required>
            <?php foreach ($statusLabels as $statusValue => $statusLabel): ?>
              <option value="<?= $e($statusValue); ?>" <?= (($tenant['status'] ?? '') === $statusValue ? 'selected' : ''); ?>>
                <?= $e($statusLabel); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Selecione a situação.</div>
        </div>

        <div class="col-12">
          <label class="form-label" for="observations">Observações</label>
          <textarea class="form-control" id="observations" name="observations" rows="3"><?= $e($tenant['observations'] ?? ''); ?></textarea>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Criado em</label>
          <input class="form-control" type="text" value="<?= !empty($tenant['created']) ? $e(maskDateBr(substr((string) $tenant['created'], 0, 10))) : ''; ?>" disabled>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label">Modificado em</label>
          <input class="form-control" type="text" value="<?= !empty($tenant['modified']) ? $e(maskDateBr(substr((string) $tenant['modified'], 0, 10))) : ''; ?>" disabled>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <strong>Pessoas e usuários vinculados</strong>
        <small class="text-body-secondary d-block">Vínculos gravados em <code>tenant_users</code> e usuários associados a <code>manager_<?= $e($tenant['database_number'] ?? ''); ?></code>.</small>
      </div>
      <span class="badge text-bg-primary"><?= count($tenantUsers); ?> vínculo(s)</span>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead>
            <tr>
              <th style="width: 1%;">#</th>
              <th>Pessoa</th>
              <th>Documento</th>
              <th>Usuário</th>
              <th>E-mail</th>
              <th style="width: 1%;">Acesso</th>
              <th style="width: 12rem;">Papel</th>
              <?php if ($canRemoveUser): ?>
                <th style="width: 1%;" class="text-nowrap text-end">Remover</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($tenantUsers)): ?>
              <?php foreach ($tenantUsers as $i => $tu): ?>
                <?php
                $role = (string) ($tu['role'] ?? 'user');
                $userActive = (int) ($tu['user_active'] ?? 0);
                ?>
                <tr data-tenant-user-row>
                  <td>
                    <?= (int) $tu['user_id']; ?>
                    <?php if ($canUpdateUser || $canRemoveUser): ?>
                      <input type="hidden" name="tenant_users[<?= (int) $i; ?>][user_id]" value="<?= (int) $tu['user_id']; ?>">
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="fw-semibold"><?= $e($tu['name'] ?? ''); ?></div>
                    <small class="text-body-secondary">Pessoa #<?= (int) ($tu['person_id'] ?? 0); ?></small>
                  </td>
                  <td><code><?= $e(maskCpfCnpj($tu['document'] ?? '') ?: ($tu['document'] ?? '')); ?></code></td>
                  <td>
                    <code><?= $e($tu['username'] ?? ''); ?></code>
                    <?php if ((int) ($tu['tenant_id'] ?? 0) === (int) $tenant['id']): ?>
                      <span class="badge text-bg-info ms-1">Principal</span>
                    <?php endif; ?>
                  </td>
                  <td><?= $e($tu['email'] ?? ''); ?></td>
                  <td>
                    <span class="badge <?= $userActive === 1 ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                      <?= $userActive === 1 ? 'Ativo' : 'Inativo'; ?>
                    </span>
                    <span class="badge text-bg-light border text-body"><?= $e($userTypeLabels[$tu['user_type'] ?? ''] ?? ($tu['user_type'] ?? '')); ?></span>
                  </td>
                  <td>
                    <?php if ($canUpdateUser): ?>
                      <select class="form-select form-select-sm" name="tenant_users[<?= (int) $i; ?>][role]" aria-label="Papel de <?= $e($tu['name'] ?? 'usuário'); ?>">
                        <?php foreach ($roleLabels as $roleValue => $roleLabel): ?>
                          <option value="<?= $e($roleValue); ?>" <?= $role === $roleValue ? 'selected' : ''; ?>>
                            <?= $e($roleLabel); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    <?php else: ?>
                      <span class="badge <?= $role === 'owner' ? 'text-bg-dark' : ($role === 'admin' ? 'text-bg-primary' : 'text-bg-secondary'); ?>">
                        <?= $e($roleLabels[$role] ?? $role); ?>
                      </span>
                    <?php endif; ?>
                  </td>
                  <?php if ($canRemoveUser): ?>
                    <td class="text-nowrap text-end">
                      <div class="form-check form-switch d-inline-flex align-items-center m-0">
                        <input class="form-check-input" type="checkbox" role="switch" name="tenant_users[<?= (int) $i; ?>][_delete]" value="1" aria-label="Remover <?= $e($tu['name'] ?? 'usuário'); ?>" data-tenant-user-remove>
                      </div>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="<?= $canRemoveUser ? 8 : 7; ?>" class="text-muted">Nenhum usuário vinculado a este tenant.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($canAddUser): ?>
    <div class="card shadow-sm mb-4">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
          <strong>Adicionar pessoa e usuário</strong>
          <small class="text-body-secondary d-block">A pessoa será criada com usuário, tenant principal e vínculo neste tenant.</small>
        </div>
        <div class="form-check form-switch m-0">
          <input class="form-check-input" type="checkbox" role="switch" id="tenantAddLinkedPerson" name="add_linked_person" value="1">
          <label class="form-check-label" for="tenantAddLinkedPerson">Adicionar</label>
        </div>
      </div>

      <div class="card-body" id="tenantLinkedPersonFields">
        <div class="row g-3">
          <div class="col-12">
            <h6 class="mb-0">Pessoa</h6>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="linkedPersonName">Nome completo</label>
            <input autocomplete="off" class="form-control" id="linkedPersonName" name="linked_person[name]" type="text" data-linked-person-field data-required="1" disabled>
            <div class="invalid-feedback">Informe o nome.</div>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="linkedPersonFantasy">Nome fantasia</label>
            <input autocomplete="off" class="form-control" id="linkedPersonFantasy" name="linked_person[fantasy]" type="text" data-linked-person-field disabled>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="linkedPersonType">Tipo de pessoa</label>
            <select autocomplete="off" class="form-select" id="linkedPersonType" name="linked_person[type]" data-linked-person-field data-required="1" disabled>
              <option value="">Selecione...</option>
              <option value="individual">Pessoa física</option>
              <option value="company">Pessoa jurídica</option>
            </select>
            <div class="invalid-feedback">Selecione o tipo de pessoa.</div>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="linkedPersonDocument">Documento</label>
            <input autocomplete="off" class="form-control mask-cpf-cnpj" id="linkedPersonDocument" name="linked_person[document]" type="text" data-linked-person-field data-required="1" disabled>
            <div class="invalid-feedback">Informe o documento.</div>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="linkedPersonBirthdate">Nascimento</label>
            <input autocomplete="off" class="form-control mask-date" id="linkedPersonBirthdate" name="linked_person[birthdate]" placeholder="DD/MM/AAAA" type="text" data-linked-person-field disabled>
          </div>

          <input type="hidden" name="linked_person[active]" value="1" data-linked-person-field disabled>

          <div class="col-12 pt-2">
            <h6 class="mb-0">Usuário</h6>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="linkedUserUsername">Nome de usuário</label>
            <input autocomplete="off" class="form-control" id="linkedUserUsername" name="linked_user[username]" type="text" data-linked-person-field data-required="1" disabled>
            <div class="invalid-feedback">Informe o usuário.</div>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="linkedUserType">Tipo de acesso</label>
            <select autocomplete="off" class="form-select" id="linkedUserType" name="linked_user[type]" data-linked-person-field data-required="1" disabled>
              <option value="user" selected>Usuário</option>
              <option value="admin">Administrador</option>
              <option value="root">Root</option>
            </select>
            <div class="invalid-feedback">Selecione o tipo de acesso.</div>
          </div>

          <div class="col-12 col-md-4">
            <label class="form-label" for="linkedTenantUserRole">Papel no tenant</label>
            <select autocomplete="off" class="form-select" id="linkedTenantUserRole" name="linked_tenant_user[role]" data-linked-person-field disabled>
              <option value="user" <?= $defaultNewUserRole === 'user' ? 'selected' : ''; ?>>Usuário</option>
              <option value="admin" <?= $defaultNewUserRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
              <option value="owner" <?= $defaultNewUserRole === 'owner' ? 'selected' : ''; ?>>Owner</option>
            </select>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="linkedUserPassword">Senha</label>
            <input autocomplete="new-password" class="form-control" id="linkedUserPassword" name="linked_user[password]" type="password" minlength="8" data-linked-person-field data-required="1" disabled>
            <div class="invalid-feedback">A senha deve ter pelo menos 8 caracteres.</div>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="linkedUserPasswordConfirm">Confirmar senha</label>
            <input autocomplete="new-password" class="form-control" id="linkedUserPasswordConfirm" name="linked_user[password_confirm]" type="password" minlength="8" data-linked-person-field data-required="1" disabled>
            <div class="invalid-feedback">Confirme a senha.</div>
          </div>

          <input type="hidden" name="linked_user[active]" value="1" data-linked-person-field disabled>

          <div class="col-12 pt-2">
            <h6 class="mb-0">E-mail</h6>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="linkedEmail">E-mail de login</label>
            <input autocomplete="off" class="form-control" id="linkedEmail" name="linked_email[email]" type="email" data-linked-person-field disabled>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="linkedEmailObservations">Observações do e-mail</label>
            <input autocomplete="off" class="form-control" id="linkedEmailObservations" name="linked_email[observations]" type="text" data-linked-person-field disabled>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
    <a href="<?= url('tenant'); ?>" class="btn btn-secondary">
      <i class="bi bi-arrow-left me-1"></i>Voltar
    </a>
    <button type="submit" class="btn btn-primary">
      <i class="bi bi-check2-circle me-1"></i>Salvar alterações
    </button>
  </div>
</form>

<script src="<?= url('assets/js/tenants/edit.js?v=1'); ?>"></script>

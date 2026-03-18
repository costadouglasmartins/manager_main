<!-- Breadcrumbs -->
<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('person'); ?>">Pessoas</a></li>
    <li class="breadcrumb-item active" aria-current="page">Nova pessoa</li>
  </ol>
</nav>
<!-- /Breadcrumbs -->

<!-- Progress -->
<div class="card shadow-sm mb-3">
	<div class="card-body">
		<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
			<div>
				<div class="fw-semibold" id="stepTitle">Etapa 1 de 4</div>
				<div class="text-body-secondary small" id="stepSubtitle">Pessoa</div>
			</div>
			<span class="badge text-bg-secondary" id="stepBadge">1/4</span>
		</div>

		<div class="progress mt-3" role="progressbar" aria-label="Progresso do cadastro" aria-valuemin="0" aria-valuemax="100">
			<div class="progress-bar" id="progressBar" style="width: 25%"></div>
		</div>

		<div class="row g-2 mt-3">
			<div class="col-6 col-md">
				<button type="button" class="btn w-100 btn-primary" data-step-jump="1">1. Pessoa</button>
			</div>
			<div class="col-6 col-md">
				<button type="button" class="btn w-100 btn-outline-secondary" data-step-jump="2">2. Usuário</button>
			</div>
			<div class="col-6 col-md">
				<button type="button" class="btn w-100 btn-outline-secondary" data-step-jump="3">3. Contatos</button>
			</div>
			<div class="col-12 col-md">
				<button type="button" class="btn w-100 btn-outline-secondary" data-step-jump="4">4. Revisão</button>
			</div>
		</div>
	</div>
</div>

<form action="<?= url('person/store'); ?>" method="POST" id="signupForm" class="needs-validation" novalidate>
	<div class="card shadow-sm">
		<div class="card-body">

			<!-- Step 1: Pessoa -->
			<section data-step="1">
				<h2 class="h6 mb-3">Pessoa</h2>

				<div class="row g-3">
					<div class="col-12 col-md-6">
						<label class="form-label" for="personName">Nome completo</label>
						<input autocomplete="off" class="form-control" id="personName" name="person[name]" placeholder="Nome completo" type="text" required />
						<div class="invalid-feedback">Informe o nome.</div>
					</div>

					<div class="col-12 col-md-6">
						<label class="form-label" for="personFantasy">Nome fantasia</label>
						<input autocomplete="off" class="form-control" id="personFantasy" name="person[fantasy]" placeholder="Nome fantasia" type="text" />
					</div>

					<div class="col-12 col-md-4">
						<label class="form-label" for="personType">Tipo de pessoa</label>
						<select autocomplete="off" class="form-select" id="personType" name="person[type]" required>
							<option value="" selected disabled>Selecione o tipo...</option>
							<option value="individual">Pessoa física</option>
							<option value="company">Pessoa jurídica</option>
						</select>
						<div class="invalid-feedback">Selecione o tipo.</div>
					</div>

					<div class="col-12 col-md-4">
						<label class="form-label" for="personDocument">Documento (CPF/CNPJ)</label>
						<input autocomplete="off" class="form-control mask-cpf-cnpj" id="personDocument" name="person[document]" placeholder="CPF ou CNPJ" type="text" required />
						<div class="invalid-feedback">Informe o documento.</div>
					</div>

					<div class="col-12 col-md-4">
						<label class="form-label" for="personBirthdate">Data de nascimento</label>
						<input autocomplete="off" class="form-control mask-date" id="personBirthdate" name="person[birthdate]" placeholder="DD/MM/AAAA" type="text" />
					</div>

					<input type="hidden" value="1" name="person[active]">
				</div>
			</section>
			<!-- /Step 1 -->

			<!-- Step 2: Usuário -->
			<section data-step="2" class="d-none">
				<h2 class="h6 mb-3">Usuário</h2>

				<div class="row g-3">
					<div class="col-12 col-md-6">
						<label class="form-label" for="userUsername">Nome de usuário</label>
						<input autocomplete="off" class="form-control" id="userUsername" name="user[username]" placeholder="Nome de usuário" type="text" required />
						<div class="invalid-feedback">Informe o nome de usuário.</div>
					</div>

					<div class="col-12 col-md-6">
						<label class="form-label" for="userType">Tipo de acesso</label>
						<select autocomplete="off" class="form-select" id="userType" name="user[type]" required>
							<option value="" selected disabled>Selecione o tipo de acesso...</option>
							<option value="root">Root</option>
							<option value="admin">Administrador</option>
							<option value="user">Usuário</option>
						</select>
						<div class="invalid-feedback">Selecione o tipo de acesso.</div>
					</div>

					<div class="col-12 col-md-6">
						<label class="form-label" for="userPassword">Senha</label>
						<input autocomplete="off" class="form-control" id="userPassword" name="user[password]" placeholder="Senha" type="password" minlength="8" required />
						<div class="invalid-feedback">A senha deve ter pelo menos 8 caracteres.</div>
					</div>

					<div class="col-12 col-md-6">
						<label class="form-label" for="userPassword2">Confirmar senha</label>
						<input autocomplete="off" class="form-control" id="userPassword2" name="user[password_confirm]" placeholder="Confirmar senha" type="password" minlength="8" required />
						<div class="invalid-feedback">Confirme a senha.</div>
					</div>

					<input type="hidden" value="1" name="user[active]">
				</div>
			</section>
			<!-- /Step 2 -->

			<!-- Step 3: Contatos -->
			<section data-step="3" class="d-none">
				<h2 class="h6 mb-3">Contatos</h2>

				<div class="row g-3">
					<!-- Telefones -->
					<div class="col-12">
						<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
							<div>
								<div class="fw-semibold">Telefones</div>
								<div class="text-body-secondary small">Você pode cadastrar mais de um telefone.</div>
							</div>
							<button type="button" class="btn btn-outline-primary btn-sm" id="btnAddPhone">Adicionar telefone</button>
						</div>

						<div class="mt-2" id="phonesContainer"></div>

						<template id="phoneTemplate">
							<div class="border rounded p-3 mt-2">
								<div class="row g-2 align-items-end">
									<div class="col-12 col-md-4">
										<label class="form-label">Número</label>
										<input class="form-control" name="phones[][number]" type="text" inputmode="numeric" placeholder="(XX) XXXXX-XXXX" />
									</div>
									<div class="col-12 col-md-7">
										<label class="form-label">Observações</label>
										<input class="form-control" name="phones[][notes]" type="text" placeholder="Ex.: celular, comercial, WhatsApp" />
									</div>
									<div class="col-12 col-md-1 d-grid">
										<button type="button" class="btn btn-outline-danger" data-remove="phone">X</button>
									</div>
								</div>
							</div>
						</template>
					</div>

					<!-- E-mails -->
					<div class="col-12 mt-2">
						<div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
							<div>
								<div class="fw-semibold">E-mails</div>
								<div class="text-body-secondary small">Você pode cadastrar mais de um e-mail.</div>
							</div>
							<button type="button" class="btn btn-outline-primary btn-sm" id="btnAddEmail">Adicionar e-mail</button>
						</div>

						<div class="mt-2" id="emailsContainer"></div>

						<template id="emailTemplate">
							<div class="border rounded p-3 mt-2">
								<div class="row g-2 align-items-end">
									<div class="col-12 col-md-5">
										<label class="form-label">Endereço de e-mail</label>
										<input class="form-control" name="emails[][address]" type="email" placeholder="exemplo@email.com" />
										<div class="invalid-feedback">Informe um e-mail válido.</div>
									</div>
									<div class="col-12 col-md-6">
										<label class="form-label">Descrição</label>
										<input class="form-control" name="emails[][description]" type="text" placeholder="Ex.: financeiro, pessoal, comercial" />
									</div>
									<div class="col-12 col-md-1 d-grid">
										<button type="button" class="btn btn-outline-danger" data-remove="email">X</button>
									</div>
								</div>
							</div>
						</template>
					</div>
				</div>
			</section>
			<!-- /Step 3 -->

			<!-- Step 4: Revisão -->
			<section data-step="4" class="d-none">
				<div class="alert alert-info mb-3" role="alert">
					Revise seus dados antes de concluir.
				</div>

				<div class="table-responsive">
					<table class="table table-sm align-middle">
						<thead>
							<tr>
								<th style="width: 30%;">Campo</th>
								<th>Valor</th>
							</tr>
						</thead>
						<tbody id="reviewBody"></tbody>
					</table>
				</div>

				<div class="form-check mt-2">
					<input class="form-check-input" type="checkbox" id="confirmReview" required />
					<label class="form-check-label" for="confirmReview">Confirmo que revisei meus dados</label>
					<div class="invalid-feedback">Você precisa confirmar a revisão.</div>
				</div>
			</section>
			<!-- /Step 4 -->

		</div>

		<div class="card-footer bg-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
			<button type="button" class="btn btn-secondary" id="btnBack" disabled>Voltar</button>

			<div class="d-flex flex-wrap gap-2">
				<button type="button" class="btn btn-primary" id="btnNext">Continuar</button>
				<button type="submit" class="btn btn-success d-none" id="btnSubmit">Concluir</button>
			</div>
		</div>
	</div>
</form>
<script src="<?= url('assets/js/person/create.js?v=2'); ?>"></script>

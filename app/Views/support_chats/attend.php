<link rel="stylesheet" href="<?= url('assets/css/support_chats/attend.css?v=5'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Suporte</li>
  </ol>
</nav>

<section class="support-admin-page" data-bootstrap-url="<?= url('support_chat/bootstrap'); ?>" data-chat-url-base="<?= url('support_chat/show'); ?>" data-open-conversation-url="<?= url('support_chat/openConversation'); ?>" data-reopen-url-base="<?= url('support_chat/reopen'); ?>" data-ws-auth-url="<?= url('support_chat/wsAuth'); ?>" data-initial-chat-id="<?= (int) ($initialChatId ?? 0); ?>">
  <div class="support-admin-sidebar">
    <div class="support-admin-panel support-admin-panel-header">
      <div>
        <h5>Atendimentos</h5>
        <p class="text-muted mb-0">Chats em tempo real por tenant e atendente.</p>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= url('support_chat'); ?>">Listagem</a>
        <button class="btn btn-outline-primary btn-sm" id="supportAdminRefreshButton" type="button">Atualizar</button>
      </div>
    </div>

    <div class="support-admin-panel support-admin-panel-search">
      <label class="form-label small fw-semibold" for="supportAdminTenantSearch">Tenant ou pessoa</label>
      <div class="support-admin-search-control">
        <input id="supportAdminTenantSearch" type="search" class="form-control form-control-sm js-autocomplete" autocomplete="off" placeholder="Tenant, pessoa, e-mail ou usuário" data-endpoint="<?= url('support_chat/tenantUserAutocomplete'); ?>" data-results="#supportAdminTenantResults" data-hidden="#supportAdminCustomerUserId" data-minlen="1" data-param="term" data-autocomplete-portal="body" aria-controls="supportAdminTenantResults">
        <input id="supportAdminCustomerUserId" type="hidden" value="">
        <input id="supportAdminTenantId" type="hidden" value="">
        <button id="supportAdminSearchClearButton" class="btn btn-outline-secondary btn-sm support-admin-search-clear" type="button" aria-label="Limpar pesquisa" title="Limpar pesquisa" hidden>
          <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
      </div>
      <ul id="supportAdminTenantResults" class="list-group support-admin-tenant-results" aria-label="Tenants e pessoas encontrados"></ul>
      <div id="supportAdminSearchSelection" class="support-admin-search-selection" hidden>
        <button id="supportAdminOpenConversationButton" class="btn btn-primary btn-sm" type="button"><i class="bi bi-chat-left-text me-1" aria-hidden="true"></i>Abrir conversa</button>
      </div>
    </div>

    <div class="support-admin-panel support-admin-panel-list">
      <div id="supportAdminChatList" class="support-admin-chat-list"></div>
    </div>
  </div>

  <div class="support-admin-main">
    <div class="support-admin-panel support-admin-chat-panel">
      <header class="support-admin-chat-header">
        <div>
          <h5 id="supportAdminChatTitle" class="mb-1">Selecione um chat</h5>
          <p id="supportAdminChatMeta" class="text-muted mb-0">Nenhum atendimento carregado.</p>
        </div>
        <div class="support-admin-actions">
          <select id="supportAdminAttendantSelect" class="form-select form-select-sm" disabled>
            <option value="">Selecionar atendente</option>
          </select>
          <button class="btn btn-outline-secondary btn-sm" id="supportAdminAssignButton" type="button" disabled>Atribuir</button>
          <button class="btn btn-success btn-sm" id="supportAdminReopenButton" type="button" hidden>
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
            Reabrir
          </button>
          <button class="btn btn-danger btn-sm" id="supportAdminCloseButton" type="button" disabled>Finalizar</button>
        </div>
      </header>

      <div id="supportAdminMessages" class="support-admin-messages">
        <div class="support-admin-empty">
          <p class="text-muted mb-0">Abra um atendimento para acompanhar a conversa.</p>
        </div>
      </div>

      <div class="support-admin-typing-wrap">
        <span id="supportAdminTyping" class="support-admin-typing"></span>
      </div>

      <form id="supportAdminComposer" class="support-admin-composer">
        <textarea id="supportAdminMessageInput" class="form-control" rows="3" placeholder="Digite a resposta para o cliente..." disabled></textarea>
        <div class="support-admin-composer-actions">
          <span id="supportAdminConnectionStatus" class="support-admin-connection text-muted">Conectando...</span>
          <button class="btn btn-primary" id="supportAdminSendButton" type="submit" disabled>Enviar</button>
        </div>
      </form>
    </div>
  </div>
</section>

<div class="modal fade" id="supportAdminDeleteModal" tabindex="-1" aria-labelledby="supportAdminDeleteModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="supportAdminDeleteModalTitle">Confirmar exclusão</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        Tem certeza que deseja excluir o <strong id="supportAdminDeleteRecordName"></strong>?
        <p class="text-muted mb-0 mt-2">Os registros da conversa também serão removidos.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <form method="POST" action="#" id="supportAdminDeleteForm" data-action-base-url="<?= url('support_chat/delete'); ?>" class="d-inline">
          <input type="hidden" name="id" id="supportAdminDeleteRecordId">
          <input type="hidden" name="redirect_to" value="support_chat/attend">
          <button type="submit" class="btn btn-danger">Excluir</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="/assets/autocomplete/autocomplete.js?v=4"></script>
<script src="<?= url('assets/js/support_chats/attend.js?v=6'); ?>"></script>
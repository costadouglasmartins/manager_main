<link rel="stylesheet" href="<?= url('assets/css/support_chats/attend.css?v=1'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item active" aria-current="page">Suporte</li>
  </ol>
</nav>

<section class="support-admin-page" data-bootstrap-url="<?= url('support_chat/bootstrap'); ?>" data-chat-url-base="<?= url('support_chat/show'); ?>" data-ws-auth-url="<?= url('support_chat/wsAuth'); ?>" data-initial-chat-id="<?= (int) ($initialChatId ?? 0); ?>">
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

<script src="<?= url('assets/js/support_chats/attend.js?v=3'); ?>"></script>

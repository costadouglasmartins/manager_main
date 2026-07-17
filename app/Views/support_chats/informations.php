<?php
$chat = $chat ?? [];
$messages = $messages ?? [];
$status = (string) ($chat['status'] ?? '');
$statusLabel = ['pending' => 'Pendente', 'open' => 'Em aberto', 'closed' => 'Finalizado'][$status] ?? $status;
$tenantName = trim((string) ($chat['tenant_name'] ?? ''));
if ($tenantName === '') {
  $tenantName = 'Tenant ' . (string) ($chat['tenant_database_number'] ?? '');
}
$customerName = (string) ($chat['customer_name'] ?? $chat['customer_username'] ?? '');
$attendantName = (string) ($chat['attendant_name'] ?? $chat['attendant_username'] ?? 'Sem atendente');
$isActiveChat = in_array($status, ['pending', 'open'], true);
?>

<link rel="stylesheet" href="<?= url('assets/css/support_chats/informations.css?v=2'); ?>">

<nav aria-label="breadcrumb">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= url('home'); ?>">Início</a></li>
    <li class="breadcrumb-item"><a href="<?= url('support_chat'); ?>">Chats de suporte</a></li>
    <li class="breadcrumb-item active" aria-current="page">Conversa #<?= (int) ($chat['id'] ?? 0); ?></li>
  </ol>
</nav>

<section class="support-chat-record-page app-detail-page" data-events-url="<?= url('support_chat/events/' . (int) ($chat['id'] ?? 0)); ?>" data-active="<?= $isActiveChat ? '1' : '0'; ?>">
  <header class="support-chat-record-header">
    <div>
      <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h4 class="mb-0">Conversa #<?= (int) ($chat['id'] ?? 0); ?></h4>
        <span id="supportChatRecordStatus" class="support-chat-status status-<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
        <span id="supportChatRecordConnection" class="support-chat-record-connection <?= $isActiveChat ? 'is-connecting' : 'is-closed'; ?>">
          <?= $isActiveChat ? 'Conectando atualização...' : 'Histórico finalizado'; ?>
        </span>
      </div>
      <p id="supportChatRecordMeta" class="text-muted mb-0"><?= htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?> · <?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <div class="support-chat-record-actions">
      <a class="btn btn-outline-secondary" href="<?= url('support_chat'); ?>">
        <i class="bi bi-arrow-left me-1"></i> Voltar
      </a>
      <?php if ($status !== 'closed'): ?>
        <a class="btn btn-primary" href="<?= url('support_chat/attend/' . (int) ($chat['id'] ?? 0)); ?>">
          <i class="bi bi-chat-dots me-1"></i> Atender
        </a>
      <?php endif; ?>
    </div>
  </header>

  <dl class="support-chat-record-summary">
    <div>
      <dt>Cliente</dt>
      <dd><?= htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?></dd>
    </div>
    <div>
      <dt>Usuário</dt>
      <dd><?= htmlspecialchars((string) ($chat['customer_username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></dd>
    </div>
    <div>
      <dt>Tenant</dt>
      <dd><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8'); ?></dd>
    </div>
    <div>
      <dt>Atendente</dt>
      <dd id="supportChatRecordAttendant"><?= htmlspecialchars($attendantName, ENT_QUOTES, 'UTF-8'); ?></dd>
    </div>
    <div>
      <dt>Criação</dt>
      <dd><?= htmlspecialchars(maskDateTimeBr((string) ($chat['created'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></dd>
    </div>
    <div>
      <dt>Última mensagem</dt>
      <dd id="supportChatRecordLastMessage"><?= htmlspecialchars(maskDateTimeBr((string) ($chat['last_message_at'] ?? $chat['created'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></dd>
    </div>
      <div id="supportChatRecordClosedAtWrap" <?= empty($chat['closed_at']) ? 'hidden' : ''; ?>>
        <dt>Finalização</dt>
        <dd id="supportChatRecordClosedAt"><?= htmlspecialchars(maskDateTimeBr((string) ($chat['closed_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></dd>
      </div>
  </dl>

  <div class="support-chat-record-chat">
    <div class="support-chat-record-chat-header">
      <div>
        <strong><?= htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8'); ?></strong>
        <span><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8'); ?></span>
      </div>
      <small id="supportChatRecordLiveLabel"><?= $isActiveChat ? 'Ao vivo' : 'Arquivo'; ?></small>
    </div>

    <div class="support-chat-records" id="supportChatRecordMessages">
      <?php if (!empty($messages)): ?>
        <?php foreach ($messages as $message): ?>
          <?php
          $role = (string) ($message['sender_role'] ?? '');
          $roleLabel = $role === 'attendant' ? 'Atendente' : ($role === 'system' ? 'Sistema' : 'Cliente');
          ?>
          <article class="support-chat-record-message is-<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>" data-message-id="<?= (int) ($message['id'] ?? 0); ?>">
            <div class="support-chat-record-bubble">
              <header>
                <strong><?= htmlspecialchars((string) ($message['sender_name'] ?? $roleLabel), ENT_QUOTES, 'UTF-8'); ?></strong>
                <span><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></span>
              </header>
              <p><?= nl2br(htmlspecialchars((string) ($message['message_text'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
              <time><?= htmlspecialchars(maskDateTimeBr((string) ($message['created'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></time>
            </div>
          </article>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="support-chat-record-empty text-center text-muted py-4 mb-0">Nenhuma mensagem registrada para esta conversa.</p>
      <?php endif; ?>
    </div>

    <div class="support-chat-record-typing" id="supportChatRecordTyping" hidden></div>
  </div>
</section>

<script src="<?= url('assets/js/support_chats/informations.js?v=1'); ?>"></script>

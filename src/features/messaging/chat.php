<?php declare(strict_types=1); ?>

<header class="page-header chat-page-header">
  <div><span class="eyebrow">Conecte-se sem ruído</span><h1>Conversas</h1><p class="page-header__lead">Continue de onde parou ou comece uma nova troca.</p></div>
  <button class="btn btn-primary" type="button" data-new-conversation><?= icon('plus') ?> Nova conversa</button>
</header>

<div class="page-content">
  <div class="chat-layout" data-chat-app>
    <section class="conversation-list card" aria-label="Suas conversas">
      <div class="conversation-list__heading"><span class="eyebrow">Mensagens</span><strong>Suas conversas <small data-conversation-count><?= count($people) ?></small></strong></div>
      <label class="chat-search" for="chat-search"><span class="sr-only">Pesquisar conversas</span><span class="icon"><?= icon('search') ?></span><input class="input" id="chat-search" data-filter placeholder="Pesquisar alguém"></label>
      <div class="conversation-list__items">
        <?php foreach ($people as $person): ?>
          <?php $isActive = $person['username'] === 'matheus'; ?>
          <button class="conversation <?= $isActive ? 'active' : '' ?>" type="button" data-conversation="<?= e($person['username']) ?>" data-name="<?= e($person['name']) ?>" data-initials="<?= e(initials($person['name'])) ?>" data-filter-item>
            <div class="avatar"><?= e(initials($person['name'])) ?></div>
            <div class="conversation__copy"><div><strong><?= e($person['name']) ?></strong><time><?= $isActive ? 'agora' : 'ontem' ?></time></div><span><?= $isActive ? 'Perfeito. Se precisar, eu mando o resumo depois.' : 'Última mensagem da conversa' ?></span></div>
            <?php if ($isActive): ?><i class="conversation__unread" aria-label="Conversa ativa"></i><?php endif; ?>
          </button>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="chat-box card" aria-label="Conversa">
      <header class="chat-box__head">
        <div class="avatar" data-chat-avatar>M</div>
        <div class="chat-box__identity"><strong data-chat-name>Matheus</strong><span><i></i> <span data-chat-status>online agora</span></span></div>
        <button class="chat-more" type="button" aria-label="Mais opções" data-chat-options>•••</button>
      </header>
      <div class="chat-messages" data-chat-messages><div class="chat-date"><span>Hoje</span></div>
        <p class="muted">Carregando conversa…</p>
      </div>
      <form class="chat-form" data-chat-form><label class="chat-composer"><span class="sr-only">Escreva uma mensagem</span><input class="input" aria-label="Mensagem" placeholder="Escreva uma mensagem..." required><button type="submit" aria-label="Enviar mensagem"><?= icon('send') ?></button></label></form>
    </section>
  </div>
</div>


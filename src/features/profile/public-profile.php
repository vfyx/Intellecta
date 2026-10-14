<?php declare(strict_types=1); $selectedUsername = (is_string($_GET['user'] ?? null) ? $_GET['user'] : ''); $person = null; foreach ($people as $candidate) { if ($candidate['username'] === $selectedUsername) { $person = $candidate; break; } } if (!$person) { http_response_code(404); echo '<section class="empty-state"><h2>Perfil indisponível</h2><a href="?page=explore">Voltar a explorar</a></section>'; return; } ?>

<header class="page-header">
  <div><span class="eyebrow">Perfil da comunidade</span><h1>Perfil público</h1></div>
  <a class="btn btn-secondary" href="?page=explore">Voltar a explorar</a>
</header>

<div class="page-content">
  <section class="public-profile-hero card" aria-labelledby="public-profile-name">
    <div class="public-profile-hero__identity">
      <div class="avatar avatar--large"><?= e(initials($person['name'])) ?></div>
      <div class="public-profile-hero__copy">
        <span class="eyebrow">Membro desde <?= e(date('Y',strtotime($person['created']))) ?></span>
        <h2 id="public-profile-name"><?= e($person['name']) ?></h2>
        <p class="profile-handle">@<?= e($person['username']) ?></p>
        <p><?= e($person['bio']) ?></p>
      </div>
    </div>
    <div class="public-profile-actions">
      <div class="public-profile-menu-wrap">
        <button class="profile-action-icon" type="button" data-profile-menu aria-expanded="false" aria-haspopup="true" aria-label="Mais opções">•••</button>
        <div class="profile-action-menu" data-profile-menu-panel hidden>
          <a href="#" data-public-info><?= icon('info') ?> Informações da conta</a>
          <button type="button" data-profile-share><?= icon('send') ?> Compartilhar @<?= e($person['username']) ?></button>
          <button type="button" data-profile-block><?= icon('lock') ?> Bloquear @<?= e($person['username']) ?></button>
          <button type="button" data-profile-report><?= icon('flag') ?> Denunciar @<?= e($person['username']) ?></button>
        </div>
      </div>
      <a class="profile-action-icon" href="?page=chat&user=<?= e($person['username']) ?>" aria-label="Conversar com <?= e($person['name']) ?>"><?= icon('message') ?></a>
      <button class="profile-action-icon" type="button" data-profile-notify aria-label="Ativar notificações do perfil"><?= icon('bell') ?></button>
      <button class="btn btn-primary profile-follow" type="button" data-follow>Seguir</button>
    </div>
  </section>

  <div class="profile-metrics">
    <div><strong>38</strong><span>publicações</span></div><div><strong>426</strong><span>seguidores</span></div><div><strong>112</strong><span>seguindo</span></div>
  </div>
  <section class="section-heading"><h2>Publicações recentes</h2><span class="eyebrow">comunidade</span></section>
  <div data-public-posts><?php foreach (array_filter($feed, fn($p) => $p['author'] === $person['id']) as $post): ?>
    <article class="post card profile-public-post"><div class="post__head"><div class="avatar"><?= e(initials($person['name'])) ?></div><div class="post__meta"><strong><?= e($person['name']) ?></strong><span>@<?= e($person['username']) ?> · <?= e($post['time']) ?></span></div></div><p class="post__body"><?= e($post['body']) ?></p></article>
  <?php endforeach; ?></div>
</div>

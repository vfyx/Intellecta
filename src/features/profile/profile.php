<?php declare(strict_types=1); ?>

<header class="page-header profile-page-header">
  <div>
    <span class="eyebrow">Seu espaço no Intellecta</span>
    <h1>Perfil</h1>
  </div>
  <a class="btn btn-secondary" href="?page=editar-perfil"><?= icon('edit') ?> Editar perfil</a>
</header>

<div class="page-content profile-page">
  <section class="profile-card card" aria-labelledby="profile-name">
    <div class="profile-card__cover">
      <span class="profile-card__cover-label">INTELLECTA</span>
      <span class="profile-card__cover-mark">✦</span>
    </div>

    <div class="profile-card__content">
      <div class="profile-card__top">
        <div class="profile-avatar-wrap">
          <div class="avatar avatar--large profile-avatar"><?= e(initials($account['name'])) ?></div>
          <span class="profile-status" aria-label="Online"></span>
        </div>
        <div class="profile-card__actions">
          <a class="btn btn-secondary btn-icon-label" href="?page=sua-conta"><?= icon('settings') ?> Conta</a>
          <button class="btn btn-ghost" type="button" data-profile-share><?= icon('send') ?> Compartilhar</button>
        </div>
      </div>

      <div class="profile-card__identity">
        <div>
          <span class="eyebrow">Perfil público</span>
          <h2 id="profile-name"><?= e($account['name']) ?></h2>
          <p class="profile-handle">@<?= e($account['username']) ?> <span class="profile-verified"><?= icon('check') ?></span></p>
        </div>
        <span class="profile-presence"><i></i> online agora</span>
      </div>

      <p class="profile-bio"><?= e($account['bio'] ?: 'Conte sobre você em Editar perfil.') ?></p>

      <div class="profile-details">
        <span><?= icon('spark') ?> Tecnologia &amp; design</span>
        <span><?= icon('home') ?> Intellecta desde <?= e(date('Y',strtotime($account['created']))) ?></span>
      </div>

      <div class="profile-metrics" aria-label="Resumo do perfil">
        <a href="#profile-posts"><strong><?= count(array_filter($feed, fn($p) => $p['author'] === $account['id'])) ?></strong><span>publicações</span></a>
        <button type="button" data-network="followers"><strong><?= count($GLOBALS['clientState']['data']['followers']) ?></strong><span>seguidores</span></button>
        <button type="button" data-network="following"><strong><?= count($account['following']) ?></strong><span>seguindo</span></button>
      </div>
    </div>
  </section>

  <nav class="profile-tabs" aria-label="Conteúdo do perfil" role="tablist">
    <button class="profile-tab active" type="button" data-profile-tab="posts" aria-selected="true" role="tab">
      <span><?= icon('edit') ?></span> Publicações <b>0</b>
    </button>
    <button class="profile-tab" type="button" data-profile-tab="likes" aria-selected="false" role="tab">
      <span>♡</span> Curtidas <b>0</b>
    </button>
    <button class="profile-tab" type="button" data-profile-tab="reposts" aria-selected="false" role="tab">
      <span>↗</span> Republicados <b>0</b>
    </button>
  </nav>

  <section class="profile-tab-panel active" id="profile-posts" data-profile-panel="posts" aria-label="Publicações" role="tabpanel">
    <div class="section-heading profile-section-heading">
      <div><span class="eyebrow">Sua biblioteca pública</span><h2>O que você compartilhou</h2></div>
      <span class="section-heading__meta">Mais recentes</span>
    </div>

    <div class="list profile-posts"></div>
  </section>

  <section class="profile-tab-panel" data-profile-panel="likes" aria-label="Publicações curtidas" role="tabpanel" hidden>
    <div class="section-heading profile-section-heading"><div><span class="eyebrow">Sua curadoria</span><h2>Ideias que você guardou</h2></div><span class="section-heading__meta">Curtidas</span></div>
    <div class="list"></div>
  </section>

  <section class="profile-tab-panel" data-profile-panel="reposts" aria-label="Publicações republicadas" role="tabpanel" hidden>
    <div class="section-heading profile-section-heading"><div><span class="eyebrow">Sua rede</span><h2>O que você levou adiante</h2></div><span class="section-heading__meta">Republicados</span></div>
    <div class="list"></div>
  </section>
</div>

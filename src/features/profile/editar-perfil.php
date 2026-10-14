<?php

declare(strict_types=1);
?>

<header class="page-header account-page-header">
  <div>
    <span class="eyebrow">Perfil</span>
    <h1>Editar perfil</h1>
  </div>
</header>

<div class="page-content account-profile-layout">
  <section class="account-profile-card" aria-labelledby="profile-edit-title">
    <div class="account-profile-identity">
      <div class="account-avatar-wrap">
        <div class="avatar avatar--large"><?= e(initials($account['name'])) ?></div>
        
      </div>
      <div>
        <div class="account-name-line">
          <h2 id="profile-edit-title"><?= e($account['name']) ?></h2>
        </div>
        <div class="account-handle">@<?= e($account['username']) ?></div>
        <span class="account-class"><?= e($account['class']) ?></span>
      </div>
    </div>

    <div class="account-divider"></div>
    <form class="account-form" method="post" action="?page=editar-perfil" data-profile-form>
      <label class="form-label">Nome<input class="input" name="name" value="<?= e($account['name']) ?>" required maxlength="100"></label>
      <label class="form-label">Usuário<input class="input" name="username" value="<?= e($account['username']) ?>" required pattern="[a-z0-9_]{3,30}"></label>
      <label class="form-label" for="bio">Bio</label>
      <div class="account-field account-field--textarea">
        <textarea class="input" id="bio" name="bio" rows="3" placeholder="Descrição"><?= e($account['bio']) ?></textarea>
        <?= icon('edit') ?>
      </div>

      <label class="form-label" for="birthdate">Data de nascimento</label>
      <input class="input" id="birthdate" type="date" name="birthdate" value="<?= e($account['birthdate']) ?>" data-profile-field>

      <label class="form-label" for="gender">Gênero</label>
      <select class="input" id="gender" name="gender" data-profile-field>
        <option value="">Selecione seu gênero</option>
        <option value="feminino">Feminino</option>
        <option value="masculino">Masculino</option>
        <option value="outro">Outro</option>
      </select>
      <div class="account-form-actions"><a class="btn btn-ghost" href="?page=profile">Cancelar</a><button class="btn btn-primary account-confirm" type="submit">Salvar alterações</button></div>
    </form>
  </section>
</div>

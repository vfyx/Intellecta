'use strict';
(() => {
  const boot = JSON.parse(document.querySelector('#app-state').textContent);
  let state = boot.data, pendingMedia = null, pendingPoll = null, activeChat = null;
  const params = new URLSearchParams(location.search);
  const $ = (s, root = document) => root.querySelector(s);
  const $$ = (s, root = document) => [...root.querySelectorAll(s)];
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const person = id => state.people.find(p => p.id === id);
  const target = () => state.people.find(p => p.username === params.get('user'));
  let toastTimer;
  function notify(text) { const t = $('#toast'); t.textContent = text; t.classList.add('show'); clearTimeout(toastTimer); toastTimer = setTimeout(() => t.classList.remove('show'), 4500); }
  function animate(el) { if (el && state?.account.settings.motion !== false && !matchMedia('(prefers-reduced-motion: reduce)').matches) el.animate([{opacity:.4,transform:'translateY(5px)'},{opacity:1,transform:'translateY(0)'}],{duration:180,easing:'ease-out'}); }
  async function api(action, values = {}) {
    const controller = new AbortController(), timer = setTimeout(() => controller.abort(), 20000);
    try {
      const response = await fetch('?api=1', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':boot.csrf},body:JSON.stringify({action,...values}),signal:controller.signal});
      const result = await response.json();
      if (!response.ok || result.error) throw new Error(result.error || 'Não foi possível concluir.');
      return result;
    } catch (error) { if (error.name === 'AbortError') throw new Error('O servidor demorou a responder. Confira o resultado antes de tentar novamente.'); throw error; }
    finally { clearTimeout(timer); }
  }
  async function refresh() { state = await api('state'); render(); }
  function dialog(title, content) {
    const d = document.createElement('dialog'); d.className = 'card'; d.style.color='var(--text)'; d.style.width='min(560px, calc(100vw - 2rem))'; d.style.maxHeight='85vh'; d.style.overflow='auto';
    d.innerHTML = `<div class="page-content"><h2>${esc(title)}</h2>${content}<div class="account-form-actions"><button type="button" class="btn btn-secondary" data-close>Fechar</button></div></div>`;
    document.body.append(d); $('[data-close]',d).onclick = () => d.close(); d.addEventListener('close',()=>d.remove(),{once:true}); d.showModal(); animate(d); return d;
  }
  function secretDialog(title, secret, next) {
    const d=dialog(title,`<p>Guarde esta chave em um lugar seguro. Ela só é exibida agora.</p><textarea class="input" readonly aria-label="Chave">${esc(secret)}</textarea><p>Você precisará dela para ${title.includes('recuperação')?'recuperar o acesso sem e-mail':'entrar na conta junto com a senha'}.</p>`);
    if(next)d.addEventListener('close',()=>location.assign(next),{once:true});
  }
  async function finishAuth(result) { if(result.recovery) secretDialog('Chave de recuperação',result.recovery,result.redirect); else location.assign(result.redirect); }
  function recovery() {
    dialog('Recuperar acesso',`<p>Use a chave entregue no cadastro. Nenhum e-mail será enviado.</p><form data-recover-form class="form-stack"><label class="form-label">E-mail<input class="input" name="email" type="email" required></label><label class="form-label">Chave de recuperação<input class="input" name="recovery" required autocomplete="off"></label><label class="form-label">Nova senha<input class="input" name="new_password" type="password" minlength="8" required></label><button class="btn btn-primary">Redefinir senha</button></form>`);
  }
  async function share(url) { try { await navigator.clipboard.writeText(url); notify('Link copiado.'); } catch { dialog('Compartilhar',`<label class="form-label">Copie o link<input class="input" readonly value="${esc(url)}"></label>`); } }
  function url(query) { const u=new URL(location.href);u.search=new URLSearchParams(query);u.hash='';return u.href; }
  function postHTML(p) {
    const a=state.account, saved=a.saved.includes(p.id);
    return `<article class="card post" data-filter-item data-post-id="${esc(p.id)}" id="post-${esc(p.id)}"><div class="post__head"><div class="avatar">${esc(p.name[0])}</div><div class="post__meta"><a href="?page=public-profile&user=${encodeURIComponent(p.username)}"><strong>${esc(p.name)}</strong></a><span>@${esc(p.username)} · ${esc(p.time)}</span></div><button class="btn btn-ghost" type="button" data-post-menu aria-label="Opções da publicação">•••</button></div><p class="post__body">${esc(p.body).replace(/\n/g,'<br>')}</p>${p.topic?`<a class="text-link" href="?page=home&topic=${encodeURIComponent(p.topic)}">#${esc(p.topic)}</a>`:''}${p.media?`<img src="${esc(p.media)}" alt="Imagem anexada à publicação" loading="lazy" width="480" style="max-width:100%;height:auto">`:''}${p.poll?`<section class="settings-group"><h3>${esc(p.poll.question)}</h3>${p.poll.options.map((o,i)=>`<button class="btn btn-secondary" type="button" data-vote="${i}" aria-pressed="${p.poll.choice===i}">${p.poll.choice===i?'✓ ':''}${esc(o)} · ${p.poll.counts[i]} voto(s)</button>`).join('')}</section>`:''}<div class="post__footer"><button class="btn btn-ghost ${p.isLiked?'liked':''}" type="button" data-like aria-label="Curtir" aria-pressed="${p.isLiked}">♡ <span data-count>${p.likes}</span></button><button class="btn btn-ghost" type="button" data-comments aria-label="Comentários">${boot.icons.message} ${p.comments}</button><button class="btn btn-ghost ${saved?'saved':''}" type="button" data-save aria-label="Salvar publicação" aria-pressed="${saved}">${boot.icons.bookmark}</button><span class="post__stats">${esc(p.time)}</span></div></article>`;
  }
  function empty(text) { return `<section class="empty-state"><p>${esc(text)}</p></section>`; }
  function postsHTML(posts) {return posts.length ? posts.map(postHTML).join('') : empty('Nenhuma publicação por aqui ainda.');}
  function renderPosts() {
    const a=state.account;
    if($('.home-feed')) {
      let feed=state.feed.filter(p=>!a.muted.includes(p.id));
      if(params.get('topic'))feed=feed.filter(p=>p.topic===params.get('topic'));
      if(params.get('post'))feed=state.feed.filter(p=>p.id===params.get('post'));
      if(a.settings['personalized-feed'])feed.sort((x,y)=>Number(a.following.includes(y.author))-Number(a.following.includes(x.author)));
      $('.home-feed').innerHTML=postsHTML(feed);
    }
    if(boot.page==='saved')$('.page-content').innerHTML=`<div class="list">${postsHTML(state.feed.filter(p=>a.saved.includes(p.id)))}</div>`;
    if(boot.page==='profile') {
      const groups={posts:state.feed.filter(p=>p.author===a.id),likes:state.feed.filter(p=>p.isLiked),reposts:state.feed.filter(p=>a.reposts.includes(p.id))};
      for(const [key,posts] of Object.entries(groups)) {const panel=$(`[data-profile-panel="${key}"]`);$('.list',panel).innerHTML=postsHTML(posts);$(`[data-profile-tab="${key}"] b`).textContent=posts.length;}
      $('.profile-metrics a strong').textContent=groups.posts.length;
      $('[data-network="followers"] strong').textContent=person(a.id)?.followers||0;
      $('[data-network="following"] strong').textContent=a.following.length;
      $('.profile-presence').hidden=!a.settings['activity-status'];
    }
    if(boot.page==='public-profile' && target()) {
      const p=target(); $('[data-public-posts]').innerHTML=postsHTML(state.feed.filter(x=>x.author===p.id));
      const metrics=$$('.profile-metrics strong');metrics[0].textContent=state.feed.filter(x=>x.author===p.id).length;metrics[1].textContent=p.followers;metrics[2].textContent=p.following;
      const follow=$('[data-follow]'); follow.textContent=a.following.includes(p.id)?'Seguindo':'Seguir';follow.classList.toggle('is-following',a.following.includes(p.id));follow.setAttribute('aria-pressed',String(a.following.includes(p.id)));follow.disabled=p.id===a.id;
      $('[data-profile-notify]').classList.toggle('is-active',a.notify.includes(p.id));$('[data-profile-notify]').setAttribute('aria-pressed',String(a.notify.includes(p.id)));
    }
  }
  function renderNotifications() {
    const unread=state.notifications.filter(n=>!n.read).length;
    $$('[data-unread-count]').forEach(el=>{el.textContent=unread;el.hidden=!unread;});
    if(!$('.notification-list'))return;
    $('.notification-summary strong').textContent=unread?'Você tem novidades.':'Você está em dia.';
    $('.notification-summary div > span').textContent=`${unread} notificação(ões) não lida(s).`;
    $('.notification-list').innerHTML=state.notifications.map(n=>`<a class="person notification-item" href="${esc(n.url)}" data-filter-item><div class="avatar">${esc(n.actor[0])}</div><div class="person__copy"><strong>${esc(n.actor)}</strong><span>${esc(n.type)}</span><span>${esc(n.time)}</span></div>${!n.read?'<span class="notification-dot" aria-label="Não lida"></span>':''}</a>`).join('')||empty('Nenhuma notificação por enquanto.');
  }
  function renderChat() {
    if(!$('[data-chat-app]'))return;
    const contacts=state.people.filter(p=>p.id!==state.account.id);
    if(!activeChat)activeChat=target()?.id||contacts[0]?.id;
    if(!contacts.some(p=>p.id===activeChat))activeChat=contacts[0]?.id;
    $('.conversation-list__items').innerHTML=contacts.map(p=>{const messages=state.messages.filter(m=>m.from===p.id||m.to===p.id);return `<button type="button" class="conversation ${activeChat===p.id?'active':''}" data-conversation="${esc(p.id)}" data-filter-item><div class="avatar">${esc(p.name[0])}</div><div class="conversation__copy"><div><strong>${esc(p.name)}</strong><time>${esc(messages.at(-1)?.time||'')}</time></div><span>${esc(messages.at(-1)?.text||(p.demo?'Perfil de exemplo · sem respostas':'Iniciar conversa'))}</span></div></button>`;}).join('');
    $('[data-conversation-count]').textContent=contacts.length;
    const p=person(activeChat), box=$('[data-chat-messages]');
    $('[data-chat-name]').textContent=p?.name||'Escolha uma pessoa';$('[data-chat-avatar]').textContent=p?.name[0]||'?';$('[data-chat-status]').textContent=p?.demo?'Perfil de exemplo':p?.online?'Ativo recentemente':'Status indisponível';$('[data-chat-form] input').disabled=!p;
    const nearBottom=box.scrollHeight-box.scrollTop-box.clientHeight<60;
    const messages=state.messages.filter(m=>(m.from===activeChat||m.to===activeChat)&&!(state.account.chatHidden?.[activeChat]||[]).includes(m.id));
    const html=messages.map(m=>{const mine=m.from===state.account.id;return `<div class="message-row ${mine?'message-row--mine':''}"><div class="message ${mine?'message--mine':''}"><p>${esc(m.text)}</p><small>${esc(m.time)}</small></div></div>`;}).join('')||empty('Envie a primeira mensagem.');
    if(box.innerHTML!==html){box.innerHTML=html;if(nearBottom)box.scrollTop=box.scrollHeight;}
  }
  function applySettings() {
    const settings=state.account.settings;$$('[data-toggle-setting]').forEach(c=>{c.checked=settings[c.dataset.toggleSetting];});
    // Original CSS files stay unchanged; these runtime overrides implement explicit preferences.
    let style=$('#runtime-preferences');if(!style){style=document.createElement('style');style.id='runtime-preferences';document.head.append(style);}
    style.textContent=(!settings.motion||matchMedia('(prefers-reduced-motion: reduce)').matches?'*,*::before,*::after{animation:none!important;transition:none!important;scroll-behavior:auto!important}':'')+(settings['compact-feed']?'.home-feed .post{padding:14px}.home-feed{gap:10px}':'');
  }
  function render() {if(!state)return;renderPosts();renderNotifications();renderChat();applySettings();$$('[data-filter]').filter(x=>x.value).forEach(filter);}
  function filter(input) {const q=input.value.trim().toLocaleLowerCase('pt-BR');let root=input.id==='chat-search'?$('.conversation-list'):input.id==='people-search'?$('.people-list'):$('.main-column');if(!root)return;$$('[data-filter-item]',root).forEach(item=>item.hidden=!!q&&!item.textContent.toLocaleLowerCase('pt-BR').includes(q));}
  async function click(button) {
    if(button.matches('[data-demo-login]'))return finishAuth(await api('demo'));
    if(button.matches('[data-provider]')){dialog(`Entrar com ${button.dataset.provider}`,`<p>Este acesso depende de credenciais OAuth do responsável pela instalação e não está configurado. Você já pode usar sua conta local.</p><a class="btn btn-primary" href="?page=register">Criar conta local</a>`);return;}
    if(button.matches('[data-password-recovery]'))return recovery();
    if(button.matches('[data-close]'))return;
    if(button.matches('[data-password-toggle]')){const input=$('input',button.parentElement);input.type=input.type==='password'?'text':'password';button.setAttribute('aria-label',input.type==='password'?'Mostrar senha':'Ocultar senha');return;}
    if(button.matches('[data-help-contact]'))return dialog('Contato da instalação','<p>Este projeto não possui uma equipe de suporte externa conectada. Procure o responsável pelo servidor ou consulte o README incluído no projeto.</p>');
    if(button.matches('[data-ai-toggle],[data-ai-backdrop]')){const panel=$('#intellia-panel'),open=button.matches('[data-ai-backdrop]')?false:panel.hidden;panel.hidden=!open;panel.setAttribute('aria-hidden',String(!open));$('[data-ai-backdrop]').hidden=!open;$$('[data-ai-toggle]').forEach(b=>b.setAttribute('aria-expanded',String(open)));document.body.classList.toggle('ai-is-open',open);if(open){animate(panel);$('#ai-question').focus();}return;}
    if(!state){notify('Entre na sua conta para usar este recurso.');return;}
    if(button.matches('[data-media]')){
      const input=document.createElement('input');input.type='file';input.accept='image/png,image/jpeg,image/gif,image/webp';
      input.onchange=async()=>{const file=input.files[0];if(!file)return;if(file.size>2097152){notify('Selecione uma imagem de até 2 MB.');return;}const reader=new FileReader();reader.onload=()=>{pendingMedia=reader.result;attachments();};reader.onerror=()=>notify('Não foi possível ler a imagem.');reader.readAsDataURL(file);};input.click();return;
    }
    if(button.matches('[data-remove-media]')){pendingMedia=null;attachments();return;}
    if(button.matches('[data-remove-poll]')){pendingPoll=null;attachments();return;}
    if(button.matches('[data-poll]')){dialog('Criar enquete','<form class="form-stack" data-poll-form><label class="form-label">Pergunta<input class="input" name="question" maxlength="300" required></label><label class="form-label">Opções (uma por linha, de 2 a 6)<textarea class="input" name="options" rows="6" required></textarea></label><button class="btn btn-primary">Anexar enquete</button></form>');return;}
    const card=button.closest('[data-post-id]'),id=card?.dataset.postId;
    if(card){
      if(button.matches('[data-like],[data-save],[data-vote],[data-repost],[data-mute],[data-delete-post]')) {
        const action=button.hasAttribute('data-like')?'like':button.hasAttribute('data-save')?'save':button.hasAttribute('data-vote')?'vote':button.hasAttribute('data-repost')?'repost':button.hasAttribute('data-mute')?'mute':'delete-post';
        if(action==='delete-post'&&!confirm('Excluir esta publicação?'))return;
        await api(action,{id,option:Number(button.dataset.vote)});button.closest('dialog')?.close();await refresh();animate($(`[data-post-id="${id}"]`));return;
      }
      if(button.matches('[data-share-post]'))return share(url({page:'home',post:id}));
      if(button.matches('[data-comments]')){const p=state.feed.find(x=>x.id===id);dialog('Comentários',`<div data-comments-list>${p.replies.map(r=>`<article class="post"><strong>${esc(r.name)}</strong><p>${esc(r.body)}</p><small>${esc(r.time)}</small></article>`).join('')||'<p>Seja a primeira pessoa a comentar.</p>'}</div><form class="form-stack" data-comment-form data-id="${esc(id)}"><label class="form-label">Comentário<textarea class="input" name="body" maxlength="2000" required></textarea></label><button class="btn btn-primary">Comentar</button></form>`);return;}
      if(button.matches('[data-post-menu]')){const p=state.feed.find(x=>x.id===id);dialog('Opções da publicação',`<div class="form-stack" data-post-id="${esc(id)}"><button class="btn btn-secondary" data-save>${state.account.saved.includes(id)?'Remover dos salvos':'Salvar'}</button><button class="btn btn-secondary" data-share-post>Copiar link</button><button class="btn btn-secondary" data-repost>${state.account.reposts.includes(id)?'Desfazer republicação':'Republicar no perfil'}</button><button class="btn btn-secondary" data-mute>Ocultar do início</button>${p.author===state.account.id?'<button class="btn btn-secondary" data-delete-post>Excluir publicação</button>':''}</div>`);return;}
    }
    if(button.matches('[data-profile-tab]')){const selected=button.dataset.profileTab;$$('[data-profile-tab]').forEach(t=>{t.classList.toggle('active',t===button);t.setAttribute('aria-selected',String(t===button));});$$('[data-profile-panel]').forEach(p=>{p.hidden=p.dataset.profilePanel!==selected;p.classList.toggle('active',!p.hidden);if(!p.hidden)animate(p);});return;}
    if(button.matches('[data-network]')){const following=button.dataset.network==='following';const contacts=following?state.people.filter(p=>state.account.following.includes(p.id)):(state.followers||[]);dialog(following?'Seguindo':'Seguidores',contacts.map(p=>`<p><a href="?page=public-profile&user=${encodeURIComponent(p.username)}">${esc(p.name)} · @${esc(p.username)}</a></p>`).join('')||'<p>Nenhum perfil por aqui ainda.</p>');return;}
    if(button.matches('[data-profile-menu]')){const panel=$('[data-profile-menu-panel]');panel.hidden=!panel.hidden;button.setAttribute('aria-expanded',String(!panel.hidden));animate(panel);return;}
    if(button.matches('[data-profile-share]'))return share(url({page:'public-profile',user:target()?.username||state.account.username}));
    if(button.matches('[data-public-info]'))return dialog('Informações públicas',`<p>${esc(target()?.name)}</p><p>@${esc(target()?.username)}</p><p>${esc(target()?.bio)}</p><p>Membro desde ${esc(target()?.created?.slice(0,10))}</p>`);
    if(button.matches('[data-follow],[data-profile-block],[data-profile-notify],[data-profile-report]')){
      const action=button.hasAttribute('data-follow')?'follow':button.hasAttribute('data-profile-block')?'block':button.hasAttribute('data-profile-notify')?'notify':'report';
      const p=target();if(!p)throw new Error('Perfil indisponível.');let reason;
      if(action==='report'){reason=prompt('Descreva o motivo da denúncia:');if(!reason?.trim())return;}
      if(action==='block'&&!confirm('Bloquear este perfil? Vocês não poderão trocar mensagens nem ver publicações um do outro.'))return;
      const result=await api(action,{target:p.id,reason});notify(result.message);if(action==='block')location.assign('?page=explore');else await refresh();return;
    }
    if(button.matches('[data-unblock]')){await api('block',{target:button.dataset.unblock});button.closest('p').remove();await refresh();return;}
    if(button.matches('[data-mark-read]')){await api('read');await refresh();notify('Notificações marcadas como lidas.');return;}
    if(button.matches('[data-conversation]')){activeChat=button.dataset.conversation;renderChat();$('[data-chat-messages]').scrollTop=$('[data-chat-messages]').scrollHeight;$('[data-chat-form] input').focus();return;}
    if(button.matches('[data-new-conversation]')){dialog('Nova conversa',`<input class="input" placeholder="Buscar nome ou usuário" data-contact-search><div>${state.people.filter(p=>p.id!==state.account.id).map(p=>`<p data-contact><button class="btn btn-secondary" data-open-chat="${esc(p.id)}">${esc(p.name)} · @${esc(p.username)}</button></p>`).join('')}</div>`);return;}
    if(button.matches('[data-open-chat]')){activeChat=button.dataset.openChat;button.closest('dialog').close();renderChat();$('[data-chat-form] input').focus();return;}
    if(button.matches('[data-chat-options]')){if(activeChat)dialog('Opções da conversa','<p>Limpar oculta o histórico apenas para sua conta.</p><button class="btn btn-secondary" data-clear-chat>Limpar meu histórico</button>');return;}
    if(button.matches('[data-clear-chat]')){await api('clear-chat',{target:activeChat});button.closest('dialog').close();await refresh();return;}
    if(button.matches('[data-logout]')){if(confirm('Sair da conta?'))location.assign((await api('logout')).redirect);return;}
    if(button.matches('[data-delete-account]')){if(!confirm('Excluir sua conta, publicações e mensagens? Esta ação não pode ser desfeita.'))return;const password=state.account.demo?'':prompt('Confirme sua senha para excluir a conta:');if(password===null)return;location.assign((await api('delete-account',{current_password:password})).redirect);return;}
    if(button.matches('[data-ai-prompt]')){$('#ai-question').value=button.dataset.aiPrompt;$('[data-ai-form]').requestSubmit();return;}
  }
  function attachments() {const box=$('[data-attachments]');box.innerHTML=(pendingMedia?`<p>Imagem anexada <button type="button" class="btn btn-ghost" data-remove-media>Remover</button></p><img src="${esc(pendingMedia)}" width="160" style="max-width:100%;height:auto" alt="Prévia do anexo">`:'')+(pendingPoll?`<p>Enquete: ${esc(pendingPoll.question)} <button type="button" class="btn btn-ghost" data-remove-poll>Remover</button></p>`:'');animate(box);}
  document.addEventListener('click',async event=>{
    const button=event.target.closest('button,a[data-password-recovery],a[data-demo-login],a[data-public-info],a[data-help-contact],[data-ai-backdrop]');if(!button)return;
    if(button.type==='submit'&&button.form)return;
    event.preventDefault();if(button.dataset.busy)return;button.dataset.busy='1';
    try{await click(button);}catch(error){notify(error.message);}finally{delete button.dataset.busy;}
  });
  document.addEventListener('submit',async event=>{
    const form=event.target;event.preventDefault();if(form.dataset.busy)return;form.dataset.busy='1';
    const submit=$('[type="submit"],button:not([type])',form);if(submit){submit.disabled=true;submit.classList.add('is-submitting');}
    try{
      const values=Object.fromEntries(new FormData(form));
      if(form.matches('[data-auth-form]'))await finishAuth(await api(form.dataset.authForm,{...values,terms:!!values.terms}));
      else if(form.matches('[data-recover-form]')){const result=await api('recover',values);form.closest('dialog').close();await finishAuth(result);}
      else if(form.matches('[data-profile-form]')){await api('profile',values);location.assign('?page=profile');}
      else if(form.matches('[data-password-form]')){notify((await api('password',values)).message);form.reset();}
      else if(form.matches('[data-post-form]')){await api('post',{body:$('textarea',form).value,topic:values.topic,media:pendingMedia,poll:pendingPoll});form.reset();pendingMedia=pendingPoll=null;attachments();await refresh();animate($('.home-feed article'));notify('Publicação criada.');}
      else if(form.matches('[data-poll-form]')){const options=values.options.split('\n').map(x=>x.trim()).filter(Boolean);if(options.length<2||options.length>6||new Set(options).size!==options.length)throw new Error('Informe de 2 a 6 opções diferentes.');pendingPoll={question:values.question,options};form.closest('dialog').close();attachments();}
      else if(form.matches('[data-comment-form]')){await api('comment',{id:form.dataset.id,body:values.body});form.closest('dialog').close();await refresh();notify('Comentário adicionado.');}
      else if(form.matches('[data-chat-form]')){await api('message',{target:activeChat,body:$('input',form).value});form.reset();await refresh();$('[data-chat-messages]').scrollTop=$('[data-chat-messages]').scrollHeight;}
      else if(form.matches('[data-ai-form]')){
        const input=$('textarea',form),question=input.value.trim();if(!question)return;
        const messages=$('[data-ai-messages]');const add=(text,type)=>{const el=document.createElement('div');el.className=`ai-message ai-message--${type}`;const p=document.createElement('p');p.textContent=text;el.append(p);messages.append(el);messages.scrollTop=messages.scrollHeight;animate(el);};
        add(question,'user');input.disabled=true;$('.ai-card').classList.add('ai-card--thinking');
        try{const answer=await api('assistant',{question});add(answer.answer,'bot');input.value='';}finally{input.disabled=false;$('.ai-card').classList.remove('ai-card--thinking');input.focus();}
      }
    }catch(error){notify(error.message);}
    finally{delete form.dataset.busy;if(submit){submit.disabled=false;submit.classList.remove('is-submitting');}}
  });
  document.addEventListener('input',e=>{const input=e.target;if(input.matches('[data-filter]'))filter(input);if(input.matches('[data-help-search]'))$$('.faq-item').forEach(item=>item.hidden=!item.textContent.toLocaleLowerCase().includes(input.value.toLocaleLowerCase()));if(input.matches('[data-contact-search]'))$$('[data-contact]',input.closest('dialog')).forEach(p=>p.hidden=!p.textContent.toLocaleLowerCase().includes(input.value.toLocaleLowerCase()));});
  document.addEventListener('change',async e=>{const input=e.target;if(!input.matches('[data-toggle-setting]'))return;const value=input.checked;input.disabled=true;try{let password='';if(input.dataset.toggleSetting==='two-factor'&&!value){password=prompt('Senha atual para desativar a chave adicional:');if(password===null){input.checked=!value;return;}}const result=await api('setting',{key:input.dataset.toggleSetting,value,current_password:password});await refresh();if(result.security_key)secretDialog('Chave adicional de acesso',result.security_key);else notify(result.message);}catch(error){input.checked=!value;notify(error.message);}finally{input.disabled=false;}});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&$('#intellia-panel')&&!$('#intellia-panel').hidden)$('[data-ai-backdrop]').click();if(e.target.matches('#ai-question')&&e.key==='Enter'&&!e.shiftKey){e.preventDefault();e.target.form.requestSubmit();}if(e.target.matches('[data-profile-tab]')&&['ArrowLeft','ArrowRight'].includes(e.key)){e.preventDefault();const tabs=$$('[data-profile-tab]'),i=tabs.indexOf(e.target),next=tabs[(i+(e.key==='ArrowRight'?1:tabs.length-1))%tabs.length];next.focus();next.click();}});
  if(state){
    render();if($('#gender'))$('#gender').value=state.account.gender;
    if(boot.page==='privacidade'&&state.account.blocked.length){const section=document.createElement('section');section.className='settings-group card';section.innerHTML='<h2>Perfis bloqueados</h2>'+state.account.blocked.map(id=>`<p>Perfil bloqueado <button class="btn btn-secondary" data-unblock="${esc(id)}">Desbloquear</button></p>`).join('');$('.page-content').append(section);}
    if($('[data-ai-messages]'))$('[data-ai-messages]').innerHTML='<div class="ai-message ai-message--bot"><p>Sou a Intellia local. Posso montar um roteiro de estudos ou extrair um resumo de um texto, sem IA externa.</p></div>';
    if(boot.page==='chat'||boot.page==='notifications'){let polling=false;setInterval(async()=>{if(document.hidden||polling||document.querySelector('[data-busy]'))return;polling=true;try{await refresh();}catch{}finally{polling=false;}},5000);}
  }
})();

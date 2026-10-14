import assert from 'node:assert/strict';
import http from 'node:http';
// Consume every response so the suite also works with PHP's short-lived HTTP connections.
async function fetch(address,options={}) {
  return new Promise((resolve,reject)=>{const req=http.request(address,{method:options.method||'GET',headers:options.headers},res=>{const chunks=[];res.on('data',c=>chunks.push(c));res.on('end',()=>resolve({status:res.statusCode,headers:{get:key=>Array.isArray(res.headers[key])?res.headers[key][0]:res.headers[key]},text:async()=>Buffer.concat(chunks).toString('utf8')}));});req.on('error',reject);req.end(options.body);});
}
const base=process.env.TEST_URL||'http://127.0.0.1:8765';
let checks=0;
function check(value,message){assert.ok(value,message);checks++;}
class Client {
  cookie='';csrf='';
  async request(path='',options={}){const res=await fetch(base+'/'+path,{...options,headers:{Cookie:this.cookie,...options.headers},redirect:'manual'});const cookie=res.headers.get('set-cookie');if(cookie)this.cookie=cookie.split(';')[0];return res;}
  async init(){const r=await this.request('?page=login');const text=await r.text();this.csrf=JSON.parse(text.match(/id="app-state">([\s\S]*?)<\/script>/)[1]).csrf;}
  async api(action,values={},status=200){const r=await this.request('?api=1',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':this.csrf},body:JSON.stringify({action,...values})});const text=await r.text();assert.equal(r.status,status,`${action}: ${text}`);checks++;return JSON.parse(text);}
}
const a=new Client(),b=new Client(),anon=new Client();await Promise.all([a.init(),b.init(),anon.init()]);
await anon.api('state',{},401);
let r=await anon.request('?api=1',{method:'POST',headers:{'Content-Type':'application/json'},body:'{"action":"demo"}'});check(r.status===403,'CSRF rejected');
for(const path of ['storage/state.json','src/app/backend.php','../storage/state.json']){r=await anon.request(path);check(r.status===404,'Private files inaccessible');}
const suffix=Date.now().toString(36),password='SenhaSegura123!';
const emailA=`a${suffix}@example.test`,emailB=`b${suffix}@example.test`;
const registration=await a.api('register',{name:'Pessoa A',username:'a'+suffix,email:emailA,password,terms:true});check(registration.recovery.length===24,'Recovery key generated');
await b.api('register',{name:'Pessoa B',username:'b'+suffix,email:emailB,password,terms:true});
let sa=await a.api('state'),sb=await b.api('state');const aid=sa.account.id,bid=sb.account.id;
await anon.api('register',{name:'Duplicado',username:'a'+suffix,email:emailA,password,terms:true},422);
await a.api('post',{body:' '},422);
await a.api('post',{body:'Imagem inválida',media:'data:image/png;base64,YWJj'},422);
const p=await a.api('post',{body:'Teste <script>alert(1)</script>',topic:'Tecnologia',poll:{question:'Funciona?',options:['Sim','Não']}});
await b.api('like',{id:p.id});await b.api('save',{id:p.id});await b.api('comment',{id:p.id,body:'Comentário persistente'});await b.api('vote',{id:p.id,option:0});await b.api('vote',{id:p.id,option:1});await b.api('repost',{id:p.id});
await b.api('delete-post',{id:p.id},403);
sb=await b.api('state');let post=sb.feed.find(x=>x.id===p.id);check(post.likes===1,'One like');check(post.replies[0].body==='Comentário persistente','Comment stored');check(post.poll.counts.join(',')==='0,1','Vote replaced');check(sb.account.saved.includes(p.id)&&sb.account.reposts.includes(p.id),'Save and repost stored');
await b.api('follow',{target:aid});await b.api('notify',{target:aid});await a.api('post',{body:'Aviso aos seguidores'});
sa=await a.api('state');check(sa.followers.some(x=>x.id===bid),'Follower list');check(sa.notifications.length>=3,'Notifications created');
await a.api('read');check((await a.api('state')).notifications.every(n=>n.read),'Read stored');
await a.api('message',{target:bid,body:'Mensagem real'});sb=await b.api('state');check(sb.messages.some(m=>m.text==='Mensagem real'),'Recipient receives message');
check(!(await a.api('state')).account.saved.includes(p.id),'Private collection isolated');
await b.api('clear-chat',{target:aid});check((await b.api('state')).account.chatHidden[aid].length===1,'Clear only recipient history');
await b.api('block',{target:aid});await a.api('message',{target:bid,body:'Bloqueada'},403);check(!(await b.api('state')).feed.some(x=>x.id===p.id),'Blocked content hidden');await b.api('block',{target:aid});
await a.api('profile',{name:'Pessoa Atualizada',username:'a'+suffix,bio:'Nova bio',birthdate:'2000-02-29',gender:'outro'});
await a.api('profile',{name:'X',username:'a'+suffix,bio:'',birthdate:'2000-02-31',gender:''},422);
await a.api('setting',{key:'profile-public',value:false});check(!(await b.api('state')).feed.some(x=>x.id===p.id),'Private profile posts protected');await a.api('setting',{key:'profile-public',value:true});
await a.api('setting',{key:'motion',value:false});await a.api('logout');await a.init();await a.api('state',{},401);await a.init();await a.api('login',{email:emailA,password:'incorrect'},401);await a.api('login',{email:emailA,password});
sa=await a.api('state');check(sa.account.name==='Pessoa Atualizada'&&!sa.account.settings.motion,'Profile and preferences persist after login');
await a.api('password',{current_password:'invalid',new_password:'NovaSenha123!',confirm_password:'NovaSenha123!'},422);
await a.api('password',{current_password:password,new_password:'NovaSenha123!',confirm_password:'NovaSenha123!'});
const key=(await a.api('setting',{key:'two-factor',value:true})).security_key;
await a.api('logout');await a.init();await a.api('login',{email:emailA,password:'NovaSenha123!'},401);await a.api('login',{email:emailA,password:'NovaSenha123!',security_key:key});
await a.api('logout');await a.init();await a.api('recover',{email:emailA,recovery:'invalid',new_password:password},401);await a.api('recover',{email:emailA,recovery:registration.recovery,new_password:password});
check(!(await a.api('state')).account.settings['two-factor'],'Recovery resets lost second key');
const pages=['home','explore','notifications','chat','profile','saved','topics','editar-perfil','sua-conta','informacoes-conta','configuracao-senha','alterar-senha','preferencias','privacidade','seguranca','ajuda','termos','privacidade-legal','public-profile&user=enzo'];
for(const page of pages){const r=await a.request('?page='+page);const text=await r.text();check(r.status===200&&!/Warning:|Fatal error:|Deprecated:/.test(text),'Page '+page);}
for(const asset of ['assets/css/styles.css','assets/css/app.css','assets/js/app.js','assets/images/intellecta_icon_branco_vetor.svg']){check((await a.request(asset)).status===200,'Asset '+asset);}
check((await a.request('?page=missing')).status===404,'Unknown page 404');
check((await a.api('assistant',{question:'Resumir: Primeiro ponto. Segundo ponto. Terceiro ponto. Quarto ponto.'})).answer.includes('Resumo por extração'),'Local assistant');
await a.api('delete-account',{current_password:password});await b.api('delete-account',{current_password:password});
await a.init();await a.api('login',{email:emailA,password},401);
console.log(`${checks} verificações passaram.`);


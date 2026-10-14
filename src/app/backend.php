<?php
declare(strict_types=1);

// All reads and writes share a lock. The data file is replaced only after a complete write.
function storage(callable $operation): mixed {
    $dir = getenv('INTELLECTA_STORAGE') ?: dirname(__DIR__, 2) . '/storage';
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) throw new RuntimeException('Não foi possível criar o armazenamento.');
    $lock = fopen($dir . '/state.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Armazenamento ocupado.');
    try {
        $file = $dir . '/state.json';
        $db = is_file($file) ? json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) : seed_store();
        $before = json_encode($db, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $result = $operation($db);
        $after = json_encode($db, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (!is_file($file) || $before !== $after) {
            $tmp = $dir . '/state.tmp';
            if (file_put_contents($tmp, $after) !== strlen($after) || !rename($tmp, $file)) throw new RuntimeException('Não foi possível salvar os dados.');
        }
        return $result;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
function defaults(): array {
    return ['motion'=>true,'compact-feed'=>false,'email-updates'=>false,'profile-public'=>true,'activity-status'=>true,'personalized-feed'=>true,'two-factor'=>false,'login-alerts'=>true];
}
function seed_store(): array {
    $seed = require __DIR__ . '/../data/data.php';
    $db = ['users'=>[], 'posts'=>[], 'messages'=>[], 'notifications'=>[], 'reports'=>[], 'attempts'=>[]];
    $people = array_merge($seed['people'], [['username'=>'maria','name'=>'Maria Souza','bio'=>'Biologia e ciência.'],['username'=>'lucas','name'=>'Lucas Alves','bio'=>'Matemática e estudos.']]);
    foreach ($people as $p) $db['users'][$p['username']] = array_merge(['id'=>$p['username'],'email'=>'','password'=>'','demo'=>true,'class'=>'','birthdate'=>'','birthdate_label'=>'','gender'=>'','country'=>'Brasil','phone'=>'','settings'=>defaults(),'following'=>[],'blocked'=>[],'notify'=>[],'saved'=>[],'muted'=>[],'reposts'=>[],'version'=>1,'created'=>date('c')], $p);
    foreach ($seed['feed'] as $i=>$p) $db['posts']['seed-'.$i] = array_merge($p, ['id'=>'seed-'.$i, 'author'=>$p['username'],'created'=>date('c'),'liked'=>[],'replies'=>[],'baseLikes'=>$p['likes'],'baseComments'=>0,'media'=>null,'poll'=>null,'topic'=>$i ? 'Matemática' : 'Ciência']);
    return $db;
}
function fail(string $message, int $status = 422): never { throw new DomainException($message, $status); }
function field(array $input, string $key, int $max = 500, bool $required = true): string {
    if (isset($input[$key]) && !is_string($input[$key])) fail('Campo inválido: '.$key);
    $v = trim($input[$key] ?? '');
    if (($required && $v === '') || strlen($v) > $max) fail('Confira o campo '.$key.'.');
    return $v;
}
function public_user(array $u): array {
    return array_intersect_key($u, array_flip(['id','name','username','bio','class','birthdate','birthdate_label','gender','country','phone','email','settings','following','blocked','notify','saved','muted','reposts','demo','created','chatHidden']));
}
function current_id(array $db): ?string {
    $id = $_SESSION['uid'] ?? null;
    return $id && isset($db['users'][$id]) && ($_SESSION['version'] ?? 0) === $db['users'][$id]['version'] ? $id : null;
}
function login_user(array $u): void { session_regenerate_id(true); $_SESSION['uid']=$u['id']; $_SESSION['version']=$u['version']; }
function visible_user(array $db, string $viewer, string $target): bool {
    return isset($db['users'][$target]) && !in_array($target, $db['users'][$viewer]['blocked'], true) && !in_array($viewer, $db['users'][$target]['blocked'], true);
}
function visible_post(array $db, string $uid, array $p): bool {
    return visible_user($db,$uid,$p['author']) && ($p['author']===$uid || $db['users'][$p['author']]['settings']['profile-public']);
}
function notification(array &$db, string $to, string $actor, string $type, string $url): void {
    if ($to===$actor || !isset($db['users'][$to])) return;
    $db['notifications'][]=['id'=>bin2hex(random_bytes(8)),'to'=>$to,'actor'=>$db['users'][$actor]['name'] ?? 'Intellecta','type'=>$type,'time'=>date('d/m H:i'),'read'=>false,'url'=>$url];
}
function list_toggle(array &$list, string $id): bool {
    $active = !in_array($id,$list,true);
    $list = $active ? array_merge($list,[$id]) : array_values(array_diff($list,[$id]));
    return $active;
}
function snapshot(array $db, string $id): array {
    $u=$db['users'][$id]; $people=[]; $posts=[];
    foreach ($db['users'] as $other) {
        if ($other['id']!==$id && (!visible_user($db,$id,$other['id']) || !$other['settings']['profile-public'])) continue;
        $people[]=array_intersect_key($other,array_flip(['id','username','name','bio','demo','created']))+['online'=>$other['settings']['activity-status'] && ($other['lastSeen']??0)>time()-120,'following'=>count($other['following']),'followers'=>count(array_filter($db['users'],fn($x)=>in_array($other['id'],$x['following'],true)))];
    }
    foreach (array_reverse($db['posts'],true) as $p) {
        if (!visible_post($db,$id,$p)) continue;
        $author=$db['users'][$p['author']];
        $p['name']=$author['name']; $p['username']=$author['username'];
        $p['time']=date('d/m H:i',strtotime($p['created']));
        $p['likes']=$p['baseLikes']+count($p['liked']); $p['comments']=count($p['replies']);
        $p['isLiked']=in_array($id,$p['liked'],true); unset($p['liked']);
        foreach ($p['replies'] as &$r) { $r['name']=$db['users'][$r['author']]['name'] ?? 'Conta excluída'; } unset($r);
        if ($p['poll']) { $votes=$p['poll']['votes']; $p['poll']['counts']=array_map(fn($i)=>count(array_filter($votes,fn($v)=>$v===$i)),array_keys($p['poll']['options'])); $p['poll']['choice']=$votes[$id]??null; unset($p['poll']['votes']); }
        $posts[]=$p;
    }
    $followers=[];foreach($db['users'] as $other)if(in_array($id,$other['following'],true)&&visible_user($db,$id,$other['id']))$followers[]=['id'=>$other['id'],'name'=>$other['name'],'username'=>$other['username']];
    return ['account'=>public_user($u),'people'=>$people,'followers'=>$followers,'feed'=>$posts,'notifications'=>array_values(array_reverse(array_filter($db['notifications'],fn($n)=>$n['to']===$id))), 'messages'=>array_values(array_filter($db['messages'],fn($m)=>($m['from']===$id||$m['to']===$id)&&visible_user($db,$id,$m['from']===$id?$m['to']:$m['from'])))];
}
function api_action(array &$db, string $action, array $in): array {
    $uid=current_id($db);
    if ($action==='register' || $action==='demo') {
        $demo=$action==='demo';
        $name=$demo?'Visitante':field($in,'name',100);
        $username=$demo?'visitante_'.bin2hex(random_bytes(4)):ltrim(strtolower(field($in,'username',30)),'@');
        if (!preg_match('/^[a-z0-9_]{3,30}$/D',$username)) fail('Usuário: use de 3 a 30 letras, números ou sublinhado.');
        $email=$demo?'':strtolower(field($in,'email',254));
        $password=$demo?bin2hex(random_bytes(20)):field($in,'password',200);
        if (!$demo && (!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8||empty($in['terms']))) fail('Confira o e-mail, aceite os termos e use uma senha com pelo menos 8 caracteres.');
        foreach ($db['users'] as $other) if ($other['username']===$username||($email!==''&&$other['email']===$email)) fail('E-mail ou usuário já cadastrado.');
        $id=bin2hex(random_bytes(12)); $recovery=bin2hex(random_bytes(12));
        $u=['id'=>$id,'name'=>$name,'username'=>$username,'email'=>$email,'password'=>password_hash($password,PASSWORD_DEFAULT),'recovery'=>password_hash($recovery,PASSWORD_DEFAULT),'demo'=>$demo,'class'=>'','birthdate'=>'','birthdate_label'=>'','gender'=>'','country'=>'Brasil','phone'=>'','bio'=>'','settings'=>defaults(),'following'=>[],'blocked'=>[],'notify'=>[],'saved'=>[],'muted'=>[],'reposts'=>[],'version'=>1,'created'=>date('c')];
        $db['users'][$id]=$u; login_user($u);
        return ['redirect'=>'?page=home','recovery'=>$demo?null:$recovery];
    }
    if ($action==='login' || $action==='recover') {
        $email=strtolower(field($in,'email',254));
        $key=hash('sha256',($_SERVER['REMOTE_ADDR']??'local').'|'.$email);
        $attempt=$db['attempts'][$key]??['count'=>0,'until'=>0];
        if ($attempt['until']>time()&&$attempt['count']>=10) return ['_status'=>429,'error'=>'Muitas tentativas. Aguarde 15 minutos.'];
        $found=null; foreach ($db['users'] as $id=>$u) if ($u['email']===$email&&!$u['demo']) {$found=$id;break;}
        $valid=$found!==null && password_verify(field($in,$action==='login'?'password':'recovery',200),$db['users'][$found][$action==='login'?'password':'recovery']);
        if ($valid && $action==='login' && $db['users'][$found]['settings']['two-factor']) $valid=password_verify(field($in,'security_key',200,false),$db['users'][$found]['security_key']??'');
        if (!$valid) { $db['attempts'][$key]=['count'=>$attempt['until']>time()?$attempt['count']+1:1,'until'=>time()+900];return ['_status'=>401,'error'=>'Credenciais inválidas. Confira também sua chave adicional, se ativada.']; }
        unset($db['attempts'][$key]);
        if ($action==='recover') { $password=field($in,'new_password',200);if(strlen($password)<8)fail('Use pelo menos 8 caracteres.');$db['users'][$found]['password']=password_hash($password,PASSWORD_DEFAULT);$db['users'][$found]['settings']['two-factor']=false;$db['users'][$found]['version']++;$recovery=bin2hex(random_bytes(12));$db['users'][$found]['recovery']=password_hash($recovery,PASSWORD_DEFAULT); }
        login_user($db['users'][$found]);
        if ($db['users'][$found]['settings']['login-alerts']) $db['notifications'][]=['id'=>bin2hex(random_bytes(8)),'to'=>$found,'actor'=>'Intellecta','type'=>'Novo acesso à conta','time'=>date('d/m H:i'),'read'=>false,'url'=>'?page=seguranca'];
        return ['redirect'=>'?page=home','recovery'=>$recovery??null];
    }
    if (!$uid) fail('Entre na sua conta para continuar.',401);
    $u=&$db['users'][$uid];$u['lastSeen']=time();
    if ($action==='state') {$u['lastSeen']=time();return snapshot($db,$uid);}
    if ($action==='logout') { $_SESSION=[];session_regenerate_id(true);return ['redirect'=>'?page=login']; }
    if ($action==='profile') {
        $name=field($in,'name',100);$username=ltrim(strtolower(field($in,'username',30)),'@');
        if (!preg_match('/^[a-z0-9_]{3,30}$/D',$username)) fail('Nome de usuário inválido.');
        foreach($db['users'] as $other) if($other['id']!==$uid&&$other['username']===$username)fail('Este usuário já existe.');
        $birth=field($in,'birthdate',10,false);
        if($birth!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$birth)||!strtotime($birth)||date('Y-m-d',strtotime($birth))!==$birth||$birth>date('Y-m-d')))fail('Data de nascimento inválida.');
        $gender=field($in,'gender',20,false);if(!in_array($gender,['','feminino','masculino','outro'],true))fail('Gênero inválido.');
        $u['name']=$name;$u['username']=$username;$u['bio']=field($in,'bio',1000,false);$u['birthdate']=$birth;$u['birthdate_label']=$birth?date('d/m/Y',strtotime($birth)):'';$u['gender']=$gender;
        return ['message'=>'Perfil atualizado.'];
    }
    if($action==='password'||$action==='delete-account') {
        if(!$u['demo']&&!password_verify(field($in,'current_password',200),$u['password']))fail('Senha atual incorreta.');
        if($action==='password') {
            $next=field($in,'new_password',200);if(strlen($next)<8||$next!==field($in,'confirm_password',200))fail('As senhas devem coincidir e ter pelo menos 8 caracteres.');
            $u['password']=password_hash($next,PASSWORD_DEFAULT);$u['version']++;$_SESSION['version']=$u['version'];return ['message'=>'Senha alterada. Outros acessos foram encerrados.'];
        }
        foreach($db['posts'] as $id=>&$p) {if($p['author']===$uid){unset($db['posts'][$id]);continue;}$p['liked']=array_values(array_diff($p['liked'],[$uid]));$p['replies']=array_values(array_filter($p['replies'],fn($r)=>$r['author']!==$uid));if($p['poll'])unset($p['poll']['votes'][$uid]);}unset($p);
        foreach($db['users'] as &$other) foreach(['following','blocked','notify'] as $key) $other[$key]=array_values(array_diff($other[$key],[$uid]));unset($other);
        $db['messages']=array_values(array_filter($db['messages'],fn($m)=>$m['from']!==$uid&&$m['to']!==$uid));
        $db['notifications']=array_values(array_filter($db['notifications'],fn($n)=>$n['to']!==$uid));
        unset($db['users'][$uid]);$_SESSION=[];session_regenerate_id(true);return ['redirect'=>'?page=login'];
    }
    if($action==='setting') {
        $key=field($in,'key',40);if(!array_key_exists($key,defaults())||!is_bool($in['value']??null))fail('Preferência inválida.');
        if($key==='email-updates'&&$in['value'])fail('O envio de e-mail requer um serviço externo. Os avisos estão disponíveis em Notificações.');
        $result=[];
        if($key==='two-factor'&&$in['value']) {if($u['demo'])fail('Cadastre uma conta para ativar uma chave adicional.');$secret=bin2hex(random_bytes(16));$u['security_key']=password_hash($secret,PASSWORD_DEFAULT);$result['security_key']=$secret;}
        if($key==='two-factor'&&!$in['value']&&!password_verify(field($in,'current_password',200),$u['password']))fail('Senha incorreta.');
        $u['settings'][$key]=$in['value'];return $result+['message'=>'Preferência salva.'];
    }
    if($action==='post') {
        $body=field($in,'body',5000,false);$media=$in['media']??null;$poll=$in['poll']??null;
        if($body===''&&!$media&&!$poll)fail('Escreva ou anexe algo antes de publicar.');
        if($media!==null) {
            if(!is_string($media)||strlen($media)>2800000||!preg_match('#^data:image/(png|jpeg|gif|webp);base64,([A-Za-z0-9+/=]+)$#D',$media,$match))fail('Selecione uma imagem PNG, JPG, GIF ou WebP de até 2 MB.');
            $bytes=base64_decode($match[2],true);$info=$bytes!==false?@getimagesizefromstring($bytes):false;
            if(!$info||$info['mime']!=='image/'.$match[1]||strlen($bytes)>2097152||$info[0]*$info[1]>25000000)fail('Imagem inválida ou muito grande.');
        }
        if($poll!==null) {if(!is_array($poll)||!is_array($poll['options']??null)||count($poll['options'])<2||count($poll['options'])>6)fail('Use de 2 a 6 opções.');$poll=['question'=>field($poll,'question',300),'options'=>array_map(fn($s)=>field(['option'=>$s],'option',150),$poll['options']),'votes'=>[]];if(count(array_unique($poll['options']))!==count($poll['options']))fail('Use opções diferentes.');}
        $topic=field($in,'topic',40,false);if(!in_array($topic,['','Tecnologia','Design','Ciência','Matemática','Projetos','Carreira'],true))fail('Tópico inválido.');
        $id=bin2hex(random_bytes(12));$db['posts'][$id]=['id'=>$id,'author'=>$uid,'body'=>$body,'created'=>date('c'),'baseLikes'=>0,'baseComments'=>0,'liked'=>[],'replies'=>[],'media'=>$media,'poll'=>$poll,'topic'=>$topic];
        foreach($db['users'] as $other) if(in_array($uid,$other['notify'],true)&&visible_user($db,$uid,$other['id']))notification($db,$other['id'],$uid,'publicou uma ideia','?page=home&post='.$id);
        return ['message'=>'Publicação criada.','id'=>$id];
    }
    if(in_array($action,['like','save','comment','vote','repost','mute','delete-post'],true)) {
        $id=field($in,'id',60);if(!isset($db['posts'][$id])||!visible_post($db,$uid,$db['posts'][$id]))fail('Publicação não encontrada.',404);$p=&$db['posts'][$id];
        if($action==='like') {$active=list_toggle($p['liked'],$uid);if($active)notification($db,$p['author'],$uid,'curtiu sua publicação','?page=home&post='.$id);}
        elseif($action==='save')$active=list_toggle($u['saved'],$id);
        elseif($action==='repost')$active=list_toggle($u['reposts'],$id);
        elseif($action==='mute')$active=list_toggle($u['muted'],$id);
        elseif($action==='comment') {$p['replies'][]=['id'=>bin2hex(random_bytes(8)),'author'=>$uid,'body'=>field($in,'body',2000),'time'=>date('d/m H:i')];notification($db,$p['author'],$uid,'comentou na sua publicação','?page=home&post='.$id);}
        elseif($action==='vote') {if(!$p['poll']||!is_int($in['option']??null)||!array_key_exists($in['option'],$p['poll']['options']))fail('Opção inválida.');$p['poll']['votes'][$uid]=$in['option'];}
        elseif($action==='delete-post') {if($p['author']!==$uid)fail('Você só pode excluir suas publicações.',403);unset($db['posts'][$id]);}
        return ['active'=>$active??null,'message'=>'Alteração salva.'];
    }
    if(in_array($action,['follow','block','notify','report','message','clear-chat'],true)) {
        $target=field($in,'target',60);if(!isset($db['users'][$target])||$target===$uid)fail('Perfil inválido.');
        if($action!=='block'&&!visible_user($db,$uid,$target))fail('Este perfil está bloqueado.',403);
        if($action==='follow') {if(!$db['users'][$target]['settings']['profile-public'])fail('Perfil privado.',403);$active=list_toggle($u['following'],$target);if($active)notification($db,$target,$uid,'começou a seguir você','?page=public-profile&user='.urlencode($u['username']));}
        if($action==='notify')$active=list_toggle($u['notify'],$target);
        if($action==='block'){$active=list_toggle($u['blocked'],$target);if($active){$u['following']=array_values(array_diff($u['following'],[$target]));$db['users'][$target]['following']=array_values(array_diff($db['users'][$target]['following'],[$uid]));}}
        if($action==='report')$db['reports'][]=['id'=>bin2hex(random_bytes(8)),'from'=>$uid,'target'=>$target,'reason'=>field($in,'reason',2000),'created'=>date('c')];
        if($action==='message'){$body=field($in,'body',4000);$db['messages'][]=['id'=>bin2hex(random_bytes(8)),'from'=>$uid,'to'=>$target,'text'=>$body,'time'=>date('d/m H:i')];notification($db,$target,$uid,'enviou uma mensagem','?page=chat&user='.urlencode($u['username']));}
        if($action==='clear-chat'){$u['chatHidden'][$target]=array_values(array_map(fn($m)=>$m['id'],array_filter($db['messages'],fn($m)=>($m['from']===$uid&&$m['to']===$target)||($m['from']===$target&&$m['to']===$uid))));}
        return ['active'=>$active??null,'message'=>$action==='report'?'Denúncia registrada no servidor local.':'Alteração salva.'];
    }
    if($action==='read') {foreach($db['notifications'] as &$n)if($n['to']===$uid)$n['read']=true;unset($n);return ['message'=>'Notificações marcadas como lidas.'];}
    if($action==='assistant') {
        $q=field($in,'question',6000);$text=strtolower($q);
        if(str_contains($text,'resum')){$source=trim(preg_replace('/^.*?[:\n]/u','',$q,1));$parts=preg_split('/(?<=[.!?])\s+/u',$source,-1,PREG_SPLIT_NO_EMPTY);$answer=count($parts)>1?'Resumo por extração: '.implode(' ',array_slice($parts,0,3)):'Cole o texto após “Resumir:” para extrair até três frases principais.';}
        elseif(str_contains($text,'plano')||str_contains($text,'estud'))$answer='Roteiro de 45 minutos: 5 min para definir o assunto; 15 min para ler e anotar três conceitos; 15 min para resolver exercícios; 10 min para revisar os erros. Amanhã, tente explicar sem consultar as anotações.';
        else $answer='Sou a assistente local, sem um modelo de IA conectado. Posso montar um plano de estudos ou extrair um resumo de um texto: envie “plano de estudos” ou “Resumir: [seu texto]”.';
        return ['answer'=>$answer];
    }
    fail('Ação não encontrada.',404);
}
function backend_start(): void {
    date_default_timezone_set('America/Sao_Paulo');
    $sessionDir=(getenv('INTELLECTA_STORAGE') ?: dirname(__DIR__,2).'/storage').'/sessions';
    if(!is_dir($sessionDir)&&!mkdir($sessionDir,0700,true))throw new RuntimeException('Não foi possível criar as sessões.');
    session_save_path($sessionDir);
    ini_set('session.use_strict_mode','1');
    session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'path'=>'/']);
    session_start();
    $_SESSION['csrf']??=bin2hex(random_bytes(24));
    header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');header('X-Frame-Options: SAMEORIGIN');
    if(!isset($_GET['api']))return;
    header('Content-Type: application/json; charset=utf-8');
    try {
        if($_SERVER['REQUEST_METHOD']!=='POST')fail('Use POST.',405);
        if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))fail('Sessão expirada. Recarregue a página.',403);
        if((int)($_SERVER['CONTENT_LENGTH']??0)>3200000)fail('Anexo muito grande.',413);
        $raw=file_get_contents('php://input',false,null,0,3200001);if(strlen($raw)>3200000)fail('Anexo muito grande.',413);
        $in=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($in))fail('Requisição inválida.');
        $action=field($in,'action',40);
        $result=storage(function(array &$db)use($action,$in){return api_action($db,$action,$in);});
        if(isset($result['_status'])){http_response_code($result['_status']);unset($result['_status']);}
        echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }catch(DomainException $e){http_response_code($e->getCode()?:422);echo json_encode(['error'=>$e->getMessage()]);}
    catch(JsonException $e){http_response_code(400);echo json_encode(['error'=>'Dados JSON inválidos.']);}
    catch(Throwable $e){error_log((string)$e);http_response_code(500);echo json_encode(['error'=>'Não foi possível concluir. Verifique o armazenamento do servidor.']);}
    exit;
}

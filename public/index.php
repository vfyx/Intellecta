<?php
declare(strict_types=1);
require_once __DIR__.'/../src/app/backend.php';
backend_start();
$app=require __DIR__.'/../src/app/bootstrap.php';
$page=is_string($_GET['page']??null)?$_GET['page']:$app['routes']['default'];
$data=storage(function(array &$db){$id=current_id($db);return $id?snapshot($db,$id):null;});
if(!$data&&!in_array($page,['login','register','termos','privacidade-legal','ajuda'],true)){header('Location: ?page=login');exit;}
if(!isset($app['routes']['views'][$page]))http_response_code(404);
$GLOBALS['clientState']=['csrf'=>$_SESSION['csrf'],'page'=>$page,'data'=>$data,'icons'=>['bookmark'=>icon('bookmark'),'message'=>icon('message')]];
$data??=['people'=>[],'feed'=>[],'notifications'=>[],'messages'=>[],'account'=>['name'=>'Visitante','username'=>'visitante']];
render_site($page,$data['people'],$data['feed'],$data['notifications'],$data['messages'],$data['account']);

<?php
require_once __DIR__.'/db.php';
if(session_status()===PHP_SESSION_NONE)session_start();
setCORSHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(['status'=>'error','message'=>'POST required'],405);
$data=json_decode(file_get_contents('php://input'),true)?:[];$type=$data['promotion_type']??'';$id=(int)($data['promotion_id']??0);$event=$data['event_type']??'';
if(!verifyCsrfToken($data['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??'')))jsonResponse(['status'=>'error','message'=>'Invalid session token'],403);
if(!in_array($type,['recommended_venue','event_promotion'],true)||!in_array($event,['impression','click'],true)||!$id)jsonResponse(['status'=>'error','message'=>'Invalid analytics event'],400);
$db=getDB();
if($type==='recommended_venue'){$stmt=$db->prepare("SELECT tenant_id FROM recommended_venue_promotions WHERE id=? AND status='active' AND starts_at<=CURDATE() AND expires_at>=CURDATE()");}
else{$stmt=$db->prepare("SELECT tenant_id FROM event_promotions WHERE id=? AND status='active' AND promotion_starts_at<=NOW() AND promotion_expires_at>=NOW()");}
$stmt->execute([$id]);$tenantId=$stmt->fetchColumn();if(!$tenantId)jsonResponse(['status'=>'error','message'=>'Promotion is not active'],404);
$db->prepare("INSERT INTO promotion_analytics (tenant_id,promotion_type,promotion_id,event_type,player_id,event_date,metadata_json) VALUES (?,?,?,?,?,CURDATE(),?)")
   ->execute([(int)$tenantId,$type,$id,$event,$_SESSION['player_id']??null,json_encode(['source'=>'marketplace'])]);
jsonResponse(['status'=>'success']);

<?php
require_once __DIR__.'/db.php';
if(session_status()===PHP_SESSION_NONE)session_start();
setCORSHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(['status'=>'error','message'=>'POST required'],405);
$data=json_decode(file_get_contents('php://input'),true)?:[];
if(!verifyCsrfToken($data['csrf_token']??($_SERVER['HTTP_X_CSRF_TOKEN']??'')))jsonResponse(['status'=>'error','message'=>'Your session expired. Refresh and try again.'],403);
$venueId=(int)($data['venue_id']??0);$bookingDate=trim($data['booking_date']??'');$startTime=trim($data['start_time']??'');$code=trim($data['coupon_code']??'');
if(!$venueId||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$bookingDate)||$startTime===''||$code==='')jsonResponse(['status'=>'error','message'=>'Select a slot and enter a coupon code.'],400);
$db=getDB();$day=(int)date('w',strtotime($bookingDate));
$slot=$db->prepare("SELECT vs.price FROM venue_slots vs JOIN venues v ON v.id=vs.venue_id AND v.status='active' WHERE vs.venue_id=? AND vs.day_of_week=? AND vs.start_time=? AND vs.is_available=1 LIMIT 1");
$slot->execute([$venueId,$day,$startTime]);$base=$slot->fetchColumn();
if($base===false)jsonResponse(['status'=>'error','message'=>'The selected slot is no longer available.'],422);
try{$price=calculateBookingPrice($venueId,$base,$code,$_SESSION['player_id']??null,trim($data['customer_phone']??''));jsonResponse(['status'=>'success','message'=>'Coupon applied successfully.','pricing'=>$price]);}
catch(RuntimeException $e){jsonResponse(['status'=>'error','message'=>$e->getMessage()],422);}

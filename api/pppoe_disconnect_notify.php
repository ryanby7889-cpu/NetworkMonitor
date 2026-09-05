<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try{
 require_once __DIR__.'/../Config/database.php';
 $pdo=(new Database())->connect();
 $input=json_decode(file_get_contents('php://input'),true);
 if(!is_array($input))$input=$_POST;
 $routerId=(int)($input['router_id']??0);
 $routerName=trim((string)($input['router_name']??'Router'));
 $events=$input['events']??[];
 if($routerId<=0||!is_array($events)||!$events){echo json_encode(['success'=>true,'sent'=>0,'skipped'=>0]);exit;}
 $q=$pdo->query("SELECT setting_name,setting_value FROM settings WHERE setting_name IN ('telegram_enabled','telegram_bot_token','telegram_chat_id')");
 $cfg=$q->fetchAll(PDO::FETCH_KEY_PAIR);
 $enabled=($cfg['telegram_enabled']??'0')==='1';
 $token=trim((string)($cfg['telegram_bot_token']??''));
 $chat=trim((string)($cfg['telegram_chat_id']??''));
 if(!$enabled||$token===''||$chat===''){echo json_encode(['success'=>true,'sent'=>0,'skipped'=>count($events),'message'=>'Telegram PPPoE Disconnect belum aktif.']);exit;}
 $pdo->exec("CREATE TABLE IF NOT EXISTS telegram_pppoe_disconnect_log (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, event_key CHAR(64) NOT NULL, router_id INT NOT NULL, username VARCHAR(128) NOT NULL, session_id VARCHAR(128) NULL, notified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY(id), UNIQUE KEY uq_event(event_key), KEY idx_router_user(router_id,username,notified_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $reserve=$pdo->prepare('INSERT IGNORE INTO telegram_pppoe_disconnect_log(event_key,router_id,username,session_id) VALUES(?,?,?,?)');
 $remove=$pdo->prepare('DELETE FROM telegram_pppoe_disconnect_log WHERE event_key=?');
 $sent=0;$skipped=0;$errors=[];
 foreach(array_slice($events,0,50) as $e){
  if(!is_array($e))continue;
  $username=trim((string)($e['name']??''));if($username==='')continue;
  $session=trim((string)($e['session_id']??''));
  $eventKey=hash('sha256',$routerId.'|'.$username.'|'.$session);
  $reserve->execute([$eventKey,$routerId,$username,$session!==''?$session:null]);
  if($reserve->rowCount()===0){$skipped++;continue;}
  $address=trim((string)($e['address']??'-'));
  $profile=trim((string)($e['profile']??'-'));
  $caller=trim((string)($e['caller_id']??'-'));
  $uptime=trim((string)($e['uptime']??'-'));
  $time=date('d-m-Y H:i:s');
  $text="🔴 <b>PPPoE DISCONNECT</b>\n\n".
        "Router : <b>".htmlspecialchars($routerName,ENT_QUOTES,'UTF-8')."</b>\n".
        "Username : <b>".htmlspecialchars($username,ENT_QUOTES,'UTF-8')."</b>\n".
        "IP : ".htmlspecialchars($address,ENT_QUOTES,'UTF-8')."\n".
        "Profile : ".htmlspecialchars($profile,ENT_QUOTES,'UTF-8')."\n".
        "Caller-ID : ".htmlspecialchars($caller,ENT_QUOTES,'UTF-8')."\n".
        "Uptime terakhir : ".htmlspecialchars($uptime,ENT_QUOTES,'UTF-8')."\n".
        "Waktu : ".$time;
  $payload=json_encode(['chat_id'=>$chat,'text'=>$text,'parse_mode'=>'HTML','disable_web_page_preview'=>true]);
  $ch=curl_init('https://api.telegram.org/bot'.rawurlencode($token).'/sendMessage');
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12]);
  $body=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
  $res=is_string($body)?json_decode($body,true):null;
  if($body===false||$code<200||$code>=300||empty($res['ok'])){$remove->execute([$eventKey]);$errors[]=$username.': '.($err!==''?$err:'Telegram menolak pesan');continue;}
  $sent++;
 }
 echo json_encode(['success'=>true,'sent'=>$sent,'skipped'=>$skipped,'errors'=>$errors],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}

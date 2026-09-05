<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try{
 require_once __DIR__.'/../Config/database.php'; $pdo=(new Database())->connect();
 if($_SERVER['REQUEST_METHOD']==='POST'){
  $action=$_POST['action']??'save';
  if($action==='save'){
   $enabled=!empty($_POST['telegram_enabled'])?'1':'0'; $token=trim((string)($_POST['telegram_bot_token']??'')); $chat=trim((string)($_POST['telegram_chat_id']??''));
   if($token!==''&&!preg_match('/^\d{6,12}:[A-Za-z0-9_-]{20,}$/',$token))throw new RuntimeException('Format Bot Token Telegram tidak valid.');
   if($chat!==''&&!preg_match('/^-?\d{5,20}$/',$chat))throw new RuntimeException('Format Chat ID Telegram tidak valid.');
   foreach(['telegram_enabled'=>$enabled,'telegram_bot_token'=>$token,'telegram_chat_id'=>$chat] as $k=>$v){$s=$pdo->prepare('SELECT id FROM settings WHERE setting_name=? LIMIT 1');$s->execute([$k]);if($s->fetch()){$u=$pdo->prepare('UPDATE settings SET setting_value=? WHERE setting_name=?');$u->execute([$v,$k]);}else{$u=$pdo->prepare('INSERT INTO settings(setting_name,setting_value) VALUES(?,?)');$u->execute([$k,$v]);}}
   echo json_encode(['success'=>true,'message'=>'Pengaturan Telegram berhasil disimpan.']);exit;
  }
  if($action==='test'){
   $q=$pdo->query("SELECT setting_name,setting_value FROM settings WHERE setting_name IN ('telegram_bot_token','telegram_chat_id')");$cfg=$q->fetchAll(PDO::FETCH_KEY_PAIR);$token=trim((string)($cfg['telegram_bot_token']??''));$chat=trim((string)($cfg['telegram_chat_id']??''));if($token===''||$chat==='')throw new RuntimeException('Bot Token dan Chat ID harus diisi terlebih dahulu.');
   $payload=json_encode(['chat_id'=>$chat,'text'=>'✅ NetMonitor Telegram Bot aktif. Pesan tes berhasil dikirim.']);$ch=curl_init('https://api.telegram.org/bot'.rawurlencode($token).'/sendMessage');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>15]);$body=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($body===false)throw new RuntimeException('Gagal menghubungi Telegram: '.$err);$res=json_decode($body,true);if($code<200||$code>=300||empty($res['ok']))throw new RuntimeException('Telegram menolak permintaan. Periksa Bot Token dan Chat ID.');echo json_encode(['success'=>true,'message'=>'Pesan tes Telegram berhasil dikirim.']);exit;
  }
  throw new RuntimeException('Aksi Telegram tidak dikenal.');
 }
 $q=$pdo->query("SELECT setting_name,setting_value FROM settings WHERE setting_name IN ('telegram_enabled','telegram_bot_token','telegram_chat_id')");$cfg=$q->fetchAll(PDO::FETCH_KEY_PAIR);$token=(string)($cfg['telegram_bot_token']??'');echo json_encode(['success'=>true,'enabled'=>($cfg['telegram_enabled']??'0')==='1','has_token'=>$token!=='','bot_token'=>$token!==''?'••••••••'.substr($token,-6):'','chat_id'=>(string)($cfg['telegram_chat_id']??'')],JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}

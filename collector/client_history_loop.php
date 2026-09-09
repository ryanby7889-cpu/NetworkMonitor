<?php
/* Client traffic history collector - 60 second interval. */
date_default_timezone_set('Asia/Jakarta');
$endpoint='http://127.0.0.1/NetworkMonitor/collector/client_history_collector.php';
$logDir=__DIR__.'/logs'; $logFile=$logDir.'/client_history.log'; $lockFile=$logDir.'/client_history.lock';
if(!is_dir($logDir)) @mkdir($logDir,0775,true);
$h=fopen($lockFile,'c'); if(!$h || !flock($h,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Client history collector sudah berjalan.\n");exit(1);}
echo "Network Monitor Client History Collector\nInterval : 60 seconds\n\n";
while(true){$started=date('Y-m-d H:i:s');$ch=curl_init($endpoint);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Cache-Control: no-cache']]);$res=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$msg=trim((string)$res);if($res===false||$code<200||$code>=300){$msg=$err?:($msg?:"HTTP {$code}");$line="[{$started}] FAILED | {$msg}";}else{$line="[{$started}] OK | ".preg_replace('/\s+/',' ',$msg);}file_put_contents($logFile,$line.PHP_EOL,FILE_APPEND|LOCK_EX);echo $line.PHP_EOL;sleep(60);}

<?php

echo "====================================\n";
echo "Network Monitor Collector\n";
echo "Interval : 10 seconds\n";
echo "Client history : 60 seconds\n";
echo "====================================\n\n";

date_default_timezone_set('Asia/Jakarta');
$collectorPath=__DIR__.DIRECTORY_SEPARATOR.'collector.php';
$logDirectory=__DIR__.DIRECTORY_SEPARATOR.'logs';
$logFile=$logDirectory.DIRECTORY_SEPARATOR.'collector.log';
$lockFile=$logDirectory.DIRECTORY_SEPARATOR.'collector.lock';
$collectorUrl='http://127.0.0.1/NetworkMonitor/collector/collector.php';
$clientHistoryUrl='http://127.0.0.1/NetworkMonitor/collector/client_history_collector.php';
if(!is_dir($logDirectory)&&!mkdir($logDirectory,0775,true)&&!is_dir($logDirectory)){fwrite(STDERR,"Tidak dapat membuat folder log: {$logDirectory}\n");exit(1);}
if(!is_file($collectorPath)){fwrite(STDERR,"File collector tidak ditemukan: {$collectorPath}\n");exit(1);}
$lockHandle=fopen($lockFile,'c');if($lockHandle===false||!flock($lockHandle,LOCK_EX|LOCK_NB)){fwrite(STDERR,"Collector lain sudah berjalan.\n");exit(1);}
$cycle=0;
while(true){
 $cycle++;$startedAt=date('Y-m-d H:i:s');
 $curl=curl_init($collectorUrl);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Cache-Control: no-cache']]);$response=curl_exec($curl);$curlError=curl_error($curl);$httpCode=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
 $message=trim((string)$response);$isSuccess=$response!==false&&$httpCode>=200&&$httpCode<300&&stripos($message,'Router Offline')===false;if(!$isSuccess&&$curlError!=='')$message=$curlError;if(!$isSuccess&&$message==='')$message="HTTP {$httpCode}";$status=$isSuccess?'OK':'FAILED';$logEntry="[{$startedAt}] {$status}";if($message!=='')$logEntry.=' | '.preg_replace('/\s+/',' ',$message);$logEntry.=PHP_EOL;file_put_contents($logFile,$logEntry,FILE_APPEND|LOCK_EX);echo $logEntry;
 if($cycle===1||$cycle%6===0){$ch=curl_init($clientHistoryUrl);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>['Cache-Control: no-cache']]);$cr=curl_exec($ch);$ce=curl_error($ch);$cc=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);$cm=trim((string)$cr);$cs=$cr!==false&&$cc>=200&&$cc<300;if(!$cs&&$ce!=='')$cm=$ce;if(!$cs&&$cm==='')$cm="HTTP {$cc}";$cl="[{$startedAt}] CLIENT_HISTORY ".($cs?'OK':'FAILED').' | '.preg_replace('/\s+/',' ',$cm).PHP_EOL;file_put_contents($logFile,$cl,FILE_APPEND|LOCK_EX);echo $cl;}
 sleep(10);
}

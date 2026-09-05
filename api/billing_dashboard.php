<?php
/* NetMonitor - Dashboard Billing API */
require_once __DIR__ . '/../Config/database.php';
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');

function out_json(bool $ok, string $message = '', array $data = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function valid_month(string $value, string $fallback): string { return preg_match('/^\d{4}-\d{2}$/', $value) ? $value : $fallback; }
function pppoe_api_call(string $action): array {
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL belum aktif.');
    $url='http://127.0.0.1/NetworkMonitor/api/pppoe.php?action='.rawurlencode($action).'&t='.time();
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Accept: application/json']]);
    $body=curl_exec($ch); $error=curl_error($ch); $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if($body===false||$error!=='') throw new RuntimeException('API PPPoE gagal: '.($error?:'unknown error'));
    $data=json_decode($body,true);
    if(!is_array($data)||!($data['success']??false)) throw new RuntimeException((string)($data['message']??('Response API PPPoE tidak valid. HTTP '.$http)));
    return $data;
}
try {
    $pdo=(new Database())->connect(); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); $pdo->exec("SET time_zone = '+07:00'");
    $currentMonth=date('Y-m'); $month=valid_month(trim((string)($_GET['month']??'')),$currentMonth); $first=$month.'-01'; $last=date('Y-m-t',strtotime($first));

    $s=$pdo->prepare("SELECT COUNT(*) invoice_count,COALESCE(SUM(CASE WHEN status<>'cancelled' THEN amount ELSE 0 END),0) billed,COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) paid,COALESCE(SUM(CASE WHEN status='unpaid' THEN amount ELSE 0 END),0) unpaid,SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) paid_count,SUM(CASE WHEN status='unpaid' AND due_date<CURDATE() THEN 1 ELSE 0 END) overdue_count,SUM(CASE WHEN status='unpaid' AND due_date>=CURDATE() THEN 1 ELSE 0 END) waiting_count FROM billing_invoices WHERE period BETWEEN ? AND ?");
    $s->execute([$first,$last]); $summary=$s->fetch(PDO::FETCH_ASSOC)?:[];
    $c=$pdo->query("SELECT COUNT(*) total_customers,SUM(status='active') active_customers,SUM(status='suspended') suspended_customers FROM billing_customers")->fetch(PDO::FETCH_ASSOC)?:[];

    $integration=['api_online'=>false,'accounts'=>0,'enabled_accounts'=>0,'disabled_accounts'=>0,'linked_accounts'=>0,'unlinked_accounts'=>0,'active_sessions'=>0,'unlinked_billing_customers'=>0]; $pppoeError='';
    try {
        $secrets=is_array(($sd=pppoe_api_call('secrets'))['secrets']??null)?$sd['secrets']:[];
        $active=is_array(($ad=pppoe_api_call('active'))['users']??null)?$ad['users']:[];
        $billingRows=$pdo->query("SELECT pppoe_username FROM billing_customers WHERE pppoe_username IS NOT NULL AND TRIM(pppoe_username)<>''")->fetchAll(PDO::FETCH_COLUMN);
        $linkedMap=[]; foreach($billingRows as $u)$linkedMap[(string)$u]=true; $linked=0;$enabled=0;$disabled=0;
        foreach($secrets as $secret){$u=(string)($secret['name']??'');$d=(bool)($secret['disabled']??false);$d?$disabled++:$enabled++;if($u!==''&&isset($linkedMap[$u]))$linked++;}
        $integration=['api_online'=>true,'accounts'=>count($secrets),'enabled_accounts'=>$enabled,'disabled_accounts'=>$disabled,'linked_accounts'=>$linked,'unlinked_accounts'=>max(0,count($secrets)-$linked),'active_sessions'=>count($active),'unlinked_billing_customers'=>0];
        $names=[];foreach($secrets as $secret){$n=trim((string)($secret['name']??''));if($n!=='')$names[$n]=true;}
        foreach($billingRows as $u)if(!isset($names[(string)$u]))$integration['unlinked_billing_customers']++;
    } catch(Throwable $e){$pppoeError=$e->getMessage();}

    $trendRows=$pdo->query("SELECT DATE_FORMAT(period,'%Y-%m') month_key,COALESCE(SUM(CASE WHEN status<>'cancelled' THEN amount ELSE 0 END),0) billed,COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) paid,COALESCE(SUM(CASE WHEN status='unpaid' THEN amount ELSE 0 END),0) unpaid,COUNT(*) invoice_count FROM billing_invoices WHERE period>=DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 11 MONTH),'%Y-%m-01') AND period<=DATE_FORMAT(CURDATE(),'%Y-%m-01') GROUP BY DATE_FORMAT(period,'%Y-%m') ORDER BY month_key")->fetchAll(PDO::FETCH_ASSOC);
    $map=[];foreach($trendRows as $r)$map[$r['month_key']]=$r;$trend=[];$cur=new DateTime(date('Y-m-01',strtotime('-11 months')));$end=new DateTime(date('Y-m-01'));
    while($cur<=$end){$k=$cur->format('Y-m');$trend[]=['month'=>$k,'billed'=>(float)($map[$k]['billed']??0),'paid'=>(float)($map[$k]['paid']??0),'unpaid'=>(float)($map[$k]['unpaid']??0),'invoice_count'=>(int)($map[$k]['invoice_count']??0)];$cur->modify('+1 month');}

    $status=$pdo->prepare("SELECT status,COUNT(*) total,COALESCE(SUM(amount),0) amount FROM billing_invoices WHERE period BETWEEN ? AND ? GROUP BY status ORDER BY total DESC");$status->execute([$first,$last]);
    $arrears=$pdo->query("SELECT c.id,c.name,c.pppoe_username,c.status customer_status,COALESCE(SUM(i.amount),0) arrears,COUNT(i.id) overdue_invoices,MIN(i.due_date) oldest_due_date,DATEDIFF(CURDATE(),MIN(i.due_date)) oldest_days FROM billing_customers c JOIN billing_invoices i ON i.customer_id=c.id WHERE i.status='unpaid' AND i.due_date<CURDATE() GROUP BY c.id,c.name,c.pppoe_username,c.status ORDER BY arrears DESC,oldest_days DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $upcoming=$pdo->query("SELECT i.id,i.invoice_no,i.amount,i.due_date,c.id customer_id,c.name customer_name,c.pppoe_username,DATEDIFF(i.due_date,CURDATE()) days_left FROM billing_invoices i JOIN billing_customers c ON c.id=i.customer_id WHERE i.status='unpaid' AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY) ORDER BY i.due_date,i.id LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

    out_json(true,'',['month'=>$month,'summary'=>['invoice_count'=>(int)($summary['invoice_count']??0),'billed'=>(float)($summary['billed']??0),'paid'=>(float)($summary['paid']??0),'unpaid'=>(float)($summary['unpaid']??0),'paid_count'=>(int)($summary['paid_count']??0),'overdue_count'=>(int)($summary['overdue_count']??0),'waiting_count'=>(int)($summary['waiting_count']??0)],'customers'=>['total'=>(int)($c['total_customers']??0),'active'=>(int)($c['active_customers']??0),'suspended'=>(int)($c['suspended_customers']??0)],'integration'=>$integration,'pppoe_error'=>$pppoeError,'trend'=>$trend,'statuses'=>$status->fetchAll(PDO::FETCH_ASSOC),'arrears'=>$arrears,'upcoming'=>$upcoming]);
} catch(Throwable $e){out_json(false,$e->getMessage(),[],500);}

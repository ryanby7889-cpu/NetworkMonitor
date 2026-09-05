<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Config/database.php';
date_default_timezone_set('Asia/Jakarta');
try {
    $pdo=(new Database())->connect();
    $month=$_GET['month']??date('Y-m');
    if(!preg_match('/^\d{4}-\d{2}$/',$month)) $month=date('Y-m');
    $start=$month.'-01';
    $end=(new DateTime($start))->modify('+1 month')->format('Y-m-d');
    $q=$pdo->prepare("SELECT COUNT(*) invoice_count,COALESCE(SUM(amount),0) billed,COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) paid,COALESCE(SUM(CASE WHEN status='unpaid' THEN amount ELSE 0 END),0) unpaid,COALESCE(SUM(CASE WHEN status='cancelled' THEN amount ELSE 0 END),0) cancelled,COUNT(CASE WHEN status='paid' THEN 1 END) paid_count,COUNT(CASE WHEN status='unpaid' THEN 1 END) unpaid_count FROM billing_invoices WHERE period>=? AND period<?");$q->execute([$start,$end]);$summary=$q->fetch(PDO::FETCH_ASSOC)?:[];
    $q=$pdo->prepare("SELECT COUNT(*) payment_count,COALESCE(SUM(amount),0) amount FROM billing_invoices WHERE status='paid' AND paid_at>=? AND paid_at<?");$q->execute([$start,$end]);$cash=$q->fetch(PDO::FETCH_ASSOC)?:[];
    $q=$pdo->prepare("SELECT COALESCE(SUM(amount),0) amount,COUNT(*) invoice_count FROM billing_invoices WHERE status='unpaid' AND due_date<CURDATE()");$q->execute();$overdue=$q->fetch(PDO::FETCH_ASSOC)?:[];
    $q=$pdo->prepare("SELECT payment_method,COUNT(*) count,COALESCE(SUM(amount),0) amount FROM billing_invoices WHERE status='paid' AND paid_at>=? AND paid_at<? GROUP BY payment_method ORDER BY amount DESC");$q->execute([$start,$end]);$methods=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->query("SELECT DATE_FORMAT(paid_at,'%Y-%m') month,COUNT(*) count,COALESCE(SUM(amount),0) amount FROM billing_invoices WHERE status='paid' AND paid_at>=DATE_SUB(CURDATE(),INTERVAL 11 MONTH) GROUP BY DATE_FORMAT(paid_at,'%Y-%m') ORDER BY month");$trend=$q->fetchAll(PDO::FETCH_ASSOC);
    $q=$pdo->prepare("SELECT c.name,c.pppoe_username,COUNT(i.id) invoices,COALESCE(SUM(i.amount),0) billed,COALESCE(SUM(CASE WHEN i.status='paid' THEN i.amount ELSE 0 END),0) paid,COALESCE(SUM(CASE WHEN i.status='unpaid' THEN i.amount ELSE 0 END),0) unpaid FROM billing_customers c LEFT JOIN billing_invoices i ON i.customer_id=c.id AND i.period>=? AND i.period<? GROUP BY c.id ORDER BY unpaid DESC,paid DESC LIMIT 20");$q->execute([$start,$end]);$customers=$q->fetchAll(PDO::FETCH_ASSOC);
    $billed=(float)($summary['billed']??0);$paid=(float)($summary['paid']??0);$cashAmount=(float)($cash['amount']??0);
    echo json_encode(['success'=>true,'month'=>$month,'summary'=>['invoice_count'=>(int)($summary['invoice_count']??0),'billed'=>$billed,'paid'=>$paid,'unpaid'=>(float)($summary['unpaid']??0),'cancelled'=>(float)($summary['cancelled']??0),'paid_count'=>(int)($summary['paid_count']??0),'unpaid_count'=>(int)($summary['unpaid_count']??0),'collection_rate'=>$billed>0?round($paid/$billed*100,2):0],'cash'=>['payment_count'=>(int)($cash['payment_count']??0),'amount'=>$cashAmount],'overdue'=>['invoice_count'=>(int)($overdue['invoice_count']??0),'amount'=>(float)($overdue['amount']??0)],'methods'=>$methods,'trend'=>$trend,'customers'=>$customers],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e) { http_response_code(500); echo json_encode(['success'=>false,'message'=>'Gagal mengambil laporan keuangan.']); }

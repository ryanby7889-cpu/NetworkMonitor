<?php
/** NetMonitor - global alarm status endpoint. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
try {
    require_once __DIR__ . '/../Config/database.php';
    $pdo = (new Database())->connect();
    $counts = $pdo->query("SELECT COUNT(*) total,SUM(status='active') active,SUM(status='active' AND severity='critical') critical,SUM(status='active' AND severity='warning') warning FROM alarms")->fetch(PDO::FETCH_ASSOC) ?: [];
    $alarms = $pdo->query("SELECT id,router_id,interface_name,alarm_type,severity,message,value,threshold,created_at FROM alarms WHERE status='active' ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($alarms as &$alarm) {
        $alarm['username'] = null;
        $alarm['detail_url'] = null;
        $alarm['history_url'] = null;
        $type = strtolower((string)($alarm['alarm_type'] ?? ''));
        $iface = trim((string)($alarm['interface_name'] ?? ''), " <>\t\n\r\0\x0B");
        if (strpos($type, 'pppoe_bandwidth') === 0) {
            if (preg_match('/^pppoe-(.+)$/i', $iface, $m)) {
                $alarm['username'] = $m[1];
            } elseif (preg_match('/^PPPoE\s+(.+?)\s+(?:download|upload)\s+/i', (string)$alarm['message'], $m)) {
                $alarm['username'] = trim($m[1]);
            }
            if ($alarm['username'] !== null && $alarm['username'] !== '') {
                $u = rawurlencode($alarm['username']);
                $alarm['detail_url'] = '../pppoe/user.php?username=' . $u;
                $alarm['history_url'] = '../pppoe/history.php?username=' . $u;
            }
        }
    }
    unset($alarm);

    echo json_encode([
        'success' => true,
        'active' => (int)($counts['active'] ?? 0),
        'critical' => (int)($counts['critical'] ?? 0),
        'warning' => (int)($counts['warning'] ?? 0),
        'history' => (int)($counts['total'] ?? 0),
        'alarms' => $alarms,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'active'=>0,'critical'=>0,'warning'=>0,'history'=>0,'alarms'=>[]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}

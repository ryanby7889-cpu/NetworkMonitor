<?php
$pageTitle = 'Alarm';
$activeMenu = 'alarm';
$pageCss = ['../assets/css/alarm.css'];
?>
<?php
require_once __DIR__ . "/../Config/database.php";
require_once __DIR__ . "/../Config/alarm.php";

class AlarmEngine
{
    private $pdo;

    public function __construct()
    {
        $db = new Database();
        $this->pdo = $db->connect();
    }

    public function checkTraffic($routerId, $interface, $download, $upload)
    {
        if ($download >= AlarmConfig::downloadCritical()) {
            $this->createAlarm($routerId, $interface, 'download_high', 'critical', 'Download Ether1 sangat tinggi', $download, AlarmConfig::downloadCritical());
        } elseif ($download >= AlarmConfig::downloadWarning()) {
            $this->createAlarm($routerId, $interface, 'download_high', 'warning', 'Download Ether1 tinggi', $download, AlarmConfig::downloadWarning());
        } else {
            $this->resolveAlarm($routerId, $interface, 'download_high');
        }

        if ($upload >= AlarmConfig::uploadCritical()) {
            $this->createAlarm($routerId, $interface, 'upload_high', 'critical', 'Upload Ether1 sangat tinggi', $upload, AlarmConfig::uploadCritical());
        } elseif ($upload >= AlarmConfig::uploadWarning()) {
            $this->createAlarm($routerId, $interface, 'upload_high', 'warning', 'Upload Ether1 tinggi', $upload, AlarmConfig::uploadWarning());
        } else {
            $this->resolveAlarm($routerId, $interface, 'upload_high');
        }
    }

    /**
     * PPPoE bandwidth alarm uses separate Download/Upload limits and thresholds.
     * Download = router TX -> pelanggan, Upload = pelanggan RX -> router.
     */
    public function checkPppoeBandwidth(
        $routerId,
        $interface,
        $username,
        $downloadMbps,
        $uploadMbps,
        $downloadLimitMbps,
        $uploadLimitMbps = 0,
        $downloadWarningPercent = null,
        $downloadCriticalPercent = null,
        $uploadWarningPercent = null,
        $uploadCriticalPercent = null
    ) {
        $interface = trim((string)$interface) !== '' ? trim((string)$interface) : 'pppoe-' . $username;
        $dl = (float)$downloadMbps;
        $ul = (float)$uploadMbps;
        $dlim = (float)$downloadLimitMbps;
        $ulim = (float)$uploadLimitMbps;
        $dw = $downloadWarningPercent === null ? AlarmConfig::downloadWarning() : (float)$downloadWarningPercent;
        $dc = $downloadCriticalPercent === null ? AlarmConfig::downloadCritical() : (float)$downloadCriticalPercent;
        $uw = $uploadWarningPercent === null ? AlarmConfig::uploadWarning() : (float)$uploadWarningPercent;
        $uc = $uploadCriticalPercent === null ? AlarmConfig::uploadCritical() : (float)$uploadCriticalPercent;

        $this->checkPppoeDirection($routerId, $interface, $username, 'download', $dl, $dlim, $dw, $dc);
        $this->checkPppoeDirection($routerId, $interface, $username, 'upload', $ul, $ulim, $uw, $uc);
    }

    private function checkPppoeDirection($routerId, $interface, $username, $direction, $value, $limit, $warning, $critical)
    {
        $type = 'pppoe_bandwidth_' . $direction;

        if ($limit <= 0 || $value <= 0) {
            $this->resolveAlarm($routerId, $interface, $type);
            return;
        }

        $usage = ($value / $limit) * 100;
        if ($usage >= $critical) {
            $this->createAlarm(
                $routerId,
                $interface,
                $type,
                'critical',
                'PPPoE ' . $username . ' ' . $direction . ' sangat tinggi',
                $value,
                $limit * ($critical / 100)
            );
        } elseif ($usage >= $warning) {
            $this->createAlarm(
                $routerId,
                $interface,
                $type,
                'warning',
                'PPPoE ' . $username . ' ' . $direction . ' tinggi',
                $value,
                $limit * ($warning / 100)
            );
        } else {
            $this->resolveAlarm($routerId, $interface, $type);
        }
    }

    private function createAlarm($routerId, $interface, $type, $severity, $message, $value, $threshold)
    {
        $stmt = $this->pdo->prepare("SELECT id FROM alarms WHERE router_id=:router_id AND interface_name=:interface AND alarm_type=:type AND status='active' LIMIT 1");
        $stmt->execute([
            ':router_id' => $routerId,
            ':interface' => $interface,
            ':type' => $type
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $update = $this->pdo->prepare("UPDATE alarms SET severity=:severity,message=:message,value=:value,threshold=:threshold WHERE id=:id");
            $update->execute([
                ':severity' => $severity,
                ':message' => $message,
                ':value' => $value,
                ':threshold' => $threshold,
                ':id' => $existing['id']
            ]);
            return;
        }

        $stmt = $this->pdo->prepare("INSERT INTO alarms (router_id,interface_name,alarm_type,severity,message,value,threshold,status) VALUES (:router_id,:interface,:type,:severity,:message,:value,:threshold,'active')");
        $stmt->execute([
            ':router_id' => $routerId,
            ':interface' => $interface,
            ':type' => $type,
            ':severity' => $severity,
            ':message' => $message,
            ':value' => $value,
            ':threshold' => $threshold
        ]);
    }

    private function resolveAlarm($routerId, $interface, $type)
    {
        $stmt = $this->pdo->prepare("UPDATE alarms SET status='resolved',resolved_at=NOW() WHERE router_id=:router_id AND interface_name=:interface AND alarm_type=:type AND status='active'");
        $stmt->execute([
            ':router_id' => $routerId,
            ':interface' => $interface,
            ':type' => $type
        ]);
    }

    public function routerOffline($routerId)
    {
        $recentSample = $this->pdo->prepare("SELECT 1 FROM traffic_history WHERE router_id=:router_id AND interface_name='ether1' AND created_at>=DATE_SUB(NOW(),INTERVAL 60 SECOND) LIMIT 1");
        $recentSample->execute([':router_id' => $routerId]);
        if ($recentSample->fetchColumn()) {
            $this->routerOnline($routerId);
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT id FROM alarms WHERE router_id=:router_id AND alarm_type='router_offline' AND status='active' LIMIT 1");
        $stmt->execute([':router_id' => $routerId]);
        if ($stmt->fetch()) return false;

        $stmt = $this->pdo->prepare("INSERT INTO alarms (router_id,interface_name,alarm_type,severity,message,value,threshold,status,created_at) VALUES (:router_id,'ether1','router_offline','critical','Router MikroTik Offline',0,0,'active',NOW())");
        $stmt->execute([':router_id' => $routerId]);
        return true;
    }

    public function routerOnline($routerId)
    {
        $stmt = $this->pdo->prepare("UPDATE alarms SET status='resolved',resolved_at=NOW() WHERE router_id=:router_id AND alarm_type='router_offline' AND status='active'");
        $stmt->execute([':router_id' => $routerId]);
        return $stmt->rowCount();
    }
}

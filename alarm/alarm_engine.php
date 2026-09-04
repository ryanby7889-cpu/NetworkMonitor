<?php
$pageTitle = 'Alarm';
$activeMenu = 'alarm';
$pageCss = ['../assets/css/alarm.css'];
?>
<?php
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/alarm.php";

class AlarmEngine
{
    private $pdo;

    public function __construct()
    {
        $db = new Database();
        $this->pdo = $db->connect();
    }

    public function checkTraffic($routerId,$interface,$download,$upload)
    {
        if ($download >= AlarmConfig::downloadCritical()) {
            $this->createAlarm($routerId,$interface,"download_high","critical","Download Ether1 sangat tinggi",$download,AlarmConfig::downloadCritical());
        } elseif ($download >= AlarmConfig::downloadWarning()) {
            $this->createAlarm($routerId,$interface,"download_high","warning","Download Ether1 tinggi",$download,AlarmConfig::downloadWarning());
        } else {
            $this->resolveAlarm($routerId,$interface,"download_high");
        }

        if ($upload >= AlarmConfig::uploadCritical()) {
            $this->createAlarm($routerId,$interface,"upload_high","critical","Upload Ether1 sangat tinggi",$upload,AlarmConfig::uploadCritical());
        } elseif ($upload >= AlarmConfig::uploadWarning()) {
            $this->createAlarm($routerId,$interface,"upload_high","warning","Upload Ether1 tinggi",$upload,AlarmConfig::uploadWarning());
        } else {
            $this->resolveAlarm($routerId,$interface,"upload_high");
        }
    }

    /**
     * PPPoE bandwidth alarm. The value is the current maximum direction in Mbps
     * and the threshold is the profile limit percentage converted to Mbps.
     */
    public function checkPppoeBandwidth($routerId,$interface,$username,$downloadMbps,$uploadMbps,$limitMbps,$warningPercent=80,$criticalPercent=90)
    {
        $interface = trim((string)$interface) !== '' ? trim((string)$interface) : 'pppoe-'.$username;
        $peak = max((float)$downloadMbps,(float)$uploadMbps);
        if ($limitMbps <= 0 || $peak <= 0) {
            $this->resolvePppoeAlarm($routerId,$interface,$username);
            return;
        }

        $usage = ($peak / $limitMbps) * 100;
        if ($usage >= $criticalPercent) {
            $this->createAlarm($routerId,$interface,'pppoe_bandwidth','critical','PPPoE '.$username.' menggunakan bandwidth sangat tinggi',$peak,$limitMbps*($criticalPercent/100));
        } elseif ($usage >= $warningPercent) {
            $this->createAlarm($routerId,$interface,'pppoe_bandwidth','warning','PPPoE '.$username.' menggunakan bandwidth tinggi',$peak,$limitMbps*($warningPercent/100));
        } else {
            $this->resolvePppoeAlarm($routerId,$interface,$username);
        }
    }

    private function createAlarm($routerId,$interface,$type,$severity,$message,$value,$threshold)
    {
        $sql="SELECT id FROM alarms WHERE router_id=:router_id AND interface_name=:interface AND alarm_type=:type AND status='active' LIMIT 1";
        $stmt=$this->pdo->prepare($sql);
        $stmt->execute([':router_id'=>$routerId,':interface'=>$interface,':type'=>$type]);
        if($stmt->fetch()) return;

        $sql="INSERT INTO alarms (router_id,interface_name,alarm_type,severity,message,value,threshold,status) VALUES (:router_id,:interface,:type,:severity,:message,:value,:threshold,'active')";
        $stmt=$this->pdo->prepare($sql);
        $stmt->execute([':router_id'=>$routerId,':interface'=>$interface,':type'=>$type,':severity'=>$severity,':message'=>$message,':value'=>$value,':threshold'=>$threshold]);
    }

    private function resolveAlarm($routerId,$interface,$type)
    {
        $stmt=$this->pdo->prepare("UPDATE alarms SET status='resolved',resolved_at=NOW() WHERE router_id=:router_id AND interface_name=:interface AND alarm_type=:type AND status='active'");
        $stmt->execute([':router_id'=>$routerId,':interface'=>$interface,':type'=>$type]);
    }

    private function resolvePppoeAlarm($routerId,$interface,$username)
    {
        $stmt=$this->pdo->prepare("UPDATE alarms SET status='resolved',resolved_at=NOW() WHERE router_id=:router_id AND interface_name=:interface AND alarm_type='pppoe_bandwidth' AND status='active'");
        $stmt->execute([':router_id'=>$routerId,':interface'=>$interface]);
    }

    public function routerOffline($routerId)
    {
        $recentSample=$this->pdo->prepare("SELECT 1 FROM traffic_history WHERE router_id=:router_id AND interface_name='ether1' AND created_at>=DATE_SUB(NOW(),INTERVAL 60 SECOND) LIMIT 1");
        $recentSample->execute([':router_id'=>$routerId]);
        if($recentSample->fetchColumn()){$this->routerOnline($routerId);return false;}

        $stmt=$this->pdo->prepare("SELECT id FROM alarms WHERE router_id=:router_id AND alarm_type='router_offline' AND status='active' LIMIT 1");
        $stmt->execute([':router_id'=>$routerId]);
        if($stmt->fetch())return false;

        $stmt=$this->pdo->prepare("INSERT INTO alarms (router_id,interface_name,alarm_type,severity,message,value,threshold,status,created_at) VALUES (:router_id,'ether1','router_offline','critical','Router MikroTik Offline',0,0,'active',NOW())");
        $stmt->execute([':router_id'=>$routerId]);
        return true;
    }

    public function routerOnline($routerId)
    {
        $stmt=$this->pdo->prepare("UPDATE alarms SET status='resolved',resolved_at=NOW() WHERE router_id=:router_id AND alarm_type='router_offline' AND status='active'");
        $stmt->execute([':router_id'=>$routerId]);
        return $stmt->rowCount();
    }
}

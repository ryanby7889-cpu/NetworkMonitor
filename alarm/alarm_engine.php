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
    public function __construct(){ $db=new Database(); $this->pdo=$db->connect(); }
    public function checkTraffic($routerId,$interface,$download,$upload){
        if($download>=AlarmConfig::downloadCritical())$this->createAlarm($routerId,$interface,"download_high","critical","Download Ether1 sangat tinggi",$download,AlarmConfig::downloadCritical());
        elseif($download>=AlarmConfig::downloadWarning())$this->createAlarm($routerId,$interface,"download_high","warning","Download Ether1 tinggi",$download,AlarmConfig::downloadWarning());
        else $this->resolveAlarm($routerId,$interface,"download_high");
        if($upload>=AlarmConfig::uploadCritical())$this->createAlarm($routerId,$interface,"upload_high","critical","Upload Ether1 sangat tinggi",$upload,AlarmConfig::uploadCritical());
        elseif($upload>=AlarmConfig::uploadWarning())$this->createAlarm($routerId,$interface,"upload_high","warning","Upload Ether1 tinggi",$upload,AlarmConfig::uploadWarning());
        else $this->resolveAlarm($routerId,$interface,"upload_high");
    }
    public function checkPppoeBandwidth($routerId,$interface,$username,$downloadMbps,$uploadMbps,$downloadLimitMbps,$uploadLimitMbps=0,$downloadWarningPercent=null,$downloadCriticalPercent=null,$uploadWarningPercent=null,$uploadCriticalPercent=null){
        $interface=trim((string)$interface)!==''?trim((string)$interface):'pppoe-'.$username;
        $dl=(float)$downloadMbps;$ul=(float)$uploadMbps;$dlim=(float)$downloadLimitMbps;$ulim=(float)$uploadLimitMbps;
        $dw=$downloadWarningPercent===null?AlarmConfig::downloadWarning():(float)$downloadWarningPercent;$dc=$downloadCriticalPercent===null?AlarmConfig::downloadCritical():(float)$downloadCriticalPercent;
        $uw=$uploadWarningPercent===null?AlarmConfig::uploadWarning():(float)$uploadWarningPercent;$uc=$uploadCriticalPercent===null?AlarmConfig::uploadCritical():(float)$uploadCriticalPercent;
        $has=false;
        if($dlim>0&&$dl>0){$usage=($dl/$dlim)*100;if($usage>=$dc){$this->createAlarm($routerId,$interface,'pppoe_bandwidth','critical','PPPoE '.$username.' download sangat tinggi',$dl,$dlim*($dc/100));$has=true;}elseif($usage>=$dw){$this->createAlarm($routerId,$interface,'pppoe_bandwidth','warning','PPPoE '.$username.' download tinggi',$dl,$dlim*($dw/100));$has=true;}}
        if($ulim>0&&$ul>0){$usage=($ul/$ulim)*100;if($usage>=$uc){$this->createAlarm($routerId,$interface,'pppoe_bandwidth','critical','PPPoE '.$username.' upload sangat tinggi',$ul,$ulim*($uc/100));$has=true;}elseif($usage>=$uw){$this->createAlarm($routerId,$interface,'pppoe_bandwidth','warning','PPPoE '.$username.' upload tinggi',$ul,$ulim*($uw/100));$has=true;}}
        if(!$has)$this->resolvePppoeAlarm($routerId,$interface,$username);
    }
    private function createAlarm($routerId,$interface,$type,$severity,$message,$value,$threshold){
        $stmt=$this->pdo->prepare("SELECT id FROM alarms WHERE router_id=:router_id AND interface_name=:interface AND alarm_type=:type AND status='active' LIMIT 1");
        $stmt->execute([':router_id'=>$routerId,':interface'=>$interface,':type'=>$type]);if($stmt->fetch())return;
        $stmt=$this->pdo->prepare("INSERT INTO alarms (router_id,interface_name,alarm_type,severity,message,value,threshold,status) VALUES (:router_id,:interface,:type,:severity,:message,:value,:threshold,'active')");
        $stmt->execute([':router_id'=>$routerId,':interface'=>$interface,':type'=>$type,':severity'=>$severity,':message'=>$message,':value'=>$value,':threshold'=>$threshold]);
    }
    private function resolveAlarm($routerId,$interface,$type){$stmt=$this->pdo->prepare("UPDATE alarms SET status='resolved',resolved_at=NOW() WHERE router_id=:router_id AND interface_name=:interface AND alarm_type=:type AND status='active'");$stmt->execute([':router_id'=>$routerId,':interface'=>$interface,'type'=>$type]);}
    private function resolvePppoeAlarm($routerId,$interface,$username){$stmt=$this->pdo->prepare("UPDATE alarms SET status='resolved',resolved_at=NOW() WHERE router_id=:routerId AND interface_name=:interface AND alarm_type='pppoe_bandwidth' AND status='active'");$stmt->execute([':routerId'=>$routerId,':interface'=>$interface]);}
    public function routerOffline($routerId){$recentSample=$this->pdo->prepare("SELECT 1 FROM traffic_history WHERE router_id=:router_id AND interface_name='ether1' AND created_at>=DATE_SUB(NOW(),INTERVAL 60 SECOND) LIMIT 1");$recentSample->execute([':router_id'=>$routerId]);if($recentSample->fetchColumn()){$this->routerOnline($routerId);return false;}$stmt=$this->pdo->prepare("SELECT id FROM alarms WHERE router_id=:router_id AND alarm_type='router_offline' AND status='active' LIMIT 1");$stmt->execute([':router_id'=>$routerId]);if($stmt->fetch())return false;$stmt=$this->pdo->prepare("INSERT INTO alarms (router_id,interface_name,alarm_type,severity,message,value,threshold,status,created_at) VALUES (:router_id,'ether1','router_offline','critical','Router MikroTik Offline',0,0,'active',NOW())");$stmt->execute([':router_id'=>$routerId]);return true;}
    public function routerOnline($routerId){$stmt=$this->pdo->prepare("UPDATE alarms SET status='resolved',resolved_at=NOW() WHERE router_id=:router_id AND alarm_type='router_offline' AND status='active'");$stmt->execute([':router_id'=>$routerId]);return $stmt->rowCount();}
}

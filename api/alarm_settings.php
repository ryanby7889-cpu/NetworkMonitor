<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    require_once __DIR__ . '/../Config/database.php';
    $pdo = (new Database())->connect();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $pdo->query("SELECT setting_name, setting_value FROM settings WHERE setting_name IN ('download_warning','download_critical','upload_warning','upload_critical')");
        $s = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        echo json_encode([
            'success' => true,
            'download_warning' => (float)($s['download_warning'] ?? 80),
            'download_critical' => (float)($s['download_critical'] ?? 90),
            'upload_warning' => (float)($s['upload_warning'] ?? 20),
            'upload_critical' => (float)($s['upload_critical'] ?? 30)
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
        exit;
    }

    $dw = max(0, min(100, (float)($_POST['download_warning'] ?? 80)));
    $dc = max(0, min(100, (float)($_POST['download_critical'] ?? 90)));
    $uw = max(0, min(100, (float)($_POST['upload_warning'] ?? 20)));
    $uc = max(0, min(100, (float)($_POST['upload_critical'] ?? 30)));

    if ($dc <= $dw) throw new RuntimeException('Download Critical harus lebih besar dari Download Warning.');
    if ($uc <= $uw) throw new RuntimeException('Upload Critical harus lebih besar dari Upload Warning.');

    foreach ([
        'download_warning' => $dw,
        'download_critical' => $dc,
        'upload_warning' => $uw,
        'upload_critical' => $uc
    ] as $name => $value) {
        $check = $pdo->prepare('SELECT id FROM settings WHERE setting_name=? LIMIT 1');
        $check->execute([$name]);
        if ($check->fetchColumn()) {
            $stmt = $pdo->prepare('UPDATE settings SET setting_value=? WHERE setting_name=?');
            $stmt->execute([(string)$value, $name]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO settings(setting_name,setting_value) VALUES(?,?)');
            $stmt->execute([$name, (string)$value]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Threshold traffic Ether1 berhasil disimpan.']);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

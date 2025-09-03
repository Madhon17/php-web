<?php
header('Content-Type: application/json; charset=utf-8');
require 'connect.php';

$device_token_expected = "6c108269";

// Ambil device_token dan action dari GET atau POST
$device_token = $_GET['device_token'] ?? $_POST['device_token'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$data_raw = $_GET['data'] ?? $_POST['data'] ?? '';

// Cek token
if ($device_token !== $device_token_expected) {
    echo json_encode(["ok"=>false,"err"=>"no_token"]);
    exit;
}

// =====================
// ACTION LOG
// =====================
if ($action === 'log') {
    // Simpan ke logs dulu
    $stmt = $pdo->prepare("INSERT INTO logs (device_token, message, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$device_token, $data_raw]);

    // Parse data menggunakan regex untuk log_access
    // Format yang dicari: {"uid":"...","time":"...","granted":true/false,"mask":"..."}
    $pattern_uid = '/"uid"\s*:\s*"([^"]+)"/i';
    $pattern_time = '/"time"\s*:\s*"([^"]+)"/i';
    $pattern_granted = '/"granted"\s*:\s*(true|false)/i';
    $pattern_mask = '/"mask"\s*:\s*"([^"]*)"/i';

    $uid = $time = $granted = $mask = null;

    if (preg_match($pattern_uid, $data_raw, $m)) $uid = $m[1];
    if (preg_match($pattern_time, $data_raw, $m)) $time = $m[1];
    if (preg_match($pattern_granted, $data_raw, $m)) $granted = strtolower($m[1]) === 'true' ? 1 : 0;
    if (preg_match($pattern_mask, $data_raw, $m)) $mask = $m[1];

    if ($uid && $time) {
        $stmt2 = $pdo->prepare("INSERT INTO log_access (device_token, uid, time, granted, mask) VALUES (?, ?, ?, ?, ?)");
        $stmt2->execute([$device_token, $uid, $time, $granted, $mask]);
    }

    echo json_encode(["ok"=>true,"msg"=>"log_saved"]);
    exit;
}

// =====================
// ACTION RELAY
// =====================
elseif ($action === 'relay') {
    $stmt = $pdo->prepare("INSERT INTO relay_states (device_token, state_csv, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$device_token, $data_raw]);

    $up = $pdo->prepare("INSERT INTO devices (device_token, relay_state, updated_at) VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE relay_state=VALUES(relay_state), updated_at=NOW()");
    $up->execute([$device_token, $data_raw]);

    echo json_encode(["ok"=>true,"msg"=>"relay_saved"]);
    exit;
}

// =====================
// ACTION GET_COMMANDS
// =====================
elseif ($action === 'get_commands') {
    $stmt = $pdo->prepare("SELECT id, command_type, payload FROM commands WHERE device_token = ? AND processed = 0 ORDER BY id ASC");
    $stmt->execute([$device_token]);
    $cmds = $stmt->fetchAll();
    $out = [];

    foreach ($cmds as $c) {
        $payload = json_decode($c['payload'], true);
        if ($c['command_type'] === 'relay') {
            $out[] = [
                'id' => (int)$c['id'],
                'type' => 'relay',
                'idx' => (int)($payload['idx'] ?? -1),
                'state' => (int)($payload['state'] ?? 0)
            ];
        }
        $u = $pdo->prepare("UPDATE commands SET processed = 1, processed_at = NOW() WHERE id = ?");
        $u->execute([$c['id']]);
    }

    echo json_encode(["ok"=>true,"commands"=>$out]);
    exit;
}

// =====================
// DEFAULT: recent logs & device status
// =====================
else {
    $logs = $pdo->prepare("SELECT message, created_at FROM logs WHERE device_token = ? ORDER BY id DESC LIMIT 50");
    $logs->execute([$device_token]);
    $logs_r = $logs->fetchAll();

    $device = $pdo->prepare("SELECT relay_state, updated_at FROM devices WHERE device_token = ?");
    $device->execute([$device_token]);
    $device_r = $device->fetch();

    echo json_encode(["ok"=>true,"logs"=>$logs_r, "device" => $device_r]);
    exit;
}

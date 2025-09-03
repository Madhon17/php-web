<?php
header('Content-Type: application/json; charset=utf-8');
require 'connect.php'; // harus menyiapkan $pdo (PDO)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["ok"=>false,"err"=>"only_post"]);
    exit;
}

$device_token = $_POST['device_token'] ?? '';
$action = $_POST['action'] ?? '';
$data = $_POST['data'] ?? '';

if (empty($device_token) || empty($action)) {
    echo json_encode(["ok"=>false,"err"=>"missing_params"]);
    exit;
}

// Optional: periksa device_token sesuai ekspektasi (atau cek di DB)
$expected = "6c108269";
if ($device_token !== $expected) {
    echo json_encode(["ok"=>false,"err"=>"invalid_token"]);
    exit;
}

try {
    if ($action === 'log') {
        // data: JSON string dari ESP (uid/time/granted/mask)
        $stmt = $pdo->prepare("INSERT INTO logs (device_token, message, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$device_token, $data]);
        echo json_encode(["ok"=>true,"msg"=>"log_saved"]);
        exit;
    }

    if ($action === 'relay') {
        // data: CSV state, simpan ke tabel relay_states
        $stmt = $pdo->prepare("INSERT INTO relay_states (device_token, state_csv, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$device_token, $data]);

        // Update latest device table (optional)
        $up = $pdo->prepare("INSERT INTO devices (device_token, relay_state, updated_at) VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE relay_state=VALUES(relay_state), updated_at=NOW()");
        $up->execute([$device_token, $data]);

        echo json_encode(["ok"=>true,"msg"=>"relay_saved"]);
        exit;
    }

    if ($action === 'get_commands') {
        // Ambil command belum diproses (0) untuk device ini
        $stmt = $pdo->prepare("SELECT id, command_type, payload FROM commands WHERE device_token = ? AND processed = 0 ORDER BY id ASC");
        $stmt->execute([$device_token]);
        $cmds = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($cmds as $c) {
            $payload = json_decode($c['payload'], true);
            if ($c['command_type'] === 'relay' && is_array($payload)) {
                $out[] = [
                    'id' => (int)$c['id'],
                    'type' => 'relay',
                    'idx' => (int)($payload['idx'] ?? -1),
                    'state' => (int)($payload['state'] ?? 0)
                ];
            }
            // mark processed
            $u = $pdo->prepare("UPDATE commands SET processed = 1, processed_at = NOW() WHERE id = ?");
            $u->execute([(int)$c['id']]);
        }
        echo json_encode(["ok"=>true,"commands"=>$out]);
        exit;
    }

    echo json_encode(["ok"=>false,"err"=>"unknown_action"]);
} catch (Exception $e) {
    echo json_encode(["ok"=>false,"err"=>$e->getMessage()]);
}

<?php
header('Content-Type: application/json; charset=utf-8');
require 'connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["ok"=>false,"err"=>"only_post"]);
    exit;
}

$uid = $_POST['uid'] ?? '';
$name = $_POST['name'] ?? '';

if (empty($uid)) {
    echo json_encode(["ok"=>false,"err"=>"missing_uid"]);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO users (uid, name) VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE name=VALUES(name)");
    $stmt->execute([$uid, $name]);
    echo json_encode(["ok"=>true,"msg"=>"user_saved"]);
} catch (Exception $e) {
    echo json_encode(["ok"=>false,"err"=>$e->getMessage()]);
}

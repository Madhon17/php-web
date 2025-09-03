<?php
// delete_user.php
require 'connect.php';
header('Content-Type: application/json');

$uid = $_POST['uid'] ?? '';

if ($uid === '') {
    echo json_encode(['ok'=>false,'err'=>'empty_uid']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE uid=?");
    $stmt->execute([$uid]);

    echo json_encode(['ok'=>true,'msg'=>'deleted']);
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'err'=>$e->getMessage()]);
}

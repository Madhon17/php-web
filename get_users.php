<?php
header('Content-Type: application/json; charset=utf-8');
require 'connect.php';

$users = $pdo->query("SELECT uid, name FROM users ORDER BY uid ASC")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode(["ok"=>true,"users"=>$users]);

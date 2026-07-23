<?php
require_once 'config.php';
$stmt = $pdo->prepare("SELECT t.*, u.full_name as assigned_name, c.full_name as creator_name, d.name as division_name 
                       FROM tasks t 
                       LEFT JOIN users u ON t.assigned_to = u.id 
                       LEFT JOIN users c ON t.created_by = c.id
                       LEFT JOIN divisions d ON t.division_id = d.id
                       WHERE t.id = ?");
$stmt->execute([9]);
var_dump($stmt->fetch(PDO::FETCH_ASSOC));

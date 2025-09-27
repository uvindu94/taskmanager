<?php
require_once 'config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Check if task_id is provided
if (!isset($_GET['task_id']) || !is_numeric($_GET['task_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit;
}

$task_id = (int)$_GET['task_id'];

try {
    // First, verify user has permission to view this task
    $taskStmt = $pdo->prepare("SELECT assigned_to, created_by FROM tasks WHERE id = ?");
    $taskStmt->execute([$task_id]);
    $task = $taskStmt->fetch();

    if (!$task) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        exit;
    }

    // Check permissions
    $canViewAll = in_array('view_all', $rolePermissions[$role] ?? []);
    $canViewTeam = in_array('view_team', $rolePermissions[$role] ?? []);

    if (!$canViewAll && !($task['assigned_to'] == $user_id || $task['created_by'] == $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Permission denied']);
        exit;
    }

    // Fetch task history
    $stmt = $pdo->prepare("
        SELECT h.*, u.full_name 
        FROM task_history h 
        JOIN users u ON h.performed_by = u.id 
        WHERE h.task_id = ? 
        ORDER BY h.performed_at DESC
    ");
    $stmt->execute([$task_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'history' => $history
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

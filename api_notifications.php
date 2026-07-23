<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}
require_once 'config.php';

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if ($action === 'count') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    echo json_encode(['count' => (int)$stmt->fetchColumn()]);
    exit;
}

if ($action === 'mark_all_read') {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'list') {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($notifications)) {
        echo '<div class="p-4 text-center text-sm text-slate-500">No new notifications</div>';
        exit;
    }
    
    foreach ($notifications as $n) {
        $bg = $n['is_read'] ? 'bg-white' : 'bg-brand-50';
        $icon = 'fa-bell';
        $color = 'text-brand-500';
        
        switch($n['type']) {
            case 'assigned': $icon = 'fa-tasks'; $color = 'text-blue-500'; break;
            case 'forwarded': $icon = 'fa-share'; $color = 'text-purple-500'; break;
            case 'completed': $icon = 'fa-check-circle'; $color = 'text-green-500'; break;
            case 'reopened': $icon = 'fa-undo'; $color = 'text-red-500'; break;
        }
        
        $link = $n['task_id'] ? "task_details.php?id={$n['task_id']}" : "#";
        
        echo "<a href='{$link}' class='block p-4 border-b border-slate-100 hover:bg-slate-50 transition-colors {$bg}'>
                <div class='flex gap-3'>
                    <div class='w-8 h-8 rounded-full bg-white border border-slate-100 flex items-center justify-center shrink-0 shadow-sm'>
                        <i class='fas {$icon} {$color}'></i>
                    </div>
                    <div>
                        <p class='text-sm text-slate-800 font-medium line-clamp-2'>" . htmlspecialchars($n['message']) . "</p>
                        <p class='text-xs text-slate-400 mt-1'>" . date('M j, g:i A', strtotime($n['created_at'])) . "</p>
                    </div>
                </div>
              </a>";
    }
    exit;
}
?>

<?php
require_once 'header.php';

$user_id = $_SESSION['user_id'];

// Mark all as read when visiting this page
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$user_id]);

// Fetch all notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Notifications</h1>
        <p class="text-slate-500 text-sm mt-1">Your recent activity and updates.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="divide-y divide-slate-100">
            <?php if (count($notifications) === 0): ?>
                <div class="p-8 text-center text-slate-500">
                    <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-bell-slash text-2xl text-slate-300"></i>
                    </div>
                    <p>No notifications yet.</p>
                </div>
            <?php endif; ?>
            
            <?php foreach ($notifications as $n): 
                $icon = 'fa-bell';
                $color = 'text-brand-500';
                $bg_color = 'bg-brand-50';
                
                switch($n['type']) {
                    case 'assigned': $icon = 'fa-tasks'; $color = 'text-blue-500'; $bg_color = 'bg-blue-50'; break;
                    case 'forwarded': $icon = 'fa-share'; $color = 'text-purple-500'; $bg_color = 'bg-purple-50'; break;
                    case 'completed': $icon = 'fa-check-circle'; $color = 'text-green-500'; $bg_color = 'bg-green-50'; break;
                    case 'reopened': $icon = 'fa-undo'; $color = 'text-red-500'; $bg_color = 'bg-red-50'; break;
                }
                
                $link = $n['task_id'] ? "task_details.php?id={$n['task_id']}" : "#";
            ?>
                <a href="<?= $link ?>" class="flex items-start gap-4 p-5 hover:bg-slate-50 transition-colors group">
                    <div class="w-10 h-10 rounded-full <?= $bg_color ?> flex items-center justify-center shrink-0 shadow-sm border border-white">
                        <i class="fas <?= $icon ?> <?= $color ?>"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-slate-800 font-medium group-hover:text-brand-600 transition-colors"><?= htmlspecialchars($n['message']) ?></p>
                        <p class="text-sm text-slate-500 mt-1"><?= date('F j, Y \a\t g:i A', strtotime($n['created_at'])) ?></p>
                    </div>
                    <?php if ($n['task_id']): ?>
                    <div class="shrink-0 self-center">
                        <i class="fas fa-chevron-right text-slate-300 group-hover:text-brand-500 transition-colors"></i>
                    </div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

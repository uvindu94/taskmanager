<?php
require_once 'header.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle Password Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Verify current password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $hash = $stmt->fetchColumn();
    
    if (!password_verify($current_password, $hash)) {
        $error = "Current password is incorrect.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_hash, $user_id]);
        $success = "Your password has been changed successfully.";
    }
}

// Fetch User Info
$stmt = $pdo->prepare("SELECT u.*, d.name as division_name, ds.name as designation_name
                       FROM users u
                       LEFT JOIN divisions d ON u.division_id = d.id
                       LEFT JOIN designations ds ON u.designation_id = ds.id
                       WHERE u.id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Recent Activities
$activities = [];

// Tasks completed
$stmt = $pdo->prepare("SELECT id, title, completed_at as date FROM tasks WHERE assigned_to = ? AND status = 'completed' AND completed_at IS NOT NULL ORDER BY completed_at DESC LIMIT 5");
$stmt->execute([$user_id]);
foreach($stmt->fetchAll() as $row) {
    $activities[] = [
        'icon' => 'fa-check-circle',
        'color' => 'text-green-600',
        'bg' => 'bg-green-100',
        'title' => 'Completed a Task',
        'description' => "You completed the task <strong>" . htmlspecialchars($row['title']) . "</strong>.",
        'date' => $row['date'],
        'link' => "task_details.php?id={$row['id']}"
    ];
}

// Projects created
$stmt = $pdo->prepare("SELECT id, project, created_at as date FROM projects WHERE created_by = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
foreach($stmt->fetchAll() as $row) {
    $activities[] = [
        'icon' => 'fa-project-diagram',
        'color' => 'text-brand-600',
        'bg' => 'bg-brand-100',
        'title' => 'Created a Project',
        'description' => "You created a new project: <strong>" . htmlspecialchars($row['project']) . "</strong>.",
        'date' => $row['date'],
        'link' => "project_details.php?id={$row['id']}"
    ];
}

// Remarks left on tasks
$stmt = $pdo->prepare("SELECT r.id, r.task_id, r.remark, r.created_at as date, t.title 
                       FROM task_remarks r 
                       JOIN tasks t ON r.task_id = t.id 
                       WHERE r.created_by = ? AND r.remark NOT LIKE '<em>%' ORDER BY r.created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
foreach($stmt->fetchAll() as $row) {
    $activities[] = [
        'icon' => 'fa-comment-alt',
        'color' => 'text-indigo-600',
        'bg' => 'bg-indigo-100',
        'title' => 'Commented on a Task',
        'description' => "You added a remark to <strong>" . htmlspecialchars($row['title']) . "</strong>.",
        'date' => $row['date'],
        'link' => "task_details.php?id={$row['task_id']}"
    ];
}

// Sort activities by date descending
usort($activities, function($a, $b) {
    return strtotime($b['date']) <=> strtotime($a['date']);
});
$activities = array_slice($activities, 0, 10);
?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">My Profile</h1>
            <p class="text-slate-500 text-sm mt-1">Manage your account settings and view your activity.</p>
        </div>
    </div>
    
    <?php if ($success): ?>
        <div x-data="{ show: true }" x-show="show" class="bg-green-50 text-green-700 p-4 rounded-xl text-sm font-medium border border-green-100 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <i class="fas fa-check-circle text-green-500 text-lg"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <button @click="show = false" class="text-green-500 hover:text-green-700"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-600 p-4 rounded-xl text-sm font-medium border border-red-100 flex items-center gap-3 shadow-sm">
            <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left Column: Profile Info & Password -->
        <div class="lg:col-span-1 space-y-6">
            
            <!-- Profile Card -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-r from-brand-500 to-brand-600"></div>
                <div class="relative flex flex-col items-center mt-8">
                    <div class="w-24 h-24 rounded-full bg-white p-1 shadow-md mb-4">
                        <div class="w-full h-full rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-4xl font-bold">
                            <?= strtoupper(substr($user_info['full_name'], 0, 1)) ?>
                        </div>
                    </div>
                    <h2 class="text-xl font-bold text-slate-800 text-center"><?= htmlspecialchars($user_info['full_name']) ?></h2>
                    <p class="text-brand-600 font-medium text-sm text-center mb-6"><?= htmlspecialchars($user_info['designation_name'] ?? 'Team Member') ?></p>
                    
                    <div class="w-full space-y-4">
                        <div class="flex flex-col border-b border-slate-100 pb-3">
                            <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">Username</span>
                            <span class="text-sm text-slate-800 font-medium">@<?= htmlspecialchars($user_info['username']) ?></span>
                        </div>
                        <?php if($user_info['email']): ?>
                        <div class="flex flex-col border-b border-slate-100 pb-3">
                            <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">Email</span>
                            <span class="text-sm text-slate-800 font-medium"><?= htmlspecialchars($user_info['email']) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex flex-col border-b border-slate-100 pb-3">
                            <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">Role</span>
                            <span class="text-sm text-slate-800 font-medium capitalize"><?= str_replace('_', ' ', $user_info['role']) ?></span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-1">Division</span>
                            <span class="text-sm text-slate-800 font-medium"><?= htmlspecialchars($user_info['division_name'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Change Password Card -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-lock text-brand-500"></i> Security
                </h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Current Password</label>
                        <input type="password" name="current_password" required
                               class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white transition-all text-slate-800 text-sm">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">New Password</label>
                        <input type="password" name="new_password" required minlength="6"
                               class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white transition-all text-slate-800 text-sm">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="6"
                               class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white transition-all text-slate-800 text-sm">
                    </div>
                    
                    <button type="submit" class="w-full px-4 py-2.5 bg-brand-600 hover:bg-brand-700 text-white font-medium rounded-xl transition-colors shadow-sm focus:ring-2 focus:ring-brand-500 focus:ring-offset-1 mt-2">
                        Update Password
                    </button>
                </form>
            </div>
            
        </div>
        
        <!-- Right Column: Recent Activity -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl p-6 md:p-8 border border-slate-200 shadow-sm h-full">
                <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2 border-b border-slate-100 pb-4">
                    <i class="fas fa-history text-brand-500"></i> Recent Activity
                </h3>
                
                <?php if (empty($activities)): ?>
                    <div class="text-center py-12">
                        <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center text-2xl mx-auto mb-4">
                            <i class="fas fa-wind"></i>
                        </div>
                        <h4 class="text-slate-700 font-medium mb-1">No recent activity</h4>
                        <p class="text-slate-500 text-sm">Your recent tasks, projects, and comments will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="relative border-l-2 border-slate-100 ml-4 space-y-8 pb-4">
                        <?php foreach($activities as $activity): ?>
                            <div class="relative pl-6 sm:pl-8 group">
                                <!-- Timeline Dot -->
                                <div class="absolute -left-[17px] top-1 w-8 h-8 rounded-full <?= $activity['bg'] ?> <?= $activity['color'] ?> flex items-center justify-center border-4 border-white shadow-sm ring-1 ring-slate-100 group-hover:scale-110 transition-transform">
                                    <i class="fas <?= $activity['icon'] ?> text-xs"></i>
                                </div>
                                
                                <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 hover:border-slate-200 transition-colors shadow-sm">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-2 gap-2">
                                        <h4 class="font-bold text-slate-800 text-sm"><?= $activity['title'] ?></h4>
                                        <span class="text-xs font-medium text-slate-400 bg-white px-2.5 py-1 rounded-md border border-slate-100 shadow-sm shrink-0">
                                            <?= date('M j, Y \a\t g:i a', strtotime($activity['date'])) ?>
                                        </span>
                                    </div>
                                    <p class="text-slate-600 text-sm mb-3">
                                        <?= $activity['description'] ?>
                                    </p>
                                    <a href="<?= $activity['link'] ?>" class="inline-flex items-center text-xs font-semibold text-brand-600 hover:text-brand-700 transition-colors">
                                        View Details <i class="fas fa-arrow-right ml-1 text-[10px]"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<?php require_once 'footer.php'; ?>
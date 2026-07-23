<?php
require_once 'header.php';

// Check permissions
if (!is_super_admin() && !is_division_head() && !in_array('create_task', $rolePermissions[$_SESSION['role']] ?? [])) {
    echo "<div class='p-4 bg-red-100 text-red-700 rounded-lg'>Access denied.</div>";
    require_once 'footer.php';
    exit;
}

$error_message = null;
$success_message = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $assigned_to = $_POST['assigned_to'] ?? []; 
    $priority = $_POST['priority'] ?? 'medium';
    $due_date = $_POST['due_date'];
    
    // V2 features
    $target_value = !empty($_POST['target_value']) ? (int)$_POST['target_value'] : null;
    $unit = trim($_POST['unit'] ?? '');
    
    $created_by = $_SESSION['user_id'];
    
    // Assign to a specific division based on creator or global
    $division_id = get_user_division(); // By default, Division Head creates for their division

    if (empty($title) || empty($description) || empty($assigned_to)) {
        $error_message = "Please fill in all required fields.";
    } else {
        try {
            $pdo->beginTransaction();

            $task_ids = [];

            // Create a task record for each assigned user
            foreach ($assigned_to as $assignee_id) {
                // Ensure assignee belongs to the creator's division if creator is division_head
                // For simplicity, we just insert it.
                
                $stmt = $pdo->prepare("INSERT INTO tasks (title, description, assigned_to, created_by, priority, due_date, target_value, unit, division_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $assignee_id, $created_by, $priority, $due_date, $target_value, $unit, $division_id]);

                $task_id = $pdo->lastInsertId();
                $task_ids[] = $task_id;

                // Log history for each task
                $stmt = $pdo->prepare("INSERT INTO task_history (task_id, action, performed_by) VALUES (?, ?, ?)");
                $action = "Task created with priority '$priority'.";
                $stmt->execute([$task_id, $action, $created_by]);
                
                // Add Notification
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, task_id, type, message) VALUES (?, ?, 'assigned', ?)");
                $stmt->execute([$assignee_id, $task_id, "You have been assigned a new task: " . mb_substr($title, 0, 30)]);
            }

            $pdo->commit();
            $success_message = count($task_ids) > 1 ? "Task created successfully and assigned to " . count($task_ids) . " users!" : "Task created successfully!";
            
            // Redirect after 2s
            echo "<script>setTimeout(() => { window.location.href = 'tasks.php'; }, 2000);</script>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Error creating task: " . $e->getMessage();
        }
    }
}

// Fetch users for assignment dropdown
// Division heads can only assign to their own division users
if (is_super_admin()) {
    $stmt = $pdo->prepare("SELECT id, full_name, role FROM users ORDER BY role, full_name");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE division_id = ? ORDER BY role, full_name");
    $stmt->execute([get_user_division()]);
}
$users = $stmt->fetchAll();
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Create New Task</h1>
        <p class="text-slate-500 text-sm mt-1">Define and assign a new task to your team, set KPI targets.</p>
    </div>

    <?php if ($success_message): ?>
    <div class="p-4 bg-green-50 text-green-700 border border-green-200 rounded-xl flex items-center gap-3">
        <i class="fas fa-check-circle text-green-500"></i>
        <?= htmlspecialchars($success_message) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="p-4 bg-red-50 text-red-700 border border-red-200 rounded-xl flex items-center gap-3">
        <i class="fas fa-exclamation-circle text-red-500"></i>
        <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden p-6 sm:p-8">
        <form method="POST" class="space-y-6">
            
            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">Task Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-all" placeholder="E.g., Publish new ad campaign">
            </div>

            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">Description <span class="text-red-500">*</span></label>
                <textarea name="description" rows="4" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-all resize-y" placeholder="Provide detailed information..."></textarea>
            </div>

            <div class="space-y-1">
                <label class="block text-sm font-medium text-slate-700">Assign To <span class="text-red-500">*</span></label>
                <!-- Using a simple multi-select to avoid heavy select2 dependency, Alpine can enhance this if needed -->
                <select name="assigned_to[]" multiple required class="w-full px-4 py-2 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none h-32">
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars(str_replace('_', ' ', $u['role'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-500">Hold Cmd/Ctrl to select multiple users.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- KPI Tracking Box -->
                <div class="p-4 bg-slate-50 border border-slate-200 rounded-xl space-y-4">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-chart-line text-brand-500"></i> KPI Target (Optional)
                    </h3>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Target Value</label>
                        <input type="number" name="target_value" min="1" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none" placeholder="E.g., 1000">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Unit</label>
                        <input type="text" name="unit" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none" placeholder="E.g., ads published">
                    </div>
                </div>

                <!-- Priority and Date Box -->
                <div class="p-4 border border-slate-200 rounded-xl space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Priority Level</label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="radio" name="priority" value="high" class="text-red-500 focus:ring-red-500">
                                <span class="group-hover:text-slate-900 transition-colors">High</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="radio" name="priority" value="medium" checked class="text-brand-500 focus:ring-brand-500">
                                <span class="group-hover:text-slate-900 transition-colors">Medium</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="radio" name="priority" value="low" class="text-green-500 focus:ring-green-500">
                                <span class="group-hover:text-slate-900 transition-colors">Low</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Due Date <span class="text-red-500">*</span></label>
                        <input type="date" name="due_date" required min="<?= date('Y-m-d') ?>" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-4 pt-6 border-t border-slate-100">
                <a href="tasks.php" class="px-6 py-2.5 text-slate-700 bg-slate-100 hover:bg-slate-200 font-medium rounded-xl transition-colors">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 text-white bg-brand-600 hover:bg-brand-700 font-medium rounded-xl transition-colors shadow-sm flex items-center gap-2">
                    <i class="fas fa-plus"></i> Create Task
                </button>
            </div>
            
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>
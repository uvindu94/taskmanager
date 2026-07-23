<?php
require_once 'header.php';

$task_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];
$division_id = get_user_division();

// Fetch Task details
$stmt = $pdo->prepare("SELECT t.*, u.full_name as assigned_name, c.full_name as creator_name, d.name as division_name 
                       FROM tasks t 
                       LEFT JOIN users u ON t.assigned_to = u.id 
                       LEFT JOIN users c ON t.created_by = c.id
                       LEFT JOIN divisions d ON t.division_id = d.id
                       WHERE t.id = ?");
$stmt->execute([$task_id]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    echo "<div class='p-4 bg-red-100 text-red-700 rounded-lg max-w-4xl mx-auto'>Task not found.</div>";
    require_once 'footer.php';
    exit;
}

// Access Control
$is_assignee = $task['assigned_to'] == $user_id;
$is_creator = $task['created_by'] == $user_id;
$is_same_division = $task['division_id'] == $division_id;
$can_view = $is_assignee || $is_creator || is_super_admin() || (is_division_head() && $is_same_division);

if (!$can_view) {
    echo "<div class='p-4 bg-red-100 text-red-700 rounded-lg max-w-4xl mx-auto'>Access denied.</div>";
    require_once 'footer.php';
    exit;
}

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'add_progress' && $is_assignee && in_array($task['status'], ['pending', 'in_progress', 'forwarded', 'reopened'])) {
            $date = $_POST['date'];
            $value = (int)$_POST['achievement_value'];
            
            if ($value > 0) {
                $stmt = $pdo->prepare("INSERT INTO task_progress (task_id, date, achievement_value, updated_by) VALUES (?, ?, ?, ?)");
                $stmt->execute([$task_id, $date, $value, $user_id]);
                
                if ($task['status'] === 'pending') {
                    $pdo->prepare("UPDATE tasks SET status = 'in_progress' WHERE id = ?")->execute([$task_id]);
                    $pdo->prepare("INSERT INTO task_status_history (task_id, changed_by, old_status, new_status) VALUES (?, ?, ?, ?)")->execute([$task_id, $user_id, 'pending', 'in_progress']);
                }
                
                $message = "Progress updated successfully!";
                // Refresh task status
                $task['status'] = $task['status'] === 'pending' ? 'in_progress' : $task['status'];
            }
        } 
        elseif ($action === 'mark_complete' && $is_assignee) {
            $pdo->prepare("UPDATE tasks SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$task_id]);
            $pdo->prepare("INSERT INTO task_status_history (task_id, changed_by, old_status, new_status) VALUES (?, ?, ?, ?)")->execute([$task_id, $user_id, $task['status'], 'completed']);
            $pdo->prepare("INSERT INTO task_history (task_id, action, performed_by) VALUES (?, ?, ?)")->execute([$task_id, "Marked task as completed", $user_id]);
            
            // Notify Creator
            $pdo->prepare("INSERT INTO notifications (user_id, task_id, type, message) VALUES (?, ?, 'completed', ?)")->execute([$task['created_by'], $task_id, "Task completed: " . mb_substr($task['title'], 0, 30)]);
            
            $message = "Task marked as completed.";
            $task['status'] = 'completed';
        }
        elseif ($action === 'reopen' && ($is_creator || is_super_admin() || (is_division_head() && $is_same_division))) {
            $pdo->prepare("UPDATE tasks SET status = 'reopened', reopened_at = NOW() WHERE id = ?")->execute([$task_id]);
            $pdo->prepare("INSERT INTO task_status_history (task_id, changed_by, old_status, new_status) VALUES (?, ?, ?, ?)")->execute([$task_id, $user_id, 'completed', 'reopened']);
            $pdo->prepare("INSERT INTO task_history (task_id, action, performed_by) VALUES (?, ?, ?)")->execute([$task_id, "Reopened task", $user_id]);
            
            // Notify Assignee
            $pdo->prepare("INSERT INTO notifications (user_id, task_id, type, message) VALUES (?, ?, 'reopened', ?)")->execute([$task['assigned_to'], $task_id, "Task reopened: " . mb_substr($task['title'], 0, 30)]);
            
            $message = "Task has been reopened.";
            $task['status'] = 'reopened';
        }
        elseif ($action === 'forward' && $is_assignee) {
            $to_user = (int)$_POST['to_user'];
            $reason = trim($_POST['reason']);
            
            // Validate to_user is in same division
            $stmt = $pdo->prepare("SELECT id, role, division_id FROM users WHERE id = ?");
            $stmt->execute([$to_user]);
            $target = $stmt->fetch();
            
            if ($target && ($target['division_id'] == $division_id || $target['role'] === 'super_admin')) {
                $old_assigned = $task['assigned_to'];
                $pdo->prepare("UPDATE tasks SET assigned_to = ?, status = 'forwarded' WHERE id = ?")->execute([$to_user, $task_id]);
                $pdo->prepare("INSERT INTO task_forward_history (task_id, from_user, to_user, reason) VALUES (?, ?, ?, ?)")->execute([$task_id, $user_id, $to_user, $reason]);
                $pdo->prepare("INSERT INTO task_status_history (task_id, changed_by, old_status, new_status) VALUES (?, ?, ?, ?)")->execute([$task_id, $user_id, $task['status'], 'forwarded']);
                
                // Notify new assignee
                $pdo->prepare("INSERT INTO notifications (user_id, task_id, type, message) VALUES (?, ?, 'forwarded', ?)")->execute([$to_user, $task_id, "Task forwarded to you: " . mb_substr($task['title'], 0, 30)]);
                
                $message = "Task forwarded successfully.";
                $task['assigned_to'] = $to_user;
                $task['status'] = 'forwarded';
                $is_assignee = false; // no longer assignee
            } else {
                $error = "Invalid target user. Can only forward within the same division.";
            }
        }
        elseif ($action === 'add_remark' && ($is_assignee || (is_division_head() && $is_same_division) || is_super_admin())) {
            $remark = trim($_POST['remark']);
            if ($remark) {
                $pdo->prepare("INSERT INTO task_remarks (task_id, created_by, remark) VALUES (?, ?, ?)")->execute([$task_id, $user_id, $remark]);
                
                // Notify the other party (if assignee posts, notify creator. If creator/head posts, notify assignee)
                $notify_user = $is_assignee ? $task['created_by'] : $task['assigned_to'];
                if ($notify_user != $user_id) {
                    $pdo->prepare("INSERT INTO notifications (user_id, task_id, type, message) VALUES (?, ?, 'assigned', ?)")->execute([$notify_user, $task_id, "New remark on task: " . mb_substr($task['title'], 0, 30)]);
                }
                
                $message = "Remark added successfully.";
            }
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Progress History
$stmt = $pdo->prepare("SELECT p.*, u.full_name FROM task_progress p JOIN users u ON p.updated_by = u.id WHERE task_id = ? ORDER BY date DESC, p.created_at DESC");
$stmt->execute([$task_id]);
$progress_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total achieved
$total_achieved = array_sum(array_column($progress_logs, 'achievement_value'));
$progress_percent = $task['target_value'] > 0 ? min(100, round(($total_achieved / $task['target_value']) * 100)) : ($task['status'] === 'completed' ? 100 : 0);

// Fetch Forward History
$stmt = $pdo->prepare("SELECT f.*, u1.full_name as from_name, u2.full_name as to_name FROM task_forward_history f JOIN users u1 ON f.from_user = u1.id JOIN users u2 ON f.to_user = u2.id WHERE task_id = ? ORDER BY forwarded_at DESC");
$stmt->execute([$task_id]);
$forward_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Remarks
$stmt = $pdo->prepare("SELECT r.*, u.full_name, u.role, u.designation_id FROM task_remarks r JOIN users u ON r.created_by = u.id WHERE task_id = ? ORDER BY r.created_at ASC");
$stmt->execute([$task_id]);
$remarks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Division Users for Forwarding
$division_users = [];
if ($is_assignee) {
    $stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE division_id = ? AND id != ? ORDER BY full_name");
    $stmt->execute([$division_id, $user_id]);
    $division_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatusBadgeDetails($status) {
    $badges = [
        'pending' => '<span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-sm font-semibold border border-slate-200"><i class="fas fa-clock mr-1"></i> Pending</span>',
        'in_progress' => '<span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-full text-sm font-semibold border border-blue-200"><i class="fas fa-spinner fa-spin mr-1"></i> In Progress</span>',
        'completed' => '<span class="px-3 py-1 bg-green-50 text-green-600 rounded-full text-sm font-semibold border border-green-200"><i class="fas fa-check-circle mr-1"></i> Completed</span>',
        'forwarded' => '<span class="px-3 py-1 bg-purple-50 text-purple-600 rounded-full text-sm font-semibold border border-purple-200"><i class="fas fa-share mr-1"></i> Forwarded</span>',
        'reopened' => '<span class="px-3 py-1 bg-red-50 text-red-600 rounded-full text-sm font-semibold border border-red-200"><i class="fas fa-undo mr-1"></i> Reopened</span>'
    ];
    return $badges[$status] ?? $badges['pending'];
}
?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center gap-4 text-sm text-slate-500 mb-2">
        <a href="tasks.php" class="hover:text-brand-600 transition-colors"><i class="fas fa-arrow-left mr-1"></i> Back to Tasks</a>
        <span>/</span>
        <span>Task #<?= $task['id'] ?></span>
    </div>
    
    <?php if ($message): ?>
    <div class="p-4 bg-green-50 text-green-700 border border-green-200 rounded-xl flex items-center gap-3">
        <i class="fas fa-check-circle text-green-500"></i>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="p-4 bg-red-50 text-red-700 border border-red-200 rounded-xl flex items-center gap-3">
        <i class="fas fa-exclamation-circle text-red-500"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Header Card -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm relative overflow-hidden">
                <?php if($task['status'] === 'completed'): ?>
                    <div class="absolute top-0 right-0 w-32 h-32 bg-green-500/10 rounded-bl-full -mr-10 -mt-10 z-0"></div>
                <?php endif; ?>
                
                <div class="relative z-10">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                        <?= getStatusBadgeDetails($task['status']) ?>
                        <div class="text-sm text-slate-500 flex items-center gap-2">
                            <i class="far fa-calendar-alt"></i> Due <?= date('F j, Y', strtotime($task['due_date'])) ?>
                        </div>
                    </div>
                    
                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 mb-4"><?= htmlspecialchars($task['title']) ?></h1>
                    
                    <div class="prose prose-slate max-w-none text-slate-600">
                        <?= nl2br(htmlspecialchars($task['description'])) ?>
                    </div>
                </div>
            </div>

            <!-- KPI Tracking -->
            <?php if ($task['target_value'] > 0): ?>
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-chart-line text-brand-500"></i> KPI Tracking
                </h3>
                
                <div class="mb-6">
                    <div class="flex justify-between items-end mb-2">
                        <div>
                            <span class="text-3xl font-bold text-slate-900"><?= $total_achieved ?></span>
                            <span class="text-slate-500">/ <?= $task['target_value'] ?> <?= htmlspecialchars($task['unit']) ?></span>
                        </div>
                        <span class="text-xl font-bold <?= $progress_percent >= 100 ? 'text-green-500' : 'text-brand-500' ?>"><?= $progress_percent ?>%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
                        <div class="<?= $progress_percent >= 100 ? 'bg-green-500' : 'bg-brand-500' ?> h-3 rounded-full transition-all duration-1000" style="width: <?= $progress_percent ?>%"></div>
                    </div>
                </div>
                
                <?php if ($is_assignee && in_array($task['status'], ['pending', 'in_progress', 'forwarded', 'reopened'])): ?>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <form method="POST" class="flex flex-col sm:flex-row gap-3 items-end">
                        <input type="hidden" name="action" value="add_progress">
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wider">Date</label>
                            <input type="date" name="date" required value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none">
                        </div>
                        <div class="flex-1 w-full">
                            <label class="block text-xs font-medium text-slate-500 mb-1 uppercase tracking-wider">Achieved (<?= htmlspecialchars($task['unit']) ?>)</label>
                            <input type="number" name="achievement_value" required min="1" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500 outline-none" placeholder="Enter amount">
                        </div>
                        <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-medium rounded-lg transition-colors">
                            Log Progress
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                
                <!-- Progress History -->
                <?php if (count($progress_logs) > 0): ?>
                <div class="mt-6 space-y-3 max-h-60 overflow-y-auto pr-2">
                    <h4 class="text-sm font-semibold text-slate-700 uppercase tracking-wider">History</h4>
                    <?php foreach ($progress_logs as $log): ?>
                    <div class="flex items-center justify-between p-3 bg-white border border-slate-100 rounded-lg shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold text-xs">
                                +<?= $log['achievement_value'] ?>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-800">Logged on <?= date('M j, Y', strtotime($log['date'])) ?></p>
                                <p class="text-xs text-slate-500">by <?= htmlspecialchars($log['full_name']) ?></p>
                            </div>
                        </div>
                        <span class="text-xs text-slate-400"><?= date('h:i A', strtotime($log['created_at'])) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Forward History -->
            <?php if (count($forward_logs) > 0): ?>
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-share-alt text-brand-500"></i> Forwarding History
                </h3>
                <div class="space-y-4 relative before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-slate-300 before:to-transparent">
                    <?php foreach ($forward_logs as $log): ?>
                    <div class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white bg-purple-100 text-purple-500 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10">
                            <i class="fas fa-exchange-alt text-sm"></i>
                        </div>
                        <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-slate-800 text-sm">Forwarded to <?= htmlspecialchars($log['to_name']) ?></span>
                                <time class="text-xs text-slate-500"><?= date('M j, Y', strtotime($log['forwarded_at'])) ?></time>
                            </div>
                            <p class="text-xs text-slate-500 mb-2">by <?= htmlspecialchars($log['from_name']) ?></p>
                            <?php if ($log['reason']): ?>
                                <p class="text-sm text-slate-600 bg-slate-50 p-2 rounded italic">"<?= htmlspecialchars($log['reason']) ?>"</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Remarks / Discussion -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col h-[500px]">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2 pb-4 border-b border-slate-100 shrink-0">
                    <i class="fas fa-comments text-brand-500"></i> Discussion & Remarks
                </h3>
                
                <!-- Chat Area -->
                <div class="flex-1 overflow-y-auto space-y-6 pr-2 mb-4">
                    <?php if (count($remarks) === 0): ?>
                        <div class="h-full flex flex-col items-center justify-center text-slate-400">
                            <i class="far fa-comment-dots text-4xl mb-3 text-slate-200"></i>
                            <p>No remarks yet. Start the conversation!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($remarks as $rem): 
                            $is_mine = $rem['created_by'] == $user_id;
                        ?>
                            <div class="flex gap-4 <?= $is_mine ? 'flex-row-reverse' : '' ?>">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm shrink-0 shadow-sm border border-white <?= $is_mine ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-700' ?>">
                                    <?= strtoupper(substr($rem['full_name'], 0, 1)) ?>
                                </div>
                                <div class="flex flex-col <?= $is_mine ? 'items-end' : 'items-start' ?> max-w-[80%]">
                                    <div class="flex items-baseline gap-2 mb-1">
                                        <span class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($rem['full_name']) ?></span>
                                        <span class="text-xs text-slate-400"><?= date('M j, g:i A', strtotime($rem['created_at'])) ?></span>
                                    </div>
                                    <div class="p-3.5 rounded-2xl text-sm shadow-sm <?= $is_mine ? 'bg-brand-600 text-white rounded-tr-sm' : 'bg-slate-50 border border-slate-100 text-slate-700 rounded-tl-sm' ?>">
                                        <?= nl2br(htmlspecialchars($rem['remark'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <!-- Auto-scroll anchor -->
                        <div id="remarks-bottom"></div>
                        <script>document.getElementById('remarks-bottom').scrollIntoView();</script>
                    <?php endif; ?>
                </div>

                <!-- Input Area -->
                <?php if ($is_assignee || (is_division_head() && $is_same_division) || is_super_admin()): ?>
                    <form method="POST" class="mt-auto shrink-0 relative">
                        <input type="hidden" name="action" value="add_remark">
                        <textarea name="remark" required rows="2" class="w-full pl-4 pr-14 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 focus:bg-white focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none transition-all resize-none shadow-sm" placeholder="Type your remark..."></textarea>
                        <button type="submit" class="absolute right-2 bottom-2 p-2 w-10 h-10 bg-brand-600 hover:bg-brand-700 text-white rounded-lg transition-colors flex items-center justify-center shadow-md">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        </div>

        <!-- Sidebar / Actions -->
        <div class="space-y-6">
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">Details</h3>
                <div class="space-y-4">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded bg-slate-50 flex items-center justify-center text-slate-400 mt-0.5"><i class="fas fa-user-circle"></i></div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wider">Assigned To</p>
                            <p class="font-medium text-slate-900"><?= htmlspecialchars($task['assigned_name']) ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded bg-slate-50 flex items-center justify-center text-slate-400 mt-0.5"><i class="fas fa-crown"></i></div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wider">Created By</p>
                            <p class="font-medium text-slate-900"><?= htmlspecialchars($task['creator_name']) ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded bg-slate-50 flex items-center justify-center text-slate-400 mt-0.5"><i class="fas fa-building"></i></div>
                        <div>
                            <p class="text-xs text-slate-500 uppercase tracking-wider">Division</p>
                            <p class="font-medium text-slate-900"><?= htmlspecialchars($task['division_name'] ?? 'Global') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider mb-4">Actions</h3>
                
                <div class="space-y-3">
                    <?php if ($is_assignee && $task['status'] !== 'completed'): ?>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to mark this task as completed?');">
                            <input type="hidden" name="action" value="mark_complete">
                            <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-green-50 hover:bg-green-100 text-green-700 font-medium rounded-xl transition-colors border border-green-200">
                                <i class="fas fa-check-circle"></i> Mark as Completed
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if (($is_creator || is_super_admin() || (is_division_head() && $is_same_division)) && $task['status'] === 'completed'): ?>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to reopen this task?');">
                            <input type="hidden" name="action" value="reopen">
                            <button type="submit" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-red-50 hover:bg-red-100 text-red-700 font-medium rounded-xl transition-colors border border-red-200">
                                <i class="fas fa-undo"></i> Reopen Task
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($is_assignee && $task['status'] !== 'completed'): ?>
                        <button @click="$dispatch('open-modal', 'forwardTask')" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-purple-50 hover:bg-purple-100 text-purple-700 font-medium rounded-xl transition-colors border border-purple-200">
                            <i class="fas fa-share"></i> Forward Task
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Forward Modal -->
<?php if ($is_assignee && $task['status'] !== 'completed'): ?>
<div x-data="{ open: false }" 
     x-on:open-modal.window="if ($event.detail === 'forwardTask') open = true"
     x-on:keydown.escape.window="open = false"
     x-cloak>
    
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" x-transition.opacity class="fixed inset-0 transition-opacity bg-slate-900/40 backdrop-blur-sm" @click="open = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div x-show="open" 
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6 border border-slate-100">
                
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-lg font-bold text-slate-900">Forward Task</h3>
                    <button @click="open = false" class="text-slate-400 hover:text-slate-500"><i class="fas fa-times"></i></button>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="forward">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Select User (Same Division)</label>
                            <select name="to_user" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none bg-white">
                                <option value="">-- Choose User --</option>
                                <?php foreach($division_users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars(str_replace('_',' ',$u['role'])) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Reason for Forwarding</label>
                            <textarea name="reason" rows="3" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none resize-none"></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button" @click="open = false" class="px-4 py-2 text-slate-700 bg-slate-100 hover:bg-slate-200 font-medium rounded-xl">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-white bg-brand-600 hover:bg-brand-700 font-medium rounded-xl">Forward Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>

<?php
require_once 'header.php';

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];
$is_super = is_super_admin();
$division_id = get_user_division();

if (!$project_id) {
    header("Location: projects.php");
    exit;
}

// Fetch project
$stmt = $pdo->prepare("SELECT p.*, c.full_name as creator_name, c.role as creator_role
                       FROM projects p 
                       LEFT JOIN users c ON p.created_by = c.id
                       WHERE p.id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    echo "<div class='p-8 max-w-2xl mx-auto mt-10 bg-red-50 text-red-600 rounded-xl border border-red-100 font-medium text-center shadow-sm'>Project not found.</div>";
    require_once 'footer.php';
    exit;
}

// Access Control
if (!$is_super && $project['division_id'] != $division_id && $project['created_by'] != $user_id) {
    echo "<div class='p-8 max-w-2xl mx-auto mt-10 bg-red-50 text-red-600 rounded-xl border border-red-100 font-medium text-center shadow-sm'>You do not have permission to view this project.</div>";
    require_once 'footer.php';
    exit;
}

$can_edit = false;
if ($is_super || is_division_head()) {
    $can_edit = true;
} elseif ($project['created_by'] == $user_id) {
    $can_edit = true;
}

// Handle Form Submissions
$success = isset($_GET['created']) ? 'Project created successfully!' : '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            $pdo->beginTransaction();
            
            if ($_POST['action'] === 'update_status' && $can_edit) {
                $status = $_POST['status'];
                $stmt = $pdo->prepare("UPDATE projects SET status = ? WHERE id = ?");
                $stmt->execute([$status, $project_id]);
                
                // Add system remark
                $stmt = $pdo->prepare("INSERT INTO project_remarks (project_id, created_by, remark) VALUES (?, ?, ?)");
                $stmt->execute([$project_id, $user_id, "<em>Changed status to <strong>" . ucwords(str_replace('_', ' ', $status)) . "</strong></em>"]);
                
                $success = "Project status updated.";
            } 
            elseif ($_POST['action'] === 'update_completion' && $can_edit) {
                $completion = (int)$_POST['completion'];
                if ($completion < 0) $completion = 0;
                if ($completion > 100) $completion = 100;
                
                $stmt = $pdo->prepare("UPDATE projects SET completion = ? WHERE id = ?");
                $stmt->execute([$completion, $project_id]);
                
                // Add system remark
                $stmt = $pdo->prepare("INSERT INTO project_remarks (project_id, created_by, remark) VALUES (?, ?, ?)");
                $stmt->execute([$project_id, $user_id, "<em>Updated completion rate to <strong>" . $completion . "%</strong></em>"]);
                
                $success = "Completion rate updated.";
            }
            elseif ($_POST['action'] === 'update_link' && $can_edit) {
                $link = trim($_POST['project_link']);
                
                $stmt = $pdo->prepare("UPDATE projects SET project_link = ? WHERE id = ?");
                $stmt->execute([$link ?: null, $project_id]);
                
                $success = "Project link updated.";
            }
            elseif ($_POST['action'] === 'add_remark') {
                $remark = trim($_POST['remark']);
                if ($remark) {
                    $stmt = $pdo->prepare("INSERT INTO project_remarks (project_id, created_by, remark) VALUES (?, ?, ?)");
                    $stmt->execute([$project_id, $user_id, $remark]);
                    $success = "Remark added successfully.";
                }
            }
            
            $pdo->commit();
            
            // Refresh project data
            $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
            $stmt->execute([$project_id]);
            $updated_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $project = array_merge($project, $updated_data);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error updating project: " . $e->getMessage();
        }
    }
}

// Fetch Remarks
$stmt = $pdo->prepare("SELECT pr.*, u.full_name as author_name, u.role as author_role
                       FROM project_remarks pr
                       JOIN users u ON pr.created_by = u.id
                       WHERE pr.project_id = ?
                       ORDER BY pr.created_at ASC");
$stmt->execute([$project_id]);
$remarks = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getProjectStatusBadge($status) {
    $badges = [
        'not_yet_start' => '<span class="px-3 py-1 bg-slate-100 text-slate-700 rounded-lg text-sm font-semibold border border-slate-200">Not Started</span>',
        'ongoing' => '<span class="px-3 py-1 bg-blue-50 text-blue-700 rounded-lg text-sm font-semibold border border-blue-200">Ongoing</span>',
        'waiting_for_customer_info' => '<span class="px-3 py-1 bg-orange-50 text-orange-700 rounded-lg text-sm font-semibold border border-orange-200">Waiting on Customer</span>',
        'completed' => '<span class="px-3 py-1 bg-green-50 text-green-700 rounded-lg text-sm font-semibold border border-green-200">Completed</span>',
        'on_hold' => '<span class="px-3 py-1 bg-purple-50 text-purple-700 rounded-lg text-sm font-semibold border border-purple-200">On Hold</span>',
        'cancelled' => '<span class="px-3 py-1 bg-red-50 text-red-700 rounded-lg text-sm font-semibold border border-red-200">Cancelled</span>'
    ];
    return $badges[$status] ?? $badges['not_yet_start'];
}
?>

<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="projects.php" class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:text-brand-600 hover:border-brand-300 hover:bg-brand-50 transition-all shadow-sm shrink-0">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight flex items-center gap-3">
                    <?= htmlspecialchars($project['project']) ?>
                </h1>
                <div class="flex items-center gap-4 mt-2 text-sm text-slate-500">
                    <span class="flex items-center gap-1.5"><i class="far fa-calendar-alt"></i> Created <?= date('M j, Y', strtotime($project['created_at'])) ?></span>
                    <?php if($project['due_date']): ?>
                    <span class="flex items-center gap-1.5 <?= strtotime($project['due_date']) < time() && !in_array($project['status'], ['completed', 'cancelled']) ? 'text-red-500 font-semibold' : '' ?>"><i class="far fa-clock"></i> Due <?= date('M j, Y', strtotime($project['due_date'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <?= getProjectStatusBadge($project['status']) ?>
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
        <!-- Main Content Column -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Quick Update Card -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <i class="fas fa-sliders-h text-brand-500"></i> Project Status & Progress
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Update Status -->
                    <?php if ($can_edit): ?>
                    <form method="POST" class="space-y-3">
                        <input type="hidden" name="action" value="update_status">
                        <label class="block text-sm font-medium text-slate-700">Project Status</label>
                        <div class="flex gap-2">
                            <select name="status" class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-brand-500 outline-none transition-colors">
                                <option value="not_yet_start" <?= $project['status'] === 'not_yet_start' ? 'selected' : '' ?>>Not Started</option>
                                <option value="ongoing" <?= $project['status'] === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                                <option value="waiting_for_customer_info" <?= $project['status'] === 'waiting_for_customer_info' ? 'selected' : '' ?>>Waiting on Customer</option>
                                <option value="on_hold" <?= $project['status'] === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                                <option value="completed" <?= $project['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= $project['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <button type="submit" class="px-4 py-2.5 bg-brand-50 text-brand-600 hover:bg-brand-100 font-medium rounded-xl transition-colors">Update</button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="space-y-3">
                        <label class="block text-sm font-medium text-slate-700">Project Status</label>
                        <div class="px-4 py-3 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl text-sm font-semibold capitalize">
                            <?= str_replace('_', ' ', $project['status']) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Update Completion -->
                    <?php if ($can_edit): ?>
                    <form method="POST" class="space-y-3" x-data="{ comp: <?= (int)$project['completion'] ?> }">
                        <input type="hidden" name="action" value="update_completion">
                        <label class="block text-sm font-medium text-slate-700 flex justify-between">
                            Completion Rate <span class="text-brand-600 font-bold" x-text="comp + '%'"></span>
                        </label>
                        <div class="flex gap-4 items-center pt-1">
                            <input type="range" name="completion" min="0" max="100" step="5" x-model="comp" class="w-full accent-brand-500 h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer">
                            <button type="submit" class="px-4 py-2.5 bg-brand-50 text-brand-600 hover:bg-brand-100 font-medium rounded-xl transition-colors whitespace-nowrap">Save</button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="space-y-3">
                        <label class="block text-sm font-medium text-slate-700 flex justify-between">
                            Completion Rate <span class="text-brand-600 font-bold"><?= (int)$project['completion'] ?>%</span>
                        </label>
                        <div class="w-full bg-slate-100 rounded-full h-2 mt-3 overflow-hidden">
                            <div class="<?= $project['completion'] >= 100 ? 'bg-green-500' : 'bg-brand-500' ?> h-2 rounded-full transition-all duration-500" style="width: <?= min(100, $project['completion']) ?>%"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Remarks Chat Interface -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col h-[600px]">
                <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2 shrink-0">
                    <i class="far fa-comments text-brand-500"></i> Project Discussion
                </h3>
                
                <div class="flex-1 overflow-y-auto space-y-6 pr-2 mb-6" id="remarks-container">
                    <?php if (count($remarks) === 0): ?>
                        <div class="text-center py-10 text-slate-400 italic">No remarks yet. Start the discussion!</div>
                    <?php endif; ?>
                    
                    <?php foreach ($remarks as $remark): 
                        $is_me = ($remark['created_by'] == $user_id);
                        $is_system = strpos($remark['remark'], '<em>') === 0;
                    ?>
                        <?php if ($is_system): ?>
                            <div class="flex justify-center my-4">
                                <div class="bg-slate-50 text-slate-500 text-xs px-4 py-1.5 rounded-full border border-slate-100 shadow-sm text-center max-w-sm">
                                    <span class="font-medium"><?= htmlspecialchars($remark['author_name']) ?></span> <?= $remark['remark'] ?> 
                                    <span class="text-slate-400 opacity-75 ml-1"><?= date('M j, g:i a', strtotime($remark['created_at'])) ?></span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="flex gap-4 <?= $is_me ? 'flex-row-reverse' : '' ?>">
                                <div class="w-10 h-10 rounded-full shrink-0 flex items-center justify-center font-bold text-sm <?= $is_me ? 'bg-brand-100 text-brand-700' : 'bg-slate-100 text-slate-600' ?>" title="<?= htmlspecialchars($remark['author_name']) ?>">
                                    <?= strtoupper(substr($remark['author_name'], 0, 1)) ?>
                                </div>
                                <div class="flex flex-col <?= $is_me ? 'items-end' : 'items-start' ?> max-w-[80%]">
                                    <div class="flex items-baseline gap-2 mb-1 px-1">
                                        <span class="text-sm font-semibold text-slate-700"><?= $is_me ? 'You' : htmlspecialchars($remark['author_name']) ?></span>
                                        <span class="text-[10px] font-medium text-slate-400 uppercase tracking-wider"><?= htmlspecialchars($remark['author_role']) ?></span>
                                        <span class="text-xs text-slate-400">&bull; <?= date('M j, g:i a', strtotime($remark['created_at'])) ?></span>
                                    </div>
                                    <div class="p-4 rounded-2xl text-sm shadow-sm <?= $is_me ? 'bg-brand-600 text-white rounded-tr-sm' : 'bg-slate-50 border border-slate-100 text-slate-700 rounded-tl-sm' ?>">
                                        <?= nl2br(htmlspecialchars($remark['remark'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <form method="POST" class="shrink-0 relative">
                    <input type="hidden" name="action" value="add_remark">
                    <textarea name="remark" rows="2" required placeholder="Type a message or project update..." class="w-full pl-4 pr-16 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 focus:bg-white transition-all text-slate-800 resize-none outline-none"></textarea>
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 bg-brand-600 hover:bg-brand-700 text-white rounded-lg shadow-sm shadow-brand-500/30 flex items-center justify-center transition-all focus:ring-2 focus:ring-brand-500 focus:ring-offset-1">
                        <i class="fas fa-paper-plane text-sm"></i>
                    </button>
                </form>
            </div>
            
            <script>
                // Auto-scroll chat to bottom
                const container = document.getElementById('remarks-container');
                container.scrollTop = container.scrollHeight;
            </script>
        </div>
        
        <!-- Sidebar Column -->
        <div class="space-y-6">
            
            <!-- Project Details Card -->
            <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
                <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
                    <i class="fas fa-info-circle text-brand-500"></i> Project Details
                </h3>
                
                <div class="space-y-5">
                    <div>
                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Created By</div>
                        <div class="flex items-center gap-3 mt-2">
                            <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0">
                                <?= strtoupper(substr($project['creator_name'], 0, 1)) ?>
                            </div>
                            <div class="text-sm font-medium text-slate-800"><?= htmlspecialchars($project['creator_name']) ?></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Sales Officer</div>
                        <div class="text-sm font-medium text-slate-800 bg-slate-50 px-3 py-2 rounded-lg border border-slate-100 inline-block mt-1">
                            <?= htmlspecialchars($project['sales_officer']) ?: '<span class="italic text-slate-400 font-normal">Unassigned</span>' ?>
                        </div>
                    </div>
                    
                    <div>
                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Project Link</div>
                        <?php if ($can_edit): ?>
                        <form method="POST" class="flex gap-2">
                            <input type="hidden" name="action" value="update_link">
                            <div class="relative flex-1">
                                <i class="fas fa-link absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <input type="url" name="project_link" value="<?= htmlspecialchars($project['project_link'] ?? '') ?>" placeholder="https://..." class="w-full pl-8 pr-3 py-2 bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none transition-colors">
                            </div>
                            <button type="submit" class="px-3 py-2 bg-slate-100 text-slate-600 hover:bg-slate-200 hover:text-slate-800 text-sm font-medium rounded-lg transition-colors">Save</button>
                        </form>
                        <?php endif; ?>
                        
                        <?php if ($project['project_link']): ?>
                        <div class="mt-2 text-sm break-all">
                            <a href="<?= htmlspecialchars($project['project_link']) ?>" target="_blank" class="text-brand-600 hover:text-brand-800 font-medium hover:underline flex items-center gap-1.5">
                                Open Link <i class="fas fa-external-link-alt text-[10px]"></i>
                            </a>
                        </div>
                        <?php elseif (!$can_edit): ?>
                        <div class="text-sm italic text-slate-400 mt-1">No link provided</div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($project['project_contacts']): ?>
                    <div>
                        <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Contacts</div>
                        <div class="bg-slate-50 border border-slate-100 rounded-xl p-4 text-sm text-slate-700 whitespace-pre-line shadow-inner">
                            <?= htmlspecialchars($project['project_contacts']) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

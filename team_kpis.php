<?php
require_once 'header.php';

$division_id = get_user_division();
$filter_user = $_GET['user_id'] ?? '';
$is_super = is_super_admin();

// Fetch Team Members
$team_members = [];
if ($is_super) {
    $stmt = $pdo->query("SELECT u.id, u.full_name, u.role, ds.name as designation,
                            (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id) as total_tasks,
                            (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'completed') as completed_tasks,
                            (SELECT SUM(target_value) FROM tasks WHERE assigned_to = u.id AND target_value > 0) as total_target,
                            (SELECT SUM(p.achievement_value) FROM task_progress p JOIN tasks t ON p.task_id = t.id WHERE t.assigned_to = u.id) as total_achieved,
                            (SELECT COUNT(*) FROM projects WHERE created_by = u.id) as total_projects,
                            (SELECT COUNT(*) FROM projects WHERE created_by = u.id AND status = 'completed') as completed_projects,
                            (SELECT AVG(completion) FROM projects WHERE created_by = u.id) as avg_project_completion
                           FROM users u 
                           LEFT JOIN designations ds ON u.designation_id = ds.id
                           ORDER BY u.full_name");
    $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT u.id, u.full_name, u.role, ds.name as designation,
                            (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id) as total_tasks,
                            (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'completed') as completed_tasks,
                            (SELECT SUM(target_value) FROM tasks WHERE assigned_to = u.id AND target_value > 0) as total_target,
                            (SELECT SUM(p.achievement_value) FROM task_progress p JOIN tasks t ON p.task_id = t.id WHERE t.assigned_to = u.id) as total_achieved,
                            (SELECT COUNT(*) FROM projects WHERE created_by = u.id) as total_projects,
                            (SELECT COUNT(*) FROM projects WHERE created_by = u.id AND status = 'completed') as completed_projects,
                            (SELECT AVG(completion) FROM projects WHERE created_by = u.id) as avg_project_completion
                           FROM users u 
                           LEFT JOIN designations ds ON u.designation_id = ds.id
                           WHERE u.division_id = ?
                           ORDER BY u.full_name");
    $stmt->execute([$division_id]);
    $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Find selected member if any
$selected_member = null;
if ($filter_user) {
    foreach ($team_members as $m) {
        if ($m['id'] == $filter_user) {
            $selected_member = $m;
            break;
        }
    }
}

// Query parameters for tasks
$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? 'all';

// Helper to get tasks for selected member
$member_tasks = [];
if ($selected_member) {
    $where_clauses = ["t.assigned_to = ?"];
    $params = [$selected_member['id']];
    
    if ($filter_status === 'overdue') {
        $where_clauses[] = "t.status != 'completed' AND t.due_date < CURDATE()";
    } elseif ($filter_status !== 'all') {
        $where_clauses[] = "t.status = ?";
        $params[] = $filter_status;
    }
    
    if ($search !== '') {
        $where_clauses[] = "(t.title LIKE ? OR t.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";
    
    $stmt = $pdo->prepare("SELECT t.*, c.full_name as creator_name, d.name as division_name 
                           FROM tasks t 
                           LEFT JOIN users c ON t.created_by = c.id
                           LEFT JOIN divisions d ON t.division_id = d.id
                           $where_sql ORDER BY t.created_at DESC");
    $stmt->execute($params);
    $member_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Helper to get projects for selected member
    $proj_where_clauses = ["p.created_by = ?"];
    $proj_params = [$selected_member['id']];
    
    if ($filter_status === 'overdue') {
        $proj_where_clauses[] = "p.status != 'completed' AND p.status != 'cancelled' AND p.due_date < CURDATE()";
    } elseif ($filter_status !== 'all' && in_array($filter_status, ['not_yet_start', 'ongoing', 'waiting_for_customer_info', 'completed', 'on_hold', 'cancelled'])) {
        $proj_where_clauses[] = "p.status = ?";
        $proj_params[] = $filter_status;
    }
    
    if ($search !== '') {
        $proj_where_clauses[] = "(p.project LIKE ? OR p.sales_officer LIKE ?)";
        $proj_params[] = "%$search%";
        $proj_params[] = "%$search%";
    }
    
    $proj_where_sql = count($proj_where_clauses) > 0 ? "WHERE " . implode(" AND ", $proj_where_clauses) : "";
    
    $stmt = $pdo->prepare("SELECT p.*, c.full_name as creator_name
                           FROM projects p 
                           LEFT JOIN users c ON p.created_by = c.id
                           $proj_where_sql ORDER BY p.created_at DESC");
    $stmt->execute($proj_params);
    $member_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-md text-xs font-medium border border-slate-200">Pending</span>',
        'in_progress' => '<span class="px-2.5 py-1 bg-blue-50 text-blue-600 rounded-md text-xs font-medium border border-blue-200">In Progress</span>',
        'completed' => '<span class="px-2.5 py-1 bg-green-50 text-green-600 rounded-md text-xs font-medium border border-green-200">Completed</span>',
        'forwarded' => '<span class="px-2.5 py-1 bg-purple-50 text-purple-600 rounded-md text-xs font-medium border border-purple-200">Forwarded</span>',
        'reopened' => '<span class="px-2.5 py-1 bg-red-50 text-red-600 rounded-md text-xs font-medium border border-red-200">Reopened</span>',
        'overdue' => '<span class="px-2.5 py-1 bg-orange-50 text-orange-600 rounded-md text-xs font-medium border border-orange-200">Overdue</span>'
    ];
    return $badges[$status] ?? $badges['pending'];
}

function getProjectStatusBadge($status) {
    $badges = [
        'not_yet_start' => '<span class="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-md text-xs font-medium border border-slate-200">Not Started</span>',
        'ongoing' => '<span class="px-2.5 py-1 bg-blue-50 text-blue-600 rounded-md text-xs font-medium border border-blue-200">Ongoing</span>',
        'waiting_for_customer_info' => '<span class="px-2.5 py-1 bg-orange-50 text-orange-600 rounded-md text-xs font-medium border border-orange-200">Waiting on Customer</span>',
        'completed' => '<span class="px-2.5 py-1 bg-green-50 text-green-600 rounded-md text-xs font-medium border border-green-200">Completed</span>',
        'on_hold' => '<span class="px-2.5 py-1 bg-purple-50 text-purple-600 rounded-md text-xs font-medium border border-purple-200">On Hold</span>',
        'cancelled' => '<span class="px-2.5 py-1 bg-red-50 text-red-600 rounded-md text-xs font-medium border border-red-200">Cancelled</span>',
        'overdue' => '<span class="px-2.5 py-1 bg-orange-50 text-orange-600 rounded-md text-xs font-medium border border-orange-200">Overdue</span>'
    ];
    return $badges[$status] ?? $badges['not_yet_start'];
}
?>

<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Team KPIs</h1>
            <p class="text-slate-500 text-sm mt-1">Track and motivate your team's performance and target achievements.</p>
        </div>
        
        <?php if ($selected_member): ?>
        <a href="team_kpis.php" class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 font-medium rounded-xl shadow-sm transition-all flex items-center gap-2">
            <i class="fas fa-arrow-left"></i> Back to Overview
        </a>
        <?php endif; ?>
    </div>

    <?php if (!$selected_member): ?>
    <!-- Filters & Dropdown -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <label class="text-sm font-medium text-slate-600 whitespace-nowrap">Filter Member:</label>
            <select onchange="window.location.href='?user_id='+this.value" class="w-full sm:w-64 px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none transition-colors">
                <option value="">All Team Members</option>
                <?php foreach($team_members as $tm): ?>
                    <option value="<?= $tm['id'] ?>"><?= htmlspecialchars($tm['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Team Overview Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4">Team Member</th>
                        <th class="px-6 py-4">Tasks</th>
                        <th class="px-6 py-4">Projects</th>
                        <th class="px-6 py-4">Target Achieved</th>
                        <th class="px-6 py-4">KPI Progress</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    <?php foreach($team_members as $member): 
                        $target = (int)$member['total_target'];
                        $achieved = (int)$member['total_achieved'];
                        $task_kpi = $target > 0 ? min(100, round(($achieved / $target) * 100)) : 0;
                        $proj_kpi = $member['total_projects'] > 0 ? (int)round($member['avg_project_completion']) : 0;
                        
                        if ($target > 0 && $member['total_projects'] > 0) {
                            $kpi_percent = round(($task_kpi + $proj_kpi) / 2);
                        } elseif ($target > 0) {
                            $kpi_percent = $task_kpi;
                        } elseif ($member['total_projects'] > 0) {
                            $kpi_percent = $proj_kpi;
                        } else {
                            $kpi_percent = 0;
                        }
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors group">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-100 to-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm shrink-0 border border-indigo-100">
                                    <?= strtoupper(substr($member['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($member['full_name']) ?></div>
                                    <div class="text-xs text-slate-500"><?= htmlspecialchars($member['designation'] ?? 'Team Member') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-baseline gap-1">
                                <span class="text-lg font-bold text-slate-800"><?= $member['completed_tasks'] ?></span>
                                <span class="text-sm font-medium text-slate-400">/ <?= $member['total_tasks'] ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-baseline gap-1">
                                <span class="text-lg font-bold text-slate-800"><?= $member['completed_projects'] ?></span>
                                <span class="text-sm font-medium text-slate-400">/ <?= $member['total_projects'] ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($target > 0): ?>
                                <div class="font-medium text-slate-700"><?= $achieved ?> <span class="text-slate-400 text-xs font-normal">out of</span> <?= $target ?></div>
                            <?php else: ?>
                                <span class="text-slate-400 italic text-xs">No targets</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 w-64">
                            <?php if ($target > 0 || $member['total_projects'] > 0): ?>
                            <div class="flex items-center gap-3">
                                <div class="w-full bg-slate-100 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all duration-1000 <?= $kpi_percent >= 100 ? 'bg-green-500' : 'bg-brand-500' ?>" style="width: <?= $kpi_percent ?>%"></div>
                                </div>
                                <span class="font-bold text-xs w-8 text-right <?= $kpi_percent >= 100 ? 'text-green-600' : 'text-slate-600' ?>"><?= $kpi_percent ?>%</span>
                            </div>
                            <?php else: ?>
                            <span class="text-slate-300">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="?user_id=<?= $member['id'] ?>" class="inline-flex items-center justify-center px-3 py-1.5 bg-brand-50 text-brand-600 font-medium text-xs rounded-lg hover:bg-brand-100 hover:text-brand-700 transition-colors opacity-0 group-hover:opacity-100">
                                View Details <i class="fas fa-arrow-right ml-1.5"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($team_members)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                            No team members found.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <?php else: // Individual Member Details ?>
    
    <?php 
    $target = (int)$selected_member['total_target'];
    $achieved = (int)$selected_member['total_achieved'];
    $task_kpi = $target > 0 ? min(100, round(($achieved / $target) * 100)) : 0;
    $proj_kpi = $selected_member['total_projects'] > 0 ? (int)round($selected_member['avg_project_completion']) : 0;
    
    if ($target > 0 && $selected_member['total_projects'] > 0) {
        $kpi_percent = round(($task_kpi + $proj_kpi) / 2);
    } elseif ($target > 0) {
        $kpi_percent = $task_kpi;
    } elseif ($selected_member['total_projects'] > 0) {
        $kpi_percent = $proj_kpi;
    } else {
        $kpi_percent = 0;
    }
    ?>
    
    <!-- Member Summary Card -->
    <div class="bg-white rounded-2xl p-6 md:p-8 border border-slate-200 shadow-sm flex flex-col md:flex-row items-center gap-8 relative overflow-hidden">
        <div class="absolute right-0 top-0 w-64 h-64 bg-gradient-to-bl from-brand-50 to-transparent rounded-bl-full pointer-events-none opacity-50"></div>
        
        <div class="flex items-center gap-6 z-10 w-full md:w-auto border-b md:border-b-0 md:border-r border-slate-100 pb-6 md:pb-0 md:pr-8">
            <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-brand-500 to-brand-600 text-white flex items-center justify-center font-bold text-3xl shadow-lg">
                <?= strtoupper(substr($selected_member['full_name'], 0, 1)) ?>
            </div>
            <div>
                <h2 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($selected_member['full_name']) ?></h2>
                <p class="text-brand-600 font-medium"><?= htmlspecialchars($selected_member['designation'] ?? 'Team Member') ?></p>
            </div>
        </div>
        
        <div class="flex-1 w-full z-10 grid grid-cols-2 lg:grid-cols-6 gap-6">
            <div>
                <div class="text-sm font-medium text-slate-500 mb-1">Total Tasks</div>
                <div class="text-2xl font-bold text-slate-800"><?= $selected_member['total_tasks'] ?></div>
            </div>
            <div>
                <div class="text-sm font-medium text-slate-500 mb-1">Done</div>
                <div class="text-2xl font-bold text-green-600"><?= $selected_member['completed_tasks'] ?></div>
            </div>
            <div>
                <div class="text-sm font-medium text-slate-500 mb-1">Total Projects</div>
                <div class="text-2xl font-bold text-slate-800"><?= $selected_member['total_projects'] ?></div>
            </div>
            <div>
                <div class="text-sm font-medium text-slate-500 mb-1">Done</div>
                <div class="text-2xl font-bold text-green-600"><?= $selected_member['completed_projects'] ?></div>
            </div>
            <div class="col-span-2 lg:col-span-2">
                <div class="flex justify-between items-end mb-1">
                    <div class="text-sm font-medium text-slate-500">Overall Target Achievement</div>
                    <div class="text-xl font-bold <?= $kpi_percent >= 100 ? 'text-green-600' : 'text-slate-800' ?>"><?= $kpi_percent ?>%</div>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2.5 mt-2">
                    <div class="h-2.5 rounded-full transition-all duration-1000 <?= $kpi_percent >= 100 ? 'bg-green-500' : 'bg-brand-500' ?>" style="width: <?= $kpi_percent ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="flex items-center justify-between mt-8 mb-6">
        <h3 class="text-xl font-bold text-slate-800">Assigned Tasks</h3>
        <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-bold rounded-full"><?= count($member_tasks) ?> Tasks</span>
    </div>

    <!-- Task Search & Filters -->
    <form method="GET" class="flex flex-col sm:flex-row gap-4 mb-6 bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <input type="hidden" name="user_id" value="<?= htmlspecialchars($filter_user) ?>">
        <div class="flex-1 min-w-[200px] relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tasks by title or description..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors">
            <?php if($search): ?>
                <a href="?user_id=<?= $filter_user ?>&status=<?= $filter_status ?>" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </div>
        <select name="status" onchange="this.form.submit()" class="px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none transition-colors min-w-[140px]">
            <option value="all">All Status</option>
            <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="reopened" <?= $filter_status === 'reopened' ? 'selected' : '' ?>>Reopened</option>
            <option value="forwarded" <?= $filter_status === 'forwarded' ? 'selected' : '' ?>>Forwarded</option>
            <option value="overdue" <?= $filter_status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
        </select>
        <button type="submit" class="hidden"></button>
    </form>

    <!-- Task Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (count($member_tasks) === 0): ?>
            <div class="col-span-full py-12 text-center text-slate-500 bg-white rounded-2xl border border-slate-200">
                <i class="fas fa-clipboard-list text-4xl mb-3 text-slate-300"></i>
                <p>No tasks currently assigned to this member.</p>
            </div>
        <?php endif; ?>
        
        <?php foreach ($member_tasks as $task): ?>
            <?php
            // Calculate Progress
            $t_progress = 0;
            $t_achieved = 0;
            if ($task['target_value'] > 0) {
                $stmt = $pdo->prepare("SELECT SUM(achievement_value) FROM task_progress WHERE task_id = ?");
                $stmt->execute([$task['id']]);
                $t_achieved = (int)$stmt->fetchColumn();
                $t_progress = min(100, round(($t_achieved / $task['target_value']) * 100));
            } elseif ($task['status'] === 'completed') {
                $t_progress = 100;
            }
            
            // Check overdue
            $is_overdue = ($task['status'] !== 'completed' && strtotime($task['due_date']) < time());
            ?>
            <a href="task_details.php?id=<?= $task['id'] ?>" class="block bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow-md hover:border-brand-300 transition-all flex flex-col h-full group relative <?= $is_overdue ? 'border-orange-200' : '' ?>">
                <?php if($task['status'] === 'completed'): ?>
                    <div class="absolute inset-0 bg-green-500/5 rounded-2xl pointer-events-none"></div>
                <?php endif; ?>
                
                <div class="flex justify-between items-start mb-3 relative z-10">
                    <?= getStatusBadge($is_overdue ? 'overdue' : $task['status']) ?>
                    <span class="text-[10px] font-bold px-2 py-1 bg-slate-50 text-slate-500 rounded-md border border-slate-100 uppercase tracking-widest shadow-sm">
                        <?= htmlspecialchars($task['priority']) ?>
                    </span>
                </div>
                
                <h3 class="text-lg font-bold text-slate-800 mb-2 group-hover:text-brand-600 transition-colors line-clamp-2 relative z-10">
                    <?= htmlspecialchars($task['title']) ?>
                </h3>
                
                <p class="text-slate-500 text-sm mb-4 line-clamp-2 flex-1 relative z-10">
                    <?= htmlspecialchars($task['description']) ?>
                </p>
                
                <?php if ($task['target_value'] > 0): ?>
                <div class="mb-4 relative z-10">
                    <div class="flex justify-between text-xs text-slate-500 mb-1 font-medium">
                        <span><?= $t_achieved ?> / <?= $task['target_value'] ?> <?= htmlspecialchars($task['unit']) ?></span>
                        <span class="<?= $t_progress >= 100 ? 'text-green-600' : '' ?>"><?= $t_progress ?>%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                        <div class="<?= $t_progress >= 100 ? 'bg-green-500' : 'bg-brand-500' ?> h-2 rounded-full transition-all duration-500" style="width: <?= $t_progress ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mt-auto pt-4 border-t border-slate-100 flex items-center justify-between relative z-10">
                    <div class="flex items-center gap-2">
                        <div class="text-xs text-slate-500 min-w-0">
                            <div class="text-slate-400">Created by <span class="font-medium text-slate-600"><?= htmlspecialchars($task['creator_name']) ?></span></div>
                            <div class="<?= $is_overdue ? 'text-orange-600 font-semibold' : '' ?>">
                                Due <?= date('M j, Y', strtotime($task['due_date'])) ?>
                            </div>
                        </div>
                    </div>
                    <div class="w-8 h-8 flex items-center justify-center text-slate-400 group-hover:text-brand-600 group-hover:bg-brand-50 rounded-lg transition-colors shrink-0">
                        <i class="fas fa-arrow-right text-sm"></i>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    
    <!-- Projects Grid -->
    <?php if (count($member_projects) > 0 || $filter_status !== 'all' || $search !== ''): ?>
    <div class="flex items-center justify-between mt-12 mb-6">
        <h3 class="text-xl font-bold text-slate-800">Owned Projects</h3>
        <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-bold rounded-full"><?= count($member_projects) ?> Projects</span>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (count($member_projects) === 0): ?>
            <div class="col-span-full py-12 text-center text-slate-500 bg-white rounded-2xl border border-slate-200">
                <i class="fas fa-project-diagram text-4xl mb-3 text-slate-300"></i>
                <p>No projects match your filters.</p>
            </div>
        <?php endif; ?>
        
        <?php foreach ($member_projects as $project): ?>
            <a href="project_details.php?id=<?= $project['id'] ?>" class="block bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow-md hover:border-brand-300 transition-all flex flex-col h-full group relative">
                
                <div class="flex justify-between items-start mb-3 relative z-10">
                    <?= getProjectStatusBadge($project['status']) ?>
                    <?php if ($project['project_link']): ?>
                        <span class="text-xs text-brand-500 bg-brand-50 px-2 py-1 rounded-md font-medium border border-brand-100" title="Project Link attached">
                            <i class="fas fa-link"></i> Link
                        </span>
                    <?php endif; ?>
                </div>
                
                <h3 class="text-lg font-bold text-slate-800 mb-2 group-hover:text-brand-600 transition-colors line-clamp-2 relative z-10">
                    <?= htmlspecialchars($project['project']) ?>
                </h3>
                
                <div class="text-slate-500 text-sm mb-4 space-y-1 relative z-10">
                    <div><span class="font-medium text-slate-600">Sales Officer:</span> <?= htmlspecialchars($project['sales_officer']) ?: '<span class="italic text-slate-400">Unassigned</span>' ?></div>
                </div>
                
                <div class="mb-4 relative z-10">
                    <div class="flex justify-between text-xs text-slate-500 mb-1 font-medium">
                        <span>Completion Rate</span>
                        <span class="<?= $project['completion'] >= 100 ? 'text-green-600' : '' ?>"><?= number_format($project['completion'], 0) ?>%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                        <div class="<?= $project['completion'] >= 100 ? 'bg-green-500' : 'bg-brand-500' ?> h-2 rounded-full transition-all duration-500" style="width: <?= min(100, $project['completion']) ?>%"></div>
                    </div>
                </div>
                
                <div class="mt-auto pt-4 border-t border-slate-100 flex items-center justify-between relative z-10">
                    <div class="flex items-center gap-2">
                        <div class="text-xs text-slate-500 min-w-0">
                            <div class="text-slate-400">Created by <span class="font-medium text-slate-600"><?= htmlspecialchars($project['creator_name']) ?></span></div>
                            <div class="<?= strtotime($project['due_date']) < time() && !in_array($project['status'], ['completed', 'cancelled']) ? 'text-red-500 font-semibold' : '' ?>">
                                Due <?= $project['due_date'] ? date('M j, Y', strtotime($project['due_date'])) : 'N/A' ?>
                            </div>
                        </div>
                    </div>
                    <div class="w-8 h-8 flex items-center justify-center text-slate-400 group-hover:text-brand-600 group-hover:bg-brand-50 rounded-lg transition-colors shrink-0">
                        <i class="fas fa-arrow-right text-sm"></i>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>

</div>

<?php require_once 'footer.php'; ?>

<?php
require_once 'header.php';

$division_id = get_user_division();
$user_id = $_SESSION['user_id'];
$is_super = is_super_admin();
$is_div_head = is_division_head();
$is_admin = $is_super || $is_div_head;

// Query parameters
$tab = $_GET['tab'] ?? ($is_admin ? 'all' : 'my_tasks');
$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? 'all';
$filter_user = $_GET['user_id'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12; // tasks per page
$offset = ($page - 1) * $limit;

// Base query parts
$params = [];
$where_clauses = [];
$joins = "LEFT JOIN users u ON t.assigned_to = u.id 
          LEFT JOIN users c ON t.created_by = c.id";

if ($is_super) {
    $joins .= " LEFT JOIN divisions d ON t.division_id = d.id";
}

// Role and Tab filters
if ($is_admin) {
    if (!$is_super) {
        $where_clauses[] = "t.division_id = ?";
        $params[] = $division_id;
    }
    
    if ($tab === 'my_tasks') {
        $where_clauses[] = "t.assigned_to = ?";
        $params[] = $user_id;
    } elseif ($tab === 'team_tasks') {
        $where_clauses[] = "t.assigned_to != ?";
        $params[] = $user_id;
    }
} else {
    // Regular users see what is assigned to them or created by them
    $where_clauses[] = "(t.assigned_to = ? OR t.created_by = ?)";
    $params[] = $user_id;
    $params[] = $user_id;
}

// Status filter
if ($filter_status === 'overdue') {
    $where_clauses[] = "t.status != 'completed' AND t.due_date < CURDATE()";
} elseif ($filter_status !== 'all') {
    $where_clauses[] = "t.status = ?";
    $params[] = $filter_status;
}

// User filter
if ($filter_user !== '') {
    $where_clauses[] = "t.assigned_to = ?";
    $params[] = $filter_user;
}

// Search filter
if ($search !== '') {
    $where_clauses[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Construct WHERE clause string
$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

// Count total for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t $where_sql");
$count_stmt->execute($params);
$total_tasks = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($total_tasks / $limit));

// Fetch paginated tasks
$select_cols = $is_super ? "t.*, u.full_name as assigned_name, c.full_name as creator_name, d.name as division_name" : "t.*, u.full_name as assigned_name, c.full_name as creator_name";
$stmt = $pdo->prepare("SELECT $select_cols FROM tasks t $joins $where_sql ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch team members for dropdown
$team_members = [];
if ($is_super) {
    $stmt = $pdo->query("SELECT id, full_name FROM users ORDER BY full_name");
    $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($is_div_head) {
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE division_id = ? ORDER BY full_name");
    $stmt->execute([$division_id]);
    $team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-md text-xs font-medium border border-slate-200">Pending</span>',
        'in_progress' => '<span class="px-2.5 py-1 bg-blue-50 text-blue-600 rounded-md text-xs font-medium border border-blue-200">In Progress</span>',
        'completed' => '<span class="px-2.5 py-1 bg-green-50 text-green-600 rounded-md text-xs font-medium border border-green-200">Completed</span>',
        'forwarded' => '<span class="px-2.5 py-1 bg-purple-50 text-purple-600 rounded-md text-xs font-medium border border-purple-200">Forwarded</span>',
        'reopened' => '<span class="px-2.5 py-1 bg-red-50 text-red-600 rounded-md text-xs font-medium border border-red-200">Reopened</span>'
    ];
    return $badges[$status] ?? $badges['pending'];
}

// Helper to build URL with maintained parameters
function buildUrl($updates = []) {
    global $tab, $search, $filter_status, $filter_user, $page;
    $q = [
        'tab' => $tab,
        'search' => $search,
        'status' => $filter_status,
        'user_id' => $filter_user,
        'page' => $page
    ];
    foreach($updates as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    // Remove defaults for cleaner URLs
    if (isset($q['tab']) && $q['tab'] === 'all' && !is_super_admin() && !is_division_head()) unset($q['tab']);
    if (isset($q['search']) && $q['search'] === '') unset($q['search']);
    if (isset($q['status']) && $q['status'] === 'all') unset($q['status']);
    if (isset($q['user_id']) && $q['user_id'] === '') unset($q['user_id']);
    if (isset($q['page']) && $q['page'] == 1) unset($q['page']);
    
    $qs = http_build_query($q);
    return 'tasks.php' . ($qs ? '?' . $qs : '');
}
?>

<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Tasks</h1>
            <p class="text-slate-500 text-sm mt-1">Manage and track task progress.</p>
        </div>
        
        <?php if ($is_admin || in_array('create_task', $rolePermissions[$_SESSION['role']] ?? [])): ?>
        <a href="create_task.php" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-medium rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-2">
            <i class="fas fa-plus"></i> Create Task
        </a>
        <?php endif; ?>
    </div>

    <!-- Tabs for Admins -->
    <?php if ($is_admin): ?>
    <div class="flex gap-6 border-b border-slate-200">
        <a href="<?= buildUrl(['tab' => 'all', 'page' => 1]) ?>" class="pb-3 px-1 font-medium text-sm transition-colors <?= $tab === 'all' ? 'text-brand-600 border-b-2 border-brand-600' : 'text-slate-500 hover:text-slate-800' ?>">All Tasks</a>
        <a href="<?= buildUrl(['tab' => 'my_tasks', 'page' => 1]) ?>" class="pb-3 px-1 font-medium text-sm transition-colors <?= $tab === 'my_tasks' ? 'text-brand-600 border-b-2 border-brand-600' : 'text-slate-500 hover:text-slate-800' ?>">My Tasks</a>
        <a href="<?= buildUrl(['tab' => 'team_tasks', 'page' => 1]) ?>" class="pb-3 px-1 font-medium text-sm transition-colors <?= $tab === 'team_tasks' ? 'text-brand-600 border-b-2 border-brand-600' : 'text-slate-500 hover:text-slate-800' ?>">Team Tasks</a>
    </div>
    <?php endif; ?>

    <!-- Search & Filters Section -->
    <form method="GET" class="flex flex-col lg:flex-row justify-between gap-4 bg-white p-4 rounded-xl shadow-sm border border-slate-200">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        
        <!-- Search Bar -->
        <div class="flex-1 min-w-[200px] relative">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search tasks by title or description..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors">
            <?php if($search): ?>
                <a href="<?= buildUrl(['search' => '']) ?>" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            <!-- Status Filter -->
            <select name="status" onchange="this.form.submit()" class="px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none transition-colors min-w-[140px]">
                <option value="all">All Status</option>
                <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="reopened" <?= $filter_status === 'reopened' ? 'selected' : '' ?>>Reopened</option>
                <option value="forwarded" <?= $filter_status === 'forwarded' ? 'selected' : '' ?>>Forwarded</option>
                <option value="overdue" <?= $filter_status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
            </select>
            
            <!-- Team Member Filter -->
            <?php if(count($team_members) > 0): ?>
            <select name="user_id" onchange="this.form.submit()" class="px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none transition-colors min-w-[160px]">
                <option value="">All Members</option>
                <?php foreach($team_members as $tm): ?>
                    <option value="<?= $tm['id'] ?>" <?= $filter_user == $tm['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tm['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>
        <!-- Hidden submit for search enter key -->
        <button type="submit" class="hidden"></button>
    </form>

    <!-- Task Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (count($tasks) === 0): ?>
            <div class="col-span-full py-16 text-center text-slate-500 bg-white rounded-2xl border border-slate-200 shadow-sm">
                <i class="fas fa-search text-4xl mb-4 text-slate-300"></i>
                <h3 class="text-lg font-medium text-slate-700 mb-1">No tasks found</h3>
                <p>We couldn't find any tasks matching your current filters.</p>
                <?php if($filter_status !== 'all' || $filter_user !== '' || $search !== ''): ?>
                    <a href="tasks.php?tab=<?= htmlspecialchars($tab) ?>" class="inline-block mt-4 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-lg transition-colors">Clear Filters</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php foreach ($tasks as $task): ?>
            <?php
            // Calculate Progress
            $progress_percent = 0;
            $achieved = 0;
            if ($task['target_value'] > 0) {
                $stmt = $pdo->prepare("SELECT SUM(achievement_value) FROM task_progress WHERE task_id = ?");
                $stmt->execute([$task['id']]);
                $achieved = (int)$stmt->fetchColumn();
                $progress_percent = min(100, round(($achieved / $task['target_value']) * 100));
            } elseif ($task['status'] === 'completed') {
                $progress_percent = 100;
            }
            ?>
            <a href="task_details.php?id=<?= $task['id'] ?>" class="block bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:shadow-md hover:border-brand-300 transition-all flex flex-col h-full group relative">
                <?php if($task['status'] === 'completed'): ?>
                    <div class="absolute inset-0 bg-green-500/5 rounded-2xl pointer-events-none"></div>
                <?php endif; ?>
                
                <div class="flex justify-between items-start mb-3 relative z-10">
                    <?= getStatusBadge($task['status']) ?>
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
                        <span><?= $achieved ?> / <?= $task['target_value'] ?> <?= htmlspecialchars($task['unit']) ?></span>
                        <span class="<?= $progress_percent >= 100 ? 'text-green-600' : '' ?>"><?= $progress_percent ?>%</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                        <div class="<?= $progress_percent >= 100 ? 'bg-green-500' : 'bg-brand-500' ?> h-2 rounded-full transition-all duration-500" style="width: <?= $progress_percent ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mt-auto pt-4 border-t border-slate-100 flex items-center justify-between relative z-10">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0" title="Assigned to <?= htmlspecialchars($task['assigned_name']) ?>">
                            <?= strtoupper(substr($task['assigned_name'], 0, 1)) ?>
                        </div>
                        <div class="text-xs text-slate-500 min-w-0">
                            <div class="font-medium text-slate-700 truncate w-24" title="<?= htmlspecialchars($task['assigned_name']) ?>"><?= htmlspecialchars($task['assigned_name']) ?></div>
                            <div class="<?= strtotime($task['due_date']) < time() && $task['status'] !== 'completed' ? 'text-red-500 font-semibold' : '' ?>">
                                Due <?= date('M j', strtotime($task['due_date'])) ?>
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

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6 rounded-xl shadow-sm mt-6">
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-slate-700">
                    Showing <span class="font-medium"><?= $offset + 1 ?></span> to 
                    <span class="font-medium"><?= min($offset + $limit, $total_tasks) ?></span> of 
                    <span class="font-medium"><?= $total_tasks ?></span> results
                </p>
            </div>
            <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                    <a href="<?= buildUrl(['page' => $page - 1]) ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
                        <span class="sr-only">Previous</span>
                        <i class="fas fa-chevron-left h-4 w-4"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1): ?>
                        <a href="<?= buildUrl(['page' => 1]) ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">1</a>
                        <?php if ($start_page > 2): ?>
                            <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span aria-current="page" class="relative z-10 inline-flex items-center bg-brand-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= buildUrl(['page' => $i]) ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300">...</span>
                        <?php endif; ?>
                        <a href="<?= buildUrl(['page' => $total_pages]) ?>" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0"><?= $total_pages ?></a>
                    <?php endif; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="<?= buildUrl(['page' => $page + 1]) ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
                        <span class="sr-only">Next</span>
                        <i class="fas fa-chevron-right h-4 w-4"></i>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
        <!-- Mobile pagination -->
        <div class="flex flex-1 justify-between sm:hidden">
            <?php if ($page > 1): ?>
                <a href="<?= buildUrl(['page' => $page - 1]) ?>" class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Previous</a>
            <?php else: ?>
                <span class="relative inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-400">Previous</span>
            <?php endif; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="<?= buildUrl(['page' => $page + 1]) ?>" class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Next</a>
            <?php else: ?>
                <span class="relative ml-3 inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-400">Next</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once 'footer.php'; ?>

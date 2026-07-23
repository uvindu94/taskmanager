<?php
require_once 'header.php';

$division_id = get_user_division();
$user_id = $_SESSION['user_id'];
$is_super = is_super_admin();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Filters
$search = trim($_GET['search'] ?? '');
$filter_status = $_GET['status'] ?? 'all';
$tab = $_GET['tab'] ?? 'all';

// Build Query
$where_clauses = [];
$params = [];

if (!$is_super) {
    if ($tab === 'my') {
        $where_clauses[] = "p.created_by = ?";
        $params[] = $user_id;
    } else {
        $where_clauses[] = "p.division_id = ?";
        $params[] = $division_id;
    }
} else {
    if ($tab === 'my') {
        $where_clauses[] = "p.created_by = ?";
        $params[] = $user_id;
    }
}

if ($filter_status !== 'all') {
    $where_clauses[] = "p.status = ?";
    $params[] = $filter_status;
}

if ($search !== '') {
    $where_clauses[] = "(p.project LIKE ? OR p.sales_officer LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count total
$count_sql = "SELECT COUNT(*) FROM projects p $where_sql";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_projects = $stmt->fetchColumn();
$total_pages = ceil($total_projects / $limit);

// Fetch projects
$sql = "SELECT p.*, c.full_name as creator_name 
        FROM projects p 
        LEFT JOIN users c ON p.created_by = c.id
        $where_sql 
        ORDER BY p.created_at DESC 
        LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key + 1, $val);
}
$stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

function buildProjectUrl($params_to_update) {
    $current = $_GET;
    $merged = array_merge($current, $params_to_update);
    return '?' . http_build_query($merged);
}

function getProjectStatusBadge($status) {
    $badges = [
        'not_yet_start' => '<span class="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-md text-xs font-medium border border-slate-200">Not Started</span>',
        'ongoing' => '<span class="px-2.5 py-1 bg-blue-50 text-blue-600 rounded-md text-xs font-medium border border-blue-200">Ongoing</span>',
        'waiting_for_customer_info' => '<span class="px-2.5 py-1 bg-orange-50 text-orange-600 rounded-md text-xs font-medium border border-orange-200">Waiting on Customer</span>',
        'completed' => '<span class="px-2.5 py-1 bg-green-50 text-green-600 rounded-md text-xs font-medium border border-green-200">Completed</span>',
        'on_hold' => '<span class="px-2.5 py-1 bg-purple-50 text-purple-600 rounded-md text-xs font-medium border border-purple-200">On Hold</span>',
        'cancelled' => '<span class="px-2.5 py-1 bg-red-50 text-red-600 rounded-md text-xs font-medium border border-red-200">Cancelled</span>'
    ];
    return $badges[$status] ?? $badges['not_yet_start'];
}
?>

<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Projects</h1>
            <p class="text-slate-500 text-sm mt-1">Manage and track the progress of team projects.</p>
        </div>
        <a href="create_project.php" class="inline-flex items-center justify-center px-4 py-2 bg-brand-600 text-white font-medium text-sm rounded-xl hover:bg-brand-700 transition-colors shadow-sm focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
            <i class="fas fa-plus mr-2"></i> New Project
        </a>
    </div>

    <!-- Controls Bar -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 space-y-4">
        <!-- Tabs -->
        <div class="flex border-b border-slate-100">
            <a href="<?= buildProjectUrl(['tab' => 'all', 'page' => 1]) ?>" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors <?= $tab === 'all' ? 'border-brand-500 text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' ?>">
                <?= $is_super ? 'All Projects' : 'Division Projects' ?>
            </a>
            <a href="<?= buildProjectUrl(['tab' => 'my', 'page' => 1]) ?>" class="px-4 py-3 text-sm font-medium border-b-2 transition-colors <?= $tab === 'my' ? 'border-brand-500 text-brand-600' : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300' ?>">
                My Projects
            </a>
        </div>
        
        <!-- Search & Filter -->
        <form method="GET" class="flex flex-col sm:flex-row gap-4">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            
            <div class="flex-1 relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search projects or sales officer..." class="w-full pl-11 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors">
                <?php if($search): ?>
                    <a href="<?= buildProjectUrl(['search' => '', 'page' => 1]) ?>" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
            
            <select name="status" onchange="this.form.submit()" class="px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors min-w-[160px] cursor-pointer">
                <option value="all">All Statuses</option>
                <option value="not_yet_start" <?= $filter_status === 'not_yet_start' ? 'selected' : '' ?>>Not Started</option>
                <option value="ongoing" <?= $filter_status === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                <option value="waiting_for_customer_info" <?= $filter_status === 'waiting_for_customer_info' ? 'selected' : '' ?>>Waiting on Customer</option>
                <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="on_hold" <?= $filter_status === 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            
            <!-- Hidden submit -->
            <button type="submit" class="hidden"></button>
        </form>
    </div>

    <!-- Project Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if (count($projects) === 0): ?>
            <div class="col-span-full py-16 text-center text-slate-500 bg-white rounded-2xl border border-slate-200 shadow-sm">
                <i class="fas fa-search text-4xl mb-4 text-slate-300"></i>
                <h3 class="text-lg font-medium text-slate-700 mb-1">No projects found</h3>
                <p>We couldn't find any projects matching your current filters.</p>
                <?php if($filter_status !== 'all' || $search !== ''): ?>
                    <a href="projects.php?tab=<?= htmlspecialchars($tab) ?>" class="inline-block mt-4 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-lg transition-colors">Clear Filters</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php foreach ($projects as $project): ?>
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
                        <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center text-xs font-bold shrink-0" title="Created by <?= htmlspecialchars($project['creator_name']) ?>">
                            <?= strtoupper(substr($project['creator_name'], 0, 1)) ?>
                        </div>
                        <div class="text-xs text-slate-500 min-w-0">
                            <div class="font-medium text-slate-700 truncate w-24" title="<?= htmlspecialchars($project['creator_name']) ?>"><?= htmlspecialchars($project['creator_name']) ?></div>
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

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6 rounded-xl shadow-sm mt-6">
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-slate-700">
                    Showing <span class="font-medium"><?= $offset + 1 ?></span> to 
                    <span class="font-medium"><?= min($offset + $limit, $total_projects) ?></span> of 
                    <span class="font-medium"><?= $total_projects ?></span> results
                </p>
            </div>
            <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                    <a href="<?= buildProjectUrl(['page' => $page - 1]) ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
                        <span class="sr-only">Previous</span>
                        <i class="fas fa-chevron-left h-4 w-4"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == 1 || $i == $total_pages || abs($i - $page) <= 2): ?>
                            <a href="<?= buildProjectUrl(['page' => $i]) ?>" aria-current="page" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?= $i === $page ? 'z-10 bg-brand-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600' : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50' ?> focus:z-20">
                                <?= $i ?>
                            </a>
                        <?php elseif (abs($i - $page) == 3): ?>
                            <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300 focus:outline-offset-0">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="<?= buildProjectUrl(['page' => $page + 1]) ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
                        <span class="sr-only">Next</span>
                        <i class="fas fa-chevron-right h-4 w-4"></i>
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php require_once 'footer.php'; ?>
<?php
require_once 'header.php';

$user_id = $_SESSION['user_id'];
$division_id = get_user_division();
$role = $_SESSION['role'];

// Fetch Statistics based on role
$stats = [
    'total_tasks' => 0,
    'completed_tasks' => 0,
    'in_progress_tasks' => 0,
    'kpi_progress' => 0
];

if (is_super_admin()) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks");
    $stats['total_tasks'] = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'completed'");
    $stats['completed_tasks'] = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'in_progress'");
    $stats['in_progress_tasks'] = $stmt->fetchColumn();
    
    // KPI progress (total achieved / total target where target > 0)
    $stmt = $pdo->query("SELECT SUM(t.target_value) as total_target, 
                         (SELECT SUM(achievement_value) FROM task_progress) as total_achieved 
                         FROM tasks t WHERE t.target_value > 0");
    $res = $stmt->fetch();
    if ($res && $res['total_target'] > 0) {
        $stats['kpi_progress'] = min(100, round(($res['total_achieved'] / $res['total_target']) * 100));
    }
} elseif (is_division_head()) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE division_id = ?");
    $stmt->execute([$division_id]);
    $stats['total_tasks'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE division_id = ? AND status = 'completed'");
    $stmt->execute([$division_id]);
    $stats['completed_tasks'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE division_id = ? AND status = 'in_progress'");
    $stmt->execute([$division_id]);
    $stats['in_progress_tasks'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT SUM(t.target_value) as total_target, 
                         (SELECT SUM(p.achievement_value) FROM task_progress p JOIN tasks t2 ON p.task_id = t2.id WHERE t2.division_id = ?) as total_achieved 
                         FROM tasks t WHERE t.division_id = ? AND t.target_value > 0");
    $stmt->execute([$division_id, $division_id]);
    $res = $stmt->fetch();
    if ($res && $res['total_target'] > 0) {
        $stats['kpi_progress'] = min(100, round(($res['total_achieved'] / $res['total_target']) * 100));
    }
} else {
    // Regular User
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ?");
    $stmt->execute([$user_id]);
    $stats['total_tasks'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $stats['completed_tasks'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'in_progress'");
    $stmt->execute([$user_id]);
    $stats['in_progress_tasks'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT SUM(t.target_value) as total_target, 
                         (SELECT SUM(p.achievement_value) FROM task_progress p JOIN tasks t2 ON p.task_id = t2.id WHERE t2.assigned_to = ?) as total_achieved 
                         FROM tasks t WHERE t.assigned_to = ? AND t.target_value > 0");
    $stmt->execute([$user_id, $user_id]);
    $res = $stmt->fetch();
    if ($res && $res['total_target'] > 0) {
        $stats['kpi_progress'] = min(100, round(($res['total_achieved'] / $res['total_target']) * 100));
    }
}

// Chart Data (Last 7 days progress)
$chart_labels = [];
$chart_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('M j', strtotime($date));
    
    if (is_super_admin()) {
        $stmt = $pdo->prepare("SELECT SUM(achievement_value) FROM task_progress WHERE date = ?");
        $stmt->execute([$date]);
    } elseif (is_division_head()) {
        $stmt = $pdo->prepare("SELECT SUM(p.achievement_value) FROM task_progress p JOIN tasks t ON p.task_id = t.id WHERE p.date = ? AND t.division_id = ?");
        $stmt->execute([$date, $division_id]);
    } else {
        $stmt = $pdo->prepare("SELECT SUM(p.achievement_value) FROM task_progress p JOIN tasks t ON p.task_id = t.id WHERE p.date = ? AND t.assigned_to = ?");
        $stmt->execute([$date, $user_id]);
    }
    $val = (int)$stmt->fetchColumn();
    $chart_data[] = $val;
}

// Recent Tasks
if (is_super_admin()) {
    $stmt = $pdo->query("SELECT id, title, status, due_date FROM tasks ORDER BY created_at DESC LIMIT 5");
} elseif (is_division_head()) {
    $stmt = $pdo->prepare("SELECT id, title, status, due_date FROM tasks WHERE division_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$division_id]);
} else {
    $stmt = $pdo->prepare("SELECT id, title, status, due_date FROM tasks WHERE assigned_to = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
}
$recent_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getStatusColor($status) {
    $colors = [
        'pending' => 'bg-slate-100 text-slate-600',
        'in_progress' => 'bg-blue-100 text-blue-700',
        'completed' => 'bg-green-100 text-green-700',
        'forwarded' => 'bg-purple-100 text-purple-700',
        'reopened' => 'bg-red-100 text-red-700'
    ];
    return $colors[$status] ?? 'bg-slate-100 text-slate-600';
}

// Calculate Top Performers in Division (Last 7 Days KPI)
$top_handlers = [];
$stmt = $pdo->prepare("SELECT u.id, u.full_name,
                        (SELECT SUM(target_value) FROM tasks WHERE assigned_to = u.id AND target_value > 0) as total_target,
                        (SELECT SUM(p.achievement_value) FROM task_progress p JOIN tasks t ON p.task_id = t.id WHERE t.assigned_to = u.id AND p.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as achieved_7d,
                        (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) as tasks_completed_7d,
                        (SELECT COUNT(*) FROM projects WHERE created_by = u.id) as total_projects,
                        (SELECT AVG(completion) FROM projects WHERE created_by = u.id) as avg_project_completion
                       FROM users u 
                       WHERE u.division_id = ?
                       ");
$stmt->execute([$division_id]);
$users_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($users_data as $u) {
    $target = (int)$u['total_target'];
    $achieved = (int)$u['achieved_7d'];
    $task_kpi = $target > 0 ? min(100, round(($achieved / $target) * 100)) : 0;
    
    $proj_kpi = $u['total_projects'] > 0 ? (int)round($u['avg_project_completion']) : 0;
    
    if ($target > 0 && $u['total_projects'] > 0) {
        $kpi_percent = round(($task_kpi + $proj_kpi) / 2);
    } elseif ($target > 0) {
        $kpi_percent = $task_kpi;
    } elseif ($u['total_projects'] > 0) {
        $kpi_percent = $proj_kpi;
    } else {
        $kpi_percent = 0;
    }
    
    $u['tasks_completed_7d'] = (int)$u['tasks_completed_7d'];
    $u['kpi_percent'] = $kpi_percent;
    $top_handlers[] = $u;
}

// Sort by Completed Tasks (DESC), then by KPI (DESC)
usort($top_handlers, function($a, $b) {
    if ($a['tasks_completed_7d'] === $b['tasks_completed_7d']) {
        return $b['kpi_percent'] <=> $a['kpi_percent'];
    }
    return $b['tasks_completed_7d'] <=> $a['tasks_completed_7d'];
});

// Take top 10 active (either completed tasks > 0 OR kpi > 0)
$top_handlers = array_filter($top_handlers, function($u) { 
    return $u['tasks_completed_7d'] > 0 || $u['kpi_percent'] > 0; 
});
$top_handlers = array_slice($top_handlers, 0, 10);

// Fetch Pending Tasks for the current user
$stmt = $pdo->prepare("SELECT t.id, t.title, t.status, t.priority, t.due_date, t.target_value,
                        (SELECT SUM(achievement_value) FROM task_progress p WHERE p.task_id = t.id) as achieved
                       FROM tasks t 
                       WHERE t.assigned_to = ? AND t.status != 'completed' 
                       ORDER BY t.due_date ASC");
$stmt->execute([$user_id]);
$my_pending_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>! 👋</h1>
            <p class="text-slate-500 text-sm mt-1">Here's your KPI tracker overview for today.</p>
        </div>
        
        <?php if (is_super_admin() || is_division_head() || in_array('create_task', $rolePermissions[$_SESSION['role']] ?? [])): ?>
        <a href="create_task.php" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-medium rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-2">
            <i class="fas fa-plus"></i> New Task
        </a>
        <?php endif; ?>
    </div>

    <!-- Top Projects and Task Handlers -->
    <?php if (count($top_handlers) > 0): ?>
    <div class="mb-2">
        <div class="flex items-center gap-2 mb-4">
            <i class="fas fa-trophy text-amber-500 text-xl drop-shadow-sm"></i>
            <h2 class="text-xl font-bold text-slate-800 tracking-tight">Top Performers (Last 7 Days)</h2>
        </div>
        
        <div class="flex overflow-x-auto pb-4 gap-4 snap-x hide-scrollbar">
            <?php foreach($top_handlers as $index => $handler): 
                $is_first = $index === 0;
                $is_second = $index === 1;
                $is_third = $index === 2;
                
                $border_class = 'border-slate-200';
                $bg_class = 'bg-white';
                $badge_class = 'bg-slate-100 text-slate-500';
                $avatar_class = 'bg-slate-100 text-slate-600';
                
                if ($is_first) {
                    $border_class = 'border-amber-300 shadow-amber-100/50 shadow-lg ring-1 ring-amber-100 bg-gradient-to-b from-amber-50/50 to-white';
                    $badge_class = 'bg-amber-100 text-amber-700 shadow-sm border border-amber-200';
                    $avatar_class = 'bg-gradient-to-br from-amber-400 to-amber-500 text-white shadow-md';
                } elseif ($is_second) {
                    $border_class = 'border-slate-300 shadow-slate-200/50 shadow-md bg-gradient-to-b from-slate-50/50 to-white';
                    $badge_class = 'bg-slate-200 text-slate-700 shadow-sm border border-slate-300';
                    $avatar_class = 'bg-gradient-to-br from-slate-300 to-slate-400 text-white shadow-sm';
                } elseif ($is_third) {
                    $border_class = 'border-orange-200 shadow-orange-100/50 shadow-md bg-gradient-to-b from-orange-50/20 to-white';
                    $badge_class = 'bg-orange-100 text-orange-800 shadow-sm border border-orange-200';
                    $avatar_class = 'bg-gradient-to-br from-orange-300 to-orange-400 text-white shadow-sm';
                }
            ?>
            <div class="min-w-[140px] max-w-[160px] flex-1 rounded-2xl p-4 border <?= $border_class ?> flex flex-col items-center text-center transition-all hover:-translate-y-1 snap-start relative">
                
                <?php if ($is_first): ?>
                    <div class="absolute -top-3 -right-2 transform rotate-12 z-10">
                        <i class="fas fa-crown text-amber-400 text-3xl drop-shadow-md"></i>
                    </div>
                <?php endif; ?>
                
                <div class="w-14 h-14 rounded-full <?= $avatar_class ?> flex items-center justify-center font-bold text-xl mb-3 relative z-0">
                    <?= strtoupper(substr($handler['full_name'], 0, 1)) ?>
                </div>
                
                <div class="font-bold text-slate-800 text-sm line-clamp-1 w-full" title="<?= htmlspecialchars($handler['full_name']) ?>">
                    <?= htmlspecialchars($handler['full_name']) ?>
                </div>
                
                <div class="mt-3 w-full bg-slate-50 p-2 rounded-lg border border-slate-100 flex flex-col gap-1.5">
                    <div class="text-[11px] font-bold <?= $is_first ? 'text-amber-600' : 'text-slate-600' ?> flex items-center justify-center gap-1">
                        <i class="fas fa-check-double text-[10px] opacity-75"></i> <?= $handler['tasks_completed_7d'] ?> Tasks
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden group-hover:bg-slate-300 transition-colors" title="<?= $handler['kpi_percent'] ?>% KPI">
                        <div class="<?= $is_first ? 'bg-amber-400' : 'bg-brand-500' ?> h-1.5 rounded-full" style="width: <?= $handler['kpi_percent'] ?>%"></div>
                    </div>
                </div>
                
                <div class="absolute top-2 left-2 w-6 h-6 rounded-full <?= $badge_class ?> flex items-center justify-center text-xs font-black">
                    <?= $index + 1 ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <style>
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
    <?php endif; ?>

    <!-- My Pending Tasks -->
    <?php if (count($my_pending_tasks) > 0): ?>
    <div class="mb-4">
        <div class="flex items-center gap-2 mb-4">
            <i class="fas fa-tasks text-brand-500 text-xl"></i>
            <h2 class="text-xl font-bold text-slate-800 tracking-tight">My Pending Tasks</h2>
            <span class="bg-red-100 text-red-600 text-xs font-bold px-2.5 py-0.5 rounded-full shadow-sm animate-pulse ml-2"><?= count($my_pending_tasks) ?> Action Required</span>
        </div>
        
        <div class="flex overflow-x-auto pb-6 gap-4 snap-x hide-scrollbar px-1">
            <?php foreach($my_pending_tasks as $task): 
                $is_overdue = $task['due_date'] && strtotime($task['due_date']) < time();
                $is_high_priority = strtolower($task['priority']) === 'high' || strtolower($task['priority']) === 'urgent';
                
                $border_class = 'border-slate-200';
                if ($is_overdue) {
                    $border_class = 'border-red-300 ring-2 ring-red-100 ring-offset-2';
                } elseif ($is_high_priority) {
                    $border_class = 'border-orange-300 ring-2 ring-orange-100 ring-offset-2';
                }
                
                // Progress
                $target = (int)$task['target_value'];
                $achieved = (int)$task['achieved'];
                $progress_pct = $target > 0 ? min(100, round(($achieved / $target) * 100)) : 0;
            ?>
            <a href="task_details.php?id=<?= $task['id'] ?>" class="min-w-[260px] max-w-[280px] flex-1 rounded-2xl p-5 bg-white border <?= $border_class ?> flex flex-col transition-all hover:-translate-y-1.5 hover:shadow-lg snap-start relative group cursor-pointer">
                
                <?php if ($is_overdue): ?>
                    <div class="absolute -top-1.5 -right-1.5">
                        <span class="flex h-3 w-3">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500 shadow-sm border border-white"></span>
                        </span>
                    </div>
                <?php elseif ($is_high_priority): ?>
                    <div class="absolute -top-1.5 -right-1.5">
                        <span class="flex h-3 w-3">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75" style="animation-duration: 2s;"></span>
                          <span class="relative inline-flex rounded-full h-3 w-3 bg-orange-500 shadow-sm border border-white"></span>
                        </span>
                    </div>
                <?php endif; ?>
                
                <div class="flex justify-between items-start mb-3">
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded capitalize <?= getStatusColor($task['status']) ?> shadow-sm border border-white/50"><?= str_replace('_', ' ', $task['status']) ?></span>
                    <?php if ($task['due_date']): ?>
                        <span class="text-[11px] font-bold <?= $is_overdue ? 'text-red-500 bg-red-50 px-1.5 py-0.5 rounded' : 'text-slate-400' ?> flex items-center gap-1.5">
                            <i class="far fa-clock"></i> <?= date('M j', strtotime($task['due_date'])) ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <h4 class="font-bold text-slate-800 text-sm leading-snug line-clamp-2 mb-4 group-hover:text-brand-600 transition-colors flex-1" title="<?= htmlspecialchars($task['title']) ?>">
                    <?= htmlspecialchars($task['title']) ?>
                </h4>
                
                <?php if ($target > 0): ?>
                <div class="mt-auto bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                    <div class="flex justify-between items-center text-[10px] text-slate-500 font-bold mb-1.5 uppercase tracking-wide">
                        <span>Progress</span>
                        <span class="<?= $progress_pct >= 100 ? 'text-green-600' : 'text-brand-600' ?> font-black"><?= $progress_pct ?>%</span>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                        <div class="<?= $progress_pct >= 100 ? 'bg-green-500' : 'bg-brand-500' ?> h-1.5 rounded-full transition-all group-hover:brightness-110" style="width: <?= $progress_pct ?>%"></div>
                    </div>
                </div>
                <?php else: ?>
                <div class="mt-auto text-[11px] text-slate-400 font-semibold italic bg-slate-50 px-3 py-2 rounded-xl text-center border border-slate-100">No target set</div>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-blue-50 rounded-full group-hover:scale-110 transition-transform"></div>
            <div class="relative z-10 flex flex-col h-full">
                <span class="text-slate-500 font-medium mb-2">Total Tasks</span>
                <span class="text-3xl font-bold text-slate-800"><?= $stats['total_tasks'] ?></span>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-green-50 rounded-full group-hover:scale-110 transition-transform"></div>
            <div class="relative z-10 flex flex-col h-full">
                <span class="text-slate-500 font-medium mb-2">Completed</span>
                <span class="text-3xl font-bold text-slate-800"><?= $stats['completed_tasks'] ?></span>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm relative overflow-hidden group hover:shadow-md transition-shadow">
            <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-orange-50 rounded-full group-hover:scale-110 transition-transform"></div>
            <div class="relative z-10 flex flex-col h-full">
                <span class="text-slate-500 font-medium mb-2">In Progress</span>
                <span class="text-3xl font-bold text-slate-800"><?= $stats['in_progress_tasks'] ?></span>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-brand-500 to-brand-700 rounded-2xl p-6 shadow-md relative overflow-hidden text-white group hover:shadow-lg transition-shadow">
            <div class="absolute right-0 top-0 w-32 h-32 bg-white/10 rounded-bl-full group-hover:scale-110 transition-transform"></div>
            <div class="relative z-10 flex flex-col h-full">
                <span class="text-brand-100 font-medium mb-2">KPI Progress</span>
                <div class="flex items-end gap-2">
                    <span class="text-3xl font-bold"><?= $stats['kpi_progress'] ?>%</span>
                </div>
                <div class="w-full bg-black/20 rounded-full h-1.5 mt-3">
                    <div class="bg-white h-1.5 rounded-full" style="width: <?= $stats['kpi_progress'] ?>%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Chart -->
        <div class="lg:col-span-2 bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
            <h3 class="text-lg font-bold text-slate-800 mb-6">KPI Activity (Last 7 Days)</h3>
            <div class="relative h-[300px] w-full">
                <canvas id="kpiChart"></canvas>
            </div>
        </div>
        
        <!-- Recent Tasks -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex flex-col">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-slate-800">Recent Tasks</h3>
                <a href="tasks.php" class="text-sm font-medium text-brand-600 hover:text-brand-700">View All</a>
            </div>
            
            <div class="space-y-4 flex-1">
                <?php if(empty($recent_tasks)): ?>
                    <div class="text-center text-slate-500 py-8">No recent tasks.</div>
                <?php else: ?>
                    <?php foreach($recent_tasks as $t): ?>
                    <a href="task_details.php?id=<?= $t['id'] ?>" class="block p-4 border border-slate-100 rounded-xl hover:border-brand-200 hover:bg-brand-50/50 transition-all group">
                        <div class="flex items-start justify-between mb-2">
                            <span class="text-xs font-semibold px-2 py-1 rounded <?= getStatusColor($t['status']) ?> capitalize">
                                <?= str_replace('_', ' ', $t['status']) ?>
                            </span>
                            <span class="text-xs text-slate-400 group-hover:text-brand-500 transition-colors">
                                <?= date('M j', strtotime($t['due_date'])) ?>
                            </span>
                        </div>
                        <h4 class="font-bold text-slate-800 line-clamp-1 group-hover:text-brand-700 transition-colors"><?= htmlspecialchars($t['title']) ?></h4>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize Chart
    const ctx = document.getElementById('kpiChart').getContext('2d');
    
    // Gradient fill
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(59, 130, 246, 0.2)'); // brand-500
    gradient.addColorStop(1, 'rgba(59, 130, 246, 0)');
    
    const kpiChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'KPI Achievements',
                data: <?= json_encode($chart_data) ?>,
                borderColor: '#3b82f6',
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#3b82f6',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    titleFont: { size: 13, family: 'Inter' },
                    bodyFont: { size: 14, family: 'Inter', weight: 'bold' },
                    displayColors: false,
                    cornerRadius: 8,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f1f5f9',
                        drawBorder: false,
                    },
                    border: { dash: [4, 4] },
                    ticks: {
                        font: { family: 'Inter', size: 12 },
                        color: '#94a3b8',
                        padding: 10
                    }
                },
                x: {
                    grid: {
                        display: false,
                        drawBorder: false,
                    },
                    ticks: {
                        font: { family: 'Inter', size: 12 },
                        color: '#94a3b8',
                        padding: 10
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index',
            },
        }
    });
</script>

<?php require_once 'footer.php'; ?>
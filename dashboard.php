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

// Fetch Team KPIs for Division Head
$team_kpis = [];
if (is_division_head()) {
    $stmt = $pdo->prepare("SELECT u.id, u.full_name, u.role, ds.name as designation,
                            (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id) as total_tasks,
                            (SELECT COUNT(*) FROM tasks WHERE assigned_to = u.id AND status = 'completed') as completed_tasks,
                            (SELECT SUM(target_value) FROM tasks WHERE assigned_to = u.id AND target_value > 0) as total_target,
                            (SELECT SUM(p.achievement_value) FROM task_progress p JOIN tasks t ON p.task_id = t.id WHERE t.assigned_to = u.id) as total_achieved
                           FROM users u 
                           LEFT JOIN designations ds ON u.designation_id = ds.id
                           WHERE u.division_id = ? AND u.role != 'division_head' 
                           ORDER BY u.full_name");
    $stmt->execute([$division_id]);
    $team_kpis = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

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
    
    <!-- Team Member KPIs (Division Head Only) -->
    <?php if (is_division_head() && !empty($team_kpis)): ?>
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm mt-6">
        <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
            <i class="fas fa-users text-brand-500"></i> Team Member KPIs
        </h3>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="px-4 py-3 rounded-tl-lg">Team Member</th>
                        <th class="px-4 py-3">Tasks (Completed/Total)</th>
                        <th class="px-4 py-3">Target Achieved</th>
                        <th class="px-4 py-3 rounded-tr-lg">KPI Progress</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    <?php foreach($team_kpis as $member): 
                        $target = (int)$member['total_target'];
                        $achieved = (int)$member['total_achieved'];
                        $kpi_percent = $target > 0 ? min(100, round(($achieved / $target) * 100)) : 0;
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs shrink-0">
                                    <?= strtoupper(substr($member['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-900"><?= htmlspecialchars($member['full_name']) ?></div>
                                    <div class="text-xs text-slate-500"><?= htmlspecialchars($member['designation'] ?? 'Team Member') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <span class="font-medium text-slate-900"><?= $member['completed_tasks'] ?></span>
                            <span class="text-slate-400">/ <?= $member['total_tasks'] ?></span>
                        </td>
                        <td class="px-4 py-4 font-medium text-slate-700">
                            <?= $target > 0 ? "$achieved / $target" : "<span class='text-slate-400 italic text-xs'>No targets set</span>" ?>
                        </td>
                        <td class="px-4 py-4">
                            <?php if ($target > 0): ?>
                            <div class="flex items-center gap-3">
                                <div class="w-full bg-slate-100 rounded-full h-2 max-w-[100px]">
                                    <div class="h-2 rounded-full <?= $kpi_percent >= 100 ? 'bg-green-500' : 'bg-brand-500' ?>" style="width: <?= $kpi_percent ?>%"></div>
                                </div>
                                <span class="font-bold text-xs <?= $kpi_percent >= 100 ? 'text-green-600' : 'text-slate-700' ?>"><?= $kpi_percent ?>%</span>
                            </div>
                            <?php else: ?>
                            <span class="text-slate-300">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
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
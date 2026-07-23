<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'config.php';

// Fetch Division Features
$user_features = [];
if (isset($_SESSION['user_id'])) {
    $current_div_id = get_user_division();
    if (is_super_admin()) {
        $stmt = $pdo->query("SELECT DISTINCT feature_key FROM division_features");
        $user_features = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $stmt = $pdo->prepare("SELECT feature_key, access_level FROM division_features WHERE division_id = ?");
        $stmt->execute([$current_div_id]);
        $features = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($features as $f) {
            if ($f['access_level'] === 'all' || ($f['access_level'] === 'division_heads_only' && is_division_head())) {
                $user_features[] = $f['feature_key'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPI Task Tracker Pro</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9; 
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1; 
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8; 
        }
        
        /* Glassmorphism utility */
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased flex h-screen overflow-hidden" x-data="{ sidebarOpen: false, notificationsOpen: false }">

    <!-- Sidebar -->
    <aside class="bg-white w-64 h-full border-r border-slate-200 flex flex-col transition-all duration-300 z-20 absolute lg:relative shadow-sm"
           :class="{'translate-x-0': sidebarOpen, '-translate-x-full lg:translate-x-0': !sidebarOpen}">
        
        <div class="h-16 flex items-center justify-between px-6 border-b border-slate-100">
            <a href="dashboard.php" class="flex items-center gap-3">
                <div class="w-8 h-8 rounded bg-gradient-to-br from-brand-500 to-brand-700 text-white flex items-center justify-center font-bold text-lg shadow-md">
                    K
                </div>
                <span class="font-bold text-lg tracking-tight text-slate-800">KPI Tracker</span>
            </a>
            <button @click="sidebarOpen = false" class="lg:hidden text-slate-400 hover:text-slate-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2 px-3">Main</div>
            <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-brand-600 transition-colors">
                <i class="fas fa-home w-5 text-center"></i>
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="tasks.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-brand-600 transition-colors">
                <i class="fas fa-tasks w-5 text-center"></i>
                <span class="font-medium">My Tasks</span>
            </a>
            
            <a href="team_kpis.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-brand-600 transition-colors">
                <i class="fas fa-chart-line w-5 text-center"></i>
                <span class="font-medium">Team KPIs</span>
            </a>
            
            <a href="projects.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-brand-600 transition-colors">
                <i class="fas fa-project-diagram w-5 text-center"></i>
                <span class="font-medium">Projects</span>
            </a>
            
            <?php if (!empty($user_features)): ?>
                <div class="mt-6 mb-2 text-xs font-semibold text-slate-400 uppercase tracking-wider px-3">Tools</div>
                <?php if (in_array('budget_calculator', $user_features)): ?>
                <a href="budget_cal.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-brand-600 transition-colors">
                    <i class="fas fa-calculator w-5 text-center"></i>
                    <span class="font-medium">Budget Calculator</span>
                </a>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php if (is_super_admin() || is_division_head()): ?>
                <div class="mt-6 mb-2 text-xs font-semibold text-slate-400 uppercase tracking-wider px-3">Administration</div>
                <a href="manage_users.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-brand-600 transition-colors">
                    <i class="fas fa-users w-5 text-center"></i>
                    <span class="font-medium">Users</span>
                </a>
                
                <?php if (is_super_admin()): ?>
                    <a href="manage_divisions.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-brand-600 transition-colors">
                        <i class="fas fa-building w-5 text-center"></i>
                        <span class="font-medium">Divisions</span>
                    </a>
                    <a href="manage_designations.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-brand-600 transition-colors">
                        <i class="fas fa-id-badge w-5 text-center"></i>
                        <span class="font-medium">Designations</span>
                    </a>
                    <a href="feature_toggles.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-50 text-slate-600 hover:text-brand-600 transition-colors">
                        <i class="fas fa-toggle-on w-5 text-center"></i>
                        <span class="font-medium">Feature Toggles</span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="p-4 border-t border-slate-100">
            <div class="flex items-center gap-3 px-3 py-2">
                <div class="w-10 h-10 rounded-full bg-brand-100 text-brand-600 flex items-center justify-center font-bold">
                    <?= substr($_SESSION['username'], 0, 1) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <a href="profile.php" class="block hover:opacity-80 transition-opacity" title="View Profile">
                        <p class="text-sm font-medium text-slate-900 truncate"><?= htmlspecialchars($_SESSION['username']) ?></p>
                        <p class="text-xs text-slate-500 truncate capitalize"><?= str_replace('_', ' ', $_SESSION['role']) ?></p>
                    </a>
                </div>
                <div class="flex gap-3">
                    <a href="profile.php" class="text-slate-400 hover:text-brand-500 transition-colors" title="Profile Settings">
                        <i class="fas fa-user-cog"></i>
                    </a>
                    <a href="logout.php" class="text-slate-400 hover:text-red-500 transition-colors" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </aside>

    <!-- Overlay for mobile sidebar -->
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 bg-slate-900/50 z-10 lg:hidden" @click="sidebarOpen = false" x-cloak></div>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
        
        <!-- Top Header -->
        <header class="h-16 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 z-10">
            <button @click="sidebarOpen = true" class="lg:hidden text-slate-500 hover:text-slate-700">
                <i class="fas fa-bars text-lg"></i>
            </button>
            
            <div class="flex-1"></div>
            
            <div class="flex items-center gap-4">
                <!-- Notifications -->
                <div class="relative" x-data="{ count: 0 }" x-init="
                    fetch('api_notifications.php?action=count')
                        .then(res => res.json())
                        .then(data => count = data.count);
                ">
                    <button @click="notificationsOpen = !notificationsOpen" class="relative p-2 text-slate-400 hover:text-brand-600 transition-colors rounded-full hover:bg-slate-50">
                        <i class="fas fa-bell text-xl"></i>
                        <span x-show="count > 0" x-text="count" class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold flex items-center justify-center rounded-full border border-white" x-cloak></span>
                    </button>
                    
                    <!-- Notification Dropdown -->
                    <div x-show="notificationsOpen" @click.away="notificationsOpen = false" x-transition x-cloak class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-slate-100 overflow-hidden z-50">
                        <div class="px-4 py-3 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                            <h3 class="font-semibold text-slate-800">Notifications</h3>
                            <button class="text-xs text-brand-600 hover:text-brand-700 font-medium" @click="fetch('api_notifications.php?action=mark_all_read').then(()=>count=0)">Mark all read</button>
                        </div>
                        <div class="max-h-96 overflow-y-auto" id="notification-list" x-init="$watch('notificationsOpen', value => {
                            if(value) {
                                fetch('api_notifications.php?action=list')
                                    .then(res => res.text())
                                    .then(html => $el.innerHTML = html);
                            }
                        })">
                            <div class="p-4 text-center text-sm text-slate-500">Loading...</div>
                        </div>
                        <a href="notifications.php" class="block px-4 py-2 text-center text-sm text-brand-600 hover:bg-slate-50 border-t border-slate-100 font-medium">View All</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">

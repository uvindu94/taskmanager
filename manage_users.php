<?php
require_once 'header.php';

if (!is_super_admin() && !is_division_head()) {
    echo "<div class='p-4 bg-red-100 text-red-700 rounded-lg'>Access denied.</div>";
    require_once 'footer.php';
    exit;
}

$message = '';
$error = '';
$current_division_id = get_user_division();
$is_super = is_super_admin();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'create') {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $full_name = trim($_POST['full_name']);
            $role = $_POST['role'];
            $email = trim($_POST['email']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $designation_id = !empty($_POST['designation_id']) ? (int)$_POST['designation_id'] : null;
            
            // Division head can only assign to their own division
            $division_id = $is_super ? (!empty($_POST['division_id']) ? (int)$_POST['division_id'] : null) : $current_division_id;

            if ($username && $password && $full_name && $role) {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = "Username already exists.";
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, is_active, email, division_id, designation_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $hash, $full_name, $role, $is_active, $email, $division_id, $designation_id]);
                        $message = "User created successfully.";
                    }
                } catch (PDOException $e) {
                    $error = "Error creating user: " . $e->getMessage();
                }
            } else {
                $error = "Please fill all required fields.";
            }
        } elseif ($action === 'edit') {
            $edit_id = (int)$_POST['edit_id'];
            $username = trim($_POST['username']);
            $full_name = trim($_POST['full_name']);
            $role = $_POST['role'];
            $email = trim($_POST['email']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $password = $_POST['password']; // optional
            $designation_id = !empty($_POST['designation_id']) ? (int)$_POST['designation_id'] : null;
            
            // Validate edit permission
            // Division head can only edit users in their division
            $can_edit = false;
            if ($is_super) {
                $can_edit = true;
                $division_id = !empty($_POST['division_id']) ? (int)$_POST['division_id'] : null;
            } else {
                $stmt = $pdo->prepare("SELECT division_id FROM users WHERE id = ?");
                $stmt->execute([$edit_id]);
                $target_div = $stmt->fetchColumn();
                if ($target_div == $current_division_id) {
                    $can_edit = true;
                    $division_id = $current_division_id;
                }
            }
            
            if ($can_edit && $username && $full_name && $role) {
                try {
                    // Check username unique
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                    $stmt->execute([$username, $edit_id]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = "Username already in use.";
                    } else {
                        if (!empty($password)) {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, role = ?, is_active = ?, email = ?, division_id = ?, designation_id = ? WHERE id = ?");
                            $stmt->execute([$username, $hash, $full_name, $role, $is_active, $email, $division_id, $designation_id, $edit_id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, role = ?, is_active = ?, email = ?, division_id = ?, designation_id = ? WHERE id = ?");
                            $stmt->execute([$username, $full_name, $role, $is_active, $email, $division_id, $designation_id, $edit_id]);
                        }
                        $message = "User updated successfully.";
                    }
                } catch (PDOException $e) {
                    $error = "Error updating user: " . $e->getMessage();
                }
            } else {
                $error = $can_edit ? "Please fill all required fields." : "Permission denied to edit this user.";
            }
        }
    }
}

// Pagination & Filters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = trim($_GET['search'] ?? '');
$filter_division = $_GET['division_id'] ?? '';

// Build Query
$where_clauses = [];
$params = [];

if (!$is_super) {
    $where_clauses[] = "u.division_id = ?";
    $params[] = $current_division_id;
} elseif ($filter_division !== '') {
    $where_clauses[] = "u.division_id = ?";
    $params[] = $filter_division;
}

if ($search !== '') {
    $where_clauses[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Count total
$count_sql = "SELECT COUNT(*) FROM users u $where_sql";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $limit);

// Fetch Users
$sql = "SELECT u.*, d.name as division_name, ds.name as designation_name 
        FROM users u 
        LEFT JOIN divisions d ON u.division_id = d.id 
        LEFT JOIN designations ds ON u.designation_id = ds.id 
        $where_sql 
        ORDER BY u.created_at DESC 
        LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key + 1, $val);
}
$stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Divisions and Designations for dropdowns
$divisions = [];
if ($is_super) {
    $stmt = $pdo->query("SELECT id, name FROM divisions ORDER BY name");
    $divisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$stmt = $pdo->query("SELECT id, name FROM designations ORDER BY name");
$designations = $stmt->fetchAll(PDO::FETCH_ASSOC);

function buildUserUrl($params_to_update) {
    $current = $_GET;
    $merged = array_merge($current, $params_to_update);
    return '?' . http_build_query($merged);
}
?>

<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Manage Users</h1>
            <p class="text-slate-500 text-sm mt-1">
                <?= $is_super ? "Manage all company users." : "Manage users in your division." ?>
            </p>
        </div>
        <button @click="$dispatch('open-modal', {id: 'createUser'})" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-medium rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-2">
            <i class="fas fa-user-plus"></i> New User
        </button>
    </div>

    <?php if ($message): ?>
    <div class="p-4 bg-green-50 text-green-700 border border-green-200 rounded-xl flex items-center gap-3 shadow-sm">
        <i class="fas fa-check-circle text-green-500"></i>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="p-4 bg-red-50 text-red-700 border border-red-200 rounded-xl flex items-center gap-3 shadow-sm">
        <i class="fas fa-exclamation-circle text-red-500"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Controls Bar -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200">
        <form method="GET" class="flex flex-col sm:flex-row gap-4">
            
            <div class="flex-1 relative">
                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, username or email..." class="w-full pl-11 pr-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors">
                <?php if($search): ?>
                    <a href="<?= buildUserUrl(['search' => '', 'page' => 1]) ?>" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
            
            <?php if ($is_super): ?>
            <select name="division_id" onchange="this.form.submit()" class="px-4 py-2.5 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl text-sm focus:ring-2 focus:ring-brand-500 focus:bg-white outline-none transition-colors min-w-[200px] cursor-pointer">
                <option value="">All Divisions</option>
                <?php foreach($divisions as $div): ?>
                    <option value="<?= $div['id'] ?>" <?= $filter_division == $div['id'] ? 'selected' : '' ?>><?= htmlspecialchars($div['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            
            <!-- Hidden submit -->
            <button type="submit" class="hidden"></button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden" x-data="{}">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4">User</th>
                        <th class="px-6 py-4">Role & Designation</th>
                        <th class="px-6 py-4">Division</th>
                        <th class="px-6 py-4">Joined</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    <?php if (count($users) === 0): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                            <i class="fas fa-search text-3xl mb-3 text-slate-300"></i>
                            <p class="font-medium text-slate-600">No users found.</p>
                            <p class="text-xs text-slate-400 mt-1">Try adjusting your search or filters.</p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php foreach ($users as $u): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center font-bold">
                                    <?= strtoupper(substr($u['full_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-medium text-slate-900"><?= htmlspecialchars($u['full_name']) ?></div>
                                    <div class="text-xs text-slate-500">@<?= htmlspecialchars($u['username']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col items-start gap-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 capitalize">
                                    <?= str_replace('_', ' ', $u['role']) ?>
                                </span>
                                <?php if($u['designation_name']): ?>
                                    <span class="text-xs text-slate-500 italic"><?= htmlspecialchars($u['designation_name']) ?></span>
                                <?php endif; ?>
                                <?php if(!$u['is_active']): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700 mt-1 uppercase tracking-wider">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-slate-600">
                            <?= $u['division_name'] ? htmlspecialchars($u['division_name']) : '<span class="text-slate-400 italic">None</span>' ?>
                        </td>
                        <td class="px-6 py-4 text-slate-500">
                            <?= date('M j, Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button @click="$dispatch('open-modal', {
                                        id: 'editUser', 
                                        user: {
                                            id: <?= $u['id'] ?>, 
                                            username: '<?= addslashes($u['username']) ?>', 
                                            full_name: '<?= addslashes($u['full_name']) ?>', 
                                            email: '<?= addslashes($u['email'] ?? '') ?>', 
                                            role: '<?= $u['role'] ?>', 
                                            is_active: <?= $u['is_active'] ? 'true' : 'false' ?>,
                                            division_id: '<?= $u['division_id'] ?>',
                                            designation_id: '<?= $u['designation_id'] ?>'
                                        }
                                    })" 
                                    class="text-brand-600 hover:text-brand-800 p-2 rounded-lg hover:bg-brand-50 transition-colors" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex items-center justify-between bg-white px-4 py-3 sm:px-6 rounded-xl border border-slate-200 shadow-sm mt-6">
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-slate-700">
                    Showing <span class="font-medium"><?= $offset + 1 ?></span> to 
                    <span class="font-medium"><?= min($offset + $limit, $total_users) ?></span> of 
                    <span class="font-medium"><?= $total_users ?></span> results
                </p>
            </div>
            <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                    <a href="<?= buildUserUrl(['page' => $page - 1]) ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
                        <span class="sr-only">Previous</span>
                        <i class="fas fa-chevron-left h-4 w-4"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == 1 || $i == $total_pages || abs($i - $page) <= 2): ?>
                            <a href="<?= buildUserUrl(['page' => $i]) ?>" aria-current="page" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold <?= $i === $page ? 'z-10 bg-brand-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600' : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50' ?> focus:z-20">
                                <?= $i ?>
                            </a>
                        <?php elseif (abs($i - $page) == 3): ?>
                            <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300 focus:outline-offset-0">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="<?= buildUserUrl(['page' => $page + 1]) ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-400 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20 focus:outline-offset-0">
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

<!-- Modal for Create User -->
<div x-data="{ open: false }" 
     x-on:open-modal.window="if ($event.detail.id === 'createUser') open = true"
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
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6 border border-slate-100">
                
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-lg font-bold text-slate-900">Create New User</h3>
                    <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-500"><i class="fas fa-times"></i></button>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Full Name *</label>
                            <input type="text" name="full_name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                            <input type="email" name="email" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Username *</label>
                            <input type="text" name="username" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Password *</label>
                            <input type="password" name="password" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Role *</label>
                            <select name="role" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all bg-white">
                                <option value="user">User</option>
                                <?php if(is_super_admin()): ?>
                                <option value="division_head">Division Head</option>
                                <option value="super_admin">Super Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Designation</label>
                            <select name="designation_id" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all bg-white">
                                <option value="">-- No Designation --</option>
                                <?php foreach($designations as $desig): ?>
                                    <option value="<?= $desig['id'] ?>"><?= htmlspecialchars($desig['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if(is_super_admin()): ?>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Division</label>
                            <select name="division_id" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all bg-white">
                                <option value="">-- No Division --</option>
                                <?php foreach($divisions as $div): ?>
                                    <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="sm:col-span-2 pt-2">
                            <label class="flex items-center gap-3 cursor-pointer group w-max">
                                <div class="relative flex items-center">
                                    <input type="checkbox" name="is_active" value="1" checked class="peer sr-only">
                                    <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-brand-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-500"></div>
                                </div>
                                <span class="text-sm font-medium text-slate-700 group-hover:text-slate-900 transition-colors">Active Account</span>
                            </label>
                            <p class="text-xs text-slate-500 mt-1 ml-13">Active users appear in KPI rankings. Inactive users do not, but retain their historical tasks.</p>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3 justify-end pt-4 border-t border-slate-100">
                        <button type="button" @click="open = false" class="px-4 py-2 text-slate-700 bg-slate-100 hover:bg-slate-200 font-medium rounded-xl transition-colors">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-white bg-brand-600 hover:bg-brand-700 font-medium rounded-xl transition-colors shadow-sm">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Edit User -->
<div x-data="{ open: false, user: {} }" 
     x-on:open-modal.window="if ($event.detail.id === 'editUser') { open = true; user = $event.detail.user; }"
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
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full sm:p-6 border border-slate-100">
                
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-lg font-bold text-slate-900">Edit User</h3>
                    <button type="button" @click="open = false" class="text-slate-400 hover:text-slate-500"><i class="fas fa-times"></i></button>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="edit_id" x-bind:value="user.id">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Full Name *</label>
                            <input type="text" name="full_name" x-bind:value="user.full_name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                            <input type="email" name="email" x-bind:value="user.email" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Username *</label>
                            <input type="text" name="username" x-bind:value="user.username" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Password (Leave blank to keep)</label>
                            <input type="password" name="password" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Role *</label>
                            <select name="role" x-bind:value="user.role" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all bg-white">
                                <option value="user">User</option>
                                <?php if(is_super_admin()): ?>
                                <option value="division_head">Division Head</option>
                                <option value="super_admin">Super Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Designation</label>
                            <select name="designation_id" x-bind:value="user.designation_id" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all bg-white">
                                <option value="">-- No Designation --</option>
                                <?php foreach($designations as $desig): ?>
                                    <option value="<?= $desig['id'] ?>"><?= htmlspecialchars($desig['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if(is_super_admin()): ?>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Division</label>
                            <select name="division_id" x-bind:value="user.division_id" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all bg-white">
                                <option value="">-- No Division --</option>
                                <?php foreach($divisions as $div): ?>
                                    <option value="<?= $div['id'] ?>"><?= htmlspecialchars($div['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="sm:col-span-2 pt-2">
                            <label class="flex items-center gap-3 cursor-pointer group w-max">
                                <div class="relative flex items-center">
                                    <input type="checkbox" name="is_active" value="1" x-model="user.is_active" class="peer sr-only">
                                    <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-brand-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-500"></div>
                                </div>
                                <span class="text-sm font-medium text-slate-700 group-hover:text-slate-900 transition-colors">Active Account</span>
                            </label>
                            <p class="text-xs text-slate-500 mt-1 ml-13">Active users appear in KPI rankings. Inactive users do not, but retain their historical tasks.</p>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3 justify-end pt-4 border-t border-slate-100">
                        <button type="button" @click="open = false" class="px-4 py-2 text-slate-700 bg-slate-100 hover:bg-slate-200 font-medium rounded-xl transition-colors">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-white bg-brand-600 hover:bg-brand-700 font-medium rounded-xl transition-colors shadow-sm">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

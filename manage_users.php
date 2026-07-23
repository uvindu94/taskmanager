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
            $designation_id = !empty($_POST['designation_id']) ? (int)$_POST['designation_id'] : null;
            
            // Division head can only assign to their own division
            $division_id = is_super_admin() ? (!empty($_POST['division_id']) ? (int)$_POST['division_id'] : null) : $current_division_id;

            if ($username && $password && $full_name && $role) {
                try {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = "Username already exists.";
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role, email, division_id, designation_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $hash, $full_name, $role, $email, $division_id, $designation_id]);
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
            $password = $_POST['password']; // optional
            $designation_id = !empty($_POST['designation_id']) ? (int)$_POST['designation_id'] : null;
            
            // Validate edit permission
            // Division head can only edit users in their division
            $can_edit = false;
            if (is_super_admin()) {
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
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, password = ?, full_name = ?, role = ?, email = ?, division_id = ?, designation_id = ? WHERE id = ?");
                            $stmt->execute([$username, $hash, $full_name, $role, $email, $division_id, $designation_id, $edit_id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, role = ?, email = ?, division_id = ?, designation_id = ? WHERE id = ?");
                            $stmt->execute([$username, $full_name, $role, $email, $division_id, $designation_id, $edit_id]);
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

// Fetch Users
if (is_super_admin()) {
    $stmt = $pdo->query("SELECT u.*, d.name as division_name, ds.name as designation_name FROM users u LEFT JOIN divisions d ON u.division_id = d.id LEFT JOIN designations ds ON u.designation_id = ds.id ORDER BY u.created_at DESC");
} else {
    $stmt = $pdo->prepare("SELECT u.*, d.name as division_name, ds.name as designation_name FROM users u LEFT JOIN divisions d ON u.division_id = d.id LEFT JOIN designations ds ON u.designation_id = ds.id WHERE u.division_id = ? ORDER BY u.created_at DESC");
    $stmt->execute([$current_division_id]);
}
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Divisions and Designations for dropdowns
$divisions = [];
if (is_super_admin()) {
    $stmt = $pdo->query("SELECT id, name FROM divisions ORDER BY name");
    $divisions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$stmt = $pdo->query("SELECT id, name FROM designations ORDER BY name");
$designations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Manage Users</h1>
            <p class="text-slate-500 text-sm mt-1">
                <?= is_super_admin() ? "Manage all company users." : "Manage users in your division." ?>
            </p>
        </div>
        <button @click="$dispatch('open-modal', {id: 'createUser'})" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-medium rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-2">
            <i class="fas fa-user-plus"></i> New User
        </button>
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
                        <td colspan="5" class="px-6 py-8 text-center text-slate-500">
                            No users found.
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

<?php
require_once 'header.php';

if (!is_super_admin()) {
    echo "<div class='p-4 bg-red-100 text-red-700 rounded-lg'>Access denied. Super Admin only.</div>";
    require_once 'footer.php';
    exit;
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $name = trim($_POST['name']);
        $head_id = !empty($_POST['division_head_id']) ? (int)$_POST['division_head_id'] : null;

        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO divisions (name, division_head_id) VALUES (?, ?)");
                $stmt->execute([$name, $head_id]);
                $message = "Division created successfully.";
            } catch (PDOException $e) {
                $error = "Error creating division: " . $e->getMessage();
            }
        } else {
            $error = "Division name is required.";
        }
    }
}

// Fetch existing divisions and their heads
$stmt = $pdo->query("SELECT d.*, u.full_name as head_name FROM divisions d LEFT JOIN users u ON d.division_head_id = u.id ORDER BY d.name");
$divisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch users eligible to be division heads
$stmt = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('division_head', 'Manager', 'Assistant Manager', 'super_admin') ORDER BY full_name");
$eligibleHeads = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Manage Divisions</h1>
            <p class="text-slate-500 text-sm mt-1">Create and oversee company divisions and assign division heads.</p>
        </div>
        <button @click="$dispatch('open-modal', 'createDivision')" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-medium rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-2">
            <i class="fas fa-plus"></i> New Division
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

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-4">ID</th>
                        <th class="px-6 py-4">Division Name</th>
                        <th class="px-6 py-4">Division Head</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    <?php if (count($divisions) === 0): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-slate-500">
                            <div class="flex flex-col items-center justify-center space-y-3">
                                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center">
                                    <i class="fas fa-building text-2xl text-slate-300"></i>
                                </div>
                                <p>No divisions found. Create one to get started.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php foreach ($divisions as $div): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 font-mono text-slate-500">#<?= $div['id'] ?></td>
                        <td class="px-6 py-4 font-medium text-slate-900"><?= htmlspecialchars($div['name']) ?></td>
                        <td class="px-6 py-4">
                            <?php if ($div['head_name']): ?>
                                <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-100 font-medium">
                                    <i class="fas fa-user-tie text-indigo-400"></i>
                                    <?= htmlspecialchars($div['head_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-slate-400 italic">Not Assigned</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button class="text-brand-600 hover:text-brand-800 p-2 rounded-lg hover:bg-brand-50 transition-colors" title="Edit">
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

<!-- Modal for Create Division -->
<div x-data="{ open: false }" 
     x-on:open-modal.window="if ($event.detail === 'createDivision') open = true"
     x-on:keydown.escape.window="open = false"
     x-cloak>
    
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            
            <div x-show="open" 
                 x-transition.opacity 
                 class="fixed inset-0 transition-opacity bg-slate-900/40 backdrop-blur-sm" 
                 @click="open = false"></div>
                 
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div x-show="open" 
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6 border border-slate-100">
                
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-lg font-bold text-slate-900">Create New Division</h3>
                    <button @click="open = false" class="text-slate-400 hover:text-slate-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Division Name</label>
                            <input type="text" name="name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-all">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Division Head (Optional)</label>
                            <select name="division_head_id" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none transition-all bg-white">
                                <option value="">-- Select Division Head --</option>
                                <?php foreach($eligibleHeads as $head): ?>
                                    <option value="<?= $head['id'] ?>"><?= htmlspecialchars($head['full_name']) ?> (<?= $head['role'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mt-1 text-xs text-slate-500">You can also assign a head later.</p>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3 justify-end">
                        <button type="button" @click="open = false" class="px-4 py-2 text-slate-700 bg-slate-100 hover:bg-slate-200 font-medium rounded-xl transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-white bg-brand-600 hover:bg-brand-700 font-medium rounded-xl transition-colors shadow-sm">
                            Create Division
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

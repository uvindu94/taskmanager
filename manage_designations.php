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
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name']);
        if ($name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO designations (name) VALUES (?)");
                $stmt->execute([$name]);
                $message = "Designation created successfully.";
            } catch (PDOException $e) {
                $error = "Error creating designation: " . $e->getMessage();
            }
        } else {
            $error = "Designation name is required.";
        }
    } elseif ($action === 'edit') {
        $edit_id = (int)$_POST['edit_id'];
        $name = trim($_POST['name']);
        
        if ($name && $edit_id) {
            try {
                $stmt = $pdo->prepare("UPDATE designations SET name = ? WHERE id = ?");
                $stmt->execute([$name, $edit_id]);
                $message = "Designation updated successfully.";
            } catch (PDOException $e) {
                $error = "Error updating designation: " . $e->getMessage();
            }
        } else {
            $error = "Designation name is required.";
        }
    } elseif ($action === 'delete') {
        $delete_id = (int)$_POST['delete_id'];
        if ($delete_id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM designations WHERE id = ?");
                $stmt->execute([$delete_id]);
                $message = "Designation deleted successfully.";
            } catch (PDOException $e) {
                $error = "Error deleting designation (it may be in use): " . $e->getMessage();
            }
        }
    }
}

// Fetch existing designations
$stmt = $pdo->query("SELECT * FROM designations ORDER BY name");
$designations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Manage Designations</h1>
            <p class="text-slate-500 text-sm mt-1">Create and oversee company designations (job titles).</p>
        </div>
        <button @click="$dispatch('open-modal', {id: 'createDesignation'})" class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white font-medium rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-2">
            <i class="fas fa-plus"></i> New Designation
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
                        <th class="px-6 py-4">ID</th>
                        <th class="px-6 py-4">Designation Name</th>
                        <th class="px-6 py-4">Added On</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    <?php if (count($designations) === 0): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-slate-500">
                            <div class="flex flex-col items-center justify-center space-y-3">
                                <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center">
                                    <i class="fas fa-id-badge text-2xl text-slate-300"></i>
                                </div>
                                <p>No designations found. Create one to get started.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php foreach ($designations as $desig): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors group">
                        <td class="px-6 py-4 font-mono text-slate-500">#<?= $desig['id'] ?></td>
                        <td class="px-6 py-4 font-medium text-slate-900"><?= htmlspecialchars($desig['name']) ?></td>
                        <td class="px-6 py-4 text-slate-500"><?= date('M j, Y', strtotime($desig['created_at'])) ?></td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="$dispatch('open-modal', {
                                            id: 'editDesignation', 
                                            designation: {
                                                id: <?= $desig['id'] ?>, 
                                                name: '<?= addslashes($desig['name']) ?>'
                                            }
                                        })" 
                                        class="text-brand-600 hover:text-brand-800 p-2 rounded-lg hover:bg-brand-50 transition-colors" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this designation?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="delete_id" value="<?= $desig['id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-800 p-2 rounded-lg hover:bg-red-50 transition-colors" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for Create Designation -->
<div x-data="{ open: false }" 
     x-on:open-modal.window="if ($event.detail.id === 'createDesignation') open = true"
     x-on:keydown.escape.window="open = false"
     x-cloak>
    
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" x-transition.opacity class="fixed inset-0 transition-opacity bg-slate-900/40 backdrop-blur-sm" @click="open = false"></div>
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
                    <h3 class="text-lg font-bold text-slate-900">Create New Designation</h3>
                    <button @click="open = false" class="text-slate-400 hover:text-slate-500"><i class="fas fa-times"></i></button>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Designation Name *</label>
                            <input type="text" name="name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all" placeholder="e.g. Software Engineer">
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3 justify-end">
                        <button type="button" @click="open = false" class="px-4 py-2 text-slate-700 bg-slate-100 hover:bg-slate-200 font-medium rounded-xl transition-colors">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-white bg-brand-600 hover:bg-brand-700 font-medium rounded-xl transition-colors shadow-sm">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Edit Designation -->
<div x-data="{ open: false, desig: {} }" 
     x-on:open-modal.window="if ($event.detail.id === 'editDesignation') { open = true; desig = $event.detail.designation; }"
     x-on:keydown.escape.window="open = false"
     x-cloak>
    
    <div x-show="open" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" x-transition.opacity class="fixed inset-0 transition-opacity bg-slate-900/40 backdrop-blur-sm" @click="open = false"></div>
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
                    <h3 class="text-lg font-bold text-slate-900">Edit Designation</h3>
                    <button @click="open = false" class="text-slate-400 hover:text-slate-500"><i class="fas fa-times"></i></button>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="edit_id" x-bind:value="desig.id">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Designation Name *</label>
                            <input type="text" name="name" x-bind:value="desig.name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all">
                        </div>
                    </div>
                    
                    <div class="mt-6 flex gap-3 justify-end">
                        <button type="button" @click="open = false" class="px-4 py-2 text-slate-700 bg-slate-100 hover:bg-slate-200 font-medium rounded-xl transition-colors">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-white bg-brand-600 hover:bg-brand-700 font-medium rounded-xl transition-colors shadow-sm">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>

<?php
require_once 'header.php';

if (!is_super_admin()) {
    header('Location: dashboard.php');
    exit;
}

$success = '';
$error = '';

// Handle Feature Toggle Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_feature') {
    $division_id = (int)($_POST['division_id'] ?? 0);
    $feature_key = $_POST['feature_key'] ?? '';
    $enable = isset($_POST['enable']) ? true : false;
    $access_level = $_POST['access_level'] ?? 'all';

    if ($division_id > 0 && $feature_key) {
        if ($enable) {
            $stmt = $pdo->prepare("INSERT INTO division_features (division_id, feature_key, access_level) VALUES (?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE access_level = ?");
            $stmt->execute([$division_id, $feature_key, $access_level, $access_level]);
            $success = "Feature settings updated successfully.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM division_features WHERE division_id = ? AND feature_key = ?");
            $stmt->execute([$division_id, $feature_key]);
            $success = "Feature disabled successfully.";
        }
    }
}

// Available Features Definition
$available_features = [
    'budget_calculator' => [
        'name' => 'Budget Calculator',
        'icon' => 'fa-calculator',
        'description' => 'Allows users to calculate project quotations and development costs.'
    ],
    'finance_info' => [
        'name' => 'Finance Info',
        'icon' => 'fa-file-invoice-dollar',
        'description' => 'Manage project financial statements, proposals, and track pipeline value.'
    ]
];

// Fetch Divisions and their enabled features
$stmt = $pdo->query("SELECT id, name FROM divisions ORDER BY name");
$divisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT * FROM division_features");
$enabled_features_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Reorganize into a nested array: $division_features[division_id][feature_key] = access_level
$division_features = [];
foreach ($enabled_features_raw as $ef) {
    $division_features[$ef['division_id']][$ef['feature_key']] = $ef['access_level'];
}
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Feature Toggles</h1>
            <p class="text-slate-500 text-sm mt-1">Manage which divisions have access to specific modules and tools.</p>
        </div>
    </div>
    
    <?php if ($success): ?>
        <div x-data="{ show: true }" x-show="show" class="bg-green-50 text-green-700 p-4 rounded-xl text-sm font-medium border border-green-100 flex justify-between items-center shadow-sm">
            <div class="flex items-center gap-3">
                <i class="fas fa-check-circle text-green-500 text-lg"></i>
                <?= htmlspecialchars($success) ?>
            </div>
            <button @click="show = false" class="text-green-500 hover:text-green-700"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($available_features as $f_key => $f_details): ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex flex-col items-center text-center">
                    <div class="w-14 h-14 bg-brand-100 text-brand-600 rounded-2xl flex items-center justify-center text-2xl mb-4 shadow-sm border border-brand-200">
                        <i class="fas <?= $f_details['icon'] ?>"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-1"><?= htmlspecialchars($f_details['name']) ?></h3>
                    <p class="text-sm text-slate-500"><?= htmlspecialchars($f_details['description']) ?></p>
                </div>
                
                <div class="p-0 flex-1 bg-white">
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($divisions as $div): 
                            $is_enabled = isset($division_features[$div['id']][$f_key]);
                            $current_access = $is_enabled ? $division_features[$div['id']][$f_key] : 'all';
                        ?>
                            <li class="p-4 hover:bg-slate-50 transition-colors" x-data="{ enabled: <?= $is_enabled ? 'true' : 'false' ?> }">
                                <form method="POST" class="flex flex-col gap-3">
                                    <input type="hidden" name="action" value="toggle_feature">
                                    <input type="hidden" name="division_id" value="<?= $div['id'] ?>">
                                    <input type="hidden" name="feature_key" value="<?= $f_key ?>">
                                    
                                    <div class="flex items-center justify-between">
                                        <div class="font-medium text-slate-700 text-sm flex items-center gap-2">
                                            <i class="fas fa-building text-slate-400"></i>
                                            <?= htmlspecialchars($div['name']) ?>
                                        </div>
                                        
                                        <!-- Toggle Switch -->
                                        <label class="relative inline-flex items-center cursor-pointer">
                                          <input type="checkbox" name="enable" value="1" class="sr-only peer" x-model="enabled" @change="$el.form.submit()">
                                          <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand-500"></div>
                                        </label>
                                    </div>
                                    
                                    <!-- Access Level Settings (Shows only if enabled) -->
                                    <div x-show="enabled" x-collapse x-cloak class="mt-2 pl-6 border-l-2 border-brand-100">
                                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Access Level</label>
                                        <select name="access_level" @change="$el.form.submit()" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-sm text-slate-700 focus:ring-2 focus:ring-brand-500 outline-none transition-colors shadow-sm cursor-pointer">
                                            <option value="all" <?= $current_access === 'all' ? 'selected' : '' ?>>All Users in Division</option>
                                            <option value="division_heads_only" <?= $current_access === 'division_heads_only' ? 'selected' : '' ?>>Division Heads Only</option>
                                        </select>
                                    </div>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>

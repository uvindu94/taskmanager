<?php
require_once 'header.php';

// Authorization Check
if (!is_super_admin()) {
    $current_div_id = get_user_division();
    $stmt = $pdo->prepare("SELECT access_level FROM division_features WHERE division_id = ? AND feature_key = 'finance_info'");
    $stmt->execute([$current_div_id]);
    $feature = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$feature) {
        // Division does not have access
        header("Location: dashboard.php");
        exit;
    }

    if ($feature['access_level'] === 'division_heads_only' && !is_division_head()) {
        // User is not a division head
        header("Location: dashboard.php");
        exit;
    }
}

// Handle Filters
$period_start = $_GET['start'] ?? date('Y-m-01'); // Default to 1st of current month
$period_end = $_GET['end'] ?? date('Y-m-t');      // Default to last day of current month
$filter_status = $_GET['status'] ?? '';
$filter_product = $_GET['product'] ?? '';
$filter_type = $_GET['product_type'] ?? '';

// Build Query
$where = "sending_date BETWEEN ? AND ?";
$params = [$period_start, $period_end];

if ($filter_status) {
    $where .= " AND status = ?";
    $params[] = $filter_status;
}
if ($filter_product) {
    $where .= " AND product = ?";
    $params[] = $filter_product;
}
if ($filter_type) {
    $where .= " AND product_type = ?";
    $params[] = $filter_type;
}

// Fetch Records
try {
    $stmt = $pdo->prepare("SELECT * FROM project_financial_statements WHERE $where ORDER BY sending_date DESC");
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Totals for the current view
    $total_amount = array_sum(array_column($records, 'amount'));
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!-- DataTables CSS for Export Buttons (We strip standard DT styling later and apply Tailwind manually) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

<style>
    /* Tailwind Override for DataTables */
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.5rem 1rem;
        margin-left: 0.5rem;
        outline: none;
    }
    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
    }
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        padding: 0.25rem 2rem 0.25rem 0.5rem;
        outline: none;
    }
    table.dataTable thead th {
        border-bottom: 2px solid #e2e8f0;
        color: #475569;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding: 1rem;
        background-color: #f8fafc;
    }
    table.dataTable tbody td {
        border-bottom: 1px solid #f1f5f9;
        padding: 1rem;
        vertical-align: middle;
        font-size: 0.875rem;
    }
    table.dataTable.no-footer {
        border-bottom: none;
    }
    .dt-buttons .dt-button {
        background: #059669 !important;
        color: white !important;
        border: none !important;
        border-radius: 0.5rem !important;
        padding: 0.5rem 1rem !important;
        font-size: 0.875rem !important;
        font-weight: 500 !important;
        transition: all 0.2s !important;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
    }
    .dt-buttons .dt-button:hover {
        background: #047857 !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
    }
</style>

<div class="max-w-7xl mx-auto space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center text-xl shadow-sm border border-emerald-200">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Project Financial Statements</h1>
                <p class="text-slate-500 text-sm mt-1">Track revenue, proposals, and pipeline history.</p>
            </div>
        </div>
        
        <?php if (is_super_admin() || is_division_head()): ?>
            <a href="add_financial_record.php" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-xl shadow-sm hover:shadow-md transition-all flex items-center gap-2 shrink-0">
                <i class="fas fa-plus"></i> New Record
            </a>
        <?php endif; ?>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex items-center gap-5 relative overflow-hidden group">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-emerald-50 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out z-0"></div>
            <div class="w-14 h-14 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-2xl relative z-10 shadow-sm border border-emerald-200">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="relative z-10">
                <p class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-1">Total Pipeline Value</p>
                <h3 class="text-3xl font-black text-slate-800">LKR <?= number_format($total_amount, 2) ?></h3>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm flex items-center gap-5 relative overflow-hidden group">
            <div class="absolute -right-6 -top-6 w-24 h-24 bg-blue-50 rounded-full group-hover:scale-150 transition-transform duration-500 ease-out z-0"></div>
            <div class="w-14 h-14 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-2xl relative z-10 shadow-sm border border-blue-200">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="relative z-10">
                <p class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-1">Records Found</p>
                <h3 class="text-3xl font-black text-slate-800"><?= count($records) ?></h3>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
        <div class="flex items-center gap-2 mb-4 border-b border-slate-100 pb-3">
            <i class="fas fa-filter text-slate-400"></i>
            <h3 class="font-semibold text-slate-700">Filter Records</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase">Start Date</label>
                <input type="date" name="start" value="<?= htmlspecialchars($period_start) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none transition-colors">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase">End Date</label>
                <input type="date" name="end" value="<?= htmlspecialchars($period_end) ?>" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none transition-colors">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase">Status</label>
                <select name="status" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none transition-colors">
                    <option value="">All Statuses</option>
                    <?php 
                    $statuses = ['Proposal Send', 'Pending', 'Quotation Send', 'Invoice Send', 'Onboard', 'AMC Send', 'Other', 'Tender Send'];
                    foreach($statuses as $s) {
                        $selected = ($filter_status == $s) ? 'selected' : '';
                        echo "<option value='$s' $selected>$s</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase">Product</label>
                <select name="product" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none transition-colors">
                    <option value="">All Products</option>
                    <?php 
                    $products = ['Web', 'SSL', 'Hosting', 'Cpanel', 'Fleet Management', 'Other'];
                    foreach($products as $p) {
                        $selected = ($filter_product == $p) ? 'selected' : '';
                        echo "<option value='$p' $selected>$p</option>";
                    }
                    ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase">Product Type</label>
                <div class="flex gap-2">
                    <select name="product_type" class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 outline-none transition-colors">
                        <option value="">All Types</option>
                        <?php 
                        $types = ['Ecommerce', 'portfolio', 'CRM', 'HRM', 'LMS', 'Multi Domain', 'Single Domain', 'Wild card', 'Tender', 'Other'];
                        foreach($types as $t) {
                            $selected = ($filter_type == $t) ? 'selected' : '';
                            echo "<option value='$t' $selected>$t</option>";
                        }
                        ?>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-medium rounded-lg shadow-sm transition-colors shrink-0">
                        Filter
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Table Section -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table id="financialTable" class="w-full text-left" style="width:100%">
                <thead>
                    <tr>
                        <th>Ref Code</th>
                        <th>Project Name</th>
                        <th>Category</th>
                        <th>Product / Type</th>
                        <th>Sales Person</th>
                        <th>Status</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <!-- Hidden cols for export -->
                        <th class="hidden">Proposal Note</th>
                        <th class="hidden">General Note</th>
                        <th class="hidden">Added By</th>
                        <th class="hidden">Created At</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $row): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="font-mono text-xs text-slate-500"><?= htmlspecialchars($row['reference_code']) ?></td>
                            <td class="font-bold text-slate-800"><?= htmlspecialchars($row['project_name']) ?></td>
                            <td>
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-md text-xs font-medium border border-slate-200 capitalize">
                                    <?= htmlspecialchars($row['sales_category']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($row['product']) ?></div>
                                <div class="text-xs text-slate-500"><?= htmlspecialchars($row['product_type']) ?></div>
                            </td>
                            <td class="text-slate-600"><?= htmlspecialchars($row['sales_person_email']) ?></td>
                            <td>
                                <?php
                                $status_color = 'bg-slate-100 text-slate-600 border-slate-200';
                                switch ($row['status']) {
                                    case 'Proposal Send': $status_color = 'bg-blue-50 text-blue-600 border-blue-200'; break;
                                    case 'Quotation Send': $status_color = 'bg-indigo-50 text-indigo-600 border-indigo-200'; break;
                                    case 'Invoice Send': $status_color = 'bg-purple-50 text-purple-600 border-purple-200'; break;
                                    case 'Onboard': $status_color = 'bg-emerald-50 text-emerald-600 border-emerald-200'; break;
                                    case 'Pending': $status_color = 'bg-amber-50 text-amber-600 border-amber-200'; break;
                                }
                                ?>
                                <span class="px-2.5 py-1 <?= $status_color ?> rounded-md text-[11px] font-bold border tracking-wide uppercase whitespace-nowrap">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>
                            <td class="font-bold text-slate-800 whitespace-nowrap">
                                LKR <?= number_format($row['amount'], 2) ?>
                            </td>
                            <td class="text-slate-500 whitespace-nowrap text-sm">
                                <?= date('M j, Y', strtotime($row['sending_date'])) ?>
                            </td>
                            
                            <!-- Hidden cols for export -->
                            <td class="hidden"><?= htmlspecialchars($row['proposal_note']) ?></td>
                            <td class="hidden"><?= htmlspecialchars($row['general_note']) ?></td>
                            <td class="hidden"><?= htmlspecialchars($row['added_by']) ?></td>
                            <td class="hidden"><?= htmlspecialchars($row['created_at']) ?></td>
                            
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="view_financial.php?id=<?= $row['id'] ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-brand-600 hover:bg-brand-50 transition-colors" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (is_super_admin() || is_division_head()): ?>
                                    <a href="edit_financial.php?id=<?= $row['id'] ?>" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors" title="Edit Record">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- DataTables Scripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
    $(document).ready(function() {
        $('#financialTable').DataTable({
            "order": [[7, "desc"]], // Sort by date (column index 7) by default
            "pageLength": 25,
            "dom": '<"flex flex-col md:flex-row justify-between items-center mb-4"<"dt-buttons"B><"dt-search"f>>rt<"flex flex-col md:flex-row justify-between items-center mt-4"lip>',
            "buttons": [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel mr-2"></i> Export to Excel',
                    className: 'btn-export-excel',
                    title: 'Financial_Export_' + new Date().toISOString().slice(0, 10),
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11] // Export all data columns except Actions
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print mr-2"></i> Print Report',
                    exportOptions: {
                        columns: [0, 1, 5, 6, 7] // Print limited columns
                    }
                }
            ],
            "language": {
                "search": "",
                "searchPlaceholder": "Search records...",
                "lengthMenu": "Show _MENU_"
            }
        });
    });
</script>

<?php require_once 'footer.php'; ?>
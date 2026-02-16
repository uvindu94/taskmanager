<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Permissions Check
$role = $_SESSION['role'];
$canManageFinancials = in_array('manage_financials', $rolePermissions[$role] ?? []);

// Handle Filters
// Handle Filters
$period_start = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
$period_end = $_GET['end'] ?? date('Y-m-d');
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
$records = $stmt->fetchAll();

    // Calculate Totals for the current view
    $total_amount = array_sum(array_column($records, 'amount'));
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Statements - Task Tracker Pro</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/css/jquery.dataTables.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.10.21/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

    <style>
        .financial-stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .fin-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .fin-card h4 {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .fin-card .value {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
        }

        .amount-cell {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            text-align: right;
        }

        .ref-code {
            font-size: 11px;
            color: #6b7280;
            font-weight: bold;
        }

        .btn-export-excel {
            background-color: #059669 !important;
            /* Professional Green */
            color: white !important;
            border: none !important;
            border-radius: 6px !important;
            padding: 8px 16px !important;
            font-size: 14px !important;
            margin-bottom: 15px !important;
            transition: background 0.2s;
        }

        .btn-export-excel:hover {
            background-color: #047857 !important;
        }

        .dt-buttons {
            margin-bottom: 15px;
        }

        .d-none {
            display: none;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1>Project Financial Statements</h1>
                    <p class="header-subtitle">Tracking revenue and proposal history</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Tasks</a>
                    <?php
                    // Define which roles are allowed to see the button
                    $allowed_roles = ['Business Analyst', 'Team Lead', 'Assistant Manager', 'Manager'];

                    if (isset($_SESSION['role']) && in_array($_SESSION['role'], $allowed_roles)):
                    ?>
                        <a href="add_financial_record.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Record
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="financial-stat-grid">
            <div class="fin-card">
                <h4>Total Pipeline Value</h4>
                <div class="value">Rs <?php echo number_format($total_amount, 2); ?></div>
            </div>
            <div class="fin-card">
                <h4>Record Count</h4>
                <div class="value"><?php echo count($records); ?></div>
            </div>
        </div>

        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="filter-group">
                    <label>Start Date</label>
                    <input type="date" name="start" value="<?php echo $period_start; ?>" class="filter-input">
                </div>
                <div class="filter-group">
                    <label>End Date</label>
                    <input type="date" name="end" value="<?php echo $period_end; ?>" class="filter-input">
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" class="filter-input">
                        <option value="">All Statuses</option>
                        <option value="Proposal Send" <?php echo ($filter_status == 'Proposal Send') ? 'selected' : ''; ?>>Proposal Send</option>
                        <option value="Pending" <?php echo ($filter_status == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="Quotation Send" <?php echo ($filter_status == 'Quotation Send') ? 'selected' : ''; ?>>Quotation Send</option>
                        <option value="Invoice Send" <?php echo ($filter_status == 'Invoice Send') ? 'selected' : ''; ?>>Invoice Send</option>
                        <option value="Onboard" <?php echo ($filter_status == 'Onboard') ? 'selected' : ''; ?>>Onboard</option>
                    </select>
                </div>

                <div class="filter-group">
            <label>Product</label>
            <select name="product" class="filter-input">
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

        <div class="filter-group">
            <label>Product Type</label>
            <select name="product_type" class="filter-input">
                <option value="">All Types</option>
                <?php 
                $types = ['Ecommerce', 'portfolio', 'CRM', 'HRM', 'LMS', 'Multi Domain', 'Single Domain', 'Wild card', 'Tender', 'Other'];
                foreach($types as $t) {
                    $selected = ($filter_type == $t) ? 'selected' : '';
                    echo "<option value='$t' $selected>$t</option>";
                }
                ?>
            </select>
        </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary" style="margin-top: 24px;background-color: cornflowerblue;">Filter</button>
                </div>
            </form>
        </div>

        <div class="tasks-section">
            <div class="table-container">
                <table id="financialTable" class="display">
                    <thead>
                        <tr>
                            <th>Ref Code</th>
                            <th>Project Name</th>
                            <th>Sales Category</th>
                            <th>Product</th>
                            <th>Product Type</th>
                            <th>Sales Person</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Sending Date</th>
                            <th class="d-none">Proposal Note</th>
                            <th class="d-none">General Note</th>
                            <th class="d-none">Added By</th>
                            <th class="d-none">Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['reference_code']); ?></td>
                                <td><strong><?php echo htmlspecialchars($row['project_name']); ?></strong></td>
                                <td><?php echo ucfirst($row['sales_category']); ?></td>
                                <td><?php echo $row['product']; ?></td>
                                <td><?php echo $row['product_type']; ?></td>
                                <td><?php echo htmlspecialchars($row['sales_person_email']); ?></td>
                                <td><?php echo $row['status']; ?></td>
                                <td><?php echo $row['amount']; ?></td>
                                <td><?php echo $row['sending_date']; ?></td>

                                <td class="d-none"><?php echo htmlspecialchars($row['proposal_note']); ?></td>
                                <td class="d-none"><?php echo htmlspecialchars($row['general_note']); ?></td>
                                <td class="d-none"><?php echo htmlspecialchars($row['added_by']); ?></td>
                                <td class="d-none"><?php echo $row['created_at']; ?></td>

                                <td>
                                    <div class="task-actions">
                                        <?php
                                        // Define allowed roles for editing
                                        $allowed_edit_roles = ['Business Analyst', 'Team Lead', 'Assistant Manager', 'Manager'];

                                        if (isset($_SESSION['role']) && in_array($_SESSION['role'], $allowed_edit_roles)):
                                        ?>
                                            <a href="edit_financial.php?id=<?php echo $row['id']; ?>" class="action-btn action-btn-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?> <a href="view_financial.php?id=<?php echo $row['id']; ?>" class="action-btn action-btn-primary"><i class="fas fa-eye"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#financialTable').DataTable({
                "order": [
                    [4, "desc"]
                ], // Sort by date by default
                "pageLength": 25
            });
        });
    </script>

    <script>
        $(document).ready(function() {
            // 1. Destroy any existing instance to prevent the "reinitialise" error
            if ($.fn.DataTable.isDataTable('#financialTable')) {
                $('#financialTable').DataTable().destroy();
            }

            // 2. Initialize with ALL columns (0 through 12)
            $('#financialTable').DataTable({
                "order": [
                    [8, "desc"]
                ], // Sorts by 'Sending Date' (column index 8)
                "pageLength": 25,
                "dom": 'Bfrtip',
                "buttons": [{
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel"></i> Export Full Report',
                        className: 'btn-export-excel',
                        title: 'Financial_Export_' + new Date().toISOString().slice(0, 10),
                        exportOptions: {
                            // This tells DataTables to grab columns 0 to 12 
                            // including the ones we hid with CSS
                            columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Print View',
                        exportOptions: {
                            columns: [0, 1, 6, 7, 8] // Only print the most important columns
                        }
                    }
                ],
                "language": {
                    "search": "Quick Search:",
                    "lengthMenu": "Show _MENU_ records per page"
                }
            });
        });
    </script>
</body>

</html>
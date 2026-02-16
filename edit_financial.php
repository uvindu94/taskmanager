<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: financeinfo.php');
    exit;
}

// Fetch existing record
$stmt = $pdo->prepare("SELECT * FROM project_financial_statements WHERE id = ?");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    die("Record not found.");
}

$message = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sql = "UPDATE project_financial_statements SET 
                reference_code = ?, 
                project_name = ?, 
                sales_category = ?, 
                product = ?, 
                product_type = ?, 
                sales_person_email = ?, 
                status = ?, 
                amount = ?, 
                sending_date = ?, 
                proposal_note = ?, 
                general_note = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['reference_code'],
            $_POST['project_name'],
            $_POST['sales_category'],
            $_POST['product'],
            $_POST['product_type'],
            $_POST['sales_person_email'],
            $_POST['status'],
            $_POST['amount'],
            $_POST['sending_date'],
            $_POST['proposal_note'],
            $_POST['general_note'],
            $id
        ]);
        
        $message = '<div class="alert alert-success" style="background:#d1fae5; color:#065f46; padding:15px; border-radius:8px; margin-bottom:20px;">
                        <i class="fas fa-check-circle"></i> Record updated successfully! 
                        <a href="financeinfo.php" style="font-weight:bold; color:#065f46; margin-left:10px;">View All Records</a>
                    </div>';
        
        // Refresh local record variable to show updated data in form
        $stmt = $pdo->prepare("SELECT * FROM project_financial_statements WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch();

    } catch (PDOException $e) {
        $message = '<div class="alert alert-danger" style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; margin-bottom:20px;">
                        Error updating record: ' . $e->getMessage() . '
                    </div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Record - <?php echo htmlspecialchars($record['reference_code']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <style>
        .form-container { background: #fff; padding: 30px; border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #374151; font-size: 14px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        .form-control:focus { outline: none; border-color: #3b82f6; ring: 2px #3b82f6; }
        textarea.form-control { height: 100px; resize: vertical; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1>Edit Financial Record</h1>
                    <p class="header-subtitle">Updating <?php echo htmlspecialchars($record['reference_code']); ?></p>
                </div>
                <div class="header-actions">
                    <a href="financeinfo.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                </div>
            </div>
        </div>

        <?php echo $message; ?>

        <div class="form-container">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Reference Code</label>
                        <input type="text" name="reference_code" value="<?php echo htmlspecialchars($record['reference_code']); ?>" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Project Name</label>
                        <input type="text" name="project_name" value="<?php echo htmlspecialchars($record['project_name']); ?>" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Sales Category</label>
                        <select name="sales_category" class="form-control">
                            <option value="sales" <?php if($record['sales_category'] == 'sales') echo 'selected'; ?>>Sales</option>
                            <option value="direct" <?php if($record['sales_category'] == 'direct') echo 'selected'; ?>>Direct</option>
                            <option value="other" <?php if($record['sales_category'] == 'other') echo 'selected'; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Product</label>
                        <select name="product" class="form-control" required>
                            <?php 
                            $products = ['Web', 'SSL', 'Hosting', 'Cpanel', 'Fleet Management', 'Other'];
                            foreach($products as $p) {
                                $sel = ($record['product'] == $p) ? 'selected' : '';
                                echo "<option value='$p' $sel>$p</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Product Type</label>
                        <select name="product_type" class="form-control" required>
                            <?php 
                            $types = ['Ecommerce', 'portfolio', 'CRM', 'HRM', 'LMS', 'Multi Domain', 'Single Domain', 'WIld card', 'Tender', 'Other'];
                            foreach($types as $t) {
                                $sel = ($record['product_type'] == $t) ? 'selected' : '';
                                echo "<option value='$t' $sel>$t</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control" required>
                            <?php 
                            $statuses = ['Proposal Send', 'Quotation Send', 'Invoice Send', 'Tender Send', 'Pending', 'Onboard', 'AMC Send', 'Other'];
                            foreach($statuses as $s) {
                                $sel = ($record['status'] == $s) ? 'selected' : '';
                                echo "<option value='$s' $sel>$s</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Sales Person / Email</label>
                        <input type="text" name="sales_person_email" value="<?php echo htmlspecialchars($record['sales_person_email']); ?>" class="form-control" >
                    </div>
                    <div class="form-group">
                        <label>Amount (Numeric only)</label>
                        <input type="number" step="0.01" name="amount" value="<?php echo htmlspecialchars($record['amount']); ?>" class="form-control" >
                    </div>
                    <div class="form-group">
                        <label>Sending Date</label>
                        <input type="date" name="sending_date" value="<?php echo $record['sending_date']; ?>" class="form-control" required>
                    </div>

                    <div class="form-group full-width">
                        <label>Note about Proposal / Quotation / Invoice</label>
                        <textarea name="proposal_note" class="form-control"><?php echo htmlspecialchars($record['proposal_note']); ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label>Any other Note</label>
                        <textarea name="general_note" class="form-control"><?php echo htmlspecialchars($record['general_note']); ?></textarea>
                    </div>
                </div>

                <div style="margin-top: 20px; text-align: right;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 30px;background-color: cornflowerblue;">
                        <i class="fas fa-save"></i> Update Financial Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
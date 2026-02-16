<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: project_financial_statements.php');
    exit;
}

// Fetch the specific record
try {
    $stmt = $pdo->prepare("SELECT * FROM project_financial_statements WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();

    if (!$record) {
        die("Record not found.");
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Record - <?php echo htmlspecialchars($record['reference_code']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/dashboard.css">
    <style>
        .detail-container {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .detail-header {
            padding: 24px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .detail-body {
            padding: 32px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 32px;
        }

        .info-item label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .info-item p {
            font-size: 16px;
            color: #111827;
            margin: 0;
            font-weight: 500;
        }

        .amount-highlight {
            font-size: 24px !important;
            color: #059669 !important;
            font-weight: 700 !important;
        }

        .note-box {
            grid-column: span 2;
            background: #f3f4f6;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }

        .meta-footer {
            padding: 16px 32px;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            color: #9ca3af;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1>Record Details</h1>
                    <p class="header-subtitle"><?php echo htmlspecialchars($record['reference_code']); ?></p>
                </div>
                <div class="header-actions">
                    <a href="financeinfo.php" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i> Back
                    </a>
                    <?php
                    // Define authorized roles
                    $privileged_roles = ['Business Analyst', 'Team Lead', 'Assistant Manager', 'Manager'];

                    if (isset($_SESSION['role']) && in_array($_SESSION['role'], $privileged_roles)):
                    ?>
                        <a href="edit_financial.php?id=<?php echo $record['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Record
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="detail-container">
            <div class="detail-header">
                <div>
                    <h2 style="margin:0; font-size: 20px;"><?php echo htmlspecialchars($record['project_name']); ?></h2>
                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '_', $record['status'])); ?>">
                        <?php echo $record['status']; ?>
                    </span>
                </div>
                <div style="text-align: right;">
                    <label style="font-size: 12px; color: #6b7280; display:block;">SENDING DATE</label>
                    <strong><?php echo date('D, M j, Y', strtotime($record['sending_date'])); ?></strong>
                </div>
            </div>

            <div class="detail-body">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Sales Category</label>
                        <p><?php echo ucfirst($record['sales_category']); ?></p>
                    </div>
                    <div class="info-item">
                        <label>Product & Type</label>
                        <p><?php echo $record['product']; ?> — <?php echo $record['product_type']; ?></p>
                    </div>
                    <div class="info-item">
                        <label>Sales Person</label>
                        <p><i class="fas fa-user-tie" style="margin-right:8px; color:#9ca3af;"></i><?php echo htmlspecialchars($record['sales_person_email']); ?></p>
                    </div>
                    <div class="info-item">
                        <label>Financial Amount</label>
                        <p class="amount-highlight">Rs <?php echo number_format($record['amount'], 2); ?></p>
                    </div>

                    <div class="info-item note-box">
                        <label><i class="fas fa-file-invoice" style="margin-right:5px;"></i> Proposal / Invoice Note</label>
                        <p><?php echo nl2br(htmlspecialchars($record['proposal_note'] ?: 'No notes provided.')); ?></p>
                    </div>

                    <div class="info-item note-box" style="border-left-color: #9ca3af;">
                        <label><i class="fas fa-sticky-note" style="margin-right:5px;"></i> General Notes</label>
                        <p><?php echo nl2br(htmlspecialchars($record['general_note'] ?: 'No additional notes.')); ?></p>
                    </div>
                </div>
            </div>

            <div class="meta-footer">
                <span><i class="fas fa-user-plus"></i> Added by: <?php echo htmlspecialchars($record['added_by']); ?></span>
                <span><i class="fas fa-history"></i> System ID: #<?php echo $record['id']; ?> | Created: <?php echo $record['created_at']; ?></span>
            </div>
        </div>
    </div>
</body>

</html>
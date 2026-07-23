<?php
session_start();
$_SESSION['user_id'] = 4; // Assuming superadmin or something
$_SESSION['role'] = 'user';
$_SESSION['username'] = 'superadmin';
$_GET['id'] = 9; // Assuming task 1 exists

try {
    ob_start();
    require_once 'task_details.php';
    $out = ob_get_clean();
    if (empty(trim($out))) {
        echo "Output is completely empty!\n";
    } else {
        echo "Output has " . strlen($out) . " bytes\n";
    }
} catch (Throwable $e) {
    echo "Fatal Error Caught: " . $e->getMessage() . " on line " . $e->getLine() . " in " . $e->getFile() . "\n";
}

<?php
session_start();  // Start session for login

define('DB_HOST', 'localhost');
define('DB_USER', 'root');  // Change to your MySQL user
define('DB_PASS', '');      // Change to your MySQL password
define('DB_NAME', 'taskmanager');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Role permissions (simple array for checks)
$rolePermissions = [
    'Manager' => ['create', 'assign', 'reassign', 'view_all', 'update_status'],
    'Assistant Manager' => ['create', 'assign', 'reassign', 'view_all', 'update_status'],
    'Team Lead' => ['create', 'assign', 'reassign', 'view_all', 'update_status'],
    'Senior Web Developer' => ['create', 'update_own', 'view_own'],
    'Associate Web Developer' => ['create', 'update_own', 'view_own'],
    'Intern Web Developer' => ['create', 'update_own', 'view_own'],
    'UI Developer' => ['create', 'update_own', 'view_own'],
    'Business Analyst' => ['create', 'update_own', 'view_own']
];

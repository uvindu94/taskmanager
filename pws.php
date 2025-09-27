<?php
$plainPassword = 'danushka@#1'; // Replace with the desired password
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
echo $hashedPassword; // Outputs something like $2y$10$...
?>
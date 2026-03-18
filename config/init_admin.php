<?php
// Standalone script to UPDATE the admin user's password.
// Run this script once from your browser: http://localhost/sbvs/config/init_admin.php

require_once 'database.php';

// --- Admin User Configuration ---
$admin_email = 'laweejallah@gmail.com';
$admin_password = '20064';
// --------------------------------

try {
    $database = new Database();
    $db = $database->getConnection();

    // Hash the password
    $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);

    // Prepare the SQL query to update the password
    $query = "UPDATE users SET password_hash = :password_hash WHERE email = :email";
    
    $stmt = $db->prepare($query);

    // Bind parameters
    $stmt->bindParam(':password_hash', $password_hash);
    $stmt->bindParam(':email', $admin_email);

    // Execute the query
    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            echo "Admin user's password updated successfully!<br>";
            echo "Email: " . htmlspecialchars($admin_email) . "<br>";
            echo "You can now log in with the new password.";
        } else {
            echo "No user found with that email address. Nothing updated.";
        }
    } else {
        echo "Failed to update admin user.";
    }

} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

echo "<p><b>SECURITY WARNING:</b> Delete this file (init_admin.php) immediately!</p>";
?>
<?php
require_once 'config.php';
require_once 'database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    if ($conn) {
        echo "✅ Connection SUCCESSFUL via new database architecture!\n";
    } else {
        echo "❌ Connection FAILED but handled securely.\n";
    }
} catch (Exception $e) {
    echo "❌ Unhandled Exception escaped out! " . $e->getMessage() . "\n";
}
?>

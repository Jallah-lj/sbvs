<?php
// Secure each view file: If someone tries to access /views/dashboard.php directly
if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SBVS - <?= $page_title ?? 'Dashboard'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
</head>
<body>
<div class="wrapper d-flex">
    <?php include('sidebar.php'); ?>
    <div class="main-content w-100">
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom p-3">
            <div class="container-fluid">
                <span class="navbar-text">Branch: <strong><?= $_SESSION['branch_name'] ?? 'All Branches'; ?></strong></span>
                <div class="ms-auto">
                    <span class="me-3"><?= $_SESSION['name']; ?> (<?= $_SESSION['role']; ?>)</span>
                    <a href="../controllers/LogoutController.php" class="btn btn-outline-danger btn-sm">Logout</a>
                </div>
            </div>
        </nav>
        <div class="p-4">
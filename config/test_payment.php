<?php
$_SERVER["REQUEST_METHOD"] = "POST";
session_id("test");
session_start();
$_SESSION["logged_in"] = true;
$_SESSION["role"] = "Super Admin";
$_SESSION["user_id"] = 1;
$_POST["action"] = "record";
$_POST["student_id"] = 1;
$_POST["enrollment_id"] = 1;
$_POST["amount"] = 50.00;
$_POST["payment_method"] = "Cash";

// Hack to prevent session_start() and require error in payment_api
$apiCode = file_get_contents("controllers/views/models/api/payment_api.php");
$apiCode = str_replace("session_start();", "", $apiCode);
$apiCode = str_replace("require_once '../../../../database.php';", "require_once 'database.php';", $apiCode);
$apiCode = str_replace("require_once '../../../../EmailService.php';", "require_once 'EmailService.php';", $apiCode);

file_put_contents("test_api.php", $apiCode);
require_once "test_api.php";

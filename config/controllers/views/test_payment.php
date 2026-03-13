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
require_once "models/api/payment_api.php";

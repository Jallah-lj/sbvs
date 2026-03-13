<?php
/**
 * Global Configuration File
 * Standardizes references to the local environment and global settings.
 */

// Determine baseline URL dynamically (avoids hardcoding http://localhost...)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
$basePath = '/sbvs/'; // Adjust this if the project sits inside a different subdirectory

define('BASE_URL', $protocol . $domainName . $basePath);

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'liberian_sbvs');
define('DB_USER', 'root');
define('DB_PASS', '');

// Security Constants
define('APP_ENV', 'development'); // set to 'production' on live server to hide verbose errors
?>

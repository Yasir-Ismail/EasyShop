<?php
/**
 * EasyShop — Configuration Template
 * 
 * Instructions:
 * 1. For local development, copy this file to 'config/database.local.php'
 *    and fill in your local MySQL credentials.
 * 2. On InfinityFree or live hosting, configure these values in 'config/database.php'
 *    or set them via environment variables.
 * 
 * NEVER commit 'config/database.local.php' or files with live credentials to Git.
 */

// Database Hostname (InfinityFree: e.g. sql123.epizy.com or sql200.infinityfree.com; Local: localhost)
define('DB_HOST', 'localhost');

// Database Name (InfinityFree prefix: e.g. if0_38123456_easyshop_db; Local: easyshop_db)
define('DB_NAME', 'easyshop_db');

// Database Username (InfinityFree: e.g. if0_38123456; Local: root)
define('DB_USER', 'root');

// Database Password (InfinityFree: vPanel account password; Local: empty string or root)
define('DB_PASS', '');

// Database Port (Standard MySQL port is 3306)
define('DB_PORT', 3306);

// Application Environment: 'development' (detailed logs) or 'production' (silent errors)
define('APP_ENV', 'development');

// Base URL of the store
define('APP_URL', 'http://localhost/easyshop');

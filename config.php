<?php
// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Place these lines at the very top of config.php, before session_start()
ini_set('session.cookie_lifetime', 0);
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.gc_maxlifetime', 3600);

// Then start the session
session_start();

// Rest of your config.php code follows...


// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u822965179_v');
define('DB_PASS', 'Goats@0713#');
define('DB_NAME', 'u822965179_v');

// Color scheme
define('COLOR_PRIMARY', '#E43D12');
define('COLOR_SECONDARY', '#D6536D');
define('COLOR_ACCENT', '#FFA2B6');
define('COLOR_HIGHLIGHT', '#EFB11D');
define('COLOR_BACKGROUND', '#EBE9E1');

// Time zone
date_default_timezone_set('America/New_York');

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// User roles
define('ROLE_ADMIN', 'admin');
define('ROLE_HR', 'hr');
define('ROLE_TL', 'tl');
define('ROLE_AGENT', 'agent');

// Entry categories
$ENTRY_CATEGORIES = [
    'ACA' => 'ACA',
    'DEBT' => 'Debt',
    'MEDICARE' => 'Medicare',
    'FE' => 'FE',
    'AUTO' => 'Auto',
    'SSDI' => 'SSDI'
];

// Utility functions

function formatDate($date, $format = 'm/d/Y') {
    return date($format, strtotime($date));
}

function checkPermission($permission) {
    if (!isset($_SESSION['permissions']) || !in_array($permission, $_SESSION['permissions'])) {
        return false;
    }
    return true;
}

function getUserRole() {
    return $_SESSION['role'] ?? null;
}

function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return getUserRole() === ROLE_ADMIN;
}

function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}

function getWorkingHours($check_in, $check_out) {
    if (!$check_out) return 0;
    
    $start = new DateTime($check_in);
    $end = new DateTime($check_out);
    $interval = $start->diff($end);
    
    return $interval->h + ($interval->i / 60);
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Database schema creation (if not exists)
$schema_queries = [
    // Users table
    "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        role ENUM('admin', 'hr', 'tl', 'agent') NOT NULL,
        position VARCHAR(50) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",
    
    // Permissions table
    "CREATE TABLE IF NOT EXISTS permissions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(50) UNIQUE NOT NULL,
        description TEXT
    )",
    
    // User permissions table
    "CREATE TABLE IF NOT EXISTS user_permissions (
        user_id INT,
        permission_id INT,
        granted_by INT,
        granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, permission_id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (permission_id) REFERENCES permissions(id),
        FOREIGN KEY (granted_by) REFERENCES users(id)
    )",
    
    // Attendance table
    "CREATE TABLE IF NOT EXISTS attendance (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        check_in DATETIME NOT NULL,
        check_out DATETIME,
        duration DECIMAL(10,2),
        status ENUM('present', 'absent', 'late', 'half-day') NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )",
    
    // Data entries table
    "CREATE TABLE IF NOT EXISTS data_entries (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        category ENUM('ACA', 'DEBT', 'MEDICARE', 'FE', 'AUTO', 'SSDI') NOT NULL,
        customer_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        email VARCHAR(100),
        status VARCHAR(50) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )",
    
    // Salary table
    "CREATE TABLE IF NOT EXISTS salaries (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        basic_salary DECIMAL(10,2) NOT NULL,
        entry_incentive DECIMAL(10,2) DEFAULT 0,
        other_incentive DECIMAL(10,2) DEFAULT 0,
        total_amount DECIMAL(10,2) NOT NULL,
        month INT NOT NULL,
        year INT NOT NULL,
        processed_by INT NOT NULL,
        processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (processed_by) REFERENCES users(id)
    )",
    
    // Chat messages table
    "CREATE TABLE IF NOT EXISTS chat_messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        sender_id INT NOT NULL,
        receiver_id INT,
        group_id INT,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id),
        FOREIGN KEY (receiver_id) REFERENCES users(id)
    )",
    
    // Chat groups table
    "CREATE TABLE IF NOT EXISTS chat_groups (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )",
    
    // Group members table
    "CREATE TABLE IF NOT EXISTS group_members (
        group_id INT NOT NULL,
        user_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (group_id, user_id),
        FOREIGN KEY (group_id) REFERENCES chat_groups(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )"
];

// Execute schema creation queries
foreach ($schema_queries as $query) {
    try {
        $pdo->exec($query);
    } catch (PDOException $e) {
        error_log("Schema creation error: " . $e->getMessage());
    }
}
?>
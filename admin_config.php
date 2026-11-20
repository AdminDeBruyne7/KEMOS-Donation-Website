<?php
// Database configuration
$config = [
    // Kemos donation database
    'kemos_db_host' => 'localhost',
    'kemos_db_user' => 'root', // Change this if using a different username
    'kemos_db_pass' => '', // Change this if you have a password
    'kemos_db_name' => 'kemos_donation',

    // Admin settings
    'items_per_page' => 10,
    'site_name' => 'KEMOS DONATION'
];

// Function to connect to the Kemos donation database
function connect_kemos_db() {
    global $config;
    $conn = new mysqli(
        $config['kemos_db_host'], 
        $config['kemos_db_user'], 
        $config['kemos_db_pass'], 
        $config['kemos_db_name']
    );
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Function to check if admin is logged in
function check_admin_login() {
    if (!isset($_SESSION['admin_id'])) {
        header("Location: admin_login.php");
        exit();
    }
}

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>


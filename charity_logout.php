<?php
// Start the session
session_start();

// Check if the user is logged in as a charity
if (!isset($_SESSION['charity_id'])) {
    // If not logged in, redirect to the login page immediately
    header("Location: charity_login.php");
    exit();
}

// Log the logout action (optional, for debugging or audit purposes)
error_log("Charity ID {$_SESSION['charity_id']} logged out at " . date('Y-m-d H:i:s'));

// Unset all charity-related session variables
unset($_SESSION['charity_id']);
unset($_SESSION['charity_name']);
unset($_SESSION['charity_email']);
unset($_SESSION['license_status']);
unset($_SESSION['license_expiry']);

// Destroy the entire session
session_unset(); // Unset all session variables
session_destroy(); // Destroy the session

// Redirect to the charity login page with a logout message
header("Location: charity_login.php?logout=success");
exit();
?>
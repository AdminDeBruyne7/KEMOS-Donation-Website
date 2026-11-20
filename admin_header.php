<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration
require_once 'admin_config.php';

// Check if admin is logged in
check_admin_login();

// Get current page for navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - KEMOS DONATION</title>
    <link rel="stylesheet" href="admin_style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>KEMOS ADMIN</h2>
            </div>
            
            <div class="sidebar-menu">
                <ul>
                    <li class="<?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
                        <a href="admin_dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="<?php echo ($current_page == 'admin_users.php') ? 'active' : ''; ?>">
                        <a href="admin_users.php">
                            <i class="fas fa-users"></i> Users Management
                        </a>
                    </li>
                    <li class="<?php echo ($current_page == 'admin_charities.php') ? 'active' : ''; ?>">
                        <a href="admin_charities.php">
                            <i class="fas fa-hand-holding-heart"></i> Charities Management
                        </a>
                    </li>
                    <li class="<?php echo ($current_page == 'admin_donations.php') ? 'active' : ''; ?>">
                        <a href="admin_donations.php">
                            <i class="fas fa-donate"></i> Donations
                        </a>
                    </li>
                    <li class="<?php echo ($current_page == 'admin_transactions.php') ? 'active' : ''; ?>">
                        <a href="admin_transactions.php">
                            <i class="fas fa-exchange-alt"></i> Transactions
                        </a>
                    </li>
                    <li class="<?php echo ($current_page == 'admin_settings.php') ? 'active' : ''; ?>">
                        <a href="admin_settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                    <li>
                        <a href="admin_logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation -->
            <div class="top-navbar">
                <div class="toggle-sidebar">
                    <i class="fas fa-bars"></i>
                </div>
                
                <div class="user-info">
                    <span>Welcome, <?php echo $_SESSION['admin_name']; ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['admin_name']); ?>&background=0052cc&color=fff" alt="Admin" class="user-avatar">
                </div>
            </div>
            
            <!-- Content Container -->
            <div class="content">


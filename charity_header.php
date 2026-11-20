<?php
// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if charity is logged in
if (!isset($_SESSION['charity_id']) || empty($_SESSION['charity_id'])) {
    header("Location: index.php");
    exit;
}

// Get charity information if not already available
if (!isset($charity) && isset($_SESSION['charity_id'])) {
    require_once 'config.php';
    $conn = connect_db();
    
    $charity_id = $_SESSION['charity_id'];
    $charity_query = "SELECT * FROM charities WHERE id = ?";
    $stmt = $conn->prepare($charity_query);
    $stmt->bind_param("i", $charity_id);
    $stmt->execute();
    $charity = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Close connection if we opened it here
    if (!isset($conn_kemos)) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KEMOS DONATION - Charity Dashboard</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="charity_settings.css">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Custom styles for charity dashboard -->
    <style>
        /* Navbar styles */
        .charity-navbar {
            background-color: #0052cc;
            color: white;
            padding: 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .charity-nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            max-width: 1400px;
            margin: 0 auto;
            height: 70px;
        }
        
        .charity-logo {
            font-size: 22px;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }
        
        .charity-nav-links {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .charity-nav-links li {
            margin: 0;
            padding: 0;
        }
        
        .charity-nav-links a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 25px 15px;
            font-size: 16px;
            transition: background-color 0.2s;
        }
        
        .charity-nav-links a:hover,
        .charity-nav-links a.active {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .charity-user-menu {
            position: relative;
        }
        
        .charity-user-button {
            display: flex;
            align-items: center;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 4px;
        }
        
        .charity-user-button:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .charity-user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 8px;
            font-weight: bold;
        }
        
        .charity-user-name {
            margin-right: 8px;
            max-width: 150px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .charity-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            min-width: 200px;
            display: none;
            z-index: 1000;
        }
        
        .charity-dropdown-menu.show {
            display: block;
        }
        
        .charity-dropdown-menu a {
            display: block;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .charity-dropdown-menu a:hover {
            background-color: #f8f9fa;
        }
        
        .charity-dropdown-menu .dropdown-divider {
            height: 1px;
            background-color: #e9ecef;
            margin: 8px 0;
        }
        
        .charity-dropdown-menu .logout-link {
            color: #dc3545;
        }
        
        /* Mobile menu toggle */
        .charity-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .charity-menu-toggle {
                display: block;
            }
            
            .charity-nav-links {
                position: fixed;
                top: 70px;
                left: 0;
                right: 0;
                background-color: #0052cc;
                flex-direction: column;
                height: 0;
                overflow: hidden;
                transition: height 0.3s ease;
                z-index: 999;
            }
            
            .charity-nav-links.show {
                height: auto;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }
            
            .charity-nav-links a {
                padding: 15px 20px;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
            }
        }
    </style>
</head>
<body>
    <!-- Charity Navigation Bar -->
    <nav class="charity-navbar">
        <div class="charity-nav-container">
            <a href="charity_dashboard.php" class="charity-logo">KEMOS DONATION</a>
            
            <button class="charity-menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <ul class="charity-nav-links" id="navLinks">
                <li><a href="charity_dashboard.php" <?php echo basename($_SERVER['PHP_SELF']) == 'charity_dashboard.php' ? 'class="active"' : ''; ?>>Dashboard</a></li>
                <li><a href="charity_donations.php" <?php echo basename($_SERVER['PHP_SELF']) == 'charity_donations.php' ? 'class="active"' : ''; ?>>Donations</a></li>
                <li><a href="charity_campaigns.php" <?php echo basename($_SERVER['PHP_SELF']) == 'charity_campaigns.php' ? 'class="active"' : ''; ?>>Campaigns</a></li>
                <li><a href="charity_settings.php" <?php echo basename($_SERVER['PHP_SELF']) == 'charity_settings.php' ? 'class="active"' : ''; ?>>Settings</a></li>
            </ul>
            
            <div class="charity-user-menu">
                <button class="charity-user-button" id="userMenuButton">
                    <div class="charity-user-avatar">
                        <?php echo isset($charity) ? strtoupper(substr($charity['charity_name'], 0, 1)) : 'C'; ?>
                    </div>
                    <span class="charity-user-name">
                        <?php echo isset($charity) ? htmlspecialchars($charity['charity_name']) : 'Charity'; ?>
                    </span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                
                <div class="charity-dropdown-menu" id="userDropdown">
                    <a href="charity_profile.php">
                        <i class="fas fa-user-circle mr-2"></i> View Profile
                    </a>
                    <a href="charity_settings.php">
                        <i class="fas fa-cog mr-2"></i> Settings
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content Container -->
    <div class="main-container">
        <?php if (isset($_SESSION['success_message']) && !empty($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Page content will be inserted here -->


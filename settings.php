<?php
// Start session to access session variables
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// If not logged in, redirect to login page
if (!$isLoggedIn) {
    header("Location: login.php");
    exit();
}

// Get user data from session
$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? '';
$userEmail = $_SESSION['email'] ?? '';
$userRole = $_SESSION['role'] ?? 'user';

// Connect to database
require_once 'config.php';

// Add this line right after to ensure we're using the kemos_donation database
$conn->select_db("kemos_donation");

// Initialize variables for form data and messages
$successMessage = '';
$errorMessage = '';
$userLastName = '';
$userPhone = '';
$userAddress = '';
$userCity = '';
$userCountry = '';
$userProfilePic = null;
$userCreatedAt = '';
$currentPassword = '';
$newPassword = '';
$confirmPassword = '';

// Get user data from database
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $userData = $result->fetch_assoc();
    // Update session data with fresh database data
    $userName = $userData['first_name'] ?? $userName;
    $userLastName = $userData['last_name'] ?? '';
    $userEmail = $userData['email'] ?? $userEmail;
    $userPhone = $userData['phone'] ?? '';
    $userAddress = $userData['address'] ?? '';
    $userCity = $userData['city'] ?? '';
    $userCountry = $userData['country'] ?? '';
    $userProfilePic = $userData['profile_pic'] ?? null;
    $userCreatedAt = $userData['created_at'] ?? '';
}
$stmt->close();

// Generate profile image URL
$profileImageUrl = $userProfilePic ? "uploads/profiles/" . $userProfilePic : 
                  "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=0052cc&color=fff";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Determine which form was submitted
    if (isset($_POST['update_profile'])) {
        // Profile update form
        $newFirstName = trim($_POST['first_name'] ?? '');
        $newLastName = trim($_POST['last_name'] ?? '');
        $newPhone = trim($_POST['phone'] ?? '');
        $newAddress = trim($_POST['address'] ?? '');
        $newCity = trim($_POST['city'] ?? '');
        $newCountry = trim($_POST['country'] ?? '');
        
        // Validate inputs
        if (empty($newFirstName)) {
            $errorMessage = "First name cannot be empty.";
        } else {
            // Update user profile in database
            $updateQuery = "UPDATE users SET 
                            first_name = ?, 
                            last_name = ?, 
                            phone = ?, 
                            address = ?, 
                            city = ?, 
                            country = ? 
                            WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ssssssi", $newFirstName, $newLastName, $newPhone, $newAddress, $newCity, $newCountry, $userId);
            
            if ($stmt->execute()) {
                // Update session variables
                $_SESSION['name'] = $newFirstName;
                $userName = $newFirstName;
                $userLastName = $newLastName;
                $userPhone = $newPhone;
                $userAddress = $newAddress;
                $userCity = $newCity;
                $userCountry = $newCountry;
                
                $successMessage = "Profile updated successfully!";
            } else {
                $errorMessage = "Error updating profile: " . $stmt->error;
            }
            $stmt->close();
        }
    } elseif (isset($_POST['update_password'])) {
        // Password update form
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errorMessage = "All password fields are required.";
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = "New password and confirmation do not match.";
        } elseif (strlen($newPassword) < 8) {
            $errorMessage = "New password must be at least 8 characters long.";
        } else {
            // Verify current password
            $passwordQuery = "SELECT password FROM users WHERE id = ?";
            $stmt = $conn->prepare($passwordQuery);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $passwordResult = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($passwordResult && password_verify($currentPassword, $passwordResult['password'])) {
                // Hash new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Update password in database
                $updateQuery = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("si", $hashedPassword, $userId);
                
                if ($stmt->execute()) {
                    $successMessage = "Password updated successfully!";
                    $currentPassword = $newPassword = $confirmPassword = '';
                } else {
                    $errorMessage = "Error updating password: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errorMessage = "Current password is incorrect.";
            }
        }
    } elseif (isset($_POST['update_profile_picture'])) {
        // Profile picture update form
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/profiles/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Get file info
            $fileName = $_FILES['profile_picture']['name'];
            $fileType = $_FILES['profile_picture']['type'];
            $fileTmpName = $_FILES['profile_picture']['tmp_name'];
            $fileError = $_FILES['profile_picture']['error'];
            $fileSize = $_FILES['profile_picture']['size'];
            
            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($fileType, $allowedTypes)) {
                $errorMessage = "Only JPG, PNG, and GIF files are allowed.";
            } elseif ($fileSize > 5000000) { // 5MB max
                $errorMessage = "File size must be less than 5MB.";
            } else {
                // Generate unique filename
                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                $newFileName = uniqid('profile_') . '.' . $fileExtension;
                $targetFilePath = $uploadDir . $newFileName;
                
                // Upload file
                if (move_uploaded_file($fileTmpName, $targetFilePath)) {
                    // Update profile picture in database
                    $updateQuery = "UPDATE users SET profile_pic = ? WHERE id = ?";
                    $stmt = $conn->prepare($updateQuery);
                    $stmt->bind_param("si", $newFileName, $userId);
                    
                    if ($stmt->execute()) {
                        // Delete old profile picture if exists
                        if ($userProfilePic && file_exists($uploadDir . $userProfilePic)) {
                            unlink($uploadDir . $userProfilePic);
                        }
                        
                        $userProfilePic = $newFileName;
                        $profileImageUrl = "uploads/profiles/" . $newFileName;
                        $successMessage = "Profile picture updated successfully!";
                    } else {
                        $errorMessage = "Error updating profile picture in database: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $errorMessage = "Error uploading file.";
                }
            }
        } else {
            $errorMessage = "Please select a file to upload.";
        }
    }
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - KEMOS DONATION</title>
    <link rel="stylesheet" href="home.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #0052cc;
            --secondary-color: #0077e6;
            --accent-color: #66b3ff;
            --light-blue: #e6f2ff;
            --dark-blue: #003d99;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --gray-light: #f8f9fc;
            --gray-medium: #eaecf4;
            --gray-dark: #5a5c69;
        }
        
        /* User profile styles */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            position: relative;
        }
        
        .username {
            font-weight: 500;
            color: #333;
        }
        
        .profile-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }
        
        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            width: 200px;
            z-index: 100;
            margin-top: 10px;
            visibility: hidden;
            opacity: 0;
            transition: visibility 0.2s, opacity 0.2s ease-in-out;
        }
        
        .user-info:hover .user-dropdown,
        .user-dropdown:hover {
            visibility: visible;
            opacity: 1;
        }
        
        .user-dropdown-item {
            padding: 12px 15px;
            display: block;
            text-decoration: none;
            color: #333;
            transition: background-color 0.3s;
        }
        
        .user-dropdown-item:hover {
            background-color: var(--light-blue);
        }
        
        .user-dropdown-item i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .user-role {
            font-size: 12px;
            background-color: var(--light-blue);
            color: var(--primary-color);
            padding: 3px 8px;
            border-radius: 10px;
            margin-left: 5px;
        }
        
        /* Settings page styles */
        .settings-container {
            max-width: 1200px;
            margin: 100px auto 50px;
            padding: 0 20px;
        }
        
        .settings-header {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .settings-header h1 {
            color: var(--primary-color);
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .settings-header p {
            color: #666;
        }
        
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }
        
        .settings-sidebar {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }
        
        .settings-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .settings-nav-item {
            margin-bottom: 10px;
        }
        
        .settings-nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            border-radius: 5px;
            text-decoration: none;
            color: #444;
            transition: all 0.3s;
        }
        
        .settings-nav-link:hover {
            background-color: var(--light-blue);
            color: var(--primary-color);
        }
        
        .settings-nav-link.active {
            background-color: var(--light-blue);
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .settings-nav-link i {
            color: var(--primary-color);
            width: 20px;
            text-align: center;
        }
        
        .settings-content {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }
        
        .settings-section {
            display: none;
        }
        
        .settings-section.active {
            display: block;
        }
        
        .settings-section h2 {
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .settings-section h2::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--accent-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            color: #444;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray-medium);
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 82, 204, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--dark-blue);
        }
        
        .btn-secondary {
            background-color: #f0f0f0;
            color: #444;
        }
        
        .btn-secondary:hover {
            background-color: #e0e0e0;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: rgba(46, 204, 113, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }
        
        .alert-danger {
            background-color: rgba(231, 76, 60, 0.1);
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
        }
        
        .profile-picture-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
            margin-bottom: 20px;
        }
        
        .file-upload {
            position: relative;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .file-upload input[type="file"] {
            position: absolute;
            font-size: 100px;
            opacity: 0;
            right: 0;
            top: 0;
            cursor: pointer;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .settings-sidebar {
                margin-bottom: 30px;
            }
            
            .settings-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .settings-nav-item {
                margin-bottom: 0;
                flex: 1 0 auto;
            }
            
            .settings-nav-link {
                justify-content: center;
                padding: 10px;
            }
            
            .settings-nav-link span {
                display: none;
            }
            
            .settings-nav-link i {
                font-size: 18px;
                margin: 0;
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <img src="/placeholder.svg" alt="">
            KEMOS DONATION
        </div>
        <nav>
            <ul class="nav-links">
                <li><a href="user_page.php">Home</a></li>
                <li><a href="about-us.php">About Us</a></li>
                <li><a href="donate.php">Donate</a></li>
                <li><a href="contact.php">Contact Us</a></li>
            </ul>
        </nav>
        
        <!-- User Profile Section -->
        <div class="user-profile">
            <div class="user-info">
                <span class="username">
                    <?= htmlspecialchars($userName) ?>
                    <span class="user-role"><?= htmlspecialchars(ucfirst($userRole)) ?></span>
                </span>
                <img src="<?= $profileImageUrl ?>" alt="<?= htmlspecialchars($userName) ?>" class="profile-icon">
                
                <!-- Dropdown Menu -->
                <div class="user-dropdown">
                    <a href="profile.php" class="user-dropdown-item">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                    <a href="donations.php" class="user-dropdown-item">
                        <i class="fas fa-donate"></i> My Donations
                    </a>
                    <a href="settings.php" class="user-dropdown-item">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <a href="logout.php" class="user-dropdown-item">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="settings-container">
        <div class="settings-header">
            <h1>Account Settings</h1>
            <p>Manage your profile, security, and preferences</p>
        </div>
        
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>
        
        <div class="settings-grid">
            <div class="settings-sidebar">
                <ul class="settings-nav">
                    <li class="settings-nav-item">
                        <a href="#profile" class="settings-nav-link active" data-section="profile">
                            <i class="fas fa-user"></i>
                            <span>Profile Information</span>
                        </a>
                    </li>
                    <li class="settings-nav-item">
                        <a href="#security" class="settings-nav-link" data-section="security">
                            <i class="fas fa-lock"></i>
                            <span>Security</span>
                        </a>
                    </li>
                    <li class="settings-nav-item">
                        <a href="#picture" class="settings-nav-link" data-section="picture">
                            <i class="fas fa-camera"></i>
                            <span>Profile Picture</span>
                        </a>
                    </li>
                    <li class="settings-nav-item">
                        <a href="#notifications" class="settings-nav-link" data-section="notifications">
                            <i class="fas fa-bell"></i>
                            <span>Notifications</span>
                        </a>
                    </li>
                    <li class="settings-nav-item">
                        <a href="#privacy" class="settings-nav-link" data-section="privacy">
                            <i class="fas fa-shield-alt"></i>
                            <span>Privacy</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="settings-content">
                <!-- Profile Information Section -->
                <div id="profile" class="settings-section active">
                    <h2>Profile Information</h2>
                    <p>Update your personal information</p>
                    
                    <form action="settings.php" method="POST">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" value="<?= htmlspecialchars($userName) ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" value="<?= htmlspecialchars($userLastName) ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" class="form-control" value="<?= htmlspecialchars($userEmail) ?>" disabled>
                            <small>Email cannot be changed. Contact support for assistance.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($userPhone) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" class="form-control" value="<?= htmlspecialchars($userAddress) ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" class="form-control" value="<?= htmlspecialchars($userCity) ?>">
                            </div>
                            <div class="form-group">
                                <label for="country">Country</label>
                                <input type="text" id="country" name="country" class="form-control" value="<?= htmlspecialchars($userCountry) ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <!-- Security Section -->
                <div id="security" class="settings-section">
                    <h2>Security</h2>
                    <p>Update your password and security settings</p>
                    
                    <form action="settings.php" method="POST">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                            <small>Password must be at least 8 characters long.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>
                
                <!-- Profile Picture Section -->
                <div id="picture" class="settings-section">
                    <h2>Profile Picture</h2>
                    <p>Update your profile picture</p>
                    
                    <div class="profile-picture-container">
                        <img src="<?= $profileImageUrl ?>" alt="<?= htmlspecialchars($userName) ?>" class="profile-picture">
                        
                        <form action="settings.php" method="POST" enctype="multipart/form-data">
                            <div class="file-upload">
                                <button type="button" class="btn btn-secondary">Choose File</button>
                                <input type="file" name="profile_picture" accept="image/jpeg, image/png, image/gif">
                            </div>
                            <small>Allowed formats: JPG, PNG, GIF. Max size: 5MB.</small>
                            
                            <div class="form-group" style="margin-top: 20px;">
                                <button type="submit" name="update_profile_picture" class="btn btn-primary">Upload New Picture</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Notifications Section -->
                <div id="notifications" class="settings-section">
                    <h2>Notifications</h2>
                    <p>Manage your notification preferences</p>
                    
                    <form action="settings.php" method="POST">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="email_notifications" checked> Email Notifications
                            </label>
                            <small>Receive emails about your donations, receipts, and updates.</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="sms_notifications" checked> SMS Notifications
                            </label>
                            <small>Receive text messages for important updates and donation confirmations.</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="marketing_emails" checked> Marketing Emails
                            </label>
                            <small>Receive newsletters, promotions, and updates about new charities.</small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="update_notifications" class="btn btn-primary">Save Preferences</button>
                        </div>
                    </form>
                </div>
                
                <!-- Privacy Section -->
                <div id="privacy" class="settings-section">
                    <h2>Privacy</h2>
                    <p>Manage your privacy settings</p>
                    
                    <form action="settings.php" method="POST">
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="public_profile" checked> Public Profile
                            </label>
                            <small>Allow others to see your donation activity (amounts will be hidden).</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="anonymous_donations"> Anonymous Donations
                            </label>
                            <small>Make all your donations anonymous by default.</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="data_sharing" checked> Data Sharing
                            </label>
                            <small>Allow us to share anonymized data with our charity partners.</small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="update_privacy" class="btn btn-primary">Save Preferences</button>
                        </div>
                    </form>
                    
                    <div style="margin-top: 40px; border-top: 1px solid var(--gray-medium); padding-top: 20px;">
                        <h3 style="color: var(--danger-color);">Danger Zone</h3>
                        <p>Permanent actions that cannot be undone</p>
                        
                        <div style="margin-top: 20px;">
                            <button type="button" class="btn btn-danger" onclick="confirmAccountDeletion()">Delete Account</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>About KEMOS</h3>
                <p>We're on a mission to transform how people give and make a positive impact in communities worldwide.</p>
                <div class="social-icons">
                    <a href="https://www.facebook.com/login/"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://x.com/?lang=en"><i class="fab fa-twitter"></i></a>
                    <a href="https://www.instagram.com/"><i class="fab fa-instagram"></i></a>
                    <a href="https://ke.linkedin.com/"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h3>Contact Us</h3>
                <p><i class="fas fa-map-marker-alt"></i> 123 Charity Lane, Nairobi, Kenya</p>
                <p><i class="fas fa-phone"></i> +254 123 456 789</p>
                <p><i class="fas fa-envelope"></i> info@kemosdonation.org</p>
            </div>
        </div>
        <div class="copyright">
            <p>&copy; 2025 KEMOS DONATION. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Tab navigation for settings sections
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.settings-nav-link');
            const sections = document.querySelectorAll('.settings-section');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all links and sections
                    navLinks.forEach(l => l.classList.remove('active'));
                    sections.forEach(s => s.classList.remove('active'));
                    
                    // Add active class to clicked link
                    this.classList.add('active');
                    
                    // Show corresponding section
                    const sectionId = this.getAttribute('data-section');
                    document.getElementById(sectionId).classList.add('active');
                });
            });
            
            // File input styling
            const fileInput = document.querySelector('input[type="file"]');
            const fileButton = document.querySelector('.file-upload .btn');
            
            if (fileInput && fileButton) {
                fileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        fileButton.textContent = this.files[0].name;
                    } else {
                        fileButton.textContent = 'Choose File';
                    }
                });
            }
        });
        
        // Confirm account deletion
        function confirmAccountDeletion() {
            if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
                alert('For security reasons, please contact our support team to delete your account.');
            }
        }
    </script>
</body>
</html>


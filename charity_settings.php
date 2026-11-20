<?php
// Start session
session_start();

// Check if logged in as charity
if (!isset($_SESSION['charity_id'])) {
    header("Location: charity_login.php");
    exit();
}

// Get charity data from session
$charityId = $_SESSION['charity_id'];
$charityName = $_SESSION['charity_name'];
$charityEmail = $_SESSION['charity_email'];
$licenseStatus = $_SESSION['license_status'] ?? 'inactive';
$licenseExpiry = $_SESSION['license_expiry'] ?? null;

// Database connection
require_once 'config.php';
$conn->select_db("kemos_donation");

// Get charity details
$stmt = $conn->prepare("SELECT logo_url, email FROM charity_users WHERE id = ?");
$stmt->bind_param("i", $charityId);
$stmt->execute();
$charityData = $stmt->get_result()->fetch_assoc();
$charityLogo = $charityData['logo_url'] ?? '/placeholder.svg?height=150&width=150';
$charityEmailDb = $charityData['email'];
$stmt->close();

// Handle form submissions
$successMessage = $errorMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_email'])) {
        $newEmail = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        if (filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $stmt = $conn->prepare("UPDATE charity_users SET email = ? WHERE id = ?");
            $stmt->bind_param("si", $newEmail, $charityId);
            if ($stmt->execute()) {
                $successMessage = "Email updated successfully!";
                $_SESSION['charity_email'] = $newEmail;
            } else {
                $errorMessage = "Failed to update email.";
            }
            $stmt->close();
        } else {
            $errorMessage = "Invalid email format.";
        }
    }

    if (isset($_POST['update_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        // Verify current password (assuming password is hashed in DB)
        $stmt = $conn->prepare("SELECT password FROM charity_users WHERE id = ?");
        $stmt->bind_param("i", $charityId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $storedPassword = $result['password'];
        $stmt->close();

        if (password_verify($currentPassword, $storedPassword)) {
            if ($newPassword === $confirmPassword) {
                if (strlen($newPassword) >= 8) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE charity_users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashedPassword, $charityId);
                    if ($stmt->execute()) {
                        $successMessage = "Password updated successfully!";
                    } else {
                        $errorMessage = "Failed to update password.";
                    }
                    $stmt->close();
                } else {
                    $errorMessage = "Password must be at least 8 characters long.";
                }
            } else {
                $errorMessage = "New password and confirmation do not match.";
            }
        } else {
            $errorMessage = "Current password is incorrect.";
        }
    }

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['logo']['tmp_name'];
        $fileName = $_FILES['logo']['name'];
        $fileSize = $_FILES['logo']['size'];
        $fileType = $_FILES['logo']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedExts = ['jpg', 'jpeg', 'png'];
        if (in_array($fileExtension, $allowedExts) && $fileSize < 2000000) { // 2MB limit
            $newFileName = 'logo_' . $charityId . '.' . $fileExtension;
            $uploadPath = 'uploads/logos/' . $newFileName;
            if (move_uploaded_file($fileTmpPath, $uploadPath)) {
                $stmt = $conn->prepare("UPDATE charity_users SET logo_url = ? WHERE id = ?");
                $stmt->bind_param("si", $uploadPath, $charityId);
                if ($stmt->execute()) {
                    $successMessage = "Logo updated successfully!";
                    $charityLogo = $uploadPath;
                } else {
                    $errorMessage = "Failed to update logo in database.";
                }
                $stmt->close();
            } else {
                $errorMessage = "Failed to upload logo.";
            }
        } else {
            $errorMessage = "Invalid file type or size (max 2MB, JPG/PNG only).";
        }
    }
}

// Check license status
$licenseActive = ($licenseStatus === 'active' && (!$licenseExpiry || strtotime($licenseExpiry) > time()));
$licenseClass = $licenseActive ? 'active' : 'inactive';
$licenseMessage = $licenseActive ? 
    "Your charity is currently visible to donors (expires " . date('M d, Y', strtotime($licenseExpiry)) . ")" : 
    "Your charity is not visible to donors. Purchase a license to showcase your charity.";

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Charity Settings - KEMOS DONATION</title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="charity.css">
    <link rel="stylesheet" href="charity_settings.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .settings-card form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .settings-card input,
        .settings-card button {
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }

        .settings-card button {
            background: var(--primary-color);
            color: white;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
        }

        .settings-card button:hover {
            background: darken(var(--primary-color), 10%);
        }

        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
        }

        .logo-preview img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 5px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <header class="charity-header">
        <div class="logo">
            <img src="/placeholder.svg" alt="">
            KEMOS DONATION
        </div>
        <nav>
            <ul class="nav-links">
                <li><a href="charity_dashboard.php">Dashboard</a></li>
                <li><a href="charity_donations.php">Donations</a></li>
                <li><a href="charity_allocation.php">Fund Allocation</a></li>
                <li><a href="charity_licenses.php">Licenses</a></li>
                <li><a href="charity_profile.php">Profile</a></li>
                <li><a href="charity_settings.php" class="active">Settings</a></li>
            </ Tolkienul>
        </nav>
        <div class="charity-user">
            <span class="charity-name"><?= htmlspecialchars($charityName) ?></span>
            <div class="charity-dropdown">
                <img src="<?= htmlspecialchars($charityLogo) ?>" alt="<?= htmlspecialchars($charityName) ?>" class="charity-logo">
                <div class="charity-dropdown-content">
                    <a href="charity_profile.php"><i class="fas fa-user-circle"></i> Profile</a>
                    <a href="charity_settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="charity_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="charity-dashboard-container">
        <div class="license-status-bar <?= $licenseClass ?>">
            <i class="fas fa-<?= $licenseActive ? 'check-circle' : 'exclamation-circle' ?>"></i>
            <span><?= $licenseMessage ?></span>
            <?php if (!$licenseActive): ?>
                <a href="charity_licenses.php" class="btn btn-sm">Purchase License</a>
            <?php endif; ?>
        </div>

        <div class="dashboard-welcome">
            <div class="welcome-text">
                <h1>Settings, <?= htmlspecialchars($charityName) ?>!</h1>
                <p>Manage your account settings and preferences.</p>
            </div>
        </div>

        <?php if ($successMessage): ?>
            <div class="message success-message"><?= $successMessage ?></div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="message error-message"><?= $errorMessage ?></div>
        <?php endif; ?>

        <div class="settings-grid">
            <div class="dashboard-card settings-card">
                <div class="card-header">
                    <h2><i class="fas fa-envelope"></i> Update Email</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <label for="email">Current Email: <?= htmlspecialchars($charityEmail) ?></label>
                        <input type="email" id="email" name="email" placeholder="New Email" required>
                        <button type="submit" name="update_email">Update Email</button>
                    </form>
                </div>
            </div>

            <div class="dashboard-card settings-card">
                <div class="card-header">
                    <h2><i class="fas fa-lock"></i> Update Password</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="password" name="current_password" placeholder="Current Password" required>
                        <input type="password" name="new_password" placeholder="New Password" required>
                        <input type="password" name="confirm_password" placeholder="Confirm New Password" required>
                        <button type="submit" name="update_password">Update Password</button>
                    </form>
                </div>
            </div>

            <div class="dashboard-card settings-card">
                <div class="card-header">
                    <h2><i class="fas fa-image"></i> Update Logo</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="logo-preview">
                            <img src="<?= htmlspecialchars($charityLogo) ?>" alt="Current Logo">
                        </div>
                        <input type="file" name="logo" accept=".jpg,.jpeg,.png" required>
                        <button type="submit">Upload New Logo</button>
                    </form>
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
</body>
</html>
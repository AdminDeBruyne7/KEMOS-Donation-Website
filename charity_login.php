<?php
// Start session
session_start();

// Check if already logged in as charity
if (isset($_SESSION['charity_id'])) {
    header("Location: charity_dashboard.php");
    exit();
}

// Database connection
require_once 'config.php';

// Initialize variables
$error = '';
$email = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Select the kemos_donation database
        $conn->select_db("kemos_donation");
        
        // Check if charity exists
        $stmt = $conn->prepare("SELECT id, charity_name, email, password, license_status, license_expiry FROM charity_users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $charity = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $charity['password'])) {
                // Set session variables
                $_SESSION['charity_id'] = $charity['id'];
                $_SESSION['charity_name'] = $charity['charity_name'];
                $_SESSION['charity_email'] = $charity['email'];
                $_SESSION['license_status'] = $charity['license_status'];
                $_SESSION['license_expiry'] = $charity['license_expiry'];
                
                // Redirect to dashboard
                header("Location: charity_dashboard.php");
                exit();
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Invalid email or password.";
        }
        
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Charity Login - KEMOS DONATION</title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="charity.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo">
            <img src="/placeholder.svg" alt="">
            KEMOS DONATION
    </header>

    <div class="charity-login-container">
        <div class="charity-login-card">
            <div class="charity-login-header">
                <h1>Charity Login</h1>
                <p>Access your charity dashboard</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form action="charity_login.php" method="POST" class="charity-login-form">
                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </div>
                
                <div class="charity-login-links">
                    <a href="index.php">Back To Website</a>
                    <a href="charity_forgot_password.php">Forgot password?</a>
                </div>
            </form>
        </div>
        
        <div class="charity-login-info">
            <h2>For Charities</h2>
            <p>Join our platform to showcase your charity, receive donations, and manage your funds efficiently.</p>
            
            <div class="charity-benefits">
                <div class="benefit-item">
                    <i class="fas fa-globe"></i>
                    <h3>Increase Visibility</h3>
                    <p>Showcase your charity to thousands of potential donors.</p>
                </div>
                
                <div class="benefit-item">
                    <i class="fas fa-hand-holding-usd"></i>
                    <h3>Receive Donations</h3>
                    <p>Accept donations securely through our platform.</p>
                </div>
                
                <div class="benefit-item">
                    <i class="fas fa-chart-line"></i>
                    <h3>Track Progress</h3>
                    <p>Monitor donations and show donors how funds are used.</p>
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


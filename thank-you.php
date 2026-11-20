<?php
// Start session to access session variables
session_start();

// Check if we have donation data in the URL parameters
$donationAmount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$donationCharity = isset($_GET['charity']) ? $_GET['charity'] : '';
$donationCharityId = isset($_GET['charity_id']) ? intval($_GET['charity_id']) : 0;

// If no donation data, redirect to donation page
if ($donationAmount == 0 || empty($donationCharity)) {
    header("Location: donate.php");
    exit();
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userName = $isLoggedIn ? $_SESSION['name'] : 'Guest';
$userEmail = $isLoggedIn ? ($_SESSION['email'] ?? '') : '';
$userRole = $isLoggedIn ? ($_SESSION['role'] ?? 'user') : '';

// Get user profile picture if available
$profileImageUrl = "";
if ($isLoggedIn) {
    $userId = $_SESSION['user_id'];
    $userProfilePic = null;
    
    try {
        // Connect to users_db to get profile info
        $conn_users = new mysqli("localhost", "root", "", "users_db");
        if (!$conn_users->connect_error) {
            $stmt = $conn_users->prepare("SELECT profile_pic FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $userData = $result->fetch_assoc();
                $userProfilePic = $userData['profile_pic'] ?? null;
            }
            $stmt->close();
            $conn_users->close();
        }
    } catch (Exception $e) {
        // Silently handle any errors
    }

    $profileImageUrl = $userProfilePic ? "uploads/profiles/" . $userProfilePic : 
                      "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=0052cc&color=fff";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You - KEMOS DONATION</title>
    <link rel="stylesheet" href="donate.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .thank-you-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 40px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .thank-you-icon {
            font-size: 80px;
            color: var(--success-color);
            margin-bottom: 20px;
        }
        
        .thank-you-title {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .thank-you-message {
            font-size: 18px;
            color: #555;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .charity-info {
            background-color: var(--light-blue);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .charity-name {
            font-size: 20px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .donation-amount {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .mpesa-instructions {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            text-align: left;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        
        .mpesa-instructions h3 {
            color: #856404;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .mpesa-instructions p {
            color: #856404;
            margin-bottom: 5px;
        }
        
        .mpesa-instructions ul {
            list-style-type: disc;
            padding-left: 20px;
            margin-top: 10px;
        }
        
        .mpesa-instructions li {
            margin-bottom: 5px;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        .view-donations-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .view-donations-btn:hover {
            background-color: var(--dark-blue);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .home-btn {
            background-color: white;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .home-btn:hover {
            background-color: var(--light-blue);
        }
        
        @media (max-width: 768px) {
            .thank-you-container {
                padding: 30px 20px;
                margin: 30px 15px;
            }
            
            .thank-you-title {
                font-size: 28px;
            }
            
            .thank-you-message {
                font-size: 16px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .view-donations-btn, .home-btn {
                width: 100%;
                justify-content: center;
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
                <li><a href="index.php">Home</a></li>
                <li><a href="about-us.php">About Us</a></li>
                <li><a href="donate.php">Donate</a></li>
                <li><a href="contact.php">Contact Us</a></li>
            </ul>
        </nav>
        
        <?php if ($isLoggedIn): ?>
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
                    <a href="donation_tracker.php" class="user-dropdown-item">
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
        <?php else: ?>
        <!-- Login/Register Buttons for non-logged in users -->
        <div class="auth-buttons">
            <a href="index.php" class="auth-btn login-btn">Login</a>
            <a href="index.php" class="auth-btn register-btn">Register</a>
        </div>
        <?php endif; ?>
    </header>

    <div class="thank-you-container">
        <div class="thank-you-icon">
            <i class="fas fa-heart"></i>
        </div>
        <h1 class="thank-you-title">Thank You for Your Generosity!</h1>
        <p class="thank-you-message">
            Your donation will make a real difference in the lives of those in need.
            We are deeply grateful for your support and commitment to our cause.
        </p>
        
        <div class="charity-info">
            <div class="charity-name"><?= htmlspecialchars($donationCharity) ?></div>
            <div class="donation-amount">KSh <?= number_format($donationAmount, 2) ?></div>
            <p>Your contribution will help us continue our mission to serve the community.</p>
        </div>
        
        <div class="mpesa-instructions">
            <h3><i class="fas fa-mobile-alt"></i> Complete Your M-Pesa Payment</h3>
            <p>To finalize your donation, please follow these steps:</p>
            <ul>
                <li>Check your phone for an M-Pesa STK push notification</li>
                <li>Enter your M-Pesa PIN to authorize the payment</li>
                <li>You will receive an SMS confirmation from M-Pesa once the transaction is complete</li>
                <li>Your donation will be processed and allocated to your selected charity</li>
            </ul>
            <p><strong>Note:</strong> If you don't see the notification, please check your phone's notification center or try again.</p>
        </div>
        
        <div class="action-buttons">
            <a href="donation_tracker.php" class="view-donations-btn">
                <i class="fas fa-chart-bar"></i> View My Donations Dashboard
            </a>
            <a href="index.php" class="home-btn">
                <i class="fas fa-home"></i> Return to Home
            </a>
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
    document.addEventListener('DOMContentLoaded', function() {
        // Add confetti effect
        function createConfetti() {
            const confettiCount = 100;
            const container = document.querySelector('body');
            
            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                
                // Random properties
                const size = Math.random() * 10 + 5;
                const color = `hsl(${Math.random() * 360}, 100%, 50%)`;
                const left = Math.random() * 100;
                const animationDuration = Math.random() * 3 + 2;
                
                // Apply styles
                confetti.style.width = `${size}px`;
                confetti.style.height = `${size}px`;
                confetti.style.backgroundColor = color;
                confetti.style.left = `${left}vw`;
                confetti.style.animationDuration = `${animationDuration}s`;
                confetti.style.position = 'fixed';
                confetti.style.top = '-10px';
                confetti.style.zIndex = '1000';
                confetti.style.borderRadius = '50%';
                confetti.style.animation = `fall ${animationDuration}s linear forwards`;
                
                container.appendChild(confetti);
                
                // Remove after animation completes
                setTimeout(() => {
                    confetti.remove();
                }, animationDuration * 1000);
            }
        }
        
        // Add keyframes for the fall animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fall {
                0% {
                    transform: translateY(-10px) rotate(0deg);
                    opacity: 1;
                }
                100% {
                    transform: translateY(100vh) rotate(360deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Create confetti when page loads
        createConfetti();
    });
    </script>
</body>
</html>

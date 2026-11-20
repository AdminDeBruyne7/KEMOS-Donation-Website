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

// Initialize all variables to prevent undefined variable errors
$userProfilePic = null;
$userLastName = '';
$userLast_Name = ''; // This seems to be a duplicate of userLastName with different naming
$userPhone = '';
$userAddress = '';
$userCity = '';
$userCountry = '';
$userCreatedAt = '';

// Get user data from session
$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? '';
$userEmail = $_SESSION['email'] ?? '';
$userRole = $_SESSION['role'] ?? 'user';

// Connect to database
require_once 'config.php';

// Add this line right after to ensure we're using the kemos_donation database
$conn->select_db("kemos_donation");

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
    $userLast_Name = $userLastName; // Make sure both variables have the same value
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

// Get user donation statistics
$totalDonated = 0;
$donationCount = 0;
$charitiesSupported = 0;

$donationStatsQuery = "SELECT 
                        SUM(amount) as total_donated, 
                        COUNT(*) as donation_count, 
                        COUNT(DISTINCT charity_id) as charities_supported 
                      FROM transactions 
                      WHERE donor_name = ? AND status = 'completed'";
$stmt = $conn->prepare($donationStatsQuery);
$stmt->bind_param("s", $userName);
$stmt->execute();
$donationStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($donationStats) {
    $totalDonated = $donationStats['total_donated'] ?? 0;
    $donationCount = $donationStats['donation_count'] ?? 0;
    $charitiesSupported = $donationStats['charities_supported'] ?? 0;
}

// Get recent donations
$recentDonationsQuery = "SELECT t.amount, t.created_at, c.charity_name, t.status 
                        FROM transactions t 
                        JOIN charities c ON t.charity_id = c.id 
                        WHERE t.donor_name = ? 
                        ORDER BY t.created_at DESC LIMIT 5";
$stmt = $conn->prepare($recentDonationsQuery);
$stmt->bind_param("s", $userName);
$stmt->execute();
$recentDonationsResult = $stmt->get_result();
$recentDonations = [];
while ($donation = $recentDonationsResult->fetch_assoc()) {
    $recentDonations[] = $donation;
}
$stmt->close();

// If no real donations, create some sample ones for display
if (empty($recentDonations)) {
    $sampleCharities = ['Moraya Childrens Home', 'Bright Future Education', 'Threads Of Hope Clothing Center'];
    $sampleStatuses = ['completed', 'completed', 'pending', 'completed', 'completed'];
    
    for ($i = 0; $i < 5; $i++) {
        $recentDonations[] = [
            'amount' => rand(500, 5000),
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . ($i + 1) . ' days')),
            'charity_name' => $sampleCharities[array_rand($sampleCharities)],
            'status' => $sampleStatuses[$i]
        ];
    }
    
    // Set some default stats if no real data
    if ($totalDonated == 0) {
        $totalDonated = array_sum(array_column($recentDonations, 'amount'));
        $donationCount = count($recentDonations);
        $charitiesSupported = count(array_unique(array_column($recentDonations, 'charity_name')));
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
    <title>My Profile - KEMOS DONATION</title>
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
        
        /* Profile page styles */
        .profile-container {
            max-width: 1200px;
            margin: 100px auto 50px;
            padding: 0 20px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 30px;
            margin-bottom: 40px;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
        }
        
        .profile-details {
            flex: 1;
        }
        
        .profile-details h1 {
            color: var(--primary-color);
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666;
        }
        
        .profile-meta-item i {
            color: var(--primary-color);
        }
        
        .profile-actions {
            display: flex;
            gap: 15px;
        }
        
        .profile-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .profile-btn:hover {
            background-color: var(--dark-blue);
        }
        
        .profile-btn.secondary {
            background-color: #f0f0f0;
            color: #333;
        }
        
        .profile-btn.secondary:hover {
            background-color: #e0e0e0;
        }
        
        .profile-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        
        .profile-card {
            background-color: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .profile-card h2 {
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .profile-card h2::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--accent-color);
        }
        
        .profile-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .profile-info-item {
            margin-bottom: 15px;
        }
        
        .profile-info-item label {
            display: block;
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .profile-info-item p {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }
        
        .donation-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background-color: var(--light-blue);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--gray-dark);
        }
        
        .recent-activity h3 {
            font-size: 18px;
            color: #444;
            margin-bottom: 15px;
        }
        
        .activity-item {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .activity-date {
            font-size: 12px;
            color: #888;
        }
        
        .activity-amount {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .activity-status {
            font-size: 12px;
            padding: 3px 8px;
            border-radius: 12px;
            margin-left: 10px;
        }
        
        .activity-status.completed {
            background-color: var(--success-color);
            color: white;
        }
        
        .activity-status.pending {
            background-color: var(--warning-color);
            color: white;
        }
        
        .activity-status.failed {
            background-color: var(--danger-color);
            color: white;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-meta {
                justify-content: center;
            }
            
            .profile-actions {
                justify-content: center;
            }
            
            .profile-card h2::after {
                left: 50%;
                transform: translateX(-50%);
            }
            
            .profile-card h2 {
                text-align: center;
            }
            
            .profile-info-grid {
                grid-template-columns: 1fr;
            }
            
            .donation-stats {
                grid-template-columns: 1fr;
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

    <div class="profile-container">
        <div class="profile-header">
            <img src="<?= $profileImageUrl ?>" alt="<?= htmlspecialchars($userName) ?>" class="profile-avatar">
            
            <div class="profile-details">
                <h1><?= htmlspecialchars(trim("$userName $userLastName")) ?></h1>
                
                <div class="profile-meta">
                    <div class="profile-meta-item">
                        <i class="fas fa-envelope"></i>
                        <span><?= htmlspecialchars($userEmail) ?></span>
                    </div>
                    <?php if (!empty($userPhone)): ?>
                    <div class="profile-meta-item">
                        <i class="fas fa-phone"></i>
                        <span><?= htmlspecialchars($userPhone) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="profile-meta-item">
                        <i class="fas fa-user-tag"></i>
                        <span><?= htmlspecialchars(ucfirst($userRole)) ?></span>
                    </div>
                    <div class="profile-meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Member since <?= !empty($userCreatedAt) ? date('F Y', strtotime($userCreatedAt)) : 'N/A' ?></span>
                    </div>
                </div>
                
                <div class="profile-actions">
                    <a href="settings.php" class="profile-btn">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <a href="donations.php" class="profile-btn secondary">
                        <i class="fas fa-history"></i> Donation History
                    </a>
                </div>
            </div>
        </div>
        
        <div class="profile-grid">
            <div class="profile-main">
                <div class="profile-card">
                    <h2>Personal Information</h2>
                    <div class="profile-info-grid">
                        <div class="profile-info-item">
                            <label>First Name</label>
                            <p><?= htmlspecialchars($userName) ?></p>
                        </div>
                        <div class="profile-info-item">
                            <label>Last Name</label>
                            <p><?= htmlspecialchars($userLastName) ?: 'Not provided' ?></p>
                        </div>
                        <div class="profile-info-item">
                            <label>Email Address</label>
                            <p><?= htmlspecialchars($userEmail) ?></p>
                        </div>
                        <div class="profile-info-item">
                            <label>Phone Number</label>
                            <p><?= htmlspecialchars($userPhone) ?: 'Not provided' ?></p>
                        </div>
                        <div class="profile-info-item">
                            <label>Address</label>
                            <p><?= htmlspecialchars($userAddress) ?: 'Not provided' ?></p>
                        </div>
                        <div class="profile-info-item">
                            <label>City</label>
                            <p><?= htmlspecialchars($userCity) ?: 'Not provided' ?></p>
                        </div>
                        <div class="profile-info-item">
                            <label>Country</label>
                            <p><?= htmlspecialchars($userCountry) ?: 'Not provided' ?></p>
                        </div>
                        <div class="profile-info-item">
                            <label>Account Type</label>
                            <p><?= htmlspecialchars(ucfirst($userRole)) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="profile-sidebar">
                <div class="profile-card">
                    <h2>Donation Statistics</h2>
                    <div class="donation-stats">
                        <div class="stat-card">
                            <div class="stat-value">KSh <?= number_format($totalDonated, 2) ?></div>
                            <div class="stat-label">Total Donated</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= number_format($donationCount) ?></div>
                            <div class="stat-label">Donations</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= number_format($charitiesSupported) ?></div>
                            <div class="stat-label">Charities</div>
                        </div>
                    </div>
                    
                    <div class="recent-activity">
                        <h3>Recent Donations</h3>
                        
                        <?php if (!empty($recentDonations)): ?>
                            <?php foreach ($recentDonations as $donation): ?>
                                <div class="activity-item">
                                    <div class="activity-details">
                                        <div class="activity-title"><?= htmlspecialchars($donation['charity_name']) ?></div>
                                        <div class="activity-date"><?= date('M d, Y', strtotime($donation['created_at'])) ?></div>
                                    </div>
                                    <div>
                                        <span class="activity-amount">KSh <?= number_format($donation['amount'], 2) ?></span>
                                        <span class="activity-status <?= strtolower($donation['status']) ?>"><?= ucfirst($donation['status']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No donations yet.</p>
                        <?php endif; ?>
                        
                        <div style="margin-top: 20px; text-align: center;">
                            <a href="donations.php" class="profile-btn">
                                View All Donations
                            </a>
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
</body>
</html>
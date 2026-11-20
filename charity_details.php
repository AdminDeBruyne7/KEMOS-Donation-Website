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
$userName = $_SESSION['name'];
$userEmail = $_SESSION['email'];
$userRole = $_SESSION['role'] ?? 'user';

// Connect to database
require_once 'config.php';

// Get user data from database
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $userData = $result->fetch_assoc();
    $userName = $userData['first_name'];
    $_SESSION['first_name'] = $userName;
    $userProfilePic = $userData['profile_pic'] ?? null;
}
$stmt->close();

// Generate profile image URL
$profileImageUrl = $userProfilePic ? "uploads/profiles/" . $userProfilePic : 
                  "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=0052cc&color=fff";

// Check if charity ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: user_page.php");
    exit();
}

$charityId = intval($_GET['id']);

// Default charities data (in case the database table doesn't exist yet)
$defaultCharities = [
    1 => [
        'id' => 1,
        'charity_name' => 'Moraya Childrens Home',
        'description' => 'Moraya Children\'s Home is dedicated to providing essential support to orphaned and vulnerable children across Kenya. We focus on providing nutritious meals, quality education, healthcare, and a safe environment for children to grow and thrive. Our team of dedicated caregivers works tirelessly to ensure that each child receives the love, attention, and resources they need to develop into healthy, confident adults.',
        'mission' => 'To ensure every child has access to basic needs, education, and a loving environment regardless of their circumstances.',
        'vision' => 'A world where all children have equal opportunities to reach their full potential.',
        'logo_url' => '',
        'date_established' => '2019-03-15',
        'date_registered' => '2019-05-20',
        'contact_email' => 'info@morayachildrenshome.org',
        'contact_phone' => '+254 712 345 678'
    ],
    2 => [
        'id' => 2,
        'charity_name' => 'Bright Future Education',
        'description' => 'Bright Future Education works to ensure that all students have the tools they need to succeed academically. We provide school supplies, textbooks, and technology to underserved schools and communities. Our programs include tutoring services, scholarship opportunities, and teacher training to improve educational outcomes for students from disadvantaged backgrounds.',
        'mission' => 'To bridge the educational resource gap and empower students through access to quality learning materials.',
        'vision' => 'An educated society where all students have equal access to the resources they need for academic success.',
        'logo_url' => '',
        'date_established' => '2020-01-25',
        'date_registered' => '2020-03-12',
        'contact_email' => 'help@brightfutureeducation.org',
        'contact_phone' => '+254 734 567 890'
    ],
    3 => [
        'id' => 3,
        'charity_name' => 'Threads Of Hope Clothing Center',
        'description' => 'Threads of Hope Clothing Center collects, sorts, and distributes clothing to individuals and families in need. We believe that everyone deserves the dignity of clean, appropriate clothing regardless of their financial situation. Our center provides not only clothing but also job interview attire, school uniforms, and seasonal necessities to ensure that everyone in our community has access to proper clothing.',
        'mission' => 'To provide quality clothing to those in need while promoting sustainability and reducing textile waste.',
        'vision' => 'A community where no one lacks proper clothing and where textile recycling is the norm.',
        'logo_url' => '',
        'date_established' => '2018-06-10',
        'date_registered' => '2018-08-22',
        'contact_email' => 'donate@threadsofhope.org',
        'contact_phone' => '+254 723 456 789'
    ]
];

// Try to fetch charity from database first
$charityQuery = "SELECT * FROM charities WHERE id = ?";
$stmt = $conn->prepare($charityQuery);
$stmt->bind_param("i", $charityId);
$stmt->execute();
$charityResult = $stmt->get_result();

if ($charityResult && $charityResult->num_rows > 0) {
    // Use charity from database
    $charity = $charityResult->fetch_assoc();
} else {
    // Use default charity if not found in database
    $charity = $defaultCharities[$charityId] ?? null;
    
    // If charity not found in defaults either, redirect to user page
    if (!$charity) {
        header("Location: user_page.php");
        exit();
    }
}
$stmt->close();

// Try to fetch donation stats from database
$donationStats = ['total' => 0, 'donors' => 0];
$recentDonations = [];

try {
    // Fetch total donations for this charity
    $donationsQuery = "SELECT COALESCE(SUM(amount), 0) as total, COUNT(DISTINCT donor_name) as donors FROM transactions WHERE charity_id = ? AND status = 'completed'";
    $stmt = $conn->prepare($donationsQuery);
    $stmt->bind_param("i", $charityId);
    $stmt->execute();
    $donationsResult = $stmt->get_result();
    
    if ($donationsResult && $donationsResult->num_rows > 0) {
        $donationStats = $donationsResult->fetch_assoc();
    }
    $stmt->close();

    // Fetch recent donations for this charity
    $recentDonationsQuery = "SELECT amount, donor_name, created_at FROM transactions WHERE charity_id = ? AND status = 'completed' ORDER BY created_at DESC LIMIT 5";
    $stmt = $conn->prepare($recentDonationsQuery);
    $stmt->bind_param("i", $charityId);
    $stmt->execute();
    $recentDonationsResult = $stmt->get_result();
    
    if ($recentDonationsResult) {
        while ($donation = $recentDonationsResult->fetch_assoc()) {
            $recentDonations[] = $donation;
        }
    }
    $stmt->close();
} catch (Exception $e) {
    // If there's an error (like the table doesn't exist), just continue with empty stats
}

// If no real donations, create some sample ones
if (empty($recentDonations)) {
    $recentDonations = [
        [
            'donor_name' => 'John Doe',
            'amount' => 5000,
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ],
        [
            'donor_name' => 'Jane Smith',
            'amount' => 2500,
            'created_at' => date('Y-m-d H:i:s', strtotime('-5 days'))
        ],
        [
            'donor_name' => 'Michael Johnson',
            'amount' => 10000,
            'created_at' => date('Y-m-d H:i:s', strtotime('-7 days'))
        ]
    ];
    
    // Set some default stats
    if ($donationStats['total'] == 0) {
        $donationStats['total'] = 17500; // Sum of sample donations
        $donationStats['donors'] = 3; // Number of sample donors
    }
}

// Format dates
$establishedDate = !empty($charity['date_established']) ? 
    date('F d, Y', strtotime($charity['date_established'])) : 'Not specified';

$registeredDate = !empty($charity['date_registered']) ? 
    date('F d, Y', strtotime($charity['date_registered'])) : 'Not specified';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($charity['charity_name']) ?> - KEMOS DONATION</title>
    <link rel="stylesheet" href="home.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        
        /* Charity Details Styles */
        .charity-container {
            max-width: 1200px;
            margin: 100px auto 50px;
            padding: 0 20px;
        }
        
        .charity-header {
            display: flex;
            align-items: flex-start;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .charity-logo {
            width: 300px;
            height: 300px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .charity-info {
            flex: 1;
        }
        
        .charity-info h1 {
            color: var(--primary-color);
            font-size: 32px;
            margin-bottom: 15px;
        }
        
        .charity-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666;
        }
        
        .meta-item i {
            color: var(--primary-color);
        }
        
        .charity-description {
            line-height: 1.8;
            color: #444;
            margin-bottom: 25px;
        }
        
        .charity-actions {
            display: flex;
            gap: 15px;
        }
        
        .donate-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .donate-btn:hover {
            background-color: var(--dark-blue);
        }
        
        .share-btn {
            background-color: #f0f0f0;
            color: #333;
            padding: 10px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .share-btn:hover {
            background-color: #e0e0e0;
        }
        
        .charity-details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-top: 40px;
        }
        
        .charity-about {
            background-color: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .charity-about h2 {
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .charity-about h2::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--accent-color);
        }
        
        .charity-about p {
            text-align: left;
            color: #444;
            line-height: 1.8;
            margin-bottom: 20px;
        }
        
        .charity-stats {
            background-color: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .charity-stats h2 {
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 20px;
            position: relative;
        }
        
        .charity-stats h2::after {
            content: "";
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--accent-color);
        }
        
        .stats-item {
            margin-bottom: 20px;
        }
        
        .stats-item h3 {
            font-size: 16px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stats-item p {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .recent-donations {
            margin-top: 20px;
        }
        
        .recent-donations h3 {
            font-size: 18px;
            color: #444;
            margin-bottom: 15px;
        }
        
        .donation-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .donation-item:last-child {
            border-bottom: none;
        }
        
        .donor-name {
            font-weight: 500;
        }
        
        .donation-amount {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .donation-date {
            font-size: 12px;
            color: #888;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 20px;
            transition: color 0.3s;
        }
        
        .back-link:hover {
            color: var(--dark-blue);
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .charity-details-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .charity-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .charity-logo {
                width: 100%;
                max-width: 300px;
                height: auto;
                aspect-ratio: 1/1;
            }
            
            .charity-meta {
                justify-content: center;
            }
            
            .charity-actions {
                justify-content: center;
            }
            
            .charity-about h2::after,
            .charity-stats h2::after {
                left: 50%;
                transform: translateX(-50%);
            }
            
            .charity-about h2,
            .charity-stats h2 {
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .charity-meta {
                flex-direction: column;
                gap: 10px;
                align-items: center;
            }
            
            .charity-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .donate-btn,
            .share-btn {
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

    <div class="charity-container">
        <a href="user_page.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>
        
        <div class="charity-header">
            <?php
            // Default image if none is provided
            $charityImage = !empty($charity['logo_url']) ? 
                htmlspecialchars($charity['logo_url']) : 
                "https://via.placeholder.com/300x300?text=" . urlencode($charity['charity_name']);
            ?>
            <img src="<?= $charityImage ?>" alt="<?= htmlspecialchars($charity['charity_name']) ?>" class="charity-logo">
            
            <div class="charity-info">
                <h1><?= htmlspecialchars($charity['charity_name']) ?></h1>
                
                <div class="charity-meta">
                    <div class="meta-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Established: <?= $establishedDate ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-certificate"></i>
                        <span>Registered: <?= $registeredDate ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-users"></i>
                        <span><?= number_format($donationStats['donors']) ?> Donors</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-hand-holding-usd"></i>
                        <span>KSh <?= number_format($donationStats['total'], 2) ?> Raised</span>
                    </div>
                </div>
                
                <div class="charity-description">
                    <?= nl2br(htmlspecialchars(substr($charity['description'], 0, 200))) ?>
                    <?= strlen($charity['description']) > 200 ? '...' : '' ?>
                </div>
                
                <div class="charity-actions">
                    <a href="donate.php?charity_id=<?= $charity['id'] ?>" class="donate-btn">
                        <i class="fas fa-heart"></i> Donate Now
                    </a>
                    <a href="#" class="share-btn" onclick="shareCharity(event)">
                        <i class="fas fa-share-alt"></i> Share
                    </a>
                </div>
            </div>
        </div>
        
        <div class="charity-details-grid">
            <div class="charity-about">
                <h2>About This Charity</h2>
                <p><?= nl2br(htmlspecialchars($charity['description'])) ?></p>
                
                <?php if (!empty($charity['mission'])): ?>
                <h3>Our Mission</h3>
                <p><?= nl2br(htmlspecialchars($charity['mission'])) ?></p>
                <?php endif; ?>
                
                <?php if (!empty($charity['vision'])): ?>
                <h3>Our Vision</h3>
                <p><?= nl2br(htmlspecialchars($charity['vision'])) ?></p>
                <?php endif; ?>
                
                <h3>History</h3>
                <p>
                    <?= htmlspecialchars($charity['charity_name']) ?> was established in <?= date('F Y', strtotime($charity['date_established'])) ?> 
                    and officially registered as a charity in <?= date('F Y', strtotime($charity['date_registered'])) ?>. 
                    Since then, we have been dedicated to our mission of helping those in need through various programs and initiatives.
                </p>
            </div>
            
            <div class="charity-stats">
                <h2>Charity Stats</h2>
                
                <div class="stats-item">
                    <h3>Total Donations</h3>
                    <p>KSh <?= number_format($donationStats['total'], 2) ?></p>
                </div>
                
                <div class="stats-item">
                    <h3>Total Donors</h3>
                    <p><?= number_format($donationStats['donors']) ?></p>
                </div>
                
                <div class="recent-donations">
                    <h3>Recent Donations</h3>
                    
                    <?php if (!empty($recentDonations)): ?>
                        <?php foreach ($recentDonations as $donation): ?>
                            <div class="donation-item">
                                <div>
                                    <div class="donor-name"><?= htmlspecialchars($donation['donor_name']) ?></div>
                                    <div class="donation-date"><?= date('M d, Y', strtotime($donation['created_at'])) ?></div>
                                </div>
                                <div class="donation-amount">KSh <?= number_format($donation['amount'], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No donations yet. Be the first to donate!</p>
                    <?php endif; ?>
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
    function shareCharity(event) {
        event.preventDefault();
        
        // Check if Web Share API is supported
        if (navigator.share) {
            navigator.share({
                title: '<?= htmlspecialchars($charity['charity_name']) ?>',
                text: 'Check out this charity on KEMOS DONATION: <?= htmlspecialchars($charity['charity_name']) ?>',
                url: window.location.href
            })
            .then(() => console.log('Share successful'))
            .catch((error) => console.log('Error sharing:', error));
        } else {
            // Fallback for browsers that don't support Web Share API
            alert('Copy this link to share: ' + window.location.href);
        }
    }
    </script>
</body>
</html>


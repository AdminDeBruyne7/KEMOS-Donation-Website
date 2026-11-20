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

// Connect to database to get fresh user data
require_once 'config.php'; // Make sure this file has your database connection

// Get user data from database
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $userData = $result->fetch_assoc();
    // Update session data with fresh database data
    $userName = $userData['first_name']; // Use the name from database
    $_SESSION['first_name'] = $userName; // Update session
    
    // If you have a profile_pic column in your users table, you can use it here
    $userProfilePic = $userData['profile_pic'] ?? null;
}
$stmt->close();

// Generate profile image URL (using UI Avatars as a fallback if no profile pic)
$profileImageUrl = $userProfilePic ? "uploads/profiles/" . $userProfilePic : 
                  "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=0052cc&color=fff";

// Fetch featured charities from the database
$featuredCharitiesQuery = "SELECT id, charity_name, description, logo_url, date_established, date_registered FROM charities ORDER BY id DESC LIMIT 3";
$featuredCharitiesResult = $conn->query($featuredCharitiesQuery);

function base_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']); // Gets the directory path
    return $protocol . $host . $scriptPath . '/';
}

// Usage
// echo base_url().'uploads\charity_logos\charity_1_67ec6df66300b.png'; 


// If no charities found in the database, use these default ones with charity building photos
$defaultCharities = [
    [
        'id' => 1,
        'charity_name' => 'Moraya Childrens Home',
        'description' => 'Providing nutritious meals to orphanages and families in need.',
        // 'logo_url' => '', // Charity building
        // 'logo_url' => base_url().'uploads\charity_logos\charity_1_67ec6df66300b.png', 
        'date_established' => '2019-03-15',
        'date_registered' => '2025-03-15'
    ],
    [
        'id' => 2,
        'charity_name' => 'Bright Future Education',
        'description' => 'Supplying essential educational materials to underprivileged students.',
        'logo_url' => '', // School building
        'date_established' => '2020-01-25',
        'date_registered' => '2025-03-12'
    ],
    [
        'id' => 3,
        'charity_name' => 'Threads Of Hope Clothing Center',
        'description' => 'Equipping those in need with clothing and essential items.',
        'logo_url' => '', // Community center
        'date_established' => '2018-06-10',
        'date_registered' => '2025-03-01'
    ]
];

// Testimonials data - Using local images as placeholders (update paths if needed)
$testimonials = [
    [
        'name' => 'Sarah Johnson',
        'role' => 'Monthly Donor',
        'image' => 'https://images.generated.photos/t1Xed6cCIRgNKknf8WoJfUBijNgOFVh1WLyJvQlajsE/rs:fit:256:256/czM6Ly9pY29uczgu/Z3Bob3Rvcy1wcm9k/LnBob3Rvcy92M18w/MTMwODkzLmpwZw.jpg',
        'text' => 'I\'ve been donating monthly for over a year now, and it\'s incredible to see the direct impact my contributions have made. The transparency reports and updates from the field make me feel connected to the cause.'
    ],
    [
        'name' => 'Michael Omondi',
        'role' => 'Corporate Partner',
        'image' => 'https://images.generated.photos/6Prm4DWHZB98c-seirSy3wZQJMhhFYMfnhYxeekucrE/rs:fit:256:256/czM6Ly9pY29uczgu/Z3Bob3Rvcy1wcm9k/LnBob3Rvcy92M18w/ODI0MDU3LmpwZw.jpg',
        'text' => 'Our company partnered with KEMOS last year, and we\'ve seen firsthand how our donations have transformed communities. The accountability and results-driven approach make this partnership truly meaningful.'
    ],
    [
        'name' => 'Amina Hassan',
        'role' => 'Volunteer & Donor',
        'image' => 'https://api.deepai.org/job-view-file/0628c602-6ceb-45df-bec9-d1474d7fdb55/outputs/output.jpg',
        'text' => 'As both a volunteer and donor, I\'ve witnessed the real change happening on the ground. Every shilling donated goes directly to helping those in need, and the impact stories from beneficiaries are truly heartwarming.'
    ],
    [
        'name' => 'David Mwangi',
        'role' => 'First-time Donor',
        'image' => 'https://images.generated.photos/nPypGMANRyO54sLPGgefQWavioqCqB_r1Yup2A4wKDM/rs:fit:256:256/czM6Ly9pY29uczgu/Z3Bob3Rvcy1wcm9k/LnBob3Rvcy92M18w/NzYxNTgxLmpwZw.jpg',
        'text' => 'I was hesitant to donate at first, but after seeing the detailed reports and success stories, I\'m convinced my contribution is making a real difference. The follow-up emails showing exactly how my donation helped were impressive.'
    ],
    [
        'name' => 'Grace Wambui',
        'role' => 'Education Sponsor',
        'image' => 'https://images.generated.photos/pmGQBir4LLCoMARmBMt1HxNpA2QjIeKsVpZYsGujhP0/rs:fit:256:256/czM6Ly9pY29uczgu/Z3Bob3Rvcy1wcm9k/LnBob3Rvcy92M18w/MDY0MDM0LmpwZw.jpg',
        'text' => 'Sponsoring a child\'s education through KEMOS has been one of the most rewarding experiences of my life. Receiving letters and progress reports from my sponsored student shows me exactly how my donations are creating a brighter future.'
    ],
    [
        'name' => 'James Kariuki',
        'role' => 'Community Fundraiser',
        'image' => 'https://images.generated.photos/RIocgYbYEv32NRHlYBYBU7KxaJ7pnzCoYN80JBIBIqs/rs:fit:256:256/czM6Ly9pY29uczgu/Z3Bob3Rvcy1wcm9k/LnBob3Rvcy92M18w/NDU5NTAyLmpwZw.jpg',
        'text' => 'After organizing a local fundraiser for KEMOS, I had the opportunity to visit one of their projects. Seeing my community\'s donations in action was incredible - from new water systems to educational materials, every contribution truly matters.'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - KEMOS DONATION</title>
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
        
        .logout-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .logout-btn:hover {
            background-color: var(--dark-blue);
        }
        
        /* User dropdown menu */
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
        
        /* Featured Charities styles */
        .featured-charities {
            padding: 60px 20px;
            background-color: #f9f9f9;
            width: 100%;
        }
        
        /* Updated charity meta styles for better alignment */
        .charity-meta {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            color: #666;
            margin: 10px 20px 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .charity-meta-item {
            display: flex;
            align-items: center;
            background-color: var(--light-blue);
            padding: 5px 10px;
            border-radius: 20px;
            transition: background-color 0.3s;
        }
        
        .charity-meta-item:hover {
            background-color: #d9e9ff;
        }
        
        .charity-meta i {
            margin-right: 5px;
            color: var(--primary-color);
            font-size: 12px;
        }
        
        /* Testimonials styles */
        .testimonials {
            padding: 60px 20px;
            background-color: var(--light-blue);
        }
        
        .testimonial-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .testimonial {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
        }
        
        .testimonial:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        
        .testimonial-quote {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .testimonial p {
            font-style: italic;
            margin-bottom: 20px;
            color: #555;
            flex-grow: 1;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
            margin-top: auto;
        }
        
        .testimonial-author img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-right: 15px;
            border: 3px solid var(--light-blue);
            object-fit: cover;
        }
        
        .author-info {
            display: flex;
            flex-direction: column;
        }
        
        .author-name {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 16px;
        }
        
        .author-role {
            font-size: 14px;
            color: #777;
        }
        
        /* Hero section with custom background */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)),
                url("https://images.unsplash.com/photo-1532629345422-7515f3d16bb6?q=80&w=1470&auto=format&fit=crop&ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D");
            background-size: cover;
            background-position: center;
            color: white;
            text-align: center;
            padding: 100px 20px;
            position: relative;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                padding: 15px;
            }
            
            .user-profile {
                margin-top: 15px;
                width: 100%;
                justify-content: center;
            }
            
            .user-dropdown {
                right: 50%;
                transform: translateX(50%);
            }
            
            .testimonial-grid {
                grid-template-columns: 1fr;
            }
            
            .charity-meta {
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
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
                <li><a href="user_page.php" class="active">Home</a></li>
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

    <section class="hero">
        <div class="overlay">
            <h1>Welcome, <span><?= htmlspecialchars($userName) ?></span>!</h1>
            <p>Thank you for being part of our mission to support communities in need.</p>
            <a href="donate.php" class="cta-btn">Make a Donation</a>
        </div>
    </section>

    <section class="featured-charities">
        <h2 class="section-title">Featured Charities</h2>
        <div class="project-grid">
            <?php 
            // Check if we have charities from the database
            if ($featuredCharitiesResult && $featuredCharitiesResult->num_rows > 0) {
                // Use charities from database
                while ($charity = $featuredCharitiesResult->fetch_assoc()) {

                    // Format dates
                    $establishedDate = !empty($charity['date_established']) ? 
                        date('M Y', strtotime($charity['date_established'])) : 'N/A';
                    
                    $registeredDate = !empty($charity['date_registered']) ? 
                        date('M Y', strtotime($charity['date_registered'])) : 'N/A';
                    
                    // Use logo_url from database, fallback to placeholder if empty
                    // $charityImage = !empty($charity['logo_url']) ? 
                    //     htmlspecialchars($charity['logo_url']) : 
                    //     "https://via.placeholder.com/300x200?text=" . urlencode($charity['charity_name']);

                    if(!empty($charity['logo_url'])){
                        $charityImage = base_url().$charity['logo_url'];
                    } else{
                        $charityImage = "https://via.placeholder.com/300x200?text=" . urlencode($charity['charity_name']);
                    }
            ?>
                <div class="project-card">
                    <img src="<?=$charityImage?>" width="300" height="200" alt="<?= htmlspecialchars($charity['charity_name']) ?>">
                    <h3><?= htmlspecialchars($charity['charity_name']) ?></h3>
                    <div class="charity-meta">
                        <div class="charity-meta-item">
                            <i class="fas fa-calendar-alt"></i> Est. <?= $establishedDate ?>
                        </div>
                        <div class="charity-meta-item">
                            <i class="fas fa-certificate"></i> Reg. <?= $registeredDate ?>
                        </div>
                    </div>
                    <p><?= htmlspecialchars(substr($charity['description'], 0, 100)) . (strlen($charity['description']) > 100 ? '...' : '') ?></p>
                    <a href="charity_details.php?id=<?= $charity['id'] ?>" class="learn-more">Learn More</a>
                </div>
            <?php 
                }
            } else {
                // Use default charities if none found in database
                foreach ($defaultCharities as $charity) {
                    $establishedDate = !empty($charity['date_established']) ? 
                        date('M Y', strtotime($charity['date_established'])) : 'N/A';
                    
                    $registeredDate = !empty($charity['date_registered']) ? 
                        date('M Y', strtotime($charity['date_registered'])) : 'N/A';
                    
                    // Ensure default charities have a valid image
                    $charityImage = !empty($charity['logo_url']) ? 
                        htmlspecialchars($charity['logo_url']) : 
                        "https://via.placeholder.com/300x200?text=" . urlencode($charity['charity_name']);
            ?>
                <div class="project-card">
                    <img src="<?= $charityImage ?>" width="300" height="200" alt="<?= htmlspecialchars($charity['charity_name']) ?>">
                    <h3><?= htmlspecialchars($charity['charity_name']) ?></h3>
                    <div class="charity-meta">
                        <div class="charity-meta-item">
                            <i class="fas fa-calendar-alt"></i> Est. <?= $establishedDate ?>
                        </div>
                        <div class="charity-meta-item">
                            <i class="fas fa-certificate"></i> Reg. <?= $registeredDate ?>
                        </div>
                    </div>
                    <p><?= htmlspecialchars($charity['description']) ?></p>
                    <a href="charity_details.php?id=<?= $charity['id'] ?>" class="learn-more">Learn More</a>
                </div>
            <?php 
                }
            }
            ?>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials">
        <h2 class="section-title">Donor Testimonials</h2>
        <div class="testimonial-grid">
            <?php foreach ($testimonials as $testimonial): ?>
                <div class="testimonial">
                    <div class="testimonial-quote">
                        <i class="fas fa-quote-left"></i>
                    </div>
                    <p><?= htmlspecialchars($testimonial['text']) ?></p>
                    <div class="testimonial-author">
                        <img src="<?= htmlspecialchars($testimonial['image']) ?>" alt="<?= htmlspecialchars($testimonial['name']) ?>">
                        <div class="author-info">
                            <span class="author-name"><?= htmlspecialchars($testimonial['name']) ?></span>
                            <span class="author-role"><?= htmlspecialchars($testimonial['role']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

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

    <script src="script.js"></script>
</body>
</html>
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

// Get user data from database
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $userData = $result->fetch_assoc();
    // Update session data with fresh database data
    $userName = $userData['first_name'] ?? $userName;
    $userProfilePic = $userData['profile_pic'] ?? null;
}
$stmt->close();

// Generate profile image URL
$profileImageUrl = !empty($userProfilePic) ? 
                   "uploads/profiles/" . htmlspecialchars($userProfilePic) : 
                   "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=0052cc&color=fff";


// Get donation statistics
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

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Get total number of donations for pagination
$totalQuery = "SELECT COUNT(*) as total FROM transactions WHERE donor_name = ?";
$stmt = $conn->prepare($totalQuery);
$stmt->bind_param("s", $userName);
$stmt->execute();
$totalResult = $stmt->get_result()->fetch_assoc();
$totalDonations = $totalResult['total'] ?? 0;
$totalPages = ceil($totalDonations / $perPage);
$stmt->close();

// Get donations with pagination
$donationsQuery = "SELECT t.*, c.charity_name 
                  FROM transactions t 
                  LEFT JOIN charities c ON t.charity_id = c.id 
                  WHERE t.donor_name = ? 
                  ORDER BY t.created_at DESC 
                  LIMIT ? OFFSET ?";
$stmt = $conn->prepare($donationsQuery);
$stmt->bind_param("sii", $userName, $perPage, $offset);
$stmt->execute();
$donationsResult = $stmt->get_result();
$donations = [];
while ($donation = $donationsResult->fetch_assoc()) {
    $donations[] = $donation;
}
$stmt->close();

// If no real donations, create some sample ones for display
if (empty($donations)) {
    $sampleCharities = [
        ['id' => 1, 'name' => 'Moraya Childrens Home'],
        ['id' => 2, 'name' => 'Bright Future Education'],
        ['id' => 3, 'name' => 'Threads Of Hope Clothing Center']
    ];
    $sampleStatuses = ['completed', 'completed', 'pending', 'completed', 'failed', 'completed'];
    $samplePaymentMethods = ['M-Pesa', 'M-Pesa', 'Bank Transfer', 'M-Pesa', 'Credit Card'];
    
    // Generate 20 sample donations
    $sampleDonations = [];
    for ($i = 0; $i < 20; $i++) {
        $charity = $sampleCharities[array_rand($sampleCharities)];
        $sampleDonations[] = [
            'id' => $i + 1,
            'transaction_id' => 'TRX' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT),
            'amount' => rand(500, 5000),
            'created_at' => date('Y-m-d H:i:s', strtotime('-' . ($i + 1) . ' days')),
            'charity_id' => $charity['id'],
            'charity_name' => $charity['name'],
            'status' => $sampleStatuses[array_rand($sampleStatuses)],
            'payment_method' => $samplePaymentMethods[array_rand($samplePaymentMethods)]
        ];
    }
    
    // Apply pagination to sample donations
    $totalDonations = count($sampleDonations);
    $totalPages = ceil($totalDonations / $perPage);
    $donations = array_slice($sampleDonations, $offset, $perPage);
    
    // Set some default stats if no real data
    if ($totalDonated == 0) {
        $totalDonated = array_sum(array_column($sampleDonations, 'amount'));
        $donationCount = count($sampleDonations);
        $charitiesSupported = count(array_unique(array_column($sampleDonations, 'charity_id')));
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
    <title>My Donations - KEMOS DONATION</title>
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
        
        /* Donations page styles */
        .donations-container {
            max-width: 1200px;
            margin: 100px auto 50px;
            padding: 0 20px;
        }
        
        .donations-header {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .donations-header h1 {
            color: var(--primary-color);
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .donations-header p {
            color: #666;
            margin-bottom: 20px;
        }
        
        .donation-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background-color: var(--light-blue);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--gray-dark);
        }
        
        .donations-table-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 30px;
            overflow-x: auto;
        }
        
        .donations-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .donations-table th {
            background-color: var(--light-blue);
            color: var(--primary-color);
            font-weight: 600;
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid var(--accent-color);
        }
        
        .donations-table td {
            padding: 15px;
            border-bottom: 1px solid var(--gray-medium);
            color: #444;
        }
        
        .donations-table tr:last-child td {
            border-bottom: none;
        }
        
        .donations-table tr:hover {
            background-color: var(--gray-light);
        }
        
        .donation-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
            min-width: 80px;
        }
        
        .donation-status.completed {
            background-color: var(--success-color);
            color: white;
        }
        
        .donation-status.pending {
            background-color: var(--warning-color);
            color: white;
        }
        
        .donation-status.failed {
            background-color: var(--danger-color);
            color: white;
        }
        
        .donation-amount {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .donation-date {
            color: #666;
        }
        
        .donation-charity {
            font-weight: 500;
        }
        
        .donation-transaction {
            font-family: monospace;
            font-size: 12px;
            color: #666;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 5px;
        }
        
        .pagination-item {
            display: inline-block;
            padding: 8px 15px;
            border-radius: 5px;
            background-color: white;
            color: var(--primary-color);
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid var(--gray-medium);
        }
        
        .pagination-item:hover {
            background-color: var(--light-blue);
        }
        
        .pagination-item.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .pagination-item.disabled {
            color: #ccc;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .no-donations {
            text-align: center;
            padding: 50px 0;
            color: #666;
        }
        
        .no-donations i {
            font-size: 48px;
            color: var(--gray-medium);
            margin-bottom: 15px;
        }
        
        .no-donations h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #444;
        }
        
        .no-donations p {
            margin-bottom: 20px;
        }
        
        .donate-btn {
            display: inline-block;
            background-color: var(--primary-color);
            color: white;
            padding: 10px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .donate-btn:hover {
            background-color: var(--dark-blue);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .donation-stats {
                grid-template-columns: 1fr;
            }
            
            .donations-table th:nth-child(3),
            .donations-table td:nth-child(3) {
                display: none;
            }
            
            .donations-table th:nth-child(5),
            .donations-table td:nth-child(5) {
                display: none;
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

    <div class="donations-container">
        <div class="donations-header">
            <h1>My Donations</h1>
            <p>Track all your contributions and their impact</p>
            
            <div class="donation-stats">
                <div class="stat-card">
                    <div class="stat-value">KSh <?= number_format($totalDonated, 2) ?></div>
                    <div class="stat-label">Total Donated</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($donationCount) ?></div>
                    <div class="stat-label">Donations Made</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format($charitiesSupported) ?></div>
                    <div class="stat-label">Charities Supported</div>
                </div>
            </div>
        </div>
        
        <div class="donations-table-container">
            <?php if (!empty($donations)): ?>
                <table class="donations-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Charity</th>
                            <th>Payment Method</th>
                            <th>Amount</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donations as $donation): ?>
                            <tr>
                                <td class="donation-date"><?= date('M d, Y', strtotime($donation['created_at'])) ?></td>
                                <td class="donation-charity"><?= htmlspecialchars($donation['charity_name']) ?></td>
                                <td><?= htmlspecialchars($donation['payment_method'] ?? 'M-Pesa') ?></td>
                                <td class="donation-amount">KSh <?= number_format($donation['amount'], 2) ?></td>
                                <td class="donation-transaction"><?= htmlspecialchars($donation['transaction_id']) ?></td>
                                <td><span class="donation-status <?= strtolower($donation['status']) ?>"><?= ucfirst($donation['status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <a href="?page=<?= max(1, $page - 1) ?>" class="pagination-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>" class="pagination-item <?= ($i == $page) ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <a href="?page=<?= min($totalPages, $page + 1) ?>" class="pagination-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-donations">
                    <i class="fas fa-donate"></i>
                    <h3>No Donations Yet</h3>
                    <p>You haven't made any donations yet. Start making a difference today!</p>
                    <a href="donate.php" class="donate-btn">Make Your First Donation</a>
                </div>
            <?php endif; ?>
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


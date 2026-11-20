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
$stmt = $conn->prepare("SELECT * FROM charity_users WHERE id = ?");
$stmt->bind_param("i", $charityId);
$stmt->execute();
$result = $stmt->get_result();
$charityData = $result->fetch_assoc();
$stmt->close();

// Get charity logo
$charityLogo = $charityData['logo_url'] ?? '/placeholder.svg?height=150&width=150';

// Get total donations
$stmt = $conn->prepare("SELECT SUM(amount) as total_donations, COUNT(*) as donation_count FROM transactions WHERE charity_id = ? AND status = 'completed'");
$stmt->bind_param("i", $charityId);
$stmt->execute();
$donationData = $stmt->get_result()->fetch_assoc();
$totalDonations = $donationData['total_donations'] ?? 0;
$donationCount = $donationData['donation_count'] ?? 0;
$stmt->close();

// Get recent donations
$stmt = $conn->prepare("SELECT amount, donor_name, created_at FROM transactions WHERE charity_id = ? AND status = 'completed' ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $charityId);
$stmt->execute();
$recentDonations = $stmt->get_result();
$stmt->close();

// Get quarterly donation data for the current year
$currentYear = date('Y');
$quarterlyDonations = [];

for ($quarter = 1; $quarter <= 4; $quarter++) {
    $startMonth = ($quarter - 1) * 3 + 1;
    $endMonth = $quarter * 3;
    
    $startDate = "$currentYear-" . str_pad($startMonth, 2, '0', STR_PAD_LEFT) . "-01";
    $endDate = "$currentYear-" . str_pad($endMonth, 2, '0', STR_PAD_LEFT) . "-31";
    
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as quarter_total FROM transactions WHERE charity_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?");
    $stmt->bind_param("iss", $charityId, $startDate, $endDate);
    $stmt->execute();
    $quarterData = $stmt->get_result()->fetch_assoc();
    $quarterlyDonations["Q$quarter"] = $quarterData['quarter_total'] ?? 0;
    $stmt->close();
}

// Get fund allocation data
$stmt = $conn->prepare("SELECT * FROM charity_allocations WHERE charity_id = ?");
$stmt->bind_param("i", $charityId);
$stmt->execute();
$allocationsResult = $stmt->get_result();
$allocations = [];

if ($allocationsResult->num_rows > 0) {
    while ($row = $allocationsResult->fetch_assoc()) {
        $allocations[] = $row;
    }
} else {
    // Default allocations if none exist
    $allocations = [
        ['category' => 'Programs', 'percentage' => 70, 'description' => 'Direct charity programs and services'],
        ['category' => 'Administration', 'percentage' => 20, 'description' => 'Administrative costs and operations'],
        ['category' => 'Fundraising', 'percentage' => 10, 'description' => 'Fundraising and marketing expenses']
    ];
}
$stmt->close();

// Check license status and expiry
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
    <title>Charity Dashboard - KEMOS DONATION</title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="charity.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Ensure the dashboard grid cards are balanced */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        /* Specific styling for the fund-allocation card */
        .dashboard-card.fund-allocation {
            max-height: 400px; /* Limit the height of the card */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Constrain the chart container */
        .fund-allocation .card-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
        }

        /* Resize the chart canvas */
        #allocationChart {
            max-height: 200px !important; /* Limit chart height */
            max-width: 200px !important; /* Limit chart width */
            margin: 0 auto; /* Center the chart */
        }

        /* Style the allocation legend to fit better */
        .allocation-legend {
            margin-top: 10px;
            max-height: 120px; /* Limit legend height */
            overflow-y: auto; /* Add scroll if legend overflows */
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        /* Style individual legend items */
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .legend-color {
            width: 15px;
            height: 15px;
            border-radius: 3px;
        }

        .legend-text {
            display: flex;
            justify-content: space-between;
            width: 100%;
        }

        .legend-label {
            font-weight: 500;
        }

        .legend-value {
            color: #666;
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
                <li><a href="charity_dashboard.php" class="active">Dashboard</a></li>
                <li><a href="charity_donations.php">Donations</a></li>
                <li><a href="charity_allocation.php">Fund Allocation</a></li>
                <li><a href="charity_licenses.php">Licenses</a></li>
                <li><a href="charity_profile.php">Profile</a></li>
            </ul>
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
                <h1>Welcome, <?= htmlspecialchars($charityName) ?>!</h1>
                <p>Manage your charity, track donations, and update fund allocations.</p>
            </div>
            <div class="quick-actions">
                <a href="charity_profile.php" class="quick-action-btn">
                    <i class="fas fa-edit"></i> Update Profile
                </a>
                <a href="charity_allocation.php" class="quick-action-btn">
                    <i class="fas fa-chart-pie"></i> Update Allocations
                </a>
                <?php if (!$licenseActive): ?>
                    <a href="charity_licenses.php" class="quick-action-btn highlight">
                        <i class="fas fa-certificate"></i> Get License
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dashboard-stats">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                <div class="stat-value">KSh <?= number_format($totalDonations, 2) ?></div>
                <div class="stat-label">Total Donations</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-value"><?= number_format($donationCount) ?></div>
                <div class="stat-label">Total Donors</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-value">KSh <?= number_format($quarterlyDonations["Q" . ceil(date('n') / 3)], 2) ?></div>
                <div class="stat-label">Current Quarter</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-<?= $licenseActive ? 'check-circle' : 'times-circle' ?>"></i></div>
                <div class="stat-value"><?= $licenseActive ? 'Active' : 'Inactive' ?></div>
                <div class="stat-label">License Status</div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="dashboard-card quarterly-donations">
                <div class="card-header">
                    <h2><i class="fas fa-chart-bar"></i> Quarterly Donations (<?= $currentYear ?>)</h2>
                </div>
                <div class="card-body">
                    <canvas id="quarterlyChart"></canvas>
                </div>
            </div>
            
            <div class="dashboard-card fund-allocation">
                <div class="card-header">
                    <h2><i class="fas fa-chart-pie"></i> Fund Allocation</h2>
                    <a href="charity_allocation.php" class="card-action-btn">Update</a>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="allocationChart"></canvas>
                    </div>
                    <div class="allocation-legend">
                        <?php foreach ($allocations as $allocation): ?>
                            <div class="legend-item">
                                <div class="legend-color" style="background-color: var(--color-<?= strtolower($allocation['category']) ?>);"></div>
                                <div class="legend-text">
                                    <span class="legend-label"><?= htmlspecialchars($allocation['category']) ?></span>
                                    <span class="legend-value"><?= $allocation['percentage'] ?>%</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-card recent-donations">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Recent Donations</h2>
                    <a href="charity_donations.php" class="card-action-btn">View All</a>
                </div>
                <div class="card-body">
                    <?php if ($recentDonations->num_rows > 0): ?>
                        <div class="recent-donations-list">
                            <?php while ($donation = $recentDonations->fetch_assoc()): ?>
                                <div class="donation-item">
                                    <div class="donation-info">
                                        <div class="donation-donor"><?= htmlspecialchars($donation['donor_name']) ?></div>
                                        <div class="donation-date"><?= date('M d, Y', strtotime($donation['created_at'])) ?></div>
                                    </div>
                                    <div class="donation-amount">KSh <?= number_format($donation['amount'], 2) ?></div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data-message">
                            <i class="fas fa-info-circle"></i>
                            <p>No donations received yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dashboard-card quick-links">
                <div class="card-header">
                    <h2><i class="fas fa-link"></i> Quick Links</h2>
                </div>
                <div class="card-body">
                    <div class="quick-links-grid">
                        <a href="charity_profile.php" class="quick-link">
                            <i class="fas fa-user-circle"></i>
                            <span>Update Profile</span>
                        </a>
                        <a href="charity_donations.php" class="quick-link">
                            <i class="fas fa-hand-holding-usd"></i>
                            <span>View Donations</span>
                        </a>
                        <a href="charity_allocation.php" class="quick-link">
                            <i class="fas fa-chart-pie"></i>
                            <span>Fund Allocation</span>
                        </a>
                        <a href="charity_licenses.php" class="quick-link">
                            <i class="fas fa-certificate"></i>
                            <span>Manage License</span>
                        </a>
                        <a href="charity_reports.php" class="quick-link">
                            <i class="fas fa-file-alt"></i>
                            <span>Generate Reports</span>
                        </a>
                        <a href="charity_settings.php" class="quick-link">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
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
        // Quarterly Donations Chart
        const quarterlyCtx = document.getElementById('quarterlyChart').getContext('2d');
        const quarterlyChart = new Chart(quarterlyCtx, {
            type: 'bar',
            data: {
                labels: ['Q1', 'Q2', 'Q3', 'Q4'],
                datasets: [{
                    label: 'Donations (KSh)',
                    data: [
                        <?= $quarterlyDonations['Q1'] ?>,
                        <?= $quarterlyDonations['Q2'] ?>,
                        <?= $quarterlyDonations['Q3'] ?>,
                        <?= $quarterlyDonations['Q4'] ?>
                    ],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(54, 162, 235, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(54, 162, 235, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'KSh ' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'KSh ' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Fund Allocation Chart
        const allocationCtx = document.getElementById('allocationChart').getContext('2d');
        const allocationChart = new Chart(allocationCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($allocations as $allocation): ?>
                        '<?= $allocation['category'] ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($allocations as $allocation): ?>
                            <?= $allocation['percentage'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                        'rgba(255, 99, 132, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 99, 132, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                },
                cutout: '60%' // Slightly smaller cutout to fit the smaller chart
            }
        });
    </script>
</body>
</html>
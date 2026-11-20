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

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Filter setup
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query conditions
$conditions = ["charity_id = ?"];
$params = [$charityId];
$types = "i";

if (!empty($startDate)) {
    $conditions[] = "created_at >= ?";
    $params[] = $startDate . " 00:00:00";
    $types .= "s";
}

if (!empty($endDate)) {
    $conditions[] = "created_at <= ?";
    $params[] = $endDate . " 23:59:59";
    $types .= "s";
}

if (!empty($status)) {
    $conditions[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}

$whereClause = implode(" AND ", $conditions);

// Get total number of donations for pagination
$totalQuery = "SELECT COUNT(*) as total FROM transactions WHERE $whereClause";
$stmt = $conn->prepare($totalQuery);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$totalResult = $stmt->get_result()->fetch_assoc();
$totalDonations = $totalResult['total'] ?? 0;
$totalPages = ceil($totalDonations / $perPage);
$stmt->close();

// Get donations with pagination
$donationsQuery = "SELECT * FROM transactions WHERE $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($donationsQuery);
$stmt->bind_param($types . "ii", $perPage, $offset, ...$params);
$stmt->execute();
$donationsResult = $stmt->get_result();
$donations = [];
while ($donation = $donationsResult->fetch_assoc()) {
    $donations[] = $donation;
}
$stmt->close();

// Get donation statistics
$statsQuery = "SELECT 
                COUNT(*) as total_count,
                SUM(amount) as total_amount,
                COUNT(DISTINCT donor_name) as unique_donors,
                AVG(amount) as average_amount,
                MAX(amount) as largest_donation,
                MIN(amount) as smallest_donation
              FROM transactions 
              WHERE $whereClause AND status = 'completed'";
$stmt = $conn->prepare($statsQuery);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get monthly donation data for chart
$monthlyQuery = "SELECT 
                  DATE_FORMAT(created_at, '%Y-%m') as month,
                  SUM(amount) as total
                FROM transactions 
                WHERE charity_id = ? AND status = 'completed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC";
$stmt = $conn->prepare($monthlyQuery);
$stmt->bind_param("i", $charityId);
$stmt->execute();
$monthlyResult = $stmt->get_result();
$monthlyData = [];
$monthlyLabels = [];
$monthlyValues = [];

while ($row = $monthlyResult->fetch_assoc()) {
    $monthlyData[] = $row;
    $monthlyLabels[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthlyValues[] = $row['total'];
}
$stmt->close();

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donations - KEMOS DONATION</title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="charity.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li><a href="charity_donations.php" class="active">Donations</a></li>
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

    <div class="charity-container">
        <div class="charity-page-header">
            <h1><i class="fas fa-hand-holding-usd"></i> Donations</h1>
            <p>Track and manage all donations to your charity</p>
        </div>
        
        <div class="donations-overview">
            <div class="donation-stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-value">KSh <?= number_format($stats['total_amount'] ?? 0, 2) ?></div>
                    <div class="stat-label">Total Donations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-donate"></i></div>
                    <div class="stat-value"><?= number_format($stats['total_count'] ?? 0) ?></div>
                    <div class="stat-label">Transactions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?= number_format($stats['unique_donors'] ?? 0) ?></div>
                    <div class="stat-label">Unique Donors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                    <div class="stat-value">KSh <?= number_format($stats['average_amount'] ?? 0, 2) ?></div>
                    <div class="stat-label">Average Donation</div>
                </div>
            </div>
            
            <div class="donation-chart-container">
                <canvas id="monthlyDonationsChart"></canvas>
            </div>
        </div>
        
        <div class="donations-filter-container">
            <form action="charity_donations.php" method="GET" class="donations-filter-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                        </select>
                    </div>
                    <div class="form-group filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <a href="charity_donations.php" class="btn btn-secondary">
                            <i class="fas fa-sync-alt"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="donations-table-container">
            <?php if (!empty($donations)): ?>
                <table class="donations-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Donor</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donations as $donation): ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($donation['created_at'])) ?></td>
                                <td><?= htmlspecialchars($donation['donor_name']) ?></td>
                                <td>KSh <?= number_format($donation['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($donation['payment_method'] ?? 'M-Pesa') ?></td>
                                <td><?= htmlspecialchars($donation['transaction_id']) ?></td>
                                <td>
                                    <span class="status-badge <?= strtolower($donation['status']) ?>">
                                        <?= ucfirst($donation['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&status=<?= urlencode($status) ?>" class="pagination-item">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&status=<?= urlencode($status) ?>" class="pagination-item <?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&status=<?= urlencode($status) ?>" class="pagination-item">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-data-message">
                    <i class="fas fa-info-circle"></i>
                    <p>No donations found matching your criteria.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="donations-export">
            <h2>Export Donations</h2>
            <p>Download donation data for your records or reporting.</p>
            
            <div class="export-options">
                <a href="charity_export.php?format=csv&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&status=<?= urlencode($status) ?>" class="export-option">
                    <i class="fas fa-file-csv"></i>
                    <span>CSV</span>
                </a>
                <a href="charity_export.php?format=excel&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&status=<?= urlencode($status) ?>" class="export-option">
                    <i class="fas fa-file-excel"></i>
                    <span>Excel</span>
                </a>
                <a href="charity_export.php?format=pdf&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&status=<?= urlencode($status) ?>" class="export-option">
                    <i class="fas fa-file-pdf"></i>
                    <span>PDF</span>
                </a>
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
        // Monthly Donations Chart
        const monthlyCtx = document.getElementById('monthlyDonationsChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($monthlyLabels) ?>,
                datasets: [{
                    label: 'Monthly Donations (KSh)',
                    data: <?= json_encode($monthlyValues) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: 'rgba(54, 162, 235, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4
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
                    title: {
                        display: true,
                        text: 'Monthly Donations (Last 12 Months)',
                        font: {
                            size: 16
                        }
                    },
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
    </script>
</body>
</html>


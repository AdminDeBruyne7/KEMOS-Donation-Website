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

// Get charity logo
$stmt = $conn->prepare("SELECT logo_url FROM charity_users WHERE id = ?");
$stmt->bind_param("i", $charityId);
$stmt->execute();
$charityData = $stmt->get_result()->fetch_assoc();
$charityLogo = $charityData['logo_url'] ?? '/placeholder.svg?height=150&width=150';
$stmt->close();

// Check license status
$licenseActive = ($licenseStatus === 'active' && (!$licenseExpiry || strtotime($licenseExpiry) > time()));
$licenseClass = $licenseActive ? 'active' : 'inactive';
$licenseMessage = $licenseActive ? 
    "Your charity is currently visible to donors (expires " . date('M d, Y', strtotime($licenseExpiry)) . ")" : 
    "Your charity is not visible to donors. Purchase a license to showcase your charity.";

// Get report data
$currentYear = date('Y');

// Total Donations
$stmt = $conn->prepare("SELECT SUM(amount) as total_donations, COUNT(*) as donation_count FROM transactions WHERE charity_id = ? AND status = 'completed'");
$stmt->bind_param("i", $charityId);
$stmt->execute();
$donationData = $stmt->get_result()->fetch_assoc();
$totalDonations = $donationData['total_donations'] ?? 0;
$donationCount = $donationData['donation_count'] ?? 0;
$stmt->close();

// Quarterly Donations
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

// Fund Allocation
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
    $allocations = [
        ['category' => 'Programs', 'percentage' => 70, 'description' => 'Direct charity programs and services'],
        ['category' => 'Administration', 'percentage' => 20, 'description' => 'Administrative costs and operations'],
        ['category' => 'Fundraising', 'percentage' => 10, 'description' => 'Fundraising and marketing expenses']
    ];
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
    <title>Charity Reports - KEMOS DONATION</title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="charity.css">
    <link rel="stylesheet" href="charity_reports.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .report-card {
            max-height: 450px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .report-card .card-body {
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .report-chart {
            max-height: 250px !important;
            max-width: 300px !important;
            margin: 0 auto;
        }

        .report-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        .generate-report-form {
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .generate-report-form select,
        .generate-report-form button {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
        }

        .generate-report-form button {
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }

        .generate-report-form button:hover {
            background: #0056b3;
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
                <li><a href="charity_reports.php" class="active">Reports</a></li>
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
                <h1>Reports, <?= htmlspecialchars($charityName) ?>!</h1>
                <p>Generate and view detailed reports on your charity's performance.</p>
            </div>
        </div>

        <div class="generate-report-form">
            <h3>Generate Custom Report</h3>
            <form id="reportForm">
                <label for="reportType">Report Type:</label>
                <select id="reportType" name="reportType">
                    <option value="quarterly">Quarterly Donations</option>
                    <option value="allocations">Fund Allocations</option>
                    <option value="donor">Donor Summary</option>
                </select>
                <label for="year">Year:</label>
                <select id="year" name="year">
                    <?php for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit">Generate Report</button>
            </form>
        </div>

        <div class="reports-grid">
            <div class="dashboard-card report-card quarterly-report">
                <div class="card-header">
                    <h2><i class="fas fa-chart-bar"></i> Quarterly Donations (<?= $currentYear ?>)</h2>
                </div>
                <div class="card-body">
                    <canvas id="quarterlyReportChart " class="report-chart"></canvas>
                    <div class="report-actions">
                        <button class="download-btn" onclick="downloadChart('quarterlyReportChart', 'Quarterly_Donations_<?= $currentYear ?>')">Download</button>
                    </div>
                </div>
            </div>

            <div class="dashboard-card report-card allocation-report">
                <div class="card-header">
                    <h2><i class="fas fa-chart-pie"></i> Fund Allocation</h2>
                </div>
                <div class="card-body">
                    <canvas id="allocationReportChart" class="report-chart"></canvas>
                    <div class="report-actions">
                        <button class="download-btn" onclick="downloadChart('allocationReportChart', 'Fund_Allocation_<?= $currentYear ?>')">Download</button>
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
        const quarterlyCtx = document.getElementById('quarterlyReportChart').getContext('2d');
        const quarterlyReportChart = new Chart(quarterlyCtx, {
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
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                    borderColor: 'rgba(54, 162, 235, 1)',
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
        const allocationCtx = document.getElementById('allocationReportChart').getContext('2d');
        const allocationReportChart = new Chart(allocationCtx, {
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
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw + '%';
                            }
                        }
                    }
                },
                cutout: '50%'
            }
        });

        // Download Chart Function
        function downloadChart(chartId, fileName) {
            const canvas = document.getElementById(chartId);
            const link = document.createElement('a');
            link.href = canvas.toDataURL('image/png');
            link.download = `${fileName}.png`;
            link.click();
        }

        // Handle Report Generation (Placeholder for future backend integration)
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const reportType = document.getElementById('reportType').value;
            const year = document.getElementById('year').value;
            alert(`Generating ${reportType} report for ${year}. This feature is under development.`);
            // Future AJAX call to backend to generate custom report
        });
    </script>
</body>
</html>
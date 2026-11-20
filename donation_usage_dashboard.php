<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?redirect=donation_usage_dashboard.php");
    exit();
}

// Get user information
$userId = $_SESSION['user_id'];
$userName = $_SESSION['first_name'] ?? 'User';
$userEmail = $_SESSION['email'] ?? '';

// Database connection
$mysqli = new mysqli("localhost", "root", "", "kemos_donation");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Get charity ID from URL parameter, default to first charity if not specified
$charityId = isset($_GET['charity_id']) ? intval($_GET['charity_id']) : null;

// Get year from URL parameter, default to current year if not specified
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Fetch all charities for the dropdown
$charitiesQuery = "SELECT id, charity_name FROM charities ORDER BY charity_name";
$charitiesResult = $mysqli->query($charitiesQuery);
if (!$charitiesResult) {
    die("Query failed: " . $mysqli->error);
}

// If no charity ID is specified, use the first one from the list
if ($charityId === null && $charitiesResult->num_rows > 0) {
    $firstCharity = $charitiesResult->fetch_assoc();
    $charityId = $firstCharity['id'];
    // Reset the result pointer
    $charitiesResult->data_seek(0);
}

// Fetch the selected charity details
$charityQuery = "SELECT * FROM charities WHERE id = ?";
$stmt = $mysqli->prepare($charityQuery);
$stmt->bind_param("i", $charityId);
$stmt->execute();
$charityResult = $stmt->get_result();
$charity = $charityResult->fetch_assoc();

// Fetch total donations for this charity
$totalDonationsQuery = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE charity_id = ? AND status = 'completed'";
$stmt = $mysqli->prepare($totalDonationsQuery);
$stmt->bind_param("i", $charityId);
$stmt->execute();
$totalResult = $stmt->get_result();
$totalDonations = $totalResult->fetch_assoc()['total'];

// Fetch quarterly donation data for the selected year
function getQuarterlyDonations($mysqli, $charityId, $year) {
    // In a real application, this would query the database for actual quarterly data
    // For this example, we'll create realistic quarterly data
    
    // This would be the actual query in a production environment:
    // $query = "SELECT 
    //     QUARTER(transaction_date) as quarter,
    //     SUM(amount) as total
    //     FROM transactions 
    //     WHERE charity_id = ? AND YEAR(transaction_date) = ? AND status = 'completed'
    //     GROUP BY QUARTER(transaction_date)
    //     ORDER BY quarter";
    // $stmt = $mysqli->prepare($query);
    // $stmt->bind_param("ii", $charityId, $year);
    // $stmt->execute();
    // $result = $stmt->get_result();
    
    // For demonstration, we'll generate sample data
    $quarters = [];
    $baseAmount = 50000; // Base amount for Q1
    
    for ($q = 1; $q <= 4; $q++) {
        // Generate realistic quarterly data with growth trend and seasonal variations
        switch ($q) {
            case 1: // Q1 (base amount)
                $amount = $baseAmount + rand(-5000, 5000);
                break;
            case 2: // Q2 (slight increase)
                $amount = $baseAmount * 1.15 + rand(-6000, 6000);
                break;
            case 3: // Q3 (summer donations peak)
                $amount = $baseAmount * 1.35 + rand(-7000, 7000);
                break;
            case 4: // Q4 (end of year giving)
                $amount = $baseAmount * 1.5 + rand(-8000, 8000);
                break;
        }
        
        $quarters[] = [
            'quarter' => $q,
            'label' => "Q$q",
            'amount' => round($amount)
        ];
    }
    
    return $quarters;
}

// Get quarterly donation data
$quarterlyDonations = getQuarterlyDonations($mysqli, $charityId, $selectedYear);

// Calculate total donations for the year from quarterly data
$yearlyTotal = array_sum(array_column($quarterlyDonations, 'amount'));

// Fetch donation usage data for this charity
// In a real application, this would come from a database table tracking expenses
function getDonationUsage($charityId, $yearlyTotal) {
    // These would normally come from a database
    $categories = [
        [
            'category' => 'Direct Aid',
            'percentage' => 65,
            'description' => 'Funds directly supporting beneficiaries through food, shelter, medical care, and education.'
        ],
        [
            'category' => 'Program Operations',
            'percentage' => 20,
            'description' => 'Costs associated with running programs, including staff, transportation, and supplies.'
        ],
        [
            'category' => 'Administrative',
            'percentage' => 10,
            'description' => 'Essential administrative costs including accounting, legal compliance, and office operations.'
        ],
        [
            'category' => 'Fundraising',
            'percentage' => 5,
            'description' => 'Costs related to raising awareness and securing additional funding.'
        ]
    ];
    
    // Calculate actual amounts based on percentages
    foreach ($categories as &$category) {
        $category['amount'] = ($category['percentage'] / 100) * $yearlyTotal;
    }
    
    return $categories;
}

// Get donation usage data
$usageData = getDonationUsage($charityId, $yearlyTotal);

// Get quarterly breakdown of usage for each category
function getQuarterlyUsageBreakdown($usageData, $quarterlyDonations) {
    $quarterlyUsage = [];
    
    foreach ($usageData as $category) {
        $categoryData = [
            'category' => $category['category'],
            'quarterly' => []
        ];
        
        foreach ($quarterlyDonations as $quarter) {
            $quarterAmount = ($category['percentage'] / 100) * $quarter['amount'];
            $categoryData['quarterly'][] = [
                'quarter' => $quarter['quarter'],
                'amount' => $quarterAmount
            ];
        }
        
        $quarterlyUsage[] = $categoryData;
    }
    
    return $quarterlyUsage;
}

// Get quarterly usage breakdown
$quarterlyUsage = getQuarterlyUsageBreakdown($usageData, $quarterlyDonations);

// Fetch recent projects/initiatives for this charity
function getRecentProjects($charityId) {
    // These would normally come from a database
    return [
        [
            'name' => 'Community Health Initiative',
            'budget' => rand(50000, 120000),
            'spent' => rand(20000, 40000),
            'status' => 'In Progress',
            'start_date' => date('Y-m-d', strtotime('-2 months')),
            'end_date' => date('Y-m-d', strtotime('+4 months')),
            'beneficiaries' => rand(30, 80)
        ],
        [
            'name' => 'Education Support Program',
            'budget' => rand(30000, 80000),
            'spent' => rand(15000, 25000),
            'status' => 'In Progress',
            'start_date' => date('Y-m-d', strtotime('-4 months')),
            'end_date' => date('Y-m-d', strtotime('+2 months')),
            'beneficiaries' => rand(15, 40)
        ],
        [
            'name' => 'Clean Water Access',
            'budget' => rand(40000, 100000),
            'spent' => rand(20000, 35000),
            'status' => 'In Progress',
            'start_date' => date('Y-m-d', strtotime('-6 months')),
            'end_date' => date('Y-m-d', strtotime('+1 month')),
            'beneficiaries' => rand(20, 50)
        ]
    ];
}

$recentProjects = getRecentProjects($charityId);

// Fetch donor statistics
function getDonorStatistics($charityId) {
    return [
        'total_donors' => rand(50, 120),
        'new_donors_this_quarter' => rand(5, 15),
        'recurring_donors' => rand(10, 30),
        'average_donation' => rand(500, 2000)
    ];
}

$donorStats = getDonorStatistics($charityId);

// Fetch impact metrics
function getImpactMetrics($charityId) {
    return [
        [
            'metric' => 'Lives Impacted',
            'value' => rand(80, 200),
            'icon' => 'fa-users'
        ],
        [
            'metric' => 'Communities Served',
            'value' => rand(3, 8),
            'icon' => 'fa-house-user'
        ],
        [
            'metric' => 'Projects Completed',
            'value' => rand(2, 6),
            'icon' => 'fa-check-circle'
        ],
        [
            'metric' => 'Volunteer Hours',
            'value' => rand(300, 800),
            'icon' => 'fa-clock'
        ]
    ];
}

$impactMetrics = getImpactMetrics($charityId);

// Generate available years for the dropdown (last 5 years)
$currentYear = intval(date('Y'));
$availableYears = range($currentYear - 4, $currentYear);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Usage Dashboard - KEMOS DONATION</title>
    <link rel="stylesheet" href="donation_tracker.css">
    <link rel="stylesheet" href="donation_usage_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">KEMOS DONATION</div>
            <ul class="nav-links">
                <li><a href="user_page.php">Home</a></li>
                <li><a href="donation_tracker.php">My Donations</a></li>
                <li><a href="donation_usage_dashboard.php" class="active">Impact Dashboard</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1>Quarterly Donation Impact Dashboard</h1>
                <p>See how your donations are making a difference throughout the year</p>
                
                <div class="filter-controls">
                    <form action="donation_usage_dashboard.php" method="GET">
                        <div class="filter-row">
                            <div class="charity-selector">
                                <label for="charity_id">Select Charity:</label>
                                <select name="charity_id" id="charity_id">
                                    <?php while ($charityOption = $charitiesResult->fetch_assoc()): ?>
                                        <option value="<?= $charityOption['id'] ?>" <?= ($charityOption['id'] == $charityId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($charityOption['charity_name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="year-selector">
                                <label for="year">Select Year:</label>
                                <select name="year" id="year">
                                    <?php foreach ($availableYears as $year): ?>
                                        <option value="<?= $year ?>" <?= ($year == $selectedYear) ? 'selected' : '' ?>>
                                            <?= $year ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="filter-button">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($charity): ?>
                <div class="charity-overview">
                    <div class="charity-info">
                        <h2><?= htmlspecialchars($charity['charity_name']) ?></h2>
                        <p><?= htmlspecialchars($charity['description'] ?? 'This charity aims to help those in need through various community programs and initiatives.') ?></p>
                    </div>
                    
                    <div class="donation-total">
                        <div class="total-amount">
                            <span class="label">Total Donations (<?= $selectedYear ?>)</span>
                            <span class="value">KSh <?= number_format($yearlyTotal, 2) ?></span>
                        </div>
                        <a href="donate.php?charity_id=<?= $charity['id'] ?>" class="donate-button">
                            <i class="fas fa-heart"></i> Donate Now
                        </a>
                    </div>
                </div>
                
                <!-- Quarterly Overview -->
                <div class="quarterly-overview">
                    <h3><i class="fas fa-calendar-alt"></i> Quarterly Donation Overview (<?= $selectedYear ?>)</h3>
                    <div class="quarterly-cards">
                        <?php foreach ($quarterlyDonations as $quarter): ?>
                            <div class="quarterly-card">
                                <div class="quarter-label"><?= $quarter['label'] ?></div>
                                <div class="quarter-amount">KSh <?= number_format($quarter['amount'], 0) ?></div>
                                <div class="quarter-percentage">
                                    <?= round(($quarter['amount'] / $yearlyTotal) * 100, 1) ?>% of yearly total
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="dashboard-grid">
                    <!-- Quarterly Donation Chart -->
                    <div class="dashboard-card quarterly-chart-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> Quarterly Donations (<?= $selectedYear ?>)</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="quarterlyChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Donation Allocation Chart -->
                    <div class="dashboard-card allocation-chart-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> Donation Allocation</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="allocationChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quarterly Usage Breakdown -->
                    <div class="dashboard-card quarterly-usage-card">
                        <div class="card-header">
                            <h3><i class="fas fa-money-bill-wave"></i> Quarterly Usage Breakdown</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="quarterlyUsageChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Donation Usage Breakdown -->
                    <div class="dashboard-card usage-breakdown-card">
                        <div class="card-header">
                            <h3><i class="fas fa-list-ul"></i> Usage Breakdown</h3>
                        </div>
                        <div class="card-body">
                            <div class="usage-list">
                                <?php foreach ($usageData as $usage): ?>
                                    <div class="usage-item">
                                        <div class="usage-header">
                                            <span class="usage-category"><?= htmlspecialchars($usage['category']) ?></span>
                                            <span class="usage-amount">KSh <?= number_format($usage['amount'], 2) ?></span>
                                        </div>
                                        <div class="usage-bar-container">
                                            <div class="usage-bar" style="width: <?= $usage['percentage'] ?>%"></div>
                                        </div>
                                        <div class="usage-percentage"><?= $usage['percentage'] ?>%</div>
                                        <div class="usage-description"><?= htmlspecialchars($usage['description']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Donor Statistics -->
                    <div class="dashboard-card donor-stats-card">
                        <div class="card-header">
                            <h3><i class="fas fa-users"></i> Donor Statistics</h3>
                        </div>
                        <div class="card-body">
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                                    <div class="stat-value"><?= number_format($donorStats['total_donors']) ?></div>
                                    <div class="stat-label">Total Donors</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
                                    <div class="stat-value"><?= number_format($donorStats['new_donors_this_quarter']) ?></div>
                                    <div class="stat-label">New This Quarter</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon"><i class="fas fa-sync-alt"></i></div>
                                    <div class="stat-value"><?= number_format($donorStats['recurring_donors']) ?></div>
                                    <div class="stat-label">Recurring Donors</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                                    <div class="stat-value">KSh <?= number_format($donorStats['average_donation']) ?></div>
                                    <div class="stat-label">Average Donation</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Impact Metrics -->
                    <div class="dashboard-card impact-metrics-card">
                        <div class="card-header">
                            <h3><i class="fas fa-heart"></i> Impact Metrics</h3>
                        </div>
                        <div class="card-body">
                            <div class="impact-grid">
                                <?php foreach ($impactMetrics as $metric): ?>
                                    <div class="impact-item">
                                        <div class="impact-icon"><i class="fas <?= $metric['icon'] ?>"></i></div>
                                        <div class="impact-value"><?= number_format($metric['value']) ?></div>
                                        <div class="impact-label"><?= htmlspecialchars($metric['metric']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Projects -->
                    <div class="dashboard-card recent-projects-card">
                        <div class="card-header">
                            <h3><i class="fas fa-project-diagram"></i> Recent Projects</h3>
                        </div>
                        <div class="card-body">
                            <div class="projects-list">
                                <?php foreach ($recentProjects as $project): ?>
                                    <div class="project-item">
                                        <div class="project-header">
                                            <h4><?= htmlspecialchars($project['name']) ?></h4>
                                            <span class="project-status"><?= htmlspecialchars($project['status']) ?></span>
                                        </div>
                                        <div class="project-details">
                                            <div class="project-budget">
                                                <span class="label">Budget:</span>
                                                <span class="value">KSh <?= number_format($project['budget']) ?></span>
                                            </div>
                                            <div class="project-spent">
                                                <span class="label">Spent:</span>
                                                <span class="value">KSh <?= number_format($project['spent']) ?></span>
                                            </div>
                                            <div class="project-dates">
                                                <span class="label">Timeline:</span>
                                                <span class="value"><?= date('M d, Y', strtotime($project['start_date'])) ?> - <?= date('M d, Y', strtotime($project['end_date'])) ?></span>
                                            </div>
                                            <div class="project-beneficiaries">
                                                <span class="label">Beneficiaries:</span>
                                                <span class="value"><?= number_format($project['beneficiaries']) ?> people</span>
                                            </div>
                                        </div>
                                        <div class="project-progress">
                                            <div class="progress-label">
                                                <span>Progress</span>
                                                <span><?= round(($project['spent'] / $project['budget']) * 100) ?>%</span>
                                            </div>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar" style="width: <?= ($project['spent'] / $project['budget']) * 100 ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-footer">
                    <p>Last updated: <?= date('F d, Y h:i A') ?></p>
                    <p>This dashboard shows quarterly donation data and estimated allocation of funds based on our charity guidelines. Actual expenditures may vary slightly.</p>
                </div>
            <?php else: ?>
                <div class="no-charity-message">
                    <p>No charity selected or the selected charity does not exist.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Quarterly Donations Chart
        const quarterlyCtx = document.getElementById('quarterlyChart').getContext('2d');
        const quarterlyChart = new Chart(quarterlyCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($quarterlyDonations as $quarter): ?>
                        '<?= $quarter['label'] ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Quarterly Donations (KSh)',
                    data: [
                        <?php foreach ($quarterlyDonations as $quarter): ?>
                            <?= $quarter['amount'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        'rgba(78, 115, 223, 0.8)',
                        'rgba(28, 200, 138, 0.8)',
                        'rgba(54, 185, 204, 0.8)',
                        'rgba(246, 194, 62, 0.8)'
                    ],
                    borderColor: [
                        'rgb(78, 115, 223)',
                        'rgb(28, 200, 138)',
                        'rgb(54, 185, 204)',
                        'rgb(246, 194, 62)'
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
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += 'KSh ' + context.parsed.y.toLocaleString();
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
        
        // Donation Allocation Chart
        const allocationCtx = document.getElementById('allocationChart').getContext('2d');
        const allocationChart = new Chart(allocationCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($usageData as $usage): ?>
                        '<?= $usage['category'] ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($usageData as $usage): ?>
                            <?= $usage['percentage'] ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#4e73df',
                        '#1cc88a',
                        '#36b9cc',
                        '#f6c23e'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            boxWidth: 12
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                const amount = (value / 100) * <?= $yearlyTotal ?>;
                                return `${label}: ${value}% (KSh ${amount.toLocaleString(undefined, {maximumFractionDigits: 0})})`;
                            }
                        }
                    }
                }
            }
        });
        
        // Quarterly Usage Breakdown Chart
        const quarterlyUsageCtx = document.getElementById('quarterlyUsageChart').getContext('2d');
        const quarterlyUsageChart = new Chart(quarterlyUsageCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($quarterlyDonations as $quarter): ?>
                        '<?= $quarter['label'] ?>',
                    <?php endforeach; ?>
                ],
                datasets: [
                    <?php foreach ($quarterlyUsage as $index => $usage): ?>
                    {
                        label: '<?= $usage['category'] ?>',
                        data: [
                            <?php foreach ($usage['quarterly'] as $quarter): ?>
                                <?= $quarter['amount'] ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: [
                            <?php 
                            $colors = ['rgba(78, 115, 223, 0.8)', 'rgba(28, 200, 138, 0.8)', 
                                      'rgba(54, 185, 204, 0.8)', 'rgba(246, 194, 62, 0.8)'];
                            echo "'{$colors[$index]}'";
                            ?>
                        ],
                        borderColor: [
                            <?php 
                            $borderColors = ['rgb(78, 115, 223)', 'rgb(28, 200, 138)', 
                                           'rgb(54, 185, 204)', 'rgb(246, 194, 62)'];
                            echo "'{$borderColors[$index]}'";
                            ?>
                        ],
                        borderWidth: 1
                    }<?= ($index < count($quarterlyUsage) - 1) ? ',' : '' ?>
                    <?php endforeach; ?>
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: true,
                    },
                    y: {
                        stacked: true,
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
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += 'KSh ' + context.parsed.y.toLocaleString();
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
        
        // Animate numbers on scroll
        const animateValue = (element, start, end, duration) => {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const currentValue = progress * (end - start) + start;
                
                if (element.classList.contains('currency')) {
                    element.textContent = 'KSh ' + Math.floor(currentValue).toLocaleString();
                } else {
                    element.textContent = Math.floor(currentValue).toLocaleString();
                }
                
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                }
            };
            window.requestAnimationFrame(step);
        };
        
        // Intersection Observer for animation
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const valueElements = entry.target.querySelectorAll('.stat-value, .impact-value, .quarter-amount');
                    valueElements.forEach(element => {
                        const value = parseFloat(element.textContent.replace(/,/g, '').replace('KSh ', ''));
                        animateValue(element, 0, value, 1500);
                    });
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        
        // Observe stat cards and quarterly cards
        document.querySelectorAll('.donor-stats-card, .impact-metrics-card, .quarterly-card').forEach(card => {
            observer.observe(card);
        });
    });
    </script>
</body>
</html>
<?php
$mysqli->close();
?>


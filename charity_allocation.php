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
$stmt = $conn->prepare("SELECT SUM(amount) as total_donations FROM transactions WHERE charity_id = ? AND status = 'completed'");
$stmt->bind_param("i", $charityId);
$stmt->execute();
$donationData = $stmt->get_result()->fetch_assoc();
$totalDonations = $donationData['total_donations'] ?? 0;
$stmt->close();

// Get fund allocation data
$stmt = $conn->prepare("SELECT * FROM charity_allocations WHERE charity_id = ? ORDER BY percentage DESC");
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
        ['id' => 0, 'charity_id' => $charityId, 'category' => 'Programs', 'percentage' => 70, 'description' => 'Direct charity programs and services'],
        ['id' => 0, 'charity_id' => $charityId, 'category' => 'Administration', 'percentage' => 20, 'description' => 'Administrative costs and operations'],
        ['id' => 0, 'charity_id' => $charityId, 'category' => 'Fundraising', 'percentage' => 10, 'description' => 'Fundraising and marketing expenses']
    ];
}
$stmt->close();

// Process allocation update
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_allocations'])) {
    $categories = $_POST['category'] ?? [];
    $percentages = $_POST['percentage'] ?? [];
    $descriptions = $_POST['description'] ?? [];
    
    // Validate total percentage equals 100%
    $totalPercentage = array_sum($percentages);
    if ($totalPercentage != 100) {
        $errorMessage = "Total allocation percentage must equal 100%. Current total: $totalPercentage%";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            // Delete existing allocations
            $deleteStmt = $conn->prepare("DELETE FROM charity_allocations WHERE charity_id = ?");
            $deleteStmt->bind_param("i", $charityId);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            // Insert new allocations
            $insertStmt = $conn->prepare("INSERT INTO charity_allocations (charity_id, category, percentage, description) VALUES (?, ?, ?, ?)");
            
            for ($i = 0; $i < count($categories); $i++) {
                if (!empty($categories[$i]) && isset($percentages[$i])) {
                    $insertStmt->bind_param("isds", $charityId, $categories[$i], $percentages[$i], $descriptions[$i]);
                    $insertStmt->execute();
                }
            }
            
            $insertStmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $successMessage = "Fund allocations updated successfully!";
            
            // Refresh allocations
            $stmt = $conn->prepare("SELECT * FROM charity_allocations WHERE charity_id = ? ORDER BY percentage DESC");
            $stmt->bind_param("i", $charityId);
            $stmt->execute();
            $allocationsResult = $stmt->get_result();
            $allocations = [];
            
            while ($row = $allocationsResult->fetch_assoc()) {
                $allocations[] = $row;
            }
            $stmt->close();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $errorMessage = "Error updating allocations: " . $e->getMessage();
        }
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
    <title>Fund Allocation - KEMOS DONATION</title>
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
                <li><a href="charity_donations.php">Donations</a></li>
                <li><a href="charity_allocation.php" class="active">Fund Allocation</a></li>
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
            <h1><i class="fas fa-chart-pie"></i> Fund Allocation</h1>
            <p>Manage how your charity allocates donations and show transparency to donors</p>
        </div>
        
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>
        
        <div class="allocation-overview">
            <div class="allocation-chart-container">
                <canvas id="allocationChart"></canvas>
            </div>
            
            <div class="allocation-summary">
                <h2>Current Allocation</h2>
                <p>Total Donations Received: <strong>KSh <?= number_format($totalDonations, 2) ?></strong></p>
                
                <div class="allocation-list">
                    <?php foreach ($allocations as $allocation): ?>
                        <div class="allocation-item">
                            <div class="allocation-header">
                                <span class="allocation-category"><?= htmlspecialchars($allocation['category']) ?></span>
                                <span class="allocation-percentage"><?= $allocation['percentage'] ?>%</span>
                            </div>
                            <div class="allocation-bar-container">
                                <div class="allocation-bar" style="width: <?= $allocation['percentage'] ?>%"></div>
                            </div>
                            <div class="allocation-amount">
                                KSh <?= number_format(($allocation['percentage'] / 100) * $totalDonations, 2) ?>
                            </div>
                            <div class="allocation-description">
                                <?= htmlspecialchars($allocation['description']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <div class="allocation-form-container">
            <div class="card-header">
                <h2><i class="fas fa-edit"></i> Update Fund Allocation</h2>
                <p>Specify how your charity allocates donations. Total must equal 100%.</p>
            </div>
            
            <form action="charity_allocation.php" method="POST" id="allocationForm">
                <div class="allocation-form-items" id="allocationItems">
                    <?php foreach ($allocations as $index => $allocation): ?>
                        <div class="allocation-form-item">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="category_<?= $index ?>">Category</label>
                                    <input type="text" id="category_<?= $index ?>" name="category[]" class="form-control" value="<?= htmlspecialchars($allocation['category']) ?>" required>
                                </div>
                                <div class="form-group percentage-group">
                                    <label for="percentage_<?= $index ?>">Percentage (%)</label>
                                    <input type="number" id="percentage_<?= $index ?>" name="percentage[]" class="form-control percentage-input" value="<?= $allocation['percentage'] ?>" min="1" max="100" required>
                                </div>
                                <?php if ($index > 0): ?>
                                    <button type="button" class="btn btn-danger remove-allocation"><i class="fas fa-times"></i></button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-primary add-allocation"><i class="fas fa-plus"></i></button>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="description_<?= $index ?>">Description</label>
                                <textarea id="description_<?= $index ?>" name="description[]" class="form-control" rows="2"><?= htmlspecialchars($allocation['description']) ?></textarea>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="allocation-total">
                    <span>Total:</span>
                    <span id="totalPercentage">100%</span>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_allocations" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Allocations
                    </button>
                </div>
            </form>
        </div>
        
        <div class="allocation-tips">
            <h2>Tips for Transparent Fund Allocation</h2>
            <ul>
                <li>
                    <strong>Be specific:</strong> Clearly define each category to help donors understand how their money is used.
                </li>
                <li>
                    <strong>Prioritize programs:</strong> Most donors prefer to see a majority of funds going directly to programs and services.
                </li>
                <li>
                    <strong>Be transparent:</strong> Honesty about administrative costs builds trust with donors.
                </li>
                <li>
                    <strong>Update regularly:</strong> Review and update your allocations quarterly to reflect your charity's current needs.
                </li>
                <li>
                    <strong>Share impact:</strong> Connect allocations to specific outcomes to show donors the impact of their contributions.
                </li>
            </ul>
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
        // Allocation Chart
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
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(255, 205, 86, 0.7)',
                        'rgba(201, 203, 207, 0.7)'
                    ],
                    borderColor: [
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(255, 205, 86, 1)',
                        'rgba(201, 203, 207, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
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
                                const total = <?= $totalDonations ?>;
                                const amount = (value / 100) * total;
                                return `${label}: ${value}% (KSh ${amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})})`;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Add and remove allocation items
        document.addEventListener('DOMContentLoaded', function() {
            const allocationItems = document.getElementById('allocationItems');
            const totalPercentage = document.getElementById('totalPercentage');
            
            // Add new allocation item
            document.querySelector('.add-allocation').addEventListener('click', function() {
                const newItem = document.createElement('div');
                newItem.className = 'allocation-form-item';
                
                const index = document.querySelectorAll('.allocation-form-item').length;
                
                newItem.innerHTML = `
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category_${index}">Category</label>
                            <input type="text" id="category_${index}" name="category[]" class="form-control" required>
                        </div>
                        <div class="form-group percentage-group">
                            <label for="percentage_${index}">Percentage (%)</label>
                            <input type="number" id="percentage_${index}" name="percentage[]" class="form-control percentage-input" value="0" min="1" max="100" required>
                        </div>
                        <button type="button" class="btn btn-danger remove-allocation"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="form-group">
                        <label for="description_${index}">Description</label>
                        <textarea id="description_${index}" name="description[]" class="form-control" rows="2"></textarea>
                    </div>
                `;
                
                allocationItems.appendChild(newItem);
                updateTotalPercentage();
                
                // Add event listener to the new remove button
                newItem.querySelector('.remove-allocation').addEventListener('click', function() {
                    newItem.remove();
                    updateTotalPercentage();
                });
                
                // Add event listener to the new percentage input
                newItem.querySelector('.percentage-input').addEventListener('input', updateTotalPercentage);
            });
            
            // Remove allocation item
            document.querySelectorAll('.remove-allocation').forEach(button => {
                button.addEventListener('click', function() {
                    this.closest('.allocation-form-item').remove();
                    updateTotalPercentage();
                });
            });
            
            // Update total percentage
            function updateTotalPercentage() {
                const percentageInputs = document.querySelectorAll('.percentage-input');
                let total = 0;
                
                percentageInputs.forEach(input => {
                    total += parseInt(input.value) || 0;
                });
                
                totalPercentage.textContent = total + '%';
                
                if (total === 100) {
                    totalPercentage.className = 'valid';
                } else {
                    totalPercentage.className = 'invalid';
                }
            }
            
            // Add event listeners to all percentage inputs
            document.querySelectorAll('.percentage-input').forEach(input => {
                input.addEventListener('input', updateTotalPercentage);
            });
            
            // Validate form before submission
            document.getElementById('allocationForm').addEventListener('submit', function(e) {
                const percentageInputs = document.querySelectorAll('.percentage-input');
                let total = 0;
                
                percentageInputs.forEach(input => {
                    total += parseInt(input.value) || 0;
                });
                
                if (total !== 100) {
                    e.preventDefault();
                    alert('Total allocation percentage must equal 100%. Current total: ' + total + '%');
                }
            });
        });
    </script>
</body>
</html>


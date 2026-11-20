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

// Check license status and expiry
$licenseActive = ($licenseStatus === 'active' && (!$licenseExpiry || strtotime($licenseExpiry) > time()));
$daysRemaining = $licenseExpiry ? ceil((strtotime($licenseExpiry) - time()) / (60 * 60 * 24)) : 0;

// Get license purchase history
$stmt = $conn->prepare("SELECT * FROM charity_licenses WHERE charity_id = ? ORDER BY purchase_date DESC");
$stmt->bind_param("i", $charityId);
$stmt->execute();
$licenseHistory = $stmt->get_result();
$stmt->close();

// Process license purchase
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_license'])) {
    $licenseType = $_POST['license_type'];
    $paymentMethod = $_POST['payment_method'];
    
    // Determine license duration and price
    $duration = 0;
    $price = 0;
    
    switch ($licenseType) {
        case 'monthly':
            $duration = 30; // days
            $price = 2500;
            break;
        case 'quarterly':
            $duration = 90; // days
            $price = 6000;
            break;
        case 'annual':
            $duration = 365; // days
            $price = 20000;
            break;
        default:
            $errorMessage = "Invalid license type selected.";
    }
    
    if (empty($errorMessage)) {
        // In a real application, you would process the payment here
        // For this example, we'll simulate a successful payment
        
        // Calculate new expiry date
        $newExpiry = date('Y-m-d H:i:s', strtotime('+' . $duration . ' days'));
        
        // If there's an existing active license, extend it
        if ($licenseActive) {
            $newExpiry = date('Y-m-d H:i:s', strtotime($licenseExpiry . ' +' . $duration . ' days'));
        }
        
        // Insert license purchase record
        $transactionId = 'LIC' . time() . rand(1000, 9999);
        $stmt = $conn->prepare("INSERT INTO charity_licenses (charity_id, license_type, duration_days, amount, transaction_id, payment_method, purchase_date, expiry_date) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->bind_param("isiisss", $charityId, $licenseType, $duration, $price, $transactionId, $paymentMethod, $newExpiry);
        
        if ($stmt->execute()) {
            // Update charity license status
            $updateStmt = $conn->prepare("UPDATE charity_users SET license_status = 'active', license_expiry = ? WHERE id = ?");
            $updateStmt->bind_param("si", $newExpiry, $charityId);
            
            if ($updateStmt->execute()) {
                // Update session variables
                $_SESSION['license_status'] = 'active';
                $_SESSION['license_expiry'] = $newExpiry;
                
                $successMessage = "License purchased successfully! Your charity is now visible to donors until " . date('F d, Y', strtotime($newExpiry)) . ".";
                
                // Refresh license status
                $licenseActive = true;
                $licenseExpiry = $newExpiry;
                $daysRemaining = ceil((strtotime($licenseExpiry) - time()) / (60 * 60 * 24));
            } else {
                $errorMessage = "Error updating license status: " . $updateStmt->error;
            }
            $updateStmt->close();
        } else {
            $errorMessage = "Error processing license purchase: " . $stmt->error;
        }
        $stmt->close();
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
    <title>Manage Licenses - KEMOS DONATION</title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="charity.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <li><a href="charity_licenses.php" class="active">Licenses</a></li>
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
            <h1><i class="fas fa-certificate"></i> Manage Licenses</h1>
            <p>Purchase and manage licenses to showcase your charity on our platform</p>
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
        
        <div class="license-status-card <?= $licenseActive ? 'active' : 'inactive' ?>">
            <div class="license-status-icon">
                <i class="fas fa-<?= $licenseActive ? 'check-circle' : 'times-circle' ?>"></i>
            </div>
            <div class="license-status-info">
                <h2>License Status: <?= $licenseActive ? 'Active' : 'Inactive' ?></h2>
                <?php if ($licenseActive): ?>
                    <p>Your charity is visible to donors until <?= date('F d, Y', strtotime($licenseExpiry)) ?> (<?= $daysRemaining ?> days remaining)</p>
                <?php else: ?>
                    <p>Your charity is not visible to donors. Purchase a license to showcase your charity.</p>
                <?php endif; ?>
            </div>
            <?php if ($licenseActive): ?>
                <div class="license-status-action">
                    <a href="#purchase" class="btn btn-primary">Extend License</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="license-grid">
            <div class="license-purchase-card" id="purchase">
                <div class="card-header">
                    <h2><i class="fas fa-shopping-cart"></i> Purchase License</h2>
                </div>
                <div class="card-body">
                    <p class="license-info">
                        Purchasing a license makes your charity visible to donors on our platform. 
                        Choose a license duration that suits your needs.
                    </p>
                    
                    <form action="charity_licenses.php" method="POST" class="license-form">
                        <div class="license-options">
                            <div class="license-option">
                                <input type="radio" id="monthly" name="license_type" value="monthly" checked>
                                <label for="monthly" class="license-label">
                                    <div class="license-name">Monthly</div>
                                    <div class="license-price">KSh 2,500</div>
                                    <div class="license-duration">30 days</div>
                                </label>
                            </div>
                            
                            <div class="license-option">
                                <input type="radio" id="quarterly" name="license_type" value="quarterly">
                                <label for="quarterly" class="license-label">
                                    <div class="license-name">Quarterly</div>
                                    <div class="license-price">KSh 6,000</div>
                                    <div class="license-duration">90 days</div>
                                    <div class="license-save">Save 20%</div>
                                </label>
                            </div>
                            
                            <div class="license-option">
                                <input type="radio" id="annual" name="license_type" value="annual">
                                <label for="annual" class="license-label">
                                    <div class="license-name">Annual</div>
                                    <div class="license-price">KSh 20,000</div>
                                    <div class="license-duration">365 days</div>
                                    <div class="license-save">Save 33%</div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_method">Payment Method</label>
                            <select id="payment_method" name="payment_method" class="form-control" required>
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit_card">Credit Card</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="purchase_license" class="btn btn-primary btn-block">
                                <i class="fas fa-shopping-cart"></i> Purchase License
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="license-history-card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> License History</h2>
                </div>
                <div class="card-body">
                    <?php if ($licenseHistory->num_rows > 0): ?>
                        <div class="license-history-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Duration</th>
                                        <th>Amount</th>
                                        <th>Expiry</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($license = $licenseHistory->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($license['purchase_date'])) ?></td>
                                            <td><?= ucfirst($license['license_type']) ?></td>
                                            <td><?= $license['duration_days'] ?> days</td>
                                            <td>KSh <?= number_format($license['amount'], 2) ?></td>
                                            <td><?= date('M d, Y', strtotime($license['expiry_date'])) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="no-data-message">
                            <i class="fas fa-info-circle"></i>
                            <p>No license purchase history found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="license-benefits">
            <h2>Benefits of Having a License</h2>
            <div class="benefits-grid">
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fas fa-eye"></i></div>
                    <h3>Increased Visibility</h3>
                    <p>Your charity will be visible to thousands of potential donors on our platform.</p>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fas fa-hand-holding-usd"></i></div>
                    <h3>More Donations</h3>
                    <p>Licensed charities receive up to 5x more donations than unlicensed ones.</p>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Detailed Analytics</h3>
                    <p>Access comprehensive donation analytics and donor demographics.</p>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fas fa-certificate"></i></div>
                    <h3>Verified Badge</h3>
                    <p>Get a verified badge that builds trust with potential donors.</p>
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
        // License option selection
        const licenseOptions = document.querySelectorAll('.license-option input');
        licenseOptions.forEach(option => {
            option.addEventListener('change', function() {
                // Remove selected class from all options
                document.querySelectorAll('.license-option label').forEach(label => {
                    label.classList.remove('selected');
                });
                
                // Add selected class to the chosen option
                if (this.checked) {
                    this.parentElement.querySelector('label').classList.add('selected');
                }
            });
        });
        
        // Trigger change event on the checked option to set initial state
        document.querySelector('.license-option input:checked').dispatchEvent(new Event('change'));
    </script>
</body>
</html>


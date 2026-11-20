<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Africa/Nairobi');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?redirect=donation_tracker.php");
    exit();
}

// Get user information
$userId = $_SESSION['user_id'];
$userName = $_SESSION['first_name'] ?? 'User';
$userEmail = $_SESSION['email'] ?? '';

// Check for donation success message
$showSuccessMessage = isset($_GET['donation']) && $_GET['donation'] === 'success';
$donationAmount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$donationCharityId = isset($_GET['charity_id']) ? intval($_GET['charity_id']) : 0;
$donationTransactionId = isset($_GET['transaction_id']) ? $_GET['transaction_id'] : '';

// Database connection
$mysqli = new mysqli("localhost", "root", "", "kemos_donation");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Function to query M-Pesa STK Push status
function queryMpesaTransactionStatus($mysqli, $checkoutRequestID) {
    $consumerKey = 'q5Y5vo4AzJJyAttdzm8eDogTSmigQNDw4KumPuIOwyVY22ig';
    $consumerSecret = 'csaIkHXzLI6hj1T1ecLjxxjIdZPWaFsG8mOWIyE47nVlGMGocg2IELGM1R15RWZT';
    $BusinessShortCode = '174379';
    $PassKey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
    $Timestamp = date('YmdHis');
    $Password = base64_encode($BusinessShortCode . $PassKey . $Timestamp);

    $accessTokenURL = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $stkQueryURL = 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query';

    // Get access token
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    $ch = curl_init($accessTokenURL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic $credentials"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode != 200) {
        error_log("Access token request failed: " . $response);
        return false;
    }
    $responseData = json_decode($response, true);
    curl_close($ch);

    $accessToken = $responseData['access_token'] ?? '';
    if (!$accessToken) {
        error_log("Failed to obtain access token: " . json_encode($responseData));
        return false;
    }

    // Query STK Push status
    $queryData = [
        'BusinessShortCode' => $BusinessShortCode,
        'Password' => $Password,
        'Timestamp' => $Timestamp,
        'CheckoutRequestID' => $checkoutRequestID
    ];

    $ch = curl_init($stkQueryURL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($queryData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode != 200) {
        error_log("STK Query failed: " . $response);
        return false;
    }

    $responseData = json_decode($response, true);
    curl_close($ch);

    $resultCode = $responseData['ResultCode'] ?? null;
    $resultDesc = $responseData['ResultDesc'] ?? 'Unknown error';
    $mpesaReceipt = $responseData['MpesaReceiptNumber'] ?? null;

    if ($resultCode === null) {
        error_log("Invalid STK Query response: " . json_encode($responseData));
        return false;
    }

    $status = ($resultCode == 0) ? 'completed' : 'failed';
    $notes = $resultDesc;

    // Update the transaction
    $stmt = $mysqli->prepare("UPDATE transactions 
                             SET status = ?, mpesa_receipt = ?, notes = ?, updated_at = NOW() 
                             WHERE checkout_request_id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $mysqli->error);
        return false;
    }
    $stmt->bind_param("ssss", $status, $mpesaReceipt, $notes, $checkoutRequestID);
    if (!$stmt->execute()) {
        error_log("Update failed: " . $stmt->error);
        return false;
    }
    $stmt->close();

    error_log("STK Query updated transaction: CheckoutRequestID=$checkoutRequestID, Status=$status, MpesaReceipt=$mpesaReceipt");
    return $status == 'completed';
}

// Check pending transactions before cleanup
$pendingTransactions = $mysqli->query("SELECT checkout_request_id 
                                      FROM transactions 
                                      WHERE status = 'pending' 
                                      AND created_at < NOW() - INTERVAL 10 MINUTE");
while ($row = $pendingTransactions->fetch_assoc()) {
    $checkoutRequestID = $row['checkout_request_id'];
    queryMpesaTransactionStatus($mysqli, $checkoutRequestID);
}

// Clean up old pending transactions (after querying M-Pesa)
$mysqli->query("UPDATE transactions 
                SET status = 'failed', notes = 'Payment timed out' 
                WHERE status = 'pending' 
                AND created_at < NOW() - INTERVAL 10 MINUTE");

// Fetch charities (only IDs 2, 3, 4 as per your database)
$query = "SELECT id, charity_name, description FROM charities WHERE id IN (2, 3, 4) ORDER BY id ASC";
$charities = $mysqli->query($query);
if (!$charities) {
    die("Query failed: " . $mysqli->error);
}

// Function to get total donations for a charity by the user
function getUserTotalDonations($mysqli, $charity_id, $donor_name) {
    $query = "SELECT COALESCE(SUM(amount), 0) AS total 
              FROM transactions 
              WHERE charity_id = ? AND donor_name = ? AND status = 'completed'";
    $stmt = $mysqli->prepare($query);
    if (!$stmt) die("Prepare failed: " . $mysqli->error);
    $stmt->bind_param("is", $charity_id, $donor_name);
    if (!$stmt->execute()) die("Execution failed: " . $stmt->error);
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    return $data['total'] ?? 0;
}

// Function to get user donations for a specific charity
function getUserDonations($mysqli, $charity_id, $donor_name, $includePending = false) {
    $query = "SELECT amount, created_at AS donation_date, status, transaction_id 
              FROM transactions 
              WHERE charity_id = ? AND donor_name = ? 
              AND (status = 'completed' OR status = 'failed'" . ($includePending ? " OR status = 'pending'" : "") . ") 
              ORDER BY created_at DESC";
    $stmt = $mysqli->prepare($query);
    if (!$stmt) die("Prepare failed: " . $mysqli->error);
    $stmt->bind_param("is", $charity_id, $donor_name);
    if (!$stmt->execute()) die("Execution failed: " . $stmt->error);
    return $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Donations - KEMOS DONATION</title>
    <link rel="stylesheet" href="donation_tracker.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">KEMOS DONATION</div>
            <ul class="nav-links">
                <li><a href="user_page.php">Home</a></li>
                <li><a href="donation_tracker.php" class="active">My Donations</a></li>
                <li><a href="donation_usage_dashboard.php">Impact Dashboard</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="tracker-container">
            <div class="user-profile-header">
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-info">
                    <h2><?= htmlspecialchars($userName) ?>'s Donations</h2>
                    <p><?= htmlspecialchars($userEmail) ?></p>
                </div>
            </div>
            
            <?php if ($showSuccessMessage): ?>
            <div class="success-message">
                <h3><i class="fas fa-check-circle"></i> Donation Initiated!</h3>
                <p>Thank you for initiating a donation of Ksh <?= number_format($donationAmount, 2) ?>. Please check your phone to complete the M-Pesa payment.</p>
            </div>
            <?php endif; ?>
            
            <div class="donation-accordion">
                <?php if ($charities->num_rows > 0): ?>
                    <?php $charities->data_seek(0); ?>
                    <?php while($charity = $charities->fetch_assoc()): ?>
                        <?php 
                            error_log("Charity ID: " . $charity['id'] . ", Name: " . $charity['charity_name']);
                            $userTotalDonated = getUserTotalDonations($mysqli, $charity['id'], $userName);
                            $includePending = $showSuccessMessage && $donationCharityId == $charity['id'];
                            $userDonations = getUserDonations($mysqli, $charity['id'], $userName, $includePending);
                        ?>
                        <div class="accordion-item" data-charity-id="<?= $charity['id'] ?>">
                            <div class="accordion-header">
                                <h3><?= htmlspecialchars($charity['charity_name']) ?> (ID: <?= $charity['id'] ?>)</h3>
                                <div class="donation-amount">
                                    <span class="currency">KSh</span>
                                    <span class="amount"><?= number_format($userTotalDonated, 2) ?></span>
                                    <span class="toggle-icon">+</span>
                                </div>
                            </div>
                            <div class="accordion-content">
                                <div class="charity-description">
                                    <h4>About this charity</h4>
                                    <p><?= htmlspecialchars($charity['description'] ?? 'This charity aims to help those in need through various community programs and initiatives.') ?></p>
                                </div>
                                
                                <div class="user-donations-section">
                                    <h4>Your Donations</h4>
                                    <?php if ($userDonations->num_rows > 0): ?>
                                        <div class="donation-list">
                                            <?php while($donation = $userDonations->fetch_assoc()): ?>
                                                <div class="donation-item">
                                                    <div class="donation-info">
                                                        <span class="donation-date">
                                                            <i class="far fa-calendar-alt"></i> 
                                                            <?= date('M d, Y', strtotime($donation['donation_date'])) ?> <span class="current-time"><?= date('h:i A') ?></span>
                                                        </span>
                                                        <span class="donation-status <?= strtolower($donation['status']) ?>">
                                                            <?= ucfirst(htmlspecialchars($donation['status'])) ?>
                                                        </span>
                                                    </div>
                                                    <div class="donation-details">
                                                        <span class="donation-amount">KSh <?= number_format($donation['amount'], 2) ?></span>
                                                        <span class="payment-method">M-Pesa</span>
                                                    </div>
                                                    <div class="donation-transaction">
                                                        <small>Transaction ID: <?= htmlspecialchars($donation['transaction_id']) ?></small>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="no-donations">You haven't made any donations to this charity yet.</p>
                                    <?php endif; ?>
                                    
                                    <div class="donate-button-container">
                                        <a href="donate.php?charity_id=<?= $charity['id'] ?>" class="donate-button">
                                            <i class="fas fa-heart"></i> Donate Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="no-charities">No charities found in the system.</p>
                <?php endif; ?>
            </div>
            
            <div class="donation-summary">
                <h3>Donation Summary</h3>
                <div class="summary-stats">
                    <?php
                    $totalDonated = 0;
                    $totalTransactions = 0;
                    $charities->data_seek(0);
                    while($charity = $charities->fetch_assoc()) {
                        $totalDonated += getUserTotalDonations($mysqli, $charity['id'], $userName);
                        $userDonations = getUserDonations($mysqli, $charity['id'], $userName);
                        $totalTransactions += $userDonations->num_rows;
                    }
                    ?>
                    <div class="stat-item">
                        <div class="stat-value">KSh <?= number_format($totalDonated, 2) ?></div>
                        <div class="stat-label">Total Donated</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $totalTransactions ?></div>
                        <div class="stat-label">Transactions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $charities->num_rows ?></div>
                        <div class="stat-label">Charities</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const accordionItems = document.querySelectorAll('.accordion-item');
        
        accordionItems.forEach(item => {
            const header = item.querySelector('.accordion-header');
            const content = item.querySelector('.accordion-content');
            
            header.addEventListener('click', () => {
                item.classList.toggle('active');
                content.style.maxHeight = item.classList.contains('active') ? content.scrollHeight + 'px' : 0;
                
                accordionItems.forEach(otherItem => {
                    if (otherItem !== item && otherItem.classList.contains('active')) {
                        otherItem.classList.remove('active');
                        otherItem.querySelector('.accordion-content').style.maxHeight = 0;
                    }
                });
            });
        });
        
        <?php if ($showSuccessMessage && $donationCharityId): ?>
        const targetCharity = document.querySelector(`.accordion-item[data-charity-id="<?= $donationCharityId ?>"]`);
        if (targetCharity) {
            targetCharity.classList.add('active');
            targetCharity.querySelector('.accordion-content').style.maxHeight = 
                targetCharity.querySelector('.accordion-content').scrollHeight + 'px';
            targetCharity.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        <?php endif; ?>
        
        const amounts = document.querySelectorAll('.amount');
        amounts.forEach((amount) => {
            const targetValue = parseFloat(amount.textContent.replace(/,/g, ''));
            animateValue(amount, 0, targetValue, 1500);
        });
        
        function animateValue(element, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const currentValue = progress * (end - start) + start;
                element.textContent = numberWithCommas(currentValue.toFixed(2));
                if (progress < 1) window.requestAnimationFrame(step);
            };
            window.requestAnimationFrame(step);
        }
        
        function numberWithCommas(x) {
            return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
    });
    </script>
</body>
</html>
<?php $mysqli->close(); ?>


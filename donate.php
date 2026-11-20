<?php
// Start session to access session variables
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "kemos_donation";

$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if transactions table exists, if not create it
$check_table = $conn->query("SHOW TABLES LIKE 'transactions'");
if ($check_table->num_rows == 0) {
    // Table doesn't exist, create it
    $create_table_sql = "CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        donor_name VARCHAR(100) NOT NULL,
        phone_number VARCHAR(15) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        transaction_id VARCHAR(50) NOT NULL,
        checkout_request_id VARCHAR(50) NULL,
        mpesa_receipt VARCHAR(50) NULL,
        status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
        notes TEXT NULL,
        charity_id INT NULL,
        created_at DATETIME NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $conn->query($create_table_sql);
}

// Initialize variables to avoid undefined variable error
$userProfilePic = null;
$profileImageUrl = null;
$userId = null;
$successMessage = null;
$success = false;

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

if ($isLoggedIn) {
    $userName = $_SESSION['name'];
    $userEmail = $_SESSION['email'];
    $userRole = $_SESSION['role'] ?? 'user';
    $userId = $_SESSION['user_id'];

    // Get user profile data if available
    try {
        // Connect to users_db to get profile info
        $conn_users = new mysqli("localhost", "root", "", "kemos_donation");
        if (!$conn_users->connect_error) {
            $stmt = $conn_users->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $userData = $result->fetch_assoc();
                $userProfilePic = $userData['profile_pic'] ?? null;
            }
            $stmt->close();
            $conn_users->close();
        }
    } catch (Exception $e) {
        // Silently handle any errors
    }

    $profileImageUrl = $userProfilePic ? "uploads/profiles/" . $userProfilePic : 
                      "https://ui-avatars.com/api/?name=" . urlencode($userName) . "&background=0052cc&color=fff";
} else {
    $userName = 'Guest';
}

// Function to initiate M-Pesa STK Push
function initiateMpesaSTKPush($phone_number, $amount) {
    // Updated M-PESA API credentials
    $consumerKey = 'q5Y5vo4AzJJyAttdzm8eDogTSmigQNDw4KumPuIOwyVY22ig';
    $consumerSecret = 'csaIkHXzLI6hj1T1ecLjxxjIdZPWaFsG8mOWIyE47nVlGMGocg2IELGM1R15RWZT';
    $BusinessShortCode = '174379';
    $PassKey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
    $Timestamp = date('YmdHis');
    $Password = base64_encode($BusinessShortCode . $PassKey . $Timestamp);
    
    // Format phone number (ensure it starts with 254)
    if (substr($phone_number, 0, 1) == '0') {
        $phone_number = '254' . substr($phone_number, 1);
    } elseif (substr($phone_number, 0, 3) != '254') {
        $phone_number = '254' . $phone_number;
    }
    
    // Set callback URL - update this with your actual callback URL
    $CallBackURL = "https://aa30-2c0f-fe38-2038-fce7-6182-6830-8dca-a253.ngrok-free.app/mpesa_callback.php";
    
    $accessTokenURL = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $stkPushURL = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

    // Get access token
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    $ch = curl_init($accessTokenURL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic $credentials"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode != 200) {
        error_log("Access token request failed with HTTP code: " . $httpCode);
        error_log("Response: " . $response);
        return ['success' => false, 'message' => 'Failed to obtain access token', 'http_code' => $httpCode, 'response' => $response];
    }
    
    $responseData = json_decode($response, true);
    curl_close($ch);
    
    $accessToken = $responseData['access_token'] ?? '';
    if (!$accessToken) {
        return ['success' => false, 'message' => 'Failed to obtain access token', 'response' => $responseData];
    }

    // Initiate STK push
    $stkPushData = [
        'BusinessShortCode' => $BusinessShortCode,
        'Password' => $Password,
        'Timestamp' => $Timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => intval($amount),
        'PartyA' => $phone_number,
        'PartyB' => $BusinessShortCode,
        'PhoneNumber' => $phone_number,
        'CallBackURL' => $CallBackURL,
        'AccountReference' => 'Kemos Donation',
        'TransactionDesc' => 'Donation Payment'
    ];

    $ch = curl_init($stkPushURL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken", 
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stkPushData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $stkResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($httpCode != 200) {
        error_log("STK Push request failed with HTTP code: " . $httpCode);
        error_log("Response: " . $stkResponse);
        return ['success' => false, 'message' => 'STK Push request failed', 'http_code' => $httpCode, 'response' => $stkResponse];
    }
    
    $stkResponseData = json_decode($stkResponse, true);
    curl_close($ch);
    
    error_log("M-PESA STK Push Response: " . json_encode($stkResponseData));
    
    return $stkResponseData;
}

// Process M-Pesa donation form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $donor_name = $userName ?? 'Anonymous';
    $amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $phone_number = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $charity = filter_input(INPUT_POST, 'charity', FILTER_SANITIZE_STRING);
    $transactionId = "MPESA" . rand(1000000, 9999999);
    $status = 'pending';
    $created_at = date("Y-m-d H:i:s");
    
    $phonePattern = '/^(?:\+254|0)[17]\d{8}$/';
    $isValid = true;
    $errors = [];
    
    if (!$amount || $amount < 50) {
        $isValid = false;
        $errors['amount'] = "Minimum donation amount is Ksh 50";
    }
    
    if (!$phone_number || !preg_match($phonePattern, $phone_number)) {
        $isValid = false;
        $errors['phone'] = "Please enter a valid Kenyan phone number";
    }
    
    if (!$charity) {
        $isValid = false;
        $errors['charity'] = "Please select a charity";
    }
    
    if ($isValid) {
        // Map charity selection to charity_id (updated IDs)
        $charity_id = null; // No default charity
        if ($charity == 'charityA') $charity_id = 2; // Moraya Childrens Home
        if ($charity == 'charityB') $charity_id = 3; // Bright Future Education
        if ($charity == 'charityC') $charity_id = 4; // Threads of Hope Clothing Center
        
        if ($charity_id === null) {
            $isValid = false;
            $errors['charity'] = "Invalid charity selection";
        } else {
            $mpesaResponse = initiateMpesaSTKPush($phone_number, $amount);
            
            if (isset($mpesaResponse['ResponseCode']) && $mpesaResponse['ResponseCode'] == "0") {
                $checkoutRequestID = $mpesaResponse['CheckoutRequestID'];
                $_SESSION['checkout_request_id'] = $checkoutRequestID;
                
                $stmt = $conn->prepare("INSERT INTO transactions (donor_name, phone_number, amount, transaction_id, checkout_request_id, status, charity_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdsssss", $donor_name, $phone_number, $amount, $transactionId, $checkoutRequestID, $status, $charity_id, $created_at);
                $stmt->execute();
                $stmt->close();

                $success = true;
                $successMessage = "Thank you for your donation of Ksh " . number_format($amount, 2) . "! Please check your phone to complete the payment.";
                
                $charityName = "Charity";
                if ($charity == 'charityA') $charityName = "Moraya Childrens Home";
                if ($charity == 'charityB') $charityName = "Bright Future Education";
                if ($charity == 'charityC') $charityName = "Threads of Hope Clothing Center";
                
                header("Location: thank-you.php?amount=$amount&charity_id=$charity_id&charity=" . urlencode($charityName));
                exit();
            } else {
                $errors['mpesa'] = "M-Pesa transaction failed. Please try again. " . ($mpesaResponse['errorMessage'] ?? 'Unknown error');
                error_log("M-PESA Error: " . json_encode($mpesaResponse));
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donate - KEMOS DONATION</title>
    <link rel="stylesheet" href="donate.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo">
            <img src="/placeholder.svg" alt="">
            KEMOS DONATION
        </div>
        <nav>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="about-us.php">About Us</a></li>
                <li><a href="donate.php" class="active">Donate</a></li>
                <li><a href="contact.php">Contact Us</a></li>
            </ul>
        </nav>
        
        <?php if ($isLoggedIn): ?>
        <div class="user-profile">
            <div class="user-info">
                <span class="username">
                    <?= htmlspecialchars($userName) ?>
                    <span class="user-role"><?= htmlspecialchars(ucfirst($userRole)) ?></span>
                </span>
                <img src="<?= $profileImageUrl ?>" alt="<?= htmlspecialchars($userName) ?>" class="profile-icon">
                
                <div class="user-dropdown">
                    <a href="profile.php" class="user-dropdown-item">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                    <a href="donation_tracker.php" class="user-dropdown-item">
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
        <?php else: ?>
        <div class="auth-buttons">
            <a href="index.php" class="auth-btn login-btn">Login</a>
            <a href="index.php" class="auth-btn register-btn">Register</a>
        </div>
        <?php endif; ?>
    </header>

    <section class="donation-hero">
        <div class="overlay">
            <h1>Your <span>Generosity</span> Makes a Difference</h1>
            <p>Every donation helps us support communities in need and create lasting change.</p>
        </div>
    </section>

    <section class="donation-form-section">
        <div class="container">
            <h2 class="section-title">Make a Donation</h2>
            <div class="donation-container">
                <div class="donation-info">
                    <h3>Why Donate?</h3>
                    <p>Your contribution helps us provide essential services to communities in need:</p>
                    <ul>
                        <li><i class="fas fa-utensils"></i> Food and groceries for families</li>
                        <li><i class="fas fa-tshirt"></i> Clothing and shoes for those in need</li>
                        <li><i class="fas fa-book"></i> Educational materials for students</li>
                        <li><i class="fas fa-hand-holding-medical"></i> Medical supplies and healthcare</li>
                    </ul>
                    <div class="donation-impact">
                        <p>Your donation directly impacts lives:</p>
                        <div class="impact-examples">
                            <div class="impact-example">
                                <span>KSh 500</span>
                                <p>Provides meals for a family for a week</p>
                            </div>
                            <div class="impact-example">
                                <span>KSh 1,000</span>
                                <p>Supplies school materials for 5 children</p>
                            </div>
                            <div class="impact-example">
                                <span>KSh 2,000</span>
                                <p>Funds essential medical supplies</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Updated Lipa na M-Pesa Form -->
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="mpesa-form">
                    <?php if ($success && $successMessage): ?>
                    <div class="success-message">
                        <h3><i class="fas fa-check-circle"></i> Thank You!</h3>
                        <p><?php echo $successMessage; ?></p>
                    </div>
                    <?php else: ?>
                    
                    <div class="mpesa-header">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/1/15/M-PESA_LOGO-01.svg/1200px-M-PESA_LOGO-01.svg.png" alt="M-Pesa Logo" class="mpesa-logo">
                        <h3>Donate via Lipa na M-Pesa</h3>
                        <p>Fast, secure, and convenient way to make your donation</p>
                    </div>
                    
                    <!-- Select Charity (Moved to First) -->
                    <div class="form-group full-width">
                        <label>Select Charity*</label>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="charity1" name="charity" value="charityA" <?php echo (isset($charity) && $charity == 'charityA') ? 'checked' : ''; ?> required>
                                <label for="charity1">
                                    <i class="fas fa-hands-helping fa-2x"></i>
                                    <span>Moraya Childrens Home</span>
                                </label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="charity2" name="charity" value="charityB" <?php echo (isset($charity) && $charity == 'charityB') ? 'checked' : ''; ?>>
                                <label for="charity2">
                                    <i class="fas fa-hand-holding-heart fa-2x"></i>
                                    <span>Bright Future Education</span>
                                </label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="charity3" name="charity" value="charityC" <?php echo (isset($charity) && $charity == 'charityC') ? 'checked' : ''; ?>>
                                <label for="charity3">
                                    <i class="fas fa-hand-holding-usd fa-2x"></i>
                                    <span>Threads of Hope Clothing Center</span>
                                </label>
                            </div>
                        </div>
                        <?php if (isset($errors['charity'])): ?>
                            <span class="error-message"><?php echo $errors['charity']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-divider"></div>
                    
                    <!-- Donation Amount (Moved to Second) -->
                    <div class="form-group full-width">
                        <label for="amount">Donation Amount (KSh)*</label>
                        <input type="number" id="amount" name="amount" min="50" placeholder="Enter amount in KSh (minimum KSh 50)" required value="<?php echo isset($amount) ? $amount : ''; ?>">
                        <span class="min-amount-notice">Minimum donation amount: KSh 50</span>
                        <?php if (isset($errors['amount'])): ?>
                            <span class="error-message"><?php echo $errors['amount']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-divider"></div>
                    
                    <!-- M-Pesa Phone Number (Adjusted Styling) -->
                    <div class="form-group full-width">
                        <label for="phone">M-Pesa Phone Number*</label>
                        <div class="phone-input">
                            <span class="phone-prefix">+254</span>
                            <input type="tel" id="phone" name="phone" placeholder="712345678" required value="<?php echo isset($phone_number) ? $phone_number : ''; ?>">
                        </div>
                        <small>Enter your M-Pesa registered phone number</small>
                        <?php if (isset($errors['phone'])): ?>
                            <span class="error-message"><?php echo $errors['phone']; ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($errors['mpesa'])): ?>
                        <div class="form-group full-width">
                            <span class="error-message"><?php echo $errors['mpesa']; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group full-width">
                        <button type="submit" class="donate-submit-btn">
                            <i class="fas fa-mobile-alt"></i> Pay with M-Pesa
                        </button>
                    </div>
                    
                    <div style="text-align: center; margin-top: 15px; font-size: 12px; color: #666;">
                        <p>You will receive an STK push notification on your phone to complete the payment</p>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </section>

    <section class="donation-faq">
        <div class="container">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <div class="faq-container">
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>How does Lipa na M-Pesa donation work?</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>After submitting your donation details, you'll receive an STK push notification on your phone. Simply enter your M-Pesa PIN to complete the donation. You'll receive an SMS confirmation from M-Pesa and our system.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>What is the minimum donation amount?</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>The minimum donation amount is KSh 50. This helps us cover transaction costs and ensures that your donation makes a meaningful impact.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>Is my donation tax-deductible?</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Yes, KEMOS DONATION is a registered non-profit organization, and all donations are tax-deductible to the extent allowed by law.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>How is my donation used?</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Your donation directly supports our programs providing food, clothing, educational materials, and other essential services to communities in need.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        <h3>Is my payment information secure?</h3>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Yes, we use industry-standard encryption and security measures to protect your personal and payment information. M-Pesa is also a secure payment platform regulated by the Central Bank of Kenya.</p>
                    </div>
                </div>
            </div>
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('donation') === 'success') {
            alert('Your donation was successful! Thank you for your contribution.');
        }
        
        const faqItems = document.querySelectorAll('.faq-item');
        
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            
            question.addEventListener('click', () => {
                item.classList.toggle('active');
                
                faqItems.forEach(otherItem => {
                    if (otherItem !== item && otherItem.classList.contains('active')) {
                        otherItem.classList.remove('active');
                    }
                });
            });
        });
        
        const donationForm = document.querySelector('.mpesa-form');
        
        if (donationForm) {
            donationForm.addEventListener('submit', function(e) {
                let isValid = true;
                
                const amount = document.getElementById('amount');
                if (!amount.value || amount.value < 50) {
                    isValid = false;
                    showError(amount, 'Minimum donation amount is Ksh 50');
                } else {
                    clearError(amount);
                }
                
                const charities = document.querySelectorAll('input[name="charity"]');
                let charitySelected = false;
                charities.forEach(charity => {
                    if (charity.checked) {
                        charitySelected = true;
                    }
                });
                
                if (!charitySelected) {
                    isValid = false;
                    showError(charities[0], 'Please select a charity');
                } else {
                    clearError(charities[0]);
                }
                
                const phone = document.getElementById('phone');
                const phonePattern = /^(?:\+254|0)[17]\d{8}$/;
                
                if (!phone.value || !phonePattern.test(phone.value)) {
                    isValid = false;
                    showError(phone, 'Please enter a valid Kenyan phone number');
                } else {
                    clearError(phone);
                }
                
                if (!isValid) {
                    e.preventDefault();
                }
            });
            
            function showError(input, message) {
                const formGroup = input.closest('.form-group');
                const errorElement = formGroup.querySelector('.error-message') || document.createElement('span');
                
                if (!formGroup.querySelector('.error-message')) {
                    errorElement.className = 'error-message';
                    formGroup.appendChild(errorElement);
                }
                
                errorElement.textContent = message;
                input.classList.add('error');
            }
            
            function clearError(input) {
                const formGroup = input.closest('.form-group');
                const errorElement = formGroup.querySelector('.error-message');
                
                if (errorElement) {
                    errorElement.textContent = '';
                }
                
                input.classList.remove('error');
            }
        }
    });
    </script>
</body>
</html>
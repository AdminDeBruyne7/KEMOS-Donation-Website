<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Database connection
$conn = new mysqli("localhost", "username", "password", "kemos_donation");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get charity information
$charity_id = isset($_GET['charity_id']) ? intval($_GET['charity_id']) : 0;

if ($charity_id <= 0) {
    header("Location: donation_tracker.php");
    exit();
}

$sql = "SELECT * FROM charities WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $charity_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: donation_tracker.php");
    exit();
}

$charity = $result->fetch_assoc();

// Process donation form submission
$donation_success = false;
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_donation'])) {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
    $user_id = $_SESSION['user_id'];
    
    if ($amount <= 0) {
        $error_message = "Please enter a valid donation amount.";
    } else {
        // Insert donation into database
        $sql = "INSERT INTO donations (user_id, charity_id, amount, payment_method, message, donation_date) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iidss", $user_id, $charity_id, $amount, $payment_method, $message);
        
        if ($stmt->execute()) {
            $donation_success = true;
        } else {
            $error_message = "Error processing donation. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KEMOS DONATION - Make a Donation</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">KEMOS DONATION</div>
            <ul class="nav-links">
                <li><a href="user_page.php">Dashboard</a></li>
                <li><a href="donation_tracker.php">Donation Tracker</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <?php if ($donation_success): ?>
            <div class="donation-success">
                <h2>Thank You for Your Donation!</h2>
                <p>Your generous contribution to <?= htmlspecialchars($charity['charity_name']) ?> has been received.</p>
                <div class="success-actions">
                    <a href="donation_tracker.php" class="primary-button">View All Donations</a>
                    <a href="make_donation.php?charity_id=<?= $charity_id ?>" class="secondary-button">Make Another Donation</a>
                </div>
            </div>
        <?php else: ?>
            <div class="donation-form-container">
                <h1>Make a Donation</h1>
                <h2>Supporting: <?= htmlspecialchars($charity['charity_name']) ?></h2>
                
                <?php if (!empty($error_message)): ?>
                    <p class="error-message"><?= $error_message ?></p>
                <?php endif; ?>
                
                <div class="charity-info">
                    <p><?= htmlspecialchars($charity['description']) ?></p>
                </div>
                
                <form action="" method="post" class="donation-form">
                    <div class="form-group">
                        <label for="amount">Donation Amount ($)</label>
                        <input type="number" id="amount" name="amount" min="1" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">Select Payment Method</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="PayPal">PayPal</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cryptocurrency">Cryptocurrency</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message (Optional)</label>
                        <textarea id="message" name="message" rows="3"></textarea>
                    </div>
                    
                    <button type="submit" name="submit_donation" class="donate-button">Complete Donation</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$conn->close();
?>


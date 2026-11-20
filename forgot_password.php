<?php
session_start();

// Initialize variables
$error = '';
$success = '';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the email from the form
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Here you would typically:
        // 1. Check if the email exists in your database
        // 2. Generate a unique token and store it with the user's record
        // 3. Create a reset link with the token
        // 4. Send the email with the reset link
        
        // For demonstration purposes, we'll just show a success message
        // In a real implementation, you would connect to your database and send an actual email
        
        // Example of what the actual code might look like:
        /*
        // Connect to database
        $conn = new mysqli($servername, $username, $password, $dbname);
        
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Generate token
            $token = bin2hex(random_bytes(50));
            
            // Store token in database with expiration
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE email = ?");
            $stmt->bind_param("sss", $token, $expiry, $email);
            $stmt->execute();
            
            // Create reset link
            $resetLink = "https://yourdomain.com/reset_password.php?token=" . $token;
            
            // Send email
            $to = $email;
            $subject = "Password Reset for KEMOS DONATION";
            $message = "Click the following link to reset your password: " . $resetLink;
            $headers = "From: noreply@kemos-donation.com";
            
            mail($to, $subject, $message, $headers);
        }
        */
        
        $success = "If your email exists in our system, you will receive a password reset link shortly.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KEMOS DONATION - Forgot Password</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="forgot_password.css">
</head>

<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">KEMOS DONATION</div>
            <ul class="nav-links">
                <li><a href="index.php">Login</a></li>
                <li><a href="index.php">Register</a></li>
                <li><a href="index.php">Charity Registration</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="form-box active" id="forgot-password-form">
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-header">
                    <h1>KEMOS DONATION</h1>
                    <h2>Forgot Password</h2>
                </div>
                
                <?php if (!empty($error)): ?>
                    <p class="error-message"><?php echo $error; ?></p>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <p class="success-message"><?php echo $success; ?></p>
                <?php else: ?>
                    <p class="instruction">Enter your email address below and we'll send you a link to reset your password.</p>
                    
                    <input type="email" name="email" placeholder="Email" required>
                    
                    <button type="submit" name="reset_password">Send Reset Link</button>
                <?php endif; ?>
                
                <p>Remember your password? <a href="index.php">Back to Login</a></p>
            </form>
        </div>
    </div>

    <script>
        // If there's a success message, disable the form
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.querySelector('.success-message');
            const form = document.querySelector('form');
            
            if (successMessage && form) {
                const inputs = form.querySelectorAll('input');
                const button = form.querySelector('button');
                
                if (inputs.length > 0 && button) {
                    inputs.forEach(input => {
                        input.style.display = 'none';
                    });
                    button.style.display = 'none';
                }
            }
        });
    </script>
</body>

</html>


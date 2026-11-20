<?php
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: user_page.php");
    }
    exit();
}

// Database connection
$conn_users = null;
$register_error = '';
$register_success = '';

// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    // Connect to users database
    $conn_users = new mysqli("localhost", "root", "", "kemos_donation");
    
    if ($conn_users->connect_error) {
        die("Connection failed: " . $conn_users->connect_error);
    }
    
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $age = filter_input(INPUT_POST, 'age', FILTER_VALIDATE_INT);
    
    // Validate input
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $register_error = "Please fill in all required fields";
    } elseif ($password !== $confirm_password) {
        $register_error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $register_error = "Password must be at least 6 characters long";
    } else {
        // Check if email already exists
        $stmt = $conn_users->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $register_error = "Email already exists";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'user'; // Default role
            
            // Insert new user
            $stmt = $conn_users->prepare("INSERT INTO users (name, email, password, age, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssis", $name, $email, $hashed_password, $age, $role);
            
            if ($stmt->execute()) {
                $register_success = "Registration successful! You can now login.";
            } else {
                $register_error = "Error registering user: " . $conn_users->error;
            }
        }
        
        $stmt->close();
    }
    
    $conn_users->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - KEMOS DONATION</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .register-container {
            max-width: 600px;
            margin: 50px auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header h1 {
            color: var(--primary-color);
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .register-header p {
            color: #666;
            font-size: 16px;
        }
        
        .register-form {
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', Arial, sans-serif;
            font-size: 14px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .form-actions {
            margin-top: 30px;
        }
        
        .register-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Poppins', Arial, sans-serif;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .register-btn:hover {
            background-color: var(--dark-blue);
        }
        
        .register-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: #666;
        }
        
        .register-footer a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .register-footer a:hover {
            text-decoration: underline;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <img src="/placeholder.svg" alt="KEMOS DONATION Logo">
            KEMOS DONATION
        </div>
        <nav>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="about-us.php">About Us</a></li>
                <li><a href="donate.php">Donate</a></li>
                <li><a href="contact.php">Contact Us</a></li>
            </ul>
        </nav>
        
        <div class="auth-buttons">
            <a href="index.php" class="auth-btn login-btn">Login</a>
            <a href="register.php" class="auth-btn register-btn">Register</a>
        </div>
    </header>

    <div class="register-container">
        <div class="register-header">
            <h1>Create an Account</h1>
            <p>Join KEMOS DONATION and start making a difference today</p>
        </div>
        
        <?php if (!empty($register_error)): ?>
            <div class="error-message">
                <?php echo $register_error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($register_success)): ?>
            <div class="success-message">
                <?php echo $register_success; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="register-form">
            <div class="form-group">
                <label for="name">Full Name *</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password *</label>
                <input type="password" id="password" name="password" required>
                <small>Password must be at least 6 characters long</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <div class="form-group">
                <label for="age">Age</label>
                <input type="number" id="age" name="age" min="0">
            </div>
            
            <div class="form-actions">
                <button type="submit" name="register" class="register-btn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </div>
        </form>
        
        <div class="register-footer">
            <p>Already have an account? <a href="index.php">Login</a></p>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>About KEMOS</h3>
                <p>We're on a mission to transform how people give and make a positive impact in communities worldwide.</p>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about-us.php">About Us</a></li>
                    <li><a href="donate.php">Donate</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="admin_login.php">Admin Portal</a></li>
                </ul>
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
</body>
</html>


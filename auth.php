<?php
session_start();
require_once 'config.php'; // Database connection

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle user registration
if (isset($_POST['register'])) {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $middle_name = mysqli_real_escape_string($conn, $_POST['middle_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $Date_of_Birth = mysqli_real_escape_string($conn, $_POST['dob']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = mysqli_real_escape_string($conn, $_POST['role']);

    // Use prepared statement to prevent SQL injection
    $checkEmailStmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $checkEmailStmt->bind_param("s", $email);
    $checkEmailStmt->execute();
    $checkEmailResult = $checkEmailStmt->get_result();
    
    if ($checkEmailResult->num_rows > 0) {
        $_SESSION['register_error'] = 'Email is already registered';
        $_SESSION['active_form'] = 'register';
    } else {
        // Use prepared statement for insert
        $insertStmt = $conn->prepare("INSERT INTO users (first_name, middle_name, last_name, email, Date_of_Birth, password, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->bind_param("sssssss", $first_name, $middle_name, $last_name, $email, $Date_of_Birth, $password, $role);
        
        if ($insertStmt->execute()) {
            $_SESSION['success_message'] = 'User registration successful! Please log in to access your account.';
            setcookie('success_message', 'User registration successful! Please log in to access your account.', 0, '/');
        } else {
            $_SESSION['register_error'] = 'Registration failed: ' . $conn->error;
            $_SESSION['active_form'] = 'register';
        }
    }

    header("Location: index.php");
    exit();
}

// Handle charity registration
if (isset($_POST['charity_register'])) {
    $charity_name = mysqli_real_escape_string($conn, $_POST['charity_name']);
    $country = mysqli_real_escape_string($conn, $_POST['country']);
    $city = mysqli_real_escape_string($conn, $_POST['city']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person']);
    $contact_email = mysqli_real_escape_string($conn, $_POST['contact_email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $role = 'charity';

    // Check if email already exists
    $checkEmailStmt = $conn->prepare("SELECT email FROM charities WHERE email = ?");
    $checkEmailStmt->bind_param("s", $email);
    $checkEmailStmt->execute();
    $checkEmailResult = $checkEmailStmt->get_result();
    
    if ($checkEmailResult->num_rows > 0) {
        $_SESSION['charity_error'] = 'Email is already registered';
        $_SESSION['active_form'] = 'charity';
    } else {
        // Insert the charity data
        $insertStmt = $conn->prepare("INSERT INTO charities (charity_name, country, city, location, email, contact_person, contact_email, phone, password, description, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $insertStmt->bind_param("sssssssssss", $charity_name, $country, $city, $location, $email, $contact_person, $contact_email, $phone, $password, $description, $role);
        
        if ($insertStmt->execute()) {
            $_SESSION['success_message'] = 'Charity registration successful! Please log in to manage your charity profile.';
            setcookie('success_message', 'Charity registration successful! Please log in to manage your charity profile.', 0, '/');
        } else {
            $_SESSION['charity_error'] = 'Registration failed: ' . $conn->error;
            $_SESSION['active_form'] = 'charity';
        }
    }

    header("Location: index.php");
    exit();
}

// Handle login
if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Debug: Log the email being checked
    error_log("Login attempt with email: $email");

    // Check users table first
    $loginStmt = $conn->prepare("SELECT id, first_name, email, password, role FROM users WHERE email = ?");
    $loginStmt->bind_param("s", $email);
    $loginStmt->execute();
    $result = $loginStmt->get_result();
    $user = $result->fetch_assoc();

    // Debug: Log if user was found in users table
    if ($user) {
        error_log("User found in users table: " . json_encode($user));
    } else {
        error_log("No user found in users table with email: $email");
    }

    // If not found in users, check charities table
    if (!$user) {
        $loginStmt = $conn->prepare("SELECT id, charity_name, email, password, role FROM charities WHERE email = ?");
        $loginStmt->bind_param("s", $email);
        $loginStmt->execute();
        $result = $loginStmt->get_result();
        $user = $result->fetch_assoc();

        // Debug: Log if user was found in charities table
        if ($user) {
            error_log("User found in charities table: " . json_encode($user));
        } else {
            error_log("No user found in charities table with email: $email");
        }
    }

    // Verify password and proceed
    if ($user) {
        // Debug: Log the stored hashed password
        error_log("Stored hashed password: " . $user['password']);

        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['first_name'] ?? $user['charity_name'] ?? 'User';
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            // Debug: Log the role and session data
            error_log("User role: " . $user['role']);
            error_log("Session data: " . json_encode($_SESSION));

            // Set login success message
            setcookie('success_message', 'Login successful! Welcome back, ' . ($_SESSION['name'] ?? 'User') . '.', 0, '/');

            // Redirect based on role (case-insensitive comparison)
            $role = strtolower($user['role']);
            if ($role === 'admin') {
                error_log("Redirecting to admin_charities.php");
                header("Location: admin_charities.php");
            } elseif ($role === 'charity') {
                error_log("Redirecting to charity_dashboard.php");
                header("Location: charity_dashboard.php");
            } else {
                error_log("Redirecting to user_page.php");
                header("Location: user_page.php");
            }
            exit();
        } else {
            error_log("Password verification failed for email: $email");
            $_SESSION['login_error'] = 'Incorrect Email or Password';
            $_SESSION['active_form'] = 'login';
            header("Location: index.php");
            exit();
        }
    } else {
        error_log("No user found with email: $email");
        $_SESSION['login_error'] = 'Incorrect Email or Password';
        $_SESSION['active_form'] = 'login';
        header("Location: index.php");
        exit();
    }
}
?>
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

// Initialize variables
$charityLogo = $charityData['logo_url'] ?? null;

// Set default logos based on charity name if no logo exists in database
if (empty($charityLogo)) {
    switch (strtolower($charityName)) {
        case 'moraya childrens home':
            $charityLogo = 'https://images.unsplash.com/photo-1560264285-18f2d824s.jpg?q=80&w=1470&auto=format&fit=crop';
            break;
        case 'bright future education':
            $charityLogo = 'https://images.unsplash.com/photo-1497633762265-9d179a990aa6?q=80&w=1473&auto=format&fit=crop';
            break;
        case 'threads of hope clothing center':
            $charityLogo = 'https://images.unsplash.com/photo-1599058917212-d750089bc07e?q=80&w=1469&auto=format&fit=crop';
            break;
        default:
            $charityLogo = '/placeholder.svg?height=150&width=150';
            break;
    }
}

$charityDescription = $charityData['description'] ?? '';
$charityMission = $charityData['mission'] ?? '';
$charityVision = $charityData['vision'] ?? '';
$charityPhone = $charityData['phone'] ?? '';
$charityWebsite = $charityData['website'] ?? '';
$charityAddress = $charityData['address'] ?? '';
$charityCity = $charityData['city'] ?? '';
$charityCountry = $charityData['country'] ?? '';
$charityEstablished = $charityData['date_established'] ?? '';
$charityRegistered = $charityData['date_registered'] ?? '';
$charityRegistrationNumber = $charityData['registration_number'] ?? '';

// Sanitize variables to prevent hidden characters
$charityLogo = trim($charityLogo);
$charityName = trim($charityName);
$charityLogo = preg_replace('/[\r\n]+/', '', $charityLogo);
$charityName = preg_replace('/[\r\n]+/', '', $charityName);

// Debug the variables
error_log("charityLogo: " . print_r($charityLogo, true));
error_log("charityName: " . print_r($charityName, true));

// Process profile update
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Get form data
    $newName = trim($_POST['charity_name'] ?? '');
    $newDescription = trim($_POST['description'] ?? '');
    $newMission = trim($_POST['mission'] ?? '');
    $newVision = trim($_POST['vision'] ?? '');
    $newPhone = trim($_POST['phone'] ?? '');
    $newWebsite = trim($_POST['website'] ?? '');
    $newAddress = trim($_POST['address'] ?? '');
    $newCity = trim($_POST['city'] ?? '');
    $newCountry = trim($_POST['country'] ?? '');
    $newEstablished = trim($_POST['date_established'] ?? '');
    $newRegistered = trim($_POST['date_registered'] ?? '');
    $newRegistrationNumber = trim($_POST['registration_number'] ?? '');
    
    // Validate inputs
    if (empty($newName)) {
        $errorMessage = "Charity name cannot be empty.";
    } else {
        // Update charity profile in database
        $updateQuery = "UPDATE charity_users SET 
                        charity_name = ?, 
                        description = ?, 
                        mission = ?, 
                        vision = ?, 
                        phone = ?, 
                        website = ?, 
                        address = ?, 
                        city = ?, 
                        country = ?, 
                        date_established = ?, 
                        date_registered = ?, 
                        registration_number = ? 
                        WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("ssssssssssssi", $newName, $newDescription, $newMission, $newVision, $newPhone, $newWebsite, $newAddress, $newCity, $newCountry, $newEstablished, $newRegistered, $newRegistrationNumber, $charityId);
        
        if ($stmt->execute()) {
            // Update session variables
            $_SESSION['charity_name'] = $newName;
            $charityName = $newName;
            
            // Update local variables
            $charityDescription = $newDescription;
            $charityMission = $newMission;
            $charityVision = $newVision;
            $charityPhone = $newPhone;
            $charityWebsite = $newWebsite;
            $charityAddress = $newAddress;
            $charityCity = $newCity;
            $charityCountry = $newCountry;
            $charityEstablished = $newEstablished;
            $charityRegistered = $newRegistered;
            $charityRegistrationNumber = $newRegistrationNumber;
            
            $successMessage = "Charity profile updated successfully!";
        } else {
            $errorMessage = "Error updating profile: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Process logo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_logo'])) {
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/charity_logos/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Get file info
        $fileName = $_FILES['logo']['name'];
        $fileType = $_FILES['logo']['type'];
        $fileTmpName = $_FILES['logo']['tmp_name'];
        $fileError = $_FILES['logo']['error'];
        $fileSize = $_FILES['logo']['size'];
        
        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($fileType, $allowedTypes)) {
            $errorMessage = "Only JPG, PNG, and GIF files are allowed.";
        } elseif ($fileSize > 5000000) { // 5MB max
            $errorMessage = "File size must be less than 5MB.";
        } else {
            // Generate unique filename
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = 'charity_' . $charityId . '_' . uniqid() . '.' . $fileExtension;
            $targetFilePath = $uploadDir . $newFileName;
            
            // Upload file
            if (move_uploaded_file($fileTmpName, $targetFilePath)) {
                // Update logo in database
                $updateQuery = "UPDATE charity_users SET logo_url = ? WHERE id = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("si", $targetFilePath, $charityId);
                
                if ($stmt->execute()) {
                    // Delete old logo if exists
                    if (!empty($charityLogo) && $charityLogo !== '/placeholder.svg?height=150&width=150' && file_exists($charityLogo)) {
                        unlink($charityLogo);
                    }
                    
                    $charityLogo = $targetFilePath;
                    $successMessage = "Charity logo updated successfully!";
                } else {
                    $errorMessage = "Error updating logo in database: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errorMessage = "Error uploading file.";
            }
        }
    } else {
        $errorMessage = "Please select a file to upload.";
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
    <title>Charity Profile - KEMOS DONATION</title>
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
                <li><a href="charity_licenses.php">Licenses</a></li>
                <li><a href="charity_profile.php" class="active">Profile</a></li>
            </ul>
        </nav>
        
        <div class="charity-user">
            <span class="charity-name"><?php echo htmlspecialchars($charityName, ENT_QUOTES, 'UTF-8'); ?></span>
            <div class="charity-dropdown">
                <img src="<?php echo htmlspecialchars($charityLogo, ENT_QUOTES, 'UTF-8'); ?>" 
                     alt="<?php echo htmlspecialchars($charityName, ENT_QUOTES, 'UTF-8'); ?>" 
                     class="charity-logo">
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
            <h1><i class="fas fa-user-circle"></i> Charity Profile</h1>
            <p>Manage your charity's information and appearance</p>
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
        
        <div class="profile-grid">
            <div class="profile-sidebar">
                <div class="profile-logo-container">
                    <img src="<?= htmlspecialchars($charityLogo) ?>" alt="<?= htmlspecialchars($charityName) ?>" class="profile-logo">
                    
                    <form action="charity_profile.php" method="POST" enctype="multipart/form-data" class="logo-upload-form">
                        <div class="file-upload">
                            <input type="file" name="logo" id="logo" accept="image/jpeg, image/png, image/gif">
                            <label for="logo" class="file-upload-label">
                                <i class="fas fa-camera"></i> Change Logo
                            </label>
                        </div>
                        <button type="submit" name="update_logo" class="btn btn-sm">Upload</button>
                    </form>
                </div>
                
                <div class="profile-preview">
                    <h3>Profile Preview</h3>
                    <p>This is how your charity appears to donors</p>
                    
                    <div class="preview-card">
                        <div class="preview-header">
                            <img src="<?= htmlspecialchars($charityLogo) ?>" alt="<?= htmlspecialchars($charityName) ?>" class="preview-logo">
                            <h4><?= htmlspecialchars($charityName) ?></h4>
                        </div>
                        <div class="preview-body">
                            <p><?= nl2br(htmlspecialchars(substr($charityDescription, 0, 100))) ?><?= strlen($charityDescription) > 100 ? '...' : '' ?></p>
                            <div class="preview-meta">
                                <?php if (!empty($charityEstablished)): ?>
                                    <div class="preview-meta-item">
                                        <i class="fas fa-calendar-alt"></i> Est. <?= date('Y', strtotime($charityEstablished)) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($charityCity) && !empty($charityCountry)): ?>
                                    <div class="preview-meta-item">
                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars("$charityCity, $charityCountry") ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="preview-footer">
                            <a href="#" class="preview-btn">Donate</a>
                            <a href="#" class="preview-link">Learn More</a>
                        </div>
                    </div>
                    
                    <div class="license-status">
                        <span class="status-label">License Status:</span>
                        <span class="status-value <?= $licenseStatus === 'active' ? 'active' : 'inactive' ?>">
                            <?= $licenseStatus === 'active' ? 'Active' : 'Inactive' ?>
                        </span>
                        <?php if ($licenseStatus === 'active' && $licenseExpiry): ?>
                            <span class="expiry-date">Expires: <?= date('M d, Y', strtotime($licenseExpiry)) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="profile-content">
                <div class="profile-form-container">
                    <h2>Charity Information</h2>
                    <p>Update your charity's details to provide accurate information to donors</p>
                    
                    <form action="charity_profile.php" method="POST" class="profile-form">
                        <div class="form-group">
                            <label for="charity_name">Charity Name</label>
                            <input type="text" id="charity_name" name="charity_name" class="form-control" value="<?= htmlspecialchars($charityName) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="4"><?= htmlspecialchars($charityDescription) ?></textarea>
                            <small>Provide a detailed description of your charity and its work.</small>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="mission">Mission</label>
                                <textarea id="mission" name="mission" class="form-control" rows="3"><?= htmlspecialchars($charityMission) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="vision">Vision</label>
                                <textarea id="vision" name="vision" class="form-control" rows="3"><?= htmlspecialchars($charityVision) ?></textarea>
                            </div>
                        </div>
                        
                        <h3>Contact Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" class="form-control" value="<?= htmlspecialchars($charityEmail) ?>" disabled>
                                <small>Email cannot be changed. Contact support for assistance.</small>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($charityPhone) ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="website">Website</label>
                            <input type="url" id="website" name="website" class="form-control" value="<?= htmlspecialchars($charityWebsite) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <input type="text" id="address" name="address" class="form-control" value="<?= htmlspecialchars($charityAddress) ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" id="city" name="city" class="form-control" value="<?= htmlspecialchars($charityCity) ?>">
                            </div>
                            <div class="form-group">
                                <label for="country">Country</label>
                                <input type="text" id="country" name="country" class="form-control" value="<?= htmlspecialchars($charityCountry) ?>">
                            </div>
                        </div>
                        
                        <h3>Registration Information</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="date_established">Date Established</label>
                                <input type="date" id="date_established" name="date_established" class="form-control" value="<?= htmlspecialchars($charityEstablished) ?>">
                            </div>
                            <div class="form-group">
                                <label for="date_registered">Date Registered</label>
                                <input type="date" id="date_registered" name="date_registered" class="form-control" value="<?= htmlspecialchars($charityRegistered) ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="registration_number">Registration Number</label>
                            <input type="text" id="registration_number" name="registration_number" class="form-control" value="<?= htmlspecialchars($charityRegistrationNumber) ?>">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
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
        // Preview logo upload
        document.getElementById('logo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.profile-logo').src = e.target.result;
                    document.querySelector('.preview-logo').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
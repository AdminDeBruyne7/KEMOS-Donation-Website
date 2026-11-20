<?php
session_start();
require_once 'admin_config.php';
check_admin_login();

// Connect to kemos database
$conn = connect_kemos_db();

// Initialize variables
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_general_settings'])) {
        // Update general settings
        $site_name = $conn->real_escape_string($_POST['site_name']);
        $site_email = $conn->real_escape_string($_POST['site_email']);
        $contact_phone = $conn->real_escape_string($_POST['contact_phone']);
        $items_per_page = intval($_POST['items_per_page']);
        
        $update_query = "UPDATE settings SET 
            value = CASE 
                WHEN name = 'site_name' THEN '$site_name'
                WHEN name = 'site_email' THEN '$site_email'
                WHEN name = 'contact_phone' THEN '$contact_phone'
                WHEN name = 'items_per_page' THEN '$items_per_page'
                ELSE value
            END
            WHERE name IN ('site_name', 'site_email', 'contact_phone', 'items_per_page')";
        
        if ($conn->query($update_query)) {
            $success_message = "General settings updated successfully";
        } else {
            $error_message = "Error updating general settings: " . $conn->error;
        }
    } elseif (isset($_POST['update_payment_settings'])) {
        // Update payment settings
        $mpesa_business_shortcode = $conn->real_escape_string($_POST['mpesa_business_shortcode']);
        $mpesa_passkey = $conn->real_escape_string($_POST['mpesa_passkey']);
        $mpesa_consumer_key = $conn->real_escape_string($_POST['mpesa_consumer_key']);
        $mpesa_consumer_secret = $conn->real_escape_string($_POST['mpesa_consumer_secret']);
        $paypal_client_id = $conn->real_escape_string($_POST['paypal_client_id']);
        $paypal_secret = $conn->real_escape_string($_POST['paypal_secret']);
        
        $update_query = "UPDATE settings SET 
            value = CASE 
                WHEN name = 'mpesa_business_shortcode' THEN '$mpesa_business_shortcode'
                WHEN name = 'mpesa_passkey' THEN '$mpesa_passkey'
                WHEN name = 'mpesa_consumer_key' THEN '$mpesa_consumer_key'
                WHEN name = 'mpesa_consumer_secret' THEN '$mpesa_consumer_secret'
                WHEN name = 'paypal_client_id' THEN '$paypal_client_id'
                WHEN name = 'paypal_secret' THEN '$paypal_secret'
                ELSE value
            END
            WHERE name IN ('mpesa_business_shortcode', 'mpesa_passkey', 'mpesa_consumer_key', 'mpesa_consumer_secret', 'paypal_client_id', 'paypal_secret')";
        
        if ($conn->query($update_query)) {
            $success_message = "Payment settings updated successfully";
        } else {
            $error_message = "Error updating payment settings: " . $conn->error;
        }
    } elseif (isset($_POST['update_email_settings'])) {
        // Update email settings
        $smtp_host = $conn->real_escape_string($_POST['smtp_host']);
        $smtp_port = intval($_POST['smtp_port']);
        $smtp_username = $conn->real_escape_string($_POST['smtp_username']);
        $smtp_password = $conn->real_escape_string($_POST['smtp_password']);
        $smtp_encryption = $conn->real_escape_string($_POST['smtp_encryption']);
        
        $update_query = "UPDATE settings SET 
            value = CASE 
                WHEN name = 'smtp_host' THEN '$smtp_host'
                WHEN name = 'smtp_port' THEN '$smtp_port'
                WHEN name = 'smtp_username' THEN '$smtp_username'
                WHEN name = 'smtp_password' THEN '$smtp_password'
                WHEN name = 'smtp_encryption' THEN '$smtp_encryption'
                ELSE value
            END
            WHERE name IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption')";
        
        if ($conn->query($update_query)) {
            $success_message = "Email settings updated successfully";
        } else {
            $error_message = "Error updating email settings: " . $conn->error;
        }
    } elseif (isset($_POST['update_admin_password'])) {
        // Update admin password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Verify current password
        $admin_id = $_SESSION['admin_id'];
        $password_query = "SELECT password FROM admins WHERE id = $admin_id";
        $password_result = $conn->query($password_query);
        
        if ($password_result && $password_result->num_rows > 0) {
            $admin_data = $password_result->fetch_assoc();
            
            if (password_verify($current_password, $admin_data['password'])) {
                if ($new_password === $confirm_password) {
                    // Hash the new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update the password
                    $update_query = "UPDATE admins SET password = '$hashed_password' WHERE id = $admin_id";
                    
                    if ($conn->query($update_query)) {
                        $success_message = "Admin password updated successfully";
                    } else {
                        $error_message = "Error updating admin password: " . $conn->error;
                    }
                } else {
                    $error_message = "New password and confirm password do not match";
                }
            } else {
                $error_message = "Current password is incorrect";
            }
        } else {
            $error_message = "Admin account not found";
        }
    }
}

// Get current settings
$settings = [];
$settings_query = "SELECT site_name, value FROM settings";
$settings_result = $conn->query($settings_query);

if ($settings_result && $settings_result->num_rows > 0) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['name']] = $row['value'];
    }
}

// Include header
include 'admin_header.php';
?>

<div class="settings-management">
    <div class="page-header">
        <h1>System Settings</h1>
    </div>
    
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-3">
            <div class="nav flex-column nav-pills settings-tabs" id="settings-tabs" role="tablist">
                <a class="nav-link active" id="general-tab" data-toggle="pill" href="#general" role="tab" aria-controls="general" aria-selected="true">
                    <i class="fas fa-cog"></i> General Settings
                </a>
                <a class="nav-link" id="payment-tab" data-toggle="pill" href="#payment" role="tab" aria-controls="payment" aria-selected="false">
                    <i class="fas fa-credit-card"></i> Payment Settings
                </a>
                <a class="nav-link" id="email-tab" data-toggle="pill" href="#email" role="tab" aria-controls="email" aria-selected="false">
                    <i class="fas fa-envelope"></i> Email Settings
                </a>
                <a class="nav-link" id="security-tab" data-toggle="pill" href="#security" role="tab" aria-controls="security" aria-selected="false">
                    <i class="fas fa-lock"></i> Security Settings
                </a>
                <a class="nav-link" id="backup-tab" data-toggle="pill" href="#backup" role="tab" aria-controls="backup" aria-selected="false">
                    <i class="fas fa-database"></i> Backup & Restore
                </a>
            </div>
        </div>
        <div class="col-md-9">
            <div class="tab-content settings-content" id="settings-content">
                <!-- General Settings -->
                <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                    <div class="card">
                        <div class="card-header">
                            <h2>General Settings</h2>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <div class="form-group">
                                    <label for="site_name">Site Name</label>
                                    <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? 'KEMOS Charity Platform'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="site_email">Site Email</label>
                                    <input type="email" class="form-control" id="site_email" name="site_email" value="<?php echo htmlspecialchars($settings['site_email'] ?? 'info@kemos.org'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="contact_phone">Contact Phone</label>
                                    <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone'] ?? '+254 700 000000'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="items_per_page">Items Per Page</label>
                                    <input type="number" class="form-control" id="items_per_page" name="items_per_page" value="<?php echo intval($settings['items_per_page'] ?? 10); ?>" min="5" max="100" required>
                                </div>
                                <button type="submit" name="update_general_settings" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Settings -->
                <div class="tab-pane fade" id="payment" role="tabpanel" aria-labelledby="payment-tab">
                    <div class="card">
                        <div class="card-header">
                            <h2>Payment Settings</h2>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <h4>M-Pesa Settings</h4>
                                <div class="form-group">
                                    <label for="mpesa_business_shortcode">Business Shortcode</label>
                                    <input type="text" class="form-control" id="mpesa_business_shortcode" name="mpesa_business_shortcode" value="<?php echo htmlspecialchars($settings['mpesa_business_shortcode'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="mpesa_passkey">Passkey</label>
                                    <input type="password" class="form-control" id="mpesa_passkey" name="mpesa_passkey" value="<?php echo htmlspecialchars($settings['mpesa_passkey'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="mpesa_consumer_key">Consumer Key</label>
                                    <input type="text" class="form-control" id="mpesa_consumer_key" name="mpesa_consumer_key" value="<?php echo htmlspecialchars($settings['mpesa_consumer_key'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="mpesa_consumer_secret">Consumer Secret</label>
                                    <input type="password" class="form-control" id="mpesa_consumer_secret" name="mpesa_consumer_secret" value="<?php echo htmlspecialchars($settings['mpesa_consumer_secret'] ?? ''); ?>">
                                </div>
                                
                                <h4 class="mt-4">PayPal Settings</h4>
                                <div class="form-group">
                                    <label for="paypal_client_id">Client ID</label>
                                    <input type="text" class="form-control" id="paypal_client_id" name="paypal_client_id" value="<?php echo htmlspecialchars($settings['paypal_client_id'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="paypal_secret">Secret</label>
                                    <input type="password" class="form-control" id="paypal_secret" name="paypal_secret" value="<?php echo htmlspecialchars($settings['paypal_secret'] ?? ''); ?>">
                                </div>
                                
                                <button type="submit" name="update_payment_settings" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Email Settings -->
                <div class="tab-pane fade" id="email" role="tabpanel" aria-labelledby="email-tab">
                    <div class="card">
                        <div class="card-header">
                            <h2>Email Settings</h2>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <div class="form-group">
                                    <label for="smtp_host">SMTP Host</label>
                                    <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="smtp_port">SMTP Port</label>
                                    <input type="number" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo intval($settings['smtp_port'] ?? 587); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="smtp_username">SMTP Username</label>
                                    <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="smtp_password">SMTP Password</label>
                                    <input type="password" class="form-control" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="smtp_encryption">Encryption</label>
                                    <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                        <option value="tls" <?php echo (($settings['smtp_encryption'] ?? 'tls') == 'tls') ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo (($settings['smtp_encryption'] ?? '') == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo (($settings['smtp_encryption'] ?? '') == 'none') ? 'selected' : ''; ?>>None</option>
                                    </select>
                                </div>
                                <button type="submit" name="update_email_settings" class="btn btn-primary">Save Changes</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Security Settings -->
                <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                    <div class="card">
                        <div class="card-header">
                            <h2>Security Settings</h2>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <h4>Change Admin Password</h4>
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" name="update_admin_password" class="btn btn-primary">Update Password</button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Backup & Restore -->
                <div class="tab-pane fade" id="backup" role="tabpanel" aria-labelledby="backup-tab">
                    <div class="card">
                        <div class="card-header">
                            <h2>Backup & Restore</h2>
                        </div>
                        <div class="card-body">
                            <div class="backup-section">
                                <h4>Database Backup</h4>
                                <p>Create a backup of your database. This will download a SQL file containing all your data.</p>
                                <a href="admin_backup.php?action=backup" class="btn btn-primary">
                                    <i class="fas fa-download"></i> Create Backup
                                </a>
                            </div>
                            
                            <hr>
                            
                            <div class="restore-section">
                                <h4>Restore Database</h4>
                                <p>Restore your database from a previous backup. This will overwrite your current data.</p>
                                <form method="post" action="admin_backup.php?action=restore" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label for="backup_file">Select Backup File</label>
                                        <input type="file" class="form-control-file" id="backup_file" name="backup_file" accept=".sql" required>
                                    </div>
                                    <button type="submit" class="btn btn-warning" onclick="return confirm('Warning: This will overwrite your current database. Are you sure you want to proceed?');">
                                        <i class="fas fa-upload"></i> Restore Backup
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show the active tab based on URL hash
    const hash = window.location.hash;
    if (hash) {
        const tab = document.querySelector(`a[href="${hash}"]`);
        if (tab) {
            tab.click();
        }
    }
    
    // Password strength validation
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    if (newPasswordInput && confirmPasswordInput) {
        newPasswordInput.addEventListener('input', validatePassword);
        confirmPasswordInput.addEventListener('input', validatePasswordMatch);
    }
    
    function validatePassword() {
        const password = newPasswordInput.value;
        const minLength = 8;
        
        if (password.length < minLength) {
            newPasswordInput.setCustomValidity(`Password must be at least ${minLength} characters long`);
        } else if (!/[A-Z]/.test(password)) {
            newPasswordInput.setCustomValidity('Password must contain at least one uppercase letter');
        } else if (!/[a-z]/.test(password)) {
            newPasswordInput.setCustomValidity('Password must contain at least one lowercase letter');
        } else if (!/[0-9]/.test(password)) {
            newPasswordInput.setCustomValidity('Password must contain at least one number');
        } else if (!/[^A-Za-z0-9]/.test(password)) {
            newPasswordInput.setCustomValidity('Password must contain at least one special character');
        } else {
            newPasswordInput.setCustomValidity('');
        }
        
        validatePasswordMatch();
    }
    
    function validatePasswordMatch() {
        if (newPasswordInput.value !== confirmPasswordInput.value) {
            confirmPasswordInput.setCustomValidity('Passwords do not match');
        } else {
            confirmPasswordInput.setCustomValidity('');
        }
    }
});
</script>

<?php
// Close database connection
$conn->close();

// Include footer
include 'admin_footer.php';
?>
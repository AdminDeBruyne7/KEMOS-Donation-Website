<?php
session_start();
require_once 'admin_config.php';
check_admin_login();

// Connect to kemos database
$conn = connect_kemos_db();

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$charity_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = '';
$error_message = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new charity
    if (isset($_POST['add_charity'])) {
        $charity_name = sanitize_input($_POST['charity_name']);
        $contact_person = sanitize_input($_POST['contact_person']);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $description = sanitize_input($_POST['description']);
        
        // Validate input
        if (empty($charity_name) || empty($contact_person) || empty($email) || empty($password)) {
            $error_message = "Please fill in all required fields";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM charities WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Email already exists";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new charity
                $stmt = $conn->prepare("INSERT INTO charities (charity_name, contact_person, email, password, description) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $charity_name, $contact_person, $email, $hashed_password, $description);
                
                if ($stmt->execute()) {
                    $success_message = "Charity added successfully";
                    // Redirect to charity list
                    header("Location: admin_charities.php?success=added");
                    exit();
                } else {
                    $error_message = "Error adding charity: " . $conn->error;
                }
            }
            $stmt->close();
        }
    }
    
    // Update existing charity
    if (isset($_POST['update_charity'])) {
        $charity_id = intval($_POST['charity_id']);
        $charity_name = sanitize_input($_POST['charity_name']);
        $contact_person = sanitize_input($_POST['contact_person']);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $description = sanitize_input($_POST['description']);
        $password = $_POST['password']; // Optional, might be empty
        
        // Validate input
        if (empty($charity_name) || empty($contact_person) || empty($email)) {
            $error_message = "Please fill in all required fields";
        } else {
            // Check if email already exists for other charities
            $stmt = $conn->prepare("SELECT id FROM charities WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $charity_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Email already exists";
            } else {
                // Update charity with or without password
                if (!empty($password)) {
                    // Hash new password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE charities SET charity_name = ?, contact_person = ?, email = ?, password = ?, description = ? WHERE id = ?");
                    $stmt->bind_param("sssssi", $charity_name, $contact_person, $email, $hashed_password, $description, $charity_id);
                } else {
                    // Update without changing password
                    $stmt = $conn->prepare("UPDATE charities SET charity_name = ?, contact_person = ?, email = ?, description = ? WHERE id = ?");
                    $stmt->bind_param("ssssi", $charity_name, $contact_person, $email, $description, $charity_id);
                }
                
                if ($stmt->execute()) {
                    $success_message = "Charity updated successfully";
                    // Redirect to charity list
                    header("Location: admin_charities.php?success=updated");
                    exit();
                } else {
                    $error_message = "Error updating charity: " . $conn->error;
                }
            }
            $stmt->close();
        }
    }
    
    // Delete charity
    if (isset($_POST['delete_charity'])) {
        $charity_id = intval($_POST['charity_id']);
        
        // Check if charity exists
        $stmt = $conn->prepare("SELECT id FROM charities WHERE id = ?");
        $stmt->bind_param("i", $charity_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error_message = "Charity not found";
        } else {
            // Check if charity has donations
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM donations WHERE charity_id = ?");
            $stmt->bind_param("i", $charity_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $donation_count = $result->fetch_assoc()['count'];
            
            if ($donation_count > 0) {
                $error_message = "Cannot delete charity with existing donations. Please reassign or delete the donations first.";
            } else {
                // Delete charity
                $stmt = $conn->prepare("DELETE FROM charities WHERE id = ?");
                $stmt->bind_param("i", $charity_id);
                
                if ($stmt->execute()) {
                    $success_message = "Charity deleted successfully";
                    // Redirect to charity list
                    header("Location: admin_charities.php?success=deleted");
                    exit();
                } else {
                    $error_message = "Error deleting charity: " . $conn->error;
                }
            }
        }
        $stmt->close();
    }
}

// Get success message from URL if redirected
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $success_message = "Charity added successfully";
            break;
        case 'updated':
            $success_message = "Charity updated successfully";
            break;
        case 'deleted':
            $success_message = "Charity deleted successfully";
            break;
    }
}

// Include header
include 'admin_header.php';
?>

<div class="charities-management">
    <div class="page-header">
        <h1><?php echo ($action == 'add') ? 'Add New Charity' : (($action == 'edit') ? 'Edit Charity' : 'Charities Management'); ?></h1>
        
        <?php if ($action == 'list'): ?>
            <a href="admin_charities.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add New Charity
            </a>
        <?php endif; ?>
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
    
    <?php if ($action == 'list'): ?>
        <!-- Charities List -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Charity Name</th>
                                <th>Contact Person</th>
                                <th>Email</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Pagination
                            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
                            $items_per_page = $config['items_per_page'];
                            $offset = ($page - 1) * $items_per_page;
                            
                            // Get total charities count
                            $count_result = $conn->query("SELECT COUNT(*) as total FROM charities");
                            $total_charities = $count_result->fetch_assoc()['total'];
                            $total_pages = ceil($total_charities / $items_per_page);
                            
                            // Get charities for current page
                            $charities_result = $conn->query("SELECT * FROM charities ORDER BY id DESC LIMIT $offset, $items_per_page");
                            
                            if ($charities_result->num_rows > 0):
                                while ($charity = $charities_result->fetch_assoc()):
                            ?>
                                <tr>
                                    <td><?php echo $charity['id']; ?></td>
                                    <td><?php echo htmlspecialchars($charity['charity_name']); ?></td>
                                    <td><?php echo htmlspecialchars($charity['contact_person']); ?></td>
                                    <td><?php echo htmlspecialchars($charity['email']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($charity['created_at'])); ?></td>
                                    <td>
                                        <a href="admin_charities.php?action=edit&id=<?php echo $charity['id']; ?>" class="btn btn-sm btn-info" data-toggle="tooltip" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="post" action="admin_charities.php" class="d-inline">
                                            <input type="hidden" name="charity_id" value="<?php echo $charity['id']; ?>">
                                            <button type="submit" name="delete_charity" class="btn btn-sm btn-danger delete-btn" data-toggle="tooltip" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <a href="admin_donations.php?charity_id=<?php echo $charity['id']; ?>" class="btn btn-sm btn-success" data-toggle="tooltip" title="View Donations">
                                            <i class="fas fa-donate"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php
                                endwhile;
                            else:
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center">No charities found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="admin_charities.php?page=<?php echo ($page - 1); ?>" class="page-link">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="admin_charities.php?page=<?php echo $i; ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="admin_charities.php?page=<?php echo ($page + 1); ?>" class="page-link">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($action == 'add'): ?>
        <!-- Add Charity Form -->
        <div class="card">
            <div class="card-body">
                <form method="post" action="admin_charities.php">
                    <div class="form-group">
                        <label for="charity_name">Charity Name *</label>
                        <input type="text" id="charity_name" name="charity_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_person">Contact Person *</label>
                        <input type="text" id="contact_person" name="contact_person" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="5"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="add_charity" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Charity
                        </button>
                        <a href="admin_charities.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    <?php elseif ($action == 'edit' && $charity_id > 0): ?>
        <?php
        // Get charity data
        $stmt = $conn->prepare("SELECT * FROM charities WHERE id = ?");
        $stmt->bind_param("i", $charity_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo '<div class="alert alert-danger">Charity not found</div>';
        } else {
            $charity = $result->fetch_assoc();
        ?>
        <!-- Edit Charity Form -->
        <div class="card">
            <div class="card-body">
                <form method="post" action="admin_charities.php">
                    <input type="hidden" name="charity_id" value="<?php echo $charity['id']; ?>">
                    
                    <div class="form-group">
                        <label for="charity_name">Charity Name *</label>
                        <input type="text" id="charity_name" name="charity_name" class="form-control" value="<?php echo htmlspecialchars($charity['charity_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_person">Contact Person *</label>
                        <input type="text" id="contact_person" name="contact_person" class="form-control" value="<?php echo htmlspecialchars($charity['contact_person']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($charity['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password (leave empty to keep current)</label>
                        <input type="password" id="password" name="password" class="form-control">
                        <small class="form-text text-muted">Leave blank to keep the current password</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="5"><?php echo htmlspecialchars($charity['description']); ?></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="update_charity" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Charity
                        </button>
                        <a href="admin_charities.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        }
        $stmt->close();
        ?>
    <?php endif; ?>
</div>

<?php
// Close database connection
$conn->close();

// Include footer
include 'admin_footer.php';
?>


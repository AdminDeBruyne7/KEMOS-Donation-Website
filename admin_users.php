<?php
session_start();
require_once 'admin_config.php';
check_admin_login();

// Connect to users database
$conn = connect_kemos_db();

// Ensure the admin user exists
$admin_email = 'debruynekelvyn@gmail.com'; // Change this as needed
$admin_password = 'Kelvyn2040'; // Change this to a secure password
$admin_role = 'admin';

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $admin_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES ('Admin', ?, ?, ?)");
    $stmt->bind_param("sss", $admin_email, $hashed_password, $admin_role);
    $stmt->execute();
}
$stmt->close();

// Initialize variables
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$success_message = '';
$error_message = '';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new user
    if (isset($_POST['add_user'])) {
        $name = sanitize_input($_POST['name']);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $role = sanitize_input($_POST['role']);
        $age = isset($_POST['age']) ? intval($_POST['age']) : 0;
        
        if (empty($name) || empty($email) || empty($password)) {
            $error_message = "Please fill in all required fields";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Email already exists";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, age) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssi", $name, $email, $hashed_password, $role, $age);
                if ($stmt->execute()) {
                    header("Location: admin_users.php?success=added");
                    exit();
                } else {
                    $error_message = "Error adding user: " . $conn->error;
                }
            }
            $stmt->close();
        }
    }

    // Update user
    if (isset($_POST['update_user'])) {
        $user_id = intval($_POST['user_id']);
        $name = sanitize_input($_POST['name']);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $role = sanitize_input($_POST['role']);
        $age = isset($_POST['age']) ? intval($_POST['age']) : 0;
        $password = $_POST['password'];

        if (empty($name) || empty($email)) {
            $error_message = "Please fill in all required fields";
        } else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Email already exists";
            } else {
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ?, age = ? WHERE id = ?");
                    $stmt->bind_param("ssssis", $name, $email, $hashed_password, $role, $age, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, role = ?, age = ? WHERE id = ?");
                    $stmt->bind_param("sssis", $name, $email, $role, $age, $user_id);
                }
                if ($stmt->execute()) {
                    header("Location: admin_users.php?success=updated");
                    exit();
                } else {
                    $error_message = "Error updating user: " . $conn->error;
                }
            }
            $stmt->close();
        }
    }
}

include 'admin_header.php';
?>

<div class="users-management">
    <h1>Users Management</h1>
    <a href="admin_users.php?action=add" class="btn btn-primary">Add New User</a>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"> <?php echo $success_message; ?> </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"> <?php echo $error_message; ?> </div>
    <?php endif; ?>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $users_result = $conn->query("SELECT * FROM users ORDER BY id DESC");
            while ($user = $users_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $user['ID']; ?></td>
                    <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo ucfirst($user['role']); ?></td>
                    <td>
                        <a href="admin_users.php?action=edit&id=<?php echo $user['ID']; ?>" class="btn btn-sm btn-info">Edit</a>
                        <form method="post" action="admin_users.php" class="d-inline">
                            <input type="hidden" name="user_id" value="<?php echo $user['ID']; ?>">
                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php
$conn->close();
include 'admin_footer.php';
?>

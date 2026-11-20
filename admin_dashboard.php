<?php
session_start();
require_once 'admin_config.php';
check_admin_login();

// Connect to the database
$conn_kemos = connect_kemos_db();
$conn_users = $conn_kemos; // Assuming users are also in the same database

// Get statistics
// Total users
$users_result = $conn_kemos->query("SELECT COUNT(*) as total FROM users");
$total_users = $users_result ? $users_result->fetch_assoc()['total'] : 0;

// Total charities
$charities_result = $conn_kemos->query("SELECT COUNT(*) as total FROM charities");
$total_charities = $charities_result ? $charities_result->fetch_assoc()['total'] : 0;

// Total donations
$donations_result = $conn_kemos->query("SELECT COUNT(*) as total, SUM(amount) as amount FROM donations");
$donations_data = $donations_result ? $donations_result->fetch_assoc() : ['total' => 0, 'amount' => 0];
$total_donations = $donations_data['total'] ?? 0;
$total_amount = $donations_data['amount'] ?? 0;

// Recent donations
$recent_donations_query = "
    SELECT d.*, c.charity_name, u.first_name, u.middle_name, u.last_name as donor_name 
    FROM donations d
    LEFT JOIN charities c ON d.charity_id = c.id
    LEFT JOIN users u ON d.user_id = u.id
    ORDER BY d.donation_date DESC
    LIMIT 5
";

$recent_donations = $conn_kemos->query($recent_donations_query);

// Recent users
$recent_users_query = "
    SELECT * FROM users
    ORDER BY created_at DESC
    LIMIT 5
";

$recent_users = $conn_kemos->query($recent_users_query);

// Include header
include 'admin_header.php';
?>


<div class="dashboard">
    <h1>Dashboard</h1>
    
    <!-- Statistics Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-card-icon bg-primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-card-info">
                <h3>Total Users</h3>
                <p class="stat-number"><?php echo number_format($total_users); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card-icon bg-success">
                <i class="fas fa-hand-holding-heart"></i>
            </div>
            <div class="stat-card-info">
                <h3>Total Charities</h3>
                <p class="stat-number"><?php echo number_format($total_charities); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card-icon bg-info">
                <i class="fas fa-donate"></i>
            </div>
            <div class="stat-card-info">
                <h3>Total Donations</h3>
                <p class="stat-number"><?php echo number_format($total_donations); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-card-icon bg-warning">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-card-info">
                <h3>Total Amount</h3>
                <p class="stat-number">KSh <?php echo number_format($total_amount, 2); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="dashboard-row">
        <!-- Recent Donations -->
        <div class="dashboard-card">
            <div class="card-header">
                <h2>Recent Donations</h2>
                <a href="admin_donations.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Donor</th>
                            <th>Charity</th>
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_donations->num_rows > 0): ?>
                            <?php while ($donation = $recent_donations->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($donation['donor_name'] ?? 'Anonymous'); ?></td>
                                    <td><?php echo htmlspecialchars($donation['charity_name']); ?></td>
                                    <td>KSh <?php echo number_format($donation['amount'], 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($donation['donation_date'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No recent donations</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Users -->
        <div class="dashboard-card">
            <div class="card-header">
                <h2>Recent Users</h2>
                <a href="admin_users.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_users->num_rows > 0): ?>
                            <?php while ($user = $recent_users->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><span class="badge badge-<?php echo ($user['role'] == 'admin') ? 'danger' : (($user['role'] == 'charity') ? 'success' : 'primary'); ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No recent users</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="action-buttons">
            <a href="admin_users.php?action=add" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Add User
            </a>
            <a href="admin_charities.php?action=add" class="btn btn-success">
                <i class="fas fa-plus-circle"></i> Add Charity
            </a>
            <a href="admin_donations.php" class="btn btn-info">
                <i class="fas fa-list"></i> View Donations
            </a>
            <a href="admin_transactions.php" class="btn btn-warning">
                <i class="fas fa-exchange-alt"></i> View Transactions
            </a>
        </div>
    </div>
</div>

<?php
// Close database connections
$conn_users->close();

// Include footer
include 'admin_footer.php';
?>


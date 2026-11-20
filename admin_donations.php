<?php
session_start();
require_once 'admin_config.php';
check_admin_login();

// Connect to kemos database
$conn = connect_kemos_db();

// Initialize variables
$charity_id = isset($_GET['charity_id']) ? intval($_GET['charity_id']) : 0;
$success_message = '';
$error_message = '';

// Include header
include 'admin_header.php';
?>

<div class="donations-management">
    <div class="page-header">
        <h1>Donations Management</h1>
        
        <div class="filter-options">
            <form method="get" action="admin_donations.php" class="filter-form">
                <div class="form-group">
                    <select name="charity_id" class="form-control" onchange="this.form.submit()">
                        <option value="">All Charities</option>
                        <?php
                        $charities_result = $conn->query("SELECT id, charity_name FROM charities ORDER BY charity_name");
                        while ($charity = $charities_result->fetch_assoc()) {
                            $selected = ($charity_id == $charity['id']) ? 'selected' : '';
                            echo "<option value='{$charity['id']}' $selected>{$charity['charity_name']}</option>";
                        }
                        ?>
                    </select>
                </div>
            </form>
        </div>
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
    
    <!-- Donations List -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Donor</th>
                            <th>Charity</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Date</th>
                            <th>Message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Pagination
                        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
                        $items_per_page = $config['items_per_page'];
                        $offset = ($page - 1) * $items_per_page;
                        
                        // Build query based on filters
                        $where_clause = '';
                        $params = [];
                        $param_types = '';
                        
                        if ($charity_id > 0) {
                            $where_clause = 'WHERE d.charity_id = ?';
                            $params[] = $charity_id;
                            $param_types .= 'i';
                        }
                        
                        // Get total donations count
                        $count_query = "SELECT COUNT(*) as total FROM donations d $where_clause";
                        if (!empty($params)) {
                            $stmt = $conn->prepare($count_query);
                            $stmt->bind_param($param_types, ...$params);
                            $stmt->execute();
                            $count_result = $stmt->get_result();
                            $total_donations = $count_result->fetch_assoc()['total'];
                            $stmt->close();
                        } else {
                            $count_result = $conn->query($count_query);
                            $total_donations = $count_result->fetch_assoc()['total'];
                        }
                        
                        $total_pages = ceil($total_donations / $items_per_page);
                        
                        // Get donations for current page
                        $query = "
                            SELECT d.*, c.charity_name, u.first_name as donor_name 
                            FROM donations d
                            LEFT JOIN charities c ON d.charity_id = c.id
                            LEFT JOIN kemos_donation.users u ON d.user_id = u.id
                            $where_clause
                            ORDER BY d.donation_date DESC
                            LIMIT ?, ?
                        ";
                        
                        $params[] = $offset;
                        $params[] = $items_per_page;
                        $param_types .= 'ii';
                        
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param($param_types, ...$params);
                        $stmt->execute();
                        $donations_result = $stmt->get_result();
                        
                        if ($donations_result->num_rows > 0):
                            while ($donation = $donations_result->fetch_assoc()):
                        ?>
                            <tr>
                                <td><?php echo $donation['id']; ?></td>
                                <td><?php echo htmlspecialchars($donation['donor_name'] ?? 'Anonymous'); ?></td>
                                <td><?php echo htmlspecialchars($donation['charity_name']); ?></td>
                                <td>KSh <?php echo number_format($donation['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($donation['payment_method']); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($donation['donation_date'])); ?></td>
                                <td><?php echo htmlspecialchars($donation['message'] ?? ''); ?></td>
                            </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="7" class="text-center">No donations found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="admin_donations.php?page=<?php echo ($page - 1); ?><?php echo ($charity_id > 0) ? '&charity_id=' . $charity_id : ''; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="admin_donations.php?page=<?php echo $i; ?><?php echo ($charity_id > 0) ? '&charity_id=' . $charity_id : ''; ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="admin_donations.php?page=<?php echo ($page + 1); ?><?php echo ($charity_id > 0) ? '&charity_id=' . $charity_id : ''; ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Donation Statistics -->
    <div class="card">
        <div class="card-header">
            <h2>Donation Statistics</h2>
        </div>
        <div class="card-body">
            <?php
            // Get donation statistics
            $stats_query = "
                SELECT 
                    COUNT(*) as total_count,
                    SUM(amount) as total_amount,
                    AVG(amount) as avg_amount,
                    MAX(amount) as max_amount,
                    MIN(amount) as min_amount
                FROM donations
            ";
            
            if ($charity_id > 0) {
                $stats_query .= " WHERE charity_id = $charity_id";
            }
            
            $stats_result = $conn->query($stats_query);
            $stats = $stats_result->fetch_assoc();
            ?>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-card-icon bg-primary">
                        <i class="fas fa-donate"></i>
                    </div>
                    <div class="stat-card-info">
                        <h3>Total Donations</h3>
                        <p class="stat-number"><?php echo number_format($stats['total_count']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-icon bg-success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-card-info">
                        <h3>Total Amount</h3>
                        <p class="stat-number">KSh <?php echo number_format($stats['total_amount'], 2); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-icon bg-info">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-card-info">
                        <h3>Average Donation</h3>
                        <p class="stat-number">KSh <?php echo number_format($stats['avg_amount'], 2); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-icon bg-warning">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-card-info">
                        <h3>Largest Donation</h3>
                        <p class="stat-number">KSh <?php echo number_format($stats['max_amount'], 2); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Close database connection
$conn->close();

// Include footer
include 'admin_footer.php';
?>


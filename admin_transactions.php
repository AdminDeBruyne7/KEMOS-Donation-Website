<?php
session_start();
require_once 'admin_config.php';
check_admin_login();

// Connect to kemos database
$conn = connect_kemos_db();

// Initialize variables
$transaction_type = isset($_GET['type']) ? $_GET['type'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$success_message = '';
$error_message = '';

// Check for session messages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Include header
include 'admin_header.php';
?>

<div class="transactions-management">
    <div class="page-header">
        <h1>Transactions Management</h1>
        
        <div class="filter-options">
            <form method="get" action="admin_transactions.php" class="filter-form">
                <div class="form-group">
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="completed" <?php echo ($status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                        <option value="pending" <?php echo ($status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="failed" <?php echo ($status == 'failed') ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Filter</button>
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
    
    <!-- Transactions List -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Donor Name</th>
                            <th>Phone</th>
                            <th>Charity</th>
                            <th>Amount</th>
                            <th>M-Pesa Receipt</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Pagination
                        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
                        $items_per_page = isset($config['items_per_page']) ? $config['items_per_page'] : 10;
                        $offset = ($page - 1) * $items_per_page;
                        
                        // Build query based on filters
                        $where_clauses = [];
                        $params = [];
                        $param_types = '';
                        
                        if (!empty($status)) {
                            $where_clauses[] = 't.status = ?';
                            $params[] = $status;
                            $param_types .= 's';
                        }
                        
                        $where_clause = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
                        
                        // Get total transactions count
                        $count_query = "SELECT COUNT(*) as total FROM transactions t $where_clause";
                        if (!empty($params)) {
                            $stmt = $conn->prepare($count_query);
                            $stmt->bind_param($param_types, ...$params);
                            $stmt->execute();
                            $count_result = $stmt->get_result();
                            $total_transactions = $count_result->fetch_assoc()['total'];
                            $stmt->close();
                        } else {
                            $count_result = $conn->query($count_query);
                            $total_transactions = $count_result->fetch_assoc()['total'];
                        }
                        
                        $total_pages = ceil($total_transactions / $items_per_page);
                        
                        // Get transactions for current page
                        $query = "
                            SELECT t.*, c.charity_name 
                            FROM transactions t
                            LEFT JOIN charities c ON t.charity_id = c.id
                            $where_clause
                            ORDER BY t.created_at DESC
                            LIMIT ?, ?
                        ";
                        
                        $params[] = $offset;
                        $params[] = $items_per_page;
                        $param_types .= 'ii';
                        
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param($param_types, ...$params);
                        $stmt->execute();
                        $transactions_result = $stmt->get_result();
                        
                        if ($transactions_result->num_rows > 0):
                            while ($transaction = $transactions_result->fetch_assoc()):
                                // Determine status class
                                $status_class = '';
                                switch ($transaction['status']) {
                                    case 'completed':
                                        $status_class = 'badge-success';
                                        break;
                                    case 'pending':
                                        $status_class = 'badge-warning';
                                        break;
                                    case 'failed':
                                        $status_class = 'badge-danger';
                                        break;
                                    default:
                                        $status_class = 'badge-secondary';
                                }
                        ?>
                            <tr>
                                <td><?php echo $transaction['id']; ?></td>
                                <td><?php echo htmlspecialchars($transaction['donor_name'] ?? 'Anonymous'); ?></td>
                                <td><?php echo htmlspecialchars($transaction['phone_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($transaction['charity_name'] ?? 'N/A'); ?></td>
                                <td>KSh <?php echo number_format($transaction['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($transaction['mpesa_receipt'] ?? 'N/A'); ?></td>
                                <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($transaction['status']); ?></span></td>
                                <td><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-info view-transaction" data-id="<?php echo $transaction['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($transaction['status'] == 'pending'): ?>
                                            <button type="button" class="btn btn-sm btn-success approve-transaction" data-id="<?php echo $transaction['id']; ?>">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger reject-transaction" data-id="<?php echo $transaction['id']; ?>">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="9" class="text-center">No transactions found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="admin_transactions.php?page=<?php echo ($page - 1); ?><?php echo (!empty($status)) ? '&status=' . $status : ''; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="admin_transactions.php?page=<?php echo $i; ?><?php echo (!empty($status)) ? '&status=' . $status : ''; ?>" class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="admin_transactions.php?page=<?php echo ($page + 1); ?><?php echo (!empty($status)) ? '&status=' . $status : ''; ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Transaction Statistics -->
    <div class="card">
        <div class="card-header">
            <h2>Transaction Statistics</h2>
        </div>
        <div class="card-body">
            <?php
            // Get transaction statistics
            $stats_query = "
                SELECT 
                    COUNT(*) as total_count,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_amount
                FROM transactions
            ";
            
            $where_clauses = [];
            if (!empty($status)) {
                $where_clauses[] = "status = '$status'";
            }
            
            if (!empty($where_clauses)) {
                $stats_query .= " WHERE " . implode(' AND ', $where_clauses);
            }
            
            $stats_result = $conn->query($stats_query);
            $stats = $stats_result->fetch_assoc();
            ?>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-card-icon bg-primary">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="stat-card-info">
                        <h3>Total Transactions</h3>
                        <p class="stat-number"><?php echo number_format($stats['total_count']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-icon bg-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <h3>Completed</h3>
                        <p class="stat-number"><?php echo number_format($stats['completed_count']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-icon bg-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-card-info">
                        <h3>Pending</h3>
                        <p class="stat-number"><?php echo number_format($stats['pending_count']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-icon bg-danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-card-info">
                        <h3>Failed</h3>
                        <p class="stat-number"><?php echo number_format($stats['failed_count']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-icon bg-info">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-card-info">
                        <h3>Total Amount (Completed)</h3>
                        <p class="stat-number">KSh <?php echo number_format($stats['total_amount'], 2); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Details Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1" role="dialog" aria-labelledby="transactionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transactionModalLabel">Transaction Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="transaction-details-content">
                    <p class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading transaction details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // View Transaction Details
    const viewButtons = document.querySelectorAll('.view-transaction');
    viewButtons.forEach(button => {
        button.addEventListener('click', function() {
            const transactionId = this.getAttribute('data-id');
            const detailsContainer = document.getElementById('transaction-details-content');
            
            // Show loading
            detailsContainer.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading transaction details...</p>';
            
            // Show modal
            $('#transactionModal').modal('show');
            
            // Fetch transaction details
            fetch(`admin_ajax.php?action=get_transaction_details&id=${transactionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let statusClass = '';
                        switch (data.transaction.status) {
                            case 'completed': statusClass = 'text-success'; break;
                            case 'pending': statusClass = 'text-warning'; break;
                            case 'failed': statusClass = 'text-danger'; break;
                            default: statusClass = 'text-secondary';
                        }
                        
                        let html = `
                            <div class="transaction-detail-item">
                                <strong>ID:</strong> ${data.transaction.id}
                            </div>
                            <div class="transaction-detail-item">
                                <strong>Donor Name:</strong> ${data.transaction.donor_name || 'Anonymous'}
                            </div>
                            <div class="transaction-detail-item">
                                <strong>Phone Number:</strong> ${data.transaction.phone_number || 'N/A'}
                            </div>
                            <div class="transaction-detail-item">
                                <strong>Charity:</strong> ${data.transaction.charity_name || 'N/A'}
                            </div>
                            <div class="transaction-detail-item">
                                <strong>Amount:</strong> KSh ${parseFloat(data.transaction.amount).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                            </div>
                            <div class="transaction-detail-item">
                                <strong>Transaction ID:</strong> ${data.transaction.transaction_id || 'N/A'}
                            </div>
                            <div class="transaction-detail-item">
                                <strong>Checkout Request ID:</strong> ${data.transaction.checkout_request_id || 'N/A'}
                            </div>
                            <div class="transaction-detail-item">
                                <strong>M-Pesa Receipt:</strong> ${data.transaction.mpesa_receipt || 'N/A'}
                            </div>
                            <div class="transaction-detail-item">
                                <strong>Status:</strong> <span class="${statusClass}">${data.transaction.status.charAt(0).toUpperCase() + data.transaction.status.slice(1)}</span>
                            </div>
                            <div class="transaction-detail-item">
                                <strong>Date:</strong> ${new Date(data.transaction.created_at).toLocaleString()}
                            </div>
                        `;
                        
                        // Add notes if they exist
                        if (data.transaction.notes) {
                            html += `
                                <div class="transaction-detail-item">
                                    <strong>Notes:</strong> ${data.transaction.notes}
                                </div>
                            `;
                        }
                        
                        detailsContainer.innerHTML = html;
                    } else {
                        detailsContainer.innerHTML = `<p class="text-danger">${data.message || 'Failed to load transaction details'}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Error fetching transaction details:', error);
                    detailsContainer.innerHTML = '<p class="text-danger">An error occurred while loading transaction details</p>';
                });
        });
    });
    
    // Approve Transaction
    const approveButtons = document.querySelectorAll('.approve-transaction');
    approveButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (confirm('Are you sure you want to approve this transaction?')) {
                const transactionId = this.getAttribute('data-id');
                
                fetch('admin_ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_transaction_status&id=${transactionId}&status=completed`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Transaction approved successfully');
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to approve transaction');
                    }
                })
                .catch(error => {
                    console.error('Error approving transaction:', error);
                    alert('An error occurred while approving the transaction');
                });
            }
        });
    });
    
    // Reject Transaction
    const rejectButtons = document.querySelectorAll('.reject-transaction');
    rejectButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (confirm('Are you sure you want to reject this transaction?')) {
                const transactionId = this.getAttribute('data-id');
                
                fetch('admin_ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_transaction_status&id=${transactionId}&status=failed`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Transaction rejected successfully');
                        window.location.reload();
                    } else {
                        alert(data.message || 'Failed to reject transaction');
                    }
                })
                .catch(error => {
                    console.error('Error rejecting transaction:', error);
                    alert('An error occurred while rejecting the transaction');
                });
            }
        });
    });
});
</script>

<?php
// Close database connection
$conn->close();

// Include footer
include 'admin_footer.php';
?>
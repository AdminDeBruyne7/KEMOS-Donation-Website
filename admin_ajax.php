<?php
session_start();
require_once 'admin_config.php';
check_admin_login();

// Connect to kemos database
$conn = connect_kemos_db();

// Initialize response array
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

// Handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'get_transaction_details') {
    // Get transaction details
    $transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($transaction_id > 0) {
        // Get transaction details with charity name
        $query = "
            SELECT t.*, c.charity_name 
            FROM transactions t
            LEFT JOIN charities c ON t.charity_id = c.id
            WHERE t.id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $transaction_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $transaction = $result->fetch_assoc();
            $response = [
                'success' => true,
                'transaction' => $transaction
            ];
        } else {
            $response['message'] = 'Transaction not found';
        }
        
        $stmt->close();
    } else {
        $response['message'] = 'Invalid transaction ID';
    }
} elseif (isset($_POST['action']) && $_POST['action'] === 'update_transaction_status') {
    // Update transaction status
    $transaction_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    
    if ($transaction_id > 0 && in_array($status, ['pending', 'completed', 'failed'])) {
        $query = "UPDATE transactions SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('si', $status, $transaction_id);
        
        if ($stmt->execute()) {
            $response = [
                'success' => true,
                'message' => 'Transaction status updated successfully'
            ];
            
            // Set session message for page reload
            $_SESSION['success_message'] = 'Transaction status updated successfully';
        } else {
            $response['message'] = 'Failed to update transaction status: ' . $conn->error;
        }
        
        $stmt->close();
    } else {
        $response['message'] = 'Invalid transaction ID or status';
    }
}

// Close database connection
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
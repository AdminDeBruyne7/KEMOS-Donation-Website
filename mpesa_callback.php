<?php
// mpesa_callback.php

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "kemos_donation";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    http_response_code(500);
    exit();
}

// Log the raw callback data
$callbackData = file_get_contents('php://input');
error_log("M-Pesa Callback Data: " . $callbackData);

// Decode the callback JSON
$callbackResponse = json_decode($callbackData, true);
if (!$callbackResponse) {
    error_log("Invalid JSON in M-Pesa callback");
    http_response_code(400);
    exit();
}

// Check if the callback contains the STK Push result
if (!isset($callbackResponse['Body']['stkCallback'])) {
    error_log("Missing stkCallback in M-Pesa callback response");
    http_response_code(400);
    exit();
}

$resultCode = $callbackResponse['Body']['stkCallback']['ResultCode'] ?? null;
$resultDesc = $callbackResponse['Body']['stkCallback']['ResultDesc'] ?? 'Unknown error';
$checkoutRequestID = $callbackResponse['Body']['stkCallback']['CheckoutRequestID'] ?? null;

if (!$checkoutRequestID || $resultCode === null) {
    error_log("Missing CheckoutRequestID or ResultCode in callback");
    http_response_code(400);
    exit();
}

// Find the transaction in the database
$stmt = $conn->prepare("SELECT * FROM transactions WHERE checkout_request_id = ?");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    exit();
}
$stmt->bind_param("s", $checkoutRequestID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $transaction = $result->fetch_assoc();

    // Extract M-Pesa receipt number safely
    $mpesaReceipt = null;
    if (isset($callbackResponse['Body']['stkCallback']['CallbackMetadata']['Item'])) {
        foreach ($callbackResponse['Body']['stkCallback']['CallbackMetadata']['Item'] as $item) {
            if (isset($item['Name']) && $item['Name'] === 'MpesaReceiptNumber') {
                $mpesaReceipt = $item['Value'] ?? null;
                break;
            }
        }
    }

    // Update the transaction status based on the result code
    if ($resultCode == 0) {
        // Payment successful
        $status = 'completed';
        $stmt = $conn->prepare("UPDATE transactions SET status = ?, mpesa_receipt = ?, updated_at = NOW() WHERE checkout_request_id = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            http_response_code(500);
            exit();
        }
        $stmt->bind_param("sss", $status, $mpesaReceipt, $checkoutRequestID);
    } else {
        // Payment failed
        $status = 'failed';
        $notes = $resultDesc;
        $stmt = $conn->prepare("UPDATE transactions SET status = ?, notes = ?, updated_at = NOW() WHERE checkout_request_id = ?");
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            http_response_code(500);
            exit();
        }
        $stmt->bind_param("sss", $status, $notes, $checkoutRequestID);
    }
    if (!$stmt->execute()) {
        error_log("Update failed: " . $stmt->error);
        http_response_code(500);
        exit();
    }
    $stmt->close();
    error_log("Transaction updated: CheckoutRequestID=$checkoutRequestID, Status=$status, MpesaReceipt=$mpesaReceipt");
} else {
    error_log("Transaction not found for CheckoutRequestID: $checkoutRequestID");
}

$conn->close();

// Respond to M-Pesa to acknowledge receipt of the callback
header('Content-Type: application/json');
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback received successfully']);
exit();
?>
<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "kemos_donation";

$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to check M-PESA transaction status
function checkTransactionStatus($checkoutRequestID) {
    // Updated M-PESA API credentials
    $consumerKey = 'q5Y5vo4AzJJyAttdzm8eDogTSmigQNDw4KumPuIOwyVY22ig';
    $consumerSecret = 'csaIkHXzLI6hj1T1ecLjxxjIdZPWaFsG8mOWIyE47nVlGMGocg2IELGM1R15RWZT';
    $BusinessShortCode = '174379';
    $PassKey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
    $Timestamp = date('YmdHis');
    $Password = base64_encode($BusinessShortCode . $PassKey . $Timestamp);
    
    // Get access token
    $accessTokenURL = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    $ch = curl_init($accessTokenURL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic $credentials"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    $accessToken = $response['access_token'] ?? '';
    if (!$accessToken) {
        return ['success' => false, 'message' => 'Failed to obtain access token'];
    }
    
    // Query transaction status
    $queryURL = 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query';
    $queryData = [
        'BusinessShortCode' => $BusinessShortCode,
        'Password' => $Password,
        'Timestamp' => $Timestamp,
        'CheckoutRequestID' => $checkoutRequestID
    ];
    
    $ch = curl_init($queryURL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken", "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($queryData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $queryResponse = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    // Log the response for debugging
    error_log("M-PESA Query Response: " . json_encode($queryResponse));
    
    return $queryResponse;
}

// Check if we have a checkout request ID to verify
if (isset($_SESSION['checkout_request_id'])) {
    $checkoutRequestID = $_SESSION['checkout_request_id'];
    $response = checkTransactionStatus($checkoutRequestID);
    
    // Update transaction status in database if completed
    if (isset($response['ResultCode']) && $response['ResultCode'] == 0) {
        // Transaction successful
        $stmt = $conn->prepare("UPDATE transactions SET status = 'completed' WHERE checkout_request_id = ?");
        $stmt->bind_param("s", $checkoutRequestID);
        $stmt->execute();
        $stmt->close();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No transaction to verify']);
    exit;
}
?>


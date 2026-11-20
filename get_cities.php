<?php
session_start();
require_once 'config.php';

// Check if charity is logged in
if (!isset($_SESSION['charity_id']) || empty($_SESSION['charity_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get country parameter
$country = isset($_GET['country']) ? $_GET['country'] : '';

if (empty($country)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Country parameter is required']);
    exit;
}

// Connect to the database
$conn = connect_db();

// Get cities for the selected country
$cities_query = "SELECT DISTINCT city FROM charities WHERE country = ? ORDER BY city";
$stmt = $conn->prepare($cities_query);
$stmt->bind_param("s", $country);
$stmt->execute();
$result = $stmt->get_result();

$cities = [];
while ($row = $result->fetch_assoc()) {
    $cities[] = $row['city'];
}

$stmt->close();
$conn->close();

// Return cities as JSON
header('Content-Type: application/json');
echo json_encode($cities);
?>


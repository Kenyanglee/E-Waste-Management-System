<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'No key id provided']);
    exit();
}

$user_id = $_SESSION['user']['user_id'];
$rewardkey_id = $_GET['id'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit();
}

// Check that this rewardkey belongs to a redemption for this user
$sql = "SELECT rk.redeem_key FROM rewardkey rk
        JOIN redemption r ON rk.rewardkey_id = r.rewardkey_id
        WHERE rk.rewardkey_id = ? AND r.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $rewardkey_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    echo json_encode(['success' => true, 'redeem_key' => $row['redeem_key']]);
} else {
    echo json_encode(['success' => false, 'error' => 'Redeem key not found or not authorized']);
} 
<?php
session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isset($_POST['email'])) {
    echo json_encode(['available' => false, 'message' => 'Email is required']);
    exit();
}

$email = $_POST['email'];
$current_user_id = $_SESSION['user']['user_id'] ?? '';

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['available' => false, 'message' => 'Invalid email format']);
    exit();
}

try {
    // Check in user table (excluding current user)
    $sql = "SELECT COUNT(*) as count FROM user WHERE email = ? AND user_id != ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_count = $result->fetch_assoc()['count'];

    // Check in admin table
    $sql = "SELECT COUNT(*) as count FROM admin WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin_count = $result->fetch_assoc()['count'];

    // Email is available if it's not found in either table
    $is_available = ($user_count == 0 && $admin_count == 0);

    echo json_encode([
        'available' => $is_available,
        'message' => $is_available ? 'Email is available' : 'Email is already in use'
    ]);

} catch (Exception $e) {
    error_log('Email check error: ' . $e->getMessage());
    echo json_encode([
        'available' => false,
        'message' => 'Error checking email availability'
    ]);
} 
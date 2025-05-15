<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: auth.php');
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user_id = $_POST['user_id'];
    $address_number = $_POST['address_number'];

    // Build the update query based on the address number
    $fields = [
        'addressline1_' . $address_number,
        'addressline2_' . $address_number,
        'zipcode_' . $address_number,
        'city_' . $address_number,
        'state_' . $address_number
    ];

    $values = [];
    $params = [];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            $values[] = "$field = ?";
            $params[] = $_POST[$field];
        }
    }

    if (empty($values)) {
        throw new Exception('No fields to update');
    }

    $sql = "UPDATE user SET " . implode(', ', $values) . " WHERE user_id = ?";
    $params[] = $user_id;

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'message' => 'Address updated successfully'
    ]);

} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 
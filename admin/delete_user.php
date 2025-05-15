<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && $_POST['action'] === 'delete') {
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "nothing_wasted";

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Start transaction
        $conn->beginTransaction();

        // Delete user's submissions first (due to foreign key constraints)
        $stmt = $conn->prepare("DELETE FROM submission WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_POST['user_id']);
        $stmt->execute();

        // Delete user's feedback
        $stmt = $conn->prepare("DELETE FROM feedback WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_POST['user_id']);
        $stmt->execute();

        // Delete user's history
        $stmt = $conn->prepare("DELETE FROM history WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_POST['user_id']);
        $stmt->execute();

        // Finally, delete the user
        $stmt = $conn->prepare("DELETE FROM user WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $_POST['user_id']);
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);

    } catch(PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error deleting user: ' . $e->getMessage()]);
    }

    $conn = null;
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?> 
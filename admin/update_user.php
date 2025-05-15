<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: auth.php");
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=nothing_wasted", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Validate required fields
    $required_fields = ['user_id', 'user_name', 'email', 'contact_number', 'dob'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $user_id = $_POST['user_id'];
    $user_name = $_POST['user_name'];
    $email = $_POST['email'];
    $contact_number = $_POST['contact_number'];
    $dob = $_POST['dob'];
    $point = isset($_POST['point']) ? (int)$_POST['point'] : 0; // Default to 0 if not set

    // Handle profile picture
    $profile_pic = null;
    if (isset($_POST['remove_profile_pic']) && $_POST['remove_profile_pic'] == '1') {
        $profile_pic = null;
    } elseif (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        // Handle new profile picture upload
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if (!in_array($_FILES['profile_pic']['type'], $allowed_types)) {
            throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed.');
        }

        if ($_FILES['profile_pic']['size'] > $max_size) {
            throw new Exception('File size too large. Maximum size is 5MB.');
        }

        $profile_pic = file_get_contents($_FILES['profile_pic']['tmp_name']);
    }

    // Build the update query
    $sql = "UPDATE user SET 
            user_name = :user_name,
            email = :email,
            contact_number = :contact_number,
            dob = :dob,
            point = :point";

    // Add profile picture to query if it's being updated
    if ($profile_pic !== null) {
        $sql .= ", profile_pic = :profile_pic";
    } elseif (isset($_POST['remove_profile_pic']) && $_POST['remove_profile_pic'] == '1') {
        $sql .= ", profile_pic = NULL";
    }

    $sql .= " WHERE user_id = :user_id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':user_name', $user_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':contact_number', $contact_number);
    $stmt->bindParam(':dob', $dob);
    $stmt->bindParam(':point', $point);

    if ($profile_pic !== null) {
        $stmt->bindParam(':profile_pic', $profile_pic, PDO::PARAM_LOB);
    }

    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'User details updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 
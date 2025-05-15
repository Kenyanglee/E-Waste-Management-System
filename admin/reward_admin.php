<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: ../user/auth.php');
    exit();
}
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// AJAX endpoint for fetching reward data
if (isset($_GET['edit']) && (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) {
    header('Content-Type: application/json');
    
    $edit_id = $conn->real_escape_string($_GET['edit']);
    $result = $conn->query("SELECT reward_id, reward_name, description, points_needed FROM reward WHERE reward_id='$edit_id'");
    
    if ($result && $data = $result->fetch_assoc()) {
        echo json_encode($data);
    } else {
        echo json_encode(['error' => 'Failed to fetch reward data']);
    }
    exit;
}

// Helper: get next available reward_id
function getNextRewardId($conn) {
    $ids = [];
    $result = $conn->query("SELECT reward_id FROM reward");
    while ($row = $result->fetch_assoc()) {
        if (preg_match('/^reward(\d+)$/', $row['reward_id'], $m)) {
            $ids[(int)$m[1]] = true;
        }
    }
    $i = 1;
    while (isset($ids[$i])) $i++;
    return 'reward' . $i;
}
// Helper: get next available rewardkey_id
function getNextRewardKeyId($conn) {
    $ids = [];
    $result = $conn->query("SELECT rewardkey_id FROM rewardkey");
    while ($row = $result->fetch_assoc()) {
        if (preg_match('/^rewardkey(\d+)$/', $row['rewardkey_id'], $m)) {
            $ids[(int)$m[1]] = true;
        }
    }
    $i = 1;
    while (isset($ids[$i])) $i++;
    return 'rewardkey' . $i;
}
// Handle Add Reward
$add_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reward'])) {
    $upload_error = false;
    $debug_info = [];
    
    // Debug info for file upload
    if (isset($_FILES['picture'])) {
        $debug_info['upload_error'] = $_FILES['picture']['error'];
        $debug_info['tmp_name'] = $_FILES['picture']['tmp_name'];
        $debug_info['size'] = $_FILES['picture']['size'];
        $debug_info['type'] = $_FILES['picture']['type'];
    }

    $reward_id = getNextRewardId($conn);
    $reward_name = $conn->real_escape_string($_POST['reward_name']);
    $description = $conn->real_escape_string($_POST['description']);
    $points_needed = (int)$_POST['points_needed'];
    $status = 1;
    $date = date('Y-m-d');
    $image_blob = null;
    $quantity = 0;

    // Handle image upload with detailed error checking
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
        try {
            if ($_FILES['picture']['size'] > 16777216) { // 16MB in bytes
                header('Location: reward_admin.php?error=' . urlencode("File size exceeds 16MB limit"));
                exit();
            }
            
            $allowed_types = ['image/jpeg', 'image/png'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['picture']['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_types)) {
                header('Location: reward_admin.php?error=' . urlencode("Invalid file type. Only JPG and PNG are allowed."));
                exit();
            }
            
            $image_blob = file_get_contents($_FILES['picture']['tmp_name']);
            if ($image_blob === false) {
                header('Location: reward_admin.php?error=' . urlencode("Failed to read image file"));
                exit();
            }
            
        } catch (Exception $e) {
            header('Location: reward_admin.php?error=' . urlencode("Image upload error: " . $e->getMessage()));
            exit();
        }
    } else {
        header('Location: reward_admin.php?error=' . urlencode("Please select an image file"));
        exit();
    }

    // Handle redeem key text file
    $keys = [];
    if (isset($_FILES['redeem_txt']) && $_FILES['redeem_txt']['error'] === UPLOAD_ERR_OK) {
        $lines = file($_FILES['redeem_txt']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $key = trim($line);
            if ($key !== '') $keys[] = $key;
        }
        $quantity = count($keys);
    } else {
        header('Location: reward_admin.php?error=' . urlencode("Please select a redeem key text file"));
        exit();
    }

    // Insert into reward table with explicit picture column
    $stmt = $conn->prepare("INSERT INTO reward (reward_id, date, reward_name, picture, description, points_needed, quantity, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        header('Location: reward_admin.php?error=' . urlencode("Prepare failed: " . $conn->error));
        exit();
    }
    
    $stmt->bind_param('sssssiis', $reward_id, $date, $reward_name, $image_blob, $description, $points_needed, $quantity, $status);
    if (!$stmt->execute()) {
        header('Location: reward_admin.php?error=' . urlencode("Execute failed: " . $stmt->error));
        exit();
    }

    // Insert into rewardkey table
    foreach ($keys as $key) {
        $rewardkey_id = getNextRewardKeyId($conn);
        $is_used = 0;
        $user_id = NULL;
        $stmt2 = $conn->prepare("INSERT INTO rewardkey (rewardkey_id, redeem_key, is_used, date, user_id, reward_id) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt2) {
            header('Location: reward_admin.php?error=' . urlencode("Rewardkey prepare failed: " . $conn->error));
            exit();
        }
        $stmt2->bind_param('ssisss', $rewardkey_id, $key, $is_used, $date, $user_id, $reward_id);
        if (!$stmt2->execute()) {
            header('Location: reward_admin.php?error=' . urlencode("Rewardkey execute failed: " . $stmt2->error));
            exit();
        }
    }

    header('Location: reward_admin.php?success=1');
    exit();
}
// Handle Delete Reward
if (isset($_GET['delete'])) {
    $del_id = $conn->real_escape_string($_GET['delete']);
    if ($conn->query("DELETE FROM reward WHERE reward_id='$del_id'")) {
        header('Location: reward_admin.php?success=1');
    } else {
        header('Location: reward_admin.php?error=' . urlencode("Failed to delete reward"));
    }
    exit();
}
// Handle Delete Key
if (isset($_GET['delete_key'])) {
    $del_key_id = $conn->real_escape_string($_GET['delete_key']);
    
    // First check if the key exists and is not used
    $check_query = "SELECT is_used, reward_id FROM rewardkey WHERE rewardkey_id = '$del_key_id'";
    $check_result = $conn->query($check_query);
    
    if ($check_result && $check_result->num_rows > 0) {
        $key_data = $check_result->fetch_assoc();
        if ($key_data['is_used'] == 0) {
            // Get the reward_id before deleting the key
            $reward_id = $key_data['reward_id'];
            
            // Delete the key
            if ($conn->query("DELETE FROM rewardkey WHERE rewardkey_id = '$del_key_id'")) {
                // Check if this was the last unused key for this reward
                $remaining_keys_query = "SELECT COUNT(*) as remaining FROM rewardkey WHERE reward_id = '$reward_id' AND is_used = 0";
                $remaining_result = $conn->query($remaining_keys_query);
                $remaining_data = $remaining_result->fetch_assoc();
                
                // If no unused keys remaining, update reward status to 0
                if ($remaining_data['remaining'] == 0) {
                    $conn->query("UPDATE reward SET status = 0 WHERE reward_id = '$reward_id'");
                }
                
                header('Location: reward_admin.php?success=1');
            } else {
                header('Location: reward_admin.php?error=' . urlencode("Failed to delete key"));
            }
        } else {
            header('Location: reward_admin.php?error=' . urlencode("Cannot delete used key"));
        }
    } else {
        header('Location: reward_admin.php?error=' . urlencode("Key not found"));
    }
    exit();
}
// Handle Multiple Key Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['operation']) && $_POST['operation'] === 'delete_selected' && isset($_POST['delete_keys']) && is_array($_POST['delete_keys'])) {
    $success = true;
    $reward_ids = [];
    
    // First verify all keys are unused
    foreach ($_POST['delete_keys'] as $key_id) {
        $key_id = $conn->real_escape_string($key_id);
        $check_query = "SELECT is_used, reward_id FROM rewardkey WHERE rewardkey_id = '$key_id'";
        $check_result = $conn->query($check_query);
        
        if ($check_result && $check_result->num_rows > 0) {
            $key_data = $check_result->fetch_assoc();
            if ($key_data['is_used'] == 0) {
                $reward_ids[$key_data['reward_id']] = true;
            } else {
                $success = false;
                break;
            }
        } else {
            $success = false;
            break;
        }
    }
    
    if ($success) {
        // Delete all selected keys
        foreach ($_POST['delete_keys'] as $key_id) {
            $key_id = $conn->real_escape_string($key_id);
            if (!$conn->query("DELETE FROM rewardkey WHERE rewardkey_id = '$key_id'")) {
                $success = false;
                break;
            }
        }
        
        // Check remaining keys for each affected reward
        if ($success) {
            foreach (array_keys($reward_ids) as $reward_id) {
                $reward_id = $conn->real_escape_string($reward_id);
                $remaining_keys_query = "SELECT COUNT(*) as remaining FROM rewardkey WHERE reward_id = '$reward_id' AND is_used = 0";
                $remaining_result = $conn->query($remaining_keys_query);
                $remaining_data = $remaining_result->fetch_assoc();
                
                // If no unused keys remaining, update reward status to 0
                if ($remaining_data['remaining'] == 0) {
                    $conn->query("UPDATE reward SET status = 0 WHERE reward_id = '$reward_id'");
                }
            }
            
            header('Location: reward_admin.php?success=1');
        } else {
            header('Location: reward_admin.php?error=' . urlencode("Failed to delete some keys"));
        }
    } else {
        header('Location: reward_admin.php?error=' . urlencode("Cannot delete used keys"));
    }
    exit();
}
// Handle Edit Reward
$edit_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_reward'])) {
    $upload_error = false;
    $debug_info = [];
    
    $reward_id = $conn->real_escape_string($_POST['reward_id']);
    $reward_name = $conn->real_escape_string($_POST['reward_name']);
    $description = $conn->real_escape_string($_POST['description']);
    $points_needed = (int)$_POST['points_needed'];
    
    // Handle image upload if provided
    $image_update = '';
    $params = [];
    $types = '';
    
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
        try {
            if ($_FILES['picture']['size'] > 16777216) {
                throw new Exception("File size exceeds 16MB limit");
            }
            
            $allowed_types = ['image/jpeg', 'image/png'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['picture']['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception("Invalid file type. Only JPG and PNG are allowed.");
            }
            
            $image_blob = file_get_contents($_FILES['picture']['tmp_name']);
            if ($image_blob === false) {
                throw new Exception("Failed to read image file");
            }
            
            $image_update = ', picture = ?';
            $params[] = $image_blob;
            $types .= 's';
            
        } catch (Exception $e) {
            header('Location: reward_admin.php?error=' . urlencode($e->getMessage()));
            exit();
        }
    }
    
    if (!$upload_error) {
        $sql = "UPDATE reward SET reward_name = ?, description = ?, points_needed = ? $image_update WHERE reward_id = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            header('Location: reward_admin.php?error=' . urlencode("Prepare failed: " . $conn->error));
            exit();
        } else {
            $params = array_merge([$reward_name, $description, $points_needed], $params, [$reward_id]);
            $types = 'ssi' . $types . 's';
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                header('Location: reward_admin.php?error=' . urlencode("Execute failed: " . $stmt->error));
                exit();
            } else {
                header('Location: reward_admin.php?success=1');
                exit();
            }
        }
    }
}
// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $response = ['success' => false, 'message' => ''];
    
    if (!isset($_POST['reward_id']) || !isset($_POST['status'])) {
        $response['message'] = 'Missing required parameters';
        echo json_encode($response);
        exit;
    }
    
    $reward_id = $_POST['reward_id'];
    $status = $_POST['status'];
    
    try {
        // Check if there are any available keys (is_used = 0) for this reward
        $check_keys_query = "SELECT COUNT(*) as available_keys FROM rewardkey WHERE reward_id = ? AND is_used = 0";
        $check_stmt = $conn->prepare($check_keys_query);
        $check_stmt->bind_param('s', $reward_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $key_data = $result->fetch_assoc();
        
        // If trying to activate (status = 1) and no available keys, prevent the update
        if ($status == 1 && $key_data['available_keys'] == 0) {
            $response['message'] = 'Cannot activate reward: No available keys';
            echo json_encode($response);
            exit;
        }
        
        // Proceed with status update if conditions are met
        $stmt = $conn->prepare("UPDATE reward SET status = ? WHERE reward_id = ?");
        $stmt->bind_param('is', $status, $reward_id);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Status updated successfully';
        } else {
            $response['message'] = 'Failed to update status';
        }
    } catch (Exception $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}
// Fetch rewards
$rewards = $conn->query("SELECT r.*, 
                        COUNT(CASE WHEN rk.is_used = 0 THEN 1 END) as key_count 
                        FROM reward r 
                        LEFT JOIN rewardkey rk ON r.reward_id = rk.reward_id 
                        GROUP BY r.reward_id 
                        ORDER BY r.status DESC, r.reward_name ASC");
// For editing
$edit_data = null;
if (isset($_GET['edit']) && (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest')) {
    $edit_id = $conn->real_escape_string($_GET['edit']);
    $result = $conn->query("SELECT * FROM reward WHERE reward_id='$edit_id'");
    $edit_data = $result->fetch_assoc();
}

// Handle Add Keys
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_keys'])) {
    $reward_id = $conn->real_escape_string($_POST['reward_id']);
    $date = date('Y-m-d');
    $is_used = 0;
    $user_id = NULL;
    
    // Handle redeem key text file
    if (isset($_FILES['redeem_keys']) && $_FILES['redeem_keys']['error'] === UPLOAD_ERR_OK) {
        $lines = file($_FILES['redeem_keys']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $success_count = 0;
        $error_count = 0;
        
        foreach ($lines as $line) {
            $key = trim($line);
            if ($key !== '') {
                $rewardkey_id = getNextRewardKeyId($conn);
                $stmt = $conn->prepare("INSERT INTO rewardkey (rewardkey_id, redeem_key, is_used, date, user_id, reward_id) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param('ssisss', $rewardkey_id, $key, $is_used, $date, $user_id, $reward_id);
                    if ($stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($success_count > 0) {
            header('Location: reward_admin.php?success=1');
        } else {
            header('Location: reward_admin.php?error=' . urlencode("Failed to add keys"));
        }
    } else {
        header('Location: reward_admin.php?error=' . urlencode("Please select a valid text file"));
    }
    exit();
}

// Get current admin information
$admin_id = $_SESSION['admin']['admin_id'];
$stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$has_privilege = isset($admin['privilege']) && $admin['privilege'] == 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reward Management - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        :root {
            --sidebar-width: 280px;
            --primary-color: #50B88E;
            --secondary-color: #4A90E2;
            --text-primary: #2D3748;
            --text-secondary: #718096;
            --bg-light: #F7FAFC;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --transition-default: all 0.3s ease;
            --max-width: 1920px;
            --content-width: 1640px;
        }
        body { display: flex; min-height: 100vh; background-color: var(--bg-light); color: var(--text-primary); max-width: var(--max-width); margin: 0 auto; overflow-x: hidden; }
        .sidebar { width: var(--sidebar-width); background-color: var(--primary-color); padding: 24px 24px 32px; color: white; position: fixed; height: 100vh; overflow-y: auto; transition: transform 0.3s ease; display: flex; flex-direction: column; justify-content: space-between; z-index: 10; }
        .logo { padding: 4px 12px; margin-bottom: 32px; }
        .logo img { width: 100%; height: auto; }
        .menu { display: flex; flex-direction: column; gap: 12px; padding: 8px 16px; }
        .menu-item { display: flex; align-items: center; gap: 16px; padding: 16px; color: white; text-decoration: none; border-radius: 8px; transition: background-color 0.3s; }
        .menu-item:hover { background-color: rgba(255, 255, 255, 0.1); }
        .menu-item.active { background-color: rgba(255, 255, 255, 0.2); }
        .main-content { flex: 1; margin-left: var(--sidebar-width); padding: 20px; max-width: var(--content-width); width: 100%; box-sizing: border-box; }
        .top-bar { display: flex; justify-content: flex-end; align-items: center; padding: 10px 20px; background-color: #D2CECE; border-radius: 8px; margin-bottom: 20px; }
        .user-actions { display: flex; align-items: center; gap: 32px; }
        .notification { padding: 8px; cursor: pointer; transition: all 0.3s ease; }
        .notification i { font-size: 24px; transition: color 0.3s ease; }
        .notification:hover { transform: scale(1.1); }
        .notification:hover i { color: var(--primary-color); }
        .account { width: 40px; height: 40px; border-radius: 50%; background-color: #C4C4C4; overflow: hidden; cursor: pointer; transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out; }
        .account:hover { transform: scale(1.1); animation: pulse-profile 2.5s infinite; }
        .account img { width: 100%; height: 100%; object-fit: cover; }
        .card { background: white; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: var(--card-shadow); transition: var(--transition-default); border: 1px solid rgba(0, 0, 0, 0.05); max-width: 100%; box-sizing: border-box; }
        .card-header { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px solid var(--bg-light); }
        .card-header h2 { margin: 0; font-size: 1.5rem; color: var(--text-primary); }
        .search-bar { display: flex; align-items: center; gap: 12px; background-color: white; padding: 12px 20px; border-radius: 8px; width: 400px; max-width: 100%; transition: all 0.3s ease; box-sizing: border-box; border: 1px solid #E2E8F0; }
        .search-bar i { color: var(--text-secondary); font-size: 1.1rem; }
        .search-bar input { border: none; outline: none; width: 100%; color: var(--text-primary); font-size: 1rem; }
        .table-container { overflow-x: auto; max-width: 100%; box-sizing: border-box; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #E2E8F0; word-wrap: break-word; }
        th { background-color: var(--bg-light); font-weight: 600; color: var(--text-primary); }
        tr:hover { background-color: var(--bg-light); }
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; }
        .page-button { padding: 8px 16px; border: 1px solid #E2E8F0; border-radius: 4px; background-color: white; cursor: pointer; transition: var(--transition-default); }
        .page-button:hover { background-color: var(--bg-light); }
        .page-button.active { background-color: var(--primary-color); color: white; border-color: var(--primary-color); }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal.show { opacity: 1; }
        .modal-content { background-color: white; border-radius: 12px; padding: 24px; width: 600px; max-width: 90%; max-height: 90vh; overflow-y: auto; transform: scale(0.7); opacity: 0; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .modal.show .modal-content { transform: scale(1); opacity: 1; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
        .modal-header h3 { margin: 0; color: var(--text-primary); font-size: 1.25rem; }
        .close-button { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary); padding: 0; }
        .modal-body { padding: 20px; color: var(--text-secondary); }
        .modal-footer { padding: 20px; border-top: 1px solid #E2E8F0; display: flex; justify-content: flex-end; gap: 12px; }
        .alert-container { position: fixed; top: 20px; right: 20px; z-index: 2000; }
        .alert { padding: 12px 24px; border-radius: 50px; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; animation: slideIn 0.3s ease, fadeOut 0.3s ease 2.7s forwards; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .alert-success { background-color: #C6F6D5; color: #2F855A; }
        .alert-error { background-color: #FED7D7; color: #C53030; }
        .alert-info { background-color: #E5E7EB; color: #374151; }
        .alert i { font-size: 1.2rem; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
        @keyframes pulse-profile {
            0% {
                box-shadow: 0 0 10px 5px rgba(255, 255, 255, 0.8);
            }
            50% {
                box-shadow: 0 0 20px 10px rgba(255, 255, 255, 0.6);
            }
            100% {
                box-shadow: 0 0 10px 5px rgba(255, 255, 255, 0.8);
            }
        }
        .action-btn {
            background: none;
            border: none;
            padding: 8px;
            cursor: pointer;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .edit-btn {
            color: #4A90E2;
        }

        .edit-btn:hover {
            background-color: rgba(74, 144, 226, 0.1);
        }

        .delete-btn {
            color: #DC2626;
        }

        .delete-btn:hover {
            background-color: rgba(220, 38, 38, 0.1);
        }

        .action-btn i {
            font-size: 1.1rem;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .action-button {
            padding: 6px 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            transition: var(--transition-default);
        }
        .edit-button {
            background-color: var(--secondary-color);
            color: white;
        }
        .delete-button {
            background-color: #F56565;
            color: white;
        }
        .action-button:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        /* Toggle Switch Styles */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 36px;
            height: 20px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #F56565;
            transition: .4s;
            border-radius: 20px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #50B88E;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(16px);
        }
        .modal-button {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition-default);
        }

        .cancel-button {
            background-color: var(--bg-light);
            color: var(--text-primary);
        }

        .confirm-button {
            background-color: var(--primary-color);
            color: white;
        }

        .modal-button:hover {
            transform: translateY(-2px);
        }

        .cancel-button:hover {
            background-color: #E2E8F0;
        }

        .confirm-button:hover {
            background-color: #3d8b6d;
        }
        .select2-container {
            width: 100% !important;
        }
        .select2-container .select2-selection--single {
            height: 38px;
            border: 1px solid #CBD5E0;
            border-radius: 4px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px;
            padding-left: 12px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        /* Responsive Styles */
        @media (max-width: 1400px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .reward-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .reward-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
                padding: 24px 12px;
            }
            
            .sidebar .logo {
                display: none;
            }
            
            .sidebar .menu-item span {
                display: none;
            }
            
            .sidebar .menu-item {
                justify-content: center;
                padding: 16px;
            }
            
            .sidebar .menu-item i {
                margin: 0;
                font-size: 1.2rem;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .submenu {
                position: absolute;
                left: 80px;
                top: 0;
                background: var(--primary-color);
                width: 200px;
                border-radius: 0 8px 8px 0;
                box-shadow: 4px 4px 10px rgba(0,0,0,0.1);
            }
            
            .submenu .menu-item {
                padding: 12px 16px;
            }
            
            .submenu .menu-item span {
                display: inline;
            }

            .table-responsive {
                overflow-x: auto;
            }

            .table th, .table td {
                min-width: 120px;
            }

            .reward-details {
                flex-direction: column;
            }

            .reward-section {
                width: 100%;
            }

            .gift-card-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                padding: 10px;
            }
            
            .user-actions {
                gap: 16px;
            }
            
            .account {
                width: 32px;
                height: 32px;
            }
            
            .card {
                padding: 16px;
            }
            
            .chart-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            
            .search-bar {
                width: 100%;
            }
            
            .filter-btn-pro {
                width: 100%;
                justify-content: center;
            }
            
            .reward-grid {
                grid-template-columns: 1fr;
            }

            .reward-stats {
                grid-template-columns: 1fr;
            }

            .gift-card-grid {
                grid-template-columns: 1fr;
            }

            .table th, .table td {
                padding: 8px;
                font-size: 0.9rem;
            }

            .btn {
                padding: 6px 12px;
                font-size: 0.9rem;
            }

            .modal-content {
                width: 95%;
                padding: 15px;
            }

            .reward-form {
                grid-template-columns: 1fr;
            }

            .form-group {
                margin-bottom: 15px;
            }

            .form-row {
                flex-direction: column;
            }

            .form-actions {
                flex-direction: column;
                gap: 10px;
            }

            .gift-card-preview {
                width: 100%;
                height: auto;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 10px;
            }
            
            .card {
                padding: 12px;
            }
            
            .reward-card {
                padding: 12px;
            }
            
            .reward-card h3 {
                font-size: 1rem;
            }
            
            .reward-card p {
                font-size: 0.8rem;
            }

            .table th, .table td {
                padding: 6px;
                font-size: 0.8rem;
            }

            .btn {
                padding: 4px 8px;
                font-size: 0.8rem;
            }

            .modal-content {
                padding: 10px;
            }

            .modal-header h3 {
                font-size: 1.1rem;
            }

            .reward-meta {
                flex-direction: column;
                gap: 5px;
            }

            .status-badge {
                font-size: 0.8rem;
                padding: 4px 8px;
            }

            .form-actions button {
                width: 100%;
            }

            .gift-card-item {
                padding: 10px;
            }

            .points-value {
                font-size: 1.2rem;
            }

            .gift-card-actions {
                flex-direction: column;
                gap: 5px;
            }
        }

        /* Print Styles */
        @media print {
            .sidebar,
            .top-bar,
            .filter-btn-pro,
            .search-bar {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }

            .table {
                width: 100%;
                border-collapse: collapse;
            }

            .table th, .table td {
                border: 1px solid #ddd;
            }

            .reward-details {
                page-break-inside: avoid;
            }

            .btn, .action-button {
                display: none;
            }

            .gift-card-grid {
                page-break-inside: avoid;
            }

            .gift-card-preview {
                max-width: 300px;
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <img src="../user/assets/homepage/logo.png" alt="Nothing Wasted Logo">
        </div>
        <div class="menu">
            <a href="admin.php" class="menu-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="report.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="userlist.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>User List</span>
            </a>
            <a href="quotation.php" class="menu-item">
                <i class="fas fa-file-alt"></i>
                <span>Quotation</span>
            </a>
            <a href="feedbackmanagement.php" class="menu-item">
                <i class="fas fa-comment-alt"></i>
                <span>Feedback</span>
            </a>
            <a href="deliverymanagement.php" class="menu-item">
                <i class="fas fa-clock"></i>
                <span>Delivery Management</span>
            </a>
            <a href="inventory.php" class="menu-item">
                <i class="fas fa-box"></i>
                <span>Inventory</span>
            </a>
            <a href="submission_admin.php" class="menu-item">
                <i class="fas fa-briefcase"></i>
                <span>E-Waste Submission</span>
            </a>
            <a href="payment_admin.php" class="menu-item active">
                <i class="fas fa-shopping-cart"></i>
                <span>Payment</span>
                <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
            </a>
            <div class="submenu" style="display: block; padding-left: 40px;">
                <a href="reward_admin.php" class="menu-item active">
                    <i class="fas fa-gift"></i>
                    <span>Reward</span>
                </a>
            </div>
        </div>
        <div class="bottom-menu">
            <a href="settings.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Log Out</span>
            </a>
        </div>
    </div>
    <div class="main-content">
        <div class="top-bar">
            <div class="user-actions">
                <div class="account">
                    <a href="settings.php">
                        <img src="../user/assets/homepage/account.png" alt="User Avatar">
                    </a>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h2>Reward Management</h2>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by Reward ID, Name, or Description...">
                </div>
                <button id="addRewardBtn" style="margin-left:auto; padding:10px 24px; background:#50B88E; color:white; border:none; border-radius:6px; cursor:pointer; font-size:1rem; font-weight:600;">+ Add Reward</button>
            </div>
            <div class="table-container">
                <table id="rewardTable">
                    <thead>
                        <tr>
                            <th>Reward ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Points Needed</th>
                            <th>Status</th>
                            <th>Quantity</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $rewards->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['reward_id']) ?></td>
                            <td><?= htmlspecialchars($row['reward_name']) ?></td>
                            <td><?= htmlspecialchars($row['description']) ?></td>
                            <td><?= htmlspecialchars($row['points_needed']) ?></td>
                            <td>
                                <?php if ($row['status'] == 1): ?>
                                    <label class="toggle-switch">
                                        <input type="checkbox" class="status-toggle" 
                                               data-reward-id="<?= htmlspecialchars($row['reward_id']) ?>"
                                               checked>
                                        <span class="toggle-slider"></span>
                                    </label>
                                <?php else: ?>
                                    <label class="toggle-switch">
                                        <input type="checkbox" class="status-toggle" 
                                               data-reward-id="<?= htmlspecialchars($row['reward_id']) ?>">
                                        <span class="toggle-slider"></span>
                                    </label>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['key_count']) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button onclick="openEditModal('<?= htmlspecialchars($row['reward_id']) ?>')" class="action-button edit-button" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteReward('<?= htmlspecialchars($row['reward_id']) ?>')" class="action-button delete-button" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <!-- Add Reward Modal -->
            <div class="modal" id="addRewardModal">
                <div class="modal-content" style="max-width:650px;">
                    <div class="modal-header">
                        <h3>Add New Reward</h3>
                        <button class="close-button" id="closeAddRewardModal">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" id="addRewardForm" enctype="multipart/form-data">
                            <div style="margin-bottom:20px;">
                                <label style="display:block; margin-bottom:8px; font-weight:500;">Reward Name</label>
                                <input type="text" name="reward_name" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #CBD5E0;">
                            </div>
                            <div style="margin-bottom:20px;">
                                <label style="display:block; margin-bottom:8px; font-weight:500;">Points Needed</label>
                                <input type="number" name="points_needed" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #CBD5E0;">
                            </div>
                            <div style="margin-bottom:20px;">
                                <label style="display:block; margin-bottom:8px; font-weight:500;">Description</label>
                                <textarea name="description" required style="width:100%; min-height:80px; padding:8px; border-radius:4px; border:1px solid #CBD5E0; resize:vertical;"></textarea>
                            </div>
                            <div style="margin-bottom:20px; text-align:center;">
                                <label style="display:block; margin-bottom:8px; font-weight:500; text-align:left;">Reward Image (JPG, PNG, max 16MB)</label> 
                                <div class="file-upload-container" style="display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                    <label class="file-upload-dropzone" style="cursor:pointer; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                        <img src="../user/assets/edit/upload.png" alt="Upload" style="filter: grayscale(1) brightness(0.6); width:64px; height:64px; margin-bottom:8px;">
                                        <span style="color:#718096;">Upload Your Image Here</span>
                                        <input type="file" name="picture" accept=".jpg,.jpeg,.png" class="file-upload-input" id="addPictureInput" style="display:none;">
                                    </label>
                                    <div class="file-upload-note" style="margin-top:8px; color:#718096;">Maximum file size: 16MB</div>
                                    <div class="file-name" id="rewardFileName" style="margin-top:4px;"></div>
                                </div>
                            </div>
                            <!-- Redeem Key Text File Upload -->
                            <div style="margin-bottom:20px; text-align:center;">
                                <label style="display:block; margin-bottom:8px; font-weight:500; text-align:left;">
                                    Redeem Key Text File (.txt, one key per line, max 2MB)
                                </label>
                                <div class="file-upload-container" style="display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                    <label class="file-upload-dropzone" id="txtDropzone" style="cursor:pointer; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                        <img src="../user/assets/edit/upload.png" alt="Upload" style="filter: grayscale(1) brightness(0.6); width:64px; height:64px; margin-bottom:8px;">
                                        <span style="color:#718096;">Upload Your .txt File Here</span>
                                        <input type="file" name="redeem_txt" accept=".txt" class="file-upload-input" id="redeemTxtInput" style="display:none;">
                                    </label>
                                    <div class="file-upload-note" style="margin-top:8px; color:#718096;">Maximum file size: 2MB</div>
                                    <div class="file-name" id="redeemTxtFileName" style="margin-top:4px;"></div>
                                </div>
                            </div>
                            <input type="hidden" name="reward_id" value="<?= uniqid('reward_') ?>">
                            <input type="hidden" name="status" value="1">
                            <div class="modal-footer">
                                <button type="submit" name="add_reward" style="padding:8px 16px; background:#50B88E; color:#fff; border:none; border-radius:4px; cursor:pointer;">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Edit Reward Modal -->
            <div class="modal" id="editRewardModal">
                <div class="modal-content" style="max-width:650px;">
                    <div class="modal-header">
                        <h3>Reward Card</h3>
                        <button class="close-button" onclick="closeEditModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form method="POST" id="editRewardForm" enctype="multipart/form-data">
                            <input type="hidden" name="reward_id" id="edit_reward_id">
                            <div style="margin-bottom:20px;">
                                <label style="display:block; margin-bottom:8px; font-weight:500;">Reward Name</label>
                                <input type="text" name="reward_name" id="edit_reward_name" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #CBD5E0;">
                            </div>
                            <div style="margin-bottom:20px;">
                                <label style="display:block; margin-bottom:8px; font-weight:500;">Points Needed</label>
                                <input type="number" name="points_needed" id="edit_points_needed" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #CBD5E0;">
                            </div>
                            <div style="margin-bottom:20px;">
                                <label style="display:block; margin-bottom:8px; font-weight:500;">Description</label>
                                <textarea name="description" id="edit_description" required style="width:100%; min-height:80px; padding:8px; border-radius:4px; border:1px solid #CBD5E0; resize:vertical;"></textarea>
                            </div>
                            <div style="margin-bottom:20px; text-align:center;">
                                <label style="display:block; margin-bottom:8px; font-weight:500; text-align:left;">Reward Image (JPG, PNG, max 16MB)</label>
                                <div class="file-upload-container" style="display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                    <label class="file-upload-dropzone" style="cursor:pointer; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                        <img src="../user/assets/edit/upload.png" alt="Upload" style="filter: grayscale(1) brightness(0.6); width:64px; height:64px; margin-bottom:8px;">
                                        <span style="color:#718096;">Upload New Image (Optional)</span>
                                        <input type="file" name="picture" accept=".jpg,.jpeg,.png" class="file-upload-input" id="editPictureInput" style="display:none;">
                                    </label>
                                    <div class="file-upload-note" style="margin-top:8px; color:#718096;">Maximum file size: 16MB</div>
                                    <div class="file-name" id="editRewardFileName" style="margin-top:4px;"></div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="edit_reward" style="padding:8px 16px; background:#50B88E; color:#fff; border:none; border-radius:4px; cursor:pointer;">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- Delete Reward Confirmation Modal -->
            <div class="modal" id="deleteModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Confirm Delete</h3>
                        <button class="close-button" onclick="closeDeleteModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this reward? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="modal-button cancel-button" onclick="closeDeleteModal()">Cancel</button>
                        <button class="modal-button confirm-button" onclick="confirmDeleteReward()">Delete</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Gift Card Keys Management Section -->
        <div class="card" style="margin-top: 40px;">
            <div class="card-header">
                <h2>Reward Keys Management</h2>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchKeysInput" placeholder="Search by Key ID, Redeem Key, or Reward ID...">
                </div>
                <button id="addKeysBtn" style="margin-left:auto; padding:10px 24px; background:#50B88E; color:white; border:none; border-radius:6px; cursor:pointer; font-size:1rem; font-weight:600;">+ Add Keys</button>
                <button id="deleteSelectedBtn" style="margin-left:10px; padding:10px 24px; background:#F56565; color:white; border:none; border-radius:6px; cursor:pointer; font-size:1rem; font-weight:600; display:none;">Delete Selected</button>
            </div>
            <div class="table-container">
                <table id="rewardKeysTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="selectAllKeys" style="cursor: pointer;">
                            </th>
                            <th>Key ID</th>
                            <th>Redeem Key</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>User ID</th>
                            <th>Reward ID</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch reward keys with related information
                        $keys_query = "SELECT rk.*, r.reward_name 
                                     FROM rewardkey rk 
                                     LEFT JOIN reward r ON rk.reward_id = r.reward_id 
                                     ORDER BY rk.date DESC";
                        $keys_result = $conn->query($keys_query);
                        
                        while ($key_row = $keys_result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td>
                                <?php if ($key_row['is_used'] == 0): ?>
                                <input type="checkbox" class="key-checkbox" data-key-id="<?= htmlspecialchars($key_row['rewardkey_id']) ?>" style="cursor: pointer;">
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($key_row['rewardkey_id']) ?></td>
                            <td>
                                <?php if ($has_privilege): ?>
                                <button onclick="viewKey('<?= htmlspecialchars($key_row['redeem_key']) ?>')" class="action-button" style="background: none; border: none; color: #4A90E2; cursor: pointer;" title="View Key">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php else: ?>
                                <button onclick="showNoPermissionAlert()" class="action-button" style="background: none; border: none; color: #A0AEC0; cursor: not-allowed;" title="No Permission">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($key_row['is_used'] == 1): ?>
                                    <span style="color: #F56565;">Used</span>
                                <?php else: ?>
                                    <span style="color: #50B88E;">Available</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($key_row['date']) ?></td>
                            <td><?= $key_row['user_id'] ? htmlspecialchars($key_row['user_id']) : 'Not Assigned' ?></td>
                            <td>
                                <?= htmlspecialchars($key_row['reward_id']) ?>
                                <br>
                                <small style="color: #718096;"><?= htmlspecialchars($key_row['reward_name']) ?></small>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($key_row['is_used'] == 0): ?>
                                    <button onclick="deleteKey('<?= htmlspecialchars($key_row['rewardkey_id']) ?>')" class="action-button delete-button" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Delete Key Confirmation Modal -->
    <div class="modal" id="deleteKeyModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete Key</h3>
                <button class="close-button" onclick="closeDeleteKeyModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this reward key? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-button cancel-button" onclick="closeDeleteKeyModal()">Cancel</button>
                <button class="modal-button confirm-button" onclick="confirmDeleteKey()">Delete</button>
            </div>
        </div>
    </div>
    <!-- View Key Modal -->
    <div class="modal" id="viewKeyModal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Redeem Key</h3>
                <button class="close-button" onclick="closeViewKeyModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div style="background: #F7FAFC; padding: 20px; border-radius: 8px; text-align: center;">
                    <p id="viewKeyContent" style="font-family: monospace; font-size: 1.2em; margin: 0; word-break: break-all;"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="modal-button confirm-button" onclick="copyKeyToClipboard()">
                    <i class="fas fa-copy"></i> Copy Key
                </button>
            </div>
        </div>
    </div>
    <!-- Add Keys Modal -->
    <div class="modal" id="addKeysModal">
        <div class="modal-content" style="max-width:650px;">
            <div class="modal-header">
                <h3>Add Reward Keys</h3>
                <button class="close-button" id="closeAddKeysModal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addKeysForm" enctype="multipart/form-data">
                    <div style="margin-bottom:20px;">
                        <label style="display:block; margin-bottom:8px; font-weight:500;">Select Reward</label>
                        <select name="reward_id" required style="width:100%; padding:8px; border-radius:4px; border:1px solid #CBD5E0;" class="select2">
                            <option value="">Select a Reward...</option>
                            <?php
                            // Get all rewards, regardless of status
                            $rewards_query = "SELECT reward_id, reward_name, status FROM reward ORDER BY reward_name ASC";
                            $rewards_result = $conn->query($rewards_query);
                            
                            if (!$rewards_result) {
                                error_log("Query failed: " . $conn->error);
                            } else {
                                while ($reward = $rewards_result->fetch_assoc()): 
                                    $status_text = $reward['status'] == 1 ? ' (Active)' : ' (Inactive)';
                                ?>
                                <option value="<?= htmlspecialchars($reward['reward_id']) ?>">
                                    <?= htmlspecialchars($reward['reward_name']) . $status_text ?>
                                </option>
                                <?php endwhile; 
                            }
                            ?>
                        </select>
                    </div>
                    <div style="margin-bottom:20px; text-align:center;">
                        <label style="display:block; margin-bottom:8px; font-weight:500; text-align:left;">
                            Redeem Keys Text File (.txt, one key per line, max 2MB)
                        </label>
                        <div class="file-upload-container" style="display:flex; flex-direction:column; align-items:center; justify-content:center;">
                            <label class="file-upload-dropzone" id="keysDropzone" style="cursor:pointer; display:flex; flex-direction:column; align-items:center; justify-content:center;">
                                <img src="../user/assets/edit/upload.png" alt="Upload" style="filter: grayscale(1) brightness(0.6); width:64px; height:64px; margin-bottom:8px;">
                                <span style="color:#718096;">Upload Your .txt File Here</span>
                                <input type="file" name="redeem_keys" accept=".txt" class="file-upload-input" id="redeemKeysInput" style="display:none;">
                            </label>
                            <div class="file-upload-note" style="margin-top:8px; color:#718096;">Maximum file size: 2MB</div>
                            <div class="file-name" id="redeemKeysFileName" style="margin-top:4px;"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="add_keys" style="padding:8px 16px; background:#50B88E; color:#fff; border:none; border-radius:4px; cursor:pointer;">Add Keys</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Delete Selected Keys Confirmation Modal -->
    <div class="modal" id="deleteSelectedModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="close-button" onclick="closeDeleteSelectedModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the selected keys? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button class="modal-button cancel-button" onclick="closeDeleteSelectedModal()">Cancel</button>
                <button class="modal-button confirm-button" onclick="confirmDeleteSelected()">Delete</button>
            </div>
        </div>
    </div>
    <!-- Alert Notification -->
    <div class="alert-container" id="alertContainer"></div>
    <script>
        // Show success/error alert if redirected with params
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('success')) {
            showAlert('Operation completed successfully', 'success');
            // Remove the param from the URL without reloading
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('success');
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }
        } else if (urlParams.has('error')) {
            showAlert(urlParams.get('error'), 'error');
            // Remove the param from the URL without reloading
            if (window.history.replaceState) {
                const url = new URL(window.location);
                url.searchParams.delete('error');
                window.history.replaceState({}, document.title, url.pathname + url.search);
            }
        }

        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            
            const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            alert.innerHTML = `
                <i class="fas fa-${icon}"></i>
                <span>${message}</span>
            `;
            
            alertContainer.appendChild(alert);
            
            // Remove alert after animation
            setTimeout(() => {
                alert.remove();
            }, 3000);
        }
        // Sidebar submenu toggle logic (same as payment_admin.php)
        const menuItems = document.querySelectorAll('.menu-item');
        let paymentMenuItem = null;
        menuItems.forEach(item => {
            if (item.querySelector('.fa-shopping-cart')) {
                paymentMenuItem = item;
            }
        });
        const paymentSubmenu = paymentMenuItem ? paymentMenuItem.nextElementSibling : null;
        const chevronIcon = paymentMenuItem ? paymentMenuItem.querySelector('.fa-chevron-down') : null;
        if (paymentMenuItem && paymentSubmenu && chevronIcon) {
            paymentMenuItem.addEventListener('click', function(e) {
                // Only prevent default if clicking the chevron icon
                if (e.target.classList.contains('fa-chevron-down')) {
                    e.preventDefault();
                    paymentSubmenu.classList.toggle('active');
                    chevronIcon.classList.toggle('active');
                    // Toggle display style for submenu
                    if (paymentSubmenu.classList.contains('active')) {
                        paymentSubmenu.style.display = 'block';
                    } else {
                        paymentSubmenu.style.display = 'none';
                    }
                }
            });
        }
        // Search functionality for reward table
        const searchInput = document.getElementById('searchInput');
        const rewardTable = document.getElementById('rewardTable');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            Array.from(rewardTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr')).forEach(row => {
                const cells = row.getElementsByTagName('td');
                let found = false;
                for (let i = 0; i < cells.length; i++) {
                    const cellText = cells[i].textContent.toLowerCase();
                    if (cellText.includes(searchTerm)) {
                        found = true;
                        break;
                    }
                }
                row.style.display = found ? '' : 'none';
            });
        });

        // Search functionality for reward keys table
        const searchKeysInput = document.getElementById('searchKeysInput');
        const rewardKeysTable = document.getElementById('rewardKeysTable');
        searchKeysInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            Array.from(rewardKeysTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr')).forEach(row => {
                const cells = row.getElementsByTagName('td');
                let found = false;
                for (let i = 0; i < cells.length; i++) {
                    const cellText = cells[i].textContent.toLowerCase();
                    if (cellText.includes(searchTerm)) {
                        found = true;
                        break;
                    }
                }
                row.style.display = found ? '' : 'none';
            });
        });
        // Add Reward Modal logic
        const addRewardBtn = document.getElementById('addRewardBtn');
        const addRewardModal = document.getElementById('addRewardModal');
        const closeAddRewardModal = document.getElementById('closeAddRewardModal');
        const addRewardForm = document.getElementById('addRewardForm');
        const addPictureInput = document.getElementById('addPictureInput');
        const redeemTxtInput = document.getElementById('redeemTxtInput');
        
        // Restore modal open logic
        addRewardBtn.addEventListener('click', function() {
            addRewardModal.style.display = 'flex';
            setTimeout(() => addRewardModal.classList.add('show'), 10);
        });
        closeAddRewardModal.addEventListener('click', function() {
            addRewardModal.classList.remove('show');
            setTimeout(() => addRewardModal.style.display = 'none', 300);
        });
        window.addEventListener('click', function(event) {
            if (event.target === addRewardModal) {
                addRewardModal.classList.remove('show');
                setTimeout(() => addRewardModal.style.display = 'none', 300);
            }
        });
        // Form validation for image upload
        document.addEventListener('DOMContentLoaded', function() {
            const addRewardForm = document.getElementById('addRewardForm');
            if (addRewardForm) {
                addRewardForm.addEventListener('submit', function(e) {
                    const addPictureInput = document.getElementById('addPictureInput');
                    const redeemTxtInput = document.getElementById('redeemTxtInput');
                    
                    if (!addPictureInput || !addPictureInput.files || addPictureInput.files.length === 0) {
                        e.preventDefault();
                        showAlert('Please select an image file for the reward.', 'error');
                        return false;
                    }
                    if (!redeemTxtInput || !redeemTxtInput.files || redeemTxtInput.files.length === 0) {
                        e.preventDefault();
                        showAlert('Please select a redeem key .txt file.', 'error');
                        return false;
                    }

                    // If validation passes, allow form submission
                    return true;
                });
            }
        });
        // File input handling for reward image
        document.addEventListener('DOMContentLoaded', function() {
            // Reward Image Upload
            const rewardImageInput = document.getElementById('addPictureInput');
            const rewardFileName = document.getElementById('rewardFileName');
            if (rewardImageInput) {
                rewardImageInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        rewardFileName.textContent = this.files[0].name;
                    } else {
                        rewardFileName.textContent = '';
                    }
                });
            }

            // Redeem Key Text Upload
            const redeemTxtInput = document.getElementById('redeemTxtInput');
            const redeemTxtFileName = document.getElementById('redeemTxtFileName');
            if (redeemTxtInput) {
                redeemTxtInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        redeemTxtFileName.textContent = this.files[0].name;
                    } else {
                        redeemTxtFileName.textContent = '';
                    }
                });
            }

            // Edit Reward Image Upload
            const editRewardImageInput = document.getElementById('editPictureInput');
            const editRewardFileName = document.getElementById('editRewardFileName');
            if (editRewardImageInput) {
                editRewardImageInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        editRewardFileName.textContent = this.files[0].name;
                    } else {
                        editRewardFileName.textContent = '';
                    }
                });
            }
        });
        // Edit Modal Functions
        function openEditModal(rewardId) {
            // Add debug console log
            console.log('Opening edit modal for reward:', rewardId);
            
            // Debug modal element
            const modal = document.getElementById('editRewardModal');
            console.log('Modal element:', modal);
            
            // Fetch reward data using XMLHttpRequest for better error handling
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `reward_admin.php?edit=${rewardId}`, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        console.log('Received data:', data);
                        
                        // Debug form elements
                        console.log('Form elements:', {
                            idField: document.getElementById('edit_reward_id'),
                            nameField: document.getElementById('edit_reward_name'),
                            pointsField: document.getElementById('edit_points_needed'),
                            descField: document.getElementById('edit_description')
                        });
                        
                        // Set form values with error checking
                        const fields = {
                            'edit_reward_id': data.reward_id,
                            'edit_reward_name': data.reward_name,
                            'edit_points_needed': data.points_needed,
                            'edit_description': data.description
                        };
                        
                        Object.entries(fields).forEach(([id, value]) => {
                            const element = document.getElementById(id);
                            if (element) {
                                element.value = value;
                            } else {
                                console.error(`Element not found: ${id}`);
                            }
                        });
                        
                        // Show modal with error checking
                        if (modal) {
                            modal.style.display = 'flex';
                            console.log('Modal displayed');
                            setTimeout(() => {
                                modal.classList.add('show');
                                console.log('Modal show class added');
                            }, 10);
                        } else {
                            console.error('Modal element not found');
                        }
                    } catch (e) {
                        console.error('Error parsing JSON:', e);
                        console.log('Raw response:', xhr.responseText);
                    }
                } else {
                    console.error('Error fetching reward data:', xhr.status, xhr.statusText);
                }
            };
            
            xhr.onerror = function() {
                console.error('Network error occurred');
            };
            
            xhr.send();
        }

        function closeEditModal() {
            const modal = document.getElementById('editRewardModal');
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        }

        // Add status toggle handler
        document.querySelectorAll('.status-toggle').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const rewardId = this.dataset.rewardId;
                const status = this.checked ? 1 : 0;
                
                const params = new URLSearchParams();
                params.append('action', 'update_status');
                params.append('reward_id', rewardId);
                params.append('status', status);
                
                fetch('reward_admin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                    } else {
                        showAlert(data.message || 'Error updating status', 'error');
                        // Revert toggle if failed
                        this.checked = !this.checked;
                    }
                })
                .catch(() => {
                    showAlert('Error updating status', 'error');
                    // Revert toggle if failed
                    this.checked = !this.checked;
                });
            });
        });
        // Delete Reward Modal Functions
        let currentDeleteId = null;

        function deleteReward(rewardId) {
            currentDeleteId = rewardId;
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                currentDeleteId = null;
            }, 300);
        }

        function confirmDeleteReward() {
            if (currentDeleteId) {
                window.location.href = 'reward_admin.php?delete=' + encodeURIComponent(currentDeleteId);
            }
        }

        // Delete Key Modal Functions
        let currentDeleteKeyId = null;

        function deleteKey(keyId) {
            currentDeleteKeyId = keyId;
            const modal = document.getElementById('deleteKeyModal');
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        }

        function closeDeleteKeyModal() {
            const modal = document.getElementById('deleteKeyModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                currentDeleteKeyId = null;
            }, 300);
        }

        function confirmDeleteKey() {
            if (currentDeleteKeyId) {
                window.location.href = 'reward_admin.php?delete_key=' + encodeURIComponent(currentDeleteKeyId);
            }
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                if (event.target.id === 'deleteModal') {
                    closeDeleteModal();
                } else if (event.target.id === 'deleteKeyModal') {
                    closeDeleteKeyModal();
                }
            }
        });

        // View Key Modal Functions
        const viewKeyModal = document.getElementById('viewKeyModal');
        let currentViewKey = null;

        function viewKey(key) {
            currentViewKey = key;
            document.getElementById('viewKeyContent').textContent = key;
            viewKeyModal.style.display = 'flex';
            setTimeout(() => viewKeyModal.classList.add('show'), 10);
        }

        function closeViewKeyModal() {
            viewKeyModal.classList.remove('show');
            setTimeout(() => {
                viewKeyModal.style.display = 'none';
                currentViewKey = null;
            }, 300);
        }

        function copyKeyToClipboard() {
            if (currentViewKey) {
                navigator.clipboard.writeText(currentViewKey).then(() => {
                    showAlert('Key copied to clipboard', 'success');
                }).catch(() => {
                    showAlert('Failed to copy key', 'error');
                });
            }
        }

        // Close view key modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === viewKeyModal) {
                closeViewKeyModal();
            }
        });

        // Add Keys Modal Functions
        const addKeysBtn = document.getElementById('addKeysBtn');
        const addKeysModal = document.getElementById('addKeysModal');
        const closeAddKeysModal = document.getElementById('closeAddKeysModal');
        const redeemKeysInput = document.getElementById('redeemKeysInput');
        const redeemKeysFileName = document.getElementById('redeemKeysFileName');

        if (addKeysBtn) {
            addKeysBtn.addEventListener('click', function() {
                addKeysModal.style.display = 'flex';
                setTimeout(() => addKeysModal.classList.add('show'), 10);
            });
        }

        if (closeAddKeysModal) {
            closeAddKeysModal.addEventListener('click', function() {
                addKeysModal.classList.remove('show');
                setTimeout(() => addKeysModal.style.display = 'none', 300);
            });
        }

        if (redeemKeysInput) {
            redeemKeysInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    redeemKeysFileName.textContent = this.files[0].name;
                } else {
                    redeemKeysFileName.textContent = '';
                }
            });
        }

        // Close add keys modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === addKeysModal) {
                addKeysModal.classList.remove('show');
                setTimeout(() => addKeysModal.style.display = 'none', 300);
            }
        });

        // Initialize select2 for searchable dropdown
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: "Search or select a Reward...",
                allowClear: true,
                width: '100%'
            });
        });

        function showNoPermissionAlert() {
            showAlert('No Permission', 'error');
        }

        // Add these new functions for multiple key selection and deletion
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAllKeys');
            const keyCheckboxes = document.getElementsByClassName('key-checkbox');
            const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
            

            // Select all functionality
            selectAllCheckbox.addEventListener('change', function() {
                // Only check visible checkboxes
                Array.from(keyCheckboxes).forEach(checkbox => {
                    const row = checkbox.closest('tr');
                    if (row.style.display !== 'none') {
                        checkbox.checked = this.checked;
                    }
                });
                updateDeleteButtonVisibility();
            });

            // Individual checkbox change
            Array.from(keyCheckboxes).forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateDeleteButtonVisibility();
                    // Update select all checkbox based on visible checkboxes only
                    const visibleCheckboxes = Array.from(keyCheckboxes).filter(cb => {
                        const row = cb.closest('tr');
                        return row.style.display !== 'none';
                    });
                    const allChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
                    selectAllCheckbox.checked = allChecked;
                });
            });

            // Update delete button visibility
            function updateDeleteButtonVisibility() {
                const hasChecked = Array.from(keyCheckboxes).some(cb => cb.checked);
                deleteSelectedBtn.style.display = hasChecked ? 'block' : 'none';
            }

            // Delete selected keys
            deleteSelectedBtn.addEventListener('click', function() {
                selectedKeysToDelete = Array.from(keyCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.dataset.keyId);

                if (selectedKeysToDelete.length > 0) {
                    const modal = document.getElementById('deleteSelectedModal');
                    modal.style.display = 'flex';
                    setTimeout(() => modal.classList.add('show'), 10);
                }
            });
        });

        // Delete Selected Modal Functions
        function closeDeleteSelectedModal() {
            const modal = document.getElementById('deleteSelectedModal');
            modal.classList.remove('show');
            setTimeout(() => modal.style.display = 'none', 300);
        }

        function confirmDeleteSelected() {
            if (selectedKeysToDelete.length > 0) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'reward_admin.php';

                // Add selected keys as hidden inputs
                selectedKeysToDelete.forEach(keyId => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'delete_keys[]';
                    input.value = keyId;
                    form.appendChild(input);
                });

                // Add a hidden input to identify this as a delete operation
                const operationInput = document.createElement('input');
                operationInput.type = 'hidden';
                operationInput.name = 'operation';
                operationInput.value = 'delete_selected';
                form.appendChild(operationInput);

                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close delete selected modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.id === 'deleteSelectedModal') {
                closeDeleteSelectedModal();
            }
        });
        let selectedKeysToDelete = [];
    </script>
</body>
</html> 
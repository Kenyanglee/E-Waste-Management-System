<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
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

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle AJAX profile edit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_profile') {
        $admin_id = $_SESSION['admin']['admin_id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $currentPassword = $_POST['currentPassword'];
        $newPassword = $_POST['newPassword'];
        $confirmPassword = $_POST['confirmPassword'];
        $stmt = $conn->prepare("SELECT password FROM admin WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['password'] !== $currentPassword) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            exit;
        }
        if ($newPassword && $newPassword !== $confirmPassword) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit;
        }
        // Update admin info
        if ($newPassword) {
            $stmt = $conn->prepare("UPDATE admin SET name = ?, email = ?, password = ? WHERE admin_id = ?");
            $stmt->execute([$name, $email, $newPassword, $admin_id]);
        } else {
            $stmt = $conn->prepare("UPDATE admin SET name = ?, email = ? WHERE admin_id = ?");
            $stmt->execute([$name, $email, $admin_id]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // Handle AJAX password change
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $admin_id = $_SESSION['admin']['admin_id'];
        $currentPassword = $_POST['currentPassword'];
        $newPassword = $_POST['newPassword'];
        $stmt = $conn->prepare("SELECT password FROM admin WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['password'] === $currentPassword) {
            $stmt = $conn->prepare("UPDATE admin SET password = ? WHERE admin_id = ?");
            $stmt->execute([$newPassword, $admin_id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        }
        exit;
    }

    // Handle AJAX admin edit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_admin') {
        $admin_id = $_POST['admin_id'];
        $email = $_POST['email'];
        $name = $_POST['name'];
        $stmt = $conn->prepare("UPDATE admin SET email = ?, name = ? WHERE admin_id = ?");
        $stmt->execute([$email, $name, $admin_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Handle AJAX privilege update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_privilege') {
        $admin_id = $_POST['admin_id'];
        $privilege = $_POST['privilege'];
        
        // Prevent updating root admin's privilege
        if ($admin_id === 'root') {
            echo json_encode(['success' => false, 'message' => 'Cannot modify root admin privileges']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE admin SET privilege = ? WHERE admin_id = ?");
        $stmt->execute([$privilege, $admin_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Handle AJAX admin remove
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_admin') {
        ob_clean(); // clear any previous output
        $admin_id = $_POST['admin_id'];
        // Prevent self-removal
        if ($admin_id === $admin['admin_id']) {
            echo json_encode(['success' => false, 'message' => 'You cannot remove your own account.']);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM admin WHERE admin_id = ?");
        $stmt->execute([$admin_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Handle AJAX create admin
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_admin') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $privilege = isset($_POST['privilege']) ? (int)$_POST['privilege'] : 0;
        // Validate
        if (!$name || !$email || !$password) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }
        // Check for duplicate email
        $stmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Email already exists.']);
            exit;
        }
        // Generate admin_id as staff_N (fill gaps)
        $stmt = $conn->query("SELECT admin_id FROM admin WHERE admin_id LIKE 'staff_%'");
        $nums = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $aid) {
            if (preg_match('/staff_(\d+)/', $aid, $m)) {
                $nums[] = (int)$m[1];
            }
        }
        $next = 1;
        sort($nums);
        foreach ($nums as $n) {
            if ($n == $next) {
                $next++;
            } else if ($n > $next) {
                break;
            }
        }
        $new_id = 'staff_' . $next;
        // Insert
        $stmt = $conn->prepare("INSERT INTO admin (admin_id, email, name, password, privilege) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$new_id, $email, $name, $password, $privilege]);
        echo json_encode(['success' => true]);
        exit;
    }

    // Handle AJAX check admin email uniqueness
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_admin_email') {
        $email = trim($_POST['email']);
        $exclude_admin_id = $_POST['exclude_admin_id'];
        $stmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE email = ? AND admin_id != ?");
        $stmt->execute([$email, $exclude_admin_id]);
        $exists = $stmt->fetchColumn() > 0;
        echo json_encode(['exists' => $exists]);
        exit;
    }

    // Handle AJAX points update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_points') {
        $field = $_POST['field'];
        $value = (int)$_POST['value'];
        
        // Validate field name to prevent SQL injection
        $allowed_fields = [
            'laptop_po', 'desktop_po', 'monitor_po', 'printer_po', 
            'phone_po', 'appliance_po', 'wearables_po', 'cables_po', 
            'accessories_po'
        ];
        
        if (!in_array($field, $allowed_fields)) {
            echo json_encode(['success' => false, 'message' => 'Invalid field']);
            exit;
        }
        
        try {
            $stmt = $conn->prepare("UPDATE points SET $field = ? WHERE points_id = 'set_points'");
            $stmt->execute([$value]);
            
            // Get current value for response
            $stmt = $conn->prepare("SELECT $field FROM points WHERE points_id = 'set_points'");
            $stmt->execute();
            $current_value = $stmt->fetchColumn();
            
            echo json_encode(['success' => true, 'current_value' => $current_value]);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $admin_id = $_SESSION['admin']['admin_id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = $_POST['password'];

        // Update admin information
        $stmt = $conn->prepare("UPDATE admin SET name = ?, email = ?, password = ? WHERE admin_id = ?");
        $stmt->execute([$name, $email, $password, $admin_id]);

        // Refresh the page to show updated information
        header('Location: settings.php');
        exit();
    }

    // Get current admin information
    $admin_id = $_SESSION['admin']['admin_id'];
    $stmt = $conn->prepare("SELECT * FROM admin WHERE admin_id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch all admins if privilege is 1, with pagination
    $show_manage_admin = false;
    $admin_list = [];
    $admin_page = isset($_GET['admin_page']) ? (int)$_GET['admin_page'] : 1;
    $admin_limit = 10;
    $admin_offset = ($admin_page - 1) * $admin_limit;
    $admin_total_pages = 1;
    if (isset($admin['privilege']) && $admin['privilege'] == 1) {
        $show_manage_admin = true;
        $stmt = $conn->query("SELECT COUNT(*) FROM admin WHERE admin_id != 'root'");
        $admin_total_records = $stmt->fetchColumn();
        $admin_total_pages = ceil($admin_total_records / $admin_limit);
        $stmt = $conn->prepare("SELECT admin_id, email, name, privilege FROM admin WHERE admin_id != 'root' AND admin_id != :current_admin_id LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':current_admin_id', $admin['admin_id'], PDO::PARAM_STR);
        $stmt->bindValue(':limit', $admin_limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $admin_offset, PDO::PARAM_INT);
        $stmt->execute();
        $admin_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        :root {
            --sidebar-width: 280px;
            --primary-color: #50B88E;
            --secondary-color: #4A90E2;
            --text-primary: #2D3748;
            --text-secondary: #718096;
            --bg-light: #F7FAFC;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --transition-default: all 0.3s ease;
        }
        body {
            display: flex;
            min-height: 100vh;
            background-color: var(--bg-light);
            color: var(--text-primary);
        }
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary-color);
            padding: 24px 24px 32px;
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            z-index: 10;
        }
        .sidebar.hide {
            transform: translateX(-100%);
        }
        .logo {
            padding: 4px 12px;
            margin-bottom: 32px;
        }
        .logo img {
            width: 100%;
            height: auto;
        }
        .menu {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 8px 16px;
        }
        .menu-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background-color 0.3s;
        }
        .menu-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .menu-item.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }
        .top-bar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding: 10px 20px;
            background-color: #D2CECE;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .user-actions {
            display: flex;
            align-items: center;
            gap: 32px;
        }
        .account {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #C4C4C4;
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        }
        .account:hover {
            transform: scale(1.1);
            animation: pulse-profile 2.5s infinite;
        }
        .account img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .settings-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--card-shadow);
            margin-bottom: 24px;
        }
        .settings-card h2 {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--bg-light);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        .save-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .save-btn:hover {
            background-color: #3d8b6f;
        }
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
        /* Modal Styles (copied and adapted from userlist.php) */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal.show {
            opacity: 1;
            display: flex;
        }
        .modal-content {
            background-color: white;
            border-radius: 12px;
            width: 400px;
            max-width: 90vw;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transform: scale(0.7);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .modal.show .modal-content {
            transform: scale(1);
            opacity: 1;
        }
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.25rem;
        }
        .close-button {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 0;
        }
        .modal-body {
            padding: 20px;
            color: var(--text-secondary);
        }
        .modal-footer {
            padding: 20px;
            border-top: 1px solid #E2E8F0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
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
            background-color: #50B88E;
            color: white;
        }
        .modal-button:hover {
            transform: translateY(-2px);
        }
        .cancel-button:hover {
            background-color: #E2E8F0;
        }
        .confirm-button:hover {
            background-color: #3d8b6f;
        }
        /* Alert Styles (copied from userlist.php) */
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 2000;
        }
        .alert {
            padding: 12px 24px;
            border-radius: 50px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease, fadeOut 0.3s ease 2.7s forwards;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .alert-success {
            background-color: #C6F6D5;
            color: #2F855A;
        }
        .alert-error {
            background-color: #FED7D7;
            color: #C53030;
        }
        .alert i {
            font-size: 1.2rem;
        }
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--card-shadow);
            transition: var(--transition-default);
            border: 1px solid rgba(0, 0, 0, 0.05);
            max-width: 100%;
            box-sizing: border-box;
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--bg-light);
        }
        .card-header h2 {
            margin: 0;
            padding: 0;
            line-height: 1.2;
            font-size: 1.5rem;
            font-weight: 700;
            vertical-align: middle;
        }
        .table-container {
            overflow-x: auto;
            max-width: 100%;
            box-sizing: border-box;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            table-layout: fixed;
        }
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #E2E8F0;
            word-wrap: break-word;
        }
        th {
            background-color: var(--bg-light);
            font-weight: 600;
            color: var(--text-primary);
        }
        tr:hover {
            background-color: var(--bg-light);
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
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }
        .page-button {
            padding: 8px 16px;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            background-color: white;
            cursor: pointer;
            transition: var(--transition-default);
        }
        .page-button:hover {
            background-color: var(--bg-light);
        }
        .page-button.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
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
        .privilege-col {
            text-align: center !important;
        }
        .search-bar {
            display: flex;
            align-items: center;
            background: #f7fafc;
            border-radius: 8px;
            padding: 4px 10px;
            border: 1px solid #e2e8f0;
            gap: 8px;
            margin: 0;
            height: 38px;
        }
        .search-bar i {
            color: #a0aec0;
            font-size: 1rem;
        }
        .search-bar input {
            background: transparent;
            border: none;
            outline: none;
            width: 100%;
            color: var(--text-primary);
            font-size: 1rem;
            height: 28px;
            line-height: 28px;
            padding: 0;
            margin: 0;
            vertical-align: middle;
        }
        .search-bar input::placeholder {
            color: var(--text-secondary);
        }
        /* Responsive Styles */
        @media (max-width: 1400px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .settings-grid {
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

            .settings-grid {
                grid-template-columns: 1fr;
            }

            .settings-section {
                width: 100%;
            }

            .profile-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .profile-avatar {
                margin-bottom: 20px;
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
            
            .settings-tabs {
                flex-direction: column;
                gap: 10px;
            }
            
            .tab-button {
                width: 100%;
                text-align: center;
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

            .profile-stats {
                grid-template-columns: 1fr;
            }

            .security-options {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 10px;
            }
            
            .card {
                padding: 12px;
            }
            
            .settings-card {
                padding: 12px;
            }
            
            .settings-card h3 {
                font-size: 1rem;
            }
            
            .settings-card p {
                font-size: 0.8rem;
            }

            .btn {
                padding: 4px 8px;
                font-size: 0.8rem;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
            }

            .profile-info h2 {
                font-size: 1.2rem;
            }

            .profile-info p {
                font-size: 0.9rem;
            }

            .form-label {
                font-size: 0.9rem;
            }

            .form-input {
                font-size: 0.9rem;
                padding: 8px;
            }

            .form-actions button {
                width: 100%;
            }

            .notification-item {
                padding: 10px;
            }

            .notification-content {
                font-size: 0.9rem;
            }

            .security-option {
                padding: 12px;
            }

            .security-option h4 {
                font-size: 1rem;
            }
        }

        /* Print Styles */
        @media print {
            .sidebar,
            .top-bar,
            .settings-tabs {
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

            .profile-section {
                page-break-inside: avoid;
            }

            .security-section {
                page-break-inside: avoid;
            }

            .btn, .action-button {
                display: none;
            }

            .form-actions {
                display: none;
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
            <a href="payment_admin.php" class="menu-item">
                <i class="fas fa-shopping-cart"></i>
                <span>Payment</span>
                <i class="fas fa-chevron-down" style="margin-left: auto;"></i>
            </a>
            <div class="submenu" style="display: none; padding-left: 40px;">
                <a href="reward_admin.php" class="menu-item">
                    <i class="fas fa-gift"></i>
                    <span>Reward</span>
                </a>
            </div>
        </div>
        <div class="bottom-menu">
            <a href="settings.php" class="menu-item active">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Log Out</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
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

        <div class="settings-container">
            <div class="settings-card">
                <h2>Account Settings</h2>
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" value="<?php echo htmlspecialchars($admin['name']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($admin['email']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" value="********" readonly>
                </div>
                <button type="button" class="save-btn" id="editProfileBtn">Edit</button>
            </div>
            <?php if ($show_manage_admin): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Manage Points</h2>
                </div>
                <div class="settings-card" style="box-shadow: none; padding: 0;">
                    <?php
                    $stmt = $conn->prepare("SELECT * FROM points WHERE points_id = 'set_points'");
                    $stmt->execute();
                    $points = $stmt->fetch(PDO::FETCH_ASSOC);
                    $items = [
                        'laptop_po' => 'Laptops',
                        'desktop_po' => 'Desktops/Servers',
                        'monitor_po' => 'Monitors/TVs',
                        'printer_po' => 'Printers/Projectors',
                        'phone_po' => 'Smartphones/Tablets',
                        'appliance_po' => 'Home Appliances',
                        'wearables_po' => 'Wearables',
                        'cables_po' => 'Cables/Wires',
                        'accessories_po' => 'Peripherals/Accessories'
                    ];
                    foreach ($items as $field => $label): ?>
                    <div class="form-group">
                        <label><?php echo htmlspecialchars($label); ?></label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <input type="number" class="point-input" 
                                   data-field="<?php echo htmlspecialchars($field); ?>" 
                                   value="<?php echo htmlspecialchars($points[$field]); ?>"
                                   min="0"
                                   style="flex: 1;">
                            <button class="save-btn save-points" 
                                    data-field="<?php echo htmlspecialchars($field); ?>"
                                    style="padding: 12px 24px; margin: 0;">
                                Save
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h2>Manage Admin Accounts</h2>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div class="search-bar" style="max-width: 300px;">
                            <i class="fas fa-search"></i>
                            <input type="text" id="adminSearchInput" placeholder="Search by ID, name, or email..." style="width: 100%; border: none; outline: none; font-size: 1rem;">
                        </div>
                        <button class="save-btn" id="newAdminBtn" type="button" style="padding: 8px 18px; font-size: 1rem; margin-left: 8px;">+ New Admin</button>
                    </div>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Admin ID</th>
                                <th>Email</th>
                                <th>Name</th>
                                <th class="privilege-col">Privilege</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="adminTableBody">
                            <?php foreach ($admin_list as $adm): ?>
                            <tr data-admin-id="<?php echo htmlspecialchars($adm['admin_id']); ?>">
                                <td><?php echo htmlspecialchars($adm['admin_id']); ?></td>
                                <td><?php echo htmlspecialchars($adm['email']); ?></td>
                                <td><?php echo htmlspecialchars($adm['name']); ?></td>
                                <td class="privilege-col">
                                    <label class="toggle-switch">
                                        <input type="checkbox" class="privilege-toggle" 
                                               data-admin-id="<?php echo htmlspecialchars($adm['admin_id']); ?>"
                                               <?php echo $adm['privilege'] == 1 ? 'checked' : ''; ?>>
                                        <span class="toggle-slider"></span>
                                    </label>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-button edit-button" onclick="editAdmin('<?php echo $adm['admin_id']; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($adm['admin_id'] !== $admin['admin_id']): ?>
                                        <button class="action-button delete-button" onclick="removeAdmin('<?php echo $adm['admin_id']; ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination">
                    <?php if ($admin_page > 1): ?>
                        <button class="page-button" onclick="window.location.href='settings.php?admin_page=<?php echo $admin_page-1; ?>'">Previous</button>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $admin_total_pages; $i++): ?>
                        <button class="page-button <?php echo $i === $admin_page ? 'active' : ''; ?>" onclick="window.location.href='settings.php?admin_page=<?php echo $i; ?>'">
                            <?php echo $i; ?>
                        </button>
                    <?php endfor; ?>
                    <?php if ($admin_page < $admin_total_pages): ?>
                        <button class="page-button" onclick="window.location.href='settings.php?admin_page=<?php echo $admin_page+1; ?>'">Next</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal" id="editProfileModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Profile</h3>
                <button class="close-button" id="closeEditProfileModal">&times;</button>
            </div>
            <form id="editProfileForm" onsubmit="return false;">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="editName">Name</label>
                        <input type="text" id="editName" name="name" value="<?php echo htmlspecialchars($admin['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="editEmail">Email</label>
                        <input type="email" id="editEmail" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="currentPasswordEdit">Current Password <span style="color:#e53e3e">*</span></label>
                        <input type="password" id="currentPasswordEdit" name="currentPassword" required placeholder="Enter current password">
                    </div>
                    <div class="form-group">
                        <label for="newPasswordEdit">New Password</label>
                        <input type="password" id="newPasswordEdit" name="newPassword" placeholder="Leave blank to keep current password">
                    </div>
                    <div class="form-group">
                        <label for="confirmPasswordEdit">Confirm New Password</label>
                        <input type="password" id="confirmPasswordEdit" name="confirmPassword" placeholder="Leave blank to keep current password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-button cancel-button" id="cancelEditProfileBtn">Cancel</button>
                    <button type="submit" class="modal-button confirm-button">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Alert Notification -->
    <div class="alert-container" id="alertContainer"></div>

    <!-- Edit Admin Modal -->
    <div class="modal" id="editAdminModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Admin</h3>
                <button class="close-button" id="closeEditAdminModal">&times;</button>
            </div>
            <form id="editAdminForm" onsubmit="return false;">
                <div class="modal-body">
                    <input type="hidden" id="editAdminId" name="admin_id">
                    <div class="form-group">
                        <label for="editAdminEmail">Email</label>
                        <input type="email" id="editAdminEmail" name="email" required>
                        <div id="editEmailErrorMsg" style="color: #e53e3e; font-size: 0.95em; margin-top: 4px; display: none;"></div>
                    </div>
                    <div class="form-group">
                        <label for="editAdminName">Name</label>
                        <input type="text" id="editAdminName" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-button cancel-button" id="cancelEditAdminBtn">Cancel</button>
                    <button type="submit" class="modal-button confirm-button">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Remove Admin Confirmation Modal -->
    <div class="modal" id="removeAdminModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Remove Admin</h3>
                <button class="close-button" id="closeRemoveAdminModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove this admin account? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-button cancel-button" id="cancelRemoveAdminBtn">Cancel</button>
                <button type="button" class="modal-button confirm-button" id="confirmRemoveAdminBtn">Remove</button>
            </div>
        </div>
    </div>

    <!-- New Admin Modal -->
    <div class="modal" id="newAdminModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Admin</h3>
                <button class="close-button" id="closeNewAdminModal">&times;</button>
            </div>
            <form id="newAdminForm" onsubmit="return false;">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="newAdminName">Name</label>
                        <input type="text" id="newAdminName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="newAdminEmail">Email</label>
                        <input type="email" id="newAdminEmail" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="newAdminPassword">Password</label>
                        <input type="password" id="newAdminPassword" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="newAdminConfirmPassword">Confirm Password</label>
                        <input type="password" id="newAdminConfirmPassword" name="confirm_password" required>
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="newAdminPrivilege" name="privilege" style="width: 18px; height: 18px;">
                        <label for="newAdminPrivilege" style="margin: 0;">Privilege</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-button cancel-button" id="cancelNewAdminBtn">Cancel</button>
                    <button type="submit" class="modal-button confirm-button">Create</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Find the menu-item with the shopping cart icon
        const menuItems = document.querySelectorAll('.menu-item');
        let paymentMenuItem = null;
        menuItems.forEach(item => {
            if (item.querySelector('.fa-shopping-cart')) {
                paymentMenuItem = item;
            }
        });
        const paymentSubmenu = paymentMenuItem.nextElementSibling;
        const chevronIcon = paymentMenuItem.querySelector('.fa-chevron-down');

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

        // Edit Profile modal logic
        const editProfileModal = document.getElementById('editProfileModal');
        const editProfileBtn = document.getElementById('editProfileBtn');
        const closeEditProfileModal = document.getElementById('closeEditProfileModal');
        const cancelEditProfileBtn = document.getElementById('cancelEditProfileBtn');
        const editProfileForm = document.getElementById('editProfileForm');

        editProfileBtn.addEventListener('click', function() {
            editProfileModal.style.display = 'flex';
            editProfileModal.offsetHeight;
            editProfileModal.classList.add('show');
        });
        closeEditProfileModal.addEventListener('click', closeEditProfileModalFunc);
        cancelEditProfileBtn.addEventListener('click', closeEditProfileModalFunc);
        function closeEditProfileModalFunc() {
            editProfileModal.classList.remove('show');
            setTimeout(() => { editProfileModal.style.display = 'none'; }, 300);
            editProfileForm.reset();
            // Restore original values
            document.getElementById('editName').value = <?php echo json_encode($admin['name']); ?>;
            document.getElementById('editEmail').value = <?php echo json_encode($admin['email']); ?>;
        }
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === editProfileModal) closeEditProfileModalFunc();
        });

        editProfileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const name = document.getElementById('editName').value;
            const email = document.getElementById('editEmail').value;
            const currentPassword = document.getElementById('currentPasswordEdit').value;
            const newPassword = document.getElementById('newPasswordEdit').value;
            const confirmPassword = document.getElementById('confirmPasswordEdit').value;
            if (newPassword && newPassword !== confirmPassword) {
                showAlert('New passwords do not match', 'error');
                return;
            }
            // AJAX to PHP for profile update
            const params = new URLSearchParams();
            params.append('action', 'edit_profile');
            params.append('name', name);
            params.append('email', email);
            params.append('currentPassword', currentPassword);
            params.append('newPassword', newPassword);
            params.append('confirmPassword', confirmPassword);
            fetch('settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert('Profile updated successfully', 'success');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showAlert(data.message || 'Error updating profile', 'error');
                }
            })
            .catch(() => showAlert('Error updating profile', 'error'));
        });

        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type === 'success' ? 'success' : 'error'}`;
            const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            alert.innerHTML = `\n        <i class=\"fas fa-${icon}\"></i>\n        <span>${message}</span>\n    `;
            alertContainer.appendChild(alert);
            setTimeout(() => { alert.remove(); }, 3000);
        }

        // Admin management logic
        let currentEditAdminId = null;
        let currentRemoveAdminId = null;
        function editAdmin(adminId) {
            const row = document.querySelector(`tr[data-admin-id='${adminId}']`);
            document.getElementById('editAdminId').value = adminId;
            document.getElementById('editAdminEmail').value = row.children[1].textContent;
            document.getElementById('editAdminName').value = row.children[2].textContent;
            document.getElementById('editAdminModal').style.display = 'flex';
            document.getElementById('editAdminModal').offsetHeight;
            document.getElementById('editAdminModal').classList.add('show');
            currentEditAdminId = adminId;
        }
        document.getElementById('closeEditAdminModal').onclick = closeEditAdminModal;
        document.getElementById('cancelEditAdminBtn').onclick = closeEditAdminModal;
        function closeEditAdminModal() {
            document.getElementById('editAdminModal').classList.remove('show');
            setTimeout(() => { document.getElementById('editAdminModal').style.display = 'none'; }, 300);
            currentEditAdminId = null;
        }
        document.getElementById('editAdminForm').onsubmit = function(e) {
            e.preventDefault();
            const admin_id = document.getElementById('editAdminId').value;
            const email = document.getElementById('editAdminEmail').value;
            const name = document.getElementById('editAdminName').value;
            const params = new URLSearchParams();
            params.append('action', 'edit_admin');
            params.append('admin_id', admin_id);
            params.append('email', email);
            params.append('name', name);
            fetch('settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert('Admin updated successfully', 'success');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showAlert(data.message || 'Error updating admin', 'error');
                }
            })
            .catch(() => showAlert('Error updating admin', 'error'));
        };
        function removeAdmin(adminId) {
            currentRemoveAdminId = adminId;
            document.getElementById('removeAdminModal').style.display = 'flex';
            document.getElementById('removeAdminModal').offsetHeight;
            document.getElementById('removeAdminModal').classList.add('show');
        }
        document.getElementById('closeRemoveAdminModal').onclick = closeRemoveAdminModal;
        document.getElementById('cancelRemoveAdminBtn').onclick = closeRemoveAdminModal;
        document.getElementById('confirmRemoveAdminBtn').onclick = function() {
            if (!currentRemoveAdminId) return;
            const params = new URLSearchParams();
            params.append('action', 'remove_admin');
            params.append('admin_id', currentRemoveAdminId);
            fetch('settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(res => res.text())
            .then(text => {
                console.log('Remove admin raw response:', text);
                let data;
                try { data = JSON.parse(text); } catch (e) { showAlert('Error removing admin (invalid response)', 'error'); return; }
                if (data.success) {
                    showAlert('Admin removed successfully', 'success');
                    setTimeout(() => window.location.reload(), 1200);
                    closeRemoveAdminModal();
                } else {
                    showAlert(data.message || 'Error removing admin', 'error');
                }
            })
            .catch(() => showAlert('Error removing admin', 'error'));
        };
        function closeRemoveAdminModal() {
            document.getElementById('removeAdminModal').classList.remove('show');
            setTimeout(() => { document.getElementById('removeAdminModal').style.display = 'none'; }, 300);
            currentRemoveAdminId = null;
        }

        // Add privilege toggle handler
        document.querySelectorAll('.privilege-toggle').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const adminId = this.dataset.adminId;
                const privilege = this.checked ? 1 : 0;
                
                const params = new URLSearchParams();
                params.append('action', 'update_privilege');
                params.append('admin_id', adminId);
                params.append('privilege', privilege);
                
                fetch('settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Privilege updated successfully', 'success');
                    } else {
                        showAlert(data.message || 'Error updating privilege', 'error');
                        // Revert toggle if failed
                        this.checked = !this.checked;
                    }
                })
                .catch(() => {
                    showAlert('Error updating privilege', 'error');
                    // Revert toggle if failed
                    this.checked = !this.checked;
                });
            });
        });

        // Admin search box filtering
        const adminSearchInput = document.getElementById('adminSearchInput');
        if (adminSearchInput) {
            adminSearchInput.addEventListener('input', function() {
                const filter = this.value.toLowerCase();
                const rows = document.querySelectorAll('#adminTableBody tr');
                rows.forEach(row => {
                    const id = row.children[0].textContent.toLowerCase();
                    const email = row.children[1].textContent.toLowerCase();
                    const name = row.children[2].textContent.toLowerCase();
                    if (id.includes(filter) || email.includes(filter) || name.includes(filter)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        // New Admin Modal logic
        const newAdminBtn = document.getElementById('newAdminBtn');
        const newAdminModal = document.getElementById('newAdminModal');
        const closeNewAdminModal = document.getElementById('closeNewAdminModal');
        const cancelNewAdminBtn = document.getElementById('cancelNewAdminBtn');
        const newAdminForm = document.getElementById('newAdminForm');

        if (newAdminBtn) {
            newAdminBtn.onclick = function() {
                newAdminModal.style.display = 'flex';
                newAdminModal.offsetHeight;
                newAdminModal.classList.add('show');
            };
        }
        if (closeNewAdminModal) closeNewAdminModal.onclick = closeNewAdminModalFunc;
        if (cancelNewAdminBtn) cancelNewAdminBtn.onclick = closeNewAdminModalFunc;
        function closeNewAdminModalFunc() {
            newAdminModal.classList.remove('show');
            setTimeout(() => { newAdminModal.style.display = 'none'; }, 300);
            newAdminForm.reset();
        }
        window.addEventListener('click', function(event) {
            if (event.target === newAdminModal) closeNewAdminModalFunc();
        });
        newAdminForm.onsubmit = function(e) {
            e.preventDefault();
            const name = document.getElementById('newAdminName').value.trim();
            const email = document.getElementById('newAdminEmail').value.trim();
            const password = document.getElementById('newAdminPassword').value;
            const confirmPassword = document.getElementById('newAdminConfirmPassword').value;
            const privilege = document.getElementById('newAdminPrivilege').checked ? 1 : 0;
            if (!name || !email || !password || !confirmPassword) {
                showAlert('All fields are required', 'error');
                return;
            }
            if (password !== confirmPassword) {
                showAlert('Passwords do not match', 'error');
                return;
            }
            const params = new URLSearchParams();
            params.append('action', 'create_admin');
            params.append('name', name);
            params.append('email', email);
            params.append('password', password);
            params.append('privilege', privilege);
            fetch('settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert('Admin created successfully', 'success');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showAlert(data.message || 'Error creating admin', 'error');
                }
            })
            .catch(() => showAlert('Error creating admin', 'error'));
        };

        const editAdminEmail = document.getElementById('editAdminEmail');
        const editEmailErrorMsg = document.getElementById('editEmailErrorMsg');
        const editAdminSubmitBtn = document.getElementById('editAdminForm').querySelector('button[type="submit"]');
        const editAdminIdInput = document.getElementById('editAdminId');

        if (editAdminEmail) {
            editAdminEmail.addEventListener('input', function() {
                const email = this.value.trim();
                const admin_id = editAdminIdInput.value;
                if (!email) {
                    editEmailErrorMsg.style.display = 'none';
                    editAdminSubmitBtn.disabled = false;
                    return;
                }
                const params = new URLSearchParams();
                params.append('action', 'check_admin_email');
                params.append('email', email);
                params.append('exclude_admin_id', admin_id);
                fetch('settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                })
                .then(res => res.json())
                .then(data => {
                    if (data.exists) {
                        editEmailErrorMsg.textContent = 'This email is already in use.';
                        editEmailErrorMsg.style.display = 'block';
                        editAdminSubmitBtn.disabled = true;
                    } else {
                        editEmailErrorMsg.style.display = 'none';
                        editAdminSubmitBtn.disabled = false;
                    }
                })
                .catch(() => {
                    editEmailErrorMsg.textContent = 'Error checking email.';
                    editEmailErrorMsg.style.display = 'block';
                    editAdminSubmitBtn.disabled = true;
                });
            });
        }

        // Points management
        document.querySelectorAll('.save-points').forEach(button => {
            button.addEventListener('click', function() {
                const field = this.dataset.field;
                const input = document.querySelector(`.point-input[data-field="${field}"]`);
                const value = input.value;
                
                if (value < 0) {
                    showAlert('Points cannot be negative', 'error');
                    return;
                }

                const params = new URLSearchParams();
                params.append('action', 'update_points');
                params.append('field', field);
                params.append('value', value);
                
                fetch('settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Points updated successfully', 'success');
                    } else {
                        showAlert(data.message || 'Error updating points', 'error');
                        // Revert input value if failed
                        input.value = data.current_value;
                    }
                })
                .catch(() => {
                    showAlert('Error updating points', 'error');
                });
            });
        });

        // Add input validation for points
        document.querySelectorAll('.point-input').forEach(input => {
            input.addEventListener('input', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
            });
        });
    </script>
</body>
</html> 
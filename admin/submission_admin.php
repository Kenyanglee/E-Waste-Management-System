<?php
session_start();
if (!isset($_SESSION['admin'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    } else {
        header('Location: ../user/auth.php');
        exit();
    }
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');

        // Get submission details
        if (isset($_GET['action']) && $_GET['action'] === 'get_details' && isset($_GET['submission_id'])) {
            try {
                $submission_id = trim($_GET['submission_id']);
                
                // Debug log
                error_log("Fetching submission details for ID: " . $submission_id);

                // First, verify if the submission exists
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM submission WHERE submission_id = :submission_id");
                $check_stmt->bindParam(':submission_id', $submission_id);
                $check_stmt->execute();
                $count = $check_stmt->fetchColumn();
                
                error_log("Submission count: " . $count);
                
                if ($count == 0) {
                    throw new Exception('Submission not found');
                }

                // If submission exists, get the details
                $stmt = $conn->prepare("
                    SELECT 
                        s.*,
                        u.user_name,
                        u.email,
                        DATE_FORMAT(s.date, '%Y-%m-%d %H:%i:%s') as formatted_date,
                        q.quotation_id
                    FROM submission s 
                    LEFT JOIN user u ON s.user_id = u.user_id 
                    LEFT JOIN quotation q ON s.submission_id = q.submission_id
                    WHERE s.submission_id = :submission_id
                ");
                $stmt->bindParam(':submission_id', $submission_id);
                $stmt->execute();
                $submission = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($submission) {
                    // Convert binary image data to base64 if it exists
                    if ($submission['ewaste_image']) {
                        $submission['ewaste_image'] = base64_encode($submission['ewaste_image']);
                    }
                    
                    // Get quotation ID only for Completed submissions
                    if ($submission['status'] === 'Completed') {
                        error_log("Found Completed submission: " . $submission['submission_id']);
                        
                        $quotation_stmt = $conn->prepare("SELECT quotation_id FROM quotation WHERE submission_id = :submission_id");
                        $quotation_stmt->bindParam(':submission_id', $submission['submission_id']);
                        $quotation_stmt->execute();
                        $quotation = $quotation_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        error_log("SQL Query: SELECT quotation_id FROM quotation WHERE submission_id = '" . $submission['submission_id'] . "'");
                        
                        if ($quotation) {
                            error_log("Found quotation: " . print_r($quotation, true));
                            $submission['quotation_id'] = $quotation['quotation_id'];
                        } else {
                            error_log("No quotation found for completed submission");
                        }
                    } else {
                        error_log("Submission status is not Completed: " . $submission['status']);
                    }
                    
                    error_log("Final submission data: " . print_r($submission, true));
                    
                    // Ensure all numeric fields are properly formatted
                    $numeric_fields = [
                        'laptop_qty', 'desktop_qty', 'monitor_qty', 'printer_qty',
                        'phone_qty', 'appliance_qty', 'wearables_qty', 'cables_qty',
                        'accessories_qty'
                    ];
                    
                    foreach ($numeric_fields as $field) {
                        $submission[$field] = (int)$submission[$field];
                    }
                    
                    error_log("Successfully retrieved submission details");
                    echo json_encode([
                        'success' => true,
                        'submission' => $submission
                    ]);
                } else {
                    throw new Exception('Error retrieving submission details');
                }
            } catch (Exception $e) {
                error_log("Error in submission_admin.php: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            exit();
        }

        // Get points configuration
        if (isset($_GET['action']) && $_GET['action'] === 'get_points') {
            try {
                $stmt = $conn->prepare("
                    SELECT 
                        laptop_po, desktop_po, monitor_po, printer_po,
                        phone_po, appliance_po, wearables_po, cables_po,
                        accessories_po, points_id
                    FROM points 
                    WHERE points_id = 'set_points'
                ");
                $stmt->execute();
                $points = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($points) {
                    echo json_encode([
                        'success' => true,
                        'points' => $points
                    ]);
                } else {
                    throw new Exception('Points configuration not found');
                }
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            exit();
        }

        // Update submission status
        if (isset($_POST['action']) && $_POST['action'] === 'update_status' && isset($_POST['submission_id']) && isset($_POST['status'])) {
            try {
                $submission_id = $_POST['submission_id'];
                $status = $_POST['status'];

                error_log('Updating status for submission_id: ' . $submission_id . ' to status: ' . $status);

                $valid_statuses = ['Pending', 'Approved', 'Accepted', 'Rejected'];
                if (!in_array($status, $valid_statuses)) {
                    error_log('Invalid status value received: ' . $status);
                    throw new Exception('Invalid status');
                }

                $stmt = $conn->prepare("
                    UPDATE submission 
                    SET status = :status 
                    WHERE submission_id = :submission_id
                ");
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':submission_id', $submission_id);
                $stmt->execute();

                error_log('Row count after update: ' . $stmt->rowCount());

                if ($stmt->rowCount() > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Status updated successfully'
                    ]);
                } else {
                    error_log('Submission not found or status unchanged for submission_id: ' . $submission_id);
                    throw new Exception('Submission not found or status unchanged');
                }
            } catch (Exception $e) {
                error_log('Error updating status: ' . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error updating status: ' . $e->getMessage()
                ]);
            }
            exit();
        }
    }

    // Regular page load - get submissions list
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    $stmt = $conn->query("SELECT COUNT(*) FROM submission");
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    $stmt = $conn->prepare("
        SELECT s.*, u.user_name, u.email 
        FROM submission s 
        JOIN user u ON s.user_id = u.user_id 
        ORDER BY FIELD(s.status, 'Pending', 'Accepted', 'Rejected', 'Completed'), s.date DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    } else {
        echo "Connection failed: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_status') {
        try {
            if (!isset($_POST['submission_id']) || !isset($_POST['status'])) {
                throw new Exception('Missing required parameters');
            }

            $submission_id = $_POST['submission_id'];
            $status = $_POST['status'];

            if (!in_array($status, ['Pending', 'Approved', 'Rejected'])) {
                throw new Exception('Invalid status value');
            }

            $stmt = $conn->prepare("UPDATE submission SET status = ? WHERE submission_id = ?");
            $stmt->execute([$status, $submission_id]);

            echo json_encode(['success' => true]);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    } elseif ($_POST['action'] === 'generate_quotation') {
        try {
            // Debug log
            error_log("Starting quotation generation process");
            error_log("POST data: " . print_r($_POST, true));
            error_log("Session data: " . print_r($_SESSION, true));
            
            // Only require submissionId
            if (!isset($_POST['submissionId']) || $_POST['submissionId'] === '') {
                throw new Exception("Missing required field: submissionId");
            }
            // Ensure all price fields are set, default to 0 if missing
            $price_fields = [
                'laptop_p', 'desktop_p', 'monitor_p', 'printer_p',
                'phone_p', 'appliance_p', 'wearables_p', 'cables_p', 'accessories_p'
            ];
            foreach ($price_fields as $field) {
                if (!isset($_POST[$field]) || $_POST[$field] === '') {
                    $_POST[$field] = 0;
                }
            }

            // Get submission quantities
            $stmt = $conn->prepare("
                SELECT 
                    laptop_qty, desktop_qty, monitor_qty, printer_qty,
                    phone_qty, appliance_qty, wearables_qty, cables_qty,
                    accessories_qty, user_id
                FROM submission 
                WHERE submission_id = ?
            ");
            $stmt->execute([$_POST['submissionId']]);
            $submission = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$submission) {
                error_log("Submission not found for ID: " . $_POST['submissionId']);
                throw new Exception("Submission not found");
            }

            error_log("Found submission with user_id: " . $submission['user_id']);

            // Get admin_id from session
            if (!isset($_SESSION['admin'])) {
                error_log("Admin session not found. Session contents: " . print_r($_SESSION, true));
                throw new Exception("Admin session not found");
            }
            $admin_id = is_array($_SESSION['admin']) ? $_SESSION['admin']['admin_id'] : $_SESSION['admin'];
            error_log("Found admin_id: " . $admin_id);

            // Get points values from points table
            $stmt = $conn->prepare("
                SELECT 
                    laptop_po, desktop_po, monitor_po, printer_po,
                    phone_po, appliance_po, wearables_po, cables_po,
                    accessories_po, points_id
                FROM points 
                WHERE points_id = 'set_points'
            ");
            $stmt->execute();
            $points = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$points) {
                error_log("Points configuration not found");
                throw new Exception("Points configuration not found");
            }

            error_log("Found points configuration with points_id: " . $points['points_id']);

            // Calculate total points
            $total_points = 0;
            $points_calculation = [
                'laptop' => $submission['laptop_qty'] * $points['laptop_po'],
                'desktop' => $submission['desktop_qty'] * $points['desktop_po'],
                'monitor' => $submission['monitor_qty'] * $points['monitor_po'],
                'printer' => $submission['printer_qty'] * $points['printer_po'],
                'phone' => $submission['phone_qty'] * $points['phone_po'],
                'appliance' => $submission['appliance_qty'] * $points['appliance_po'],
                'wearables' => $submission['wearables_qty'] * $points['wearables_po'],
                'cables' => $submission['cables_qty'] * $points['cables_po'],
                'accessories' => $submission['accessories_qty'] * $points['accessories_po']
            ];

            $total_points = array_sum($points_calculation);
            error_log("Calculated total points: " . $total_points);

            // Generate sequential quotation ID
            $quotation_id = null;
            $max_attempts = 100; // Prevent infinite loop
            $attempt = 1;

            while ($quotation_id === null && $attempt <= $max_attempts) {
                // Check for existing quotation with this number
                $stmt = $conn->prepare("SELECT COUNT(*) FROM quotation WHERE quotation_id = ?");
                $stmt->execute(['Q#' . $attempt]);
                $exists = $stmt->fetchColumn();

                if (!$exists) {
                    $quotation_id = 'Q#' . $attempt;
                }
                $attempt++;
            }

            if ($quotation_id === null) {
                error_log("Failed to generate unique quotation ID after " . $max_attempts . " attempts");
                throw new Exception("Failed to generate unique quotation ID");
            }

            error_log("Generated quotation ID: " . $quotation_id);

            // Calculate total price
            $total = array_sum([
                floatval($_POST['laptop_p']),
                floatval($_POST['desktop_p']),
                floatval($_POST['monitor_p']),
                floatval($_POST['printer_p']),
                floatval($_POST['phone_p']),
                floatval($_POST['appliance_p']),
                floatval($_POST['wearables_p']),
                floatval($_POST['cables_p']),
                floatval($_POST['accessories_p'])
            ]);

            error_log("Calculated total price: " . $total);

            // Set validity to 7 days from now
            $validity = date('Y-m-d', strtotime('+7 days'));

            // Insert quotation into database
            $stmt = $conn->prepare("
                INSERT INTO quotation (
                    quotation_id, submission_id, points_id, user_id, admin_id, validity, 
                    laptop_p, desktop_p, monitor_p, printer_p, phone_p, 
                    appliance_p, wearables_p, cables_p, accessories_p, 
                    total, point_to_add, remarks, status, method, addressline1, addressline2, zipcode, city, state
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");

            $params = [
                $quotation_id,
                $_POST['submissionId'],
                $points['points_id'],
                $submission['user_id'],
                $admin_id,
                $validity,
                floatval($_POST['laptop_p']),
                floatval($_POST['desktop_p']),
                floatval($_POST['monitor_p']),
                floatval($_POST['printer_p']),
                floatval($_POST['phone_p']),
                floatval($_POST['appliance_p']),
                floatval($_POST['wearables_p']),
                floatval($_POST['cables_p']),
                floatval($_POST['accessories_p']),
                $total,
                $total_points,
                $_POST['remarks'] ?? null,
                'Pending',
                0,  // Set method as 0 (tinyint(1))
                null, // addressline1
                null, // addressline2
                null, // zipcode
                null, // city
                null  // state
            ];

            error_log("Attempting to insert quotation with parameters: " . print_r($params, true));

            try {
                $stmt->execute($params);
                error_log("Successfully inserted quotation");
            } catch (PDOException $e) {
                error_log("Database error during quotation insert: " . $e->getMessage());
                error_log("SQL State: " . $e->getCode());
                error_log("Error Info: " . print_r($stmt->errorInfo(), true));
                throw new Exception("Database error: " . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'quotation_id' => $quotation_id,
                'validity' => $validity,
                'total' => $total,
                'points' => $total_points
            ]);
            exit;
        } catch (Exception $e) {
            error_log("Error in quotation generation: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    } elseif ($_POST['action'] === 'reject_submission') {
        try {
            if (!isset($_POST['submission_id']) || !isset($_POST['reason'])) {
                throw new Exception('Missing required parameters');
            }
            $submission_id = $_POST['submission_id'];
            $reason = $_POST['reason'];
            $stmt = $conn->prepare("UPDATE submission SET status = 'Rejected', reason = :reason WHERE submission_id = :submission_id");
            $stmt->bindParam(':reason', $reason);
            $stmt->bindParam(':submission_id', $submission_id);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception('Submission not found or already rejected');
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Waste Submissions - Admin Dashboard</title>
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
            --max-width: 1920px;
            --content-width: 1640px;
        }
        body {
            display: flex;
            min-height: 100vh;
            background-color: var(--bg-light);
            color: var(--text-primary);
            max-width: var(--max-width);
            margin: 0 auto;
            overflow-x: hidden;
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
            z-index: 100;
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
            max-width: var(--content-width);
            width: 100%;
            box-sizing: border-box;
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
        .notification {
            padding: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .notification i {
            font-size: 24px;
            transition: color 0.3s ease;
        }
        .notification:hover {
            transform: scale(1.1);
        }
        .notification:hover i {
            color: var(--primary-color);
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
        @keyframes pulse-profile {
            0% {
                box-shadow: 0 0 5px 2px rgba(255, 255, 255, 0.8);
            }
            50% {
                box-shadow: 0 0 10px 5px rgba(255, 255, 255, 0.6);
            }
            100% {
                box-shadow: 0 0 5px 2px rgba(255, 255, 255, 0.8);
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
        .card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-2px);
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
            font-size: 1.5rem;
            color: var(--text-primary);
        }
        .search-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            background-color: white;
            padding: 12px 20px;
            border-radius: 8px;
            width: 400px;
            max-width: 100%;
            transition: all 0.3s ease;
            box-sizing: border-box;
            border: 1px solid #E2E8F0;
        }
        .search-bar i {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        .search-bar input {
            border: none;
            outline: none;
            width: 100%;
            color: var(--text-primary);
            font-size: 1rem;
        }
        .search-bar:hover, .search-bar:focus-within {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-color);
        }
        .search-bar:focus-within i {
            color: var(--primary-color);
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
        }
        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #E2E8F0;
        }
        th {
            background-color: var(--bg-light);
            font-weight: 600;
            color: var(--text-primary);
        }
        tr:hover {
            background-color: var(--bg-light);
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .status-approved {
            background-color: #C6F6D5;
            color: #2F855A;
        }
        .status-rejected {
            background-color: #FED7D7;
            color: #C53030;
        }
        .status-accepted {
            background-color: #C6F6D5;
            color: #228B22;
        }
        .status-completed {
            background-color: #E2E8F0;
            color: #6B7280;
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
        .view-button {
            background-color: var(--secondary-color);
            color: white;
        }
        .approve-button {
            background-color: #48BB78;
            color: white;
        }
        .reject-button {
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
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal.show {
            opacity: 1;
        }
        .modal-content {
            background-color: white;
            border-radius: 12px;
            padding: 24px;
            width: 600px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.7);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .modal.show .modal-content {
            transform: scale(1);
            opacity: 1;
        }
        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 200px;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        .modal-header h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.5rem;
        }
        .close-button {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            transition: color 0.3s ease;
            padding: 5px;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .close-button:hover {
            color: var(--text-primary);
            background-color: #f3f4f6;
        }
        .modal-body {
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .modal-footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            animation: fadeIn 0.3s ease;
        }
        .modal-button {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .modal-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .approve-button {
            background-color: #48BB78;
            color: white;
        }
        .approve-button:hover {
            background-color: #38A169;
        }
        .reject-button {
            background-color: #F56565;
            color: white;
        }
        .reject-button:hover {
            background-color: #E53E3E;
        }
        .ewaste-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .ewaste-image:hover {
            transform: scale(1.02);
        }
        /* Image Zoom Modal */
        .image-zoom-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            padding: 20px;
            box-sizing: border-box;
        }
        .zoomed-image {
            max-width: 90%;
            max-height: 90vh;
            margin: auto;
            display: block;
            object-fit: contain;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .close-zoom {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 30px;
            cursor: pointer;
            z-index: 10001;
        }
        .submission-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 20px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .detail-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }
        .detail-value {
            color: var(--text-primary);
            font-weight: 500;
        }
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
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
        @media (max-width: 1920px) {
            .main-content {
                padding: 20px;
            }
            .card {
                padding: 20px;
            }
            th, td {
                padding: 10px 12px;
            }
        }
        @media (max-width: 1600px) {
            :root {
                --content-width: 1360px;
            }
        }
        @media (max-width: 1366px) {
            :root {
                --content-width: 1086px;
            }
            .search-bar {
                width: 300px;
            }
        }
        /* Quotation Modal Styles */
        .quotation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .quotation-modal.show {
            opacity: 1;
        }
        .quotation-modal-content {
            background-color: white;
            border-radius: 12px;
            padding: 24px;
            width: 800px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.7);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .quotation-modal.show .quotation-modal-content {
            transform: scale(1);
            opacity: 1;
        }
        .price-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .price-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(80, 184, 142, 0.2);
            outline: none;
        }
        .price-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
            align-items: center;
            margin-bottom: 12px;
        }
        .price-label {
            font-weight: 500;
            color: var(--text-primary);
        }
        .total-row {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .points-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .points-header {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .points-header i {
            color: var(--primary-color);
        }
        .points-calculation {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        .points-total {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            justify-content: space-between;
        }
        .remarks-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            min-height: 100px;
            resize: vertical;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        .remarks-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(80, 184, 142, 0.2);
            outline: none;
        }
        .generate-button {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        .generate-button:hover {
            background-color: #3d9c7a;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        /* Responsive Styles */
        @media (max-width: 1400px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .submission-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .submission-stats {
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

            .submission-details {
                flex-direction: column;
            }

            .submission-section {
                width: 100%;
            }

            .ewaste-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .image-gallery {
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
            
            .submission-grid {
                grid-template-columns: 1fr;
            }

            .submission-stats {
                grid-template-columns: 1fr;
            }

            .ewaste-grid {
                grid-template-columns: 1fr;
            }

            .image-gallery {
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
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 10px;
            }
            
            .card {
                padding: 12px;
            }
            
            .submission-card {
                padding: 12px;
            }
            
            .submission-card h3 {
                font-size: 1rem;
            }
            
            .submission-card p {
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

            .submission-meta {
                flex-direction: column;
                gap: 5px;
            }

            .status-badge {
                font-size: 0.8rem;
                padding: 4px 8px;
            }

            .image-preview {
                height: 150px;
            }

            .form-actions button {
                width: 100%;
            }

            .tab-buttons {
                flex-wrap: wrap;
                gap: 5px;
            }

            .tab-button {
                flex: 1 1 calc(50% - 5px);
                font-size: 0.9rem;
                padding: 8px;
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

            .submission-details {
                page-break-inside: avoid;
            }

            .btn, .action-button {
                display: none;
            }

            .ewaste-grid {
                page-break-inside: avoid;
            }

            .image-gallery {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar from admin.php -->
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
            <a href="submission_admin.php" class="menu-item active">
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

        <div class="card">
            <div class="card-header">
                <h2>E-Waste Submissions</h2>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by ID, user name, or email...">
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Submission ID</th>
                            <th>Date</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Items</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="submissionTableBody">
                        <?php foreach ($submissions as $submission): ?>
                        <tr data-submission-id="<?php echo htmlspecialchars($submission['submission_id']); ?>" onclick="viewSubmission('<?php echo $submission['submission_id']; ?>')" style="cursor: pointer;">
                            <td><?php echo htmlspecialchars($submission['submission_id']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($submission['date'])); ?></td>
                            <td><?php echo htmlspecialchars($submission['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($submission['email']); ?></td>
                            <td>
                                <?php
                                $items = [];
                                if ($submission['laptop_qty'] > 0) $items[] = "Laptop: " . $submission['laptop_qty'];
                                if ($submission['desktop_qty'] > 0) $items[] = "Desktops/Servers: " . $submission['desktop_qty'];
                                if ($submission['monitor_qty'] > 0) $items[] = "Monitors/TVs: " . $submission['monitor_qty'];
                                if ($submission['printer_qty'] > 0) $items[] = "Printers/Projectors: " . $submission['printer_qty'];
                                if ($submission['phone_qty'] > 0) $items[] = "Smartphones/Tablets: " . $submission['phone_qty'];
                                if ($submission['appliance_qty'] > 0) $items[] = "Home Appliances: " . $submission['appliance_qty'];
                                if ($submission['wearables_qty'] > 0) $items[] = "Wearables: " . $submission['wearables_qty'];
                                if ($submission['cables_qty'] > 0) $items[] = "Cables/Wires: " . $submission['cables_qty'];
                                if ($submission['accessories_qty'] > 0) $items[] = "Peripherals/Accessories: " . $submission['accessories_qty'];
                                echo implode(", ", $items);
                                ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($submission['status']); ?>">
                                    <?php echo htmlspecialchars($submission['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <button class="page-button" onclick="window.location.href='?page=<?php echo $page-1; ?>'">Previous</button>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <button class="page-button <?php echo $i === $page ? 'active' : ''; ?>" 
                            onclick="window.location.href='?page=<?php echo $i; ?>'">
                        <?php echo $i; ?>
                    </button>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <button class="page-button" onclick="window.location.href='?page=<?php echo $page+1; ?>'">Next</button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Submission Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Submission Details</h3>
                <button class="close-button" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <img id="submissionImage" class="ewaste-image" src="" alt="E-Waste Image">
                <div class="submission-details">
                    <div class="detail-item">
                        <span class="detail-label">Submission ID</span>
                        <span class="detail-value" id="modalSubmissionId"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Date</span>
                        <span class="detail-value" id="modalDate"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">User Name</span>
                        <span class="detail-value" id="modalUserName"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Email</span>
                        <span class="detail-value" id="modalEmail"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status</span>
                        <span class="detail-value" id="modalStatus"></span>
                    </div>
                </div>
                <h4>Items Submitted</h4>
                <div class="submission-details">
                    <div class="detail-item">
                        <span class="detail-label">Laptops</span>
                        <span class="detail-value" id="modalLaptopQty"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Desktops/Servers</span>
                        <span class="detail-value" id="modalDesktopQty"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Monitors/TVs</span>
                        <span class="detail-value" id="modalMonitorQty"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Printers/Projectors</span>
                        <span class="detail-value" id="modalPrinterQty"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Smartphones/Tablets</span>
                        <span class="detail-value" id="modalPhoneQty"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Home Appliances</span>
                        <span class="detail-value" id="modalApplianceQty"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Wearables</span>
                        <span class="detail-value" id="modalWearablesQty"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Cables/Wires</span>
                        <span class="detail-value" id="modalCablesQty"></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Peripherals/Accessories</span>
                        <span class="detail-value" id="modalAccessoriesQty"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quotation Generation Modal -->
    <div class="quotation-modal" id="quotationModal">
        <div class="quotation-modal-content">
            <div class="modal-header">
                <h3>Generate Quotation</h3>
                <button class="close-button" onclick="closeQuotationModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="quotationForm">
                    <input type="hidden" id="submissionId" name="submissionId">
                    <div class="price-row">
                        <span class="price-label">Laptops Price (RM)</span>
                        <input type="number" class="price-input" name="laptop_p" step="0.01" min="0" required>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Desktops/Servers Price (RM)</span>
                        <input type="number" class="price-input" name="desktop_p" step="0.01" min="0" required>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Monitors/TVs Price (RM)</span>
                        <input type="number" class="price-input" name="monitor_p" step="0.01" min="0" required>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Printers/Projectors Price (RM)</span>
                        <input type="number" class="price-input" name="printer_p" step="0.01" min="0" required>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Smartphones/Tablets Price (RM)</span>
                        <input type="number" class="price-input" name="phone_p" step="0.01" min="0" required>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Home Appliances Price (RM)</span>
                        <input type="number" class="price-input" name="appliance_p" step="0.01" min="0" required>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Wearables Price (RM)</span>
                        <input type="number" class="price-input" name="wearables_p" step="0.01" min="0" required>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Cables/Wires Price (RM)</span>
                        <input type="number" class="price-input" name="cables_p" step="0.01" min="0" required>
                    </div>
                    <div class="price-row">
                        <span class="price-label">Peripherals/Accessories Price (RM)</span>
                        <input type="number" class="price-input" name="accessories_p" step="0.01" min="0" required>
                    </div>
                    <div class="price-row total-row" id="totalRow">
                        <span class="price-label">Total (RM)</span>
                        <span id="totalAmount">0.00</span>
                    </div>

                    <div class="points-section">
                        <div class="points-header">
                            <i class="fas fa-star"></i>
                            <span>Points Calculation</span>
                        </div>
                        <div id="pointsCalculation" class="points-calculation">
                            <!-- Points calculation will be populated here -->
                        </div>
                        <div class="points-total">
                            <span>Total Points to Add:</span>
                            <span id="totalPoints">0</span>
                        </div>
                    </div>

                    <textarea class="remarks-input" name="remarks" placeholder="Enter remarks here..."></textarea>
                    <button type="submit" class="generate-button">Generate Quotation</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Reject Reason Modal -->
    <div class="modal" id="rejectReasonModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reject Submission</h3>
                <button class="close-button" onclick="closeRejectReasonModal()">&times;</button>
            </div>
            <div class="modal-body">
                <label for="rejectReasonInput">Please provide a reason for rejection:</label>
                <textarea id="rejectReasonInput" class="remarks-input" placeholder="Type reason here..." required></textarea>
            </div>
            <div class="modal-footer">
                <button class="modal-button reject-button" onclick="submitRejectReason()">Reject</button>
                <button class="modal-button" onclick="closeRejectReasonModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Alert Modal -->
    <div class="modal" id="alertModal">
        <div class="modal-content" id="alertModalContent" style="max-width: 400px; text-align: center;">
            <div class="modal-header" style="justify-content: center; border-bottom: none;">
                <span id="alertModalIcon" style="font-size: 2rem;"></span>
            </div>
            <div class="modal-body">
                <span id="alertModalMessage"></span>
            </div>
            <div class="modal-footer" style="justify-content: center; border-top: none;">
                <button class="modal-button" onclick="closeAlertModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Image Zoom Modal -->
    <div class="image-zoom-modal" id="imageZoomModal">
        <span class="close-zoom">&times;</span>
        <img class="zoomed-image" id="zoomedImage" src="" alt="Zoomed Image">
    </div>

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const submissionTableBody = document.getElementById('submissionTableBody');
        const rows = submissionTableBody.getElementsByTagName('tr');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            Array.from(rows).forEach(row => {
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                // Check each cell
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

        // View submission details
        function viewSubmission(submissionId) {
            console.log('Viewing submission:', submissionId);
            
            const modal = document.getElementById('viewModal');
            modal.style.display = 'flex';
            
            // Force a reflow to ensure the animation plays
            modal.offsetHeight;
            
            modal.classList.add('show');

            // Show loading state
            const modalContent = modal.querySelector('.modal-content');
            modalContent.innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner"></div>
                </div>
            `;

            // Fetch submission details
            fetch(`submission_admin.php?action=get_details&submission_id=${encodeURIComponent(submissionId)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data); // Debug log
                
                if (data.success) {
                    const submission = data.submission;
                    console.log('Submission data:', submission); // Debug log
                    console.log('Status:', submission.status); // Debug log
                    console.log('Quotation ID:', submission.quotation_id); // Debug log
                    
                    // Determine if ewaste_image is a PDF or image
                    let fileType = null;
                    if (submission.ewaste_image) {
                        // Try to detect if it's a PDF (first 4 bytes: %PDF)
                        const b64 = submission.ewaste_image;
                        const firstBytes = atob(b64).slice(0, 4);
                        if (firstBytes === '%PDF') fileType = 'pdf';
                        else fileType = 'image';
                    }
                    // Set modal content
                    modalContent.innerHTML = `
                        <div class="modal-header">
                            <h3>Submission Details</h3>
                            <button class="close-button" onclick="closeModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            ${submission.ewaste_image ? (
                                fileType === 'pdf'
                                ? `<div style='margin-bottom:16px;'><a href="view_ewaste_file.php?submission_id=${encodeURIComponent(submission.submission_id)}" target="_blank" class="modal-button view-button">View PDF</a></div>`
                                : `<img id="submissionImage" class="ewaste-image" src="data:image/jpeg;base64,${submission.ewaste_image}" alt="E-Waste Image" onclick="zoomImage(this.src)">`
                            ) : `<img id="submissionImage" class="ewaste-image" src="../user/assets/homepage/no-image.png" alt="E-Waste Image">`}
                            <div class="submission-details">
                                <div class="detail-item">
                                    <span class="detail-label">Submission ID</span>
                                    <span class="detail-value">${submission.submission_id}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Date</span>
                                    <span class="detail-value">${new Date(submission.formatted_date || submission.date).toLocaleDateString()}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">User Name</span>
                                    <span class="detail-value">${submission.user_name || 'N/A'}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Email</span>
                                    <span class="detail-value">${submission.email || 'N/A'}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Status</span>
                                    <span class="detail-value status-badge status-${submission.status.toLowerCase()}">${submission.status}</span>
                                </div>
                                ${submission.status === 'Completed' && submission.quotation_id ? `
                                <div class="detail-item" style="margin-top: 10px;">
                                    <span class="detail-label">Quotation ID</span>
                                    <span class="detail-value" style="color: #50B88E; font-weight: 600; font-size: 1.1em;">${submission.quotation_id}</span>
                                </div>
                                ` : ''}
                            </div>
                            <h4>Items Submitted</h4>
                            <div class="submission-details">
                                <div class="detail-item">
                                    <span class="detail-label">Laptops</span>
                                    <span class="detail-value">${submission.laptop_qty || 0}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Desktops/Servers</span>
                                    <span class="detail-value">${submission.desktop_qty || 0}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Monitors/TVs</span>
                                    <span class="detail-value">${submission.monitor_qty || 0}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Printers/Projectors</span>
                                    <span class="detail-value">${submission.printer_qty || 0}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Smartphones/Tablets</span>
                                    <span class="detail-value">${submission.phone_qty || 0}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Home Appliances</span>
                                    <span class="detail-value">${submission.appliance_qty || 0}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Wearables</span>
                                    <span class="detail-value">${submission.wearables_qty || 0}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Cables/Wires</span>
                                    <span class="detail-value">${submission.cables_qty || 0}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Peripherals/Accessories</span>
                                    <span class="detail-value">${submission.accessories_qty || 0}</span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            ${submission.status === 'Pending' ? `
                                <button class="modal-button approve-button" onclick="updateStatus('${submission.submission_id}', 'Approved')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="modal-button reject-button" onclick="updateStatus('${submission.submission_id}', 'Rejected')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            ` : ''}
                        </div>
                    `;
                } else {
                    showAlert(data.message || 'Error fetching submission details', 'error');
                    closeModal();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error fetching submission details: ' + error.message, 'error');
                closeModal();
            });
        }

        // Update submission status
        function updateStatus(submissionId, status) {
            if (status === 'Approved') {
                showQuotationModal(submissionId);
            } else if (status === 'Rejected') {
                showRejectReasonModal(submissionId);
            } else {
                if (!confirm(`Are you sure you want to ${status.toLowerCase()} this submission?`)) {
                    return;
                }
                submitStatusUpdate(submissionId, status);
            }
        }

        let rejectSubmissionId = null;
        function showRejectReasonModal(submissionId) {
            rejectSubmissionId = submissionId;
            document.getElementById('rejectReasonInput').value = '';
            const modal = document.getElementById('rejectReasonModal');
            modal.style.display = 'flex';
            modal.offsetHeight;
            modal.classList.add('show');
        }
        function closeRejectReasonModal() {
            const modal = document.getElementById('rejectReasonModal');
            const modalContent = modal.querySelector('.modal-content');
            modalContent.style.transform = 'scale(0.7)';
            modalContent.style.opacity = '0';
            setTimeout(() => {
                modal.classList.remove('show');
                modal.style.display = 'none';
                modalContent.style.transform = '';
                modalContent.style.opacity = '';
            }, 300);
        }
        function submitRejectReason() {
            const reason = document.getElementById('rejectReasonInput').value.trim();
            if (!reason) {
                showAlert('Please provide a reason for rejection.', 'error');
                return;
            }
            // AJAX to update reason and status
            const formData = new FormData();
            formData.append('action', 'reject_submission');
            formData.append('submission_id', rejectSubmissionId);
            formData.append('reason', reason);
            fetch('submission_admin.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Submission rejected successfully');
                    closeRejectReasonModal();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert(data.message || 'Error rejecting submission', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error rejecting submission', 'error');
            });
        }

        function showQuotationModal(submissionId) {
            const modal = document.getElementById('quotationModal');
            document.getElementById('submissionId').value = submissionId;
            modal.style.display = 'flex';
            // Force a reflow to ensure the animation plays
            modal.offsetHeight;
            modal.classList.add('show');

            // Fetch submission details and points calculation
            fetch(`submission_admin.php?action=get_details&submission_id=${encodeURIComponent(submissionId)}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const submission = data.submission;
                    // Dynamically build price input fields based on submission quantities
                    buildQuotationFormFields(submission);
                    updatePointsCalculation(submission);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error fetching submission details', 'error');
            });
        }

        function buildQuotationFormFields(submission) {
            const itemFields = [
                { key: 'laptop_qty', label: 'Laptops', input: 'laptop_p' },
                { key: 'desktop_qty', label: 'Desktops/Servers', input: 'desktop_p' },
                { key: 'monitor_qty', label: 'Monitors/TVs', input: 'monitor_p' },
                { key: 'printer_qty', label: 'Printers/Projectors', input: 'printer_p' },
                { key: 'phone_qty', label: 'Smartphones/Tablets', input: 'phone_p' },
                { key: 'appliance_qty', label: 'Home Appliances', input: 'appliance_p' },
                { key: 'wearables_qty', label: 'Wearables', input: 'wearables_p' },
                { key: 'cables_qty', label: 'Cables/Wires', input: 'cables_p' },
                { key: 'accessories_qty', label: 'Peripherals/Accessories', input: 'accessories_p' }
            ];
            const form = document.getElementById('quotationForm');
            // Remove all price-row fields except total, points, remarks, and button
            Array.from(form.querySelectorAll('.price-row')).forEach(row => {
                if (!row.id || row.id !== 'totalRow') row.remove();
            });
            // Always insert price input fields before the totalRow
            let totalRow = document.getElementById('totalRow');
            itemFields.forEach(item => {
                if (parseInt(submission[item.key]) > 0) {
                    const div = document.createElement('div');
                    div.className = 'price-row';
                    div.innerHTML = `
                        <span class="price-label">${item.label} Price (RM)</span>
                        <input type="number" class="price-input" name="${item.input}" step="0.01" min="0" required>
                    `;
                    form.insertBefore(div, totalRow);
                }
            });
            // Re-attach event listeners for total calculation
            document.querySelectorAll('.price-input').forEach(input => {
                input.addEventListener('input', calculateTotal);
            });
            calculateTotal();
        }

        function updatePointsCalculation(submission) {
            // Fetch points values from points table
            fetch('submission_admin.php?action=get_points', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const points = data.points;
                    let totalPoints = 0;
                    let calculationHTML = '';

                    const items = [
                        { qty: 'laptop_qty', points: 'laptop_po', label: 'Laptops' },
                        { qty: 'desktop_qty', points: 'desktop_po', label: 'Desktops/Servers' },
                        { qty: 'monitor_qty', points: 'monitor_po', label: 'Monitors/TVs' },
                        { qty: 'printer_qty', points: 'printer_po', label: 'Printers/Projectors' },
                        { qty: 'phone_qty', points: 'phone_po', label: 'Smartphones/Tablets' },
                        { qty: 'appliance_qty', points: 'appliance_po', label: 'Home Appliances' },
                        { qty: 'wearables_qty', points: 'wearables_po', label: 'Wearables' },
                        { qty: 'cables_qty', points: 'cables_po', label: 'Cables/Wires' },
                        { qty: 'accessories_qty', points: 'accessories_po', label: 'Peripherals/Accessories' }
                    ];

                    const pointsCalcElem = document.getElementById('pointsCalculation');
                    const totalPointsElem = document.getElementById('totalPoints');
                    if (!pointsCalcElem || !totalPointsElem) {
                        showAlert('Quotation form is missing required fields. Please reload the page.', 'error');
                        return;
                    }

                    items.forEach(item => {
                        const quantity = parseInt(submission[item.qty]) || 0;
                        const pointsPerItem = parseInt(points[item.points]) || 0;
                        const itemPoints = quantity * pointsPerItem;
                        totalPoints += itemPoints;
                        if (quantity > 0) {
                            calculationHTML += `
                                <div>${item.label} (${quantity} × ${pointsPerItem} points)</div>
                                <div>${itemPoints} points</div>
                            `;
                        }
                    });

                    pointsCalcElem.innerHTML = calculationHTML;
                    totalPointsElem.textContent = totalPoints;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error fetching points calculation', 'error');
            });
        }

        function closeModal() {
            const modal = document.getElementById('viewModal');
            const modalContent = modal.querySelector('.modal-content');
            
            // Add closing animation
            modalContent.style.transform = 'scale(0.7)';
            modalContent.style.opacity = '0';
            
            // Remove show class after animation
            setTimeout(() => {
                modal.classList.remove('show');
                modal.style.display = 'none';
                // Reset transform for next open
                modalContent.style.transform = '';
                modalContent.style.opacity = '';
            }, 300);
        }

        // Show alert as modal popup
        function showAlert(message, type = 'success') {
            const modal = document.getElementById('alertModal');
            const iconSpan = document.getElementById('alertModalIcon');
            const messageSpan = document.getElementById('alertModalMessage');
            // Set icon and color
            if (type === 'success') {
                iconSpan.innerHTML = '<i class="fas fa-check-circle" style="color: #38A169;"></i>';
            } else {
                iconSpan.innerHTML = '<i class="fas fa-exclamation-circle" style="color: #C53030;"></i>';
            }
            messageSpan.textContent = message;
            modal.style.display = 'flex';
            modal.offsetHeight;
            modal.classList.add('show');
        }
        function closeAlertModal() {
            const modal = document.getElementById('alertModal');
            const modalContent = modal.querySelector('.modal-content');
            modalContent.style.transform = 'scale(0.7)';
            modalContent.style.opacity = '0';
            setTimeout(() => {
                modal.classList.remove('show');
                modal.style.display = 'none';
                modalContent.style.transform = '';
                modalContent.style.opacity = '';
            }, 300);
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const viewModal = document.getElementById('viewModal');
            const rejectModal = document.getElementById('rejectReasonModal');
            const alertModal = document.getElementById('alertModal');
            const quotationModal = document.getElementById('quotationModal');
            const imageZoomModal = document.getElementById('imageZoomModal');

            // Check if the click was outside the modal content
            if (event.target === viewModal) {
                closeModal();
            }
            if (event.target === rejectModal) {
                closeRejectReasonModal();
            }
            if (event.target === alertModal) {
                closeAlertModal();
            }
            if (event.target === quotationModal) {
                closeQuotationModal();
            }
            if (event.target === imageZoomModal) {
                imageZoomModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });

        // Add this to your existing table row generation
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tr[data-submission-id]');
            rows.forEach(row => {
                row.addEventListener('click', function(e) {
                    const submissionId = this.getAttribute('data-submission-id');
                    console.log('Row clicked, submission ID:', submissionId); // Debug log
                    viewSubmission(submissionId);
                });
            });
        });

        // Calculate total when prices change
        document.querySelectorAll('.price-input').forEach(input => {
            input.addEventListener('input', calculateTotal);
        });

        function calculateTotal() {
            const inputs = document.querySelectorAll('.price-input');
            let total = 0;
            inputs.forEach(input => {
                total += parseFloat(input.value || 0);
            });
            const totalAmountElem = document.getElementById('totalAmount');
            if (totalAmountElem) {
                totalAmountElem.textContent = total.toFixed(2);
            }
        }

        // Handle quotation form submission
        document.getElementById('quotationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'generate_quotation');
            fetch('submission_admin.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeQuotationModal();
                    // Update submission status after successful quotation generation
                    submitStatusUpdate(formData.get('submissionId'), 'Accepted', true);
                } else {
                    showAlert(data.message || 'Error generating quotation', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error generating quotation', 'error');
            });
        });

        // Modified submitStatusUpdate to optionally show alert after status update
        function submitStatusUpdate(submissionId, status, showQuotationSuccess) {
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('submission_id', submissionId);
            formData.append('status', status);

            fetch('submission_admin.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Status update response:', data);
                if (data.success) {
                    if (showQuotationSuccess) {
                        showAlert('Quotation generated and submission accepted successfully');
                    } else {
                        showAlert(`Submission ${status.toLowerCase()} successfully`);
                    }
                    // Refresh the page to show updated data
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert(data.message || 'Error updating submission status', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error updating submission status', 'error');
            });
        }

        // Close quotation modal when clicking outside
        document.addEventListener('click', function(event) {
            const modal = document.getElementById('quotationModal');
            if (event.target === modal) {
                closeQuotationModal();
            }
        });

        function closeQuotationModal() {
            const modal = document.getElementById('quotationModal');
            const modalContent = modal.querySelector('.quotation-modal-content');
            
            // Add closing animation
            modalContent.style.transform = 'scale(0.7)';
            modalContent.style.opacity = '0';
            
            setTimeout(() => {
                modal.classList.remove('show');
                modal.style.display = 'none';
                modalContent.style.transform = '';
                modalContent.style.opacity = '';
            }, 300);
        }

        // Find the menu-item with the shopping cart icon
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

        // Image zoom functionality
        function zoomImage(src) {
            const zoomModal = document.getElementById('imageZoomModal');
            const zoomedImg = document.getElementById('zoomedImage');
            zoomedImg.src = src;
            zoomModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // Close zoom modal
        document.querySelector('.close-zoom').addEventListener('click', function() {
            document.getElementById('imageZoomModal').style.display = 'none';
            document.body.style.overflow = '';
        });

        // Close zoom modal when clicking outside
        document.getElementById('imageZoomModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
                document.body.style.overflow = '';
            }
        });

        // Close zoom modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                document.getElementById('imageZoomModal').style.display = 'none';
                document.body.style.overflow = '';
            }
        });
    </script>
</body>
</html> 
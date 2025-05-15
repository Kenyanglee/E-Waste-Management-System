<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: auth.php");
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

// Add this after the database connection code at the top
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['handover_method']) && $_POST['handover_method'] === 'dropoff') {
    $quotation_id = $_POST['quotationId'];
    
    // Check if a dropoff already exists for this quotation
    $sql = "SELECT dropoff_id FROM dropoff WHERE quotation_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $quotation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        // Dropoff already exists for this quotation
        $existing = $result->fetch_assoc();
        $response = array(
            'success' => false,
            'message' => 'Drop-off already exists for this quotation.',
            'dropoff_id' => $existing['dropoff_id']
        );
        if (isset($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        // Optionally, handle non-AJAX case here
    }
    
    // Get submission_id from quotation
    $sql = "SELECT submission_id FROM quotation WHERE quotation_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $quotation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $quotation_data = $result->fetch_assoc();
    $submission_id = $quotation_data['submission_id'];

    // Get the next available drop-off ID
    $sql = "SELECT dropoff_id FROM dropoff ORDER BY CAST(SUBSTRING(dropoff_id, 6) AS UNSIGNED)";
    $result = $conn->query($sql);
    
    $used_numbers = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $number = intval(substr($row['dropoff_id'], 5)); // Extract number after 'drop#'
            $used_numbers[] = $number;
        }
    }
    
    // Find the first available number
    $next_number = 1;
    sort($used_numbers);
    foreach ($used_numbers as $num) {
        if ($num != $next_number) {
            break;
        }
        $next_number++;
    }
    
    $dropoff_id = "drop#" . $next_number;
    
    // Calculate drop-off date (7 days from now)
    $dropoff_date = new DateTime();
    $dropoff_date->modify('+7 days');
    $dropoff_date_str = $dropoff_date->format('Y-m-d');
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update quotation method
        $sql = "UPDATE quotation SET method = 0, status = 'Accepted' WHERE quotation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $quotation_id);
        $stmt->execute();
        
        // Update submission status
        $sql = "UPDATE submission SET status = 'Completed' WHERE submission_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $submission_id);
        $stmt->execute();
        
        // Insert into dropoff table
        $sql = "INSERT INTO dropoff (dropoff_id, quotation_id, dropoff_date, status) VALUES (?, ?, ?, 0)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $dropoff_id, $quotation_id, $dropoff_date_str);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        $response = array(
            'success' => true,
            'message' => 'Drop-off information saved successfully',
            'dropoff_id' => $dropoff_id,
            'dropoff_date' => $dropoff_date_str
        );
        
        if (isset($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $response = array(
            'success' => false,
            'message' => 'Error saving drop-off information: ' . $e->getMessage()
        );
        
        if (isset($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }
}

// Add this after the dropoff handling code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['handover_method']) && $_POST['handover_method'] === 'pickup') {
    $quotation_id = $_POST['quotationId'];
    $pickup_address = $_POST['pickup_address'];
    $user_id = $_SESSION['user']['user_id'];

    // Check if a delivery already exists for this quotation
    $sql = "SELECT delivery_id FROM delivery WHERE quotation_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $quotation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        // Delivery already exists for this quotation
        $existing = $result->fetch_assoc();
        $response = array(
            'success' => false,
            'message' => 'Delivery already exists for this quotation.',
            'delivery_id' => $existing['delivery_id']
        );
        if (isset($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        // Optionally, handle non-AJAX case here
    }

    // Get submission_id from quotation
    $sql = "SELECT submission_id FROM quotation WHERE quotation_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $quotation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $quotation_data = $result->fetch_assoc();
    $submission_id = $quotation_data['submission_id'];

    // Get the next available delivery ID
    $sql = "SELECT delivery_id FROM delivery ORDER BY CAST(SUBSTRING(delivery_id, 10) AS UNSIGNED)";
    $result = $conn->query($sql);
    
    $used_numbers = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $number = intval(substr($row['delivery_id'], 9)); // Extract number after 'delivery#'
            $used_numbers[] = $number;
        }
    }
    
    // Find the first available number
    $next_number = 1;
    sort($used_numbers);
    foreach ($used_numbers as $num) {
        if ($num != $next_number) {
            break;
        }
        $next_number++;
    }
    
    $delivery_id = "delivery#" . $next_number;
    
    // Calculate dates
    $current_date = new DateTime();
    $est_arrival = new DateTime();
    $est_arrival->modify('+14 days');
    
    $current_date_str = $current_date->format('Y-m-d');
    $est_arrival_str = $est_arrival->format('Y-m-d');
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update quotation status and address
        $sql = "UPDATE quotation SET status = 'Accepted', method = 1, address = ? WHERE quotation_id = ?";
        $stmt = $conn->prepare($sql);
        $address_value = "address" . $pickup_address;
        $stmt->bind_param("ss", $address_value, $quotation_id);
        $stmt->execute();

        // Update submission status
        $sql = "UPDATE submission SET status = 'Completed' WHERE submission_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $submission_id);
        $stmt->execute();
        
        // Insert into delivery table
        $sql = "INSERT INTO delivery (delivery_id, date, status, est_arrival, user_id, quotation_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $status = "Assigning Driver";
        $stmt->bind_param("ssssss", $delivery_id, $current_date_str, $status, $est_arrival_str, $user_id, $quotation_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Return success response
        $response = array(
            'success' => true,
            'message' => 'Pickup information saved successfully',
            'delivery_id' => $delivery_id,
            'est_arrival' => $est_arrival_str
        );
        
        if (isset($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $response = array(
            'success' => false,
            'message' => 'Error saving pickup information: ' . $e->getMessage()
        );
        
        if (isset($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }
}

// Add this PHP handler after the existing POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reject_quotation') {
    $quotation_id = $_POST['quotationId'];
    
    // Get submission_id from quotation
    $sql = "SELECT submission_id FROM quotation WHERE quotation_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $quotation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $quotation_data = $result->fetch_assoc();
    $submission_id = $quotation_data['submission_id'];
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update quotation status
        $sql = "UPDATE quotation SET status = 'Rejected' WHERE quotation_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $quotation_id);
        $stmt->execute();
        
        // Update submission status
        $sql = "UPDATE submission SET status = 'Completed' WHERE submission_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $submission_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $response = array(
            'success' => true,
            'message' => 'Quotation rejected successfully'
        );
        
        if (isset($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $response = array(
            'success' => false,
            'message' => 'Error rejecting quotation: ' . $e->getMessage()
        );
        
        if (isset($_POST['is_ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }
}

// Get user data
$user_id = $_SESSION['user']['user_id'];
$sql = "SELECT * FROM user WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get user's points
$points = $user['point'];

// Get user's name
$user_name = $user['user_name'];

// Get user's profile picture
$profile_pic = $user['profile_pic'] ? 'data:image/jpeg;base64,' . base64_encode($user['profile_pic']) : 'assets/account/account.png';

// Get user's submissions
$sql = "SELECT s.*, q.quotation_id 
        FROM submission s 
        LEFT JOIN quotation q ON s.submission_id = q.submission_id 
        WHERE s.user_id = ? 
        ORDER BY s.date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$submissions_result = $stmt->get_result();
$submissions = [];
while ($row = $submissions_result->fetch_assoc()) {
    $submissions[] = $row;
}

// Custom sorting function for submissions
function sortSubmissions($a, $b) {
    // Define status priority
    $statusPriority = [
        'Pending' => 1,
        'Accepted' => 2,
        'Rejected' => 3,
        'Completed' => 4
    ];
    
    // Compare status first
    $statusA = $a['status'];
    $statusB = $b['status'];
    
    if ($statusPriority[$statusA] !== $statusPriority[$statusB]) {
        return $statusPriority[$statusA] - $statusPriority[$statusB];
    }
    
    // If status is the same, compare dates (newest first)
    return strtotime($b['date']) - strtotime($a['date']);
}

// Sort submissions
usort($submissions, 'sortSubmissions');

// Get user's quotations (joining with submission to get user_id)
$sql = "SELECT q.*, s.date, s.laptop_qty, s.desktop_qty, s.monitor_qty, s.printer_qty, s.phone_qty, s.appliance_qty, s.wearables_qty, s.cables_qty, s.accessories_qty, 
        p.laptop_po, p.desktop_po, p.monitor_po, p.printer_po, p.phone_po, p.appliance_po, p.wearables_po, p.cables_po, p.accessories_po,
        a.name AS admin_name, u.user_name, u.addressline1_1, u.addressline2_1, u.zipcode_1, u.city_1, u.state_1,
        d.delivery_id, do.dropoff_id, pay.payment_id
        FROM quotation q 
        JOIN submission s ON q.submission_id = s.submission_id 
        JOIN points p ON q.points_id = p.points_id
        LEFT JOIN admin a ON q.admin_id = a.admin_id
        LEFT JOIN user u ON s.user_id = u.user_id
        LEFT JOIN delivery d ON q.quotation_id = d.quotation_id
        LEFT JOIN dropoff do ON q.quotation_id = do.quotation_id
        LEFT JOIN payment pay ON q.quotation_id = pay.quotation_id
        WHERE s.user_id = ? 
        GROUP BY q.quotation_id
        ORDER BY 
            CASE 
                WHEN q.status = 'Expired' THEN 1 
                ELSE 0 
            END,
            DATE_SUB(q.validity, INTERVAL 7 DAY) DESC,
            s.date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$quotations_result = $stmt->get_result();
$quotations = [];
while ($row = $quotations_result->fetch_assoc()) {
    $quotations[] = $row;
}

// Sort quotations by status and date
function sortQuotations($a, $b) {
    $statusPriority = [
        'Pending' => 1,
        'Rejected' => 2,
        'Accepted' => 3,
        'Completed' => 4,
        'Expired' => 5
    ];
    $statusA = $a['status'];
    $statusB = $b['status'];
    $priorityA = isset($statusPriority[$statusA]) ? $statusPriority[$statusA] : 99;
    $priorityB = isset($statusPriority[$statusB]) ? $statusPriority[$statusB] : 99;
    if ($priorityA !== $priorityB) {
        return $priorityA - $priorityB;
    }
    // If status is the same, sort by latest date
    return strtotime($b['date']) - strtotime($a['date']);
}
usort($quotations, 'sortQuotations');

// Get user's deliveries (tracking)
$sql = "SELECT d.*, q.addressline1, q.addressline2, q.zipcode, q.city, q.state 
        FROM delivery d 
        LEFT JOIN quotation q ON d.quotation_id = q.quotation_id 
        LEFT JOIN payment p ON d.delivery_id = p.delivery_id 
        WHERE d.user_id = ? 
        ORDER BY d.date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$delivery_result = $stmt->get_result();
$deliveries = [];
while ($row = $delivery_result->fetch_assoc()) {
    $deliveries[] = $row;
}

// Get user's dropoffs (tracking)
$sql = "SELECT d.*, q.user_id 
        FROM dropoff d 
        JOIN quotation q ON d.quotation_id = q.quotation_id 
        LEFT JOIN payment p ON d.dropoff_id = p.dropoff_id 
        WHERE q.user_id = ? 
        GROUP BY d.dropoff_id 
        ORDER BY d.dropoff_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$dropoff_result = $stmt->get_result();
$dropoffs = [];
while ($row = $dropoff_result->fetch_assoc()) {
    $dropoffs[] = $row;
}

// Merge deliveries and dropoffs for tracking
$tracking = [];
$seen_dropoff_ids = [];
foreach ($deliveries as $delivery) {
    if (!empty($delivery['delivery_id'])) {
        $delivery['type'] = 'delivery';
        $delivery['tracking_date'] = $delivery['date']; // Use delivery date directly
        $tracking[] = $delivery;
    }
}
foreach ($dropoffs as $dropoff) {
    if (!empty($dropoff['dropoff_id']) && !in_array($dropoff['dropoff_id'], $seen_dropoff_ids)) {
        $dropoff['type'] = 'dropoff';
        // Calculate dropoff date by subtracting 7 days
        $dropoff_date = new DateTime($dropoff['dropoff_date']);
        $dropoff_date->modify('-7 days');
        $dropoff['tracking_date'] = $dropoff_date->format('Y-m-d');
        $tracking[] = $dropoff;
        $seen_dropoff_ids[] = $dropoff['dropoff_id'];
    }
}
// Sort by date descending
usort($tracking, function($a, $b) {
    return strtotime($b['tracking_date']) - strtotime($a['tracking_date']);
});

// After the database connection code, add this to fetch addresses
$sql = "SELECT 
    addressline1_1, addressline2_1, zipcode_1, city_1, state_1,
    addressline1_2, addressline2_2, zipcode_2, city_2, state_2,
    addressline1_3, addressline2_3, zipcode_3, city_3, state_3
FROM user WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$addresses = $result->fetch_assoc();

// Function to get status color class
function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'pending': return 'status-pending';
        case 'accepted': return 'status-accepted';
        case 'completed': return 'status-completed';
        case 'rejected': return 'status-rejected';
        case 'expired': return 'status-expired';
        default: return 'status-pending';
    }
}

// Add this function in PHP to map delivery status to badge HTML
function getPickupStatusBadge($status) {
    $statusMap = [
        1 => ['class' => 'status-assigning', 'label' => 'Assigning Driver', 'color' => '#cd7800', 'text' => '#ffffff'],
        2 => ['class' => 'status-assigned', 'label' => 'Driver Assigned', 'color' => '#1E40AF', 'text' => '#ffffff'],
        3 => ['class' => 'status-pickedup', 'label' => 'Picked Up', 'color' => '#00bb0c', 'text' => '#ffffff'],
        4 => ['class' => 'status-received', 'label' => 'Received', 'color' => '#b5b5b5', 'text' => '#ffffff'],
    ];
    $status = (int)$status;
    if (isset($statusMap[$status])) {
        $info = $statusMap[$status];
        return '<span class="status-badge ' . $info['class'] . '" style="background-color:' . $info['color'] . ';color:' . $info['text'] . ';">' . $info['label'] . '</span>';
    }
    // fallback: default to Assigning Driver
    $info = $statusMap[1];
    return '<span class="status-badge ' . $info['class'] . '" style="background-color:' . $info['color'] . ';color:' . $info['text'] . ';">' . $info['label'] . '</span>';
}

// Add this function to handle dropoff status
function getDropoffStatusBadge($status) {
    $statusMap = [
        0 => ['class' => 'status-pending', 'label' => 'Pending', 'color' => '#cd7800', 'text' => '#ffffff'],
        1 => ['class' => 'status-done', 'label' => 'Done', 'color' => '#b5b5b5', 'text' => '#ffffff'],
        2 => ['class' => 'status-expired', 'label' => 'Expired', 'color' => '#800000', 'text' => '#ffffff'],
    ];
    $status = (int)$status;
    if (isset($statusMap[$status])) {
        $info = $statusMap[$status];
        return '<span class="status-badge ' . $info['class'] . '" style="background-color:' . $info['color'] . ';color:' . $info['text'] . ';">' . $info['label'] . '</span>';
    }
    // fallback: default to Pending
    $info = $statusMap[0];
    return '<span class="status-badge ' . $info['class'] . '" style="background-color:' . $info['color'] . ';color:' . $info['text'] . ';">' . $info['label'] . '</span>';
}

// Get user's history (with payment, user, and quotation details)
$sql = "SELECT h.*, 
               p.bank_acc, p.bank_name, p.name AS acc_holder, q.total AS amount, q.point_to_add, p.date_paid, 
               q.quotation_id, q.status AS quotation_status, q.method AS quotation_method, 
               u.user_name, u.email, u.contact_number
        FROM history h
        LEFT JOIN payment p ON h.payment_id = p.payment_id
        LEFT JOIN quotation q ON p.quotation_id = q.quotation_id
        LEFT JOIN user u ON h.user_id = u.user_id
        WHERE h.user_id = ?
        ORDER BY p.date_paid DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$history_result = $stmt->get_result();
$histories = [];
while ($row = $history_result->fetch_assoc()) {
    $histories[] = $row;
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8" />
    <link rel="stylesheet" href="style/globals.css" />
    <link rel="stylesheet" href="style/account.css" />
    <style>
        /* Profile Picture Styles */
        .picture {
            border-radius: 50%;
            overflow: hidden;
            width: 150px;
            height: 150px;
        }

        .picture img.profile {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* Section Styles */
        .section {
            margin: 60px 0;
            padding: 0;
            position: relative;
            z-index: 1;
            width: 100%;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            padding: 10px 0;
        }

        .section-title {
            font-family: "Arial Rounded MT Bold", sans-serif;
            font-size: 24px;
            color: #ffffff;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::after {
            content: '▼';
            font-size: 16px;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-block;
        }

        .section.collapsed .section-title::after {
            transform: rotate(-90deg);
        }

        .section-content {
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            max-height: 1000px;
            opacity: 1;
            transform: translateY(0);
        }

        .section.collapsed .section-content {
            max-height: 0;
            opacity: 0;
            transform: translateY(-10px);
        }

        .section-title a {
            font-family: "Arial Rounded MT Bold", sans-serif;
            color: #ffffff;
            text-decoration: none;
            font-size: 16px;
        }

        .section-title a:hover {
            text-decoration: underline;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(4, 280px);
            gap: 30px;
            margin-top: 20px;
            padding: 0;
            justify-content: center;
            width: 100%;
        }

        .section .card {
            background: rgba(0, 0, 0, 0.4) !important;
            border: 3px solid #59b8a0;
            border-radius: 30px;
            padding: 20px;
            color: white;
            position: relative;
            width: 280px;
            height: 180px;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin: 0;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-date {
            font-family: "Arial Rounded MT Bold", sans-serif;
            color: #ffffff;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .card-id {
            font-family: "Arial Rounded MT Bold", sans-serif;
            color: #59b8a0;
            font-size: 20px;
            margin-bottom: 20px;
        }

        .status-badge {
            font-family: "Arial Rounded MT Bold", sans-serif;
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 16px;
            color: white;
            text-align: center;
            min-width: 120px;
        }

        .status-pending { background: #cd7800; }
        .status-accepted { background: #00bb0c; }
        .status-completed { background: #b5b5b5; }
        .status-rejected { background: #c90000; }
        .status-expired { background: #800000 !important; }
        .status-dropoff-completed { background: #00bb0c !important; }

        .divider {
            height: 2px;
            background: #59b8a0;
            margin: 20px 0;
            width: 100%;
        }

        .no-items {
            font-family: "Arial Rounded MT Bold", sans-serif;
            text-align: center;
            color: #ffffff;
            padding: 20px;
            font-style: italic;
            width: 280px;
            margin: 0 auto;
            grid-column: 1 / -1;
            font-size: 16px;
        }

        /* Fix for sections visibility */
        .account .div {
            min-height: 100vh;
            padding-bottom: 100px;
        }

        .sections-container {
            position: relative;
            z-index: 1;
            margin-top: 600px;
            padding: 0;
            width: 1210px;
            margin-left: auto;
            margin-right: auto;
        }

        .section-header a {
            font-family: "Arial Rounded MT Bold", sans-serif;
            color: #ffffff !important;
            text-decoration: none;
            font-size: 16px;
        }

        .section-header a:hover {
            color: #ffffff !important;
            text-decoration: underline;
        }

        /* Modal Styles */
        .modal {
            opacity: 0 !important;
            pointer-events: none !important;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            overflow-y: auto;
            transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .modal.show {
            opacity: 1 !important;
            pointer-events: auto !important;
        }
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translate(-50%, -50%) scale(0.85);
            opacity: 0;
        }
        .modal.show .modal-content {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 10001;
        }

        .modal-title {
            font-family: "Arial Rounded MT Bold", sans-serif;
            font-size: 24px;
            color: #59b8a0;
            margin: 0;
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            margin: 0;
        }

        .modal-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            position: relative;
            z-index: 10001;
        }

        .modal-info {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .modal-info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background: rgba(89, 184, 160, 0.1);
            border-radius: 10px;
        }

        .modal-info-label {
            font-family: "Arial Rounded MT Bold", sans-serif;
            color: #59b8a0;
            font-weight: bold;
        }

        .modal-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #59b8a0;
        }

        .modal-status {
            font-family: "Arial Rounded MT Bold", sans-serif;
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 16px;
            z-index: 10001;
        }

        .cursor-pointer {
            cursor: pointer;
        }

        /* Ensure modal is above all other content */
        .account .div {
            position: relative;
            z-index: 1;
        }

        .sections-container {
            position: relative;
            z-index: 1;
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
            z-index: 10000;
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

        /* Custom scrollbar for modal content */
        .modal-content::-webkit-scrollbar {
            width: 10px;
        }
        .modal-content::-webkit-scrollbar-thumb {
            background: #59b8a0;
            border-radius: 8px;
        }
        .modal-content::-webkit-scrollbar-track {
            background: #222e2a;
            border-radius: 8px;
        }
        /* For Firefox */
        .modal-content {
            scrollbar-width: thin;
            scrollbar-color: #59b8a0 #222e2a;
        }

        /* Custom scrollbar styles */
        #addressSelection::-webkit-scrollbar {
            width: 10px;
        }
        #addressSelection::-webkit-scrollbar-thumb {
            background: #59b8a0;
            border-radius: 8px;
        }
        #addressSelection::-webkit-scrollbar-track {
            background: #222e2a;
            border-radius: 8px;
        }

        #paymentForm::-webkit-scrollbar {
            width: 10px;
        }
        #paymentForm::-webkit-scrollbar-thumb {
            background: #59b8a0;
            border-radius: 8px;
        }
        #paymentForm::-webkit-scrollbar-track {
            background: #222e2a;
            border-radius: 8px;
        }

        /* Custom scrollbar styles */
        #addressSelection > div::-webkit-scrollbar {
            height: 8px;
        }
        #addressSelection > div::-webkit-scrollbar-thumb {
            background: #59b8a0;
            border-radius: 4px;
        }
        #addressSelection > div::-webkit-scrollbar-track {
            background: #222e2a;
            border-radius: 4px;
        }

        /* Add this to your existing styles */
        .alert-pill {
            position: fixed;
            top: -100px;
            left: 50%;
            transform: translateX(-50%);
            padding: 16px 32px;
            border-radius: 50px;
            color: #fff;
            font-family: 'Arial Rounded MT Bold', sans-serif;
            font-size: 16px;
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: top 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .alert-pill.success {
            background-color: #59b8a0;
        }

        .alert-pill.error {
            background-color:rgb(255, 0, 0);
        }

        .alert-pill.show {
            top: 24px;
        }

        .alert-pill .icon {
            font-size: 20px;
        }

        /* Add this to your existing styles */
        .save-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed !important;
        }

        .error-message {
            font-family: 'Arial Rounded MT Bold', sans-serif;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .error-message[style*="display: block"] {
            opacity: 1;
            transform: translateY(0);
        }

        /* Update the confirmation modal styles to match existing modal animations */
        .confirmation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10001;
            backdrop-filter: blur(5px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .confirmation-modal.show {
            display: block;
            opacity: 1;
            pointer-events: auto;
        }

        .confirmation-content {
            background: #18322b;
            padding: 32px;
            border-radius: 20px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            font-family: 'Arial Rounded MT Bold', sans-serif;
            color: #fff;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.85);
            opacity: 0;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .confirmation-modal.show .confirmation-content {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }

        .confirmation-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: #fff;
            font-weight: 700;
            font-family: 'Arial Rounded MT Bold', sans-serif;
        }

        .confirmation-buttons {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-top: 24px;
        }

        .confirm-btn {
            background: #e74184;
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 10px 32px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Arial Rounded MT Bold', sans-serif;
            transition: transform 0.2s ease, background-color 0.2s ease;
        }

        .confirm-btn:hover {
            transform: scale(1.05);
            background: #d63875;
        }

        .cancel-confirm-btn {
            background: #666;
            color: #fff;
            border: none;
            border-radius: 20px;
            padding: 10px 32px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Arial Rounded MT Bold', sans-serif;
            transition: transform 0.2s ease, background-color 0.2s ease;
        }

        .cancel-confirm-btn:hover {
            transform: scale(1.05);
            background: #555;
        }

        /* Update payment modal styles */
        #paymentModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.7);
            z-index: 10000;
            backdrop-filter: blur(5px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #paymentModal.show {
            display: block;
            opacity: 1;
            pointer-events: auto;
        }

        #paymentModal .modal-content {
            background: #18322b;
            padding: 32px 32px 24px 32px;
            width: 100%;
            max-width: 900px !important;
            color: #fff;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.85);
            opacity: 0;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            font-family: 'Arial Rounded MT Bold', 'Inter', sans-serif;
            border-radius: 28px;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        #paymentModal.show .modal-content {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }

        .alert-pill.reject {
            background-color: #ff0000;
        }

        #myModal {
            z-index: 9999;
        }

        #paymentModal {
            z-index: 10000;
        }

        #confirmationModal {
            z-index: 10001;
        }

        .modal-content, .confirmation-content {
            position: relative;
            z-index: 1;
        }

        /* Responsive Styles */
        @media screen and (max-width: 1200px) {
            .sections-container {
                width: 95%;
                margin-left: auto;
                margin-right: auto;
            }

            .card-grid {
                grid-template-columns: repeat(3, 280px);
            }
        }

        @media screen and (max-width: 992px) {
            .card-grid {
                grid-template-columns: repeat(2, 280px);
            }

            .modal-content {
                width: 90% !important;
                padding: 24px !important;
            }

            #paymentModal .modal-content {
                width: 90% !important;
                padding: 24px !important;
            }

            .modal-body {
                grid-template-columns: 1fr;
            }
        }

        @media screen and (max-width: 768px) {
            .sections-container {
                margin-top: 400px;
            }

            .card-grid {
                grid-template-columns: 280px;
            }

            .overlap {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .overlap-3 {
                margin-top: 20px;
            }

            .div-wrapper {
                margin-left: 0 !important;
                margin-top: 20px;
            }

            .modal-content {
                padding: 16px !important;
            }

            .modal-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .modal-title {
                font-size: 20px;
            }

            .modal-info {
                width: 100%;
            }

            .modal-info-item {
                flex-direction: column;
                gap: 5px;
                text-align: center;
            }

            #paymentForm input[type="text"] {
                width: 100% !important;
            }

            .confirmation-content {
                width: 95% !important;
                padding: 20px !important;
            }

            .confirmation-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .confirm-btn, .cancel-confirm-btn {
                width: 100%;
            }
        }

        @media screen and (max-width: 480px) {
            .sections-container {
                margin-top: 350px;
            }

            .section-title {
                font-size: 20px;
            }

            .search-container {
                width: 100%;
            }

            .search-input {
                width: 100%;
            }

            .card {
                width: 100%;
                max-width: 280px;
            }

            .modal-content {
                padding: 12px !important;
            }

            .modal-title {
                font-size: 18px;
            }

            .modal-info-label {
                font-size: 14px;
            }

            .alert-pill {
                width: 90%;
                font-size: 14px;
                padding: 12px 24px;
            }

            .confirmation-title {
                font-size: 20px;
            }

            .confirmation-content {
                padding: 16px !important;
            }

            .confirm-btn, .cancel-confirm-btn {
                padding: 8px 24px;
                font-size: 14px;
            }
        }

        /* Additional responsive utilities */
        .hide-on-mobile {
            display: block;
        }

        .show-on-mobile {
            display: none;
        }

        @media screen and (max-width: 768px) {
            .hide-on-mobile {
                display: none;
            }

            .show-on-mobile {
                display: block;
            }
        }

        /* Responsive table styles */
        @media screen and (max-width: 768px) {
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            th, td {
                min-width: 120px;
            }
        }

        /* Responsive image styles */
        @media screen and (max-width: 768px) {
            .picture {
                width: 120px;
                height: 120px;
            }

            .cover {
                height: 200px;
            }

            .zoomed-image {
                max-width: 95%;
                max-height: 80vh;
            }
        }

        /* Responsive form styles */
        @media screen and (max-width: 768px) {
            #paymentForm {
                padding: 0 10px;
            }

            #paymentForm input[type="text"],
            #paymentForm input[type="radio"] {
                font-size: 14px;
            }

            #paymentForm label {
                font-size: 14px;
            }

            .save-btn, .cancel-btn {
                width: 100%;
                margin: 10px 0;
            }
        }

        /* Responsive modal animations */
        @media screen and (max-width: 768px) {
            .modal-content {
                transform: translate(-50%, -50%) scale(0.9);
            }

            .modal.show .modal-content {
                transform: translate(-50%, -50%) scale(1);
            }
        }
    </style>
</head>

<body>
    <div class="account">
        <div class="settings-dropdown">
            <img class="setting" src="assets/account/setting.png" />
            <div class="overlap-wrapper">
                <div class="overlap-2">
                    <a href="edit.php" class="menu-item">Edit Profile</a>
                    <a href="savedaddress.php" class="menu-item">Saved Address</a>
                    <a href="logout.php" class="menu-item logout">Log Out</a>
                </div>
            </div>
        </div>
        <div class="div">
            <div class="overlap">
                <div class="overlap-group">
                    <div class="rectangle"></div>
                    <img class="cover" src="assets/account/cover.png" />
                    <div class="picture"><img class="profile" src="<?php echo htmlspecialchars($profile_pic); ?>" /></div>
                    <div class="text-wrapper"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="group">
                        <a href="homepage_user.php" class="back-btn">
                            <div class="back">&lt; Back</div>
                        </a>
                    </div>
                    <!-- Removed setting icon and menu from here -->
                </div>
                <div class="overlap-3">
                    <p class="element-points">
                      <img class="point-inline" src="assets/account/point.png" alt="Points" />
                      <span class="span"><?php echo htmlspecialchars($points); ?></span>
                      <span class="text-wrapper-5">Points</span>
                    </p>
                </div>
                <div class="div-wrapper" style="margin-left: 50px;">
                    <a href="reward.php" class="overlap-4">
                        <div class="text-wrapper-6">Redeem</div>
                    </a>
                </div>
            </div>

            <div class="sections-container">
                <!-- My Submissions Section -->
                <div class="section">
                    <div class="section-header">
                        <div class="section-title">
                            <h2>My Submissions</h2>
                            <div class="search-container">
                                <img src="assets/account/glass.png" class="search-icon" alt="Search">
                                <input type="text" class="search-input" placeholder="Search by ID or date..." data-section="submissions">
                            </div>
                        </div>
                    </div>
                    <div class="divider"></div>
                    <div class="section-content">
                        <div class="card-grid" id="submissions-grid">
                            <?php if (!empty($submissions)): ?>
                                <?php foreach ($submissions as $submission): ?>
                                    <?php
                                        $submission_data = $submission;
                                        unset($submission_data['ewaste_image']); // Remove binary image for JSON
                                        $img_src = $submission['ewaste_image'] ? 'data:image/jpeg;base64,' . base64_encode($submission['ewaste_image']) : 'assets/account/no-image.png';
                                    ?>
                                    <div class="card cursor-pointer"
                                         data-submission='<?php echo htmlspecialchars(json_encode($submission_data), ENT_QUOTES, 'UTF-8'); ?>'
                                         data-image="<?php echo htmlspecialchars($img_src, ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="card-date"><?php echo date('d/m/Y', strtotime($submission['date'])); ?></div>
                                        <div class="card-id"><?php echo htmlspecialchars($submission['submission_id']); ?></div>
                                        <div class="status-badge <?php echo getStatusColor($submission['status']); ?>">
                                            <?php echo htmlspecialchars($submission['status']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-items">No submissions found</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- My Quotations Section -->
                <div class="section">
                    <div class="section-header">
                        <div class="section-title">
                            <h2>My Quotations</h2>
                            <div class="search-container">
                                <img src="assets/account/glass.png" class="search-icon" alt="Search">
                                <input type="text" class="search-input" placeholder="Search by ID or date..." data-section="quotations">
                            </div>
                        </div>
                    </div>
                    <div class="divider"></div>
                    <div class="section-content">
                        <div class="card-grid" id="quotations-grid">
                            <?php if (!empty($quotations)): ?>
                                <?php foreach ($quotations as $quotation): ?>
                                    <div class="card cursor-pointer"
                                         data-quotation='<?php echo htmlspecialchars(json_encode($quotation), ENT_QUOTES, 'UTF-8'); ?>'>
                                        <div class="card-date"><?php echo date('d/m/Y', strtotime($quotation['date'])); ?></div>
                                        <div class="card-id"><?php echo htmlspecialchars($quotation['quotation_id']); ?></div>
                                        <div class="status-badge <?php echo getStatusColor($quotation['status']); ?>">
                                            <?php echo htmlspecialchars($quotation['status']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-items">No quotations found</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tracking Section -->
                <div class="section">
                    <div class="section-header">
                        <div class="section-title">
                            <h2>Tracking</h2>
                            <div class="search-container">
                                <img src="assets/account/glass.png" class="search-icon" alt="Search">
                                <input type="text" class="search-input" placeholder="Search by ID or date..." data-section="tracking">
                            </div>
                        </div>
                    </div>
                    <div class="divider"></div>
                    <div class="section-content">
                        <div class="card-grid" id="tracking-grid">
                            <?php if (!empty($tracking)): ?>
                                <?php foreach ($tracking as $item): ?>
                                    <div class="card<?php echo $item['type'] === 'dropoff' ? ' cursor-pointer' : ''; ?><?php echo $item['type'] === 'delivery' ? ' cursor-pointer' : ''; ?>"
                                        <?php if ($item['type'] === 'dropoff'): ?>
                                            data-dropoff='<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>'
                                        <?php elseif ($item['type'] === 'delivery'): ?>
                                            data-delivery='<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>'
                                        <?php endif; ?>>
                                        <div class="card-date">
                                            <?php
                                            if ($item['type'] === 'dropoff') {
                                                echo date('d/m/Y', strtotime($item['tracking_date']));
                                            } else {
                                                echo date('d/m/Y', strtotime($item['tracking_date']));
                                            }
                                            ?>
                                        </div>
                                        <div class="card-id">
                                            <?php
                                            if ($item['type'] === 'delivery') {
                                                echo htmlspecialchars($item['delivery_id']);
                                            } else {
                                                echo htmlspecialchars($item['dropoff_id']);
                                            }
                                            ?>
                                        </div>
                                        <?php if ($item['type'] === 'delivery'): ?>
                                            <?php echo getPickupStatusBadge($item['status']); ?>
                                        <?php else: ?>
                                            <?php echo getDropoffStatusBadge($item['status']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-items">No tracking items found</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- History Section -->
                <div class="section">
                    <div class="section-header">
                        <div class="section-title">
                            <h2>History</h2>
                            <div class="search-container">
                                <img src="assets/account/glass.png" class="search-icon" alt="Search">
                                <input type="text" class="search-input" placeholder="Search by ID or date..." data-section="history">
                            </div>
                        </div>
                    </div>
                    <div class="divider"></div>
                    <div class="section-content">
                        <div class="card-grid" id="history-grid">
                            <?php if (!empty($histories)): ?>
                                <?php foreach ($histories as $history): ?>
                                    <div class="card cursor-pointer"
                                         data-history='<?php echo htmlspecialchars(json_encode($history), ENT_QUOTES, 'UTF-8'); ?>'>
                                        <div class="card-date"><?php echo date('d/m/Y', strtotime($history['date_paid'])); ?></div>
                                        <div class="card-id"><?php echo htmlspecialchars($history['history_id']); ?></div>
                                        <div class="status-badge status-accepted">
                                            +<?php echo htmlspecialchars($history['point_to_add']); ?> Points
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-items">No history found</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Simple Modal -->
    <div id="myModal" class="modal" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.7); z-index: 9999;">
        <div id="modalBox" class="modal-content" style="background: #18322b; padding: 48px 32px 56px 32px; border-radius: 28px; width: 95%; max-width: 1200px; color: #fff; position: absolute; top: 50%; left: 50%; box-shadow: 0 8px 32px rgba(0,0,0,0.25); font-family: 'Inter', sans-serif; max-height: 90vh; overflow-y: auto;">
            <button onclick="closeModal()" style="position: absolute; top: 18px; right: 18px; background: none; border: none; color: #fff; font-size: 28px; cursor: pointer;">&times;</button>
            <div id="modalContent"></div>
        </div>
    </div>

    <!-- Payment Information Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <button type="button" class="close-modal" onclick="closePaymentModal()">&times;</button>
            <h2 style="margin-bottom: 40px; color: #fff;">Complete Payment and Choose Handover Method</h2>
            <form id="paymentForm" onsubmit="submitPayment(event)" style="display: flex; flex-direction: column; align-items: center; max-height: calc(90vh - 100px); overflow-y: auto;">
                <input type="hidden" id="quotationId" name="quotationId">
                <div style="margin-bottom: 40px; width: 100%; display: flex; flex-direction: column; align-items: center;">
                    <div style="font-weight: bold; color: #59b8a0; margin-bottom: 24px; font-size: 16px; text-align: center;">Payment Information</div>
                    <div style="margin-bottom: 24px; width: 30%;">
                        <label style="margin-bottom: 16px; display: block; text-align: left; color: #fff;">Bank Account Number</label>
                        <input type="text" name="bank_acc" required style="width: 100%; padding: 12px 18px; border: none; border-radius: 20px; background: #fff; color: #18322b; font-size: 15px; font-family: 'Arial Rounded MT Bold', sans-serif; text-align: left;" oninput="validateBankAccount(this)">
                        <div class="error-message" style="color: #e74184; font-size: 12px; margin-top: 8px; display: none;">Invalid account number.</div>
                    </div>
                    <div style="margin-bottom: 24px; width: 30%;">
                        <label style="margin-bottom: 16px; display: block; text-align: left; color: #fff;">Bank Name</label>
                        <input type="text" name="bank_name" required style="width: 100%; padding: 12px 18px; border: none; border-radius: 20px; background: #fff; color: #18322b; font-size: 15px; font-family: 'Arial Rounded MT Bold', sans-serif; text-align: left;">
                    </div>
                    <div style="margin-bottom: 24px; width: 30%;">
                        <label style="margin-bottom: 16px; display: block; text-align: left; color: #fff;">Account Holder Name</label>
                        <input type="text" name="name" required style="width: 100%; padding: 12px 18px; border: none; border-radius: 20px; background: #fff; color: #18322b; font-size: 15px; font-family: 'Arial Rounded MT Bold', sans-serif; text-align: left;">
                    </div>
                </div>
                <div style="margin-bottom: 40px; width: 100%; display: flex; flex-direction: column; align-items: center;">
                    <div style="font-weight: bold; color: #59b8a0; margin-bottom: 24px; font-size: 16px; text-align: center;">Select E-Waste Handover Method</div>
                    <div style="display: flex; flex-direction: column; gap: 20px; margin-top: 16px; width: 80%;">
                        <label style="display: flex; align-items: center; gap: 8px; color: #fff;">
                            <input type="radio" name="handover_method" value="dropoff" required style="accent-color: #59b8a0;" onchange="toggleAddressSelection(false)">
                            Drop-Off at E-Waste Collection Point
                        </label>
                        <div id="dropoffInfo" style="display: none; margin-left: 24px; padding: 12px; border: 1px solid #59b8a0; border-radius: 10px; color: #fff;">
                            <div style="font-weight: bold; color: #59b8a0; margin-bottom: 12px;">Drop-off Before: 
                                <span style="color: #fff; font-weight: normal;">
                                    <?php 
                                    $dropoff_date = new DateTime();
                                    $dropoff_date->modify('+7 days');
                                    echo $dropoff_date->format('d/m/Y'); 
                                    ?>
                                </span>
                            </div>
                            <div style="line-height: 1.4;">
                                Nothing Wasted,<br>
                                Jalan Nothing 1/1, Taman Bukit Jalil,<br>
                                48000 Bukit Jalil,<br>
                                Wilayah Persekutuan Kuala Lumpur,<br>
                                Malaysia.
                            </div>
                        </div>
                        <label style="display: flex; align-items: center; gap: 8px; color: #fff;">
                            <input type="radio" name="handover_method" value="pickup" style="accent-color: #59b8a0;" onchange="toggleAddressSelection(true)">
                            Pick-up from My Address
                        </label>
                        <div style="margin-left: 24px; margin-top: -12px; color: #59b8a0; font-size: 14px;">
                            (Arrive within 14 days)
                        </div>
                        <div id="addressSelection" style="display: none; margin-top: 16px; width: 100%;">
                            <div style="font-weight: bold; color: #59b8a0; margin-bottom: 16px; font-size: 14px; text-align: left;">Select Pick-up Address</div>
                            <div style="display: flex; gap: 20px; justify-content: center;">
                                <?php if ($addresses['addressline1_1']): ?>
                                <label style="flex: 1; max-width: 300px; display: flex; align-items: flex-start; gap: 8px; color: #fff; padding: 12px; border: 1px solid #59b8a0; border-radius: 10px; cursor: pointer;">
                                    <input type="radio" name="pickup_address" value="1" required style="accent-color: #59b8a0; margin-top: 4px;" checked>
                                    <div style="font-size: 14px; line-height: 1.4; width: 100%;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <span style="font-weight: bold; color: #59b8a0;">Address 1</span>
                                            <span style="background: #59b8a0; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 12px;">Primary</span>
                                        </div>
                                        <?php 
                                        echo htmlspecialchars($addresses['addressline1_1']) . ",";
                                        if ($addresses['addressline2_1']) echo "<br>" . htmlspecialchars($addresses['addressline2_1']) . ",";
                                        echo "<br>" . htmlspecialchars($addresses['zipcode_1']) . " " . htmlspecialchars($addresses['city_1']) . ",";
                                        echo "<br>" . htmlspecialchars($addresses['state_1']) . ".";
                                        ?>
                                    </div>
                                </label>
                                <?php endif; ?>
                                
                                <?php if ($addresses['addressline1_2']): ?>
                                <label style="flex: 1; max-width: 300px; display: flex; align-items: flex-start; gap: 8px; color: #fff; padding: 12px; border: 1px solid #59b8a0; border-radius: 10px; cursor: pointer;">
                                    <input type="radio" name="pickup_address" value="2" required style="accent-color: #59b8a0; margin-top: 4px;">
                                    <div style="font-size: 14px; line-height: 1.4; width: 100%;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <span style="font-weight: bold; color: #59b8a0;">Address 2</span>
                                        </div>
                                        <?php 
                                        echo htmlspecialchars($addresses['addressline1_2']) . ",";
                                        if ($addresses['addressline2_2']) echo "<br>" . htmlspecialchars($addresses['addressline2_2']) . ",";
                                        echo "<br>" . htmlspecialchars($addresses['zipcode_2']) . " " . htmlspecialchars($addresses['city_2']) . ",";
                                        echo "<br>" . htmlspecialchars($addresses['state_2']) . ".";
                                        ?>
                                    </div>
                                </label>
                                <?php endif; ?>
                                
                                <?php if ($addresses['addressline1_3']): ?>
                                <label style="flex: 1; max-width: 300px; display: flex; align-items: flex-start; gap: 8px; color: #fff; padding: 12px; border: 1px solid #59b8a0; border-radius: 10px; cursor: pointer;">
                                    <input type="radio" name="pickup_address" value="3" required style="accent-color: #59b8a0; margin-top: 4px;">
                                    <div style="font-size: 14px; line-height: 1.4; width: 100%;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                            <span style="font-weight: bold; color: #59b8a0;">Address 3</span>
                                        </div>
                                        <?php 
                                        echo htmlspecialchars($addresses['addressline1_3']) . ",";
                                        if ($addresses['addressline2_3']) echo "<br>" . htmlspecialchars($addresses['addressline2_3']) . ",";
                                        echo "<br>" . htmlspecialchars($addresses['zipcode_3']) . " " . htmlspecialchars($addresses['city_3']) . ",";
                                        echo "<br>" . htmlspecialchars($addresses['state_3']) . ".";
                                        ?>
                                    </div>
                                </label>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-actions" style="justify-content: center; margin-top: 32px;">
                    <button type="button" class="cancel-btn" onclick="closePaymentModal()">Cancel</button>
                    <button type="submit" class="save-btn">Accept and Submit</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Image Zoom Modal -->
    <div class="image-zoom-modal" id="imageZoomModal">
        <span class="close-zoom">&times;</span>
        <img class="zoomed-image" id="zoomedImage" src="" alt="Zoomed Image">
    </div>

    <!-- Add the confirmation modal HTML -->
    <div id="confirmationModal" class="confirmation-modal">
        <div class="confirmation-content">
            <div class="confirmation-title">Confirm Rejection</div>
            <div>Are you sure you want to reject this quotation?</div>
            <div class="confirmation-buttons">
                <button class="cancel-confirm-btn" onclick="closeConfirmationModal()">Cancel</button>
                <button class="confirm-btn" onclick="confirmReject()">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        const settingIcon = document.querySelector('.setting');
        const overlapWrapper = document.querySelector('.overlap-wrapper');

        // Toggle menu on icon click
        settingIcon.addEventListener('click', (e) => {
            e.stopPropagation();
            overlapWrapper.classList.toggle('active');
        });

        // Prevent menu from closing when clicking inside
        overlapWrapper.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Close menu when clicking outside
        document.addEventListener('click', () => {
            overlapWrapper.classList.remove('active');
        });

        // Add collapsible functionality to sections
        document.querySelectorAll('.section-header').forEach(header => {
            header.addEventListener('click', () => {
                const section = header.parentElement;
                section.classList.toggle('collapsed');
            });
        });

        // Collapse all sections by default
        document.querySelectorAll('.section').forEach(section => {
            section.classList.add('collapsed');
        });

        // Simple modal functions
        function getQuantitiesHTMLStyled(submission) {
            const quantities = [];
            const quantityFields = [
                {field: 'laptop_qty', label: 'Laptops'},
                {field: 'desktop_qty', label: 'Desktops/Servers'},
                {field: 'monitor_qty', label: 'Monitors/TVs'},
                {field: 'printer_qty', label: 'Printers/Projectors'},
                {field: 'phone_qty', label: 'Smartphones/Tablets'},
                {field: 'appliance_qty', label: 'Home Appliances'},
                {field: 'wearables_qty', label: 'Wearables'},
                {field: 'cables_qty', label: 'Cables/Wires'},
                {field: 'accessories_qty', label: 'Accessories/Peripherals'}
            ];

            quantityFields.forEach(q => {
                if (submission[q.field] && submission[q.field] > 0) {
                    quantities.push(`
                        <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: rgba(89, 184, 160, 0.1); border-radius: 8px; margin-bottom: 8px;">
                            <span style="color: #3ecf8e; font-weight: 500;">${q.label}:</span>
                            <span style="color: #fff; font-weight: 600;">${submission[q.field]}</span>
                        </div>
                    `);
                }
            });

            return quantities.length > 0 
                ? `<div style="width: 100%; margin-top: 15px; text-align: left;">${quantities.join('')}</div>` 
                : '';
        }

        function openModal(submission, imgSrc) {
            console.log('openModal called', submission, imgSrc);
            const modal = document.getElementById('myModal');
            const modalBox = document.getElementById('modalBox');
            
            // Add click handler to prevent closing when clicking inside modal content
            modalBox.addEventListener('click', function(event) {
                event.stopPropagation();
            });

            // Check if the file is a PDF
            const isPDF = imgSrc.startsWith('data:application/pdf;base64,') || 
                         (imgSrc.startsWith('data:') && atob(imgSrc.split(',')[1]).startsWith('%PDF'));

            let content = `
                <div style="text-align: center; margin-bottom: 18px; font-size: 1.15em; color: #3ecf8e; font-weight: 600; letter-spacing: 0.5px; font-family: 'Arial Rounded MT Bold', sans-serif;">${submission.submission_id}</div>
                <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 18px; align-items: center;">
                    ${isPDF ? `
                        <div style="margin-bottom: 10px;">
                            <a href="view_ewaste_file_user.php?submission_id=${encodeURIComponent(submission.submission_id)}" target="_blank" 
                               style="display: inline-block; padding: 10px 20px; background: #59b8a0; color: white; border-radius: 8px; text-decoration: none; font-weight: 500; font-family: 'Arial Rounded MT Bold', sans-serif;">
                                View PDF
                            </a>
                        </div>
                    ` : `
                        <img src="${imgSrc}"
                             style="width: 200px; height: 200px; object-fit: cover; border-radius: 12px; border: 2px solid #59b8a0; background: #222; margin-bottom: 10px; cursor: pointer;" 
                             alt="E-waste Image" 
                             onclick="zoomImage(this.src)">
                    `}
                    <div style="color: #3ecf8e; font-weight: 500; font-size: 1em; font-family: 'Arial Rounded MT Bold', sans-serif;">Status: <span style="color: ${getStatusColor(submission.status)}; font-weight: 600;">${submission.status}</span></div>
                    <div style="color: #b5b5b5; font-size: 0.95em; font-family: 'Arial Rounded MT Bold', sans-serif;">Date: ${new Date(submission.date).toLocaleDateString()}</div>
                    ${(submission.status === 'Accepted' || submission.status === 'Completed') && submission.quotation_id ? `
                        <div style="margin-top: 10px; padding: 8px 12px; background: rgba(89, 184, 160, 0.1); border-radius: 8px; width: 100%; text-align: center; font-family: 'Arial Rounded MT Bold', sans-serif;">
                            <span style="color: #3ecf8e; font-weight: 500;">Quotation Generated:</span>
                            <span style="color: #fff; font-weight: 600; margin-left: 8px;">${submission.quotation_id}</span>
                        </div>
                    ` : ''}
                    
                </div>
                <div style="margin-top: 10px;">
                    <div style="color: #3ecf8e; font-weight: 500; font-size: 1em; margin-bottom: 10px; font-family: 'Arial Rounded MT Bold', sans-serif;">Items:</div>
                    ${getQuantitiesHTMLStyled(submission)}
                </div>
                ${submission.status && submission.status.toLowerCase() === 'rejected' && submission.reason ? `
                    <div style="margin-top: 20px;">
                        <div style="color: #c90000; font-weight: 700; font-size: 1em; margin-bottom: 10px; font-family: 'Arial Rounded MT Bold', sans-serif;">Rejection Reason:</div>
                        <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: rgba(201,0,0,0.1); border-radius: 8px; width: 100%; font-family: 'Arial Rounded MT Bold', sans-serif;">
                            <span style="color: #fff; font-weight: 400;">${submission.reason}</span>
                        </div>
                    </div>
                ` : ''}
            `;
            document.getElementById('modalContent').innerHTML = content;
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            // Reset modal box to default size for submissions
            if (modalBox) {
                modalBox.style.background = '#18322b';
                modalBox.style.maxWidth = '420px';
                modalBox.style.width = '95%';
                modalBox.style.minHeight = '';
                modalBox.style.padding = '32px 32px 40px 32px';
                modalBox.style.borderRadius = '28px';
            }
        }

        function closeModal() {
            console.log('Closing modal');
            const modal = document.getElementById('myModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Show modal with animation
        window.onclick = function(event) {
            const paymentModal = document.getElementById('paymentModal');
            const confirmationModal = document.getElementById('confirmationModal');
            const quotationModal = document.getElementById('myModal');
            const submissionModal = document.getElementById('submissionModal');

            // Close modals if clicking outside of their content
            if (event.target === paymentModal) {
                closePaymentModal();
            }
            if (event.target === confirmationModal) {
                closeConfirmationModal();
            }
            if (event.target === quotationModal) {
                closeModal();
            }
            if (event.target === submissionModal) {
                closeSubmissionModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePaymentModal();
                closeConfirmationModal();
                closeModal();
                closeSubmissionModal();
            }
        });

        function getStatusColor(status) {
            switch (status.toLowerCase()) {
                case 'pending': return '#cd7800';
                case 'accepted': return '#00bb0c';
                case 'completed': return '#b5b5b5';
                case 'rejected': return '#c90000';
                case 'expired': return '#800000';
                default: return '#cd7800';
            }
        }

        // Add event listeners to cards to open modal with correct data
        document.addEventListener('DOMContentLoaded', function() {
            document.body.addEventListener('click', function(e) {
                const card = e.target.closest('.card.cursor-pointer');
                if (card) {
                    if (card.hasAttribute('data-submission')) {
                    const submission = JSON.parse(card.getAttribute('data-submission'));
                    const imgSrc = card.getAttribute('data-image');
                    openModal(submission, imgSrc);
                    } else if (card.hasAttribute('data-quotation')) {
                        const quotation = JSON.parse(card.getAttribute('data-quotation'));
                        openQuotationModal(quotation);
                    } else if (card.hasAttribute('data-dropoff')) {
                        const dropoff = JSON.parse(card.getAttribute('data-dropoff'));
                        openDropoffModal(dropoff);
                    } else if (card.hasAttribute('data-delivery')) {
                        const delivery = JSON.parse(card.getAttribute('data-delivery'));
                        openDeliveryModal(delivery);
                    } else if (card.hasAttribute('data-history')) {
                        const history = JSON.parse(card.getAttribute('data-history'));
                        openHistoryModal(history);
                    }
                }
            });
        });

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

        function openQuotationModal(quotation) {
            const modal = document.getElementById('myModal');
            const modalBox = document.getElementById('modalBox');
            
            // Add click handler to prevent closing when clicking inside modal content
            modalBox.addEventListener('click', function(event) {
                event.stopPropagation();
            });

            // Define all possible e-waste types and their display names
            const ewasteTypes = [
                { field: 'laptop', label: 'Laptop' },
                { field: 'desktop', label: 'Desktop/Server' },
                { field: 'monitor', label: 'Monitor/TV' },
                { field: 'printer', label: 'Printer/Projector' },
                { field: 'phone', label: 'Smartphone/Tablet' },
                { field: 'appliance', label: 'Home Appliance' },
                { field: 'wearables', label: 'Wearable' },
                { field: 'cables', label: 'Cables/Wire' },
                { field: 'accessories', label: 'Accessory/Peripheral' }
            ];
            // Build table rows for all items with quantity > 0
            let rows = '';
            let total = 0;
            let totalPoints = 0;
            ewasteTypes.forEach(type => {
                const qty = quotation[type.field + '_qty'] || 0; // from submission
                const amount = quotation[type.field + '_p'] || 0; // from quotation
                const points = quotation[type.field + '_po'] || 0; // from points table
                const totalItemPoints = points * qty;
                if (qty > 0) {
                    rows += `<tr>
                        <td style="font-family: 'Arial Rounded MT Bold', sans-serif; padding: 8px; border: 0.5px solid #b5b5b5;">${type.label}</td>
                        <td style="font-family: 'Arial Rounded MT Bold', sans-serif; padding: 8px; border: 0.5px solid #b5b5b5; text-align: center;">${qty}</td>
                        <td style="font-family: 'Arial Rounded MT Bold', sans-serif; padding: 8px; border: 0.5px solid #b5b5b5; text-align: center;">${totalItemPoints}</td>
                        <td style="font-family: 'Arial Rounded MT Bold', sans-serif; padding: 8px; border: 0.5px solid #b5b5b5; text-align: right;">${amount}</td>
                    </tr>`;
                    total += parseFloat(amount);
                    totalPoints += totalItemPoints;
                }
            });
            // If no items, show empty state
            if (!rows) {
                rows = `<tr>
                    <td colspan="3" style="font-family: 'Arial Rounded MT Bold', sans-serif; padding: 8px; border: 0.5px solid #b5b5b5; text-align: center;">No items found</td>
                </tr>`;
            }
            let content = `
                <div style="text-align: center; margin-bottom: 32px;">
                    <img src="assets/homepage/logo.png" style="height: 120px; margin-bottom: 24px;" alt="Logo">
                </div>
                <div style="text-align: left; margin-bottom: 32px;">
                    <div style="font-family: 'Arial Rounded MT Bold', sans-serif; font-size: 1.5em; font-weight: 700; color: #fff;">Quotation</div>
                    <div style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #b5b5b5; font-size: 1em; margin-bottom: 48px;">
                        ${quotation.quotation_id} | ${(() => {
                            if (quotation.validity) {
                                let validityDate = new Date(quotation.validity);
                                let displayDate = new Date(validityDate.getTime() - 7 * 24 * 60 * 60 * 1000);
                                return displayDate.toLocaleDateString();
                            } else {
                                return '';
                            }
                        })()}
                    </div>
                </div>
                <div style="margin-bottom: 32px;">
                    <div style="display: flex; justify-content: space-between; gap: 40px;">
                        <div>
                            <div style="font-family: 'Arial Rounded MT Bold', sans-serif; font-weight: 600; color: #fff; margin-bottom: 8px;">From</div>
                            <div style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #fff; font-size: 0.95em; line-height: 1.3;">${quotation.admin_name || 'John Lim Hen Yang'}<br><span style='display:block; margin-bottom:8px;'></span>Nothing Wasted,<br>Jalan Nothing 1/1, Taman Bukit Jalil,<br>48000 Bukit Jalil,<br>Wilayah Persekutuan Kuala Lumpur,<br>Malaysia.</div>
                        </div>
                        <div>
                            <div style="font-family: 'Arial Rounded MT Bold', sans-serif; font-weight: 600; color: #fff; margin-bottom: 8px;">To</div>
                            <div style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #fff; font-size: 0.95em; line-height: 1.3;">
                                ${quotation.user_name || 'Ali Baker'}<br><span style='display:block; margin-bottom:8px;'></span>
                                ${quotation.addressline1_1 ? quotation.addressline1_1 + ',' : ''}<br>
                                ${quotation.addressline2_1 ? quotation.addressline2_1 + ',' : ''}<br>
                                ${quotation.zipcode_1 ? quotation.zipcode_1 + ',' : ''} ${quotation.city_1 ? quotation.city_1 + ',' : ''}<br>
                                ${quotation.state_1 ? quotation.state_1 + ',' : ''}<br>
                                Malaysia.
                            </div>
                        </div>
                    </div>
                </div>
                <div style="margin-bottom: 32px;">
                    <div style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #59b8a0; font-size: 1.1em; font-weight: 600; margin-bottom: 16px;">SubmissionID: ${quotation.submission_id}</div>
                </div>
                <div style="margin-bottom: 18px; border: 0.5px solid #b5b5b5; border-radius: 8px; overflow: hidden;">
                    <table style="width: 100%; border-collapse: collapse; color: #fff;">
                        <thead>
                            <tr>
                                <th style="font-family: 'Arial Rounded MT Bold', sans-serif; padding: 8px; border: 0.5px solid #b5b5b5; font-weight: 700;">E-WASTE TYPES</th>
                                <th style="font-family: 'Arial Rounded MT Bold', sans-serif; padding: 8px; border: 0.5px solid #b5b5b5; font-weight: 700;">QUANTITY</th>
                                <th style="font-family: 'Arial Rounded MT Bold', sans-serif; padding: 8px; border: 0.5px solid #b5b5b5; font-weight: 700;">POINTS</th>
                                <th style="font-family: 'Arial Rounded MT Bold', sans-serif; padding: 8px; border: 0.5px solid #b5b5b5; font-weight: 700;">AMOUNT (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows}
                        </tbody>
                    </table>
                </div>
                <div style="text-align: right; margin-top: 32px;">
                    <div style="margin-bottom: 16px;">
                        <span style="font-family: 'Arial Rounded MT Bold', sans-serif; font-size: 1.2em; color: #fff; font-weight: 600;">
                            <img src="assets/account/point.png" style="height: 24px; width: auto; vertical-align: middle; margin-right: 10px; object-fit: contain; display: inline-block;" alt="Points">
                            TOTAL POINTS
                        </span>
                        <span style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #59b8a0; border: 2px solid #59b8a0; border-radius: 24px; padding: 8px 28px; font-weight: 700; margin-left: 16px; background: transparent; display: inline-block;">${totalPoints}</span>
                    </div>
                    <div>
                        <span style="font-family: 'Arial Rounded MT Bold', sans-serif; font-size: 1.2em; color: #fff; font-weight: 600;">TOTAL AMOUNT</span>
                        <span style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #ffb07c; border: 2px solid #ffb07c; border-radius: 24px; padding: 8px 28px; font-weight: 700; margin-left: 16px; background: transparent; display: inline-block;">RM${total}</span>
                    </div>
                    <div style="margin-top: 32px; margin-bottom: 120px; text-align: left;">
                        <div style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #59b8a0; font-size: 1.1em; font-weight: 600; margin-bottom: 8px; margin-left: 32px;">Remarks</div>
                        <div style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #fff; background: rgba(89,184,160,0.08); border-radius: 12px; padding: 12px 18px; width: calc(100% - 64px); height: 60px; min-height: 60px; max-height: 100px; box-sizing: border-box; display: flex; align-items: flex-start; overflow-y: auto; margin: 0 32px;">${quotation.remarks ? quotation.remarks : '-'}</div>
                        <div style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #b5b5b5; font-size: 1em; margin-left: 32px; margin-top: 8px;">Valid until: ${quotation.validity ? new Date(quotation.validity).toLocaleDateString() : '-'}</div>
                    </div>
                    ${quotation.status === 'Accepted' || quotation.status === 'Completed' ? `
                        <div style="margin-top: 32px; margin-bottom: 120px; text-align: left;">
                            <div style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #59b8a0; font-size: 1.1em; font-weight: 600; margin-bottom: 16px; margin-left: 32px;">References</div>
                            <div style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #fff; margin: 0 32px;">
                                ${quotation.dropoff_id ? `
                                    <div style="margin-bottom: 8px;">
                                        <span style="color: #59b8a0;">Drop-off ID:</span>
                                        <span style="margin-left: 8px; font-weight: 700;">${quotation.dropoff_id}</span>
                                    </div>
                                ` : ''}
                                ${quotation.delivery_id ? `
                                    <div style="margin-bottom: 8px;">
                                        <span style="color: #59b8a0;">Delivery ID:</span>
                                        <span style="margin-left: 8px; font-weight: 700;">${quotation.delivery_id}</span>
                                    </div>
                                    <div style="margin-bottom: 8px;">
                                        <span style="color: #59b8a0; display: block;">Delivery Address:</span>
                                        <span style="display: block; font-weight: 700; line-height: 1.4; margin-left: 140px;">
                                            ${quotation.addressline1_1 ? quotation.addressline1_1 + ',' : ''}<br>
                                            ${quotation.addressline2_1 ? quotation.addressline2_1 + ',' : ''}<br>
                                            ${quotation.zipcode_1 ? quotation.zipcode_1 + ' ' : ''}${quotation.city_1 ? quotation.city_1 + ',' : ''}<br>
                                            ${quotation.state_1 ? quotation.state_1 + ',' : ''}<br>
                                            Malaysia.
                                        </span>
                                    </div>
                                ` : ''}
                                ${quotation.payment_id ? `
                                    <div>
                                        <span style="color: #59b8a0;">Payment ID:</span>
                                        <span style="margin-left: 8px; font-weight: 700;">${quotation.payment_id}</span>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    ` : ''}
                    ${quotation.status === 'Accepted' ? `
                        <div style="display: flex; justify-content: center; margin-bottom: 8px;">
                            <div style="background: rgba(89, 184, 160, 0.1); padding: 12px 32px; border-radius: 50px;">
                                <span style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #59b8a0; font-size: 16px; font-weight: bold;">Accepted</span>
                            </div>
                        </div>
                    ` : quotation.status === 'Rejected' ? `
                        <div style="display: flex; flex-direction: column; align-items: center; gap: 16px; margin-bottom: 8px;">
                            <div style="background: rgba(231, 65, 132, 0.1); padding: 12px 32px; border-radius: 50px;">
                                <span style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #e74184; font-size: 16px; font-weight: bold;">Rejected</span>
                            </div>
                            <a href="feedback.php?quotation_id=${encodeURIComponent(quotation.quotation_id)}" style="font-family: 'Arial Rounded MT Bold', sans-serif; background: #4170e7; color: #fff; border: none; border-radius: 50px; padding: 12px 32px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; gap: 8px; text-decoration: none; justify-content: center;" onmouseover="this.style.background='#2855c5'" onmouseout="this.style.background='#4170e7'">
                                <span>Leave us a feedback!</span>
                                <span style="font-size: 18px;">💭</span>
                            </a>
                        </div>
                    ` : quotation.status === 'Expired' ? `
                        <div style="display: flex; justify-content: center; margin-bottom: 8px;">
                            <div style="background: rgba(128, 0, 0, 0.1); padding: 12px 32px; border-radius: 50px;">
                                <span style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #800000; font-size: 16px; font-weight: bold;">Expired</span>
                            </div>
                        </div>
                    ` : quotation.status === 'Pending' ? `
                        <div style="display: flex; justify-content: center; gap: 16px; margin-bottom: 8px;">
                            <button onclick="openPaymentModal('${quotation.quotation_id}')" style="font-family: 'Arial Rounded MT Bold', sans-serif; background: #00bb0c; color: #fff; border: none; border-radius: 20px; padding: 10px 32px; font-size: 1.1em; font-weight: 600; cursor: pointer; transition: background 0.2s;">Accept</button>
                            <button onclick="showConfirmationModal('${quotation.quotation_id}')" style="font-family: 'Arial Rounded MT Bold', sans-serif; background: #c90000; color: #fff; border: none; border-radius: 20px; padding: 10px 32px; font-size: 1.1em; font-weight: 600; cursor: pointer; transition: background 0.2s;">Reject</button>
                        </div>
                    ` : ''}
                </div>
            `;
            // Set modal content and update modal box style for background and size
            document.getElementById('modalContent').innerHTML = content;
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            // Set background and size to match the design
            if (modalBox) {
                modalBox.style.background = '#181f1c'; // dark green/black
                modalBox.style.maxWidth = '1200px'; // Increased from 900px
                modalBox.style.width = '95%';
                modalBox.style.minHeight = '600px'; // Increased from 520px
                modalBox.style.padding = '48px 48px 56px 48px';
                modalBox.style.borderRadius = '28px';
            }
        }

        function openPaymentModal(quotationId) {
            const modal = document.getElementById('paymentModal');
            const modalContent = modal.querySelector('.modal-content');
            
            // Add click handler to prevent closing when clicking inside modal content
            modalContent.addEventListener('click', function(event) {
                event.stopPropagation();
            });

            document.getElementById('quotationId').value = quotationId;
            modal.style.display = 'block';
            modal.offsetHeight; // Force reflow
            requestAnimationFrame(() => {
                modal.classList.add('show');
            });
            document.body.style.overflow = 'hidden';
        }

        function closePaymentModal() {
            console.log('Closing payment modal');
            const modal = document.getElementById('paymentModal');
            if (modal) {
                modal.classList.remove('show');
                setTimeout(() => {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }, 350);
            }
        }

        // Update window click handler for payment modal
        window.onclick = function(event) {
            const paymentModal = document.getElementById('paymentModal');
            const confirmationModal = document.getElementById('confirmationModal');
            const quotationModal = document.getElementById('myModal');
            const submissionModal = document.getElementById('submissionModal');

            // Close modals if clicking outside of their content
            if (event.target === paymentModal) {
                closePaymentModal();
            }
            if (event.target === confirmationModal) {
                closeConfirmationModal();
            }
            if (event.target === quotationModal) {
                closeModal();
            }
            if (event.target === submissionModal) {
                closeSubmissionModal();
            }
        }

        // Update escape key handler for both modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePaymentModal();
                closeConfirmationModal();
                closeModal();
                closeSubmissionModal();
            }
        });

        // Add this function for alert handling
        function showAlert(message, type = 'success') {
            // Remove any existing alert
            const existingAlert = document.querySelector('.alert-pill');
            if (existingAlert) {
                existingAlert.remove();
            }

            // Create new alert
            const alert = document.createElement('div');
            alert.className = `alert-pill ${type}`;
            
            // Add icon based on type
            let icon = '✓';
            if (type === 'error') icon = '✕';
            if (type === 'reject') icon = '✕';
            
            alert.innerHTML = `
                <span class="icon">${icon}</span>
                <span>${message}</span>
            `;
            
            // Add to document
            document.body.appendChild(alert);
            
            // Trigger animation
            setTimeout(() => {
                alert.classList.add('show');
            }, 100);
            
            // Remove after delay
            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => {
                    alert.remove();
                }, 500);
            }, 3000);
        }

        // Update the submitPayment function
        function submitPayment(event) {
            event.preventDefault();
            const form = event.target;
            const bankAccInput = form.querySelector('input[name="bank_acc"]');
            
            // Check if bank account is valid before proceeding
            if (!/^\d+$/.test(bankAccInput.value)) {
                showAlert('Please enter a valid bank account number.', 'error');
                return;
            }

            const formData = new FormData(form);
            formData.append('is_ajax', '1');

            fetch('submit_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Create new FormData for the second request
                    const methodData = new FormData();
                    methodData.append('handover_method', formData.get('handover_method'));
                    methodData.append('quotationId', formData.get('quotationId'));
                    methodData.append('is_ajax', '1');
                    
                    // Add pickup_address if pickup method is selected
                    if (formData.get('handover_method') === 'pickup') {
                        methodData.append('pickup_address', formData.get('pickup_address'));
                    }

                    return fetch(window.location.href, {
                        method: 'POST',
                        body: methodData
                    }).then(response => response.json())
                    .then(methodData => {
                        // Always update payment if we have an ID, even if success is false (already exists)
                        const updateData = new FormData();
                        updateData.append('payment_id', data.payment_id);
                        if (methodData.delivery_id) {
                            updateData.append('delivery_id', methodData.delivery_id);
                        }
                        if (methodData.dropoff_id) {
                            updateData.append('dropoff_id', methodData.dropoff_id);
                        }
                        updateData.append('is_ajax', '1');

                        // Only update if we have an ID
                        if (methodData.delivery_id || methodData.dropoff_id) {
                            return fetch('update_payment.php', {
                                method: 'POST',
                                body: updateData
                            }).then(response => response.json());
                        } else {
                            throw new Error('No delivery_id or dropoff_id returned from server.');
                        }
                    });
                }
                throw new Error(data.message);
            })
            .then(data => {
                showAlert('Payment information submitted successfully!');
                closePaymentModal();
                closeModal();
                setTimeout(() => {
                    location.reload();
                }, 1000);
            })
            .catch(error => {
                console.error('Error submitting payment:', error);
                showAlert(error.message || 'An error occurred while submitting payment information.', 'error');
            });
        }

        function toggleAddressSelection(show) {
            const addressSelection = document.getElementById('addressSelection');
            const dropoffInfo = document.getElementById('dropoffInfo');
            if (show) {
                addressSelection.style.display = 'block';
                dropoffInfo.style.display = 'none';
                // Make address selection required only when pickup is selected
                const addressRadios = document.getElementsByName('pickup_address');
                addressRadios.forEach(radio => radio.required = true);
            } else {
                addressSelection.style.display = 'none';
                dropoffInfo.style.display = 'block';
                // Remove required attribute when dropoff is selected
                const addressRadios = document.getElementsByName('pickup_address');
                addressRadios.forEach(radio => radio.required = false);
            }
        }

        // Add validation to ensure address is selected when pickup is chosen
        document.getElementById('paymentForm').addEventListener('submit', function(event) {
            const pickupRadio = document.querySelector('input[value="pickup"]');
            if (pickupRadio.checked) {
                const addressSelected = document.querySelector('input[name="pickup_address"]:checked');
                if (!addressSelected) {
                    event.preventDefault();
                    alert('Please select a pickup address');
                }
            }
        });

        // Add this validation function
        function validateBankAccount(input) {
            const submitBtn = document.querySelector('.save-btn');
            const errorMessage = input.parentElement.querySelector('.error-message');
            const isValid = /^\d+$/.test(input.value);

            if (!isValid && input.value !== '') {
                errorMessage.style.display = 'block';
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
            } else {
                errorMessage.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
            }
        }

        // Add these functions for rejection handling
        let currentQuotationId = null;

        function showConfirmationModal(quotationId) {
            const modal = document.getElementById('confirmationModal');
            const modalContent = modal.querySelector('.confirmation-content');
            
            // Add click handler to prevent closing when clicking inside modal content
            modalContent.addEventListener('click', function(event) {
                event.stopPropagation();
            });

            currentQuotationId = quotationId;
            modal.style.display = 'block';
            modal.offsetHeight; // Force reflow
            requestAnimationFrame(() => {
                modal.classList.add('show');
            });
        }

        function closeConfirmationModal() {
            const modal = document.getElementById('confirmationModal');
            modal.classList.remove('show');
            // Wait for animation to complete before hiding
            setTimeout(() => {
                modal.style.display = 'none';
                currentQuotationId = null;
            }, 350);
        }

        function confirmReject() {
            if (!currentQuotationId) return;
            
            const formData = new FormData();
            formData.append('action', 'reject_quotation');
            formData.append('quotationId', currentQuotationId);
            formData.append('is_ajax', '1');

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Quotation rejected successfully', 'reject');
                    closeConfirmationModal();
                    closeModal();
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                console.error('Error rejecting quotation:', error);
                showAlert(error.message || 'An error occurred while rejecting the quotation.', 'error');
            });
        }

        // Add this to your existing JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize search functionality
            const searchInputs = document.querySelectorAll('.search-input');
            const searchIcons = document.querySelectorAll('.search-icon');
            const searchContainers = document.querySelectorAll('.search-container');
            
            // Add click handlers for search icons
            searchIcons.forEach(icon => {
                icon.addEventListener('click', function(e) {
                    e.stopPropagation(); // Prevent event from bubbling up
                    const container = this.parentElement;
                    const input = container.querySelector('.search-input');
                    const section = container.closest('.section');
                    
                    // Expand the section if it's collapsed
                    if (section.classList.contains('collapsed')) {
                        section.classList.remove('collapsed');
                    }
                    
                    container.classList.toggle('expanded');
                    if (container.classList.contains('expanded')) {
                        input.focus();
                    }
                });
            });

            // Prevent click events on search containers and inputs from collapsing sections
            searchContainers.forEach(container => {
                container.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });

            searchInputs.forEach(input => {
                input.addEventListener('click', function(e) {
                    e.stopPropagation();
                });

                input.addEventListener('input', function() {
                    const section = this.dataset.section;
                    const searchTerm = this.value.toLowerCase();
                    const grid = document.getElementById(`${section}-grid`);
                    const cards = grid.querySelectorAll('.card');
                    
                    cards.forEach(card => {
                        const id = card.querySelector('.card-id').textContent.toLowerCase();
                        const date = card.querySelector('.card-date').textContent.toLowerCase();
                        
                        if (id.includes(searchTerm) || date.includes(searchTerm)) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                    
                    // Show no results message if needed
                    const visibleCards = grid.querySelectorAll('.card[style=""]').length;
                    let noResults = grid.querySelector('.no-results');
                    
                    if (visibleCards === 0) {
                        if (!noResults) {
                            noResults = document.createElement('div');
                            noResults.className = 'no-results';
                            noResults.textContent = 'No matching results found';
                            grid.appendChild(noResults);
                        }
                    } else if (noResults) {
                        noResults.remove();
                    }
                });
            });
            
            // Add blur handler to collapse search when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.search-container')) {
                    document.querySelectorAll('.search-container').forEach(container => {
                        container.classList.remove('expanded');
                    });
                }
            });
        });

        // Find the openDropoffModal function and add a showAlert call at the start
        function openDropoffModal(dropoff) {
            const modal = document.getElementById('myModal');
            const modalBox = document.getElementById('modalBox');
            
            // Prevent closing when clicking inside modal content
            modalBox.addEventListener('click', function(event) {
                event.stopPropagation();
            });

            // Format deadline
            let deadline = '';
            if (dropoff.dropoff_date) {
                const d = new Date(dropoff.dropoff_date);
                deadline = d.toLocaleDateString();
            }
            // Status color and text
            let statusText = '';
            let statusColor = '#cd7800';
            switch (dropoff.status) {
                case 0:
                case '0':
                    statusText = 'Pending Drop Off...';
                    statusColor = '#cd7800';
                    break;
                case 1:
                case '1':
                    statusText = 'Completed Drop Off';
                    statusColor = '#00bb0c'; // green
                    break;
                case 2:
                case '2':
                    statusText = 'Expired';
                    statusColor = '#800000'; // dark red
                    break;
                default:
                    statusText = 'Pending Drop Off...';
            }
            // Modal content: big image at top, three detail boxes in a row below
            let truckAnimation = '';
            if (dropoff.status == 0 || dropoff.status == '0') {
                truckAnimation = `
                    <div class="truck-animation-container">
                        <img src='assets/account/truck.png' class='truck-animation' alt='Truck' />
                    </div>
                    <style>
                    .truck-animation-container {
                        position: relative;
                        width: 100%;
                        height: 0;
                        margin: 0;
                    }
                    .truck-animation {
                        position: absolute;
                        left: 0;
                        bottom: -20px; /* Move the truck exactly to the bottom border of the top image (220px image height, truck height 96px, so -36px aligns bottom) */
                        height: 96px;
                        width: auto;
                        animation: truck-move 3s linear infinite;
                        z-index: 3;
                        pointer-events: none;
                    }
                    @keyframes truck-move {
                        0% { left: 0; }
                        100% { left: calc(100% - 140px); }
                    }
                    </style>
                `;
            } else if (dropoff.status == 1 || dropoff.status == '1') {
                truckAnimation = `
                    <div class="truck-center-container">
                        <img src='assets/account/truck.png' class='truck-center' alt='Truck' />
                    </div>
                    <style>
                    .truck-center-container {
                        position: relative;
                        width: 100%;
                        height: 0;
                        margin: 0;
                    }
                    .truck-center {
                        position: absolute;
                        left: 65%;
                        transform: translateX(-50%) rotate(28deg);
                        bottom: 20px;
                        height: 96px;
                        width: auto;
                        z-index: 3;
                        pointer-events: none;
                    }
                    </style>
                `;
            }
            let content = `
                <div style="display: flex; flex-direction: column; align-items: center; width: 100%;">
                    <!-- Big Image at the Top -->
                    <img src='assets/account/dropoff.png' alt='Drop-Off'
                        style='width: 100%; height: 220px; object-fit: cover; border-radius: 18px 18px 0 0; margin-bottom: 0;'/>
                    ${truckAnimation}
                    <!-- Three Detail Boxes Side by Side -->
                    <div style="width: 100%; display: flex; flex-direction: row; gap: 24px; margin-top: 40px; justify-content: space-between;">
                        <!-- Drop-off Address -->
                        <div style="flex: 1; background: rgba(89,184,160,0.10); border-radius: 24px; padding: 22px 18px; font-family: 'Arial Rounded MT Bold', sans-serif; color: #fff; min-width: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                            <div style='color: #59b8a0; font-size: 1.1em; font-weight: 700; margin-bottom: 10px;'>Drop Off Address</div>
                            <div style='font-size: 1em; color: #fff; line-height: 1.5; white-space: pre-line;'>Nothing Wasted,\nJalan Nothing 1/1, Taman Bukit Jalil,\n48000 Bukit Jalil,\nWilayah Persekutuan Kuala Lumpur,\nMalaysia.</div>
                        </div>
                        <!-- DropoffID + Status -->
                        <div style="flex: 1; background: rgba(89,184,160,0.10); border-radius: 24px; padding: 22px 18px; font-family: 'Arial Rounded MT Bold', sans-serif; color: #fff; min-width: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                            <div style="color: #59b8a0; font-size: 1.1em; font-weight: 700; margin-bottom: 10px;">Dropoff ID</div>
                            <div style="color: #fff; font-size: 1.2em; font-weight: 700; margin-bottom: 10px;">${dropoff.dropoff_id}</div>
                            <div style="color: ${statusColor}; font-size: 1.1em; font-weight: 700; margin-bottom: 18px;">${statusText}</div>
                        </div>
                        <!-- Up/Down Split Box for Drop Off By and Quotation ID -->
                        <div style="flex: 1; display: flex; flex-direction: column; gap: 18px; background: transparent; min-width: 0; align-items: stretch; justify-content: stretch;">
                            <!-- Drop Off By (Deadline) -->
                            <div style='background: rgba(89,184,160,0.10); border-radius: 18px; padding: 22px 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;'>
                                <div style='color: #59b8a0; font-size: 1.1em; font-weight: 700; margin-bottom: 10px;'>Drop Off By</div>
                                <div style='color: #fff; font-size: 1.2em; font-weight: 700;'>${deadline || 'DD/MM/YYYY'}</div>
                            </div>
                            <!-- Quotation ID -->
                            <div style='background: rgba(89,184,160,0.10); border-radius: 18px; padding: 22px 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;'>
                                <div style='color: #59b8a0; font-size: 1.1em; font-weight: 700; margin-bottom: 10px;'>Quotation ID</div>
                                <div style='color: #fff; font-size: 1.2em; font-weight: 700;'>${dropoff.quotation_id || '-'}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('modalContent').innerHTML = content;
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            // Style modal box
            if (modalBox) {
                modalBox.style.background = '#18322b';
                modalBox.style.maxWidth = '900px';
                modalBox.style.width = '95%';
                modalBox.style.minHeight = '';
                modalBox.style.padding = '32px 32px 40px 32px';
                modalBox.style.borderRadius = '28px';
            }
        }

        // 1. Update delivery cards in the tracking section to include data-delivery attribute
        function openDeliveryModal(delivery) {
            const modal = document.getElementById('myModal');
            const modalBox = document.getElementById('modalBox');
            modalBox.addEventListener('click', function(event) { event.stopPropagation(); });

            // Compose pickup address from quotation fields if available
            let address = '';
            if (delivery.addressline1 || delivery.addressline2 || delivery.zipcode || delivery.city || delivery.state) {
                address =
                    (delivery.addressline1 ? delivery.addressline1 + '<br>' : '') +
                    (delivery.addressline2 ? delivery.addressline2 + '<br>' : '') +
                    ((delivery.zipcode ? delivery.zipcode : '') + (delivery.city ? ' ' + delivery.city : '')) + '<br>' +
                    (delivery.state ? delivery.state : '');
            } else if (delivery.address) {
                address = delivery.address;
            }
            // Status text and color
            let statusText = '';
            let statusColor = '#cd7800';
            switch (delivery.status) {
                case 1:
                case '1':
                    statusText = 'Assigning Driver';
                    statusColor = '#cd7800';
                    break;
                case 2:
                case '2':
                    statusText = 'Driver Assigned';
                    statusColor = '#1E40AF';
                    break;
                case 3:
                case '3':
                    statusText = 'Picked Up';
                    statusColor = '#b5b5b5';
                    break;
                case 4:
                case '4':
                    statusText = 'Received';
                    statusColor = '#00bb0c';
                    break;
                default:
                    statusText = 'Assigning Driver';
                    statusColor = '#cd7800';
            }
            // Truck overlay position for each status
            let truckLeft = '10%'; // default for status 1
            let truckTop = '173px';
            switch (delivery.status) {
                case 1:
                case '1':
                    truckLeft = '0%'; // first dot
                    break;
                case 2:
                case '2':
                    truckLeft = '20%'; // second dot
                    break;
                case 3:
                case '3':
                    truckLeft = '46%'; // third dot
                    truckTop = '65px';
                    break;
                case 4:
                case '4':
                    truckLeft = '91%';
                    break;
                default:
                    truckLeft = '0%';
            }
            let truckOverlay = `<img src='assets/account/truck.png' alt='Truck' style='position: absolute; left: ${truckLeft}; top: ${truckTop}; height: 60px; width: auto; z-index: 2; pointer-events: none;'/>`;
            let content = `
                <div style="display: flex; flex-direction: column; align-items: center; width: 100%;">
                    <!-- Big Image at the Top -->
                    <div style='position: relative; width: 100%; height: 220px;'>
                        <img src='assets/account/pickup.png' alt='Pickup'
                            style='width: 100%; height: 220px; object-fit: cover; border-radius: 18px 18px 0 0; margin-bottom: 0;'/>
                        ${truckOverlay}
                    </div>
                    <!-- Three Detail Boxes Side by Side -->
                    <div style="width: 100%; display: flex; flex-direction: row; gap: 24px; margin-top: 40px; justify-content: space-between;">
                        <!-- Pick Up Address -->
                        <div style="flex: 1; background: rgba(89,184,160,0.10); border-radius: 24px; padding: 22px 18px; font-family: 'Arial Rounded MT Bold', sans-serif; color: #fff; min-width: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                            <div style='color: #59b8a0; font-size: 1.1em; font-weight: 700; margin-bottom: 10px;'>Pick Up Address</div>
                            <div style='font-size: 1em; color: #fff; line-height: 1.5; white-space: pre-line;'>${address}</div>
                        </div>
                        <!-- DeliveryID + Status -->
                        <div style="flex: 1; background: rgba(89,184,160,0.10); border-radius: 24px; padding: 22px 18px; font-family: 'Arial Rounded MT Bold', sans-serif; color: #fff; min-width: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;">
                            <div style="color: #59b8a0; font-size: 1.1em; font-weight: 700; margin-bottom: 10px;">Delivery ID</div>
                            <div style="color: #fff; font-size: 1.2em; font-weight: 700; margin-bottom: 10px;">${delivery.delivery_id || ''}</div>
                            <div style="color: ${statusColor}; font-size: 1.1em; font-weight: 700; margin-bottom: 18px;">${statusText}</div>
                        </div>
                        <!-- Up/Down Split Box for Est. Arrival and Quotation ID -->
                        <div style="flex: 1; display: flex; flex-direction: column; gap: 18px; background: transparent; min-width: 0; align-items: stretch; justify-content: stretch;">
                            <!-- Est. Arrival Date -->
                            <div style='background: rgba(89,184,160,0.10); border-radius: 18px; padding: 22px 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;'>
                                <div style='color: #59b8a0; font-size: 1.1em; font-weight: 700; margin-bottom: 10px;'>Est. Arrival Date</div>
                                <div style='color: #fff; font-size: 1.2em; font-weight: 700;'>${delivery.est_arrival ? new Date(delivery.est_arrival).toLocaleDateString() : 'DD/MM/YYYY'}</div>
                            </div>
                            <!-- Quotation ID -->
                            <div style='background: rgba(89,184,160,0.10); border-radius: 18px; padding: 22px 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;'>
                                <div style='color: #59b8a0; font-size: 1.1em; font-weight: 700; margin-bottom: 10px;'>Quotation ID</div>
                                <div style='color: #fff; font-size: 1.2em; font-weight: 700;'>${delivery.quotation_id || '-'}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('modalContent').innerHTML = content;
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            // Style modal box
            if (modalBox) {
                modalBox.style.background = '#18322b';
                modalBox.style.maxWidth = '900px';
                modalBox.style.width = '95%';
                modalBox.style.minHeight = '';
                modalBox.style.padding = '32px 32px 40px 32px';
                modalBox.style.borderRadius = '28px';
            }
        }

        function openHistoryModal(history) {
            const modal = document.getElementById('myModal');
            const modalBox = document.getElementById('modalBox');
            modalBox.addEventListener('click', function(event) { event.stopPropagation(); });

            // Format date
            const datePaid = history.date_paid ? new Date(history.date_paid).toLocaleDateString() : 'DD/MM/YYYY';
            // Amount
            const amount = history.amount ? 'RM' + history.amount : 'RMXXX';
            // Points
            const points = history.point_to_add || '0';

            let content = `
                <div style="display: flex; flex-direction: column; align-items: flex-start; background: #18322b; border-radius: 24px; padding: 32px 32px 32px 32px; min-width: 340px; min-height: 340px; position: relative;">
                    <button onclick="closeModal()" style="position: absolute; top: 18px; right: 18px; background: none; border: none; color: #fff; font-size: 28px; cursor: pointer;">&times;</button>
                    <div style="width: 100%; text-align: center; margin-bottom: 18px;">
                        <span style="font-family: 'Arial Rounded MT Bold', sans-serif; color: #59b8a0; font-size: 20px; font-weight: bold;">${history.history_id || 'HistoryID'}</span>
                    </div>
                    <div style="margin-bottom: 18px; width: 100%;">
                        <div style="color: #59b8a0; font-family: 'Arial Rounded MT Bold', sans-serif; font-size: 16px; margin-bottom: 8px; font-weight: bold;">
                            Amount Paid: <span style="color: #fff; font-weight: bold;">${amount}</span>
                        </div>
                        <div style="color: #3ecf8e; font-family: 'Arial Rounded MT Bold', sans-serif; font-size: 16px; margin-bottom: 8px; font-weight: bold;">
                            <span style='display: inline-block; vertical-align: middle;'><img src='assets/account/point.png' style='width: 22px; height: 22px; object-fit: contain; vertical-align: middle; margin-right: 6px; display: inline-block;' alt='Points'/></span>
                            <span style='display: inline-block; vertical-align: middle;'>Points Rewarded: <span style="color: #fff; margin-left: 4px; font-weight: bold;">${points} Points</span></span>
                        </div>
                        <div style="color: #59b8a0; font-family: 'Arial Rounded MT Bold', sans-serif; font-size: 16px; font-weight: bold;">
                            Date Paid: <span style="color: #fff; font-weight: bold;">${datePaid}</span>
                        </div>
                    </div>
                    <div style='width: 100%; background: rgba(89,184,160,0.10); border-radius: 16px; padding: 18px 18px 10px 18px; margin-bottom: 18px;'>
                        <div style='color: #59b8a0; font-family: "Arial Rounded MT Bold", sans-serif; font-size: 15px; font-weight: bold; margin-bottom: 8px;'>Payment Details</div>
                        <div style='color: #fff; font-family: "Arial Rounded MT Bold", sans-serif; font-size: 15px; margin-bottom: 4px;'><span style="font-weight:bold;">Payment ID:</span> <span style='color: #b5b5b5; font-weight:normal;'>${history.payment_id || '-'}</span></div>
                        <div style='color: #fff; font-family: "Arial Rounded MT Bold", sans-serif; font-size: 15px; margin-bottom: 4px;'><span style="font-weight:bold;">Bank Account:</span> <span style='color: #b5b5b5; font-weight:normal;'>${history.bank_acc || '-'}</span></div>
                        <div style='color: #fff; font-family: "Arial Rounded MT Bold", sans-serif; font-size: 15px; margin-bottom: 4px;'><span style="font-weight:bold;">Bank Name:</span> <span style='color: #b5b5b5; font-weight:normal;'>${history.bank_name || '-'}</span></div>
                        <div style='color: #fff; font-family: "Arial Rounded MT Bold", sans-serif; font-size: 15px; margin-bottom: 4px;'><span style="font-weight:bold;">Account Holder:</span> <span style='color: #b5b5b5; font-weight:normal;'>${history.acc_holder || '-'}</span></div>
                        <div style='color: #fff; font-family: "Arial Rounded MT Bold", sans-serif; font-size: 15px; margin-bottom: 4px;'><span style="font-weight:bold;">Quotation ID:</span> <span style='color: #b5b5b5; font-weight:normal;'>${history.quotation_id || '-'}</span></div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 16px; width: 100%; margin-top: 8px;">
                        <button id="viewPaymentReceiptBtn" style="background: #59b8a0; color: #fff; border: none; border-radius: 20px; padding: 12px 0; font-size: 16px; font-family: 'Arial Rounded MT Bold', sans-serif; font-weight: 600; cursor: pointer; width: 100%; margin-bottom: 8px;">
                            View Payment Receipt
                        </button>
                        <a href="feedback.php?quotation_id=${encodeURIComponent(history.quotation_id)}" style="font-family: 'Arial Rounded MT Bold', sans-serif; background: #4170e7; color: #fff; border: none; border-radius: 50px; padding: 12px 32px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; gap: 8px; text-decoration: none; justify-content: center;" onmouseover="this.style.background='#2855c5'" onmouseout="this.style.background='#4170e7'">
                            <span>Leave us a feedback!</span>
                            <span style="font-size: 18px;">💭</span>
                        </a>
                    </div>
                </div>
            `;

            document.getElementById('modalContent').innerHTML = content;
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';

            // Add event listener for View Payment Receipt button
            setTimeout(function() {
                const btn = document.getElementById('viewPaymentReceiptBtn');
                if (btn) {
                    btn.onclick = function() {
                        if (history.payment_id) {
                            window.open('view_payment_receipt.php?id=' + encodeURIComponent(history.payment_id), '_blank');
                        } else {
                            alert('No payment receipt available.');
                        }
                    };
                }
            }, 0);

            // Style modal box for compact look
            if (modalBox) {
                modalBox.style.background = 'transparent';
                modalBox.style.maxWidth = '400px';
                modalBox.style.width = '95%';
                modalBox.style.minHeight = '';
                modalBox.style.padding = '0';
                modalBox.style.borderRadius = '28px';
            }
        }
    </script>
</body>

</html>
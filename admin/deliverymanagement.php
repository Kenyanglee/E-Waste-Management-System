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

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Pagination for dropoff
    $dropoff_page = isset($_GET['dropoff_page']) ? (int)$_GET['dropoff_page'] : 1;
    $limit = 10;
    $dropoff_offset = ($dropoff_page - 1) * $limit;
    $stmt = $conn->query("SELECT COUNT(*) FROM dropoff");
    $dropoff_total_records = $stmt->fetchColumn();
    $dropoff_total_pages = ceil($dropoff_total_records / $limit);
    $stmt = $conn->prepare("
        SELECT d.*, q.user_id, u.user_name, u.email
        FROM dropoff d
        LEFT JOIN quotation q ON d.quotation_id = q.quotation_id
        LEFT JOIN user u ON q.user_id = u.user_id
        ORDER BY d.dropoff_date DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $dropoff_offset, PDO::PARAM_INT);
    $stmt->execute();
    $dropoffs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pagination for delivery
    $pickup_page = isset($_GET['pickup_page']) ? (int)$_GET['pickup_page'] : 1;
    $pickup_offset = ($pickup_page - 1) * $limit;
    $stmt = $conn->query("SELECT COUNT(*) FROM delivery");
    $pickup_total_records = $stmt->fetchColumn();
    $pickup_total_pages = ceil($pickup_total_records / $limit);
    $stmt = $conn->prepare("
        SELECT d.*, q.user_id, u.user_name, u.email
        FROM delivery d
        LEFT JOIN quotation q ON d.quotation_id = q.quotation_id
        LEFT JOIN user u ON q.user_id = u.user_id
        ORDER BY d.date DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $pickup_offset, PDO::PARAM_INT);
    $stmt->execute();
    $pickups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Delete expired dropoffs and update quotation status
    try {
        $today = date('Y-m-d');
        $stmt = $conn->prepare('SELECT dropoff_id, quotation_id FROM dropoff WHERE status = 0 AND dropoff_date < :today');
        $stmt->execute([':today' => $today]);
        $expiredDropoffs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($expiredDropoffs as $expired) {
            // Update dropoff status to Expired (2)
            $stmt2 = $conn->prepare('UPDATE dropoff SET status = 2 WHERE dropoff_id = :dropoff_id');
            $stmt2->execute([':dropoff_id' => $expired['dropoff_id']]);
            // Update quotation status to Expired
            $stmt3 = $conn->prepare('UPDATE quotation SET status = "Expired" WHERE quotation_id = :quotation_id');
            $stmt3->execute([':quotation_id' => $expired['quotation_id']]);
        }
    } catch (Exception $e) {
        // Optionally log error
    }

    // Calculate dropoff status counts
    $dropoffStatusCounts = [
        'Pending' => 0,
        'Done' => 0,
        'Expired' => 0
    ];
    foreach ($dropoffs as $dropoff) {
        if ($dropoff['status'] == 1) {
            $dropoffStatusCounts['Done']++;
        } else if ($dropoff['status'] == 2) {
            $dropoffStatusCounts['Expired']++;
        } else {
            $dropoffStatusCounts['Pending']++;
        }
    }
    $dropoffTotal = $dropoffStatusCounts['Pending'] + $dropoffStatusCounts['Done'] + $dropoffStatusCounts['Expired'];

    // Calculate delivery status counts
    $deliveryStatusCounts = [
        'Assigning Driver' => 0,
        'Driver Assigned' => 0,
        'Picked Up' => 0,
        'Received' => 0,
    ];
    foreach ($pickups as $pickup) {
        switch ((int)$pickup['status']) {
            case 1:
                $deliveryStatusCounts['Assigning Driver']++;
                break;
            case 2:
                $deliveryStatusCounts['Driver Assigned']++;
                break;
            case 3:
                $deliveryStatusCounts['Picked Up']++;
                break;
            case 4:
                $deliveryStatusCounts['Received']++;
                break;
        }
    }
    $deliveryTotal = array_sum($deliveryStatusCounts);

} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit();
}

// Helper function to generate the next available ewaste_id in the format inv#X
function getNextEwasteId($conn) {
    $stmt = $conn->query("SELECT ewaste_id FROM inventory");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $used = [];
    foreach ($ids as $id) {
        if (preg_match('/^inv#(\\d+)$/', $id, $m)) {
            $used[(int)$m[1]] = true;
        }
    }
    $i = 1;
    while (true) {
        if (!isset($used[$i])) {
            return 'inv#' . $i;
        }
        $i++;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['action']) && $input['action'] === 'update_dropoff_status') {
        $dropoff_id = $input['dropoff_id'];
        $quotation_id = $input['quotation_id'];
        $status = (int)$input['status'];
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare('UPDATE dropoff SET status = :status WHERE dropoff_id = :dropoff_id');
            $stmt->execute([':status' => $status, ':dropoff_id' => $dropoff_id]);
            if ($status === 1) {
                $stmt = $conn->prepare('UPDATE quotation SET status = "Completed" WHERE quotation_id = :quotation_id');
                $stmt->execute([':quotation_id' => $quotation_id]);
                
                // Get submission details for inventory and points
                $stmt = $conn->prepare('
                    SELECT s.*, q.total as value, q.point_to_add, q.user_id 
                    FROM submission s 
                    JOIN quotation q ON s.submission_id = q.submission_id 
                    WHERE q.quotation_id = :quotation_id
                ');
                $stmt->execute([':quotation_id' => $quotation_id]);
                $submission = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($submission) {
                    // Add points to user
                    if ($submission['point_to_add'] > 0) {
                        $stmt = $conn->prepare('UPDATE user SET point = point + :points WHERE user_id = :user_id');
                        $stmt->execute([
                            ':points' => $submission['point_to_add'],
                            ':user_id' => $submission['user_id']
                        ]);
                    }

                    // Generate ewaste_id
                    $ewaste_id = getNextEwasteId($conn);
                    // Insert into inventory
                    $stmt = $conn->prepare('
                        INSERT INTO inventory (
                            ewaste_id, submission_id, quotation_id, dropoff_id,
                            laptop_inv, desktop_inv, monitor_inv, printer_inv,
                            phone_inv, appliance_inv, wearables_inv, cables_inv,
                            accessories_inv, date
                        ) VALUES (
                            :ewaste_id, :submission_id, :quotation_id, :dropoff_id,
                            :laptop_inv, :desktop_inv, :monitor_inv, :printer_inv,
                            :phone_inv, :appliance_inv, :wearables_inv, :cables_inv,
                            :accessories_inv, :date
                        )
                    ');
                    $stmt->execute([
                        ':ewaste_id' => $ewaste_id,
                        ':submission_id' => $submission['submission_id'],
                        ':quotation_id' => $quotation_id,
                        ':dropoff_id' => $dropoff_id,
                        ':laptop_inv' => $submission['laptop_qty'],
                        ':desktop_inv' => $submission['desktop_qty'],
                        ':monitor_inv' => $submission['monitor_qty'],
                        ':printer_inv' => $submission['printer_qty'],
                        ':phone_inv' => $submission['phone_qty'],
                        ':appliance_inv' => $submission['appliance_qty'],
                        ':wearables_inv' => $submission['wearables_qty'],
                        ':cables_inv' => $submission['cables_qty'],
                        ':accessories_inv' => $submission['accessories_qty'],
                        ':date' => date('Y-m-d')
                    ]);
                }
            }
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    if (isset($input['action']) && $input['action'] === 'update_pickup_status') {
        $delivery_id = $input['delivery_id'];
        $status = (int)$input['status'];
        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare('UPDATE delivery SET status = :status WHERE delivery_id = :delivery_id');
            $stmt->execute([':status' => $status, ':delivery_id' => $delivery_id]);
            // If status is 4 (Received), update related quotation to Completed
            if ($status === 4) {
                $stmt = $conn->prepare('SELECT quotation_id FROM delivery WHERE delivery_id = :delivery_id');
                $stmt->execute([':delivery_id' => $delivery_id]);
                $quotation_id = $stmt->fetchColumn();
                if ($quotation_id) {
                    $stmt = $conn->prepare('UPDATE quotation SET status = "Completed" WHERE quotation_id = :quotation_id');
                    $stmt->execute([':quotation_id' => $quotation_id]);
                    
                    // Get submission details for inventory and points
                    $stmt = $conn->prepare('
                        SELECT s.*, q.total as value, q.point_to_add, q.user_id 
                        FROM submission s 
                        JOIN quotation q ON s.submission_id = q.submission_id 
                        WHERE q.quotation_id = :quotation_id
                    ');
                    $stmt->execute([':quotation_id' => $quotation_id]);
                    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($submission) {
                        // Add points to user
                        if ($submission['point_to_add'] > 0) {
                            $stmt = $conn->prepare('UPDATE user SET point = point + :points WHERE user_id = :user_id');
                            $stmt->execute([
                                ':points' => $submission['point_to_add'],
                                ':user_id' => $submission['user_id']
                            ]);
                        }

                        // Generate ewaste_id
                        $ewaste_id = getNextEwasteId($conn);
                        // Insert into inventory
                        $stmt = $conn->prepare('
                            INSERT INTO inventory (
                                ewaste_id, submission_id, quotation_id, delivery_id,
                                laptop_inv, desktop_inv, monitor_inv, printer_inv,
                                phone_inv, appliance_inv, wearables_inv, cables_inv,
                                accessories_inv, date
                            ) VALUES (
                                :ewaste_id, :submission_id, :quotation_id, :delivery_id,
                                :laptop_inv, :desktop_inv, :monitor_inv, :printer_inv,
                                :phone_inv, :appliance_inv, :wearables_inv, :cables_inv,
                                :accessories_inv, :date
                            )
                        ');
                        $stmt->execute([
                            ':ewaste_id' => $ewaste_id,
                            ':submission_id' => $submission['submission_id'],
                            ':quotation_id' => $quotation_id,
                            ':delivery_id' => $delivery_id,
                            ':laptop_inv' => $submission['laptop_qty'],
                            ':desktop_inv' => $submission['desktop_qty'],
                            ':monitor_inv' => $submission['monitor_qty'],
                            ':printer_inv' => $submission['printer_qty'],
                            ':phone_inv' => $submission['phone_qty'],
                            ':appliance_inv' => $submission['appliance_qty'],
                            ':wearables_inv' => $submission['wearables_qty'],
                            ':cables_inv' => $submission['cables_qty'],
                            ':accessories_inv' => $submission['accessories_qty'],
                            ':date' => date('Y-m-d')
                        ]);
                    }
                }
            }
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    if (isset($input['action']) && $input['action'] === 'update_est_arrival') {
        $delivery_id = $input['delivery_id'];
        $est_arrival = $input['est_arrival'];
        try {
            $stmt = $conn->prepare('UPDATE delivery SET est_arrival = :est_arrival WHERE delivery_id = :delivery_id');
            $stmt->execute([':est_arrival' => $est_arrival, ':delivery_id' => $delivery_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// Add this function before any HTML output
function getPickupStatusBadge($status) {
    $statusMap = [
        1 => ['class' => 'status-assigning', 'label' => 'Assigning Driver'],
        2 => ['class' => 'status-assigned', 'label' => 'Driver Assigned'],
        3 => ['class' => 'status-pickedup', 'label' => 'Picked Up'],
        4 => ['class' => 'status-received', 'label' => 'Received'],
    ];
    $status = (int)$status;
    if (isset($statusMap[$status])) {
        $info = $statusMap[$status];
        return '<span class="status-badge ' . $info['class'] . '">' . $info['label'] . '</span>';
    }
    // fallback: default to Assigning Driver
    $info = $statusMap[1];
    return '<span class="status-badge ' . $info['class'] . '">' . $info['label'] . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Management - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        .account { width: 40px; height: 40px; border-radius: 50%; background-color: #C4C4C4; overflow: hidden; cursor: pointer; transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out; }
        .account:hover { transform: scale(1.1); animation: pulse-profile 2.5s infinite; }
        .account img { width: 100%; height: 100%; object-fit: cover; }
        .card { background: white; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: var(--card-shadow); transition: var(--transition-default); border: 1px solid rgba(0, 0, 0, 0.05); max-width: 100%; box-sizing: border-box; }
        .card:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); transform: translateY(-2px); }
        .card-header { display: flex; align-items: center; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
        .card-header h2 { margin: 0; white-space: nowrap; }
        .tab-btn { background: #F7FAFC; color: #2D3748; border: 1px solid #E2E8F0; border-radius: 8px 8px 0 0; padding: 10px 32px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.2s, color 0.2s; outline: none; }
        .tab-btn.active, .tab-btn[aria-pressed="true"] { background: var(--primary-color); color: #fff; border-bottom: 2px solid var(--primary-color); }
        .tab-btn:not(.active):hover { background: #E6F4F1; color: var(--primary-color); }
        .search-bar { position: relative; width: 400px; }
        .search-bar input { width: 100%; padding: 12px 20px 12px 45px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 0.95rem; color: var(--text-primary); background: white; transition: all 0.3s ease; }
        .search-bar input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(80, 184, 142, 0.2); }
        .search-bar i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #A0AEC0; font-size: 1.1rem; }
        .search-bar input::placeholder { color: #A0AEC0; }
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
        .modal-content { background-color: white; border-radius: 12px; width: 800px; max-width: 90%; max-height: 90vh; overflow-y: auto; transform: scale(0.7); opacity: 0; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .modal.show .modal-content { transform: scale(1); opacity: 1; }
        .modal-header { padding: 20px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; color: var(--text-primary); font-size: 1.25rem; }
        .close-button { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary); padding: 0; }
        .modal-body { padding: 20px; color: var(--text-secondary); }
        .detail-item { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #E2E8F0; }
        .detail-item:last-child { border-bottom: none; }
        .detail-label { font-weight: 500; color: var(--text-primary); }
        .detail-value { color: var(--text-secondary); }
        .quotation-details { margin-bottom: 20px; }
        .quotation-details h4 { margin: 20px 0 10px; color: var(--text-primary); }
        tr { cursor: pointer; }
        tr:hover { background-color: #F7FAFC; }
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
        .alert-info {
            background-color: #E5E7EB;
            color: #374151;
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
        @media (max-width: 1920px) { .main-content { padding: 20px; } .card { padding: 20px; } th, td { padding: 10px 12px; } }
        @media (max-width: 1600px) { :root { --content-width: 1360px; } }
        @media (max-width: 1366px) { :root { --content-width: 1086px; } .search-bar { width: 300px; } }
        .status-badge { padding: 6px 12px; border-radius: 50px; font-size: 0.875rem; font-weight: 500; text-align: center; display: inline-block; min-width: 100px; }
        .status-pending { background-color: #FEF3C7; color: #92400E; }
        .status-accepted { background-color: #10B981; color: white; }
        .status-rejected { background-color: #EF4444; color: white; }
        .status-completed { background-color: #6B7280; color: white; }
        .status-expired { background-color: #6B7280; color: white; }
        .status-badge.status-done {
            background-color: #C6F6D5;
            color: #065F46;
        }
        .status-badge.status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        /* Add custom styles for colored dropdowns */
        .dropoff-status-dropdown {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 500;
            min-width: 110px;
            border: 1px solid #E2E8F0;
            background-color: #FEF3C7; /* default to pending yellow */
            color: #92400E;
            transition: background 0.2s, color 0.2s;
        }
        .dropoff-status-dropdown.done {
            background-color: #C6F6D5;
            color: #065F46;
        }
        .dropoff-status-dropdown.pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .dropoff-status-dropdown option[value="0"] {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .dropoff-status-dropdown option[value="1"] {
            background-color: #C6F6D5;
            color: #065F46;
        }
        .status-badge.status-assigning {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .status-badge.status-assigned {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        .status-badge.status-pickedup {
            background-color: #E5E7EB;
            color: #374151;
        }
        .status-badge.status-received {
            background-color: #C6F6D5;
            color: #065F46;
        }
        .pickup-status-dropdown {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 500;
            min-width: 110px;
            border: 1px solid #E2E8F0;
            background-color: #FEF3C7; /* default to assigning yellow */
            color: #92400E;
            transition: background 0.2s, color 0.2s;
        }
        .pickup-status-dropdown.status-1 {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .pickup-status-dropdown.status-2 {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        .pickup-status-dropdown.status-3 {
            background-color: #E5E7EB;
            color: #374151;
        }
        .pickup-status-dropdown.status-4 {
            background-color: #C6F6D5;
            color: #065F46;
        }
        .pickup-status-dropdown option[value="1"] {
            background-color: #FEF3C7;
            color: #92400E;
        }
        .pickup-status-dropdown option[value="2"] {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        .pickup-status-dropdown option[value="3"] {
            background-color: #E5E7EB;
            color: #374151;
        }
        .pickup-status-dropdown option[value="4"] {
            background-color: #C6F6D5;
            color: #065F46;
        }
        /* Alert Modal Styles */
        .alert-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .alert-modal.show {
            opacity: 1;
        }
        .alert-modal-content {
            background-color: white;
            border-radius: 12px;
            width: 400px;
            max-width: 90%;
            transform: scale(0.7);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .alert-modal.show .alert-modal-content {
            transform: scale(1);
            opacity: 1;
        }
        .alert-modal-header {
            padding: 20px;
            border-bottom: 1px solid #E2E8F0;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }
        .alert-modal-header i {
            font-size: 2rem;
        }
        .alert-modal-header i.success {
            color: #38A169;
        }
        .alert-modal-header i.error {
            color: #C53030;
        }
        .alert-modal-body {
            padding: 20px;
            text-align: center;
            color: var(--text-primary);
            font-size: 1.1rem;
        }
        .alert-modal-footer {
            padding: 20px;
            border-top: 1px solid #E2E8F0;
            display: flex;
            justify-content: center;
            gap: 12px;
        }
        .alert-modal-button {
            padding: 8px 24px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .alert-modal-button.confirm {
            background-color: var(--primary-color);
            color: white;
        }
        .alert-modal-button.confirm:hover {
            background-color: #3d8b6d;
        }
        .alert-modal-button.cancel {
            background-color: var(--bg-light);
            color: var(--text-primary);
        }
        .alert-modal-button.cancel:hover {
            background-color: #E2E8F0;
        }
        .dropoff-status-dropdown.expired {
            background-color: #6B7280;
            color: white;
        }
        .est-arrival-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .edit-est-arrival-btn {
            background: none;
            border: none;
            color: #4A90E2;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }
        .edit-est-arrival-btn:hover {
            background-color: #EBF5FF;
            transform: scale(1.1);
        }
        .est-arrival-input {
            padding: 4px 8px;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        /* Responsive Styles */
        @media (max-width: 1400px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .delivery-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .delivery-stats {
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
                display: none;
                transition: all 0.3s ease;
                overflow: hidden;
            }
            .submenu .menu-item {
                padding: 12px 16px;
                font-size: 0.95em;
            }
            .submenu.active {
                display: block !important;
            }
            .fa-chevron-down {
                transition: transform 0.3s ease;
            }
            .fa-chevron-down.active {
                transform: rotate(180deg);
            }

            .table-responsive {
                overflow-x: auto;
            }

            .table th, .table td {
                min-width: 120px;
            }

            .delivery-details {
                flex-direction: column;
            }

            .delivery-section {
                width: 100%;
            }

            .map-container {
                height: 300px;
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
            
            .delivery-grid {
                grid-template-columns: 1fr;
            }

            .delivery-stats {
                grid-template-columns: 1fr;
            }

            .delivery-card {
                padding: 15px;
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

            .status-timeline {
                padding: 10px;
            }

            .timeline-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }

            .map-container {
                height: 250px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 10px;
            }
            
            .card {
                padding: 12px;
            }
            
            .delivery-card {
                padding: 12px;
            }
            
            .delivery-card h3 {
                font-size: 1rem;
            }
            
            .delivery-card p {
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

            .delivery-meta {
                flex-direction: column;
                gap: 5px;
            }

            .status-badge {
                font-size: 0.8rem;
                padding: 4px 8px;
            }

            .map-container {
                height: 200px;
            }

            .timeline-dot {
                width: 20px;
                height: 20px;
            }

            .timeline-content {
                padding-left: 30px;
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

            .delivery-details {
                page-break-inside: avoid;
            }

            .btn, .action-button {
                display: none;
            }

            .status-timeline {
                page-break-inside: avoid;
            }

            .map-container {
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
            <a href="deliverymanagement.php" class="menu-item active">
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
            <div class="card-header" style="flex-direction: column; align-items: flex-start; gap: 0;">
                <div style="display: flex; gap: 16px; width: 100%; margin-bottom: 32px;">
                    <button id="pickupTab" class="tab-btn active">Pickup</button>
                    <button id="dropoffTab" class="tab-btn">Dropoff</button>
                    <button id="checkValidityBtn" class="tab-btn" style="margin-left: auto; background-color: #4A90E2; color: white; display: none;">
                        <i class="fas fa-clock"></i> Check Validity
                    </button>
                </div>
                <div style="display: flex; align-items: center; width: 100%; gap: 24px;">
                    <h2 style="margin: 0;">Delivery Management</h2>
                    <div class="search-bar" style="margin-left: 0;">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by ID, Name, or Email...">
                    </div>
                </div>
            </div>
            <!-- Status Summary Table -->
            <div style="margin-bottom: 32px;">
                <h3 style="margin-bottom: 12px; color: #2D3748;">Dropoff & Delivery Status Summary</h3>
                <?php
                // Dropoff summary table HTML
                $dropoffSummaryTable = '<table style="width: 100%; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 24px; border-collapse: separate; border-spacing: 0;">
                    <thead>
                        <tr style="background: #F7FAFC;">
                            <th style="padding: 10px 16px; text-align: left;">Type</th>
                            <th style="padding: 10px 16px; text-align: left;">Total</th>
                            <th style="padding: 10px 16px; text-align: left;">Status</th>
                            <th style="padding: 10px 16px; text-align: left;">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td rowspan="3" style="font-weight: bold; color: #4170e7;">Dropoff</td>
                            <td rowspan="3" style="font-weight: bold;">' . $dropoffTotal . '</td>
                            <td style="color: #cd7800;">Pending</td>
                            <td>' . $dropoffStatusCounts['Pending'] . '</td>
                        </tr>
                        <tr>
                            <td style="color: #00bb0c;">Done</td>
                            <td>' . $dropoffStatusCounts['Done'] . '</td>
                        </tr>
                        <tr>
                            <td style="color: #6B7280;">Expired</td>
                            <td>' . $dropoffStatusCounts['Expired'] . '</td>
                        </tr>
                    </tbody>
                </table>';
                // Delivery summary table HTML
                $deliverySummaryTable = '<table style="width: 100%; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 24px; border-collapse: separate; border-spacing: 0;">
                    <thead>
                        <tr style="background: #F7FAFC;">
                            <th style="padding: 10px 16px; text-align: left;">Type</th>
                            <th style="padding: 10px 16px; text-align: left;">Total</th>
                            <th style="padding: 10px 16px; text-align: left;">Status</th>
                            <th style="padding: 10px 16px; text-align: left;">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td rowspan="4" style="font-weight: bold; color: #59b8a0;">Delivery</td>
                            <td rowspan="4" style="font-weight: bold;">' . $deliveryTotal . '</td>
                            <td style="color: #cd7800;">Assigning Driver</td>
                            <td>' . $deliveryStatusCounts['Assigning Driver'] . '</td>
                        </tr>
                        <tr>
                            <td style="color: #1E40AF;">Driver Assigned</td>
                            <td>' . $deliveryStatusCounts['Driver Assigned'] . '</td>
                        </tr>
                        <tr>
                            <td style="color: #b5b5b5;">Picked Up</td>
                            <td>' . $deliveryStatusCounts['Picked Up'] . '</td>
                        </tr>
                        <tr>
                            <td style="color: #00bb0c;">Received</td>
                            <td>' . $deliveryStatusCounts['Received'] . '</td>
                        </tr>
                    </tbody>
                </table>';
                ?>
            </div>
            <div class="table-container" id="pickupContent">
                <?= $deliverySummaryTable ?>
                <table>
                    <thead>
                        <tr>
                            <th>Delivery ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Est. Arrival</th>
                            <th>Quotation ID</th>
                        </tr>
                    </thead>
                    <tbody id="pickupTableBody">
                        <?php foreach ($pickups as $pickup): ?>
                        <tr data-type="pickup" data-id="<?= htmlspecialchars($pickup['delivery_id']) ?>">
                            <td><?= htmlspecialchars($pickup['delivery_id']) ?></td>
                            <td><?= htmlspecialchars($pickup['user_name']) ?></td>
                            <td><?= htmlspecialchars($pickup['email']) ?></td>
                            <td><?= getPickupStatusBadge($pickup['status']) ?></td>
                            <td><?= date('d/m/Y', strtotime($pickup['date'])) ?></td>
                            <td>
                                <div class="est-arrival-container">
                                    <span class="est-arrival-display"><?= $pickup['est_arrival'] ? date('d/m/Y', strtotime($pickup['est_arrival'])) : '-' ?></span>
                                    <button class="edit-est-arrival-btn" data-delivery-id="<?= htmlspecialchars($pickup['delivery_id']) ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($pickup['quotation_id']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="pagination">
                    <?php if ($pickup_page > 1): ?>
                        <button class="page-button" onclick="window.location.href='?pickup_page=<?= $pickup_page-1 ?>&dropoff_page=<?= $dropoff_page ?>'">Previous</button>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $pickup_total_pages; $i++): ?>
                        <button class="page-button <?= $i === $pickup_page ? 'active' : '' ?>" onclick="window.location.href='?pickup_page=<?= $i ?>&dropoff_page=<?= $dropoff_page ?>'">
                            <?= $i ?>
                        </button>
                    <?php endfor; ?>
                    <?php if ($pickup_page < $pickup_total_pages): ?>
                        <button class="page-button" onclick="window.location.href='?pickup_page=<?= $pickup_page+1 ?>&dropoff_page=<?= $dropoff_page ?>'">Next</button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-container" id="dropoffContent" style="display:none;">
                <?= $dropoffSummaryTable ?>
                <table>
                    <thead>
                        <tr>
                            <th>Dropoff ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Dropoff Deadline</th>
                            <th>Quotation ID</th>
                        </tr>
                    </thead>
                    <tbody id="dropoffTableBody">
                        <?php foreach ($dropoffs as $dropoff): ?>
                        <tr data-type="dropoff" data-id="<?= htmlspecialchars($dropoff['dropoff_id']) ?>">
                            <td><?= htmlspecialchars($dropoff['dropoff_id']) ?></td>
                            <td><?= htmlspecialchars($dropoff['user_name']) ?></td>
                            <td><?= htmlspecialchars($dropoff['email']) ?></td>
                            <td class="dropoff-status-cell" data-status="<?= $dropoff['status'] ?>">
                                <?php if ((int)$dropoff['status'] === 1): ?>
                                    <span class="status-badge status-done">Done</span>
                                <?php elseif ((int)$dropoff['status'] === 2): ?>
                                    <span class="status-badge status-expired">Expired</span>
                                <?php else: ?>
                                    <select class="dropoff-status-dropdown" data-dropoff-id="<?= htmlspecialchars($dropoff['dropoff_id']) ?>" data-quotation-id="<?= htmlspecialchars($dropoff['quotation_id']) ?>">
                                        <option value="0" <?= (int)$dropoff['status'] === 0 ? 'selected' : '' ?>>Pending</option>
                                        <option value="1">Done</option>
                                    </select>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($dropoff['dropoff_date'])) ?></td>
                            <td><?= htmlspecialchars($dropoff['quotation_id']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="pagination">
                    <?php if ($dropoff_page > 1): ?>
                        <button class="page-button" onclick="window.location.href='?pickup_page=<?= $pickup_page ?>&dropoff_page=<?= $dropoff_page-1 ?>'">Previous</button>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $dropoff_total_pages; $i++): ?>
                        <button class="page-button <?= $i === $dropoff_page ? 'active' : '' ?>" onclick="window.location.href='?pickup_page=<?= $pickup_page ?>&dropoff_page=<?= $i ?>'">
                            <?= $i ?>
                        </button>
                    <?php endfor; ?>
                    <?php if ($dropoff_page < $dropoff_total_pages): ?>
                        <button class="page-button" onclick="window.location.href='?pickup_page=<?= $pickup_page ?>&dropoff_page=<?= $dropoff_page+1 ?>'">Next</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="alert-container" id="alertContainer"></div>

    <!-- View Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Details</h3>
                <button class="close-button" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Content will be dynamically populated -->
            </div>
        </div>
    </div>

    <!-- Alert Modal -->
    <div class="alert-modal" id="alertModal">
        <div class="alert-modal-content">
            <div class="alert-modal-header">
                <i class="fas fa-check-circle success" id="alertModalIcon"></i>
            </div>
            <div class="alert-modal-body">
                <span id="alertModalMessage"></span>
            </div>
            <div class="alert-modal-footer">
                <button class="alert-modal-button confirm" id="alertModalConfirm">OK</button>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="alert-modal" id="confirmModal">
        <div class="alert-modal-content">
            <div class="alert-modal-header">
                <i class="fas fa-question-circle" style="color: #4A90E2;"></i>
            </div>
            <div class="alert-modal-body">
                <span id="confirmModalMessage"></span>
            </div>
            <div class="alert-modal-footer">
                <button class="alert-modal-button confirm" id="confirmModalOk">OK</button>
                <button class="alert-modal-button cancel" id="confirmModalCancel">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Tab switching logic
        const pickupTab = document.getElementById('pickupTab');
        const dropoffTab = document.getElementById('dropoffTab');
        const pickupContent = document.getElementById('pickupContent');
        const dropoffContent = document.getElementById('dropoffContent');
        const checkValidityBtn = document.getElementById('checkValidityBtn');

        pickupTab.addEventListener('click', function() {
            pickupTab.classList.add('active');
            dropoffTab.classList.remove('active');
            pickupContent.style.display = '';
            dropoffContent.style.display = 'none';
            checkValidityBtn.style.display = 'none';
        });

        dropoffTab.addEventListener('click', function() {
            dropoffTab.classList.add('active');
            pickupTab.classList.remove('active');
            dropoffContent.style.display = '';
            pickupContent.style.display = 'none';
            checkValidityBtn.style.display = 'block';
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            // Pickup
            document.querySelectorAll('#pickupTableBody tr').forEach(row => {
                const id = row.cells[0].textContent.toLowerCase();
                const name = row.cells[1].textContent.toLowerCase();
                const email = row.cells[2].textContent.toLowerCase();
                const status = row.cells[3].textContent.toLowerCase();
                row.style.display = id.includes(searchTerm) || name.includes(searchTerm) || email.includes(searchTerm) || status.includes(searchTerm) ? '' : 'none';
            });
            // Dropoff
            document.querySelectorAll('#dropoffTableBody tr').forEach(row => {
                const id = row.cells[0].textContent.toLowerCase();
                const name = row.cells[1].textContent.toLowerCase();
                const email = row.cells[2].textContent.toLowerCase();
                const status = row.cells[3].textContent.toLowerCase();
                row.style.display = id.includes(searchTerm) || name.includes(searchTerm) || email.includes(searchTerm) || status.includes(searchTerm) ? '' : 'none';
            });
        });
        function showAlert(message, type = 'success') {
            const modal = document.getElementById('alertModal');
            const icon = document.getElementById('alertModalIcon');
            const messageSpan = document.getElementById('alertModalMessage');
            const confirmButton = document.getElementById('alertModalConfirm');

            // Set icon and color
            icon.className = `fas fa-${type === 'success' ? 'check-circle success' : 'exclamation-circle error'}`;
            messageSpan.textContent = message;

            // Show modal
            modal.style.display = 'flex';
            modal.offsetHeight; // Force reflow
            modal.classList.add('show');

            // Handle confirm button click
            confirmButton.onclick = function() {
                closeAlertModal();
            };
        }
        function viewDetails(type, id) {
            const modal = document.getElementById('viewModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = modal.querySelector('.modal-body');
            modal.style.display = 'flex';
            modal.offsetHeight;
            modal.classList.add('show');
            modalTitle.textContent = type === 'pickup' ? 'Pickup Details' : 'Dropoff Details';
            // Fetch details via AJAX (implement as needed)
            modalBody.innerHTML = `<div style='text-align:center;padding:40px;'>Details for <b>${type === 'pickup' ? 'Delivery' : 'Dropoff'} ID:</b> ${id}</div>`;
        }
        function closeModal() {
            const modal = document.getElementById('viewModal');
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
        window.onclick = function(event) {
            const modal = document.getElementById('viewModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        function closeAlertModal() {
            const modal = document.getElementById('alertModal');
            const modalContent = modal.querySelector('.alert-modal-content');
            
            modalContent.style.transform = 'scale(0.7)';
            modalContent.style.opacity = '0';
            
            setTimeout(() => {
                modal.classList.remove('show');
                modal.style.display = 'none';
                modalContent.style.transform = '';
                modalContent.style.opacity = '';
            }, 300);
        }
        // Payment dropdown logic
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
        function updateDropoffStatusColor(select) {
            // Remove both classes first
            select.classList.remove('done', 'pending');
            if (select.value === '1') {
                select.classList.add('done');
            } else {
                select.classList.add('pending');
            }
        }
        document.querySelectorAll('.dropoff-status-dropdown').forEach(function(select) {
            updateDropoffStatusColor(select);
            let previousValue = select.value;
            select.addEventListener('change', async function() {
                const dropoffId = this.getAttribute('data-dropoff-id');
                const quotationId = this.getAttribute('data-quotation-id');
                const newStatus = this.value;
                if (newStatus === '0') {
                    // Prevent reverting from Done to Pending
                    showAlert('Cannot revert status from Done to Pending.', 'error');
                    this.value = previousValue;
                    updateDropoffStatusColor(this);
                    return;
                }
                let confirmMsg = 'Mark this dropoff as Done? This will also mark the related quotation as Completed and the status cannot be edited anymore.';
                const confirmed = await showConfirm(confirmMsg);
                if (!confirmed) {
                    this.value = previousValue;
                    updateDropoffStatusColor(this);
                    return;
                }
                updateDropoffStatusColor(this);
                fetch('deliverymanagement.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_dropoff_status',
                        dropoff_id: dropoffId,
                        quotation_id: quotationId,
                        status: newStatus
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        previousValue = newStatus;
                        showAlert('Status updated successfully!', 'success');
                    } else {
                        showAlert(data.message || 'Failed to update status.', 'error');
                        this.value = previousValue;
                        updateDropoffStatusColor(this);
                    }
                })
                .catch(() => {
                    showAlert('Failed to update status.', 'error');
                    this.value = previousValue;
                    updateDropoffStatusColor(this);
                });
            });
            select.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent modal from opening when clicking dropdown
            });
        });
        document.querySelectorAll('#pickupTableBody tr').forEach(row => {
            const statusCell = row.cells[3];
            // Get the current status integer from the badge class
            let badge = statusCell.querySelector('.status-badge');
            let statusInt = 1;
            if (badge) {
                if (badge.classList.contains('status-assigning')) statusInt = 1;
                else if (badge.classList.contains('status-assigned')) statusInt = 2;
                else if (badge.classList.contains('status-pickedup')) statusInt = 3;
                else if (badge.classList.contains('status-received')) statusInt = 4;
            }
            const deliveryId = row.getAttribute('data-id');
            const statusLabels = {
                1: 'Assigning Driver',
                2: 'Driver Assigned',
                3: 'Picked Up',
                4: 'Received'
            };
            if (statusInt < 4) {
                // Replace badge with dropdown
                statusCell.innerHTML = `<select class='pickup-status-dropdown status-${statusInt}'>
                    <option value='${statusInt}' selected>${statusLabels[statusInt]}</option>
                    <option value='${statusInt + 1}'>${statusLabels[statusInt + 1]}</option>
                </select>`;
                const select = statusCell.querySelector('.pickup-status-dropdown');
                select.addEventListener('change', async function() {
                    const newStatus = parseInt(this.value);
                    if (newStatus === statusInt + 1) {
                        const confirmed = await showConfirm(`Change status to '${statusLabels[newStatus]}'? This cannot be undone.`);
                        if (confirmed) {
                            fetch('deliverymanagement.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    action: 'update_pickup_status',
                                    delivery_id: deliveryId,
                                    status: newStatus
                                })
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data.success) {
                                    showAlert('Status updated successfully!', 'success');
                                    setTimeout(() => { location.reload(); }, 1200);
                                } else {
                                    showAlert(data.message || 'Failed to update status.', 'error');
                                    this.value = statusInt;
                                }
                            })
                            .catch(() => {
                                showAlert('Failed to update status.', 'error');
                                this.value = statusInt;
                            });
                        } else {
                            this.value = statusInt;
                        }
                    } else {
                        this.value = statusInt;
                    }
                });
            }
        });
        // Custom confirmation modal function
        function showConfirm(message) {
            return new Promise((resolve) => {
                const modal = document.getElementById('confirmModal');
                const messageSpan = document.getElementById('confirmModalMessage');
                const okBtn = document.getElementById('confirmModalOk');
                const cancelBtn = document.getElementById('confirmModalCancel');
                messageSpan.textContent = message;
                modal.style.display = 'flex';
                modal.offsetHeight;
                modal.classList.add('show');
                function cleanup(result) {
                    modal.classList.remove('show');
                    setTimeout(() => { modal.style.display = 'none'; }, 300);
                    okBtn.onclick = null;
                    cancelBtn.onclick = null;
                    window.onclick = null;
                    resolve(result);
                }
                okBtn.onclick = function() { cleanup(true); };
                cancelBtn.onclick = function() { cleanup(false); };
                window.onclick = function(event) {
                    if (event.target === modal) cleanup(false);
                };
            });
        }
        // Add validity check function
        function checkDropoffValidity() {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            let expiredCount = 0;
            let promises = [];

            document.querySelectorAll('#dropoffTableBody tr').forEach(row => {
                const dropoffDate = new Date(row.cells[4].textContent.split('/').reverse().join('-'));
                const statusCell = row.cells[3];
                const dropoffId = row.getAttribute('data-id');
                const quotationId = row.cells[5].textContent;

                if (dropoffDate < today && !statusCell.querySelector('.status-badge.status-expired')) {
                    expiredCount++;
                    // Update status to expired
                    promises.push(
                        fetch('deliverymanagement.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'update_dropoff_status',
                                dropoff_id: dropoffId,
                                quotation_id: quotationId,
                                status: 2
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                statusCell.innerHTML = '<span class="status-badge status-expired">Expired</span>';
                            } else {
                                throw new Error(data.message || 'Failed to update status');
                            }
                        })
                    );
                }
            });

            // Wait for all updates to complete
            Promise.all(promises)
                .then(() => {
                    if (expiredCount > 0) {
                        // Show notification in the alert container
                        const alertContainer = document.getElementById('alertContainer');
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-info';
                        alert.innerHTML = `
                            <i class="fas fa-info-circle"></i>
                            <span>${expiredCount} dropoff${expiredCount > 1 ? 's' : ''} ${expiredCount > 1 ? 'have' : 'has'} expired and been marked as expired.</span>
                        `;
                        alertContainer.appendChild(alert);

                        // Remove the alert after 3 seconds
                        setTimeout(() => {
                            alert.remove();
                        }, 3000);
                    } else {
                        // Show notification if no dropoffs expired
                        const alertContainer = document.getElementById('alertContainer');
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-info';
                        alert.innerHTML = `
                            <i class="fas fa-info-circle"></i>
                            <span>No dropoffs have expired.</span>
                        `;
                        alertContainer.appendChild(alert);

                        // Remove the alert after 3 seconds
                        setTimeout(() => {
                            alert.remove();
                        }, 3000);
                    }
                })
                .catch(error => {
                    showAlert(error.message || 'Failed to update status.', 'error');
                });
        }

        // Add click event listener for validity check button
        checkValidityBtn.addEventListener('click', checkDropoffValidity);

        // Initial validity check when dropoff tab is shown
        dropoffTab.addEventListener('click', checkDropoffValidity);

        // Add est_arrival update functionality
        document.querySelectorAll('.edit-est-arrival-btn').forEach(button => {
            button.addEventListener('click', function() {
                const container = this.closest('.est-arrival-container');
                const display = container.querySelector('.est-arrival-display');
                const deliveryId = this.getAttribute('data-delivery-id');
                const currentDate = display.textContent !== '-' ? display.textContent.split('/').reverse().join('-') : '';
                
                // Create input element
                const input = document.createElement('input');
                input.type = 'date';
                input.className = 'est-arrival-input';
                input.value = currentDate;
                
                // Replace display with input
                display.style.display = 'none';
                container.insertBefore(input, this);
                
                // Focus input
                input.focus();
                
                // Handle input changes
                const handleInput = async () => {
                    const newDate = input.value;
                    if (newDate) {
                        try {
                            const response = await fetch('deliverymanagement.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    action: 'update_est_arrival',
                                    delivery_id: deliveryId,
                                    est_arrival: newDate
                                })
                            });
                            
                            const data = await response.json();
                            if (data.success) {
                                display.textContent = new Date(newDate).toLocaleDateString('en-GB');
                                showAlert('Estimated arrival date updated successfully!', 'success');
                            } else {
                                throw new Error(data.message || 'Failed to update date');
                            }
                        } catch (error) {
                            showAlert(error.message || 'Failed to update date', 'error');
                        }
                    }
                    
                    // Restore display
                    input.remove();
                    display.style.display = '';
                };
                
                // Handle input events
                input.addEventListener('blur', handleInput);
                input.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        handleInput();
                    }
                });
            });
        });
    </script>
</body>
</html>

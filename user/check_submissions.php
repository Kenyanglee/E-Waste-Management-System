<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "nothing_wasted";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all submissions
    $stmt = $conn->prepare("SELECT submission_id, user_id FROM submission");
    $stmt->execute();
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "All submissions in database:\n";
    print_r($submissions);
    
    // Get the structure of the submission table
    $stmt = $conn->prepare("DESCRIBE submission");
    $stmt->execute();
    $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nTable structure:\n";
    print_r($structure);
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?> 
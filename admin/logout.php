<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to user login page
header('Location: ../user/auth.php');
exit();
?> 
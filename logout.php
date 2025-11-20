<?php
session_start();

// Destroy all session data
$_SESSION = array();
session_destroy();

// Redirect to home page with message
header("Location: ../index.php?logout=success");
exit();
?>
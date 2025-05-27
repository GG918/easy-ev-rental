<?php
include_once 'auth.php';

// Process based on request type
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // API request returns JSON
    $result = $auth->logout();
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
} else {
    // Regular request redirects
    $auth->logout();
    header('Location: index.php?logout=true');
    exit;
}
?>

<?php
// Handle login request page

// Load authentication functions and utility functions
require_once 'auth.php';
require_once '../includes/utils.php';

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST data
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $return_url = $_POST['return_url'] ?? url('index.php');
    
    // Attempt login
    $result = login($username, $password);
    
    // If AJAX request
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
    
    // Regular form submission
    if ($result['success']) {
        // Login successful, redirect
        header("Location: $return_url");
        exit;
    } else {
        // Login failed, return error
        $error = urlencode($result['message']);
        $username = urlencode($username);
        // Use url() function for redirection path
        $login_page_url = url('index', ['show_login' => 1, 'login_error' => $error, 'username' => $username, 'return_url' => $return_url]);
        header("Location: " . $login_page_url);
        exit;
    }
} else {
    // Non-POST request, redirect to homepage
    // Use url() function for redirection path
    header('Location: ' . url('index'));
    exit;
} 
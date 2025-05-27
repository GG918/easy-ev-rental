<?php
// Handle registration request page

// Load authentication functions
require_once 'auth.php';
require_once '../includes/utils.php';

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get POST data
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Attempt registration
    $result = register($username, $email, $password);
    
    // If AJAX request
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
    
    // Regular form submission
    if ($result['success']) {
        // Registration successful, redirect to homepage with success message
        header('Location: ' . url('index', ['register_success' => 1]));
        exit;
    } else {
        // Registration failed, return error
        $error = urlencode($result['message']);
        $username = urlencode($username);
        $email = urlencode($email);
        $register_page_url = url('index', ['register_error' => $error, 'username' => $username, 'email' => $email]);
        header("Location: " . $register_page_url);
        exit;
    }
} else {
    // Non-POST request, redirect to homepage
    header('Location: ' . url('index'));
    exit;
} 
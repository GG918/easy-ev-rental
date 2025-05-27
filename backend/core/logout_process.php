<?php
// Handle logout request page

// Load authentication functions and utility functions
require_once 'auth.php';
require_once '../includes/utils.php';

// Execute logout
$result = logout();

// Redirect to homepage
// Use url() function for redirection path
header('Location: ' . url('index', ['logout' => 'true']));
exit; 
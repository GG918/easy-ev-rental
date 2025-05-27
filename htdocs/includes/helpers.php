<?php
/**
 * Common helper functions
 */

/**
 * Format date to a human-readable string
 * @param string $dateStr - Date string
 * @param string $format - Format string
 * @return string - Formatted date
 */
function formatDate($dateStr, $format = 'Y-m-d H:i:s') {
    try {
        $date = new DateTime($dateStr);
        return $date->format($format);
    } catch (Exception $e) {
        return $dateStr;
    }
}

/**
 * JSON response helper
 * @param array $data - Response data
 * @param int $status - HTTP status code
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Return error response
 * @param string $message - Error message
 * @param int $status - HTTP status code
 */
function errorResponse($message, $status = 400) {
    jsonResponse(['success' => false, 'message' => $message], $status);
}

/**
 * Return success response
 * @param array $data - Response data
 * @param string $message - Success message
 */
function successResponse($data = [], $message = 'Operation successful') {
    $response = ['success' => true, 'message' => $message];
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    jsonResponse($response);
}

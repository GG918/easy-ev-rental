<?php
/**
 * Common helper functions
 * @license MIT
 */

function formatDate($dateStr, $format = 'Y-m-d H:i:s') {
    try {
        $date = new DateTime($dateStr);
        return $date->format($format);
    } catch (Exception $e) {
        return $dateStr;
    }
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function errorResponse($message, $status = 400) {
    jsonResponse(['success' => false, 'message' => $message], $status);
}

function successResponse($data = [], $message = 'Operation successful') {
    $response = ['success' => true, 'message' => $message];
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    jsonResponse($response);
}

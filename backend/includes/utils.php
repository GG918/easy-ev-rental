<?php
/**
 * Utility function library, provides basic path retrieval and other functions
 */

/**
 * Get the application's base path
 * Automatically detects environment and adapts paths
 * 
 * @return string Base path with leading slash, returns empty string if in root directory
 */
function get_base_path() {
    // Try to get base path from different environment variables
    
    // 1. First try to determine from PHP_SELF
    $path = dirname($_SERVER['PHP_SELF']);
    
    // 2. Or determine from SCRIPT_NAME 
    if ($path == '/' || empty($path)) {
        $path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    }
    
    // 3. If still root directory, try to get from REQUEST_URI
    if ($path == '/' || empty($path)) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path_parts = explode('?', $uri);
        $path = dirname($path_parts[0]);
    }
        
    // If all above failed, check current executing script path
    if ($path == '/' || empty($path)) {
        $current_file = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        
        if (!empty($document_root) && !empty($current_file) && strpos($current_file, $document_root) === 0) {
            $relative_path = dirname(substr($current_file, strlen($document_root)));
            if ($relative_path != '/' && !empty($relative_path)) {
                $path = $relative_path;
            }
        }
    }
    
    // Clean and format path
    $path = rtrim($path, '/');
    
    // If root path, return empty string
    if ($path == '/' || empty($path)) {
        return '';
    }

    return $path;
}

/**
 * Generate URL with correct base path
 * 
 * @param string $path Path relative to application root directory
 * @param array $query_params Optional query parameters as an associative array
 * @return string Complete URL path
 */
function url($path, $query_params = []) {
    $base = get_base_path();
    $path = ltrim($path, '/');
    $url = $base . '/' . $path;
    
    if (!empty($query_params)) {
        $url .= '?' . http_build_query($query_params);
    }
    
    return $url;
}
?> 
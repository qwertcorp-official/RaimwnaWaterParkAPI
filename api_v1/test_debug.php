<?php
// Create this file as test_data.php in your API root directory

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log everything about the request
error_log("=== TEST DATA ENDPOINT ===");
error_log("METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
error_log("HTTP_CONTENT_TYPE: " . ($_SERVER['HTTP_CONTENT_TYPE'] ?? 'not set'));

// Get all headers
$headers = getallheaders();
error_log("HEADERS: " . print_r($headers, true));

// Get raw input
$input = file_get_contents("php://input");
error_log("RAW_INPUT: " . $input);
error_log("INPUT_LENGTH: " . strlen($input));

// Try to parse JSON
$data = json_decode($input, true);
error_log("JSON_DECODE_SUCCESS: " . ($data ? 'YES' : 'NO'));
error_log("JSON_LAST_ERROR: " . json_last_error());
error_log("JSON_LAST_ERROR_MSG: " . json_last_error_msg());

if ($data) {
    error_log("PARSED_DATA: " . print_r($data, true));
}

// Get $_POST data (if any)
if (!empty($_POST)) {
    error_log("POST_DATA: " . print_r($_POST, true));
}

// Get $_GET data (if any)
if (!empty($_GET)) {
    error_log("GET_DATA: " . print_r($_GET, true));
}

// Response
echo json_encode([
    'success' => true,
    'message' => 'Test endpoint working',
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'headers' => $headers,
    'raw_input' => $input,
    'raw_input_length' => strlen($input),
    'json_decode_success' => $data ? true : false,
    'json_last_error' => json_last_error(),
    'json_last_error_msg' => json_last_error_msg(),
    'parsed_data' => $data,
    'post_data' => $_POST,
    'get_data' => $_GET,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
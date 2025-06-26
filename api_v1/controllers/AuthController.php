<?php
require_once 'models/User.php';
require_once 'middleware/JWTMiddleware.php';

class AuthController {
    private $user;

    public function __construct() {
        try {
            $this->user = new User();
        } catch (Exception $e) {
            error_log("AuthController constructor error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed'
            ]);
            exit();
        }
    }

    public function register() {
        // DEBUG: Log everything we can about the request
        error_log("=== REGISTER DEBUG START ===");
        error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        
        try {
            // Get input data
            $input = file_get_contents("php://input");
            
            // DEBUG: Log the raw input
            error_log("RAW INPUT: " . $input);
            error_log("INPUT LENGTH: " . strlen($input));
            
            if (empty($input)) {
                error_log("ERROR: Empty input received");
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'No data received'
                ]);
                return;
            }
            
            // Try to decode JSON
            $data = json_decode($input, true);
            
            // DEBUG: Log JSON decode result
            error_log("JSON_DECODE_RESULT: " . ($data ? 'SUCCESS' : 'FAILED'));
            error_log("JSON_LAST_ERROR: " . json_last_error());
            error_log("JSON_LAST_ERROR_MSG: " . json_last_error_msg());
            
            if ($data) {
                error_log("DECODED_DATA: " . print_r($data, true));
            }
            
            // Check if JSON decode worked
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode failed with error: " . json_last_error_msg());
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid JSON format: ' . json_last_error_msg(),
                    'raw_input' => substr($input, 0, 200), // First 200 chars for debugging
                    'error_code' => json_last_error()
                ]);
                return;
            }
            
            // Validate required fields
            error_log("VALIDATING FIELDS...");
            
            if (!isset($data['email'])) {
                error_log("ERROR: Missing email field");
            }
            if (!isset($data['password'])) {
                error_log("ERROR: Missing password field");
            }
            if (!isset($data['name'])) {
                error_log("ERROR: Missing name field");
            }
            
            if (!isset($data['email']) || !isset($data['password']) || !isset($data['name'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields: email, password, name',
                    'received_fields' => array_keys($data),
                    'data_received' => $data
                ]);
                return;
            }

            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                error_log("ERROR: Invalid email format: " . $data['email']);
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid email format'
                ]);
                return;
            }

            // Check if email already exists
            error_log("CHECKING IF EMAIL EXISTS: " . $data['email']);
            if ($this->user->emailExists($data['email'])) {
                error_log("ERROR: Email already exists: " . $data['email']);
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'Email already exists'
                ]);
                return;
            }

            // Register user
            error_log("ATTEMPTING TO REGISTER USER...");
            $user_id = $this->user->register(
                $data['email'], 
                $data['password'], 
                trim($data['name']), 
                isset($data['phone']) ? trim($data['phone']) : null
            );

            error_log("REGISTER RESULT: " . ($user_id ? "SUCCESS (ID: $user_id)" : "FAILED"));

            if ($user_id) {
                // Get the complete user data
                $user_data = $this->user->getUserById($user_id);
                
                if ($user_data) {
                    // Format user data for response
                    $formatted_user = $this->user->formatUserData($user_data);
                    
                    // Generate JWT token
                    $token = JWTMiddleware::generateToken($formatted_user);

                    error_log("SUCCESS: User registered with token generated");

                    http_response_code(201);
                    echo json_encode([
                        'success' => true,
                        'message' => 'User registered successfully',
                        'token' => $token,
                        'user' => $formatted_user
                    ]);
                } else {
                    error_log("ERROR: Could not retrieve user data after registration");
                    http_response_code(500);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Registration failed - could not retrieve user data'
                    ]);
                }
            } else {
                error_log("ERROR: Registration failed in User model");
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Registration failed'
                ]);
            }

        } catch (Exception $e) {
            error_log("EXCEPTION in register: " . $e->getMessage());
            error_log("STACK TRACE: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ]);
        }
        
        error_log("=== REGISTER DEBUG END ===");
    }

    public function login() {
        // DEBUG: Log everything about login request
        error_log("=== LOGIN DEBUG START ===");
        error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
        error_log("CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
        
        try {
            // Get input data
            $input = file_get_contents("php://input");
            
            // DEBUG: Log the raw input
            error_log("RAW LOGIN INPUT: " . $input);
            error_log("LOGIN INPUT LENGTH: " . strlen($input));
            
            if (empty($input)) {
                error_log("ERROR: Empty login input received");
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'No data received'
                ]);
                return;
            }
            
            $data = json_decode($input, true);
            
            // DEBUG: Log JSON decode result
            error_log("LOGIN JSON_DECODE_RESULT: " . ($data ? 'SUCCESS' : 'FAILED'));
            error_log("LOGIN JSON_LAST_ERROR: " . json_last_error());
            
            if ($data) {
                error_log("LOGIN DECODED_DATA: " . print_r($data, true));
            }
            
            // Check if JSON decode worked
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Login JSON decode failed: " . json_last_error_msg());
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid JSON format: ' . json_last_error_msg(),
                    'raw_input' => substr($input, 0, 200)
                ]);
                return;
            }
            
            // Validate required fields
            if (!isset($data['email']) || !isset($data['password'])) {
                error_log("ERROR: Missing login fields");
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Email and password are required',
                    'received_fields' => array_keys($data)
                ]);
                return;
            }

            // Attempt login
            error_log("ATTEMPTING LOGIN FOR: " . $data['email']);
            $user_data = $this->user->login($data['email'], $data['password']);

            if ($user_data) {
                // Format user data for response
                $formatted_user = $this->user->formatUserData($user_data);
                
                // Generate JWT token
                $token = JWTMiddleware::generateToken($formatted_user);

                error_log("LOGIN SUCCESS for: " . $data['email']);

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'token' => $token,
                    'user' => $formatted_user
                ]);
            } else {
                error_log("LOGIN FAILED for: " . $data['email']);
                
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid email or password'
                ]);
            }

        } catch (Exception $e) {
            error_log("EXCEPTION in login: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ]);
        }
        
        error_log("=== LOGIN DEBUG END ===");
    }
}
?>
<?php
require_once 'models/User.php';
require_once 'middleware/JWTMiddleware.php';

class UserController {
    private $user;

    public function __construct() {
        try {
            $this->user = new User();
        } catch (Exception $e) {
            error_log("UserController constructor error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed'
            ]);
            exit();
        }
    }

    public function getProfile() {
        try {
            // Verify JWT token
            $user_data = JWTMiddleware::verifyToken();
            
            if (!$user_data) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized'
                ]);
                return;
            }

            // Get fresh user data
            $fresh_user_data = $this->user->getUserById($user_data->id);
            
            if ($fresh_user_data) {
                $formatted_user = $this->user->formatUserData($fresh_user_data);
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Profile retrieved successfully',
                    'user' => $formatted_user
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'User not found'
                ]);
            }

        } catch (Exception $e) {
            error_log("Get profile error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error'
            ]);
        }
    }

    public function updateProfile() {
        try {
            // Verify JWT token
            $user_data = JWTMiddleware::verifyToken();
            
            if (!$user_data) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized'
                ]);
                return;
            }

            $input = file_get_contents("php://input");
            $data = json_decode($input, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid JSON data'
                ]);
                return;
            }
            
            // Update profile
            $update_success = $this->user->updateProfile($user_data->id, $data);
            
            if ($update_success) {
                $updated_user_data = $this->user->getUserById($user_data->id);
                $formatted_user = $this->user->formatUserData($updated_user_data);
                
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Profile updated successfully',
                    'user' => $formatted_user
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to update profile'
                ]);
            }

        } catch (Exception $e) {
            error_log("Update profile error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error'
            ]);
        }
    }
}
?>
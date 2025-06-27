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

    public function userlist(){
        /*
        // pass this parameters for the condition only, on seach
        // To get the user list no need to pass these parameter without parameters also works
        {
            "conditions" : {"name" : "Malen Basumatary","email" : "null","phone" : "null","is_active":"null","role":"null"},
            "order-key" : "name",
            "order-by" : "ASC"
        }
        */
        
        try{
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
            $input = json_decode($input, true);

            $getusers = $this->user->getAllUsers($input);
            if($getusers && is_array($getusers)){
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'status' => 'success',
                    'message' => 'Users Lists Found',
                    'data' => $getusers
                ]);
            }else{
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'No users found',
                    'data' => []
                ]);
            }
        } catch (Exception $e) {
            error_log("Get Users Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error'
            ]);
        }
    }

    public function useradd(){
        //  {"name": "asdfafd","email": "asdfsafasdf","roles":"Ticket Scanner"}
        try{

            $inp_val = [
                "name" => "User Name",
                "email" => "Email",
                "password" => "User Password",
                "role" => "User Role"
            ];

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
            $input = json_decode($input, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid JSON data'
                ]);
                return;
            }

            $sanitized = [];

            foreach ($inp_val as $key => $label) {
                if (empty($input[$key])) {
                    $errors[] = "$label is required.";
                }else {
                    $sanitized[$key] =  trim(strip_tags($input[$key]));  
                }
            }

            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "A valid email is required.";
            }

            if (!empty($errors)) {
                http_response_code(422);
                error_log("❗ " .  json_encode($errors));
                echo json_encode([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $errors
                ]);
                return;
            }

            $addUser = $this->user->userAdd($sanitized);

            // print_r($addUser);

            echo json_encode($addUser);
            // return;

        }catch (Exception $e) {
            error_log("A Users Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error'
            ]);
        }
    }
}
?>
<?php
require_once 'models/User.php';
require_once 'middleware/JWTMiddleware.php';

class UserController
{
    private $user;

    public function __construct()
    {
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

    public function getProfile()
    {
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

    public function updateProfile()
    {
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

    public function userlist()
    {
        /*
        // pass this parameters for the condition only, on seach
        // To get the user list no need to pass these parameter without parameters also works
        {
            "conditions" : {"name" : "Malen Basumatary","email" : "null","phone" : "null","is_active":"null","role":"null"},
            "order-key" : "name",
            "order-by" : "ASC"
        }
        */

        try {
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
            if ($getusers && is_array($getusers)) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'status' => 'success',
                    'message' => 'Users Lists Found',
                    'data' => $getusers
                ]);
            } else {
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

    public function useradd()
    {
        //  accespts this parameters must proviide these keys
        //  {"name": "asdfafd","email": "asdfsafasdf","roles":"Ticket Scanner"}
        try {

            $inp_val = [
                "name" => "User Name",
                "email" => "Email", 
                "roles" => "User Role"
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
            print_r($input);
            // die();
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid JSON data'
                ]);
                return;
            }
             
            $input = filter_var_array($input, [
                "email" =>  FILTER_VALIDATE_EMAIL ,
                "name" => ["filter"=>FILTER_CALLBACK, "options"=> function ($n) {
                    $n = strip_tags(trim(preg_replace('/\s{2,}/', ' ', $n)));
                    return empty($n) ? false : $n;
                }],
                "roles" => [FILTER_DEFAULT]
                
                // [FILTER_CALLBACK, function ($roles) { 
                //     echo "roles";
                //     print_r($roles);
                //     return $roles;
                //     // $roles = array_map(function ($r) {
                //     //     $r = trim(preg_replace('/\s{2,}/', ' ', $r));
                //     //     return $r;
                //     // }, $roles);
                //     // print_r($roles);if (array_filter($roles, function ($r) {
                //     //     return empty($r);
                //     // })) return "";
                //     // return json_encode($roles);
                // } ]
            ]); 

print_r($input);
die();

            // $sanitized = [];

            foreach ($inp_val as $key => $label) {
                if (empty($input[$key])) {
                    $errors[] = "$label is required.";
                }  
            }

            // if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            //     $errors[] = "A valid email is required.";
            // }

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

            $addUser = $this->user->userAdd($input);

            // print_r($addUser);

            echo json_encode($addUser);
            // return;

        } catch (Exception $e) {
            error_log("A Users Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error'
            ]);
        }
    }
    /*public function userupdate() {
        // Accepts: {"update_id": "1", "update": {"name": "Updated Name", "email": "updated@example.com", "password":"ajlsdfnlsdjfwoeh", "role": "Admin"}}
        try {
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

            if (!isset($input['update_id']) || !is_numeric($input['update_id']) || intval($input['update_id']) <= 0) {
                http_response_code(422);
                error_log("❗ A valid update ID is required on user update.");
                echo json_encode([
                    "success" => false,
                    "message" => "A valid update ID is required.",
                ]);
                return;
            } else {
                $sanitized['id'] = intval($input['update_id']);
            }

            if (!isset($input['update']) || empty($input['update'])) {

                http_response_code(422);
                error_log("❗ 'update' key not found or empty .");
                echo json_encode([
                    "success" => false,
                    "message" => "'update' key not found or empty .",
                ]);
                return;
            }
            if(!is_array($input['update'])){
                $errors[] = "Update data is required and must be an object.";
            }

            if (!empty($errors)) {
                http_response_code(422);
                error_log("❗ " . json_encode($errors));
                echo json_encode([
                    "success" => false,
                    "message" => "Validation failed",
                    "errors" => $errors
                ]);
                return;
            }

            $updateKey = $input['update'];

            if(isset($updateKey["name"])){
                if(empty($updateKey["name"])){
                    error_log("User Update failed (Name is Empty) ");
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Name should not be empty'
                    ]);
                    return;
                }
                $sanitized['name'] = trim(strip_tags($updateKey["name"]));
            }

            if(isset($updateKey["password"])){
                if(empty($updateKey["password"])){
                    error_log("User Update failed (password is Empty) ");
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'password should not be empty'
                    ]);
                    return;
                }
                $sanitized['password'] = md5(trim(strip_tags($updateKey["password"])));
            }

            if (isset($updateKey["email"])) {
                if (empty(trim($updateKey["email"]))) {
                    error_log("User Update failed (Email is Empty)");
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Email should not be empty'
                    ]);
                    return;
                }

                $cleanEmail = trim(strip_tags($updateKey["email"]));

                if (!filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
                    error_log("User Update failed (Email is invalid)");
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid email format'
                    ]);
                    return;
                }

                $sanitized['email'] = $cleanEmail;
            }

            if(isset($updateKey["role"])){
                if(empty($updateKey["role"])){
                    error_log("User Update failed (role is Empty) ");
                    http_response_code(422);
                    echo json_encode([
                        'success' => false,
                        'message' => 'role should not be empty'
                    ]);
                    return;
                }
                $sanitized['role'] = strtolower(trim(strip_tags($updateKey["role"])));
            }

            $updateUser = $this->user->userUpdate($sanitized);

            echo json_encode($updateUser);

        } catch (Exception $e) {
            error_log("User Update Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error'
            ]);
        }
    }*/

    public function userupdate()
    {
        // Accepts: {"update_id": "1", "update": {"name": "Updated Name", "email": "updated@example.com", "password":"ajlsdfnlsdjfwoeh", "role": "Admin"}}
        // for roll pass these values : 'user', 'admin', 'ticket scanner' or sql error may come
        try {
            $user_data = JWTMiddleware::verifyToken();
            if (!$user_data) {
                http_response_code(401);
                echo json_encode(value: ['success' => false, 'message' => 'Unauthorized']);
                return;
            }

            $input = json_decode(file_get_contents("php://input"), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
                return;
            }

            if (!isset($input['update_id']) || empty($input['update_id']) || !is_numeric($input['update_id'])) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'A valid update ID is required.']);
                return;
            }

            if (empty($input['update']) || !is_array($input['update'])) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => "'update' must be a non-empty object."]);
                return;
            }

            $allowedFields = ['name', 'email', 'password', 'role'];
            $update = $input['update'];
            $invalidKeys = array_diff(array_keys($update), $allowedFields);

            if (!empty($invalidKeys)) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid fields provided: ' . implode(', ', $invalidKeys)
                ]);
                return;
            }

            $sanitized = ['id' => intval($input['update_id'])];

            foreach ($allowedFields as $key) {
                if (isset($update[$key])) {
                    $value = trim(strip_tags($update[$key]));
                    if ($value === '') {
                        http_response_code(422);
                        echo json_encode(['success' => false, 'message' => ucfirst($key) . ' should not be empty']);
                        return;
                    }

                    if ($key === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        http_response_code(422);
                        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
                        return;
                    }

                    $sanitized[$key] = ($key === 'password') ? md5($value) : strtolower($value);
                }
            }

            $result = $this->user->userUpdate($sanitized);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("User Update Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function userdelete()
    {
        // {"delete_id" : "1"}

        try {
            $user_data = JWTMiddleware::verifyToken();
            if (!$user_data) {
                http_response_code(401);
                echo json_encode(value: ['success' => false, 'message' => 'Unauthorized']);
                return;
            }

            $input = json_decode(file_get_contents("php://input"), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
                return;
            }

            if (!isset($input['delete_id']) || empty($input['delete_id']) || !is_numeric($input['delete_id'])) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'A valid update ID is required.']);
                return;
            }

            $result = $this->user->userDelete($input['delete_id']);
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("User Delete Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function roleList()
    {
        try {
            // $user_data = JWTMiddleware::verifyToken();
            // if (!$user_data) {
            //     http_response_code(401);
            //     echo json_encode([
            //         'success' => false,
            //         'message' => 'Unauthorized'
            //     ]);
            //     return;
            // }

            // $userrole = $this->user->userRoleList();

            $userrole = [
                "success" => true,
                "status" => "success",
                "message" => "User Role List",
                "data" => []
            ];
            echo json_encode($userrole);
        } catch (Exception $e) {
            error_log("Get User Role Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error'
            ]);
        }
    }

    public function roleUpdate()
    {
        try {
            // $user_data = JWTMiddleware::verifyToken();
            // if (!$user_data) {
            //     http_response_code(401);
            //     echo json_encode([
            //         'success' => false,
            //         'message' => 'Unauthorized'
            //     ]);
            //     return;
            // }

            $result = [
                "success" => true,
                "status" => "success",
                "message" => "Role Updated Successfully",
                "data" => []
            ];
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("Update User Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error'
            ]);
        }
    }

    public function roleAdd()
    {
        try {
            // $user_data = JWTMiddleware::verifyToken();
            // if (!$user_data) {
            //     http_response_code(401);
            //     echo json_encode([
            //         'success' => false,
            //         'message' => 'Unauthorized'
            //     ]);
            //     return;
            // }

            $result = [
                "success" => true,
                "status" => "success",
                "message" => "Role Added Successfully",
                "data" => []
            ];
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("Add User Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error'
            ]);
        }
    }

    public function roleDelete()
    {
        try {
            // $user_data = JWTMiddleware::verifyToken();
            // if (!$user_data) {
            //     http_response_code(401);
            //     echo json_encode([
            //         'success' => false,
            //         'message' => 'Unauthorized'
            //     ]);
            //     return;
            // }

            $result = [
                "success" => true,
                "status" => "success",
                "message" => "Role Deleted Successfully",
                "data" => []
            ];
            echo json_encode($result);
        } catch (Exception $e) {
            error_log("Delete User Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error'
            ]);
        }
    }
}

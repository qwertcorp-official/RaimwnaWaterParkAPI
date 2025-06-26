<?php
require_once 'config/database.php';

class User {
    private $conn;
    private $table = 'users_for_rimona';

    public function __construct() {
        try {
            $database = new Database();
            $this->conn = $database->getConnection();
            
            if (!$this->conn) {
                throw new Exception("Database connection failed");
            }
        } catch (Exception $e) {
            error_log("User model constructor error: " . $e->getMessage());
            throw $e;
        }
    }

    public function register($email, $password, $name, $phone = null) {

        // echo "I am here";
        // print_r($email);
        // print_r($password);
        // print_r($name);
        // print_r($phone);
        // die();

        try {
            // Check if email already exists
            if ($this->emailExists($email)) {
                error_log("Registration failed: Email already exists - " . $email);
                return false;
            }

            $query = "INSERT INTO " . $this->table . " 
                      (email, password, name, phone, is_active, email_verified, role, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, 1, 0, 'user', NOW(), NOW())";
            
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                error_log("Prepare failed: " . $this->conn->error);
                return false;
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            if ($stmt->execute([$email, $hashed_password, $name, $phone])) {
                $user_id = $this->conn->lastInsertId();
                error_log("User registered successfully with ID: " . $user_id);
                return $user_id;
            } else {
                error_log("Execute failed: " . implode(", ", $stmt->errorInfo()));
                return false;
            }
        } catch (Exception $e) {
            error_log("Register method error: " . $e->getMessage());
            return false;
        }
    }

    public function login($email, $password) {
        try {
            $query = "SELECT id, email, password, name, phone, date_of_birth, profile_image, 
                             is_active, email_verified, role, created_at, updated_at 
                      FROM " . $this->table . " 
                      WHERE email = ?";
            
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                error_log("Login prepare failed: " . $this->conn->error);
                return false;
            }
            
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if user is active
                if ($row['is_active'] != 1) {
                    error_log("Login failed: User account is inactive - " . $email);
                    return false;
                }
                
                // Verify password
                if (password_verify($password, $row['password'])) {
                    // Remove password from returned data
                    unset($row['password']);
                    error_log("Login successful for: " . $email);
                    return $row;
                } else {
                    error_log("Login failed: Invalid password for - " . $email);
                    return false;
                }
            } else {
                error_log("Login failed: User not found - " . $email);
                return false;
            }
        } catch (Exception $e) {
            error_log("Login method error: " . $e->getMessage());
            return false;
        }
    }

    public function getUserById($id) {
        try {
            $query = "SELECT id, email, name, phone, date_of_birth, profile_image, 
                             is_active, email_verified, role, created_at, updated_at 
                      FROM " . $this->table . " 
                      WHERE id = ?";
            
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                error_log("getUserById prepare failed: " . $this->conn->error);
                return false;
            }
            
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                error_log("User not found with ID: " . $id);
                return false;
            }
        } catch (Exception $e) {
            error_log("getUserById method error: " . $e->getMessage());
            return false;
        }
    }

    public function emailExists($email) {
        try {
            $query = "SELECT id FROM " . $this->table . " WHERE email = ?";
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                error_log("emailExists prepare failed: " . $this->conn->error);
                return false;
            }
            
            $stmt->execute([$email]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("emailExists method error: " . $e->getMessage());
            return false;
        }
    }

    public function updateProfile($id, $data) {
        try {
            $updateFields = [];
            $params = [];
            
            // Build dynamic update query based on provided data
            if (isset($data['name']) && !empty(trim($data['name']))) {
                $updateFields[] = "name = ?";
                $params[] = trim($data['name']);
            }
            
            if (isset($data['phone'])) {
                $updateFields[] = "phone = ?";
                $params[] = $data['phone'];
            }
            
            if (isset($data['date_of_birth']) && !empty($data['date_of_birth'])) {
                $updateFields[] = "date_of_birth = ?";
                $params[] = $data['date_of_birth'];
            }
            
            if (isset($data['profile_image'])) {
                $updateFields[] = "profile_image = ?";
                $params[] = $data['profile_image'];
            }
            
            if (empty($updateFields)) {
                error_log("updateProfile: No fields to update for user ID: " . $id);
                return false;
            }
            
            // Always update the updated_at timestamp
            $updateFields[] = "updated_at = NOW()";
            $params[] = $id;
            
            $query = "UPDATE " . $this->table . " SET " . implode(", ", $updateFields) . " WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                error_log("updateProfile prepare failed: " . $this->conn->error);
                return false;
            }
            
            $result = $stmt->execute($params);
            
            if ($result) {
                error_log("Profile updated successfully for user ID: " . $id);
            } else {
                error_log("updateProfile execute failed: " . implode(", ", $stmt->errorInfo()));
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("updateProfile method error: " . $e->getMessage());
            return false;
        }
    }

    public function formatUserData($userData) {
        if (!$userData) {
            return null;
        }
        
        return [
            'id' => $userData['id'],
            'email' => $userData['email'],
            'name' => $userData['name'],
            'phone' => $userData['phone'],
            'date_of_birth' => $userData['date_of_birth'],
            'profile_image' => $userData['profile_image'],
            'is_active' => (int)$userData['is_active'],
            'email_verified' => (int)$userData['email_verified'],
            'role' => $userData['role'],
            'created_at' => $userData['created_at'],
            'updated_at' => $userData['updated_at']
        ];
    }

    // Additional helper methods
    public function verifyEmail($id) {
        try {
            $query = "UPDATE " . $this->table . " SET email_verified = 1, updated_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("verifyEmail method error: " . $e->getMessage());
            return false;
        }
    }

    public function changePassword($id, $newPassword) {
        try {
            $query = "UPDATE " . $this->table . " SET password = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $hashed_password = password_hash($newPassword, PASSWORD_DEFAULT);
            return $stmt->execute([$hashed_password, $id]);
        } catch (Exception $e) {
            error_log("changePassword method error: " . $e->getMessage());
            return false;
        }
    }

    public function deactivateUser($id) {
        try {
            $query = "UPDATE " . $this->table . " SET is_active = 0, updated_at = NOW() WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            error_log("deactivateUser method error: " . $e->getMessage());
            return false;
        }
    }
}
?>
<?php
require_once 'config/jwt.php';
require_once 'lib/JWT.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTMiddleware {
    
    public static function generateToken($userData) {
        try {
            error_log("Generating JWT token for user: " . $userData['email']);
            
            $issuedAt = time();
            $expirationTime = $issuedAt + JWTConfig::$expiration_time;
            
            $payload = array(
                "iss" => JWTConfig::$issuer,
                "aud" => JWTConfig::$audience,
                "iat" => $issuedAt,
                "exp" => $expirationTime,
                "id" => $userData['id'],
                "email" => $userData['email'],
                "name" => $userData['name'],
                "role" => $userData['role'] ?? 'user'
            );
            
            $token = JWT::encode($payload, JWTConfig::$secret_key, 'HS256');
            error_log("JWT token generated successfully");
            
            return $token;
            
        } catch (Exception $e) {
            error_log("JWT token generation error: " . $e->getMessage());
            throw new Exception("Failed to generate authentication token");
        }
    }
    
    public static function validateToken($token) {
        try {
            $decoded = JWT::decode($token, new Key(JWTConfig::$secret_key, 'HS256'));
            return $decoded;
        } catch (Exception $e) {
            error_log("JWT validation error: " . $e->getMessage());
            return false;
        }
    }
    
    public static function getTokenFromHeaders() {
    // Method 1: Try getallheaders()
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if ($headers) {
            foreach ($headers as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    if (preg_match('/Bearer\s+(.*)$/i', $value, $matches)) {
                        error_log("Found token via getallheaders(): " . substr($matches[1], 0, 20) . "...");
                        return $matches[1];
                    }
                }
            }
        }
    }
    
    // Method 2: Check $_SERVER variables for authorization
    $serverKeys = [
        'HTTP_AUTHORIZATION',
        'REDIRECT_HTTP_AUTHORIZATION',
        'HTTP_AUTHORIZATION_BEARER',
        'AUTHORIZATION',
        'Bearer'
    ];
    
    foreach ($serverKeys as $key) {
        if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
            $value = $_SERVER[$key];
            if (preg_match('/Bearer\s+(.*)$/i', $value, $matches)) {
                error_log("Found token via \$_SERVER['$key']: " . substr($matches[1], 0, 20) . "...");
                return $matches[1];
            }
            // Sometimes the token comes without "Bearer " prefix
            if (strlen($value) > 100 && strpos($value, '.') !== false) {
                error_log("Found token via \$_SERVER['$key'] (without Bearer): " . substr($value, 0, 20) . "...");
                return $value;
            }
        }
    }
    
    // Method 3: Manual $_SERVER parsing
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_' && stripos($name, 'AUTH') !== false) {
            if (preg_match('/Bearer\s+(.*)$/i', $value, $matches)) {
                error_log("Found token via manual parsing \$_SERVER['$name']: " . substr($matches[1], 0, 20) . "...");
                return $matches[1];
            }
        }
    }
    
    error_log("No authorization token found in any method");
    error_log("Available \$_SERVER keys with HTTP_: " . implode(', ', array_filter(array_keys($_SERVER), function($k) { return substr($k, 0, 5) === 'HTTP_'; })));
    
    return null;
}
    
    // This method is used by your UserController
    public static function verifyToken() {
        try {
            $token = self::getTokenFromHeaders();

            error_log("Token from headers: " . substr($token ?? 'null', 0, 50));
            
            if (!$token) {
                error_log("No token found in headers");
                return false;
            }
            
            $decoded = self::validateToken($token);
            
            if (!$decoded) {
                error_log("Token validation failed");
                return false;
            }
            
            return (array) $decoded;
            
        } catch (Exception $e) {
            error_log("Token verification error: " . $e->getMessage());
            return false;
        }
    }

    public static function verifyTokenAsArray() {
    $decoded = self::verifyToken();
    if (!$decoded) {
        return false;
    }
    return (array) $decoded;
    }
    
    // Alternative method for compatibility
    public static function requireAuth() {
        $userData = self::verifyToken();
        
        if (!$userData) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Access token required or invalid'
            ]);
            exit();
        }
        
        // Convert object to array for consistency
        return (array) $userData;
    }
    
    public static function requireAdmin() {
        $userData = self::requireAuth();
        
        if ($userData['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Admin access required'
            ]);
            exit();
        }
        
        return $userData;
    }
}
?>
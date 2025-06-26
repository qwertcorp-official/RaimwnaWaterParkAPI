<?php
// Include JWT library files
require_once 'lib/JWT.php';
require_once 'lib/Key.php';

class JWTConfig {
    public static $secret_key = "Ra1m0n4W4t3rP4rk2025!@#$%^&*()_+SecretKey987654321";
    public static $issuer = "raimona_water_park";
    public static $audience = "app_users";
    public static $expiration_time = 3600; // 1 hour
}
?>
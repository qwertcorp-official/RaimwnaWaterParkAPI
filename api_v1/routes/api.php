<?php
require_once 'controllers/AuthController.php';
require_once 'controllers/UserController.php';

// Initialize controllers
$authController = new AuthController();
$userController = new UserController();

// Route handling
switch ($request_uri) {
    case '/register':
        if ($request_method == 'POST') {
            $authController->register();
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
        }
        break;
        
    case '/login':
        if ($request_method == 'POST') {
            $authController->login();
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
        }
        break;
        
    case '/profile':
        if ($request_method == 'GET') {
            $userController->getProfile();
        } elseif ($request_method == 'PUT') {
            $userController->updateProfile();
        } else {
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
        }
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['message' => 'Endpoint not found']);
        break;
}
?>
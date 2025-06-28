<?php
// Enable error reporting for debugging - IMPORTANT!
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Set headers first
// header('Content-Type: application/json');
// header('Access-Control-Allow-Origin: *');
// header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
// header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

error_log("🔥 Debug Start: index.php running");

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Function to send JSON error and exit
function sendError($message, $code = 500, $debug_info = [])
{
    http_response_code($code);
    $response = [
        'success' => false,
        'message' => $message
    ];

    // Add debug info if provided
    if (!empty($debug_info)) {
        $response['debug'] = $debug_info;
    }

    echo json_encode($response);
    exit();
}

// Function to send JSON success
function sendSuccess($data)
{
    echo json_encode($data);
    exit();
}

// Log incoming request
error_log("=== NEW REQUEST ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("URI: " . $_SERVER['REQUEST_URI']);
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
error_log("Raw input: " . file_get_contents("php://input"));

// Get request URI and clean it
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Remove the script directory from the URI
$base_path = dirname($script_name);
if ($base_path !== '/') {
    $request_uri = substr($request_uri, strlen($base_path));
}

// Remove query string
$request_uri = strtok($request_uri, '?');

// Remove leading/trailing slashes and normalize
$request_uri = '/' . trim($request_uri, '/');

// Get request method
$request_method = $_SERVER['REQUEST_METHOD'];

error_log("Processed URI: $request_uri");
error_log("Method: $request_method");

// Debug endpoint - ALWAYS respond to this first
if ($request_uri == '/debug' || $request_uri == '/test') {
    sendSuccess([
        'status' => 'working',
        'message' => 'API is responding correctly',
        'request_uri' => $request_uri,
        'request_method' => $request_method,
        'script_name' => $script_name,
        'base_path' => $base_path,
        'original_uri' => $_SERVER['REQUEST_URI'],
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
    ]);
}

// Check if required files exist before including
/*
$required_files = [
    'config/database.php',
    'config/jwt.php', 
    'models/User.php',
    'controllers/AuthController.php',
    'middleware/JWTMiddleware.php'
];
*/


$required_files = [
    'config/database.php',
    'config/jwt.php',
    'models/User.php',
    'models/Admin.php',
    'controllers/AuthController.php',
    'controllers/AdminController.php',
    'controllers/UserController.php',
    'middleware/JWTMiddleware.php'
];

// Check for booking-related files
$booking_files = [
    'models/Booking.php',
    'controllers/BookingController.php'
];

$missing_files = [];
foreach ($required_files as $file) {
    if (!file_exists($file)) {
        $missing_files[] = $file;
    }
}

if (!empty($missing_files)) {
    sendError('Missing required files', 500, [
        'missing_files' => $missing_files,
        'current_directory' => getcwd(),
        'files_in_directory' => scandir('.')
    ]);
}

// Include the ESSENTIAL files only
try {
    error_log("Including required files...");

    require_once 'config/database.php';
    error_log("✓ database.php included");

    require_once 'config/jwt.php';
    error_log("✓ jwt.php included");

    require_once 'models/User.php';
    error_log("✓ User.php included");

    require_once 'controllers/AuthController.php';
    error_log("✓ AuthController.php included");

    require_once 'controllers/UserController.php';
    error_log("✓ UserController.php included");

    require_once 'controllers/AdminController.php';
    error_log("✓ AdminController.php included");

    require_once 'controllers/RoleController.php';
    error_log("✓ RoleController.php included");

    require_once 'controllers/PaymentController.php';
    error_log("✓ PaymentController.php included");

    require_once 'middleware/JWTMiddleware.php';
    error_log("✓ JWTMiddleware.php included");

    // Include booking files if they exist
    $bookingController = null;
    if (file_exists('models/Booking.php') && file_exists('controllers/BookingController.php')) {
        require_once 'models/Booking.php';
        require_once 'controllers/BookingController.php';
        error_log("✓ Booking files included");
        $bookingController = new BookingController();

        require_once 'controllers/TicketController.php';
        $ticketController = new TicketController();

        // require_once 'controllers/TicketController.php';
        // $ticketController = new TicketController();
    }

    error_log("All files included successfully");
} catch (ParseError $e) {
    error_log("Parse error in file: " . $e->getMessage());
    sendError('Syntax error in server files', 500, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    error_log("Fatal error: " . $e->getMessage());
    sendError('Fatal error in server files', 500, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Exception $e) {
    error_log("Include error: " . $e->getMessage());
    sendError('Server configuration error', 500, [
        'error' => $e->getMessage()
    ]);
}

// Test database connection
try {
    error_log("Testing database connection...");
    $database = new Database();
    $conn = $database->getConnection();
    if (!$conn) {
        throw new Exception("Failed to get database connection");
    }
    error_log("✓ Database connection successful");
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    sendError('Database connection error', 500, [
        'error' => $e->getMessage()
    ]);
}

// Initialize controllers
try {
    error_log("Initializing AuthController...");
    $authController = new AuthController();
    $adminController = new AdminController();
    $userController = new UserController();
    $roleController = new RoleController();
    $paymentController = new PaymentController();
    error_log("✓ AuthController initialized");
} catch (Exception $e) {
    error_log("Controller initialization error: " . $e->getMessage());
    sendError('Controller initialization error', 500, [
        'error' => $e->getMessage()
    ]);
}

// Route handling - EXPANDED with booking endpoints
try {
    error_log("Processing route: $request_uri");

    switch ($request_uri) {

        // Role routes
        case '/role/list':
            if ($request_method == 'POST') {
                error_log("Calling role list on admin control...");
                $roleController->roleList();
            } else {
                sendError('Method not allowed for /role/list', 405);
            }
            break;
        case '/role/add':
            if ($request_method == 'POST') {
                error_log("Calling role add on admin control...");
                $roleController->roleAdd();
            } else {
                sendError('Method not allowed for /role/add', 405);
            }
            break;
        case '/role/update':
            if ($request_method == 'POST') {
                error_log("Calling role update on admin control...");
                $roleController->roleUpdate();
            } else {
                sendError('Method not allowed for /role/update', 405);
            }
            break;
        case '/role/delete':
            if ($request_method == 'POST') {
                error_log("Calling role delete on admin control...");
                $roleController->roleDelete();
            } else {
                sendError('Method not allowed for /role/delete', 405);
            }
            break;

        // User routes
        case '/user/list':
            if ($request_method == 'POST') {
                if ($request_method == 'POST') {
                    error_log("Calling user list on user control...");
                    $userController->userlist();
                } else {
                    sendError('Method not allowed for /user/list', 405);
                }
            }
            break;
        case '/user/add':
            if ($request_method == 'POST') {
                if ($request_method == 'POST') {
                    error_log("Calling user add on user control...");
                    $userController->useradd();
                } else {
                    sendError('Method not allowed for /user/add', 405);
                }
            }
            break;
        case '/user/update':
            if ($request_method == 'POST') {
                if ($request_method == 'POST') {
                    error_log("Calling user update on user control...");
                    $userController->userupdate();
                } else {
                    sendError('Method not allowed for /user/update', 405);
                }
            }
            break;
        case '/user/delete':
            if ($request_method == 'POST') {
                if ($request_method == 'POST') {
                    error_log("Calling user delete on user control...");
                    $userController->userdelete();
                } else {
                    sendError('Method not allowed for /user/delete', 405);
                }
            }
            break;

        case '/bookings/list':
            if ($request_method == 'POST') {
                if ($bookingController) {
                    error_log("Calling get user booking list method...");
                    $bookingController->bookinglist();
                } else {
                    sendError('Booking list functionality not available', 503);
                }
            } else {
                sendError('Method not allowed for /bookings/list', 405);
            }
            break;
        


        // Authentication routes
        case '/register':
            if ($request_method == 'POST') {
                error_log("Calling register method...");
                $authController->register();
            } else {
                sendError('Method not allowed for /register', 405);
            }
            break;

        case '/login':
            if ($request_method == 'POST') {
                error_log("Calling login method...");
                $authController->login();
            } else {
                sendError('Method not allowed for /login', 405);
            }
            break;

        case '/profile':
            if ($request_method == 'GET') {
                error_log("Calling profile method...");
                $authController->getProfile();
            } else {
                sendError('Method not allowed for /profile', 405);
            }
            break;

        // Booking routes
        case '/pricing':
            if ($request_method == 'GET') {
                if ($bookingController) {
                    error_log("Calling pricing method...");
                    $bookingController->getPricing();
                } else {
                    sendError('Booking functionality not available', 503);
                }
            } else {
                sendError('Method not allowed for /pricing', 405);
            }
            break;

        case '/pricing':
            if ($request_method == 'GET') {
                if ($bookingController) {
                    error_log("Calling pricing method...");
                    $bookingController->getPricing();
                } else {
                    sendError('Booking functionality not available', 503);
                }
            } else {
                sendError('Method not allowed for /pricing', 405);
            }
            break;

        case '/all-pricing':
            if ($request_method == 'GET') {
                if ($bookingController) {
                    error_log("Calling all pricing method...");
                    $bookingController->getAllPricings();
                } else {
                    sendError('Pricing functionality not available', 503);
                }
            } else {
                sendError('Method not allowed for /all-pricing', 405);
            }
            break;

        case '/bookings':
            if ($request_method == 'POST') {
                if ($bookingController) {
                    error_log("Calling create booking method...");
                    $bookingController->createBooking();
                } else {
                    sendError('Booking functionality not available', 503);
                }
            } else {
                sendError('Method not allowed for /bookings', 405);
            }
            break;

        case '/bookings/user':
            if ($request_method == 'GET') {
                if ($bookingController) {
                    error_log("Calling get user bookings method...");
                    $bookingController->getUserBookings();
                } else {
                    sendError('Booking functionality not available', 503);
                }
            } else {
                sendError('Method not allowed for /bookings/user', 405);
            }
            break;

        case '/bookings/validate':
            if ($request_method == 'POST') {
                if ($bookingController) {
                    error_log("Calling validate booking method...");
                    $bookingController->validateBooking();
                } else {
                    sendError('Booking functionality not available', 503);
                }
            } else {
                sendError('Method not allowed for /bookings/validate', 405);
            }
            break;

        case '/bookings/test':
            if ($request_method == 'GET') {
                if ($bookingController) {
                    error_log("Calling booking test method...");
                    $bookingController->testConnection();
                } else {
                    sendError('Booking functionality not available', 503);
                }
            } else {
                sendError('Method not allowed for /bookings/test', 405);
            }
            break;

        // Root endpoint
        case '/':
        case '':
            $endpoints = [
                'POST /register' => 'User registration',
                'POST /login' => 'User login',
                'GET /profile' => 'Get user profile',
                'GET /debug' => 'Debug information',
                'GET /file-check' => 'Check file structure'
            ];

            if ($bookingController) {
                $endpoints = array_merge($endpoints, [
                    'GET /pricing' => 'Get pricing for date',
                    'POST /bookings' => 'Create new booking',
                    'GET /bookings/user' => 'Get user bookings',
                    'POST /bookings/validate' => 'Validate booking',
                    'PUT /bookings/{id}/cancel' => 'Cancel booking',
                    'GET /bookings/test' => 'Test booking API'
                ]);
            }

            sendSuccess([
                'message' => 'Raimona Water Park API v1 - DEBUG MODE',
                'status' => 'running',
                'version' => '1.0.0-debug',
                'booking_available' => $bookingController !== null,
                'available_endpoints' => $endpoints,
                'debug_info' => [
                    'php_version' => PHP_VERSION,
                    'current_time' => date('Y-m-d H:i:s'),
                    'request_uri' => $request_uri,
                    'method' => $request_method
                ]
            ]);
            break;

        case '/file-check':
            // Special endpoint to check file structure
            $file_status = [];
            $all_files = array_merge($required_files, $booking_files);
            foreach ($all_files as $file) {
                $file_status[$file] = [
                    'exists' => file_exists($file),
                    'readable' => file_exists($file) ? is_readable($file) : false,
                    'size' => file_exists($file) ? filesize($file) : 0
                ];
            }

            sendSuccess([
                'message' => 'File structure check',
                'files' => $file_status,
                'current_directory' => getcwd(),
                'directory_contents' => scandir('.'),
                'php_version' => PHP_VERSION,
                'booking_available' => $bookingController !== null
            ]);
            break;

        case '/jwt-debug':
            if ($request_method == 'GET') {
                error_log("=== JWT DEBUG START ===");

                // Get all headers
                $allHeaders = function_exists('getallheaders') ? getallheaders() : [];

                // Get all $_SERVER vars that might contain auth info
                $authServerVars = [];
                foreach ($_SERVER as $key => $value) {
                    if (stripos($key, 'auth') !== false || substr($key, 0, 5) === 'HTTP_') {
                        $authServerVars[$key] = substr($value, 0, 100); // Truncate for security
                    }
                }

                // Try JWT verification
                $user = null;
                if ($bookingController) {
                    $user = JWTMiddleware::verifyToken();
                }

                sendSuccess([
                    'message' => 'Enhanced JWT Debug Information',
                    'all_headers' => $allHeaders,
                    'auth_server_vars' => $authServerVars,
                    'booking_controller_exists' => $bookingController ? true : false,
                    'jwt_verification_result' => $user ? 'SUCCESS' : 'FAILED',
                    'user_id' => $user ? ($user['id'] ?? 'not found') : null,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'php_version' => PHP_VERSION
                ]);
            }
            break;

        case '/tickets':
            if ($request_method == 'POST') {
                error_log("Calling create ticket method...");
                $ticketController->createTicket();
            } else {
                sendError('Method not allowed for /tickets', 405);
            }
            break;

        case '/tickets/user':
            if ($request_method == 'GET') {
                error_log("Calling get user tickets method...");
                $ticketController->getUserTickets();
            } else {
                sendError('Method not allowed for /tickets/user', 405);
            }
            break;

        // Add these routes to your index.php switch statement:

        //
        case '/payments/temp':
            if ($request_method == 'POST') {
                // Store temporary payment data
                try {
                    $data = json_decode(file_get_contents("php://input"), true);

                    // You can store this in database or just return success for now
                    sendSuccess([
                        'message' => 'Temporary payment data stored',
                        'txnId' => $data['txnId'] ?? 'unknown'
                    ]);
                } catch (Exception $e) {
                    sendError('Failed to store temp payment data', 500);
                }
            } else {
                sendError('Method not allowed for /payments/temp', 405);
            }
            break;

            
        // Payment / Recheck routes

        case '/transaction/status':
            if ($request_method == 'POST') {
                error_log("Calling /transaction/status method...");
                $paymentController->recheckTransaction();
            } else {
                sendError('Method not allowed for /transaction/status', 405);  
            }
            break;

        // Admin Routes

        case '/park/status/close':
            if ($request_method == 'POST') {
                error_log("Calling /park/status/close method...");
                $adminController->closePark();
            } else {
                sendError('Method not allowed for /park/status/close', 405);
            }
            break;
        case '/park/status/reopen':
            if ($request_method == 'POST') {
                error_log("Calling /park/status/reopen method...");
                $adminController->reopenClosedPark();
            } else {
                sendError('Method not allowed for /park/status/reopen', 405);
            }
            break;
        case '/park/status/add':
            if ($request_method == 'POST') {
                error_log("Calling /park/status/add method...");
                $adminController->addParkStatus();
            } else {
                sendError('Method not allowed for /park/status/add', 405);
            }
            break;
        case '/park/status/list':
            if ($request_method == 'POST') {
                error_log("Calling /park/status/list method...");
                $adminController->listParkStatus();
            } else {
                sendError('Method not allowed for /park/status/list', 405);
            }
            break;
        case '/base/price/manage':
            if ($request_method == 'POST') {
                error_log("Calling /base/price/manage method...");
                $adminController->updateBasePrice();
            } else {
                sendError('Method not allowed for /base/price/manage', 405);
            }
            break;
        

        case '/base/price/get':
            if ($request_method == 'POST') {
                error_log("Calling /base/price/get method...");
                $adminController->getBasePrice();
            } else {
                sendError('Method not allowed for /manage/base/get', 405);
            }
            break;

        case '/offer/price/status':
            if ($request_method == 'POST') {
                error_log("Calling /offer/price/status method...");
                $adminController->activateDeactivateOfferPrice();
            } else {
                sendError('Method not allowed for /offer/price/status', 405);
            }
            break;
        
        case '/offer/price/add':
            if ($request_method == 'POST') {
                error_log("Calling /offer/price/add method...");
                $adminController->addOfferPrice();
            } else {
                sendError('Method not allowed for /offer/price/add', 405);
            }
            break;

        case '/offer/price/list':
            if ($request_method == 'POST') {
                error_log("Calling /offer/price/list method...");
                $adminController->listOfferPricing();
            } else {
                sendError('Method not allowed for /offer/price/list', 405);
            }
            break;

        case '/bookings/pending':
            if ($request_method == 'POST') {
                // Store pending booking
                try {
                    $data = json_decode(file_get_contents("php://input"), true);

                    sendSuccess([
                        'message' => 'Pending booking stored',
                        'txnId' => $data['txnId'] ?? 'unknown'
                    ]);
                } catch (Exception $e) {
                    sendError('Failed to store pending booking', 500);
                }
            } else {
                sendError('Method not allowed for /bookings/pending', 405);
            }
            break;

        case '/tickets/failed':
            if ($request_method == 'POST') {
                // Store failed ticket creation
                try {
                    $data = json_decode(file_get_contents("php://input"), true);

                    sendSuccess([
                        'message' => 'Failed ticket data stored for manual processing'
                    ]);
                } catch (Exception $e) {
                    sendError('Failed to store failed ticket data', 500);
                }
            } else {
                sendError('Method not allowed for /tickets/failed', 405);
            }
            break;


        case '/payment/status':
            if ($request_method == 'GET') {
                $txnId = $_GET['txnid'] ?? '';

                if (empty($txnId)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
                    exit;
                }

                // Check if payment result file exists
                $resultFile = __DIR__ . "/payment_success_{$txnId}.json";

                if (file_exists($resultFile)) {
                    $result = json_decode(file_get_contents($resultFile), true);

                    echo json_encode([
                        'success' => true,
                        'message' => 'Payment completed successfully',
                        'data' => $result
                    ]);

                    // Don't delete the file immediately, let it expire naturally
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Payment still pending or failed',
                        'data' => null
                    ]);
                }
                exit;
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
                exit;
            }
            break;


        default:
            // Check for dynamic booking routes
            if (preg_match('/^\/bookings\/([a-zA-Z0-9]+)$/', $request_uri, $matches)) {
                if ($request_method == 'GET' && $bookingController) {
                    $bookingId = $matches[1];
                    error_log("Getting booking: $bookingId");
                    $bookingController->getBooking($bookingId);
                } else {
                    sendError('Method not allowed or booking not available', 405);
                }
            } elseif (preg_match('/^\/bookings\/([a-zA-Z0-9]+)\/cancel$/', $request_uri, $matches)) {
                if ($request_method == 'PUT' && $bookingController) {
                    $bookingId = $matches[1];
                    error_log("Cancelling booking: $bookingId");
                    $bookingController->cancelBooking($bookingId);
                } else {
                    sendError('Method not allowed or booking not available', 405);
                }
            } else {
                error_log("Unknown endpoint: $request_uri");
                sendError('Endpoint not found: ' . $request_uri, 404, [
                    'available_endpoints' => ['/register', '/login', '/profile', '/pricing', '/bookings', '/debug', '/file-check', '/']
                ]);
            }
            break;
    }
} catch (Exception $e) {
    error_log("Route handling error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    sendError('Server error in route handling', 500, [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

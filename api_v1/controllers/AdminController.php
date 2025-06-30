<?php
require_once 'models/Booking.php';
require_once 'models/Admin.php';
require_once 'middleware/JWTMiddleware.php';

class AdminController {
    private $booking;
    private $admin;

    public function __construct() {
        $this->booking = new Booking();
        $this->admin = new Admin();
    }

    public function normalizeDate(string $input): ?string {
        if (empty($input)) {
            return null;
        }

        // Try a few known patterns:
        $formats = [
            'Y-m-d',    // 2025-07-15
            'd/m/Y',    // 15/07/2025
            'm-d-Y',    // 07-15-2025
            'd-M-Y',    // 15-Jul-2025
        ];

        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $input);
            if ($dt && $dt->format($fmt) === $input) {
                return $dt->format('Y-m-d');
            }
        }

        throw new \Exception("Unrecognized date format: $input");
    }

    //Close park
    public function closePark() {
        try {
            $admin = $this->verifyAdmin();

            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            if(!isset($body['date']) || $body['date'] == null || empty($body['date'])) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Date field is empty',
                ]);
                return;
            }

            if(!isset($body['reason']) || $body['reason'] == null || empty($body['reason'])) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Reason field is empty',
                ]);
                return;
            }

            $data["date"] = $this->normalizeDate($body['date']) ?? null;

            $data['reason'] = preg_replace('/\s+/', ' ', trim($body['reason']));
            
            if (!$data) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid date format',
                ]);
                return;
            }

            $this->admin->closeParkModel($data, $admin['id']);

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'Park has been closed',
            ]);
        } catch (\InvalidArgumentException $e) {
            // Missing/invalid input
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);

        } catch (\RuntimeException $e) {
            // No matching record or model-level error
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);

        } catch (\Exception $e) {
            // Unexpected error
            error_log('Error closing park: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Unable to close park status at this time.',
            ]);
        }
    }

    // Reopen closed park
    public function reopenClosedPark() {
        try {
            $admin = $this->verifyAdmin();

            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            if(!isset($body['date']) || $body['date'] == null || empty($body['date'])) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Date field is empty',
                ]);
                return;
            }
            $data["date"] = $this->normalizeDate($body['date']) ?? null;

            $data["reason"] = $body['reason'] ?? null;
            
            if (!$data) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid date format',
                ]);
                return;
            }

            $this->admin->reopenClosedParkModel($data, $admin['id']);

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'Park has been reopened',
            ]);
        } catch (\InvalidArgumentException $e) {
            // Model threw for missing/invalid input
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);

        } catch (\RuntimeException $e) {
            // Model threw when no record found or other logical error
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
            ]);

        } catch (\Exception $e) {
            // Unexpected errors
            error_log('Error reopening park: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Unable to reopen park status at this time.',
            ]);
        }
        
        // catch (Exception $e) {
        //     http_response_code(500);
        //     echo json_encode([
        //         'success' => false,
        //         'message' => 'Server error: ' . $e->getMessage(),
        //     ]);
        // }
    }

    // Add park status
    public function addParkStatus(){
        try {
            $admin = $this->verifyAdmin();

            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            $required = [
                'date',
                'opening_time',
                'closing_time',
                'is_open',
                'closure_reason',
                'has_special_event',
                'special_event_details',
            ];
            foreach ($required as $field) {
                if (! isset($body[$field]) || $body[$field] === '') {
                    http_response_code(400);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => "Missing required field: $field",
                    ]);
                    return;
                }
            }

            $date = DateTime::createFromFormat('Y-m-d', $body['date']);
            if (! $date) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid date format; expected YYYY-MM-DD',
                ]);
                return;
            }

            $opening = DateTime::createFromFormat('H:i:s', $body['opening_time']);
            $closing = DateTime::createFromFormat('H:i:s', $body['closing_time']);
            if (! $opening || ! $closing) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid time format; expected HH:MM:SS',
                ]);
                return;
            }
            if ($opening > $closing) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Opening time must be before closing time',
                ]);
                return;
            }

            $body['is_open'] = in_array(strtolower($body['is_open']), ['yes','1','true'], true) ? 1 : 0;
            $body['has_special_event'] = in_array(strtolower($body['has_special_event']), ['yes','1','true'], true) ? 1 : 0;

            $this->admin->addParkStatusModel($body, $admin['id']);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'message' => 'Park status added successfully'
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    // Get list of park status
    public function listParkStatus(){
        try {
            $this->verifyAdmin();

            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            $filters = $body["filters"] ?? [];

            if(!empty($filters)) {
                $filters = [
                    // 'date'         => $filters['date']         ?? date('Y-m-d'),
                    'date'         => $filters['date'] ?? null,
                    'is_open' => isset($filters['is_open']) && strtolower($filters['is_open']) == 'yes' ? 1 : 0,
                ];
            }

            // print_r($filters);
            // die();

            $rows = $this->admin->listParkStatusModel($filters);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'data'    => $rows
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    // Activate/Deactivate Offer Price
    public function activateDeactivateOfferPrice() {
        try {
            $admin = $this->verifyAdmin();
            $data  = json_decode(file_get_contents('php://input'), true);

            // Validate inputs
            foreach (['ref', 'activate'] as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode([
                        'error'   => true,
                        'message' => "Missing required field: $field",
                    ]);
                    return;
                }
            }

            $data['activate'] = (strtolower($data['activate']) === 'yes') ? 1 : 0;

            $updated = $this->admin->activateDeactivateOfferPriceModel($data, $admin['id']);

            if ($updated) {
                echo json_encode([
                    'success' => true,
                    'status' => 'success',
                    'message' => 'Offer status updated successfully',
                ]);
            } else {
                // either no rows changed or the model returned false
                http_response_code(409); // 409 Conflict: no state change
                echo json_encode([
                    'success' => false,
                    'message' => 'No change made.',
                ]);
            }

        } catch (RuntimeException $e) {
            // this is our sanitized exception
            http_response_code(500);
            echo json_encode([
                'error'   => true,
                'message' => $e->getMessage(),
            ]);
        } catch (Exception $e) {
            // fallback for anything unexpected
            error_log("[Controller][activateDeactivateOfferPrice] " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error'   => true,
                'message' => 'An unexpected error occurred.',
            ]);
        }
    }

    // Get list of offer pricing
    public function listOfferPricing(){
        try {
            $this->verifyAdmin();

            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            // print_r($body);
            // die();

            $filters = $body["filters"] ?? [];

            if(!empty($filters)) {
                $filters = [
                    // 'date'         => $filters['date']         ?? date('Y-m-d'),
                    'date'         => $filters['date'] ?? null,
                    'has_discount' => isset($filters['has_discount']) && strtolower($filters['has_discount']) == 'yes' ? 1 : null,
                    'is_holiday'   => isset($filters['is_holiday']) && strtolower($filters['is_holiday']) == 'yes' ? 1 : null,
                    'active'       => isset($filters['active']) && strtolower($filters['active']) == 'yes' ? 1 : null,
                ];
            }
            // print_r($filters);

            $rows = $this->admin->listOfferPricingModel($filters);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'status' => 'success',
                'data'    => $rows
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    // Add Daily Price
    public function addOfferPrice(){
        // header('Content-Type: application/json');

        try {
            $admin = $this->verifyAdmin();
            
            $data = json_decode(file_get_contents("php://input"), true);

            $requiredFields = ['date_from', 'date_to', 'adult_price', 'child_price'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => true, 'message' => "Missing required field: $field"]);
                    return;
                }
            }

            // Validate pricing
            if ($data['adult_price'] < 0 || $data['child_price'] < 0) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Prices must be non-negative']);
                return;
            }

            $data['date_from'] = $this->normalizeDate($data['date_from']);
            $data['date_to'] = $this->normalizeDate($data['date_to']);

            if($data['date_from'] > $data['date_to']){
                echo json_encode([
                    'success' => false,
                    'error' => true,
                    'message' => 'Date range is invalid'
                ]);
                return;
            }

            $data['adult_price'] = preg_replace('/\s+/', '', $data['adult_price']);
            $data['child_price'] = preg_replace('/\s+/', '', $data['child_price']);

            //Non-required fields
            
            $data['has_discount'] = isset($data['has_discount']) && !empty($data['has_discount']) && $data['has_discount'] == 'yes' ? 1 : 0;
            $data['discount_percentage'] = isset($data['discount_percentage']) && !empty($data['discount_percentage']) ? preg_replace('/\s+/', '', $data['discount_percentage']) : 0;
            $data['discount_reason'] = isset($data['discount_reason']) && !empty($data['discount_reason']) ? preg_replace('/\s+/', ' ', trim($data['discount_reason'])) : null;
            $data['special_message'] = isset($data['special_message']) && !empty($data['special_message']) ? preg_replace('/\s+/', ' ', trim($data['special_message'])) : null;
            $data['is_holiday'] = isset($data['is_holiday']) && !empty($data['is_holiday']) && $data['is_holiday'] == 'yes' ? 1 : 0;
        

            $result = $this->admin->addOfferPriceModel($data, $admin['id']);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'status' => 'success',
                    'message' => 'Offer Price updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => true, 'message' => 'Failed to update offer price']);
            }

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    // Get Base Price
    public function getBasePrice(){
        // header('Content-Type: application/json');

        try {
            $admin = $this->verifyAdmin();

            $prices = $this->admin->getBasePriceModel();

            echo json_encode([
                'success' => true,
                'status' => 'success',
                'data'    => $prices
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'error'   => true,
                'message' => $e->getMessage()
            ]);
        }
    }

    // Update Base Price
    public function updateBasePrice() {
        try {
            $admin = $this->verifyAdmin();
            
            $data = json_decode(file_get_contents("php://input"), true);

            $requiredFields = ['ref', 'price'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => true, 'message' => "Missing required field: $field"]);
                    return;
                }
            }

            // Validate pricing
            if ($data['price'] < 0) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Prices must be non-negative']);
                return;
            }


            $result = $this->admin->updateBasePriceModel($data, $admin['id']);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'status' => 'success',
                    'message' => 'Base Price updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => true, 'message' => 'Failed to update base price']);
            }

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    // Verify admin access
    private function verifyAdmin() {
        $user = JWTMiddleware::verifyToken();
        if (!$user || $user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => true, 'message' => 'Admin access required']);
            exit();
        }
        return $user;
    }

    // Get admin dashboard data
    public function getDashboard() {
        try {
            $admin = $this->verifyAdmin();
            
            $dashboardData = $this->admin->getDashboardStats();
            
            echo json_encode([
                'success' => true,
                'data' => $dashboardData
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => true, 'message' => 'Server error: ' . $e->getMessage()]);
        }
    }

    // Get all bookings with filters
    public function getAllBookings() {
        try {
            $admin = $this->verifyAdmin();
            
            // Get query parameters
            $date = $_GET['date'] ?? null;
            $status = $_GET['status'] ?? null;
            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 50);
            
            $bookings = $this->admin->getAllBookings($date, $status, $page, $limit);
            
            echo json_encode([
                'success' => true,
                'data' => $bookings
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => true, 'message' => 'Server error: ' . $e->getMessage()]);
        }
    }

    // Get specific booking details
    public function getBookingDetails($bookingId) {
        try {
            $admin = $this->verifyAdmin();
            
            $booking = $this->admin->getBookingDetails($bookingId);
            
            if ($booking) {
                echo json_encode([
                    'success' => true,
                    'data' => $booking
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => true, 'message' => 'Booking not found']);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => true, 'message' => 'Server error: ' . $e->getMessage()]);
        }
    }

    // Admin cancel booking
    public function adminCancelBooking($bookingId) {
        try {
            $admin = $this->verifyAdmin();
            
            $data = json_decode(file_get_contents("php://input"), true);
            $reason = $data['reason'] ?? 'Cancelled by admin';
            
            $result = $this->admin->cancelBooking($bookingId, $admin['id'], $reason);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Booking cancelled successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => true, 'message' => 'Failed to cancel booking']);
            }

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    // Get pricing settings
    public function getPricingSettings() {
        try {
            $admin = $this->verifyAdmin();
            
            $date = $_GET['date'] ?? null;
            $startDate = $_GET['start_date'] ?? date('Y-m-d');
            $endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('+30 days'));
            
            if ($date) {
                $pricing = $this->admin->getPricingForDate($date);
            } else {
                $pricing = $this->admin->getPricingRange($startDate, $endDate);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $pricing
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => true, 'message' => 'Server error: ' . $e->getMessage()]);
        }
    }

    // Update pricing
    public function updatePricing() {
        try {
            $admin = $this->verifyAdmin();
            
            $data = json_decode(file_get_contents("php://input"), true);
            
            // Validate required fields
            $requiredFields = ['date', 'adult_price', 'child_price'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => true, 'message' => "Missing required field: $field"]);
                    return;
                }
            }

            // Validate pricing
            if ($data['adult_price'] < 0 || $data['child_price'] < 0) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Prices must be non-negative']);
                return;
            }

            if (isset($data['discount_percentage']) && ($data['discount_percentage'] < 0 || $data['discount_percentage'] > 100)) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Discount percentage must be between 0 and 100']);
                return;
            }

            $result = $this->admin->updatePricing($data, $admin['id']);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Pricing updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => true, 'message' => 'Failed to update pricing']);
            }

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    // Update park status (open/close park for specific date)
    public function updateParkStatus() {
        try {
            $admin = $this->verifyAdmin();
            
            $data = json_decode(file_get_contents("php://input"), true);
            
            // Validate required fields
            if (!isset($data['date']) || !isset($data['is_open'])) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Date and is_open status required']);
                return;
            }

            // Validate date format
            if (!$this->isValidDate($data['date'])) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Invalid date format']);
                return;
            }

            // If closing park, validate closure reason is provided
            if (!$data['is_open'] && empty($data['closure_reason'])) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Closure reason required when closing park']);
                return;
            }

            $result = $this->admin->updateParkStatus($data, $admin['id']);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Park status updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => true, 'message' => 'Failed to update park status']);
            }

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    // Add rest time (maintenance/cleaning time)
    public function addRestTime() {
        try {
            $admin = $this->verifyAdmin();
            
            $data = json_decode(file_get_contents("php://input"), true);
            
            // Validate required fields
            $requiredFields = ['date', 'start_time', 'end_time', 'reason'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => true, 'message' => "Missing required field: $field"]);
                    return;
                }
            }

            // Validate date and time formats
            if (!$this->isValidDate($data['date'])) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Invalid date format']);
                return;
            }

            if (!$this->isValidTime($data['start_time']) || !$this->isValidTime($data['end_time'])) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Invalid time format. Use HH:MM']);
                return;
            }

            // Validate time range
            if (strtotime($data['start_time']) >= strtotime($data['end_time'])) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'End time must be after start time']);
                return;
            }

            $result = $this->admin->addRestTime($data, $admin['id']);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Rest time added successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => true, 'message' => 'Failed to add rest time']);
            }

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    // Remove rest time
    public function removeRestTime() {
        try {
            $admin = $this->verifyAdmin();
            
            $restTimeId = $_GET['id'] ?? null;
            
            if (!$restTimeId) {
                http_response_code(400);
                echo json_encode(['error' => true, 'message' => 'Rest time ID required']);
                return;
            }

            $result = $this->admin->removeRestTime($restTimeId, $admin['id']);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Rest time removed successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => true, 'message' => 'Failed to remove rest time']);
            }

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    // Get revenue report
    public function getRevenueReport() {
        try {
            $admin = $this->verifyAdmin();
            
            $startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
            $endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today
            $groupBy = $_GET['group_by'] ?? 'day'; // day, month, year
            
            $report = $this->admin->getRevenueReport($startDate, $endDate, $groupBy);
            
            echo json_encode([
                'success' => true,
                'data' => $report
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => true, 'message' => 'Server error: ' . $e->getMessage()]);
        }
    }

    // Get visitor statistics
    public function getVisitorStats() {
        try {
            $admin = $this->verifyAdmin();
            
            $date = $_GET['date'] ?? date('Y-m-d');
            $period = $_GET['period'] ?? 'month'; // day, week, month, year
            
            $stats = $this->admin->getVisitorStats($date, $period);
            
            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => true, 'message' => 'Server error: ' . $e->getMessage()]);
        }
    }

    // Get user list
    public function getUsers() {
        try {
            $admin = $this->verifyAdmin();
            
            $page = intval($_GET['page'] ?? 1);
            $limit = intval($_GET['limit'] ?? 50);
            $search = $_GET['search'] ?? null;
            
            $users = $this->admin->getUsers($page, $limit, $search);
            
            echo json_encode([
                'success' => true,
                'data' => $users
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => true, 'message' => 'Server error: ' . $e->getMessage()]);
        }
    }

    // Send notification to users
    public function sendNotification() {
        try {
            $admin = $this->verifyAdmin();
            
            $data = json_decode(file_get_contents("php://input"), true);
            
            // Validate required fields
            $requiredFields = ['title', 'message', 'type'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => true, 'message' => "Missing required field: $field"]);
                    return;
                }
            }

            $result = $this->admin->sendNotification($data, $admin['id']);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Notification sent successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => true, 'message' => 'Failed to send notification']);
            }

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    // Helper function to validate date format
    private function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    // Helper function to validate time format
    private function isValidTime($time) {
        $t = DateTime::createFromFormat('H:i', $time);
        return $t && $t->format('H:i') === $time;
    }
}
?>
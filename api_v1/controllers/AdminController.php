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

    // Get Base Price
    public function getBasePrice(){
        // header('Content-Type: application/json');

        try {
            $admin = $this->verifyAdmin();

            $prices = $this->admin->getBasePriceModel();

            echo json_encode([
                'success' => true,
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
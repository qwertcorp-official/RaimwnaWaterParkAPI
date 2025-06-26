<?php
require_once 'models/Booking.php';
require_once 'middleware/JWTMiddleware.php';

class BookingController {
    private $booking;

    public function __construct() {
        $this->booking = new Booking();
    }

    // Get pricing for a specific date
    
    public function getPricing() {
        try {
            // Verify JWT token
            $user = JWTMiddleware::verifyTokenAsArray();
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Unauthorized - Please login again'
                ]);
                return;
            }

            $date = $_GET['date'] ?? null;
            
            if (!$date) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Date parameter is required'
                ]);
                return;
            }

            // Validate date format
            if (!$this->isValidDate($date)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Invalid date format. Use YYYY-MM-DD'
                ]);
                return;
            }

            // Check if date is not in the past
            if (strtotime($date) < strtotime(date('Y-m-d'))) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Cannot book for past dates',
                    'code' => 'INVALID_DATE'
                ]);
                return;
            }

            // Get pricing data
            $pricing = $this->booking->getPricingForDate($date);
            
            if ($pricing) {
                // Ensure all required fields are present and properly formatted
                $response = [
                    'success' => true,
                    'message' => 'Pricing retrieved successfully',
                    'data' => [
                        'date' => $pricing['date'],
                        'adult_price' => (int)$pricing['adult_price'],
                        'child_price' => (int)$pricing['child_price'],
                        'is_available' => (bool)($pricing['is_available'] ?? true),
                        'max_capacity' => (int)($pricing['max_capacity'] ?? 500),
                        'current_bookings' => (int)($pricing['current_bookings'] ?? 0),
                        'available_time_slots' => $pricing['available_time_slots'] ?? ['Full Day (9:00 AM - 9:00 PM)'],
                        'has_discount' => (bool)($pricing['has_discount'] ?? false),
                        'discount_percentage' => (int)($pricing['discount_percentage'] ?? 0),
                        'special_message' => $pricing['special_message'] ?? null,
                        'rest_times' => $pricing['rest_times'] ?? [],
                        'available_spots' => (int)($pricing['max_capacity'] ?? 500) - (int)($pricing['current_bookings'] ?? 0),
                        'is_holiday' => (bool)($pricing['is_holiday'] ?? false)
                    ]
                ];
                
                echo json_encode($response);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Pricing not available for this date'
                ]);
            }

        } catch (Exception $e) {
            error_log("Pricing API Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => true, 
                'message' => 'Server error occurred while fetching pricing'
            ]);
        }
    }

    // All Pricings admin side
    public function getAllPricings() {
        try {
            // Step 1: Verify JWT token
            $user = JWTMiddleware::verifyTokenAsArray();
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => true,
                    'message' => 'Unauthorized - Please login again'
                ]);
                return;
            }

            // Step 2: Fetch all pricing entries
            $allPricings = $this->booking->getAllPricingEntries(); // This must exist in your model

            if (!$allPricings || empty($allPricings)) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => true,
                    'message' => 'No pricing data found'
                ]);
                return;
            }

            // print_r($allPricings);
            // die();

            // Step 3: Format each pricing entry
            $formatted = array_map(function ($p) {
                return [
                    'date' => $p['date'],
                    'adult_price' => (int)$p['adult_price'],
                    'child_price' => (int)$p['child_price'],
                    'is_available' => (bool)($p['is_available'] ?? true),
                    'max_capacity' => (int)($p['max_capacity'] ?? 500),
                    'current_bookings' => (int)($p['current_bookings'] ?? 0),
                    'available_time_slots' => $p['available_time_slots'] ?? ['Full Day (9:00 AM - 9:00 PM)'],
                    'has_discount' => (bool)($p['has_discount'] ?? false),
                    'discount_percentage' => (int)($p['discount_percentage'] ?? 0),
                    'special_message' => $p['special_message'] ?? null,
                    'rest_times' => $p['rest_times'] ?? [],
                    'available_spots' => (int)($p['max_capacity'] ?? 500) - (int)($p['current_bookings'] ?? 0),
                    'is_holiday' => (bool)($p['is_holiday'] ?? false)
                ];
            }, $allPricings);

            // Step 4: Send success response
            echo json_encode([
                'success' => true,
                'message' => 'All pricing entries retrieved',
                'data' => $formatted
            ]);

        } catch (Exception $e) {
            error_log("getAllPricings Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => true,
                'message' => 'Server error occurred while fetching all pricing data'
            ]);
        }
    }


    // Create a new booking
    public function createBooking() {
        try {
            // Verify JWT token
            $user = JWTMiddleware::verifyTokenAsArray();
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Unauthorized - Please login again'
                ]);
                return;
            }

            $input = file_get_contents("php://input");
            $data = json_decode($input, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Invalid JSON data'
                ]);
                return;
            }

            // Validate required fields
            $requiredFields = ['date', 'adult_tickets', 'child_tickets'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => true, 
                        'message' => "Missing required field: $field"
                    ]);
                    return;
                }
            }

            // Set default time_slot if not provided
            if (!isset($data['time_slot'])) {
                $data['time_slot'] = 'Full Day Access';
            }

            // Validate ticket numbers
            $adultTickets = (int)$data['adult_tickets'];
            $childTickets = (int)$data['child_tickets'];

            if ($adultTickets < 0 || $childTickets < 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Ticket numbers must be non-negative'
                ]);
                return;
            }

            if ($adultTickets + $childTickets == 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'At least one ticket must be selected'
                ]);
                return;
            }

            if ($adultTickets + $childTickets > 20) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Maximum 20 tickets per booking'
                ]);
                return;
            }

            // Validate date
            if (!$this->isValidDate($data['date'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Invalid date format'
                ]);
                return;
            }

            // Check if date is not in the past
            if (strtotime($data['date']) < strtotime(date('Y-m-d'))) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Cannot book for past dates',
                    'code' => 'INVALID_DATE'
                ]);
                return;
            }

            // Create the booking
            $booking = $this->booking->createBooking($user['id'], $data);

            if ($booking) {
                // Format the response to match what Flutter expects
                $response = [
                    'success' => true,
                    'message' => 'Booking created successfully',
                    'data' => [
                        'booking_id' => $booking['booking_id'],
                        'date' => $booking['date'],
                        'time_slot' => $booking['time_slot'],
                        'adult_tickets' => (int)$booking['adult_tickets'],
                        'child_tickets' => (int)$booking['child_tickets'],
                        'total_amount' => (int)$booking['total_amount'],
                        'status' => $booking['status'],
                        'created_at' => $booking['created_at'],
                        'qr_code' => $booking['qr_code'],
                        'special_instructions' => $booking['special_requests'] ?? null
                    ]
                ];

                http_response_code(201);
                echo json_encode($response);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Failed to create booking'
                ]);
            }

        } catch (Exception $e) {
            error_log("Booking Creation Error: " . $e->getMessage());
            
            // Handle specific booking exceptions
            $errorCode = 'BOOKING_ERROR';
            $httpCode = 400;
            
            if (strpos($e->getMessage(), 'closed') !== false) {
                $errorCode = 'PARK_CLOSED';
            } elseif (strpos($e->getMessage(), 'capacity') !== false || strpos($e->getMessage(), 'Insufficient') !== false) {
                $errorCode = 'FULL_CAPACITY';
            } elseif (strpos($e->getMessage(), 'date') !== false) {
                $errorCode = 'INVALID_DATE';
            }

            http_response_code($httpCode);
            echo json_encode([
                'success' => false,
                'error' => true, 
                'message' => $e->getMessage(),
                'code' => $errorCode
            ]);
        }
    }

    // Get user's bookings
    public function getUserBookings() {
        try {
            // Verify JWT token
            $user = JWTMiddleware::verifyTokenAsArray();
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Unauthorized'
                ]);
                return;
            }

            $bookings = $this->booking->getUserBookings($user['id']);

            // Format bookings for Flutter
            $formattedBookings = array_map(function($booking) {
                return [
                    'id' => $booking['booking_id'],
                    'date' => $booking['date'],
                    'time_slot' => $booking['time_slot'],
                    'adult_tickets' => (int)$booking['adult_tickets'],
                    'child_tickets' => (int)$booking['child_tickets'],
                    'total_amount' => (int)$booking['total_amount'],
                    'status' => $booking['status'],
                    'created_at' => $booking['created_at'],
                    'qr_code' => $booking['qr_code']
                ];
            }, $bookings);

            echo json_encode([
                'success' => true,
                'message' => 'Bookings retrieved successfully',
                'data' => $formattedBookings
            ]);

        } catch (Exception $e) {
            error_log("Get User Bookings Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => true, 
                'message' => 'Server error occurred while fetching bookings'
            ]);
        }
    }

    // Get specific booking by ID
    public function getBooking($bookingId) {
        try {
            // Verify JWT token
            $user = JWTMiddleware::verifyTokenAsArray();
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Unauthorized'
                ]);
                return;
            }

            $booking = $this->booking->getBookingByBookingId($bookingId);

            if ($booking && $booking['user_id'] == $user['id']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Booking retrieved successfully',
                    'data' => $booking
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Booking not found'
                ]);
            }

        } catch (Exception $e) {
            error_log("Get Booking Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => true, 
                'message' => 'Server error occurred while fetching booking'
            ]);
        }
    }

    // Cancel booking
    public function cancelBooking($bookingId) {
        try {
            // Verify JWT token
            $user = JWTMiddleware::verifyTokenAsArray();
            
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Unauthorized'
                ]);
                return;
            }

            $result = $this->booking->cancelBooking($bookingId, $user['id']);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Booking cancelled successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Failed to cancel booking'
                ]);
            }

        } catch (Exception $e) {
            error_log("Cancel Booking Error: " . $e->getMessage());
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => true, 
                'message' => $e->getMessage()
            ]);
        }
    }

    // Validate booking
    public function validateBooking() {
        try {
            // Verify JWT token
            $user = JWTMiddleware::verifyTokenAsArray();
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Unauthorized'
                ]);
                return;
            }

            $data = json_decode(file_get_contents("php://input"), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => true, 
                    'message' => 'Invalid JSON data'
                ]);
                return;
            }

            $validation = $this->booking->validateBooking($data);

            echo json_encode([
                'success' => true,
                'valid' => $validation['valid'],
                'message' => $validation['message'] ?? ''
            ]);

        } catch (Exception $e) {
            error_log("Validate Booking Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => true, 
                'message' => 'Server error occurred during validation'
            ]);
        }
    }

    // Test endpoint for debugging
    public function testConnection() {
        try {
            echo json_encode([
                'success' => true,
                'message' => 'Booking API is working correctly',
                'timestamp' => date('Y-m-d H:i:s'),
                'server_time' => time(),
                'booking_model' => 'Available'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => true,
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
    }

    // Helper function to validate date format
    private function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
?>
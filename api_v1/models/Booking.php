<?php
require_once 'config/database.php';

class Booking {
    private $conn;
    private $table = 'bookings';
    private $tickets = 'tickets';
    private $pricing_table = 'daily_pricing';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /*public function bookingList($input = []){

        try {

            $defaultParamsForCondition = [
                "amount" => [
                    "column" => "ticket_amount",
                    "val" => null,
                ],
                "book_by" => [
                    "column" => "user_id",
                    "val" => null,
                ],
                "booking_date" => [
                    "column" => "updated_at",
                    "val" => null,
                ],
                "sale_date" => [
                    "column" => "updated_at",
                    "val" => null, // 0/1
                ],
                "visited" => [
                    "column" => "scan_status",
                    "val" => null, // 0/1
                ],
            ];

            $defaultConditions = [
                "amount" => null,
                "book_by" => null,
                "booking_date" => null,
                "sale_date" => null,
                "visited" => null,
            ];

            $query = "SELECT * FROM " . $this->tickets;
            // $query = "SELECT id, amount, book_by, booking_date, sale_date, visited, created_at, updated_at FROM " . $this->table;

            $where = [];
            $params = [];

            $finalConditions = $defaultConditions;
            if (!empty($input['conditions']) && is_array($input['conditions'])) {
                foreach ($input['conditions'] as $key => $value) {
                    if (strtolower($value) !== 'null') {
                        $finalConditions[$key] = $value;
                    }
                }
            }

            foreach ($finalConditions as $key => $value) {
                if (!is_null($value)) {
                    $where[] = "$key = :$key";
                    $params[":$key"] = $value;
                }
            }


            // if (empty($where)) {
            //     $query .= " WHERE deleted = 0";
            // } else {
            //     $query .= " WHERE " . implode(" AND ", $where) . " AND deleted = 0";
            // }

            if (!empty($where)){
                $query .= " WHERE " . implode(" AND ", $where);
            }


            if (!empty($input['order-key']) && !empty($input['order-by'])) {
                $orderKey = trim($input['order-key']);
                $orderBy = strtoupper(trim($input['order-by']));

                $allowedDirs = ['ASC', 'DESC'];
                $allowedColumns = ['id', 'amount', 'book_by', 'booking_date', 'sale_date', 'visited', 'created_at', 'updated_at'];

                if (in_array($orderBy, $allowedDirs) && in_array($orderKey, $allowedColumns)) {
                    $query .= " ORDER BY `$orderKey` $orderBy";
                }
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

            exit();

            if (!$stmt) {
                error_log("Fetching Bookings failed (getAllBookings method): " . $this->conn->error);
                return false;
            }

            foreach ($params as $param => $val) {
                $stmt->bindValue($param, $val);
            }

            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                error_log("🔺Bookings not found (getAllBookings)");
                return false;
            }

        } catch (Exception $e) {
            error_log("Get All Bookings method error: " . $e->getMessage());
            return false;
        }

    }*/

    public function bookingList($input = []) {
        try {
            $defaultParamsForCondition = [
                "amount" => [
                    "column" => "ticket_amount",
                    "val" => null,
                ],
                "book_by" => [
                    "column" => "user_id",
                    "val" => null,
                ],
                "booking_date" => [
                    "column" => "updated_at",
                    "val" => null,
                ],
                "sale_date" => [
                    "column" => "sale_date",
                    "val" => null,
                ],
                "visited" => [
                    "column" => "scan_status",
                    "val" => null,
                ],
            ];

            $query = "SELECT * FROM " . $this->tickets;
            // $query = "SELECT id, ticket_amount FROM " . $this->tickets;
            $where = [];
            $params = [];

            // Assign incoming values to $defaultParamsForCondition
            if (!empty($input['conditions']) && is_array($input['conditions'])) {
                foreach ($defaultParamsForCondition as $key => &$config) {
                    if (isset($input['conditions'][$key]) && strtolower($input['conditions'][$key]) !== 'null') {
                        $config['val'] = $input['conditions'][$key];
                    }
                }
            }

            // Build WHERE conditions
            foreach ($defaultParamsForCondition as $key => $config) {
                if (!is_null($config['val'])) {
                    $where[] = "{$config['column']} = :$key";
                    $params[":$key"] = $config['val'];
                }
            }

            if (!empty($where)) {
                $query .= " WHERE " . implode(" AND ", $where);
            }

            // Order clause
            if (!empty($input['order-key']) && !empty($input['order-by'])) {
                $orderKey = trim($input['order-key']);
                $orderBy = strtoupper(trim($input['order-by']));

                $allowedDirs = ['ASC', 'DESC'];
                $allowedColumns = [
                    'id', 'ticket_amount', 'user_id', 'booking_date', 'sale_date',
                    'scan_status', 'created_at', 'updated_at'
                ];

                if (in_array($orderBy, $allowedDirs) && in_array($orderKey, $allowedColumns)) {
                    $query .= " ORDER BY `$orderKey` $orderBy";
                }
            }

            $stmt = $this->conn->prepare($query);

            if (!$stmt) {
                error_log("Fetching Bookings failed (bookingList method): " . $this->conn->error);
                return [
                    "success" => false,
                    "status" => "error",
                    "message" => "Failed to prepare statement",
                    "data" => []
                ];
            }

            foreach ($params as $param => $val) {
                $stmt->bindValue($param, $val);
            }

            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return [
                    "success" => true,
                    "status" => "success",
                    "message" => "Booking records fetched successfully.",
                    "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)
                ];
            } else {
                return [
                    "success" => false,
                    "status" => "not_found",
                    "message" => "No bookings found for given filters.",
                    "data" => []
                ];
            }

        } catch (Exception $e) {
            error_log("Booking List method error: " . $e->getMessage());
            return [
                "success" => false,
                "status" => "exception",
                "message" => "An error occurred while fetching booking list.",
                "error" => $e->getMessage(),
                "data" => []
            ];
        }
    }





    // Get pricing for a specific date
    public function getPricingForDate($date) {
        try {
            $query = "SELECT * FROM " . $this->pricing_table . " WHERE date = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$date]);
            
            if ($stmt->rowCount() > 0) {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Apply discount if exists
                if ($result['has_discount'] && $result['discount_percentage'] > 0) {
                    $original_adult = $result['adult_price'];
                    $original_child = $result['child_price'];
                    $result['adult_price'] = round($original_adult * (100 - $result['discount_percentage']) / 100);
                    $result['child_price'] = round($original_child * (100 - $result['discount_percentage']) / 100);
                }
                
                // Add availability info
                $result['is_available'] = true;
                $result['max_capacity'] = 500;
                $result['current_bookings'] = $this->getCurrentBookings($date);
                $result['available_time_slots'] = ['Full Day (9:00 AM - 9:00 PM)'];
                $result['rest_times'] = [];
                
                return $result;
            }
            
            // Return default pricing if no specific pricing exists
            return $this->getDefaultPricing($date);
            
        } catch (Exception $e) {
            error_log("Error getting pricing for date $date: " . $e->getMessage());
            // Return default pricing on error
            return $this->getDefaultPricing($date);
        }
    }

    // Get all pricing entries admin side
    public function getAllPricingEntries() {
        try {
            $query = "SELECT * FROM daily_pricing ORDER BY date ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Model getAllPricingEntries error: " . $e->getMessage());
            return [];
        }
    }


    // Get default pricing
    private function getDefaultPricing($date) {
        return [
            'date' => $date,
            'adult_price' => 500,  // Your default adult price
            'child_price' => 300,  // Your default child price
            'is_available' => true,
            'max_capacity' => 500,
            'current_bookings' => $this->getCurrentBookings($date),
            'available_time_slots' => ['Full Day (9:00 AM - 9:00 PM)'],
            'has_discount' => false,
            'discount_percentage' => 0,
            'special_message' => 'Default pricing applied',
            'rest_times' => [],
            'is_holiday' => false
        ];
    }

    // Get current bookings for a date
    private function getCurrentBookings($date) {
        try {
            $query = "SELECT COALESCE(SUM(adult_tickets + child_tickets), 0) as total_bookings 
                      FROM " . $this->table . " 
                      WHERE date = ? AND status IN ('confirmed', 'pending')";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$date]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['total_bookings'];
            
        } catch (Exception $e) {
            error_log("Error getting current bookings for date $date: " . $e->getMessage());
            return 0;
        }
    }

    // Create a new booking
    public function createBooking($userId, $data) {
        // Start transaction
        $this->conn->beginTransaction();
        
        try {
            // Validate date and capacity
            if (!$this->validateBookingDate($data['date'])) {
                throw new Exception('Invalid booking date');
            }
            
            if (!$this->checkCapacity($data['date'], $data['adult_tickets'] + $data['child_tickets'])) {
                throw new Exception('Insufficient capacity for selected date');
            }
            
            // Get current pricing for the date
            $pricing = $this->getPricingForDate($data['date']);
            $adultPrice = $data['adult_price'] ?? $pricing['adult_price'];
            $childPrice = $data['child_price'] ?? $pricing['child_price'];
            
            // Calculate total amount
            $subtotal = ($data['adult_tickets'] * $adultPrice) + ($data['child_tickets'] * $childPrice);
            $serviceFee = round($subtotal * 0.10); // 10% service fee
            $totalAmount = $subtotal + $serviceFee;
            
            // Generate booking ID
            $bookingId = $this->generateBookingId();
            
            $query = "INSERT INTO " . $this->table . " 
                      (booking_id, user_id, date, time_slot, adult_tickets, child_tickets, 
                       adult_price, child_price, subtotal, service_fee, total_amount, 
                       status, payment_status, special_requests, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'pending', ?, NOW())";
                      
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $bookingId,
                $userId,
                $data['date'],
                $data['time_slot'] ?? 'Full Day Access',
                $data['adult_tickets'],
                $data['child_tickets'],
                $adultPrice,
                $childPrice,
                $subtotal,
                $serviceFee,
                $totalAmount,
                $data['special_requests'] ?? null
            ]);
            
            // Generate QR code for the booking
            $qrCode = $this->generateQRCode($bookingId);
            
            // Update booking with QR code
            $updateQuery = "UPDATE " . $this->table . " SET qr_code = ? WHERE booking_id = ?";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->execute([$qrCode, $bookingId]);
            
            $this->conn->commit();
            
            return $this->getBookingByBookingId($bookingId);
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Get user's bookings
    public function getUserBookings($userId) {
        try {
            $query = "SELECT booking_id as id, booking_id, user_id, date, time_slot, 
                             adult_tickets, child_tickets, total_amount, status, 
                             payment_status, created_at, qr_code
                      FROM " . $this->table . " 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC";
                      
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error getting user bookings: " . $e->getMessage());
            return [];
        }
    }

    // Get booking by booking ID
    public function getBookingByBookingId($bookingId) {
        try {
            $query = "SELECT b.*, u.name as user_name, u.email as user_email 
                      FROM " . $this->table . " b
                      JOIN users_for_rimona u ON b.user_id = u.id
                      WHERE b.booking_id = ?";
                      
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$bookingId]);
            
            if ($stmt->rowCount() > 0) {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
            return false;
            
        } catch (Exception $e) {
            error_log("Error getting booking by ID: " . $e->getMessage());
            return false;
        }
    }

    // Cancel booking
    public function cancelBooking($bookingId, $userId) {
        try {
            // Check if booking belongs to user and can be cancelled
            $booking = $this->getBookingByBookingId($bookingId);
            
            if (!$booking || $booking['user_id'] != $userId) {
                throw new Exception('Booking not found or unauthorized');
            }
            
            if ($booking['status'] != 'confirmed') {
                throw new Exception('Booking cannot be cancelled');
            }
            
            // Check if booking is at least 24 hours in the future
            $bookingDate = new DateTime($booking['date']);
            $now = new DateTime();
            $diff = $bookingDate->diff($now);
            
            if ($diff->days < 1) {
                throw new Exception('Booking can only be cancelled at least 24 hours in advance');
            }
            
            $query = "UPDATE " . $this->table . " 
                      SET status = 'cancelled', cancelled_at = NOW(), 
                          cancellation_reason = 'Cancelled by user'
                      WHERE booking_id = ?";
                      
            $stmt = $this->conn->prepare($query);
            return $stmt->execute([$bookingId]);
            
        } catch (Exception $e) {
            error_log("Error cancelling booking: " . $e->getMessage());
            throw $e;
        }
    }

    // Validate booking date
    private function validateBookingDate($date) {
        // Check if date is not in the past
        $today = date('Y-m-d');
        return $date >= $today;
    }

    // Check capacity for date
    private function checkCapacity($date, $requestedTickets) {
        try {
            $currentBookings = $this->getCurrentBookings($date);
            $maxCapacity = 500; // Default capacity
            $availableSpots = $maxCapacity - $currentBookings;
            return $availableSpots >= $requestedTickets;
            
        } catch (Exception $e) {
            error_log("Error checking capacity: " . $e->getMessage());
            return false;
        }
    }

    // Generate unique booking ID
    private function generateBookingId() {
        $prefix = 'RWP' . date('Ymd');
        $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return $prefix . $random;
    }

    // Generate QR code data
    private function generateQRCode($bookingId) {
        return base64_encode(json_encode([
            'booking_id' => $bookingId,
            'park' => 'Raimona Water Park',
            'timestamp' => time(),
            'verification_code' => md5($bookingId . time())
        ]));
    }

    // Validate booking data
    public function validateBooking($data) {
        try {
            // Check if date is valid
            if (!$this->validateBookingDate($data['date'])) {
                return ['valid' => false, 'message' => 'Cannot book for past dates'];
            }
            
            // Check capacity
            if (!$this->checkCapacity($data['date'], $data['adult_tickets'] + $data['child_tickets'])) {
                return ['valid' => false, 'message' => 'Insufficient capacity'];
            }
            
            // Check if tickets are reasonable
            if ($data['adult_tickets'] + $data['child_tickets'] > 20) {
                return ['valid' => false, 'message' => 'Maximum 20 tickets per booking'];
            }
            
            if ($data['adult_tickets'] + $data['child_tickets'] <= 0) {
                return ['valid' => false, 'message' => 'At least one ticket must be selected'];
            }
            
            return ['valid' => true];
            
        } catch (Exception $e) {
            return ['valid' => false, 'message' => $e->getMessage()];
        }
    }
}
?>
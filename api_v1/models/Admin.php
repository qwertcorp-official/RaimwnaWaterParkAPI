<?php
require_once 'config/database.php';

class Admin {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Add park status
    public function addParkStatusModel(array $data, int $adminId): bool{
        $sql = "
            INSERT INTO `park_status` (`date`, `is_open`, `closure_reason`, `has_special_event`, `special_event_details`, `opening_time`, `closing_time`, `created_at`)
            VALUES
                (:date,
                :is_open,
                :closure_reason,
                :has_event,
                :event_details,
                :opening_time,
                :closing_time,
                :created_at)
        ";
        $stmt = $this->conn->prepare($sql);

        try {
            $stmt->execute([
                ':date'             => $data['date'],
                ':is_open'          => (int)$data['is_open'],
                ':closure_reason'   => $data['closure_reason'],
                ':has_event'        => (int)$data['has_special_event'],
                ':event_details'    => $data['special_event_details'],
                ':opening_time'     => $data['opening_time'],
                ':closing_time'     => $data['closing_time'],
                ':created_at'       => date('Y-m-d H:i:s'),
            ]);

            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {
            // 23000: integrity constraint violation (e.g. duplicate date)
            if ($e->getCode() === '23000') {
                throw new RuntimeException('Park status for this date already exists.');
            }
            error_log("[ParkStatusModel] Insert failed: " . $e->getMessage());
            throw new RuntimeException('Could not add park status right now.');
        }
    }



    // List park status model
    public function listParkStatusModel(array $filters = []){
        $sql = "
            SELECT id, date, is_open, closure_reason, max_capacity, has_special_event, special_event_details, created_at, updated_at
            FROM park_status
        ";

        $conds  = [];
        $params = [];

         // 1) date filter (exact match)
        if (!empty($filters['date'])) {
            $conds[]            = 'date = :date';
            $params[':date']    = $filters['date'];
        }

        // 2) is_open filter (yes/no)
        if (isset($filters['is_open'])) {
            $conds[]                = 'is_open = :is_open';
            $params[':is_open']     = (int)$filters['is_open'];
        }

        if (count($conds)) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }

        $sql .= ' ORDER BY date DESC';

        $stmt = $this->conn->prepare($sql);

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

    // Activate/Deactivate Offer pricing model
    public function activateDeactivateOfferPriceModel($data, $adminId){

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare(
                'UPDATE `offer_pricing`
                SET `active` = :active
                WHERE `id` = :ref'
            );
            $stmt->bindValue(':active', $data['activate'], PDO::PARAM_INT);
            $stmt->bindValue(':ref',    $data['ref'],      PDO::PARAM_INT);

            // print_r($stmt);
            // print_r($data);
            // die();

            $stmt->execute();

            $affected = $stmt->rowCount();
            $this->conn->commit();

            if ($affected === 0) {
                // nothing changed (maybe already in that state)
                return false;
            }
            return true;

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("[OfferPriceModel] Update failed: " . $e->getMessage());
            throw new RuntimeException("Could not update offer status right now.");
        }
    }

    // List offer pricing model
    public function listOfferPricingModel(array $filters = []){
        $sql = "
            SELECT id, date_from, date_to, adult_price, child_price, has_discount, discount_percentage, discount_reason, special_message, is_holiday, active, created_at, updated_at
            FROM offer_pricing
        ";

        $conds  = [];
        $params = [];

        // 1) single-date filter
        if (!empty($filters['date'])) {
            $conds[]         = 'date_from <= :date AND date_to >= :date';
            $params[':date'] = $filters['date'];
        }

        // 2) has_discount filter
        if (isset($filters['has_discount'])) {
            $conds[]                         = 'has_discount = :has_discount';
            $params[':has_discount']         = (int)$filters['has_discount'];
        }

        // 3) is_holiday filter
        if (isset($filters['is_holiday'])) {
            $conds[]                         = 'is_holiday = :is_holiday';
            $params[':is_holiday']           = (int)$filters['is_holiday'];
        }

        // 4) active filter
        if (isset($filters['active'])) {
            $conds[]                         = 'active = :active';
            $params[':active']               = (int)$filters['active'];
        }

        if (count($conds)) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }

        $sql .= ' ORDER BY date_from DESC';

        $stmt = $this->conn->prepare($sql);

        // print_r($stmt);
        // print_r($params);
        // die();
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

    // Add daily price or offers
    public function addOfferPriceModel(array $data, int $adminId){

        $overlapSql = "
            SELECT COUNT(*) 
            FROM `offer_pricing`
            WHERE `active` = 1
            AND date_from <= :new_to
            AND date_to   >= :new_from
        ";
        $stmt = $this->conn->prepare($overlapSql);
        $stmt->execute([
            ':new_from' => $data['date_from'],
            ':new_to'   => $data['date_to'],
        ]);
        // print_r($stmt);
        // print_r($data);
        // die();
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) {
            throw new RuntimeException('Date range overlaps an existing active offer');
        }
        
        $sql = "
            INSERT INTO offer_pricing(date_from, date_to, adult_price ,child_price ,has_discount, discount_percentage, discount_reason, special_message, is_holiday, created_at)
            VALUES
            (:date_from
            ,:date_to
            ,:adult_price
            ,:child_price
            ,:has_discount
            ,:discount_percentage
            ,:discount_reason
            ,:special_message
            ,:is_holiday
            ,:created_at
            );
        ";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            // ':date'               => $data['date_from'],
            ':date_from'          => $data['date_from'],
            ':date_to'            => $data['date_to'],
            ':adult_price'        => (int)$data['adult_price'],
            ':child_price'        => (int)$data['child_price'],
            ':has_discount'       => (int)$data['has_discount'],
            ':discount_percentage'=> (int)$data['discount_percentage'],
            ':discount_reason'    => $data['discount_reason'],
            ':special_message'    => $data['special_message'],
            ':is_holiday'         => (int)$data['is_holiday'],
            ':created_at'         => date('Y-m-d H:i:s')
        ]);
    }

    // Get Base pricing
    public function getBasePriceModel(){
        $stmt = $this->conn->prepare("
            SELECT category, price
            FROM base_price
            WHERE active = 1
        ");
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'adult_price' => isset($rows['adult_price']) ? (int)$rows['adult_price'] : null,
            'childPrice' => isset($rows['child_price']) ? (int)$rows['child_price'] : null,
        ];

        // return [
        //     'adult_price' => isset($rows['adult_price']) ? (int)$rows['adult_price'] : null,
        //     'child_price' => isset($rows['child_price']) ? (int)$rows['child_price'] : null,
        // ];
    }

    // Update Base pricing
    public function updateBasePriceModel($data, $adminId){

        if (empty($data['ref']) || ! in_array($data['ref'], ['adult_price','child_price'], true)) {
            throw new InvalidArgumentException(
                'Invalid ref – must be "adult_price" or "child_price".'
            );
        }
        if (! isset($data['price']) || ! is_numeric($data['price'])) {
            throw new InvalidArgumentException('Please provide a numeric price.');
        }

        $category = $data['ref'];
        $newPrice = (int) $data['price'];

        // Wrap in a transaction so deactivate+insert are atomic
        $this->conn->beginTransaction();
        try {
            // 1) Deactivate previous for this category
            $stmt = $this->conn->prepare("
                UPDATE base_price
                SET active = 0
                WHERE category = :cat
            ");
            $stmt->execute([':cat' => $category]);

            // 2) Insert the new active price
            $stmt = $this->conn->prepare("
                INSERT INTO base_price
                (category, price, created_at, active)
                VALUES
                (:cat, :price, NOW(), 1)
            ");
            $stmt->execute([
                ':cat'   => $category,
                ':price' => $newPrice,
            ]);

            // 3) Log the change
            // $this->logAdminActivity(
            //     $adminId,
            //     'pricing_updated',
            //     ucfirst(str_replace('_',' ', $category))." set to {$newPrice}",
            //     null,
            //     null,
            //     $category
            // );

            $this->conn->commit();
            return true;
        } catch (\Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Get dashboard statistics
    public function getDashboardStats() {
        $stats = [];
        
        // Today's stats
        $today = date('Y-m-d');
        
        // Today's bookings and revenue
        $query = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(adult_tickets + child_tickets) as total_visitors,
                    SUM(total_amount) as total_revenue
                  FROM bookings 
                  WHERE date = ? AND status = 'confirmed'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$today]);
        $todayStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // This month's stats
        $thisMonth = date('Y-m');
        $query = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(adult_tickets + child_tickets) as total_visitors,
                    SUM(total_amount) as total_revenue
                  FROM bookings 
                  WHERE DATE_FORMAT(date, '%Y-%m') = ? AND status = 'confirmed'";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$thisMonth]);
        $monthStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Park capacity for today
        $query = "SELECT 
                    ps.max_capacity,
                    COALESCE(SUM(b.adult_tickets + b.child_tickets), 0) as current_bookings
                  FROM park_status ps
                  LEFT JOIN bookings b ON ps.date = b.date AND b.status = 'confirmed'
                  WHERE ps.date = ?
                  GROUP BY ps.date, ps.max_capacity";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$today]);
        $capacityStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Recent bookings
        $query = "SELECT b.*, u.name as user_name, u.email as user_email
                  FROM bookings b
                  JOIN users_for_rimona u ON b.user_id = u.id
                  ORDER BY b.created_at DESC
                  LIMIT 10";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Revenue trend (last 7 days)
        $query = "SELECT 
                    date,
                    COUNT(*) as bookings_count,
                    SUM(total_amount) as revenue
                  FROM bookings 
                  WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                    AND status = 'confirmed'
                  GROUP BY date
                  ORDER BY date";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $revenueTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'today' => $todayStats,
            'month' => $monthStats,
            'capacity' => $capacityStats ?: ['max_capacity' => 500, 'current_bookings' => 0],
            'recent_bookings' => $recentBookings,
            'revenue_trend' => $revenueTrend
        ];
    }

    // Get all bookings with filters
    public function getAllBookings($date = null, $status = null, $page = 1, $limit = 50) {
        $offset = ($page - 1) * $limit;
        $whereConditions = [];
        $params = [];
        
        if ($date) {
            $whereConditions[] = "b.date = ?";
            $params[] = $date;
        }
        
        if ($status) {
            $whereConditions[] = "b.status = ?";
            $params[] = $status;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total 
                       FROM bookings b 
                       JOIN users_for_rimona u ON b.user_id = u.id 
                       $whereClause";
        $countStmt = $this->conn->prepare($countQuery);
        $countStmt->execute($params);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get bookings
        $query = "SELECT b.*, u.name as user_name, u.email as user_email, u.phone as user_phone
                  FROM bookings b
                  JOIN users_for_rimona u ON b.user_id = u.id
                  $whereClause
                  ORDER BY b.created_at DESC
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'bookings' => $bookings,
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalCount / $limit)
        ];
    }

    // Get booking details with user info
    public function getBookingDetails($bookingId) {
        $query = "SELECT b.*, u.name as user_name, u.email as user_email, u.phone as user_phone,
                         dp.is_holiday, dp.has_discount, dp.discount_percentage
                  FROM bookings b
                  JOIN users_for_rimona u ON b.user_id = u.id
                  LEFT JOIN daily_pricing dp ON b.date = dp.date
                  WHERE b.booking_id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$bookingId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Admin cancel booking
    public function cancelBooking($bookingId, $adminId, $reason) {
        $this->conn->beginTransaction();
        
        try {
            // Update booking status
            $query = "UPDATE bookings 
                      SET status = 'cancelled', 
                          cancelled_at = NOW(),
                          cancellation_reason = ?
                      WHERE booking_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$reason, $bookingId]);
            
            // Log admin activity
            $this->logAdminActivity($adminId, 'booking_cancelled', "Cancelled booking $bookingId", null, $bookingId);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Get pricing for specific date
    public function getPricingForDate($date) {
        $query = "SELECT * FROM daily_pricing WHERE date = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$date]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get pricing for date range
    public function getPricingRange($startDate, $endDate) {
        $query = "SELECT * FROM daily_pricing 
                  WHERE date BETWEEN ? AND ? 
                  ORDER BY date";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$startDate, $endDate]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Update pricing
    public function updatePricing($data, $adminId) {
        $this->conn->beginTransaction();
        
        try {
            $query = "INSERT INTO daily_pricing 
                      (date, adult_price, child_price, has_discount, discount_percentage, discount_reason, special_message, is_holiday)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE
                      adult_price = VALUES(adult_price),
                      child_price = VALUES(child_price),
                      has_discount = VALUES(has_discount),
                      discount_percentage = VALUES(discount_percentage),
                      discount_reason = VALUES(discount_reason),
                      special_message = VALUES(special_message),
                      is_holiday = VALUES(is_holiday),
                      updated_at = NOW()";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $data['date'],
                $data['adult_price'],
                $data['child_price'],
                $data['has_discount'] ?? 0,
                $data['discount_percentage'] ?? 0,
                $data['discount_reason'] ?? null,
                $data['special_message'] ?? null,
                $data['is_holiday'] ?? 0
            ]);
            
            // Log admin activity
            $this->logAdminActivity($adminId, 'pricing_updated', "Updated pricing for {$data['date']}", null, null, $data['date']);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Update park status
    public function updateParkStatus($data, $adminId) {
        $this->conn->beginTransaction();
        
        try {
            $query = "INSERT INTO park_status 
                      (date, is_open, closure_reason, max_capacity, has_special_event, special_event_details)
                      VALUES (?, ?, ?, ?, ?, ?)
                      ON DUPLICATE KEY UPDATE
                      is_open = VALUES(is_open),
                      closure_reason = VALUES(closure_reason),
                      max_capacity = VALUES(max_capacity),
                      has_special_event = VALUES(has_special_event),
                      special_event_details = VALUES(special_event_details),
                      updated_at = NOW()";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $data['date'],
                $data['is_open'] ? 1 : 0,
                $data['closure_reason'] ?? null,
                $data['max_capacity'] ?? 500,
                $data['has_special_event'] ?? 0,
                $data['special_event_details'] ?? null
            ]);
            
            // Log admin activity
            $status = $data['is_open'] ? 'opened' : 'closed';
            $this->logAdminActivity($adminId, 'park_status_updated', "Park $status for {$data['date']}", null, null, $data['date']);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Add rest time
    public function addRestTime($data, $adminId) {
        $this->conn->beginTransaction();
        
        try {
            $query = "INSERT INTO rest_times (date, start_time, end_time, reason, affects_booking)
                      VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $data['date'],
                $data['start_time'],
                $data['end_time'],
                $data['reason'],
                $data['affects_booking'] ?? 1
            ]);
            
            // Log admin activity
            $this->logAdminActivity($adminId, 'rest_time_added', "Added rest time for {$data['date']} ({$data['start_time']} - {$data['end_time']})", null, null, $data['date']);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Remove rest time
    public function removeRestTime($restTimeId, $adminId) {
        $this->conn->beginTransaction();
        
        try {
            // Get rest time details before deleting
            $query = "SELECT * FROM rest_times WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$restTimeId]);
            $restTime = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$restTime) {
                throw new Exception('Rest time not found');
            }
            
            // Delete rest time
            $query = "DELETE FROM rest_times WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$restTimeId]);
            
            // Log admin activity
            $this->logAdminActivity($adminId, 'rest_time_removed', "Removed rest time for {$restTime['date']} ({$restTime['start_time']} - {$restTime['end_time']})", null, null, $restTime['date']);
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Get revenue report
    public function getRevenueReport($startDate, $endDate, $groupBy = 'day') {
        $groupByClause = '';
        $selectClause = '';
        
        switch ($groupBy) {
            case 'month':
                $groupByClause = "DATE_FORMAT(date, '%Y-%m')";
                $selectClause = "DATE_FORMAT(date, '%Y-%m') as period";
                break;
            case 'year':
                $groupByClause = "DATE_FORMAT(date, '%Y')";
                $selectClause = "DATE_FORMAT(date, '%Y') as period";
                break;
            default: // day
                $groupByClause = "date";
                $selectClause = "date as period";
                break;
        }
        
        $query = "SELECT 
                    $selectClause,
                    COUNT(*) as total_bookings,
                    SUM(adult_tickets) as total_adults,
                    SUM(child_tickets) as total_children,
                    SUM(adult_tickets + child_tickets) as total_visitors,
                    SUM(subtotal) as total_subtotal,
                    SUM(service_fee) as total_service_fee,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_booking_value
                  FROM bookings 
                  WHERE date BETWEEN ? AND ? AND status = 'confirmed'
                  GROUP BY $groupByClause
                  ORDER BY period";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$startDate, $endDate]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get visitor statistics
    public function getVisitorStats($date, $period = 'month') {
        $dateCondition = '';
        
        switch ($period) {
            case 'day':
                $dateCondition = "date = '$date'";
                break;
            case 'week':
                $dateCondition = "YEARWEEK(date) = YEARWEEK('$date')";
                break;
            case 'year':
                $dateCondition = "YEAR(date) = YEAR('$date')";
                break;
            default: // month
                $dateCondition = "DATE_FORMAT(date, '%Y-%m') = DATE_FORMAT('$date', '%Y-%m')";
                break;
        }
        
        $query = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(adult_tickets) as total_adults,
                    SUM(child_tickets) as total_children,
                    SUM(adult_tickets + child_tickets) as total_visitors,
                    AVG(adult_tickets + child_tickets) as avg_group_size,
                    COUNT(DISTINCT user_id) as unique_customers
                  FROM bookings 
                  WHERE $dateCondition AND status = 'confirmed'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get users list
    public function getUsers($page = 1, $limit = 50, $search = null) {
        $offset = ($page - 1) * $limit;
        $whereClause = '';
        $params = [];
        
        if ($search) {
            $whereClause = "WHERE name LIKE ? OR email LIKE ?";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM users_for_rimona $whereClause";
        $countStmt = $this->conn->prepare($countQuery);
        $countStmt->execute($params);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get users
        $query = "SELECT id, name, email, phone, role, is_active, email_verified, created_at,
                         (SELECT COUNT(*) FROM bookings WHERE user_id = users_for_rimona.id) as total_bookings,
                         (SELECT SUM(total_amount) FROM bookings WHERE user_id = users_for_rimona.id AND status = 'confirmed') as total_spent
                  FROM users_for_rimona 
                  $whereClause
                  ORDER BY created_at DESC
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'users' => $users,
            'total' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalCount / $limit)
        ];
    }

    // Send notification to users
    public function sendNotification($data, $adminId) {
        $this->conn->beginTransaction();
        
        try {
            // Get target users
            $userIds = [];
            
            if (isset($data['target_users']) && is_array($data['target_users'])) {
                $userIds = $data['target_users'];
            } elseif ($data['target'] === 'all') {
                $query = "SELECT id FROM users_for_rimona WHERE is_active = 1";
                $stmt = $this->conn->prepare($query);
                $stmt->execute();
                $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } elseif ($data['target'] === 'recent_bookings') {
                $query = "SELECT DISTINCT user_id FROM bookings 
                          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                $stmt = $this->conn->prepare($query);
                $stmt->execute();
                $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            // Insert notifications
            $query = "INSERT INTO notifications (user_id, type, title, message, data, expires_at)
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            
            foreach ($userIds as $userId) {
                $stmt->execute([
                    $userId,
                    $data['type'],
                    $data['title'],
                    $data['message'],
                    isset($data['data']) ? json_encode($data['data']) : null,
                    $data['expires_at'] ?? null
                ]);
            }
            
            // Log admin activity
            $this->logAdminActivity($adminId, 'notification_sent', "Sent notification '{$data['title']}' to " . count($userIds) . " users");
            
            $this->conn->commit();
            return count($userIds);
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }

    // Log admin activity
    private function logAdminActivity($adminId, $activityType, $description, $oldValues = null, $affectedBookingId = null, $affectedDate = null) {
        $query = "INSERT INTO admin_activities 
                  (admin_id, activity_type, description, affected_date, affected_booking_id, old_values, ip_address, user_agent)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $adminId,
            $activityType,
            $description,
            $affectedDate,
            $affectedBookingId,
            $oldValues ? json_encode($oldValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}
?>
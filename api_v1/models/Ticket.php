<?php
require_once 'config/database.php';

class Ticket {
    private $conn;
    private $table = 'tickets';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Create ticket
    public function createTicket($data) {
        try {
            $query = "INSERT INTO " . $this->table . " 
                      (id, user_id, transaction_id, payu_id, visit_date, time_slot, 
                       adult_tickets, child_tickets, ticket_amount, service_fee, 
                       total_amount, status, created_at, qr_code, payment_details, 
                       special_instructions) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)";

            $stmt = $this->conn->prepare($query);
            return $stmt->execute([
                $data['id'],
                $data['user_id'],
                $data['transaction_id'],
                $data['payu_id'],
                $data['visit_date'],
                $data['time_slot'],
                $data['adult_tickets'],
                $data['child_tickets'],
                $data['ticket_amount'],
                $data['service_fee'],
                $data['total_amount'],
                $data['status'],
                $data['qr_code'],
                json_encode($data['payment_details']),
                $data['special_instructions']
            ]);
        } catch (Exception $e) {
            error_log("Error creating ticket: " . $e->getMessage());
            throw $e;
        }
    }

    // Get user tickets
    public function getUserTickets($userId) {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                      WHERE user_id = ? 
                      ORDER BY created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user tickets: " . $e->getMessage());
            return [];
        }
    }
}
?>
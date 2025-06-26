<?php
require_once 'models/Ticket.php';
require_once 'middleware/JWTMiddleware.php';

class TicketController {
    private $ticket;

    public function __construct() {
        $this->ticket = new Ticket();
    }

    // Create ticket
    public function createTicket() {
        try {
            $user = JWTMiddleware::verifyTokenAsArray();
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized'
                ]);
                return;
            }

            $data = json_decode(file_get_contents("php://input"), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid JSON data'
                ]);
                return;
            }

            $result = $this->ticket->createTicket($data);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Ticket created successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create ticket'
                ]);
            }

        } catch (Exception $e) {
            error_log("Create Ticket Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
    }

    // Get user tickets
    public function getUserTickets() {
        try {
            $user = JWTMiddleware::verifyTokenAsArray();
            if (!$user) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unauthorized'
                ]);
                return;
            }

            $tickets = $this->ticket->getUserTickets($user['id']);

            echo json_encode([
                'success' => true,
                'message' => 'Tickets retrieved successfully',
                'data' => $tickets
            ]);

        } catch (Exception $e) {
            error_log("Get User Tickets Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
    }
}
?>
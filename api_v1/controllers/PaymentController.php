<?php
require_once 'models/Payment.php';
require_once 'middleware/JWTMiddleware.php';

class PaymentController {
    private $payment;

    public function __construct() {
        try {
            $this->payment = new Payment();
        } catch (Exception $e) {
            error_log("Payment Controller constructor error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed'
            ]);
            exit();
        }
    }

    // In your controller:

    public function recheckTransaction(){
        try {
            // $user_data = JWTMiddleware::verifyToken();
            // if (!$user_data) {
            //     http_response_code(401);
            //     header('Content-Type: application/json');
            //     echo json_encode([
            //         'success' => false,
            //         'message' => 'Unauthorized'
            //     ]);
            //     return;
            // }

            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            if (empty($body['id']) || empty($body['transactionid']) || empty($body['phone_no'])) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields: id and transactionid'
                ]);
                return;
            }

            $data = [
                'id'             => trim($body['id']),
                'transactionid'  => trim($body['transactionid']),
                'phone_no'       => trim($body['phone_no'])
            ];

            $apiResponse = $this->payment->recheckTransactionModel($data);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'status'  => 'success',
                'message' => 'Payment status retrieved',
                'data'    => $apiResponse
            ]);

        } catch (\InvalidArgumentException $e) {
            // Bad input
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

        } catch (\RuntimeException $e) {
            // Upstream failure (cURL, JSON parse, etc)
            http_response_code(502);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);

        } catch (\Exception $e) {
            // Anything else
            error_log("Recheck Transaction Error: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Internal server error'
            ]);
        }
    }

}
?>
<?php
require_once 'config/database.php';
require_once 'config/conf.php';

class Payment {
    private $conn;
    private $table = 'payments';

    public function __construct() {
        try {
            $database = new Database();
            $this->conn = $database->getConnection();
            
            if (!$this->conn) {
                throw new Exception("Database connection failed");
            }
        } catch (Exception $e) {
            error_log("Payment model constructor error: " . $e->getMessage());
            throw $e;
        }
    }

    // In your Model class

    public function recheckTransactionModel(array $data){
        if (empty($data['id']) || empty($data['transactionid'])) {
            throw new \InvalidArgumentException('Both id and transactionid are required.');
        }

        $payload = [
            // 'client_id'      => QC_PAY['CLIENTID'], // Raimwna Client ID

            'phone_no'       => $data['phone_no'],
            'client_id'      => 'D1M5XZFC4G',  // CSB Client for testing only
            'transaction_id' => $data['id'],
            'transaction_no' => $data['transactionid'],
        ];

        //  $raw = implode('|', [
        //     $payload['transaction_no'],
        //     $payload['transaction_id'],
        //     QC_PAY['CLIENTID'],
        //     $payload['phone_no'],
        //     $payload['transaction_no'],
        // ]);

        // // error_log("QC_PAY hash string: $raw");          // debug
        
        // $payload['hash'] = hash('sha256', $raw);

        $stringToHash = $payload['transaction_no']  . "|" . $payload['client_id'] . "|". $payload['phone_no'] . "|". $payload['transaction_no'];

        // echo $stringToHash;
        // die;

        $payload['hash'] = hash("sha256", $stringToHash);

        $ch = curl_init(QC_PAY['RECHECK_URL']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authentication: ' . QC_PAY['API_TOKEN'],
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),

            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $resp = curl_exec($ch);

        // print_r($resp);
        // die;

        if ($err = curl_error($ch)) {
            curl_close($ch);
            throw new \RuntimeException("cURL error: $err");
        }
        curl_close($ch);

        $decoded = json_decode($resp, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON from payment API.');
        }

        return $decoded;
    }



}
?>
<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header('Content-Type: text/html; charset=UTF-8');

// Log function
function logPaymentResponse($message, $data = []) {
    $logFile = __DIR__ . '/payment_responses.log';
    $logEntry = date('Y-m-d H:i:s') . " - $message: " . json_encode($data) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

try {
    // Get payment response data
    $status = $_GET['status'] ?? 'unknown';
    $payuData = $_POST;

    logPaymentResponse("Payment response received", [
        'status' => $status,
        'get_data' => $_GET,
        'post_data' => $_POST
    ]);

    // PayU test credentials
    $salt = "b1cWBZS43gYiZ1ow4jzxxJKYz9FBdSVM";

    $paymentSuccess = false;
    $ticketData = null;
    $errorMessage = '';
    $txnId = '';
    $amount = 0;
    $payuId = '';
    $customerName = '';
    $customerEmail = '';

    if ($status === 'success' && !empty($payuData)) {
        // Extract basic data
        $txnId = $payuData['txnid'] ?? '';
        $amount = intval($payuData['amount'] ?? 0);
        $payuId = $payuData['mihpayid'] ?? '';
        $customerName = $payuData['firstname'] ?? '';
        $customerEmail = $payuData['email'] ?? '';

        logPaymentResponse("Processing successful payment", [
            'txnId' => $txnId,
            'amount' => $amount,
            'payuId' => $payuId
        ]);

        // Simple hash verification
        if (verifyPayUHash($payuData, $salt)) {
            // Create ticket
            $ticketResult = createSimpleTicket($payuData);
            if ($ticketResult['success']) {
                $paymentSuccess = true;
                $ticketData = $ticketResult['ticket'];
                logPaymentResponse("Ticket created successfully", $ticketData);
            } else {
                $errorMessage = $ticketResult['error'];
                logPaymentResponse("Ticket creation failed", ['error' => $errorMessage]);
            }
        } else {
            $errorMessage = 'Payment verification failed - hash mismatch';
            logPaymentResponse("Hash verification failed", ['provided_hash' => $payuData['hash'] ?? 'none']);
        }
    } else if ($status === 'failure') {
        $txnId = $payuData['txnid'] ?? '';
        $errorMessage = $payuData['Error_Message'] ?? $payuData['error_Message'] ?? 'Payment failed';
        logPaymentResponse("Payment failure", ['txnId' => $txnId, 'error' => $errorMessage]);
    } else {
        $errorMessage = 'Invalid payment response';
        logPaymentResponse("Invalid response", ['status' => $status, 'post_empty' => empty($payuData)]);
    }

} catch (Exception $e) {
    logPaymentResponse("Exception occurred", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    $errorMessage = 'Server error: ' . $e->getMessage();
}

function verifyPayUHash($data, $salt) {
    try {
        if (empty($data['hash'])) {
            return false;
        }
        
        // PayU reverse hash sequence
        $hashSequence = [
            $salt,
            $data['status'] ?? '',
            '', '', '', '', '', // Empty fields
            $data['udf5'] ?? '',
            $data['udf4'] ?? '',
            $data['udf3'] ?? '',
            $data['udf2'] ?? '',
            $data['udf1'] ?? '',
            $data['email'] ?? '',
            $data['firstname'] ?? '',
            $data['productinfo'] ?? '',
            $data['amount'] ?? '',
            $data['txnid'] ?? '',
            $data['key'] ?? ''
        ];
        
        $hashString = implode('|', $hashSequence);
        $calculatedHash = hash('sha512', $hashString);
        
        logPaymentResponse("Hash verification", [
            'calculated' => $calculatedHash,
            'provided' => $data['hash'],
            'match' => strtolower($calculatedHash) === strtolower($data['hash'])
        ]);
        
        return strtolower($calculatedHash) === strtolower($data['hash']);
    } catch (Exception $e) {
        logPaymentResponse("Hash verification error", ['error' => $e->getMessage()]);
        return false;
    }
}

function createSimpleTicket($payuData) {
    try {
        // Extract data
        $txnId = $payuData['txnid'] ?? '';
        $payuId = $payuData['mihpayid'] ?? '';
        $amount = intval($payuData['amount'] ?? 0);
        $customerName = $payuData['firstname'] ?? '';
        $customerEmail = $payuData['email'] ?? '';
        $customerPhone = $payuData['phone'] ?? '';
        
        // Generate ticket ID
        $ticketId = 'TKT' . time() . rand(1000, 9999);
        
        // Generate simple QR code data
        $qrData = [
            'ticketId' => $ticketId,
            'txnId' => $txnId,
            'amount' => $amount,
            'visitDate' => date('Y-m-d', strtotime('+1 day')),
            'timestamp' => time()
        ];
        $qrCode = base64_encode(json_encode($qrData));
        
        // Prepare ticket data
        $ticketData = [
            'ticketId' => $ticketId,
            'txnId' => $txnId,
            'payuId' => $payuId,
            'amount' => $amount,
            'customerName' => $customerName,
            'customerEmail' => $customerEmail,
            'customerPhone' => $customerPhone,
            'visitDate' => date('Y-m-d', strtotime('+1 day')),
            'timeSlot' => $payuData['udf3'] ?? 'Full Day',
            'qrCode' => $qrCode,
            'status' => 'confirmed',
            'createdAt' => date('Y-m-d H:i:s')
        ];
        
        // Try to save to database (with fallback)
        $dbSaved = saveTicketToDatabase($ticketData);
        
        // Always save to file for Flutter to retrieve
        $resultFile = __DIR__ . "/payment_success_{$txnId}.json";
        file_put_contents($resultFile, json_encode([
            'success' => true,
            'ticketData' => $ticketData,
            'dbSaved' => $dbSaved,
            'timestamp' => time()
        ]));
        
        return [
            'success' => true,
            'ticket' => $ticketData
        ];
        
    } catch (Exception $e) {
        logPaymentResponse("Ticket creation error", ['error' => $e->getMessage()]);
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function saveTicketToDatabase($ticketData) {
    try {
        // Database connection - adjust these credentials
        $host = 'mysql.api.bodolandtransport.com';
        $dbname = 'db_bts'; 
        $username = 'bts_transport';
        $password = '14bC37q#$SAl';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "INSERT INTO tickets (
            id, user_id, transaction_id, payu_id, visit_date, time_slot,
            adult_tickets, child_tickets, ticket_amount, service_fee, total_amount,
            status, created_at, qr_code, customer_name, customer_email, customer_phone
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute([
            $ticketData['ticketId'],
            $ticketData['customerEmail'], // Using email as user_id
            $ticketData['txnId'],
            $ticketData['payuId'],
            $ticketData['visitDate'],
            $ticketData['timeSlot'],
            1, // adult_tickets
            0, // child_tickets
            $ticketData['amount'] - 30, // ticket_amount (minus service fee)
            30, // service_fee
            $ticketData['amount'],
            'confirmed',
            $ticketData['qrCode'],
            $ticketData['customerName'],
            $ticketData['customerEmail'],
            $ticketData['customerPhone']
        ]);
        
        return $success;
        
    } catch (Exception $e) {
        logPaymentResponse("Database save error", ['error' => $e->getMessage()]);
        return false; // Don't fail the whole process if DB save fails
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment <?php echo ucfirst($status); ?> - Raimona Water Park</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0; padding: 20px; 
            background: linear-gradient(135deg, #2E86AB 0%, #A23B72 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container { 
            max-width: 500px; 
            background: white; 
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
        }
        .success-icon { color: #28a745; font-size: 64px; margin-bottom: 20px; }
        .failure-icon { color: #dc3545; font-size: 64px; margin-bottom: 20px; }
        .logo { font-size: 32px; margin-bottom: 20px; }
        h1 { margin-bottom: 20px; }
        .success { color: #28a745; }
        .failure { color: #dc3545; }
        .ticket-info {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin: 25px 0;
            text-align: left;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 12px 0;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-weight: bold; color: #6c757d; }
        .info-value { color: #495057; font-weight: 600; }
        .btn { 
            background: linear-gradient(135deg, #2E86AB, #A23B72);
            color: white; 
            padding: 15px 30px; 
            border: none; 
            border-radius: 10px; 
            text-decoration: none; 
            display: inline-block; 
            margin: 15px; 
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .countdown { margin-top: 30px; font-size: 14px; color: #6c757d; }
        .debug-info { 
            background: #f8f9fa; 
            padding: 15px; 
            border-radius: 8px; 
            margin-top: 20px; 
            text-align: left; 
            font-size: 12px; 
            color: #6c757d;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🌊</div>
        
        <?php if ($paymentSuccess): ?>
            <div class="success-icon">✅</div>
            <h1 class="success">Booking Confirmed!</h1>
            <p>Your Raimona Water Park ticket has been booked successfully.</p>
            
            <div class="ticket-info">
                <div class="info-row">
                    <span class="info-label">Ticket ID:</span>
                    <span class="info-value"><?php echo htmlspecialchars($ticketData['ticketId']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Transaction ID:</span>
                    <span class="info-value"><?php echo htmlspecialchars($ticketData['txnId']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Amount Paid:</span>
                    <span class="info-value">₹<?php echo $ticketData['amount']; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Customer:</span>
                    <span class="info-value"><?php echo htmlspecialchars($ticketData['customerName']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Visit Date:</span>
                    <span class="info-value"><?php echo $ticketData['visitDate']; ?></span>
                </div>
            </div>
            
        <?php else: ?>
            <div class="failure-icon">❌</div>
            <h1 class="failure">Payment <?php echo ucfirst($status); ?></h1>
            <p><?php echo htmlspecialchars($errorMessage); ?></p>
            
            <?php if (!empty($txnId)): ?>
            <div class="ticket-info">
                <div class="info-row">
                    <span class="info-label">Transaction ID:</span>
                    <span class="info-value"><?php echo htmlspecialchars($txnId); ?></span>
                </div>
                <?php if ($amount > 0): ?>
                <div class="info-row">
                    <span class="info-label">Amount:</span>
                    <span class="info-value">₹<?php echo $amount; ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <button onclick="closeWindow()" class="btn">
            Return to App
        </button>
        
        <div class="countdown">
            This window will close automatically in <span id="countdown">15</span> seconds
        </div>
        
        <!-- Debug info (hidden by default) -->
        <div class="debug-info" id="debugInfo">
            <strong>Debug Info:</strong><br>
            Status: <?php echo htmlspecialchars($status); ?><br>
            PayU Data: <?php echo htmlspecialchars(json_encode($_POST)); ?><br>
            <?php if (isset($errorMessage)): ?>
            Error: <?php echo htmlspecialchars($errorMessage); ?><br>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Countdown timer
        let countdown = 15;
        const timer = setInterval(() => {
            countdown--;
            document.getElementById('countdown').textContent = countdown;
            if (countdown <= 0) {
                clearInterval(timer);
                closeWindow();
            }
        }, 1000);
        
        function closeWindow() {
            // Notify parent window if exists
            if (window.opener) {
                window.opener.postMessage({
                    type: 'PAYMENT_COMPLETE',
                    success: <?php echo $paymentSuccess ? 'true' : 'false'; ?>,
                    txnId: '<?php echo htmlspecialchars($txnId); ?>',
                    <?php if ($paymentSuccess): ?>
                    ticketData: <?php echo json_encode($ticketData); ?>
                    <?php endif; ?>
                }, '*');
            }
            
            // Set localStorage for polling fallback
            <?php if (!empty($txnId)): ?>
            localStorage.setItem('payment_result_<?php echo htmlspecialchars($txnId); ?>', JSON.stringify({
                success: <?php echo $paymentSuccess ? 'true' : 'false'; ?>,
                timestamp: Date.now(),
                <?php if ($paymentSuccess): ?>
                ticketData: <?php echo json_encode($ticketData); ?>
                <?php endif; ?>
            }));
            <?php endif; ?>
            
            // Close window
            setTimeout(() => {
                window.close();
            }, 500);
        }
        
        // Show debug info on double click
        document.addEventListener('dblclick', function() {
            const debugInfo = document.getElementById('debugInfo');
            debugInfo.style.display = debugInfo.style.display === 'none' ? 'block' : 'none';
        });
    </script>
</body>
</html>
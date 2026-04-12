<?php
// Gemini code 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

include_once "../connect.php";

$input = file_get_contents('php://input');

if (empty($input)) {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
    exit;
}

// FIX 1: Removed the 'echo' from this line
$data = json_decode($input, true);

$name = $data['name'];
$email = $data['email'];
$mobile = $data['mobile'];
$experience = $data['experience'];
$position = $data["position"];
$location = $data['location'];

if (
    empty($data['name']) ||
    empty($data['email']) ||
    empty($data['mobile']) ||
    empty($data['experience']) ||
    empty($data['position']) ||
    empty($data['location']) ||
    !is_numeric($data['mobile'])
) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing fields']);
    exit;
} else {
    $sql = "INSERT INTO `pending_app` (`name`, `email`, `mobile`, `experience`, `position`, `location`, `pay_status`) VALUES ('{$name}', '{$email}', '{$mobile}', '{$experience}', '{$position}', '{$location}', 'PENDING')";

    if (mysqli_query($conn, $sql)) {
        $id = mysqli_insert_id($conn);
        // ------------------- FUNCTIONS ----------------------

        function generateUniqueOrderId($prefix = "Stremax")
        {
            return $prefix . time() . rand(100, 999);
        }

        function logMessage($message)
        {
            $logFile = __DIR__ . "/api_log.txt";
            $logEntry = "[" . date("Y-m-d H:i:s") . "] " . $message . PHP_EOL;
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }

        function createCashfreeOrder($id, $name, $mobile, $email, $amount)
        {
            $appId = 'TEST10391805bba611f4daa459b772ff50819301';
            $secretKey = 'cfsk_ma_test_ae7f2166e630e0a21002d246c553a813_84981a2a';
            $orderId = generateUniqueOrderId();

            $url = "https://sandbox.cashfree.com/pg/orders";

            $payload = [
                "order_id" => $orderId,
                "order_amount" => $amount,
                "order_currency" => "INR",
                "order_note" => "Form Payment",
                "customer_details" => [
                    "customer_id" => (string) $id,
                    "customer_phone" => $mobile,
                    "customer_email" => $email,
                    "customer_name" => $name
                ],
                "order_meta" => [
                    // Production url
                    //"return_url" => "https://stremaxfoundation.org/assets/cashfree/verify.html?txnId={order_id}"
                    "return_url" => "https://elle-noisy-carelessly.ngrok-free.dev/stremax-hiring/assets/cashfree/verify.html?txnId={order_id}"
                ]
            ];

            $headers = [
                "Content-Type: application/json",
                "x-client-id: $appId",
                "x-client-secret: $secretKey",
                "x-api-version: 2023-08-01" // Updated to a stable version
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                logMessage("CURL ERROR: " . curl_error($ch));
            }

            // Modern way to close
            unset($ch);

            return json_decode($response, true);
        }

        // ------------------- MAIN EXECUTION ----------------------

        $name = $data['name'];
        $mobile = $data['mobile'];
        $amount = $data['amount'] ?? 350; // Added fallback amount

        $response = createCashfreeOrder($id, $name, $mobile, $email, $amount);
        logMessage("PAYMENT REQUEST: " . json_encode($response));

        // Check for success
        if (isset($response['payment_session_id'])) {
            echo json_encode([
                "success" => true,
                "order_id" => $response['order_id'],
                "payment_session_id" => $response['payment_session_id'],
                "redirect_url" => $response['payments']['url'] ?? "https://sandbox.cashfree.com" . $response['payment_session_id']
            ]);
            exit;
        }
        // Error handling
        echo json_encode([
            "success" => false,
            "error_code" => $response['code'] ?? 'unknown_error',
            "error_message" => $response['message'] ?? 'Unexpected error from Cashfree',
            "raw" => $response // Useful for debugging
        ]);
    } else {
        echo json_encode([
            'message' => 'Failed to initialize payment: ' . mysqli_error($conn),
        ]);
    }
}




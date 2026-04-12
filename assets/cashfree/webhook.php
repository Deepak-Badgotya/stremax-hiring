<?php
require_once "../connect.php";

$clientSecret = "cfsk_ma_test_ae7f2166e630e0a21002d246c553a813_84981a2a";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $headers = getallheaders();
    $rawBody = file_get_contents("php://input");

    $receivedSignature = $headers['x-webhook-signature'] ?? $headers['X-Webhook-Signature'] ?? '';
    $receivedTimestamp = $headers['x-webhook-timestamp'] ?? $headers['X-Webhook-Timestamp'] ?? '';

    // Verify Signature
    $payload = (string) $receivedTimestamp . $rawBody;
    $generatedSignature = base64_encode(hash_hmac('sha256', $payload, $clientSecret, true));

    if ($generatedSignature !== $receivedSignature) {
        http_response_code(401);
        exit("Invalid Signature");
    }

    $postData = json_decode($rawBody, true);
    $eventType = $postData['type'] ?? '';
    $order_id = $postData['data']['order']['order_id'] ?? null;
    $customer_id = $postData['data']['customer_details']['customer_id'] ?? null;
    $payment_status = $postData['data']['order']['order_status'] ?? 'UNKNOWN';

    // Validate required fields
    if (!$order_id || !$customer_id) {
        file_put_contents("webhook_error.log", date('Y-m-d H:i:s') . " - Missing order_id or customer_id\n", FILE_APPEND);
        http_response_code(200);
        echo "Missing data but acknowledged";
        exit;
    }

    // Check current status in database
    $checkStmt = $conn->prepare("SELECT pay_status FROM pending_app WHERE pay_id = ?");
    $checkStmt->bind_param("i", $customer_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $currentData = $result->fetch_assoc();
    $currentStatus = $currentData['pay_status'] ?? null;
    $checkStmt->close();

    // If already PAID, exit immediately
    if ($currentStatus === 'PAID') {
        file_put_contents("webhook.log", date('Y-m-d H:i:s') . " - Order $order_id already PAID. Ignoring webhook: $eventType\n", FILE_APPEND);
        http_response_code(200);
        echo "Already PAID - Ignored";
        exit;
    }

    // Handle different event types
    switch ($eventType) {
        case 'PAYMENT_SUCCESS_WEBHOOK':
            // Update database to PAID
            $stmt = $conn->prepare("UPDATE pending_app SET pay_status = ?, trans_id = ? WHERE pay_id = ?");
            $finalStatus = 'PAID';
            $stmt->bind_param("ssi", $finalStatus, $order_id, $customer_id);

            if ($stmt->execute()) {
                file_put_contents("webhook.log", date('Y-m-d H:i:s') . " - Order $order_id updated to PAID\n", FILE_APPEND);

                // AFTER SUCCESSFUL UPDATE, PULL THE DATA AND DISTRIBUTE TO OTHER TABLES
                $distributionResult = distributePaidData($conn, $customer_id, $order_id);

                http_response_code(200);
                echo "OK - Status updated to PAID and data distributed";
            } else {
                file_put_contents("webhook_error.log", date('Y-m-d H:i:s') . " - DB Update Failed: " . $stmt->error . "\n", FILE_APPEND);
                http_response_code(200);
                echo "DB Error but acknowledged";
            }
            $stmt->close();
            break;

        default:
            // Log other events but don't update database
            file_put_contents("webhook.log", date('Y-m-d H:i:s') . " - Event $eventType received for Order $order_id (not updating DB)\n", FILE_APPEND);
            http_response_code(200);
            echo "Event logged - No DB update";
            break;
    }
}

/**
 * Function to pull PAID status data and distribute to DCO or BCO tables
 */
function distributePaidData($conn, $customer_id, $order_id)
{
    // STEP 1: Pull the complete row data from pending_app
    $fetchStmt = $conn->prepare("SELECT * FROM pending_app WHERE pay_id = ? AND pay_status = 'PAID'");
    $fetchStmt->bind_param("i", $customer_id);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    $rowData = $result->fetch_assoc();
    $fetchStmt->close();

    // Log to update distribution status
    if (!$rowData) {
        file_put_contents("webhook_distribution.log", date('Y-m-d H:i:s') . " - No PAID data found for customer_id: $customer_id\n", FILE_APPEND);
        return false;
    }

    // Log the pulled data
    file_put_contents("webhook_distribution.log", date('Y-m-d H:i:s') . " - Data pulled for customer_id: $customer_id\n", FILE_APPEND);
    file_put_contents("webhook_distribution.log", "Order ID: $order_id\n", FILE_APPEND);

    // STEP 2: Determine position type (DCO or BCO)
    // Adjust the field name based on your actual schema
    $position = $rowData['position'];

    $distributionStatus = false;

    if (strtoupper($position) === 'DCO' || stripos($position, 'DCO') !== false) {
        // Distribute to dis_co table (DCO)
        $distributionStatus = insertIntoDisCO($conn, $rowData);
        file_put_contents("webhook_distribution.log", date('Y-m-d H:i:s') . " - Data sent to dis_co table (DCO)\n", FILE_APPEND);
    } elseif (strtoupper($position) === 'BCO' || stripos($position, 'BCO') !== false) {
        // Distribute to blo_co table (BCO)
        $distributionStatus = insertIntoBloCO($conn, $rowData);
        file_put_contents("webhook_distribution.log", date('Y-m-d H:i:s') . " - Data sent to blo_co table (BCO)\n", FILE_APPEND);
    } else {
        // If position type not found, log error
        file_put_contents("webhook_distribution.log", date('Y-m-d H:i:s') . " - Unknown position type: $position for customer_id: $customer_id\n", FILE_APPEND);
        return false;
    }

    // STEP 3: Update pending_app to mark that data has been distributed
    if ($distributionStatus) {
        $updateStmt = $conn->prepare("UPDATE pending_app SET data_distributed = 1, distributed_at = CURRENT_TIMESTAMP WHERE pay_id = ?");
        $updateStmt->bind_param("i", $customer_id);

        if ($updateStmt->execute()) {
            file_put_contents("webhook_distribution.log", date('Y-m-d H:i:s') . " - Distribution completed successfully for customer_id: $customer_id\n", FILE_APPEND);

            // AFTER SUCCESSFUL DISTRIBUTION MAIL THE PROSPECTUS TO APPLICANT
            $emailResult = prospectusEmail($conn, $customer_id, $order_id, $position);

            if ($emailResult) {
                file_put_contents("webhook_distribution.log", date('Y-m-d H:i:s') . " - Email sent successfully for customer_id: $customer_id\n", FILE_APPEND);
            } else {
                file_put_contents("webhook_distribution.log", date('Y-m-d H:i:s') . " - Email failed for customer_id: $customer_id\n", FILE_APPEND);
            }

            /* http_response_code(200);
            echo "OK - Status updated to PAID and data distributed"; */
        } else {
            file_put_contents("webhook_distribution.log", date('Y-m-d H:i:s') . " - Distribution Failed: " . $updateStmt->error . "for customer_id: $customer_id\n", FILE_APPEND);
            /* http_response_code(200);
            echo "DB Error but acknowledged"; */
        }
        $updateStmt->close();
        return true;
    }

    return false;
}

/**
 * Insert data into dis_co table (for DCO)
 */
function insertIntoDisCO($conn, $data)
{
    try {
        // Adjust these column names based on your actual dis_co table structure
        $stmt = $conn->prepare("
            INSERT INTO dis_co (
                name,
                email,
                mobile,
                experience,
                d_id,
                pay_id
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        // Extract data from the pulled row (adjust field names as per your schema)
        $name = $data['name'];
        $email = $data['email'];
        $mobile = $data['mobile'];
        $experience = $data['experience'];
        $d_id = $data['location'];
        $pay_id = $data['pay_id'];

        $stmt->bind_param(
            "ssssss",
            $name,
            $email,
            $mobile,
            $experience,
            $d_id,
            $pay_id
        );

        $result = $stmt->execute();
        $stmt->close();

        return $result;

    } catch (Exception $e) {
        file_put_contents("webhook_distribution.log", date('Y-m-d H:i:s') . " - dis_co insert error: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

/**
 * Insert data into blo_co table (for BCO)
 */
function insertIntoBloCO($conn, $data)
{
    try {
        // Adjust these column names based on your actual blo_co table structure
        $stmt = $conn->prepare("
            INSERT INTO blo_co (
                name,
                email,
                mobile,
                experience,
                b_id,
                pay_id
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        // Extract data from the pulled row (adjust field names as per your schema)
        $name = $data['name'];
        $email = $data['email'];
        $mobile = $data['mobile'];
        $experience = $data['experience'];
        $b_id = $data['location'];
        $pay_id = $data['pay_id'];

        $stmt->bind_param(
            "ssssss",
            $name,
            $email,
            $mobile,
            $experience,
            $b_id,
            $pay_id
        );

        $result = $stmt->execute();
        $stmt->close();

        return $result;

    } catch (Exception $e) {
        file_put_contents("webhook_distribution.log", date('Y-m-d H:i:s') . " - blo_co insert error: " . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

// Mail the prospectus to the applicant 
function prospectusEmail($conn, $customer_id, $order_id, $position)
{
    // Fetch user data from pending_app
    $fetchStmt = $conn->prepare("SELECT * FROM pending_app WHERE pay_id = ? AND pay_status = 'PAID'");
    $fetchStmt->bind_param("i", $customer_id);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    $userData = $result->fetch_assoc();
    $fetchStmt->close();

    if (!$userData) {
        file_put_contents("email_log.log", date('Y-m-d H:i:s') . " - No data found for customer_id: $customer_id\n", FILE_APPEND);
        return false;
    }

    // Extract user details
    $recEmail = $userData['email'];
    $recName = $userData['name'];

    if (empty($recEmail)) {
        file_put_contents("email_log.log", date('Y-m-d H:i:s') . " - No email found for customer_id: $customer_id\n", FILE_APPEND);
        return false;
    }

    $positionType = ($position == 'DCO') ? "District Coordinator" : "Block Coordinator";

    // Include the mail function file
    require_once "mail.php";

    // Call the function with all three required parameters
    $sendStatus = sendCustomEmail($recEmail, $recName, $positionType);

    // Log the result
    if ($sendStatus === true) {
        file_put_contents("email_log.log", date('Y-m-d H:i:s') . " - Email sent successfully to $recEmail for customer_id: $customer_id\n", FILE_APPEND);
        return true;
    } else {
        file_put_contents("email_log.log", date('Y-m-d H:i:s') . " - Email failed for $recEmail: " . $sendStatus . "\n", FILE_APPEND);
        return false;
    }
}
// Close connection
$conn->close();

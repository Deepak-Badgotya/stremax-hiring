<?php
// test_email.php
require_once "../connect.php";

$customer_id = 1; // Replace with actual customer ID
$order_id = "TEST_ORDER_123";
$positionType = ($position == 'DCO') ? "District Coordinator" : "Block Coordinator"; // or "BCO"
$recMail = "digexpoitsolutions@gmail.com";
$recName = "Deepak Singh";
include_once "mail.php";

$result = sendPaymentConfirmationEmail($conn, $customer_id, $order_id, $positionType);

if ($result) {
    echo "Email sent successfully!";
} else {
    echo "Email failed. Check logs.";
}
?>
<?php
header("Content-Type: application/json");
//Connecting older stremax data for test
include_once "connect.php";

// Getting blocks of districts ========
$d_id = isset($_GET["d_id"]) ? (int) $_GET["d_id"] : 0;

if ($d_id > 0) {
    $sql = "SELECT b.* FROM block b LEFT JOIN bco b4 ON b.b_id = b4.b_id WHERE b.d_id = $d_id AND b4.b_id IS NULL";
    $result = $conn->query($sql);

    $blocks = [];
    while ($row = $result->fetch_assoc()) {
        $blocks[] = $row;
    }

    echo json_encode($blocks);
} else {
    echo json_encode([]);
}

//Always close connection
mysqli_close($conn);
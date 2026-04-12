<?php
header("Content-Type: application/json");
//Connecting older stremax data for test
include_once "connect.php"; 

// Getting districts of state ========
$st_id = isset($_GET["st_id"]) ? (int) $_GET["st_id"] : 0;
$exclude  = $_GET['exclude'] ?? 'false';

if ($st_id > 0) {
    if ($exclude === "true") {
        $sql = "SELECT d.* FROM district d LEFT JOIN dco d4 ON d.d_id = d4.d_id WHERE d.st_id = $st_id AND d4.d_id IS NULL";
    } else {
        $sql = "SELECT d_id, dname FROM district WHERE st_id = $st_id";
    }
    
    $result = $conn->query($sql);

    $districts = [];
    while ($row = $result->fetch_assoc()) {
        $districts[] = $row;
    }

    echo json_encode($districts);
} else {
    echo json_encode([]);
}

//Always close connection
mysqli_close($conn);

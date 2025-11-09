<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

$sql = "SELECT facultyID, facultyName FROM faculty";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $faculties = [];
    while ($row = $result->fetch_assoc()) {
        $faculties[] = $row;
    }
    echo json_encode($faculties);
} else {
    echo json_encode([]);
}

$conn->close();
?>
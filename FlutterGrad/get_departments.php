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

if (!isset($_GET['facultyID'])) {
    echo json_encode(["success" => false, "message" => "Faculty parameter is required"]);
    exit();
}

$facultyID = $conn->real_escape_string($_GET['facultyID']);
$sql = "SELECT departmentID, departmentName FROM department WHERE facultyID = '$facultyID'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $departments = [];
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
    echo json_encode($departments);
} else {
    echo json_encode([]);
}

$conn->close();
?>
<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// DB config
$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

// Connect to DB
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]);
    exit();
}

// Get input
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->activityID) || !isset($data->status)) {
    echo json_encode(["status" => "error", "message" => "Missing required parameters."]);
    exit();
}

$activityID = $conn->real_escape_string($data->activityID);
$status = $conn->real_escape_string($data->status);

// Update query
$sql = "UPDATE activities SET status = '$status' WHERE activityID = '$activityID'";

if ($conn->query($sql) === TRUE) {
    echo json_encode(["status" => "success", "message" => "Activity status updated."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update: " . $conn->error]);
}

$conn->close();
?>

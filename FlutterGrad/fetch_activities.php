<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

// Connect to the database
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

// Automatically mark expired activities as Done
$conn->query("UPDATE activities SET status='Done' WHERE expiryDate < CURDATE() AND status = 'Pending'");

// Fetch activities
$sql = "SELECT activityID, activityName, activityHostID, activityDate, expiryDate, CONTENT, status FROM activities ORDER BY activityDate DESC";
$result = $conn->query($sql);

$activities = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
}

echo json_encode(["status" => "success", "activities" => $activities]);
?>

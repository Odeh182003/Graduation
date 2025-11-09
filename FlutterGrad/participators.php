<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["activityID"]) || !isset($data["userID"])) {
    echo json_encode(["status" => "error", "message" => "Missing activityID or userID"]);
    exit;
}

$activityID = $data["activityID"];
$userID = $data["userID"];

// Check if the user is the creator of the activity
$checkSql = "SELECT activityHostID FROM activities WHERE activityID = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("i", $activityID);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Activity not found"]);
    exit;
}

$row = $checkResult->fetch_assoc();
if ($row["activityHostID"] != $userID) {
    echo json_encode(["status" => "error", "message" => "You are not authorized to view participators"]);
    exit;
}

$sql = "SELECT ua.userID, u.username, ua.created_at
        FROM useractivities ua
        JOIN users u ON ua.userID = u.universityID
        WHERE ua.activityID = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $activityID);
$stmt->execute();
$result = $stmt->get_result();

$participators = [];
while ($row = $result->fetch_assoc()) {
    $participators[] = $row;
}

echo json_encode([
    "status" => "success",
    "participators" => $participators
]);

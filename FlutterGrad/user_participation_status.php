<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

// Connect
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

if (!isset($_GET['userID'])) {
    echo json_encode(["success" => false, "error" => "Missing userID parameter"]);
    exit;
}

$userID = intval($_GET['userID']);

$stmt = $conn->prepare("
    SELECT 
        activityID, 
        status,
        rejection_reason,
        rejection_count
    FROM participation_requests 
    WHERE userID = ?
    UNION ALL
    SELECT 
        activityID, 
        'participated' AS status,
        NULL AS rejection_reason,
        0 AS rejection_count
    FROM useractivities 
    WHERE userID = ?
");
$stmt->bind_param("ii", $userID, $userID);
$stmt->execute();
$result = $stmt->get_result();

$statuses = [];
while ($row = $result->fetch_assoc()) {
    $activityID = $row['activityID'];
    $statuses[$activityID] = [
        'status' => $row['status'],
        'rejection_reason' => $row['rejection_reason'],
        'rejection_count' => intval($row['rejection_count'])
    ];
}

echo json_encode([
    'status' => 'success',
    'statuses' => $statuses
]);
?>
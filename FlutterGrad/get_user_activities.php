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
    SELECT a.activityID, a.activityName, a.activityDate 
    FROM activities a
    JOIN useractivities ua ON a.activityID = ua.activityID
    WHERE ua.userID = ?
    ORDER BY a.activityDate ASC
");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();

$activities = [];
while ($row = $result->fetch_assoc()) {
    $activities[] = $row;
}

echo json_encode([
    'success' => true,
    'activities' => $activities
]);
?>

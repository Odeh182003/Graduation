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
$activityID = intval($_GET['activityID']);

$stmt = $conn->prepare("SELECT pr.id as requestID, pr.userID, u.username, pr.status, pr.created_at
                        FROM participation_requests pr
                        JOIN users u ON pr.userID = u.universityID
                        WHERE pr.activityID=? AND pr.status='pending'");
$stmt->bind_param("i", $activityID);
$stmt->execute();
$result = $stmt->get_result();

$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

echo json_encode(['status' => 'success', 'requests' => $requests]);
?>
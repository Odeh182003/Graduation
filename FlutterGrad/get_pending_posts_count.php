<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed"]));
}

$reviewerId = $_GET['reviewerId'] ?? '';

if (empty($reviewerId)) {
    echo json_encode(["error" => "Missing reviewerId"]);
    exit;
}

$sql = "SELECT COUNT(*) AS count FROM posts WHERE APPROVALSTATUS = 'pending' AND reviewedby = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $reviewerId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
echo json_encode(["count" => $result['count']]);
$conn->close();
?>

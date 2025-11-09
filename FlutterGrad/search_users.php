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

// Get search keyword from query string
$keyword = $_GET['query'] ?? '';

$sql = "SELECT universityID, username FROM users WHERE username LIKE ? LIMIT 10";
$stmt = $conn->prepare($sql);
$search = "%$keyword%";
$stmt->bind_param("s", $search);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

echo json_encode($users);
?>
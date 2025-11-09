<?php
// === SECURITY HEADERS ===
$allowedOrigins = ['http://localhost:80',
                   'http://172.19.4.101'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Max-Age: 600");
header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");

// Respond to preflight requests immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// === DATABASE CONNECTION ===
$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "bzu_leads";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Connection failed"]);
    exit;
}

// === SECURE QUERY ===
$sql = "SELECT systemIpAddress, systemName, systemLogo FROM settings LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(["success" => true, "data" => $row]);
} else {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "No settings found"]);
}

$conn->close();

/*header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "bzu_leads";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Connection failed"]);
    exit;
}

$sql = "SELECT systemIpAddress, systemName, systemLogo FROM settings LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode(["success" => true, "data" => $row]);
} else {
    echo json_encode(["success" => false, "message" => "No settings found"]);
}

$conn->close();*/
?>

<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if (!isset($_GET['facultyID'])) {
    echo json_encode(["status" => "error", "message" => "Missing facultyID"]);
    exit;
}

$facultyID = $conn->real_escape_string($_GET['facultyID']);
$sql = "SELECT academicID, academicName, officeHours, room FROM academic WHERE faculityID = '$facultyID'";
$result = $conn->query($sql);

$academics = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $academics[] = [
            "academicID" => $row["academicID"],
            "name" => $row["academicName"],
            "officeHours" => $row["officeHours"],
            "room" => $row["room"]
        ];
    }
    echo json_encode(["status" => "success", "academics" => $academics]);
} else {
    echo json_encode(["status" => "error", "message" => "No academics found."]);
}

$conn->close();
?>

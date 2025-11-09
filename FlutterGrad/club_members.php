/*<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

$club_id = isset($_GET['club_id']) ? $conn->real_escape_string($_GET['club_id']) : '';

if ($club_id) {
    $query = "SELECT studentID, username FROM students 
              JOIN users ON students.studentID = users.universityID 
              WHERE studentclubID = '$club_id'";

    $result = $conn->query($query);

    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = [
            "studentID" => $row['studentID'],
            "username" => $row['username']
        ];
    }

    echo json_encode(["success" => true, "members" => $members]);
} else {
    echo json_encode(["success" => false, "message" => "Invalid club ID"]);
}

$conn->close();
?>
*/
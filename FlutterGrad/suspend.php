<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Search students by username or university ID
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $sql = "
        SELECT students.studentID AS universityID, users.username
        FROM students
        JOIN users ON students.studentID = users.universityID
        WHERE users.username LIKE '%$search%' OR students.studentID LIKE '%$search%'
    ";
    $result = $conn->query($sql);
    $students = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
    }
    echo json_encode(["success" => true, "students" => $students]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Suspend a student by inserting into isactive
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['universityID']) || !isset($data['universityAdministrationID']) || !isset($data['startDate']) || !isset($data['endDate'])) {
        echo json_encode(["success" => false, "message" => "Missing required fields"]);
        exit;
    }

    $universityID = $conn->real_escape_string($data['universityID']);
    $memberID = $conn->real_escape_string($data['universityAdministrationID']); // This is the memberID from frontend
    $startDate = $conn->real_escape_string($data['startDate']);
    $endDate = $conn->real_escape_string($data['endDate']);

    // Get UniversityAdministrationID using memberID
    $query = "SELECT UniversityAdministrationID FROM universityadministration WHERE memberID = '$memberID' LIMIT 1";
    $result = $conn->query($query);

    if (!$result || $result->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "Invalid universityAdministrationID: memberID not found"]);
        exit;
    }

    $row = $result->fetch_assoc();
    $officialID = $row['UniversityAdministrationID'];

    // Insert using the actual foreign key value
    $sql = "INSERT INTO isactive (USERID, OFFICIALID, STARTDATE, ENDDATE) 
            VALUES ('$universityID', '$officialID', '$startDate', '$endDate')";
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode(["success" => true, "message" => "Student suspended successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Error: " . $conn->error]);
    }

    exit;
}

echo json_encode(["success" => false, "message" => "Invalid request method"]);
$conn->close();
?>
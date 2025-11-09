<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]);
    exit();
}

$users = [];

if (isset($_GET['department'])) {
    $departmentID = $conn->real_escape_string($_GET['department']);
    $sql = "
        SELECT o.officialID AS universityID, u.username
        FROM official o
        JOIN users u ON o.officialID = u.universityID
        JOIN department d ON o.facultyID = d.facultyID
        WHERE d.departmentID = '$departmentID'
        UNION
        SELECT s.studentID AS universityID, u.username
        FROM students s
        JOIN users u ON s.studentID = u.universityID
        JOIN department d ON s.facultyID = d.facultyID
        WHERE d.departmentID = '$departmentID'
        UNION
        SELECT a.academicID AS universityID, u.username
        FROM academic a
        JOIN users u ON a.academicID = u.universityID
        JOIN department d ON a.faculityID = d.facultyID
        WHERE d.departmentID = '$departmentID'
    ";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
} /*elseif (isset($_GET['faculty'])) {
    $facultyName = $conn->real_escape_string($_GET['faculty']);
    $sql = "
        SELECT o.officialID AS universityID, u.username
        FROM official o
        JOIN users u ON o.officialID = u.universityID
        JOIN department d ON o.departmentID = d.departmentID
        WHERE d.facultyName = '$facultyName'
        UNION
        SELECT s.studentID AS universityID, u.username
        FROM students s
        JOIN users u ON s.studentID = u.universityID
        JOIN department d ON s.departmentID = d.departmentID
        WHERE d.facultyName = '$facultyName'
        UNION
        SELECT a.academicID AS universityID, u.username
        FROM academic a
        JOIN users u ON a.academicID = u.universityID
        JOIN department d ON a.departmentID = d.departmentID
        WHERE d.facultyName = '$facultyName'
    ";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
}*/ elseif (isset($_GET['facultyID'])) {
    $facultyID = $conn->real_escape_string($_GET['facultyID']);
    $sql = "
        SELECT o.officialID AS universityID, u.username
        FROM official o
        JOIN users u ON o.officialID = u.universityID
        JOIN department d ON o.facultyID = d.facultyID
        WHERE d.facultyID = '$facultyID'
        UNION
        SELECT s.studentID AS universityID, u.username
        FROM students s
        JOIN users u ON s.studentID = u.universityID
        JOIN department d ON s.facultyID = d.facultyID
        WHERE d.facultyID = '$facultyID'
        UNION
        SELECT a.academicID AS universityID, u.username
        FROM academic a
        JOIN users u ON a.academicID = u.universityID
        JOIN department d ON a.faculityID = d.facultyID
        WHERE d.facultyID = '$facultyID'
    ";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
}

echo json_encode($users);

$conn->close();
?>

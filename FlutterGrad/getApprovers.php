<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
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

$postType = $_GET['postType'] ?? 'public';
$facultyID = $_GET['facultyID'] ?? null;
$studentID = $_GET['studentID'] ?? null;

try {
    if ($postType === 'public') {
        // Public: return all officials with their username
        $sql = "
            SELECT u.universityID AS id, u.username AS name 
            FROM official o 
            JOIN users u ON o.officialID = u.universityID
        ";
        $stmt = $conn->prepare($sql);
    } elseif ($facultyID && $studentID) {
        // First, get departmentID of the student
        $deptQuery = $conn->prepare("SELECT departmentID FROM students WHERE studentID = ?");
        $deptQuery->bind_param("i", $studentID);
        $deptQuery->execute();
        $deptResult = $deptQuery->get_result();
        $deptRow = $deptResult->fetch_assoc();
        $departmentID = $deptRow['departmentID'] ?? null;
        $deptQuery->close();

        if (!$departmentID) {
            echo json_encode(["success" => false, "message" => "Could not find department for student."]);
            exit();
        }

        // Private post: get faculty head (from faculty table), department head (from department table), faculty officials, and department club head
        $sql = "
            SELECT f.facultyHeadID AS id, u.username AS name
            FROM faculty f
            JOIN users u ON f.facultyHeadID = u.universityID
            WHERE f.facultyID = ?

            UNION

            SELECT d.departmentHeadID AS id, u.username AS name
            FROM department d
            JOIN users u ON d.departmentHeadID = u.universityID
            WHERE d.departmentID = ?

            UNION

            SELECT o.officialID AS id, u.username AS name
            FROM official o
            JOIN users u ON o.officialID = u.universityID
            WHERE u.roleID = 2 AND o.facultyID = ?

            UNION

            SELECT dcm.studentID AS id, u.username AS name
            FROM departmentclub_members dcm
            JOIN users u ON dcm.studentID = u.universityID
            WHERE dcm.departmentclubID = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $facultyID, $departmentID, $facultyID, $departmentID);
    } else {
        echo json_encode(["success" => false, "message" => "Missing parameters for private post."]);
        exit();
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode(["success" => true, "data" => $data]);

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
    exit();
}
?>
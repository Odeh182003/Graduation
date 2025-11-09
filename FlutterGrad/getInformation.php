<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root"; 
$password = "";
$database = "BZU_Leads";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

// Get universityID from request
$universityID = isset($_GET['universityID']) ? $_GET['universityID'] : die(json_encode(["error" => "No user ID provided"]));

// Fetch user details from the users table
$sql = "SELECT universityID, roleID, username, PALESTINIANIDNUMBER, ISACTIVE, GENDER, DATEOFBIRTH, image FROM users WHERE universityID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $universityID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $roleID = $user['roleID'];
    $additionalInfo = [];

    // Fetch additional info based on role
    if ($roleID == 1) { // Student
        $query = $conn->prepare("
            SELECT 
                s.major, 
                s.minor, 
                s.EMAIL, 
                s.HOBBIES, 
                s.studentclubID,
                f.facultyID,
                f.facultyName, 
                d.departmentName
            FROM students s
            LEFT JOIN department d ON s.departmentID = d.departmentID
            LEFT JOIN faculty f ON d.facultyID = f.facultyID
            WHERE s.studentID = ?
        ");
        $query->bind_param("s", $universityID);
    } elseif ($roleID == 2) { // Academic
        $query = $conn->prepare("
            SELECT a.EMAIL, a.officeHours, f.facultyName, f.facultyID
            FROM academic a
            LEFT JOIN faculty f ON a.faculityID = f.facultyID
            WHERE a.academicID = ?
        ");
        $query->bind_param("s", $universityID);
    } elseif ($roleID == 3) { // Official
        // Fix: Use correct column names from your official table
        $query = $conn->prepare("SELECT email, facultyID FROM official WHERE officialID = ?");
        $query->bind_param("s", $universityID);
    } else {
        echo json_encode(["error" => "Invalid role ID"]);
        exit();
    }

    // Execute the role-specific query
    if ($query->execute()) {
        $result = $query->get_result();
        $additionalInfo = $result->fetch_assoc();

        // If additional data found, merge it with the user data
        if ($additionalInfo) {
            $user = array_merge($user, $additionalInfo);
        }
    } else {
        echo json_encode(["error" => "Error executing role-specific query"]);
        exit();
    }

    // 🔹 Fetch student club information if studentclubID exists (fix column name)
    if (!empty($user['studentclubID'])) {
        $studentClubID = $user['studentclubID'];

        $clubQuery = $conn->prepare("
            SELECT studentclubname, membersince, endDate, headStudentID
            FROM studentclub 
            WHERE studentclubID = ?
        ");
        $clubQuery->bind_param("i", $studentClubID);
        $clubQuery->execute();
        $clubResult = $clubQuery->get_result();
        $clubInfo = $clubResult->fetch_assoc();

        // Merge student club info with user data
        if ($clubInfo) {
            $user['studentClub'] = $clubInfo;
        }
    }

    // Return final response
    echo json_encode(["success" => true, "data" => $user]);
} else {
    echo json_encode(["error" => "User not found"]);
}

// Close connections
$stmt->close();
$conn->close();
?>
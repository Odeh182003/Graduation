<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

// Connect to DB
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

// Get input (JSON or form-data)
$data = strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
    ? json_decode(file_get_contents("php://input"), true)
    : $_POST;

// Validate required fields
if (empty($data["universityID"]) || empty($data["username"]) || empty($data["password"]) || empty($data["roleID"])) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit();
}

$universityID = $conn->real_escape_string($data["universityID"]);
$username = $conn->real_escape_string($data["username"]);
$password = password_hash($data["password"], PASSWORD_DEFAULT); // secure hashing
$roleID = intval($data["roleID"]);
$gender = !empty($data["GENDER"]) ? $conn->real_escape_string($data["GENDER"]) : "Male"; // Default to "Male"
$dob = !empty($data["DATEOFBIRTH"]) ? $conn->real_escape_string($data["DATEOFBIRTH"]) : "0000-00-00"; // Default to "0000-00-00"
$palestinianID = !empty($data["PALESTINIANIDNUMBER"]) ? $conn->real_escape_string($data["PALESTINIANIDNUMBER"]) : null;

// Handle image upload or set default
$image = "uploads/Capture.JPG"; // Default image
if (!empty($data["image"])) {
    $imageName = uniqid() . ".jpg";
    $imagePath = "uploads/" . $imageName;
    file_put_contents($imagePath, base64_decode($data["image"]));
    $image = $imagePath;
}

// Check if user exists in the users table
$check = $conn->prepare("SELECT universityID FROM users WHERE universityID = ?");
$check->bind_param("s", $universityID);
$check->execute();
$checkResult = $check->get_result();
if ($checkResult->num_rows > 0) {
    // User exists in the users table, check their role and insert into the corresponding table
    if ($roleID == 1) { // Student
        $facultyID = isset($data["facultyID"]) ? intval($data["facultyID"]) : null;
        $departmentID = isset($data["DEPARTMENTID"]) ? intval($data["DEPARTMENTID"]) : null;
        $major = !empty($data["major"]) ? $conn->real_escape_string($data["major"]) : "Undeclared";
        $minor = !empty($data["minor"]) ? $conn->real_escape_string($data["minor"]) : null;
        $email = !empty($data["email"]) ? $conn->real_escape_string($data["email"]) : null;
        $isGraduate = 0; // Always set to 0 internally
        $hobbies = !empty($data["HOBBIES"]) ? $conn->real_escape_string($data["HOBBIES"]) : null;

        // Check if facultyID exists in the faculty table
        if ($facultyID !== null) {
            $facultyCheck = $conn->prepare("SELECT facultyID FROM faculty WHERE facultyID = ?");
            $facultyCheck->bind_param("i", $facultyID);
            $facultyCheck->execute();
            $facultyResult = $facultyCheck->get_result();
            if ($facultyResult->num_rows === 0) {
                echo json_encode(["success" => false, "message" => "Invalid facultyID"]);
                exit();
            }
            $facultyCheck->close();
        }

        // Insert into students table
        $studentCheck = $conn->prepare("SELECT studentID FROM students WHERE studentID = ?");
        $studentCheck->bind_param("s", $universityID);
        $studentCheck->execute();
        $studentResult = $studentCheck->get_result();
        if ($studentResult->num_rows === 0) {
            $sql = "INSERT INTO students (studentID, facultyID, DEPARTMENTID, major, minor, EMAIL, ISGRADUATE, HOBBIES) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sissssis", $universityID, $facultyID, $departmentID, $major, $minor, $email, $isGraduate, $hobbies);
            $stmt->execute();
            $stmt->close();
        }
        $studentCheck->close();
    } elseif ($roleID == 2) { // Academic
        $facultyID = isset($data["facultyID"]) ? intval($data["facultyID"]) : null;
        $email = !empty($data["email"]) ? $conn->real_escape_string($data["email"]) : null;
        $officeHours = !empty($data["officeHours"]) ? $conn->real_escape_string($data["officeHours"]) : "Not Set";
        $room = !empty($data["room"]) ? $conn->real_escape_string($data["room"]) : "Unknown";

        // Insert into academic table
        $academicCheck = $conn->prepare("SELECT academicID FROM academic WHERE academicID = ?");
        $academicCheck->bind_param("s", $universityID);
        $academicCheck->execute();
        $academicResult = $academicCheck->get_result();
        if ($academicResult->num_rows === 0) {
            $sql = "INSERT INTO academic (academicID, faculityID, EMAIL, officeHours, academicName, room) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sissss", $universityID, $facultyID, $email, $officeHours, $username, $room);
            $stmt->execute();
            $stmt->close();
        }
        $academicCheck->close();
    }
    echo json_encode(["success" => true, "message" => "User already exists in users table but has been added to the corresponding table"]);
    exit();
}
$check->close();

// Insert into users table
$sql = "INSERT INTO users (universityID, username, password, roleID, GENDER, DATEOFBIRTH, PALESTINIANIDNUMBER, image)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssssss", $universityID, $username, $password, $roleID, $gender, $dob, $palestinianID, $image);
if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Error creating user: " . $stmt->error]);
    exit();
}
$stmt->close();

// Insert into role-specific tables
if ($roleID == 1) { // Student
    $facultyID = isset($data["facultyID"]) ? intval($data["facultyID"]) : null;
    $departmentID = isset($data["DEPARTMENTID"]) ? intval($data["DEPARTMENTID"]) : null;
    $major = !empty($data["major"]) ? $conn->real_escape_string($data["major"]) : "Undeclared";
    $minor = !empty($data["minor"]) ? $conn->real_escape_string($data["minor"]) : null;
    $email = !empty($data["email"]) ? $conn->real_escape_string($data["email"]) : null;
    $isGraduate = 0; // Always set to 0 internally
    $hobbies = !empty($data["HOBBIES"]) ? $conn->real_escape_string($data["HOBBIES"]) : null;

    // Check if facultyID exists in the faculty table
    if ($facultyID !== null) {
        $facultyCheck = $conn->prepare("SELECT facultyID FROM faculty WHERE facultyID = ?");
        $facultyCheck->bind_param("i", $facultyID);
        $facultyCheck->execute();
        $facultyResult = $facultyCheck->get_result();
        if ($facultyResult->num_rows === 0) {
            echo json_encode(["success" => false, "message" => "Invalid facultyID"]);
            exit();
        }
        $facultyCheck->close();
    }

    // Insert into students table
    $sql = "INSERT INTO students (studentID, facultyID, DEPARTMENTID, major, minor, EMAIL, ISGRADUATE, HOBBIES) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sissssis", $universityID, $facultyID, $departmentID, $major, $minor, $email, $isGraduate, $hobbies);
    $stmt->execute();
    $stmt->close();
} elseif ($roleID == 2) { // Academic
    $facultyID = isset($data["facultyID"]) ? intval($data["facultyID"]) : null;
    $email = !empty($data["email"]) ? $conn->real_escape_string($data["email"]) : null;
    $officeHours = !empty($data["officeHours"]) ? $conn->real_escape_string($data["officeHours"]) : "Not Set";
    $room = !empty($data["room"]) ? $conn->real_escape_string($data["room"]) : "Unknown";

    $sql = "INSERT INTO academic (academicID, faculityID, EMAIL, officeHours, academicName, room) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sissss", $universityID, $facultyID, $email, $officeHours, $username, $room);
    $stmt->execute();
    $stmt->close();
} 

if ($roleID == 1 || $roleID == 2) { // For Students or Academics
    // Fetch all faculty names and IDs for the dropdown
    $facultyQuery = "SELECT facultyID, facultyName FROM faculty";
    $facultyStmt = $conn->prepare($facultyQuery);
    $facultyStmt->execute();
    $facultyResult = $facultyStmt->get_result();

    $faculties = [];
    while ($row = $facultyResult->fetch_assoc()) {
        $faculties[] = [
            "facultyID" => $row["facultyID"],
            "facultyName" => $row["facultyName"]
        ];
    }
    $facultyStmt->close();

    // Fetch all department names and IDs for the dropdown
    $departmentQuery = "SELECT departmentID, departmentName FROM department";
    $departmentStmt = $conn->prepare($departmentQuery);
    $departmentStmt->execute();
    $departmentResult = $departmentStmt->get_result();

    $departments = [];
    while ($row = $departmentResult->fetch_assoc()) {
        $departments[] = [
            "departmentID" => $row["departmentID"],
            "departmentName" => $row["departmentName"]
        ];
    }
    $departmentStmt->close();

    echo json_encode([
        "success" => true,
        "message" => "User registered successfully",
        "faculties" => $faculties, // Return faculty data for the dropdown
        "departments" => $departments // Return department data for the dropdown
    ]);
    exit();
}

echo json_encode(["success" => true, "message" => "User registered successfully"]);
$conn->close();
?>
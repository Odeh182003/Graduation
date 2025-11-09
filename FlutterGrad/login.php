<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
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

$data = $_SERVER['REQUEST_METHOD'] === 'POST' ? json_decode(file_get_contents("php://input"), true) : $_GET;

// Validate input
if (empty($data['universityID']) || empty($data['password'])) {
    echo json_encode(["success" => false, "message" => "Missing universityID or password"]);
    exit();
}

// Treat universityID as a string
$universityID = $conn->real_escape_string($data['universityID']);
$inputPassword = $data['password'];

// Fetch username and hashed password
$query = "SELECT username, password FROM users WHERE universityID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $universityID); // Use "s" for string
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "User not found"]);
    exit();
}

$row = $result->fetch_assoc();
$username = $row['username'];
$hashedPassword = $row['password'];


// Verify the password
if (!password_verify($inputPassword, $hashedPassword)) {
    echo json_encode(["success" => false, "message" => "Invalid password"]);
    exit();
}

// Check if the user is suspended
$isActiveQuery = "SELECT * FROM isactive WHERE USERID = ? AND CURDATE() BETWEEN STARTDATE AND ENDDATE";
$stmt = $conn->prepare($isActiveQuery);
$stmt->bind_param("s", $universityID);
$stmt->execute();
$isActiveResult = $stmt->get_result();

if ($isActiveResult->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "Your account is suspended. Please contact administration."]);
    exit();
}


// Initialize response
$response = [
    "success" => true,
    "username" => $username,
    "roles" => []
];

// Check if the user is a student
$studentQuery = "SELECT studentID, studentclubID, facultyID FROM students WHERE studentID = ?";
$stmt = $conn->prepare($studentQuery);
$stmt->bind_param("s", $universityID);
$stmt->execute();
$studentResult = $stmt->get_result();

if ($studentResult->num_rows > 0) {
    $studentRow = $studentResult->fetch_assoc();
        // Check graduation status
    $isGraduateQuery = "SELECT isGraduate FROM students WHERE studentID = ?";
    $stmt = $conn->prepare($isGraduateQuery);
    $stmt->bind_param("s", $universityID);
    $stmt->execute();
    $gradResult = $stmt->get_result();

    if ($gradResult->num_rows > 0) {
        $gradRow = $gradResult->fetch_assoc();
        if ((int)$gradRow['isGraduate'] === 1) {
            echo json_encode(["success" => false, "message" => "Access denied: You have graduated."]);
            exit();
        }
    }
    $response["roles"][] = "student";
    $response["facultyID"] = $studentRow['facultyID'];

    // Fetch sections
    $sectionQuery = "SELECT DISTINCT cs.sectionID
                     FROM coursestudent cs
                     JOIN coursesection csec ON cs.courseID = csec.courseID
                     WHERE cs.studentID = ?";
    $stmt = $conn->prepare($sectionQuery);
    $stmt->bind_param("s", $universityID); // Use "s" for string
    $stmt->execute();
    $sectionResult = $stmt->get_result();

    $sections = [];
    while ($sectionRow = $sectionResult->fetch_assoc()) {
        $sections[] = $sectionRow['sectionID'];
    }

    $response["studentData"] = ["sectionIDs" => $sections];

    // Fetch student club details
    if (!empty($studentRow['studentclubID'])) {
        $clubQuery = "SELECT studentclubID, studentclubname FROM studentclub WHERE studentclubID = ?";
        $stmt = $conn->prepare($clubQuery);
        $stmt->bind_param("s", $studentRow['studentclubID']); // Use "s" for string
        $stmt->execute();
        $clubResult = $stmt->get_result();

        if ($clubResult->num_rows > 0) {
            $clubRow = $clubResult->fetch_assoc();
            $response["studentClub"] = [
                "studentclubID" => $clubRow['studentclubID'],
                "studentclubname" => $clubRow['studentclubname']
            ];
        }
    }
}

// Check if the user is an academic
// Check if the user is an academic
$academicCheckQuery = "SELECT academicID, faculityID FROM academic WHERE academicID = ?";
$stmt = $conn->prepare($academicCheckQuery);
$stmt->bind_param("s", $universityID);
$stmt->execute();
$academicCheckResult = $stmt->get_result();

if ($academicCheckResult->num_rows > 0) {
    $academicRow = $academicCheckResult->fetch_assoc(); 
    $response["roles"][] = "academic";
    $response["facultyID"] = $academicRow["faculityID"]; 

    // Now get the sections they teach, if any
    $academicSectionQuery = "SELECT DISTINCT sectionID FROM coursesection WHERE academicID = ?";
    $stmt = $conn->prepare($academicSectionQuery);
    $stmt->bind_param("s", $universityID);
    $stmt->execute();
    $sectionResult = $stmt->get_result();

    $sections = [];
    while ($row = $sectionResult->fetch_assoc()) {
        $sections[] = $row['sectionID'];
    }

    $response["academicData"] = ["sectionIDs" => $sections];
}


// Check if the user is an official
$officialQuery = "SELECT facultyID FROM official WHERE officialID = ?";
$stmt = $conn->prepare($officialQuery);
$stmt->bind_param("s", $universityID); // Use "s" for string
$stmt->execute();
$officialResult = $stmt->get_result();

if ($officialResult->num_rows > 0) {
    $officialRow = $officialResult->fetch_assoc();
    $response["roles"][] = "official";
    if (!isset($response["facultyID"])) {
        $response["facultyID"] = $officialRow['facultyID'];
    }
}

// Check if the user is a university administrator
$adminQuery = "SELECT memberID FROM universityadministration WHERE memberID = ?";
$stmt = $conn->prepare($adminQuery);
$stmt->bind_param("s", $universityID); // Use "s" for string
$stmt->execute();
$adminResult = $stmt->get_result();

if ($adminResult->num_rows > 0) {
    $response["roles"][] = "universityAdministrator";
}

// Set default role
if (in_array("universityAdministrator", $response["roles"])) {
    $response["defaultRole"] = "universityAdministrator";
} elseif (in_array("official", $response["roles"])) {
    $response["defaultRole"] = "official";
} elseif (in_array("academic", $response["roles"])) {
    $response["defaultRole"] = "academic";
} elseif (in_array("student", $response["roles"])) {
    $response["defaultRole"] = "student";
} else {
    $response["defaultRole"] = "unknown";
}

echo json_encode($response);
$conn->close();
?>
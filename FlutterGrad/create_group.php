<?php
ini_set('display_errors', 0);
error_reporting(0);
ob_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}
// Only allow GET method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    $conn->close();
    exit;
}

function createGroup($conn, $name, $createdBy, $courseSectionID, $roleID, $members) {
    // Check if the group already exists
    $checkQuery = "SELECT groupID FROM messagesgroup WHERE MESSAGINGGROUPNAME = ? AND CREATEDBY = ? AND roleID = ?";
    if ($courseSectionID !== null) {
        $checkQuery .= " AND COURSESECTIONID = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("siii", $name, $createdBy, $roleID, $courseSectionID);
    } else {
        $checkQuery .= " AND COURSESECTIONID IS NULL";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("sii", $name, $createdBy, $roleID);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $existingGroup = $result->fetch_assoc();
        return ["success" => false, "message" => "Group already exists", "groupID" => $existingGroup['groupID']];
    }

    // Insert new group
    $stmt = $conn->prepare("INSERT INTO messagesgroup (MESSAGINGGROUPNAME, CREATEDBY, COURSESECTIONID, roleID, CREATIONDATE) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("siii", $name, $createdBy, $courseSectionID, $roleID);
    if (!$stmt->execute()) {
        return ["success" => false, "message" => "Failed to create group."];
    }
    $groupID = $conn->insert_id;

    $insertMember = $conn->prepare("INSERT INTO chatting_group_members (groupID, userID) VALUES (?, ?)");
    foreach ($members as $userID) {
        $insertMember->bind_param("ii", $groupID, $userID);
        $insertMember->execute();
    }

    return ["success" => true, "message" => "Group created successfully", "groupID" => $groupID];
}


// For Academic
if (isset($_GET['academic_id'])) {
    $academicID = intval($_GET['academic_id']);
    $query = "SELECT cs.sectionID, cs.courseID, c.courseName 
              FROM coursesection cs 
              JOIN course c ON cs.courseID = c.courseID 
              WHERE cs.academicID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $academicID);
    $stmt->execute();
    $result = $stmt->get_result();

    $responses = [];
    while ($row = $result->fetch_assoc()) {
        $sectionID = $row['sectionID'];
        $courseName = $row['courseName'];

        $studentsQuery = "SELECT DISTINCT studentID FROM coursestudent WHERE courseID = ? AND sectionID = ?";
        $stmt2 = $conn->prepare($studentsQuery);
        $stmt2->bind_param("ii", $row['courseID'], $sectionID);
        $stmt2->execute();
        $studentsResult = $stmt2->get_result();

        $members = [];
        while ($student = $studentsResult->fetch_assoc()) {
            $members[] = $student['studentID'];
        }
        $members[] = $academicID; // add academic

        $groupName = "$courseName - Section $sectionID";
        $res = createGroup($conn, $groupName, $academicID, $sectionID, 2, $members); // 2 = academic
        $responses[] = $res;
    }
    echo json_encode($responses);
    $conn->close();
    exit;
}

// For Official
if (isset($_GET['official_id'])) {
    $officialID = intval($_GET['official_id']);
    $responses = [];

    // DEPARTMENT CLUBS
    $deptQuery = "SELECT departmentclubID, departmentclubname FROM departmentclub WHERE headdepartmentclubID = ?";
    $stmt = $conn->prepare($deptQuery);
    $stmt->bind_param("i", $officialID);
    $stmt->execute();
    $deptResult = $stmt->get_result();
    while ($row = $deptResult->fetch_assoc()) {
        $clubID = $row['departmentclubID'];
        $groupName = $row['departmentclubname'];

        $membersQuery = "SELECT studentID FROM departmentclub_members WHERE departmentclubID = ?";
        $stmt2 = $conn->prepare($membersQuery);
        $stmt2->bind_param("i", $clubID);
        $stmt2->execute();
        $membersResult = $stmt2->get_result();

        $members = [];
        while ($member = $membersResult->fetch_assoc()) {
            $members[] = $member['studentID'];
        }
        $members[] = $officialID;

        $res = createGroup($conn, $groupName, $officialID, null, 3, $members); // 3 = official
        $responses[] = $res;
    }

    // STUDENT CLUBS
    $studQuery = "SELECT studentclubID, studentclubname FROM studentclub WHERE headStudentID = ?";
    $stmt = $conn->prepare($studQuery);
    $stmt->bind_param("i", $officialID);
    $stmt->execute();
    $studResult = $stmt->get_result();
    while ($row = $studResult->fetch_assoc()) {
        $clubID = $row['studentclubID'];
        $groupName = $row['studentclubname'];

        $membersQuery = "SELECT studentID FROM students WHERE studentclubID = ?";
        $stmt2 = $conn->prepare($membersQuery);
        $stmt2->bind_param("i", $clubID);
        $stmt2->execute();
        $membersResult = $stmt2->get_result();

        $members = [];
        while ($member = $membersResult->fetch_assoc()) {
            $members[] = $member['studentID'];
        }
        $members[] = $officialID;

        $res = createGroup($conn, $groupName, $officialID, null, 3, $members); // 3 = official
        $responses[] = $res;
    }

    echo json_encode($responses);
    $conn->close();
    exit;
}
if (isset($_GET['view_academic_id'])) {
    $academicID = intval($_GET['view_academic_id']);
    $query = "
        SELECT g.groupID, g.MESSAGINGGROUPNAME, g.CREATIONDATE 
        FROM messagesgroup g
        JOIN chatting_group_members m ON g.groupID = m.groupID
        WHERE m.userID = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $academicID);
    $stmt->execute();
    $result = $stmt->get_result();

    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }

    echo json_encode(["success" => true, "groups" => $groups]);
    $conn->close();
    exit;
}
if (isset($_GET['view_official_id'])) {
    $officialID = intval($_GET['view_official_id']);
    $query = "
        SELECT g.groupID, g.MESSAGINGGROUPNAME, g.CREATIONDATE 
        FROM messagesgroup g
        JOIN chatting_group_members m ON g.groupID = m.groupID
        WHERE m.userID = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $officialID);
    $stmt->execute();
    $result = $stmt->get_result();

    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }

    echo json_encode(["success" => true, "groups" => $groups]);
    $conn->close();
    exit;
}
if (isset($_GET['view_student_id'])) {
    $studentID = intval($_GET['view_student_id']);
    $query = "
        SELECT g.groupID, g.MESSAGINGGROUPNAME, g.CREATIONDATE 
        FROM messagesgroup g
        JOIN chatting_group_members m ON g.groupID = m.groupID
        WHERE m.userID = ?
    ";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $studentID);
    $stmt->execute();
    $result = $stmt->get_result();

    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = $row;
    }

    echo json_encode(["success" => true, "groups" => $groups]);
    $conn->close();
    exit;
}

echo json_encode(["success" => false, "message" => "Invalid request."]);
$conn->close();
?>

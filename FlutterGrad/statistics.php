<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

$response = [];

// User Stats
$response['total_users'] = getCount("users");
$response['active_students'] = getCount("users", "roleID = 1 AND ISACTIVE = 1");
$response['active_faculty'] = getCount("faculty", "facultyID IN (
    SELECT DISTINCT f.facultyID 
    FROM posts p 
    JOIN users u ON p.POSTCREATORID = u.universityID
    LEFT JOIN students s ON s.studentID = u.universityID
    LEFT JOIN academic a ON a.academicID = u.universityID
    LEFT JOIN official o ON o.officialID = u.universityID
    JOIN faculty f ON f.facultyID = s.facultyID OR f.facultyID = a.faculityID OR f.facultyID = o.facultyID
    WHERE u.ISACTIVE = 1
)");// Post Stats
$response['public_posts'] = getCount("posts", "postType = 'public' AND APPROVALSTATUS = 'approved'");
$response['private_posts'] = getCount("posts", "postType = 'private' AND APPROVALSTATUS = 'approved'");
$response['total_posts'] = $response['public_posts'] + $response['private_posts'];

// Chat Groups
$response['messagesgroup'] = getCount("messagesgroup");
$response['avg_users_per_group'] = getAverage("chatting_group_members", "groupID");
//$response['most_active_group'] = getMostActiveGroup(); // You can implement this later if needed

// Activities
$response['total_activities'] = getCount("activities");
$response['done_activities'] = getCount("activities", "status = 'done'");
$response['pending_activities'] = getCount("activities", "status = 'pending'");
$response['cancelled_activities'] = getCount("activities", "status = 'cancelled'");

// Faculty & Department Insights
$response['faculties'] = getFacultyDetails();
$response['departments'] = getDepartmentDetails();
$response['faculty_posts'] = getFacultyPostCounts();

echo json_encode($response);

// Helper Functions

function getCount($table, $where = "1") {
    global $conn;
    $sql = "SELECT COUNT(*) AS total FROM $table WHERE $where";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result)['total'] ?? 0;
}

function getAverage($table, $groupField) {
    global $conn;
    $sql = "SELECT AVG(member_count) AS avg_count 
            FROM (
                SELECT COUNT(*) AS member_count 
                FROM $table 
                GROUP BY $groupField
            ) AS temp";
    $result = mysqli_query($conn, $sql);
    return round(mysqli_fetch_assoc($result)['avg_count'] ?? 0, 1);
}

function getFacultyDetails() {
    global $conn;
    $sql = "SELECT 
                f.facultyID,
                f.facultyName,
                f.facultyHeadID,
                COUNT(a.academicID) AS academic_count
            FROM faculty f
            LEFT JOIN users u ON f.facultyHeadID = u.universityID
            LEFT JOIN academic a ON a.faculityID = f.facultyID
            GROUP BY f.facultyID, f.facultyName, u.username";

    $result = mysqli_query($conn, $sql);
    $faculties = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $faculties[] = $row;
    }
    return $faculties;
}

function getDepartmentDetails() {
    global $conn;
    $sql = "SELECT 
                d.departmentName,
                f.facultyName,
                d.departmentHeadID
            FROM department d
            JOIN faculty f ON d.facultyID = f.facultyID
            LEFT JOIN users u ON d.departmentHeadID = u.universityID";

    $result = mysqli_query($conn, $sql);
    $departments = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $departments[] = $row;
    }
    return $departments;
}

function getFacultyPostCounts() {
    global $conn;
    $data = [];

    $sql = "
        SELECT f.facultyName, COUNT(p.POSTID) AS count
        FROM posts p
        JOIN users u ON p.POSTCREATORID = u.universityID

        LEFT JOIN academic a ON a.academicID = u.universityID
        LEFT JOIN students s ON s.studentID = u.universityID
        LEFT JOIN official o ON o.officialID = u.universityID

        LEFT JOIN faculty f 
            ON f.facultyID = a.faculityID 
            OR f.facultyID = s.facultyID 
            OR f.facultyID = o.facultyID

        WHERE f.facultyName IS NOT NULL
        GROUP BY f.facultyName
        ORDER BY count DESC
    ";

    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    return $data;
}

?>
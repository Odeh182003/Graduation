<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

// DB config
$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

// Decode incoming JSON payload
$input_data = json_decode(file_get_contents("php://input"), true);
$universityID = $input_data['universityID'] ?? '';

if (empty($universityID)) {
    echo json_encode(["status" => "error", "message" => "University ID is required."]);
    exit;
}

// Helper function to fetch media files per POSTID
function fetchMediaFiles($conn, $postId) {
    $media = [];
    $stmt = $conn->prepare("SELECT location FROM postsphotos WHERE PostID = ?");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $media[] = $row['location'];
    }
    $stmt->close();
    return $media;
}

// Query for rejected posts (public and private)
$sql = "
    SELECT 
        p.postType,
        u.username,
        p.postID,
        p.posttitle,
        p.CONTENT,
        p.DATECREATED,
        p.REVIEWEDBY,
        p.facultyID
    FROM posts p
    INNER JOIN users u ON p.POSTCREATORID = u.universityID
    WHERE p.APPROVALSTATUS = 'rejected' AND p.POSTCREATORID = ?
    ORDER BY p.DATECREATED DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $universityID);
$stmt->execute();

$result = $stmt->get_result();
$posts = [];

while ($row = $result->fetch_assoc()) {
    $row["media"] = fetchMediaFiles($conn, $row["postID"]);
    $posts[] = $row;
}

echo json_encode($posts);
$conn->close();
?>

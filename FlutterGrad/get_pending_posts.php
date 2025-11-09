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
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

$reviewerId = $_GET['reviewerId'] ?? '';

if (empty($reviewerId)) {
    echo json_encode(["error" => "Missing reviewerId parameter"]);
    exit;
}

$posts = [];

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

// Fetch all pending posts for the reviewer and join with users table to get the username
$sql = "SELECT 
            p.POSTID, 
            p.posttitle, 
            p.CONTENT, 
            p.DATECREATED, 
            p.POSTCREATORID,
            p.postType,
            u.username
        FROM posts p
        INNER JOIN users u ON p.POSTCREATORID = u.universityID
        WHERE p.APPROVALSTATUS = 'pending' AND p.reviewedby = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $reviewerId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row["media"] = fetchMediaFiles($conn, $row["POSTID"]);
    $posts[] = $row;
}

$stmt->close();
echo json_encode($posts);
$conn->close();
?>

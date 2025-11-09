<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json; charset=UTF-8");

$servername = "localhost:3307";
$username   = "root";
$password   = "";
$database   = "bzu_leads";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
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

if (
    (isset($_GET['facultyID']) && $_GET['facultyID'] !== '') // Filter by facultyID
) {
    $facultyID = intval($_GET['facultyID']);
    $sql = "SELECT u.username, u.universityID, p.postID, p.posttitle, p.CONTENT, p.DATECREATED, p.REVIEWEDBY 
            FROM posts p
            INNER JOIN users u ON p.POSTCREATORID = u.universityID
            WHERE p.APPROVALSTATUS = 'approved' AND p.postType = 'private' AND p.facultyID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $facultyID);
    $stmt->execute();
    $result = $stmt->get_result();
} elseif (isset($_GET['allPrivate'])) {
    // University administration: show all private posts (no facultyID filter)
    $sql = "SELECT u.username, u.universityID, p.postID, p.posttitle, p.CONTENT, p.DATECREATED, p.REVIEWEDBY 
            FROM posts p
            INNER JOIN users u ON p.POSTCREATORID = u.universityID
            WHERE p.APPROVALSTATUS = 'approved' AND p.postType = 'private'";
    $result = $conn->query($sql);
} else {
    // Show all public posts
    $sql = "SELECT u.username, u.universityID, p.postID, p.posttitle, p.CONTENT, p.DATECREATED, p.REVIEWEDBY 
            FROM posts p
            INNER JOIN users u ON p.POSTCREATORID = u.universityID
            WHERE p.APPROVALSTATUS = 'approved' AND p.postType = 'public'";
    $result = $conn->query($sql);
}

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row["media"] = fetchMediaFiles($conn, $row["postID"]);
        $posts[] = $row;
    }
}

echo json_encode($posts, JSON_UNESCAPED_UNICODE);
$conn->close();
?>
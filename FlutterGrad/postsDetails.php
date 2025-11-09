<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Content-Type: application/json; charset=UTF-8");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

$conn = new mysqli($servername, $username, $password, $database);
$conn->set_charset("utf8mb4"); // Ensure UTF-8 for Arabic/English

if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

if (!isset($_GET['postID'])) {
    echo json_encode(["error" => "No post ID provided"]);
    exit;
}

$postID = intval($_GET['postID']);

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

// Query to get all post details for both public and private posts
$query = "SELECT 
    p.postID,
    p.APPROVALID, 
    p.POSTCREATORID, 
    p.posttitle, 
    p.CONTENT, 
    p.APPROVALSTATUS, 
    p.DATECREATED, 
    p.REVIEWEDBY, 
    p.REVIEWEDDATE, 
    a.APPROVALID AS approval_id, 
    a.APPROVERID, 
    a.POSTTYPE AS approval_post_type, 
    a.STATUS,
    p.facultyID,
    p.postType,
    u.username, 
    a.APPROVALDATE,
    o.email,
    approver_user.username AS APPROVERNAME
FROM posts p
LEFT JOIN approval a ON p.APPROVALID = a.APPROVALID
LEFT JOIN users u ON p.POSTCREATORID = u.universityID
LEFT JOIN official o ON a.APPROVERID = o.officialID
LEFT JOIN users approver_user ON a.APPROVERID = approver_user.universityID
WHERE p.postID = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $postID);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();

if ($post) {
    // Fetch media as an array from postsphotos table
    $post["media"] = fetchMediaFiles($conn, $post["postID"]);
    // Convert all int values to string for frontend compatibility
    foreach ($post as $key => $value) {
        if (is_int($value)) {
            $post[$key] = strval($value);
        }
    }
    echo json_encode(["post" => $post], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["error" => "Post not found"]);
}

$stmt->close();
$conn->close();
?>
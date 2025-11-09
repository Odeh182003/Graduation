<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username   = "root";
$password   = "";
$database   = "bzu_leads";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die(json_encode(["error" => "Connection failed: " . $conn->connect_error]));
}

if (!isset($_GET['postID'])) {
    echo json_encode(["error" => "Missing postID"]);
    exit();
}
$postID = intval($_GET['postID']);

$postStmt = $conn->prepare("SELECT postType, facultyID FROM posts WHERE POSTID = ?");
$postStmt->bind_param("i", $postID);
$postStmt->execute();
$postRes = $postStmt->get_result();

if ($postRes->num_rows === 0) {
    echo json_encode(["error" => "Post not found."]);
    $conn->close();
    exit();
}
$postStmt->close();

$sql = "SELECT u.username,
               c.COMMENTID,
               c.COMMENTCREATORID,
               c.COMMENTTEXT,
               c.TIMESTAMP,
               c.attachment
        FROM comments c
        INNER JOIN users u ON c.COMMENTCREATORID = u.universityID
        WHERE c.POSTID = ?
        ORDER BY c.TIMESTAMP DESC";

$cmtStmt = $conn->prepare($sql);
if (!$cmtStmt) {
    echo json_encode(["error" => "Prepare failed: " . $conn->error]);
    $conn->close();
    exit();
}

$cmtStmt->bind_param("i", $postID);
$cmtStmt->execute();
$result   = $cmtStmt->get_result();
$comments = [];

while ($row = $result->fetch_assoc()) {
    $row = array_change_key_case($row, CASE_LOWER);
    $comments[] = [
        "commentid" => $row['commentid'],
        "commentcreatorid" => $row['commentcreatorid'],
        "commenttext" => $row['commenttext'],
        "timestamp" => $row['timestamp'],
        "username" => $row['username'],
        "attachment" => $row['attachment'],
    ];
}


echo json_encode(["comments" => $comments]);

$cmtStmt->close();
$conn->close();
?>

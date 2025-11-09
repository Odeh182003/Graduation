<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $json = json_decode(file_get_contents('php://input'), true);
    if (is_array($json)) {
        $_POST = $json;
    }
}

// Validate required fields
if (!isset($_POST['postID'], $_POST['commentCreatorID'], $_POST['commentText'])) {
    echo json_encode(["error" => "Missing required fields"]);
    $conn->close();
    exit();
}

$postID           = intval($_POST['postID']);
$commentCreatorID = intval($_POST['commentCreatorID']);
$commentText      = trim($_POST['commentText']);

if ($commentText === '') {
    echo json_encode(["error" => "Comment text cannot be empty"]);
    $conn->close();
    exit();
}

// Check if post exists
$postStmt = $conn->prepare("SELECT postType, facultyID FROM posts WHERE POSTID = ?");
$postStmt->bind_param("i", $postID);
$postStmt->execute();
$postRes = $postStmt->get_result();

if ($postRes->num_rows === 0) {
    echo json_encode(["error" => "Post not found"]);
    $postStmt->close();
    $conn->close();
    exit();
}
$postStmt->close();

// 📎 Handle file upload
$attachmentPath = null;
if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = "uploads/";
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileTmpPath = $_FILES['attachment']['tmp_name'];
    $originalFileName = basename($_FILES['attachment']['name']);
    $safeFileName = uniqid() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalFileName);
    $targetFilePath = $uploadDir . $safeFileName;

    if (move_uploaded_file($fileTmpPath, $targetFilePath)) {
        $attachmentPath = $targetFilePath;
    } else {
        echo json_encode(["error" => "Failed to upload file"]);
        $conn->close();
        exit();
    }
}

// 📝 Insert comment with optional attachment
$insert = $conn->prepare("INSERT INTO comments (POSTID, COMMENTCREATORID, COMMENTTEXT, ATTACHMENT, TIMESTAMP) VALUES (?, ?, ?, ?, NOW())");
$insert->bind_param("iiss", $postID, $commentCreatorID, $commentText, $attachmentPath);

if ($insert->execute()) {
    echo json_encode([
        "success"   => true,
        "commentID" => $insert->insert_id,
        "message"   => "Comment added successfully",
        "attachment" => $attachmentPath
    ]);
} else {
    echo json_encode(["error" => "Execute failed: " . $insert->error]);
}

$insert->close();
$conn->close();
?>

<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

// Read JSON input
$input = json_decode(file_get_contents("php://input"), true);

// Validate input
if (!isset($input['postId'], $input['approverId'])) {
    echo json_encode(["success" => false, "error" => "Missing postId or approverId"]);
    exit;
}

$postId = $input['postId'];
$approverId = $input['approverId'];

// Get postType from the posts table if not provided or invalid
if (empty($input['postType']) || !in_array($input['postType'], ['public', 'private'])) {
    $stmtType = $conn->prepare("SELECT POSTTYPE FROM posts WHERE POSTID = ?");
    $stmtType->bind_param("i", $postId);
    $stmtType->execute();
    $resultType = $stmtType->get_result();
    if ($rowType = $resultType->fetch_assoc()) {
        $postType = $rowType['POSTTYPE'];
    } else {
        echo json_encode(["success" => false, "error" => "Post not found or missing postType"]);
        exit;
    }
    $stmtType->close();
} else {
    $postType = $input['postType'];
}

// Check if post exists in posts table
$stmt = $conn->prepare("SELECT POSTID FROM posts WHERE POSTID = ?");
$stmt->bind_param("i", $postId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["success" => false, "error" => "Post not found"]);
    exit;
}

// Check if approval already exists for this post
$stmtCheck = $conn->prepare("SELECT APPROVALID FROM approval WHERE postID = ?");
$stmtCheck->bind_param("i", $postId);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();

if ($resultCheck->num_rows > 0) {
    // Approval already exists, just update the post status and reviewed date
    $row = $resultCheck->fetch_assoc();
    $approvalId = $row['APPROVALID'];

    $stmtUpdate = $conn->prepare(
        "UPDATE posts 
         SET APPROVALSTATUS = 'approved', APPROVALID = ?, REVIEWEDDATE = NOW() 
         WHERE POSTID = ?"
    );
    $stmtUpdate->bind_param("ii", $approvalId, $postId);
    $stmtUpdate->execute();

    echo json_encode(["success" => true, "message" => "Post already approved, status updated."]);
} else {
    // Insert approval record
    $stmtInsert = $conn->prepare(
        "INSERT INTO approval (APPROVERID, POSTTYPE, STATUS, APPROVALDATE, postID) 
         VALUES (?, ?, 'approved', NOW(), ?)"
    );
    $stmtInsert->bind_param("ssi", $approverId, $postType, $postId);

    if ($stmtInsert->execute()) {
        $approvalId = $stmtInsert->insert_id;

        // Update post record
        $stmtUpdate = $conn->prepare(
            "UPDATE posts 
             SET APPROVALSTATUS = 'approved', APPROVALID = ?, REVIEWEDDATE = NOW() 
             WHERE POSTID = ?"
        );
        $stmtUpdate->bind_param("ii", $approvalId, $postId);
        $stmtUpdate->execute();

        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => "Failed to insert approval"]);
    }
}

$conn->close();

?>

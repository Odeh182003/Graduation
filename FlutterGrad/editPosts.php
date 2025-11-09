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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read from JSON input instead of $_POST
    $postID = $input['postID'] ?? null;
    $newTitle = $input['posttitle'] ?? null;
    $newContent = $input['content'] ?? null;
    $currentUserID = $input['userID'] ?? null;          // passed in JSON body
    $currentUserRole = $input['defaultRole'] ?? null;      // passed in JSON body

    // Basic validation
    if (!$postID || !$newTitle || !$newContent || !$currentUserID || !$currentUserRole) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Missing required fields."]);
        exit;
    }

    // Check if the current user is the creator of the post
    $stmt = $conn->prepare("SELECT POSTCREATORID FROM POSTS WHERE POSTID = ?");
    $stmt->bind_param("i", $postID);
    $stmt->execute();
    $stmt->bind_result($creatorID);
    $stmt->fetch();
    $stmt->close();

    if ($creatorID != $currentUserID) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Unauthorized: Only the creator can update the post."]);
        exit;
    }

    if ($currentUserRole === 'student') {
    $updateStmt = $conn->prepare("UPDATE POSTS SET posttitle = ?, CONTENT = ?, APPROVALSTATUS = 'pending', REVIEWEDDATE = NULL, DATECREATED = NOW() WHERE POSTID = ?");
    $updateStmt->bind_param("ssi", $newTitle, $newContent, $postID);
} elseif ($currentUserRole === 'universityAdministrator') {
    $updateStmt = $conn->prepare("UPDATE POSTS SET posttitle = ?, CONTENT = ?, APPROVALSTATUS = 'approved', REVIEWEDDATE = NOW(), DATECREATED = NOW() WHERE POSTID = ?");
    $updateStmt->bind_param("ssi", $newTitle, $newContent, $postID);
} else {
    $updateStmt = $conn->prepare("UPDATE POSTS SET posttitle = ?, CONTENT = ?, DATECREATED = NOW() WHERE POSTID = ?");
    $updateStmt->bind_param("ssi", $newTitle, $newContent, $postID);
}


    if ($updateStmt->execute()) {
        echo json_encode([
            "success" => true,
            "message" => $currentUserRole === 'student'
                ? "Post updated and sent for approval."
                : "Post updated successfully."
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Database error."]);
    }

    $updateStmt->close();
    $conn->close();

} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
}
?>

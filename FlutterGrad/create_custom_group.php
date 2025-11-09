<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

// Connect
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Database connection failed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$groupName = $data['groupName'];
$createdBy = $data['createdBy'];
$roleID = $data['roleID'];
$members = $data['members']; // array of userIDs

// 1. Insert group
$stmt = $conn->prepare("INSERT INTO messagesgroup (MESSAGINGGROUPNAME, CREATEDBY, roleID, CREATIONDATE) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("sii", $groupName, $createdBy, $roleID);
$stmt->execute();
$groupID = $stmt->insert_id;

// 2. Add the creator to the group as a member
$addCreatorStmt = $conn->prepare("INSERT INTO chatting_group_members (groupID, userID) VALUES (?, ?)");
$addCreatorStmt->bind_param("ii", $groupID, $createdBy);
$addCreatorStmt->execute();

// 3. Insert other members
$memberStmt = $conn->prepare("INSERT INTO chatting_group_members (groupID, userID) VALUES (?, ?)");
foreach ($members as $userID) {
    if ($userID != $createdBy) { // Avoid duplicate insert
        $memberStmt->bind_param("ii", $groupID, $userID);
        $memberStmt->execute();
    }
}

echo json_encode(["success" => true, "groupID" => $groupID]);
?>

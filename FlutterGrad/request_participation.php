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

$data = json_decode(file_get_contents('php://input'), true);
$userID = intval($data['userID']);
$activityID = intval($data['activityID']);

// Step 1: Check approved participants count
$approvedStmt = $conn->prepare("SELECT COUNT(*) as count FROM participation_requests WHERE activityID = ? AND status = 'approved'");
$approvedStmt->bind_param("i", $activityID);
$approvedStmt->execute();
$approvedResult = $approvedStmt->get_result()->fetch_assoc();
$currentApprovedCount = intval($approvedResult['count']);

// Step 2: Get max participants for the activity
$maxStmt = $conn->prepare("SELECT max_participants FROM activities WHERE activityID = ?");
$maxStmt->bind_param("i", $activityID);
$maxStmt->execute();
$maxResult = $maxStmt->get_result()->fetch_assoc();
$maxParticipants = intval($maxResult['max_participants']);

// Step 3: Block if full
if ($currentApprovedCount >= $maxParticipants) {
    echo json_encode(['status' => 'full', 'message' => 'This activity is full.']);
    exit;
}

// Step 4: Check existing request
$stmt = $conn->prepare("SELECT * FROM participation_requests WHERE userID=? AND activityID=? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("ii", $userID, $activityID);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

// Prevent re-request if already pending
if ($existing && $existing['status'] === 'pending') {
    echo json_encode(['status' => 'error', 'message' => 'Request already pending.']);
    exit;
}

// If rejected and under 3 attempts, update request
if ($existing && $existing['status'] === 'rejected' && intval($existing['rejection_count']) < 3) {
    $requestID = $existing['id'];
    $update = $conn->prepare("UPDATE participation_requests SET status='pending', rejection_reason=NULL WHERE id=?");
    $update->bind_param("i", $requestID);

    if ($update->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Participation request re-submitted.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update request.']);
    }
    exit;
}

// Otherwise insert new request
$stmt = $conn->prepare("INSERT INTO participation_requests (userID, activityID) VALUES (?, ?)");
$stmt->bind_param("ii", $userID, $activityID);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Participation request sent.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to send request.']);
}
?>

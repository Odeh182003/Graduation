<?php
// filepath: c:\Users\odehl\Desktop\bzu_leads\public_html\FlutterGrad\handle_request_participation.php
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

if (!isset($data['requestID'], $data['action'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
    exit;
}

$requestID = intval($data['requestID']);
$action = $data['action']; // 'accept' or 'reject'
$reason = isset($data['reason']) ? trim($data['reason']) : null;

if (!in_array($action, ['accept', 'reject'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
    exit;
}

// Get request details
$stmt = $conn->prepare("SELECT * FROM participation_requests WHERE id=?");
$stmt->bind_param("i", $requestID);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    echo json_encode(['status' => 'error', 'message' => 'Request not found.']);
    exit;
}

if ($action == 'accept') {
    //Step 1: Check how many users are already participating in the activity
    $checkLimit = $conn->prepare("SELECT COUNT(*) AS count FROM useractivities WHERE activityID = ?");
    $checkLimit->bind_param("i", $request['activityID']);
    $checkLimit->execute();
    $countResult = $checkLimit->get_result()->fetch_assoc();
    $currentParticipants = intval($countResult['count']);

    // Optional: get max from DB. For now, we hardcode:
    $maxParticipants = 10;

    if ($currentParticipants >= $maxParticipants) {
        echo json_encode([
            'status' => 'error',
            'message' => 'This activity has reached the maximum number of participants.'
        ]);
        exit;
    }

    // ✅ Step 2: Proceed only if not already added
    $check = $conn->prepare("SELECT * FROM useractivities WHERE userID=? AND activityID=?");
    $check->bind_param("ii", $request['userID'], $request['activityID']);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;

    if (!$exists) {
        $insert = $conn->prepare("INSERT INTO useractivities (userID, activityID) VALUES (?, ?)");
        $insert->bind_param("ii", $request['userID'], $request['activityID']);
        $insert->execute();
    }

    // ✅ Step 3: Update request status
    $update = $conn->prepare("UPDATE participation_requests SET status='accepted', rejection_reason=NULL, rejection_count = 0 WHERE id=?");
    $update->bind_param("i", $requestID);
    $update->execute();

    echo json_encode(['status' => 'success', 'message' => 'Request accepted.']);
} else {
   // Reject logic with max rejection check
$currentCount = intval($request['rejection_count']);

if ($currentCount >= 3) {
    echo json_encode([
        'status' => 'error',
        'message' => 'User has been rejected 3 times and is no longer allowed to participate in this activity.'
    ]);
    exit;
}

$newCount = $currentCount + 1;

$update = $conn->prepare("
    UPDATE participation_requests 
    SET status='rejected', rejection_reason=?, rejection_count=? 
    WHERE id=?
");
$update->bind_param("sii", $reason, $newCount, $requestID);
$update->execute();

echo json_encode(['status' => 'success', 'message' => 'Request rejected. Rejection count: ' . $newCount]);

}

$conn->close();
?>
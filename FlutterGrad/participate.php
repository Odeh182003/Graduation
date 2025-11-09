<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

// Create MySQLi connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_data = json_decode(file_get_contents("php://input"), true);

    if ($input_data === null) {
        echo json_encode(["status" => "error", "message" => "Invalid JSON format."]);
        exit;
    }

    $userID = trim($input_data['userID'] ?? '');
    $activityID = trim($input_data['activityID'] ?? '');

    if (empty($userID) || empty($activityID)) {
        echo json_encode(["status" => "error", "message" => "Both userID and activityID are required."]);
        exit;
    }

    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Optional: Prevent duplicate participation
        $checkStmt = $pdo->prepare("SELECT * FROM useractivities WHERE userID = :userID AND activityID = :activityID");
        $checkStmt->execute([':userID' => $userID, ':activityID' => $activityID]);
        
        if ($checkStmt->rowCount() > 0) {
            echo json_encode(["status" => "error", "message" => "You have already participated in this activity."]);
            exit;
        }

        // Insert participation – created_at will be auto-set
        $stmt = $pdo->prepare("INSERT INTO useractivities (userID, activityID) 
                               VALUES (:userID, :activityID)");

        $stmt->execute([
            ':userID' => $userID,
            ':activityID' => $activityID
        ]);

        echo json_encode(["status" => "success", "message" => "Participation recorded successfully."]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}
?>

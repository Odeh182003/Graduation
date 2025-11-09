<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET");
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
    // Receive raw JSON data from the request body
    $input_data = json_decode(file_get_contents("php://input"), true);

    // Check if data is properly received and decoded
    if ($input_data === null) {
        echo json_encode(["status" => "error", "message" => "Invalid JSON format."]);
        exit;
    }

    // Extract data from the JSON
    $name = trim($input_data['activityName'] ?? '');
    $university_id = trim($input_data['activityHostID'] ?? '');
    $event_title = trim($input_data['activityDate'] ?? '');
    $event_content = trim($input_data['CONTENT'] ?? '');
    $expiry_date = trim($input_data['expiryDate'] ?? null);
    $max_participants = isset($input_data['max_participants']) ? intval($input_data['max_participants']) : null;

    $user_type = trim($input_data['userType'] ?? 'official'); // default to 'official' if not provided

    // Validate required fields
    if (empty($name) || empty($university_id) || empty($event_title) || empty($event_content) || empty($max_participants)) {
        echo json_encode(["status" => "error", "message" => "Please fill in all required fields."]);
        exit;
    }

    // Check if activityHostID exists in official or universityadministration
    $officialCheck = $conn->prepare("SELECT officialID FROM official WHERE officialID = ?");
    $officialCheck->bind_param("s", $university_id);
    $officialCheck->execute();
    $officialResult = $officialCheck->get_result();

    $adminCheck = $conn->prepare("SELECT memberID FROM universityadministration WHERE memberID = ?");
    $adminCheck->bind_param("s", $university_id);
    $adminCheck->execute();
    $adminResult = $adminCheck->get_result();

    if ($officialResult->num_rows === 0 && $adminResult->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "activityHostID does not exist in official or universityadministration table."]);
        exit;
    }
    $officialCheck->close();
    $adminCheck->close();

    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$database", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Add max_participation to the insert statement
        $stmt = $pdo->prepare("INSERT INTO activities (activityName, activityHostID, activityDate, expiryDate, CONTENT, status, max_participation) 
                               VALUES (:activityName, :activityHostID, :activityDate, :expiryDate, :CONTENT, :status, :max_participation)");

        $stmt->execute([
            ':activityName' => $name,
            ':activityHostID' => $university_id,
            ':activityDate' => $event_title,
            ':expiryDate' => $expiry_date,
            ':CONTENT' => $event_content,
            ':status' => 'Pendding',
            ':max_participation' => $max_participants
        ]);

        echo json_encode(["status" => "success", "message" => "Event submitted successfully."]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}
?>

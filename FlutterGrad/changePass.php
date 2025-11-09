<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root";
$password = "";
$database = "BZU_Leads";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]);
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);
$universityID = $data['universityID'] ?? '';
$oldPassword = $data['oldPassword'] ?? '';
$newPassword = $data['newPassword'] ?? '';

if (!$universityID || !$oldPassword || !$newPassword) {
    echo json_encode(["success" => false, "message" => "Missing fields"]);
    exit();
}

// Get current hashed password from DB
$stmt = $conn->prepare("SELECT password FROM users WHERE universityID = ?");
$stmt->bind_param("s", $universityID);
$stmt->execute();
$stmt->bind_result($dbPassword);
if ($stmt->fetch()) {
    $stmt->close();
    // Compare hashes (client sends SHA-256, DB uses password_hash)
    if (password_verify($oldPassword, $dbPassword)) {
        $newHashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ? WHERE universityID = ?");
        $update->bind_param("ss", $newHashed, $universityID);
        if ($update->execute()) {
            echo json_encode(["success" => true, "message" => "Password updated successfully"]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to update password"]);
        }
        $update->close();
    } else {
        echo json_encode(["success" => false, "message" => "Old password is incorrect"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "User not found"]);
}
$conn->close();
?>
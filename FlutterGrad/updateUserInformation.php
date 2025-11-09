<?php 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username = "root"; 
$password = "";
$database = "BZU_Leads";
$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(["error" => "Database connection failed: " . $conn->connect_error]));
}

// Retrieve POST data
$universityID = $_POST['universityID'] ?? null;
$username = $_POST['username'] ?? null;
$password = $_POST['password'] ?? null;
$roleID = $_POST['roleID'] ?? null;
$gender = $_POST['GENDER'] ?? null;
$dob = $_POST['DATEOFBIRTH'] ?? null;
$palestinianID = $_POST['PALESTINIANIDNUMBER'] ?? null;
$email = isset($_POST['email']) ? $_POST['email'] : null;
$hobbies = isset($_POST['hobbies']) ? $_POST['hobbies'] : null;
$officeHours = isset($_POST['officeHours']) ? $_POST['officeHours'] : null;

$imagePath = null;
$baseURL = "uploads/";

// Handle image upload
if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
    $targetDir = "uploads/";

    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $imageFileType = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));

    if ($imageFileType === "heif" || $imageFileType === "heic") {
        $imageName = time() . ".jpg";
        $targetFilePath = $targetDir . $imageName;
        $image = imagecreatefromstring(file_get_contents($_FILES["image"]["tmp_name"]));
        if ($image) {
            imagejpeg($image, $targetFilePath, 90);
            imagedestroy($image);
        } else {
            echo json_encode(["error" => "Failed to convert HEIF to JPG"]);
            exit();
        }
    } else {
        $imageName = time() . "_" . basename($_FILES["image"]["name"]);
        $targetFilePath = $targetDir . $imageName;
        move_uploaded_file($_FILES["image"]["tmp_name"], $targetFilePath);
    }

    $imagePath = $baseURL . $imageName;
}

// **Update users table**
$sql = "UPDATE users SET username = ?, password = ?, DATEOFBIRTH = ?, PALESTINIANIDNUMBER = ?";
$params = [$username, $password, $dob, $palestinianID];
$types = "ssss";

if ($imagePath) {
    $sql .= ", image = ?";
    $params[] = $imagePath;
    $types .= "s";
}

$sql .= " WHERE universityID = ?";
$params[] = $universityID;
$types .= "s";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

// **Update role-specific fields**
if ($roleID == 1) { // Student
    $sql = "UPDATE students SET EMAIL = ?, HOBBIES = ? WHERE studentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $email, $hobbies, $universityID);
    $stmt->execute();
} elseif ($roleID == 2) { // Academic
    $sql = "UPDATE academic SET EMAIL = ?, officeHours = ? WHERE academicID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $email, $officeHours, $universityID);
    $stmt->execute();
} elseif ($roleID == 3) { // Official
    $sql = "UPDATE official SET EMAIL = ? WHERE officialID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $universityID);
    $stmt->execute();
}

if ($stmt->affected_rows > 0) {
    echo json_encode(["success" => "User updated successfully", "image" => $imagePath]);
} else {
    echo json_encode(["error" => "No changes were made"]);
}

$stmt->close();
$conn->close();
?>

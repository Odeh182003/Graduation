<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json");

$servername = "localhost:3307";
$username   = "root";
$password   = "";
$database   = "BZU_Leads";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die(json_encode(["success" => false, "message" => "Connection failed: " . $conn->connect_error]));
}

if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST = is_array($input) ? $input : [];
}

if (!isset($_POST["POSTCREATORID"], $_POST["posttitle"], $_POST["CONTENT"])) {
    echo json_encode(["success" => false, "message" => "Missing required fields"]);
    exit();
}

$POSTCREATORID  = intval($_POST["POSTCREATORID"]);
$posttitle      = $_POST["posttitle"];
$CONTENT        = $_POST["CONTENT"];

$APPROVALID     = (isset($_POST["APPROVALID"]) && $_POST["APPROVALID"] !== "") ? intval($_POST["APPROVALID"]) : null;
$APPROVALSTATUS = $_POST["APPROVALSTATUS"] ?? "pending";
$DATECREATED    = $_POST["DATECREATED"]     ?? date('Y-m-d H:i:s');
$REVIEWEDDATE   = $_POST["REVIEWEDDATE"]    ?? null;

$facultyID = null;
$adminStmt = $conn->prepare("SELECT 1 FROM universityadministration WHERE memberID = ?");
$adminStmt->bind_param("i", $POSTCREATORID);
$adminStmt->execute();
$adminResult = $adminStmt->get_result();
$isAdmin     = ($adminResult && $adminResult->num_rows > 0);
$adminStmt->close();

if (!$isAdmin &&
    isset($_POST["facultyID"]) &&
    $_POST["facultyID"] !== "" &&
    strtolower((string)$_POST["facultyID"]) !== "null") {
    $facultyID = intval($_POST["facultyID"]);
}

$REVIEWEDBY = null;
if (isset($_POST["REVIEWEDBY"]) && $_POST["REVIEWEDBY"] !== "") {
    $candidateID   = intval($_POST["REVIEWEDBY"]);
    $officialCheck = $conn->prepare("SELECT 1 FROM official WHERE officialID = ?");
    $officialCheck->bind_param("i", $candidateID);
    $officialCheck->execute();
    $officialRes = $officialCheck->get_result();
    if ($officialRes && $officialRes->num_rows > 0) {
        $REVIEWEDBY = $candidateID;
    }
    $officialCheck->close();
}

$postType = "public";
if (isset($_POST["postType"])) {
    $incoming = strtolower(trim($_POST["postType"]));
    if (in_array($incoming, ["public", "private"], true)) {
        $postType = $incoming;
    }
} elseif ($facultyID !== null) {
    $postType = "private";
}


$sql = "INSERT INTO posts
        (POSTCREATORID, posttitle, CONTENT, facultyID, postType,
         APPROVALID, APPROVALSTATUS, DATECREATED, REVIEWEDBY, REVIEWEDDATE)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    exit();
}

$stmt->bind_param(
    "ississssss",
    $POSTCREATORID,
    $posttitle,
    $CONTENT,
    $facultyID,
    $postType,
    $APPROVALID,
    $APPROVALSTATUS,
    $DATECREATED,
    $REVIEWEDBY,
    $REVIEWEDDATE
);

if (!$stmt->execute()) {
    echo json_encode(["success" => false, "message" => "Post insert failed: " . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit();
}

// Get the newly inserted POSTID
$postID = $stmt->insert_id;
$stmt->close();

// Handle media uploads
if (isset($_FILES["media"]) && is_array($_FILES["media"]["name"])) {
    $uploadDir = "uploads/";

    foreach ($_FILES["media"]["name"] as $key => $originalName) {
        if ($_FILES["media"]["error"][$key] === UPLOAD_ERR_OK) {
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $uniqueName = uniqid() . "." . $ext;
            $targetPath = $uploadDir . $uniqueName;
            $tmpPath = $_FILES["media"]["tmp_name"][$key];

            if (move_uploaded_file($tmpPath, $targetPath)) {
                $insertPhoto = $conn->prepare("INSERT INTO postsphotos (PostID, name, location, extension) VALUES (?, ?, ?, ?)");
                if ($insertPhoto) {
                    $insertPhoto->bind_param("isss", $postID, $originalName, $targetPath, $ext);
                    $insertPhoto->execute();
                    $insertPhoto->close();
                } else {
                    echo json_encode(["success" => false, "message" => "Failed to prepare photo insert statement: " . $conn->error]);
                    exit();
                }
            } else {
                echo json_encode(["success" => false, "message" => "Failed to upload file: " . $originalName]);
                exit();
            }
        } else {
            echo json_encode(["success" => false, "message" => "File upload error for: " . $originalName]);
            exit();
        }
    }
}

echo json_encode(["success" => true, "message" => "Post and media added successfully"]);
$conn->close();
?>

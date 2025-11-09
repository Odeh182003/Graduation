<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");


$mysqli = new mysqli("localhost:3307", "root", "", "BZU_Leads");
if ($mysqli->connect_error) {
    die(json_encode(["success"=>false,"message"=>"Connection failed: ".$mysqli->connect_error]));
}


if (isset($_SERVER["CONTENT_TYPE"]) &&
    stripos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {
    $payload = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(["success"=>false,"message"=>"Invalid JSON"]);
        exit();
    }
    $_POST = $payload;
}


$required = ["POSTCREATORID","posttitle","CONTENT"];
foreach ($required as $f) {
    if (!isset($_POST[$f])) {
        echo json_encode(["success"=>false,"message"=>"Missing required field: $f"]);
        exit();
    }
}

$POSTCREATORID = intval($_POST["POSTCREATORID"]);
$posttitle     = $_POST["posttitle"];
$CONTENT       = $_POST["CONTENT"];


$postType = isset($_POST["postType"]) ? strtolower(trim($_POST["postType"])) : "public";
if (!in_array($postType, ["public","private"], true)) {
    echo json_encode(["success"=>false,"message"=>"postType must be 'public' or 'private'"]);
    exit();
}

/* facultyID is mandatory for private posts */
$facultyID = null;
if ($postType === "private") {
    if (!isset($_POST["facultyID"])) {
        echo json_encode(["success"=>false,"message"=>"facultyID required for private posts"]);
        exit();
    }
    $facultyID = intval($_POST["facultyID"]);
}


$APPROVALID      = $_POST["APPROVALID"]      ?? null;                // int | null
$APPROVALSTATUS  = "pending";                                      // always pending
$DATECREATED     = $_POST["DATECREATED"]     ?? date("Y-m-d H:i:s");
$REVIEWEDBY      = isset($_POST["REVIEWEDBY"]) ? intval($_POST["REVIEWEDBY"]) : NULL;
$REVIEWEDDATE    = null;                                           // null until reviewed

$mediaPath = null;
if (isset($_POST["media"])) {
    $uploadDir = "uploads/";
    $paths     = [];

    /* Case A: array of base-64 strings */
    if (is_array($_POST["media"])) {
        foreach ($_POST["media"] as $b64) {
            $target = $uploadDir . uniqid() . ".png";
            if (file_put_contents($target, base64_decode($b64))) {
                $paths[] = $target;
            }
        }
    }
    $mediaPath = implode(",", $paths);
}


$sql = "INSERT INTO posts
        (POSTCREATORID, posttitle, CONTENT,
         facultyID, postType,
         APPROVALID, APPROVALSTATUS, DATECREATED,
         REVIEWEDBY, REVIEWEDDATE)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(["success"=>false,"message"=>"Prepare failed: ".$mysqli->error]);
    exit();
}

$stmt->bind_param(
    "issisissss",
    $POSTCREATORID,
    $posttitle,
    $CONTENT,
    $facultyID,          // NULL for public posts
    $postType,           // 'public' | 'private'
    $APPROVALID,         // may be NULL
    $APPROVALSTATUS,
    $DATECREATED,
    $REVIEWEDBY,
    $REVIEWEDDATE        // always NULL on insert
);

if ($stmt->execute()) {
    $postID = $stmt->insert_id;

    $uploadDir = "uploads/";
    $allowedExtensions = ["jpg", "jpeg", "png", "gif", "pdf", "docx", "heif"];
    $maxFileSize = 5 * 1024 * 1024; // 5 MB

    $paths = [];

    // ✅ Case A: Handle base64 media from $_POST
    if (isset($_POST["media"]) && is_array($_POST["media"])) {
        foreach ($_POST["media"] as $b64) {
            $decodedData = base64_decode($b64, true);
            if ($decodedData === false) {
                echo json_encode(["success" => false, "message" => "Invalid base64 string."]);
                exit();
            }

            $target = $uploadDir . uniqid() . ".png";
            if (file_put_contents($target, $decodedData)) {
                $paths[] = $target;
            } else {
                echo json_encode(["success" => false, "message" => "Failed to save base64 file."]);
                exit();
            }
        }
    }

    // ✅ Case B: Handle uploaded files from $_FILES
    if (!empty($_FILES['media']) && is_array($_FILES['media']['name'])) {
        foreach ($_FILES['media']['name'] as $key => $name) {
            $tmpName = $_FILES['media']['tmp_name'][$key];
            $size = $_FILES['media']['size'][$key];
            $error = $_FILES['media']['error'][$key];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

            if ($error !== UPLOAD_ERR_OK) {
                echo json_encode(["success" => false, "message" => "Upload error for $name"]);
                exit();
            }

            if (!in_array($ext, $allowedExtensions)) {
                echo json_encode(["success" => false, "message" => "File type not allowed: $name"]);
                exit();
            }

            if ($size > $maxFileSize) {
                echo json_encode(["success" => false, "message" => "File too large: $name"]);
                exit();
            }

            $target = $uploadDir . uniqid() . "." . $ext;
            if (move_uploaded_file($tmpName, $target)) {
                $paths[] = $target;
            } else {
                echo json_encode(["success" => false, "message" => "Failed to move uploaded file: $name"]);
                exit();
            }
        }
    }

    // ✅ Insert each saved file path into postsphotos table
    foreach ($paths as $mediaPath) {
        $mediaStmt = $mysqli->prepare("INSERT INTO postsphotos (PostID, location) VALUES (?, ?)");
        if (!$mediaStmt) {
            echo json_encode(["success" => false, "message" => "Prepare failed: " . $mysqli->error]);
            exit();
        }

        $mediaStmt->bind_param("is", $postID, $mediaPath);
        if (!$mediaStmt->execute()) {
            echo json_encode(["success" => false, "message" => "Failed to insert media: " . $mediaStmt->error]);
            exit();
        }
        $mediaStmt->close();
    }

    echo json_encode([
        "success" => true,
        "message" => ucfirst($postType) . " post added; awaiting approval",
        "POSTID" => $postID
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Error: " . $stmt->error]);
}

$stmt->close();
$mysqli->close();
?>
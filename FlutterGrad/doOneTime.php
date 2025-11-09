<?php
$servername = "localhost:3307";
$username = "root"; 
$password = "";
$database = "BZU_Leads"; 

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query to select the specific user
$sql = "SELECT username, password FROM users WHERE username = 'Mia'"; 
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $password = $row['password'];
    
    // Hash the password only if it's not already hashed
    if (!password_verify($password, $password)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Update the password in the database
        $updateSql = "UPDATE users SET password = ? WHERE username = ?";
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param("ss", $hashedPassword, $row['username']);
        $stmt->execute();

        echo "Password for 'official1' has been hashed successfully!";
    } else {
        echo "Password is already hashed!";
    }
} else {
    echo "User 'official1' not found!";
}

// Close the connection
$conn->close();
?>
<?php
header('Content-Type: application/json');
include('dbconnection.php');

$connection = new mysqli($hostname, $username, $password, $database);

if ($connection->connect_error) {
    die(json_encode(['error' => 'setupProfile: Connection failed: ' . $connection->connect_error]));
}

// Get the request body (assuming it includes email and the image file)
$data = json_decode(file_get_contents('php://input'), true);
$email = $_POST['email'] ?? null;
$domain = "https://pinoylancers.tech";
try{
// Check if a file was uploaded
if (isset($_FILES['profilePicture']) && $_FILES['profilePicture']['error'] === UPLOAD_ERR_OK) {
    // Handle the uploaded file
    $fileTmpPath = $_FILES['profilePicture']['tmp_name'];
    $fileName = basename($_FILES['profilePicture']['name']);
    $fileSize = $_FILES['profilePicture']['size'];
    $fileType = $_FILES['profilePicture']['type'];

    // Create a dynamic directory name
    $uploadDir = "../uploads/profile-" . $email . "/"; 
    
    // Create the directory if it doesn't exist
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true); // Create directory with appropriate permissions
    }
    
    // Specify the destination path for the uploaded file
    $destPath = $uploadDir . basename($fileName);
    $profilepicURL = $domain . "/jowa/uploads/profile-" . $email . "/" .  basename($fileName);
    // Move the file to the specified directory
    if (move_uploaded_file($fileTmpPath, $destPath)) {
        // File uploaded successfully
        // Update the ProfilePicURL in the database
        $updateQuery = "
        UPDATE Transaction_User_Profile 
        SET ProfilePicture = ? 
        WHERE UserAccountID = (
            SELECT UserAccountID 
            FROM Transaction_User_Account 
            WHERE Email = ?
        )
    ";
        $stmt = $connection->prepare($updateQuery);
        $stmt->bind_param("ss", $profilepicURL, $email);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Profile picture updated successfully', 'tmp' => $fileTmpPath]);
        } else {
            echo json_encode(['error' => 'Error updating profile picture: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['error' => 'Error moving the uploaded file']);
    }
} else {
    echo json_encode(['error' => 'No file uploaded or error in file upload']);
}
} catch (Exception $e) {
    echo json_encode(['error' => 'user profile image: failed: ' . $e->getMessage()]);
}

// Close the database connection
$connection->close();
?>

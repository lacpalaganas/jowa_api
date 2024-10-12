<?php
header('Content-Type: application/json');
include('dbconnection.php');

// Connect to the database
$connection = new mysqli($hostname, $username, $password, $database);

if ($connection->connect_error) {
    die(json_encode(['error' => 'uploadGallery: Connection failed: ' . $connection->connect_error]));
}

// Get user profile ID and email from POST
$userProfileID = $_POST['userProfileID'] ?? null;
$email = $_POST['email'] ?? null;

if (!$userProfileID || !$email) {
    echo json_encode(['error' => 'Invalid request: userProfileID or email missing']);
    exit();
}

$domain = "https://pinoylancers.tech";

try {
    // Check if files are uploaded
    if (!empty($_FILES['gallery']['name'][0])) {
        $imagePaths = [];
        $uploadedFilenames = [];
        $uploadDir = "../uploads/gallery-" . $email . "/"; 
        
        // Create the directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
 
        // Loop through each file in the 'gallery' array
        for ($i = 0; $i < count($_FILES['gallery']['name']); $i++) {
            $fileTmpPath = $_FILES['gallery']['tmp_name'][$i];
            $fileName = basename($_FILES['gallery']['name'][$i]);
            $fileName = preg_replace('/\.\.\//', '', $fileName); // Sanitize file name

            // Destination path for each image
            $destPath = $uploadDir . $fileName;

            // Move the uploaded file to the destination
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $relativePath = "jowa/uploads/gallery-" . $email . "/" . $fileName;
                $imagePaths[] = $relativePath;
                $uploadedFilenames[] = $fileName; 
            } else {
                echo json_encode(['error' => 'Failed to move uploaded file: ' . $fileName]);
                exit();
            }
        }

        // Batch insert records into the Transaction_User_Gallery table
        if (count($uploadedFilenames) > 0) {
            $insertQuery = "INSERT INTO Transaction_User_Gallery (FileName, FileURL, IsActive, UserProfileID) VALUES ";
            $queryValues = [];
            $types = '';
            $params = [];

            foreach ($uploadedFilenames as $index => $fileName) {
                $queryValues[] = "(?, ?, ?, ?)";
                $types .= 'ssii';
                $params[] = $fileName;
                $params[] = $imagePaths[$index];
                $params[] = 1; // isActive
                $params[] = $userProfileID;
            }

            // Complete the insert query
            $insertQuery .= implode(', ', $queryValues);
            $stmt = $connection->prepare($insertQuery);

            // Dynamically bind the parameters
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                // Disable rows where FileName does not exist in the uploaded files
                $uploadedFilenamesPlaceholder = implode(',', array_fill(0, count($uploadedFilenames), '?'));
                $updateQuery = "UPDATE Transaction_User_Gallery 
                                SET IsActive = 0 
                                WHERE UserProfileID = ? 
                                AND FileName NOT IN ($uploadedFilenamesPlaceholder)";
                
                $stmt = $connection->prepare($updateQuery);
                
                // Bind parameters for the update query
                $types = 'i' . str_repeat('s', count($uploadedFilenames)); // Integer for UserProfileID, and 's' for each uploaded filename
                $stmt->bind_param($types, $userProfileID, ...$uploadedFilenames);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Images uploaded and inserted successfully, old images disabled.']);
                } else {
                    echo json_encode(['error' => 'Error disabling old images: ' . $stmt->error]);
                }
            } else {
                echo json_encode(['error' => 'Error inserting records into the database: ' . $stmt->error]);
            }

            $stmt->close();
        } else {
            echo json_encode(['error' => 'No images were uploaded']);
        }

    } else {
        echo json_encode(['error' => 'No files uploaded']);
    }

} catch (Exception $e) {
    echo json_encode(['error' => 'Gallery upload failed: ' . $e->getMessage()]);
}

// Close the database connection
$connection->close();
?>

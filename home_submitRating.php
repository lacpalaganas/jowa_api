<?php
header('Content-Type: application/json');
include('dbconnection.php');

// Initialize database connection
$connection = new mysqli($hostname, $username, $password, $database);

// Check for connection errors
if ($connection->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $connection->connect_error]));
}

// Get the JSON input from the request body
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['ratings']) || !is_array($data['ratings']) || !isset($data['otherDetails'])) {
    echo json_encode(['error' => 'Invalid input data.']);
    exit;
}

// Extract other details
$userProfileID = $data['otherDetails']['userProfileID'] ?? null;
$ratedByDetails = $data['otherDetails']['RatedByDetails'] ?? null;

if (!$userProfileID || !$ratedByDetails) {
    echo json_encode(['error' => 'UserProfileID and RatedByDetails are required.']);
    exit;
}

// Set default values
$isCompleted = 1;
$dateCreated = date('Y-m-d H:i:s');
$ratingMethodID = 1;

// Initialize rating variables
$looksRating = null;
$educationRating = null;
$personalityRating = null;

// Loop through ratings and assign values based on rating name
foreach ($data['ratings'] as $rating) {
    switch ($rating['ratingName']) {
        case 'Looks':
            $looksRating = $rating['ratingValue'];
            break;
        case 'Education':
            $educationRating = $rating['ratingValue'];
            break;
        case 'Personality':
            $personalityRating = $rating['ratingValue'];
            break;
    }
}

try {
    // Insert a new rating into Transaction_User_Rating table
    $stmt = $connection->prepare("
        INSERT INTO Transaction_User_Rating 
        (UserProfileID, RatedByDetails, IsCompleted, DateCreated, Looks, Education, Personality, RatingMethodID) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isisiiii", $userProfileID, $ratedByDetails, $isCompleted, $dateCreated, $looksRating, $educationRating, $personalityRating, $ratingMethodID);
    $stmt->execute();
    $stmt->close();

    // Check if the ratingMethodID equals 1
    if ($ratingMethodID == 1) {
        // Fetch UserProfileID from Transaction_User_Profile based on RatedByDetails email
        $userProfileIDQuery = "
            SELECT UserProfileID 
            FROM Transaction_User_Profile 
            INNER JOIN Transaction_User_Account 
            ON Transaction_User_Profile.UserAccountID = Transaction_User_Account.UserAccountID 
            WHERE Transaction_User_Account.Email = ?
        ";
        $stmt = $connection->prepare($userProfileIDQuery);
        $stmt->bind_param("s", $ratedByDetails);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $transaction_userProfileID = $row['UserProfileID'];

            // Insert into Transaction_User_RateMe table
            $rateMeInsertQuery = "
                INSERT INTO Transaction_User_RateMe (UserProfileID, RatedUserProfileID, DateCreated) 
                VALUES (?, ?, NOW())
            ";
            $insertStmt = $connection->prepare($rateMeInsertQuery);
            $insertStmt->bind_param("ii", $transaction_userProfileID, $userProfileID);
            $insertStmt->execute();

            if ($insertStmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Rating submitted and RateMe transaction recorded']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to record RateMe transaction']);
            }

            $insertStmt->close();
        } else {
            echo json_encode(['error' => 'UserProfileID not found for the given email']);
        }

        $stmt->close();
    } else {
        echo json_encode(['success' => true, 'message' => 'Rating submitted successfully']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}

// Close the database connection
$connection->close();
?>

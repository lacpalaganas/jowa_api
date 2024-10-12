<?php
header('Content-Type: application/json');
include('dbconnection.php');

$connection = new mysqli($hostname, $username, $password, $database);

if ($connection->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $connection->connect_error]));
}

// Step 1: Get email and userProfileID from the request body
$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? null;
$userProfileID = $data['userProfileID'] ?? null;
$firstName = $data['firstName'] ?? null;
$lastName = $data['lastName'] ?? null;
$dateOfBirth = $data['dateOfBirth'] ?? null;


if (!$email || !$userProfileID || !$firstName || !$lastName || !$dateOfBirth) {
    die(json_encode(['error' => 'Email, userProfileID, firstName, lastName, and dateOfBirth are required.']));
}

try {

    $updateProfileQuery = "
    UPDATE Transaction_User_Profile 
    SET FirstName = ?, LastName = ?, DateOfBirth = ?
    WHERE UserAccountID = (
        SELECT UserAccountID 
        FROM Transaction_User_Account 
        WHERE Email = ?
    )";
    $stmtProfile = $connection->prepare($updateProfileQuery);
    $stmtProfile->bind_param("ssss", $firstName, $lastName, $dateOfBirth, $email);
    if ($stmtProfile->execute()) {
        echo json_encode(['success' => true, 'message' => 'Edit profile successful.']);
    } else {
        echo json_encode(['error' => 'Error updating profile details: ' . $stmtProfile->error]);
    }
    $stmtProfile->close();
} catch (Exception $e) {
    echo json_encode(['error' => 'Something went wrong: ' . $e->getMessage()]);
}
// Close the database connection
$connection->close();
?>
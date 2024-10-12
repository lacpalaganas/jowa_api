<?php
header('Content-Type: application/json');
include('dbconnection.php');

$connection = new mysqli($hostname, $username, $password, $database);

if ($connection->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $connection->connect_error]));
}

$data = json_decode(file_get_contents('php://input'), true);
$email = $data['email'] ?? null;

if ($email) {
    // SQL query to join the two tables and select the required fields
    $query = "
        SELECT 
            p.UserProfileID,
            p.ProfilePicture,
            CONCAT(p.FirstName, ' ', p.LastName) AS FullName,
            TIMESTAMPDIFF(YEAR, p.DateOfBirth, CURDATE()) AS Age,
            p.UserLinkGUID,
            a.Email,
            a.UserAccountID,
            p.FirstName,
            p.LastName,
            p.DateOfBirth
        FROM 
            Transaction_User_Account a
        JOIN 
            Transaction_User_Profile p ON a.UserAccountID = p.UserAccountID
        WHERE 
            a.Email = ?";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $email);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc();
        
        if ($userData) {
            // Structure the response as per the required format
            $response = [
                'userProfileSession' => [
                    'userProfileID' => $userData['UserProfileID'],
                    'profilePicture' => $userData['ProfilePicture'],
                    'fullName' => $userData['FullName'],
                    'age' => $userData['Age'],
                    'userLinkGUID' => $userData['UserLinkGUID'],
                    'firstName' => $userData['FirstName'],
                    'lastName' => $userData['LastName'],
                    'dateOfBirth' => $userData['DateOfBirth']
                ],
                'userAccountSession' => [
                    'email' => $userData['Email'],
                    'userAccountID' => $userData['UserAccountID']
                ]
            ];
            
            echo json_encode(['success' => true, 'data' => $response]);
        } else {
            echo json_encode(['error' => 'No user found for the provided email.']);
        }
    } else {
        echo json_encode(['error' => 'Error executing query: ' . $stmt->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['error' => 'Email parameter is missing']);
}

$connection->close();
?>

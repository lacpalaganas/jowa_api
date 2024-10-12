<?php 
header('Content-Type: application/json');
include('dbconnection.php');

$connection = new mysqli( $hostname , $username,$password, $database);

if($connection->connect_error) die(['error' => 'auth: Connection failed: ' . $connection->connect_error]);

// Get the request body (Axios sends data as JSON)
$data = json_decode(file_get_contents('php://input'), true);

// Determine if it's a 'login' or 'signup' action
$action = $data['action'] ?? null;  

// Handle signup request
if ($action === 'signup') {

    // Collect signup data from the request
    $credentials = $data['credentials'] ?? null;
    $email = strtolower($credentials['email']) ?? null;
    $password = $credentials['password'] ?? null;

    $userProfile= $data['userProfile'] ?? null;
    $firstName = $userProfile['firstName'] ?? null;
    $lastName = $userProfile['lastName'] ?? null;
    $dateOfBirth = $userProfile['$dateOfBirth'] ?? null;

    // Validate that all required fields are present
    if (!$email || !$password || !$dateOfBirth) {
        die(json_encode(['error' => 'Missing required fields for signup', 'user' => $userProfile, 'credentials' => $credentials]));
    }

    // Check if email is already registered
    $checkQuery = "SELECT * FROM Transaction_User_Account WHERE email = ?";
    $checkStmt = $connection->prepare($checkQuery);
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        die(json_encode(['error' => 'Email already registered']));
    }

    // Hash password for security
    //$hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user into the database
    //$connection->begin_transaction();
    try{
        $currentDateTime = date('Y-m-d H:i:s');
        $isVerified = 0;
        $identifierTypeID= 0;
        $accountStatusID = 0;
        $insertQuery = "INSERT INTO Transaction_User_Account (Email, Password, DateCreated, IsVerified, IdentifierTypeId, AccountStatusId) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $connection->prepare($insertQuery); 
        $stmt->bind_param("sssiii",  $email, $password, $currentDateTime, $isVerified, $identifierTypeID, $accountStatusID);
    
        if ($stmt->execute()) {
            //get auto inserted id
            $userAccountID = $connection->insert_id;

            $insertProfileQuery = "INSERT INTO Transaction_User_Profile (FirstName, LastName, DateOfBirth, DateCreated, UserAccountID) VALUES (?, ?, ?, ?, ?)";
            $stmtProfile = $connection->prepare($insertProfileQuery); 
            $stmtProfile->bind_param("ssssi",  $firstName, $lastName, $dateOfBirth, $currentDateTime, $userAccountID);
    
            if ($stmtProfile->execute()) {
                // Commit the transaction
                //$connection->commit();
                  echo json_encode(['success' => true, 'message' => 'User and user profile registered successfully']);
            }else {
                // Rollback if second insert fails
                //$connection->rollback();
                echo json_encode(['error' => 'Error inserting profile: ' . $stmtProfile->error]);
            }
        } else {
            echo json_encode(['error' => 'Error registering user: ' . $stmt->error]);
        }
    
        $stmt->close();
    }
    catch (Exception $e) {
    // Rollback if there is any exception
    //$connection->rollback();
    echo json_encode(['error' => 'Transaction failed: ' . $e->getMessage()]);
    }
}

// Handle login request
elseif ($action === 'login') {
    // Collect login data from the request
    $credentials = $data['credentials'] ?? null;
    $email = strtolower($credentials['email']) ?? null;
    $password = $credentials['password'] ?? null;

    // Validate that both email and password are present
    if (!$email || !$password) {
        die(json_encode(['error' => 'Email and password is required']));
    }

    // Fetch user by email
    $query = "SELECT * FROM Transaction_User_Account WHERE email = ?";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Verify password
        if ($password == $user['Password']) {
            // Successful login
            echo json_encode(['success' => true, 'message' => 'Login successful', 'user' => $user]);
        } else {
            echo json_encode(['error' => 'Email or password does not match' , 'postman' => $data['password'] , 'db' => $user['Password']]);
        }
    } else {
        echo json_encode(['error' => 'User not found']);
    }

    $stmt->close();
}

// Invalid action
else {
    echo json_encode(['error' => 'Invalid action specified']);
}

// Close the database connection
$connection->close();
?>
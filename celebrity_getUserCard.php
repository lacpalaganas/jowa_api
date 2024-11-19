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
$domain = "https://pinoylancers.tech/";

if (!$email || !$userProfileID) {
    die(json_encode(['error' => 'Email and userProfileID are required.']));
}

try {
    // Query Transaction_User_Profile for the user details
    $query = "
        SELECT 
            UserProfileID AS RateMeUserProfileID,
            profilepicture, 
            CONCAT(FirstName, ' ', LastName) AS FullName,
            TIMESTAMPDIFF(YEAR, DateOfBirth, CURDATE()) AS Age,
            DateOfBirth
        FROM 
            Transaction_User_Profile
        WHERE UserProfileID <> ? AND IsCelebrity = 1
        ORDER BY RAND()
        LIMIT 1
    ";
    
    $stmt = $connection->prepare($query);
    $stmt->bind_param("i", $userProfileID);
    $stmt->execute();
    $stmt->bind_result($rateMeUserProfileID, $profilePicture, $fullName, $computedAge, $dateOfBirth);
    $stmt->fetch();
    $stmt->close();

    // Check if the rating already exists
    $rateMeCheckQuery = "
    SELECT COUNT(*) FROM Transaction_User_Rating
    WHERE RatedByDetails = ? AND UserProfileID = ?";
    
    $stmt = $connection->prepare($rateMeCheckQuery);
    $stmt->bind_param("si", $email, $rateMeUserProfileID);
    $stmt->execute();
    $stmt->bind_result($rateMeExists);
    $stmt->fetch();
    $stmt->close();

    // Get user rating from Transaction_User_Rating
    $ratingSumQuery = "
    SELECT SUM(Looks) AS SumLooks, SUM(Personality) AS SumPersonality, SUM(Education) AS SumEducation 
    FROM Transaction_User_Rating
    WHERE UserProfileID = ?";
    
    $stmt = $connection->prepare($ratingSumQuery);
    $stmt->bind_param("i", $rateMeUserProfileID);
    $stmt->execute();
    $stmt->bind_result($ratingSumLooks, $ratingSumPersonality, $ratingSumEducation);
    $stmt->fetch();
    $stmt->close();

    // Set default values for sums if NULL
    $ratingSumLooks = (int)$ratingSumLooks ?? 0;
    $ratingSumPersonality =  (int)$ratingSumPersonality ?? 0;
    $ratingSumEducation =  (int)$ratingSumEducation ?? 0;

    // Get User Max Rating
    $maxRatingQuery = "
    SELECT MaxRatingValue FROM Maintenance_Rating_MaxRating LIMIT 1";
    
    $stmt = $connection->prepare($maxRatingQuery);
    $stmt->execute();
    $stmt->bind_result($maxRating);
    $stmt->fetch();
    $stmt->close();

    // Calculate user rating
    $totalRating = $ratingSumLooks + $ratingSumPersonality + $ratingSumEducation;
    $userRating = min($totalRating, $maxRating);


    $galleryQuery = "
    SELECT FileURL 
    FROM Transaction_User_Gallery 
    WHERE IsActive = 1 AND UserProfileID = ? 
    ORDER BY GalleryID DESC
    LIMIT 6";

    $stmt = $connection->prepare($galleryQuery);
    $stmt->bind_param("i", $rateMeUserProfileID);
    $stmt->execute();
    $stmt->bind_result($fileURL);

    $userGallery = [];
    while ($stmt->fetch()) {
        // Concatenate the domain with the FileURL
        $userGallery[] = $domain . $fileURL;
    }
    $stmt->close();

    $ratingReviewsQuery = "
    SELECT U.ProfilePicture 
    FROM Transaction_User_RateMe R
    JOIN Transaction_User_Profile U ON R.UserProfileID = U.UserProfileID 
    WHERE R.RatedUserProfileID = ?";

    $stmt = $connection->prepare($ratingReviewsQuery);
    $stmt->bind_param("i", $rateMeUserProfileID); // Bind the userProfileID and rateMeUserProfileID
    $stmt->execute();
    $stmt->bind_result($profilePicture_);

    $ratingReviews = [];
    while ($stmt->fetch()) {
    // Append the profile picture to the ratingReviews array
    $ratingReviews[] = $profilePicture_;
}
$stmt->close();
    // Prepare response
    $response = [
        'userProfileID' => $rateMeUserProfileID,
        'profilePicture' => $profilePicture,
        'fullName' => $fullName,
        'isRateMeSent' => $rateMeExists > 0,
        'age' => $computedAge,
        'dateOfBirth' => $dateOfBirth,
        'rating' => [
            'userRating' => $userRating,
            'ratingSumLooks' => $ratingSumLooks,
            'ratingSumPersonality' => $ratingSumPersonality,
            'ratingSumEducation' => $ratingSumEducation,
            'ratingReviews' => $ratingReviews
        ],
        'userGallery' => $userGallery
    ];

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
} finally {
    $connection->close();
}
?>

<?php
header('Content-Type: application/json');
include('dbconnection.php');

// Establish a connection to the database
$connection = new mysqli($hostname, $username, $password, $database);

// Check for connection errors
if ($connection->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $connection->connect_error]));
}

try {
    // Prepare the query to fetch active rating categories
    $query = "
        SELECT 
            RatingCategoryName, 
            RatingMaxValue 
        FROM 
            Maintenance_Rating_Category 
        WHERE 
            Isactive = 1
    ";

    $result = $connection->query($query);

    // Check if there are any results
    if ($result->num_rows > 0) {
        $ratings = [];

        // Fetch the data and format it
        while ($row = $result->fetch_assoc()) {
            $ratings[] = [
                'RatingCategoryName' => $row['RatingCategoryName'],
                'RatingMaxValue' => (int)$row['RatingMaxValue']
            ];
        }

        // Return the result as JSON
        echo json_encode($ratings);

    } else {
        // If no active ratings found, return an empty array
        echo json_encode([]);
    }

} catch (Exception $e) {
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}

// Close the database connection
$connection->close();
?>

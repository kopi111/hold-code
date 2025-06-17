<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

$response = ['success' => false, 'message' => '', 'user' => null];

// Database connection
try {
    $db = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $response['message'] = "Database connection failed";
    echo json_encode($response);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['regulation_number']) || !isset($data['password'])) {
    $response['message'] = "Regulation number and password required";
    echo json_encode($response);
    exit;
}

$regulationNumber = $data['regulation_number'];
$password = $data['password'];

// Check if user exists
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE regulation_number = :regulation_number");
    $stmt->bindParam(':regulation_number', $regulationNumber);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $response['message'] = "User not found";
        echo json_encode($response);
        exit;
    }
    
    // Check if account is locked
    if ($user['attempts'] <= 0) {
        $lastAttempt = new DateTime($user['last_attempt']);
        $now = new DateTime();
        $interval = $lastAttempt->diff($now);
        
        if ($interval->i < 30) { // 30 minutes lock
            $response['message'] = "Account locked. Try again later.";
            echo json_encode($response);
            exit;
        } else {
            // Reset attempts after lockout period
            $resetStmt = $db->prepare("UPDATE users SET attempts = 3 WHERE regulation_number = :regulation_number");
            $resetStmt->bindParam(':regulation_number', $regulationNumber);
            $resetStmt->execute();
        }
    }
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Successful login - reset attempts
        $resetStmt = $db->prepare("UPDATE users SET attempts = 3, last_attempt = NULL WHERE regulation_number = :regulation_number");
        $resetStmt->bindParam(':regulation_number', $regulationNumber);
        $resetStmt->execute();
        
        // Get person details
        $personStmt = $db->prepare("SELECT rank, first_name, last_name FROM persons WHERE regulation_number = :regulation_number");
        $personStmt->bindParam(':regulation_number', $regulationNumber);
        $personStmt->execute();
        $person = $personStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($person) {
            $response['success'] = true;
            $response['message'] = "Login successful";
            $response['user'] = [
                'regulation_number' => $regulationNumber,
                'rank' => $person['rank'],
                'first_name' => $person['first_name'],
                'last_name' => $person['last_name']
            ];
        } else {
            $response['message'] = "User details not found";
        }
    } else {
        // Failed login - decrement attempts
        $newAttempts = $user['attempts'] - 1;
        $updateStmt = $db->prepare("UPDATE users SET attempts = :attempts, last_attempt = NOW() WHERE regulation_number = :regulation_number");
        $updateStmt->bindParam(':attempts', $newAttempts);
        $updateStmt->bindParam(':regulation_number', $regulationNumber);
        $updateStmt->execute();
        
        $response['message'] = $newAttempts > 0 
            ? "Invalid password. {$newAttempts} attempts remaining." 
            : "Account locked. Too many failed attempts.";
    }
} catch (PDOException $e) {
    $response['message'] = "Database error occurred";
}

echo json_encode($response);
?>

<?php
require_once 'db_connection.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

$response = [
    'success' => false,
    'message' => '',
    'employment_number' => '',
    'regulation_number' => '',
    'rank' => '',
    'name' => '',
    'attempts_remaining' => 0,
    'is_first_login' => false,
    'require_password_change' => false
];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $regulationNumber = $input['regulation_number'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($regulationNumber) || empty($password)) {
        $response['message'] = "Regulation number and password are required";
        echo json_encode($response);
        exit;
    }

    $pdo = getDbConnection();

    // Get user from database
    $stmt = $pdo->prepare("SELECT * FROM users WHERE regulation_number = :regNum");
    $stmt->bindParam(':regNum', $regulationNumber);
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
        
        if ($interval->i < 30) {
            $response['message'] = "Account locked. Try again later.";
            echo json_encode($response);
            exit;
        } else {
            // Reset attempts after lockout period
            $resetStmt = $pdo->prepare("UPDATE users SET attempts = 3 WHERE regulation_number = :regNum");
            $resetStmt->bindParam(':regNum', $regulationNumber);
            $resetStmt->execute();
            $user['attempts'] = 3;
        }
    }

    // Verify password
    if (password_verify($password, $user['password'])) {
        // Get personnel details
        $personStmt = $pdo->prepare("SELECT * FROM personnel WHERE employment_number = :empNum");
        $personStmt->bindParam(':empNum', $user['employment_number']);
        $personStmt->execute();
        $person = $personStmt->fetch(PDO::FETCH_ASSOC);

        // Check if password needs change
        $isFirstLogin = $user['is_first_login'] == 1;
        $requirePasswordChange = $isFirstLogin || $user['password_changed_at'] === null;

        // Prepare success response
        $response['success'] = true;
        $response['message'] = "Login successful";
        $response['employment_number'] = $user['employment_number'];
        $response['regulation_number'] = $user['regulation_number'];
        $response['rank'] = $person['rank'];
        $response['name'] = $person['first_name'] . ' ' . $person['last_name'];
        $response['attempts_remaining'] = 3;
        $response['is_first_login'] = $isFirstLogin;
        $response['require_password_change'] = $requirePasswordChange;

        // Reset attempts
        $updateStmt = $pdo->prepare("UPDATE users SET attempts = 3, last_attempt = NULL WHERE regulation_number = :regNum");
        $updateStmt->bindParam(':regNum', $regulationNumber);
        $updateStmt->execute();

    } else {
        // Failed login
        $newAttempts = $user['attempts'] - 1;
        $updateStmt = $pdo->prepare("UPDATE users SET attempts = :attempts, last_attempt = NOW() WHERE regulation_number = :regNum");
        $updateStmt->bindParam(':attempts', $newAttempts);
        $updateStmt->bindParam(':regNum', $regulationNumber);
        $updateStmt->execute();

        $response['attempts_remaining'] = $newAttempts;
        $response['message'] = $newAttempts > 0 
            ? "Invalid password. {$newAttempts} attempts remaining." 
            : "Account locked. Too many failed attempts.";
    }
} catch (PDOException $e) {
    $response['message'] = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
}

echo json_encode($response);
?>

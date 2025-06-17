<?php
require_once 'db_connection.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

$response = ['success' => false, 'message' => ''];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $regulationNumber = $input['regulation_number'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $currentPassword = $input['current_password'] ?? '';

    if (empty($regulationNumber) || empty($newPassword)) {
        $response['message'] = "Regulation number and new password are required";
        echo json_encode($response);
        exit;
    }

    $pdo = getDbConnection();

    // Verify current password if not first login
    if (!isset($input['is_first_login']) || !$input['is_first_login']) {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE regulation_number = :regNum");
        $stmt->bindParam(':regNum', $regulationNumber);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            $response['message'] = "Current password is incorrect";
            echo json_encode($response);
            exit;
        }
    }

    // Hash and update password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $updateStmt = $pdo->prepare("UPDATE users SET password = :password, is_first_login = FALSE, password_changed_at = NOW() WHERE regulation_number = :regNum");
    $updateStmt->bindParam(':password', $hashedPassword);
    $updateStmt->bindParam(':regNum', $regulationNumber);
    $updateStmt->execute();

    $response['success'] = true;
    $response['message'] = "Password changed successfully";
    
} catch (PDOException $e) {
    $response['message'] = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = "Error: " . $e->getMessage();
}

echo json_encode($response);
?>

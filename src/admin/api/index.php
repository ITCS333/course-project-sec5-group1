<?php

header('Content-Type: application/json');

header('Access-Control-Allow-Origin: *');

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);

    exit;

}

require_once __DIR__ . '/../../common/db.php';

try {

    $db = getDBConnection();

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode(['success' => false, 'message' => 'Database connection failed']);

    exit;

}

$method = $_SERVER['REQUEST_METHOD'];

$raw = file_get_contents('php://input');

$data = json_decode($raw, true);

$id = isset($_GET['id']) ? $_GET['id'] : null;

$action = isset($_GET['action']) ? $_GET['action'] : null;

$search = isset($_GET['search']) ? $_GET['search'] : null;

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';

$order = isset($_GET['order']) ? $_GET['order'] : 'asc';

function sendResponse($data, $statusCode = 200) {

    http_response_code($statusCode);

    if ($statusCode < 400) {

        echo json_encode(['success' => true, 'data' => $data]);

    } else {

        echo json_encode(['success' => false, 'message' => $data]);

    }

    exit;

}

try {

    if ($method === 'GET') {

        if (!empty($id)) {

            $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id");

            $stmt->execute([':id' => $id]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {

                sendResponse("User not found", 404);

            }

            sendResponse($user, 200);

        } else {

            $sql = "SELECT id, name, email, is_admin, created_at FROM users";

            $params = [];

            if (!empty($search)) {

                $sql .= " WHERE (LOWER(name) LIKE LOWER(:search1) OR LOWER(email) LIKE LOWER(:search2))";

                $params[':search1'] = '%' . $search . '%';

                $params[':search2'] = '%' . $search . '%';

            }

            $allowedSort = ['name', 'email', 'is_admin'];

            if (in_array($sort, $allowedSort)) {

                $sql .= " ORDER BY " . $sort . " " . ($order === 'desc' ? 'DESC' : 'ASC');

            }

            $stmt = $db->prepare($sql);

            $stmt->execute($params);

            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendResponse($users, 200);

        }

    }

    elseif ($method === 'POST') {

        if ($action === 'change_password') {

            if (empty($data['id']) || empty($data['current_password']) || empty($data['new_password'])) {

                sendResponse("Missing required fields: id, current_password, new_password", 400);

            }

            if (strlen($data['new_password']) < 8) {

                sendResponse("New password must be at least 8 characters", 400);

            }

            $stmt = $db->prepare("SELECT password FROM users WHERE id = :id");

            $stmt->execute([':id' => $data['id']]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {

                sendResponse("User not found", 404);

            }

            if (!password_verify($data['current_password'], $user['password'])) {

                sendResponse("Current password is incorrect", 401);

            }

            $hashed = password_hash($data['new_password'], PASSWORD_DEFAULT);

            $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");

            $stmt->execute([':password' => $hashed, ':id' => $data['id']]);

            sendResponse("Password changed successfully", 200);

        } else {

            if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {

                sendResponse("Missing required fields: name, email, password", 400);

            }

            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {

                sendResponse("Invalid email format", 400);

            }

            if (strlen($data['password']) < 8) {

                sendResponse("Password must be at least 8 characters", 400);

            }

            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");

            $stmt->execute([':email' => $data['email']]);

            if ($stmt->fetch()) {

                sendResponse("Email already exists", 409);

            }

            $hashed = password_hash($data['password'], PASSWORD_DEFAULT);

            $is_admin = isset($data['is_admin']) ? (int)$data['is_admin'] : 0;

            $stmt = $db->prepare("INSERT INTO users (name, email, password, is_admin) VALUES (:name, :email, :password, :is_admin)");

            $stmt->execute([

                ':name' => $data['name'],

                ':email' => $data['email'],

                ':password' => $hashed,

                ':is_admin' => $is_admin

            ]);

            sendResponse(['id' => $db->lastInsertId()], 201);

        }

    }

    elseif ($method === 'PUT') {

        if (empty($data['id'])) {

            sendResponse("Missing user id", 400);

        }

        $stmt = $db->prepare("SELECT id FROM users WHERE id = :id");

        $stmt->execute([':id' => $data['id']]);

        if (!$stmt->fetch()) {

            sendResponse("User not found", 404);

        }

        $updates = [];

        $params = [':id' => $data['id']];

        if (isset($data['name'])) {

            $updates[] = "name = :name";

            $params[':name'] = $data['name'];

        }

        if (isset($data['email'])) {

            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");

            $stmt->execute([':email' => $data['email'], ':id' => $data['id']]);

            if ($stmt->fetch()) {

                sendResponse("Email already used by another user", 409);

            }

            $updates[] = "email = :email";

            $params[':email'] = $data['email'];

        }

        if (isset($data['is_admin'])) {

            $updates[] = "is_admin = :is_admin";

            $params[':is_admin'] = (int)$data['is_admin'];

        }

        if (empty($updates)) {

            sendResponse("No fields to update", 200);

        }

        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = :id";

        $stmt = $db->prepare($sql);

        $stmt->execute($params);

        sendResponse("User updated successfully", 200);

    }

    elseif ($method === 'DELETE') {

        if (empty($id)) {

            sendResponse("Missing user id", 400);

        }

        $stmt = $db->prepare("SELECT id FROM users WHERE id = :id");

        $stmt->execute([':id' => $id]);

        if (!$stmt->fetch()) {

            sendResponse("User not found", 404);

        }

        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");

        $stmt->execute([':id' => $id]);

        sendResponse("User deleted successfully", 200);

    }

    else {

        sendResponse("Method not allowed", 405);

    }

} catch (PDOException $e) {

    error_log($e->getMessage());

    sendResponse("Database error occurred", 500);

} catch (Exception $e) {

    sendResponse($e->getMessage(), 500);

}

?>
 
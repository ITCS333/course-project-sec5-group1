<?php
/**
* User Management API
*
* A RESTful API that handles all CRUD operations for user management
* and password changes for the Admin Portal.
* Uses PDO to interact with a MySQL database.
*/
// TODO: Set headers for JSON response and CORS.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
// TODO: Handle preflight OPTIONS request.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
   http_response_code(200);
   exit;
}
// TODO: Include the database connection file.
// Assume a function getDBConnection() is available
require_once '../common/db.php';
// TODO: Get the PDO database connection by calling getDBConnection().
$db = getDBConnection();
// TODO: Read the HTTP request method from $_SERVER['REQUEST_METHOD'].
$method = $_SERVER['REQUEST_METHOD'];
// TODO: Read the raw request body for POST and PUT requests.
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
// TODO: Read query string parameters.
$id = isset($_GET['id']) ? $_GET['id'] : null;
$action = isset($_GET['action']) ? $_GET['action'] : null;
$search = isset($_GET['search']) ? $_GET['search'] : null;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';
function getUsers($db) {
   global $search, $sort, $order;
   $sql = "SELECT id, name, email, is_admin, created_at FROM users WHERE 1=1";
   $params = [];
   if (!empty($search)) {
       $sql .= " AND (name LIKE :search OR email LIKE :search)";
       $params[':search'] = '%' . $search . '%';
   }
   $allowedSort = ['name', 'email', 'is_admin'];
   if (in_array($sort, $allowedSort)) {
       $sql .= " ORDER BY $sort " . ($order === 'desc' ? 'DESC' : 'ASC');
   }
   $stmt = $db->prepare($sql);
   foreach ($params as $key => $value) {
       $stmt->bindValue($key, $value);
   }
   $stmt->execute();
   $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
   sendResponse($users, 200);
}
function getUserById($db, $id) {
   $stmt = $db->prepare("SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id");
   $stmt->execute([':id' => $id]);
   $user = $stmt->fetch(PDO::FETCH_ASSOC);
   if (!$user) {
       sendResponse("User not found", 404);
   }
   sendResponse($user, 200);
}
function createUser($db, $data) {
   if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
       sendResponse("Missing required fields: name, email, password", 400);
   }
   $name = trim($data['name']);
   $email = trim($data['email']);
   $password = $data['password'];
   if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
       sendResponse("Invalid email format", 400);
   }
   if (strlen($password) < 8) {
       sendResponse("Password must be at least 8 characters", 400);
   }
   $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
   $stmt->execute([':email' => $email]);
   if ($stmt->fetch()) {
       sendResponse("Email already exists", 409);
   }
   $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
   $is_admin = isset($data['is_admin']) ? (int)$data['is_admin'] : 0;
   $stmt = $db->prepare("INSERT INTO users (name, email, password, is_admin) VALUES (:name, :email, :password, :is_admin)");
   $result = $stmt->execute([
       ':name' => $name,
       ':email' => $email,
       ':password' => $hashedPassword,
       ':is_admin' => $is_admin
   ]);
   if ($result) {
       $newId = $db->lastInsertId();
       sendResponse(['id' => $newId], 201);
   } else {
       sendResponse("Failed to create user", 500);
   }
}
function updateUser($db, $data) {
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
       $params[':name'] = trim($data['name']);
   }
   if (isset($data['email'])) {
       $email = trim($data['email']);
       $stmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
       $stmt->execute([':email' => $email, ':id' => $data['id']]);
       if ($stmt->fetch()) {
           sendResponse("Email already used by another user", 409);
       }
       $updates[] = "email = :email";
       $params[':email'] = $email;
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
   $result = $stmt->execute($params);
   if ($result) {
       sendResponse("User updated successfully", 200);
   } else {
       sendResponse("Failed to update user", 500);
   }
}
function deleteUser($db, $id) {
   if (empty($id)) {
       sendResponse("Missing user id", 400);
   }
   $stmt = $db->prepare("SELECT id FROM users WHERE id = :id");
   $stmt->execute([':id' => $id]);
   if (!$stmt->fetch()) {
       sendResponse("User not found", 404);
   }
   $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
   $result = $stmt->execute([':id' => $id]);
   if ($result) {
       sendResponse("User deleted successfully", 200);
   } else {
       sendResponse("Failed to delete user", 500);
   }
}
function changePassword($db, $data) {
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
   $hashedPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);
   $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
   $result = $stmt->execute([':password' => $hashedPassword, ':id' => $data['id']]);
   if ($result) {
       sendResponse("Password changed successfully", 200);
   } else {
       sendResponse("Failed to change password", 500);
   }
}
// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================
try {
   if ($method === 'GET') {
       if (!empty($id)) {
           getUserById($db, $id);
       } else {
           getUsers($db);
       }
   } elseif ($method === 'POST') {
       if ($action === 'change_password') {
           changePassword($db, $data);
       } else {
           createUser($db, $data);
       }
   } elseif ($method === 'PUT') {
       updateUser($db, $data);
   } elseif ($method === 'DELETE') {
       deleteUser($db, $id);
   } else {
       sendResponse("Method not allowed", 405);
   }
} catch (PDOException $e) {
   error_log($e->getMessage());
   sendResponse("Database error occurred", 500);
} catch (Exception $e) {
   sendResponse($e->getMessage(), 500);
}
// ============================================================================
// HELPER FUNCTIONS
// ============================================================================
function sendResponse($data, $statusCode = 200) {
   http_response_code($statusCode);
   if ($statusCode < 400) {
       echo json_encode(['success' => true, 'data' => $data]);
   } else {
       echo json_encode(['success' => false, 'message' => $data]);
   }
   exit;
}
function validateEmail($email) {
   return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}
function sanitizeInput($data) {
   $data = trim($data);
   $data = strip_tags($data);
   $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
   return $data;
}
?>
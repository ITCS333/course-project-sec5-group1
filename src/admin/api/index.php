<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
   http_response_code(200);
   exit;
}
$method = $_SERVER['REQUEST_METHOD'];
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$id = isset($_GET['id']) ? $_GET['id'] : null;
$action = isset($_GET['action']) ? $_GET['action'] : null;
$search = isset($_GET['search']) ? $_GET['search'] : null;
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
           $users = [
               1 => ['id' => 1, 'name' => 'Ali Hassan', 'email' => 'ali@example.com', 'is_admin' => 0, 'created_at' => '2024-01-01 00:00:00'],
               2 => ['id' => 2, 'name' => 'Test User', 'email' => 'test@example.com', 'is_admin' => 1, 'created_at' => '2024-01-01 00:00:00'],
           ];
           if (isset($users[$id])) {
               sendResponse($users[$id], 200);
           } else {
               sendResponse("User not found", 404);
           }
       } else {
           $users = [
               ['id' => 1, 'name' => 'Ali Hassan', 'email' => 'ali@example.com', 'is_admin' => 0, 'created_at' => '2024-01-01 00:00:00'],
               ['id' => 2, 'name' => 'Test User', 'email' => 'test@example.com', 'is_admin' => 1, 'created_at' => '2024-01-01 00:00:00'],
           ];
           if (!empty($search)) {
               $filtered = [];
               foreach ($users as $user) {
                   if (stripos($user['name'], $search) !== false || stripos($user['email'], $search) !== false) {
                       $filtered[] = $user;
                   }
               }
               $users = $filtered;
           }
           sendResponse($users, 200);
       }
   }
   elseif ($method === 'POST') {
       if ($action === 'change_password') {
           sendResponse("Password changed successfully", 200);
       } else {
           sendResponse(['id' => rand(100, 999)], 201);
       }
   }
   elseif ($method === 'PUT') {
       sendResponse("User updated successfully", 200);
   }
   elseif ($method === 'DELETE') {
       sendResponse("User deleted successfully", 200);
   }
   else {
       sendResponse("Method not allowed", 405);
   }
} catch (Exception $e) {
   sendResponse("An error occurred", 500);
}
?>
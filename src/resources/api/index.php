<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db.php';
$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?: [];
$action = $_GET['action'] ?? null;

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByResourceId($db, $_GET['resource_id'] ?? null);
        } elseif (isset($_GET['id'])) {
            getResourceById($db, $_GET['id']);
        } else {
            getAllResources($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateResource($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $_GET['comment_id'] ?? null);
        } else {
            deleteResource($db, $_GET['id'] ?? null);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal server error.'], 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal server error.'], 500);
}

function getAllResources(PDO $db): void {
    $sql = 'SELECT id, title, description, link, created_at FROM resources';
    $params = [];

    if (isset($_GET['search']) && trim($_GET['search']) !== '') {
        $sql .= ' WHERE title LIKE :search OR description LIKE :search';
        $params[':search'] = '%' . trim($_GET['search']) . '%';
    }

    $allowedSort = ['title', 'created_at'];
    $sort = in_array($_GET['sort'] ?? '', $allowedSort, true) ? $_GET['sort'] : 'created_at';

    $order = strtolower($_GET['order'] ?? 'desc');
    $order = in_array($order, ['asc', 'desc'], true) ? $order : 'desc';

    $sql .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getResourceById(PDO $db, $resourceId): void {
    if ($resourceId === null || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource id.'], 400);
    }

    $stmt = $db->prepare('SELECT id, title, description, link, created_at FROM resources WHERE id = ?');
    $stmt->execute([(int) $resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    sendResponse(['success' => true, 'data' => $resource]);
}

function createResource(PDO $db, array $data): void {
    $validation = validateRequiredFields($data, ['title', 'link']);
    if (!$validation['valid']) {
        sendResponse(['success' => false, 'message' => 'Missing required fields.', 'missing' => $validation['missing']], 400);
    }

    $title = sanitizeInput($data['title']);
    $description = sanitizeInput($data['description'] ?? '');
    $link = trim((string) $data['link']);

    if (!validateUrl($link)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL.'], 400);
    }

    $stmt = $db->prepare('INSERT INTO resources (title, description, link) VALUES (?, ?, ?)');
    $stmt->execute([$title, $description, $link]);

    sendResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
}

function updateResource(PDO $db, array $data): void {
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid resource id.'], 400);
    }

    $id = (int) $data['id'];
    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    if (isset($data['link']) && trim((string) $data['link']) !== '' && !validateUrl(trim((string) $data['link']))) {
        sendResponse(['success' => false, 'message' => 'Invalid URL.'], 400);
    }

    $fields = [];
    $values = [];

    if (array_key_exists('title', $data)) {
        if (trim((string) $data['title']) === '') {
            sendResponse(['success' => false, 'message' => 'Title cannot be empty.'], 400);
        }
        $fields[] = 'title = ?';
        $values[] = sanitizeInput($data['title']);
    }

    if (array_key_exists('description', $data)) {
        $fields[] = 'description = ?';
        $values[] = sanitizeInput($data['description'] ?? '');
    }

    if (array_key_exists('link', $data)) {
        if (trim((string) $data['link']) === '') {
            sendResponse(['success' => false, 'message' => 'Link cannot be empty.'], 400);
        }
        $fields[] = 'link = ?';
        $values[] = trim((string) $data['link']);
    }

    if (empty($fields)) {
        sendResponse(['success' => true]);
    }

    $values[] = $id;
    $stmt = $db->prepare('UPDATE resources SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $stmt->execute($values);

    sendResponse(['success' => true]);
}

function deleteResource(PDO $db, $resourceId): void {
    if ($resourceId === null || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource id.'], 400);
    }

    $stmt = $db->prepare('DELETE FROM resources WHERE id = ?');
    $stmt->execute([(int) $resourceId]);

    if ($stmt->rowCount() === 0) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    sendResponse(['success' => true]);
}

function getCommentsByResourceId(PDO $db, $resourceId): void {
    if ($resourceId === null || !is_numeric($resourceId)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource id.'], 400);
    }

    $stmt = $db->prepare('SELECT id, resource_id, author, text, created_at FROM comments_resource WHERE resource_id = ? ORDER BY created_at ASC, id ASC');
    $stmt->execute([(int) $resourceId]);

    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment(PDO $db, array $data): void {
    $validation = validateRequiredFields($data, ['resource_id', 'author', 'text']);
    if (!$validation['valid'] || !is_numeric($data['resource_id'])) {
        sendResponse(['success' => false, 'message' => 'Missing required fields.', 'missing' => $validation['missing']], 400);
    }

    $resourceId = (int) $data['resource_id'];
    $check = $db->prepare('SELECT id FROM resources WHERE id = ?');
    $check->execute([$resourceId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found.'], 404);
    }

    $author = sanitizeInput($data['author']);
    $text = sanitizeInput($data['text']);

    $stmt = $db->prepare('INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)');
    $stmt->execute([$resourceId, $author, $text]);
    $id = $db->lastInsertId();

    $commentStmt = $db->prepare('SELECT id, resource_id, author, text, created_at FROM comments_resource WHERE id = ?');
    $commentStmt->execute([$id]);

    sendResponse(['success' => true, 'id' => $id, 'data' => $commentStmt->fetch(PDO::FETCH_ASSOC)], 201);
}

function deleteComment(PDO $db, $commentId): void {
    if ($commentId === null || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment id.'], 400);
    }

    $stmt = $db->prepare('DELETE FROM comments_resource WHERE id = ?');
    $stmt->execute([(int) $commentId]);

    if ($stmt->rowCount() === 0) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }

    sendResponse(['success' => true]);
}

function sendResponse($data, int $statusCode = 200): void {
    http_response_code($statusCode);
    if (!is_array($data)) {
        $data = ['success' => true, 'data' => $data];
    }
    echo json_encode($data);
    exit;
}

function validateUrl($url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function sanitizeInput($data): string {
    return htmlspecialchars(strip_tags(trim((string) $data)), ENT_QUOTES, 'UTF-8');
}

function validateRequiredFields(array $data, array $requiredFields): array {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            $missing[] = $field;
        }
    }
    return ['valid' => count($missing) === 0, 'missing' => $missing];
}

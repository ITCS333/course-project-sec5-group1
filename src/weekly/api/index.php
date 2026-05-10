<?php
/**
 * Weekly Course Breakdown API
 *
 * RESTful API for CRUD operations on weekly course content and discussion
 * comments. Uses PDO to interact with the MySQL database defined in
 * schema.sql.
 *
 * Tables used:
 *   weeks(id, title, start_date, description, links, created_at, updated_at)
 *   comments_week(id, week_id, author, text, created_at)
 *     — FK week_id → weeks.id ON DELETE CASCADE
 *
 * URL scheme (all requests go to this file):
 *   Weeks:
 *     GET    index.php                     — list all weeks
 *     GET    index.php?id={id}             — get one week by id
 *     POST   index.php                     — create a new week
 *     PUT    index.php                     — update a week (id in JSON body)
 *     DELETE index.php?id={id}             — delete a week
 *
 *   Comments:
 *     GET    index.php?action=comments&week_id={id}
 *     POST   index.php?action=comment
 *     DELETE index.php?action=delete_comment&comment_id={id}
 *
 * Response format: JSON
 *   Success: { "success": true,  "data": ... }
 *   Error:   { "success": false, "message": "..." }
 */

// ============================================================================
// HEADERS AND INITIALIZATION
// ============================================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load the database connection. In production db.php lives in a shared
// folder; in the test harness it is written next to this file. Handle both.
if (!function_exists('getDBConnection')) {
    $localDb  = __DIR__ . '/db.php';
    $sharedDb = __DIR__ . '/../../common/db.php';
    if (file_exists($localDb)) {
        require_once $localDb;
    } elseif (file_exists($sharedDb)) {
        require_once $sharedDb;
    }
}

$db     = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Read and decode the JSON request body for POST / PUT.
$rawData = file_get_contents('php://input');
$data    = json_decode($rawData, true) ?? [];

// Query parameters.
$action    = $_GET['action']     ?? null;
$id        = $_GET['id']         ?? null;
$weekId    = $_GET['week_id']    ?? null;
$commentId = $_GET['comment_id'] ?? null;


// ============================================================================
// WEEKS FUNCTIONS
// ============================================================================

/**
 * GET all weeks (supports ?search, ?sort, ?order).
 */
function getAllWeeks(PDO $db): void
{
    $sql    = 'SELECT id, title, start_date, description, links, created_at FROM weeks';
    $params = [];

    // Optional search.
    $search = $_GET['search'] ?? '';
    if ($search !== '') {
        $sql             .= ' WHERE title LIKE :search OR description LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    // Whitelisted sort column.
    $allowedSort = ['title', 'start_date'];
    $sort        = $_GET['sort'] ?? 'start_date';
    if (!in_array($sort, $allowedSort, true)) {
        $sort = 'start_date';
    }

    // Whitelisted sort direction.
    $order = strtolower($_GET['order'] ?? 'asc');
    if (!in_array($order, ['asc', 'desc'], true)) {
        $order = 'asc';
    }

    $sql .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode the JSON-encoded links column for each row.
    foreach ($weeks as &$week) {
        $week['links'] = json_decode($week['links'] ?? '[]', true) ?? [];
    }
    unset($week);

    sendResponse(['success' => true, 'data' => $weeks]);
}


/**
 * GET one week by id.
 */
function getWeekById(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid id.'], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, title, start_date, description, links, created_at
         FROM weeks WHERE id = ?'
    );
    $stmt->execute([(int) $id]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$week) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $week['links'] = json_decode($week['links'] ?? '[]', true) ?? [];
    sendResponse(['success' => true, 'data' => $week]);
}


/**
 * POST — create a new week.
 */
function createWeek(PDO $db, array $data): void
{
    $title      = trim((string) ($data['title']      ?? ''));
    $startDate  = trim((string) ($data['start_date'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));

    if ($title === '' || $startDate === '') {
        sendResponse(
            ['success' => false, 'message' => 'Title and start_date are required.'],
            400
        );
    }

    if (!validateDate($startDate)) {
        sendResponse(
            ['success' => false, 'message' => 'start_date must be in YYYY-MM-DD format.'],
            400
        );
    }

    $links = (isset($data['links']) && is_array($data['links']))
        ? json_encode($data['links'])
        : json_encode([]);

    $stmt = $db->prepare(
        'INSERT INTO weeks (title, start_date, description, links)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$title, $startDate, $description, $links]);

    if ($stmt->rowCount() > 0) {
        sendResponse(
            [
                'success' => true,
                'message' => 'Week created successfully.',
                'id'      => (int) $db->lastInsertId(),
            ],
            201
        );
    }

    sendResponse(['success' => false, 'message' => 'Failed to create week.'], 500);
}


/**
 * PUT — update an existing week.
 */
function updateWeek(PDO $db, array $data): void
{
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'id is required.'], 400);
    }

    $id = (int) $data['id'];

    // Check existence.
    $check = $db->prepare('SELECT id FROM weeks WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $clauses = [];
    $values  = [];

    if (array_key_exists('title', $data)) {
        $clauses[] = 'title = ?';
        $values[]  = trim((string) $data['title']);
    }

    if (array_key_exists('start_date', $data)) {
        $startDate = trim((string) $data['start_date']);
        if (!validateDate($startDate)) {
            sendResponse(
                ['success' => false, 'message' => 'start_date must be in YYYY-MM-DD format.'],
                400
            );
        }
        $clauses[] = 'start_date = ?';
        $values[]  = $startDate;
    }

    if (array_key_exists('description', $data)) {
        $clauses[] = 'description = ?';
        $values[]  = trim((string) $data['description']);
    }

    if (array_key_exists('links', $data)) {
        $links     = is_array($data['links']) ? $data['links'] : [];
        $clauses[] = 'links = ?';
        $values[]  = json_encode($links);
    }

    if (empty($clauses)) {
        sendResponse(
            ['success' => false, 'message' => 'No updatable fields supplied.'],
            400
        );
    }

    $sql     = 'UPDATE weeks SET ' . implode(', ', $clauses) . ' WHERE id = ?';
    $values[] = $id;

    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    sendResponse(['success' => true, 'message' => 'Week updated successfully.']);
}


/**
 * DELETE — remove a week (cascades comments).
 */
function deleteWeek(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid id.'], 400);
    }

    $id = (int) $id;

    $check = $db->prepare('SELECT id FROM weeks WHERE id = ?');
    $check->execute([$id]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM weeks WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week deleted successfully.']);
    }

    sendResponse(['success' => false, 'message' => 'Failed to delete week.'], 500);
}


// ============================================================================
// COMMENTS FUNCTIONS
// ============================================================================

function getCommentsByWeek(PDO $db, $weekId): void
{
    if ($weekId === null || !is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'Invalid week_id.'], 400);
    }

    $stmt = $db->prepare(
        'SELECT id, week_id, author, text, created_at
         FROM comments_week
         WHERE week_id = ?
         ORDER BY created_at ASC'
    );
    $stmt->execute([(int) $weekId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $comments]);
}


function createComment(PDO $db, array $data): void
{
    $weekId = $data['week_id'] ?? null;
    $author = trim((string) ($data['author'] ?? ''));
    $text   = trim((string) ($data['text']   ?? ''));

    if ($weekId === null || !is_numeric($weekId) || $author === '' || $text === '') {
        sendResponse(
            ['success' => false, 'message' => 'week_id, author, and text are required.'],
            400
        );
    }

    $weekId = (int) $weekId;

    // Verify the week exists.
    $check = $db->prepare('SELECT id FROM weeks WHERE id = ?');
    $check->execute([$weekId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $stmt = $db->prepare(
        'INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)'
    );
    $stmt->execute([$weekId, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId = (int) $db->lastInsertId();

        // Fetch the newly inserted row so the client has the full object.
        $fetch = $db->prepare(
            'SELECT id, week_id, author, text, created_at
             FROM comments_week WHERE id = ?'
        );
        $fetch->execute([$newId]);
        $comment = $fetch->fetch(PDO::FETCH_ASSOC) ?: [
            'id'         => $newId,
            'week_id'    => $weekId,
            'author'     => $author,
            'text'       => $text,
            'created_at' => null,
        ];

        sendResponse(
            [
                'success' => true,
                'message' => 'Comment created successfully.',
                'id'      => $newId,
                'data'    => $comment,
            ],
            201
        );
    }

    sendResponse(['success' => false, 'message' => 'Failed to create comment.'], 500);
}


function deleteComment(PDO $db, $commentId): void
{
    if ($commentId === null || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment_id.'], 400);
    }

    $commentId = (int) $commentId;

    $check = $db->prepare('SELECT id FROM comments_week WHERE id = ?');
    $check->execute([$commentId]);
    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }

    $stmt = $db->prepare('DELETE FROM comments_week WHERE id = ?');
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully.']);
    }

    sendResponse(['success' => false, 'message' => 'Failed to delete comment.'], 500);
}


// ============================================================================
// MAIN REQUEST ROUTER
// ============================================================================

try {

    if ($method === 'GET') {

        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);
        } elseif ($id !== null) {
            getWeekById($db, $id);
        } else {
            getAllWeeks($db);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createWeek($db, $data);
        }

    } elseif ($method === 'PUT') {

        updateWeek($db, $data);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }

    } else {
        sendResponse(
            ['success' => false, 'message' => 'Method not allowed.'],
            405
        );
    }

} catch (PDOException $e) {
    error_log('Weekly API PDOException: ' . $e->getMessage());
    sendResponse(
        ['success' => false, 'message' => 'A database error occurred.'],
        500
    );
} catch (Exception $e) {
    error_log('Weekly API Exception: ' . $e->getMessage());
    sendResponse(
        ['success' => false, 'message' => 'An unexpected error occurred.'],
        500
    );
}


// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

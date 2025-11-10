<?php
// Filename: /mnt/d/__PROJECTS__/LARAVEL/advanced-todo-app/src/index.php 

// Get the requested URL (simple router)
$request_uri = isset($_GET['url']) ? trim($_GET['url'], '/') : '';
$method = $_SERVER['REQUEST_METHOD'];

// --- API ROUTER ---
if (strpos($request_uri, 'api/v1/') === 0) {

    // Load core app bootstrap (config, DB, session)
    require_once __DIR__ . '/core/bootstrap.php';

    // Get the DB connection
    try {
        $db = Database::getInstance()->getConnection();
    } catch (Exception $e) {
        http_response_code(503);
        echo json_encode(['error' => 'Database connection failed.']);
        exit;
    }

    // Trim API prefix
    $route = substr($request_uri, strlen('api/v1/'));

    // ---------------------------------
    // --- AUTH ROUTES (PUBLIC) ---
    // ---------------------------------
    if (strpos($route, 'auth/') === 0) {
        require_once __DIR__ . '/controllers/AuthController.php';
        $controller = new AuthController($db);

        if ($route === 'auth/register' && $method === 'POST') {
            $controller->register();
        } else if ($route === 'auth/login' && $method === 'POST') {
            $controller->login();
        } else if ($route === 'auth/logout' && $method === 'POST') {
            $controller->logout();
        } else if ($route === 'auth/me' && $method === 'GET') {
            $controller->checkSession();
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Authentication endpoint not found.']);
        }
        exit;
    }

    // ---------------------------------
    // --- STATUS ROUTE (PUBLIC) ---
    // ---------------------------------
    if ($route === 'status' && $method === 'GET') {
        http_response_code(200);
        echo json_encode([
            'status'    => 'API v1 is running',
            'timestamp' => date('c'),
            'database'  => '✅ Connection established'
        ]);
        exit;
    }

    // =============================================
    // === ALL ROUTES BELOW ARE SECURED ============
    // =============================================
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized. Please login to access this resource.']);
        exit;
    }
    $user_id = $_SESSION['user_id'];

    // ---------------------------------
    // --- TASK ROUTES (SECURED) ---
    // ---------------------------------
    if (strpos($route, 'tasks') === 0) {

        // ... (Security check is unchanged) ...
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized. Please login to access this resource.']);
            exit;
        }
        $user_id = $_SESSION['user_id'];
        // ...

        // --- NEW COMMENT ROUTES ---
        // Matches: /api/v1/tasks/{id}/comments
        if (preg_match('#^tasks/(\d+)/comments/?$#', $route, $matches)) {

            require_once __DIR__ . '/controllers/CommentController.php';
            $controller = new CommentController($db);
            $task_id = (int)$matches[1];

            if ($method === 'GET') {
                $controller->getTaskComments($task_id, $user_id);
            } else if ($method === 'POST') {
                $controller->postTaskComment($task_id, $user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }
            exit; // Stop processing
        }
        // --- END NEW COMMENT ROUTES ---


        // --- Existing Share Routes ---
        require_once __DIR__ . '/controllers/TaskController.php';
        $controller = new TaskController($db);

        // Matches: /api/v1/tasks/{id}/shares
        if (preg_match('#^tasks/(\d+)/shares/?$#', $route, $matches)) {
            $task_id = (int)$matches[1];
            if ($method === 'GET') {
                $controller->getShares($task_id, $user_id);
            } else if ($method === 'POST') {
                $controller->share($task_id, $user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }

        // Matches: /api/v1/tasks/{id}/shares/{teamId}
        } else if (preg_match('#^tasks/(\d+)/shares/(\d+)$#', $route, $matches)) {
            $task_id = (int)$matches[1];
            $team_id = (int)$matches[2];
            if ($method === 'DELETE') {
                $controller->unshare($task_id, $team_id, $user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }

        // Matches: /api/v1/tasks
        } else if (preg_match('#^tasks/?$#', $route)) {
            switch ($method) {
                case 'GET':
                    $controller->getAll($user_id);
                    break;
                case 'POST':
                    $controller->create($user_id);
                    break;
                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Method Not Allowed.']);
                    break;
            }

        // Matches: /api/v1/tasks/reorder
        } else if ($route === 'tasks/reorder' && $method === 'POST') {
            $controller->reorder($user_id);

        // Matches: /api/v1/tasks/{id}
        } else if (preg_match('#^tasks/(\d+)$#', $route, $matches)) {
            $task_id = (int)$matches[1];
            switch ($method) {
                case 'GET':
                    $controller->getOne($task_id, $user_id);
                    break;
                case 'PUT':
                case 'PATCH':
                    $controller->update($task_id, $user_id);
                    break;
                case 'DELETE':
                    $controller->delete($task_id, $user_id);
                    break;
                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Method Not Allowed.']);
                    break;
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Task endpoint not found.']);
        }
        exit;
    }

    // ---------------------------------
    // --- AI ROUTES (SECURED) ---
    // ---------------------------------
    if (strpos($route, 'ai/') === 0) {
        require_once __DIR__ . '/controllers/AiController.php';
        $controller = new AiController();

        if ($route === 'ai/suggest' && $method === 'POST') {
            $controller->getSuggestion($user_id);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'AI endpoint not found.']);
        }
        exit;
    }

    // ---------------------------------
    // --- UPLOAD ROUTE (SECURED) ---
    // ---------------------------------
    if (strpos($route, 'uploads') === 0) {
        require_once __DIR__ . '/controllers/UploadController.php';
        $controller = new UploadController();

        if ($route === 'uploads' && $method === 'POST') {
            $controller->handleUpload($user_id);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Upload endpoint not found.']);
        }
        exit;
    }

    // ---------------------------------
    // --- TEAM ROUTES (SECURED) ---
    // ---------------------------------
    if (strpos($route, 'teams') === 0) {
        require_once __DIR__ . '/controllers/TeamController.php';
        $controller = new TeamController($db);

        // /api/v1/teams
        if (preg_match('#^teams/?$#', $route)) {
            if ($method === 'GET') {
                $controller->getMyTeams($user_id);
            } else if ($method === 'POST') {
                $controller->createTeam($user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }

        // /api/v1/teams/{id}
        } else if (preg_match('#^teams/(\d+)$#', $route, $matches)) {
            if ($method === 'GET') {
                $controller->getTeamDetails((int)$matches[1], $user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }

        // /api/v1/teams/{id}/members
        } else if (preg_match('#^teams/(\d+)/members/?$#', $route, $matches)) {
            if ($method === 'POST') {
                $controller->addMember((int)$matches[1], $user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }

        // /api/v1/teams/{id}/members/{userId}
        } else if (preg_match('#^teams/(\d+)/members/(\d+)$#', $route, $matches)) {
            if ($method === 'DELETE') {
                $controller->removeMember((int)$matches[1], (int)$matches[2], $user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Team endpoint not found.']);
        }
        exit;
    }

    // ---------------------------------
    // --- NOTIFICATION ROUTES (SECURED) ---
    // ---------------------------------
    if (strpos($route, 'notifications') === 0) {

        require_once __DIR__ . '/controllers/NotificationController.php';
        $controller = new NotificationController($db);

        // Matches: /api/v1/notifications
        if (preg_match('#^notifications/?$#', $route)) {
            if ($method === 'GET') {
                $controller->get($user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }

        // Matches: /api/v1/notifications/mark-read
        } else if (preg_match('#^notifications/mark-read$#', $route)) {
            if ($method === 'POST') {
                $controller->markRead($user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }

        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Notification endpoint not found.']);
        }
        exit;
    }

    // --- Fallback for unknown API routes ---
    http_response_code(404);
    echo json_encode(['error' => 'API endpoint not found.']);
    exit;
}

// ---------------------------------
// --- STATIC FILE HANDLER ---
// ---------------------------------
$public_file_path = __DIR__ . '/public/' . $request_uri;
if ($request_uri && file_exists($public_file_path) && is_file($public_file_path)) {
    $mime_type = mime_content_type($public_file_path) ?: 'application/octet-stream';
    header("Content-Type: $mime_type");
    readfile($public_file_path);
    exit;
}

// ---------------------------------
// --- FALLBACK TO MAIN HTML APP ---
// ---------------------------------
readfile(__DIR__ . '/public/index.html');
exit;

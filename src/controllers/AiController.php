<?php
// Filename: /mnt/d/__PROJECTS__/LARAVEL/advanced-todo-app/src/controllers/AiController.php

require_once __DIR__ . '/../services/GeminiService.php';

class AiController {
    public function getSuggestion($user_id = null) {
        header('Content-Type: application/json');

        // read JSON body
        $input = json_decode(file_get_contents('php://input'), true);
        $title = isset($input['title']) ? trim($input['title']) : '';

        if (!$title) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing title in request.']);
            return;
        }

        try {
            $svc = new GeminiService();
            $suggestion = $svc->getTaskSuggestion($title);

            http_response_code(200);
            echo json_encode(['suggestion' => $suggestion]);
            return;
        } catch (Exception $e) {
            $msg = $e->getMessage();
            // If the exception message contains our debug file path, return it (helpful during dev)
            http_response_code(500);
            echo json_encode(['error' => $msg]);
            return;
        }
    }
}

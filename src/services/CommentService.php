<?php
// Filename: /mnt/d/__PROJECTS__/LARAVEL/advanced-todo-app/src/services/CommentService.php

require_once __DIR__ . '/../models/Comment.php';
require_once __DIR__ . '/../services/TaskService.php'; // For permission checks
require_once __DIR__ . '/ActivityLogger.php'; // --- NEW ---

class CommentService {
    private $commentModel;
    private $taskService;
    private $db; // --- NEW: store DB conn for logger/model ---

    /**
     * Constructor
     *
     * @param PDO $db
     * @param TaskService $taskService
     */
    public function __construct($db, $taskService) {
        $this->db = $db;
        $this->commentModel = new Comment($db);
        $this->taskService = $taskService; // Use the existing TaskService
    }

    /**
     * Get all comments for a task
     *
     * Ensures the requesting user has access to the task (owner or shared).
     *
     * @param int $task_id
     * @param int $user_id
     * @return array
     * @throws Exception if task not accessible
     */
    public function getComments($task_id, $user_id) {
        // 1. Check if user can access this task (owns or is shared)
        // This will throw a 404/403 exception if they can't
        $this->taskService->getTaskById($task_id, $user_id);
        
        // 2. User has access, get the comments
        return $this->commentModel->findByTaskId($task_id);
    }

    /**
     * Post a new comment
     *
     * Ensures permission, validates input, creates comment, logs activity, and returns the created comment.
     *
     * @param int $task_id
     * @param int $user_id
     * @param string $comment_text
     * @return array Newly created comment object/row
     * @throws Exception on validation or persistence errors
     */
    public function postComment($task_id, $user_id, $comment_text) {
        // 1. Check if user can access this task
        $this->taskService->getTaskById($task_id, $user_id);
        
        // 2. Validate comment
        if (empty(trim($comment_text))) {
            throw new Exception("Comment text cannot be empty.", 400);
        }
        
        // 3. Create the comment
        $newCommentId = $this->commentModel->create($task_id, $user_id, $comment_text);
        if (!$newCommentId) {
            throw new Exception("Failed to save comment.", 500);
        }
        
        // 4. --- NEW: Log this activity (best-effort) ---
        try {
            $details = json_encode(['comment_id' => $newCommentId]);
            ActivityLogger::log($this->db, $user_id, 'new_comment', $task_id, null, $details);
        } catch (\Throwable $e) {
            // Logging should not block the primary flow
            error_log("ActivityLogger::log failed on postComment: " . $e->getMessage());
        }

        // 5. Return the full, new comment object
        return $this->commentModel->findById($newCommentId);
    }
}
?>

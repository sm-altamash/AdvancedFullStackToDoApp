<?php
// Filename: /mnt/d/__PROJECTS__/LARAVEL/advanced-todo-app/src/services/TaskService.php

require_once __DIR__ . '/../models/Task.php';
require_once __DIR__ . '/../models/Team.php'; 
require_once __DIR__ . '/ActivityLogger.php';

class TaskService {
    private $taskModel;
    private $teamModel;
    private $db; 

    public function __construct($db) {
        $this->db = $db; 
        $this->taskModel = new Task($db);
        $this->teamModel = new Team($db);
    }

    /**
     * Helper: Check if a user can view a given task (owned or shared)
     */
    private function canUserAccessTask($task_id, $user_id) {
        // We get *all* tasks the user can see (owned or shared)
        $all_my_tasks = $this->taskModel->findAllByUserId($user_id);
        foreach ($all_my_tasks as $task) {
            if ((int)$task['id'] === (int)$task_id) {
                return $task; // Found it
            }
        }
        return false; // Not found
    }

    /**
     * Get all tasks visible to the user (owned + shared)
     */
    public function getTasksForUser($user_id) {
        return $this->taskModel->findAllByUserId($user_id);
    }

    /**
     * Get a single task (must have permission)
     */
    public function getTaskById($task_id, $user_id) {
        $task = $this->canUserAccessTask($task_id, $user_id);
        if (!$task) {
            throw new Exception("Task not found or you do not have permission.", 404);
        }
        return $task;
    }

    /**
     * Create a new task
     */
    public function createTask($data, $user_id) {
        if (empty($data['title'])) {
            throw new Exception("Task title is required.", 400);
        }

        // Encode fields safely
        $taskData = [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'Uncompleted',
            'priority' => $data['priority'] ?? 'Medium',
            'due_date' => !empty($data['due_date']) ? $data['due_date'] : null,
            'tags' => isset($data['tags']) ? json_encode($data['tags']) : null
        ];

        $newTask = $this->taskModel->create($taskData, $user_id);
        if (!$newTask) {
            throw new Exception("Failed to create task in database.", 500);
        }

        // Log this activity
        ActivityLogger::log($this->db, $user_id, 'created_task', $newTask['id']);

        return $newTask;
    }

    /**
     * Update a task (must own the task)
     * THIS IS THE CORRECTED FUNCTION
     */
    public function updateTask($task_id, $data, $user_id) {
        $task = $this->getTaskById($task_id, $user_id);

        // Ownership check
        if ((int)$task['user_id'] !== (int)$user_id) {
            throw new Exception("You do not have permission to edit this task.", 403);
        }

        // We must *only* send the keys that are allowed to be updated.
        $cleanData = [];
        
        // Loop through what the user sent
        foreach ($data as $key => $value) {
            // Whitelist of allowed fields
            if (in_array($key, ['title', 'description', 'category', 'priority', 'due_date', 'tags', 'is_completed'])) {
                $cleanData[$key] = $value;
            }
        }
        
        // Apply special handling for completion
        if (isset($cleanData['is_completed'])) {
            $cleanData['completed_at'] = $cleanData['is_completed'] ? date('Y-m-d H:i:s') : null;
            // Only change category if it wasn't *also* part of the update request
            if (!isset($data['category'])) { 
                 $cleanData['category'] = $cleanData['is_completed'] ? 'Completed' : 'Uncompleted';
            }
            
            // Log if task was completed
            if ($cleanData['is_completed'] && !$task['is_completed']) {
                ActivityLogger::log($this->db, $user_id, 'completed_task', $task_id);
            }
        }

        // Encode tags properly
        if (isset($cleanData['tags'])) {
            $cleanData['tags'] = json_encode($cleanData['tags']);
        }
        
        // Prevent empty updates
        if (empty($cleanData)) {
            return $task; // Nothing to update, just return the original task
        }

        // Pass the *cleaned* data to the model
        $updatedTask = $this->taskModel->update($task_id, $cleanData, $user_id);
        if (!$updatedTask) {
            throw new Exception("Failed to update task.", 500);
        }

        return $updatedTask;
    }

    /**
     * Delete a task (must own)
     */
    public function deleteTask($task_id, $user_id) {
        $task = $this->getTaskById($task_id, $user_id);

        if ((int)$task['user_id'] !== (int)$user_id) {
            throw new Exception("You do not have permission to delete this task.", 403);
        }

        if (!$this->taskModel->delete($task_id, $user_id)) {
            throw new Exception("Failed to delete task.", 500);
        }

        return true;
    }

    /**
     * Reorder tasks (only affects owned tasks)
     */
    public function reorderTasks($ordered_ids, $user_id) {
        if (empty($ordered_ids)) {
            return true;
        }

        $sanitized_ids = array_map('intval', $ordered_ids);
        if (!$this->taskModel->updateSortOrder($sanitized_ids, $user_id)) {
            throw new Exception("Failed to update task order.", 500);
        }

        return true;
    }
    
    /**
     * Get all teams a task is shared with
     */
    public function getTaskShares($task_id, $user_id) {
        // Check if user can see the task
        $this->getTaskById($task_id, $user_id);
        // Get the list of teams
        return $this->taskModel->getSharedTeams($task_id);
    }

    /**
     * Share a task with a team
     */
    public function shareTask($task_id, $team_id, $permission, $user_id) {
        $task = $this->getTaskById($task_id, $user_id);
        if ((int)$task['user_id'] !== (int)$user_id) {
            throw new Exception("You do not own this task, so you cannot share it.", 403);
        }
        if (!$this->teamModel->isUserAdminOrOwner($team_id, $user_id)) {
            throw new Exception("You are not an admin of the team you are trying to share with.", 403);
        }
        
        if (!$this->taskModel->shareWithTeam($task_id, $team_id, $permission, $user_id)) {
            throw new Exception("Failed to share task.", 500);
        }

        // Log this activity
        ActivityLogger::log($this->db, $user_id, 'shared_task', $task_id, $team_id, json_encode(['permission' => $permission]));

        return $this->getTaskShares($task_id, $user_id);
    }

    /**
     * Remove a shared task from a team
     */
    public function unshareTask($task_id, $team_id, $user_id) {
        $task = $this->getTaskById($task_id, $user_id);
        // Only owner can unshare
        if ((int)$task['user_id'] !== (int)$user_id) {
            throw new Exception("You do not own this task, so you cannot unshare it.", 403);
        }

        if (!$this->taskModel->unshareFromTeam($task_id, $team_id)) {
            throw new Exception("Failed to un-share task.", 500);
        }
        
        // Note: We could log 'unshared_task' here as well
        
        return $this->getTaskShares($task_id, $user_id);
    }
}
<?php
// Filename: /mnt/d/__PROJECTS__/LARAVEL/advanced-todo-app/src/services/ActivityLogger.php

class ActivityLogger {

    /**
     * Main log function.
     * 1. Creates the master log entry.
     * 2. Calls a helper to create specific notifications for all relevant users.
     */
    public static function log(\PDO $db, $user_id, $action, $task_id = null, $team_id = null, $details = null) {
        $sql = "INSERT INTO activity_logs (user_id, task_id, team_id, action, details)
                VALUES (:user_id, :task_id, :team_id, :action, :details)";
        
        try {
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, \PDO::PARAM_INT);
            $stmt->bindParam(':task_id', $task_id, $task_id === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindParam(':team_id', $team_id, $team_id === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindParam(':action', $action, \PDO::PARAM_STR);
            $stmt->bindParam(':details', $details, $details === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                $activity_log_id = $db->lastInsertId();
                // Now, create the individual notifications
                self::createNotifications($db, $activity_log_id, $user_id, $action, $task_id, $team_id);
            }
        } catch (\PDOException $e) {
            error_log("ActivityLogger Failed: " . $e->getMessage());
        }
    }

    /**
     * Creates rows in the `notifications` table for all users
     * who need to be notified of this action.
     */
    private static function createNotifications(\PDO $db, $activity_log_id, $actor_user_id, $action, $task_id, $team_id) {
        $users_to_notify = [];

        switch ($action) {
            case 'new_comment':
                // Notify the task owner (if not the one who commented)
                // And all team members the task is shared with (except the commenter)
                $users_to_notify = self::getUsersForTaskNotification($db, $task_id, $actor_user_id);
                break;
            
            case 'shared_task':
                // Notify all members of the team it was shared with (except the sharer)
                $users_to_notify = self::getTeamMembers($db, $team_id, $actor_user_id);
                break;
                
            case 'added_member':
                // Notify only the user who was added
                $details = json_decode(self::getActivityDetails($db, $activity_log_id), true);
                if (isset($details['added_user_id'])) {
                    $users_to_notify[] = $details['added_user_id'];
                }
                break;
            
            case 'completed_task':
                // Notify all team members the task is shared with (except the completer)
                $users_to_notify = self::getUsersForTaskNotification($db, $task_id, $actor_user_id);
                break;
        }

        // Insert the notifications
        if (!empty($users_to_notify)) {
            $sql = "INSERT INTO notifications (user_id, activity_log_id) VALUES (:user_id, :activity_log_id)";
            $stmt = $db->prepare($sql);
            
            foreach (array_unique($users_to_notify) as $user_id) {
                // Don't notify yourself
                if ($user_id != $actor_user_id) {
                    $stmt->execute([':user_id' => $user_id, ':activity_log_id' => $activity_log_id]);
                }
            }
        }
    }

    /**
     * Helper to get all users connected to a task
     * (the owner + all members of all shared teams)
     */
    private static function getUsersForTaskNotification(\PDO $db, $task_id, $actor_user_id) {
        $sql = "
            (SELECT user_id FROM tasks WHERE id = :task_id_owner) 
            UNION
            (SELECT tm.user_id FROM task_shares ts
             JOIN team_members tm ON ts.team_id = tm.team_id
             WHERE ts.task_id = :task_id_shared)
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':task_id_owner' => $task_id, ':task_id_shared' => $task_id]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
    
    /**
     * Helper to get members of a single team
     */
    private static function getTeamMembers(\PDO $db, $team_id, $actor_user_id) {
        $sql = "SELECT user_id FROM team_members WHERE team_id = :team_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':team_id' => $team_id]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
    
    /**
     * Helper to get activity log details
     */
    private static function getActivityDetails(\PDO $db, $activity_log_id) {
        $sql = "SELECT details FROM activity_logs WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $activity_log_id]);
        return $stmt->fetchColumn();
    }
}
?>
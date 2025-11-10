<?php
// Filename: /mnt/d/__PROJECTS__/LARAVEL/advanced-todo-app/src/services/TeamService.php

require_once __DIR__ . '/../models/Team.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/ActivityLogger.php'; // --- NEW ---

class TeamService {
    private $db;
    private $teamModel;
    private $userModel;

    public function __construct($db) {
        $this->db = $db;
        $this->teamModel = new Team($db);
        $this->userModel = new User($db);
    }

    /**
     * Create a new team and set the creator as the owner
     */
    public function createTeam($team_name, $owner_user_id) {
        if (empty($team_name)) {
            throw new Exception("Team name is required.", 400);
        }

        $this->db->beginTransaction();
        try {
            $team_id = $this->teamModel->create($team_name, $owner_user_id);
            if (!$team_id) {
                throw new Exception("Failed to create team in database.", 500);
            }

            if (!$this->teamModel->addMember($team_id, $owner_user_id, 'Owner')) {
                throw new Exception("Failed to add owner to team.", 500);
            }

            $this->db->commit();

            // --- NEW: Log this activity (best-effort) ---
            try {
                ActivityLogger::log($this->db, $owner_user_id, 'created_team', null, $team_id);
            } catch (\Throwable $e) {
                error_log("ActivityLogger::log failed on createTeam: " . $e->getMessage());
            }

            return $this->teamModel->findById($team_id);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Add a new member to a team by their email address
     */
    public function addMemberByEmail($team_id, $email, $role, $current_user_id) {
        // 1. Permission check
        if (!$this->teamModel->isUserAdminOrOwner($team_id, $current_user_id)) {
            throw new Exception("You do not have permission to add members to this team.", 403);
        }

        // 2. Find user by email
        $user_to_add = $this->userModel->findByEmail($email);
        if (!$user_to_add) {
            throw new Exception("User with email '$email' not found.", 404);
        }
        $user_to_add_id = $user_to_add['id'];

        // 3. Already a member?
        if ($this->teamModel->isUserMember($team_id, $user_to_add_id)) {
            throw new Exception("This user is already a member of the team.", 400);
        }

        // 4. Add the user
        if (!$this->teamModel->addMember($team_id, $user_to_add_id, $role)) {
            throw new Exception("Failed to add member to team.", 500);
        }

        // --- NEW: Log this activity (best-effort) ---
        try {
            $details = json_encode([
                'added_user_id' => $user_to_add_id,
                'added_email' => $email,
                'role' => $role
            ]);
            ActivityLogger::log($this->db, $current_user_id, 'added_member', null, $team_id, $details);
        } catch (\Throwable $e) {
            error_log("ActivityLogger::log failed on addMemberByEmail: " . $e->getMessage());
        }

        return $this->teamModel->findMembersByTeamId($team_id);
    }

    /**
     * Remove a member from a team
     */
    public function removeMember($team_id, $user_to_remove_id, $current_user_id) {
        // 1. Permission check
        if (!$this->teamModel->isUserAdminOrOwner($team_id, $current_user_id)) {
            throw new Exception("You do not have permission to remove members from this team.", 403);
        }

        // 2. Ensure we have team info and cannot remove owner
        $team = $this->teamModel->findById($team_id);
        if (!$team) {
            throw new Exception("Team not found.", 404);
        }

        if ((int)$team['owner_user_id'] === (int)$user_to_remove_id) {
            throw new Exception("Cannot remove the team owner.", 400);
        }

        // 3. Remove the member
        if (!$this->teamModel->removeMember($team_id, $user_to_remove_id)) {
            throw new Exception("Failed to remove member.", 500);
        }

        // --- NEW: Log this activity (best-effort) ---
        try {
            $details = json_encode([
                'removed_user_id' => $user_to_remove_id
            ]);
            ActivityLogger::log($this->db, $current_user_id, 'removed_member', null, $team_id, $details);
        } catch (\Throwable $e) {
            error_log("ActivityLogger::log failed on removeMember: " . $e->getMessage());
        }

        return $this->teamModel->findMembersByTeamId($team_id);
    }

    /**
     * Get all teams for the current user
     */
    public function getTeamsForUser($user_id) {
        return $this->teamModel->findTeamsByUserId($user_id);
    }

    /**
     * Get details (including members) for a single team
     */
    public function getTeamDetails($team_id, $user_id) {
        // Permission: must be a member to view
        if (!$this->teamModel->isUserMember($team_id, $user_id)) {
            throw new Exception("You are not a member of this team.", 403);
        }

        $team = $this->teamModel->findById($team_id);
        if (!$team) {
            throw new Exception("Team not found.", 404);
        }
        $team['members'] = $this->teamModel->findMembersByTeamId($team_id);

        return $team;
    }
}
?>

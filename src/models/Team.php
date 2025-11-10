<?php
// Filename: /mnt/d/__PROJECTS__/LARAVEL/advanced-todo-app/src/models/Team.php

class Team {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create a new team
     */
    public function create($team_name, $owner_user_id) {
        $sql = "INSERT INTO teams (team_name, owner_user_id) VALUES (:team_name, :owner_user_id)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':team_name', $team_name);
        $stmt->bindParam(':owner_user_id', $owner_user_id);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }
    
    /**
     * Get a team by its ID
     */
    public function findById($team_id) {
        $sql = "SELECT * FROM teams WHERE id = :team_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':team_id', $team_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Add a user to a team
     */
    public function addMember($team_id, $user_id, $role = 'Member') {
        $sql = "INSERT INTO team_members (team_id, user_id, role) VALUES (:team_id, :user_id, :role)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':team_id', $team_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':role', $role);
        return $stmt->execute();
    }

    /**
     * Remove a user from a team
     */
    public function removeMember($team_id, $user_id) {
        $sql = "DELETE FROM team_members WHERE team_id = :team_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':team_id', $team_id);
        $stmt->bindParam(':user_id', $user_id);
        return $stmt->execute();
    }

    /**
     * Get all teams a user belongs to
     */
    public function findTeamsByUserId($user_id) {
        $sql = "SELECT t.*, tm.role 
                FROM teams t
                JOIN team_members tm ON t.id = tm.team_id
                WHERE tm.user_id = :user_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all members of a specific team
     */
    public function findMembersByTeamId($team_id) {
        $sql = "SELECT u.id, u.username, u.email, tm.role 
                FROM users u
                JOIN team_members tm ON u.id = tm.user_id
                WHERE tm.team_id = :team_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':team_id', $team_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if a user is a member of a team
     */
    public function isUserMember($team_id, $user_id) {
        $sql = "SELECT 1 FROM team_members WHERE team_id = :team_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':team_id', $team_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if a user is the owner or an admin of a team
     */
    public function isUserAdminOrOwner($team_id, $user_id) {
        $sql = "SELECT 1 FROM team_members 
                WHERE team_id = :team_id 
                AND user_id = :user_id 
                AND (role = 'Owner' OR role = 'Admin')";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':team_id', $team_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
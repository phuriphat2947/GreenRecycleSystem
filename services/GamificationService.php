<?php
require_once __DIR__ . '/../db_connect/db_connect.php';

class GamificationService
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Add recycled weight to user's total and update level if needed.
     */
    public function addExperience($userId, $weight)
    {
        // 1. Get current stats
        $stmt = $this->conn->prepare("SELECT total_recycled_weight, membership_level FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return;

        $newTotal = $user['total_recycled_weight'] + $weight;
        $newLevel = $this->calculateLevel($newTotal);

        // 2. Update DB
        $sql = "UPDATE users SET total_recycled_weight = :w, membership_level = :lvl WHERE id = :id";
        $this->conn->prepare($sql)->execute([
            ':w' => $newTotal,
            ':lvl' => $newLevel,
            ':id' => $userId
        ]);

        return $newLevel; // Return new level to check for upgrades
    }

    /**
     * Determine Level based on Total Weight
     * 0-99kg: Seedling
     * 100-499kg: Guardian
     * 500kg+: Titan
     */
    public function calculateLevel($weight)
    {
        if ($weight >= 500) return 'titan';
        if ($weight >= 100) return 'guardian';
        return 'seedling';
    }

    /**
     * Get Bonus Multiplier based on Level
     * Seedling: 0%
     * Guardian: +5% (0.05)
     * Titan: +10% (0.10)
     */
    public function getBonusPercentage($level)
    {
        switch (strtolower($level)) {
            case 'titan':
                return 0.10;
            case 'guardian':
                return 0.05;
            default:
                return 0.00;
        }
    }

    /**
     * Get Badge Icon/Color (Helper for UI)
     */
    public function getBadgeDetails($level)
    {
        switch (strtolower($level)) {
            case 'titan':
                return ['icon' => 'fas fa-robot', 'color' => '#e74c3c', 'name' => 'Titan (สุดยอดนักกู้โลก)'];
            case 'guardian':
                return ['icon' => 'fas fa-shield-alt', 'color' => '#f1c40f', 'name' => 'Guardian (ผู้พิทักษ์)'];
            default:
                return ['icon' => 'fas fa-seedling', 'color' => '#27ae60', 'name' => 'Seedling (ต้นกล้า)'];
        }
    }

    /**
     * Calculate Progress to Next Level
     */
    public function calculateProgress($weight)
    {
        if ($weight < 100) {
            return [
                'next_level' => 'Guardian',
                'target' => 100,
                'percent' => ($weight / 100) * 100
            ];
        } elseif ($weight < 500) {
            return [
                'next_level' => 'Titan',
                'target' => 500,
                'percent' => (($weight - 100) / (500 - 100)) * 100
            ];
        } else {
            return [
                'next_level' => 'Max Level',
                'target' => null,
                'percent' => 100
            ];
        }
    }
}

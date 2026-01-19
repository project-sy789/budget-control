<?php
require_once __DIR__ . '/../../config/database.php';

class FiscalYearService {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    public function getAll() {
        try {
            $query = "SELECT * FROM fiscal_years ORDER BY name DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting fiscal years: " . $e->getMessage());
            return [];
        }
    }

    public function getById($id) {
        try {
            $query = "SELECT * FROM fiscal_years WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting fiscal year: " . $e->getMessage());
            return null;
        }
    }

    public function create($name, $startDate, $endDate) {
        try {
            $query = "INSERT INTO fiscal_years (name, start_date, end_date) VALUES (:name, :start_date, :end_date)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            
            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'เพิ่มข้อมูลปีสำเร็จ',
                    'id' => $this->conn->lastInsertId()
                ];
            }
            return ['success' => false, 'message' => 'ไม่สามารถเพิ่มข้อมูลปีได้'];
        } catch (Exception $e) {
            error_log("Error creating fiscal year: " . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    }

    public function update($id, $name, $startDate, $endDate) {
        try {
            $query = "UPDATE fiscal_years SET name = :name, start_date = :start_date, end_date = :end_date WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'แก้ไขข้อมูลปีสำเร็จ'];
            }
            return ['success' => false, 'message' => 'ไม่สามารถแก้ไขข้อมูลปีได้'];
        } catch (Exception $e) {
            error_log("Error updating fiscal year: " . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    }

    public function delete($id) {
        try {
            // Check usage
            $checkQuery = "SELECT COUNT(*) FROM projects WHERE fiscal_year_id = :id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'ไม่สามารถลบได้เนื่องจากมีโครงการที่ผูกกับปีนี้'];
            }

            $query = "DELETE FROM fiscal_years WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'ลบข้อมูลปีสำเร็จ'];
            }
            return ['success' => false, 'message' => 'ไม่สามารถลบข้อมูลปีได้'];
        } catch (Exception $e) {
            error_log("Error deleting fiscal year: " . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    }

    public function setActiveYear($id) {
        try {
            $this->conn->beginTransaction();

            // Set all to inactive
            $this->conn->exec("UPDATE fiscal_years SET is_active = 0");

            // Set specific to active
            $query = "UPDATE fiscal_years SET is_active = 1 WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();

            $this->conn->commit();
            return ['success' => true, 'message' => 'ตั้งค่าปีงบประมาณปัจจุบันสำเร็จ'];
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error setting active fiscal year: " . $e->getMessage());
            return ['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    }

    public function getActiveYear() {
        try {
            $query = "SELECT * FROM fiscal_years WHERE is_active = 1 LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting active fiscal year: " . $e->getMessage());
            return null;
        }
    }
}

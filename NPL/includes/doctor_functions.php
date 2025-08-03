<?php
require_once __DIR__ . '/../config/error_handler.php';

/**
 * Get all doctors with optional search parameters
 */
function getAllDoctors($search = '', $limit = 0, $offset = 0) {
    global $pdo;
    
    try {
        $query = "SELECT * FROM doctors";
        $params = [];
        
        if (!empty($search)) {
            $query .= " WHERE name LIKE ? OR qualifications LIKE ? OR workplace LIKE ?";
            $searchTerm = "%$search%";
            $params = [$searchTerm, $searchTerm, $searchTerm];
        }
        
        $query .= " ORDER BY name";
        
        if ($limit > 0) {
            $query .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDatabaseError($e->getMessage(), $query);
        throw new Exception("Error fetching doctors");
    }
}

/**
 * Get doctor by ID
 */
function getDoctorById($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM doctors WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDatabaseError($e->getMessage());
        throw new Exception("Error fetching doctor");
    }
}

/**
 * Add new doctor
 */
function addDoctor($name, $qualifications, $designation, $workplace) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO doctors (name, qualifications, designation, workplace)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            'Dr. ' . trim($name),
            trim($qualifications),
            trim($designation),
            trim($workplace)
        ]);
        
        logAction('ADD_DOCTOR', "Added doctor: $name");
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        logDatabaseError($e->getMessage());
        throw new Exception("Error adding doctor");
    }
}

/**
 * Update doctor
 */
function updateDoctor($id, $name, $qualifications, $designation, $workplace) {
    global $pdo;
    
    try {
        // Check if doctor has reports
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM test_reports WHERE doctor_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            throw new Exception("Cannot update doctor with existing reports");
        }
        
        $stmt = $pdo->prepare("
            UPDATE doctors 
            SET name = ?, 
                qualifications = ?, 
                designation = ?, 
                workplace = ?
            WHERE id = ?
        ");
        $stmt->execute([
            'Dr. ' . trim($name),
            trim($qualifications),
            trim($designation),
            trim($workplace),
            $id
        ]);
        
        logAction('UPDATE_DOCTOR', "Updated doctor ID: $id");
        return true;
    } catch (PDOException $e) {
        logDatabaseError($e->getMessage());
        throw new Exception("Error updating doctor");
    }
}

/**
 * Delete doctor
 */
function deleteDoctor($id) {
    global $pdo;
    
    try {
        // Check if doctor has reports
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM test_reports WHERE doctor_id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['count'] > 0) {
            throw new Exception("Cannot delete doctor with existing reports");
        }
        
        $stmt = $pdo->prepare("DELETE FROM doctors WHERE id = ?");
        $stmt->execute([$id]);
        
        logAction('DELETE_DOCTOR', "Deleted doctor ID: $id");
        return true;
    } catch (PDOException $e) {
        logDatabaseError($e->getMessage());
        throw new Exception("Error deleting doctor");
    }
}

/**
 * Get doctor's reports
 */
function getDoctorReports($doctorId, $limit = 0, $offset = 0) {
    global $pdo;
    
    try {
        $query = "
            SELECT r.*, i.patient_name, t.test_name
            FROM test_reports r
            JOIN invoices i ON r.invoice_id = i.id
            JOIN tests_info t ON r.test_code = t.test_code
            WHERE r.doctor_id = ?
            ORDER BY r.report_date DESC
        ";
        
        if ($limit > 0) {
            $query .= " LIMIT ? OFFSET ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$doctorId, $limit, $offset]);
        } else {
            $stmt = $pdo->prepare($query);
            $stmt->execute([$doctorId]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDatabaseError($e->getMessage());
        throw new Exception("Error fetching doctor's reports");
    }
}

/**
 * Get doctor statistics
 */
function getDoctorStatistics($doctorId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_reports,
                SUM(CASE WHEN report_status = 'completed' THEN 1 ELSE 0 END) as completed_reports,
                SUM(CASE WHEN report_status = 'pending' THEN 1 ELSE 0 END) as pending_reports,
                MIN(report_date) as first_report_date,
                MAX(report_date) as last_report_date
            FROM test_reports
            WHERE doctor_id = ?
        ");
        $stmt->execute([$doctorId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        logDatabaseError($e->getMessage());
        throw new Exception("Error fetching doctor statistics");
    }
} 
<?php
/**
 * Audit Log API Endpoint
 * GET /api/business/audit_log.php
 * 
 * Parameters:
 * - page: page number (default: 1)
 * - per_page: items per page (default: 20)
 * - action: filter by action (optional)
 */

require_once '../config.php';

// Check authentication
if (!isAuthenticated()) {
    sendError('Unauthorized access', 401);
}

try {
    $db = Database::getInstance();
    
    // Get parameters
    $page = max(1, intval($_GET['page'] ?? 1));
    $perPage = max(1, min(100, intval($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;
    $action = $_GET['action'] ?? '';
    
    // Build query
    $whereConditions = [];
    $params = [];
    
    if (!empty($action)) {
        $whereConditions[] = "action = ?";
        $params[] = $action;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM audit_logs $whereClause";
    $countResult = $db->prepare($countQuery, $params);
    $totalRecords = $countResult->fetch()['total'];
    
    // Get audit logs with pagination
    $query = "SELECT 
                id,
                user_id,
                action,
                meta,
                created_at
              FROM audit_logs 
              $whereClause 
              ORDER BY created_at DESC 
              LIMIT $perPage OFFSET $offset";
    
    $result = $db->prepare($query, $params);
    $logs = $result->fetchAll();
    
    // Parse JSON meta values
    foreach ($logs as &$log) {
        $log['meta'] = $log['meta'] ? json_decode($log['meta'], true) : null;
    }
    
    // Calculate pagination info
    $totalPages = ceil($totalRecords / $perPage);
    
    // Get available actions for filters
    $actionsQuery = "SELECT DISTINCT action FROM audit_logs ORDER BY action";
    $actionsResult = $db->prepare($actionsQuery);
    $actions = array_column($actionsResult->fetchAll(), 'action');
    
    $response = [
        'logs' => $logs,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_records' => $totalRecords,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ],
        'filters' => [
            'actions' => $actions
        ]
    ];
    
    sendSuccess($response);
    
} catch (Exception $e) {
    sendError('Database error: ' . $e->getMessage(), 500);
}
?>

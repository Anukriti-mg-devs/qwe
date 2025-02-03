<?php
require_once '../config.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== ROLE_AGENT && $_SESSION['role'] !== ROLE_TL) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

try {
    // Get filters from query parameters
    $date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $_GET['date_to'] ?? date('Y-m-d');
    $category = $_GET['category'] ?? null;
    $status = $_GET['status'] ?? null;

    // Build query
    $query = "
        SELECT 
            d.*,
            u.full_name as agent_name
        FROM data_entries d
        JOIN users u ON d.user_id = u.id
        WHERE 1=1
    ";
    $params = [];
    $types = "";

    // Add user filter for agents (TLs can see all entries)
    if ($_SESSION['role'] === ROLE_AGENT) {
        $query .= " AND d.user_id = ?";
        $params[] = $_SESSION['user_id'];
        $types .= "i";
    }

    // Add date range filter
    $query .= " AND DATE(d.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $types .= "ss";

    // Add category filter
    if ($category) {
        $query .= " AND d.category = ?";
        $params[] = $category;
        $types .= "s";
    }

    // Add status filter
    if ($status) {
        $query .= " AND d.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    // Add sorting
    $query .= " ORDER BY d.created_at DESC";

    // Add limit
    $query .= " LIMIT 100";

    // Execute query
    $stmt = $pdo->prepare($query);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }

    // Format the results
    $entries = [];
    while ($row = $stmt->fetch()) {
        // Format dates to mm/dd/yyyy
        $created_at = new DateTime($row['created_at']);
        $row['created_at'] = $created_at->format('m/d/Y H:i:s');
        
        // Add to entries array
        $entries[] = [
            'id' => $row['id'],
            'category' => $row['category'],
            'customer_name' => $row['customer_name'],
            'phone' => $row['phone'],
            'email' => $row['email'],
            'status' => $row['status'],
            'notes' => $row['notes'],
            'created_at' => $row['created_at'],
            'agent_name' => $row['agent_name']
        ];
    }

    // Get summary statistics
    $stats = [
        'total_entries' => count($entries),
        'by_category' => [],
        'by_status' => []
    ];

    foreach ($entries as $entry) {
        // Count by category
        if (!isset($stats['by_category'][$entry['category']])) {
            $stats['by_category'][$entry['category']] = 0;
        }
        $stats['by_category'][$entry['category']]++;

        // Count by status
        if (!isset($stats['by_status'][$entry['status']])) {
            $stats['by_status'][$entry['status']] = 0;
        }
        $stats['by_status'][$entry['status']]++;
    }

    echo json_encode([
        'success' => true,
        'entries' => $entries,
        'stats' => $stats,
        'filters' => [
            'date_from' => $date_from,
            'date_to' => $date_to,
            'category' => $category,
            'status' => $status
        ]
    ]);

} catch (Exception $e) {
    error_log("Error fetching entries: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error retrieving entries'
    ]);
}
?>
<?php
// admin/export_users.php - Export users to CSV
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(["Admin", "Super Admin"]);

// Get the same filters from the main users page
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'DESC';

// Build where clause (same logic as users.php)
$whereConditions = ['u.deleted_at IS NULL']; // Only show non-deleted users
$params = [];

if ($roleFilter) {
    $whereConditions[] = "r.role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter) {
    if ($statusFilter === 'active') {
        $whereConditions[] = "u.is_active = 1";
    } elseif ($statusFilter === 'inactive') {
        $whereConditions[] = "u.is_active = 0";
    }
}

if ($searchQuery) {
    $whereConditions[] = "(u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $searchParam = "%{$searchQuery}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$whereClause = implode(' AND ', $whereConditions);

// Get all users (no pagination for export)
$users = fetchAll("
    SELECT u.id, u.username, u.first_name, u.last_name, u.email, 
           r.role as role_name,
           CASE WHEN u.is_active = 1 THEN 'Active' ELSE 'Inactive' END as status,
           DATE(u.created_at) as join_date,
           DATE(u.last_login) as last_login_date,
           u.created_at,
           u.updated_at
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE {$whereClause}
    ORDER BY {$sortBy} {$sortOrder}
", $params);

// Generate filename with timestamp and filters
$filename = 'users_export_' . date('Y-m-d_H-i-s');
if ($roleFilter) {
    $filename .= '_' . strtolower(str_replace(' ', '_', $roleFilter));
}
if ($statusFilter) {
    $filename .= '_' . $statusFilter;
}
$filename .= '.csv';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fputs($output, "\xEF\xBB\xBF");

// CSV Headers
$headers = [
    'User ID',
    'Username',
    'First Name',
    'Last Name',
    'Email Address',
    'Role',
    'Status',
    'Join Date',
    'Last Login',
    'Created At',
    'Updated At'
];
fputcsv($output, $headers);

// Add user data
foreach ($users as $user) {
    $row = [
        $user['id'],
        $user['username'],
        $user['first_name'],
        $user['last_name'],
        $user['email'],
        $user['role_name'],
        $user['status'],
        $user['join_date'] ?: 'N/A',
        $user['last_login_date'] ?: 'Never',
        $user['created_at'],
        $user['updated_at'] ?: 'N/A'
    ];
    fputcsv($output, $row);
}

// Add summary at the end
fputcsv($output, []); // Empty row
fputcsv($output, ['Summary']);
fputcsv($output, ['Total Users Exported:', count($users)]);
fputcsv($output, ['Export Date:', date('Y-m-d H:i:s')]);
fputcsv($output, ['Exported By:', $_SESSION['username'] ?? 'Unknown']);

// Applied filters summary
if ($roleFilter || $statusFilter || $searchQuery) {
    fputcsv($output, []); // Empty row
    fputcsv($output, ['Applied Filters:']);
    
    if ($roleFilter) {
        fputcsv($output, ['Role Filter:', $roleFilter]);
    }
    if ($statusFilter) {
        fputcsv($output, ['Status Filter:', ucfirst($statusFilter)]);
    }
    if ($searchQuery) {
        fputcsv($output, ['Search Query:', $searchQuery]);
    }
}

// Log the export activity
ActivityLogger::log($_SESSION['user_id'], 'EXPORT_USERS', 'users', null, null, [
    'total_exported' => count($users),
    'filters' => [
        'role' => $roleFilter,
        'status' => $statusFilter,
        'search' => $searchQuery
    ],
    'filename' => $filename
]);

// Close output stream
fclose($output);
exit;
?>
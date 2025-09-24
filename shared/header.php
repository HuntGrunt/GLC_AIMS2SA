<?php 
require_once __DIR__ . '/../data/auth.php';

//for user prof email in header
$email = ''; // default value
if (Auth::isLoggedIn() && !empty($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $result = fetchOne("SELECT email FROM users WHERE id = ?", [$userId]);
    $email = $result['email'] ?? ''; // ensure $email is always a string
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="/GLC_AIMS/shared/GLC_LOGO.png" type="image/x-icon">

    <style>
        :root {
            --primary-blue: #1e3a8a;
            --light-blue: #3b82f6;
            --accent-yellow: #fbbf24;
            --light-yellow: #fef3c7;
            --dark-blue: #1e40af;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --white: #ffffff;
            --light-gray: #f8fafc;
            --border-gray: #e5e7eb;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --info: #06b6d4;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* Header Styles */
        .header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: var(--white);
            padding: 1rem 0;
            box-shadow: 0 4px 20px rgba(30, 58, 138, 0.3);
            position: relative;
            /* overflow: hidden; */
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.1;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .logo img {
            height: 50px;
            width: auto;
            border-radius: 8px;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-title {
            font-size: 1.2rem;
            line-height: 1;
        }

        .logo-subtitle {
            font-size: 0.8rem;
            opacity: 0.9;
            line-height: 1;
            margin-top: 0.2rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-user-avatar {
            background: var(--accent-yellow);
            color: var(--primary-blue);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
            flex-shrink: 0;  
        }

        .user-details {
            max-width: 200px;            
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;         
            margin-bottom: 0.2rem;
        }

        .role-badge {
            display: inline-block;          
            width: 120px;                 
            text-align: left;            
            white-space: nowrap;           
            overflow: hidden;             
            text-overflow: ellipsis;      
            background: rgba(251, 191, 36, 0.2);
            color: var(--accent-yellow);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }

        /* Navigation Styles */
        .navigation {
            background: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 0.8rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .nav-btn {
            text-decoration: none;
            padding: 0.7rem 1.2rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-dark);
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            position: relative;
        }

        .nav-btn:hover {
            background: var(--light-yellow);
            color: var(--primary-blue);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(251, 191, 36, 0.2);
            text-decoration: none;
        }

        .nav-btn.active {
            background: var(--accent-yellow);
            color: var(--primary-blue);
            font-weight: 600;
        }

        .nav-btn.logout {
            margin-left: auto;
            background: var(--error);
            color: var(--white);
        }

        .nav-btn.logout:hover {
            background: #dc2626;
            color: var(--white);
        }

        /* Notification Badge */
        .nav-badge {
            position: absolute;
            top: 0.2rem;
            right: 0.2rem;
            background: var(--error);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        /* Mobile Navigation Toggle */
        .nav-toggle {
            display: none;
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.2rem;
        }

        .nav-menu {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
            flex: 1;
        }

        /* Main Content */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .content-card {
            background: var(--white);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border-gray);
            position: relative;
            overflow: hidden;
        }

        .content-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-blue) 0%, var(--accent-yellow) 100%);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .form-input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid var(--border-gray);
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-yellow);
            box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.1);
            transform: translateY(-1px);
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 3rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            text-align: center;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--light-blue) 100%);
            color: var(--white);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
            text-decoration: none;
        }

        .btn-secondary {
            background: var(--accent-yellow);
            color: var(--primary-blue);
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.3);
        }

        .btn-secondary:hover {
            background: #f59e0b;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(251, 191, 36, 0.4);
            text-decoration: none;
        }

        .btn-success {
            background: var(--success);
            color: var(--white);
        }

        .btn-warning {
            background: var(--warning);
            color: var(--white);
        }

        .btn-danger {
            background: var(--error);
            color: var(--white);
        }

        .btn-info {
            background: var(--info);
            color: var(--white);
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            margin: 1.5rem 0;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
        }

        .table th {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--light-blue) 100%);
            color: var(--white);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            border: none;
        }

        .table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-gray);
        }

        .table tr:hover {
            background: var(--light-gray);
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid var(--error);
        }

        .alert-warning {
            background: #fffbeb;
            color: #92400e;
            border-left: 4px solid var(--warning);
        }

        .alert-info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid var(--light-blue);
        }

        /* Grid System */
        .grid {
            display: grid;
            gap: 1.5rem;
        }

        .grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }

        .grid-3 {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }

        /* Stats Cards */
        .stats-card {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--accent-yellow);
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-blue);
        }

        .stats-label {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--accent-yellow);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-gray);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--accent-yellow);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--warning);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .header-content {
                flex-wrap: wrap;
                gap: 1rem;
            }
            
            .nav-content {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .nav-btn {
                font-size: 0.85rem;
                padding: 0.6rem 1rem;
            }
        }

        @media (max-width: 768px) {
            .nav-toggle {
                display: block;
            }
            
            .nav-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                flex-direction: column;
                padding: 1rem;
                gap: 0.5rem;
            }
            
            .nav-menu.active {
                display: flex;
            }
            
            .nav-btn {
                width: 100%;
                justify-content: flex-start;
                padding: 1rem;
                border-radius: 8px;
            }
            
            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .user-info {
                order: 2;
            }

            .user-details {
                text-align: center;
            }

            .container {
                padding: 0 1rem;
            }

            .content-card {
                padding: 1.5rem;
            }

            .grid-2, .grid-3 {
                grid-template-columns: 1fr;
            }
        }

        /* Profile Dropdown */
.profile-dropdown {
    position: absolute;
    top: 70px; /* adjust depending on header height */
    right: 2rem;
    background: var(--white);
    border: 1px solid var(--border-gray);
    border-radius: 10px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    width: 250px;
    display: none;
    flex-direction: column;
    z-index: 3000;
    overflow: hidden;
}

.profile-dropdown.active {
    display: flex;
    animation: fadeIn 0.2s ease-in-out;
}

.profile-dropdown-header {
    padding: 1rem;
    border-bottom: 1px solid var(--border-gray);
    background: var(--light-gray);
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--text-dark);
}

.profile-dropdown-item {
    padding: 0.9rem 1rem;
    cursor: pointer;
    font-size: 0.9rem;
    color: var(--text-dark);
    transition: background 0.2s;
    text-decoration: none;
}

.profile-dropdown-item:hover {
    background: var(--light-yellow);
    color: var(--primary-blue);
}

/* Small fade animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <img src="/GLC_AIMS/shared/GLC_LOGO.png" alt="GLC Logo">
                <div class="logo-text">
                    <div class="logo-title">GLC AIMS</div>
                    <div class="logo-subtitle">Academic Information Management System</div>
                </div>
            </div>
            <?php if (Auth::isLoggedIn()): ?>
                <!-- <div class="user-info">
                    <div class="header-user-avatar">
                        <?= substr($_SESSION['full_name'] ?? $_SESSION['username'], 0, 1) ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></div>
                        <div class="role-badge"><?= htmlspecialchars($_SESSION['role_name']) ?></div>
                    </div>
                </div> -->

                <div id="profileToggle" class="user-info" style="cursor: pointer;">
    <div class="header-user-avatar">
        <?= substr($_SESSION['full_name'] ?? $_SESSION['username'], 0, 1) ?>
    </div>
    <div class="user-details">
        <div class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?></div>
        <div class="role-badge"><?= htmlspecialchars($_SESSION['role_name']) ?></div>
    </div>
</div>
   
            <?php endif; ?>
        </div>
    </header>

<!-- Profile Dropdown -->
<div id="profileDropdown" class="profile-dropdown">
    <div class="profile-dropdown-header">
        <div style="font-weight: 600;">
            <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?>
        </div>
<div style="font-size: 0.85rem; color: gray; margin-top: 2px;">
    <?= htmlspecialchars($email) ?>

</div>
</div>

    <!-- Toggle button -->
    <div id="changePasswordToggle" class="profile-dropdown-item" style="cursor:pointer;">
        <i class="fas fa-key"></i> Change Password
    </div>

    <!-- Hidden form -->
    <form id="changePasswordForm" class="profile-dropdown-item" style="display:none; flex-direction: column; gap: 0.5rem;">
        <input type="password" name="current_password" placeholder="Current Password" class="form-input" required>
        <input type="password" name="new_password" placeholder="New Password" class="form-input" required>
        <input type="password" name="confirm_password" placeholder="Confirm Password" class="form-input" required>
        <button type="submit" class="btn btn-primary">Update</button>
        <div id="passwordMessage" style="font-size:0.8rem; color:red;"></div>
    </form>
    <!-- Logout -->
    <a href="/GLC_AIMS/logout.php" class="profile-dropdown-item" style="color: var(--error);">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>



    <!-- Navigation -->
    <nav class="navigation">
        <div class="nav-content">
            <button class="nav-toggle" onclick="toggleMobileNav()">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="nav-menu" id="navMenu">
                <?php
                $currentPage = basename($_SERVER['PHP_SELF']);
                $role = $_SESSION['role_name'] ?? '';
                
                if ($role === "Super Admin") {
                    echo '<a class="nav-btn ' . ($currentPage == 'dashboard.php' ? 'active' : '') . '" href="/GLC_AIMS/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'users.php' ? 'active' : '') . '" href="/GLC_AIMS/admin/users.php"><i class="fas fa-users"></i> Manage Users</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'reports.php' ? 'active' : '') . '" href="/GLC_AIMS/admin/reports.php"><i class="fas fa-chart-bar"></i> Reports</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'activity_logs.php' ? 'active' : '') . '" href="/GLC_AIMS/admin/activity_logs.php"><i class="fas fa-history"></i> Activity Logs</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'super_dashboard.php' ? 'active' : '') . '" href="/GLC_AIMS/admin/super_dashboard.php"><i class="fas fa-crown"></i> Super Dashboard</a>';
                    
                } elseif ($role === "Registrar") {
                    echo '<a class="nav-btn ' . ($currentPage == 'dashboard.php' ? 'active' : '') . '" href="/GLC_AIMS/registrar/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'manage_students.php' ? 'active' : '') . '" href="/GLC_AIMS/registrar/manage_students.php"><i class="fas fa-users"></i> Students</a>';
                    
                    // Check for pending grade approvals
                    $pendingGrades = 0;
                    if (Auth::isLoggedIn()) {
                        $pendingResult = fetchOne("SELECT COUNT(*) as count FROM grade_submissions WHERE status = 'pending'");
                        $pendingGrades = $pendingResult['count'] ?? 0;
                    }
                    
                    echo '<a class="nav-btn ' . ($currentPage == 'pending_grade_approvals.php' ? 'active' : '') . '" href="/GLC_AIMS/registrar/pending_grade_approvals.php" style="position: relative;">';
                    echo '<i class="fas fa-clipboard-check"></i> Grade Approvals';
                    if ($pendingGrades > 0) {
                        echo '<span class="nav-badge">' . min($pendingGrades, 99) . '</span>';
                    }
                    echo '</a>';
                    
                    //echo '<a class="nav-btn ' . ($currentPage == 'upload_grades.php' ? 'active' : '') . '" href="/GLC_AIMS/registrar/upload_grades.php"><i class="fas fa-graduation-cap"></i> Grades</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'upload_files.php' ? 'active' : '') . '" href="/GLC_AIMS/registrar/upload_files.php"><i class="fas fa-cloud-upload-alt"></i> Files</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'grade_reports.php' ? 'active' : '') . '" href="/GLC_AIMS/registrar/grade_reports.php"><i class="fas fa-chart-bar"></i> Reports</a>';
                    
                } elseif ($role === "Faculty") {
                    echo '<a class="nav-btn ' . ($currentPage == 'dashboard.php' ? 'active' : '') . '" href="/GLC_AIMS/faculty/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'submit_grades.php' ? 'active' : '') . '" href="/GLC_AIMS/faculty/submit_grades.php"><i class="fas fa-plus-circle"></i> Submit Grades</a>';
                    
                    // Check for rejected submissions
                    $rejectedGrades = 0;
                    $pendingFacultyGrades = 0;
                    if (Auth::isLoggedIn()) {
                        $facultyId = $_SESSION['user_id'];
                        $rejectedResult = fetchOne("SELECT COUNT(*) as count FROM grade_submissions WHERE faculty_id = ? AND status = 'rejected'", [$facultyId]);
                        $pendingResult = fetchOne("SELECT COUNT(*) as count FROM grade_submissions WHERE faculty_id = ? AND status = 'pending'", [$facultyId]);
                        $rejectedGrades = $rejectedResult['count'] ?? 0;
                        $pendingFacultyGrades = $pendingResult['count'] ?? 0;
                    }
                    
                    echo '<a class="nav-btn ' . ($currentPage == 'my_submissions.php' ? 'active' : '') . '" href="/GLC_AIMS/faculty/my_submissions.php" style="position: relative;">';
                    echo '<i class="fas fa-list-alt"></i> My Submissions';
                    if ($rejectedGrades > 0 || $pendingFacultyGrades > 0) {
                        $totalNotifications = $rejectedGrades + $pendingFacultyGrades;
                        echo '<span class="nav-badge">' . min($totalNotifications, 99) . '</span>';
                    }
                    echo '</a>';
                    
                    echo '<a class="nav-btn ' . ($currentPage == 'my_students.php' ? 'active' : '') . '" href="/GLC_AIMS/faculty/my_students.php"><i class="fas fa-users"></i> My Students</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'grade_reports.php' ? 'active' : '') . '" href="/GLC_AIMS/faculty/grade_reports.php"><i class="fas fa-chart-bar"></i> Grade Reports</a>';
                    
                } elseif ($role === "SAO") {
                    echo '<a class="nav-btn ' . ($currentPage == 'dashboard.php' ? 'active' : '') . '" href="/GLC_AIMS/sao/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'announcements.php' ? 'active' : '') . '" href="/GLC_AIMS/sao/announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'inventory.php' ? 'active' : '') . '" href="/GLC_AIMS/sao/inventory.php"><i class="fas fa-boxes"></i> Inventory</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'borrow_requests.php' ? 'active' : '') . '" href="/GLC_AIMS/sao/borrow_requests.php"><i class="fas fa-hand-holding"></i> Borrow Requests</a>';
                    
                } elseif ($role === "Student") {
                    echo '<a class="nav-btn ' . ($currentPage == 'dashboard.php' ? 'active' : '') . '" href="/GLC_AIMS/student/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'grades.php' ? 'active' : '') . '" href="/GLC_AIMS/student/grades.php"><i class="fas fa-chart-line"></i> My Grades</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'files.php' ? 'active' : '') . '" href="/GLC_AIMS/student/files.php"><i class="fas fa-folder"></i> My Files</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'borrow.php' ? 'active' : '') . '" href="/GLC_AIMS/student/borrow.php"><i class="fas fa-hand-holding"></i> Borrow Items</a>';
                    echo '<a class="nav-btn ' . ($currentPage == 'announcements.php' ? 'active' : '') . '" href="/GLC_AIMS/student/announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a>';
                } else {
                    echo '<a class="nav-btn" href="/GLC_AIMS/login.php"><i class="fas fa-sign-in-alt"></i> Login</a>';
                }
                
                // if (Auth::isLoggedIn()) {
                //     echo '<a class="nav-btn logout" href="/GLC_AIMS/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>';
                // }
                ?>
            </div>
        </div>
    </nav>

    <script>
        function toggleMobileNav() {
            const navMenu = document.getElementById('navMenu');
            navMenu.classList.toggle('active');
        }

        // Close mobile nav when clicking outside
        document.addEventListener('click', function(e) {
            const nav = document.querySelector('.navigation');
            const navMenu = document.getElementById('navMenu');
            const navToggle = document.querySelector('.nav-toggle');
            
            if (!nav.contains(e.target) && navMenu.classList.contains('active')) {
                navMenu.classList.remove('active');
            }
        });

        // Auto-refresh navigation badges every 30 seconds
        setInterval(function() {
            // Only refresh if user is logged in and is faculty or registrar
            const role = '<?= $role ?>';
            if (role === 'Faculty' || role === 'Registrar') {
                // This could be enhanced with AJAX to update badges without full page refresh
                // For now, we'll just log for debugging
                console.log('Navigation badges refreshed');
            }
        }, 30000);


    const profileToggle = document.getElementById('profileToggle');
    const profileDropdown = document.getElementById('profileDropdown');

    profileToggle.addEventListener('click', () => {
        profileDropdown.classList.toggle('active');
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
            profileDropdown.classList.remove('active');
        }
    });

    //for change pass
    const changePasswordToggle = document.getElementById('changePasswordToggle');
const changePasswordForm = document.getElementById('changePasswordForm');
const passwordMessage = document.getElementById('passwordMessage');

changePasswordToggle.addEventListener('click', () => {
    changePasswordForm.style.display = changePasswordForm.style.display === 'flex' ? 'none' : 'flex';
});

// Optional: AJAX form submission
changePasswordForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const formData = new FormData(changePasswordForm);

    fetch('/GLC_AIMS/change_password.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        passwordMessage.textContent = data.message;
        if (data.success) {
            changePasswordForm.reset();
            setTimeout(() => { changePasswordForm.style.display = 'none'; passwordMessage.textContent = ''; }, 2000);
        }
    })
    .catch(err => {
        passwordMessage.textContent = 'An error occurred.';
        console.error(err);
    });
});

    </script>

    <!-- Main Container -->
    <div class="container">
        <div class="content-card">
            <!-- Page content will be inserted here by individual pages -->

    </body>
</html>
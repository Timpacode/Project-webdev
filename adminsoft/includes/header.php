
<?php
session_start();
require_once '../config/database.php';
require_once '../config/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// This one line does the authentication check and redirect if needed
$auth->checkAuth();

// Get current user info
$currentUser = $auth->getCurrentUser();

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AdminSoft - Barangay Management System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
   <div class="sidebar">
    <div class="sidebar-content">
        <div class="logo">
            <h1>
                <i class="fas fa-home"></i>
                BarangayHub
            </h1>
        </div>
        
       <ul class="menu">
    <li>
        <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            Dashboard
        </a>
    </li>
    <li>
        <a href="residents.php" class="<?php echo $current_page == 'residents.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            Residents
        </a>
    </li>
    <li>
        <a href="requests.php" class="<?php echo $current_page == 'requests.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            Document Requests
        </a>
    </li>
    <li>
        <a href="documents.php" class="<?php echo $current_page == 'documents.php' ? 'active' : ''; ?>">
            <i class="fas fa-archive"></i>
            Documents
        </a>
    </li>
    <li>
        <a href="history.php" class="<?php echo $current_page == 'history.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            Request History
        </a>
    </li>
    <li>
        <a href="roles.php" class="<?php echo $current_page == 'roles.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i>
            Roles & Admin
        </a>
    </li>
    <li>
        <a href="logout.php" style="color: #ff6b6b;">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </li>
</ul>
    </div>
</div>
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h2 id="page-title">
                    <?php 
                    // Set page title based on current page
                    $titles = [
                        'dashboard.php' => 'Dashboard',
                        'residents.php' => 'Residents Management', 
                        'requests.php' => 'Document Requests',
                        'history.php' => 'Request history',
                        'documents.php' => 'Documents',
                        'roles.php' => 'Roles & Admin Management'
                    ];
                    echo $titles[$current_page] ?? 'Admin Panel';
                    ?>
                </h2>
                <div class="user-info">
                    <div class="user-details">
                        <div class="name"><?php echo $_SESSION['first_name'] . ' ' . $_SESSION['last_name']; ?></div>
                        <div class="role"><?php echo ucfirst($_SESSION['role']); ?></div>
                    </div>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['first_name'] . '+' . $_SESSION['last_name']); ?>&background=4361ee&color=fff" alt="Admin">
                </div>
            </div>

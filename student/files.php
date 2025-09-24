<?php
require_once __DIR__ . "/../data/auth.php";
Auth::requireRole(['Student', 'Super Admin']);
include __DIR__ . "/../shared/header.php";
require_once __DIR__ . "/../data/db.php";

// Ensure $con is initialized and is a valid mysqli connection
if (!isset($con) || !$con instanceof mysqli) {
    $con = new mysqli("localhost", "your_db_user", "your_db_password", "your_db_name");
    if ($con->connect_error) {
        die("Database connection failed: " . $con->connect_error);
    }
}

$uid = (int)$_SESSION["user_id"];
$res = mysqli_query($con, "SELECT file_name, file_path, uploaded_at FROM student_files WHERE user_id = $uid ORDER BY id DESC");
?>
<div class="page-header">
    <h1><i class="fas fa-file-alt"></i> My Files </h1>
    <p>Files Uploaded</p>
    <br>
</div>

<div class="quick-actions" style="margin-bottom: 2rem;">
    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    <div class="action-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem;">
        <a href="/GLC_AIMS/registrar/registrationForm.php" class="action-card">
            <div class="action-icon">
                <i class="fas fa-folder"></i>
            </div>
            <div class="action-content">
                <h4>Registration Forms</h4>
                <p>Your registration forms.</p>
            </div>
        </a>
        
        <a href="/GLC_AIMS/registrar/birthCertificate.php" class="action-card">
            <div class="action-icon">
                <i class="fas fa-folder"></i>
            </div>
            <div class="action-content">
                <h4>Birth Certificate</h4>
                <p>Your personal identification paper.</p>
            </div>
        </a>
        
        <a href="/GLC_AIMS/registrar/form137.php" class="action-card">
            <div class="action-icon">
                <i class="fas fa-folder"></i>
            </div>
            <div class="action-content">
                <h4>Form 137 and GM</h4>
                <p>Your senior high school's papers.</p>
            </div>
        </a>
        
        <a href="/GLC_AIMS/registrar/agreementForm.php" class="action-card">
            <div class="action-icon">
                <i class="fas fa-folder"></i>
            </div>
            <div class="action-content">
                <h4>Agreements Forms</h4>
                <p>Your scholarship and school's agreements.</p>
            </div>
        </a>
    </div>
</div>

  <?php while ($row = mysqli_fetch_assoc($res)): ?>
    <tr>
      <td><a href="<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank"><?php echo htmlspecialchars($row["file_name"]); ?></a></td>
      <td><?php echo htmlspecialchars($row["uploaded_at"]); ?></td>
    </tr>
  <?php endwhile; ?>
<?php include __DIR__ . "/../shared/footer.php"; ?>

<style>
  .quick-actions h3 {
    color: var(--primary-blue);
    margin-bottom: 1rem;
  }
    
  .action-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 1rem;
  }
    
    .action-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    text-decoration: none;
    color: inherit;
  }
    
    .action-icon {
    width: 50px;
    height: 50px;
    background: var(--accent-yellow);
    color: var(--primary-blue);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
  }
    
    .action-content h4 {
    color: var(--primary-blue);
    margin-bottom: 0.3rem;
  }
    
    .action-content p {
    color: var(--text-light);
    font-size: 0.9rem;
    margin: 0;
  }

  @media (max-width: 768px) {
    .grid-2, .grid-4 {
      grid-template-columns: 1fr;
    }
        
    .action-grid {
      grid-template-columns: 1fr;
    }
        
    .stats-card, .action-card {
      flex-direction: column;
      text-align: center;
    }
        
    .performance-summary {
      grid-template-columns: repeat(2, 1fr);
    }
  }
    
</style>
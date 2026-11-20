<?php
session_start();
require_once 'admin_config.php';
check_admin_login();

// Connect to kemos database
$conn = connect_kemos_db();

// Check action
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'backup') {
    // Create database backup
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    $backup = "";
    
    // Add drop table statements
    foreach ($tables as $table) {
        $backup .= "DROP TABLE IF EXISTS `$table`;\n\n";
    }
    
    // Add create table statements
    foreach ($tables as $table) {
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch_row();
        $backup .= $row[1] . ";\n\n";
        
        // Add insert statements
        $result = $conn->query("SELECT * FROM `$table`");
        $num_fields = $result->field_count;
        
        while ($row = $result->fetch_row()) {
            $backup .= "INSERT INTO `$table` VALUES(";
            
            for ($i = 0; $i < $num_fields; $i++) {
                if (isset($row[$i])) {
                    $row[$i] = addslashes($row[$i]);
                    $row[$i] = str_replace("\n", "\\n", $row[$i]);
                    $backup .= "'" . $row[$i] . "'";
                } else {
                    $backup .= "NULL";
                }
                
                if ($i < ($num_fields - 1)) {
                    $backup .= ",";
                }
            }
            
            $backup .= ");\n";
        }
        
        $backup .= "\n\n";
    }
    
    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="kemos_backup_' . date('Y-m-d_H-i-s') . '.sql"');
    header('Content-Length: ' . strlen($backup));
    header('Connection: close');
    
    // Output backup file
    echo $backup;
    exit;
} elseif ($action === 'restore') {
    // Restore database from backup
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
        $file = $_FILES['backup_file'];
        
        // Check for errors
        if ($file['error'] === UPLOAD_ERR_OK) {
            // Check file type
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if ($file_ext === 'sql') {
                // Read backup file
                $backup = file_get_contents($file['tmp_name']);
                
                // Split backup into individual queries
                $queries = explode(';', $backup);
                
                // Execute queries
                $success = true;
                $conn->query('SET FOREIGN_KEY_CHECKS = 0');
                
                foreach ($queries as $query) {
                    $query = trim($query);
                    
                    if (!empty($query)) {
                        if (!$conn->query($query)) {
                            $success = false;
                            break;
                        }
                    }
                }
                
                $conn->query('SET FOREIGN_KEY_CHECKS = 1');
                
                // Redirect with message
                if ($success) {
                    $_SESSION['success_message'] = 'Database restored successfully';
                } else {
                    $_SESSION['error_message'] = 'Error restoring database: ' . $conn->error;
                }
            } else {
                $_SESSION['error_message'] = 'Invalid file type. Only SQL files are allowed';
            }
        } else {
            $_SESSION['error_message'] = 'Error uploading file: ' . $file['error'];
        }
    } else {
        $_SESSION['error_message'] = 'No backup file provided';
    }
    
    // Redirect back to settings page
    header('Location: admin_settings.php#backup');
    exit;
}

// Close database connection
$conn->close();

// Redirect to settings page if no action specified
header('Location: admin_settings.php');
exit;
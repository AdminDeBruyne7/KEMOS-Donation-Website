<?php
session_start();
require_once 'config.php';

// Check if charity is logged in
if (!isset($_SESSION['charity_id']) || empty($_SESSION['charity_id'])) {
    header("Location: index.php");
    exit;
}

$charity_id = $_SESSION['charity_id'];
$conn = connect_db();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_media'])) {
    // Handle logo upload
    if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] == 0) {
        $logo_file = $_FILES['logo_upload'];
        
        // Validate file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($logo_file['type'], $allowed_types)) {
            $error_message = "Logo must be a JPEG, PNG, or GIF image";
        } elseif ($logo_file['size'] > $max_size) {
            $error_message = "Logo file size must be less than 2MB";
        } else {
            // Process and save the file
            $upload_dir = 'uploads/charity_logos/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $filename = $charity_id . '_' . time() . '_' . basename($logo_file['name']);
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($logo_file['tmp_name'], $target_path)) {
                // Update database with new logo path
                $logo_path = $target_path;
                $update_query = "UPDATE charities SET logo = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("si", $logo_path, $charity_id);
                
                if ($stmt->execute()) {
                    $success_message = "Logo uploaded successfully!";
                } else {
                    $error_message = "Error updating logo in database: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error_message = "Error uploading logo file";
            }
        }
    }
    
    // Handle cover photo upload
    if (isset($_FILES['cover_upload']) && $_FILES['cover_upload']['error'] == 0) {
        $cover_file = $_FILES['cover_upload'];
        
        // Validate file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($cover_file['type'], $allowed_types)) {
            $error_message .= ($error_message ? "<br>" : "") . "Cover photo must be a JPEG, PNG, or GIF image";
        } elseif ($cover_file['size'] > $max_size) {
            $error_message .= ($error_message ? "<br>" : "") . "Cover photo file size must be less than 5MB";
        } else {
            // Process and save the file
            $upload_dir = 'uploads/charity_covers/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $filename = $charity_id . '_' . time() . '_' . basename($cover_file['name']);
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($cover_file['tmp_name'], $target_path)) {
                // Update database with new cover path
                $cover_path = $target_path;
                $update_query = "UPDATE charities SET cover_photo = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("si", $cover_path, $charity_id);
                
                if ($stmt->execute()) {
                    $success_message .= ($success_message ? "<br>" : "") . "Cover photo uploaded successfully!";
                } else {
                    $error_message .= ($error_message ? "<br>" : "") . "Error updating cover photo in database: " . $conn->error;
                }
                $stmt->close();
            } else {
                $error_message .= ($error_message ? "<br>" : "") . "Error uploading cover photo file";
            }
        }
    }
    
    // Store messages in session and redirect back to settings page
    if (!empty($success_message)) {
        $_SESSION['success_message'] = $success_message;
    }
    
    if (!empty($error_message)) {
        $_SESSION['error_message'] = $error_message;
    }
    
    $conn->close();
    header("Location: charity_settings.php");
    exit;
}
?>


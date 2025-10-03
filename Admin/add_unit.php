<?php
session_start();
require_once '../database/database.php';

// Turn on error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$db = new Database();

// --- Admin auth check ---
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}
$ua_id = $_SESSION['admin_id'] ?? null;

// Photo upload configuration
$max_photos_per_unit = 5; // Set your desired limit here

$success_unit = '';
$error_unit = '';
$success_type = '';
$error_type = '';

// --- Handle photo delete for a specific index in JSON array ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'delete_photo') {
    $space_id = intval($_POST['space_id'] ?? 0);
    $photo_index = intval($_POST['photo_index'] ?? -1);
    if ($space_id >= 1 && $photo_index >= 0) {
        $space = $db->getSpacePhoto($space_id);
        $photos = [];
        if ($space && !empty($space['Photo'])) {
            $photos = json_decode($space['Photo'], true) ?: [];
        }
        if (isset($photos[$photo_index])) {
            $filepath = __DIR__ . "/../uploads/unit_photos/" . $photos[$photo_index];
            if (file_exists($filepath)) unlink($filepath);
            array_splice($photos, $photo_index, 1);
            
            // FIX: Use the new public method instead of direct pdo access
            if ($db->updateSpacePhotoJson($space_id, json_encode($photos))) {
                $success_unit = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                Photo deleted successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
            }
        }
    }
}

// --- Handle photo update/upload (append to JSON array) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'update_photo') {
    $space_id = intval($_POST['space_id'] ?? 0);
    $photo_index = isset($_POST['photo_index']) ? intval($_POST['photo_index']) : null;
    
    if ($space_id && isset($_FILES['new_photo']) && $_FILES['new_photo']['error'] == UPLOAD_ERR_OK) {
        // Get current photos to check limit
        $space = $db->getSpacePhoto($space_id);
        $current_photos = [];
        if ($space && !empty($space['Photo'])) {
            $current_photos = json_decode($space['Photo'], true) ?: [];
        }
        
        // Check limit for new uploads (not replacements)
        if ($photo_index === null && count($current_photos) >= $max_photos_per_unit) {
            $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                          <i class="fas fa-exclamation-circle me-2"></i>
                          Maximum ' . $max_photos_per_unit . ' photos allowed per unit. Please delete some photos first.
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file = $_FILES['new_photo'];
            if (!in_array($file['type'], $allowed_types)) {
                $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                              <i class="fas fa-exclamation-circle me-2"></i>
                              Invalid file type for photo.
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
            } elseif ($file['size'] > 2*1024*1024) {
                $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                              <i class="fas fa-exclamation-circle me-2"></i>
                              Photo is too large (max 2MB).
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = "adminunit_" . time() . "_" . rand(1000,9999) . "." . $ext;
                $upload_dir = __DIR__ . "/../uploads/unit_photos/";
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $filepath = $upload_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // FIX: Use updateSpacePhotos correctly with photo_index for replacements
                    if ($db->updateSpacePhotos($space_id, $filename, $photo_index)) {
                        $success_unit = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                                        <i class="fas fa-check-circle me-2"></i>
                                        Photo ' . ($photo_index !== null ? 'updated' : 'uploaded') . ' successfully!
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>';
                    } else {
                        $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                                      <i class="fas fa-exclamation-circle me-2"></i>
                                      Failed to update photo in database.
                                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                      </div>';
                    }
                } else {
                    $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                                  <i class="fas fa-exclamation-circle me-2"></i>
                                  Failed to upload new photo.
                                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                  </div>';
                }
            }
        }
    }
}

// --- Handle form submission for new space/unit ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'unit') {
    $name = trim($_POST['name'] ?? '');
    $spacetype_id = intval($_POST['spacetype_id'] ?? 0);
    $price = isset($_POST['price']) && is_numeric($_POST['price']) ? floatval($_POST['price']) : null;

    // Handle file upload (main photo goes to Photo JSON array)
    $photo_json = null;
    $upload_dir = __DIR__ . "/../uploads/unit_photos/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $photos = [];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                          <i class="fas fa-exclamation-circle me-2"></i>
                          Invalid file type for photo.
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
        } elseif ($file['size'] > 2*1024*1024) {
            $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                          <i class="fas fa-exclamation-circle me-2"></i>
                          Photo is too large (max 2MB).
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = "adminunit_" . time() . "_" . rand(1000,9999) . "." . $ext;
            $filepath = $upload_dir . $filename;
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                $photos[] = $filename;
            } else {
                $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                              <i class="fas fa-exclamation-circle me-2"></i>
                              Failed to upload photo.
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
            }
        }
    }
    $photo_json = json_encode($photos);

    if (empty($name) || empty($spacetype_id) || $price === null || empty($ua_id)) {
        $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                      <i class="fas fa-exclamation-circle me-2"></i>
                      Please fill in all required fields.
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } elseif ($price < 0) {
        $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                      <i class="fas fa-exclamation-circle me-2"></i>
                      Price must be a non-negative number.
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } elseif ($db->isSpaceNameExists($name)) {
        $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                      <i class="fas fa-exclamation-circle me-2"></i>
                      A space/unit with this name already exists.
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } else {
        if ($db->addNewSpace($name, $spacetype_id, $ua_id, $price, $photo_json)) {
            $success_unit = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            Space/unit added successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
        } else {
            $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                          <i class="fas fa-exclamation-circle me-2"></i>
                          A database error occurred. The unit could not be added.
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
        }
    }
}

// --- Handle form submission for new space type ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'type') {
    $spacetype_name = trim($_POST['spacetype_name'] ?? '');

    if (empty($spacetype_name)) {
        $error_type = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                      <i class="fas fa-exclamation-circle me-2"></i>
                      Please enter a space type name.
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } else {
        $existing_types = $db->getAllSpaceTypes();
        $existing = array_filter($existing_types, function($type) use ($spacetype_name) {
            return strtolower(trim($type['SpaceTypeName'])) === strtolower(trim($spacetype_name));
        });
        if ($existing) {
            $error_type = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                          <i class="fas fa-exclamation-circle me-2"></i>
                          This space type already exists.
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
        } else {
            if ($db->addSpaceType($spacetype_name)) {
                $success_type = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                Space type added successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
            } else {
                $error_type = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                              <i class="fas fa-exclamation-circle me-2"></i>
                              A database error occurred. Space type could not be added.
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
            }
        }
    }
}

// --- Fetch Data for Display ---
$spacetypes = $db->getAllSpaceTypes();
$spaces = $db->getAllSpacesWithDetails();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, minimum-scale=1.0, maximum-scale=5.0">
    <title>Space & Unit Management | ASRT Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --dark: #1f2937;
            --darker: #111827;
            --light: #f3f4f6;
            --sidebar-width: 280px;
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(to right, #f8fafc, #f1f5f9);
            color: #374151;
            min-height: 100vh;
            position: relative;
        }

        /* Mobile Menu Overlay */
        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .mobile-overlay.active {
            display: block;
        }

        /* Mobile Header */
        .mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            z-index: 1001;
            padding: 0 1rem;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .mobile-menu-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            padding: 0.5rem;
            border-radius: 8px;
            transition: var(--transition);
        }

        .mobile-menu-btn:hover {
            background: rgba(0,0,0,0.1);
        }

        .mobile-brand {
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--dark);
        }
        
        /* Sidebar Styling */
        .sidebar {
            position: fixed;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--dark), var(--darker));
            color: white;
            padding: 1.5rem 1rem;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: var(--transition);
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 0 0 1.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.35rem;
            color: white;
            text-decoration: none;
        }
        
        .sidebar-brand i {
            color: var(--primary);
            font-size: 1.5rem;
        }
        
        .nav-item {
            margin-bottom: 0.5rem;
            position: relative;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.85);
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .nav-link i {
            width: 24px;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }
        
        .badge-notification {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: var(--transition);
        }
        
        /* Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .page-title h1 {
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--dark);
            margin-bottom: 0;
        }

        .page-title p {
            font-size: 0.9rem;
        }
        
        .title-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            font-size: 1.25rem;
        }
        
        /* Dashboard Card */
        .dashboard-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.25rem 1.5rem;
            background: white;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card-header i {
            color: var(--primary);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Form Styling */
        .form-control, .form-select {
            border-radius: var(--border-radius);
            border: 1px solid #d1d5db;
            transition: var(--transition);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }
        
        /* Table Styling */
        .table-container {
            overflow-x: auto;
            border-radius: var(--border-radius);
        }
        
        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 600px;
        }
        
        .custom-table th {
            background-color: #f9fafb;
            padding: 0.75rem 1rem;
            font-weight: 600;
            text-align: left;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.9rem;
        }
        
        .custom-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        .custom-table tr:last-child td {
            border-bottom: none;
        }
        
        .custom-table tr:hover {
            background-color: #f9fafb;
        }
        
        /* Photo Management - HORIZONTAL LAYOUT */
        .photo-management {
            background: #f9fafb;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .photo-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .photo-item {
            background: white;
            border-radius: var(--border-radius);
            border: 1px solid #e5e7eb;
            padding: 1rem;
            min-width: 200px;
            flex: 1;
        }
        
        .photo-preview {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 0.75rem;
        }
        
        .photo-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.8rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            border: none;
            width: 100%;
            justify-content: center;
        }
        
        .btn-update {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        .btn-update:hover {
            background: var(--primary);
            color: white;
        }
        
        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .btn-delete:hover {
            background: var(--danger);
            color: white;
        }
        
        .btn-upload {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .btn-upload:hover {
            background: var(--secondary);
            color: white;
        }
        
        /* File Input Styling */
        .file-input-container {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-container input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
            width: 100%;
            text-align: center;
        }
        
        .file-input-label:hover {
            background: var(--primary);
            color: white;
        }
        
        .filename-display {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.5rem;
            word-break: break-all;
            text-align: center;
        }
        
        /* Price Display */
        .price-display {
            font-size: 0.9rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Mobile Card Layout for Tables */
        .mobile-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            padding: 1rem;
            border-left: 4px solid var(--primary);
        }

        .mobile-card-header {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .mobile-card-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .mobile-card-detail .label {
            font-weight: 500;
            color: #6b7280;
        }

        .mobile-card-detail .value {
            color: var(--dark);
        }

        .mobile-photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .mobile-photo-item {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            border: 1px solid #e5e7eb;
        }

        .mobile-photo-item img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }

        .mobile-photo-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .mobile-photo-actions .btn-action {
            padding: 0.5rem;
            font-size: 0.7rem;
        }

        /* Add Photo Section */
        .add-photo-section {
            background: white;
            border: 2px dashed #d1d5db;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            margin-top: 1rem;
        }

        .add-photo-section:hover {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.02);
        }
        
        /* Mobile Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }

            .mobile-header {
                display: flex;
            }
            
            .main-content {
                margin-left: 0;
                margin-top: 60px;
                padding: 1rem;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }

            .page-title h1 {
                font-size: 1.5rem;
            }

            .title-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .card-body {
                padding: 1rem;
            }

            .card-header {
                padding: 1rem;
                font-size: 1rem;
            }

            .custom-table {
                display: none;
            }

            .table-mobile {
                display: block;
            }

            .btn-action {
                font-size: 0.75rem;
                padding: 0.4rem 0.8rem;
            }

            .photo-grid {
                flex-direction: column;
            }

            .photo-item {
                min-width: auto;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 0.75rem;
            }

            .dashboard-card {
                margin-bottom: 1.5rem;
            }

            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .mobile-photo-grid {
                grid-template-columns: 1fr;
            }

            .photo-preview {
                height: 100px;
            }
        }

        @media (max-width: 480px) {
            .page-title h1 {
                font-size: 1.3rem;
            }

            .dashboard-card {
                border-radius: 8px;
            }

            .btn {
                font-size: 0.9rem;
                padding: 0.75rem 1.5rem;
            }

            .form-control, .form-select {
                padding: 0.75rem;
            }
        }

        /* Hide desktop table on mobile */
        .table-mobile {
            display: none;
        }

        @media (max-width: 992px) {
            .table-mobile {
                display: block;
            }
        }
        
        /* Animations */
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .btn-action {
                min-height: 44px;
                min-width: 44px;
            }

            .nav-link {
                min-height: 44px;
            }

            .mobile-menu-btn {
                min-height: 44px;
                min-width: 44px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </button>
        <div class="mobile-brand">
            ASRT Admin
        </div>
        <div></div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="#" class="sidebar-brand">
                <i class="fas fa-crown"></i>
                <span>ASRT Admin</span>
            </a>
        </div>
        
        <div class="sidebar-nav">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="manage_user.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Manage Users & Units</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="view_rental_requests.php" class="nav-link">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Rental Requests</span>
                </a>
            </div>
            
           <div class="nav-item">
    <a href="manage_maintenance.php" class="nav-link">
        <i class="fas fa-tools"></i>
        <span>Maintenance</span>
    </a>
</div>
            
            <div class="nav-item">
                <a href="generate_invoice.php" class="nav-link">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Invoices</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="add_unit.php" class="nav-link active">
                    <i class="fas fa-plus-square"></i>
                    <span>Add Unit</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="admin_add_handyman.php" class="nav-link">
                    <i class="fas fa-user-plus"></i>
                    <span>Add Handyman</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="admin_kick_unpaid.php" class="nav-link">
                    <i class="fas fa-user-slash"></i>
                    <span>Overdue Accounts</span>
                </a>
            </div>
            
            <div class="nav-item mt-4">
                <a href="logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="page-title">
                <div class="title-icon">
                    <i class="fas fa-home"></i>
                </div>
                <div>
                    <h1>Space & Unit Management</h1>
                    <p class="text-muted mb-0">Add and manage spaces, units, and space types</p>
                </div>
            </div>
        </div>
        
        <?= $success_unit ?>
        <?= $error_unit ?>
        <?= $success_type ?>
        <?= $error_type ?>
        
        <div class="row">
            <!-- Add New Space/Unit -->
            <div class="col-lg-6">
                <div class="dashboard-card animate-fade-in">
                    <div class="card-header">
                        <i class="fas fa-plus-circle"></i>
                        <span>Add New Space/Unit</span>
                    </div>
                    <div class="card-body">
                        <form method="post" enctype="multipart/form-data" class="row g-3" autocomplete="off">
                            <input type="hidden" name="form_type" value="unit" />
                            <div class="col-12">
                                <label for="name" class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                                <input id="name" type="text" class="form-control" name="name" placeholder="Unit name" required />
                            </div>
                            <div class="col-12">
                                <label for="spacetype_id" class="form-label fw-semibold">Space Type <span class="text-danger">*</span></label>
                                <select id="spacetype_id" name="spacetype_id" class="form-select" required>
                                    <option value="" selected disabled>Select Type</option>
                                    <?php foreach ($spacetypes as $stype): ?>
                                        <option value="<?= $stype['SpaceType_ID'] ?>"><?= htmlspecialchars($stype['SpaceTypeName']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="price" class="form-label fw-semibold">Price (PHP) <span class="text-danger">*</span></label>
                                <input id="price" type="number" step="100" min="0" class="form-control" name="price" placeholder="0.00" required />
                                <div id="priceDisplay" class="price-display"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Main Photo (max 2MB, JPG/PNG/GIF):</label>
                                <div class="file-input-container">
                                    <div class="file-input-label">
                                        <i class="fas fa-upload me-1"></i> Choose File
                                    </div>
                                    <input type="file" name="photo" accept="image/*" required />
                                </div>
                                <div class="filename-display" id="photoFileName"></div>
                            </div>
                            <div class="col-12 text-center mt-4">
                                <button type="submit" class="btn btn-primary px-5">
                                    <i class="fas fa-plus-circle me-1"></i> Add Space/Unit
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Add New Space Type -->
            <div class="col-lg-6">
                <div class="dashboard-card animate-fade-in">
                    <div class="card-header">
                        <i class="fas fa-tag"></i>
                        <span>Add New Space Type</span>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3" autocomplete="off">
                            <input type="hidden" name="form_type" value="type" />
                            <div class="col-12">
                                <label for="spacetype_name" class="form-label fw-semibold">Space Type Name <span class="text-danger">*</span></label>
                                <input id="spacetype_name" type="text" class="form-control" name="spacetype_name" placeholder="e.g. Apartment, Studio, Commercial" required />
                            </div>
                            <div class="col-12 text-center mt-4">
                                <button type="submit" class="btn btn-primary px-5">
                                    <i class="fas fa-plus-circle me-1"></i> Add Space Type
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Existing Spaces/Units -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-list"></i>
                <span>Existing Spaces/Units</span>
                <span class="badge bg-primary ms-2"><?= count($spaces) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($spaces)): ?>
                    <!-- Desktop Table -->
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Price (PHP)</th>
                                    <th>Photos (Max: <?= $max_photos_per_unit ?>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($spaces as $space): 
                                    $photos = [];
                                    if (!empty($space['Photo'])) {
                                        $photos = json_decode($space['Photo'], true) ?: [];
                                    }
                                    $current_count = count($photos);
                                    $can_add_more = $current_count < $max_photos_per_unit;
                                    $photos_remaining = $max_photos_per_unit - $current_count;
                                ?>
                                    <tr>
                                        <td>
                                            <span class="fw-medium">#<?= $space['Space_ID'] ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($space['Name']) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars($space['SpaceTypeName']) ?></td>
                                        <td>₱<?= number_format($space['Price'], 2) ?></td>
                                        <td>
                                            <div class="photo-management">
                                                <!-- Photo Counter -->
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <small class="text-muted">
                                                        <?= $current_count ?>/<?= $max_photos_per_unit ?> photos
                                                    </small>
                                                    <?php if (!$can_add_more): ?>
                                                        <span class="badge bg-warning">Limit Reached</span>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Existing Photos Grid -->
                                                <?php if (!empty($photos)): ?>
                                                    <div class="photo-grid">
                                                        <?php foreach ($photos as $index => $photo): ?>
                                                            <div class="photo-item">
                                                                <img src="../uploads/unit_photos/<?= htmlspecialchars($photo) ?>" class="photo-preview" alt="Photo <?= $index + 1 ?>">
                                                                <div class="photo-actions">
                                                                    <form method="post" enctype="multipart/form-data">
                                                                        <div class="file-input-container">
                                                                            <div class="file-input-label btn-action btn-update">
                                                                                <i class="fas fa-sync-alt"></i> Update
                                                                            </div>
                                                                            <input type="file" name="new_photo" accept="image/*" required onchange="showFileName(this, 'update<?= $space['Space_ID'].$index ?>')">
                                                                            <input type="hidden" name="form_type" value="update_photo">
                                                                            <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                                            <input type="hidden" name="photo_index" value="<?= $index ?>">
                                                                        </div>
                                                                        <span class="filename-display" id="update<?= $space['Space_ID'].$index ?>"></span>
                                                                        <button type="submit" class="btn btn-primary btn-sm mt-2 w-100">Update</button>
                                                                    </form>
                                                                    <form method="post" onsubmit="return confirm('Delete this photo?');">
                                                                        <input type="hidden" name="form_type" value="delete_photo">
                                                                        <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                                        <input type="hidden" name="photo_index" value="<?= $index ?>">
                                                                        <button type="submit" class="btn-action btn-delete">
                                                                            <i class="fas fa-trash"></i> Delete
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center text-muted py-3">
                                                        <i class="fas fa-images fa-2x mb-2"></i>
                                                        <p>No photos uploaded yet</p>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Add New Photo Section -->
                                                <?php if ($can_add_more): ?>
                                                    <div class="add-photo-section">
                                                        <div class="text-success mb-2">
                                                            <i class="fas fa-info-circle"></i>
                                                            <?= $photos_remaining ?> photo(s) remaining
                                                        </div>
                                                        <form method="post" enctype="multipart/form-data">
                                                            <div class="file-input-container">
                                                                <div class="file-input-label btn-action btn-upload">
                                                                    <i class="fas fa-plus-circle me-1"></i> Add New Photo
                                                                </div>
                                                                <input type="file" name="new_photo" accept="image/*" required onchange="showFileName(this, 'add<?= $space['Space_ID'] ?>')">
                                                                <input type="hidden" name="form_type" value="update_photo">
                                                                <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                            </div>
                                                            <span class="filename-display" id="add<?= $space['Space_ID'] ?>"></span>
                                                            <button type="submit" class="btn btn-success btn-sm mt-2">
                                                                <i class="fas fa-upload me-1"></i> Upload Photo
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="add-photo-section text-center text-muted">
                                                        <i class="fas fa-ban fa-2x mb-2"></i>
                                                        <p>Maximum <?= $max_photos_per_unit ?> photos reached</p>
                                                        <small>Delete some photos to add new ones</small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card Layout -->
                    <div class="table-mobile">
                        <?php foreach ($spaces as $space): 
                            $photos = [];
                            if (!empty($space['Photo'])) {
                                $photos = json_decode($space['Photo'], true) ?: [];
                            }
                            $current_count = count($photos);
                            $can_add_more = $current_count < $max_photos_per_unit;
                            $photos_remaining = $max_photos_per_unit - $current_count;
                        ?>
                            <div class="mobile-card">
                                <div class="mobile-card-header">
                                    <?= htmlspecialchars($space['Name']) ?>
                                    <span class="badge bg-primary ms-2">#<?= $space['Space_ID'] ?></span>
                                    <span class="badge bg-secondary ms-1"><?= $current_count ?>/<?= $max_photos_per_unit ?> photos</span>
                                    <?php if (!$can_add_more): ?>
                                        <span class="badge bg-warning ms-1">Limit Reached</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mobile-card-detail">
                                    <span class="label">Type:</span>
                                    <span class="value"><?= htmlspecialchars($space['SpaceTypeName']) ?></span>
                                </div>
                                
                                <div class="mobile-card-detail">
                                    <span class="label">Price:</span>
                                    <span class="value">₱<?= number_format($space['Price'], 2) ?></span>
                                </div>

                                <div class="mobile-photo-grid">
                                    <?php foreach ($photos as $index => $photo): ?>
                                        <div class="mobile-photo-item">
                                            <img src="../uploads/unit_photos/<?= htmlspecialchars($photo) ?>" alt="Photo <?= $index + 1 ?>">
                                            <div class="mobile-photo-actions">
                                                <form method="post" enctype="multipart/form-data">
                                                    <div class="file-input-container w-100">
                                                        <div class="file-input-label btn-action btn-update w-100">
                                                            <i class="fas fa-sync-alt"></i> Update
                                                        </div>
                                                        <input type="file" name="new_photo" accept="image/*" required onchange="showFileName(this, 'mobile-update<?= $space['Space_ID'].$index ?>')">
                                                        <input type="hidden" name="form_type" value="update_photo">
                                                        <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                        <input type="hidden" name="photo_index" value="<?= $index ?>">
                                                    </div>
                                                    <div class="filename-display" id="mobile-update<?= $space['Space_ID'].$index ?>"></div>
                                                    <button type="submit" class="btn btn-primary btn-sm w-100 mt-1" style="font-size: 0.7rem;">Update</button>
                                                </form>
                                                <form method="post" onsubmit="return confirm('Delete this photo?');">
                                                    <input type="hidden" name="form_type" value="delete_photo">
                                                    <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                    <input type="hidden" name="photo_index" value="<?= $index ?>">
                                                    <button type="submit" class="btn-action btn-delete w-100">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Add New Photo Mobile -->
                                    <?php if ($can_add_more): ?>
                                        <div class="mobile-photo-item" style="background: transparent; border: 2px dashed #d1d5db;">
                                            <div class="text-success mb-2">
                                                <i class="fas fa-info-circle"></i>
                                                <?= $photos_remaining ?> photo(s) remaining
                                            </div>
                                            <form method="post" enctype="multipart/form-data">
                                                <div class="file-input-container w-100">
                                                    <div class="file-input-label btn-action btn-upload w-100">
                                                        <i class="fas fa-plus-circle"></i> Add Photo
                                                    </div>
                                                    <input type="file" name="new_photo" accept="image/*" required onchange="showFileName(this, 'mobile-add<?= $space['Space_ID'] ?>')">
                                                    <input type="hidden" name="form_type" value="update_photo">
                                                    <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                </div>
                                                <div class="filename-display" id="mobile-add<?= $space['Space_ID'] ?>"></div>
                                                <button type="submit" class="btn btn-success btn-sm w-100 mt-1" style="font-size: 0.7rem;">
                                                    <i class="fas fa-upload"></i> Upload
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="mobile-photo-item text-center text-muted" style="background: transparent; border: 2px dashed #e5e7eb;">
                                            <i class="fas fa-ban fa-2x mb-2"></i>
                                            <p>Maximum reached</p>
                                            <small>Delete photos to add new ones</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-home"></i>
                        <h4>No spaces/units found</h4>
                        <p>There are no spaces or units in the system</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Existing Space Types -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-tags"></i>
                <span>Existing Space Types</span>
                <span class="badge bg-primary ms-2"><?= count($spacetypes) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($spacetypes)): ?>
                    <!-- Desktop Table -->
                    <div class="table-container">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($spacetypes as $type): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-medium">#<?= $type['SpaceType_ID'] ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($type['SpaceTypeName']) ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card Layout -->
                    <div class="table-mobile">
                        <?php foreach ($spacetypes as $type): ?>
                            <div class="mobile-card">
                                <div class="mobile-card-header">
                                    <?= htmlspecialchars($type['SpaceTypeName']) ?>
                                    <span class="badge bg-primary ms-2">#<?= $type['SpaceType_ID'] ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-tag"></i>
                        <h4>No space types found</h4>
                        <p>There are no space types in the system</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu functionality
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');

        function toggleMobileMenu() {
            sidebar.classList.toggle('active');
            mobileOverlay.classList.toggle('active');
        }

        mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        mobileOverlay.addEventListener('click', toggleMobileMenu);

        // Close mobile menu when clicking on nav links
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    sidebar.classList.remove('active');
                    mobileOverlay.classList.remove('active');
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 992) {
                sidebar.classList.remove('active');
                mobileOverlay.classList.remove('active');
            }
        });

        // Price formatting
        document.getElementById('price').addEventListener('input', function() {
            const val = this.value;
            const display = document.getElementById('priceDisplay');
            if (val !== "" && !isNaN(val)) {
                display.textContent = "₱ " + Number(val).toLocaleString();
            } else {
                display.textContent = "";
            }
        });

        // File name display for main photo
        document.querySelector('input[name="photo"]').addEventListener('change', function() {
            const display = document.getElementById('photoFileName');
            if (this.files.length > 0) {
                display.textContent = this.files[0].name;
            } else {
                display.textContent = '';
            }
        });

        // File name display for photo updates
        function showFileName(input, elementId) {
            const display = document.getElementById(elementId);
            if (display && input.files.length > 0) {
                display.textContent = input.files[0].name;
            } else if (display) {
                display.textContent = '';
            }
        }

        // Check photo limit before upload
        function checkPhotoLimit(spaceId, isNewUpload = true) {
            if (!isNewUpload) return true; // Allow updates/replacements
            
            // Count existing photos for this space
            const existingPhotos = document.querySelectorAll(`[data-space-id="${spaceId}"] .photo-item`).length;
            const maxPhotos = <?= $max_photos_per_unit ?>;
            
            if (existingPhotos >= maxPhotos) {
                alert(`Maximum ${maxPhotos} photos allowed. Please delete some photos first.`);
                return false;
            }
            return true;
        }

        // Add to your file input change events
        document.querySelectorAll('input[type="file"][name="new_photo"]').forEach(input => {
            input.addEventListener('change', function() {
                const form = this.closest('form');
                const spaceId = form.querySelector('input[name="space_id"]').value;
                const isNewUpload = !form.querySelector('input[name="photo_index"]');
                
                if (isNewUpload && !checkPhotoLimit(spaceId, true)) {
                    this.value = ''; // Clear the file input
                }
            });
        });

        // Add data-space-id attributes to photo items for counting
        document.querySelectorAll('.photo-item').forEach(item => {
            const spaceId = item.closest('tr').querySelector('td:first-child span').textContent.replace('#', '');
            item.closest('td').setAttribute('data-space-id', spaceId);
        });

        // Confirmation for delete actions
        document.querySelectorAll('form').forEach(form => {
            if (form.querySelector('input[name="form_type"][value="delete_photo"]')) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Are you sure you want to delete this photo? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            }
        });

        // Prevent double submission on forms
        document.querySelectorAll('form').forEach(form => {
            let isSubmitting = false;
            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }
                isSubmitting = true;
                
                // Re-enable after 3 seconds to handle errors
                setTimeout(() => {
                    isSubmitting = false;
                }, 3000);
            });
        });

        // Auto-hide alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 300);
                }
            }, 5000);
        });
    </script>
</body>
</html>
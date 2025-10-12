<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Debug output for POST and FILES
if (!empty($_POST) || !empty($_FILES)) {
    echo '<pre style="background:#fffbe6;color:#b91c1c;padding:1em;border:1px solid #fde68a;max-width:700px;margin:2em auto;overflow:auto;">';
    echo "<strong>POST:</strong>\n";
    print_r($_POST);
    echo "<strong>FILES:</strong>\n";
    print_r($_FILES);
    echo '</pre>';
}

// Debug photo upload specifically
if (isset($_POST['space_id']) && isset($_FILES['unit_photo'])) {
    echo '<div style="background:#e6f3ff;color:#1e40af;padding:1em;border:1px solid #60a5fa;margin:1em auto;max-width:700px;">';
    echo "<strong>PHOTO UPLOAD DEBUG:</strong><br>";
    echo "Space ID: " . $_POST['space_id'] . "<br>";
    echo "File present: " . (isset($_FILES['unit_photo']) ? 'YES' : 'NO') . "<br>";
    echo "Upload button in POST: " . (isset($_POST['upload_unit_photo']) ? 'YES' : 'NO') . "<br>";
    echo "File error: " . $_FILES['unit_photo']['error'] . "<br>";
    echo "</div>";
}

require 'database/database.php'; 
session_start();

// Create an instance of the Database class
$db = new Database();

if (!isset($_SESSION['client_id'])) {
    header('Location: login.php');
    exit();
}
$client_id = $_SESSION['client_id'];

// --- CHECK IF CLIENT IS INACTIVE ---
$client_status = $db->getClientStatus($client_id);
if ($client_status && isset($client_status['Status']) && strtolower($client_status['Status']) !== 'active') {
    // Show SweetAlert and log out automatically
    echo <<<HTML
    <!doctype html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <title>Account Inactive</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
    <script>
      Swal.fire({
        icon: 'warning',
        title: 'Account has been Inactive',
        text: 'Please message the admin for activation.',
        confirmButtonColor: '#d33',
        allowOutsideClick: false,
        allowEscapeKey: false
      }).then(() => {
        window.location.href = 'logout.php?inactive=1';
      });
      setTimeout(function() {
        window.location.href = 'logout.php?inactive=1';
      }, 7000); // Fallback: auto-logout after 7 seconds
    </script>
    </body>
    </html>
    HTML;
    exit();
}

$show_login_success = false;
if (isset($_SESSION['login_success'])) {
    $show_login_success = true;
    unset($_SESSION['login_success']);
}

// Initialize feedback variables
$feedback_success = '';
$feedback_error = '';

// --- FEEDBACK PROCESSING (FIXED) ---
if (isset($_POST['invoice_id']) && isset($_POST['rating']) && !empty($_POST['rating'])) {
    $invoice_id = intval($_POST['invoice_id']);
    $rating = intval($_POST['rating']);
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';

    // Validate rating range
    if ($rating >= 1 && $rating <= 5) {
        // Check if feedback already exists for this invoice
        if (method_exists($db, 'checkExistingFeedback')) {
            $existing_feedback = $db->checkExistingFeedback($invoice_id);
        } else {
            $existing_feedback = false; // Skip check if method doesn't exist
        }
        
        if (!$existing_feedback) {
            if ($db->saveFeedback($invoice_id, $rating, $comments)) {
                $feedback_success = "Thank you for your feedback!";
                $_SESSION['feedback_success'] = $feedback_success;
                // Redirect to prevent resubmission
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $feedback_error = "Failed to save feedback. Please try again.";
            }
        } else {
            $feedback_error = "You have already provided feedback for this invoice.";
        }
    } else {
        $feedback_error = "Invalid rating value. Please select a rating between 1 and 5.";
    }
}

// Check for feedback success from redirect
if (isset($_SESSION['feedback_success'])) {
    $feedback_success = $_SESSION['feedback_success'];
    unset($_SESSION['feedback_success']);
}

// --- PHOTO UPLOAD/DELETE LOGIC (UPDATED FOR JSON) ---
$photo_upload_success = '';
$photo_upload_error = '';

// Photo Upload Processing
if (isset($_POST['space_id']) && isset($_FILES['unit_photo']) && $_FILES['unit_photo']['error'] === 0) {
    $space_id = intval($_POST['space_id']);
    $file = $_FILES['unit_photo'];

    // Validate space_id belongs to client
    $rented_units = $db->getRentedUnits($client_id);
    $valid_space_ids = array_column($rented_units, 'Space_ID');
    
    if (!in_array($space_id, $valid_space_ids)) {
        $photo_upload_error = "Invalid space ID. You don't have access to this unit.";
    } else if ($file['error'] !== UPLOAD_ERR_OK) {
        // Handle upload errors
        switch($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $photo_upload_error = "File size too large. Maximum 2MB allowed.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $photo_upload_error = "File upload was interrupted. Please try again.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $photo_upload_error = "No file selected. Please choose an image to upload.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $photo_upload_error = "Server configuration error. Please contact support.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $photo_upload_error = "Failed to save file. Please try again.";
                break;
            default:
                $photo_upload_error = "Unknown upload error occurred.";
        }
    } else {
        // Get current photos from JSON column
        $unit_photos_temp = $db->getUnitPhotosForClient($client_id);
        $current_photos = isset($unit_photos_temp[$space_id]) ? $unit_photos_temp[$space_id] : [];
        $used_slots = count($current_photos);
        
        if ($used_slots >= 6) {
            $photo_upload_error = "You can upload up to 6 photos only. Please delete some photos first.";
        } else {
            // Validate file type and size
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            // Get actual file type using finfo
            if (function_exists('finfo_open')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $actual_type = $finfo->file($file['tmp_name']);
            } else {
                $actual_type = $file['type']; // Fallback
            }
            
            if (!in_array($actual_type, $allowed_types)) {
                $photo_upload_error = "Invalid file type. Only JPG, PNG, and GIF images are allowed.";
            } else if ($file['size'] > $max_size) {
                $photo_upload_error = "File size too large. Maximum 2MB allowed.";
            } else {
                // Validate image dimensions
                $image_info = getimagesize($file['tmp_name']);
                if ($image_info === false) {
                    $photo_upload_error = "Invalid image file. File appears to be corrupted.";
                } else if ($image_info[0] > 2048 || $image_info[1] > 2048) {
                    $photo_upload_error = "Image dimensions too large. Maximum 2048x2048 pixels allowed.";
                } else {
                    // Generate secure filename
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (!in_array($ext, $allowed_extensions)) {
                        $photo_upload_error = "Invalid file extension. Only .jpg, .jpeg, .png, and .gif files are allowed.";
                    } else {
                        // Create upload directory if it doesn't exist
                        $upload_dir = __DIR__ . "/uploads/unit_photos/";
                        if (!is_dir($upload_dir)) {
                            if (!mkdir($upload_dir, 0755, true)) {
                                $photo_upload_error = "Failed to create upload directory. Please contact support.";
                            }
                        }
                        
                        if (empty($photo_upload_error)) {
                            // Generate unique filename
                            $filename = "unit_{$space_id}_client_{$client_id}_" . uniqid() . "." . $ext;
                            $filepath = $upload_dir . $filename;
                            
                            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                                // Add to existing photos array and save as JSON
                                $current_photos[] = $filename;
                                $json_photos = json_encode($current_photos);
                                
                                // Save to database (UPDATED FOR JSON)
                                if ($db->updateUnitPhotos($space_id, $client_id, $json_photos)) {
                                    $photo_upload_success = "Photo uploaded successfully for this unit!";
                                    $_SESSION['photo_upload_success'] = $photo_upload_success;
                                    // Redirect to prevent resubmission
                                    header("Location: " . $_SERVER['PHP_SELF']);
                                    exit();
                                } else {
                                    // Delete uploaded file if database insert failed
                                    if (file_exists($filepath)) {
                                        unlink($filepath);
                                    }
                                    $photo_upload_error = "Database error occurred. Photo was not saved.";
                                }
                            } else {
                                $photo_upload_error = "Failed to move uploaded file. Check file permissions.";
                            }
                        }
                    }
                }
            }
        }
    }
}

// Photo Delete Processing (UPDATED FOR JSON)
if (isset($_POST['space_id']) && isset($_POST['photo_filename']) && !empty($_POST['photo_filename'])) {
    $space_id = intval($_POST['space_id']);
    $photo_filename = trim($_POST['photo_filename']);
    
    // Validate space_id belongs to client
    $rented_units = $db->getRentedUnits($client_id);
    $valid_space_ids = array_column($rented_units, 'Space_ID');
    
    if (!in_array($space_id, $valid_space_ids)) {
        $photo_upload_error = "Invalid space ID. You don't have access to this unit.";
    } else if (empty($photo_filename)) {
        $photo_upload_error = "Invalid photo filename.";
    } else {
        // Validate filename to prevent directory traversal
        if (!preg_match('/^unit_\d+_client_\d+_[a-zA-Z0-9]+\.(jpg|jpeg|png|gif)$/i', $photo_filename)) {
            $photo_upload_error = "Invalid photo filename format.";
        } else {
            // Get current photos and remove the specified one
            $unit_photos_temp = $db->getUnitPhotosForClient($client_id);
            $current_photos = isset($unit_photos_temp[$space_id]) ? $unit_photos_temp[$space_id] : [];
            
            if (!in_array($photo_filename, $current_photos)) {
                $photo_upload_error = "Photo not found or you don't have permission to delete it.";
            } else {
                // Remove photo from array
                $updated_photos = array_values(array_diff($current_photos, [$photo_filename]));
                $json_photos = json_encode($updated_photos);
                
                // Update database with new JSON array (UPDATED FOR JSON)
                if ($db->updateUnitPhotos($space_id, $client_id, $json_photos)) {
                    // Delete file from filesystem
                    $upload_dir = __DIR__ . "/uploads/unit_photos/";
                    $file_to_delete = $upload_dir . basename($photo_filename);
                    if (file_exists($file_to_delete)) {
                        if (unlink($file_to_delete)) {
                            $photo_upload_success = "Photo deleted successfully!";
                        } else {
                            $photo_upload_success = "Photo deleted from database, but file removal failed.";
                        }
                    } else {
                        $photo_upload_success = "Photo deleted successfully!";
                    }
                    $_SESSION['photo_upload_success'] = $photo_upload_success;
                    // Redirect to prevent resubmission
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $photo_upload_error = "Failed to delete photo from database.";
                }
            }
        }
    }
}

// Check for photo success from redirect
if (isset($_SESSION['photo_upload_success'])) {
    $photo_upload_success = $_SESSION['photo_upload_success'];
    unset($_SESSION['photo_upload_success']);
}

// --- SAFELY GET CLIENT DETAILS ---
$client_details = $db->getClientDetails($client_id);
$client_display = "Unknown User";

if ($client_details && is_array($client_details)) {
    $first_name = isset($client_details['Client_fn']) ? trim($client_details['Client_fn']) : '';
    $last_name = isset($client_details['Client_ln']) ? trim($client_details['Client_ln']) : '';
    $username = isset($client_details['C_username']) ? trim($client_details['C_username']) : '';
    
    $full_name = trim("$first_name $last_name");
    $client_display = !empty($full_name) ? $full_name : (!empty($username) ? $username : "Unknown User");
}

// Get data with error handling
try {
    $feedback_prompts = $db->getFeedbackPrompts($client_id);
    $rented_units = $db->getRentedUnits($client_id);
    
    // Ensure arrays are returned
    $feedback_prompts = is_array($feedback_prompts) ? $feedback_prompts : [];
    $rented_units = is_array($rented_units) ? $rented_units : [];
    
    // Get maintenance history and photos only if there are rented units
    $maintenance_history = [];
    $unit_photos = [];
    
    if (!empty($rented_units)) {
        $unit_ids = array_column($rented_units, 'Space_ID');
        $maintenance_history = $db->getMaintenanceHistoryForUnits($unit_ids, $client_id);
        $unit_photos = $db->getUnitPhotosForClient($client_id);
        
        // Ensure arrays are returned
        $maintenance_history = is_array($maintenance_history) ? $maintenance_history : [];
        $unit_photos = is_array($unit_photos) ? $unit_photos : [];
    }
    
} catch (Exception $e) {
    // Log error and set defaults
    error_log("Dashboard data fetch error: " . $e->getMessage());
    $feedback_prompts = [];
    $rented_units = [];
    $maintenance_history = [];
    $unit_photos = [];
}

// Function to format date in month letters
function formatDateToMonthLetters($date) {
    if ($date === 'N/A' || empty($date)) {
        return 'N/A';
    }
    
    try {
        $dateTime = new DateTime($date);
        return $dateTime->format('F j, Y');
    } catch (Exception $e) {
        return $date; // Return original if parsing fails
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Client Dashboard - ASRT Commercial Spaces</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary: #1e293b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f8fafc;
            --lighter: #ffffff;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --gray-dark: #334155;
            --border-radius: 16px;
            --border-radius-sm: 8px;
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.02);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.08);
            --shadow-xl: 0 16px 40px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            color: var(--secondary);
            line-height: 1.6;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: 0.2em;
            right: -0.7em;
            background: #ef4444;
            color: #fff;
            font-size: 0.75em;
            font-weight: bold;
            border-radius: 50%;
            padding: 0.2em 0.55em;
            min-width: 1.5em;
            min-height: 1.5em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(239,68,68,0.15);
            z-index: 10;
            transition: all 0.2s;
            pointer-events: none;
            border: 2px solid #fff;
            animation: pulse-badge 1.2s infinite;
        }
        
        @keyframes pulse-badge {
            0% { box-shadow: 0 0 0 0 rgba(239,68,68,0.4); }
            70% { box-shadow: 0 0 0 8px rgba(239,68,68,0); }
            100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
        }

        /* Modern Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--gray-light);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1030;
            box-shadow: var(--shadow-sm);
        }

        .navbar-brand {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary) !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link {
            font-weight: 500;
            color: var(--gray-dark) !important;
            padding: 0.75rem 1rem !important;
            border-radius: var(--border-radius-sm);
            transition: var(--transition);
            position: relative;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--primary) !important;
            background: rgba(37, 99, 235, 0.1);
        }

        .navbar-toggler {
            border: none;
            padding: 0.5rem;
        }

        .navbar-toggler:focus {
            box-shadow: none;
        }

        /* Dashboard Header */
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 3rem 0 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" patternUnits="userSpaceOnUse" width="20" height="20"><circle cx="10" cy="10" r="1.5" fill="white" opacity="0.15"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
        }

        .dashboard-header-content {
            position: relative;
            z-index: 2;
        }

        .welcome-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        /* Alert Styles */
        .alert {
            border: none;
            border-radius: var(--border-radius);
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            font-weight: 500;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            background: var(--lighter);
            transition: var(--transition);
            height: 100%;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            background: var(--light);
            border-bottom: 1px solid var(--gray-light);
            padding: 1.25rem;
            font-weight: 600;
            color: var(--secondary);
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Unit Cards */
        .unit-card {
            border: 1px solid var(--gray-light);
            background: var(--lighter);
            transition: var(--transition);
        }

        .unit-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-xl);
        }

        .unit-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }

        .unit-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .unit-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .unit-badge {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: var(--border-radius-sm);
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .unit-details {
            color: var(--gray);
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }

        /* Photo Gallery */
        .photo-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 0.75rem;
            margin: 1rem 0;
        }

        .photo-item {
            position: relative;
            border-radius: var(--border-radius-sm);
            overflow: hidden;
            aspect-ratio: 4/3;
        }

        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
            cursor: pointer;
        }

        .photo-item:hover img {
            transform: scale(1.05);
        }

        .delete-photo-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            cursor: pointer;
            opacity: 0;
            transition: var(--transition);
            z-index: 10;
        }

        .photo-item:hover .delete-photo-btn {
            opacity: 1;
        }

        .no-photos {
            text-align: center;
            color: var(--gray);
            font-style: italic;
            padding: 2rem;
            background: var(--light);
            border-radius: var(--border-radius-sm);
            margin: 1rem 0;
        }

        /* Upload Form */
        .upload-form {
            background: var(--light);
            border-radius: var(--border-radius-sm);
            padding: 1rem;
            margin-top: 1rem;
        }

        .upload-form input[type="file"] {
            border: 2px dashed var(--gray-light);
            border-radius: var(--border-radius-sm);
            padding: 1rem;
            text-align: center;
            transition: var(--transition);
            background: white;
        }

        .upload-form input[type="file"]:hover {
            border-color: var(--primary);
        }

        /* Image Preview */
        .image-preview {
            display: block;
            margin-top: 10px;
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-sm);
        }

        /* Maintenance History */
        .maintenance-section {
            background: var(--light);
            border-radius: var(--border-radius-sm);
            padding: 1.25rem;
            margin-top: 1.5rem;
            border-left: 4px solid var(--primary);
        }

        .maintenance-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .maintenance-header h6 {
            font-weight: 700;
            color: var(--primary);
            margin: 0;
        }

        .maintenance-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .maintenance-item {
            background: white;
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius-sm);
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--gray-light);
        }

        .maintenance-date {
            font-weight: 600;
            color: var(--secondary);
        }

        .maintenance-status {
            padding: 0.25rem 0.75rem;
            border-radius: var(--border-radius-sm);
            font-size: 0.8rem;
            font-weight: 500;
            margin-left: auto;
        }

        .maintenance-status.completed {
            background: var(--success);
            color: white;
        }

        .maintenance-status.pending {
            background: var(--warning);
            color: white;
        }

        .maintenance-status.in-progress {
            background: var(--primary);
            color: white;
        }

        .no-maintenance {
            text-align: center;
            color: var(--gray);
            font-style: italic;
            padding: 1rem;
        }

        /* Buttons */
        .btn {
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            padding: 0.6rem 1.2rem;
            transition: var(--transition);
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #34d399);
            color: white;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669, var(--success));
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        /* Form Elements */
        .form-control,
        .form-select {
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius-sm);
            padding: 0.75rem 1rem;
            transition: var(--transition);
            background: white;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }

        .form-label {
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        /* Feedback Section */
        .feedback-card {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid var(--warning);
        }

        .rating-select {
            background: white;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .welcome-title {
                font-size: 2rem;
            }

            .dashboard-header {
                padding: 2rem 0 1.5rem;
            }

            .card-body {
                padding: 1rem;
            }

            .photo-gallery {
                grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
                gap: 0.5rem;
            }

            .unit-icon {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .maintenance-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .maintenance-status {
                margin-left: 0;
            }
        }

        @media (max-width: 576px) {
            .welcome-title {
                font-size: 1.75rem;
            }

            .navbar-brand {
                font-size: 1.5rem;
            }

            .upload-form {
                padding: 0.75rem;
            }

            .maintenance-section {
                padding: 1rem;
            }
        }

        /* Loading States */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h5 {
            color: var(--gray-dark);
            margin-bottom: 0.5rem;
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Maintenance History Dropdown */
        .maintenance-dropdown {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--gray-light);
            border-radius: var(--border-radius-sm);
            background: white;
            margin-top: 0.5rem;
        }
        .maintenance-dropdown::-webkit-scrollbar {
            width: 6px;
        }
        .maintenance-dropdown::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .maintenance-dropdown::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        .maintenance-dropdown::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        .toggle-maintenance {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 0.85rem;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: var(--border-radius-sm);
            transition: var(--transition);
        }
        .toggle-maintenance:hover {
            background: rgba(37, 99, 235, 0.1);
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-house-door-fill"></i>
                ASRT Home
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house me-1"></i>Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="invoice_history.php">
                            <i class="bi bi-credit-card me-1"></i>Payment
                            <span class="notification-badge d-none" id="client-unread-admin-badge"></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="handyman_type.php">
                            <i class="bi bi-tools me-1"></i>Handyman
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="maintenance.php">
                            <i class="bi bi-gear me-1"></i>Maintenance
                        </a>
                    </li>
                    <li class="nav-item ms-lg-3">
                        <form action="logout.php" method="post" class="d-inline">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-box-arrow-right me-1"></i>Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="dashboard-header-content">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="welcome-title">Welcome <?= htmlspecialchars($client_display) ?>!</h1>
                        <p class="welcome-subtitle">Manage your rental units and stay updated with your property status</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <div class="d-flex align-items-center justify-content-md-end">
                            <i class="bi bi-speedometer2 fs-1 me-3"></i>
                            <div>
                                <div class="fw-bold fs-5">Dashboard</div>
                                <small class="opacity-75">Your Control Center</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Alerts -->
        <?php if ($photo_upload_success): ?>
            <div class="alert alert-success fade-in">
                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($photo_upload_success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($photo_upload_error): ?>
            <div class="alert alert-danger fade-in">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($photo_upload_error) ?>
            </div>
        <?php endif; ?>

        <?php if ($feedback_success): ?>
            <div class="alert alert-success fade-in">
                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($feedback_success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($feedback_error): ?>
            <div class="alert alert-danger fade-in">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($feedback_error) ?>
            </div>
        <?php endif; ?>

        <!-- Feedback Section -->
        <?php if (!empty($feedback_prompts)): ?>
            <div class="alert alert-warning fade-in">
                <i class="bi bi-chat-heart me-2"></i>
                We value your experience! Please provide feedback for your recently ended rental(s).
            </div>
            
            <?php foreach ($feedback_prompts as $prompt): ?>
                <div class="card feedback-card mb-3 fade-in">
                    <div class="card-header">
                        <i class="bi bi-star me-2"></i>
                        Feedback for <?= htmlspecialchars($prompt['SpaceName'] ?? 'Unit') ?>
                        <small class="text-muted ms-2">(Invoice Date: <?= htmlspecialchars($prompt['InvoiceDate'] ?? 'N/A') ?>)</small>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="invoice_id" value="<?= intval($prompt['Invoice_ID']) ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-star-fill me-1"></i>Rating
                                    </label>
                                    <select name="rating" class="form-select rating-select" required>
                                        <option value="">Select Rating</option>
                                        <option value="5">★★★★★ Excellent (5)</option>
                                        <option value="4">★★★★☆ Very Good (4)</option>
                                        <option value="3">★★★☆☆ Good (3)</option>
                                        <option value="2">★★☆☆☆ Fair (2)</option>
                                        <option value="1">★☆☆☆☆ Poor (1)</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="bi bi-chat-text me-1"></i>Comments
                                    </label>
                                    <textarea name="comments" class="form-control" rows="3" placeholder="Share your experience..."></textarea>
                                </div>
                            </div>
                            <button class="btn btn-primary" type="submit" name="submit_feedback" value="1">
                                <i class="bi bi-send me-1"></i>Submit Feedback
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Rented Units Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">
                <i class="bi bi-buildings me-2"></i>Your Rental Portfolio
            </h2>
            <div class="badge bg-primary fs-6">
                <?= count($rented_units) ?> Active Unit<?= count($rented_units) !== 1 ? 's' : '' ?>
            </div>
        </div>

        <?php if (!empty($rented_units)): ?>
            <div class="row g-4">
                <?php foreach ($rented_units as $rent): 
                    $space_id = intval($rent['Space_ID']);
                    $photos = isset($unit_photos[$space_id]) ? $unit_photos[$space_id] : [];
                    
                    // Get dates safely and format them
                    $rental_start = isset($rent['StartDate']) ? formatDateToMonthLetters($rent['StartDate']) : 'N/A';
                    $rental_end = isset($rent['EndDate']) ? formatDateToMonthLetters($rent['EndDate']) : 'N/A';
                    $due_date = formatDateToMonthLetters($rental_end);

                    // Use InvoiceDate from the latest invoice with Flow_Status 'new'
                    if (method_exists($db, 'getLatestNewInvoiceForUnit')) {
                        try {
                            $latest_invoice = $db->getLatestNewInvoiceForUnit($client_id, $space_id);
                            if ($latest_invoice && is_array($latest_invoice) && isset($latest_invoice['Flow_Status']) && strtolower($latest_invoice['Flow_Status']) === 'new') {
                                $rental_start = isset($latest_invoice['InvoiceDate']) ? formatDateToMonthLetters($latest_invoice['InvoiceDate']) : $rental_start;
                                $rental_end = isset($latest_invoice['EndDate']) ? formatDateToMonthLetters($latest_invoice['EndDate']) : $rental_end;
                                $due_date = formatDateToMonthLetters($rental_end);
                            }
                        } catch (Exception $e) {
                            // Use default values if method fails
                            error_log("Failed to get latest invoice: " . $e->getMessage());
                        }
                    }
                ?>
                    <div class="col-12 col-lg-6 col-xl-4">
                        <div class="card unit-card fade-in h-100">
                            <div class="card-body d-flex flex-column">
                                <!-- Unit Icon -->
                                <div class="unit-icon">
                                    <i class="bi bi-house-door"></i>
                                </div>

                                <!-- Unit Info -->
                                <div class="text-center mb-3">
                                    <h5 class="unit-title"><?= htmlspecialchars($rent['Name'] ?? 'Unit') ?></h5>
                                    <div class="unit-price">₱<?= number_format(floatval($rent['Price'] ?? 0), 0) ?>/month</div>
                                    <span class="unit-badge"><?= htmlspecialchars($rent['SpaceTypeName'] ?? 'Space') ?></span>
                                </div>

                                <!-- Location & Billing Information -->
                                <div class="mb-3">
                                    <div class="unit-details">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        <?= htmlspecialchars($rent['Street'] ?? '') ?>, <?= htmlspecialchars($rent['Brgy'] ?? '') ?>, <?= htmlspecialchars($rent['City'] ?? '') ?>
                                    </div>
                                    <div class="unit-details">
                                        <i class="bi bi-calendar-range me-1"></i>
                                        <strong>Period:</strong> <?= $rental_start ?> to <?= $rental_end ?>
                                    </div>
                                    <div class="unit-details">
                                        <i class="bi bi-calendar-check me-1"></i>
                                        <strong>Due:</strong> <?= $due_date ?>
                                    </div>
                                    <?php if (isset($latest_invoice['Status']) && strtolower($latest_invoice['Status']) === 'paid'): ?>
                                        <div class="unit-details text-success">
                                            <i class="bi bi-check-circle me-1"></i>
                                            <strong>Status:</strong> Paid
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Photo Gallery -->
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">
                                            <i class="bi bi-images me-1"></i>Unit Photos
                                        </h6>
                                        <small class="text-muted"><?= count($photos) ?>/6</small>
                                    </div>

                                    <?php if (!empty($photos)): ?>
                                        <div class="photo-gallery">
                                            <?php foreach ($photos as $photo): ?>
                                                <div class="photo-item">
                                                    <img src="uploads/unit_photos/<?= htmlspecialchars($photo) ?>" 
                                                         alt="Unit Photo"
                                                         onclick="showImageModal('uploads/unit_photos/<?= htmlspecialchars($photo) ?>')">
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="space_id" value="<?= $space_id ?>">
                                                        <input type="hidden" name="photo_filename" value="<?= htmlspecialchars($photo) ?>">
                                                        <button type="submit" 
                                                                name="delete_unit_photo" 
                                                                class="delete-photo-btn"
                                                                onclick="return confirmDeletePhoto(event, '<?= htmlspecialchars($photo) ?>');"
                                                                title="Delete photo">
                                                            <i class="bi bi-x"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-photos">
                                            <i class="bi bi-camera mb-2 d-block fs-3"></i>
                                            No photos uploaded yet
                                        </div>
                                    <?php endif; ?>

                                    <!-- Upload Form -->
                                    <?php if (count($photos) < 6): ?>
                                        <div class="upload-form">
                                            <form method="post" enctype="multipart/form-data" id="uploadForm_<?= $space_id ?>">
                                                <input type="hidden" name="space_id" value="<?= $space_id ?>">
                                                <div class="mb-2">
                                                    <input type="file" 
                                                           name="unit_photo" 
                                                           accept="image/jpeg,image/jpg,image/png,image/gif" 
                                                           class="form-control" 
                                                           id="fileInput_<?= $space_id ?>"
                                                           required>
                                                    <small class="text-muted">Max 2MB. JPG, PNG, GIF only. Max dimensions 2048x2048px.</small>
                                                </div>
                                                <button type="submit" name="upload_unit_photo" class="btn btn-success btn-sm w-100" id="uploadBtn_<?= $space_id ?>">
                                                    <i class="bi bi-cloud-upload me-1"></i>Upload Photo
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Maintenance History -->
                                <div class="maintenance-section mt-auto">
                                    <div class="maintenance-header">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-tools"></i>
                                            <h6 class="mb-0 ms-2">Maintenance History</h6>
                                        </div>
                                        <?php if (isset($maintenance_history[$space_id]) && count($maintenance_history[$space_id]) > 3): ?>
                                            <button type="button" class="toggle-maintenance" onclick="toggleMaintenanceHistory(<?= $space_id ?>)">
                                                <i class="bi bi-chevron-down" id="maintenance-icon-<?= $space_id ?>"></i>
                                                View All
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if (isset($maintenance_history[$space_id]) && !empty($maintenance_history[$space_id])): ?>
                                        <ul class="maintenance-list" id="maintenance-preview-<?= $space_id ?>">
                                            <?php foreach (array_slice($maintenance_history[$space_id], 0, 3) as $mh): ?>
                                                <li class="maintenance-item">
                                                    <div>
                                                        <div class="maintenance-date"><?= htmlspecialchars($mh['RequestDate'] ?? 'N/A') ?></div>
                                                        <small class="text-muted">Maintenance Request</small>
                                                    </div>
                                                    <span class="maintenance-status <?= strtolower($mh['Status'] ?? 'pending') ?>">
                                                        <?= htmlspecialchars($mh['Status'] ?? 'Pending') ?>
                                                    </span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                        
                                        <!-- Full Maintenance History (Hidden by default) -->
                                        <div class="maintenance-dropdown d-none" id="maintenance-full-<?= $space_id ?>">
                                            <ul class="maintenance-list">
                                                <?php foreach ($maintenance_history[$space_id] as $mh): ?>
                                                    <li class="maintenance-item">
                                                        <div>
                                                            <div class="maintenance-date"><?= htmlspecialchars($mh['RequestDate'] ?? 'N/A') ?></div>
                                                            <small class="text-muted">Maintenance Request</small>
                                                        </div>
                                                        <span class="maintenance-status <?= strtolower($mh['Status'] ?? 'pending') ?>">
                                                            <?= htmlspecialchars($mh['Status'] ?? 'Pending') ?>
                                                        </span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                        
                                        <?php if (count($maintenance_history[$space_id]) > 3): ?>
                                            <small class="text-muted">
                                                <i class="bi bi-three-dots me-1"></i>
                                                +<?= count($maintenance_history[$space_id]) - 3 ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="no-maintenance">
                                            <i class="bi bi-check-circle text-success me-1"></i>
                                            No maintenance requests yet
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="bi bi-house-x"></i>
                <h5>No Active Rentals</h5>
                <p>You currently have no active rental units. <a href="tel:9451357685">Contact the Admin</a> to explore available properties.</p>
            </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="row mt-5">
            <div class="col-md-4 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-primary mb-3">
                            <i class="bi bi-credit-card-2-back fs-1"></i>
                        </div>
                        <h5>Payment Center</h5>
                        <p class="text-muted">View invoices and payment history</p>
                        <a href="invoice_history.php" class="btn btn-primary">
                            <i class="bi bi-arrow-right me-1"></i>Go to Payment
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-success mb-3">
                            <i class="bi bi-tools fs-1"></i>
                        </div>
                        <h5>Handyman Services</h5>
                        <p class="text-muted">Request maintenance and repairs</p>
                        <a href="handyman_type.php" class="btn btn-success">
                            <i class="bi bi-arrow-right me-1"></i>Request Service
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="text-warning mb-3">
                            <i class="bi bi-gear fs-1"></i>
                        </div>
                        <h5>Maintenance</h5>
                        <p class="text-muted">Track your maintenance requests</p>
                        <a href="maintenance.php" class="btn btn-warning text-white">
                            <i class="bi bi-arrow-right me-1"></i>View Status
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius: var(--border-radius); border: none; box-shadow: var(--shadow-xl);">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">
                        <i class="bi bi-image me-2"></i>Unit Photo
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img id="modalImage" src="" alt="Unit photo" class="img-fluid" style="max-height: 70vh;">
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Image modal functionality
        function showImageModal(imageSrc) {
            const modalImage = document.getElementById('modalImage');
            modalImage.src = imageSrc;
            const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
            imageModal.show();
        }

        // Enhanced photo delete confirmation
        function confirmDeletePhoto(event, filename) {
            event.preventDefault();
            Swal.fire({
                title: 'Delete Photo?',
                text: `Are you sure you want to delete this photo?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit the form
                    event.target.closest('form').submit();
                }
            });
            return false;
        }

        // Toggle maintenance history dropdown
        function toggleMaintenanceHistory(spaceId) {
            const preview = document.getElementById(`maintenance-preview-${spaceId}`);
            const full = document.getElementById(`maintenance-full-${spaceId}`);
            const icon = document.getElementById(`maintenance-icon-${spaceId}`);
            
            if (full.classList.contains('d-none')) {
                // Show full history
                preview.classList.add('d-none');
                full.classList.remove('d-none');
                icon.classList.remove('bi-chevron-down');
                icon.classList.add('bi-chevron-up');
            } else {
                // Show preview only
                preview.classList.remove('d-none');
                full.classList.add('d-none');
                icon.classList.remove('bi-chevron-up');
                icon.classList.add('bi-chevron-down');
            }
        }

        // Payment Status Notification System - FIXED AJAX PATH
        let shownPaymentNotifications = new Set();
        let shownReminderNotifications = new Set();

       function checkPaymentStatusAndNotify() {
    <?php if (!empty($rented_units) && isset($client_id)): ?>
    console.log('🔔 Checking payment status for client:', <?= json_encode($client_id) ?>);
    
    // USE ../AJAX/ like the working get_unread_admin_chat_counts.php
    fetch(`../AJAX/check_payment_status.php?client_id=<?= $client_id ?>&t=${Date.now()}`)
    .then(res => {
        console.log('📡 Response status:', res.status);
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
    })
    .then(data => {
        console.log('📊 Payment status data:', data);
        
        if (data.success) {
            console.log('✅ Found paid invoices:', data.paid_invoices?.length || 0);
            console.log('📅 Found upcoming payments:', data.upcoming_payments?.length || 0);
            
            // Show payment confirmation notifications
            if (data.paid_invoices && data.paid_invoices.length > 0) {
                data.paid_invoices.forEach(invoice => {
                    const notificationKey = `paid_${invoice.Invoice_ID}`;
                    
                    if (!shownPaymentNotifications.has(notificationKey)) {
                        console.log('💰 Showing payment confirmation for invoice:', invoice.Invoice_ID);
                        showPaymentConfirmation(invoice);
                        shownPaymentNotifications.add(notificationKey);
                        
                        // Remove from set after 1 hour to allow re-notification if needed
                        setTimeout(() => {
                            shownPaymentNotifications.delete(notificationKey);
                        }, 3600000);
                    }
                });
            }

            // Show payment reminder notifications
            if (data.upcoming_payments && data.upcoming_payments.length > 0) {
                data.upcoming_payments.forEach(invoice => {
                    const reminderKey = `reminder_${invoice.Invoice_ID}`;
                    
                    if (!shownReminderNotifications.has(reminderKey)) {
                        console.log('⏰ Showing payment reminder for invoice:', invoice.Invoice_ID);
                        showPaymentReminder(invoice);
                        shownReminderNotifications.add(reminderKey);
                    }
                });
            }
            
            if (data.paid_invoices?.length === 0 && data.upcoming_payments?.length === 0) {
                console.log('ℹ️ No payment notifications to show');
            }
        } else {
            console.error('❌ Payment status check failed:', data.error);
        }
    })
    .catch(err => {
        console.error('❌ Payment status check failed:', err);
    });
    <?php else: ?>
    console.log('ℹ️ No rented units, skipping payment check');
    <?php endif; ?>
}

        function showPaymentConfirmation(invoice) {
            const nextDueDate = invoice.NextDueDate ? new Date(invoice.NextDueDate).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }) : 'Next month';
            
            Swal.fire({
                icon: 'success',
                title: '💰 Payment Confirmed!',
                html: `
                    <div class="text-start">
                        <p class="mb-2"><strong>Unit:</strong> ${invoice.UnitName || 'Your unit'}</p>
                        <p class="mb-2"><strong>Period:</strong> Paid in full</p>
                        <p class="mb-0"><strong>Next Payment Due:</strong> ${nextDueDate}</p>
                    </div>
                `,
                confirmButtonText: 'View Payment History',
                confirmButtonColor: '#10b981',
                showCancelButton: true,
                cancelButtonText: 'Close',
                background: 'linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%)',
                color: '#065f46',
                timer: 8000,
                timerProgressBar: true
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'invoice_history.php';
                }
            });
        }

        function showPaymentReminder(invoice) {
            const dueDate = new Date(invoice.EndDate).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            const daysLeft = invoice.DaysUntilDue;
            let urgencyLevel = 'warning';
            let urgencyText = 'Upcoming Payment';
            
            if (daysLeft <= 1) {
                urgencyLevel = 'error';
                urgencyText = 'Payment Due Tomorrow!';
            } else if (daysLeft <= 3) {
                urgencyLevel = 'warning';
                urgencyText = 'Payment Due Soon';
            }
            
            Swal.fire({
                icon: urgencyLevel,
                title: `📅 ${urgencyText}`,
                html: `
                    <div class="text-start">
                        <p class="mb-2"><strong>Unit:</strong> ${invoice.UnitName || 'Your unit'}</p>
                        <p class="mb-2"><strong>Amount Due:</strong> ₱${parseFloat(invoice.InvoiceTotal || 0).toLocaleString()}</p>
                        <p class="mb-2"><strong>Due Date:</strong> ${dueDate}</p>
                        <p class="mb-0"><strong>Time Left:</strong> ${daysLeft} day${daysLeft !== 1 ? 's' : ''}</p>
                    </div>
                `,
                confirmButtonText: 'Pay Now',
                confirmButtonColor: urgencyLevel === 'error' ? '#ef4444' : '#f59e0b',
                showCancelButton: true,
                cancelButtonText: 'Remind Me Later',
                background: urgencyLevel === 'error' ? 
                    'linear-gradient(135deg, #fee2e2 0%, #fecaca 100%)' : 
                    'linear-gradient(135deg, #fef3c7 0%, #fde68a 100%)',
                color: urgencyLevel === 'error' ? '#991b1b' : '#92400e',
                timer: urgencyLevel === 'error' ? 10000 : 6000,
                timerProgressBar: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'invoice_history.php';
                }
            });
        }

        // Test function to manually trigger notifications
        function testPaymentNotifications() {
            console.log('🧪 Testing payment notifications...');
            
            // Test payment confirmation
            const testPaidInvoice = {
                Invoice_ID: 999,
                UnitName: 'Test Unit',
                NextDueDate: '2025-01-15'
            };
            showPaymentConfirmation(testPaidInvoice);
            
            // Test payment reminder
            const testReminderInvoice = {
                Invoice_ID: 998,
                UnitName: 'Test Unit',
                InvoiceTotal: 11000,
                EndDate: '2024-12-20',
                DaysUntilDue: 2
            };
            setTimeout(() => showPaymentReminder(testReminderInvoice), 2000);
        }

        // Close navbar on mobile when clicking nav links
        document.addEventListener('DOMContentLoaded', function() {
            const navbarCollapse = document.getElementById('navbarNav');
            if (navbarCollapse) {
                navbarCollapse.addEventListener('click', function(e) {
                    let target = e.target;
                    while (target && target !== navbarCollapse) {
                        if (target.classList && (target.classList.contains('nav-link') || target.type === 'submit')) {
                            if (window.innerWidth < 992) {
                                const bsCollapse = bootstrap.Collapse.getOrCreateInstance(navbarCollapse);
                                bsCollapse.hide();
                            }
                            break;
                        }
                        target = target.parentElement;
                    }
                });
            }

            // Enhanced file input with preview and validation
            const fileInputs = document.querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const file = this.files[0];
                    const spaceId = this.id.replace('fileInput_', '');
                    
                    if (file) {
                        // Validate file size
                        if (file.size > 2 * 1024 * 1024) { // 2MB
                            Swal.fire({
                                icon: 'error',
                                title: 'File Too Large',
                                text: 'Please select an image smaller than 2MB.',
                                confirmButtonColor: '#2563eb'
                            });
                            this.value = '';
                            return;
                        }
                        
                        // Validate file type
                        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        if (!allowedTypes.includes(file.type)) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Invalid File Type',
                                text: 'Please select a JPG, PNG, or GIF image.',
                                confirmButtonColor: '#2563eb'
                            });
                            this.value = '';
                            return;
                        }

                        // Create image preview
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                // Remove existing preview
                                const existingPreview = input.parentNode.querySelector('.image-preview');
                                if (existingPreview) {
                                    existingPreview.remove();
                                }
                                
                                // Create preview
                                const preview = document.createElement('img');
                                preview.src = e.target.result;
                                preview.className = 'image-preview';
                                preview.style.maxWidth = '100px';
                                preview.style.maxHeight = '100px';
                                preview.style.objectFit = 'cover';
                                preview.style.borderRadius = '8px';
                                preview.style.marginTop = '10px';
                                preview.style.boxShadow = '0 2px 4px rgba(0, 0, 0, 0.1)';
                                
                                input.parentNode.appendChild(preview);
                            };
                            reader.readAsDataURL(file);
                        }
                    }
                });
            });

            // Form submission loading state
            const uploadForms = document.querySelectorAll('form[id^="uploadForm_"]');
            uploadForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    
                    // Validate file selection
                    const fileInput = this.querySelector('input[type="file"]');
                    if (!fileInput.files[0]) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'No File Selected',
                            text: 'Please select an image to upload.',
                            confirmButtonColor: '#2563eb'
                        });
                        return;
                    }
                    
                    // Show loading state
                    submitBtn.innerHTML = '<span class="spinner me-1"></span>Uploading...';
                    submitBtn.disabled = true;
                    
                    // Reset button after 30 seconds (timeout)
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 30000);
                });
            });

            // Feedback form loading state
            const feedbackForms = document.querySelectorAll('form:has(button[name="submit_feedback"])');
            feedbackForms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const rating = this.querySelector('select[name="rating"]').value;
                    if (!rating) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'warning',
                            title: 'Rating Required',
                            text: 'Please select a rating before submitting.',
                            confirmButtonColor: '#2563eb'
                        });
                        return;
                    }
                    
                    const submitBtn = this.querySelector('button[name="submit_feedback"]');
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="spinner me-1"></span>Submitting...';
                    submitBtn.disabled = true;
                    
                    // Reset button after 10 seconds (timeout)
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 10000);
                });
            });

            // Animate cards on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fade-in');
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.card').forEach((card) => {
                observer.observe(card);
            });

            // Start payment status polling
            checkPaymentStatusAndNotify();
            setInterval(checkPaymentStatusAndNotify, 30000); // Check every 30 seconds
            
            // Make test function available globally
            window.testPaymentNotifications = testPaymentNotifications;
        });


        
        // Live poll unread admin messages for client (Payment nav badge)
        function pollClientUnreadAdminBadge() {
            // Only run if client is logged in
            <?php if (isset($_SESSION['client_id'])): ?>
            fetch('../AJAX/get_unread_admin_chat_counts.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'client_id=' + encodeURIComponent(<?= json_encode($_SESSION['client_id']) ?>)
            })
            .then(res => res.json())
            .then(counts => {
                // Sum all unread admin messages across all invoices
                let total = 0;
                Object.values(counts).forEach(cnt => { total += cnt; });
                const badge = document.getElementById('client-unread-admin-badge');
                if (badge) {
                    if (total > 0) {
                        badge.textContent = total;
                        badge.classList.remove('d-none');
                    } else {
                        badge.textContent = '';
                        badge.classList.add('d-none');
                    }
                }
            });
            <?php endif; ?>
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            pollClientUnreadAdminBadge();
            setInterval(pollClientUnreadAdminBadge, 5000);
        });
    </script>

    <?php if ($show_login_success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Welcome!',
            text: 'Login successful. Good to see you!',
            confirmButtonColor: '#2563eb',
            timer: 3000,
            timerProgressBar: true
        });


        
    </script>
    <?php endif; ?>
</body>
</html>
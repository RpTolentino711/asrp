<?php
session_start();
require_once '../database/database.php';

// Turn on error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$db = new Database();

// --- NOTIFICATION SYSTEM VARIABLES ---
$unseen_rentals_sql = "SELECT COUNT(*) as count FROM rentalrequest WHERE Status = 'Pending' AND admin_seen = 0 AND Flow_Status = 'new'";
$unseen_rentals_result = $db->getRow($unseen_rentals_sql);
$unseen_rentals = $unseen_rentals_result['count'] ?? 0;

$new_maintenance_sql = "SELECT COUNT(*) as count FROM maintenancerequest WHERE Status = 'Submitted' AND admin_seen = 0";
$new_maintenance_result = $db->getRow($new_maintenance_sql);
$new_maintenance_requests = $new_maintenance_result['count'] ?? 0;

$unread_messages_sql = "SELECT COUNT(*) as count FROM invoice_chat WHERE Sender_Type = 'client' AND is_read_admin = 0";
$unread_messages_result = $db->getRow($unread_messages_sql);
$unread_client_messages = $unread_messages_result['count'] ?? 0;

// Get counts for sidebar badges
$rental_count = $db->getRow("SELECT COUNT(*) as count FROM rentalrequest WHERE Status = 'Pending' AND Flow_Status = 'new'")['count'];
$maintenance_count = $db->getRow("SELECT COUNT(*) as count FROM maintenancerequest WHERE Status = 'Submitted'")['count'];
$chat_count = $db->getRow("SELECT COUNT(*) as count FROM invoice_chat WHERE Sender_Type = 'client' AND is_read_admin = 0")['count'];

// MARK ALL MAINTENANCE REQUESTS AS SEEN WHEN ADMIN VIEWS THE PAGE
$db->executeStatement(
    "UPDATE maintenancerequest SET admin_seen = 1 WHERE Status = 'Submitted' AND admin_seen = 0"
);

// --- Admin auth check ---
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit();
}
$ua_id = $_SESSION['admin_id'] ?? null;

// Photo upload configuration
$max_photos_per_unit = 12;

$success_unit = '';
$error_unit = '';
$success_type = '';
$error_type = '';
$success_amenity = '';
$error_amenity = '';

// --- Handle Amenity Category Addition ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'add_amenity_category') {
    $category_name = trim($_POST['category_name'] ?? '');
    $category_icon = trim($_POST['category_icon'] ?? 'fas fa-star');
    $category_order = intval($_POST['category_order'] ?? 0);

    if (empty($category_name)) {
        $error_amenity = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                      <i class="fas fa-exclamation-circle me-2"></i>
                      Please enter a category name.
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } else {
        if ($db->addNewAmenityCategory($category_name, $category_icon, $category_order)) {
            $success_amenity = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            Amenity category added successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
        } else {
            $error_amenity = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                          <i class="fas fa-exclamation-circle me-2"></i>
                          Failed to add amenity category.
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
        }
    }
}

// --- Handle Amenity Addition ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'add_amenity') {
    $amenity_category = intval($_POST['amenity_category'] ?? 0);
    $amenity_name = trim($_POST['amenity_name'] ?? '');
    $amenity_icon = trim($_POST['amenity_icon'] ?? 'fas fa-check');
    $amenity_description = trim($_POST['amenity_description'] ?? '');
    $amenity_order = intval($_POST['amenity_order'] ?? 0);

    if (empty($amenity_name) || empty($amenity_category)) {
        $error_amenity = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                      <i class="fas fa-exclamation-circle me-2"></i>
                      Please fill in all required fields.
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } else {
        if ($db->addNewAmenity($amenity_category, $amenity_name, $amenity_icon, $amenity_description, $amenity_order)) {
            $success_amenity = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            Amenity added successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
        } else {
            $error_amenity = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                          <i class="fas fa-exclamation-circle me-2"></i>
                          Failed to add amenity.
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
        }
    }
}

// --- Handle Space Amenities Management ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type'])) {
    if ($_POST['form_type'] === 'add_space_amenity') {
        $space_id = intval($_POST['space_id'] ?? 0);
        $amenity_id = intval($_POST['amenity_id'] ?? 0);
        
        if ($space_id && $amenity_id) {
            if ($db->addAmenityToSpace($space_id, $amenity_id)) {
                $success_unit = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                Amenity added to space successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
            } else {
                $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                              <i class="fas fa-exclamation-circle me-2"></i>
                              Amenity is already added to this space.
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
            }
        }
    } elseif ($_POST['form_type'] === 'remove_space_amenity') {
        $space_id = intval($_POST['space_id'] ?? 0);
        $amenity_id = intval($_POST['amenity_id'] ?? 0);
        
        if ($space_id && $amenity_id) {
            if ($db->removeAmenityFromSpace($space_id, $amenity_id)) {
                $success_unit = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                Amenity removed from space successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
            } else {
                $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                              <i class="fas fa-exclamation-circle me-2"></i>
                              Failed to remove amenity from space.
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
            }
        }
    }
}

// --- Handle photo description update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'update_description') {
    $history_id = intval($_POST['history_id'] ?? 0);
    $description = trim($_POST['photo_description'] ?? '');
    
    // Validate description length (1000 characters max)
    if (strlen($description) > 1000) {
        $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                      <i class="fas fa-exclamation-circle me-2"></i>
                      Description too long. Maximum 1000 characters allowed.
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } elseif ($history_id >= 1) {
        if ($db->updatePhotoDescription($history_id, $description)) {
            $success_unit = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            Photo description updated successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
        } else {
            $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                          <i class="fas fa-exclamation-circle me-2"></i>
                          Failed to update photo description.
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
        }
    }
}

// --- Handle photo delete with history tracking ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'delete_photo') {
    $space_id = intval($_POST['space_id'] ?? 0);
    $photo_path = trim($_POST['photo_path'] ?? '');
    
    if ($space_id >= 1 && !empty($photo_path)) {
        // Mark photo as inactive in history
        if ($db->deactivatePhoto($space_id, $photo_path)) {
            $success_unit = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            Photo deleted successfully! (Marked as inactive in history)
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>';
        } else {
            $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                          <i class="fas fa-exclamation-circle me-2"></i>
                          Failed to delete photo.
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
        }
    }
}

// --- Handle photo upload with history tracking ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'upload_photo') {
    $space_id = intval($_POST['space_id'] ?? 0);
    $photo_description = trim($_POST['photo_description'] ?? ''); // Get description from form
    
    // Validate description length
    if (strlen($photo_description) > 1000) {
        $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                      <i class="fas fa-exclamation-circle me-2"></i>
                      Description too long. Maximum 1000 characters allowed.
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } elseif ($space_id && isset($_FILES['new_photo']) && $_FILES['new_photo']['error'] == UPLOAD_ERR_OK) {
        // Get current active photos to check limit
        $current_photos = $db->getCurrentSpacePhotos($space_id);
        
        // Check limit
        if (count($current_photos) >= $max_photos_per_unit) {
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
                    // Log the upload in history with description
                    if ($db->addPhotoToHistory($space_id, $filename, 'uploaded', null, $ua_id, $photo_description)) {
                        $success_unit = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                                        <i class="fas fa-check-circle me-2"></i>
                                        Photo uploaded successfully! (Added to history)
                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                        </div>';
                    } else {
                        $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                                      <i class="fas fa-exclamation-circle me-2"></i>
                                      Failed to record photo in history.
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

// --- Handle photo update with history tracking ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'update_photo') {
    $space_id = intval($_POST['space_id'] ?? 0);
    $old_photo_path = trim($_POST['old_photo_path'] ?? '');
    $photo_description = trim($_POST['photo_description'] ?? ''); // Get description from form
    
    // Validate description length
    if (strlen($photo_description) > 1000) {
        $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                      <i class="fas fa-exclamation-circle me-2"></i>
                      Description too long. Maximum 1000 characters allowed.
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } elseif ($space_id && !empty($old_photo_path) && isset($_FILES['new_photo']) && $_FILES['new_photo']['error'] == UPLOAD_ERR_OK) {
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
            $new_filename = "adminunit_" . time() . "_" . rand(1000,9999) . "." . $ext;
            $upload_dir = __DIR__ . "/../uploads/unit_photos/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $filepath = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Mark old photo as inactive
                $db->deactivatePhoto($space_id, $old_photo_path);
                
                // Add new photo as update with description
                if ($db->addPhotoToHistory($space_id, $new_filename, 'updated', $old_photo_path, $ua_id, $photo_description)) {
                    $success_unit = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Photo updated successfully! (Old photo marked inactive, new photo added)
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                } else {
                    $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                                  <i class="fas fa-exclamation-circle me-2"></i>
                                  Failed to record photo update in history.
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

// --- Handle form submission for new space/unit ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'unit') {
    $name = trim($_POST['name'] ?? '');
    $spacetype_id = intval($_POST['spacetype_id'] ?? 0);
    $price = isset($_POST['price']) && is_numeric($_POST['price']) ? floatval($_POST['price']) : null;

    // Handle file upload first to get filename
    $upload_dir = __DIR__ . "/../uploads/unit_photos/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $uploaded_photo_filename = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($file['type'], $allowed_types) && $file['size'] <= 2*1024*1024) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $uploaded_photo_filename = "adminunit_" . time() . "_" . rand(1000,9999) . "." . $ext;
            $filepath = $upload_dir . $uploaded_photo_filename;
            
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                              <i class="fas fa-exclamation-circle me-2"></i>
                              Failed to upload photo.
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
                $uploaded_photo_filename = null;
            }
        } else {
            if (!in_array($file['type'], $allowed_types)) {
                $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                              <i class="fas fa-exclamation-circle me-2"></i>
                              Invalid file type for photo.
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
            } else {
                $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                              <i class="fas fa-exclamation-circle me-2"></i>
                              Photo is too large (max 2MB).
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
            }
        }
    }

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
        // Add space without photo column
        if ($db->addNewSpace($name, $spacetype_id, $ua_id, $price)) {
            // Get the newly created space by name to get its ID
            // Get all spaces and find the newly created one
            $all_spaces = $db->getAllSpacesWithDetails();
            $new_space = null;
            foreach ($all_spaces as $space) {
                if ($space['Name'] === $name) {
                    $new_space = $space;
                    break;
                }
            }
            
            if ($new_space && $uploaded_photo_filename) {
                // Add the photo to photo_history
                $db->addPhotoToHistory($new_space['Space_ID'], $uploaded_photo_filename, 'uploaded', null, $ua_id);
            }
            
            $success_unit = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            Space/unit added successfully!' . ($uploaded_photo_filename ? ' (Photo added to history)' : '') . '
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

// --- Handle space type update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'update_type') {
    $type_id = intval($_POST['type_id'] ?? 0);
    $new_name = trim($_POST['new_type_name'] ?? '');

    if (empty($new_name)) {
        $error_type = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                      <i class="fas fa-exclamation-circle me-2"></i>
                      Please enter a space type name.
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } else {
        // Check if the new name already exists (excluding current type)
        $existing_types = $db->getAllSpaceTypes();
        $existing = array_filter($existing_types, function($type) use ($new_name, $type_id) {
            return strtolower(trim($type['SpaceTypeName'])) === strtolower(trim($new_name)) && $type['SpaceType_ID'] != $type_id;
        });
        
        if ($existing) {
            $error_type = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                          <i class="fas fa-exclamation-circle me-2"></i>
                          This space type name already exists.
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
        } else {
            if ($db->updateSpaceType($type_id, $new_name)) {
                $success_type = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                Space type updated successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
            } else {
                $error_type = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                              <i class="fas fa-exclamation-circle me-2"></i>
                              Failed to update space type.
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
            }
        }
    }
}

// --- Handle space type deletion ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'delete_type') {
    $type_id = intval($_POST['type_id'] ?? 0);
    
    if ($db->deleteSpaceType($type_id)) {
        $success_type = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        Space type deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
    } else {
        $error_type = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                      <i class="fas fa-exclamation-circle me-2"></i>
                      Cannot delete space type. It may be in use by existing spaces.
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    }
}

// --- Handle space update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'update_space') {
    $space_id = intval($_POST['space_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $spacetype_id = intval($_POST['spacetype_id'] ?? 0);
    $price = isset($_POST['price']) && is_numeric($_POST['price']) ? floatval($_POST['price']) : null;

    if (empty($name) || empty($spacetype_id) || $price === null) {
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
    } else {
        // Check if name already exists (excluding current space)
        $existing_spaces = $db->getAllSpacesWithDetails();
        $existing = array_filter($existing_spaces, function($space) use ($name, $space_id) {
            return strtolower(trim($space['Name'])) === strtolower(trim($name)) && $space['Space_ID'] != $space_id;
        });
        
        if ($existing) {
            $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                          <i class="fas fa-exclamation-circle me-2"></i>
                          A space/unit with this name already exists.
                          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                          </div>';
        } else {
            if ($db->updateSpace($space_id, $name, $spacetype_id, $price)) {
                $success_unit = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                Space/unit updated successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>';
            } else {
                $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                              <i class="fas fa-exclamation-circle me-2"></i>
                              Failed to update space/unit.
                              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                              </div>';
            }
        }
    }
}

// --- Fetch Data for Display ---
$spacetypes = $db->getAllSpaceTypes();
$spaces = $db->getAllSpacesWithDetails();

// Fetch amenities data
$amenities_categories = $db->getAllAmenitiesCategories();
$all_amenities = $db->getAllAmenities();

// Get space amenities for each space
$space_amenities_data = [];
foreach ($spaces as $space) {
    $space_amenities_data[$space['Space_ID']] = $db->getSpaceAmenitiesGrouped($space['Space_ID']);
}

// Get current active photos for each space
$space_photos = [];
foreach ($spaces as $space) {
    $space_photos[$space['Space_ID']] = $db->getCurrentSpacePhotos($space['Space_ID']);
}

$photo_history = $db->getPhotoHistory();

// Get unique spaces for filter
$unique_spaces = [];
foreach ($photo_history as $history) {
    $space_id = $history['Space_ID'];
    if (!isset($unique_spaces[$space_id])) {
        $unique_spaces[$space_id] = [
            'id' => $space_id,
            'name' => $history['Space_Name'] ?? 'Unit #' . $space_id
        ];
    }
}

// Group history by space for better organization
$space_history = [];
foreach ($photo_history as $history) {
    $space_history[$history['Space_ID']][] = $history;
}
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
            --mobile-header-height: 65px;
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
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 999;
            display: none;
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }

        .mobile-overlay.active {
            display: block;
            animation: fadeInOverlay 0.3s ease-out;
        }

        @keyframes fadeInOverlay {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .mobile-header {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--mobile-header-height);
            background: white;
            border-bottom: 1px solid #e5e7eb;
            z-index: 1001;
            padding: 0 1rem;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .mobile-menu-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            padding: 0.75rem;
            border-radius: 8px;
            transition: var(--transition);
            min-width: 48px;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .mobile-menu-btn:active {
            background: rgba(0,0,0,0.1);
        }

        .mobile-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .mobile-brand i {
            color: var(--primary);
        }
        
        .sidebar {
            position: fixed;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, var(--dark), var(--darker));
            color: white;
            padding: 1.5rem 1rem;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transition: var(--transition);
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
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
            padding: 0.85rem 1rem;
            color: rgba(255, 255, 255, 0.85);
            border-radius: var(--border-radius);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.95rem;
            min-height: 48px;
        }
        
        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(5px);
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
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: var(--transition);
            min-height: 100vh;
        }
        
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
            color: #6b7280;
            margin: 0;
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
        
        .dashboard-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
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
            -webkit-overflow-scrolling: touch;
        }
        
        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 900px;
        }
        
        .custom-table th {
            background-color: #f9fafb;
            padding: 0.75rem 1rem;
            font-weight: 600;
            text-align: left;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.9rem;
            white-space: nowrap;
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.25rem;
            padding: 1.25rem;
            border-left: 4px solid var(--primary);
        }

        .mobile-card-header {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .mobile-card-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            align-items: flex-start;
        }

        .mobile-card-detail .label {
            font-weight: 500;
            color: #6b7280;
            min-width: 80px;
        }

        .mobile-card-detail .value {
            color: var(--dark);
            text-align: right;
            flex: 1;
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

        /* Space Type Actions */
        .space-type-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-edit {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-edit:hover {
            background: #3b82f6;
            color: white;
        }

        .btn-remove {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-remove:hover {
            background: var(--danger);
            color: white;
        }

        /* Space Actions */
        .space-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-edit-space {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(16, 185, 129, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: var(--transition);
        }

        .btn-edit-space:hover {
            background: var(--secondary);
            color: white;
        }
        
        /* Photo Description Styles */
        .photo-description {
            border-top: 1px solid #e5e7eb;
            padding-top: 0.75rem;
        }

        .description-display {
            font-size: 0.8rem;
        }

        .description-text {
            word-wrap: break-word;
            line-height: 1.4;
            max-height: 120px;
            overflow-y: auto;
        }

        .description-timeline {
            border-left: 3px solid var(--primary);
            font-size: 0.85rem;
            max-height: 150px;
            overflow-y: auto;
        }

        .char-counter {
            text-align: right;
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }

        /* Amenities Styles */
        .amenities-section {
            background: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .amenity-category {
            margin-bottom: 2rem;
        }

        .amenity-category-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }

        .amenity-category-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            font-size: 1.1rem;
        }

        .amenity-category-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark);
            margin: 0;
        }

        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }

        .amenity-item {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            padding: 1rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .amenity-item:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .amenity-item.selected {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .amenity-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .amenity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            font-size: 0.9rem;
        }

        .amenity-name {
            font-weight: 500;
            font-size: 0.95rem;
            color: var(--dark);
            margin: 0;
        }

        .amenity-description {
            font-size: 0.8rem;
            color: #6b7280;
            line-height: 1.4;
        }

        .amenity-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-add-amenity {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
            border: 1px solid rgba(16, 185, 129, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: var(--transition);
            width: 100%;
            text-align: center;
        }

        .btn-add-amenity:hover {
            background: var(--secondary);
            color: white;
        }

        .btn-remove-amenity {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: var(--transition);
            width: 100%;
            text-align: center;
        }

        .btn-remove-amenity:hover {
            background: var(--danger);
            color: white;
        }

        .current-amenities {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .current-amenity-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .current-amenity-item:last-child {
            margin-bottom: 0;
        }

        /* Notification Styles */
        .notification-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .bell-shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(-15deg); }
            75% { transform: rotate(15deg); }
        }

        .tools-shake {
            animation: toolsShake 0.5s ease-in-out;
        }

        @keyframes toolsShake {
            0%, 100% { transform: rotate(0deg) scale(1); }
            25% { transform: rotate(-10deg) scale(1.1); }
            50% { transform: rotate(10deg) scale(1.1); }
            75% { transform: rotate(-5deg) scale(1.05); }
        }

        .message-shake {
            animation: messageShake 0.5s ease-in-out;
        }

        @keyframes messageShake {
            0%, 100% { transform: rotate(0deg) scale(1); }
            25% { transform: rotate(-8deg) scale(1.1); }
            50% { transform: rotate(8deg) scale(1.1); }
            75% { transform: rotate(-4deg) scale(1.05); }
        }
        
        /* CRITICAL: Mobile Responsive Breakpoints */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }

            .mobile-header {
                display: flex;
            }
            
            .main-content {
                margin-left: 0;
                margin-top: var(--mobile-header-height);
                padding: 1.25rem;
            }

            /* CRITICAL FIX: Toggle table/mobile display */
            .table-desktop {
                display: none !important;
            }

            .table-mobile {
                display: block !important;
            }

            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .page-title h1 {
                font-size: 1.6rem;
            }

            .card-body {
                padding: 1.25rem;
            }

            .photo-grid {
                flex-direction: column;
            }

            .photo-item {
                min-width: auto;
            }

            .space-type-actions,
            .space-actions {
                flex-direction: column;
            }

            .photo-description {
                font-size: 0.75rem;
            }
            
            .description-timeline {
                font-size: 0.8rem;
            }

            .amenities-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
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

        .animate-slide-up {
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .nav-link,
            .mobile-menu-btn,
            .btn-action,
            .file-input-label {
                min-height: 48px;
                min-width: 48px;
            }
        }

        /* Photo History Styles */
        .history-photo {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .history-photo:hover {
            transform: scale(1.1);
        }

        .history-badge-uploaded {
            background: #10b981;
        }

        .history-badge-updated {
            background: #f59e0b;
        }

        .history-badge-deleted {
            background: #ef4444;
        }

        /* Timeline Styles for Clear History */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2.3rem;
            top: 1.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
            border: 2px solid white;
            box-shadow: 0 0 0 2px var(--primary);
        }

        .timeline-date {
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .timeline-content {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
        }

        .timeline-photos {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .photo-comparison {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 0.5rem;
        }

        .photo-arrow {
            color: #6b7280;
            font-size: 1.5rem;
        }

        /* Filter Styles */
        .filter-section {
            background: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Mobile Header -->
    <div class="mobile-header">
        <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>
        <div class="mobile-brand">
            <i class="fas fa-crown"></i>
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
                    <?php if ($rental_count > 0): ?>
                        <span class="badge badge-notification bg-danger notification-badge" id="sidebarRentalBadge"><?= $rental_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="manage_maintenance.php" class="nav-link">
                    <i class="fas fa-tools"></i>
                    <span>Maintenance</span>
                    <?php if ($maintenance_count > 0): ?>
                        <span class="badge badge-notification bg-warning" id="sidebarMaintenanceBadge"><?= $maintenance_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="generate_invoice.php" class="nav-link">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Invoices</span>
                    <?php if ($chat_count > 0): ?>
                        <span class="badge badge-notification bg-info" id="sidebarInvoicesBadge"><?= $chat_count ?></span>
                    <?php endif; ?>
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
                    <p class="text-muted mb-0">Add and manage spaces, units, amenities, and space types</p>
                </div>
            </div>
        </div>
        
        <?= $success_unit ?>
        <?= $error_unit ?>
        <?= $success_type ?>
        <?= $error_type ?>
        <?= $success_amenity ?>
        <?= $error_amenity ?>
        
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
                            
                            <!-- Photo Upload Field -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">Main Photo (max 2MB, JPG/PNG/GIF):</label>
                                <div class="file-input-container">
                                    <div class="file-input-label">
                                        <i class="fas fa-upload me-1"></i> Choose File
                                    </div>
                                    <input type="file" name="photo" accept="image/*" />
                                </div>
                                <div class="filename-display" id="photoFileName"></div>
                                <small class="text-muted">You can add more photos after creating the unit</small>
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

        <!-- Amenities Management Section -->
        <div class="row">
            <!-- Add New Amenity Category -->
            <div class="col-lg-6">
                <div class="dashboard-card animate-fade-in">
                    <div class="card-header">
                        <i class="fas fa-layer-group"></i>
                        <span>Add New Amenity Category</span>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3" autocomplete="off">
                            <input type="hidden" name="form_type" value="add_amenity_category" />
                            <div class="col-12">
                                <label for="category_name" class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                                <input id="category_name" type="text" class="form-control" name="category_name" placeholder="e.g. Kitchen, Outdoor, Entertainment" required />
                            </div>
                            <div class="col-12">
                                <label for="category_icon" class="form-label fw-semibold">Icon Class <span class="text-danger">*</span></label>
                                <input id="category_icon" type="text" class="form-control" name="category_icon" placeholder="fas fa-utensils" value="fas fa-star" required />
                                <small class="text-muted">Use FontAwesome icon classes (e.g., fas fa-utensils, fas fa-wifi)</small>
                            </div>
                            <div class="col-12">
                                <label for="category_order" class="form-label fw-semibold">Display Order</label>
                                <input id="category_order" type="number" class="form-control" name="category_order" value="0" min="0" />
                            </div>
                            <div class="col-12 text-center mt-4">
                                <button type="submit" class="btn btn-primary px-5">
                                    <i class="fas fa-plus-circle me-1"></i> Add Category
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Add New Amenity -->
            <div class="col-lg-6">
                <div class="dashboard-card animate-fade-in">
                    <div class="card-header">
                        <i class="fas fa-plus-square"></i>
                        <span>Add New Amenity</span>
                    </div>
                    <div class="card-body">
                        <form method="post" class="row g-3" autocomplete="off">
                            <input type="hidden" name="form_type" value="add_amenity" />
                            <div class="col-12">
                                <label for="amenity_category" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select id="amenity_category" name="amenity_category" class="form-select" required>
                                    <option value="" selected disabled>Select Category</option>
                                    <?php foreach ($amenities_categories as $category): ?>
                                        <option value="<?= $category['Category_ID'] ?>"><?= htmlspecialchars($category['Category_Name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="amenity_name" class="form-label fw-semibold">Amenity Name <span class="text-danger">*</span></label>
                                <input id="amenity_name" type="text" class="form-control" name="amenity_name" placeholder="e.g. Swimming Pool, Air Conditioning" required />
                            </div>
                            <div class="col-12">
                                <label for="amenity_icon" class="form-label fw-semibold">Icon Class</label>
                                <input id="amenity_icon" type="text" class="form-control" name="amenity_icon" placeholder="fas fa-swimming-pool" value="fas fa-check" />
                            </div>
                            <div class="col-12">
                                <label for="amenity_description" class="form-label fw-semibold">Description</label>
                                <textarea id="amenity_description" class="form-control" name="amenity_description" rows="3" placeholder="Brief description of this amenity"></textarea>
                            </div>
                            <div class="col-12">
                                <label for="amenity_order" class="form-label fw-semibold">Display Order</label>
                                <input id="amenity_order" type="number" class="form-control" name="amenity_order" value="0" min="0" />
                            </div>
                            <div class="col-12 text-center mt-4">
                                <button type="submit" class="btn btn-primary px-5">
                                    <i class="fas fa-plus-circle me-1"></i> Add Amenity
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Existing Spaces/Units with Amenities -->
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
                                    <th>Amenities</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($spaces as $space): 
                                    $current_photos = $space_photos[$space['Space_ID']] ?? [];
                                    $current_count = count($current_photos);
                                    $can_add_more = $current_count < $max_photos_per_unit;
                                    $photos_remaining = $max_photos_per_unit - $current_count;
                                    $space_amenities = $space_amenities_data[$space['Space_ID']] ?? [];
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
                                                <?php if (!empty($current_photos)): ?>
                                                    <div class="photo-grid">
                                                        <?php foreach ($current_photos as $photo): ?>
                                                            <div class="photo-item">
                                                                <img src="../uploads/unit_photos/<?= htmlspecialchars($photo['Photo_Path']) ?>" class="photo-preview" alt="Space Photo">
                                                                <div class="photo-actions">
                                                                    <!-- Update Photo Form with Description -->
                                                                    <form method="post" enctype="multipart/form-data">
                                                                        <div class="file-input-container">
                                                                            <div class="file-input-label btn-action btn-update">
                                                                                <i class="fas fa-sync-alt"></i> Update Photo
                                                                            </div>
                                                                            <input type="file" name="new_photo" accept="image/*" required onchange="showFileName(this, 'update<?= $space['Space_ID'].$photo['History_ID'] ?>')">
                                                                            <input type="hidden" name="form_type" value="update_photo">
                                                                            <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                                            <input type="hidden" name="old_photo_path" value="<?= htmlspecialchars($photo['Photo_Path']) ?>">
                                                                        </div>
                                                                        <span class="filename-display" id="update<?= $space['Space_ID'].$photo['History_ID'] ?>"></span>
                                                                        
                                                                        <!-- Description field for photo update -->
                                                                        <div class="mb-2 mt-2">
                                                                            <label class="form-label small fw-semibold">Photo Description:</label>
                                                                            <textarea name="photo_description" class="form-control form-control-sm" rows="2" 
                                                                                      placeholder="Describe this photo (max 1000 characters)" 
                                                                                      maxlength="1000"><?= htmlspecialchars($photo['description'] ?? '') ?></textarea>
                                                                            <div class="char-counter small text-muted"><?= strlen($photo['description'] ?? '') ?>/1000</div>
                                                                        </div>
                                                                        
                                                                        <button type="submit" class="btn btn-primary btn-sm mt-2 w-100">Update Photo</button>
                                                                    </form>
                                                                    
                                                                    <!-- Delete Photo Form -->
                                                                    <form method="post" onsubmit="return confirm('Delete this photo? It will be marked as inactive in history.');">
                                                                        <input type="hidden" name="form_type" value="delete_photo">
                                                                        <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                                        <input type="hidden" name="photo_path" value="<?= htmlspecialchars($photo['Photo_Path']) ?>">
                                                                        <button type="submit" class="btn-action btn-delete">
                                                                            <i class="fas fa-trash"></i> Delete
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                                
                                                                <!-- Photo Description Display -->
                                                                <div class="photo-description mt-2">
                                                                    <?php if (!empty($photo['description'])): ?>
                                                                        <div class="description-display">
                                                                            <small class="text-muted d-block">Description:</small>
                                                                            <div class="description-text bg-light p-2 rounded small">
                                                                                <?= htmlspecialchars($photo['description']) ?>
                                                                            </div>
                                                                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1 w-100" 
                                                                                    data-bs-toggle="modal" data-bs-target="#editDescriptionModal"
                                                                                    data-history-id="<?= $photo['History_ID'] ?>"
                                                                                    data-current-description="<?= htmlspecialchars($photo['description']) ?>">
                                                                                <i class="fas fa-edit me-1"></i> Edit Description
                                                                            </button>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <button type="button" class="btn btn-outline-primary btn-sm w-100" 
                                                                                data-bs-toggle="modal" data-bs-target="#editDescriptionModal"
                                                                                data-history-id="<?= $photo['History_ID'] ?>"
                                                                                data-current-description="">
                                                                            <i class="fas fa-plus me-1"></i> Add Description
                                                                        </button>
                                                                    <?php endif; ?>
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

                                                <!-- Add New Photo Section with Description -->
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
                                                                <input type="hidden" name="form_type" value="upload_photo">
                                                                <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                            </div>
                                                            <span class="filename-display" id="add<?= $space['Space_ID'] ?>"></span>
                                                            
                                                            <!-- Description field for new photo -->
                                                            <div class="mb-2 mt-2">
                                                                <label class="form-label small fw-semibold">Photo Description:</label>
                                                                <textarea name="photo_description" class="form-control form-control-sm" rows="2" 
                                                                          placeholder="Describe this photo (max 1000 characters)" 
                                                                          maxlength="1000"></textarea>
                                                                <div class="char-counter small text-muted">0/1000</div>
                                                            </div>
                                                            
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
                                        <td>
                                            <!-- Amenities Management -->
                                            <div class="amenities-section">
                                                <!-- Current Amenities -->
                                                <?php if (!empty($space_amenities)): ?>
                                                    <div class="current-amenities mb-3">
                                                        <h6 class="fw-semibold mb-2">Current Amenities:</h6>
                                                        <?php foreach ($space_amenities as $category_id => $category_data): ?>
                                                            <div class="mb-2">
                                                                <small class="text-muted fw-semibold"><?= htmlspecialchars($category_data['category_name']) ?>:</small>
                                                                <?php foreach ($category_data['amenities'] as $amenity): ?>
                                                                    <div class="current-amenity-item">
                                                                        <div class="amenity-icon">
                                                                            <i class="<?= htmlspecialchars($amenity['Icon']) ?>"></i>
                                                                        </div>
                                                                        <span class="amenity-name"><?= htmlspecialchars($amenity['Amenity_Name']) ?></span>
                                                                        <form method="post" class="ms-auto">
                                                                            <input type="hidden" name="form_type" value="remove_space_amenity">
                                                                            <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                                            <input type="hidden" name="amenity_id" value="<?= $amenity['Amenity_ID'] ?>">
                                                                            <button type="submit" class="btn-remove-amenity" title="Remove amenity">
                                                                                <i class="fas fa-times"></i>
                                                                            </button>
                                                                        </form>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-center text-muted py-2">
                                                        <p>No amenities added yet</p>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Add Amenities -->
                                                <div class="add-amenities">
                                                    <h6 class="fw-semibold mb-3">Add Amenities:</h6>
                                                    <?php foreach ($amenities_categories as $category): 
                                                        $category_amenities = $db->getAmenitiesByCategory($category['Category_ID']);
                                                    ?>
                                                        <?php if (!empty($category_amenities)): ?>
                                                            <div class="amenity-category">
                                                                <div class="amenity-category-header">
                                                                    <div class="amenity-category-icon">
                                                                        <i class="<?= htmlspecialchars($category['Icon']) ?>"></i>
                                                                    </div>
                                                                    <h5 class="amenity-category-title"><?= htmlspecialchars($category['Category_Name']) ?></h5>
                                                                </div>
                                                                <div class="amenities-grid">
                                                                    <?php foreach ($category_amenities as $amenity): 
                                                                        $is_added = false;
                                                                        if (isset($space_amenities[$category['Category_ID']])) {
                                                                            foreach ($space_amenities[$category['Category_ID']]['amenities'] as $added_amenity) {
                                                                                if ($added_amenity['Amenity_ID'] == $amenity['Amenity_ID']) {
                                                                                    $is_added = true;
                                                                                    break;
                                                                                }
                                                                            }
                                                                        }
                                                                    ?>
                                                                        <div class="amenity-item <?= $is_added ? 'selected' : '' ?>">
                                                                            <div class="amenity-header">
                                                                                <div class="amenity-icon">
                                                                                    <i class="<?= htmlspecialchars($amenity['Icon']) ?>"></i>
                                                                                </div>
                                                                                <h6 class="amenity-name"><?= htmlspecialchars($amenity['Amenity_Name']) ?></h6>
                                                                            </div>
                                                                            <?php if (!empty($amenity['Description'])): ?>
                                                                                <p class="amenity-description"><?= htmlspecialchars($amenity['Description']) ?></p>
                                                                            <?php endif; ?>
                                                                            <div class="amenity-actions">
                                                                                <?php if (!$is_added): ?>
                                                                                    <form method="post">
                                                                                        <input type="hidden" name="form_type" value="add_space_amenity">
                                                                                        <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                                                        <input type="hidden" name="amenity_id" value="<?= $amenity['Amenity_ID'] ?>">
                                                                                        <button type="submit" class="btn-add-amenity">
                                                                                            <i class="fas fa-plus me-1"></i> Add
                                                                                        </button>
                                                                                    </form>
                                                                                <?php else: ?>
                                                                                    <span class="text-success small"><i class="fas fa-check me-1"></i>Added</span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="space-actions">
                                                <button type="button" class="btn-edit-space" data-bs-toggle="modal" data-bs-target="#editSpaceModal" 
                                                        data-space-id="<?= $space['Space_ID'] ?>" 
                                                        data-space-name="<?= htmlspecialchars($space['Name']) ?>" 
                                                        data-space-type="<?= $space['SpaceType_ID'] ?>" 
                                                        data-space-price="<?= $space['Price'] ?>">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </button>
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
                            $current_photos = $space_photos[$space['Space_ID']] ?? [];
                            $current_count = count($current_photos);
                            $can_add_more = $current_count < $max_photos_per_unit;
                            $photos_remaining = $max_photos_per_unit - $current_count;
                            $space_amenities = $space_amenities_data[$space['Space_ID']] ?? [];
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

                                <!-- Amenities Section for Mobile -->
                                <div class="amenities-section mt-3">
                                    <h6 class="fw-semibold mb-2">Amenities:</h6>
                                    <?php if (!empty($space_amenities)): ?>
                                        <?php foreach ($space_amenities as $category_id => $category_data): ?>
                                            <div class="mb-2">
                                                <small class="text-muted fw-semibold"><?= htmlspecialchars($category_data['category_name']) ?>:</small>
                                                <?php foreach ($category_data['amenities'] as $amenity): ?>
                                                    <div class="current-amenity-item">
                                                        <div class="amenity-icon">
                                                            <i class="<?= htmlspecialchars($amenity['Icon']) ?>"></i>
                                                        </div>
                                                        <span class="amenity-name"><?= htmlspecialchars($amenity['Amenity_Name']) ?></span>
                                                        <form method="post" class="ms-auto">
                                                            <input type="hidden" name="form_type" value="remove_space_amenity">
                                                            <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                            <input type="hidden" name="amenity_id" value="<?= $amenity['Amenity_ID'] ?>">
                                                            <button type="submit" class="btn-remove-amenity" title="Remove amenity">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-center text-muted py-2">
                                            <p>No amenities added</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="space-actions mt-2">
                                    <button type="button" class="btn-edit-space" data-bs-toggle="modal" data-bs-target="#editSpaceModal" 
                                            data-space-id="<?= $space['Space_ID'] ?>" 
                                            data-space-name="<?= htmlspecialchars($space['Name']) ?>" 
                                            data-space-type="<?= $space['SpaceType_ID'] ?>" 
                                            data-space-price="<?= $space['Price'] ?>">
                                        <i class="fas fa-edit me-1"></i> Edit Space
                                    </button>
                                </div>

                                <div class="mobile-photo-grid">
                                    <?php foreach ($current_photos as $photo): ?>
                                        <div class="mobile-photo-item">
                                            <img src="../uploads/unit_photos/<?= htmlspecialchars($photo['Photo_Path']) ?>" alt="Space Photo">
                                            <div class="mobile-photo-actions">
                                                <!-- Update Photo Form with Description -->
                                                <form method="post" enctype="multipart/form-data">
                                                    <div class="file-input-container w-100">
                                                        <div class="file-input-label btn-action btn-update w-100">
                                                            <i class="fas fa-sync-alt"></i> Update
                                                        </div>
                                                        <input type="file" name="new_photo" accept="image/*" required onchange="showFileName(this, 'mobile-update<?= $space['Space_ID'].$photo['History_ID'] ?>')">
                                                        <input type="hidden" name="form_type" value="update_photo">
                                                        <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                        <input type="hidden" name="old_photo_path" value="<?= htmlspecialchars($photo['Photo_Path']) ?>">
                                                    </div>
                                                    <div class="filename-display" id="mobile-update<?= $space['Space_ID'].$photo['History_ID'] ?>"></div>
                                                    
                                                    <!-- Description field for photo update (mobile) -->
                                                    <div class="mb-2 mt-2">
                                                        <label class="form-label small fw-semibold">Description:</label>
                                                        <textarea name="photo_description" class="form-control form-control-sm" rows="2" 
                                                                  placeholder="Describe this photo (max 1000 chars)" 
                                                                  maxlength="1000"><?= htmlspecialchars($photo['description'] ?? '') ?></textarea>
                                                        <div class="char-counter small text-muted"><?= strlen($photo['description'] ?? '') ?>/1000</div>
                                                    </div>
                                                    
                                                    <button type="submit" class="btn btn-primary btn-sm w-100 mt-1" style="font-size: 0.7rem;">Update Photo</button>
                                                </form>
                                                
                                                <!-- Delete Photo Form -->
                                                <form method="post" onsubmit="return confirm('Delete this photo? It will be marked as inactive in history.');">
                                                    <input type="hidden" name="form_type" value="delete_photo">
                                                    <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                    <input type="hidden" name="photo_path" value="<?= htmlspecialchars($photo['Photo_Path']) ?>">
                                                    <button type="submit" class="btn-action btn-delete w-100">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                                
                                                <!-- Photo Description Display (Mobile) -->
                                                <div class="photo-description mt-2">
                                                    <?php if (!empty($photo['description'])): ?>
                                                        <div class="description-display">
                                                            <small class="text-muted d-block">Description:</small>
                                                            <div class="description-text bg-light p-2 rounded small">
                                                                <?= htmlspecialchars($photo['description']) ?>
                                                            </div>
                                                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1 w-100" 
                                                                    data-bs-toggle="modal" data-bs-target="#editDescriptionModal"
                                                                    data-history-id="<?= $photo['History_ID'] ?>"
                                                                    data-current-description="<?= htmlspecialchars($photo['description']) ?>"
                                                                    style="font-size: 0.7rem;">
                                                                <i class="fas fa-edit me-1"></i> Edit Desc
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-outline-primary btn-sm w-100" 
                                                                data-bs-toggle="modal" data-bs-target="#editDescriptionModal"
                                                                data-history-id="<?= $photo['History_ID'] ?>"
                                                                data-current-description=""
                                                                style="font-size: 0.7rem;">
                                                            <i class="fas fa-plus me-1"></i> Add Desc
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <!-- Add New Photo Mobile with Description -->
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
                                                    <input type="hidden" name="form_type" value="upload_photo">
                                                    <input type="hidden" name="space_id" value="<?= $space['Space_ID'] ?>">
                                                </div>
                                                <div class="filename-display" id="mobile-add<?= $space['Space_ID'] ?>"></div>
                                                
                                                <!-- Description field for new photo (mobile) -->
                                                <div class="mb-2 mt-2">
                                                    <label class="form-label small fw-semibold">Description:</label>
                                                    <textarea name="photo_description" class="form-control form-control-sm" rows="2" 
                                                              placeholder="Describe this photo (max 1000 chars)" 
                                                              maxlength="1000"></textarea>
                                                    <div class="char-counter small text-muted">0/1000</div>
                                                </div>
                                                
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
                                    <th>Actions</th>
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
                                        <td>
                                            <div class="space-type-actions">
                                                <!-- Edit Button -->
                                                <button type="button" class="btn-edit" data-bs-toggle="modal" data-bs-target="#editTypeModal" 
                                                        data-type-id="<?= $type['SpaceType_ID'] ?>" data-type-name="<?= htmlspecialchars($type['SpaceTypeName']) ?>">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </button>
                                                
                                                <!-- Delete Button -->
                                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this space type? This action cannot be undone.');">
                                                    <input type="hidden" name="form_type" value="delete_type">
                                                    <input type="hidden" name="type_id" value="<?= $type['SpaceType_ID'] ?>">
                                                    <button type="submit" class="btn-remove">
                                                        <i class="fas fa-trash me-1"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
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
                                <div class="space-type-actions mt-2">
                                    <button type="button" class="btn-edit" data-bs-toggle="modal" data-bs-target="#editTypeModal" 
                                            data-type-id="<?= $type['SpaceType_ID'] ?>" data-type-name="<?= htmlspecialchars($type['SpaceTypeName']) ?>">
                                        <i class="fas fa-edit me-1"></i> Edit
                                    </button>
                                    
                                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this space type? This action cannot be undone.');" class="mt-1">
                                        <input type="hidden" name="form_type" value="delete_type">
                                        <input type="hidden" name="type_id" value="<?= $type['SpaceType_ID'] ?>">
                                        <button type="submit" class="btn-remove">
                                            <i class="fas fa-trash me-1"></i> Delete
                                        </button>
                                    </form>
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

        <!-- Clear Photo History Timeline -->
        <div class="dashboard-card animate-fade-in">
            <div class="card-header">
                <i class="fas fa-history"></i>
                <span>Photo Change History</span>
                <span class="badge bg-info ms-2"><?= count($photo_history) ?></span>
            </div>
            <div class="card-body">
                <!-- Filter Section -->
                <div class="filter-section">
                    <div class="row">
                        <div class="col-md-6">
                            <label for="unitFilter" class="form-label fw-semibold">Filter by Unit:</label>
                            <select id="unitFilter" class="form-select">
                                <option value="all">All Units</option>
                                <?php foreach ($unique_spaces as $space): ?>
                                    <option value="<?= $space['id'] ?>"><?= htmlspecialchars($space['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="actionFilter" class="form-label fw-semibold">Filter by Action:</label>
                            <select id="actionFilter" class="form-select">
                                <option value="all">All Actions</option>
                                <option value="uploaded">Uploaded</option>
                                <option value="updated">Updated</option>
                                <option value="deleted">Deleted</option>
                            </select>
                        </div>
                    </div>
                </div>

                <?php if (!empty($photo_history)): ?>
                    <div class="timeline" id="photoTimeline">
                        <?php foreach ($photo_history as $history): 
                            $space_id = $history['Space_ID'];
                            $space_name = htmlspecialchars($history['Space_Name'] ?? 'Unit #' . $space_id);
                            $date = date('M j, Y g:i A', strtotime($history['Action_Date']));
                        ?>
                            <div class="timeline-item" data-unit="<?= $space_id ?>" data-action="<?= $history['Action'] ?>">
                                <div class="timeline-date">
                                    <i class="fas fa-clock me-1"></i><?= $date ?>
                                </div>
                                <div class="timeline-content">
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong><?= $space_name ?></strong>
                                                <span class="badge 
                                                    <?= $history['Action'] === 'uploaded' ? 'bg-success' : '' ?>
                                                    <?= $history['Action'] === 'updated' ? 'bg-warning' : '' ?>
                                                    <?= $history['Action'] === 'deleted' ? 'bg-danger' : '' ?>
                                                    ms-2">
                                                    <?= ucfirst($history['Action']) ?>
                                                </span>
                                            </div>
                                            <small class="text-muted">Unit ID: <?= $space_id ?></small>
                                        </div>
                                        
                                        <!-- Add description display in timeline -->
                                        <?php if (!empty($history['description'])): ?>
                                            <div class="description-timeline bg-light p-2 rounded mt-2 small">
                                                <strong>Description:</strong> <?= htmlspecialchars($history['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($history['Action'] === 'updated' && $history['Previous_Photo_Path']): ?>
                                            <!-- Photo Update - Show Before/After -->
                                            <div class="photo-comparison mt-2">
                                                <div class="text-center">
                                                    <img src="../uploads/unit_photos/<?= htmlspecialchars($history['Previous_Photo_Path']) ?>" 
                                                         class="history-photo"
                                                         data-bs-toggle="modal" 
                                                         data-bs-target="#imageModal"
                                                         data-image-src="../uploads/unit_photos/<?= htmlspecialchars($history['Previous_Photo_Path']) ?>"
                                                         data-image-title="<?= $space_name ?> - Previous Version (<?= date('M j, Y', strtotime($history['Action_Date'])) ?>)">
                                                    <div class="small text-muted mt-1">Previous</div>
                                                </div>
                                                <div class="photo-arrow">
                                                    <i class="fas fa-arrow-right"></i>
                                                </div>
                                                <div class="text-center">
                                                    <img src="../uploads/unit_photos/<?= htmlspecialchars($history['Photo_Path']) ?>" 
                                                         class="history-photo"
                                                         data-bs-toggle="modal" 
                                                         data-bs-target="#imageModal"
                                                         data-image-src="../uploads/unit_photos/<?= htmlspecialchars($history['Photo_Path']) ?>"
                                                         data-image-title="<?= $space_name ?> - New Version (<?= date('M j, Y', strtotime($history['Action_Date'])) ?>)">
                                                    <div class="small text-muted mt-1">New</div>
                                                </div>
                                            </div>
                                        <?php elseif ($history['Action'] === 'uploaded'): ?>
                                            <!-- Photo Upload -->
                                            <div class="timeline-photos mt-2">
                                                <div class="text-center">
                                                    <img src="../uploads/unit_photos/<?= htmlspecialchars($history['Photo_Path']) ?>" 
                                                         class="history-photo"
                                                         data-bs-toggle="modal" 
                                                         data-bs-target="#imageModal"
                                                         data-image-src="../uploads/unit_photos/<?= htmlspecialchars($history['Photo_Path']) ?>"
                                                         data-image-title="<?= $space_name ?> - Uploaded (<?= date('M j, Y', strtotime($history['Action_Date'])) ?>)">
                                                    <div class="small text-muted mt-1">Uploaded Photo</div>
                                                </div>
                                            </div>
                                        <?php elseif ($history['Action'] === 'deleted'): ?>
                                            <!-- Photo Deletion -->
                                            <div class="timeline-photos mt-2">
                                                <div class="text-center">
                                                    <div class="history-photo bg-light d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-trash text-danger fa-2x"></i>
                                                    </div>
                                                    <div class="small text-muted mt-1">Deleted: <?= htmlspecialchars($history['Photo_Path']) ?></div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h4>No photo history found</h4>
                        <p>Photo changes will appear here in a clear timeline format</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Space Type Modal -->
    <div class="modal fade" id="editTypeModal" tabindex="-1" aria-labelledby="editTypeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTypeModalLabel">Edit Space Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="form_type" value="update_type">
                        <input type="hidden" name="type_id" id="edit_type_id">
                        <div class="mb-3">
                            <label for="edit_type_name" class="form-label">Space Type Name</label>
                            <input type="text" class="form-control" id="edit_type_name" name="new_type_name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Space Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Space Modal -->
    <div class="modal fade" id="editSpaceModal" tabindex="-1" aria-labelledby="editSpaceModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSpaceModalLabel">Edit Space/Unit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="form_type" value="update_space">
                        <input type="hidden" name="space_id" id="edit_space_id">
                        <div class="mb-3">
                            <label for="edit_space_name" class="form-label">Space/Unit Name</label>
                            <input type="text" class="form-control" id="edit_space_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_space_type" class="form-label">Space Type</label>
                            <select class="form-select" id="edit_space_type" name="spacetype_id" required>
                                <option value="" disabled>Select Type</option>
                                <?php foreach ($spacetypes as $stype): ?>
                                    <option value="<?= $stype['SpaceType_ID'] ?>"><?= htmlspecialchars($stype['SpaceTypeName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_space_price" class="form-label">Price (PHP)</label>
                            <input type="number" step="100" min="0" class="form-control" id="edit_space_price" name="price" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Space/Unit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Description Modal -->
    <div class="modal fade" id="editDescriptionModal" tabindex="-1" aria-labelledby="editDescriptionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDescriptionModalLabel">Edit Photo Description</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="form_type" value="update_description">
                        <input type="hidden" name="history_id" id="edit_history_id">
                        <div class="mb-3">
                            <label for="edit_photo_description" class="form-label">Photo Description</label>
                            <textarea class="form-control" id="edit_photo_description" name="photo_description" 
                                      rows="6" maxlength="1000" 
                                      placeholder="Describe this photo in detail (e.g., 'Living room view from entrance showing sofa and coffee table', 'Kitchen area with modern appliances and counter space', etc.)"></textarea>
                            <div class="form-text">Max 1000 characters. Provide detailed description of what the photo shows.</div>
                            <div class="form-text char-counter"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Description</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">Photo Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Preview" class="img-fluid" style="max-height: 70vh;">
                    <div id="modalImageTitle" class="mt-3 fw-bold"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- COMPLETE NOTIFICATION SYSTEM ---
        let rentalNotificationCooldown = false;
        let maintenanceNotificationCooldown = false;
        let clientMessageNotificationCooldown = false;

        let lastUnseenRentals = <?= $unseen_rentals ?? 0 ?>;
        let lastUnseenMaintenance = <?= $new_maintenance_requests ?? 0 ?>;
        let lastUnreadClientMessages = <?= $unread_client_messages ?? 0 ?>;
        let isFirstLoad = true;
        let isTabActive = true;

        // Debug logging
        console.log('Initial counts - Rentals: <?= $unseen_rentals ?? 0 ?>, Maintenance: <?= $new_maintenance_requests ?? 0 ?>, Messages: <?= $unread_client_messages ?? 0 ?>');

        // Tab visibility handling
        document.addEventListener('visibilitychange', function() {
            isTabActive = !document.hidden;
            console.log('Tab visibility changed:', isTabActive ? 'active' : 'hidden');
            if (isTabActive) {
                fetchDashboardCounts();
            }
        });

        // 1. Show rental notification
        function showNewRentalNotification(count) {
            if (rentalNotificationCooldown) {
                console.log('Rental notification cooldown active');
                return;
            }
            
            console.log('Showing rental notification for', count, 'new requests');
            rentalNotificationCooldown = true;
            
            const notification = document.createElement('div');
            notification.className = 'alert alert-success alert-dismissible fade show';
            notification.style.cssText = `
                position: fixed; 
                top: 20px; 
                right: 20px; 
                z-index: 9999; 
                min-width: 320px; 
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-left: 4px solid #10b981;
                animation: slideInRight 0.3s ease-out;
            `;
            notification.innerHTML = `
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-bell text-success fs-4 me-3 bell-shake"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="alert-heading mb-1">🏠 New Rental Request!</h6>
                        <p class="mb-2">You have <strong>${count}</strong> new pending request${count > 1 ? 's' : ''} to review.</p>
                        <div class="d-flex gap-2 mt-2">
                            <a href="view_rental_requests.php" class="btn btn-sm btn-success">
                                <i class="fas fa-eye me-1"></i>View Requests
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="alert">
                                Dismiss
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 8 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.remove();
                        }
                    }, 300);
                }
            }, 8000);
            
            // Reset cooldown after 10 seconds
            setTimeout(() => {
                rentalNotificationCooldown = false;
                console.log('Rental notification cooldown reset');
            }, 10000);
        }

        // 2. Show maintenance notification
        function showNewMaintenanceNotification(count) {
            if (maintenanceNotificationCooldown) {
                console.log('Maintenance notification cooldown active');
                return;
            }
            
            console.log('Showing maintenance notification for', count, 'new requests');
            maintenanceNotificationCooldown = true;
            
            const notification = document.createElement('div');
            notification.className = 'alert alert-warning alert-dismissible fade show';
            notification.style.cssText = `
                position: fixed; 
                top: 100px; 
                right: 20px; 
                z-index: 9999; 
                min-width: 320px; 
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-left: 4px solid #f59e0b;
                animation: slideInRight 0.3s ease-out;
            `;
            notification.innerHTML = `
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-tools text-warning fs-4 me-3 tools-shake"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="alert-heading mb-1">🔧 New Maintenance Request!</h6>
                        <p class="mb-2">You have <strong>${count}</strong> new maintenance request${count > 1 ? 's' : ''} to review.</p>
                        <div class="d-flex gap-2 mt-2">
                            <a href="manage_maintenance.php" class="btn btn-sm btn-warning text-white">
                                <i class="fas fa-tools me-1"></i>View Requests
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="alert">
                                Dismiss
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 8 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.remove();
                        }
                    }, 300);
                }
            }, 8000);
            
            // Reset cooldown after 10 seconds
            setTimeout(() => {
                maintenanceNotificationCooldown = false;
                console.log('Maintenance notification cooldown reset');
            }, 10000);
        }

        // 3. Show client message notification
        function showNewClientMessageNotification(count) {
            if (clientMessageNotificationCooldown) {
                console.log('Client message notification cooldown active');
                return;
            }
            
            console.log('Showing client message notification for', count, 'new messages');
            clientMessageNotificationCooldown = true;
            
            const notification = document.createElement('div');
            notification.className = 'alert alert-info alert-dismissible fade show';
            notification.style.cssText = `
                position: fixed; 
                top: 180px; 
                right: 20px; 
                z-index: 9999; 
                min-width: 320px; 
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-left: 4px solid #06b6d4;
                animation: slideInRight 0.3s ease-out;
            `;
            notification.innerHTML = `
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-comments text-info fs-4 me-3 message-shake"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="alert-heading mb-1">💬 New Client Message!</h6>
                        <p class="mb-2">You have <strong>${count}</strong> new message${count > 1 ? 's' : ''} from client${count > 1 ? 's' : ''}.</p>
                        <div class="d-flex gap-2 mt-2">
                            <a href="generate_invoice.php" class="btn btn-sm btn-info text-white">
                                <i class="fas fa-inbox me-1"></i>View Messages
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="alert">
                                Dismiss
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 8 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.remove();
                        }
                    }, 300);
                }
            }, 8000);
            
            // Reset cooldown after 10 seconds
            setTimeout(() => {
                clientMessageNotificationCooldown = false;
                console.log('Client message notification cooldown reset');
            }, 10000);
        }

        function updateBadgeAnimation(badgeElement, newCount, oldCount) {
            if (newCount > oldCount && !isFirstLoad) {
                badgeElement.classList.add('notification-badge');
                setTimeout(() => {
                    badgeElement.classList.remove('notification-badge');
                }, 3000);
            }
        }

        // Function to update sidebar badges
        function updateSidebarBadge(currentCount, badgeId, linkSelector) {
            const sidebarBadge = document.getElementById(badgeId);
            if (sidebarBadge) {
                const oldCount = parseInt(sidebarBadge.textContent);
                sidebarBadge.textContent = currentCount;
                updateBadgeAnimation(sidebarBadge, currentCount, oldCount);
            } else {
                // Create badge if it doesn't exist
                const link = document.querySelector(`a[href="${linkSelector}"]`);
                if (link && currentCount > 0) {
                    const newBadge = document.createElement('span');
                    newBadge.id = badgeId;
                    newBadge.className = 'badge badge-notification bg-danger notification-badge';
                    newBadge.textContent = currentCount;
                    link.appendChild(newBadge);
                }
            }
        }

        // Fetch dashboard counts
        function fetchDashboardCounts() {
            if (!isTabActive) {
                console.log('Tab not active, skipping count fetch');
                return;
            }
            
            console.log('Fetching dashboard counts...');
            fetch('../AJAX/ajax_admin_dashboard_counts.php')
                .then(res => {
                    if (!res.ok) throw new Error('Network response was not ok');
                    return res.json();
                })
                .then(data => {
                    console.log('Counts received:', data);
                    
                    if (data && !data.error) {
                        const currentUnseenRentals = data.unseen_rentals ?? 0;
                        const currentUnseenMaintenance = data.new_maintenance_requests ?? 0;
                        const currentUnreadClientMessages = data.unread_client_messages ?? 0;

                        // Check for new rental requests
                        if (!isFirstLoad && currentUnseenRentals > lastUnseenRentals) {
                            const newRequests = currentUnseenRentals - lastUnseenRentals;
                            console.log(`New rental requests detected: ${newRequests} (was ${lastUnseenRentals}, now ${currentUnseenRentals})`);
                            showNewRentalNotification(newRequests);
                            
                            // Update sidebar badge
                            updateSidebarBadge(currentUnseenRentals, 'sidebarRentalBadge', 'view_rental_requests.php');
                        }
                        
                        // Check for new maintenance requests
                        if (!isFirstLoad && currentUnseenMaintenance > lastUnseenMaintenance) {
                            const newRequests = currentUnseenMaintenance - lastUnseenMaintenance;
                            console.log(`New maintenance requests detected: ${newRequests} (was ${lastUnseenMaintenance}, now ${currentUnseenMaintenance})`);
                            showNewMaintenanceNotification(newRequests);
                            
                            // Update sidebar badge
                            updateSidebarBadge(currentUnseenMaintenance, 'sidebarMaintenanceBadge', 'manage_maintenance.php');
                        }
                        
                        // Check for new client messages
                        if (!isFirstLoad && currentUnreadClientMessages > lastUnreadClientMessages) {
                            const newMessages = currentUnreadClientMessages - lastUnreadClientMessages;
                            console.log(`New client messages detected: ${newMessages} (was ${lastUnreadClientMessages}, now ${currentUnreadClientMessages})`);
                            showNewClientMessageNotification(newMessages);
                            
                            // Update sidebar badge
                            updateSidebarBadge(currentUnreadClientMessages, 'sidebarInvoicesBadge', 'generate_invoice.php');
                        }
                        
                        lastUnseenRentals = currentUnseenRentals;
                        lastUnseenMaintenance = currentUnseenMaintenance;
                        lastUnreadClientMessages = currentUnreadClientMessages;
                        isFirstLoad = false;
                    }
                })
                .catch(err => {
                    console.error('Error fetching dashboard counts:', err);
                });
        }

        // Mobile menu functionality
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');
        
        function toggleMobileMenu() {
            sidebar.classList.toggle('active');
            mobileOverlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        }

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', toggleMobileMenu);
        }
        
        if (mobileOverlay) {
            mobileOverlay.addEventListener('click', toggleMobileMenu);
        }

        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992 && sidebar.classList.contains('active')) {
                    toggleMobileMenu();
                }
            });
        });

        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                if (window.innerWidth > 992 && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    mobileOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }, 250);
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

        // Edit Space Type Modal
        const editTypeModal = document.getElementById('editTypeModal');
        if (editTypeModal) {
            editTypeModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const typeId = button.getAttribute('data-type-id');
                const typeName = button.getAttribute('data-type-name');
                
                const modal = this;
                modal.querySelector('#edit_type_id').value = typeId;
                modal.querySelector('#edit_type_name').value = typeName;
            });
        }

        // Edit Space Modal
        const editSpaceModal = document.getElementById('editSpaceModal');
        if (editSpaceModal) {
            editSpaceModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const spaceId = button.getAttribute('data-space-id');
                const spaceName = button.getAttribute('data-space-name');
                const spaceType = button.getAttribute('data-space-type');
                const spacePrice = button.getAttribute('data-space-price');
                
                const modal = this;
                modal.querySelector('#edit_space_id').value = spaceId;
                modal.querySelector('#edit_space_name').value = spaceName;
                modal.querySelector('#edit_space_type').value = spaceType;
                modal.querySelector('#edit_space_price').value = spacePrice;
            });
        }

        // Edit Description Modal
        const editDescriptionModal = document.getElementById('editDescriptionModal');
        if (editDescriptionModal) {
            editDescriptionModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const historyId = button.getAttribute('data-history-id');
                const currentDescription = button.getAttribute('data-current-description');
                
                const modal = this;
                modal.querySelector('#edit_history_id').value = historyId;
                modal.querySelector('#edit_photo_description').value = currentDescription;
                
                // Trigger character counter update
                const textarea = modal.querySelector('#edit_photo_description');
                textarea.dispatchEvent(new Event('input'));
            });
        }

        // Image Preview Modal
        const imageModal = document.getElementById('imageModal');
        if (imageModal) {
            imageModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const imageSrc = button.getAttribute('data-image-src');
                const imageTitle = button.getAttribute('data-image-title');
                
                const modal = this;
                modal.querySelector('#modalImage').src = imageSrc;
                modal.querySelector('#modalImageTitle').textContent = imageTitle;
            });
        }

        // Photo History Filtering
        const unitFilter = document.getElementById('unitFilter');
        const actionFilter = document.getElementById('actionFilter');
        const timelineItems = document.querySelectorAll('.timeline-item');

        function filterTimeline() {
            const selectedUnit = unitFilter.value;
            const selectedAction = actionFilter.value;

            timelineItems.forEach(item => {
                const unitId = item.getAttribute('data-unit');
                const actionType = item.getAttribute('data-action');
                
                const unitMatch = selectedUnit === 'all' || selectedUnit === unitId;
                const actionMatch = selectedAction === 'all' || selectedAction === actionType;
                
                if (unitMatch && actionMatch) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        if (unitFilter && actionFilter) {
            unitFilter.addEventListener('change', filterTimeline);
            actionFilter.addEventListener('change', filterTimeline);
        }

        // Character counter for description (1000 characters)
        const descriptionTextarea = document.getElementById('edit_photo_description');
        if (descriptionTextarea) {
            descriptionTextarea.addEventListener('input', function() {
                const maxLength = 1000;
                const currentLength = this.value.length;
                const counter = this.parentNode.querySelector('.char-counter') || 
                               (function() {
                                   const counter = document.createElement('div');
                                   counter.className = 'form-text char-counter';
                                   this.parentNode.appendChild(counter);
                                   return counter;
                               }).call(this);
                
                counter.textContent = `${currentLength}/${maxLength} characters`;
                
                if (currentLength > maxLength) {
                    counter.classList.add('text-danger');
                } else {
                    counter.classList.remove('text-danger');
                }
            });
            
            // Trigger input event to show initial count
            descriptionTextarea.dispatchEvent(new Event('input'));
        }

        // Add character counters to all description textareas in photo forms
        document.querySelectorAll('textarea[name="photo_description"]').forEach(textarea => {
            textarea.addEventListener('input', function() {
                const maxLength = 1000;
                const currentLength = this.value.length;
                let counter = this.parentNode.querySelector('.char-counter');
                
                if (!counter) {
                    counter = document.createElement('div');
                    counter.className = 'char-counter small text-muted';
                    this.parentNode.appendChild(counter);
                }
                
                counter.textContent = `${currentLength}/${maxLength} characters`;
                
                if (currentLength > maxLength) {
                    counter.classList.add('text-danger');
                } else {
                    counter.classList.remove('text-danger');
                }
            });
            
            // Initialize counter display
            textarea.dispatchEvent(new Event('input'));
        });

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
                const isNewUpload = !form.querySelector('input[name="old_photo_path"]');
                
                if (isNewUpload && !checkPhotoLimit(spaceId, true)) {
                    this.value = ''; // Clear the file input
                }
            });
        });

        // Confirmation for delete actions
        document.querySelectorAll('form').forEach(form => {
            if (form.querySelector('input[name="form_type"][value="delete_photo"]')) {
                form.addEventListener('submit', function(e) {
                    if (!confirm('Are you sure you want to delete this photo? It will be marked as inactive in history.')) {
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

        // Start polling for notifications
        document.addEventListener('DOMContentLoaded', () => {
            console.log('Space & Unit Management page fully loaded with COMPLETE notification system and AMENITIES management');
            console.log('Test notifications with: testNotification("rental") or testNotification("maintenance") or testNotification("client_message")');
            
            fetchDashboardCounts();
            
            // Poll every 5 seconds for faster response
            setInterval(() => {
                if (isTabActive) {
                    fetchDashboardCounts();
                }
            }, 5000);
        });

        // Debug: Manual trigger for testing
        window.testNotification = function(type) {
            if (type === 'rental') {
                showNewRentalNotification(1);
            } else if (type === 'maintenance') {
                showNewMaintenanceNotification(1);
            } else if (type === 'client_message') {
                showNewClientMessageNotification(1);
            }
        };

        // Add slideInRight animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100%);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
        `;
        document.head.appendChild(style);

        // Amenity item hover effects
        document.querySelectorAll('.amenity-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });
    </script>
</body>
</html>
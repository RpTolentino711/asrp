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

// --- Handle photo description update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['form_type']) && $_POST['form_type'] === 'update_description') {
    $history_id = intval($_POST['history_id'] ?? 0);
    $description = trim($_POST['photo_description'] ?? '');
    
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
    $photo_description = trim($_POST['photo_description'] ?? '');
    
    if (strlen($photo_description) > 1000) {
        $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                      <i class="fas fa-exclamation-circle me-2"></i>
                      Description too long. Maximum 1000 characters allowed.
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
    } elseif ($space_id && isset($_FILES['new_photo']) && $_FILES['new_photo']['error'] == UPLOAD_ERR_OK) {
        $current_photos = $db->getCurrentSpacePhotos($space_id);
        
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
    $photo_description = trim($_POST['photo_description'] ?? '');
    
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
                $db->deactivatePhoto($space_id, $old_photo_path);
                
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
    
    // Collect utilities data
    $utilities_data = [
        'bedrooms' => intval($_POST['bedrooms'] ?? 0),
        'toilets' => intval($_POST['toilets'] ?? 0),
        'has_water' => isset($_POST['has_water']) ? 1 : 0,
        'has_electricity' => isset($_POST['has_electricity']) ? 1 : 0,
        'square_meters' => !empty($_POST['square_meters']) ? floatval($_POST['square_meters']) : null,
        'furnished' => isset($_POST['furnished']) ? 1 : 0,
        'air_conditioning' => isset($_POST['air_conditioning']) ? 1 : 0,
        'parking' => isset($_POST['parking']) ? 1 : 0,
        'internet' => isset($_POST['internet']) ? 1 : 0
    ];

    // Handle file upload
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
        if ($db->addNewSpace($name, $spacetype_id, $ua_id, $price)) {
            $all_spaces = $db->getAllSpacesWithUtilities();
            $new_space = null;
            foreach ($all_spaces as $space) {
                if ($space['Name'] === $name) {
                    $new_space = $space;
                    break;
                }
            }
            
            if ($new_space) {
                if ($db->addSpaceUtilities($new_space['Space_ID'], $utilities_data)) {
                    if ($uploaded_photo_filename) {
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
                                  Space added but failed to save utilities data.
                                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                  </div>';
                }
            }
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
    
    // Collect utilities data for update
    $utilities_data = [
        'bedrooms' => intval($_POST['bedrooms'] ?? 0),
        'toilets' => intval($_POST['toilets'] ?? 0),
        'has_water' => isset($_POST['has_water']) ? 1 : 0,
        'has_electricity' => isset($_POST['has_electricity']) ? 1 : 0,
        'square_meters' => !empty($_POST['square_meters']) ? floatval($_POST['square_meters']) : null,
        'furnished' => isset($_POST['furnished']) ? 1 : 0,
        'air_conditioning' => isset($_POST['air_conditioning']) ? 1 : 0,
        'parking' => isset($_POST['parking']) ? 1 : 0,
        'internet' => isset($_POST['internet']) ? 1 : 0
    ];

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
        $existing_spaces = $db->getAllSpacesWithUtilities();
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
                // Update utilities
                if ($db->updateSpaceUtilities($space_id, $utilities_data)) {
                    $success_unit = '<div class="alert alert-success alert-dismissible fade show animate-fade-in" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Space/unit updated successfully!
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                    </div>';
                } else {
                    $error_unit = '<div class="alert alert-danger alert-dismissible fade show animate-fade-in" role="alert">
                                  <i class="fas fa-exclamation-circle me-2"></i>
                                  Space updated but failed to save utilities data.
                                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                  </div>';
                }
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
$spaces = $db->getAllSpacesWithUtilities();

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
            justify-content: between;
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

        /* NEW: Utilities Icons Overlay on Photos */
        .photo-with-utilities {
            position: relative;
        }

        .utilities-overlay {
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            gap: 4px;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .utility-icon {
            font-size: 0.6rem;
            opacity: 0.9;
        }

        .utility-count {
            font-weight: 600;
            margin: 0 2px;
        }

        /* NEW: Utilities Section Styling */
        .utilities-section {
            background: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
        }

        .utilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .utility-item {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            text-align: center;
        }

        .utility-icon-large {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .utility-value {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--dark);
        }

        .utility-label {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        /* NEW: Utilities Badges */
        .utilities-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 0.5rem;
        }

        .utility-badge {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            border: 1px solid rgba(99, 102, 241, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .utility-badge i {
            font-size: 0.6rem;
        }

        /* NEW: Compact utilities display for table */
        .compact-utilities {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            max-width: 200px;
        }

        .compact-utility {
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.7rem;
            color: #6b7280;
            display: inline-flex;
            align-items: center;
            gap: 2px;
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
                            
                            <!-- Basic Information -->
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
                            
                            <!-- NEW: Utilities Section -->
                            <div class="col-12">
                                <div class="utilities-section">
                                    <h6 class="fw-semibold mb-3">
                                        <i class="fas fa-home me-2"></i>Unit Utilities & Features
                                    </h6>
                                    
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label for="bedrooms" class="form-label small fw-semibold">Bedrooms</label>
                                            <select class="form-select form-select-sm" id="bedrooms" name="bedrooms">
                                                <option value="0">0 Bedrooms</option>
                                                <option value="1">1 Bedroom</option>
                                                <option value="2">2 Bedrooms</option>
                                                <option value="3">3 Bedrooms</option>
                                                <option value="4">4 Bedrooms</option>
                                                <option value="5">5+ Bedrooms</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="toilets" class="form-label small fw-semibold">Toilets</label>
                                            <select class="form-select form-select-sm" id="toilets" name="toilets">
                                                <option value="0">0 Toilets</option>
                                                <option value="1">1 Toilet</option>
                                                <option value="2">2 Toilets</option>
                                                <option value="3">3 Toilets</option>
                                                <option value="4">4+ Toilets</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <label for="square_meters" class="form-label small fw-semibold">Area (Square Meters)</label>
                                            <input type="number" class="form-control form-control-sm" id="square_meters" name="square_meters" min="0" step="0.5" placeholder="e.g., 25.5">
                                        </div>
                                    </div>
                                    
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="has_water" id="has_water" value="1" checked>
                                                <label class="form-check-label small" for="has_water">
                                                    <i class="fas fa-tint me-1 text-primary"></i>Water
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="has_electricity" id="has_electricity" value="1" checked>
                                                <label class="form-check-label small" for="has_electricity">
                                                    <i class="fas fa-bolt me-1 text-warning"></i>Electricity
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="furnished" id="furnished" value="1">
                                                <label class="form-check-label small" for="furnished">
                                                    <i class="fas fa-couch me-1 text-success"></i>Furnished
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="air_conditioning" id="air_conditioning" value="1">
                                                <label class="form-check-label small" for="air_conditioning">
                                                    <i class="fas fa-snowflake me-1 text-info"></i>Air Conditioning
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="parking" id="parking" value="1">
                                                <label class="form-check-label small" for="parking">
                                                    <i class="fas fa-car me-1 text-secondary"></i>Parking
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="internet" id="internet" value="1">
                                                <label class="form-check-label small" for="internet">
                                                    <i class="fas fa-wifi me-1 text-purple"></i>Internet
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
                                    <th>Utilities</th>
                                    <th>Photos (Max: <?= $max_photos_per_unit ?>)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($spaces as $space): 
                                    $current_photos = $space_photos[$space['Space_ID']] ?? [];
                                    $current_count = count($current_photos);
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
                                            <div class="compact-utilities">
                                                <?php if ($space['Bedrooms'] > 0): ?>
                                                    <span class="compact-utility">
                                                        <i class="fas fa-bed"></i> <?= $space['Bedrooms'] ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($space['Toilets'] > 0): ?>
                                                    <span class="compact-utility">
                                                        <i class="fas fa-bath"></i> <?= $space['Toilets'] ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($space['Square_Meters']): ?>
                                                    <span class="compact-utility">
                                                        <i class="fas fa-ruler-combined"></i> <?= $space['Square_Meters'] ?>m²
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($space['Has_Water']): ?>
                                                    <span class="compact-utility">
                                                        <i class="fas fa-tint text-primary"></i>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($space['Has_Electricity']): ?>
                                                    <span class="compact-utility">
                                                        <i class="fas fa-bolt text-warning"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
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
                                                                <div class="photo-with-utilities">
                                                                    <img src="../uploads/unit_photos/<?= htmlspecialchars($photo['Photo_Path']) ?>" class="photo-preview" alt="Space Photo">
                                                                    
                                                                    <!-- NEW: Utilities Overlay -->
                                                                    <div class="utilities-overlay">
                                                                        <?php if ($space['Bedrooms'] > 0): ?>
                                                                            <i class="fas fa-bed utility-icon"></i>
                                                                            <span class="utility-count"><?= $space['Bedrooms'] ?></span>
                                                                        <?php endif; ?>
                                                                        <?php if ($space['Toilets'] > 0): ?>
                                                                            <i class="fas fa-bath utility-icon"></i>
                                                                            <span class="utility-count"><?= $space['Toilets'] ?></span>
                                                                        <?php endif; ?>
                                                                        <?php if ($space['Square_Meters']): ?>
                                                                            <i class="fas fa-ruler-combined utility-icon"></i>
                                                                            <span class="utility-count"><?= $space['Square_Meters'] ?>m²</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                                
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
                                            <div class="space-actions">
                                                <button type="button" class="btn-edit-space" data-bs-toggle="modal" data-bs-target="#editSpaceModal" 
                                                        data-space-id="<?= $space['Space_ID'] ?>" 
                                                        data-space-name="<?= htmlspecialchars($space['Name']) ?>" 
                                                        data-space-type="<?= $space['SpaceType_ID'] ?>" 
                                                        data-space-price="<?= $space['Price'] ?>"
                                                        data-bedrooms="<?= $space['Bedrooms'] ?? 0 ?>"
                                                        data-toilets="<?= $space['Toilets'] ?? 0 ?>"
                                                        data-square-meters="<?= $space['Square_Meters'] ?? '' ?>"
                                                        data-has-water="<?= $space['Has_Water'] ?? 0 ?>"
                                                        data-has-electricity="<?= $space['Has_Electricity'] ?? 0 ?>"
                                                        data-furnished="<?= $space['Furnished'] ?? 0 ?>"
                                                        data-air-conditioning="<?= $space['Air_Conditioning'] ?? 0 ?>"
                                                        data-parking="<?= $space['Parking'] ?? 0 ?>"
                                                        data-internet="<?= $space['Internet'] ?? 0 ?>">
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

                                <div class="mobile-card-detail">
                                    <span class="label">Utilities:</span>
                                    <span class="value">
                                        <div class="compact-utilities">
                                            <?php if ($space['Bedrooms'] > 0): ?>
                                                <span class="compact-utility">
                                                    <i class="fas fa-bed"></i> <?= $space['Bedrooms'] ?> BR
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($space['Toilets'] > 0): ?>
                                                <span class="compact-utility">
                                                    <i class="fas fa-bath"></i> <?= $space['Toilets'] ?> Bath
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($space['Square_Meters']): ?>
                                                <span class="compact-utility">
                                                    <i class="fas fa-ruler-combined"></i> <?= $space['Square_Meters'] ?>m²
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="utilities-badges mt-1">
                                            <?php if ($space['Has_Water']): ?>
                                                <span class="utility-badge">
                                                    <i class="fas fa-tint"></i> Water
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($space['Has_Electricity']): ?>
                                                <span class="utility-badge">
                                                    <i class="fas fa-bolt"></i> Electricity
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($space['Furnished']): ?>
                                                <span class="utility-badge">
                                                    <i class="fas fa-couch"></i> Furnished
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($space['Air_Conditioning']): ?>
                                                <span class="utility-badge">
                                                    <i class="fas fa-snowflake"></i> AC
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($space['Parking']): ?>
                                                <span class="utility-badge">
                                                    <i class="fas fa-car"></i> Parking
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($space['Internet']): ?>
                                                <span class="utility-badge">
                                                    <i class="fas fa-wifi"></i> Internet
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </span>
                                </div>

                                <div class="space-actions mt-2">
                                    <button type="button" class="btn-edit-space" data-bs-toggle="modal" data-bs-target="#editSpaceModal" 
                                            data-space-id="<?= $space['Space_ID'] ?>" 
                                            data-space-name="<?= htmlspecialchars($space['Name']) ?>" 
                                            data-space-type="<?= $space['SpaceType_ID'] ?>" 
                                            data-space-price="<?= $space['Price'] ?>"
                                            data-bedrooms="<?= $space['Bedrooms'] ?? 0 ?>"
                                            data-toilets="<?= $space['Toilets'] ?? 0 ?>"
                                            data-square-meters="<?= $space['Square_Meters'] ?? '' ?>"
                                            data-has-water="<?= $space['Has_Water'] ?? 0 ?>"
                                            data-has-electricity="<?= $space['Has_Electricity'] ?? 0 ?>"
                                            data-furnished="<?= $space['Furnished'] ?? 0 ?>"
                                            data-air-conditioning="<?= $space['Air_Conditioning'] ?? 0 ?>"
                                            data-parking="<?= $space['Parking'] ?? 0 ?>"
                                            data-internet="<?= $space['Internet'] ?? 0 ?>">
                                        <i class="fas fa-edit me-1"></i> Edit Space
                                    </button>
                                </div>

                                <div class="mobile-photo-grid">
                                    <?php foreach ($current_photos as $photo): ?>
                                        <div class="mobile-photo-item">
                                            <div class="photo-with-utilities">
                                                <img src="../uploads/unit_photos/<?= htmlspecialchars($photo['Photo_Path']) ?>" alt="Space Photo">
                                                
                                                <!-- NEW: Utilities Overlay for Mobile -->
                                                <div class="utilities-overlay">
                                                    <?php if ($space['Bedrooms'] > 0): ?>
                                                        <i class="fas fa-bed utility-icon"></i>
                                                        <span class="utility-count"><?= $space['Bedrooms'] ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($space['Toilets'] > 0): ?>
                                                        <i class="fas fa-bath utility-icon"></i>
                                                        <span class="utility-count"><?= $space['Toilets'] ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
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

    <!-- Edit Space Modal with Utilities -->
    <div class="modal fade" id="editSpaceModal" tabindex="-1" aria-labelledby="editSpaceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
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

                        <!-- NEW: Utilities Section in Edit Modal -->
                        <div class="utilities-section">
                            <h6 class="fw-semibold mb-3">
                                <i class="fas fa-home me-2"></i>Unit Utilities & Features
                            </h6>
                            
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label for="edit_bedrooms" class="form-label small fw-semibold">Bedrooms</label>
                                    <select class="form-select form-select-sm" id="edit_bedrooms" name="bedrooms">
                                        <option value="0">0 Bedrooms</option>
                                        <option value="1">1 Bedroom</option>
                                        <option value="2">2 Bedrooms</option>
                                        <option value="3">3 Bedrooms</option>
                                        <option value="4">4 Bedrooms</option>
                                        <option value="5">5+ Bedrooms</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="edit_toilets" class="form-label small fw-semibold">Toilets</label>
                                    <select class="form-select form-select-sm" id="edit_toilets" name="toilets">
                                        <option value="0">0 Toilets</option>
                                        <option value="1">1 Toilet</option>
                                        <option value="2">2 Toilets</option>
                                        <option value="3">3 Toilets</option>
                                        <option value="4">4+ Toilets</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="edit_square_meters" class="form-label small fw-semibold">Area (Square Meters)</label>
                                    <input type="number" class="form-control form-control-sm" id="edit_square_meters" name="square_meters" min="0" step="0.5" placeholder="e.g., 25.5">
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="has_water" id="edit_has_water" value="1">
                                        <label class="form-check-label small" for="edit_has_water">
                                            <i class="fas fa-tint me-1 text-primary"></i>Water
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="has_electricity" id="edit_has_electricity" value="1">
                                        <label class="form-check-label small" for="edit_has_electricity">
                                            <i class="fas fa-bolt me-1 text-warning"></i>Electricity
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="furnished" id="edit_furnished" value="1">
                                        <label class="form-check-label small" for="edit_furnished">
                                            <i class="fas fa-couch me-1 text-success"></i>Furnished
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="air_conditioning" id="edit_air_conditioning" value="1">
                                        <label class="form-check-label small" for="edit_air_conditioning">
                                            <i class="fas fa-snowflake me-1 text-info"></i>Air Conditioning
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="parking" id="edit_parking" value="1">
                                        <label class="form-check-label small" for="edit_parking">
                                            <i class="fas fa-car me-1 text-secondary"></i>Parking
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="internet" id="edit_internet" value="1">
                                        <label class="form-check-label small" for="edit_internet">
                                            <i class="fas fa-wifi me-1 text-purple"></i>Internet
                                        </label>
                                    </div>
                                </div>
                            </div>
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

        // Edit Space Modal with Utilities
        const editSpaceModal = document.getElementById('editSpaceModal');
        if (editSpaceModal) {
            editSpaceModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const spaceId = button.getAttribute('data-space-id');
                const spaceName = button.getAttribute('data-space-name');
                const spaceType = button.getAttribute('data-space-type');
                const spacePrice = button.getAttribute('data-space-price');
                
                // NEW: Get utilities data
                const bedrooms = button.getAttribute('data-bedrooms') || '0';
                const toilets = button.getAttribute('data-toilets') || '0';
                const squareMeters = button.getAttribute('data-square-meters') || '';
                const hasWater = button.getAttribute('data-has-water') === '1';
                const hasElectricity = button.getAttribute('data-has-electricity') === '1';
                const furnished = button.getAttribute('data-furnished') === '1';
                const airConditioning = button.getAttribute('data-air-conditioning') === '1';
                const parking = button.getAttribute('data-parking') === '1';
                const internet = button.getAttribute('data-internet') === '1';
                
                const modal = this;
                modal.querySelector('#edit_space_id').value = spaceId;
                modal.querySelector('#edit_space_name').value = spaceName;
                modal.querySelector('#edit_space_type').value = spaceType;
                modal.querySelector('#edit_space_price').value = spacePrice;
                
                // NEW: Set utilities values
                modal.querySelector('#edit_bedrooms').value = bedrooms;
                modal.querySelector('#edit_toilets').value = toilets;
                modal.querySelector('#edit_square_meters').value = squareMeters;
                modal.querySelector('#edit_has_water').checked = hasWater;
                modal.querySelector('#edit_has_electricity').checked = hasElectricity;
                modal.querySelector('#edit_furnished').checked = furnished;
                modal.querySelector('#edit_air_conditioning').checked = airConditioning;
                modal.querySelector('#edit_parking').checked = parking;
                modal.querySelector('#edit_internet').checked = internet;
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
            console.log('Space & Unit Management page fully loaded with utilities integration');
        });
    </script>
</body>
</html>
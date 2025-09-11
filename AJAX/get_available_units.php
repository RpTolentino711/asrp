<?php
// ajax/get_available_units.php
require '../database/database.php';
session_start();

header('Content-Type: application/json');

try {
    $db = new Database();
    
    $is_logged_in = isset($_SESSION['client_id']);
    $client_is_inactive = false;
    $hide_client_rented_unit_ids = [];
    
    if ($is_logged_in) {
        $hide_client_rented_unit_ids = $db->getClientRentedUnitIds($_SESSION['client_id']);
        if (isset($_SESSION['C_status'])) {
            $client_is_inactive = ($_SESSION['C_status'] == 0 || $_SESSION['C_status'] === 'inactive');
        }
    }
    
    $available_units = $db->getHomepageAvailableUnits(10);
    
    if (empty($available_units)) {
        echo json_encode([
            'success' => false,
            'message' => 'No available units found'
        ]);
        exit;
    }
    
    $html = '';
    $modal_counter = 0;
    $modals_html = '';
    
    foreach ($available_units as $space) {
        if (in_array($space['Space_ID'], $hide_client_rented_unit_ids)) continue;
        
        $modal_counter++;
        $modal_id = "unitModal" . $modal_counter;
        $photo_modal_id = "photoModal" . $modal_counter;
        
        // Multi-photo display logic
        $photo_urls = [];
        for ($i = 1; $i <= 5; $i++) {
            $photo_field = "Photo$i";
            if (!empty($space[$photo_field])) {
                $photo_urls[] = "uploads/unit_photos/" . htmlspecialchars($space[$photo_field]);
            }
        }
        if (empty($photo_urls) && !empty($space['Photo'])) {
            $photo_urls[] = "uploads/unit_photos/" . htmlspecialchars($space['Photo']);
        }
        
        // Build unit card HTML
        $html .= '<div class="col-lg-4 col-md-6 animate-on-scroll">
          <div class="card unit-card">';
        
        if (!empty($photo_urls)) {
            $html .= '<img src="' . $photo_urls[0] . '" class="card-img-top" alt="' . htmlspecialchars($space['Name']) . '" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#' . $photo_modal_id . '">';
        } else {
            $html .= '<div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 250px;">
                <i class="fa-solid fa-house text-primary" style="font-size: 4rem;"></i>
              </div>';
        }
        
        $html .= '<div class="card-body">
              <h5 class="card-title fw-bold">' . htmlspecialchars($space['Name']) . '</h5>
              <p class="unit-price">₱' . number_format($space['Price'], 0) . ' / month</p>
              <p class="card-text text-muted">Premium commercial space in a strategic location.</p>
              <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="unit-type">' . htmlspecialchars($space['SpaceTypeName']) . '</span>
                <small class="unit-location">' . htmlspecialchars($space['City']) . '</small>
              </div>';
        
        if ($is_logged_in && !$client_is_inactive) {
            $html .= '<button class="btn btn-accent w-100" data-bs-toggle="modal" data-bs-target="#' . $modal_id . '">
                  <i class="bi bi-key me-2"></i>Rent Now
                </button>';
        } elseif ($is_logged_in && $client_is_inactive) {
            $html .= '<button class="btn btn-secondary w-100" disabled>
                  Account Inactive
                </button>';
        } else {
            $html .= '<button class="btn btn-accent w-100" data-bs-toggle="modal" data-bs-target="#loginModal">
                  <i class="bi bi-key me-2"></i>Login to Rent
                </button>';
        }
        
        $html .= '</div></div></div>';
        
        // Build photo modal
        $modals_html .= '<div class="modal fade" id="' . $photo_modal_id . '" tabindex="-1" aria-labelledby="' . $photo_modal_id . 'Label" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content bg-dark">
              <div class="modal-header border-0">
                <h5 class="modal-title text-white" id="' . $photo_modal_id . 'Label">
                  Photo Gallery: ' . htmlspecialchars($space['Name']) . '
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body text-center">';
        
        if (count($photo_urls) === 1) {
            $modals_html .= '<img src="' . $photo_urls[0] . '" alt="Unit Photo Zoom" class="img-fluid rounded shadow" style="max-height:60vh;">';
        } else {
            $modals_html .= '<div id="zoomCarousel' . $modal_counter . '" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner">';
            
            foreach ($photo_urls as $idx => $url) {
                $active_class = $idx === 0 ? ' active' : '';
                $modals_html .= '<div class="carousel-item' . $active_class . '">
                          <img src="' . $url . '" class="d-block mx-auto img-fluid rounded shadow" alt="Zoom Photo ' . ($idx + 1) . '" style="max-height:60vh;">
                        </div>';
            }
            
            $modals_html .= '</div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#zoomCarousel' . $modal_counter . '" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#zoomCarousel' . $modal_counter . '" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                  </div>';
        }
        
        $modals_html .= '</div>
            </div>
          </div>
        </div>';
        
        // Build rental modal
        if ($is_logged_in && !$client_is_inactive) {
            $modals_html .= '<div class="modal fade" id="' . $modal_id . '" tabindex="-1" aria-labelledby="' . $modal_id . 'Label" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="' . $modal_id . 'Label">Contact Admin to Rent: ' . htmlspecialchars($space['Name']) . '</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        To rent this unit and receive an invoice, please contact our admin team for rental approval.
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="fw-bold">Admin Contact:</h6>
                                            <p class="mb-1"><i class="bi bi-envelope me-2"></i><a href="mailto:rom_telents@asrt.com">rom_telents@asrt.com</a></p>
                                            <p class="mb-3"><i class="bi bi-telephone me-2"></i><a href="tel:+639171234567">+63 917 123 4567</a></p>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="alert alert-warning">
                                                <strong>Invoice Required:</strong><br>
                                                Please request your invoice from the admin for the rental process.
                                            </div>
                                        </div>
                                    </div>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item"><strong>Price:</strong> ₱' . number_format($space['Price'], 0) . ' per month</li>
                                        <li class="list-group-item"><strong>Unit Type:</strong> ' . htmlspecialchars($space['SpaceTypeName']) . '</li>
                                        <li class="list-group-item"><strong>Location:</strong> ' . htmlspecialchars($space['Street']) . ', ' . htmlspecialchars($space['Brgy']) . ', ' . htmlspecialchars($space['City']) . '</li>
                                    </ul>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <a href="rent_request.php?space_id=' . urlencode($space['Space_ID']) . '" class="btn btn-success">
                                        <i class="bi bi-receipt me-2"></i>Request Invoice
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>';
        }
    }
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'modals' => $modals_html,
        'count' => $modal_counter
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error loading available units',
        'error' => $e->getMessage()
    ]);
}
?>
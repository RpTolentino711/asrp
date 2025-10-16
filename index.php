<?php
require 'database/database.php';
session_start();

// Create an instance of the Database class
$db = new Database();

$is_logged_in = isset($_SESSION['client_id']);
$client_username = '';
$client_is_inactive = false; 

// --- Session and Status Handling ---
if ($is_logged_in) {
    $client_id = $_SESSION['client_id'];
    if (isset($_SESSION['C_username'])) {
        $client_username = $_SESSION['C_username'];
        if (!isset($_SESSION['C_status'])) {
            $status_record = $db->getClientStatus($client_id);
            if ($status_record) {
                $_SESSION['C_status'] = $status_record['Status'];
                $client_is_inactive = ($status_record['Status'] == 0 || $status_record['Status'] === 'inactive');
            }
        } else {
            $client_is_inactive = ($_SESSION['C_status'] == 0 || $_SESSION['C_status'] === 'inactive');
        }
    } else {
        $details = $db->getClientFullDetails($client_id);
        if ($details) {
            $_SESSION['C_username'] = $details['C_username'];
            $_SESSION['C_status'] = $details['Status'];
            $client_username = $details['Client_fn'] && $details['Client_ln'] ? "{$details['Client_fn']} {$details['Client_ln']}" : $details['C_username'];
            $client_is_inactive = ($details['Status'] == 0 || $details['Status'] === 'inactive');
        }
    }
}

// --- Fetch ALL Data for the Page Using Clean Methods ---
$hide_client_rented_unit_ids = $is_logged_in ? $db->getClientRentedUnitIds($_SESSION['client_id']) : [];
$available_units = $db->getHomepageAvailableUnits(20);
$rented_units_display = $db->getHomepageRentedUnits(20);
$job_types_display = $db->getAllJobTypes();
$testimonials = $db->getHomepageTestimonials(20);
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ASRT Commercial Spaces - Premium Business Solutions</title>
  
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
  
  <!-- Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  
  <!-- Swiper -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">

  <style>
    :root {
      --primary: #1e40af;
      --primary-light: #3b82f6;
      --primary-dark: #1e3a8a;
      --secondary: #0f172a;
      --accent: #ff385c;
      --accent-light: #ff6b81;
      --success: #059669;
      --warning: #d97706;
      --light: #f8fafc;
      --lighter: #ffffff;
      --gray: #64748b;
      --gray-light: #e2e8f0;
      --gray-dark: #334155;
      --gradient-primary: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      --gradient-accent: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
      --gradient-success: linear-gradient(135deg, var(--success) 0%, #10b981 100%);
      --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
      --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.12);
      --shadow-xl: 0 16px 40px rgba(0, 0, 0, 0.15);
      --border-radius: 16px;
      --border-radius-sm: 8px;
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      line-height: 1.6;
      color: var(--secondary);
      background: var(--light);
      overflow-x: hidden;
    }

    html {
      scroll-behavior: smooth;
    }

    /* Navigation */
    .navbar {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      box-shadow: var(--shadow-sm);
      border-bottom: 1px solid var(--gray-light);
      transition: var(--transition);
    }

    .navbar-brand {
      font-family: 'Playfair Display', serif;
      font-weight: 700;
      font-size: 1.5rem;
      color: var(--primary) !important;
    }

    .nav-link {
      font-weight: 500;
      color: var(--gray-dark) !important;
      transition: var(--transition);
      position: relative;
    }

    .nav-link:hover {
      color: var(--primary) !important;
    }

    .nav-link::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 50%;
      width: 0;
      height: 2px;
      background: var(--primary);
      transition: var(--transition);
      transform: translateX(-50%);
    }

    .nav-link:hover::after {
      width: 100%;
    }

    /* Buttons */
    .btn {
      font-weight: 600;
      border-radius: var(--border-radius-sm);
      padding: 0.75rem 1.5rem;
      border: none;
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }

    .btn-primary {
      background: var(--gradient-primary);
      color: white;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
    }

    .btn-accent {
      background: var(--gradient-accent);
      color: white;
    }

    .btn-accent:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
    }

    .btn-outline-primary {
      border: 2px solid var(--primary);
      color: var(--primary);
      background: transparent;
    }

    .btn-outline-primary:hover {
      background: var(--primary);
      color: white;
      transform: translateY(-2px);
    }

    /* Hero Section */
    .hero-section {
      position: relative;
      height: 100vh;
      overflow: hidden;
      display: flex;
      align-items: center;
    }

    .hero-carousel {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: 1;
    }

    .hero-image {
      width: 100%;
      height: 100vh;
      object-fit: cover;
      filter: brightness(0.7);
    }

    .hero-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, rgba(30, 64, 175, 0.8) 0%, rgba(30, 58, 138, 0.6) 100%);
      z-index: 2;
    }

    .hero-content {
      position: relative;
      z-index: 3;
      color: white;
      max-width: 600px;
      animation: fadeInUp 1s ease-out;
    }

    .hero-title {
      font-family: 'Playfair Display', serif;
      font-size: 3.5rem;
      font-weight: 700;
      line-height: 1.2;
      margin-bottom: 1.5rem;
    }

    .hero-subtitle {
      font-size: 1.25rem;
      font-weight: 400;
      margin-bottom: 2rem;
      opacity: 0.9;
    }

    /* Section Headings */
    .section-title {
      text-align: center;
      margin-bottom: 4rem;
    }

    .section-title h2 {
      font-family: 'Playfair Display', serif;
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--secondary);
      margin-bottom: 1rem;
      position: relative;
    }

    .section-title h2::after {
      content: '';
      position: absolute;
      bottom: -10px;
      left: 50%;
      transform: translateX(-50%);
      width: 80px;
      height: 4px;
      background: var(--gradient-primary);
      border-radius: 2px;
    }

    .section-title p {
      font-size: 1.1rem;
      color: var(--gray);
      max-width: 600px;
      margin: 0 auto;
    }

    /* Airbnb-style Unit Cards */
    .unit-card {
      border: none;
      border-radius: 16px;
      overflow: hidden;
      background: #fff;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
    }

    .unit-card:hover {
      transform: translateY(-6px);
      box-shadow: 0 12px 40px rgba(0,0,0,0.15);
    }

    .card-img-top {
      height: 260px;
      object-fit: cover;
      transition: transform 0.4s ease;
    }

    .unit-card:hover .card-img-top {
      transform: scale(1.05);
    }

    /* Unit Cards */
    .unit-price {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--secondary);
    }

    .unit-price-amount {
      color: var(--accent);
      font-weight: 800;
    }

    .unit-type {
      background: linear-gradient(135deg, var(--primary-light), var(--primary));
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-size: 0.875rem;
      font-weight: 500;
    }

    .unit-location {
      color: var(--gray);
      font-size: 0.9rem;
    }

    /* Available Units Section */
    .available-units {
      padding: 6rem 0;
      background: var(--lighter);
    }

    /* Rented Units Section */
    .rented-units {
      padding: 6rem 0;
      background: var(--light);
    }

    /* Airbnb-style Badges */
    .rented-badge {
      position: absolute;
      top: 1rem;
      left: 1rem;
      background: linear-gradient(135deg, #059669, #10b981);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-size: 0.875rem;
      font-weight: 600;
      box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
      z-index: 2;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .photo-count-badge {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: rgba(255, 255, 255, 0.95);
      color: var(--secondary);
      padding: 0.4rem 0.8rem;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      z-index: 2;
      backdrop-filter: blur(10px);
    }

    /* Handyman Section */
    .handyman-section {
      padding: 6rem 0;
      background: var(--lighter);
    }

    .handyman-card {
      background: var(--lighter);
      border-radius: var(--border-radius);
      padding: 2rem;
      box-shadow: var(--shadow-md);
      transition: var(--transition);
      text-align: center;
      border: 1px solid var(--gray-light);
      height: 100%;
    }

    .handyman-card:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-xl);
      border-color: var(--primary-light);
    }

    .handyman-icon {
      width: 80px;
      height: 80px;
      margin-bottom: 1.5rem;
      transition: var(--transition);
    }

    .handyman-card:hover .handyman-icon {
      transform: scale(1.1);
    }

    /* Testimonials */
    .testimonials-section {
      padding: 6rem 0;
      background: var(--light);
    }

    .testimonial-card {
      background: var(--lighter);
      border-radius: var(--border-radius);
      padding: 2rem;
      box-shadow: var(--shadow-md);
      height: 100%;
      position: relative;
    }

    .testimonial-stars {
      color: #fbbf24;
      margin-bottom: 1rem;
    }

    .testimonial-text {
      font-style: italic;
      font-size: 1.1rem;
      line-height: 1.6;
      margin-bottom: 1.5rem;
    }

    .testimonial-author {
      font-weight: 600;
      color: var(--secondary);
    }

    /* Contact Section */
    .contact-section {
      padding: 6rem 0;
      background: var(--lighter);
    }

    .contact-card {
      background: var(--lighter);
      border-radius: var(--border-radius);
      padding: 2rem;
      box-shadow: var(--shadow-md);
      text-align: center;
      border: 1px solid var(--gray-light);
      height: 100%;
      transition: var(--transition);
    }

    .contact-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-lg);
    }

    .contact-icon {
      font-size: 2.5rem;
      color: var(--primary);
      margin-bottom: 1rem;
    }

    /* Map */
    .map-container {
      border-radius: var(--border-radius);
      overflow: hidden;
      box-shadow: var(--shadow-lg);
    }

    /* Floating Message Button */
    .floating-message {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      width: 60px;
      height: 60px;
      background: var(--gradient-primary);
      color: white;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      box-shadow: var(--shadow-lg);
      cursor: pointer;
      transition: var(--transition);
      z-index: 1000;
    }

    .floating-message:hover {
      transform: scale(1.1);
      box-shadow: var(--shadow-xl);
    }

    /* Animations */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(40px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .fade-in-up {
      animation: fadeInUp 0.8s ease-out;
    }

    .animate-on-scroll {
      opacity: 0;
      transform: translateY(40px);
      transition: all 0.8s ease-out;
    }

    .animate-on-scroll.animate {
      opacity: 1;
      transform: translateY(0);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .hero-title {
        font-size: 2.5rem;
      }
      
      .hero-subtitle {
        font-size: 1.1rem;
      }
      
      .section-title h2 {
        font-size: 2rem;
      }
      
      .floating-message {
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
        bottom: 1rem;
        right: 1rem;
      }
    }

    @media (max-width: 576px) {
      .hero-section {
        height: 70vh;
      }
      
      .hero-title {
        font-size: 2rem;
      }
      
      .btn {
        padding: 0.625rem 1.25rem;
        font-size: 0.9rem;
      }
    }

    /* Swiper customization */
    .swiper-pagination-bullet {
      background: var(--primary);
    }

    .swiper-pagination-bullet-active {
      background: var(--primary-dark);
    }

    /* Modal improvements */
    .modal-content {
      border-radius: var(--border-radius);
      border: none;
      box-shadow: var(--shadow-xl);
    }

    .modal-header {
      border-bottom: 1px solid var(--gray-light);
      border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    .modal-footer {
      border-top: 1px solid var(--gray-light);
    }

    /* Airbnb-style Utilities */
    .utilities-overlay {
      position: absolute;
      top: 8px;
      left: 8px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      color: var(--secondary);
      padding: 8px 12px;
      border-radius: 12px;
      font-size: 0.8rem;
      display: flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      z-index: 2;
    }

    .utility-icon {
      font-size: 0.8rem;
      color: var(--primary);
    }

    .utility-count {
      font-weight: 600;
      margin-right: 4px;
    }

    .utilities-badges {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-bottom: 1rem;
    }

    .utility-badge {
      background: rgba(99, 102, 241, 0.08);
      color: #6366f1;
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 0.75rem;
      border: 1px solid rgba(99, 102, 241, 0.2);
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-weight: 500;
    }

    /* Description text */
    .description-text {
      line-height: 1.5;
      word-wrap: break-word;
      white-space: pre-wrap;
    }

    /* Tooltip customization */
    .tooltip {
      max-width: 400px !important;
    }

    .tooltip-inner {
      max-width: 400px;
      white-space: pre-wrap;
      text-align: left;
      padding: 8px 12px;
    }

    /* Airbnb-style buttons */
    .btn-airbnb {
      background: var(--gradient-accent);
      color: white;
      font-weight: 600;
      border-radius: 8px;
      padding: 12px 24px;
      border: none;
      transition: all 0.3s ease;
    }

    .btn-airbnb:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(255, 56, 92, 0.3);
    }

    /* Card body improvements */
    .card-body {
      padding: 1.5rem;
    }

    .card-title {
      font-size: 1.25rem;
      color: #222;
      margin-bottom: 0.5rem;
      font-weight: 600;
    }

    /* Photo modal improvements */
    .carousel-indicators button {
      width: 60px;
      height: 40px;
      margin: 0 2px;
      border: none;
      background-size: cover;
      background-position: center;
    }

    /* Enhanced rented units styling */
    .rented-unit-card {
      position: relative;
      overflow: hidden;
    }

    .rented-unit-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(5, 150, 105, 0.1), rgba(16, 185, 129, 0.05));
      z-index: 1;
      pointer-events: none;
    }

    .rented-unit-card .card-img-top {
      filter: brightness(0.95);
    }
  </style>
</head>

<body>

<!-- Show alerts for login messages -->
<?php if (isset($_GET['free_msg']) && $_GET['free_msg'] === 'sent'): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      Swal.fire({
        icon: 'success',
        title: 'Message Sent!',
        text: 'We\'ll be there for you shortly.',
        confirmButtonColor: '#3085d6'
      });
    });
  </script>
<?php endif; ?>

<?php
if (isset($_SESSION['login_error'])) {
  echo "<script>
          document.addEventListener('DOMContentLoaded', function() {
              Swal.fire({ 
                  icon: 'error', 
                  title: 'Login Failed', 
                  text: '" . addslashes($_SESSION['login_error']) . "' 
              });
          });
        </script>";
  unset($_SESSION['login_error']);
}
?>

<?php if ($is_logged_in && $client_is_inactive): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      Swal.fire({
        icon: 'error',
        title: 'Account Inactive',
        text: 'Your account is currently inactive. Please contact the administrator.',
        confirmButtonColor: '#d33'
      });
    });
  </script>
<?php endif; ?>

<?php require('header.php'); ?>

  <!-- Hero Section -->
  <section id="home" class="hero-section">
    <div class="hero-carousel">
      <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="4000">
        <div class="carousel-inner">
          <div class="carousel-item active">
            <img src="IMG/show/two.jfif" alt="ASRT Commercial Spaces 1" class="hero-image">
          </div>
          <div class="carousel-item">
            <img src="IMG/show/three.jfif" alt="ASRT Commercial Spaces 2" class="hero-image">
          </div>
          <div class="carousel-item">
            <img src="IMG/show/One.jfif" alt="ASRT Commercial Spaces 3" class="hero-image">
          </div>
        </div>
      </div>
    </div>
    
    <div class="hero-overlay"></div>
    
    <div class="container">
      <div class="row">
        <div class="col-lg-8">
          <div class="hero-content">
            <h1 class="hero-title">Welcome to ASRT Commercial Spaces</h1>
            <p class="hero-subtitle">Your partner in secure, reliable, and flexible commercial leasing. Our mission is to empower businesses with modern, well-equipped workspaces and outstanding service.</p>
            <div class="d-flex gap-3 flex-wrap">
              <a href="#units" class="btn btn-accent btn-lg">Explore Units</a>
              <form action="about.php" method="get" style="display: inline;">
                <button type="submit" class="btn btn-light btn-lg text-dark" style="border: 1px solid #ccc;">About Us</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Available Units Section -->
  <section id="units" class="available-units">
    <div class="container">
      <div class="section-title animate-on-scroll">
        <h2>Available Units</h2>
        <p>Choose from our carefully selected commercial spaces, each designed to meet your unique business needs.</p>
      </div>

      <div class="row g-4">
        <?php
        if (!empty($available_units)) {
            $modal_counter = 0;
            $modals = '';
            foreach ($available_units as $space) {
                if (in_array($space['Space_ID'], $hide_client_rented_unit_ids)) continue;
                $modal_counter++;
                $modal_id = "unitModal" . $modal_counter;
                $photo_modal_id = "photoModal" . $modal_counter;

                // Get photos with descriptions from photo_history table
                $photos_with_descriptions = [];
                
                // Get active photos from photo_history table
                $current_photos = $db->getCurrentSpacePhotos($space['Space_ID']);
                if (!empty($current_photos)) {
                    foreach ($current_photos as $photo) {
                        if (!empty($photo['Photo_Path']) && $photo['Status'] === 'active') {
                            $photos_with_descriptions[] = [
                                'url' => "uploads/unit_photos/" . htmlspecialchars($photo['Photo_Path']),
                                'description' => !empty($photo['description']) ? htmlspecialchars($photo['description']) : 'Unit Photo',
                                'full_description' => !empty($photo['description']) ? htmlspecialchars($photo['description']) : ''
                            ];
                        }
                    }
                }
                
                // Fallback for backward compatibility
                $photo_fields = ['Photo1', 'Photo2', 'Photo3', 'Photo4', 'Photo5'];
                foreach ($photo_fields as $photo_field) {
                    if (!empty($space[$photo_field])) {
                        $photos_with_descriptions[] = [
                            'url' => "uploads/unit_photos/" . htmlspecialchars($space[$photo_field]),
                            'description' => 'Unit Photo',
                            'full_description' => ''
                        ];
                    }
                }
                
                // Limit to maximum 6 photos for display
                $photos_with_descriptions = array_slice($photos_with_descriptions, 0, 6);
                $photo_count = count($photos_with_descriptions);
                $first_photo_url = !empty($photos_with_descriptions) ? $photos_with_descriptions[0]['url'] : null;

                // Get utilities data for this space
                $utilities = $db->getSpaceUtilities($space['Space_ID']);
                
                // If no utilities found in space_utilities table, check if utilities exist in the space array itself
                if (empty($utilities)) {
                    $utilities = [
                        'Bedrooms' => $space['Bedrooms'] ?? 0,
                        'Toilets' => $space['Toilets'] ?? 0,
                        'Square_Meters' => $space['Square_Meters'] ?? null,
                        'Has_Water' => $space['Has_Water'] ?? 0,
                        'Has_Electricity' => $space['Has_Electricity'] ?? 0,
                        'Furnished' => $space['Furnished'] ?? 0,
                        'Air_Conditioning' => $space['Air_Conditioning'] ?? 0,
                        'Parking' => $space['Parking'] ?? 0,
                        'Internet' => $space['Internet'] ?? 0
                    ];
                }
                ?>
                <div class="col-lg-4 col-md-6 animate-on-scroll">
                  <div class="card unit-card">
                    <?php if (!empty($first_photo_url)): ?>
                      <div style="position:relative;">
                        <img src="<?= $first_photo_url ?>" class="card-img-top" alt="<?= htmlspecialchars($space['Name']) ?>" style="cursor: pointer; height: 260px; object-fit: cover;" 
                             data-bs-toggle="modal" data-bs-target="#<?= $photo_modal_id ?>" 
                             data-bs-slide-to="0">
                        
                        <!-- Utilities Overlay on Photo -->
                        <div class="utilities-overlay">
                          <?php if (!empty($utilities['Bedrooms']) && $utilities['Bedrooms'] > 0): ?>
                            <i class="fas fa-bed utility-icon"></i>
                            <span class="utility-count"><?= $utilities['Bedrooms'] ?></span>
                          <?php endif; ?>
                          <?php if (!empty($utilities['Toilets']) && $utilities['Toilets'] > 0): ?>
                            <i class="fas fa-bath utility-icon"></i>
                            <span class="utility-count"><?= $utilities['Toilets'] ?></span>
                          <?php endif; ?>
                          <?php if (!empty($utilities['Square_Meters'])): ?>
                            <i class="fas fa-ruler-combined utility-icon"></i>
                            <span class="utility-count"><?= $utilities['Square_Meters'] ?>m²</span>
                          <?php endif; ?>
                        </div>

                        <?php if ($photo_count > 1): ?>
                          <span class="photo-count-badge">
                            <i class="bi bi-images me-1"></i><?= $photo_count ?>
                          </span>
                        <?php endif; ?>
                      </div>
                    <?php else: ?>
                      <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 260px;">
                        <i class="fa-solid fa-house text-primary" style="font-size: 4rem;"></i>
                      </div>
                    <?php endif; ?>

                    <div class="card-body">
                      <h5 class="card-title"><?= htmlspecialchars($space['Name']) ?></h5>
                      <p class="unit-price">
                        <span class="unit-price-amount">₱<?= number_format($space['Price'], 0) ?></span> / month
                      </p>
                      <p class="card-text text-muted mb-3"><?= htmlspecialchars($space['Street']) ?>, <?= htmlspecialchars($space['City']) ?></p>
                      
                      <!-- Utilities Badges -->
                      <div class="utilities-badges mb-3">
                        <?php if (!empty($utilities['Bedrooms']) && $utilities['Bedrooms'] > 0): ?>
                          <span class="utility-badge">
                            <i class="fas fa-bed"></i> <?= $utilities['Bedrooms'] ?> BR
                          </span>
                        <?php endif; ?>
                        <?php if (!empty($utilities['Toilets']) && $utilities['Toilets'] > 0): ?>
                          <span class="utility-badge">
                            <i class="fas fa-bath"></i> <?= $utilities['Toilets'] ?> Bath
                          </span>
                        <?php endif; ?>
                        <?php if (!empty($utilities['Square_Meters'])): ?>
                          <span class="utility-badge">
                            <i class="fas fa-ruler-combined"></i> <?= $utilities['Square_Meters'] ?>m²
                          </span>
                        <?php endif; ?>
                      </div>

                      <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="unit-type"><?= htmlspecialchars($space['SpaceTypeName']) ?></span>
                      </div>
                      
                      <?php if ($is_logged_in && !$client_is_inactive): ?>
                        <button class="btn btn-airbnb w-100" data-bs-toggle="modal" data-bs-target="#<?= $modal_id ?>">
                          <i class="bi bi-key me-2"></i>Rent Now
                        </button>
                      <?php elseif ($is_logged_in && $client_is_inactive): ?>
                        <button class="btn btn-secondary w-100" disabled>
                          Account Inactive
                        </button>
                      <?php else: ?>
                        <button class="btn btn-airbnb w-100" data-bs-toggle="modal" data-bs-target="#loginModal">
                          <i class="bi bi-key me-2"></i>Login to Rent
                        </button>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>

                <!-- Photo Modal -->
                <div class="modal fade" id="<?= $photo_modal_id ?>" tabindex="-1" aria-labelledby="<?= $photo_modal_id ?>Label" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content bg-dark">
                      <div class="modal-header border-0">
                        <h5 class="modal-title text-white" id="<?= $photo_modal_id ?>Label">
                          <?= htmlspecialchars($space['Name']) ?>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body text-center">
                        <?php if (count($photos_with_descriptions) === 1): ?>
                          <img src="<?= $photos_with_descriptions[0]['url'] ?>" alt="<?= $photos_with_descriptions[0]['description'] ?>" class="img-fluid rounded shadow" style="max-height:60vh; object-fit: contain;">
                        <?php elseif (count($photos_with_descriptions) > 1): ?>
                          <div id="zoomCarousel<?= $modal_counter ?>" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">
                              <?php foreach ($photos_with_descriptions as $idx => $photo_data): ?>
                                <div class="carousel-item<?= $idx === 0 ? ' active' : '' ?>" data-slide-index="<?= $idx ?>">
                                  <img src="<?= $photo_data['url'] ?>" class="d-block mx-auto img-fluid rounded shadow" alt="<?= $photo_data['description'] ?>" style="max-height:60vh; object-fit: contain;">
                                </div>
                              <?php endforeach; ?>
                            </div>
                            <button class="carousel-control-prev" type="button" data-bs-target="#zoomCarousel<?= $modal_counter ?>" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#zoomCarousel<?= $modal_counter ?>" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Next</span>
                            </button>
                          </div>
                        <?php else: ?>
                          <div class="text-center mb-3" style="font-size:56px;color:#2563eb;">
                            <i class="fa-solid fa-house"></i>
                          </div>
                          <p class="text-white">No photos available for this unit</p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>

                <?php
                // Build rental modal
                if ($is_logged_in && !$client_is_inactive) {
                    $modals .= '
                    <div class="modal fade" id="' . $modal_id . '" tabindex="-1" aria-labelledby="' . $modal_id . 'Label" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="' . $modal_id . 'Label">Rent: ' . htmlspecialchars($space['Name']) . '</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        To rent this unit, please submit your rental request. The admin will review your application and contact you.
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="fw-bold">Contact Information:</h6>
                                            <p class="mb-1"><i class="bi bi-envelope me-2"></i><a href="mailto:management@asrt.space">management@asrt.space</a></p>
                                            <p class="mb-3"><i class="bi bi-telephone me-2"></i><a href="tel:+639451357685">+63 9451357685</a></p>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="alert alert-warning">
                                                <strong>Direct Contact:</strong><br>
                                                Contact the admin directly for immediate assistance.
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
            
            // Output all modals at the end
            echo $modals;
        } else {
            echo '<div class="col-12 text-center">
                    <div class="alert alert-info">No units currently available.</div>
                  </div>';
        }
        ?>
      </div>

      <div class="text-center mt-5">
        <button id="moreUnitsBtn" class="btn btn-outline-primary btn-lg">View More Units</button>
      </div>
    </div>
  </section>

  <!-- Rented Units Section -->
  <section class="rented-units">
    <div class="container">
      <div class="section-title animate-on-scroll">
        <h2>Currently Rented</h2>
        <p>See our successful partnerships with businesses across various industries.</p>
      </div>

      <div class="row g-4">
        <?php
        if (!empty($rented_units_display)) {
          $rented_modal_counter = 0;
          // Get all rented unit photos for the units being displayed
          $rented_unit_ids = array_column($rented_units_display, 'Space_ID');
          $rented_unit_photos = $db->getAllUnitPhotosForUnits($rented_unit_ids);
          foreach ($rented_units_display as $rent) {
            $rented_modal_counter++;
            $rented_modal_id = "rentedModal" . $rented_modal_counter;
            
            // Get photos from JSON array instead of multiple columns
            $rented_photo_urls = [];
            if (!empty($rented_unit_photos[$rent['Space_ID']])) {
              foreach ($rented_unit_photos[$rent['Space_ID']] as $photo) {
                if (!empty($photo)) {
                  $rented_photo_urls[] = "uploads/unit_photos/" . htmlspecialchars($photo);
                }
              }
            }
            ?>
            <div class="col-lg-4 col-md-6 animate-on-scroll">
              <div class="card unit-card rented-unit-card">
                <div class="rented-badge">
                  <i class="bi bi-check-circle me-1"></i>Currently Rented
                </div>
                <?php if (!empty($rented_photo_urls)): ?>
                  <div style="position:relative;">
                    <img src="<?= $rented_photo_urls[0] ?>" class="card-img-top" alt="<?= htmlspecialchars($rent['Name']) ?>" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#<?= $rented_modal_id ?>_photo">
                    <?php if (count($rented_photo_urls) > 1): ?>
                      <span class="photo-count-badge">
                        <i class="bi bi-images me-1"></i><?= count($rented_photo_urls) ?>
                      </span>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 260px;">
                    <i class="fa-solid fa-house-user text-success" style="font-size: 4rem;"></i>
                  </div>
                <?php endif; ?>
                <div class="card-body">
                  <h5 class="card-title"><?= htmlspecialchars($rent['Name']) ?></h5>
                  <p class="unit-price">
                    <span class="unit-price-amount">₱<?= number_format($rent['Price'], 0) ?></span> / month
                  </p>
                  <p class="card-text text-muted mb-3"><?= htmlspecialchars($rent['Street']) ?>, <?= htmlspecialchars($rent['City']) ?></p>
                  
                  <div class="utilities-badges mb-3">
                    <?php if (!empty($rent['Bedrooms']) && $rent['Bedrooms'] > 0): ?>
                      <span class="utility-badge">
                        <i class="fas fa-bed"></i> <?= $rent['Bedrooms'] ?> BR
                      </span>
                    <?php endif; ?>
                    <?php if (!empty($rent['Toilets']) && $rent['Toilets'] > 0): ?>
                      <span class="utility-badge">
                        <i class="fas fa-bath"></i> <?= $rent['Toilets'] ?> Bath
                      </span>
                    <?php endif; ?>
                  </div>

                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="unit-type"><?= htmlspecialchars($rent['SpaceTypeName']) ?></span>
                  </div>
                  <button class="btn btn-outline-success w-100" data-bs-toggle="modal" data-bs-target="#<?= $rented_modal_id ?>">
                    <i class="bi bi-eye me-2"></i>View Details
                  </button>
                </div>
              </div>
            </div>

            <!-- Rented Unit Photo Modal -->
            <div class="modal fade" id="<?= $rented_modal_id ?>_photo" tabindex="-1" aria-labelledby="<?= $rented_modal_id ?>_photoLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content bg-dark">
                  <div class="modal-header border-0">
                    <h5 class="modal-title text-white" id="<?= $rented_modal_id ?>_photoLabel">
                      <?= htmlspecialchars($rent['Name']) ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body text-center">
                    <?php if (count($rented_photo_urls) === 1): ?>
                      <img src="<?= $rented_photo_urls[0] ?>" alt="Unit Photo Zoom" class="img-fluid rounded shadow" style="max-height:60vh;">
                    <?php elseif (count($rented_photo_urls) > 1): ?>
                      <div id="rentedZoomCarousel<?= $rented_modal_counter ?>" class="carousel slide" data-bs-ride="carousel">
                        <div class="carousel-inner">
                          <?php foreach ($rented_photo_urls as $idx => $url): ?>
                            <div class="carousel-item<?= $idx === 0 ? ' active' : '' ?>">
                              <img src="<?= $url ?>" class="d-block mx-auto img-fluid rounded shadow" alt="Zoom Photo <?= $idx+1 ?>" style="max-height:60vh;">
                            </div>
                          <?php endforeach; ?>
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#rentedZoomCarousel<?= $rented_modal_counter ?>" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Previous</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#rentedZoomCarousel<?= $rented_modal_counter ?>" data-bs-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="visually-hidden">Next</span>
                        </button>
                      </div>
                    <?php else: ?>
                      <div class="text-center mb-3" style="font-size:56px;color:#059669;">
                        <i class="fa-solid fa-house-user"></i>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <!-- Rented Unit Modal -->
            <div class="modal fade" id="<?= $rented_modal_id ?>" tabindex="-1" aria-labelledby="<?= $rented_modal_id ?>Label" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold" id="<?= $rented_modal_id ?>Label">
                      <?= htmlspecialchars($rent['Name']) ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <div class="text-center mb-3" style="font-size:56px;color:#059669;">
                      <i class="fa-solid fa-house-user"></i>
                    </div>
                    <ul class="list-group list-group-flush">
                      <li class="list-group-item"><strong>Price:</strong> ₱<?= number_format($rent['Price'], 0) ?> / month</li>
                      <li class="list-group-item"><strong>Unit Type:</strong> <?= htmlspecialchars($rent['SpaceTypeName']) ?></li>
                      <li class="list-group-item"><strong>Location:</strong> <?= htmlspecialchars($rent['Street']) ?>, <?= htmlspecialchars($rent['Brgy']) ?>, <?= htmlspecialchars($rent['City']) ?></li>
                      <li class="list-group-item"><strong>Renter:</strong> <?= htmlspecialchars($rent['Client_fn'].' '.$rent['Client_ln']) ?></li>
                    </ul>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                  </div>
                </div>
              </div>
            </div>
            <?php
          }
        } else {
          echo '<div class="col-12 text-center">
                  <div class="alert alert-info">No units currently rented.</div>
                </div>';
        }
        ?>
      </div>
    </div>
  </section>

  <!-- Handyman Services Section -->
  <section id="services" class="handyman-section">
    <div class="container">
      <div class="section-title animate-on-scroll">
        <h2>Professional Services</h2>
        <p>Comprehensive maintenance and repair services to keep your business running smoothly.</p>
      </div>

      <div class="row g-4 justify-content-center">
        <?php
        // Fallback icons for when database icons don't exist
        $fallback_icons = [
          "CARPENTRY" => "IMG/show/CARPENTRY.png",
          "ELECTRICAL" => "IMG/show/ELECTRICAL.png",
          "PLUMBING" => "IMG/show/PLUMBING.png",
          "PAINTING" => "IMG/show/PAINTING.png",
          "APPLIANCE REPAIR" => "IMG/show/APPLIANCE.png",
          "PIPELINE" => "IMG/show/PLUMBING.png"
        ];

        if (!empty($job_types_display)) {
          foreach ($job_types_display as $row) {
            // Use the actual icon from database with automatic fallback
            $icon_filename = $row['Icon'] ?? 'default-icon.png';
            $img_src = "uploads/jobtype_icons/" . $icon_filename;
            
            // Fallback system with auto-check
            $name_upper = strtoupper($row['JobType_Name']);
            
            // If the uploaded file doesn't exist, use fallback
            if (!file_exists($img_src)) {
                $img_src = isset($fallback_icons[$name_upper]) ? $fallback_icons[$name_upper] : "IMG/show/wifi.png";
            }
            
            // Get handyman count for this job type
            $handyman_count = $handyman_counts[$row['JobType_ID']] ?? 0;
        ?>
        <div class="col-lg-2 col-md-4 col-sm-6 animate-on-scroll">
          <form method="get" action="handyman_type.php">
            <input type="hidden" name="jobtype_id" value="<?= htmlspecialchars($row['JobType_ID']) ?>">
            <button type="submit" class="handyman-card w-100 border-0" 
                    <?= ($is_logged_in && $client_is_inactive) ? 'disabled style="opacity:0.6; cursor:not-allowed;"' : '' ?>>
              <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($row['JobType_Name']) ?>" class="handyman-icon" 
                   onerror="this.src='IMG/show/wifi.png'">
              <h5 class="fw-bold"><?= htmlspecialchars($row['JobType_Name']) ?></h5>
              <p class="text-muted small">
                <?php if ($handyman_count > 0): ?>
                  <span class="text-success">
                    <i class="bi bi-person-check me-1"></i><?= $handyman_count ?> professional<?= $handyman_count > 1 ? 's' : '' ?> available
                  </span>
                <?php else: ?>
                  <span class="text-warning">
                    <i class="bi bi-clock me-1"></i>Check availability
                  </span>
                <?php endif; ?>
              </p>
            </button>
          </form>
        </div>
        <?php
          }
        } else {
          echo '<div class="col-12 text-center">
                  <div class="alert alert-info">No services currently available.</div>
                </div>';
        }
        ?>
      </div>

      <div class="text-center mt-5">
        <a href="handyman_type.php" class="btn btn-primary btn-lg">View All Services</a>
      </div>
    </div>
  </section>

  <!-- Testimonials Section -->
  <section id="testimonials" class="testimonials-section">
    <div class="container">
      <div class="section-title animate-on-scroll">
        <h2>What Our Clients Say</h2>
        <p>Hear from businesses that have found their perfect space with us.</p>
      </div>

      <div class="swiper testimonials-swiper">
        <div class="swiper-wrapper">
          <?php
          if (!empty($testimonials)) {
            foreach ($testimonials as $fb) {
              $stars = str_repeat('<i class="bi bi-star-fill"></i>', $fb['Rating']);
              $stars .= str_repeat('<i class="bi bi-star text-muted"></i>', 5 - $fb['Rating']);
          ?>
          <div class="swiper-slide">
            <div class="testimonial-card">
              <div class="testimonial-stars">
                <?= $stars ?>
              </div>
              <p class="testimonial-text"><?= htmlspecialchars($fb['Comments']) ?></p>
              <div class="testimonial-author"><?= htmlspecialchars($fb['Client_fn'] . ' ' . $fb['Client_ln']) ?></div>
            </div>
          </div>
          <?php
            }
          } else {
            echo '<div class="swiper-slide">
                    <div class="testimonial-card">
                      <p class="testimonial-text">No testimonials available yet.</p>
                    </div>
                  </div>';
          }
          ?>
        </div>
        <div class="swiper-pagination"></div>
      </div>
    </div>
  </section>

  <!-- Contact Section -->
  <section id="contact" class="contact-section">
    <div class="container">
      <div class="section-title animate-on-scroll">
        <h2>Get In Touch</h2>
        <p>Ready to find your ideal commercial space? Contact us today for a consultation.</p>
      </div>

      <div class="row g-4">
        <div class="col-lg-8">
          <div class="map-container animate-on-scroll">
            <iframe 
              src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3872.161920992376!2d121.16322267491122!3d13.948962686463787!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd6c9ea0e9c9bf%3A0xf9daae5e3d997480!2sGen.%20Luna%20St%2C%20Lipa%2C%20Batangas!5e0!3m2!1sen!2sph!4v1748185696621!5m2!1sen!2sph" 
              width="100%" 
              height="400" 
              style="border:0;" 
              allowfullscreen="" 
              loading="lazy" 
              referrerpolicy="no-referrer-when-downgrade">
            </iframe>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="row g-3">
            <div class="col-12 animate-on-scroll">
              <div class="contact-card">
                <div class="contact-icon">
                  <i class="bi bi-telephone"></i>
                </div>
                <h5 class="fw-bold">Call Us</h5>
                <p class="text-muted mb-2">Speak with our leasing specialists</p>
                <a href="tel:+639451357685" class="text-decoration-none">+63 9451357685</a>
              </div>
            </div>

            <div class="col-12 animate-on-scroll">
              <div class="contact-card">
                <div class="contact-icon">
                  <i class="bi bi-envelope"></i>
                </div>
                <h5 class="fw-bold">Email Us</h5>
                <p class="text-muted mb-2">Get detailed information</p>
                <a href="mailto:management@asrt.space" class="text-decoration-none">management@asrt.space</a>
              </div>
            </div>

            <div class="col-12 animate-on-scroll">
              <div class="contact-card">
                <div class="contact-icon">
                  <i class="bi bi-geo-alt"></i>
                </div>
                <h5 class="fw-bold">Visit Us</h5>
                <p class="text-muted mb-2">Our main location</p>
                <address class="mb-0">Gen. Luna St, Lipa, Batangas</address>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Floating Message Button (only for non-logged-in users) -->
  <?php if (!$is_logged_in): ?>
  <div class="floating-message" data-bs-toggle="modal" data-bs-target="#messageModal">
    <i class="bi bi-chat-dots"></i>
  </div>

  <!-- Message Modal -->
  <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post" action="free_message_send.php">
          <div class="modal-header">
            <h5 class="modal-title" id="messageModalLabel"><i class="fas fa-envelope-open-text me-2"></i>Ask us anything!</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="client_name" class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" name="client_name" id="client_name" class="form-control" required pattern="[A-Za-z\s]+" title="Name is required and must contain only letters and spaces">
            </div>
            <div class="mb-3">
              <label for="client_email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="client_email" id="client_email" class="form-control" required title="A valid email is required">
            </div>
            <div class="mb-3">
              <label for="client_phone" class="form-label">Phone</label>
              <input type="text" name="client_phone" id="client_phone" class="form-control" required pattern="\d{11}" maxlength="11" minlength="11" title="Phone number must be exactly 11 digits">
            </div>
            <div class="mb-3">
              <label for="message_text" class="form-label">Your Message <span class="text-danger">*</span></label>
              <textarea name="message_text" id="message_text" class="form-control" rows="4" required></textarea>
            <script>
              // Extra client-side validation for phone field
              document.addEventListener('DOMContentLoaded', function() {
                var phoneInput = document.getElementById('client_phone');
                phoneInput.addEventListener('input', function(e) {
                  this.value = this.value.replace(/[^\d]/g, '').slice(0, 11);
                });
                var form = phoneInput.closest('form');
                form.addEventListener('submit', function(e) {
                  if (!/^\d{11}$/.test(phoneInput.value)) {
                    e.preventDefault();
                    phoneInput.setCustomValidity('Phone number must be exactly 11 digits');
                    phoneInput.reportValidity();
                  } else {
                    phoneInput.setCustomValidity('');
                  }
                });
              });
            </script>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Send Message</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Login Modal -->
  <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="loginModalLabel">Login Required</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <p class="mb-0">Please login first to rent a unit.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <?php require('footer.php'); ?>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- Swiper JS -->
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  
  <!-- Custom JavaScript -->
  <script>
    // Initialize Swiper for testimonials
    const testimonialSwiper = new Swiper('.testimonials-swiper', {
      effect: 'coverflow',
      grabCursor: true,
      centeredSlides: true,
      slidesPerView: 'auto',
      loop: true,
      coverflowEffect: {
        rotate: 50,
        stretch: 0,
        depth: 100,
        modifier: 1,
        slideShadows: false,
      },
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
      autoplay: {
        delay: 4000,
        disableOnInteraction: false,
      },
      breakpoints: {
        320: { slidesPerView: 1, spaceBetween: 20 },
        768: { slidesPerView: 2, spaceBetween: 30 },
        1024: { slidesPerView: 3, spaceBetween: 40 }
      },
    });

    // Scroll animations
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animate');
        }
      });
    }, observerOptions);

    // Observe all elements with animate-on-scroll class
    document.querySelectorAll('.animate-on-scroll').forEach((el) => {
      observer.observe(el);
    });

    // Smooth navbar background change on scroll
    window.addEventListener('scroll', () => {
      const navbar = document.querySelector('.navbar');
      if (window.scrollY > 50) {
        navbar.style.background = 'rgba(255, 255, 255, 0.98)';
        navbar.style.boxShadow = 'var(--shadow-md)';
      } else {
        navbar.style.background = 'rgba(255, 255, 255, 0.95)';
        navbar.style.boxShadow = 'var(--shadow-sm)';
      }
    });

    // More Units button
    document.getElementById('moreUnitsBtn').addEventListener('click', function (e) {
      e.preventDefault();
      Swal.fire({
        icon: 'info',
        title: 'More Units Coming Soon!',
        text: 'We are working on adding more properties. Please check back later!',
        confirmButtonColor: 'var(--primary)'
      });
    });

    // Add hover effects to cards
    document.querySelectorAll('.card').forEach(card => {
      card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-6px)';
      });
      
      card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
      });
    });

    // Handle direct photo navigation when clicking thumbnails in rental modal
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-bs-slide-to]').forEach(element => {
            element.addEventListener('click', function() {
                const slideTo = this.getAttribute('data-bs-slide-to');
                const targetModal = this.getAttribute('data-bs-target');
                
                // Wait for the photo modal to be fully shown
                const photoModal = document.querySelector(targetModal);
                if (photoModal) {
                    photoModal.addEventListener('shown.bs.modal', function() {
                        // Find the carousel within this modal
                        const carousel = this.querySelector('.carousel');
                        if (carousel && slideTo !== null) {
                            const bsCarousel = bootstrap.Carousel.getOrCreateInstance(carousel);
                            bsCarousel.to(parseInt(slideTo));
                        }
                    }, { once: true });
                }
            });
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl, {
                delay: {show: 500, hide: 100}
            });
        });
    });
  </script>
</body>
</html>
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
$available_units = $db->getHomepageAvailableUnits(10);
$rented_units_display = $db->getHomepageRentedUnits(10);
$job_types_display = $db->getAllJobTypes();
$testimonials = $db->getHomepageTestimonials(6);
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
      --accent: #ef4444;
      --accent-light: #f87171;
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

    /* Cards */
    .card {
      border: none;
      border-radius: var(--border-radius);
      box-shadow: var(--shadow-md);
      background: var(--lighter);
      transition: var(--transition);
      overflow: hidden;
      height: 100%;
    }

    .card:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-xl);
    }

    .card-img-top {
      height: 250px;
      object-fit: cover;
      transition: var(--transition);
    }

    .card:hover .card-img-top {
      transform: scale(1.05);
    }

    /* Unit Cards */
    .unit-card {
      position: relative;
      overflow: hidden;
    }

    .unit-price {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--success);
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

    .rented-badge {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: var(--gradient-success);
      color: white;
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-size: 0.875rem;
      font-weight: 600;
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

    /* New items animation */
    .new-item {
      animation: slideInFadeIn 0.6s ease-out;
      border: 2px solid var(--success);
      box-shadow: 0 0 20px rgba(5, 150, 105, 0.3);
    }

    @keyframes slideInFadeIn {
      0% {
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
      }
      100% {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    /* Loading spinner */
    .loading-spinner {
      display: none;
      text-align: center;
      padding: 2rem;
    }

    .spinner-border-custom {
      width: 3rem;
      height: 3rem;
      border: 0.3em solid var(--primary-light);
      border-right-color: transparent;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      100% { transform: rotate(360deg); }
    }

    /* Update notification */
    .update-notification {
      position: fixed;
      top: 100px;
      right: 20px;
      background: var(--gradient-success);
      color: white;
      padding: 1rem 1.5rem;
      border-radius: var(--border-radius-sm);
      box-shadow: var(--shadow-lg);
      z-index: 1050;
      transform: translateX(100%);
      transition: transform 0.3s ease-out;
    }

    .update-notification.show {
      transform: translateX(0);
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
  </style>
</head>

<body>

<!-- Update Notification -->
<div id="updateNotification" class="update-notification">
  <i class="bi bi-check-circle me-2"></i>
  <span id="notificationText">New content available!</span>
</div>

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
                <button type="submit" class="btn btn-black btn-lg">About Us</button>
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

      <div class="loading-spinner" id="availableUnitsLoading">
        <div class="spinner-border-custom"></div>
        <p class="mt-2 text-muted">Loading available units...</p>
      </div>

      <div class="row g-4" id="availableUnitsContainer">
        <!-- Available units will be loaded here via AJAX -->
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

      <div class="loading-spinner" id="rentedUnitsLoading">
        <div class="spinner-border-custom"></div>
        <p class="mt-2 text-muted">Loading rented units...</p>
      </div>

      <div class="row g-4" id="rentedUnitsContainer">
        <!-- Rented units will be loaded here via AJAX -->
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

      <div class="loading-spinner" id="servicesLoading">
        <div class="spinner-border-custom"></div>
        <p class="mt-2 text-muted">Loading services...</p>
      </div>

      <div class="row g-4 justify-content-center" id="servicesContainer">
        <!-- Services will be loaded here via AJAX -->
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

      <div class="loading-spinner" id="testimonialsLoading">
        <div class="spinner-border-custom"></div>
        <p class="mt-2 text-muted">Loading testimonials...</p>
      </div>

      <div class="swiper testimonials-swiper" id="testimonialsContainer">
        <!-- Testimonials will be loaded here via AJAX -->
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
                <a href="tel:+639123456789" class="text-decoration-none">+63 912 345 6789</a>
              </div>
            </div>

            <div class="col-12 animate-on-scroll">
              <div class="contact-card">
                <div class="contact-icon">
                  <i class="bi bi-envelope"></i>
                </div>
                <h5 class="fw-bold">Email Us</h5>
                <p class="text-muted mb-2">Get detailed information</p>
                <a href="mailto:info@asrt.com" class="text-decoration-none">info@asrt.com</a>
              </div>
            </div>

            <div class="col-12 animate-on-scroll">
              <div class="contact-card">
                <div class="contact-icon">
                  <i class="bi bi-geo-alt"></i>
                </div>
                <h5 class="fw-bold">Visit Us</h5>
                <p class="text-muted mb-2">Our main office location</p>
                <address class="mb-0">Gen. Luna St, Lipa, Batangas</address>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Dynamic Modal Container -->
  <div id="dynamicModalsContainer"></div>

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
              <input type="text" name="client_name" id="client_name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="client_email" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="client_email" id="client_email" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="client_phone" class="form-label">Phone</label>
              <input type="text" name="client_phone" id="client_phone" class="form-control">
            </div>
            <div class="mb-3">
              <label for="message_text" class="form-label">Your Message <span class="text-danger">*</span></label>
              <textarea name="message_text" id="message_text" class="form-control" rows="4" required></textarea>
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
    // Global variables
    let testimonialSwiper = null;
    let lastDataHashes = {};
    let isLoggedIn = <?= $is_logged_in ? 'true' : 'false' ?>;
    let clientIsInactive = <?= $client_is_inactive ? 'true' : 'false' ?>;
    let hideClientRentedUnitIds = <?= json_encode($hide_client_rented_unit_ids) ?>;

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
      initializePage();
      startRealTimeUpdates();
    });

    // Initialize all components
    function initializePage() {
      loadAvailableUnits();
      loadRentedUnits();
      loadHandymanServices();
      loadTestimonials();
      initializeScrollAnimations();
      initializeNavbarEffects();
      initializeEventListeners();
    }

    // Start real-time updates
    function startRealTimeUpdates() {
      // Check for updates every 3 seconds
      setInterval(() => {
        checkForUpdates();
      }, 3000);
    }

    // Check for updates
    async function checkForUpdates() {
      try {
        const response = await fetch('ajax/check_updates.php');
        const data = await response.json();
        
        if (data.success) {
          // Check each section for updates
          if (data.hashes.available_units !== lastDataHashes.available_units) {
            loadAvailableUnits(true);
            lastDataHashes.available_units = data.hashes.available_units;
          }
          
          if (data.hashes.rented_units !== lastDataHashes.rented_units) {
            loadRentedUnits(true);
            lastDataHashes.rented_units = data.hashes.rented_units;
          }
          
          if (data.hashes.services !== lastDataHashes.services) {
            loadHandymanServices(true);
            lastDataHashes.services = data.hashes.services;
          }
          
          if (data.hashes.testimonials !== lastDataHashes.testimonials) {
            loadTestimonials(true);
            lastDataHashes.testimonials = data.hashes.testimonials;
          }
        }
      } catch (error) {
        console.error('Error checking for updates:', error);
      }
    }

    // Show update notification
    function showUpdateNotification(message) {
      const notification = document.getElementById('updateNotification');
      const notificationText = document.getElementById('notificationText');
      
      notificationText.textContent = message;
      notification.classList.add('show');
      
      setTimeout(() => {
        notification.classList.remove('show');
      }, 3000);
    }

    // Load available units
    async function loadAvailableUnits(isUpdate = false) {
      const container = document.getElementById('availableUnitsContainer');
      const loading = document.getElementById('availableUnitsLoading');
      
      if (!isUpdate) {
        loading.style.display = 'block';
        container.style.display = 'none';
      }
      
      try {
        const response = await fetch('ajax/get_available_units.php');
        const data = await response.json();
        
        if (data.success) {
          container.innerHTML = data.html;
          
          if (isUpdate) {
            showUpdateNotification('New available units loaded!');
            // Add animation to new items
            container.querySelectorAll('.col-lg-4').forEach(item => {
              item.classList.add('new-item');
              setTimeout(() => item.classList.remove('new-item'), 2000);
            });
          }
        } else {
          container.innerHTML = '<div class="col-12 text-center"><div class="alert alert-info">No units currently available.</div></div>';
        }
      } catch (error) {
        console.error('Error loading available units:', error);
        container.innerHTML = '<div class="col-12 text-center"><div class="alert alert-danger">Error loading units. Please try again.</div></div>';
      } finally {
        if (!isUpdate) {
          loading.style.display = 'none';
          container.style.display = 'flex';
        }
      }
    }

    // Load rented units
    async function loadRentedUnits(isUpdate = false) {
      const container = document.getElementById('rentedUnitsContainer');
      const loading = document.getElementById('rentedUnitsLoading');
      
      if (!isUpdate) {
        loading.style.display = 'block';
        container.style.display = 'none';
      }
      
      try {
        const response = await fetch('ajax/get_rented_units.php');
        const data = await response.json();
        
        if (data.success) {
          container.innerHTML = data.html;
          
          if (isUpdate) {
            showUpdateNotification('Rented units updated!');
            // Add animation to new items
            container.querySelectorAll('.col-lg-4').forEach(item => {
              item.classList.add('new-item');
              setTimeout(() => item.classList.remove('new-item'), 2000);
            });
          }
        } else {
          container.innerHTML = '<div class="col-12 text-center"><div class="alert alert-info">No units currently rented.</div></div>';
        }
      } catch (error) {
        console.error('Error loading rented units:', error);
        container.innerHTML = '<div class="col-12 text-center"><div class="alert alert-danger">Error loading rented units. Please try again.</div></div>';
      } finally {
        if (!isUpdate) {
          loading.style.display = 'none';
          container.style.display = 'flex';
        }
      }
    }

    // Load handyman services
    async function loadHandymanServices(isUpdate = false) {
      const container = document.getElementById('servicesContainer');
      const loading = document.getElementById('servicesLoading');
      
      if (!isUpdate) {
        loading.style.display = 'block';
        container.style.display = 'none';
      }
      
      try {
        const response = await fetch('ajax/get_handyman_services.php');
        const data = await response.json();
        
        if (data.success) {
          container.innerHTML = data.html;
          
          if (isUpdate) {
            showUpdateNotification('Services updated!');
            // Add animation to new items
            container.querySelectorAll('.col-lg-2').forEach(item => {
              item.classList.add('new-item');
              setTimeout(() => item.classList.remove('new-item'), 2000);
            });
          }
        } else {
          container.innerHTML = '<div class="col-12 text-center"><div class="alert alert-info">No services currently available.</div></div>';
        }
      } catch (error) {
        console.error('Error loading handyman services:', error);
        container.innerHTML = '<div class="col-12 text-center"><div class="alert alert-danger">Error loading services. Please try again.</div></div>';
      } finally {
        if (!isUpdate) {
          loading.style.display = 'none';
          container.style.display = 'flex';
        }
      }
    }

    // Load testimonials
    async function loadTestimonials(isUpdate = false) {
      const container = document.getElementById('testimonialsContainer');
      const loading = document.getElementById('testimonialsLoading');
      
      if (!isUpdate) {
        loading.style.display = 'block';
        container.style.display = 'none';
      }
      
      try {
        const response = await fetch('ajax/get_testimonials.php');
        const data = await response.json();
        
        if (data.success) {
          container.innerHTML = data.html;
          
          // Destroy existing swiper if it exists
          if (testimonialSwiper) {
            testimonialSwiper.destroy(true, true);
          }
          
          // Initialize new swiper
          initializeTestimonialSwiper();
          
          if (isUpdate) {
            showUpdateNotification('New testimonials loaded!');
          }
        } else {
          container.innerHTML = '<div class="swiper-wrapper"><div class="swiper-slide"><div class="testimonial-card"><p class="testimonial-text">No testimonials available yet.</p></div></div></div>';
        }
      } catch (error) {
        console.error('Error loading testimonials:', error);
        container.innerHTML = '<div class="swiper-wrapper"><div class="swiper-slide"><div class="testimonial-card"><p class="testimonial-text">Error loading testimonials. Please try again.</p></div></div></div>';
      } finally {
        if (!isUpdate) {
          loading.style.display = 'none';
          container.style.display = 'block';
        }
      }
    }

    // Initialize testimonial swiper
    function initializeTestimonialSwiper() {
      testimonialSwiper = new Swiper('.testimonials-swiper', {
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
    }

    // Initialize scroll animations
    function initializeScrollAnimations() {
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
    }

    // Initialize navbar effects
    function initializeNavbarEffects() {
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
    }

    // Initialize event listeners
    function initializeEventListeners() {
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

      // Add hover effects to cards (delegated event handling)
      document.addEventListener('mouseenter', function(e) {
        if (e.target.classList.contains('card')) {
          e.target.style.transform = 'translateY(-8px)';
        }
      }, true);
      
      document.addEventListener('mouseleave', function(e) {
        if (e.target.classList.contains('card')) {
          e.target.style.transform = 'translateY(0)';
        }
      }, true);

      // Handle dynamic modal events
      document.addEventListener('click', function(e) {
        if (e.target.matches('[data-bs-toggle="modal"]')) {
          const modalId = e.target.getAttribute('data-bs-target');
          if (modalId && modalId.startsWith('#unitModal')) {
            // Handle unit rental modal
            loadUnitModal(modalId);
          } else if (modalId && modalId.startsWith('#photoModal')) {
            // Handle photo modal
            loadPhotoModal(modalId);
          }
        }
      });
    }

    // Load unit modal dynamically
    async function loadUnitModal(modalId) {
      const spaceId = modalId.replace('#unitModal', '');
      
      try {
        const response = await fetch(`ajax/get_unit_modal.php?space_id=${spaceId}`);
        const data = await response.json();
        
        if (data.success) {
          let modalContainer = document.getElementById('dynamicModalsContainer');
          if (!document.querySelector(modalId)) {
            modalContainer.innerHTML += data.html;
          }
        }
      } catch (error) {
        console.error('Error loading unit modal:', error);
      }
    }

    // Load photo modal dynamically  
    async function loadPhotoModal(modalId) {
      const spaceId = modalId.replace('#photoModal', '');
      
      try {
        const response = await fetch(`ajax/get_photo_modal.php?space_id=${spaceId}`);
        const data = await response.json();
        
        if (data.success) {
          let modalContainer = document.getElementById('dynamicModalsContainer');
          if (!document.querySelector(modalId)) {
            modalContainer.innerHTML += data.html;
          }
        }
      } catch (error) {
        console.error('Error loading photo modal:', error);
      }
    }

    // Auto-refresh data every 30 seconds for important updates
    setInterval(() => {
      loadAvailableUnits(true);
    }, 30000);
  </script>
</body>
</html>
<?php
require 'database/database.php';
session_start();

$db = new Database();
$is_logged_in = isset($_SESSION['client_id']);
$job_types = $db->getAllJobTypes();

// Remove the hardcoded icon_map since we'll use the actual icons from database
?>
<!doctype html>
<html lang="en">
<head>
    <title>Professional Services - ASRT Commercial Spaces</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <?php require('links.php'); ?>
    
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

    html, body {
        height: 100%;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        line-height: 1.6;
        color: var(--secondary);
        background: var(--light);
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .main-content {
        flex: 1 0 auto;
        padding-top: 100px; /* Account for fixed navbar */
    }

    .footer {
        flex-shrink: 0;
        width: 100%;
        margin-top: auto;
    }

    /* Hero Section */
    .hero-section {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        color: white;
        padding: 4rem 0 3rem;
        margin-bottom: 3rem;
        position: relative;
        overflow: hidden;
    }

    .hero-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" patternUnits="userSpaceOnUse" width="100" height="100"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        opacity: 0.3;
    }

    .hero-content {
        position: relative;
        z-index: 2;
    }

    .hero-title {
        font-family: 'Playfair Display', serif;
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
        animation: fadeInUp 0.8s ease-out;
    }

    .hero-subtitle {
        font-size: 1.2rem;
        opacity: 0.9;
        margin-bottom: 0;
        animation: fadeInUp 0.8s ease-out 0.2s both;
    }

    /* Service Cards */
    .service-card {
        background: var(--lighter);
        border-radius: var(--border-radius);
        padding: 2rem 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--gray-light);
        transition: var(--transition);
        height: 100%;
        text-align: center;
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }

    .service-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.1), transparent);
        transition: var(--transition);
    }

    .service-card:hover::before {
        left: 100%;
    }

    .service-card:hover {
        transform: translateY(-8px);
        box-shadow: var(--shadow-xl);
        border-color: var(--primary-light);
    }

    .service-card.disabled {
        opacity: 0.6;
        cursor: not-allowed;
        background: var(--gray-light);
    }

    .service-card.disabled:hover {
        transform: none;
        box-shadow: var(--shadow-md);
        border-color: var(--gray-light);
    }

    .service-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 1.5rem;
        transition: var(--transition);
        border-radius: 50%;
        padding: 1rem;
        background: linear-gradient(135deg, var(--light) 0%, var(--lighter) 100%);
        box-shadow: var(--shadow-sm);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .service-card:hover .service-icon {
        transform: scale(1.1) rotate(5deg);
        box-shadow: var(--shadow-lg);
    }

    .service-icon img {
        width: 50px;
        height: 50px;
        object-fit: contain;
    }

    .service-title {
        font-weight: 600;
        font-size: 1.1rem;
        color: var(--secondary);
        margin-bottom: 0.5rem;
    }

    .service-description {
        color: var(--gray);
        font-size: 0.9rem;
        line-height: 1.4;
    }

    .service-button {
        background: none;
        border: none;
        padding: 0;
        width: 100%;
        height: 100%;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        display: block;
    }

    /* Alert Styles */
    .modern-alert {
        border-radius: var(--border-radius);
        border: none;
        box-shadow: var(--shadow-md);
        padding: 1.5rem;
        margin-bottom: 2rem;
        background: linear-gradient(135deg, var(--accent) 0%, var(--accent-light) 100%);
        color: white;
        text-align: center;
    }

    .modern-alert-icon {
        font-size: 2rem;
        margin-bottom: 1rem;
        display: block;
    }

    /* Loading States */
    .service-card.loading {
        pointer-events: none;
        opacity: 0.8;
    }

    .service-card.loading .service-icon {
        animation: pulse 1.5s infinite;
    }

    /* Animations */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.05);
        }
    }

    .animate-on-scroll {
        opacity: 0;
        transform: translateY(30px);
        transition: all 0.8s ease-out;
    }

    .animate-on-scroll.animate {
        opacity: 1;
        transform: translateY(0);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .main-content {
            padding-top: 80px;
        }

        .hero-section {
            padding: 3rem 0 2rem;
            margin-bottom: 2rem;
        }
        
        .hero-title {
            font-size: 2rem;
        }
        
        .hero-subtitle {
            font-size: 1.1rem;
        }
        
        .service-card {
            padding: 1.5rem 1rem;
            margin-bottom: 1rem;
        }
        
        .service-icon {
            width: 70px;
            height: 70px;
            margin-bottom: 1rem;
        }
        
        .service-icon img {
            width: 40px;
            height: 40px;
        }
    }

    @media (max-width: 576px) {
        .main-content {
            padding-top: 70px;
        }

        .hero-section {
            padding: 2rem 0 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .hero-title {
            font-size: 1.8rem;
        }
        
        .service-card {
            padding: 1.2rem 0.8rem;
        }
        
        .service-icon {
            width: 60px;
            height: 60px;
        }
        
        .service-icon img {
            width: 35px;
            height: 35px;
        }
    }

    /* Additional Polish */
    .service-grid {
        gap: 1.5rem;
    }

    .service-card:nth-child(odd) .service-icon {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    }

    .service-card:nth-child(even) .service-icon {
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    }

    /* Breadcrumb */
    .breadcrumb {
        background: transparent;
        padding: 0;
        margin: 0 0 2rem 0;
    }

    .breadcrumb-item a {
        color: var(--primary);
        text-decoration: none;
    }

    .breadcrumb-item.active {
        color: var(--gray);
    }
    </style>
</head>

<body>
    <?php require('header.php'); ?>
    
    <div class="main-content">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="container">
                <div class="hero-content text-center">
                    <h1 class="hero-title">Professional Services</h1>
                    <p class="hero-subtitle">Choose the right professional for your maintenance and repair needs</p>
                </div>
            </div>
        </section>

        <div class="container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Services</li>
                </ol>
            </nav>

            <!-- Login Required Alert -->
            <?php if (!$is_logged_in): ?>
                <div class="modern-alert animate-on-scroll">
                    <i class="bi bi-shield-lock modern-alert-icon"></i>
                    <h4 class="fw-bold mb-2">Login Required</h4>
                    <p class="mb-0">Please log in to access our professional handyman services and request assistance.</p>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Auto-show login modal for better UX
                        setTimeout(function() {
                            var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                            loginModal.show();
                        }, 1000);
                    });
                </script>
            <?php endif; ?>

            <!-- Services Grid -->
            <div class="row service-grid justify-content-center">
                <?php
                if (!empty($job_types)) {
                    $delay = 0;
                    foreach ($job_types as $row) {
                        // Use the actual icon from database
                        $icon_filename = $row['Icon'] ?? 'default-icon.png';
                        $img_src = "uploads/jobtype_icons/" . $icon_filename;
                        
                        // Fallback to default icon if file doesn't exist
                        if (!file_exists($img_src)) {
                            $img_src = "IMG/show/wifi.png"; // Your default fallback
                        }
                        
                        $name_upper = strtoupper($row['JobType_Name']);
                        
                        // Service descriptions
                        $descriptions = [
                            "CARPENTRY" => "Custom woodwork, repairs, and installations",
                            "ELECTRICAL" => "Wiring, installations, and electrical repairs",
                            "PLUMBING" => "Pipe repairs, installations, and water systems",
                            "PAINTING" => "Interior and exterior painting services",
                            "APPLIANCE REPAIR" => "Equipment maintenance and repairs",
                            "PIPELINE" => "Pipe systems and plumbing installations"
                        ];
                        
                        $description = isset($descriptions[$name_upper]) ? $descriptions[$name_upper] : "Professional maintenance service";
                        $delay += 100;
                        
                        echo '<div class="col-6 col-sm-4 col-md-3 col-lg-2 animate-on-scroll" style="animation-delay: ' . $delay . 'ms;">';
                        echo '<div class="service-card' . (!$is_logged_in ? ' disabled' : '') . '">';
                        
                        if (!$is_logged_in) {
                            echo '<div class="service-button">';
                        } else {
                            echo '<form method="get" action="handyman.php" class="h-100">';
                            echo '<input type="hidden" name="jobtype_id" value="' . htmlspecialchars($row['JobType_ID']) . '">';
                            echo '<button type="submit" class="service-button">';
                        }
                        
                        echo '<div class="service-icon">';
                        echo '<img src="' . $img_src . '" alt="' . htmlspecialchars($row['JobType_Name']) . ' Icon" onerror="this.src=\'IMG/show/wifi.png\'">';
                        echo '</div>';
                        echo '<h5 class="service-title">' . htmlspecialchars($row['JobType_Name']) . '</h5>';
                        echo '<p class="service-description">' . $description . '</p>';
                        
                        if (!$is_logged_in) {
                            echo '</div>';
                        } else {
                            echo '</button>';
                            echo '</form>';
                        }
                        
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="col-12 text-center animate-on-scroll">';
                    echo '<div class="alert alert-info" style="border-radius: var(--border-radius); border: none; box-shadow: var(--shadow-md);">';
                    echo '<i class="bi bi-info-circle fs-3 mb-3 d-block"></i>';
                    echo '<h5>No Services Available</h5>';
                    echo '<p class="mb-0">We are currently updating our services. Please check back later.</p>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
            </div>

            <!-- Call to Action -->
            <div class="text-center mt-5 mb-5 animate-on-scroll">
                <div class="card" style="border-radius: var(--border-radius); border: none; box-shadow: var(--shadow-md); background: linear-gradient(135deg, var(--light) 0%, var(--lighter) 100%);">
                    <div class="card-body p-4">
                        <h4 class="card-title mb-3">Need Help Choosing?</h4>
                        <p class="card-text text-muted mb-4">Contact our team for personalized recommendations based on your specific needs.</p>
                        <a href="mailto:management@asrt.space" class="btn btn-primary btn-lg me-3" style="background: var(--gradient-primary); border: none; border-radius: var(--border-radius-sm);">
                            <i class="bi bi-envelope me-2"></i>Contact Us
                        </a>
                        <a href="tel:+09451357685" class="btn btn-outline-primary btn-lg" style="border-radius: var(--border-radius-sm);">
                            <i class="bi bi-telephone me-2"></i>Call Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require('footer.php'); ?>

    <!-- Bootstrap JS -->

    <script>
        // Smooth scroll animations
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

        // Enhanced service card interactions
        document.addEventListener('DOMContentLoaded', function() {
            const serviceCards = document.querySelectorAll('.service-card:not(.disabled)');
            
            serviceCards.forEach(card => {
                // Add loading state on click
                card.addEventListener('click', function() {
                    if (!this.classList.contains('disabled')) {
                        this.classList.add('loading');
                        
                        // Remove loading state after form submission or timeout
                        setTimeout(() => {
                            this.classList.remove('loading');
                        }, 3000);
                    }
                });

                // Enhanced hover effects with sound feedback (optional)
                card.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('disabled')) {
                        // Optional: Add subtle sound feedback
                        // new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp56hVFApGn+DyvmY').play().catch(e => {});
                    }
                });
            });

            // Mobile navbar auto-close
            var navbarCollapse = document.getElementById('navbarNav');
            if (navbarCollapse) {
                navbarCollapse.addEventListener('click', function(e) {
                    var target = e.target;
                    while (target && target !== navbarCollapse) {
                        if (target.classList && (target.classList.contains('nav-link') || target.type === 'submit' || (target.classList.contains('btn') && !target.classList.contains('navbar-toggler')))) {
                            if (window.innerWidth < 992) {
                                var bsCollapse = bootstrap.Collapse.getOrCreateInstance(navbarCollapse);
                                bsCollapse.hide();
                            }
                            break;
                        }
                        target = target.parentElement;
                    }
                });
            }

            // Show success message for logged-in users
            <?php if ($is_logged_in): ?>
                setTimeout(function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Welcome!',
                        text: 'Choose any service to connect with our professional team.',
                        timer: 3000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                }, 500);
            <?php endif; ?>
        });

        // Improved form submission with better UX
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const serviceCard = this.closest('.service-card');
                if (serviceCard) {
                    serviceCard.classList.add('loading');
                    
                    // Show loading toast
                    Swal.fire({
                        title: 'Loading...',
                        text: 'Connecting you with our professionals',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>
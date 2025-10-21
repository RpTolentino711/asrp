<?php
require 'database/database.php';
session_start();

$db = new Database();

if (!isset($_SESSION['client_id'])) {
    // Block access for guests
    echo <<<HTML
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Login Required - ASRT Commercial Spaces</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        
        <!-- Fonts -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
        
        <!-- Bootstrap & Icons -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
        
        <style>
            :root {
                --primary: #2563eb;
                --primary-light: #3b82f6;
                --danger: #ef4444;
                --light: #f8fafc;
                --lighter: #ffffff;
                --gray: #64748b;
                --gray-dark: #334155;
                --border-radius: 16px;
                --shadow-xl: 0 16px 40px rgba(0, 0, 0, 0.15);
                --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem;
            }

            .access-denied-container {
                max-width: 600px;
                width: 100%;
            }

            .access-denied-card {
                background: var(--lighter);
                border-radius: var(--border-radius);
                box-shadow: var(--shadow-xl);
                padding: 3rem;
                text-align: center;
                border: none;
            }

            .lock-icon {
                width: 100px;
                height: 100px;
                background: linear-gradient(135deg, var(--danger), #f87171);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 2rem;
                color: white;
                font-size: 2.5rem;
            }

            .access-title {
                font-family: 'Playfair Display', serif;
                font-size: 2.2rem;
                font-weight: 700;
                color: var(--gray-dark);
                margin-bottom: 1rem;
            }

            .access-message {
                color: var(--gray);
                font-size: 1.1rem;
                margin-bottom: 2.5rem;
                line-height: 1.6;
            }

            .btn-group-custom {
                display: flex;
                gap: 1rem;
                justify-content: center;
                flex-wrap: wrap;
            }

            .modern-btn {
                padding: 0.875rem 2rem;
                border-radius: 12px;
                font-weight: 600;
                font-size: 1rem;
                transition: var(--transition);
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border: none;
            }

            .modern-btn-primary {
                background: linear-gradient(135deg, var(--primary), var(--primary-light));
                color: white;
            }

            .modern-btn-secondary {
                background: var(--lighter);
                color: var(--gray-dark);
                border: 2px solid #e2e8f0;
            }

            .modern-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
                color: white;
            }

            .modern-btn-secondary:hover {
                color: var(--primary);
                border-color: var(--primary);
            }

            .modern-btn i {
                margin-right: 0.5rem;
            }

            @media (max-width: 768px) {
                body {
                    padding: 1rem;
                }
                
                .access-denied-card {
                    padding: 2rem 1.5rem;
                }
                
                .access-title {
                    font-size: 1.8rem;
                }
                
                .lock-icon {
                    width: 80px;
                    height: 80px;
                    font-size: 2rem;
                }
                
                .btn-group-custom {
                    flex-direction: column;
                }
            }
        </style>
    </head>
    <body>
        <div class="access-denied-container">
            <div class="access-denied-card">
                <div class="lock-icon">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <h1 class="access-title">Access Restricted</h1>
                <p class="access-message">
                    Please login to access our handyman services directory. Our professional handymen are ready to assist with your maintenance needs.
                </p>
                <div class="btn-group-custom">
                    <a href="login.php" class="modern-btn modern-btn-primary">
                        <i class="bi bi-person-check"></i>Login to Continue
                    </a>
                    <a href="index.php" class="modern-btn modern-btn-secondary">
                        <i class="bi bi-house"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    HTML;
    exit();
}

// --- Parameter Validation ---
if (!isset($_GET['jobtype_id']) || !is_numeric($_GET['jobtype_id'])) {
    header("Location: index.php");
    exit();
}
$jobtype_id = intval($_GET['jobtype_id']);

// --- Fetch Data ---
$jobtype_record = $db->getJobTypeNameById($jobtype_id);
$jobtype_name = $jobtype_record ? $jobtype_record['JobType_Name'] : 'Unknown';

$handymen = $db->getHandymenByJobType($jobtype_id);

// --- Image/Icon mapping ---
$icon_map = [
    "CARPENTRY" => "IMG/show/CARPENTRY.png",
    "ELECTRICAL" => "IMG/show/ELECTRICAL.png",
    "PLUMBING" => "IMG/show/PLUMBING.png",
    "PAINTING" => "IMG/show/PAINTING.png",
    "APPLIANCE REPAIR" => "IMG/show/APPLIANCE.png",
];
$img_src = isset($icon_map[strtoupper($jobtype_name)]) ? $icon_map[strtoupper($jobtype_name)] : "IMG/show/wifi.png";

// --- Icon mapping for better UI ---
$service_icons = [
    "CARPENTRY" => "bi-hammer",
    "ELECTRICAL" => "bi-lightning-charge",
    "PLUMBING" => "bi-droplet",
    "PAINTING" => "bi-paint-bucket",
    "APPLIANCE REPAIR" => "bi-tools",
];
$service_icon = isset($service_icons[strtoupper($jobtype_name)]) ? $service_icons[strtoupper($jobtype_name)] : "bi-wrench-adjustable";

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($jobtype_name) ?> Handymen - ASRT Commercial Spaces</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <?php require('links.php'); ?>
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary: #1e293b;
            --success: #10b981;
            --warning: #f59e0b;
            --info: #0ea5e9;
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
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--primary) !important;
            background: rgba(37, 99, 235, 0.1);
        }

        /* Service Header */
        .service-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 3rem 0 2rem;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }

        .service-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="tools" patternUnits="userSpaceOnUse" width="30" height="30"><path d="M15,5 L20,10 L15,15 L10,10 Z" fill="white" opacity="0.1"/><circle cx="15" cy="20" r="2" fill="white" opacity="0.08"/></pattern></defs><rect width="100" height="100" fill="url(%23tools)"/></svg>');
        }

        .service-header-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .service-icon-large {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .service-icon-large i {
            font-size: 3rem;
            color: white;
        }

        .service-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .service-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        /* Handyman Cards */
        .handyman-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .handyman-card {
            background: var(--lighter);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-light);
            transition: var(--transition);
            overflow: hidden;
            height: 100%;
        }

        .handyman-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }

        .handyman-card-body {
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .handyman-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
            border: 4px solid var(--lighter);
            box-shadow: var(--shadow-md);
        }

        .handyman-name {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 0.5rem;
        }

        .handyman-role {
            color: var(--gray);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .contact-section {
            background: var(--light);
            border-radius: var(--border-radius-sm);
            padding: 1.25rem;
            margin-top: 1rem;
        }

        .contact-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray);
            margin-bottom: 0.75rem;
            display: block;
        }

        .phone-button {
            background: linear-gradient(135deg, var(--success), #34d399);
            color: white;
            border: none;
            border-radius: var(--border-radius-sm);
            padding: 0.875rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
        }

        .phone-button:hover {
            background: linear-gradient(135deg, #059669, var(--success));
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
            text-decoration: none;
        }

        .phone-button:active {
            transform: translateY(0);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }

        .empty-state-icon {
            width: 120px;
            height: 120px;
            background: var(--light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            border: 3px solid var(--gray-light);
        }

        .empty-state-icon i {
            font-size: 3rem;
            color: var(--gray);
        }

        .empty-state-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-dark);
            margin-bottom: 1rem;
        }

        .empty-state-message {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Back Button */
        .back-button-container {
            text-align: center;
            margin-top: 3rem;
        }

        .back-button {
            background: var(--lighter);
            color: var(--gray-dark);
            border: 2px solid var(--gray-light);
            border-radius: var(--border-radius-sm);
            padding: 0.875rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-button:hover {
            color: var(--primary);
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.05);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            text-decoration: none;
        }

        /* Service Badge */
        .service-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: linear-gradient(135deg, var(--warning), #fbbf24);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: var(--border-radius-sm);
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Stats Section */
        .stats-section {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            text-align: center;
        }

        .stat-item h4 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: white;
        }

        .stat-item p {
            font-size: 0.9rem;
            margin: 0;
            opacity: 0.9;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .service-header {
                padding: 2rem 0 1.5rem;
            }

            .service-title {
                font-size: 2rem;
            }

            .service-subtitle {
                font-size: 1.1rem;
            }

            .service-icon-large {
                width: 100px;
                height: 100px;
            }

            .service-icon-large i {
                font-size: 2.5rem;
            }

            .handyman-card-body {
                padding: 1.5rem;
            }

            .handyman-name {
                font-size: 1.2rem;
            }

            .stats-section {
                padding: 1rem;
            }

            .stat-item h4 {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .service-title {
                font-size: 1.75rem;
            }

            .handyman-card-body {
                padding: 1.25rem;
            }

            .handyman-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.75rem;
            }
        }

        /* Mobile navbar fixes */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                position: absolute;
                top: 100%;
                left: 0;
                width: 100%;
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(20px);
                z-index: 1050;
                border-bottom-left-radius: var(--border-radius);
                border-bottom-right-radius: var(--border-radius);
                box-shadow: var(--shadow-lg);
                border: 1px solid var(--gray-light);
                border-top: none;
            }

            .navbar-nav {
                flex-direction: column !important;
                align-items: stretch !important;
                margin: 0;
                padding: 0.5rem 0;
            }

            .nav-item,
            .nav-link {
                width: 100% !important;
                text-align: left !important;
                padding: 0.75rem 1rem !important;
                margin: 0 !important;
            }
        }

        /* Loading animation */
        .loading-spinner {
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

        /* Fade in animation */
        .fade-in {
            animation: fadeIn 0.6s ease-in-out;
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
    </style>
</head>

<body>
    <?php require('header.php'); ?>

    <!-- Service Header -->
    <div class="service-header">
        <div class="container">
            <div class="service-header-content">
                <div class="service-icon-large">
                    <i class="<?= htmlspecialchars($service_icon) ?>"></i>
                </div>
                <h1 class="service-title"><?= htmlspecialchars($jobtype_name) ?> Specialists</h1>
                <p class="service-subtitle">Professional handymen ready to help with your <?= strtolower(htmlspecialchars($jobtype_name)) ?> needs</p>
                
                <!-- Stats Section -->
                <div class="stats-section">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <h4><?= count($handymen) ?></h4>
                            <p>Available Experts</p>
                        </div>
                        <div class="stat-item">
                            <h4>24/7</h4>
                            <p>Support Available</p>
                        </div>
                        <div class="stat-item">
                            <h4>100%</h4>
                            <p>Verified Professionals</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container handyman-container">
        <?php if (!empty($handymen)): ?>
            <div class="row g-4 mb-5">
                <?php foreach ($handymen as $index => $hm): ?>
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="handyman-card fade-in" style="animation-delay: <?= $index * 0.1 ?>s;">
                            <div class="handyman-card-body">
                                <div class="service-badge">
                                    <?= htmlspecialchars($jobtype_name) ?>
                                </div>
                                
                                <div class="handyman-avatar">
                                    <i class="bi bi-person-check"></i>
                                </div>
                                
                                <h3 class="handyman-name">
                                    <?= htmlspecialchars($hm['Handyman_fn'] . ' ' . $hm['Handyman_ln']) ?>
                                </h3>
                                
                                <div class="handyman-role">
                                    <?= htmlspecialchars($jobtype_name) ?> Specialist
                                </div>
                                
                                <div class="contact-section">
                                    <label class="contact-label">
                                        <i class="bi bi-telephone me-1"></i>Contact Professional
                                    </label>
                                    <a href="tel:<?= htmlspecialchars($hm['Phone']) ?>" 
                                       class="phone-button"
                                       onclick="trackCall('<?= htmlspecialchars($hm['Handyman_fn'] . ' ' . $hm['Handyman_ln']) ?>')">
                                        <i class="bi bi-telephone-fill"></i>
                                        <?= htmlspecialchars($hm['Phone']) ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach ?>
            </div>
        <?php else: ?>
            <div class="empty-state fade-in">
                <div class="empty-state-icon">
                    <i class="bi bi-search"></i>
                </div>
                <h3 class="empty-state-title">No Specialists Available</h3>
                <p class="empty-state-message">
                    We currently don't have any <?= strtolower(htmlspecialchars($jobtype_name)) ?> specialists available. 
                    Please check back later or contact our support team for assistance.
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="tel:+09451357685" class="phone-button" style="width: auto;">
                        <i class="bi bi-telephone"></i>Call Support
                    </a>
                    <a href="mailto:management@asrt.space" class="back-button">
                        <i class="bi bi-envelope"></i>Email Us
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Back Button -->
        <div class="back-button-container">
            <a href="handyman_type.php" class="back-button">
                <i class="bi bi-arrow-left"></i>
                Back to Services
            </a>
        </div>
    </div>

    <?php require('footer.php'); ?>

    <!-- Bootstrap JavaScript -->

    <script>
        // Mobile navbar handling
        document.addEventListener('DOMContentLoaded', function() {
            const navbarCollapse = document.getElementById('navbarNav');
            if (navbarCollapse) {
                navbarCollapse.addEventListener('click', function(e) {
                    let target = e.target;
                    while (target && target !== navbarCollapse) {
                        if (target.classList && (
                            target.classList.contains('nav-link') || 
                            target.type === 'submit' || 
                            (target.classList.contains('btn') && !target.classList.contains('navbar-toggler'))
                        )) {
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

            // Add loading state to phone buttons
            const phoneButtons = document.querySelectorAll('.phone-button');
            phoneButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const originalContent = this.innerHTML;
                    this.innerHTML = '<span class="loading-spinner me-2"></span>Connecting...';
                    this.style.pointerEvents = 'none';
                    
                    // Reset after 3 seconds
                    setTimeout(() => {
                        this.innerHTML = originalContent;
                        this.style.pointerEvents = 'auto';
                    }, 3000);
                });
            });
        });

        // Call tracking function
        function trackCall(handymanName) {
            console.log('Call initiated to:', handymanName);
            // You can add analytics tracking here if needed
            
            // Show confirmation message
            setTimeout(() => {
                // Optional: Show a toast notification
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'info',
                        title: 'Calling ' + handymanName,
                        text: 'Your call is being initiated...',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                }
            }, 500);
        }

        // Smooth scroll animations
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

        // Observe all cards for animation
        document.querySelectorAll('.handyman-card').forEach((card) => {
            observer.observe(card);
        });

        // Add hover effects for better UX
        document.querySelectorAll('.handyman-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(-4px)';
            });
        });

        // Keyboard accessibility
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                // Close any open modals or dropdowns
                const openCollapse = document.querySelector('.navbar-collapse.show');
                if (openCollapse) {
                    const bsCollapse = bootstrap.Collapse.getOrCreateInstance(openCollapse);
                    bsCollapse.hide();
                }
            }
        });

        // Performance optimization: Lazy load images if any
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            imageObserver.unobserve(img);
                        }
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }

        // Add ripple effect to buttons (optional enhancement)
        function createRipple(event) {
            const button = event.currentTarget;
            const circle = document.createElement("span");
            const diameter = Math.max(button.clientWidth, button.clientHeight);
            const radius = diameter / 2;

            circle.style.width = circle.style.height = `${diameter}px`;
            circle.style.left = `${event.clientX - button.offsetLeft - radius}px`;
            circle.style.top = `${event.clientY - button.offsetTop - radius}px`;
            circle.classList.add("ripple");

            const ripple = button.getElementsByClassName("ripple")[0];
            if (ripple) {
                ripple.remove();
            }

            button.appendChild(circle);
        }

        // Apply ripple effect to buttons
        document.querySelectorAll('.phone-button, .back-button').forEach(button => {
            button.addEventListener('click', createRipple);
        });

        // Add CSS for ripple effect
        const rippleStyle = document.createElement('style');
        rippleStyle.textContent = `
            .ripple {
                position: absolute;
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 600ms linear;
                background-color: rgba(255, 255, 255, 0.6);
                pointer-events: none;
            }
            
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(rippleStyle);
    </script>
</body>
</html>
<?php
session_start();
$is_logged_in = isset($_SESSION['C_username']) && isset($_SESSION['client_id']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>About Us - ASRT Commercial Spaces</title>
  <?php require('links.php'); ?>
  
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
  
  <!-- Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Swiper -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
  
  <style>
    :root {
      --primary: #1e40af;
      --primary-light: #3b82f6;
      --primary-dark: #1e3a8a;
      --secondary: #0f172a;
      --accent: #ef4444;
      --success: #059669;
      --warning: #d97706;
      --light: #f8fafc;
      --lighter: #ffffff;
      --gray: #64748b;
      --gray-light: #e2e8f0;
      --gray-dark: #334155;
      --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
      --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.12);
      --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
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
      background: var(--light);
      color: var(--secondary);
      line-height: 1.6;
      padding-top: 80px;
    }

    /* Hero Section */
    .hero-section {
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      color: white;
      padding: 4rem 0 3rem;
      margin-bottom: 4rem;
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
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="about-pattern" patternUnits="userSpaceOnUse" width="40" height="40"><circle cx="20" cy="20" r="1" fill="white" opacity="0.1"/><circle cx="10" cy="30" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23about-pattern)"/></svg>');
      opacity: 0.3;
    }

    .hero-content {
      position: relative;
      z-index: 2;
      text-align: center;
    }

    .hero-title {
      font-family: 'Playfair Display', serif;
      font-size: 3.5rem;
      font-weight: 700;
      margin-bottom: 1rem;
      animation: fadeInUp 0.8s ease-out;
    }

    .hero-subtitle {
      font-size: 1.25rem;
      opacity: 0.9;
      max-width: 800px;
      margin: 0 auto 2rem;
      animation: fadeInUp 0.8s ease-out 0.2s both;
    }

    .hero-divider {
      width: 80px;
      height: 4px;
      background: rgba(255, 255, 255, 0.8);
      margin: 0 auto;
      border-radius: 2px;
      animation: fadeInUp 0.8s ease-out 0.4s both;
    }

    /* Story Section */
    .story-section {
      padding: 4rem 0;
      background: var(--lighter);
    }

    .story-image {
      border-radius: var(--border-radius);
      box-shadow: var(--shadow-xl);
      transition: var(--transition);
    }

    .story-image:hover {
      transform: scale(1.02);
      box-shadow: var(--shadow-xl);
    }

    .story-caption {
      margin-top: 1rem;
      font-weight: 600;
      color: var(--primary);
      text-align: center;
    }

    .story-text {
      font-size: 1.1rem;
      line-height: 1.8;
      color: var(--gray-dark);
    }

    .story-title {
      font-family: 'Playfair Display', serif;
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--secondary);
      margin-bottom: 2rem;
    }

    /* Stats Section */
    .stats-section {
      padding: 4rem 0;
      background: var(--light);
    }

    .stat-card {
      background: var(--lighter);
      border-radius: var(--border-radius);
      padding: 2.5rem 2rem;
      text-align: center;
      box-shadow: var(--shadow-md);
      border: 1px solid var(--gray-light);
      transition: var(--transition);
      height: 100%;
      position: relative;
      overflow: hidden;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: linear-gradient(90deg, var(--primary) 0%, var(--success) 100%);
      transform: scaleX(0);
      transition: var(--transition);
      transform-origin: left;
    }

    .stat-card:hover {
      transform: translateY(-8px);
      box-shadow: var(--shadow-xl);
      border-color: var(--primary-light);
    }

    .stat-card:hover::before {
      transform: scaleX(1);
    }

    .stat-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      transition: var(--transition);
    }

    .stat-icon img {
      width: 40px;
      height: 40px;
      filter: brightness(0) invert(1);
    }

    .stat-card:hover .stat-icon {
      transform: scale(1.1) rotate(5deg);
    }

    .stat-title {
      font-weight: 700;
      font-size: 1.25rem;
      color: var(--secondary);
      margin-bottom: 1rem;
    }

    .stat-description {
      color: var(--gray);
      font-size: 1rem;
      line-height: 1.6;
    }

    /* Team Section */
    .team-section {
      padding: 4rem 0;
      background: var(--lighter);
    }

    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--secondary);
      text-align: center;
      margin-bottom: 1.5rem;
    }

    .section-subtitle {
      text-align: center;
      color: var(--gray);
      font-size: 1.1rem;
      max-width: 800px;
      margin: 0 auto 3rem;
      line-height: 1.7;
    }

    .team-member {
      text-align: center;
      padding: 1rem;
    }

    .team-image {
      width: 200px;
      height: 200px;
      border-radius: 50%;
      object-fit: cover;
      box-shadow: var(--shadow-lg);
      transition: var(--transition);
      margin: 0 auto 1.5rem;
      border: 4px solid var(--lighter);
      position: relative;
    }

    .team-member:hover .team-image {
      transform: scale(1.05);
      box-shadow: var(--shadow-xl);
      border-color: var(--primary-light);
    }

    .team-name {
      font-weight: 600;
      font-size: 1.1rem;
      color: var(--secondary);
      margin-bottom: 0.5rem;
    }

    .team-role {
      color: var(--primary);
      font-weight: 500;
      font-size: 0.9rem;
    }

    /* Swiper customization */
    .team-swiper {
      padding: 2rem 0 3rem;
    }

    .swiper-pagination {
      bottom: 10px;
    }

    .swiper-pagination-bullet {
      background: var(--primary);
      opacity: 0.3;
      width: 12px;
      height: 12px;
      transition: var(--transition);
    }

    .swiper-pagination-bullet-active {
      opacity: 1;
      transform: scale(1.2);
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
      body {
        padding-top: 70px;
      }
      
      .hero-title {
        font-size: 2.5rem;
      }
      
      .hero-subtitle {
        font-size: 1.1rem;
      }
      
      .story-title,
      .section-title {
        font-size: 2rem;
      }
      
      .stat-card {
        padding: 2rem 1.5rem;
        margin-bottom: 1.5rem;
      }
      
      .team-image {
        width: 180px;
        height: 180px;
      }
    }

    @media (max-width: 576px) {
      .hero-section {
        padding: 3rem 0 2rem;
      }
      
      .hero-title {
        font-size: 2rem;
      }
      
      .story-section,
      .stats-section,
      .team-section {
        padding: 3rem 0;
      }
      
      .team-image {
        width: 160px;
        height: 160px;
      }
    }

    /* Custom scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: var(--light);
    }

    ::-webkit-scrollbar-thumb {
      background: var(--primary);
      border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: var(--primary-dark);
    }
  </style>
</head>

<body>
  <?php require('header.php'); ?>

  <!-- Hero Section -->
  <section class="hero-section">
    <div class="container">
      <div class="hero-content">
        <h1 class="hero-title">About ASRT Commercial Spaces</h1>
        <div class="hero-divider"></div>
        <p class="hero-subtitle">
          Your partner in secure, reliable, and flexible commercial leasing. Our mission is to empower businesses with modern, well-equipped workspaces and outstanding service, fostering an environment where enterprises can thrive.
        </p>
      </div>
    </div>
  </section>

  <!-- Story Section -->
  <section class="story-section">
    <div class="container">
      <div class="row justify-content-between align-items-center">
        <div class="col-lg-6 col-md-6 mb-4 animate-on-scroll">
          <h2 class="story-title">Our Story</h2>
          <div class="story-text">
            <p>
              Established with a commitment to supporting local entrepreneurs, ASRT has evolved into a trusted provider of commercial workspaces. We uphold the highest standards of safety, convenience, and client satisfaction—ensuring every business enjoys a productive, worry-free environment.
            </p>
            <p>
              Whether you are a startup, freelancer, or established enterprise, our flexible solutions adapt to your needs. Benefit from modern amenities, dedicated maintenance, and a vibrant network of professionals. At ASRT, your growth is not just our goal—it's our purpose.
            </p>
          </div>
        </div>
        <div class="col-lg-5 col-md-6 mb-4 text-center animate-on-scroll">
          <img src="IMG/show/asrt.jpg" class="img-fluid story-image" alt="ASRT Owners">
          <p class="story-caption">
            <i class="bi bi-people-fill me-2"></i>Proud Owners of ASRT Commercial Spaces
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- Stats Section -->
  <section class="stats-section">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-4 col-md-6 animate-on-scroll">
          <div class="stat-card">
            <div class="stat-icon">
              <i class="bi bi-building" style="font-size: 2rem; color: white;"></i>
            </div>
            <h3 class="stat-title">10+ Units Available</h3>
            <p class="stat-description">A diverse selection of commercial units to accommodate businesses of all sizes and industries.</p>
          </div>
        </div>
        
        <div class="col-lg-4 col-md-6 animate-on-scroll">
          <div class="stat-card">
            <div class="stat-icon">
              <i class="bi bi-award" style="font-size: 2rem; color: white;"></i>
            </div>
            <h3 class="stat-title">Over a Decade of Service</h3>
            <p class="stat-description">Serving the business community with integrity and excellence for more than 10 years of trusted partnership.</p>
          </div>
        </div>
        
        <div class="col-lg-4 col-md-6 animate-on-scroll">
          <div class="stat-card">
            <div class="stat-icon">
              <i class="bi bi-people" style="font-size: 2rem; color: white;"></i>
            </div>
            <h3 class="stat-title">100+ Satisfied Clients</h3>
            <p class="stat-description">Join a growing network of successful business owners who have found their perfect commercial space with us.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Team Section -->
  <section class="team-section">
    <div class="container">
      <h2 class="section-title animate-on-scroll">Meet Our Developers</h2>
      <p class="section-subtitle animate-on-scroll">
        This website was built by talented students of <strong>NU Lipa – INF232</strong>, united by a passion for innovation and collaboration. Every aspect—from design to functionality—reflects the dedication of aspiring developers committed to shaping the future of technology.
      </p>

      <div class="swiper team-swiper animate-on-scroll">
        <div class="swiper-wrapper">
          <div class="swiper-slide">
            <div class="team-member">
              <img src="IMG/SHOW/BRYAN.jpg" alt="Bryan Gabriel Tesoro" class="team-image">
              <h4 class="team-name">Bryan Gabriel Tesoro</h4>
              <p class="team-role">Full-Stack Developer</p>
            </div>
          </div>
          
          <div class="swiper-slide">
            <div class="team-member">
              <img src="IMG/SHOW/LUKE.jpg" alt="Luke Aron Magpantay" class="team-image">
              <h4 class="team-name">Luke Aron Magpantay</h4>
              <p class="team-role">Frontend Developer</p>
            </div>
          </div>
          
          <div class="swiper-slide">
            <div class="team-member">
              <img src="IMG/SHOW/jin.jpg" alt="Jin Carlo Maullon" class="team-image">
              <h4 class="team-name">Jin Carlo Maullon</h4>
              <p class="team-role">Backend Developer</p>
            </div>
          </div>
          
          <div class="swiper-slide">
            <div class="team-member">
              <img src="IMG/SHOW/kibrys.jpg" alt="John Kibry Buño" class="team-image">
              <h4 class="team-name">John Kibry Buño</h4>
              <p class="team-role">UI/UX Designer</p>
            </div>
          </div>
          
          <div class="swiper-slide">
            <div class="team-member">
              <img src="IMG/SHOW/romeo.jpg" alt="Romeo Paolo Tolentino" class="team-image">
              <h4 class="team-name">Romeo Paolo Tolentino</h4>
              <p class="team-role">System Architect</p>
            </div>
          </div>
        </div>
        <div class="swiper-pagination"></div>
      </div>
    </div>
  </section>

  <?php require('footer.php'); ?>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  
  <script>
    // Initialize Swiper
    const swiper = new Swiper('.team-swiper', {
      slidesPerView: 1,
      spaceBetween: 30,
      loop: true,
      autoplay: {
        delay: 3000,
        disableOnInteraction: false,
      },
      pagination: {
        el: '.swiper-pagination',
        clickable: true,
      },
      breakpoints: {
        640: {
          slidesPerView: 2,
          spaceBetween: 20
        },
        768: {
          slidesPerView: 3,
          spaceBetween: 30
        },
        1024: {
          slidesPerView: 4,
          spaceBetween: 40
        },
        1200: {
          slidesPerView: 5,
          spaceBetween: 30
        }
      }
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

    // Add stagger animation to stat cards
    document.querySelectorAll('.stat-card').forEach((card, index) => {
      card.style.animationDelay = `${index * 0.2}s`;
    });

    // Smooth scroll for internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });

    // Add loading animation to images
    document.querySelectorAll('img').forEach(img => {
      img.addEventListener('load', function() {
        this.style.opacity = '0';
        this.style.transition = 'opacity 0.3s ease';
        setTimeout(() => {
          this.style.opacity = '1';
        }, 100);
      });
    });
  </script>
</body>
</html>
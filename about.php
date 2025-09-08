<?php
session_start();
$is_logged_in = isset($_SESSION['C_username']) && isset($_SESSION['client_id']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ASRT Commercial Spaces | About Us</title>
  <?php require('links.php'); ?>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
  <style>
    body {
      background: #f8f9fa;
      color: #222;
      font-family: 'Poppins', Arial, sans-serif;
    }
    .h-font {
      font-family: 'Poppins', Arial, sans-serif;
      font-size: 2.5rem;
      font-weight: 600;
    }
    .h-line {
      width: 80px;
      height: 4px;
      background-color: #000;
      margin: 10px auto;
    }
    .box {
      border-top: 4px solid var(--teal, #20c997);
      transition: transform 0.3s ease-in-out;
    }
    .box:hover {
      transform: translateY(-10px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.15);
    }
    .swiper-slide img {
      width: 220px;
      height: 220px;
      object-fit: cover;
      border-radius: 15px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.15);
      transition: transform 0.3s;
    }
    .swiper-slide img:hover {
      transform: scale(1.05);
    }
    h3, h5, .fw-bold, .fw-semibold {
      font-family: 'Poppins', Arial, sans-serif !important;
      font-weight: 600;
    }
  </style>
</head>
<body class="bg-light">

<?php require('header.php'); ?>

<div class="my-5 px-4 text-center">
  <h2 class="fw-bold h-font">About Us</h2>
  <div class="h-line"></div>
  <p class="mt-3 text-muted col-lg-8 mx-auto">
    Welcome to <strong>ASRT Commercial Spaces</strong>—your partner in secure, reliable, and flexible commercial leasing. Our mission is to empower businesses with modern, well-equipped workspaces and outstanding service, fostering an environment where enterprises can thrive.
  </p>
</div>

<div class="container">
  <div class="row justify-content-between align-items-center">
    <div class="col-lg-6 col-md-5 mb-4 order-lg-1 order-md-2 order-2">
      <h3 class="mb-3">Our Story</h3>
      <p class="text-muted">
        Established with a commitment to supporting local entrepreneurs, ASRT has evolved into a trusted provider of commercial workspaces. We uphold the highest standards of safety, convenience, and client satisfaction—ensuring every business enjoys a productive, worry-free environment.<br><br>
        Whether you are a startup, freelancer, or established enterprise, our flexible solutions adapt to your needs. Benefit from modern amenities, dedicated maintenance, and a vibrant network of professionals. At ASRT, your growth is not just our goal—it's our purpose.
      </p>
    </div>
    <div class="col-lg-5 col-md-5 mb-4 text-center order-lg-2 order-md-1 order-1">
      <img src="IMG/show/asrt.jpg" class="img-fluid rounded shadow" alt="ASRT Owners">
      <p class="mt-3 fw-semibold">
        Proud Owners of ASRT Commercial Spaces
      </p>
    </div>
  </div>
</div>

<div class="container mt-5">
  <div class="row text-center">
    <div class="col-lg-4 col-md-6 mb-4 px-4">
      <div class="bg-white rounded shadow p-4 box">
        <img src="IMG/show/unit.webp" width="70px" alt="Units">
        <h5 class="mt-3">10+ Units Available</h5>
        <p class="text-muted">A diverse selection of commercial units to accommodate businesses of all sizes.</p>
      </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-4 px-4">
      <div class="bg-white rounded shadow p-4 box">
        <img src="IMG/show/unit.webp" width="70px" alt="Experience">
        <h5 class="mt-3">Over a Decade of Service</h5>
        <p class="text-muted">Serving the business community with integrity for more than 10 years.</p>
      </div>
    </div>
    <div class="col-lg-4 col-md-6 mb-4 px-4">
      <div class="bg-white rounded shadow p-4 box">
        <img src="IMG/show/unit.webp" width="70px" alt="Clients">
        <h5 class="mt-3">100+ Satisfied Clients</h5>
        <p class="text-muted">Join a growing network of successful business owners.</p>
      </div>
    </div>
  </div>
</div>

<h3 class="text-center mt-5 mb-4">Meet Our Developers</h3>
<p class="text-center text-muted mb-4 col-lg-8 mx-auto">
  This website was built by students of <strong>NU Lipa – INF232</strong>, united by a passion for innovation and collaboration. Every aspect—from design to functionality—reflects the dedication of aspiring developers committed to shaping the future of technology.
</p>

<div class="container px-4">
  <div class="swiper mySwiper">
    <div class="swiper-wrapper mb-5">
      <div class="swiper-slide text-center">
        <img src="IMG/SHOW/BRYAN.jpg" alt="Bryan Gabriel Tesoro">
        <p class="mt-2 fw-semibold">Bryan Gabriel Tesoro</p>
      </div>
      <div class="swiper-slide text-center">
        <img src="IMG/SHOW/LUKE.jpg" alt="Luke Aron Magpantay">
        <p class="mt-2 fw-semibold">Luke Aron Magpantay</p>
      </div>
      <div class="swiper-slide text-center">
        <img src="IMG/SHOW/jin.jpg" alt="Jin Carlo Maullon">
        <p class="mt-2 fw-semibold">Jin Carlo Maullon</p>
      </div>
      <div class="swiper-slide text-center">
        <img src="IMG/SHOW/kibrys.jpg" alt="John Kibry Buño">
        <p class="mt-2 fw-semibold">John Kibry Buño</p>
      </div>
      <div class="swiper-slide text-center">
        <img src="IMG/SHOW/romeo.jpg" alt="Romeo Paolo Tolentino">
        <p class="mt-2 fw-semibold">Romeo Paolo Tolentino</p>
      </div>
    </div>
    <div class="swiper-pagination"></div>
  </div>
</div>

<?php require('footer.php'); ?>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
<script>
  var swiper = new Swiper(".mySwiper", {
    slidesPerView: 1,
    spaceBetween: 30,
    pagination: {
      el: ".swiper-pagination",
      clickable: true,
    },
    breakpoints: {
      640: { slidesPerView: 2, spaceBetween: 20 },
      768: { slidesPerView: 3, spaceBetween: 40 },
      1024: { slidesPerView: 4, spaceBetween: 50 },
      1200: { slidesPerView: 5, spaceBetween: 30 }
    }
  });
</script>

</body>
</html>
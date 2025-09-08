<!-- Modern Professional Footer -->
<footer class="modern-footer">
  <div class="footer-main">
    <div class="container">
      <div class="row g-4">
        <!-- Brand Section -->
        <div class="col-lg-4 col-md-6">
          <div class="footer-brand">
            <div class="brand-logo">
              <i class="bi bi-buildings"></i>
              <span class="brand-name">ASRT Commercial Spacing</span>
            </div>
            <p class="brand-description">
              Providing flexible, secure, and affordable commercial units where your business or lifestyle finds room to grow and thrive.
            </p>
            <div class="brand-features">
              <div class="feature-item">
                <i class="bi bi-shield-check"></i>
                <span>Secure</span>
              </div>
              <div class="feature-item">
                <i class="bi bi-cash-stack"></i>
                <span>Affordable</span>
              </div>
              <div class="feature-item">
                <i class="bi bi-arrows-expand"></i>
                <span>Flexible</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Links -->
        <div class="col-lg-2 col-md-6 col-6">
          <div class="footer-section">
            <h5 class="footer-title">Quick Links</h5>
            <ul class="footer-links">
              <li>
                <a href="index.php" class="footer-link">
                  <i class="bi bi-house-door"></i>
                  <span>Home</span>
                </a>
              </li>
              <li>
                <a href="#units" class="footer-link">
                  <i class="bi bi-building"></i>
                  <span>Units</span>
                </a>
              </li>
              <li>
                <a href="handyman_type.php" class="footer-link">
                  <i class="bi bi-tools"></i>
                  <span>Handyman</span>
                </a>
              </li>
              <li>
                <a href="maintenance.php" class="footer-link">
                  <i class="bi bi-gear"></i>
                  <span>Maintenance</span>
                </a>
              </li>
            </ul>
          </div>
        </div>

        <!-- Services -->
        <div class="col-lg-2 col-md-6 col-6">
          <div class="footer-section">
            <h5 class="footer-title">Services</h5>
            <ul class="footer-links">
              <li>
                <a href="invoice_history.php" class="footer-link">
                  <i class="bi bi-credit-card"></i>
                  <span>Payment Center</span>
                </a>
              </li>
              <li>
               
              </li>
              <li>
                
              </li>
              <li>
                <a href="about.php" class="footer-link">
                  <i class="bi bi-info-circle"></i>
                  <span>About Us</span>
                </a>
              </li>
            </ul>
          </div>
        </div>

        <!-- Contact & Social -->
        <div class="col-lg-4 col-md-6">
          <div class="footer-section">
            <h5 class="footer-title">Connect With Us</h5>
            
            <!-- Contact Info -->
            <div class="contact-info">
              <div class="contact-item">
                <i class="bi bi-telephone"></i>
                <div class="contact-details">
                  <span class="contact-label">Phone</span>
                  <a href="tel:+639123456789" class="contact-value">+63 912 345 6789</a>
                </div>
              </div>
              <div class="contact-item">
                <i class="bi bi-envelope"></i>
                <div class="contact-details">
                  <span class="contact-label">Email</span>
                  <a href="mailto:info@asrt.com" class="contact-value">info@asrt.com</a>
                </div>
              </div>
            </div>

            <!-- Social Media -->
            <div class="social-section">
              <h6 class="social-title">Follow Us</h6>
              <div class="social-links">
                <a href="#" class="social-link facebook" title="Facebook">
                  <i class="bi bi-facebook"></i>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer Bottom -->
  <div class="footer-bottom">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-md-6">
          <div class="copyright">
            <span>&copy; 2024 ASRT Commercial Spacing. All rights reserved.</span>
          </div>
        </div>
        <div class="col-md-6">
          <div class="credits">
            <span>Designed & Developed by</span>
            <strong>NU LIPA Students INF232</strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</footer>

<style>
/* Modern Footer Styles */
.modern-footer {
  background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
  color: #e2e8f0;
  margin-top: 4rem;
  position: relative;
  overflow: hidden;
}

.modern-footer::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="footerPattern" patternUnits="userSpaceOnUse" width="40" height="40"><circle cx="20" cy="20" r="1" fill="white" opacity="0.05"/><circle cx="10" cy="10" r="0.5" fill="white" opacity="0.03"/><circle cx="30" cy="30" r="0.5" fill="white" opacity="0.03"/></pattern></defs><rect width="100" height="100" fill="url(%23footerPattern)"/></svg>');
}

.footer-main {
  padding: 4rem 0 2rem;
  position: relative;
  z-index: 2;
}

/* Brand Section */
.footer-brand .brand-logo {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.footer-brand .brand-logo i {
  font-size: 2rem;
  color: #3b82f6;
  background: rgba(59, 130, 246, 0.1);
  padding: 0.75rem;
  border-radius: 12px;
  border: 2px solid rgba(59, 130, 246, 0.2);
}

.footer-brand .brand-name {
  font-family: 'Playfair Display', serif;
  font-size: 1.5rem;
  font-weight: 700;
  color: white;
  line-height: 1.2;
}

.footer-brand .brand-description {
  color: #94a3b8;
  font-size: 1rem;
  line-height: 1.6;
  margin-bottom: 1.5rem;
  max-width: 350px;
}

.brand-features {
  display: flex;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #cbd5e1;
  font-size: 0.9rem;
  font-weight: 500;
}

.feature-item i {
  color: #10b981;
  font-size: 1rem;
}

/* Footer Sections */
.footer-section .footer-title {
  color: white;
  font-weight: 700;
  font-size: 1.1rem;
  margin-bottom: 1.5rem;
  position: relative;
  padding-bottom: 0.5rem;
}

.footer-section .footer-title::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 30px;
  height: 2px;
  background: linear-gradient(90deg, #3b82f6, #10b981);
  border-radius: 1px;
}

.footer-links {
  list-style: none;
  padding: 0;
  margin: 0;
}

.footer-links li {
  margin-bottom: 0.75rem;
}

.footer-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: #94a3b8;
  text-decoration: none;
  font-size: 0.95rem;
  font-weight: 500;
  transition: all 0.3s ease;
  padding: 0.5rem 0;
  border-radius: 6px;
}

.footer-link:hover {
  color: #3b82f6;
  transform: translateX(4px);
  text-decoration: none;
}

.footer-link i {
  font-size: 1rem;
  width: 18px;
  text-align: center;
  opacity: 0.8;
}

/* Contact Section */
.contact-info {
  margin-bottom: 2rem;
}

.contact-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
  padding: 0.75rem;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  transition: all 0.3s ease;
}

.contact-item:hover {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(59, 130, 246, 0.3);
}

.contact-item i {
  font-size: 1.25rem;
  color: #3b82f6;
  background: rgba(59, 130, 246, 0.1);
  padding: 0.5rem;
  border-radius: 8px;
  flex-shrink: 0;
}

.contact-details {
  display: flex;
  flex-direction: column;
}

.contact-label {
  font-size: 0.8rem;
  color: #94a3b8;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.contact-value {
  color: white;
  text-decoration: none;
  font-weight: 600;
  transition: color 0.3s ease;
}

.contact-value:hover {
  color: #3b82f6;
}

/* Social Media */
.social-section .social-title {
  color: #cbd5e1;
  font-size: 0.9rem;
  font-weight: 600;
  margin-bottom: 1rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.social-links {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.social-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  text-decoration: none;
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
}

.social-link::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  border-radius: inherit;
  padding: 2px;
  background: linear-gradient(135deg, transparent, rgba(255,255,255,0.1));
  -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  -webkit-mask-composite: xor;
  mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
  mask-composite: exclude;
}

.social-link i {
  font-size: 1.25rem;
  z-index: 2;
  position: relative;
}

.social-link.facebook {
  background: rgba(59, 89, 152, 0.15);
  color: #4267B2;
  border: 1px solid rgba(66, 103, 178, 0.3);
}

.social-link.facebook:hover {
  background: rgba(59, 89, 152, 0.25);
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(66, 103, 178, 0.3);
}

.social-link.instagram {
  background: rgba(225, 48, 108, 0.15);
  color: #E1306C;
  border: 1px solid rgba(225, 48, 108, 0.3);
}

.social-link.instagram:hover {
  background: rgba(225, 48, 108, 0.25);
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(225, 48, 108, 0.3);
}

.social-link.twitter {
  background: rgba(29, 161, 242, 0.15);
  color: #1DA1F2;
  border: 1px solid rgba(29, 161, 242, 0.3);
}

.social-link.twitter:hover {
  background: rgba(29, 161, 242, 0.25);
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(29, 161, 242, 0.3);
}

.social-link.linkedin {
  background: rgba(0, 119, 181, 0.15);
  color: #0077B5;
  border: 1px solid rgba(0, 119, 181, 0.3);
}

.social-link.linkedin:hover {
  background: rgba(0, 119, 181, 0.25);
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 119, 181, 0.3);
}

/* Footer Bottom */
.footer-bottom {
  background: rgba(15, 23, 42, 0.8);
  backdrop-filter: blur(10px);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  padding: 1.5rem 0;
  position: relative;
  z-index: 2;
}

.copyright {
  color: #94a3b8;
  font-size: 0.9rem;
}

.credits {
  color: #cbd5e1;
  font-size: 0.9rem;
  text-align: right;
}

.credits strong {
  color: #3b82f6;
  font-weight: 600;
}

/* Responsive Design */
@media (max-width: 768px) {
  .footer-main {
    padding: 3rem 0 1.5rem;
  }

  .footer-brand .brand-logo {
    justify-content: center;
    text-align: center;
  }

  .footer-brand .brand-description {
    text-align: center;
    max-width: none;
  }

  .brand-features {
    justify-content: center;
  }

  .footer-section {
    text-align: center;
  }

  .footer-section .footer-title::after {
    left: 50%;
    transform: translateX(-50%);
  }

  .footer-link {
    justify-content: center;
  }

  .contact-item {
    justify-content: center;
    text-align: center;
  }

  .social-links {
    justify-content: center;
  }

  .credits {
    text-align: center;
    margin-top: 1rem;
  }

  .footer-bottom .row {
    text-align: center;
  }
}

@media (max-width: 576px) {
  .footer-brand .brand-logo i {
    font-size: 1.5rem;
    padding: 0.5rem;
  }

  .footer-brand .brand-name {
    font-size: 1.25rem;
  }

  .feature-item {
    font-size: 0.85rem;
  }

  .contact-item {
    flex-direction: column;
    text-align: center;
    gap: 0.5rem;
  }

  .contact-details {
    align-items: center;
  }
}

/* Accessibility */
@media (prefers-reduced-motion: reduce) {
  .footer-link,
  .social-link,
  .contact-item {
    transition: none;
  }
  
  .footer-link:hover {
    transform: none;
  }
  
  .social-link:hover {
    transform: none;
  }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
  .modern-footer {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  }
}
</style>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>

<!-- Enhanced Navbar auto-collapse script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Navbar collapse functionality
  const navbarCollapse = document.getElementById('navbarNav');
  if (navbarCollapse) {
    navbarCollapse.addEventListener('click', function(e) {
      let target = e.target;
      while (target && target !== navbarCollapse) {
        if (
          target.classList &&
          (target.classList.contains('nav-link') ||
            target.classList.contains('navbar-btn-equal') ||
            (target.classList.contains('btn') && !target.classList.contains('navbar-toggler')))
        ) {
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

  // Smooth scrolling for footer links
  document.querySelectorAll('.footer-link[href^="#"]').forEach(link => {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      const targetId = this.getAttribute('href').substring(1);
      const targetElement = document.getElementById(targetId);
      if (targetElement) {
        targetElement.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });

  // Add loading states to contact links
  document.querySelectorAll('.contact-value').forEach(link => {
    link.addEventListener('click', function() {
      // Add brief loading state for contact interactions
      const originalText = this.textContent;
      if (this.href.startsWith('tel:')) {
        this.textContent = 'Connecting...';
        setTimeout(() => {
          this.textContent = originalText;
        }, 1500);
      } else if (this.href.startsWith('mailto:')) {
        this.textContent = 'Opening...';
        setTimeout(() => {
          this.textContent = originalText;
        }, 1500);
      }
    });
  });

  // Enhanced social media tracking
  document.querySelectorAll('.social-link').forEach(link => {
    link.addEventListener('click', function(e) {
      const platform = this.className.split(' ').find(cls => 
        ['facebook', 'instagram', 'twitter', 'linkedin'].includes(cls)
      );
      
      // Add analytics tracking here if needed
      console.log(`Social media click: ${platform}`);
      
      // Add visual feedback
      this.style.transform = 'translateY(-4px) scale(1.05)';
      setTimeout(() => {
        this.style.transform = '';
      }, 200);
    });
  });

  // Lazy load social media icons for better performance
  if ('IntersectionObserver' in window) {
    const socialObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const socialLink = entry.target;
          socialLink.style.opacity = '1';
          socialLink.style.transform = 'translateY(0)';
        }
      });
    }, {
      threshold: 0.1
    });

    document.querySelectorAll('.social-link').forEach(link => {
      link.style.opacity = '0';
      link.style.transform = 'translateY(20px)';
      link.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
      socialObserver.observe(link);
    });
  }
});
</script>
</body>
</html>
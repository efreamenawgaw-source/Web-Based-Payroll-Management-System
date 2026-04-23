<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BiT Payroll Management System &rdquo;” Bahir Dar Institute of Technology</title>
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar" id="navbar">
    <div class="nav-container">
        <!-- Brand -->
        <a href="home.php" class="nav-brand">
            <div class="nav-logo">BiT</div>
            <div class="nav-brand-text">
                <span class="nav-brand-title">BiT Payroll</span>
                <span class="nav-brand-sub">Management System</span>
            </div>
        </a>

        <!-- Desktop Nav Links -->
        <ul class="nav-links" id="navLinks">
            <li><a href="#home" class="nav-link active">Home</a></li>
            <li><a href="#about" class="nav-link">About</a></li>
            <li><a href="#features" class="nav-link">Features</a></li>
            <li><a href="#contact" class="nav-link">Contact</a></li>
            <li><a href="pages/auth/login.php" class="nav-btn">
                <i class="fas fa-sign-in-alt"></i> Login
            </a></li>
        </ul>

        <!-- Hamburger -->
        <button class="hamburger" id="hamburger" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</nav>

<!-- ===== HERO SECTION ===== -->
<section class="hero" id="home">
    <div class="hero-bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
    </div>
    <div class="hero-container">
        <div class="hero-content">
            <div class="hero-badge">
                <i class="fas fa-university"></i>
                Bahir Dar Institute of Technology
            </div>
            <h1 class="hero-title">
                Web-Based Payroll<br>
                <span class="hero-highlight">Management System</span>
            </h1>
            <p class="hero-desc">
                A secure, automated web-based solution for managing employee salaries,
                allowances, Ethiopian tax calculations, pension contributions, and
                digital payslip generation &rdquo;” all in one place.
            </p>
            <div class="hero-actions">
                <a href="pages/auth/login.php" class="btn-hero-primary">
                    <i class="fas fa-sign-in-alt"></i> Access System
                </a>
                <a href="#about" class="btn-hero-secondary">
                    <i class="fas fa-info-circle"></i> Learn More
                </a>
            </div>
            <!-- Quick Stats -->
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-num">135+</span>
                    <span class="hero-stat-label">Staff Members</span>
                </div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat">
                    <span class="hero-stat-num">4</span>
                    <span class="hero-stat-label">User Roles</span>
                </div>
                <div class="hero-stat-divider"></div>
                <div class="hero-stat">
                    <span class="hero-stat-num">100%</span>
                    <span class="hero-stat-label">Automated</span>
                </div>
            </div>
        </div>
        <div class="hero-visual">
            <div class="dashboard-mockup">
                <div class="mockup-header">
                    <div class="mockup-dots">
                        <span></span><span></span><span></span>
                    </div>
                    <span class="mockup-title">BiT Payroll Dashboard</span>
                </div>
                <div class="mockup-body">
                    <div class="mockup-stat-row">
                        <div class="mockup-stat blue">
                            <i class="fas fa-users"></i>
                            <div>
                                <p>Total Staff</p>
                                <h3>135</h3>
                            </div>
                        </div>
                        <div class="mockup-stat green">
                            <i class="fas fa-money-bill-wave"></i>
                            <div>
                                <p>Monthly Payout</p>
                                <h3>ETB 1.69M</h3>
                            </div>
                        </div>
                    </div>
                    <div class="mockup-stat-row">
                        <div class="mockup-stat orange">
                            <i class="fas fa-file-invoice"></i>
                            <div>
                                <p>Payslips Generated</p>
                                <h3>135</h3>
                            </div>
                        </div>
                        <div class="mockup-stat purple">
                            <i class="fas fa-shield-alt"></i>
                            <div>
                                <p>Tax Compliance</p>
                                <h3>100%</h3>
                            </div>
                        </div>
                    </div>
                    <div class="mockup-bar-section">
                        <p>Payroll Processing</p>
                        <div class="mockup-bar"><div class="mockup-bar-fill" style="width:97%"></div></div>
                        <p>Tax Calculation</p>
                        <div class="mockup-bar"><div class="mockup-bar-fill" style="width:100%"></div></div>
                        <p>Payslip Generation</p>
                        <div class="mockup-bar"><div class="mockup-bar-fill" style="width:88%"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== ABOUT SECTION ===== -->
<section class="about-section" id="about">
    <div class="section-container">
        <div class="section-header">
            <span class="section-tag">About the System</span>
            <h2>What is BiT Payroll System?</h2>
            <p>
                The BiT Payroll Management System is developed for Bahir Dar Institute of Technology
                to replace manual, spreadsheet-based payroll processing with a fully automated,
                secure, and centralized web-based solution.
            </p>
        </div>

        <div class="about-grid">
            <div class="about-card">
                <div class="about-icon" style="background:#E3F2FD;color:#1565C0;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>The Problem</h3>
                <p>
                    The existing payroll system at BiT relies on manual methods and spreadsheets,
                    leading to calculation errors, delays, poor data security, and limited
                    employee access to payroll information.
                </p>
            </div>
            <div class="about-card">
                <div class="about-icon" style="background:#E8F5E9;color:#2E7D32;">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <h3>Our Solution</h3>
                <p>
                    A secure web-based system that automates salary calculations, applies
                    Ethiopian tax and pension rules, generates digital payslips, and provides
                    role-based access for Admin, HR, Finance, and Employees.
                </p>
            </div>
            <div class="about-card">
                <div class="about-icon" style="background:#FFF3E0;color:#E65100;">
                    <i class="fas fa-users"></i>
                </div>
                <h3>Who Benefits</h3>
                <p>
                    BiT institution, HR staff, Finance department, and all employees benefit
                    from improved accuracy, transparency, faster processing, and convenient
                    self-service access to payroll data.
                </p>
            </div>
        </div>

        <!-- System Actors -->
        <div class="roles-section">
            <h3 style="text-align:center;margin-bottom:28px;color:#263238;">System User Roles</h3>
            <div class="roles-grid">
                <div class="role-card">
                    <div class="role-icon" style="background:#0D47A1;">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h4>Administrator</h4>
                    <p>Manages user accounts, assigns roles, monitors system activity and security.</p>
                    <ul class="role-list">
                        <li><i class="fas fa-check"></i> Create & manage users</li>
                        <li><i class="fas fa-check"></i> Assign system roles</li>
                        <li><i class="fas fa-check"></i> View audit logs</li>
                        <li><i class="fas fa-check"></i> System configuration</li>
                    </ul>
                </div>
                <div class="role-card">
                    <div class="role-icon" style="background:#1565C0;">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4>HR Personnel</h4>
                    <p>Manages employee records, allowances, and employment status changes.</p>
                    <ul class="role-list">
                        <li><i class="fas fa-check"></i> Register employees</li>
                        <li><i class="fas fa-check"></i> Update employee info</li>
                        <li><i class="fas fa-check"></i> Manage allowances</li>
                        <li><i class="fas fa-check"></i> Update status</li>
                    </ul>
                </div>
                <div class="role-card">
                    <div class="role-icon" style="background:#1976D2;">
                        <i class="fas fa-coins"></i>
                    </div>
                    <h4>Finance Officer</h4>
                    <p>Processes payroll, verifies calculations, and generates financial reports.</p>
                    <ul class="role-list">
                        <li><i class="fas fa-check"></i> Process payroll</li>
                        <li><i class="fas fa-check"></i> Verify & approve</li>
                        <li><i class="fas fa-check"></i> Generate payslips</li>
                        <li><i class="fas fa-check"></i> Financial reports</li>
                    </ul>
                </div>
                <div class="role-card">
                    <div class="role-icon" style="background:#2196F3;">
                        <i class="fas fa-id-badge"></i>
                    </div>
                    <h4>Employee</h4>
                    <p>Views personal payroll information and downloads payslips securely.</p>
                    <ul class="role-list">
                        <li><i class="fas fa-check"></i> View payslips</li>
                        <li><i class="fas fa-check"></i> Download PDF</li>
                        <li><i class="fas fa-check"></i> View salary history</li>
                        <li><i class="fas fa-check"></i> Personal profile</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== FEATURES SECTION ===== -->
<section class="features-section" id="features">
    <div class="section-container">
        <div class="section-header">
            <span class="section-tag">Key Features</span>
            <h2>Everything You Need for Payroll</h2>
            <p>Built specifically for Ethiopian institutions with full compliance to national tax and pension regulations.</p>
        </div>

        <div class="features-grid">
            <?php
            $features = [
                ['fas fa-calculator',        '#1565C0', 'Automated Salary Calculation',
                 'Automatically computes gross salary, allowances, deductions, and net pay for all employees.'],
                ['fas fa-percent',           '#2E7D32', 'Ethiopian Tax Compliance',
                 'Applies Ethiopian income tax brackets (Proclamation 1395/2025) accurately for every employee.'],
                ['fas fa-piggy-bank',        '#E65100', 'Pension Management',
                 'Calculates 11% employee pension and 18% employer pension contributions automatically.'],
                ['fas fa-file-invoice-dollar','#6A1B9A','Digital Payslip Generation',
                 'Generates electronic payslips that employees can view and download as PDF anytime.'],
                ['fas fa-shield-alt',        '#C62828', 'Role-Based Security',
                 'RBAC ensures each user only accesses data relevant to their role. Passwords are encrypted.'],
                ['fas fa-chart-bar',         '#0277BD', 'Financial Reports',
                 'Generates comprehensive payroll reports for management, auditing, and CBE bank transfers.'],
                ['fas fa-mobile-alt',        '#1565C0', 'Mobile Responsive',
                 'Fully responsive design works seamlessly on desktops, tablets, and smartphones.'],
                ['fas fa-history',           '#2E7D32', 'Payroll History',
                 'Maintains complete historical payroll records for auditing and employee reference.'],
            ];
            foreach ($features as $f): ?>
            <div class="feature-card">
                <div class="feature-icon" style="background:<?= $f[1] ?>20;color:<?= $f[1] ?>;">
                    <i class="<?= $f[0] ?>"></i>
                </div>
                <h4><?= $f[2] ?></h4>
                <p><?= $f[3] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== HOW IT WORKS ===== -->
<section class="how-section">
    <div class="section-container">
        <div class="section-header">
            <span class="section-tag">Workflow</span>
            <h2>How the System Works</h2>
            <p>A simple, streamlined payroll workflow from employee registration to payslip delivery.</p>
        </div>
        <div class="steps-grid">
            <div class="step-card">
                <div class="step-num">1</div>
                <div class="step-icon"><i class="fas fa-user-plus"></i></div>
                <h4>HR Registers Employee</h4>
                <p>HR personnel add employee details, salary structure, and allowances to the system.</p>
            </div>
            <div class="step-arrow"><i class="fas fa-chevron-right"></i></div>
            <div class="step-card">
                <div class="step-num">2</div>
                <div class="step-icon"><i class="fas fa-play-circle"></i></div>
                <h4>Finance Processes Payroll</h4>
                <p>Finance officer runs monthly payroll &rdquo;” system auto-calculates tax and pension.</p>
            </div>
            <div class="step-arrow"><i class="fas fa-chevron-right"></i></div>
            <div class="step-card">
                <div class="step-num">3</div>
                <div class="step-icon"><i class="fas fa-check-double"></i></div>
                <h4>Verify & Approve</h4>
                <p>Finance reviews and approves the payroll data before finalizing.</p>
            </div>
            <div class="step-arrow"><i class="fas fa-chevron-right"></i></div>
            <div class="step-card">
                <div class="step-num">4</div>
                <div class="step-icon"><i class="fas fa-download"></i></div>
                <h4>Employee Downloads Payslip</h4>
                <p>Employees log in and download their digital payslip as PDF anytime.</p>
            </div>
        </div>
    </div>
</section>

<!-- ===== CONTACT SECTION ===== -->
<section class="contact-section" id="contact">
    <div class="section-container">
        <div class="section-header">
            <span class="section-tag">Contact Us</span>
            <h2>Get in Touch</h2>
            <p>Have questions about the system? Reach out to the BiT IT department or project team.</p>
        </div>

        <div class="contact-grid">
            <!-- Contact Info -->
            <div class="contact-info">
                <div class="contact-info-card">
                    <div class="contact-info-icon"><i class="fas fa-university"></i></div>
                    <div>
                        <h4>Institution</h4>
                        <p>Bahir Dar Institute of Technology (BiT)<br>Bahir Dar University</p>
                    </div>
                </div>
                <div class="contact-info-card">
                    <div class="contact-info-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div>
                        <h4>Address</h4>
                        <p>Bahir Dar, Amhara Region<br>Ethiopia</p>
                    </div>
                </div>
                <div class="contact-info-card">
                    <div class="contact-info-icon"><i class="fas fa-envelope"></i></div>
                    <div>
                        <h4>Email</h4>
                        <p>payroll@bit.edu.et<br>it@bit.edu.et</p>
                    </div>
                </div>
                <div class="contact-info-card">
                    <div class="contact-info-icon"><i class="fas fa-phone"></i></div>
                    <div>
                        <h4>Phone</h4>
                        <p>+251 58 220 6112<br>+251 58 220 6113</p>
                    </div>
                </div>
                <div class="contact-info-card">
                    <div class="contact-info-icon"><i class="fas fa-clock"></i></div>
                    <div>
                        <h4>Office Hours</h4>
                        <p>Monday &rdquo;“ Friday: 8:00 AM &rdquo;“ 5:00 PM<br>Saturday: 8:00 AM &rdquo;“ 12:00 PM</p>
                    </div>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="contact-form-card">
                <h3>Send a Message</h3>
                <form class="contact-form" onsubmit="handleContact(event)">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" class="form-input" placeholder="Your full name" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" class="form-input" placeholder="your@email.com" required>
                    </div>
                    <div class="form-group">
                        <label>Subject</label>
                        <select class="form-input">
                            <option>General Inquiry</option>
                            <option>Technical Support</option>
                            <option>Payslip Issue</option>
                            <option>Account Access</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea class="form-input" rows="4" placeholder="Your message..." required></textarea>
                    </div>
                    <button type="submit" class="btn-contact-submit">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
                <div class="contact-success" id="contactSuccess" style="display:none;">
                    <i class="fas fa-check-circle"></i>
                    <p>Message sent successfully! We'll get back to you soon.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-grid">
            <!-- Brand -->
            <div class="footer-brand">
                <div class="footer-logo">
                    <div class="footer-logo-box">BiT</div>
                    <div>
                        <h3>BiT Payroll System</h3>
                        <p>Bahir Dar Institute of Technology</p>
                    </div>
                </div>
                <p class="footer-desc">
                    A secure, automated web-based payroll management system designed
                    for BiT staff &rdquo;” ensuring accurate, transparent, and timely salary processing.
                </p>
            </div>

            <!-- Quick Links -->
            <div class="footer-links">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="#home"><i class="fas fa-chevron-right"></i> Home</a></li>
                    <li><a href="#about"><i class="fas fa-chevron-right"></i> About</a></li>
                    <li><a href="#features"><i class="fas fa-chevron-right"></i> Features</a></li>
                    <li><a href="#contact"><i class="fas fa-chevron-right"></i> Contact</a></li>
                    <li><a href="pages/auth/login.php"><i class="fas fa-chevron-right"></i> Login</a></li>
                </ul>
            </div>

            <!-- System Modules -->
            <div class="footer-links">
                <h4>System Modules</h4>
                <ul>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> User Management</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Employee Management</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Payroll Processing</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Payslip Generation</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Financial Reports</a></li>
                </ul>
            </div>

            <!-- Tech Stack -->
            <div class="footer-links">
                <h4>Built With</h4>
                <ul>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> PHP (Backend)</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> MySQL (Database)</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> HTML / CSS / JS</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Apache Web Server</a></li>
                    <li><a href="#"><i class="fas fa-chevron-right"></i> Font Awesome Icons</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Bahir Dar Institute of Technology &rdquo;” BiT Payroll Management System. All rights reserved.</p>
            <p style="font-size:0.78rem;opacity:0.6;margin-top:4px;">
                Developed by: Efream Enawgaw &bull; Abebe Guadie &bull; Chalachew Assefa &nbsp;|&nbsp; Advisor: Tiruedel A
            </p>
        </div>
    </div>
</footer>

<!-- Back to Top -->
<button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})">
    <i class="fas fa-chevron-up"></i>
</button>

<script>
// ---- Navbar scroll effect ----
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
    // Back to top button
    document.getElementById('backToTop').classList.toggle('visible', window.scrollY > 400);
});

// ---- Hamburger menu ----
const hamburger = document.getElementById('hamburger');
const navLinks  = document.getElementById('navLinks');
hamburger.addEventListener('click', () => {
    hamburger.classList.toggle('open');
    navLinks.classList.toggle('open');
});

// Close menu on link click
document.querySelectorAll('.nav-link, .nav-btn').forEach(link => {
    link.addEventListener('click', () => {
        hamburger.classList.remove('open');
        navLinks.classList.remove('open');
    });
});

// ---- Active nav link on scroll ----
const sections = document.querySelectorAll('section[id]');
window.addEventListener('scroll', () => {
    let current = '';
    sections.forEach(section => {
        if (window.scrollY >= section.offsetTop - 80) {
            current = section.getAttribute('id');
        }
    });
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === '#' + current) {
            link.classList.add('active');
        }
    });
});

// ---- Smooth scroll for anchor links ----
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

// ---- Contact form ----
function handleContact(e) {
    e.preventDefault();
    document.querySelector('.contact-form').style.display = 'none';
    document.getElementById('contactSuccess').style.display = 'flex';
}

// ---- Animate on scroll (simple) ----
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animate-in');
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.about-card, .feature-card, .role-card, .step-card').forEach(el => {
    observer.observe(el);
});
</script>
</body>
</html>


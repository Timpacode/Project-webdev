<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ABSM SYSTEM - Barangay Document Retrieval</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="Homepage.css">
</head>
<body>
    <!-- Notification Message -->
    <div class="success-message" id="successMessage">
        <i class="fas fa-check-circle"></i>
        <span class="message-text">Your request has been submitted successfully!</span>
    </div>

    <!-- Header -->
    <header>
        <div class="container header-content">
            <div class="logo-container">
                <img class="logo-img" src="Barangay.png" alt="Barangay Logo">
                <div class="logo">
                    <span class="logo-main">ABSM SYSTEM</span>
                    <span class="logo-sub">Barangay Services</span>
                </div>
            </div>
            
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
            
            <nav>
                <ul>
                    <li><a href="#home"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="#services"><i class="fas fa-file-alt"></i> Services</a></li>
                    <li><a href="#process"><i class="fas fa-cogs"></i> Process</a></li>
                    <li><a href="#contact"><i class="fas fa-phone"></i> Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Mobile Navigation -->
    <div class="mobile-nav" id="mobileNav">
        <button class="close-mobile-menu" id="closeMobileMenu">
            <i class="fas fa-times"></i>
        </button>
        <div class="mobile-nav-header">
            <img src="Barangay.png" alt="Barangay Logo">
            <h3>ABSM SYSTEM</h3>
        </div>
        <ul>
            <li><a href="#home"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="#services"><i class="fas fa-file-alt"></i> Services</a></li>
            <li><a href="#process"><i class="fas fa-cogs"></i> Process</a></li>
            <li><a href="#contact"><i class="fas fa-phone"></i> Contact</a></li>
        </ul>
    </div>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container hero-content">
            <div class="hero-text">
                <h1>Barangay Document Retrieval System</h1>
                <p>Streamlining barangay services for faster, more efficient document processing.</p>
                
                <div class="hero-features">
                    <div class="feature">
                        <i class="fas fa-clock"></i>
                        <span>24/7 Online Access</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-bolt"></i>
                        <span>Quick Processing</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-check-circle"></i>
                        <span>100% Secure</span>
                    </div>
                    <div class="feature">
                        <i class="fas fa-search"></i>
                        <span>Track Your Request</span>
                    </div>
                </div>

                <div class="hero-actions">
                    <a href="#services" class="btn-primary">
                        <i class="fas fa-file-download"></i>
                        Get Documents
                    </a>
                    <a href="#process" class="btn-secondary">
                        <i class="fas fa-play-circle"></i>
                        How It Works
                    </a>
                    <button class="btn-secondary" onclick="trackRequest()">
                        <i class="fas fa-search"></i>
                        Track Request
                    </button>
                </div>
            </div>
            <div class="hero-image">
                <div class="image-container">
                    <img src="Location.png" alt="Barangay Location" class="main-image">
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Our Services</h2>
                <p class="section-subtitle">Access various barangay documents with just a few clicks</p>
            </div>
            
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div class="service-content">
                        <h3>Barangay Clearance</h3>
                        <p>Official certification of residency and good moral character.</p>
                        <div class="service-details">
                            <div class="service-fee">Fee: ₱50.00</div>
                            <div class="service-duration">Processing: 1 day</div>
                        </div>
                        <a class="proceed-btn" data-document="Barangay Clearance" data-document-id="1">
                            <i class="fas fa-arrow-right"></i>
                            Request Now
                        </a>
                    </div>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="service-content">
                        <h3>Certificate of Residency</h3>
                        <p>Proof of residence for various applications.</p>
                        <div class="service-details">
                            <div class="service-fee">Fee: ₱30.00</div>
                            <div class="service-duration">Processing: 1 day</div>
                        </div>
                        <a class="proceed-btn" data-document="Certificate of Residency" data-document-id="2">
                            <i class="fas fa-arrow-right"></i>
                            Request Now
                        </a>
                    </div>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-hand-holding-heart"></i>
                    </div>
                    <div class="service-content">
                        <h3>Certificate of Indigency</h3>
                        <p>Document certifying financial status for assistance programs.</p>
                        <div class="service-details">
                            <div class="service-fee">Fee: Free</div>
                            <div class="service-duration">Processing: 3 days</div>
                        </div>
                        <a class="proceed-btn" data-document="Certificate of Indigency" data-document-id="3">
                            <i class="fas fa-arrow-right"></i>
                            Request Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Process Section -->
    <section class="process" id="process">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">How It Works</h2>
                <p class="section-subtitle">Simple process to get your documents quickly</p>
            </div>
            
            <div class="process-steps">
                <div class="process-step">
                    <div class="step-icon">
                        <i class="fas fa-file-signature"></i>
                    </div>
                    <h3>Submit Request</h3>
                    <p>Fill out the online form with your information</p>
                </div>
                
                <div class="process-step">
                    <div class="step-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Verification</h3>
                    <p>Our staff verifies your information</p>
                </div>
                
                <div class="process-step">
                    <div class="step-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3>Get Notified</h3>
                    <p>Receive notification when ready</p>
                </div>
                
                <div class="process-step">
                    <div class="step-icon">
                        <i class="fas fa-file-download"></i>
                    </div>
                    <h3>Collect Document</h3>
                    <p>Pick up at barangay hall with valid ID</p>
                </div>
            </div>

            <div class="tracking-section">
                <div class="tracking-content">
                    <h3>Track Your Request</h3>
                    <p>Use your request code to check the status of your document request anytime.</p>
                    <button class="btn-primary" onclick="trackRequest()">
                        <i class="fas fa-search"></i>
                        Track Request Now
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <footer id="contact">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <img src="Barangay.png" alt="Barangay Logo">
                        <div>
                            <h3>ABSM SYSTEM</h3>
                            <p>Barangay Document Retrieval</p>
                        </div>
                    </div>
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Barangay Hall, Main Street</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span>(072) 123-4567</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span>info@barangay.gov.ph</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <span>Mon-Fri: 8:00 AM - 5:00 PM</span>
                        </div>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="#home"><i class="fas fa-chevron-right"></i> Home</a></li>
                        <li><a href="#services"><i class="fas fa-chevron-right"></i> Services</a></li>
                        <li><a href="#process"><i class="fas fa-chevron-right"></i> Process</a></li>
                        <li><a href="#contact"><i class="fas fa-chevron-right"></i> Contact</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Quick Actions</h3>
                    <ul class="footer-links">
                        <li><a href="#" onclick="trackRequest(); return false;"><i class="fas fa-chevron-right"></i> Track Request</a></li>
                        <li><a href="#services"><i class="fas fa-chevron-right"></i> Request Document</a></li>
                        <li><a href="tel:(072)123-4567"><i class="fas fa-chevron-right"></i> Call Us</a></li>
                        <li><a href="mailto:info@barangay.gov.ph"><i class="fas fa-chevron-right"></i> Email Us</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="copyright">
                    <p>&copy; 2024 <span class="brand-highlight">ABSM SYSTEM</span>. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Request Form -->
    <div class="request-form-container" id="requestForm">
        <div class="request-form">
            <button class="close-btn" id="closeForm">&times;</button>
            <div class="form-header">
                <div class="form-icon">
                    <i class="fas fa-file-contract"></i>
                </div>
                <h2>Document Request Form</h2>
                <p>Fill out the form below to request your barangay document</p>
            </div>
            
            <form id="documentRequestForm">
                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>
                    
                    <div class="form-group">
                        <label for="documentType">Document Type</label>
                        <input type="text" id="documentType" name="documentType" readonly>
                        <input type="hidden" id="documentTypeId" name="documentTypeId">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fullName">Full Name *</label>
                            <input type="text" id="fullName" name="fullName" required placeholder="Enter your full name">
                        </div>
                        
                        <div class="form-group">
                            <label for="contactNumber">Contact Number *</label>
                            <input type="text" id="contactNumber" name="contactNumber" required placeholder="09XXXXXXXXX">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="birthdate">Birthdate</label>
                            <input type="date" id="birthdate" name="birthdate" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="occupation">Occupation</label>
                            <input type="text" id="occupation" name="occupation" placeholder="Your current occupation">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required placeholder="your.email@example.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Complete Address *</label>
                        <input type="text" id="address" name="address" required placeholder="Street, Barangay, City">
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-edit"></i> Request Details</h3>
                    
              <div class="form-group">
    <label for="purpose">Purpose of Request</label>
    <textarea id="purpose" name="purpose" placeholder="Please describe the purpose of your document request..."></textarea>
    <div class="char-count">0/500 characters</div>
</div>
                </div>
                
                <div class="form-notice">
                    <i class="fas fa-info-circle"></i>
                    <p>Please ensure all information is accurate. You will need to present valid ID when picking up your document.</p>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-paper-plane"></i>
                    Submit Request
                </button>
            </form>
        </div>
    </div>

    <!-- Quick Action Buttons -->
    <div class="quick-actions">
        <button class="quick-action-btn" id="scrollToTop">
            <i class="fas fa-arrow-up"></i>
        </button>
        <button class="quick-action-btn" id="helpBtn">
            <i class="fas fa-question"></i>
        </button>
        <button class="quick-action-btn" id="trackRequestBtn">
            <i class="fas fa-search"></i>
        </button>
    </div>

    <script src="HomepageJS.js"></script>
</body>
</html>
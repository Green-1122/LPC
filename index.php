<?php
/**
 * Hana-Eunhaeng | Liberty Point Capital
 * Production-Grade Fintech Online Banking Platform
 * 
 * Entry Point: index.php
 * Architecture: Secure MVC-inspired, responsive fintech dashboard
 * Tech Stack: PHP 8+, MySQL, HTML5, CSS3, Vanilla JavaScript
 * 
 * @package Hana-Eunhaeng
 * @version 1.0.0
 * @author Development Team
 */

// ============================================================================
// 1. SECURITY & PERFORMANCE HEADERS
// ============================================================================

// Prevent caching for dynamic content
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Content Security Policy (fintech-safe)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://charts.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com; connect-src 'self' https://api.github.com");

// Set content type
header('Content-Type: text/html; charset=utf-8');

// ============================================================================
// 2. APPLICATION INITIALIZATION
// ============================================================================

// Define base path for consistency
define('BASE_PATH', __DIR__ . '/');
define('APP_VERSION', '1.0.0');
define('APP_NAME', 'Hana-Eunhaeng');
define('APP_DISPLAY_NAME', 'Liberty Point Capital');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

// ============================================================================
// 3. ROUTING & CONTROLLER LOGIC
// ============================================================================

// Simple routing based on request URI
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request_uri = str_replace('/index.php', '', $request_uri);
$request_uri = trim($request_uri, '/');

// Route to appropriate page
if (empty($request_uri) || $request_uri === '') {
    // Dashboard/Home
    $page = 'dashboard';
} else {
    $page = htmlspecialchars($request_uri, ENT_QUOTES, 'UTF-8');
}

// Valid pages
$valid_pages = ['dashboard', 'login', 'accounts', 'transfer', 'cards', 'investments', 'loans', 'settings', 'support', 'logout'];

// Validate and set current page
$current_page = in_array($page, $valid_pages) ? $page : 'dashboard';

// Track user session state (mock for now)
$is_authenticated = isset($_SESSION['user_id']);

// ============================================================================
// 4. AUTHENTICATION CHECK
// ============================================================================

// Redirect to login if accessing protected routes while not authenticated
$protected_pages = ['dashboard', 'accounts', 'transfer', 'cards', 'investments', 'loans', 'settings'];

if (in_array($current_page, $protected_pages) && !$is_authenticated && $current_page !== 'login') {
    $current_page = 'login';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- ================================================================
         META & SECURITY
         ================================================================ -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="<?php echo APP_DISPLAY_NAME; ?> - Premium Online Banking, Investment Portfolio, Wealth Management, and Financial Planning Platform">
    <meta name="keywords" content="banking, investments, wealth management, fintech, financial planning, trading">
    <meta name="author" content="<?php echo APP_DISPLAY_NAME; ?>">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo APP_DISPLAY_NAME; ?>">
    
    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>">
    <meta property="og:title" content="<?php echo APP_DISPLAY_NAME; ?>">
    <meta property="og:description" content="Premium Online Banking & Wealth Management Platform">
    <meta property="og:image" content="<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>/assets/img/og-image.png">
    <meta property="og:site_name" content="<?php echo APP_DISPLAY_NAME; ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo APP_DISPLAY_NAME; ?>">
    <meta name="twitter:description" content="Premium Online Banking & Wealth Management Platform">
    <meta name="twitter:image" content="<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']; ?>/assets/img/twitter-card.png">
    
    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16x16.png">
    <link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.json">
    
    <!-- Page Title -->
    <title><?php 
        $page_titles = [
            'dashboard' => 'Dashboard',
            'login' => 'Login',
            'accounts' => 'My Accounts',
            'transfer' => 'Transfer Money',
            'cards' => 'My Cards',
            'investments' => 'Investments',
            'loans' => 'Loans',
            'settings' => 'Settings',
            'support' => 'Support'
        ];
        echo ($page_titles[$current_page] ?? 'Welcome') . ' | ' . APP_DISPLAY_NAME;
    ?></title>
    
    <!-- ================================================================
         STYLESHEETS & FONTS
         ================================================================ -->
    
    <!-- Google Fonts: Inter, Playfair Display (financial elegance) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="preload" as="style">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS (optional; customize or replace with Tailwind) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUarbnLVrZ8/tf2jbcopqvnqnqsbnqQGDALSw0IltjJhsydkam7a" crossorigin="anonymous">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Core Application Stylesheet -->
    <link rel="stylesheet" href="/assets/css/variables.css">
    <link rel="stylesheet" href="/assets/css/global.css">
    <link rel="stylesheet" href="/assets/css/components.css">
    <link rel="stylesheet" href="/assets/css/layout.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="stylesheet" href="/assets/css/animations.css">
    <link rel="stylesheet" href="/assets/css/dark-mode.css">
    
    <!-- Page-specific Stylesheet -->
    <link rel="stylesheet" href="/assets/css/pages/<?php echo $current_page; ?>.css">
    
    <!-- ================================================================
         PERFORMANCE & PRELOAD HINTS
         ================================================================ -->
    
    <!-- Preload critical assets -->
    <link rel="preload" href="/assets/fonts/inter-regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/css/global.css" as="style">
    
    <!-- DNS prefetch for external APIs -->
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://charts.jsdelivr.net">
    
    <!-- ================================================================
         NOSCRIPT FALLBACK
         ================================================================ -->
    <noscript>
        <style>
            .js-required { display: block; }
            .js-hidden { display: none; }
        </style>
    </noscript>
</head>

<body class="<?php echo 'page-' . $current_page; echo $is_authenticated ? ' authenticated' : ' unauthenticated'; ?>">
    
    <!-- ================================================================
         LAYOUT WRAPPER
         ================================================================ -->
    <div id="app-root" class="app-container" data-page="<?php echo $current_page; ?>" data-authenticated="<?php echo $is_authenticated ? 'true' : 'false'; ?>">
        
        <!-- No JavaScript Fallback -->
        <noscript class="js-required alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>JavaScript is required</strong> to use <?php echo APP_DISPLAY_NAME; ?>.
            Please enable JavaScript in your browser settings.
        </noscript>
        
        <!-- Loading Screen -->
        <div id="loading-screen" class="loading-overlay" style="display: none;">
            <div class="loading-spinner">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted">Initializing <?php echo APP_DISPLAY_NAME; ?>...</p>
            </div>
        </div>
        
        <!-- Navigation (conditionally loaded based on authentication) -->
        <?php if ($is_authenticated): ?>
            <nav id="main-nav" class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top" role="navigation">
                <div class="container-fluid px-4">
                    <a class="navbar-brand fw-bold" href="/">
                        <i class="bi bi-bank me-2"></i><?php echo APP_DISPLAY_NAME; ?>
                    </a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarNav">
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item"><a class="nav-link" href="/dashboard">Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="/accounts">Accounts</a></li>
                            <li class="nav-item"><a class="nav-link" href="/transfer">Transfer</a></li>
                            <li class="nav-item"><a class="nav-link" href="/investments">Investments</a></li>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-person-circle"></i> Account
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                    <li><a class="dropdown-item" href="/settings">Settings</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="/logout">Logout</a></li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        <?php endif; ?>
        
        <!-- Main Content Area -->
        <main id="main-content" class="main-content" role="main">
            
            <?php
                // Load page content based on routing
                $view_path = BASE_PATH . 'views/' . $current_page . '.php';
                
                if (file_exists($view_path)) {
                    include $view_path;
                } else {
                    // Fallback 404 view
                    ?>
                    <section class="hero-section text-center py-5">
                        <div class="container">
                            <h1 class="display-4 fw-bold mb-3">Page Not Found</h1>
                            <p class="lead text-muted">The page you're looking for doesn't exist.</p>
                            <a href="/" class="btn btn-primary btn-lg mt-4">
                                <i class="bi bi-house me-2"></i>Return to Home
                            </a>
                        </div>
                    </section>
                    <?php
                }
            ?>
            
        </main>
        
        <!-- Footer -->
        <footer id="app-footer" class="footer bg-dark text-light py-5 mt-5" role="contentinfo">
            <div class="container">
                <div class="row mb-4">
                    <div class="col-md-4 mb-4 mb-md-0">
                        <h5 class="fw-bold mb-3"><?php echo APP_DISPLAY_NAME; ?></h5>
                        <p class="text-muted small">Premium online banking and wealth management platform for modern investors.</p>
                    </div>
                    <div class="col-md-2 mb-4 mb-md-0">
                        <h6 class="fw-bold mb-3">Product</h6>
                        <ul class="list-unstyled small">
                            <li><a href="#" class="text-decoration-none text-muted">Features</a></li>
                            <li><a href="#" class="text-decoration-none text-muted">Pricing</a></li>
                            <li><a href="#" class="text-decoration-none text-muted">Security</a></li>
                        </ul>
                    </div>
                    <div class="col-md-2 mb-4 mb-md-0">
                        <h6 class="fw-bold mb-3">Support</h6>
                        <ul class="list-unstyled small">
                            <li><a href="/support" class="text-decoration-none text-muted">Help Center</a></li>
                            <li><a href="#" class="text-decoration-none text-muted">Contact Us</a></li>
                            <li><a href="#" class="text-decoration-none text-muted">Status</a></li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <h6 class="fw-bold mb-3">Security & Privacy</h6>
                        <p class="text-muted small mb-2">256-bit SSL encryption | FDIC insured | SOC 2 Type II certified</p>
                        <div class="badge bg-success">🔒 Bank-Grade Security</div>
                    </div>
                </div>
                <hr class="bg-secondary opacity-25">
                <div class="row small text-muted">
                    <div class="col-md-8">
                        <p>&copy; <?php echo date('Y'); ?> <?php echo APP_DISPLAY_NAME; ?>. All rights reserved. | 
                        <a href="#" class="text-decoration-none text-muted">Privacy Policy</a> | 
                        <a href="#" class="text-decoration-none text-muted">Terms of Service</a></p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <p>Version <?php echo APP_VERSION; ?> | Secure Banking Since 2024</p>
                    </div>
                </div>
            </div>
        </footer>
        
    </div>
    
    <!-- ================================================================
         SCRIPTS
         ================================================================ -->
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWbSxccPQtF3EpF3fnJHog6LaEVF4+tVQTuck+f5+e8mI0PY6r" crossorigin="anonymous" defer></script>
    
    <!-- Chart.js for financial analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
    
    <!-- Core Application JavaScript -->
    <script src="/assets/js/config.js" defer></script>
    <script src="/assets/js/utils.js" defer></script>
    <script src="/assets/js/api-client.js" defer></script>
    <script src="/assets/js/theme.js" defer></script>
    <script src="/assets/js/app.js" defer></script>
    
    <!-- Page-specific JavaScript -->
    <script src="/assets/js/pages/<?php echo $current_page; ?>.js" defer></script>
    
    <!-- ================================================================
         ANALYTICS & ERROR TRACKING (Optional)
         ================================================================ -->
    
    <script>
        // Global error handler for client-side debugging
        window.addEventListener('error', function(e) {
            console.error('Global Error:', e.error);
            // In production, send to error tracking service (e.g., Sentry)
        });
        
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Unhandled Promise Rejection:', e.reason);
        });
        
        // Page initialization marker
        console.log('<?php echo APP_DISPLAY_NAME; ?> v<?php echo APP_VERSION; ?> - Initializing...');
    </script>
    
</body>
</html>

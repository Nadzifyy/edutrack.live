<?php
require_once 'config/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth = new Auth();
$error = '';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    redirect('index.php');
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if ($auth->login($username, $password)) {
            redirect('index.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-layout">
            <div class="login-box">
                <div class="login-header">
                    <div class="logo-container">
                        <img src="LOGO.png" alt="<?php echo SCHOOL_NAME; ?> Logo" class="school-logo">
                    </div>
                    <h1 class="school-name"><?php echo SCHOOL_NAME; ?></h1>
                    <p class="system-subtitle">EduTrack : Learning Progress Tracker</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="margin-right: 8px; vertical-align: middle;">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="login-form">
                    <div class="form-group">
                        <label for="username">
                            <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" style="margin-right: 6px; vertical-align: middle;">
                                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zM10 11a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6z"/>
                            </svg>
                            Username or Email
                        </label>
                        <input type="text" id="username" name="username" placeholder="Enter your username or email" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">
                            <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" style="margin-right: 6px; vertical-align: middle;">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            Password
                        </label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block btn-login">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="margin-right: 8px; vertical-align: middle;">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 011 1v12a1 1 0 11-2 0V4a1 1 0 011-1zm7.707 3.293a1 1 0 010 1.414L9.414 9H17a1 1 0 110 2H9.414l1.293 1.293a1 1 0 01-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Sign In
                    </button>
                    
                    <div style="text-align: center; margin-top: 20px;">
                        <p style="color: #666;">Don't have an account? <a href="register.php" style="color: var(--primary-color); font-weight: bold;">Register as Parent</a></p>
                    </div>
                </form>
            </div>
            
            <div class="about-school">
                <div class="about-header">
                    <h2>
                        <svg width="24" height="24" viewBox="0 0 20 20" fill="currentColor" style="margin-right: 10px; vertical-align: middle;">
                            <path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.257l4-1.714a1 1 0 11.788 1.838L7.667 9.088l1.94.831a1 1 0 00.787 0l7-3a1 1 0 000-1.838l-7-3zM3.31 9.397L5 10.12v4.102a8.969 8.969 0 00-1.05-.174 1 1 0 01-.89-.89 11.115 11.115 0 01.25-3.762zM9.3 16.573A9.026 9.026 0 007 14.935v-3.957l1.818.78a3 3 0 002.364 0l5.508-2.361a11.026 11.026 0 01.25 3.762 1 1 0 01-.89.89 8.968 8.968 0 00-5.35 2.524 1 1 0 01-1.4 0zM6 18a1 1 0 001-1v-2.065a8.935 8.935 0 00-2-.712V17a1 1 0 001 1z"/>
                        </svg>
                        About Our School
                    </h2>
                </div>
                <div class="about-content">
                    <p>Cirilo Bueno Sr. Elementary School is a public elementary school located at Scott Road, San Raymundo Jolo (Capital) Sulu. Our school is committed to providing quality education to our students, empowering them to become successful and responsible members of society. With a focus on academic excellence, character development, and community involvement, we aim to nurture well-rounded individuals who are equipped with the skills and knowledge necessary to thrive in today's competitive world.</p>
                    
                    <p>At Cirilo Bueno Sr. Elementary School, we offer a comprehensive curriculum that includes core subjects such as Mathematics, Science, English, and Social Studies, as well as enrichment programs in Art, Music, Physical Education, and Technology. Our dedicated teachers and staff are passionate about helping each student reach their full potential and we strive to create a safe and inclusive learning environment where every child feels valued and supported. We are proud of our strong partnerships with parents, community members, and local organizations, which allows us to offer a variety of resources and opportunities to enhance the educational experience of our students. Together, we are shaping the future leaders of tomorrow at Cirilo Bueno Sr. Elementary School.</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>


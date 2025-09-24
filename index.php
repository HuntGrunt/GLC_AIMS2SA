<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golden Link College Foundation Inc. - Be The Best That You Can Be!</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="/GLC_AIMS/shared/GLC_LOGO.png" type="image/x-icon">
    <style>
        /* Offered Programs slider animation */
        .program-slide-right {
            animation: slideInRight 0.4s cubic-bezier(0.4,0,0.2,1);
        }
        .program-slide-left {
            animation: slideInLeft 0.4s cubic-bezier(0.4,0,0.2,1);
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(60px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-60px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .scroll-reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.7s cubic-bezier(0.4,0,0.2,1), transform 0.7s cubic-bezier(0.4,0,0.2,1);
        }
        .scroll-reveal.revealed {
            opacity: 1;
            transform: none;
        }
        :root {
            --primary-blue: #1e3a8a;
            --light-blue: #3b82f6;
            --accent-yellow: #fbbf24;
            --light-yellow: #fef3c7;
            --dark-blue: #1e40af;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --white: #ffffff;
            --light-gray: #f8fafc;
            --border-gray: #e5e7eb;
            --success: #10b981;
            --gradient-blue: linear-gradient(135deg, var(--primary-blue) 0%, var(--light-blue) 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            overflow-x: hidden;
        }

        /* Header */
        .header {
            background: var(--gradient-blue);
            color: var(--white);
            padding: 1rem 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(30, 58, 138, 0.3);
            backdrop-filter: blur(10px);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.3rem;
            font-weight: 700;
        }

        .logo img {
            width: 50px;
            height: 50px;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(251, 191, 36, 0.3);
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-link {
            color: var(--white);
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .nav-link:hover {
            background: rgba(251, 191, 36, 0.2);
            border-color: var(--accent-yellow);
        }

        .login-btn {
            background: var(--accent-yellow);
            color: var(--primary-blue);
            font-weight: 600;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(251, 191, 36, 0.4);
        }

        /* Hero Section */
        .hero {
            background: var(--gradient-blue);
            color: var(--white);
            padding: 120px 0 80px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            line-height: 1.1;
            animation: fadeInUp 1s ease-out;
        }

        .hero-logo {
            width: 150px; /* Adjust size as needed */
            height: auto;
            margin-bottom: 20px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .hero .motto {
            font-size: 1.5rem;
            font-style: italic;
            color: var(--light-yellow);
            margin-bottom: 2rem;
            animation: fadeInUp 1s ease-out 0.2s both;
        }

        .hero .subtitle {
            font-size: 1.2rem;
            margin-bottom: 3rem;
            opacity: 0.9;
            animation: fadeInUp 1s ease-out 0.4s both;
        }

        .cta-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease-out 0.6s both;
        }

        .cta-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            padding: 1rem 2rem;
            border: none;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .cta-btn.primary {
            background: var(--accent-yellow);
            color: var(--primary-blue);
        }

        .cta-btn.secondary {
            background: transparent;
            color: var(--white);
            border: 2px solid var(--white);
        }

        .cta-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.3);
        }

        .cta-btn.primary:hover {
            background: #f59e0b;
        }

        .cta-btn.secondary:hover {
            background: var(--white);
            color: var(--primary-blue);
        }

        /* Mission Vision Section */
        .mission-vision {
            background: var(--white);
            padding: 80px 0;
            position: relative;
        }

        .mission-vision::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-blue);
        }

        .section-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 3rem;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--accent-yellow);
            border-radius: 2px;
        }

        .mv-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 3rem;
            margin-top: 4rem;
        }

        .mv-card {
            background: var(--light-gray);
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid var(--border-gray);
            position: relative;
            overflow: hidden;
        }

        .mv-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: var(--gradient-blue);
        }

        .mv-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(30, 58, 138, 0.15);
        }

        .mv-icon {
            background: var(--gradient-blue);
            color: var(--white);
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 25px rgba(30, 58, 138, 0.3);
        }

        .mv-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 1rem;
        }

        .mv-card p {
            font-size: 1rem;
            color: var(--text-light);
            line-height: 1.8;
        }

        /* Features Section */
        .features {
            background: var(--light-gray);
            padding: 80px 0;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .feature-item {
            background: var(--white);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 4px solid var(--accent-yellow);
        }

        .feature-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
        }

        .feature-icon {
            background: linear-gradient(135deg, var(--accent-yellow), #f59e0b);
            color: var(--primary-blue);
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1.5rem;
            box-shadow: 0 6px 20px rgba(251, 191, 36, 0.3);
        }

        .feature-item h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-blue);
            margin-bottom: 0.8rem;
        }

        .feature-item p {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        /* CTA Section */
        .cta-section {
            background: var(--gradient-blue);
            color: var(--white);
            padding: 80px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="2" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
        }

        .cta-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 2rem;
            position: relative;
            z-index: 1;
        }

        .cta-section h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .cta-section p {
            font-size: 1.2rem;
            margin-bottom: 2.5rem;
            opacity: 0.9;
        }

        /* Footer */
        .footer {
            background: var(--text-dark);
            color: var(--white);
            padding: 25px 0 10px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        .footer-section h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--accent-yellow);
        }

        .footer-section p, .footer-section li {
            margin-bottom: 0.5rem;
            opacity: 0.8;
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section a {
            color: var(--white);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-section a:hover {
            color: var(--accent-yellow);
        }

        .footer-bottom {
            margin-top: 1.2rem;
            padding-top: 1rem;
            border-top: 1px solid #374151;
            text-align: center;
            opacity: 0.7;
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

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .floating {
            animation: float 3s ease-in-out infinite;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .hero {
                padding: 100px 0 60px;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero .motto {
                font-size: 1.2rem;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }

            .cta-btn {
                width: 100%;
                max-width: 300px;
            }

            .mv-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .section-title {
                font-size: 2rem;
            }
        }

        /* Scroll animations */
        .scroll-reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease-out;
        }

        .scroll-reveal.revealed {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
        <body>
        <script>
        // Scroll reveal animation for all .scroll-reveal elements
        document.addEventListener('DOMContentLoaded', function() {
            var revealEls = document.querySelectorAll('.scroll-reveal');
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('revealed');
                    } else {
                        entry.target.classList.remove('revealed');
                    }
                });
            }, { threshold: 0.15 });
            revealEls.forEach(function(el) { observer.observe(el); });
        });
        </script>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="logo">
                <img src="shared/GLC_LOGO.png" alt="GLC Logo">
                <span>Golden Link College Foundation Inc.</span>
            </div>
            <nav class="nav-links">
                <a href="#home" class="nav-link">Home</a>
                <a href="#about" class="nav-link">About</a>
                <a href="#offered-program" class="nav-link">Programs</a>
                <a href="#contact" class="nav-link">Contact</a>
                <a href="login.php" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    Login
                </a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-content">
            <img src="shared/GLC_LOGO.png" alt="Golden Link College Foundation Inc. Logo" class="hero-logo">
            <h1>Golden Link College Foundation Inc.</h1>
            <p class="motto">"Be The Best That You Can Be!"</p>
            <p class="subtitle">
                Empowering minds, shaping futures, and building tomorrow's leaders through quality education and comprehensive development programs.
            </p>
            <div class="cta-buttons">
                 <a href="enrollment.php" class="cta-btn primary">
                    <i class="fas fa-graduation-cap"></i>
                    Enroll Now
                </a>
                <a href="#about" class="cta-btn secondary">
                    <i class="fas fa-info-circle"></i>
                    Learn More
                </a>
            </div>
        </div>
    </section>

    <!-- Mission & Vision Section -->
    <section id="about" class="mission-vision">
        <div class="section-content">
            <h2 class="section-title scroll-reveal">Our Foundation</h2>
            <div class="mv-grid">
                <div class="mv-card scroll-reveal floating">
                    <div class="mv-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3>Our Mission and Vision</h3>
                    <p>
                        To educate and bring up children and young adults to become competent, well-balanced, emotionally mature, socially responsible, morally upright and spiritually sensitive individuals <br> <br>

                        To develop and promote an enlightened educational system that teaches the art and science of wise living in addition to achieving academic excellence - a system that can be shared with other institutions for the benefit of individuals and society <br> <br>

                        To be a center of peace and harmony where people of different cultures will live and learn together to help promote world peace and brotherhood without distinction of religion, race, sex or nationality, in the light of the ageless wisdom of life <br> <br>

                        To help bring high quality and right education to the less privileged
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Promotional Video Section -->
    <section id="promo-video" class="promo-video-section">
        <div class="section-content">
            <h2 class="section-title scroll-reveal">Watch Our Campus Video</h2>
            <div class="promo-video-wrapper scroll-reveal" style="display: flex; justify-content: center; align-items: center; margin-top: 2rem;">
                <div class="promo-video-embed" style="max-width: 800px; width: 100%; box-shadow: 0 8px 32px rgba(30,58,138,0.15); border-radius: 20px; overflow: hidden; animation: fadeInUp 1.2s cubic-bezier(.4,2,.6,1) 0.2s both;">
                    <iframe width="100%" height="450" src="https://www.youtube.com/embed/8f9SnBI2-Kk?autoplay=1&mute=1" title="GLC Promotional Video" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="border-radius: 20px;"></iframe>
                </div>
            </div>
        </div>
    </section>

        <!-- Facebook Page Section -->

    <section id="facebook-page" class="facebook-page-section" style="background: var(--light-gray); padding: 40px 0;">
    <div class="section-content" style="margin-bottom: 40px;">
            <h2 class="section-title scroll-reveal">Connect with Us on Facebook</h2>
            <div style="display: flex; flex-direction: row; align-items: stretch; background: var(--white); border-radius: 24px; box-shadow: 0 8px 32px rgba(30,58,138,0.13); padding: 2rem 2.5rem; border-top: 6px solid var(--accent-yellow); gap: 2rem;">
                <div style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                    <div style="font-size: 1.25rem; font-weight: 700; color: var(--primary-blue); margin-bottom: 0.3rem;">Golden Link College Official Facebook Page</div>
                    <div style="color: var(--text-light); font-size: 1.05rem; margin-bottom: 1.1rem; max-width: 350px;">Golden Link College (GLC) is a community where excellence meets character, shaping not only bright minds but also compassionate hearts. We believe education goes beyond academics by nurturing knowledge, values, and purpose, empowering students to become lifelong learners and future leaders. At GLC, we are committed to providing quality education, holistic student development, and meaningful opportunities that inspire dreams and make a difference.</div>
                    <a href="https://www.facebook.com/goldenlinkcollege" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 0.5rem; background: linear-gradient(90deg, #1877f3 60%); color: #fff; font-weight: 600; border-radius: 8px; padding: 0.5rem 0.9rem; font-size: 0.98rem; text-decoration: none; box-shadow: 0 2px 8px rgba(30,58,138,0.08); transition: background 0.2s, box-shadow 0.2s; border: none; outline: none; min-width: 60px; max-width: 100px;">
                        <i class="fab fa-facebook-f" style="font-size: 1rem;"></i>Follow
                    </a>
                </div>
                <div style="flex: 1; display: flex; align-items: center; justify-content: flex-end; min-width: 260px;">
                    <img src="shared\GLCcover1.jpg" alt="GLC Facebook Cover Photo" style="width: 650px; height: 240px; border-radius: 18px; box-shadow: 0 2px 16px rgba(30,58,138,0.13); background: #fff; border: 4px solid var(--light-gray); object-fit: cover; display: block; margin: 0 auto;">
                </div>
            </div>
            <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v19.0" nonce="glcpage"></script>
        </div>

        <div class="section-content">
            <div style="display: flex; flex-direction: row; align-items: stretch; background: var(--white); border-radius: 24px; box-shadow: 0 8px 32px rgba(30,58,138,0.13); padding: 2rem 2.5rem; border-top: 6px solid var(--accent-yellow); gap: 2rem;">
                <div style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                    <div style="font-size: 1.25rem; font-weight: 700; color: var(--primary-blue); margin-bottom: 0.3rem;">Golden Link College New Gen Transformation Facebook Page</div>
                    <div style="color: var(--text-light); font-size: 1.05rem; margin-bottom: 1.1rem; max-width: 350px;">Golden Link College embraces the New Gen Transformation, through innovative learning, holistic growth, and leadership opportunities, we equip the new generation with the knowledge, creativity, and compassion to shape a brighter future. At GLC, transformation is not just about education—it’s about empowering every student to become a catalyst for positive change in society. This page also serves as your hub for the latest events, school activities, and updates—celebrating achievements, promoting programs, and keeping our community connected and inspired.</div>
                    <a href="https://www.facebook.com/GLCNewGenTransformation" target="_blank" rel="noopener" style="display: inline-flex; align-items: center; gap: 0.5rem; background: linear-gradient(90deg, #1877f3 60%); color: #fff; font-weight: 600; border-radius: 8px; padding: 0.5rem 0.9rem; font-size: 0.98rem; text-decoration: none; box-shadow: 0 2px 8px rgba(30,58,138,0.08); transition: background 0.2s, box-shadow 0.2s; border: none; outline: none; min-width: 60px; max-width: 100px;">
                        <i class="fab fa-facebook-f" style="font-size: 1rem;"></i>Follow
                    </a>
                </div>
                <div style="flex: 1; display: flex; align-items: center; justify-content: flex-end; min-width: 260px;">
                    <img src="shared\GLCcover2.jpg" alt="GLC Facebook Cover Photo" style="width: 650px; height: 240px; border-radius: 18px; box-shadow: 0 2px 16px rgba(30,58,138,0.13); background: #fff; border: 4px solid var(--light-gray); object-fit: cover; display: block; margin: 0 auto;">
                </div>
            </div>
            <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v19.0" nonce="glcpage"></script>
        </div>
    </section>

    <!-- Campus Gallery Section -->
    <section id="gallery" class="gallery-section" style="background: var(--white); padding: 80px 0;">
        <div class="section-content">
            <h2 class="section-title scroll-reveal">Campus Gallery</h2>
            <div class="gallery-2x2-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2.5rem; margin-top: 3.5rem;">
                <!-- Upper row -->
                <div class="gallery-item scroll-reveal" style="background: var(--light-gray); border-radius: 24px; overflow: hidden; box-shadow: 0 6px 24px rgba(30,58,138,0.10);">
                    <img src="shared/GLC_BG.jpg" alt="Campus Entrance" style="width: 100%; height: 320px; object-fit: cover;">
                    <div style="padding: 1.5rem;">
                        <h4 style="margin: 0 0 0.7rem; color: var(--primary-blue); font-size: 1.3rem;">Main Campus Caloocan CIty</h4>
                        <p style="color: var(--text-light); font-size: 1.05rem;">Welcome to Golden Link College's main entrance, where every journey begins.</p>
                    </div>
                </div>
                <div class="gallery-item scroll-reveal" style="background: var(--light-gray); border-radius: 24px; overflow: hidden; box-shadow: 0 6px 24px rgba(30,58,138,0.10);">
                    <img src="shared/GLC_LOGO.png" alt="School Logo" style="width: 100%; height: 320px; object-fit: contain; background: #fff;">
                    <div style="padding: 1.5rem;">
                        <h4 style="margin: 0 0 0.7rem; color: var(--primary-blue); font-size: 1.3rem;">School Logo</h4>
                        <p style="color: var(--text-light); font-size: 1.05rem;">The official emblem of Golden Link College Foundation Inc.</p>
                    </div>
                </div>
                <!-- Lower row -->
                <div class="gallery-item scroll-reveal" style="background: var(--light-gray); border-radius: 24px; overflow: hidden; box-shadow: 0 6px 24px rgba(30,58,138,0.10);">
                    <img src="shared\Library.jpg" alt="Library" style="width: 100%; height: 320px; object-fit: cover;">
                    <div style="padding: 1.5rem;">
                        <h4 style="margin: 0 0 0.7rem; color: var(--primary-blue); font-size: 1.3rem;">Golden Link Library</h4>
                        <p style="color: var(--text-light); font-size: 1.05rem;">A place for students to study, research, and collaborate.</p>
                    </div>
                </div>
                <div class="gallery-item scroll-reveal" style="background: var(--light-gray); border-radius: 24px; overflow: hidden; box-shadow: 0 6px 24px rgba(30,58,138,0.10);">
                    <img src="shared\ComLab2.jpg" alt="Computer Lab" style="width: 100%; height: 320px; object-fit: cover; background: #000;">
                    <div style="padding: 1.5rem;">
                        <h4 style="margin: 0 0 0.7rem; color: var(--primary-blue); font-size: 1.3rem;">Golden Link Computer Lab</h4>
                        <p style="color: var(--text-light); font-size: 1.05rem;">Equipped with modern computers for IT and research activities.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- School Staff Section -->
    <section id="gallery" class="gallery-section" style="background: var(--white); padding: 80px 0;">
        <div class="section-content" style="text-align: center;">
            <h2 class="section-title scroll-reveal" style="text-align: center;">School Staffs</h2>
            <div class="gallery-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 2rem; margin-top: 3rem;">
                <!-- Example gallery item, replace src and text as needed -->
                <div class="gallery-item scroll-reveal" style="background: var(--light-gray); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(30,58,138,0.08); text-align: center;">
                     <img src="shared/Vicente.jpg" alt="School Logo" style="width: 100%; height: 220px; object-fit: cover; display: block;">
                    <div style="padding: 1rem;">
                        <h4 style="margin: 0 0 0.5rem; color: var(--primary-blue); font-size: 1.1rem; text-align: center;">Mr. Vicente Hao Chin Jr.</h4>
                        <p style="color: var(--text-light); font-size: 0.95rem; text-align: center;">President of Golden Link Collge Foundation Inc.</p>
                    </div>
                </div>
                <div class="gallery-item scroll-reveal" style="background: var(--light-gray); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(30,58,138,0.08); text-align: center;">
                    <img src="shared/Eirin.jpg" alt="School Logo" style="width: 100%; height: 220px; object-fit: cover; display: block;">
                    <div style="padding: 1rem;">
                        <h4 style="margin: 0 0 0.5rem; color: var(--primary-blue); font-size: 1.1rem; text-align: center;">Mrs. Eiren Galang</h4>
                        <p style="color: var(--text-light); font-size: 0.95rem; text-align: center;">School Principal</p>
                    </div>
                </div>
               
                <div class="gallery-item scroll-reveal" style="background: var(--light-gray); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(30,58,138,0.08); text-align: center;">
                    <img src="shared/DocRomeo.jpg" alt="School Logo" style="width: 100%; height: 220px; object-fit: cover; display: block;">
                    <div style="padding: 1rem;">
                        <h4 style="margin: 0 0 0.5rem; color: var(--primary-blue); font-size: 1.1rem; text-align: center;">Dr. Romeo Torres</h4>
                        <p style="color: var(--text-light); font-size: 0.95rem; text-align: center;">School Dean</p>
                    </div>
                </div>

                <div class="gallery-item scroll-reveal" style="background: var(--light-gray); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(30,58,138,0.08); text-align: center;">
                    <img src="shared/JD.jpg" alt="School Logo" style="width: 100%; height: 220px; object-fit: cover; display: block;">
                    <div style="padding: 1rem;">
                        <h4 style="margin: 0 0 0.5rem; color: var(--primary-blue); font-size: 1.1rem; text-align: center;">Mr. John Derson Herbolario</h4>
                        <p style="color: var(--text-light); font-size: 0.95rem; text-align: center;">Assistant of the Dean</p>
                    </div>
                </div>

                <div class="gallery-item scroll-reveal" style="background: var(--light-gray); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(30,58,138,0.08); text-align: center;">
                     <img src="shared/Rekha.jpg" alt="School Logo" style="width: 100%; height: 220px; object-fit: cover; display: block;">
                    <div style="padding: 1rem;">
                        <h4 style="margin: 0 0 0.5rem; color: var(--primary-blue); font-size: 1.1rem; text-align: center;">Ms. Rekha Nahar</h4>
                        <p style="color: var(--text-light); font-size: 0.95rem; text-align: center;">Administration Head</p>
                    </div>
                </div>
                <div class="gallery-item scroll-reveal" style="background: var(--light-gray); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(30,58,138,0.08); text-align: center;">
                    <img src="shared/AnaRea.jpg" alt="School Logo" style="width: 100%; height: 220px; object-fit: cover; display: block;">
                    <div style="padding: 1rem;">
                        <h4 style="margin: 0 0 0.5rem; color: var(--primary-blue); font-size: 1.1rem; text-align: center;">Mrs. Ana Rea Toledo</h4>
                        <p style="color: var(--text-light); font-size: 0.95rem; text-align: center;">Registrar</p>
                    </div>
                </div>
               
                <div class="gallery-item scroll-reveal" style="background: var(--light-gray); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(30,58,138,0.08); text-align: center;">
                    <img src="shared/Eunice.jpg" alt="School Logo" style="width: 100%; height: 220px; object-fit: cover; display: block;">
                    <div style="padding: 1rem;">
                        <h4 style="margin: 0 0 0.5rem; color: var(--primary-blue); font-size: 1.1rem; text-align: center;">Ms. Eunice Pimentel</h4>
                        <p style="color: var(--text-light); font-size: 0.95rem; text-align: center;">IT Professional</p>
                    </div>
                </div>

                <div class="gallery-item scroll-reveal" style="background: var(--light-gray); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(30,58,138,0.08); text-align: center;">
                    <img src="shared/Abelle.jpg" alt="School Logo" style="width: 100%; height: 220px; object-fit: cover; display: block;">
                    <div style="padding: 1rem;">
                        <h4 style="margin: 0 0 0.5rem; color: var(--primary-blue); font-size: 1.1rem; text-align: center;">Ms. Abelle Manglicmot</h4>
                        <p style="color: var(--text-light); font-size: 0.95rem; text-align: center;">Accountant</p>
                    </div>
                </div>
            </div>
            <!-- Centered last two staff cards -->
            <div style="display: flex; justify-content: center; gap: 2rem; margin-top: 2rem; flex-wrap: wrap;">
                <div class="gallery-item scroll-reveal" style="background: var(--light-gray); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(30,58,138,0.08); text-align: center; width: 260px;">
                    <img src="shared/Luwalhati.jpg" alt="School Logo" style="width: 100%; height: 220px; object-fit: cover; display: block;">
                    <div style="padding: 1rem;">
                        <h4 style="margin: 0 0 0.5rem; color: var(--primary-blue); font-size: 1.1rem; text-align: center;">Ms. Luwalhati Briones</h4>
                        <p style="color: var(--text-light); font-size: 0.95rem; text-align: center;">Academic Service</p>
                    </div>
                </div>
                <div class="gallery-item scroll-reveal" style="background: var(--light-gray); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 16px rgba(30,58,138,0.08); text-align: center; width: 260px;">
                    <img src="shared/Nikko.jpg" alt="School Logo" style="width: 100%; height: 220px; object-fit: cover; display: block;">
                    <div style="padding: 1rem;">
                        <h4 style="margin: 0 0 0.5rem; color: var(--primary-blue); font-size: 1.1rem; text-align: center;">Mr. Nikko F. Iligan</h4>
                        <p style="color: var(--text-light); font-size: 0.95rem; text-align: center;">School Teacher</p>
                    </div>
                </div>
            </div>
                
            </div>
        </div>
    </section>


     <!-- Offered Programs  Section -->
    <section id="offered-program" class="gallery-section" style="background: var(--light-gray); padding: 80px 0;">
        <div class="section-content" style="text-align: center; position: relative;">
            <h2 class="section-title scroll-reveal">Offered Programs</h2>
            <div style="display: flex; align-items: center; justify-content: center; margin-top: 3.5rem; gap: 1.5rem;">
                <!-- Left Arrow Button -->
                <button id="program-arrow-left" aria-label="Previous Program" style="background: var(--primary-blue); color: #fff; border: none; border-radius: 50%; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; font-size: 2rem; box-shadow: 0 2px 8px rgba(30,58,138,0.10); cursor: pointer; transition: background 0.2s;">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div id="offered-programs-slider" style="width: 600px; max-width: 90vw;">
                    <!-- Program cards will be injected here by JS -->
                    <div id="program-card-container"></div>
                </div>
                <!-- Right Arrow Button -->
                <button id="program-arrow-right" aria-label="Next Program" style="background: var(--primary-blue); color: #fff; border: none; border-radius: 50%; width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; font-size: 2rem; box-shadow: 0 2px 8px rgba(30,58,138,0.10); cursor: pointer; transition: background 0.2s;">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </section>

    <!-- Features Section -->

    <section id="programs" class="features">
        <div class="section-content">
            <h2 class="section-title scroll-reveal">Why Choose GLC?</h2>
            <div class="features-grid">
                <div class="feature-item scroll-reveal">
                    <div class="feature-icon">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3>Expert Faculty</h3>
                    <p>Learn from experienced educators and industry professionals committed to your success.</p>
                </div>
                <div class="feature-item scroll-reveal">
                    <div class="feature-icon">
                        <i class="fas fa-laptop-code"></i>
                    </div>
                    <h3>Modern Facilities</h3>
                    <p>State-of-the-art laboratories, libraries, and technology-enhanced learning environments.</p>
                </div>
                <div class="feature-item scroll-reveal">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Small Class Sizes</h3>
                    <p>Personalized attention and mentorship in intimate classroom settings.</p>
                </div>
                <div class="feature-item scroll-reveal">
                    <div class="feature-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h3>Accredited Programs</h3>
                    <p>Recognized and accredited academic programs that meet industry standards.</p>
                </div>
                <div class="feature-item scroll-reveal">
                    <div class="feature-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <h3>Career Support</h3>
                    <p>Comprehensive career guidance, internship opportunities, and job placement assistance.</p>
                </div>
                <div class="feature-item scroll-reveal">
                    <div class="feature-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>Personal Transformation</h3>
                    <p>Holistic support for self-growth, confidence building, leadership development, and lifelong learning.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="cta-content">
            <h2 class="scroll-reveal">Ready to Start Your Journey?</h2>
            <p class="scroll-reveal">
                Join thousands of successful graduates who have transformed their lives through quality education at Golden Link College.
            </p>
            <div class="cta-buttons scroll-reveal">
                 <a href="enrollment.php" class="cta-btn primary">
                    <i class="fas fa-rocket"></i>
                    Begin Your Future Today
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact" class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3>Golden Link College</h3>
                <p>Empowering minds and shaping futures since 2002.</p>
                <p><i class="fas fa-map-marker-alt"></i> Caloocan City, NCR., Philippines</p>
                <p><i class="fas fa-phone"></i> Contact: 289615836</p>
                <p><i class="fas fa-envelope"></i> office@goldenlink.ph</p>
            </div>
            <div class="footer-section">
                <h3>Student Services</h3>
                <ul>
                    <li><a href="login.php">Academic Information System</a></li>
                    <li><a href="#">Library Services</a></li>
                    <li><a href="#">Student Affairs</a></li>
                    <li><a href="#">Career & Academic Center</a></li>
                    <li><a href="#">Mindfulness Meditation Center</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Office Hours</h3>
                <p>Monday - Friday: 8:00 AM - 5:00 PM</p>
                <p>Saturday: 8:00 AM - 3:00 PM</p>
                <p>Sunday & Holidays: Closed</p>
                <p>Lunch Break: 11:30 AM - 1:30 PM</p>
            </div>
            <div class="footer-section">
                <h3>School Location</h3>
                <div style="width:120%;height:130px;">
                    <iframe src="https://www.google.com/maps?q=Golden+Link+College+Caloocan&output=embed" width="100%" height="120" style="border:0; border-radius:8px;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="GLC Map"></iframe>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> GLC Academic Information Management System</p>
            <p style="opacity: 0.8; font-size: 0.9rem;">Developed by Group 1: Team Atlas <i style="font-style: normal; font-size: 1em; line-height: 1; color: var(--accent-gray);">🌏</i> for the Capstone Project II</p>
        </div>
    </footer>

    <!-- Enrollment Modal -->
    <div id="enrollmentModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 2000; overflow-y: auto;">
        <div style="background: white; max-width: 800px; margin: 2rem auto; border-radius: 20px; padding: 3rem; position: relative;">
            <button onclick="closeEnrollmentModal()" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">&times;</button>
            
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="background: var(--gradient-blue); color: white; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1rem;">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h2 style="color: var(--primary-blue); margin-bottom: 0.5rem;">Enrollment Application</h2>
                <p style="color: var(--text-light);">Begin your journey at Golden Link College Foundation Inc.</p>
            </div>

            <form style="display: grid; gap: 1.5rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">First Name</label>
                        <input type="text" required style="width: 100%; padding: 0.8rem; border: 2px solid var(--border-gray); border-radius: 10px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Last Name</label>
                        <input type="text" required style="width: 100%; padding: 0.8rem; border: 2px solid var(--border-gray); border-radius: 10px;">
                    </div>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Email Address</label>
                    <input type="email" required style="width: 100%; padding: 0.8rem; border: 2px solid var(--border-gray); border-radius: 10px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Phone Number</label>
                    <input type="tel" required style="width: 100%; padding: 0.8rem; border: 2px solid var(--border-gray); border-radius: 10px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Preferred Program</label>
                    <select required style="width: 100%; padding: 0.8rem; border: 2px solid var(--border-gray); border-radius: 10px;">
                        <option value="">Select a program</option>
                        <option value="business">Business Administration</option>
                        <option value="it">Information Technology</option>
                        <option value="education">Education</option>
                        <option value="engineering">Engineering</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-dark);">Address</label>
                    <textarea required rows="3" style="width: 100%; padding: 0.8rem; border: 2px solid var(--border-gray); border-radius: 10px; resize: vertical;"></textarea>
                </div>
                
                <button type="submit" style="background: var(--gradient-blue); color: white; border: none; padding: 1rem 2rem; border-radius: 12px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                    <i class="fas fa-paper-plane"></i>
                    Submit Application
                </button>
            </form>
            
            <div style="margin-top: 2rem; padding: 1rem; background: var(--light-yellow); border-radius: 10px; border-left: 4px solid var(--accent-yellow);">
                <p style="color: var(--text-dark); font-size: 0.9rem;">
                    <i class="fas fa-info-circle" style="margin-right: 0.5rem;"></i>
                    Your application will be reviewed within 2-3 business days. You will receive a confirmation email with next steps.
                </p>
            </div>
        </div>
    </div>

    <script>
    // Offered Programs slider logic
    const offeredPrograms = [
        {
            img: 'shared/ENGLISH.jpg',
            title: 'Bachelor in Secondary Education Major in English',
            desc: ' Develop future educators with strong communication skills, literary appreciation, and innovative teaching strategies to inspire students in the field of English language and literature.'
        },
        {
            img: 'shared/Math.jpg',
            title: 'Bachelor in Secondary Education Major in Mathematics',
            desc: 'Train aspiring teachers to master mathematical concepts and modern pedagogies, equipping them to make math engaging, practical, and meaningful for learners.'
        },
        {
            img: 'shared/SCIENCE.jpg',
            title: 'Bachelor in Secondary Education Major in Science',
            desc: 'Prepare students to become dynamic science educators with a solid foundation in biology, chemistry, physics, and environmental studies, fostering curiosity and scientific thinking.'
        },
        {
            img: 'shared/BEED.jpg',
            title: 'Bachelor of Elementary Education ',
            desc: 'Provide future elementary teachers with broad knowledge and versatile teaching skills to nurture young learners and lay strong foundations in their early education years'
        },
        {
            img: 'shared/IT.jpg',
            title: 'Bachelor of Science in Information Technology',
            desc: 'Build future IT professionals skilled in programming, networking, database management, and emerging technologies, ready to excel in the digital and tech-driven world.'
        },
        {
            img: 'shared/BA.jpg',
            title: 'Bachelor of Science in Business Administration Major in Marketing Management',
            desc: 'Equip students with leadership, management, and entrepreneurial skills to thrive in diverse business environments and create opportunities for growth and innovation.'
        },
        {
            img: 'shared/AIS.jpg',
            title: 'Bachelor of Science in Accounting Information System',
            desc: 'Combine accounting expertise with information systems knowledge to train professionals who can manage financial data, support decision-making, and ensure business efficiency.'
        },
        {
            img: 'shared/Psych.jpg',
            title: 'Bachelor of Science in Psychology',
            desc: 'Explore human behavior, mental processes, and social dynamics while preparing students for careers in counseling, research, human resources, and community development.'
        }
    ];

    let currentProgram = 0;
    const programCardContainer = document.getElementById('program-card-container');
    function renderProgram(idx) {
        const prog = offeredPrograms[idx];
        // direction: 'right' or 'left' (default right)
        const direction = renderProgram.direction || 'right';
        const animClass = direction === 'left' ? 'program-slide-left' : 'program-slide-right';
        programCardContainer.innerHTML = `
            <div class="gallery-item ${animClass}" style="background: var(--white); border-radius: 24px; overflow: hidden; box-shadow: 0 6px 24px rgba(30,58,138,0.10); text-align: center;">
                <img src="${prog.img}" alt="${prog.title}" style="width: 100%; height: 320px; object-fit: cover; display: block; margin: 0 auto;">
                <div style="padding: 1.5rem;">
                    <h4 style="margin: 0 0 0.7rem; color: var(--primary-blue); font-size: 1.3rem; text-align: center;">${prog.title}</h4>
                    <p style="color: var(--text-light); font-size: 1.05rem; text-align: center;">${prog.desc}</p>
                </div>
            </div>
        `;
    }
    // Initial render defaults to right
    renderProgram.direction = 'right';
    renderProgram(currentProgram);

    document.getElementById('program-arrow-left').onclick = function() {
        currentProgram = (currentProgram - 1 + offeredPrograms.length) % offeredPrograms.length;
        renderProgram.direction = 'left';
        renderProgram(currentProgram);
    };
    document.getElementById('program-arrow-right').onclick = function() {
        currentProgram = (currentProgram + 1) % offeredPrograms.length;
        renderProgram.direction = 'right';
        renderProgram(currentProgram);
    };
        // Smooth scrolling for navigation links
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

        // Scroll reveal animation
        function revealOnScroll() {
            const reveals = document.querySelectorAll('.scroll-reveal');
            
            reveals.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementVisible = 150;
                
                if (elementTop < window.innerHeight - elementVisible) {
                    element.classList.add('revealed');
                }
            });
        }

        // Enrollment modal functions
        function showEnrollmentPage() {
            document.getElementById('enrollmentModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeEnrollmentModal() {
            document.getElementById('enrollmentModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('enrollmentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEnrollmentModal();
            }
        });

        // Handle enrollment form submission
        document.querySelector('#enrollmentModal form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show success message
            this.innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <div style="background: var(--success); color: white; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; margin: 0 auto 1.5rem;">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 style="color: var(--success); margin-bottom: 1rem;">Application Submitted Successfully!</h3>
                    <p style="color: var(--text-light); margin-bottom: 2rem;">Thank you for your interest in Golden Link College. We will review your application and contact you within 2-3 business days.</p>
                    <button type="button" onclick="closeEnrollmentModal()" style="background: var(--success); color: white; border: none; padding: 0.8rem 2rem; border-radius: 10px; cursor: pointer;">
                        Close
                    </button>
                </div>
            `;
        });

        // Initialize scroll reveal
        window.addEventListener('scroll', revealOnScroll);
        window.addEventListener('load', revealOnScroll);

        // Header scroll effect
        let lastScroll = 0;
        window.addEventListener('scroll', function() {
            const header = document.querySelector('.header');
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 100) {
                header.style.background = 'rgba(30, 58, 138, 0.95)';
                header.style.backdropFilter = 'blur(15px)';
            } else {
                header.style.background = 'var(--gradient-blue)';
                header.style.backdropFilter = 'blur(10px)';
            }
            
            lastScroll = currentScroll;
        });

        // Add floating animation delays for mission/vision cards
        document.addEventListener('DOMContentLoaded', function() {
            const floatingElements = document.querySelectorAll('.floating');
            floatingElements.forEach((element, index) => {
                element.style.animationDelay = (index * 0.5) + 's';
            });
        });

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            // Escape key to close modal
            if (e.key === 'Escape' && document.getElementById('enrollmentModal').style.display === 'block') {
                closeEnrollmentModal();
            }
        });

        // Form input focus effects
        document.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('focus', function() {
                this.style.borderColor = 'var(--accent-yellow)';
                this.style.boxShadow = '0 0 0 3px rgba(251, 191, 36, 0.1)';
            });
            
            input.addEventListener('blur', function() {
                this.style.borderColor = 'var(--border-gray)';
                this.style.boxShadow = 'none';
            });
        });

        // Mobile menu toggle (for smaller screens)
        function toggleMobileMenu() {
            const navLinks = document.querySelector('.nav-links');
            navLinks.classList.toggle('mobile-active');
        }

        // Add mobile menu styles dynamically
        const mobileStyles = `
            @media (max-width: 768px) {
                .nav-links {
                    position: fixed;
                    top: 80px;
                    left: -100%;
                    width: 100%;
                    height: calc(100vh - 80px);
                    background: var(--gradient-blue);
                    flex-direction: column;
                    justify-content: flex-start;
                    padding: 2rem;
                    transition: left 0.3s ease;
                }
                
                .nav-links.mobile-active {
                    left: 0;
                }
                
                .mobile-menu-toggle {
                    display: block !important;
                    background: none;
                    border: none;
                    color: white;
                    font-size: 1.5rem;
                    cursor: pointer;
                }
            }
            
            .mobile-menu-toggle {
                display: none;
            }
        `;
        
        const styleSheet = document.createElement('style');
        styleSheet.textContent = mobileStyles;
        document.head.appendChild(styleSheet);

        // Add mobile menu button
        const mobileMenuBtn = document.createElement('button');
        mobileMenuBtn.className = 'mobile-menu-toggle';
        mobileMenuBtn.innerHTML = '<i class="fas fa-bars"></i>';
        mobileMenuBtn.onclick = toggleMobileMenu;
        document.querySelector('.header-content').appendChild(mobileMenuBtn);
    </script>
</body>
</html>
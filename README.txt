---Developed by Harold B. Brocoy---

GLC Academic Information Management System (AIMS)
System Overview
AIMS includes:

Role-based authentication with password hashing and session management
Inventory management system for school equipment and materials
Borrowing system with approval workflow
Enhanced security features including CSRF protection, input validation, and activity logging
Modern responsive design with blue/yellow color scheme
Activity logging and audit trails

1. Operating System

    Windows 11
        Chosen for development and deployment environment.
        Provides compatibility with PHP, XAMPP, and Composer.

2. Web Server Stack

    XAMPP (Apache, MySQL, PHP)
        Apache: Handles HTTP requests and serves PHP pages.
        MySQL (MariaDB): Stores user data (credentials, email, date of birth, OTP records).
        PHP: Server-side scripting language powering the authentication logic and OTP system.

3. Programming Language

    PHP (8.x)
        Core language for backend logic.
        Handles user authentication, OTP generation, email sending, and password reset workflows.
    HTML5
        Markup for all pages.

    CSS (including modern tools like Flexbox / Grid)
        Styling and responsive layout for all pages.

    JavaScript (plain JS / optional frameworks)
        Client-side validation, UI interactivity, and AJAX calls for asynchronous OTP verification and form submission.

4. Libraries and Dependencies

    PHPMailer (phpmailer/phpmailer via Composer)
        Sends automated emails containing OTP codes.
        Provides SMTP support with encryption (TLS/SSL).
        Ensures reliability compared to PHP’s default mail() function.

    Composer
        Dependency manager for PHP.
        Used to install and manage PHPMailer and other packages.

5. Email Service Integration

    Google Account (Gmail SMTP with App Password)
        Configured as the email sender for OTP codes and password reset links.
        App Passwords used instead of normal Gmail login credentials (for security).
        Provides secure, authenticated SMTP connection to Gmail servers.

    SMTP Configuration:
        Host: smtp.gmail.com
        Port: 587 (TLS) or 465 (SSL)
        Encryption: TLS/SSL
        Authentication: Gmail with App Password

6. Authentication Features Implemented

    One-Time Password (OTP) via Email
        OTP generated dynamically for login and forgot password process.
        Ensures multi-factor authentication (MFA) and adds an extra security layer.

    Forgot Password Workflow
        Users provide registered email and date of birth.
        System sends OTP via Gmail for verification before resetting the password.

    Session Management
        PHP sessions used to manage authenticated users.
        Tokens and expiry times secure sessions against hijacking.

    Account Lockout Security
        Failed login attempts are logged.
        Accounts temporarily locked to prevent brute-force attacks.

7. Security Features

    Password Hashing
        Uses password_hash() and password_verify() for secure storage.

    OTP Expiry
        OTP codes expire after a short period to prevent reuse.

    Secure Database Queries
        Uses prepared statements to prevent SQL injection.

    Gmail App Password
        Prevents direct use of your real Google password.
        Works even with 2-Step Verification enabled.

8. Development Tools

    Visual Studio Code
        Code editor for PHP, HTML, and configuration files.

    Git
        Version control system for managing updates to the code.

    GitHub
        Remote repository hosting, collaboration, issue tracking, and CI.    

    phpMyAdmin
        GUI tool for managing MySQL database inside XAMPP.


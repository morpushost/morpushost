<?php
session_start();

// Database connection (replace with your actual database credentials)
$host = 'your_database_host';
$db   = 'your_database_name';
$user = 'your_database_user';
$pass = 'your_database_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Include PHPMailer autoloader
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Server-side validation and sanitization
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

    // Additional validation if needed

    // Validate unique username and email
    if (isUsernameTaken($pdo, $username)) {
        $errors['username'] = 'Username is already taken';
    }

    if (isEmailTaken($pdo, $email)) {
        $errors['email'] = 'Email is already registered';
    }

    if (empty($errors)) {
        // Hash the password before storing it in the database
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Save user data to the database
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$username, $email, $hashedPassword]);

        // Generate and store OTP in the database
        $otp = generateOTP();
        storeOTP($pdo, $email, $otp);

        // Send verification email
        sendVerificationEmail($email, $otp);

        // Redirect to OTP verification page
        header('Location: verify_otp.php?email=' . urlencode($email));
        exit();
    }
}

function isUsernameTaken($pdo, $username)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetchColumn() > 0;
}

function isEmailTaken($pdo, $email)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetchColumn() > 0;
}

function generateOTP()
{
    // Implement your OTP generation logic (e.g., random number)
    return rand(100000, 999999);
}

function storeOTP($pdo, $email, $otp)
{
    // Implement the storage of OTP in the database
    // For simplicity, you can create an OTP table with columns (email, otp, timestamp)
    $stmt = $pdo->prepare("INSERT INTO otps (email, otp) VALUES (?, ?)");
    $stmt->execute([$email, $otp]);
}

function sendVerificationEmail($to, $otp)
{
    // Initialize PHPMailer
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com';  // SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your_smtp_username';  // SMTP username
        $mail->Password   = 'your_smtp_password';  // SMTP password
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('from@example.com', 'Your Website');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification';
        $mail->Body    = 'Your OTP for email verification is: ' . $otp;

        // Send email
        $mail->send();
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Your Website</title>
    <!-- Include necessary stylesheets and scripts -->
</head>
<body>
    <div class="container">
        <h2>Register</h2>
        <?php if (!empty($errors)) : ?>
            <ul class="errors">
                <?php foreach ($errors as $error) : ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Register</button>
        </form>
    </div>
</body>
</html>

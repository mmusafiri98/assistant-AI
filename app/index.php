<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

if (!isset($_SESSION['initiated'])) {
    session_regenerate_id();
    $_SESSION['initiated'] = true;
}

// --- Gestion du login ---
$error_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $servername = "localhost";
    $dbname = "veronica_ai_login";
    $username_db = "root";
    $password_db = "";

    $username = htmlspecialchars(trim($_POST['username']));
    $password = htmlspecialchars(trim($_POST['password']));

    try {
        $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username_db, $password_db);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $conn->prepare("SELECT password FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && password_verify($password, $result['password'])) {
            $_SESSION['username'] = $username;
            $_SESSION['is_logged_in'] = true;

            $redirect_url = "http://localhost/assistente%20virtuale%20AI/description.php";
            header("Location: " . filter_var($redirect_url, FILTER_SANITIZE_URL));
            exit();
        } else {
            $error_message = "❌ Login échoué. Vérifiez vos identifiants.";
        }
    } catch (PDOException $e) {
        $error_message = "⚠️ Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage());
    }

    $conn = null;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veronica AI – Connexion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: "Poppins", sans-serif;
            background: url("drapeau.jpg") no-repeat center center fixed;
            background-size: cover;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            justify-content: center;
            align-items: center;
            margin: 0;
            overflow-x: hidden;
            position: relative;
        }

        /* Superposition pour lisibilité */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(4px);
            z-index: 0;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            width: 100%;
            padding: 10px;
            position: fixed;
            top: 0;
            left: 0;
            display: flex;
            justify-content: center;
            z-index: 1000;
        }

        .navbar img {
            height: 70px;
        }

        .login-card {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            text-align: center;
            width: 360px;
            animation: fadeIn 0.8s ease-in-out;
        }

        .login-card h2 {
            font-weight: 600;
            color: #1e293b;
        }

        .login-card p {
            color: #475569;
            font-size: 0.95rem;
        }

        .form-control {
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px solid #dbeafe;
        }

        .btn-login {
            background-color: #4f46e5;
            border: none;
            color: white;
            width: 100%;
            border-radius: 10px;
            padding: 10px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            background-color: #4338ca;
            transform: translateY(-2px);
        }

        .error-message {
            color: #dc2626;
            font-weight: 600;
            margin-bottom: 10px;
        }

        footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(5px);
            text-align: center;
            padding: 10px;
            font-size: 0.9rem;
            color: #334155;
            z-index: 1;
        }

        .social-icons img {
            width: 35px;
            margin: 0 8px;
            transition: transform 0.3s ease;
        }

        .social-icons img:hover {
            transform: scale(1.2);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 500px) {
            .login-card {
                width: 90%;
                padding: 30px;
            }

            .navbar img {
                height: 60px;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar">
        <img src="create.png" alt="Logo Veronica AI">
    </nav>

    <div class="login-card mt-5">
        <h2>Bienvenue sur <span style="color:#4f46e5;">Veronica AI</span></h2>
        <p>Apprends les langues plus vite grâce à l'intelligence artificielle 🌍</p>

        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?= $error_message ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="text" name="username" class="form-control" placeholder="Nom d'utilisateur" required>
            <input type="password" name="password" class="form-control" placeholder="Mot de passe" required>
            <button type="submit" class="btn-login">Se connecter</button>
        </form>

        <p class="mt-3">Pas encore de compte ? 
            <a href="register.php" style="color:#4f46e5; font-weight:500;">Inscris-toi ici</a>.
        </p>
    </div>

    <footer>
        <p>Suivez <strong>Veronica AI</strong> sur les réseaux sociaux</p>
        <div class="social-icons">
            <img src="Logo Facebook.svg" alt="Facebook">
            <img src="Logo instagram.svg" alt="Instagram">
        </div>
    </footer>
</body>
</html>

<?php ob_end_flush(); ?>

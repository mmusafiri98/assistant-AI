<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Paramètres de connexion locale
    $servername = "localhost";
    $dbname = "veronica_ai_login";
    $username_db = "root";
    $password_db = "";

    $username = htmlspecialchars(trim($_POST['username']));
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if ($password !== $confirm_password) {
        $error_message = "❌ Les mots de passe ne correspondent pas.";
    } else {
        try {
            $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username_db, $password_db);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Vérifier si le nom d'utilisateur existe déjà
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $exists = $stmt->fetchColumn();

            if ($exists) {
                $error_message = "⚠️ Ce nom d'utilisateur existe déjà. Choisis-en un autre.";
            } else {
                // Hachage sécurisé du mot de passe
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // Insertion du nouvel utilisateur
                $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed_password);

                if ($stmt->execute()) {
                    $success_message = "✅ Inscription réussie ! Tu peux maintenant <a href='login.php' style='color:#4f46e5;'>te connecter</a>.";
                } else {
                    $error_message = "❌ Une erreur s'est produite lors de l'inscription.";
                }
            }
        } catch (PDOException $e) {
            $error_message = "⚠️ Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage());
        }

        $conn = null;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veronica AI Assistant pour apprende l anglais – Inscription</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: "Poppins", sans-serif;
            background: linear-gradient(135deg, #7dd3fc, #a78bfa);
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
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

        .register-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            text-align: center;
            width: 380px;
            animation: fadeIn 0.8s ease-in-out;
        }

        h2 {
            color: #1e293b;
            font-weight: 600;
        }

        p {
            color: #475569;
        }

        .form-control {
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px solid #dbeafe;
        }

        .btn-register {
            background-color: #4f46e5;
            border: none;
            color: white;
            width: 100%;
            border-radius: 10px;
            padding: 10px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .btn-register:hover {
            background-color: #4338ca;
            transform: translateY(-2px);
        }

        .error-message {
            color: #dc2626;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .success-message {
            color: #16a34a;
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
            .register-card {
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
    <!-- Barre de navigation -->
    <nav class="navbar">
        <img src="create.png" alt="Logo LinguaAI">
    </nav>

    <!-- Carte d'inscription -->
    <div class="register-card mt-5">
        <h2>Créer un compte</h2>
        <p>Rejoins <strong style="color:#4f46e5;">Veronica AI</strong> et commence ton voyage linguistique 🌍</p>

        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?= $error_message ?></p>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <p class="success-message"><?= $success_message ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="text" name="username" class="form-control" placeholder="Nom d'utilisateur" required>
            <input type="password" name="password" class="form-control" placeholder="Mot de passe" required>
            <input type="password" name="confirm_password" class="form-control" placeholder="Confirme le mot de passe" required>
            <button type="submit" class="btn-register">S'inscrire</button>
        </form>

        <p class="mt-3">Déjà un compte ? <a href="login.php" style="color:#4f46e5; font-weight:500;">Connecte-toi ici</a>.</p>
    </div>

    <!-- Pied de page -->
    <footer>
        <p>Suivez <strong>LinguaAI</strong> sur les réseaux sociaux</p>
        <div class="social-icons">
            <img src="Logo Facebook.svg" alt="Facebook">
            <img src="Logo instagram.svg" alt="Instagram">
        </div>
    </footer>
</body>
</html>

<?php ob_end_flush(); ?>

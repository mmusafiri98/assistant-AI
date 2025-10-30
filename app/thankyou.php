<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

// --- Vérification de la connexion utilisateur ---
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

// --- Configuration de la base Neon ---
$db_host = 'ep-autumn-salad-adwou7x2-pooler.c-2.us-east-1.aws.neon.tech';
$db_port = '5432';
$db_name = 'veronica_db_login';
$db_user = 'neondb_owner';
$db_pass = 'npg_QolPDv5L9gVj';

$username = $_SESSION['username'] ?? "Cher apprenant";

try {
    $conn = new PDO(
        "pgsql:host=$db_host;port=$db_port;dbname=$db_name;sslmode=require",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // --- Si on a un user_id, on récupère le nom depuis la base
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT username FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['username'])) {
            $username = $user['username'];
        }
    }

} catch (PDOException $e) {
    error_log("Erreur de connexion DB : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Merci – Veronica AI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            font-family: "Poppins", sans-serif;
            background: linear-gradient(135deg, #a78bfa, #7dd3fc);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #1e293b;
            overflow: hidden;
        }

        .thankyou-container {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            animation: fadeIn 1s ease-in-out;
        }

        h1 {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 10px;
        }

        h2 {
            font-size: 1.2rem;
            color: #334155;
            margin-bottom: 25px;
        }

        p {
            color: #475569;
            font-size: 1rem;
            margin-bottom: 30px;
        }

        .ai-loader {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 25px;
        }

        .dot {
            width: 14px;
            height: 14px;
            background-color: #4f46e5;
            border-radius: 50%;
            animation: pulse 1.4s infinite ease-in-out;
        }

        .dot:nth-child(2) { animation-delay: 0.2s; }
        .dot:nth-child(3) { animation-delay: 0.4s; }

        @keyframes pulse {
            0%, 80%, 100% { transform: scale(0.8); opacity: 0.7; }
            40% { transform: scale(1.2); opacity: 1; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .button {
            background-color: #4f46e5;
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 1.1rem;
            transition: 0.3s ease;
            display: inline-block;
        }

        .button:hover {
            background-color: #4338ca;
            transform: translateY(-2px);
        }

        footer {
            margin-top: 25px;
            font-size: 0.9rem;
            color: #334155;
        }

        @media (max-width: 500px) {
            .thankyou-container { padding: 25px; }
            h1 { font-size: 1.6rem; }
        }
    </style>
</head>
<body>
    <div class="thankyou-container">
        <h1>Merci, <?= htmlspecialchars($username) ?> 🎉</h1>
        <h2>Ton profil linguistique a bien été enregistré.</h2>
        
        <div class="ai-loader">
            <div class="dot"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>

        <p>Veronica AI analyse maintenant tes réponses afin de créer un parcours d’apprentissage personnalisé 💡<br>
        Tu recevras bientôt tes premières leçons adaptées à ton niveau et à tes objectifs.</p>

        <a href="dashboard.php" class="button">👉 Accéder à mon espace d’apprentissage</a>

        <footer>🪄 Veronica AI – Ton coach linguistique intelligent</footer>
    </div>

    <script>
        // 🔊 Synthèse vocale automatique
        window.onload = () => {
            const synth = window.speechSynthesis;
            const text = "Merci d’avoir complété ton profil linguistique. Je prépare ton parcours d’apprentissage personnalisé. À très bientôt !";
            if (synth) {
                const utter = new SpeechSynthesisUtterance(text);
                utter.lang = 'fr-FR';
                synth.speak(utter);
            }
        };
    </script>
</body>
</html>

<?php ob_end_flush(); ?>





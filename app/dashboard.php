<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Lax', // ✅ Lax évite la perte de cookie après redirection
]);

// ====== Vérification de la session ======
if (empty($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit;
}

$username_session = $_SESSION['username'];

// ====== Connexion à la base ======
$db_host = 'ep-autumn-salad-adwou7x2-pooler.c-2.us-east-1.aws.neon.tech';
$db_port = '5432';
$db_name = 'veronica_db_login';
$db_user = 'neondb_owner';
$db_pass = 'npg_QolPDv5L9gVj';

try {
    $pdo = new PDO(
        "pgsql:host=$db_host;port=$db_port;dbname=$db_name;sslmode=require",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("<h3 style='color:red;'>❌ Erreur connexion Neon :</h3>" . htmlspecialchars($e->getMessage()));
}

// ====== Récupération des infos de l’utilisateur ======
try {
    $stmt = $pdo->prepare("
        SELECT username, level, goal, skills, accent, days, minutes 
        FROM user_quiz 
        WHERE username = :username 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([':username' => $username_session]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $username = htmlspecialchars($user['username']);
        $level = htmlspecialchars($user['level']);
        $goal = htmlspecialchars($user['goal']);
        $skills = htmlspecialchars($user['skills']);
        $accent = htmlspecialchars($user['accent']);
        $days = intval($user['days']);
        $minutes = intval($user['minutes']);
    } else {
        $username = htmlspecialchars($username_session);
        $level = $goal = $skills = $accent = "Non défini";
        $days = $minutes = 0;
    }

} catch (PDOException $e) {
    error_log("Erreur SQL Dashboard: " . $e->getMessage());
}

// ====== Calcul des minutes ======
$totalWeekly = $days * $minutes;
$totalMonthly = $totalWeekly * 4;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Tableau de bord – Veronica AI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {margin: 0; padding: 0; box-sizing: border-box;}
        body {font-family: "Poppins", sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;}
        .sidebar {position: fixed; top: 0; left: 0; width: 260px; height: 100%; background: linear-gradient(180deg, #4f46e5, #6366f1); color: white; padding: 30px 20px;}
        .sidebar h1 {text-align: center; font-size: 1.8rem; margin-bottom: 40px;}
        .sidebar a {display: block; color: white; padding: 14px 20px; text-decoration: none; margin-bottom: 8px; border-radius: 12px;}
        .sidebar a:hover {background: rgba(255,255,255,0.2);}
        .main {margin-left: 280px; padding: 40px;}
        .header {background: white; border-radius: 20px; padding: 30px; margin-bottom: 30px;}
        .cards {display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;}
        .card {background: white; border-radius: 20px; padding: 30px;}
        .button {background: linear-gradient(135deg, #4f46e5, #6366f1); color: white; padding: 12px 24px; border-radius: 12px; text-decoration: none;}
    </style>
</head>
<body>
    <div class="sidebar">
        <h1>🎓 Veronica AI</h1>
        <a href="#" class="active">🏠 Accueil</a>
        <a href="conversation.php">🗣️ Conversations</a>
        <a href="#">🏆 Classement</a>
        <a href="profile.php">👤 Profil</a>
        <a href="index.php" style="margin-top: 20px; background: rgba(239,68,68,0.2);">🚪 Déconnexion</a>
    </div>

    <div class="main">
        <div class="header">
            <h2>Bonjour, <?= $username ?> 👋</h2>
            <p>Voici ton tableau de bord personnalisé pour suivre ton apprentissage 🇫🇷</p>
        </div>

        <div class="cards">
            <div class="card">
                <h3>Profil linguistique</h3>
                <p><strong>Niveau :</strong> <?= $level ?></p>
                <p><strong>Objectif :</strong> <?= $goal ?></p>
                <p><strong>Accent :</strong> <?= $accent ?></p>
                <p><strong>Compétences :</strong> <?= $skills ?></p>
            </div>

            <div class="card">
                <h3>Pratique</h3>
                <p><strong>Jours/semaine :</strong> <?= $days ?></p>
                <p><strong>Minutes/jour :</strong> <?= $minutes ?></p>
                <p><strong>Total/semaine :</strong> <?= $totalWeekly ?> min</p>
                <p><strong>Total/mois :</strong> <?= $totalMonthly ?> min</p>
            </div>

            <div class="card">            
                <a href="lessons.php" class="button">🚀 Commencer la révision des grammaires</a>
            </div>
        </div>
    </div>
</body>
</html>
<?php ob_end_flush(); ?>













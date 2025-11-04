<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

// --- Configuration de la base Neon ---
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
    die("Erreur de connexion : " . $e->getMessage());
}

// --- Récupération de l'utilisateur connecté ---
$username_session = $_SESSION['username'] ?? 'Invité';

// ====== RÉCUPÉRATION DU NIVEAU DE L'UTILISATEUR ======
try {
    $stmt = $pdo->prepare("
        SELECT username, level 
        FROM user_quiz 
        WHERE username = ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$username_session]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $username = htmlspecialchars($user['username'] ?? $username_session);
    $user_level = $user['level'] ?? 'A1';
} catch (PDOException $e) {
    error_log("Erreur : " . $e->getMessage());
    $username = $username_session;
    $user_level = 'A1';
}

// ====== DÉFINITION DES LEÇONS ======
$lessons_by_level = [
    'A1' => [
        'grammaire' => [
            ['title' => 'Les articles (le, la, les)', 'duration' => '15 min', 'icon' => '📝', 'theme' => 'articles'],
            ['title' => 'Le présent des verbes être et avoir', 'duration' => '20 min', 'icon' => '✍️', 'theme' => 'etre_avoir'],
            ['title' => 'Les pronoms personnels', 'duration' => '10 min', 'icon' => '👤', 'theme' => 'pronoms'],
            ['title' => 'La négation simple (ne...pas)', 'duration' => '12 min', 'icon' => '🚫', 'theme' => 'negation']
        ],
        'vocabulaire' => [
            ['title' => 'Se présenter et saluer', 'duration' => '15 min', 'icon' => '👋', 'theme' => 'vocabulaire_saluer'],
            ['title' => 'Les nombres de 0 à 100', 'duration' => '18 min', 'icon' => '🔢', 'theme' => 'vocabulaire_nombres'],
            ['title' => 'Les jours et les mois', 'duration' => '12 min', 'icon' => '📅', 'theme' => 'vocabulaire_jours'],
            ['title' => 'La famille', 'duration' => '15 min', 'icon' => '👨‍👩‍👧', 'theme' => 'vocabulaire_famille']
        ],
        'conversation' => [
            ['title' => 'Commander au restaurant', 'duration' => '20 min', 'icon' => '🍽️', 'theme' => 'conversation_restaurant'],
            ['title' => 'Demander son chemin', 'duration' => '18 min', 'icon' => '🗺️', 'theme' => 'conversation_chemin'],
            ['title' => 'Faire les courses', 'duration' => '16 min', 'icon' => '🛒', 'theme' => 'conversation_courses']
        ]
    ]
];

$current_lessons = $lessons_by_level[$user_level] ?? $lessons_by_level['A1'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Leçons - Niveau <?= $user_level ?> - Veronica AI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: "Poppins", sans-serif; background: linear-gradient(135deg, #667eea, #764ba2); margin: 0; }
        .sidebar { position: fixed; width: 260px; height: 100%; background: #4f46e5; color: white; padding: 25px; }
        .sidebar h1 { text-align: center; font-size: 1.8rem; margin-bottom: 30px; }
        .sidebar a { display: block; padding: 12px; color: white; text-decoration: none; border-radius: 10px; margin-bottom: 10px; transition: 0.3s; }
        .sidebar a:hover { background: rgba(255,255,255,0.2); }
        .sidebar a.active { background: rgba(255,255,255,0.3); }
        .main { margin-left: 280px; padding: 40px; }
        .header { background: white; border-radius: 15px; padding: 25px; margin-bottom: 30px; }
        .header h2 { margin: 0 0 10px; }
        .level-badge { display: inline-block; background: #4f46e5; color: white; padding: 6px 15px; border-radius: 25px; }
        .lessons-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .lesson-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: 0.3s; }
        .lesson-card:hover { transform: translateY(-5px); }
        .lesson-icon { font-size: 2rem; }
        .start-btn { background: #4f46e5; color: white; border: none; padding: 10px; width: 100%; border-radius: 10px; cursor: pointer; margin-top: 10px; }
        .start-btn:hover { background: #4338ca; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h1>🎓 Veronica AI</h1>
        <a href="dashboard.php">🏠 Accueil</a>
        <a href="lessons.php" class="active">📖 Leçons</a>
        <a href="index.php">💬 Veronica Chat</a>
        <a href="profile.php">👤 Profil</a>
        <a href="index.php" style="background: rgba(239,68,68,0.3);">🚪 Déconnexion</a>
    </div>

    <div class="main">
        <div class="header">
            <h2>📚 Leçons personnalisées pour <?= $username ?></h2>
            <p>Ton niveau actuel : <span class="level-badge"><?= $user_level ?></span></p>
        </div>

        <?php foreach ($current_lessons as $category => $lessons): ?>
            <h3 style="color:white;"><?= ucfirst($category) ?></h3>
            <div class="lessons-grid">
                <?php foreach ($lessons as $lesson): ?>
                    <div class="lesson-card">
                        <div class="lesson-icon"><?= $lesson['icon'] ?></div>
                        <h4><?= htmlspecialchars($lesson['title']) ?></h4>
                        <p>Durée : <?= htmlspecialchars($lesson['duration']) ?></p>
                        <button class="start-btn" onclick="startLesson('<?= $lesson['theme'] ?>', '<?= $category ?>', '<?= $user_level ?>')">
                            Commencer la leçon
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <br>
        <?php endforeach; ?>
    </div>

    <script>
        function startLesson(theme, category, level) {
            window.location.href = `lesson.php?theme=${theme}&category=${category}&level=${level}`;
        }
    </script>
</body>
</html>

<?php ob_end_flush(); ?>






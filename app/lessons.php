<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

$username = $_SESSION['username'];
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



$username_session = $_SESSION['username'];

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

// ====== MAPPING TITRE -> THEME_KEY ======
$title_to_theme = [
    'Les articles (le, la, les)' => 'articles',
    'Le présent des verbes être et avoir' => 'etre_avoir',
    'Les pronoms personnels' => 'pronoms',
    'La négation simple (ne...pas)' => 'negation',
    'Se présenter et saluer' => 'vocabulaire_saluer',
    'Les nombres de 0 à 100' => 'vocabulaire_nombres',
    'Les jours et les mois' => 'vocabulaire_jours',
    'La famille' => 'vocabulaire_famille',
    'Commander au restaurant' => 'conversation_restaurant',
    'Demander son chemin' => 'conversation_chemin',
    'Faire les courses' => 'conversation_courses',
];

// ====== DÉFINITION DES LEÇONS PAR NIVEAU ======
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
    ],
    'A2' => [
        'grammaire' => [
            ['title' => 'Le passé composé', 'duration' => '25 min', 'icon' => '⏮️', 'theme' => 'passe_compose'],
            ['title' => 'L\'imparfait', 'duration' => '30 min', 'icon' => '📖', 'theme' => 'imparfait'],
            ['title' => 'Les pronoms COD et COI', 'duration' => '28 min', 'icon' => '🎯', 'theme' => 'pronoms_cod_coi'],
            ['title' => 'Le futur simple', 'duration' => '22 min', 'icon' => '🔮', 'theme' => 'futur_simple']
        ],
        'vocabulaire' => [
            ['title' => 'Décrire une personne', 'duration' => '20 min', 'icon' => '👥', 'theme' => 'vocabulaire_description'],
            ['title' => 'Parler de ses hobbies', 'duration' => '18 min', 'icon' => '🎨', 'theme' => 'vocabulaire_hobbies'],
            ['title' => 'La météo et les saisons', 'duration' => '15 min', 'icon' => '☀️', 'theme' => 'vocabulaire_meteo'],
            ['title' => 'Les vêtements', 'duration' => '17 min', 'icon' => '👔', 'theme' => 'vocabulaire_vetements']
        ],
        'conversation' => [
            ['title' => 'Raconter un voyage', 'duration' => '25 min', 'icon' => '✈️', 'theme' => 'conversation_voyage'],
            ['title' => 'Prendre rendez-vous', 'duration' => '20 min', 'icon' => '📞', 'theme' => 'conversation_rdv'],
            ['title' => 'Exprimer une opinion', 'duration' => '22 min', 'icon' => '💭', 'theme' => 'conversation_opinion']
        ]
    ],
    // Les autres niveaux peuvent garder la même structure pour l'instant
    'B1' => ['grammaire' => [], 'vocabulaire' => [], 'conversation' => []],
    'B2' => ['grammaire' => [], 'vocabulaire' => [], 'conversation' => []],
    'C1' => ['grammaire' => [], 'vocabulaire' => [], 'conversation' => []],
    'C2' => ['grammaire' => [], 'vocabulaire' => [], 'conversation' => []]
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100%;
            background: linear-gradient(180deg, #4f46e5 0%, #6366f1 100%);
            color: white;
            padding: 30px 20px;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar h1 {
            text-align: center;
            font-size: 1.8rem;
            margin-bottom: 40px;
            color: #fff;
            font-weight: 700;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: white;
            padding: 14px 20px;
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 8px;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }

        .sidebar a.active {
            background: rgba(255, 255, 255, 0.25);
            font-weight: 600;
        }

        .main {
            margin-left: 280px;
            padding: 40px;
            min-height: 100vh;
        }

        .header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .header h2 {
            color: #1e293b;
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .level-badge {
            display: inline-block;
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            margin-top: 10px;
            font-size: 1.1rem;
        }

        .category-section {
            margin-bottom: 40px;
        }

        .category-title {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .category-title h3 {
            color: #1e293b;
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .lessons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .lesson-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            cursor: pointer;
            border-left: 5px solid #4f46e5;
        }

        .lesson-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .lesson-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .lesson-title {
            color: #1e293b;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .lesson-duration {
            color: #64748b;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .lesson-duration::before {
            content: "⏱️";
        }

        .start-btn {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            margin-top: 15px;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
        }

        .start-btn:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
            transform: scale(1.02);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .lessons-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <h1>🎓 Veronica AI</h1>
        <a href="dashboard.php">🏠 Accueil</a>
        <a href="lessons.php" class="active">📖 Leçons</a>
        <a href="index.php">✍️ Conversations avec Veronica AI</a>
        <a href="ranking.php">🏆 Classement</a>
        <a href="profile.php">👤 Profil</a>
        <a href="settings.php">⚙️ Paramètres</a>
        <a href="login.php" style="margin-top: 20px; background: rgba(239, 68, 68, 0.2);">🚪 Déconnexion</a>
    </div>

    <div class="main">
        <div class="header">
            <h2>📚 Mes leçons personnalisées</h2>
            <p>Bonjour <?= $username ?> ! Voici les leçons adaptées à ton niveau</p>
            <span class="level-badge">Niveau actuel : <?= $user_level ?></span>
        </div>

        <!-- GRAMMAIRE -->
        <?php if (!empty($current_lessons['grammaire'])): ?>
            <div class="category-section">
                <div class="category-title">
                    <h3>📝 Grammaire</h3>
                </div>
                <div class="lessons-grid">
                    <?php foreach ($current_lessons['grammaire'] as $lesson): ?>
                        <div class="lesson-card">
                            <div class="lesson-icon"><?= $lesson['icon'] ?></div>
                            <div class="lesson-title"><?= htmlspecialchars($lesson['title']) ?></div>
                            <div class="lesson-duration"><?= htmlspecialchars($lesson['duration']) ?></div>
                            <button class="start-btn" onclick="startLesson('<?= $lesson['theme'] ?>', 'grammaire', '<?= $user_level ?>')">
                                Commencer la leçon
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- VOCABULAIRE -->
        <?php if (!empty($current_lessons['vocabulaire'])): ?>
            <div class="category-section">
                <div class="category-title">
                    <h3>📚 Vocabulaire</h3>
                </div>
                <div class="lessons-grid">
                    <?php foreach ($current_lessons['vocabulaire'] as $lesson): ?>
                        <div class="lesson-card">
                            <div class="lesson-icon"><?= $lesson['icon'] ?></div>
                            <div class="lesson-title"><?= htmlspecialchars($lesson['title']) ?></div>
                            <div class="lesson-duration"><?= htmlspecialchars($lesson['duration']) ?></div>
                            <button class="start-btn" onclick="startLesson('<?= $lesson['theme'] ?>', 'vocabulaire', '<?= $user_level ?>')">
                                Commencer la leçon
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- CONVERSATION -->
        <?php if (!empty($current_lessons['conversation'])): ?>
            <div class="category-section">
                <div class="category-title">
                    <h3>💬 Conversation</h3>
                </div>
                <div class="lessons-grid">
                    <?php foreach ($current_lessons['conversation'] as $lesson): ?>
                        <div class="lesson-card">
                            <div class="lesson-icon"><?= $lesson['icon'] ?></div>
                            <div class="lesson-title"><?= htmlspecialchars($lesson['title']) ?></div>
                            <div class="lesson-duration"><?= htmlspecialchars($lesson['duration']) ?></div>
                            <button class="start-btn" onclick="startLesson('<?= $lesson['theme'] ?>', 'conversation', '<?= $user_level ?>')">
                                Commencer la leçon
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function startLesson(theme, category, level) {
            // Redirection vers lesson.php avec le bon themeKey
            window.location.href = `lesson.php?theme=${theme}&category=${category}&level=${level}`;
            console.log('Navigation vers:', theme, category, level);
        }
    </script>

</body>

</html>

<?php ob_end_flush(); ?>




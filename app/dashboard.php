<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

// ====== CONNEXION À LA BASE DE DONNÉES ======
$host = 'localhost';
$dbname = 'veronica_ai_login'; // Changez selon votre BDD
$username_db = 'root';    // Changez selon vos identifiants
$password_db = '';        // Changez selon votre mot de passe

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// ====== VÉRIFICATION DE LA SESSION ======
// Vérifier si l'utilisateur est connecté via username
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$username_session = $_SESSION['username'];

// ====== RÉCUPÉRATION DES DONNÉES DEPUIS user_quiz ======
try {
    // Recherche par username (car pas de user_id dans la table)
    $stmt = $pdo->prepare("
        SELECT username, level, goal, skills, accent, days, minutes 
        FROM user_quiz 
        WHERE username = ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$username_session]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $username = htmlspecialchars($user['username'] ?? "Cher apprenant");
        $level = htmlspecialchars($user['level'] ?? "Non défini");
        $goal = htmlspecialchars($user['goal'] ?? "Non défini");
        $skills = htmlspecialchars($user['skills'] ?? "Aucune compétence sélectionnée");
        $accent = htmlspecialchars($user['accent'] ?? "Non défini");
        $days = intval($user['days'] ?? 0);
        $minutes = intval($user['minutes'] ?? 0);
    } else {
        // Fallback sur les valeurs par défaut
        $username = "Cher apprenant";
        $level = "Non défini";
        $goal = "Non défini";
        $skills = "Aucune compétence sélectionnée";
        $accent = "Non défini";
        $days = 0;
        $minutes = 0;
    }
} catch (PDOException $e) {
    error_log("Erreur de récupération des données : " . $e->getMessage());
    // Valeurs par défaut en cas d'erreur
    $username = "Cher apprenant";
    $level = "Non défini";
    $goal = "Non défini";
    $skills = "Aucune compétence sélectionnée";
    $accent = "Non défini";
    $days = 0;
    $minutes = 0;
}

$totalWeekly = $days * $minutes;
$totalMonthly = $totalWeekly * 4;
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Tableau de bord – Veronica AI</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            letter-spacing: 1px;
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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
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

        .header p {
            color: #64748b;
            font-size: 1.1rem;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
        }

        .card h3 {
            margin-top: 0;
            color: #1e293b;
            font-size: 1.4rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card h3::before {
            content: "📊";
            font-size: 1.6rem;
        }

        .card:nth-child(1) h3::before {
            content: "👤";
        }

        .card:nth-child(3) h3::before {
            content: "📚";
        }

        .card:nth-child(4) h3::before {
            content: "🏆";
        }

        .card p {
            color: #475569;
            margin-bottom: 12px;
            font-size: 1rem;
            line-height: 1.6;
        }

        .card p strong {
            color: #1e293b;
            font-weight: 600;
        }

        .button {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            display: inline-block;
            margin-top: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }

        .button:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
        }

        .chart-container {
            position: relative;
            height: 250px;
            margin-top: 20px;
        }

        canvas {
            border-radius: 12px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 15px;
        }

        .stat-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-item .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #4f46e5;
            display: block;
        }

        .stat-item .stat-label {
            font-size: 0.9rem;
            color: #64748b;
            margin-top: 5px;
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 200px;
            }

            .main {
                margin-left: 220px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 20px;
            }

            .main {
                margin-left: 0;
                padding: 20px;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            .header h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>

    <div class="sidebar">
        <h1>🎓 Veronica AI</h1>
        <a href="#" class="active">🏠 Accueil</a>
        <a href="lessons.php">📖 Leçons </a>
        <a href="index.html">✍️ Conversations avec veronica AI orale</a>
        <a href="#">🏆 Classement</a>
        <a href="#">👤 Profil</a>
        <a href="#">⚙️ Paramètres</a>
        <a href="login.php" style="margin-top: 20px; background: rgba(239, 68, 68, 0.2);">🚪 Déconnexion</a>
    </div>

    <div class="main">
        <div class="header">
            <h2>Bonjour, <?= $username ?> 👋</h2>
            <p>Voici ton tableau de bord personnalisé pour suivre ton apprentissage du français 🇫🇷</p>
        </div>

        <div class="cards">
            <div class="card">
                <h3>Profil linguistique</h3>
                <p><strong>Niveau :</strong> <?= $level ?></p>
                <p><strong>Objectif :</strong> <?= $goal ?></p>
                <p><strong>Accent préféré :</strong> <?= $accent ?></p>
                <p><strong>Compétences ciblées :</strong> <?= $skills ?></p>
            </div>

            <div class="card">
                <h3>Pratique hebdomadaire</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-value"><?= $days ?></span>
                        <span class="stat-label">Jours/semaine</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $minutes ?></span>
                        <span class="stat-label">Minutes/jour</span>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="practiceChart"></canvas>
                </div>
            </div>

            <div class="card">
                <h3>Prochaines leçons</h3>
                <p>Révise tes chapitres précédents pour consolider tes connaissances et progresser rapidement.</p>
                <a href="#" class="button">🚀 Commencer la révision</a>
            </div>

            <div class="card">
                <h3>Classement</h3>
                <p><strong>Position actuelle :</strong> 14ᵉ place 📍</p>
                <p><strong>Ligue :</strong> Argent 🥈</p>
                <p style="margin-top: 15px; padding: 12px; background: #fef3c7; border-radius: 8px; color: #92400e;">
                    💪 Continue comme ça ! Encore 50 points pour la Ligue Or !
                </p>
                <a href="#" class="button">🏅 Voir le classement complet</a>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('practiceChart');

        const data = {
            labels: ['Minutes / semaine', 'Minutes / mois'],
            datasets: [{
                label: 'Temps de pratique',
                data: [<?= $totalWeekly ?>, <?= $totalMonthly ?>],
                backgroundColor: [
                    'rgba(79, 70, 229, 0.8)',
                    'rgba(139, 92, 246, 0.8)'
                ],
                borderColor: [
                    'rgba(79, 70, 229, 1)',
                    'rgba(139, 92, 246, 1)'
                ],
                borderWidth: 2,
                borderRadius: 10,
            }]
        };

        new Chart(ctx, {
            type: 'bar',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        borderColor: 'rgba(79, 70, 229, 1)',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    }
                }
            }
        });

        // Synthèse vocale au chargement
        window.onload = () => {
            const synth = window.speechSynthesis;
            const text = "Bienvenue sur ton tableau de bord Veronica AI. Suis ton progrès et améliore tes compétences en français.";
            if (synth) {
                const utter = new SpeechSynthesisUtterance(text);
                utter.lang = 'fr-FR';
                utter.rate = 0.9;
                utter.pitch = 1;
                // Décommenter pour activer la synthèse vocale
                // synth.speak(utter);
            }
        };
    </script>

</body>

</html>

<?php ob_end_flush(); ?>
<?php
ob_start();
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);

// ====== VÉRIFICATION DE LA SESSION ======
if (!isset($_SESSION['username'])) {
    header('Location: index.php');
    exit();
}

// ====== CONNEXION À NEON POSTGRESQL ======
$db_host = 'ep-autumn-salad-adwou7x2-pooler.c-2.us-east-1.aws.neon.tech';
$db_port = '5432';
$db_name = 'veronica_db_login';
$db_user = 'neondb_owner';
$db_pass = 'npg_QolPDv5L9gVj';

try {
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name;sslmode=require";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("⚠️ Erreur de connexion à Neon : " . htmlspecialchars($e->getMessage()));
}

// ====== RÉCUPÉRATION DES DONNÉES DE L'UTILISATEUR ======
$username_session = $_SESSION['username'];
try {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = :username LIMIT 1");
    $stmt->bindParam(':username', $username_session);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $username = htmlspecialchars($user['username']);
        $user_id = $user['id'];
    } else {
        session_destroy();
        header('Location: login.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Erreur récupération utilisateur : " . $e->getMessage());
    $username = htmlspecialchars($username_session);
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choix de langue - Veronica AI</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .header { text-align: center; color: white; margin-bottom: 40px; padding: 20px; }
        .header h1 { font-size: 2.5rem; margin-bottom: 10px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3); }
        .header p { font-size: 1.2rem; opacity: 0.9; }
        .user-info { background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); padding: 15px 30px; border-radius: 50px; display: inline-block; margin-top: 15px; color: white; font-weight: 600; }
        .container { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; padding: 20px; }
        .card { background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.2); transition: transform 0.3s ease, box-shadow 0.3s ease; cursor: pointer; }
        .card:hover { transform: translateY(-10px); box-shadow: 0 15px 40px rgba(0,0,0,0.3); }
        .flag { width: 100%; height: 200px; background-size: cover; background-position: center; position: relative; }
        .flag::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 50%; background: linear-gradient(to top, rgba(0,0,0,0.3), transparent); }
        .flag-france { background-image: url('https://upload.wikimedia.org/wikipedia/commons/c/c3/Flag_of_France.svg'); }
        .flag-usa { background-image: url('https://upload.wikimedia.org/wikipedia/commons/a/a4/Flag_of_the_United_States.svg'); }
        .flag-japan { background-image: url('https://upload.wikimedia.org/wikipedia/commons/9/9e/Flag_of_Japan.svg'); }
        .flag-italy { background-image: url('https://upload.wikimedia.org/wikipedia/commons/0/03/Flag_of_Italy.svg'); }
        .flag-germany { background-image: url('https://upload.wikimedia.org/wikipedia/commons/b/ba/Flag_of_Germany.svg'); }
        .flag-china { background-image: url('https://upload.wikimedia.org/wikipedia/commons/f/fa/Flag_of_the_People%27s_Republic_of_China.svg'); }
        .content { padding: 25px; text-align: center; }
        .content h2 { color: #1e293b; font-size: 1.8rem; margin-bottom: 15px; }
        .content p { color: #64748b; margin-bottom: 20px; line-height: 1.6; }
        button { background: linear-gradient(135deg,#4f46e5 0%,#6366f1 100%); border: none; padding: 14px 28px; border-radius: 12px; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(79,70,229,0.3); width: 100%; }
        button:hover { background: linear-gradient(135deg,#4338ca 0%,#4f46e5 100%); transform: translateY(-2px); box-shadow: 0 6px 20px rgba(79,70,229,0.4); }
        button a { color: white; text-decoration: none; display: block; font-weight: 600; font-size: 1rem; }
        .logout-btn { position: fixed; top: 20px; right: 20px; background: rgba(239,68,68,0.9); color: white; padding: 12px 24px; border-radius: 12px; text-decoration: none; font-weight: 600; box-shadow: 0 4px 15px rgba(239,68,68,0.3); transition: all 0.3s ease; z-index: 1000; }
        .logout-btn:hover { background: rgba(220,38,38,1); transform: translateY(-2px); }
        @media(max-width:768px){.header h1{font-size:2rem}.container{grid-template-columns:1fr;gap:20px}.logout-btn{position:static;display:block;margin:20px auto;width:fit-content;}}
    </style>
</head>

<body>
    <a href="index.php" class="logout-btn">🚪 Déconnexion</a>

    <div class="header">
        <h1>🌍 Choix de langue</h1>
        <p>Sélectionnez la langue que vous souhaitez apprendre avec Veronica AI</p>
        <div class="user-info">
            👤 Connecté en tant que : <?= $username ?>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <div class="flag flag-france"></div>
            <div class="content">
                <h2>🇫🇷 Français</h2>
                <p>Apprenez le français avec Veronica AI</p>
                <button><a href="french.php">Commencer l'apprentissage</a></button>
            </div>
        </div>

        <div class="card">
            <div class="flag flag-usa"></div>
            <div class="content">
                <h2>🇺🇸 Anglais (USA)</h2>
                <p>Apprenez l'anglais américain avec Veronica AI</p>
                <button><a href="eng.php">Commencer l'apprentissage</a></button>
            </div>
        </div>

        <div class="card">
            <div class="flag flag-japan"></div>
            <div class="content">
                <h2>🇯🇵 Japonais</h2>
                <p>Apprenez le japonais avec Veronica AI</p>
                <button><a href="japan.php">Commencer l'apprentissage</a></button>
            </div>
        </div>

        <div class="card">
            <div class="flag flag-italy"></div>
            <div class="content">
                <h2>🇮🇹 Italien</h2>
                <p>Apprenez l'italien avec Veronica AI</p>
                <button><a href="italie.php">Commencer l'apprentissage</a></button>
            </div>
        </div>

        <div class="card">
            <div class="flag flag-germany"></div>
            <div class="content">
                <h2>🇩🇪 Allemand</h2>
                <p>Apprenez l'allemand avec Veronica AI</p>
                <button><a href="Allemagne.php">Commencer l'apprentissage</a></button>
            </div>
        </div>

        <div class="card">
            <div class="flag flag-china"></div>
            <div class="content">
                <h2>🇨🇳 Chinois</h2>
                <p>Apprenez le chinois avec Veronica AI</p>
                <button><a href="China.php">Commencer l'apprentissage</a></button>
            </div>
        </div>
    </div>
</body>

</html>

<?php ob_end_flush(); ?>



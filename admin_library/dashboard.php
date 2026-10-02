<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
$currentPage = basename($_SERVER['PHP_SELF']);

// Initialiser la session avant de vérifier l'authentification
initSession();

// Vérification d'authentification et de rôle
checkAuth(['admin_library']);  // Cette fonction vérifie si l'utilisateur est authentifié et a le bon rôle

// Récupération de l'ID de l'admin_bibliotheque
$admin_id = $_SESSION['user_id'];

// Récupération des informations de admin_bibliotheque
try {
    $stmt = $pdo->prepare("
        SELECT u.first_name, u.last_name, a.email FROM user u JOIN account a ON u.id = a.id_user WHERE u.id = ?
    ");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();

    if (!$admin) {
        throw new Exception("No admin found");
    }

    $nomComplet = htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']);
    $email = htmlspecialchars($admin['email']);
} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    $_SESSION['error'] = "Error retrieving information";
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Admin Bibliothèque</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/adminstyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Styles for personal info in sidebar */
        .sidebar-personal-info {
            padding: 15px;
            margin-bottom: 20px;
            background-color: #2c3e50;
            border-radius: 5px;
            color: #ecf0f1;
        }
        
        .sidebar-personal-info h4 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #3498db;
            border-bottom: 1px solid #3498db;
            padding-bottom: 5px;
        }
        
        .info-item {
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .info-label {
            font-weight: bold;
            color: #bdc3c7;
            display: inline-block;
            margin-right: 5px;
        }
        
        .info-value {
            color: #ecf0f1;
            word-break: break-word;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Navigation latérale -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../assets/img/logo_without backg.png" alt="Logo" width="70" height="50">
                <h3 class="app-name">Gestion de Projets Universitaires</h3>
                <div class="user-profile">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-details">
                        <span class="user-name"><?= htmlspecialchars($_SESSION['user']['first_name'] ?? '') . ' ' . htmlspecialchars($_SESSION['user']['last_name'] ?? '') ?></span>
                        <?php if (($_SESSION['user']['role'] ?? '') === 'student' && isset($_SESSION['user']['details']['matricule'])): ?>
                            <span class="user-matricule"><?= htmlspecialchars($_SESSION['user']['details']['matricule']) ?></span>
                        <?php endif; ?>
                        <span class="user-role"><?= ucfirst($_SESSION['user']['role'] ?? '') ?></span>
                    </div>
                </div>
            </div>

            <!-- Informations personnelles dans la sidebar -->
            <div class="sidebar-personal-info">
                <h4>Informations personnelles</h4>
                <div class="info-item">
                    <span class="info-label">Nom:</span>
                    <span class="info-value"><?= $nomComplet ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?= $email ?></span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="quitus.php" class="nav-item <?= $currentPage == 'quitus.php' ? 'active' : '' ?>">
                    <i class="fas fa-archive"></i>
                    <span>Gestion des Quitus</span>
                </a>
                <a href="../logout.php" class="nav-item logout"> <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </nav>
        </aside>
        <!-- Zone de contenu principal -->
        <div class="main-content">
            <div class="content-content">
                <!-- Statistiques -->
                <div class="main-cards">
                    <div class="card">
                        <h4 class="inner-card">
                            Nombre total d'étudiants
                        </h4>
                        <h3>
                            <?php
                            // Calcul du nombre d'Étudiants 
                            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT id) AS total_etudiants_avec_memoire
                                                    FROM student;
                                                    ");
                            $stmt->execute(); // Aucun paramètre
                            echo $stmt->fetch()['total_etudiants_avec_memoire'] ?? 0;
                            ?>
                        </h3>
                        <p class="text-muted">Étudiants</p>
                    </div>

                    <div class="card">
                        <h4 class="inner-card">
                            Nombre d'étudiants ayant déposé leur mémoire
                        </h4>
                        <h3>
                            <?php
                            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.id) AS total_etudiants_avec_memoire
                                                    FROM student s
                                                    JOIN groupe g ON g.id = s.id_groupe 
                                                    JOIN project p ON g.id_project = p.id
                                                    JOIN pv_thesis pv ON pv.id_project = p.id
                                                    JOIN thesis th ON th.id_pv = pv.id
                                                   ");
                            $stmt->execute();
                            echo $stmt->fetch()['total_etudiants_avec_memoire'] ?? 0;
                            ?>
                        </h3>
                        <p class="text-muted">Étudiants ayant déposé leur mémoire</p>
                    </div>

                    <div class="card">
                        <h4 class="inner-card">
                            Nombre d'étudiants avec mémoire validé
                        </h4>
                        <h3>
                            <?php
                            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.id) AS total_etudiants_avec_memoire
                                                    FROM student s
                                                    JOIN groupe g ON g.id = s.id_groupe 
                                                    JOIN project p ON g.id_project = p.id
                                                    JOIN pv_thesis pv ON pv.id_project = p.id
                                                    JOIN thesis th ON th.id_pv = pv.id WHERE status = 'approved'");
                            $stmt->execute();
                            echo $stmt->fetch()['total_etudiants_avec_memoire'] ?? 0;
                            ?>
                        </h3>
                        <p class="text-muted">Étudiants avec mémoire validé</p>
                    </div>

                    <div class="card">
                        <h4 class="inner-card">
                            Nombre d'étudiants avec quitus généré
                        </h4>
                        <h3>
                            <?php
                            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.id) AS nombre_etudiants_avec_quitus_ready
                                            FROM student s
                                            WHERE quitus_status = 'ready';
                                            ");
                            $stmt->execute();
                            echo $stmt->fetch()['nombre_etudiants_avec_quitus_ready'] ?? 0;
                            ?>
                        </h3>
                        <p class="text-muted">Étudiants avec quitus</p>
                    </div>

                    <div class="card">
                        <h4 class="inner-card">
                            Nombre d'étudiants sans quitus
                        </h4>
                        <?php
                        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.id) AS nombre_etudiants_avec_quitus_not_ready
                                            FROM student s
                                            WHERE quitus_status = 'not_ready';
                                            ");
                        $stmt->execute();
                        $nb_non_genere = $stmt->fetch()['nombre_etudiants_avec_quitus_not_ready'] ?? 0;
                        ?>
                        <h3><?= $nb_non_genere ?></h3>
                        <p class="text-muted">Étudiants sans quitus</p>
                    </div>

                    <div class="card">
                        <h4 class="inner-card">
                            Nombre d'étudiants avec mémoire non validé
                        </h4>
                        <h3>
                            <?php
                            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.id) AS nb_etudiants_non_approves
                                                    FROM student s
                                                    JOIN groupe g ON g.id = s.id_groupe 
                                                    JOIN project p ON g.id_project = p.id
                                                    JOIN pv_thesis pv ON pv.id_project = p.id
                                                    JOIN thesis th ON th.id_pv = pv.id WHERE status != 'approved' ");
                            $stmt->execute();
                            echo $stmt->fetch()['nb_etudiants_non_approves'] ?? 0;
                            ?>
                        </h3>
                        <p class="text-muted">Étudiants avec mémoire rejeté ou en attente de validation</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer>
        <small>&copy; 2025 Gestion des Mémoires Universitaires. Tous droits réservés.</small>
    </footer>
</body>

</html>

<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
$currentPage = basename($_SERVER['PHP_SELF']);

// Initialiser la session
initSession();

// Vérification d'authentification et de rôle
checkAuth(['student']);

// ID de l'utilisateur connecté
$admin_id = $_SESSION['user_id'];

try {
    // Récupération infos de base de l'utilisateur
    $stmt = $pdo->prepare("
        SELECT u.first_name, u.last_name, a.email 
        FROM user u 
        JOIN account a ON u.id = a.id_user 
        WHERE u.id = ?
    ");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();

    if (!$admin) {
        throw new Exception("Utilisateur introuvable");
    }

    $nomComplet = htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']);
    $email = htmlspecialchars($admin['email']);

    // Récupérer ID étudiant
    $stmt = $pdo->prepare("SELECT id FROM student WHERE user_id = ?");
    $stmt->execute([$admin_id]);
    $studentBasic = $stmt->fetch();

    if (!$studentBasic) {
        throw new Exception("Aucun étudiant associé à cet utilisateur.");
    }
} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors de la récupération des données.";
    header('Location: login.php');
    exit();
}
$stmt = $pdo->prepare("SELECT g.code AS group_code, p.title AS project_title, pv.id AS pv_id
    FROM student s
    JOIN groupe g ON s.id_groupe = g.id
    JOIN project p ON g.id_project = p.id
   LEFT JOIN pv_thesis pv ON pv.id_project = p.id
    WHERE s.user_id = ?
");
$stmt->execute([$admin_id]);
$data = $stmt->fetch();

// Initialize variables to prevent undefined variable errors
$no_project_title = false;
$no_pv = false;
$no_submission = false;
$project_title = '';
$currentStatus = '';
$currentFeedback = '';
$currentFilePath = '';
$currentFileName = '';
$currentDate = '';
$reviewdAt = '';
$history = [];

if (empty($data['project_title'])) {
    $no_project_title = true;
} elseif (empty($data['pv_id'])) {
    $no_pv = true;
    $project_title = htmlspecialchars($data['project_title']);
} else {
    $pv_id = $data['pv_id'];
    $project_title = htmlspecialchars($data['project_title']);

    // Rechercher les mémoires seulement si un PV existe
    $stmt = $pdo->prepare("
        SELECT * FROM thesis
        WHERE id_pv = ? AND is_deleted = 0
        ORDER BY submitted_at DESC
    ");
    $stmt->execute([$pv_id]);
    $theses = $stmt->fetchAll();

    if (!$theses) {
        $no_submission = true;
    } else {
        $current = $theses[0];
        $currentStatus = strtoupper($current['status']);
        $currentFeedback = nl2br(htmlspecialchars($current['feedback'] ?? ''));
        $currentFilePath = htmlspecialchars($current['file_path']);
        $currentFileName = htmlspecialchars($current['original_filename']);
        $currentDate = date('d/m/Y H:i', strtotime($current['submitted_at']));
        // Fix for deprecated strtotime with null parameter
        $reviewdAt = !empty($current['reviewed_at']) ? date('d/m/Y H:i', strtotime($current['reviewed_at'])) : 'Non disponible';
        $history = array_slice($theses, 1);
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Étudiant - Soumission</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../admin_library/css/adminstyle.css">
    <style>
        .info-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .info-section h2 {
            text-align: center;
        }

        h2,
        h3 {
            color: #2c3e50;
        }

        .content-content a {
            color: #2980b9;
            text-decoration: none;
        }

        .content-content a:hover {
            text-decoration: underline;
        }

        p {
            line-height: 1.6;
        }

        ul {
            padding-left: 20px;
        }

        li {
            margin-bottom: 20px;
            border-left: 4px solid #3498db;
            padding-left: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }

        .status {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .status.APPROVED {
            background-color: #2ecc71;
            color: white;
        }

        .status.MODIFICATIONREQUEST {
            background-color: #e74c3c;
            color: white;
        }

        .status.PENDING {
            background-color: #f1c40f;
            color: black;
        }

        .feedback {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .feedback.APPROVED {
            background-color: #2ecc71;
            color: white;
        }

        .feedback.MODIFICATIONREQUEST {
            background-color: #e74c3e;
            color: white;
        }

        .feedback.PENDING {
            background-color: #f1c40f;
            color: black;
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

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="uploadPV.php" class="nav-item <?= $currentPage == 'uploadPV.php' ? 'active' : '' ?>">
                    <i class="fas fa-file-upload"></i>
                    <span>Déposer la fiche d'évaluation</span>
                </a>
                <a href="upload.php" class="nav-item <?= $currentPage == 'upload.php' ? 'active' : '' ?>">
                    <i class="fas fa-file-upload"></i>
                    <span>Déposer le mémoire</span>
                </a>
                <a href="status.php" class="nav-item <?= $currentPage == 'status.php' ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i>
                    <span>Statut des soumissions</span>
                </a>
                <a href="../logout.php" class="nav-item logout"> <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </nav>
        </aside>
        <!-- Zone de contenu principal -->
        <div class="main-content">
            <div class="content-content">
                <div class="info-section">
                    <?php if (!empty($no_project_title)): ?>
                        <p>Vous n'avez pas encore de projet attribué.</p>
                    <?php elseif (!empty($no_pv)): ?>
                        <p>Aucun PV (fiche d'évaluation) n'a encore été déposé. Veuillez <a href='uploadPV.php'>déposer votre fiche d'évaluation</a> avant de déposer votre mémoire.</p>
                    <?php elseif (!empty($no_submission)): ?>
                        <p>Aucun mémoire n'a encore été déposé. Veuillez <a href='upload.php'>déposer votre mémoire</a>.</p>
                    <?php else: ?>
                        <h2>Statut actuel</h2>
                        <p><strong>Projet :</strong> <?= $project_title ?></p>
                        <p><strong>Statut :</strong> <span class="status <?= $currentStatus ?>"><?= $currentStatus ?></span></p>
                        <p><strong>Date de soumission :</strong> <?= $currentDate ?></p>
                        <p><a href="../<?= $currentFilePath ?>" target="_blank">Voir le mémoire (<?= $currentFileName ?>)</a></p>

                        <?php if ($currentStatus !== 'APPROVED'): ?>
                            <?php if (!empty($currentFeedback)): ?>
                                <p><strong>Retour :</strong> <span class="feedback <?= $currentStatus ?>">
                                    <?= $currentFeedback ?>
                                </span></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="info-section">
                    <?php if (!empty($history)): ?>
                        <h2>Historique des soumissions</h2>
                        <ul>
                            <?php foreach ($history as $thesis): ?>
                                <li>
                                    <strong>Date :</strong> <?= date('d/m/Y H:i', strtotime($thesis['submitted_at'])) ?><br>
                                    <strong>Statut :</strong>
                                    <span class="status <?= strtoupper($thesis['status']) ?>"><?= strtoupper($thesis['status']) ?></span><br>
                                    <strong>Fichier :</strong>
                                    <a href="../<?= htmlspecialchars($thesis['file_path']) ?>" target="_blank"><?= htmlspecialchars($thesis['original_filename']) ?></a><br>
                                    <?php if (!empty($thesis['feedback'])): ?>
                                        <strong>Retour :</strong>
                                        <span class="feedback <?= strtoupper($thesis['status']) ?>"><?= nl2br(htmlspecialchars($thesis['feedback'])) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <small>&copy; 2025 Gestion des Mémoires Universitaires. Tous droits réservés.</small>
    </footer>
</body>

</html>

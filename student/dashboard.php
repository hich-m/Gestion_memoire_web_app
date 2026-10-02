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

    $studentId = $studentBasic['id'];

    // Récupérer les détails du projet, groupe, encadrants, etc.
    $stmt = $pdo->prepare("
        SELECT 
            u.first_name,
            u.last_name,
            s.matricule,
            s.quitus_status,
            s.diploma_status,
            d.title AS department,

            g.code AS group_code,

            p.id AS project_id,
            p.title AS project_title,
            p.description AS project_description,
            p.key_words,
            p.level AS project_level,
            p.type AS project_type,
            p.place,
            p.created_at,

            r.role AS project_role,
            pu.first_name AS professor_first_name,
            pu.last_name AS professor_last_name,
            prof.grade AS professor_grade

        FROM student s
        JOIN user u ON s.user_id = u.id
        LEFT JOIN account a ON a.id_user = u.id
        LEFT JOIN departement d ON s.department_id = d.departement_id
        LEFT JOIN groupe g ON s.id_groupe = g.id
        LEFT JOIN project p ON g.id_project = p.id
        LEFT JOIN role r ON r.id_project = p.id
        LEFT JOIN professor prof ON r.professor_id = prof.id
        LEFT JOIN user pu ON prof.user_id = pu.id

        WHERE s.user_id = ?
    ");
    $stmt->execute([$admin_id]);
    $studentDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur: " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors de la récupération des données.";
    header('Location: login.php');
    exit();
}

$feedback = '';
$group_id = null;
$group_members = [];

if ($studentId) {
    $stmt = $pdo->prepare("SELECT feedback, id_groupe FROM student WHERE id = ?");
    $stmt->execute([$studentId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $feedback = $result['feedback'];
        $group_id = $result['id_groupe'];
    }

    // Si un groupe est défini, récupérer les autres membres
    $stmt = $pdo->prepare("
    SELECT u.first_name, u.last_name, a.email 
    FROM student s 
    JOIN user u ON s.user_id = u.id
    LEFT JOIN account a ON a.id_user = u.id
    WHERE s.id_groupe = ? AND s.id != ?
");
    $stmt->execute([$group_id, $studentId]);
    $group_members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer quitus .
$stmt = $pdo->prepare("SELECT s.quitus_status AS quitus, s.id AS student_id
                           FROM student s
                           WHERE s.user_id = ?
                       ");
$stmt->execute([$admin_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Étudiant - Tableau de bord</title>
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

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .info-item {
            margin-bottom: 10px;
        }

        .info-label {
            font-weight: bold;
            color: #495057;
        }

        .team-members {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .member-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .project-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .project-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .project-description {
            line-height: 1.6;
            color: #495057;
        }

        .header {
            margin-bottom: 30px;
        }

        .feedback-card {
            background-color: #ffe4b2;
            border: 1px solid #ffae42;
            border-radius: 5px;
            padding: 15px;
            margin: 20px 0;
            color: #663c00;
            font-family: Arial, sans-serif;
        }

        .feedback-title {
            font-weight: bold;
            margin-bottom: 10px;
        }

        .quitus-card {
            border: 1px solid #28a745;
            border-radius: 8px;
            background-color: #f9fdfa;
            color: #333;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .quitus-title {
            font-weight: 700;
            font-size: 1.6rem;
            color: #28a745;
            margin-bottom: 20px;
            text-align: center;
        }

        .card-title {
            font-weight: 600;
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 1.3rem;
        }

        .card-text {
            font-size: 1.1rem;
            margin-bottom: 25px;
            color: #555;
        }

        .btn-success {
            background-color: transparent;
            border: 2px solid #28a745;
            color: #28a745;
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            background-color: #28a745;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
    </style>

</head>

<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
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
        <!-- Main Content Area -->
        <div class="main-content">
            <div class="content-content">
                <!-- Student Information Section -->
                <div class="info-section">
                    <h2>Informations de l'étudiant</h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Prénom :</div>
                            <div><?php echo htmlspecialchars($studentDetails[0]['first_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Nom :</div>
                            <div><?php echo htmlspecialchars($studentDetails[0]['last_name']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Matricule :</div>
                            <div><?php echo isset($studentDetails[0]['matricule']) ? htmlspecialchars($studentDetails[0]['matricule']) : 'Non disponible'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Département :</div>
                            <div><?php echo htmlspecialchars($studentDetails[0]['department']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Statut Quitus :</div>
                            <?php
                            $status = $studentDetails[0]['quitus_status'];
                            if ($status === 'ready') {
                                echo "Votre quitus est prêt";
                            } else {
                                echo "Votre quitus n'est pas prêt";
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <?php if (!empty($feedback)) : ?>
                    <div class="feedback-card">
                        <div class="feedback-title">Remarques de la bibliothèque :</div>
                        <div class="feedback-content"><?= nl2br(htmlspecialchars($feedback)) ?></div>
                    </div>
                <?php endif; ?>

                <?php
                if ($student['quitus'] === 'ready') : ?>
                    <div class="quitus-card">
                        <div class="quitus-title">Quitus prêt</div>
                        <div class="card-body text-success">
                            <h5 class="card-title">Félicitations !</h5>
                            <p class="card-text">Votre quitus est prêt. Vous pouvez l'imprimer maintenant.</p>
                            <a href="imprimer_quitus.php?id=<?= htmlspecialchars($student['student_id']) ?>" class="btn btn-success" target="_blank">
                                Imprimer le quitus
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($group_members)) : ?>
                    <div class="info-section"> <strong>Autres membres de votre groupe :</strong>
                        <ul style="margin-top: 10px; list-style-type: disc; padding-left: 20px;">
                            <?php foreach ($group_members as $member): ?>
                                <li><?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' ' . $member['email']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="info-section">
                    <h2>Informations sur le projet</h2>
                    <?php if (!empty($studentDetails[0]['project_id'])): ?>
                        <div class="project-info">
                            <p><strong>Titre :</strong> <?php echo htmlspecialchars($studentDetails[0]['project_title']); ?></p>
                            <p><strong>Description :</strong> <?php echo htmlspecialchars($studentDetails[0]['project_description']); ?></p>
                            <p><strong>Mots-clés :</strong> <?php echo htmlspecialchars($studentDetails[0]['key_words']); ?></p>
                            <p><strong>Type :</strong> <?php echo htmlspecialchars($studentDetails[0]['project_type']); ?></p>
                            <p><strong>Niveau :</strong> <?php echo htmlspecialchars($studentDetails[0]['project_level']); ?></p>
                            <p><strong>Lieu :</strong> <?php echo htmlspecialchars($studentDetails[0]['place']); ?></p>
                            <p><strong>Groupe :</strong> <?php echo htmlspecialchars($studentDetails[0]['group_code']); ?></p>
                            <p><strong>Créé le :</strong> <?php echo htmlspecialchars($studentDetails[0]['created_at']); ?></p>
                        </div>
                    <?php else: ?>
                        <p>Aucun projet assigné.</p>
                    <?php endif; ?>
                </div>

                <!-- Supervisor Information Section -->
                <?php if (!empty($studentDetails)): ?>
                    <div class="info-section">
                        <h2>Professeurs</h2>
                        <div class="info-grid">
                            <?php foreach ($studentDetails as $supervisor): ?>
                               <div>
                                <div class="info-item">
                                    <div class="info-label">Rôle :</div>
                                    <div><?php echo !empty($supervisor['project_role']) ? htmlspecialchars($supervisor['project_role']) : 'Non défini'; ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Nom :</div>
                                    <div>
                                        <?php 
                                        $professorName = '';
                                        if (!empty($supervisor['professor_first_name']) || !empty($supervisor['professor_last_name'])) {
                                            $professorName = trim(
                                                (!empty($supervisor['professor_grade']) ? htmlspecialchars($supervisor['professor_grade']) . ' ' : '') .
                                                (!empty($supervisor['professor_first_name']) ? htmlspecialchars($supervisor['professor_first_name']) . ' ' : '') .
                                                (!empty($supervisor['professor_last_name']) ? htmlspecialchars($supervisor['professor_last_name']) : '')
                                            );
                                            echo $professorName ?: 'Non défini';
                                        } else {
                                            echo 'Non défini';
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Grade :</div>
                                    <div><?php echo !empty($supervisor['professor_grade']) ? htmlspecialchars($supervisor['professor_grade']) : 'Non défini'; ?></div>
                                </div>
                                </div>
                                <br> <br>
                            <?php endforeach; ?>
                        </div>
                    </div>
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

<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
$currentPage = basename($_SERVER['PHP_SELF']);

// Initialiser la session avant de vérifier l'authentification
initSession();

// Vérification d'authentification et de rôle
checkAuth(['professor']);  // Cette fonction vérifie si l'utilisateur est authentifié et a le bon rôle

// Récupération de l'ID de professor
$professorAdmin = $_SESSION['user_id'];

// Récupération des informations de professor
try {
    $stmt = $pdo->prepare("
        SELECT p.id AS teacher_id, u.first_name, u.last_name, a.email, p.grade, d.title AS department_name FROM professor p JOIN user u ON p.user_id = u.id JOIN account a ON u.id = a.id_user LEFT JOIN departement d ON p.department_id = d.departement_id WHERE u.id = ?
    ");
    $stmt->execute([$professorAdmin]);
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
//  Récupérer l'ID du professeur à partir du user_id
$stmt = $pdo->prepare("SELECT id FROM professor WHERE user_id = ?");
$stmt->execute([$professorAdmin]);
$professor = $stmt->fetch();

$professorId = $professor['id'];

// fetch projects 
$stmt = $pdo->prepare("SELECT p.id AS project_id, p.title, p.description , p.key_words, p.created_at 
    FROM project p 
    JOIN role r ON r.id_project = p.id 
    WHERE r.professor_id = ?
    AND r.role ='supervisor'
");
$stmt->execute([$professorId]);

$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professeur - Tableau de bord</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../admin_library/css/adminstyle.css">
    <style>
        .submenu {
            display: none;
            flex-direction: column;
            padding-left: 20px;
            background-color: #2f3e50;
        }

        .submenu-item {
            font-size: 14px;
            color: #ecf0f1;
            padding: 8px 15px;
            text-decoration: none;
        }

        .submenu-item:hover {
            background-color: #3c4a5a;
        }
        
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
                <div class="info-item">
                    <span class="info-label">Grade:</span>
                    <span class="info-value"><?= $admin['grade'] ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Département:</span>
                    <span class="info-value"><?= $admin['department_name'] ?></span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Tableau de bord</span>
                </a>
                <a href="#" class="toggle-submenu nav-item <?= $currentPage == 'student_details.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-graduate"></i>
                    <span>Groupes</span>
                    <i class="fas fa-caret-down submenu-icon" style="margin-left:auto;"></i>
                </a>
                <div class="submenu">
                    <a href="encadrant.php" class="nav-item submenu-item<?= $currentPage == 'encadrant.php' ? ' active' : '' ?>">Encadrant</a>
                    <a href="rapporteur.php" class="nav-item submenu-item<?= $currentPage == 'rapporteur.php' ? ' active' : '' ?>">Rapporteur</a>
                    <a href="examinateur.php" class="nav-item submenu-item<?= $currentPage == 'examinateur.php' ? ' active' : '' ?>">Président du jury</a>
                </div>
                <a href="../logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </nav>
        </aside>

        <div class="main-content">
            <div class="content-content">
                <?php if (isset($_SESSION['flash'])): ?>
                    <div style="
        padding: 10px;
        margin: 15px 0;
        border-radius: 5px;
        color: <?= $_SESSION['flash']['type'] === 'success' ? '#155724' : '#721c24' ?>;
        background-color: <?= $_SESSION['flash']['type'] === 'success' ? '#d4edda' : '#f8d7da' ?>;
        border: 1px solid <?= $_SESSION['flash']['type'] === 'success' ? '#c3e6cb' : '#f5c6cb' ?>;
    ">
                        <?= htmlspecialchars($_SESSION['flash']['message']) ?>
                    </div>
                    <?php unset($_SESSION['flash']); ?>
                <?php endif; ?>

                <!-- Removed the personal info card from here -->

                <!-- Carte Projets -->
                <div class="card">
                    <div class="card-header">
                        <h2>Vos Projets</h2>
                    </div>
                    <div class="card-body">
                        <?php if (empty($projects)): ?>
                            <p>Aucun projet ajouté.</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Titre</th>
                                        <th>Description</th>
                                        <th>Mot Clé</th>
                                        <th>Date de création</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($project['title']) ?></td>
                                            <td><?= htmlspecialchars($project['description']) ?></td>
                                            <td><?= htmlspecialchars($project['key_words']) ?></td>
                                            <td><?= htmlspecialchars($project['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Ajouter Projet -->
                <div class="card">
                    <div class="card-header">
                        <h2>Ajouter un Projet</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="ajout_project.php">
                            <div class="form-group">
                                <label for="title">Titre du Projet :</label>
                                <input type="text" id="title" name="title" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="description">Description :</label>
                                <textarea id="description" name="description" class="form-control" rows="4"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="key_words">Mot Clés :</label>
                                <textarea id="key_words" name="key_words" class="form-control" rows="4"></textarea>
                            </div>
                            <div class="form-group">
                                <label for="niveau">Niveau :</label>
                                <select name="niveau" id="niveau" required>
                                    <option value="">--Sélectionner un Niveau--</option>
                                    <option value="Master 2">Master 2</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="type">Type :</label>
                                <select name="type" id="type" required>
                                    <option value="">--Sélectionner un Type--</option>
                                    <option value="Externe">Externe</option>
                                    <option value="Interne">Interne</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="lieu">Lieu :</label>
                                <input type="text" id="lieu" name="lieu" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary" title="Ajouter un projet">Ajouter</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <footer>
        <small>&copy; 2025 Gestion des Mémoires Universitaires. Tous droits réservés.</small>
    </footer>
    <script>
        // GÉRER LE SOUS-MENU
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.querySelector('.toggle-submenu');
            const submenu = document.querySelector('.submenu');

            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                submenu.style.display = submenu.style.display === 'flex' ? 'none' : 'flex';
            });
        });
    </script>
</body>

</html>

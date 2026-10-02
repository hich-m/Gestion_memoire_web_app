<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
$currentPage = basename($_SERVER['PHP_SELF']);

// Initialiser la session avant de vérifier l'authentification
initSession();

// Vérification d'authentification et de rôle
checkAuth(['admin']);  // Cette fonction vérifie si l'utilisateur est authentifié et a le bon rôle

// Récupération de l'ID de l'admin_bibliotheque
$admin_id = $_SESSION['user_id'];


// Récupération des informations de admin
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
// Requête pour récupérer les projets et les enseignants associés
$stmt = $pdo->prepare("
    SELECT project.id AS project_id, project.title AS project_title, 
    project.description, CONCAT(user.first_name, ' ', user.last_name) AS supervisor_name,
     professor.id AS professor_id FROM role JOIN professor 
     ON role.professor_id = professor.id JOIN user ON professor.user_id = user.id 
     JOIN project 
    ON role.id_project = project.id WHERE role.role = 'supervisor';
");

// Exécution de la requête
$stmt->execute();

// Récupérer les résultats
$projets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Requête pour récupérer la liste des enseignants
$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name 
    FROM user u 
    WHERE u.role = 'professor' 
    ORDER BY u.last_name, u.first_name
");

// Exécution de la requête
$stmt->execute();

// Récupérer les résultats des enseignants
$professors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Requête pour récupérer les étudiants non assignés et leurs informations
$stmt = $pdo->prepare("
    SELECT 
    s.id AS student_id,
    u.first_name AS student_first_name,
    u.last_name AS student_last_name,
    s.matricule,
    p.title AS project_title
FROM student s
JOIN user u ON s.user_id = u.id
LEFT JOIN groupe g ON s.id_groupe = g.id
LEFT JOIN project p ON g.id_project = p.id
WHERE g.id IS NULL
ORDER BY u.last_name, u.first_name;
");

// Exécution de la requête
$stmt->execute();

// Récupérer les résultats des étudiants non assignés
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);


//PAGINATION TAB 
// Nombre de projets par page
$parPage = 10;

// Page actuelle (par défaut à 1 si non définie)
$pageActuelle = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Calcul de l'index de départ
$start = ($pageActuelle - 1) * $parPage;

// Récupérer les projets à afficher (à adapter si tu as une BDD)
$projetsPage = array_slice($projets, $start, $parPage);

// Nombre total de pages
$totalPages = ceil(count($projets) / $parPage);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des affectations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styleAdminPage.css">
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

                <a href="#" class="nav-item toggle-submenu">
                    <i class="fas fa-tasks"></i>
                    <span>Gestion des affectations</span>
                    <i class="fas fa-caret-down submenu-icon" style="margin-left:auto;"></i>
                </a>

                <div class="submenu">
                    <a href="projectList.php" class="nav-item submenu-item<?= $currentPage == 'projectList.php' ? ' active' : '' ?>">Liste des projets</a>
                    <a href="manageGroups.php" class="nav-item submenu-item<?= $currentPage == 'manageGroups.php' ? ' active' : '' ?>">Gérer les groupes</a>
                    <a href="assignGroupsToProjects.php" class="nav-item submenu-item<?= $currentPage == 'assignGroupsToProjects.php' ? ' active' : '' ?>">Assigner groupes aux projets</a>
                    <a href="assignProfessorsToGroups.php" class="nav-item submenu-item<?= $currentPage == 'assignProfessorsToGroups.php' ? ' active' : '' ?>">Assigner professeurs aux groupes</a>
                </div>

                <a href="../logout.php" class="nav-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </nav>

        </aside>

        <!-- Main Content Area -->
        <div class="main-content">
            <div class="content-content">
                <!-- Project List Tab -->
                <div class="card">
                    <div class="card-header">Liste des projets</div>
                    <div class="card-body">
                        <h3>Projets encadrés</h3>

                        <?php if (count($projets) === 0): ?>
                            <p>Aucun projet encadré trouvé.</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Titre du projet</th>
                                        <th>Description</th>
                                        <th>Nom de l'encadrant</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projetsPage as $p): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($p['project_title']) ?></td>
                                            <td><?= htmlspecialchars($p['description']) ?></td>
                                            <td><?= htmlspecialchars($p['supervisor_name']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>

                            </table>
                        <?php endif; ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <!-- Bouton Précédent -->
                                <li class="page-item <?= $pageActuelle <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $pageActuelle - 1 ?>">Précédent</a>
                                </li>

                                <!-- Liens vers chaque page -->
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i == $pageActuelle ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Bouton Suivant -->
                                <li class="page-item <?= $pageActuelle >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $pageActuelle + 1 ?>">Suivant</a>
                                </li>
                            </ul>
                        </nav>

                    </div>
                </div>

            </div>
        </div>
    </div>


    <?php require_once '../includes/footer.php'; ?>
    <script>
        //MANAGE SOUS MENU ASSIGNEMENT MANAGEMENT 
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.querySelector('.toggle-submenu');
            const submenu = document.querySelector('.submenu');

            // Afficher automatiquement si un lien est actif
            if (submenu.querySelector('.active')) {
                submenu.style.display = 'flex';
            }

            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                submenu.style.display = submenu.style.display === 'flex' ? 'none' : 'flex';
            });
        });
    </script>
</body>

</html>
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
                <?php displayFlashMessage(); ?>

                <div class="card">
                    <div class="card-header">Assigner des groupes à des projets</div>
                    <div class="card-body">
                        <form action="assignment_action.php" method="post">
                            <input type="hidden" name="action" value="assign_group_to_project">

                            <?php
                            // Retrieve projects not assigned to any group
                            $sql = "SELECT p.id, p.title FROM project p LEFT JOIN groupe g ON g.id_project = p.id WHERE g.id_project IS NULL";
                            $stmt = $pdo->query($sql);
                            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>

                            <div class="form-group">
                                <label for="project-select">Projet :</label>
                                <select id="project-select" name="project_id" class="form-control" required>
                                    <option value="">Sélectionner un projet</option>
                                    <?php
                                    if ($projects) {
                                        // Loop to display projects in the <select>
                                        foreach ($projects as $project) {
                                            echo "<option value='{$project['id']}'>{$project['title']}</option>";
                                        }
                                    } else {
                                        echo "<option value=''>Aucun projet disponible</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="group-select">Groupe :</label>
                                <select id="group-select" name="group_id" class="form-control" required>
                                    <option value="">Sélectionner un groupe</option>
                                    <?php
                                    // Query to get groups not assigned to a project with their students
                                    $sql = "SELECT 
                                                  g.id AS groupe_id, 
                                                  g.code AS groupe_code, 
                                                  GROUP_CONCAT(CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') AS students
                                              FROM groupe g 
                                              JOIN student s ON g.id = s.id_groupe 
                                              JOIN user u ON s.user_id = u.id 
                                              WHERE g.id_project IS NULL
                                              GROUP BY g.id, g.code
                                              ";

                                    $stmt = $pdo->query($sql);
                                    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    // Check if groups are found
                                    if ($groups) {
                                        // Loop to display groups in the <select>
                                        foreach ($groups as $group) {
                                            echo "<option value='{$group['groupe_id']}'>
                                    {$group['groupe_code']} - {$group['students']}
                                  </option>";
                                        }
                                    } else {
                                        echo "<option value=''>Aucun groupe disponible</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-success">Assigner</button>
                        </form>

                    </div>

                </div>
            </div>
            <!-- TABLE TO DISPLAY GROUPS ASSIGNED TO PROJECTS -->

            <div class="card">
                <div class="card-header">Étudiants par groupe et projet</div>
                <div class="card-body">
                    <?php
                    // Pagination : nombre d'éléments par page
                    $parPage = 5;

                    // Page actuelle récupérée depuis l'URL, défaut 1
                    $pageActuelle = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
                    if ($pageActuelle < 1) $pageActuelle = 1;

                    // Calculer le nombre total de groupes pour pagination
                    $countSql = $pdo->query("SELECT COUNT(DISTINCT g.id) FROM groupe g WHERE g.id_project IS NOT NULL");
                    $totalCount = $countSql->fetchColumn();
                    $totalPages = ceil($totalCount / $parPage);

                    // Calcul de l'offset
                    $offset = ($pageActuelle - 1) * $parPage;

                    // Requête avec LIMIT et OFFSET
                    $sql = $pdo->prepare("
            SELECT g.id AS group_id,
                   p.title AS project_title,
                   g.code AS group_code,
                   GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') AS student_names,
                   GROUP_CONCAT(DISTINCT CONCAT(eu.first_name, ' ', eu.last_name, ' (', r.role, ')') SEPARATOR ', ') AS teacher_name
            FROM groupe g
            JOIN project p ON g.id_project = p.id
            JOIN student s ON g.id = s.id_groupe
            JOIN user u ON s.user_id = u.id
            LEFT JOIN role r ON p.id = r.id_project
            LEFT JOIN professor prof ON r.professor_id = prof.id
            LEFT JOIN user eu ON prof.user_id = eu.id
            GROUP BY g.id, p.title, g.code
            ORDER BY p.title
            LIMIT :limit OFFSET :offset
        ");

                    $sql->bindValue(':limit', $parPage, PDO::PARAM_INT);
                    $sql->bindValue(':offset', $offset, PDO::PARAM_INT);
                    $sql->execute();

                    $liste = $sql->fetchAll(PDO::FETCH_ASSOC);

                    if (count($liste) === 0): ?>
                        <p>Aucun étudiant ou groupe n'est assigné à un projet.</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Titre du projet</th>
                                    <th>Code du groupe</th>
                                    <th>Étudiants</th>
                                    <th>Enseignant encadrant</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($liste as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['project_title']) ?></td>
                                        <td><?= htmlspecialchars($row['group_code']) ?></td>
                                        <td><?= htmlspecialchars($row['student_names']) ?></td>
                                        <td><?= htmlspecialchars($row['teacher_name'] ?? 'Non défini') ?></td>
                                        <td>
                                            <a href="assignment_action.php?action=remove_project_group&id=<?= $row['group_id'] ?>"
                                                class="btn btn-danger btn-sm"
                                                onclick="return confirm('Êtes-vous sûr de vouloir retirer cet étudiant du groupe ?')">
                                                Retirer
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination Bootstrap -->
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $pageActuelle <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $pageActuelle - 1 ?>" tabindex="-1">Précédent</a>
                                </li>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?= $i == $pageActuelle ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <li class="page-item <?= $pageActuelle >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $pageActuelle + 1 ?>">Suivant</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
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
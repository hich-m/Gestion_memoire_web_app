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
JOIN account a ON a.id_user = u.id
LEFT JOIN groupe g ON s.id_groupe = g.id
LEFT JOIN project p ON g.id_project = p.id
WHERE g.id IS NULL 
AND a.status = 'approved'
ORDER BY u.last_name, u.first_name;
");

// Exécution de la requête
$stmt->execute();

// Récupérer les résultats des étudiants non assignés
$etudiants = $stmt->fetchAll(PDO::FETCH_ASSOC);


//PAGINATION TAB 

// Nombre de résultats par page
$parPage = 10;

// Page actuelle depuis l'URL (sinon 1)
$pageActuelle = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
if ($pageActuelle < 1) $pageActuelle = 1;

// Compter le nombre total d'étudiants
$totalCount = $pdo->query("SELECT COUNT(*) FROM student")->fetchColumn();
$totalPages = ceil($totalCount / $parPage);

// Calculer l'offset pour la requête SQL
$offset = ($pageActuelle - 1) * $parPage;

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des groupes</title>
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

                <!-- Group Assignment Tab -->
                <div class="card">
                    <div class="card-header">Assigner des étudiants à un groupe</div>
                    <div class="card-body">
                        <?php
                        // 1) On récupère la liste des groupes avec le nombre d'étudiants dans chaque groupe
                        $stmt = $pdo->prepare("
                            SELECT g.id, g.code, COUNT(s.id) AS student_count 
                            FROM groupe g
                            LEFT JOIN student s ON g.id = s.id_groupe
                            GROUP BY g.id, g.code
                            HAVING COUNT(s.id) < 4
                            ORDER BY g.code
                        ");
                        $stmt->execute();
                        $groupes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <!-- Formulaire de création de groupe -->
                        <div class="mb-3">
                            <button id="showCreateGroupBtn" class="btn btn-outline-primary mb-2" type="button">
                                <i class="fas fa-plus"></i> Créer un groupe
                            </button>
                            <div id="createGroupContainer" style="display:none;">
                                <form id="createGroupForm" action="assignment_action.php" method="post">
                                    <input type="hidden" name="action" value="create_group">
                                    <div class="input-group w-100">
                                        <input type="text" name="code" class="form-control" placeholder="Code du nouveau groupe" required>
                                        <button class="btn btn-success" type="submit">Enregistrer</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if (empty($groupes)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Tous les groupes existants ont déjà 4 étudiants. Veuillez créer un nouveau groupe.
                            </div>
                        <?php else: ?>
                            <form action="assignment_action.php" method="post">
                                <input type="hidden" name="action" value="assign_group">

                                <div class="form-group mb-3">
                                    <label for="groupe_id">Groupe :</label>
                                    <select name="groupe_id" id="groupe_id" class="form-control" required>
                                        <option value="">Veuillez sélectionner un groupe</option>
                                        <?php foreach ($groupes as $g): ?>
                                            <option value="<?= $g['id'] ?>">
                                                <?= htmlspecialchars($g['code']) ?> 
                                                (<?= $g['student_count'] ?>/4 étudiants)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="etudiants">Étudiants :</label>
                                    <select name="etudiants[]" id="etudiants" class="form-control" multiple required>
                                        <?php foreach ($etudiants as $e): ?>
                                            <option value="<?= $e['student_id'] ?>">
                                                <?= htmlspecialchars($e['student_last_name'] . ' ' . $e['student_first_name']) ?>
                                                (<?= htmlspecialchars($e['matricule']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Maintenez la touche Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs étudiants.</small>
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Assigner
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">Étudiants groupés</div>
                    <div class="card-body">

                        <?php
                        $q = $pdo->prepare("SELECT s.id AS student_id, u.first_name, u.last_name, s.matricule, g.code AS groupe
                                            FROM student s
                                            JOIN user u    ON u.id = s.user_id
                                            JOIN groupe g  ON s.id_groupe = g.id
                                            JOIN account a ON a.id_user = u.id
                                            WHERE a.status='approved'
                                            ORDER BY g.code, u.last_name, u.first_name
                                            LIMIT :limit OFFSET :offset
                                        ");

                        $q->bindValue(':limit', $parPage, PDO::PARAM_INT);
                        $q->bindValue(':offset', $offset, PDO::PARAM_INT);
                        $q->execute();
                        $liste = $q->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <?php if (count($liste) === 0): ?>
                            <p>Il n'y a aucun étudiant assigné à un groupe.</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Étudiant</th>
                                        <th>Matricule</th>
                                        <th>Groupe</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($liste as $r): ?>
                                        <tr>
                                            <td><?= htmlspecialchars("{$r['first_name']} {$r['last_name']}") ?></td>
                                            <td><?= htmlspecialchars($r['matricule']) ?></td>
                                            <td><?= htmlspecialchars($r['groupe']) ?></td>
                                            <td>
                                                <a href="assignment_action.php?action=remove_from_group&id=<?= $r['student_id'] ?>"
                                                    class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Êtes-vous sûr de vouloir retirer cet étudiant du groupe ?')">
                                                    Retirer
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <li class="page-item <?= $pageActuelle <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $pageActuelle - 1 ?>">Précédent</a>
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

        document.addEventListener('DOMContentLoaded', function() {
            // Show/hide group form
            document.getElementById('showCreateGroupBtn').addEventListener('click', function() {
                const cont = document.getElementById('createGroupContainer');
                cont.style.display = cont.style.display === 'none' ? 'block' : 'none';
            });

            // Tab navigation
            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(button => {
                button.addEventListener('click', () => {
                    const tabId = button.getAttribute('data-tab');
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                    document.getElementById(tabId).classList.add('active');

                    buttons.forEach(btn => btn.classList.remove('active'));
                    button.classList.add('active');
                });
            });
        });
    </script>
</body>

</html>

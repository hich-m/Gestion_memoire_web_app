<?php
require_once '../config/db_config.php';
require_once '../includes/functions.php';
$currentPage = basename($_SERVER['PHP_SELF']);

// Initialiser la session avant de vérifier l'authentification
initSession();

// Vérification d'authentification et de rôle
checkAuth(['admin']);  // Cette fonction vérifie si l'utilisateur est authentifié et a le bon rôle

// Récupération de l'ID de l'admin
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
// Handle search and filter functionality
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'pending';
$roleFilter = isset($_GET['role']) ? sanitizeInput($_GET['role']) : 'all';

// Build base query conditions
$conditions = [];
$params = [];


// Status filter
if ($statusFilter === 'pending') {
    $conditions[] = "a.status = 'pending'";
} elseif ($statusFilter === 'approved') {
    $conditions[] = "a.status = 'approved'";
}

// Role filter
if ($roleFilter !== 'all') {
    $conditions[] = "u.role = ?";
    $params[] = $roleFilter;
}

// Search condition
if (!empty($search)) {
    $searchTerm = "%$search%";
    $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR a.email LIKE ?)";
    $params = array_merge($params, array_fill(0, 3, $searchTerm));
}
// Build the final query
$query = "SELECT 
    u.id,
    u.first_name,
    u.last_name,
    a.email,
    u.role,
    a.status,
    p.grade,
    pr.deadline AS deadline
FROM user u
JOIN account a ON a.id_user = u.id
LEFT JOIN professor p ON p.user_id = u.id AND u.role = 'professor'
LEFT JOIN student s ON s.user_id = u.id AND u.role = 'student'
LEFT JOIN groupe g ON g.id = s.id_groupe
LEFT JOIN project pr ON pr.id = g.id_project
";

// Ajouter la condition WHERE si nécessaire
if (!empty($conditions)) {
    $query .= " WHERE u.is_deleted = 0 AND " . implode(" AND ", $conditions);
} else {
    $query .= " WHERE u.is_deleted = 0";
}

// Update the ORDER BY clause to use a valid column
$query .= " ORDER BY u.id DESC";

// Exécuter la requête préparée avec PDO
$stmt = $pdo->prepare($query);
if (!empty($params)) {
    foreach ($params as $index => $param) {
        // Les index de bindValue commencent à 1 en PDO
        $stmt->bindValue($index + 1, $param);
    }
}
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter les comptes par statut pour les statistiques
$countQuery = "SELECT status, COUNT(*) as count FROM account WHERE is_deleted = 0 GROUP BY status";
$countStmt = $pdo->query($countQuery);
$statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];

while ($row = $countStmt->fetch(PDO::FETCH_ASSOC)) {
    $status = $row['status'];
    $count = $row['count'];
    if (array_key_exists($status, $statusCounts)) {
        $statusCounts[$status] = $count;
    }
}

// Compter également les comptes supprimés qui sont marqués comme rejetés
$rejectedQuery = "SELECT COUNT(*) as count FROM account WHERE is_deleted = 1 AND status = 'rejected'";
$rejectedStmt = $pdo->query($rejectedQuery);
$rejectedCount = $rejectedStmt->fetchColumn();

// Ajouter les comptes supprimés au compteur de rejetés
$statusCounts['rejected'] += $rejectedCount;

$stmt = $pdo->query("
    SELECT deadline, COUNT(*) AS total
    FROM project
    WHERE deadline IS NOT NULL
    GROUP BY deadline
    ORDER BY total DESC
    LIMIT 1
");

$commonDeadline = $stmt->fetch(PDO::FETCH_ASSOC);
$currentDeadline = $commonDeadline ? $commonDeadline['deadline'] : null;

// Formater pour <input type="datetime-local">
$formattedDeadline = $currentDeadline ? date('Y-m-d\TH:i', strtotime($currentDeadline)) : '';

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Administrateur</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styleAdminPage.css">
</head>

<body>
    <!-- Modal Modification-->
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form id="editUserForm" method="POST" action="update_user.php">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Modifier l'utilisateur</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="user_id" name="user_id">

                        <!-- Nom -->
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Nom</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required>
                        </div>

                        <!-- Prénom -->
                        <div class="mb-3">
                            <label for="first_name" class="form-label">Prénom</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required>
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>

                        <!-- Rôle -->
                        <div class="mb-3">
                            <label for="role" class="form-label">Rôle</label>
                            <select id="role" name="role" class="form-select" onchange="toggleRoleFields()" required>
                                <option value="admin">Administrateur</option>
                                <option value="admin_library">Administrateur Bibliothèque</option>
                                <option value="professor">Professeur</option>
                                <option value="student">Étudiant</option>
                            </select>
                        </div>

                        <!-- Statut -->
                        <div class="mb-3">
                            <label for="status" class="form-label">Statut</label>
                            <select id="status" name="status" class="form-select">
                                <option value="pending">En attente</option>
                                <option value="approved">Approuvé</option>
                                <option value="rejected">Rejeté</option>
                            </select>
                        </div>

                        <!-- Grade (prof) -->
                        <div class="mb-3" id="grade_field" style="display: none;">
                            <label for="grade" class="form-label">Grade</label>
                            <select id="grade" name="grade" class="form-select">
                                <option value="maitre_assistant">Maître Assistant</option>
                                <option value="assistant">Assistant</option>
                                <option value="professeur">Professeur</option>
                                <option value="docteur">Docteur</option>
                            </select>
                        </div>

                        <!-- Mot de passe (étudiant) -->
                        <div class="mb-3" id="password_field" style="display: none;">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" id="password" name="password" class="form-control">
                        </div>

                        <!-- Date limite (étudiant) -->
                        <div class="mb-3" id="deadline_field" style="display: none;">
                            <label for="deadline" class="form-label">Date limite</label>
                            <input type="datetime-local" id="deadline" name="deadline" class="form-control">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">Enregistrer</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <!-- Modal -->
    <div class="modal fade" id="modalDeadlineGlobal" tabindex="-1" aria-labelledby="modalDeadlineGlobalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDeadlineGlobalLabel">Fixer une date limite d'accès pour tous les étudiants</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="set_global_deadline.php" id="formDeadlineGlobal">
                        <label for="inputDeadlineGlobal">Date limite :</label>
                        <input type="datetime-local"
                            id="inputDeadlineGlobal"
                            name="deadline"
                            required
                            class="form-control"
                            value="<?= htmlspecialchars($formattedDeadline) ?>">
                        <br>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="submit" form="formDeadlineGlobal" class="btn btn-primary">Appliquer à tous</button>
                </div>
            </div>
        </div>
    </div>

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
        <!-- Statistics Cards -->
        <!-- Main Content Area -->
        <div class="main-content">
            <div class="content-content">
                <?php displayFlashMessage(); ?>
                <div class="stats-container">
                    <div class="stat-card pending">
                        <h3>Comptes en attente</h3>
                        <div class="count"><?php echo $statusCounts['pending']; ?></div>
                    </div>
                    <div class="stat-card approved">
                        <h3>Comptes approuvés</h3>
                        <div class="count"><?php echo $statusCounts['approved']; ?></div>
                    </div>
                    <div class="stat-card rejected">
                        <h3>Comptes rejetés</h3>
                        <div class="count"><?php echo $statusCounts['rejected']; ?></div>
                    </div>
                </div>

                <!-- Search and Filter Section -->
                <div class="search-filter-container">

                    <div class="search-box">
                        <form method="get" action="dashboard.php">
                            <input type="text" name="search" placeholder="Rechercher par nom, prénom, email ou ..."
                                value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="status" value="<?php echo $statusFilter; ?>">
                            <input type="hidden" name="role" value="<?php echo $roleFilter; ?>">
                        </form>
                    </div>

                    <div class="filter-group">
                        <span class="filter-label">Statut :</span>
                        <select class="filter-select" id="status-filter">
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                            <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approuvé</option>
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>Tous</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <span class="filter-label">Rôle :</span>
                        <select class="filter-select" id="role-filter">
                            <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>Tous les rôles</option>
                            <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                            <option value="admin_library" <?php echo $roleFilter === 'admin_library' ? 'selected' : ''; ?>>Administrateur Bibliothèque</option>
                            <option value="professor" <?php echo $roleFilter === 'professor' ? 'selected' : ''; ?>>Professeur</option>
                            <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Étudiant</option>
                        </select>
                    </div>

                    <button class="filter-btn" id="apply-filters">Filtrer</button>

                    <?php if (!empty($search) || $statusFilter !== 'pending' || $roleFilter !== 'all'): ?>
                        <a href="dashboard.php" class="clear-btn">Réinitialiser</a>
                    <?php endif; ?>
                </div>

                <!-- Account Table -->
                <div class="card">

                    <!-- Button trigger modal -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#modalDeadlineGlobal">
                            Fixer la date limite globale
                        </button>
                    </div>
                    <div class="card-body">

                        <?php if (!empty($accounts)): ?>
                            <table class="account-table">
                                <thead>
                                    <tr>
                                        <th>Nom</th>
                                        <th>Prénom</th>
                                        <th>Email</th>
                                        <th>Rôle</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($accounts as $account): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($account['last_name']) ?></td>
                                            <td><?= htmlspecialchars($account['first_name']) ?></td>
                                            <td><?= htmlspecialchars($account['email']) ?></td>
                                            <td>
                                                <span class="role-badge role-<?= $account['role'] ?>">
                                                    <?= match ($account['role']) {
                                                        'professor' => 'Professeur',
                                                        'student' => 'Étudiant',
                                                        'admin_library' => 'Administrateur Bibliothèque',
                                                        default => 'Administrateur',
                                                    } ?>
                                                </span>
                                            </td>

                                            <td>
                                                <span class="status-badge status-<?= $account['status'] ?>">
                                                    <?= match ($account['status']) {
                                                        'pending' => 'En attente',
                                                        'approved' => 'Approuvé',
                                                        'rejected' => 'Rejeté',
                                                        default => 'Inconnu',
                                                    } ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($account['status'] === 'pending'): ?>
                                                    <button class="action-btn btn-success accept-account" data-id="<?= $account['id'] ?>">Accepter</button>
                                                <?php endif; ?>
                                                <button class="action-btn btn-primary"
                                                    data-id="<?= $account['id'] ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#exampleModal"
                                                    onclick="fillModal(<?= htmlspecialchars(json_encode($account)) ?>)">
                                                    Modifier
                                                </button>
                                                <button class="action-btn btn-danger delete-account" data-id="<?= $account['id'] ?>">Supprimer</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="no-results">Aucun compte trouvé.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php require_once '../includes/footer.php'; ?>

    <!-- Edit Account Modal -->

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>

    <script>
        function fillModal(account) {
            document.getElementById('user_id').value = account.id;
            document.getElementById('first_name').value = account.first_name || '';
            document.getElementById('last_name').value = account.last_name || '';
            document.getElementById('email').value = account.email || '';
            document.getElementById('role').value = account.role;
            document.getElementById('status').value = account.status || 'active';

            // Spécifique professeur
            document.getElementById('grade').value = account.grade || '';

            // Spécifique étudiant
            document.getElementById('password').value = '';
            if (account.role === 'student' && account.deadline) {
                // Ensure deadline is formatted for datetime-local input
                let formatted = '';
                if (account.deadline.length > 0) {
                    const deadlineDate = new Date(account.deadline.replace(' ', 'T'));
                    formatted = deadlineDate.toISOString().slice(0, 16);
                }
                document.getElementById('deadline').value = formatted;
            } else {
                document.getElementById('deadline').value = '';
            }


            // Afficher ou cacher les champs selon le rôle
            toggleRoleFields(account.role);
        }

        function toggleRoleFields(role = null) {
            const selectedRole = role || document.getElementById('role').value;

            document.getElementById('grade_field').style.display = (selectedRole === 'professor') ? 'block' : 'none';
            document.getElementById('password_field').style.display = (selectedRole === 'student') ? 'block' : 'none';
            document.getElementById('deadline_field').style.display = (selectedRole === 'student') ? 'block' : 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Apply filters when button is clicked
            document.getElementById('apply-filters').addEventListener('click', function() {
                const status = document.getElementById('status-filter').value;
                const role = document.getElementById('role-filter').value;
                const search = document.querySelector('input[name="search"]').value;

                let url = 'dashboard.php?';
                if (status !== 'pending') url += `status=${status}&`;
                if (role !== 'all') url += `role=${role}&`;
                if (search) url += `search=${encodeURIComponent(search)}&`;

                // Remove trailing & or ? if no params
                if (url.endsWith('&')) url = url.slice(0, -1);
                if (url.endsWith('?')) url = url.slice(0, -1);

                window.location.href = url;
            });

            // Submit search form when typing (with delay)
            let searchTimer;
            document.querySelector('input[name="search"]').addEventListener('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    this.form.submit();
                }, 500);
            });

            // Role-specific fields
            const roleDisplay = document.getElementById('role-display');
            const matriculeField = document.querySelector('.student-field');

            // Edit account
            document.querySelectorAll('.edit-account').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');

                    // Set form action
                    document.getElementById('form-action').value = 'edit';
                    document.getElementById('account-id').value = id;
                    document.getElementById('modal-title').textContent = 'Modifier le compte';

                    // Get account data and populate form
                    fetch('get_account.php?id=' + id)
                        .then(response => response.json())
                        .then(data => {
                            document.getElementById('last-name').value = data.last_name;
                            document.getElementById('first-name').value = data.first_name;
                            document.getElementById('email').value = data.email;
                            roleDisplay.value = data.role === 'professor' ? 'Professeur' :
                                data.role === 'student' ? 'Étudiant' : 'Administrateur';
                            document.getElementById('role').value = data.role;

                            // Show/hide matricule field
                            if (data.role === 'student') {
                                matriculeField.style.display = 'block';
                                document.getElementById('matricule').value = data.matricule || '';
                            } else {
                                matriculeField.style.display = 'none';
                            }

                            // Clear password field
                            document.getElementById('password').value = '';

                            // Show modal
                            document.getElementById('account-modal').classList.add('active');
                        })
                        .catch(error => {
                            console.error('Erreur lors du chargement des données du compte :', error);
                            alert('Une erreur est survenue lors du chargement des données du compte.');
                        });
                });
            });

            // Accept account
            document.querySelectorAll('.accept-account').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    if (confirm('Êtes-vous sûr de vouloir accepter ce compte ?')) {
                        window.location.href = 'account_action.php?action=accept&id=' + id;
                    }
                });
            });

            // Reject account
            document.querySelectorAll('.reject-account').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    if (confirm('Êtes-vous sûr de vouloir rejeter ce compte ?')) {
                        window.location.href = 'account_action.php?action=reject&id=' + id;
                    }
                });
            });

            // Delete account
            document.querySelectorAll('.delete-account').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    if (confirm('Êtes-vous sûr de vouloir supprimer ce compte ? Cette action est irréversible.')) {
                        window.location.href = 'account_action.php?action=delete&id=' + id;
                    }
                });
            });

        });

        //MANAGE SOUS MENU ASSIGNEMENT MANAGEMENT 
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

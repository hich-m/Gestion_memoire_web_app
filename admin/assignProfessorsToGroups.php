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
    <!-- MODAL SUPRESSION -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" action="assignment_action.php" id="delete_role">
                <input type="hidden" name="action" value="delete_role">
                <input type="hidden" name="role_id" id="delete-role-id">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="confirmDeleteModalLabel">Confirmer la suppression</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>
                    <div class="modal-body">
                        Êtes-vous sûr de vouloir supprimer le rapporteur et l’examinateur pour ce groupe ?
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger">Oui, supprimer</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- MODAL MODIFICATION  -->
    <div class="modal fade" id="editAssignmentModal" tabindex="-1" aria-labelledby="editAssignmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="assignment_action.php" id="edit_assignment_form">
                <input type="hidden" name="action" value="edit_assignment">
                <input type="hidden" name="group_id" id="edit-group-id">

                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editAssignmentModalLabel">Modifier l'affectation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit-rapporteur-id" class="form-label">Rapporteur:</label>
                            <select name="rapporteur_id" id="edit-rapporteur-id" class="form-control" required>
                                <!-- Options remplies en JS -->
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="edit-examinateur-id" class="form-label">Examinateur:</label>
                            <select name="examinateur_id" id="edit-examinateur-id" class="form-control" required>
                                <!-- Options remplies en JS -->
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    </div>
                </div>
            </form>
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
        <!-- Main Content Area -->
        <div class="main-content">
            <div class="content-content">
                <?php displayFlashMessage(); ?>
                <!-- Group-Professor Assignment Tab -->
                <div class="card">
                    <div class="card-header">Affecter l'examinateur et le rapporteur aux groupes</div>
                    <div class="card-body">
                        <?php
                        // FETCH GROUPS WITH STUDENTS AND SUPERVISOR
                        $groupsStmt = $pdo->query("SELECT 
    g.id AS group_id,
    p.id AS project,
    g.code AS group_code,
    
    GROUP_CONCAT(DISTINCT CONCAT(u_student.first_name, ' ', u_student.last_name) SEPARATOR ', ') AS etudiants,
    
    CONCAT(u_sup.first_name, ' ', u_sup.last_name) AS encadrant,
    prof_sup.id AS encadrant_id

FROM groupe g
JOIN project p ON g.id_project = p.id

LEFT JOIN student s ON s.id_groupe = g.id
LEFT JOIN `user` u_student ON s.user_id = u_student.id

LEFT JOIN role r_sup ON r_sup.id_project = p.id AND r_sup.role = 'supervisor'
LEFT JOIN professor prof_sup ON prof_sup.id = r_sup.professor_id
LEFT JOIN `user` u_sup ON u_sup.id = prof_sup.user_id
JOIN account a ON a.id_user = u_sup.id
-- Exclure les projets qui ont déjà un rapporteur ou un examinateur
WHERE p.id NOT IN (
    SELECT id_project FROM role WHERE role IN ('rapporteur', 'examiner')
)
AND a.status='approved'
GROUP BY g.id, g.code, prof_sup.id, u_sup.first_name, u_sup.last_name, p.id
ORDER BY g.code");

                        $groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

                        // Liste des professeurs
                        $professorsStmt = $pdo->query("SELECT p.id AS teacher_id,
                                                           CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
                                                       FROM professor p
                                                       JOIN user u ON u.id = p.user_id
                                                       JOIN account a ON a.id_user = u.id
                                                       WHERE a.status='approved'
                                                       ");

                        $allProfessors = $professorsStmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <form action="assignment_action.php" method="post">
                            <input type="hidden" name="action" value="assign_professor_to_group">
                            <div class="form-group">
                                <label for="group_id">Groupe:</label>
                                <select id="group_id" name="group_id" class="form-control" onchange="filterProfessors()" required>
                                    <option value="">Sélectionner un groupe</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option
                                            value="<?= $group['group_id'] ?>"
                                            data-encadrant="<?= $group['encadrant_id'] ?>">
                                            <?= $group['group_code'] ?> - <?= $group['etudiants'] ?> - (Encadrant: <?= $group['encadrant'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Rapporteur -->
                            <div class="form-group">
                                <label for="rapporteur_id">Rapporteur:</label>
                                <select name="rapporteur_id" id="rapporteur_id" class="form-control" required>
                                    <option value="">Sélectionner un rapporteur</option>
                                    <?php foreach ($allProfessors as $prof): ?>
                                        <option value="<?= $prof['teacher_id'] ?>"><?= $prof['teacher_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Examiner -->
                            <div class="form-group">
                                <label for="examinateur_id">Examinateur:</label>
                                <select name="examinateur_id" id="examinateur_id" class="form-control" required>
                                    <option value="">Sélectionner un examinateur</option>
                                    <?php foreach ($allProfessors as $prof): ?>
                                        <option value="<?= $prof['teacher_id'] ?>"><?= $prof['teacher_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">Affecter</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Affectations existantes</div>
                <div class="card-body">
                    <?php
                    // PAGINATION 

                    $itemsPerPage = 10;
                    $pageActuelle = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                    $offset = ($pageActuelle - 1) * $itemsPerPage;

                    $countStmt = $pdo->query(" SELECT COUNT(DISTINCT g.id) AS total
                                               FROM groupe g
                                               JOIN project p ON g.id_project = p.id
                                               LEFT JOIN role r_rap ON r_rap.id_project = p.id AND r_rap.role = 'rapporteur'
                                               LEFT JOIN role r_exam ON r_exam.id_project = p.id AND r_exam.role = 'examiner'
                                               WHERE r_rap.id IS NOT NULL OR r_exam.id IS NOT NULL
                    ");
                    $totalItems = $countStmt->fetchColumn();
                    $totalPages = ceil($totalItems / $itemsPerPage);
                    ?>


                    <?php

                    $stmt = $pdo->prepare("SELECT 
                                        r_rap.id AS role_id,
                                                 g.id AS group_id,
                                                 g.code AS groupe_code,
                                                 p.title AS projet_title,
                                                 
                                                 GROUP_CONCAT(DISTINCT CONCAT(u_student.first_name, ' ', u_student.last_name) SEPARATOR ', ') AS etudiants,
                                                 
                                                 CONCAT(u_sup.first_name, ' ', u_sup.last_name) AS encadrant,
                                                 prof_sup.id AS encadrant_id,
                                                 
                                                 CONCAT(u_rap.first_name, ' ', u_rap.last_name) AS rapporteur,
                                                 prof_rap.id AS rapporteur_id,
                                                 r_rap.id AS rapporteur_role_id,
                                             
                                                 CONCAT(u_exam.first_name, ' ', u_exam.last_name) AS examinateur,
                                                 prof_exam.id AS examinateur_id,
                                                 r_exam.id AS examinateur_role_id
                                             
                                             FROM groupe g
                                             JOIN project p ON g.id_project = p.id
                                             
                                             -- Étudiants
                                             LEFT JOIN student s ON s.id_groupe = g.id
                                             LEFT JOIN `user` u_student ON s.user_id = u_student.id
                                             
                                             -- Encadrant
                                             LEFT JOIN role r_sup ON r_sup.id_project = p.id AND r_sup.role = 'supervisor'
                                             LEFT JOIN professor prof_sup ON prof_sup.id = r_sup.professor_id
                                             LEFT JOIN `user` u_sup ON u_sup.id = prof_sup.user_id
                                             
                                             -- Rapporteur
                                             LEFT JOIN role r_rap ON r_rap.id_project = p.id AND r_rap.role = 'rapporteur'
                                             LEFT JOIN professor prof_rap ON prof_rap.id = r_rap.professor_id
                                             LEFT JOIN `user` u_rap ON u_rap.id = prof_rap.user_id
                                             
                                             -- Examinateur
                                             LEFT JOIN role r_exam ON r_exam.id_project = p.id AND r_exam.role = 'examiner'
                                             LEFT JOIN professor prof_exam ON prof_exam.id = r_exam.professor_id
                                             LEFT JOIN `user` u_exam ON u_exam.id = prof_exam.user_id
                                             
                                             -- Condition (s'assurer qu’il y ait au moins rapporteur ou examinateur)
                                             WHERE r_rap.id IS NOT NULL OR r_exam.id IS NOT NULL
                                             
                                             GROUP BY g.id, g.code, p.title, prof_sup.id, prof_rap.id, prof_exam.id
                                             ORDER BY g.code
                                             LIMIT :limit OFFSET :offset
                                             ");
                    $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
                    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                    $stmt->execute();
                    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (count($assignments) === 0): ?>

                        <p>Aucune affectation n'a été faite.</p>
                    <?php else: ?>

                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Groupe</th>
                                    <th>Projet</th>
                                    <th>Étudiants</th>
                                    <th>Encadrant</th>
                                    <th>Rapporteur</th>
                                    <th>Examinateur</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $assignment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assignment['groupe_code']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['projet_title']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['etudiants']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['encadrant'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['rapporteur'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['examinateur'] ?? '-'); ?></td>
                                        <td>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editAssignmentModal"
                                                data-group-id="<?= htmlspecialchars($assignment['group_id']) ?>"
                                                data-encadrant-id="<?= htmlspecialchars($assignment['encadrant_id']) ?>"
                                                data-rapporteur-id="<?= htmlspecialchars($assignment['rapporteur_id']) ?>"
                                                data-examinateur-id="<?= htmlspecialchars($assignment['examinateur_id']) ?>">
                                                Modifier
                                            </button>


                                            <button
                                                type="button"
                                                class="btn btn-danger btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#confirmDeleteModal"
                                                data-role-id="<?php echo $assignment['role_id']; ?>">
                                                Supprimer
                                            </button>



                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
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

                </div>
            </div>
        </div>
    </div>


    <?php require_once '../includes/footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/alertify.min.js"></script>

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


        //FOR SELECT PROFESSOR NOT MATCH EXAMINER RAPPORTEUR ET ENCADRANT 
        function filterProfessors() {
            const groupSelect = document.getElementById('group_id');
            const rapporteurSelect = document.getElementById('rapporteur_id');
            const examinateurSelect = document.getElementById('examinateur_id');

            const encadrantId = groupSelect.options[groupSelect.selectedIndex].dataset.encadrant;
            console.log("Encadrant ID:", encadrantId);

            // Réactiver toutes les options
            for (const option of rapporteurSelect.options) option.disabled = false;
            for (const option of examinateurSelect.options) option.disabled = false;

            // Désactiver l'encadrant dans les deux listes
            for (const option of rapporteurSelect.options) {
                if (option.value.trim() === String(encadrantId).trim()) {
                    option.disabled = true;
                }
            }
            for (const option of examinateurSelect.options) {
                if (option.value.trim() === String(encadrantId).trim()) {
                    option.disabled = true;
                }
            }

            // Désactive le rapporteur sélectionné dans la liste des examinateurs
            const selectedRapporteurId = rapporteurSelect.value;
            for (const option of examinateurSelect.options) {
                if (option.value === selectedRapporteurId) {
                    option.disabled = true;
                }
            }

            // Désactive l'examinateur sélectionné dans la liste des rapporteurs
            const selectedExaminateurId = examinateurSelect.value;
            for (const option of rapporteurSelect.options) {
                if (option.value === selectedExaminateurId) {
                    option.disabled = true;
                }
            }
        }

        // Réexécute filterProfessors si on change rapporteur ou examinateur
        document.getElementById('rapporteur_id').addEventListener('change', filterProfessors);
        document.getElementById('examinateur_id').addEventListener('change', filterProfessors);


        //for modal suppression
        const confirmDeleteModal = document.getElementById('confirmDeleteModal');
        confirmDeleteModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const roleId = button.getAttribute('data-role-id');
            const inputRoleId = confirmDeleteModal.querySelector('#delete-role-id');
            inputRoleId.value = roleId;
        });

        const editModal = document.getElementById('editAssignmentModal');
        const editRapporteurSelect = document.getElementById('edit-rapporteur-id');
        const editExaminateurSelect = document.getElementById('edit-examinateur-id');
        const editGroupIdInput = document.getElementById('edit-group-id');

        const allProfessors = <?= json_encode($allProfessors) ?>; // tableau PHP avec tous les profs

        function populateSelectOptions(select, disabledValues = []) {
            select.innerHTML = ''; // vide options
            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = 'Sélectionner';
            select.appendChild(placeholderOption);

            allProfessors.forEach(prof => {
                const option = document.createElement('option');
                option.value = prof.teacher_id;
                option.textContent = prof.teacher_name;
                if (disabledValues.includes(String(prof.teacher_id))) {
                    option.disabled = true;
                }
                select.appendChild(option);
            });
        }

        function filterEditProfessors() {
            const encadrantId = editModal.dataset.encadrantId || '';
            const selectedRapporteurId = editRapporteurSelect.value;
            const selectedExaminateurId = editExaminateurSelect.value;

            // Reactiver toutes les options d'abord
            for (const option of editRapporteurSelect.options) option.disabled = false;
            for (const option of editExaminateurSelect.options) option.disabled = false;

            // Désactiver encadrant dans les deux selects
            for (const option of editRapporteurSelect.options) {
                if (option.value === encadrantId) option.disabled = true;
            }
            for (const option of editExaminateurSelect.options) {
                if (option.value === encadrantId) option.disabled = true;
            }

            // Désactiver rapporteur sélectionné dans examinateur
            for (const option of editExaminateurSelect.options) {
                if (option.value === selectedRapporteurId && selectedRapporteurId !== '') option.disabled = true;
            }

            // Désactiver examinateur sélectionné dans rapporteur
            for (const option of editRapporteurSelect.options) {
                if (option.value === selectedExaminateurId && selectedExaminateurId !== '') option.disabled = true;
            }
        }

        // Quand le modal s’ouvre
        editModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;

            const groupId = button.getAttribute('data-group-id');
            const encadrantId = button.getAttribute('data-encadrant-id');
            const currentRapporteurId = button.getAttribute('data-rapporteur-id') || '';
            const currentExaminateurId = button.getAttribute('data-examinateur-id') || '';

            // Stocker encadrantId dans dataset modal
            editModal.dataset.encadrantId = encadrantId;

            // Remplir input caché group_id
            editGroupIdInput.value = groupId;

            // Remplir selects avec tous les professeurs et désactiver encadrant
            populateSelectOptions(editRapporteurSelect, [encadrantId]);
            populateSelectOptions(editExaminateurSelect, [encadrantId]);

            // Sélectionner rapporteur et examinateur actuels
            editRapporteurSelect.value = currentRapporteurId;
            editExaminateurSelect.value = currentExaminateurId;

            // Appliquer les règles de désactivation
            filterEditProfessors();
        });

        // Lorsqu’on change rapporteur ou examinateur dans le modal
        editRapporteurSelect.addEventListener('change', filterEditProfessors);
        editExaminateurSelect.addEventListener('change', filterEditProfessors);
    </script>
</body>

</html>

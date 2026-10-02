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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des affectations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../admin_library/css/adminstyle.css">
    <style>
        .button-tabs {
            display: flex;
            gap: 10px;
            /* espace entre les boutons */
            justify-content: center;
            flex-wrap: wrap;
            /* permet de passer à la ligne si l’écran est trop petit */
            margin-bottom: 20px;
        }

        .button-tabs button {
            flex: none;
            /* pour que les boutons gardent leur taille naturelle */
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        button {
            background-color: #3498db;
            color: #fff;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s, transform 0.2s;
        }

        button:hover {
            background-color: #2980b9;
            transform: scale(1.05);
        }

        button:active {
            background-color: #1c5980;
            transform: scale(0.95);
        }

        .project-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
        }

        .project-header h3 {
            margin: 0;
            font-size: 1.5em;
            color: #2c3e50;
        }

        .project-header p {
            margin: 5px 0;
            color: #7f8c8d;
        }

        .group-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            background-color: #ecf0f1;
            border-radius: 5px;
        }

        .prof-assignment {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
        }

        #etudiants-select {
            height: 200px;
            /* Affiche plusieurs lignes visibles */
            font-size: 16px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background-color: #f9f9f9;
        }

        .menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .menu ul li {
            position: relative;
        }

        .menu a {
            display: block;
            padding: 10px 15px;
            text-decoration: none;
            color: #fff;
        }

        .submenu {
            display: none;
            padding-left: 20px;
            background-color: #333;
        }

        .submenu li a {
            padding: 8px 15px;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="../assets/img/logo_without backg.png" alt="Logo" width="70" height="50">
                <h3 class="app-name">University Project Management</h3>
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

            <nav class="menu">
                <ul>
                    <li>
                        <a href="#" class="toggle-submenu">Manage Assignment</a>
                        <ul class="submenu">
                            <li><a href="#">Rapporteur</a></li>
                            <li><a href="#">Examinateur</a></li>
                            <li><a href="#">Encadrant</a></li>
                        </ul>
                    </li>
                </ul>
            </nav>
        </aside>


        <!-- Main Content Area -->
        <div class="main-content">
            <div class="content-content">
                <div class="header">
                    <h1>Gestion des affectations</h1>
                </div>

                <div class="card">
                    <div class="card-header">Actions</div>
                    <div class="card-body">
                        <div class="button-tabs">
                            <button data-tab="project-list" class="tab-btn active">Liste des projets</button>
                            <button data-tab="group-assignment" class="tab-btn">Gérer les groupes</button>
                            <button data-tab="group-project-assignment" class="tab-btn">Affecter groupes à projets</button>
                            <button data-tab="group-professor-assignment" class="tab-btn">Affecter professeurs aux groupes</button>
                        </div>
                    </div>
                </div>

                <!-- Project List Tab -->
                <div class="card tab-content active" id="project-list">
                    <div class="card-header">Liste des projets</div>
                    <div class="card-body">
                        <h3>Projets supervisés</h3>

                        <?php if (count($projets) === 0): ?>
                            <p>Aucun projet supervisé trouvé.</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Titre du projet</th>
                                        <th>Description</th>
                                        <th>Nom du superviseur</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projets as $p): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($p['project_title']) ?></td>
                                            <td><?= htmlspecialchars($p['description']) ?></td>
                                            <td><?= htmlspecialchars($p['supervisor_name']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>


                <!-- Group Assignment Tab -->
                <div class="card tab-content" id="group-assignment">
                    <div class="card-header">Affecter à un groupe</div>
                    <div class="card-body">
                        <?php
                        // 1) On récupère la liste des groupes
                        $stmt = $pdo->prepare("SELECT id, code FROM groupe ORDER BY code");
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
                                        <input type="text" name="code" class="form-control" placeholder="Code Du Nouveau Groupe" required>
                                        <button class="btn btn-success" type="submit">Enregistrer</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <form action="assignment_action.php" method="post">
                            <input type="hidden" name="action" value="assign_group">

                            <label>Groupe :</label>
                            <select name="groupe_id" required>
                                <option value="">Sélectionner un groupe</option>
                                <?php foreach ($groupes as $g): ?>
                                    <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['code']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <label>Étudiants :</label>
                            <select name="etudiants[]" multiple required>
                                <?php foreach ($etudiants as $e): ?>
                                    <option value="<?= $e['student_id'] ?>">
                                        <?= htmlspecialchars($e['student_last_name'] . ' ' . $e['student_first_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit">Affecter</button>
                        </form>


                        <h3 style="margin-top:30px;">Étudiants par groupe</h3>
                        <?php
                        $q = $pdo->prepare("
                            SELECT s.id AS student_id, u.first_name, u.last_name, s.matricule, g.code AS groupe
                            FROM student s
                            JOIN user u    ON u.id = s.user_id
                            JOIN groupe g  ON s.id_groupe = g.id
                            ORDER BY g.code, u.last_name, u.first_name
                        ");

                        $q->execute();
                        $liste = $q->fetchAll(PDO::FETCH_ASSOC);

                        if (count($liste) === 0): ?>
                            <p>Aucun étudiant n'est assigné à un groupe.</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Matricule</th>
                                        <th>Group</th>
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

                    </div>
                </div>

                <!-- Group-Project Assignment Tab -->
                <div class="card tab-content" id="group-project-assignment">
                    <div class="card-header">Affecter des groupes à des projets</div>
                    <div class="card-body">
                        <form action="assignment_action.php" method="post">
                            <input type="hidden" name="action" value="assign_group_to_project">

                            <?php
                            // Récupérer les projets non affectés à un groupe
                            $sql = "SELECT p.id, p.title FROM project p LEFT JOIN groupe g ON g.id_project = p.id WHERE g.id_project IS NULL";
                            $stmt = $pdo->query($sql);
                            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>

                            <div class="form-group">
                                <label for="project-select">Projet:</label>
                                <select id="project-select" name="project_id" class="form-control" required>
                                    <option value="">Sélectionner un projet</option>
                                    <?php
                                    if ($projects) {
                                        // Boucle pour afficher les projets dans le <select>
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
                                <label for="group-select">Groupe:</label>
                                <select id="group-select" name="group_id" class="form-control" required>
                                    <option value="">Sélectionner un groupe</option>
                                    <?php
                                    // Requête pour récupérer les groupes non affectés à un projet avec leurs étudiants
                                    $sql = "SELECT 
                                g.id AS groupe_id, 
                                g.code AS groupe_code, 
                                u.first_name AS student_first_name, 
                                u.last_name AS student_last_name 
                            FROM groupe g 
                            LEFT JOIN project p ON g.id_project = p.id 
                            JOIN student s ON g.id = s.id_groupe 
                            JOIN user u ON s.user_id = u.id 
                            WHERE g.id_project IS NULL";

                                    $stmt = $pdo->query($sql);
                                    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    // Vérifier si des groupes sont trouvés
                                    if ($groups) {
                                        // Boucle pour afficher les groupes dans le <select>
                                        foreach ($groups as $group) {
                                            echo "<option value='{$group['groupe_id']}'>
                                    {$group['groupe_code']} - {$group['student_first_name']} {$group['student_last_name']}
                                  </option>";
                                        }
                                    } else {
                                        echo "<option value=''>Aucun groupe disponible</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-success">Affecter</button>
                        </form>


                        <!-- TABLE POUR AFFICHES LES GROUPES AFFECTER A DES PROJETS  -->
                        <h3 style="margin-top:30px;">Étudiants par groupe et project</h3>
                        <?php
                        $sql =  $pdo->prepare("SELECT g.id AS group_id,
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
                                   ORDER BY p.title");

                        $sql->execute();
                        $liste = $sql->fetchAll(PDO::FETCH_ASSOC);

                        if (count($liste) === 0): ?>
                            <p>Aucun étudiant ou Groupe n'est assigné à un project.</p>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Titre du Projet</th>
                                        <th>Code du Groupe</th>
                                        <th>Étudiants</th>
                                        <th>Enseignant Encadrant</th>
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
                                            <td><a href="assignment_action.php?action=remove_project_group&id=<?= $row['group_id'] ?>"
                                                    class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Êtes-vous sûr de vouloir retirer cet étudiant du groupe ?')">
                                                    Retirer
                                                </a></td>
                                        </tr>
                                    <?php endforeach; ?>

                                </tbody>
                            </table>
                        <?php endif; ?>

                    </div>
                </div>




                <!-- Group-Professor Assignment Tab -->
                <div class="card tab-content" id="group-professor-assignment">
                    <div class="card-header">Affecter Examinateur Et Rapporteur aux groupes</div>
                    <div class="card-body">

                        <?php
                        // RECUPERER LES GROUPES AVEC ETUDIANTS ET ENCADRANT
                        $groupsStmt = $pdo->query("SELECT 
                                                g.id AS group_id,
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
                                            GROUP BY g.id, g.code, prof_sup.id, u_sup.first_name, u_sup.last_name
                                            ORDER BY g.code
                                            ");

                        $groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);

                        // Liste des professeurs
                        $professorsStmt = $pdo->query("SELECT p.id AS teacher_id,
                                                           CONCAT(u.first_name, ' ', u.last_name) AS teacher_name
                                                       FROM professor p
                                                       JOIN user u ON u.id = p.user_id;
                                                       ");

                        $allProfessors = $professorsStmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <form action="assignment_action.php" method="post">
                            <input type="hidden" name="action" value="assign_examinat_raport_to_group_">
                            <div class="form-group">
                                <label for="group_id">Groupe :</label>
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
                                <label for="rapporteur_id">Rapporteur :</label>
                                <select name="rapporteur_id" id="rapporteur_id" class="form-control" required>
                                    <option value="">Sélectionner un Rapporteur</option>
                                    <?php foreach ($allProfessors as $prof): ?>
                                        <option value="<?= $prof['teacher_id'] ?>"><?= $prof['teacher_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Examinateur -->
                            <div class="form-group">
                                <label for="examinateur_id">Examinateur :</label>
                                <select name="examinateur_id" id="examinateur_id" class="form-control" required>
                                    <option value="">Sélectionner un Examinateur</option>
                                    <?php foreach ($allProfessors as $prof): ?>
                                        <option value="<?= $prof['teacher_id'] ?>"><?= $prof['teacher_name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">Affecter</button>
                        </form>


                        <h3 style="margin-top: 30px;">Affectations existantes</h3>
                        <?php
                        $stmt = $pdo->prepare("SELECT 
                                g.code AS groupe_code,
                                p.title AS projet_title,
                                GROUP_CONCAT(DISTINCT CONCAT(u_student.first_name, ' ', u_student.last_name) SEPARATOR ', ') AS etudiants,
                                
                                CONCAT(u_sup.first_name, ' ', u_sup.last_name) AS encadrant,
                                IF(u_rap.id IS NOT NULL, CONCAT(u_rap.first_name, ' ', u_rap.last_name), '') AS rapporteur,
                                IF(u_exam.id IS NOT NULL, CONCAT(u_exam.first_name, ' ', u_exam.last_name), '') AS examinateur
                            
                            FROM groupe g
                            JOIN project p ON g.id_project = p.id
                            
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
                            
                            WHERE prof_rap.id IS NOT NULL OR prof_exam.id IS NOT NULL
                            
                            GROUP BY g.id, g.code, p.title, prof_sup.id, prof_rap.id, prof_exam.id
                            ORDER BY g.code");

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
                                        <th>Etudiants</th>
                                        <th>Encadrant</th>
                                        <th>Rapporteur</th>
                                        <th>Examinateur</th>
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
                                        </tr>
                                    <?php endforeach; ?>

                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Autres onglets cachés (si besoin, à compléter) -->
                <div class="card tab-content" id="group-project-assignment">
                    <div class="card-header">Affecter groupes à projets</div>
                    <div class="card-body">Contenu à implémenter.</div>
                </div>
                <div class="card tab-content" id="group-professor-assignment">
                    <div class="card-header">Affecter professeurs aux groupes</div>
                    <div class="card-body">Contenu à implémenter.</div>
                </div>
            </div>
        </div>
    </div>
    <footer>
        <small>&copy; 2025 University Project Documentation. All rights reserved.</small>
    </footer>
    <!-- JS: Gestion des onglets -->
    <script>
        //MANAGE ASSIGNEMETS LIST MENU
        document.addEventListener('DOMContentLoaded', function() {
            const toggles = document.querySelectorAll('.toggle-submenu');

            toggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const submenu = this.nextElementSibling;
                    submenu.style.display = submenu.style.display === 'block' ? 'none' : 'block';
                });
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
    </script>

</body>

</html>
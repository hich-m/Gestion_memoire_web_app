<?php
// welcome.php
require_once 'config/db_config.php';
require_once 'includes/functions.php';

initSession();

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$specialty = isset($_GET['specialty']) ? $_GET['specialty'] : '';

// Define available specialties (must match exactly with database values)
$availableSpecialties = [
    "Ingenierie du Logiciel et Traitement de L'information",
    "Systemes d'Information Repartis",
    "Technologie de l'Information"
];

// Base SQL query pour afficher les mémoires avec des informations 
$sql = "SELECT 
    t.id AS thesis_id,
    p.title AS project_title,
    t.submitted_at,
    YEAR(t.submitted_at) AS year_submitted,
    t.status,
    t.file_path AS fichier_memoire,
    s.specialite AS specialite,

    -- Noms des étudiants
    GROUP_CONCAT(DISTINCT CONCAT(student_user.first_name, ' ', student_user.last_name) SEPARATOR ', ') AS student_names,

    -- Noms des professeurs encadrants (supervisor)
    GROUP_CONCAT(DISTINCT 
        CASE WHEN r.role = 'supervisor' 
             THEN CONCAT(prof_user.first_name, ' ', prof_user.last_name) 
        END
        SEPARATOR ', '
    ) AS supervisor,

    -- Noms des examinateurs
    GROUP_CONCAT(DISTINCT 
        CASE WHEN r.role = 'examiner' 
             THEN CONCAT(prof_user.first_name, ' ', prof_user.last_name) 
        END
        SEPARATOR ', '
    ) AS examiner

FROM thesis t
JOIN pv_thesis pv ON t.id_pv = pv.id
JOIN project p ON p.id = pv.id_project
JOIN groupe g ON g.id_project = p.id
JOIN student s ON s.id_groupe = g.id
JOIN `user` student_user ON student_user.id = s.user_id

-- Les professeurs sont toujours en LEFT JOIN
LEFT JOIN role r ON r.id_project = p.id
LEFT JOIN professor prof ON prof.id = r.professor_id
LEFT JOIN `user` prof_user ON prof_user.id = prof.user_id

WHERE t.status = 'approved'";

// Add search condition to WHERE clause
if (!empty($search)) {
    $searchPattern = '%' . $search . '%';
    $sql .= " AND (p.title LIKE ? 
              OR student_user.first_name LIKE ? 
              OR student_user.last_name LIKE ?
              OR prof_user.first_name LIKE ? 
              OR prof_user.last_name LIKE ?)";
}

// Add year filter
if (!empty($year)) {
    $sql .= " AND YEAR(t.submitted_at) = ?";
}

// Add specialty filter
if (!empty($specialty) && in_array($specialty, $availableSpecialties)) {
    $sql .= " AND s.specialite = ?";
}

// Group by clause
$sql .= " GROUP BY t.id";

// Having clause for filtering after GROUP BY on aggregated columns
if (!empty($search)) {
    $sql .= " HAVING (
                project_title LIKE ? 
                OR student_names LIKE ? 
                OR supervisor LIKE ? 
                OR examiner LIKE ?
              )";
}

// Final order by
$sql .= " ORDER BY t.submitted_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters in order
    $paramIndex = 1;
    
    if (!empty($search)) {
        // WHERE clause parameters
        $stmt->bindValue($paramIndex++, '%' . $search . '%');
        $stmt->bindValue($paramIndex++, '%' . $search . '%');
        $stmt->bindValue($paramIndex++, '%' . $search . '%');
        $stmt->bindValue($paramIndex++, '%' . $search . '%');
        $stmt->bindValue($paramIndex++, '%' . $search . '%');
    }
    
    if (!empty($year)) {
        $stmt->bindValue($paramIndex++, $year);
    }
    
    if (!empty($specialty) && in_array($specialty, $availableSpecialties)) {
        $stmt->bindValue($paramIndex++, $specialty);
    }
    
    // HAVING clause parameters
    if (!empty($search)) {
        $stmt->bindValue($paramIndex++, '%' . $search . '%');
        $stmt->bindValue($paramIndex++, '%' . $search . '%');
        $stmt->bindValue($paramIndex++, '%' . $search . '%');
        $stmt->bindValue($paramIndex++, '%' . $search . '%');
    }
    
    $stmt->execute();
    $submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erreur lors de l'exécution de la requête : " . $e->getMessage();
    exit;
}

// Récupérer les années distinctes pour le filtre
try {
    $yearsStmt = $pdo->query("SELECT DISTINCT YEAR(submitted_at) as year FROM thesis WHERE status = 'approved' ORDER BY year DESC");
    $years = $yearsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Erreur lors de la récupération des années : " . $e->getMessage();
    exit;
}


// Translations (English)
$title = "Système de Gestion de Projets Universitaires";
$searchTitle = "Recherche des mémoires archivés";
$searchPlaceholder = "Rechercher par nom de professeur ou titre de projet...";
$allYears = "Toutes les années";
$allSpecialties = "Toutes les spécialités";
$noResults = "Aucune soumission approuvée ne correspond à vos critères.";
$supervisorLabel = "Encadrant:";
$documentLabel = "Document:";
$approvedByLabel = "Approuvé par:";
$downloadLabel = "Télécharger";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css"> 
</head>

<body>
    <div id="root">
        <div class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand" href="index.php">
                    <img src="assets/img/logo_without backg.png" style="background: #2c3e50;" alt="Logo" width="90" height="70">
                    Système de Gestion des Projets Universitaires
                </a>

                <div class="d-flex">
                    <a href="login.php" class="btn btn-login me-2">Login</a>
                    <a href="register.php" class="btn btn-register">Register</a>
                </div>
            </div>
        </div>
        <main class="main-content">
            <section class="search-section">
                <h1 class="search-title"><?= htmlspecialchars($searchTitle) ?></h1>
                <form class="search-form" method="get" action="">
                    <div class="search-row">
                        <input
                            type="text"
                            class="search-input"
                            name="search"
                            placeholder="<?= htmlspecialchars($searchPlaceholder) ?>"
                            value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn search-btn">Search</button>
                    </div>

                    <div class="filter-section">
                        <select class="filter-select" name="year">
                            <option value=""><?= htmlspecialchars($allYears) ?></option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?= $y['year'] ?>" <?= $year == $y['year'] ? 'selected' : '' ?>>
                                    <?= $y['year'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select class="filter-select" name="specialty">
                            <option value=""><?= htmlspecialchars($allSpecialties) ?></option>
                            <?php foreach ($availableSpecialties as $spec): ?>
                                <option value="<?= htmlspecialchars($spec) ?>" <?= $specialty === $spec ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($spec) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" class="btn filter-btn">Filter</button>
                    </div>
                </form>
            </section>

            <section class="submissions-grid">
                <?php if (empty($submissions)): ?>
                    <div class="no-results">
                        <p><?= htmlspecialchars($noResults) ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($submissions as $submission): ?>
                        <article class="submission-card">
                            <div class="submission-image">
                                <i class="fas fa-file-pdf document-icon"></i>
                            </div>
                            <div class="submission-details">
                                <h3 class="submission-title"><?= htmlspecialchars($submission['project_title']) ?></h3>
                                <div class="submission-meta">
                                    <strong>Students:</strong>
                                    <span class="submission-author">
                                        <?= htmlspecialchars($submission['student_names']) ?>
                                    </span>
                                </div>
                                <div class="submission-meta">
                                    <strong>Speciality:</strong>
                                    <?= htmlspecialchars($submission['specialite']) ?>
                                </div>
                                <div class="submission-meta">
                                    <strong><?= $supervisorLabel ?></strong>
                                    <?= htmlspecialchars(
                                        ($submission['supervisor'] ?? 'Unknown')
                                    ) ?>
                                </div>
                                <div class="submission-meta">
                                    <strong><?= $approvedByLabel ?></strong>
                                    <?= htmlspecialchars(
                                        ($submission['examiner'] ?? 'Unknown')
                                    ) ?>
                                </div>
                                <div class="submission-meta">
                                    <strong>Year:</strong>
                                    <?= date('Y', strtotime($submission['submitted_at'])) ?>
                                </div>
                                <div class="submission-meta">
                                    <a href="/gestion_memoire_web_app/<?= htmlspecialchars($submission['fichier_memoire']) ?>" class="btn btn-primary" target="_blank">
                                        <i class="fas fa-download"></i> <?= $downloadLabel ?>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </main>

        <?php require_once 'includes/footer.php'; ?>

    </div>
</body>

</html>

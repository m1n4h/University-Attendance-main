<?php
// admin/student-upload.php
// Handles BULK CSV upload of student records with Subject Assignment support.

// -----------------------------------------------------------
// 1. Core Configuration & Security Includes
// -----------------------------------------------------------
require_once '../config/config.php';
require_once '../config/dbcon.php';
require_once '../includes/auth_check.php';

// Enforce Admin role access
check_access('admin'); 

// Initialize variables
$error = '';
$success = '';

// -----------------------------------------------------------
// 2. Data Fetching (Classes and Subjects for Reference)
// -----------------------------------------------------------
$classes = [];
$subjects = [];
try {
    $classes = $pdo->query("SELECT classId, className, yearLevel, semester FROM tblclass ORDER BY yearLevel, className")->fetchAll();
    $subjects = $pdo->query("SELECT subjectId, subjectCode, subjectName FROM tblsubject ORDER BY subjectCode")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching data: " . $e->getMessage());
    $error = "Could not load reference data.";
}

// Create subject code to ID mapping
$subjectCodeMap = [];
foreach ($subjects as $sub) {
    $subjectCodeMap[strtoupper($sub['subjectCode'])] = $sub['subjectId'];
}

// -----------------------------------------------------------
// 3. BULK UPLOAD Handler (POST)
// -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_upload') {
    
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security validation failed. Please refresh and try again.";
    } elseif (empty($_FILES['student_file']['name'])) {
        $error = "Please select a CSV file to upload.";
    } else {
        $fileName = $_FILES['student_file']['tmp_name'];
        $fileExtension = pathinfo($_FILES['student_file']['name'], PATHINFO_EXTENSION);
        
        if (strtolower($fileExtension) !== 'csv') {
            $error = "Invalid file type. Only CSV files are allowed.";
        } else {
            $uploadedCount = 0;
            $skippedCount = 0;
            $subjectAssignCount = 0;
            $pdo->beginTransaction();

            try {
                if (($handle = fopen($fileName, "r")) !== FALSE) {
                    fgetcsv($handle); // Skip header row
                    
                    $sqlStudent = "INSERT IGNORE INTO tblstudent (admissionNo, firstName, lastName, otherName, email, password, classId) 
                                   VALUES (:adm, :fn, :ln, :oname, :email, :pwd, :cid)";
                    $stmtStudent = $pdo->prepare($sqlStudent);
                    
                    $sqlSubject = "INSERT IGNORE INTO tblstudent_subject (studentId, subjectId) VALUES (:sid, :subid)";
                    $stmtSubject = $pdo->prepare($sqlSubject);
                    
                    $sqlGetStudent = "SELECT studentId FROM tblstudent WHERE admissionNo = :adm";
                    $stmtGetStudent = $pdo->prepare($sqlGetStudent);
                    
                    while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
                        // Minimum 5 columns: AdmNo, FirstName, LastName, Email, ClassId
                        // Optional: OtherName (col 4), SubjectCodes (last column)
                        if (count($data) < 5) {
                            $skippedCount++;
                            continue; 
                        }
                        
                        // Parse CSV columns
                        $adm = trim($data[0]);
                        $fn = trim($data[1]);
                        $ln = trim($data[2]);
                        
                        // Detect format based on column count
                        $numCols = count($data);
                        
                        if ($numCols == 5) {
                            // Format: AdmNo, FirstName, LastName, Email, ClassId
                            $oname = null;
                            $email = filter_var(trim($data[3]), FILTER_VALIDATE_EMAIL);
                            $cid = filter_var(trim($data[4]), FILTER_VALIDATE_INT);
                            $subjectCodes = '';
                        } elseif ($numCols == 6) {
                            // Format: AdmNo, FirstName, LastName, OtherName, Email, ClassId
                            // OR: AdmNo, FirstName, LastName, Email, ClassId, SubjectCodes
                            if (filter_var(trim($data[3]), FILTER_VALIDATE_EMAIL)) {
                                // data[3] is email
                                $oname = null;
                                $email = filter_var(trim($data[3]), FILTER_VALIDATE_EMAIL);
                                $cid = filter_var(trim($data[4]), FILTER_VALIDATE_INT);
                                $subjectCodes = trim($data[5]);
                            } else {
                                // data[3] is otherName
                                $oname = trim($data[3]) ?: null;
                                $email = filter_var(trim($data[4]), FILTER_VALIDATE_EMAIL);
                                $cid = filter_var(trim($data[5]), FILTER_VALIDATE_INT);
                                $subjectCodes = '';
                            }
                        } else {
                            // Format: AdmNo, FirstName, LastName, OtherName, Email, ClassId, SubjectCodes
                            $oname = trim($data[3]) ?: null;
                            $email = filter_var(trim($data[4]), FILTER_VALIDATE_EMAIL);
                            $cid = filter_var(trim($data[5]), FILTER_VALIDATE_INT);
                            $subjectCodes = isset($data[6]) ? trim($data[6]) : '';
                        }
                        
                        // Password = Admission Number (hashed)
                        $hashedPassword = password_hash($adm, PASSWORD_BCRYPT);
                        
                        // Validate required fields
                        if (empty($adm) || empty($fn) || empty($ln) || !$cid) {
                            $skippedCount++;
                            continue;
                        }

                        // Insert student
                        $result = $stmtStudent->execute([
                            ':adm' => $adm, ':fn' => $fn, ':ln' => $ln,
                            ':oname' => $oname, ':email' => $email,
                            ':pwd' => $hashedPassword, ':cid' => $cid
                        ]);

                        if ($result && $stmtStudent->rowCount() > 0) {
                            $uploadedCount++;
                            $studentId = $pdo->lastInsertId();
                        } else {
                            // Student might already exist, get their ID
                            $stmtGetStudent->execute([':adm' => $adm]);
                            $existingStudent = $stmtGetStudent->fetch();
                            $studentId = $existingStudent ? $existingStudent['studentId'] : null;
                        }
                        
                        // Assign subjects if provided and student exists
                        if ($studentId && !empty($subjectCodes)) {
                            $codes = array_map('trim', explode(';', $subjectCodes));
                            foreach ($codes as $code) {
                                $code = strtoupper($code);
                                if (isset($subjectCodeMap[$code])) {
                                    $stmtSubject->execute([':sid' => $studentId, ':subid' => $subjectCodeMap[$code]]);
                                    if ($stmtSubject->rowCount() > 0) {
                                        $subjectAssignCount++;
                                    }
                                }
                            }
                        }
                    }
                    fclose($handle);
                } else {
                    throw new Exception("Could not open the uploaded file.");
                }

                $pdo->commit();
                $success = "Upload complete! <strong>{$uploadedCount}</strong> students added, <strong>{$subjectAssignCount}</strong> subject assignments, <strong>{$skippedCount}</strong> skipped.";

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Upload failed: " . htmlspecialchars($e->getMessage());
                error_log("Bulk Upload Error: " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>Bulk Student Upload | <?php echo SITE_NAME; ?></title>
</head>
<body>
    <div id="wrapper">
        <?php include_once '../includes/sidebar.php'; ?>
        <div id="content">
            <?php include_once '../includes/navbar.php'; ?>

            <div class="container-fluid pt-4">
                <h1 class="mb-4 text-info"><i class="fas fa-file-upload"></i> Bulk Student Upload</h1>
                <p class="lead">Import multiple students with subject assignments via CSV file.</p>
                
                <hr>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-times-circle me-2"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card shadow mb-4">
                            <div class="card-header bg-info text-white"><i class="fas fa-file-csv me-2"></i> CSV File Import</div>
                            <div class="card-body">
                                
                                <form method="POST" action="" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <input type="hidden" name="action" value="bulk_upload">

                                    <div class="mb-3">
                                        <label for="student_file" class="form-label">Select CSV File <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control" id="student_file" name="student_file" accept=".csv" required>
                                    </div>
                                    
                                    <div class="alert alert-warning">
                                        <p class="fw-bold mb-2"><i class="fas fa-info-circle me-1"></i> CSV Format (Row 1 = Header):</p>
                                        <table class="table table-sm table-bordered mb-2 small">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Col 1</th><th>Col 2</th><th>Col 3</th><th>Col 4</th><th>Col 5</th><th>Col 6</th><th>Col 7</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>AdmNo*</td><td>FirstName*</td><td>LastName*</td><td>OtherName</td><td>Email</td><td>ClassID*</td><td>SubjectCodes</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <ul class="small mb-0">
                                            <li><strong>SubjectCodes:</strong> Separate multiple codes with semicolon (;) e.g., <code>IT101;NW201;DB301</code></li>
                                            <li><strong>Password:</strong> Auto-set to Admission Number</li>
                                        </ul>
                                    </div>

                                    <div class="d-grid mt-4">
                                        <button type="submit" class="btn btn-info btn-lg"><i class="fas fa-upload me-2"></i> Start Import</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Classes Reference -->
                        <div class="card shadow mb-3">
                            <div class="card-header bg-secondary text-white py-2">
                                <i class="fas fa-building me-1"></i> Classes (ClassID)
                            </div>
                            <div class="card-body p-0" style="max-height: 200px; overflow-y: auto;">
                                <table class="table table-sm table-striped mb-0">
                                    <thead class="table-dark sticky-top">
                                        <tr><th>ID</th><th>Class Name</th><th>Year</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classes as $c): ?>
                                        <tr>
                                            <td><strong><?php echo $c['classId']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($c['className']); ?></td>
                                            <td>Y<?php echo $c['yearLevel']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Subjects Reference -->
                        <div class="card shadow mb-3">
                            <div class="card-header bg-primary text-white py-2">
                                <i class="fas fa-book me-1"></i> Subjects (Codes)
                            </div>
                            <div class="card-body p-0" style="max-height: 200px; overflow-y: auto;">
                                <table class="table table-sm table-striped mb-0">
                                    <thead class="table-dark sticky-top">
                                        <tr><th>Code</th><th>Subject Name</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subjects as $s): ?>
                                        <tr>
                                            <td><code><?php echo htmlspecialchars($s['subjectCode']); ?></code></td>
                                            <td><?php echo htmlspecialchars($s['subjectName']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Sample CSV -->
                        <div class="card shadow">
                            <div class="card-header bg-success text-white py-2">
                                <i class="fas fa-file-download me-1"></i> Sample CSV
                            </div>
                            <div class="card-body">
                                <pre class="bg-light p-2 small mb-2" style="font-size: 0.7rem;">AdmNo,FirstName,LastName,OtherName,Email,ClassID,SubjectCodes
STU001,John,Doe,,john@mail.com,1,IT101;NW201
STU002,Jane,Smith,Mary,jane@mail.com,1,IT101
STU003,Bob,Wilson,,bob@mail.com,2,DB301</pre>
                                <a href="data:text/csv;charset=utf-8,AdmNo,FirstName,LastName,OtherName,Email,ClassID,SubjectCodes%0ASTU001,John,Doe,,john@mail.com,1,IT101;NW201%0ASTU002,Jane,Smith,Mary,jane@mail.com,1,IT101" 
                                   download="sample_students.csv" class="btn btn-sm btn-outline-success w-100">
                                    <i class="fas fa-download me-1"></i> Download Sample
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="<?php echo BASE_URL; ?>admin/manage-students.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Students
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>

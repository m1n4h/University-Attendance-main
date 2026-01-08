<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config/config.php';
require_once '../config/dbcon.php';
require_once '../includes/auth_check.php';
check_access('admin');

$error = '';
$success = '';

// Fetch data with error handling
$classes = [];
$subjects = [];
$students = [];

try {
    $classes = $pdo->query("SELECT classId, className, yearLevel, semester FROM tblclass ORDER BY yearLevel")->fetchAll();
    $subjects = $pdo->query("SELECT subjectId, subjectCode, subjectName FROM tblsubject ORDER BY subjectCode")->fetchAll();
    $students = $pdo->query("SELECT s.*, c.className, c.yearLevel FROM tblstudent s JOIN tblclass c ON s.classId = c.classId ORDER BY s.lastName")->fetchAll();
    
    // Get all student-subject assignments for edit modals
    $studentSubjects = [];
    $ssResult = $pdo->query("SELECT studentId, subjectId FROM tblstudent_subject")->fetchAll();
    foreach ($ssResult as $ss) {
        $studentSubjects[$ss['studentId']][] = $ss['subjectId'];
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Security error";
    } else {
        $action = $_POST['action'];
        
        if ($action === 'add_student') {
            try {
                $sql = "INSERT INTO tblstudent (admissionNo, firstName, lastName, email, password, classId) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_POST['admissionNo'],
                    $_POST['firstName'], 
                    $_POST['lastName'],
                    $_POST['email'],
                    password_hash($_POST['password'], PASSWORD_BCRYPT),
                    $_POST['classId']
                ]);
                $studentId = $pdo->lastInsertId();
                
                // Assign subjects if selected
                if (!empty($_POST['subjects'])) {
                    $subjectStmt = $pdo->prepare("INSERT INTO tblstudent_subject (studentId, subjectId) VALUES (?, ?)");
                    foreach ($_POST['subjects'] as $subjectId) {
                        $subjectStmt->execute([$studentId, $subjectId]);
                    }
                }
                
                $success = "Student added!";
                header("Location: manage-students.php?success=1");
                exit;
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
        
        if ($action === 'edit_student') {
            try {
                $sql = "UPDATE tblstudent SET admissionNo=?, firstName=?, lastName=?, email=?, classId=? WHERE studentId=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_POST['admissionNo'],
                    $_POST['firstName'], 
                    $_POST['lastName'],
                    $_POST['email'],
                    $_POST['classId'],
                    $_POST['studentId']
                ]);
                
                // Update subjects - delete old and insert new
                $pdo->prepare("DELETE FROM tblstudent_subject WHERE studentId = ?")->execute([$_POST['studentId']]);
                if (!empty($_POST['subjects'])) {
                    $subjectStmt = $pdo->prepare("INSERT INTO tblstudent_subject (studentId, subjectId) VALUES (?, ?)");
                    foreach ($_POST['subjects'] as $subjectId) {
                        $subjectStmt->execute([$_POST['studentId'], $subjectId]);
                    }
                }
                
                header("Location: manage-students.php?updated=1");
                exit;
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
        
        if ($action === 'delete_student') {
            try {
                $pdo->prepare("DELETE FROM tblstudent WHERE studentId = ?")->execute([$_POST['studentId']]);
                $success = "Student deleted!";
                header("Location: manage-students.php?deleted=1");
                exit;
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['success'])) $success = "Student added successfully!";
if (isset($_GET['deleted'])) $success = "Student deleted!";
if (isset($_GET['updated'])) $success = "Student updated!";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once '../includes/header.php'; ?>
    <title>Manage Students</title>
</head>
<body>
<div id="wrapper">
    <?php include_once '../includes/sidebar.php'; ?>
    <div id="content">
        <?php include_once '../includes/navbar.php'; ?>
        <div class="container-fluid pt-4">
            <h1 class="mb-4 text-primary"><i class="fas fa-user-graduate"></i> Manage Students</h1>
            
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="d-flex gap-2 mb-3">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fas fa-plus-circle me-1"></i> Add New Student
                </button>
                <a href="<?php echo BASE_URL; ?>admin/student-upload.php" class="btn btn-info">
                    <i class="fas fa-file-upload me-1"></i> Bulk Upload (CSV)
                </a>
            </div>

            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-list me-2"></i> Registered Students
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Adm No</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Class</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $i=1; foreach($students as $s): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($s['admissionNo']); ?></td>
                            <td><?php echo htmlspecialchars($s['firstName'].' '.$s['lastName']); ?></td>
                            <td><?php echo htmlspecialchars($s['email']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo $s['className'].' Y'.$s['yearLevel']; ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-warning me-1" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $s['studentId']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $s['studentId']; ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if(empty($students)): ?>
                    <div class="alert alert-info text-center">No students registered yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5>Register New Student</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="add_student">
                    
                    <div class="mb-3">
                        <label>Admission No *</label>
                        <input type="text" class="form-control" name="admissionNo" required>
                    </div>
                    <div class="mb-3">
                        <label>First Name *</label>
                        <input type="text" class="form-control" name="firstName" required>
                    </div>
                    <div class="mb-3">
                        <label>Last Name *</label>
                        <input type="text" class="form-control" name="lastName" required>
                    </div>
                    <div class="mb-3">
                        <label>Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label>Class *</label>
                        <select class="form-select" name="classId" required>
                            <option value="">Select</option>
                            <?php foreach($classes as $c): ?>
                            <option value="<?php echo $c['classId']; ?>"><?php echo $c['className'].' Y'.$c['yearLevel']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Password *</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    
                    <hr>
                    <label class="fw-bold"><i class="fas fa-book me-1"></i> Assign Subjects</label>
                    <div class="border p-2 bg-light" style="max-height:150px;overflow-y:auto;">
                        <?php foreach($subjects as $sub): ?>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="subjects[]" value="<?php echo $sub['subjectId']; ?>">
                            <label class="form-check-label"><?php echo $sub['subjectCode'].' - '.$sub['subjectName']; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit & Delete Modals for each student -->
<?php foreach($students as $s): ?>
<!-- Edit Modal -->
<div class="modal fade" id="editModal<?php echo $s['studentId']; ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit: <?php echo $s['admissionNo']; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="edit_student">
                    <input type="hidden" name="studentId" value="<?php echo $s['studentId']; ?>">
                    
                    <div class="mb-3">
                        <label>Admission No *</label>
                        <input type="text" class="form-control" name="admissionNo" value="<?php echo htmlspecialchars($s['admissionNo']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>First Name *</label>
                        <input type="text" class="form-control" name="firstName" value="<?php echo htmlspecialchars($s['firstName']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Last Name *</label>
                        <input type="text" class="form-control" name="lastName" value="<?php echo htmlspecialchars($s['lastName']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Email *</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($s['email']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Class *</label>
                        <select class="form-select" name="classId" required>
                            <?php foreach($classes as $c): ?>
                            <option value="<?php echo $c['classId']; ?>" <?php echo ($c['classId']==$s['classId'])?'selected':''; ?>><?php echo $c['className'].' Y'.$c['yearLevel']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <hr>
                    <label class="fw-bold"><i class="fas fa-book me-1"></i> Assign Subjects</label>
                    <div class="border p-2 bg-light" style="max-height:150px;overflow-y:auto;">
                        <?php 
                        $assignedSubs = $studentSubjects[$s['studentId']] ?? [];
                        foreach($subjects as $sub): 
                        $checked = in_array($sub['subjectId'], $assignedSubs) ? 'checked' : '';
                        ?>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="subjects[]" value="<?php echo $sub['subjectId']; ?>" <?php echo $checked; ?>>
                            <label class="form-check-label"><?php echo $sub['subjectCode'].' - '.$sub['subjectName']; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save me-1"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal<?php echo $s['studentId']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Delete Student</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="delete_student">
                    <input type="hidden" name="studentId" value="<?php echo $s['studentId']; ?>">
                    <p>Are you sure you want to delete <strong><?php echo $s['firstName'].' '.$s['lastName']; ?></strong> (<?php echo $s['admissionNo']; ?>)?</p>
                    <p class="text-danger small"><i class="fas fa-exclamation-triangle me-1"></i>This will also delete all attendance records.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i> Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php include_once '../includes/footer.php'; ?>
</body>
</html>

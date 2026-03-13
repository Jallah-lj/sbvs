<?php
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: http://localhost/sbvs/config/controllers/views/login.php");
    exit;
}

require_once '../../database.php';
require_once 'models/Branch.php';

$db     = (new Database())->getConnection();
$branch = new Branch($db);

$role         = $_SESSION['role'] ?? '';
$isSuperAdmin = ($role === 'Super Admin');

$success = '';
$error   = '';

// ── Only Super Admin may access branch management ─────────────────────────────
if (!$isSuperAdmin) {
    header("Location: dashboard.php");
    exit;
}

// Handle Create & Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $data = [
        'name'    => trim($_POST['name']    ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'phone'   => trim($_POST['phone']   ?? ''),
        'email'   => trim($_POST['email']   ?? ''),
        'status'  => $_POST['status']       ?? 'Active'
    ];

    if ($_POST['action'] === 'create') {
        if ($branch->create($data)) {
            $success = "Branch created successfully!";
        } else {
            $error = "Failed to create branch.";
        }
    } elseif ($_POST['action'] === 'update' && !empty($_POST['id'])) {
        if ($branch->update((int)$_POST['id'], $data)) {
            $success = "Branch updated successfully!";
        } else {
            $error = "Failed to update branch.";
        }
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete'])) {
    if ($branch->delete((int)$_GET['delete'])) {
        $success = "Branch deleted successfully!";
    } else {
        $error = "Failed to delete branch. It may have linked records.";
    }
}

// Fetch all branches
$stmt        = $branch->getAll();
$allBranches = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
// ── Page identity for partials ───────────────────────────────────────────────
$pageTitle  = 'Branches';
$activePage = 'branches.php';
?>
<?php require_once __DIR__ . '/partials/head.php'; ?>
<body>
<?php require_once __DIR__ . '/partials/sidebar.php'; ?>

<div class="sbvs-layout">
<main class="sbvs-main">

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <h2 class="mb-0 fw-bold" style="letter-spacing: -0.02em;"><i class="bi bi-building me-2 text-primary"></i>Branches</h2>
                <p class="text-muted small mb-0 mt-1">
                    <?= $isSuperAdmin ? 'Create and manage all vocational training branches.' : 'View registered branches. Contact the Super Admin to make changes.' ?>
                </p>
            </div>
            <?php if ($isSuperAdmin): ?>
            <button class="btn btn-primary shadow-sm rounded-pill px-4 d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#addBranchModal">
                <i class="bi bi-plus-circle-fill me-2"></i> Add New Branch
            </button>
            <?php else: ?>
            <span class="badge bg-secondary badge-custom fs-6 px-3 py-2"><i class="bi bi-eye-fill me-2"></i>View Only Mode</span>
            <?php endif; ?>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (!$isSuperAdmin): ?>
        <div class="alert alert-info border-0 shadow-sm d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <span>You are viewing branches in <strong>read-only</strong> mode. Only the Super Admin can add, edit, or delete branches.</span>
        </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-list-ul me-1"></i>Registered Branches</h6>
            </div>
            <div class="card-body p-0 p-md-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Branch Name</th>
                                <th>Address</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Status</th>
                                <?php if ($isSuperAdmin): ?><th class="text-end pe-4">Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allBranches)): ?>
                            <tr><td colspan="<?= $isSuperAdmin ? 7 : 6 ?>" class="text-center text-muted py-5">
                                <i class="bi bi-buildings opacity-50 display-4 d-block mb-3"></i>No branches found.
                            </td></tr>
                            <?php else: ?>
                            <?php foreach ($allBranches as $i => $row): ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px;">
                                            <i class="bi bi-building text-primary fs-5"></i>
                                        </div>
                                        <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($row['name']) ?></span>
                                    </div>
                                </td>
                                <td><span class="text-muted"><i class="bi bi-geo-alt me-1 text-secondary"></i><?= htmlspecialchars($row['address']) ?></span></td>
                                <td><?= htmlspecialchars($row['phone']) ?></td>
                                <td><a href="mailto:<?= htmlspecialchars($row['email']) ?>" class="text-decoration-none"><?= htmlspecialchars($row['email']) ?></a></td>
                                <td>
                                    <span class="badge badge-custom badge-<?= $row['status'] === 'Active' ? 'success' : 'secondary' ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <?php if ($isSuperAdmin): ?>
                                <td>
                                    <div class="d-flex gap-2 justify-content-end">
                                        <button class="btn btn-action btn-edit"
                                            title="Edit"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editBranchModal"
                                            data-id="<?= $row['id'] ?>"
                                            data-name="<?= htmlspecialchars($row['name']) ?>"
                                            data-address="<?= htmlspecialchars($row['address']) ?>"
                                            data-phone="<?= htmlspecialchars($row['phone']) ?>"
                                            data-email="<?= htmlspecialchars($row['email']) ?>"
                                            data-status="<?= htmlspecialchars($row['status']) ?>">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <a href="?delete=<?= $row['id'] ?>"
                                           class="btn btn-action btn-delete"
                                           title="Delete"
                                           onclick="return confirm('Delete this branch? This cannot be undone.')">
                                            <i class="bi bi-trash-fill"></i>
                                        </a>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<?php if ($isSuperAdmin): ?>
<!-- Add Branch Modal -->
<!-- Add Branch Modal -->
<div class="modal fade" id="addBranchModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <input type="hidden" name="action" value="create">
            <div class="modal-header modal-header-accent border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle-fill me-2 opacity-75"></i>Add New Branch</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body position-relative pt-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Main Campus" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Address <span class="text-danger">*</span></label>
                        <input type="text" name="address" class="form-control" placeholder="e.g. Monrovia, Liberia" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g. 0770000000" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="e.g. branch@sbvs.com" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Status</label>
                        <select name="status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Branch</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Branch Modal -->
<div class="modal fade" id="editBranchModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header modal-header-warning border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square me-2 opacity-75"></i>Edit Branch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body position-relative pt-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Branch Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Address <span class="text-danger">*</span></label>
                        <input type="text" name="address" id="edit_address" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" id="edit_phone" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i>Update Branch</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('editBranchModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('edit_id').value      = btn.dataset.id;
    document.getElementById('edit_name').value    = btn.dataset.name;
    document.getElementById('edit_address').value = btn.dataset.address;
    document.getElementById('edit_phone').value   = btn.dataset.phone;
    document.getElementById('edit_email').value   = btn.dataset.email;
    document.getElementById('edit_status').value  = btn.dataset.status;
});
</script>
<?php else: ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>
</body>
</html>

<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('users.manage');

$pageTitle    = 'Users & Roles';
$pageSubtitle = 'Manage system users and their access';
$breadcrumbs  = ['Users & Roles' => null];
$pageScripts  = [ASSETS_URL . '/js/users.js'];

$pdo = db();
$roles = $pdo->query('SELECT id, name FROM roles ORDER BY id ASC')->fetchAll();

$pageActions = '<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openUser()">'
             . '<i class="bi bi-plus-lg me-1"></i> Add User</button>';

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="gims-card">
            <div class="gims-card-head">
                <div>
                    <h5 class="gims-card-title">System Users</h5>
                    <small class="text-muted" id="userCount">Loading…</small>
                </div>
                <div class="d-flex gap-2">
                    <input type="text" id="userSearch" class="form-control form-control-sm" placeholder="Search…" style="width:200px">
                    <select id="userRole" class="form-select form-select-sm" style="width:150px">
                        <option value="">All roles</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="gims-card-body p-0">
                <div class="table-responsive">
                    <table class="table gims-table mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Phone</th>
                                <th>Last Login</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="userBody">
                            <tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="gims-card">
            <div class="gims-card-head">
                <h5 class="gims-card-title">Roles</h5>
            </div>
            <div class="gims-card-body p-0">
                <div id="rolesList"></div>
            </div>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="userForm">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="userId" value="">
                <div class="mb-3">
                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="userName" class="form-control" required maxlength="150">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="userEmail" class="form-control" required maxlength="190">
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" id="userPhone" class="form-control" maxlength="30">
                </div>
                <div class="mb-3">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select name="role_id" id="userRoleInput" class="form-select" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password <span class="text-danger" id="pwdReq">*</span></label>
                    <input type="password" name="password" id="userPassword" class="form-control" minlength="8" autocomplete="new-password">
                    <small class="text-muted" id="pwdHint">Minimum 8 characters.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirm" id="userPasswordC" class="form-control" minlength="8" autocomplete="new-password">
                </div>
                <div class="mb-0">
                    <label class="form-label">Status</label>
                    <select name="status" id="userStatusInput" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Permission Modal -->
<div class="modal fade" id="permModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Permissions — <span id="permRoleName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="permRoleId" value="">
                <div id="permGroups"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="savePerms"><i class="bi bi-check2-circle me-1"></i> Save Permissions</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
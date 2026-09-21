<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('employee.manage');

$pageTitle    = 'Departments';
$pageSubtitle = 'Organisational units';
$breadcrumbs  = ['Employees' => BASE_URL . '/employees/index.php', 'Departments' => null];
$pageScripts  = [ASSETS_URL . '/js/hr.js'];

$pageActions = '<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#deptModal" onclick="openDepartment()">'
             . '<i class="bi bi-plus-lg me-1"></i> Add Department</button>';

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">All Departments</h5>
            <small class="text-muted" id="deptCount">Loading…</small>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th class="text-end">Employees</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="deptBody">
                    <tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="deptModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" id="deptForm">
            <div class="modal-header"><h5 class="modal-title" id="deptModalTitle">Add Department</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id" id="deptId" value="">
                <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="name" id="deptName" class="form-control" required maxlength="120"></div>
                <div class="mb-3"><label class="form-label">Code <span class="text-danger">*</span></label><input type="text" name="code" id="deptCode" class="form-control" required maxlength="30" placeholder="PROD"></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" id="deptDesc" class="form-control" rows="2" maxlength="255"></textarea></div>
                <div class="mb-0"><label class="form-label">Status</label>
                    <select name="status" id="deptStatusInput" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
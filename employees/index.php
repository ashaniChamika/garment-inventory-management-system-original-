<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('employee.manage');

$pageTitle    = 'Employees';
$pageSubtitle = 'Manage your workforce';
$breadcrumbs  = ['Employees' => null, 'Employees' => null];
$pageScripts  = [ASSETS_URL . '/js/hr.js'];

$pdo = db();
$total = (int)$pdo->query('SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL')->fetchColumn();
$active= (int)$pdo->query('SELECT COUNT(*) FROM employees WHERE deleted_at IS NULL AND status = "active"')->fetchColumn();
$departments = $pdo->query('SELECT id, name FROM departments WHERE status = "active" ORDER BY name')->fetchAll();

$pageActions = '<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#empModal" onclick="openEmployee()">'
             . '<i class="bi bi-plus-lg me-1"></i> Add Employee</button>';

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="gims-kpi gims-kpi-primary">
            <div class="gims-kpi-icon"><i class="bi bi-people"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Total Employees</span>
                <strong class="gims-kpi-value"><?= number_format($total) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="gims-kpi gims-kpi-success">
            <div class="gims-kpi-icon"><i class="bi bi-person-check"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Active</span>
                <strong class="gims-kpi-value"><?= number_format($active) ?></strong>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="gims-kpi gims-kpi-info">
            <div class="gims-kpi-icon"><i class="bi bi-diagram-3"></i></div>
            <div class="gims-kpi-body">
                <span class="gims-kpi-label">Departments</span>
                <strong class="gims-kpi-value"><?= count($departments) ?></strong>
            </div>
        </div>
    </div>
</div>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" id="empSearch" class="form-control" placeholder="Name, employee #, NIC…">
            </div>
            <div class="col-md-3">
                <label class="form-label">Department</label>
                <select id="empDept" class="form-select">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select id="empStatus" class="form-select">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="resigned">Resigned</option>
                    <option value="terminated">Terminated</option>
                </select>
            </div>
            <div class="col-md-3 text-end">
                <button class="btn btn-outline-secondary btn-sm" id="empReset"><i class="bi bi-arrow-clockwise"></i> Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Employee Directory</h5>
            <small class="text-muted" id="empCount">Loading…</small>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Employee #</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Phone</th>
                        <th>Joined</th>
                        <th class="text-end">Salary</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="empBody">
                    <tr><td colspan="9" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="empPagerWrap" style="display:none">
            <small class="text-muted" id="empPageInfo"></small>
            <nav><ul class="pagination pagination-sm mb-0" id="empPager"></ul></nav>
        </div>
    </div>
</div>

<div class="modal fade" id="empModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" id="empForm" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title" id="empModalTitle">Add Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="empId" value="">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Employee # <span class="text-danger">*</span></label>
                        <input type="text" name="employee_no" id="empNo" class="form-control" required maxlength="40">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" id="empName" class="form-control" required maxlength="150">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">NIC</label>
                        <input type="text" name="nic" id="empNic" class="form-control" maxlength="30">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gender</label>
                        <select name="gender" id="empGender" class="form-select">
                            <option value="">— Select —</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" id="empDob" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="empPhone" class="form-control" maxlength="30">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="empEmail" class="form-control" maxlength="190">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" id="empAddress" class="form-control" maxlength="400">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Department</label>
                        <select name="department_id" id="empDeptInput" class="form-select">
                            <option value="">— Select —</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" id="empPosition" class="form-control" maxlength="120">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Join Date</label>
                        <input type="date" name="join_date" id="empJoin" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Salary</label>
                        <input type="number" step="0.01" min="0" name="salary" id="empSalary" class="form-control" value="0">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" id="empStatusInput" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="resigned">Resigned</option>
                            <option value="terminated">Terminated</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Emergency Contact</label>
                        <input type="text" name="emergency_contact" id="empEmergency" class="form-control" maxlength="120">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Profile Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script>
window.EMPLOYEE_CAN_MANAGE = <?= hasPermission('employee.manage') ? 'true' : 'false' ?>;
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
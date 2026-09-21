<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('attendance.manage');

$pageTitle    = 'Attendance Reports';
$pageSubtitle = 'Summary by employee over a date range';
$breadcrumbs  = ['Employees' => BASE_URL . '/employees/index.php', 'Attendance' => BASE_URL . '/attendance/index.php', 'Reports' => null];
$pageScripts  = [ASSETS_URL . '/js/hr.js'];

$pdo = db();
$departments = $pdo->query('SELECT id, name FROM departments WHERE status = "active" ORDER BY name')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" id="repFrom" class="form-control" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" id="repTo" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Department</label>
                <select id="repDept" class="form-select">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" id="runReport"><i class="bi bi-bar-chart me-1"></i> Generate Report</button>
            </div>
        </div>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Attendance Summary</h5>
            <small class="text-muted" id="repCount">Run report to see data</small>
        </div>
        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th class="text-end">Present</th>
                        <th class="text-end">Late</th>
                        <th class="text-end">Half Days</th>
                        <th class="text-end">Absent</th>
                        <th class="text-end">Leave</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody id="repBody">
                    <tr><td colspan="8"><div class="gims-empty"><i class="bi bi-bar-chart"></i><p>Click "Generate Report" to load data.</p></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
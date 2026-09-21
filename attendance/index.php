<?php
require_once __DIR__ . '/../config/config.php';
requirePermission('attendance.manage');

$pageTitle    = 'Attendance';
$pageSubtitle = 'Daily attendance tracking';
$breadcrumbs  = ['Employees' => BASE_URL . '/employees/index.php', 'Attendance' => null];
$pageScripts  = [ASSETS_URL . '/js/hr.js'];

$pdo = db();
$departments = $pdo->query('SELECT id, name FROM departments WHERE status = "active" ORDER BY name')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-2">
        <div class="gims-kpi gims-kpi-success"><div class="gims-kpi-icon"><i class="bi bi-person-check"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Present</span><strong class="gims-kpi-value" id="kpiPresent">—</strong></div></div>
    </div>
    <div class="col-md-2">
        <div class="gims-kpi gims-kpi-danger"><div class="gims-kpi-icon"><i class="bi bi-person-x"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Absent</span><strong class="gims-kpi-value" id="kpiAbsent">—</strong></div></div>
    </div>
    <div class="col-md-2">
        <div class="gims-kpi gims-kpi-warning"><div class="gims-kpi-icon"><i class="bi bi-clock-history"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Late</span><strong class="gims-kpi-value" id="kpiLate">—</strong></div></div>
    </div>
    <div class="col-md-2">
        <div class="gims-kpi gims-kpi-info"><div class="gims-kpi-icon"><i class="bi bi-airplane"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">On Leave</span><strong class="gims-kpi-value" id="kpiLeave">—</strong></div></div>
    </div>
    <div class="col-md-2">
        <div class="gims-kpi gims-kpi-secondary"><div class="gims-kpi-icon"><i class="bi bi-hourglass"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Not Marked</span><strong class="gims-kpi-value" id="kpiUnmarked">—</strong></div></div>
    </div>
    <div class="col-md-2">
        <div class="gims-kpi gims-kpi-dark"><div class="gims-kpi-icon"><i class="bi bi-check2-all"></i></div><div class="gims-kpi-body"><span class="gims-kpi-label">Marked</span><strong class="gims-kpi-value" id="kpiMarked">—</strong></div></div>
    </div>
</div>

<div class="gims-card mb-3">
    <div class="gims-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" id="attDate" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Department</label>
                <select id="attDept" class="form-select">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select id="attStatus" class="form-select">
                    <option value="">All</option>
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="late">Late</option>
                    <option value="half_day">Half Day</option>
                    <option value="leave">Leave</option>
                </select>
            </div>
            <div class="col-md-4 text-end">
                <button class="btn btn-success btn-sm" id="saveAllBtn"><i class="bi bi-check2-all me-1"></i> Save All</button>
                <button class="btn btn-outline-secondary btn-sm" id="attReset"><i class="bi bi-arrow-clockwise"></i> Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="gims-card">
    <div class="gims-card-head">
        <div>
            <h5 class="gims-card-title">Attendance Sheet</h5>
            <small class="text-muted" id="attCount">Loading…</small>
        </div>
    </div>
    <div class="gims-card-body p-0">
        <div class="table-responsive">
            <table class="table gims-table mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody id="attBody">
                    <tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center p-3 border-top" id="attPagerWrap" style="display:none">
            <small class="text-muted" id="attPageInfo"></small>
            <nav><ul class="pagination pagination-sm mb-0" id="attPager"></ul></nav>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<?php
declare(strict_types=1);
if (!defined('GIMS_APP')) { exit('Direct access denied'); }
?>
        </main>

        <footer class="gims-footer">
            <div class="gims-footer-left">
                &copy; <?= date('Y') ?> <strong><?= e(setting('company_name', APP_NAME)) ?></strong>
                &middot; v<?= e(APP_VERSION) ?>
            </div>
            <div class="gims-footer-right">
                <span class="text-muted small">Made with <i class="bi bi-heart-fill text-danger"></i> Ashani Chamika</span>
            </div>
        </footer>

    </div><!-- /.gims-main -->
</div><!-- /.gims-app -->

<!-- Global loading overlay -->
<div class="gims-loader" id="gimsLoader">
    <div class="gims-loader-box">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 mb-0 small text-muted">Loading…</p>
    </div>
</div>

<!-- Global toast container -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index:1080"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= ASSETS_URL ?>/js/app.js"></script>
<?php if (!empty($pageScripts)): foreach ((array)$pageScripts as $js): ?>
    <script src="<?= e($js) ?>"></script>
<?php endforeach; endif; ?>

<script>
/* auto-dismiss flash alerts */
document.querySelectorAll('.gims-flash-stack .alert').forEach((el, i) => {
    setTimeout(() => {
        try { new bootstrap.Alert(el).close(); } catch(e) {}
    }, 4500 + (i * 400));
});
</script>

</body>
</html>
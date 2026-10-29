</main>

<?php if (empty($isAdmin)): ?>
<footer class="ks-footer mt-5">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-5">
                <h5 class="footer-brand"><i class="bi bi-flower1 me-2"></i>KalaSetu</h5>
                <p class="mb-2">A bridge between India's traditional skills, the artisans who keep them alive, and the people who want to learn them.</p>
                <p class="small footer-note mb-0">All artisan profiles, contacts and products shown are fictional demo data created for a college DBMS project.</p>
            </div>
            <div class="col-6 col-lg-3">
                <h6>Explore</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="<?= e($root) ?>skills.php">Traditional Skills</a></li>
                    <li><a href="<?= e($root) ?>artisans.php">Artisans</a></li>
                    <li><a href="<?= e($root) ?>products.php">Products</a></li>
                    <li><a href="<?= e($root) ?>training.php">Training</a></li>
                    <li><a href="<?= e($root) ?>register.php">Register as Artisan</a></li>
                    <li><a href="<?= e($root) ?>artisan/login.php">Artisan Login</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-4">
                <h6>Insights</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="<?= e($root) ?>at_risk.php">Skills at Risk</a></li>
                    <li><a href="<?= e($root) ?>popular_artisans.php">Most Discovered Artisans</a></li>
                    <li><a href="<?= e($root) ?>admin/login.php">Admin Login</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="footer-bottom text-center py-3 small">
        &copy; <?= date('Y') ?> KalaSetu – Local Skills &amp; Artisan Preservation · DBMS Project
    </div>
</footer>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e($root) ?>assets/js/script.js"></script>
</body>
</html>

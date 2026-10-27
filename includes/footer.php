        </main>

        <!-- Footer -->
        <footer class="border-top py-3 px-4 bg-white text-muted small mt-auto">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div>
                    &copy; <?= date('Y') ?> <strong><?= htmlspecialchars($salon_name) ?></strong> &bull; All Rights Reserved.
                </div>
                <div class="mt-2 mt-md-0 text-muted" style="font-size: 0.8rem;">
                    Timezone: <span class="badge bg-light text-dark border">Asia/Kolkata (IST)</span> &bull; Hostinger Business Hosting
                </div>
            </div>
        </footer>
    </div>

    <!-- Bootstrap 5.3.3 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom Application JS -->
    <script src="<?= $root ?>assets/js/app.js"></script>
    <?php if (isset($extra_js)): ?><?= $extra_js ?><?php endif; ?>
</body>
</html>

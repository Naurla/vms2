    </main><!-- /page-content -->
</div><!-- /main-content -->

</div><!-- /app-wrapper -->

<!-- Toast container (JS appends toasts here) -->
<div id="toast-container" class="toast-container"></div>

<script src="<?= $bp ?>assets/js/main.js"></script>
<?php if (isset($extraScripts)): ?>
<?= $extraScripts ?>
<?php endif; ?>
</body>
</html>

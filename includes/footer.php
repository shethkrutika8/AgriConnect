<?php
// ============================================================
// AgriConnect – Footer Include
// ============================================================
?>
  </div><!-- /.main-content -->
</div><!-- /.app-wrapper -->

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Main JS -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

<?php if (isset($extra_js)): ?>
  <?php foreach ((array)$extra_js as $js): ?>
  <script src="<?= htmlspecialchars($js) ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (isset($inline_js) && $inline_js): ?>
<script>
<?= $inline_js ?>
</script>
<?php endif; ?>

</body>
</html>

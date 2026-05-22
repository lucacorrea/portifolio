  </div>
  <script src="assets/js/app.js"></script>
  <?php foreach (($pageJs ?? []) as $js): ?>
    <script src="assets/js/<?= htmlspecialchars($js) ?>.js"></script>
  <?php endforeach; ?>
</body>
</html>

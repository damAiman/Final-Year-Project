    </div><!-- /.page-content -->
  </main>
</div><!-- /.app-shell -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
<?php if (!empty($inlineFooterScript)): ?>
<script><?= $inlineFooterScript ?></script>
<?php endif; ?>
<?php if (!empty($pageScripts)): foreach ($pageScripts as $script): ?>
<script src="<?= e(preg_match('#^https?://#', $script) ? $script : base_url($script)) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>

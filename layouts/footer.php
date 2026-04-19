<?php
/**
 * CMS BDU - Footer Layout
 * File footer dùng chung cho tất cả các trang
 */
?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../public/js/script.js"></script>
    <?php if (isset($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="../public/js/<?= e($js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

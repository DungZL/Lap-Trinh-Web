    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js" defer></script>
<script src="<?= e(url('assets/js/select.js')) ?>?v=<?= (int)(@filemtime(dirname(__DIR__) . '/assets/js/select.js') ?: 1) ?>" defer></script>
<script src="<?= e(url('assets/js/admin.js')) ?>?v=<?= (int)(@filemtime(dirname(__DIR__) . '/assets/js/admin.js') ?: 1) ?>" defer></script>
<?php foreach ($extraScripts ?? [] as $src): ?>
    <script src="<?= e($src) ?>" defer></script>
<?php endforeach; ?>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js" defer></script>
</body>
</html>

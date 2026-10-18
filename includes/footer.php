<?php
$inSubdir = in_array(basename(dirname($_SERVER['PHP_SELF'])), ['school', 'admin', 'staff']);
$rootPath = $inSubdir ? '../' : './';
?>
</main>

<footer>
    <div
        style="max-width: 1200px; margin: 0 auto; justify-content: center; display: flex;  align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            &copy; <?php echo date('Y'); ?> <strong>Educational Epiphany Merit Test (EEMT 2027)</strong>. All rights
            reserved.
        </div>

    </div>
</footer>

<script src="<?php echo $rootPath; ?>assets/js/app.js"></script>
</body>

</html>
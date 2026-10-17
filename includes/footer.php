<?php
$inSubdir = in_array(basename(dirname($_SERVER['PHP_SELF'])), ['school', 'admin', 'staff']);
$rootPath = $inSubdir ? '../' : './';
?>
</main>

<footer>
    <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            &copy; <?php echo date('Y'); ?> <strong>Educational Epiphany Merit Test (EEMT 2027)</strong>. All rights reserved.
        </div>
        <div style="font-size: 0.8rem; color: #94a3b8; display: flex; gap: 1rem; align-items: center;">
            <span>Andhra Pradesh Government Schools Prelims Program</span>
            <span>&bull;</span>
            <a href="<?php echo $rootPath; ?>staff_login.php" style="color: #64748b; text-decoration: none; font-size: 0.78rem;">Staff & Admin Access</a>
        </div>
    </div>
</footer>

<script src="<?php echo $rootPath; ?>assets/js/app.js"></script>
</body>
</html>

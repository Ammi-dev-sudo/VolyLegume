<script>
    (function () {
        try {
            var storageKey = 'volylegume.theme';
            var stored = window.localStorage.getItem(storageKey);
            var choice = stored === 'light' || stored === 'dark' ? stored : 'system';
            var isDark = choice === 'dark'
                || (choice === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            var theme = isDark ? 'dark' : 'light';
            var root = document.documentElement;

            root.setAttribute('data-theme', theme);
            root.setAttribute('data-theme-choice', choice);
            root.style.colorScheme = theme;

            var meta = document.querySelector('meta[name="theme-color"]');

            if (meta) {
                var color = theme === 'dark'
                    ? meta.getAttribute('data-dark-color')
                    : meta.getAttribute('data-light-color');

                if (color) {
                    meta.setAttribute('content', color);
                }
            }
        } catch (error) {
            // Le thème clair reste appliqué si le stockage est indisponible.
        }
    })();
</script>

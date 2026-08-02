/* قوائم اختيار مع خيار "غير موجود بالقائمة" يظهر عندها حقل كتابة حر */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.dropdown-with-other').forEach(function (select) {
        var target = document.getElementById(select.getAttribute('data-other-target'));
        if (!target) return;
        var input = target.querySelector('input');
        function sync() {
            var isOther = select.value === '__other__';
            target.classList.toggle('hidden', !isOther);
            if (isOther && input) input.focus();
        }
        select.addEventListener('change', sync);
    });
});

/* قوائم إجراءات الجداول المنسدلة (⋮) */
(function () {
    function closeAllMenus(except) {
        document.querySelectorAll('.row-menu.open').forEach(function (menu) {
            if (menu !== except) menu.classList.remove('open');
        });
    }

    function positionDropdown(toggleBtn, dropdown) {
        var rect = toggleBtn.getBoundingClientRect();
        dropdown.style.top = (rect.bottom + 6) + 'px';
        dropdown.style.left = 'auto';
        var width = dropdown.offsetWidth || 195;
        var idealRight = window.innerWidth - rect.right;
        var minRight = 8;
        var maxRight = Math.max(minRight, window.innerWidth - width - 8);
        dropdown.style.right = Math.min(Math.max(idealRight, minRight), maxRight) + 'px';
    }

    document.addEventListener('click', function (e) {
        var toggleBtn = e.target.closest('.row-menu-toggle');
        if (toggleBtn) {
            var menu = toggleBtn.closest('.row-menu');
            var wasOpen = menu.classList.contains('open');
            closeAllMenus();
            if (!wasOpen) {
                var dropdown = menu.querySelector('.row-menu-dropdown');
                positionDropdown(toggleBtn, dropdown);
                menu.classList.add('open');
            }
            e.stopPropagation();
            return;
        }
        if (!e.target.closest('.row-menu-dropdown')) {
            closeAllMenus();
        }
    });

    window.addEventListener('scroll', function () { closeAllMenus(); }, true);
    window.addEventListener('resize', function () { closeAllMenus(); });
})();

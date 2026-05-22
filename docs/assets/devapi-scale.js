(function () {
    function detectScale() {
        var ratio = Number(window.devicePixelRatio || 1);
        if (ratio >= 2.5) {
            return 1.2;
        }
        if (ratio >= 2) {
            return 1.16;
        }
        if (ratio >= 1.5) {
            return 1.12;
        }
        if (ratio >= 1.25) {
            return 1.08;
        }
        return 1;
    }

    function applyScale() {
        var scale = detectScale();
        document.documentElement.style.setProperty('--devapi-ui-scale', scale.toFixed(2));
        document.documentElement.dataset.devapiDpiScale = scale.toFixed(2);
    }

    applyScale();
    window.addEventListener('resize', applyScale, { passive: true });
})();

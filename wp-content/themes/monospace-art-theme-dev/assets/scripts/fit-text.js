(function () {
    'use strict';

    function fitTextElement(el) {
        const parent = el.parentElement;
        if (!parent) return;

        const text = el.textContent.trim();
        if (!text) return;

        const style = window.getComputedStyle(el);
        const fontFamily = el.dataset.fitFont || style.fontFamily;
        const fontWeight = el.dataset.fitWeight || style.fontWeight;
        const targetPercent = parseFloat(el.dataset.fitWidth || '100');

        const parentWidth = parent.clientWidth;
        if (!parentWidth) return;

        const maxWidth = parseFloat(el.dataset.fitMaxWidth || parentWidth);
        const cappedWidth = Math.min(parentWidth, maxWidth);

        const padBoth = parseFloat(el.dataset.fitPadding || '0');
        const padLeft = parseFloat(el.dataset.fitPaddingLeft || padBoth);
        const padRight = parseFloat(el.dataset.fitPaddingRight || padBoth);

        const availableWidth = cappedWidth - padLeft - padRight;
        if (availableWidth <= 0) return;

        const targetPx = availableWidth * (targetPercent / 100);

        // Use canvas for measurement
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        ctx.font = `${fontWeight} 100px ${fontFamily}`;
        const measuredWidth = ctx.measureText(text).width;

        if (!measuredWidth) return;

        let finalSize = 100 * (targetPx / measuredWidth);

        // Safari-specific adjustment
        const isSafari = /Safari/.test(navigator.userAgent) && !/Chrome/.test(navigator.userAgent);
        if (isSafari) {
            finalSize *= 0.97;
        }

        const minFont = parseFloat(el.dataset.fitMinFont || 14);
        const maxFont = parseFloat(el.dataset.fitMaxFont || 200);
        finalSize = Math.max(minFont, Math.min(finalSize, maxFont));

        el.style.fontSize = finalSize + 'px';
    }

    function fitAll() {
        document.querySelectorAll('[data-fit-text]').forEach(fitTextElement);
    }

    // Prevent layout shift by setting up elements early
    const style = document.createElement('style');
    style.textContent = `
        [data-fit-text] {
            white-space: nowrap;
            overflow: hidden;
            opacity: 0;
        }
    `;
    document.head.appendChild(style);

    const isSafari = /Safari/.test(navigator.userAgent) && !/Chrome/.test(navigator.userAgent);

    // Run calculation synchronously as early as possible
    function initFit() {
        fitAll();
        // Make visible immediately after sizing
        requestAnimationFrame(() => {
            style.textContent = `
                [data-fit-text] {
                    white-space: nowrap;
                    overflow: hidden;
                    opacity: 1;
                }
            `;
        });
    }

    // Safari: wait for fonts, others: run ASAP
    if (isSafari && document.fonts && document.fonts.ready) {
        document.fonts.ready.then(initFit);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFit);
    } else {
        initFit();
    }

    // Debounced resize
    let resizeTimeout;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            requestAnimationFrame(fitAll);
        }, 100);
    });

})();
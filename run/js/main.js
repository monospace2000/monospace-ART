// ============================================================
// MAIN ENTRY POINT (Canvas-Ready)
// ============================================================

import { uiReady, setupModalManager } from './ui/ui.js';
import { initAttractor } from './model/attractor.js';
import { log, moduleTag, trace } from './utils/utilities.js';
import { initSimulationCanvas, startSimulation } from './control/controls.js';
import { initCanvasRenderer } from './render/render.js';

// ============================================================
// DETECT MOBILE
// ============================================================
export function isMobile() {
    return /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
}

// ============================================================
// LOADING SCREEN HANDLERS
// ============================================================
function showLoadingScreen() {
    let loader = document.getElementById('warmup-loader');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'warmup-loader';
        Object.assign(loader.style, {
            position: 'fixed',
            inset: '0',
            background: 'rgba(0,0,0,1)',
            color: '#fff',
            display: 'flex',
            justifyContent: 'center',
            alignItems: 'center',
            fontSize: '24px',
            fontFamily: 'monospace',
            zIndex: 20000,
            flexDirection: 'column',
        });

        const message = document.createElement('div');
        message.id = 'warmup-loader-message';
        message.style.width = '20ch';
        message.style.textAlign = 'center';
        message.textContent = 'Initializing... 0%';
        loader.appendChild(message);

        document.body.appendChild(loader);
    }
    loader.style.display = 'flex';
}

function updateLoadingMessage(message) {
    const msgEl = document.getElementById('warmup-loader-message');
    if (msgEl) msgEl.textContent = message;
}

function hideLoadingScreen() {
    const loader = document.getElementById('warmup-loader');
    if (loader) loader.style.display = 'none';
}

// ============================================================
// CANVAS INITIALIZATION
// ============================================================
let canvas, ctx;

function initCanvas() {
    canvas = document.getElementById('dslCanvas');
    if (!canvas) {
        canvas = document.createElement('canvas');
        canvas.id = 'dslCanvas';
        canvas.style.position = 'fixed';
        canvas.style.top = '0';
        canvas.style.left = '0';
        canvas.style.zIndex = '1'; // behind UI overlays
        document.body.appendChild(canvas);
    }
    ctx = canvas.getContext('2d');

    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    return { canvas, ctx };
}

// ============================================================
// INITIALIZATION
// ============================================================
export async function init() {
    showLoadingScreen();

    const mobile = isMobile();

    if (!mobile) {
        setupModalManager();
        await uiReady;
        log('Desktop viewer mode');
    } else {
        document.body.classList.add('mobile');
        log('Mobile viewer mode');
    }

    // Fade out everything except loader
    const contentElements = Array.from(document.body.children).filter(
        (el) => el.id !== 'warmup-loader'
    );
    contentElements.forEach((el) => (el.style.opacity = '0'));

    // --- CANVAS & RENDERER INITIALIZATION ---
    initCanvas(); // create/resizes <canvas>
    initCanvasRenderer(); // sets ctx for canvasRenderer
    initSimulationCanvas(); // sets ctx for controls

    initAttractor(canvas);

    // --- START SIMULATION ---
    startSimulation();

    // Warm-up animation
    let warmupTicks = 0;
    const warmupDuration = 30;

    const checkWarmup = () => {
        warmupTicks++;
        const progress = Math.floor((warmupTicks / warmupDuration) * 100);
        updateLoadingMessage(`Initializing... ${progress}%`);

        if (warmupTicks >= warmupDuration) {
            contentElements.forEach((el) => {
                el.style.transition = 'opacity 0.3s';
                el.style.opacity = '1';
            });
            hideLoadingScreen();
            console.log('WELCOME TO DIGITAL SEX LIFE 2025');
        } else {
            requestAnimationFrame(checkWarmup);
        }
    };

    requestAnimationFrame(checkWarmup);
}

// ============================================================
// START UP
// ============================================================
document.addEventListener('DOMContentLoaded', async () => {
    await init();
});

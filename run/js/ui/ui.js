// ============================================================
// UI MODULE
// ============================================================

import { log, moduleTag, trace } from '../utils/utilities.js';
import { state } from '../model/state.js';
import {
    resetSimulation,
    startSimulation,
    stopSimulation,
} from '../control/controls.js';
import { initGraph, initStatsBar, updateStats } from './stats.js';
import { initializeParameters } from './parameters.js';
import { setupAppearanceModal } from './appearance.js';
import { isMobile } from '../main.js';

// ============================================================
// MODALS LOADER
// ============================================================
export function loadModals() {
    return new Promise((resolve) => {
        const modalsContainer = document.getElementById('modalsContainer');
        if (!modalsContainer) return resolve();

        fetch('/run/modals.html')
            .then((res) => {
                if (!res.ok) throw new Error('Failed to load modals');
                return res.text();
            })
            .then((html) => {
                modalsContainer.innerHTML = html;
                resolve();
            })
            .catch((err) => {
                console.error(err);
                modalsContainer.innerHTML = `<p>Error loading modals.</p>`;
                resolve();
            });
    });
}

// ============================================================
// ABOUT TEXT LOADER
// ============================================================
export function loadAboutText() {
    return new Promise((resolve) => {
        const aboutContainer = document.getElementById('aboutContent');
        if (!aboutContainer) return resolve();

        fetch('/about.html')
            .then((res) => {
                if (!res.ok) throw new Error('Failed to load about text');
                return res.text();
            })
            .then((html) => {
                aboutContainer.innerHTML = html;
                resolve();
            })
            .catch((err) => {
                console.error(err);
                aboutContainer.innerHTML = `<p>Error loading about text.</p>`;
                resolve();
            });
    });
}

// ============================================================
// MODAL FUNCTIONS
// ============================================================
export let topZIndex = 10000;

export function bringToFront(modal) {
    modal.style.zIndex = ++topZIndex;
}

export function toggleModal(id, show) {
    const modal = document.getElementById(id);
    const overlay = document.getElementById(id.replace('Modal', 'Overlay'));
    if (modal) {
        modal.style.display = show ? 'flex' : 'none';
        if (show) bringToFront(modal); // bring frontmost when opened
    }
    if (overlay) overlay.style.display = show ? 'block' : 'none';
}

export function makeDraggable(modal, handle) {
    let offsetX = 0,
        offsetY = 0;
    modal.draggingHeader = false;

    handle.addEventListener('mousedown', (e) => {
        if (e.target.classList.contains('modal-close')) return;
        modal.draggingHeader = true;
        const rect = modal.getBoundingClientRect();
        offsetX = e.clientX - rect.left;
        offsetY = e.clientY - rect.top;
        document.addEventListener('mousemove', drag);
        document.addEventListener('mouseup', stopDrag);
        bringToFront(modal); // frontmost when dragging
    });

    function drag(e) {
        if (!modal.draggingHeader) return;
        modal.style.left = e.clientX - offsetX + 'px';
        modal.style.top = e.clientY - offsetY + 'px';
    }

    function stopDrag() {
        modal.draggingHeader = false;
        document.removeEventListener('mousemove', drag);
        document.removeEventListener('mouseup', stopDrag);
    }
}

// ============================================================
// BLOCK CLICKS INSIDE MODALS
// ============================================================
export function blockModalClicks() {
    document.querySelectorAll('.modal-window').forEach((modal) => {
        modal.addEventListener('click', (e) => {
            if (!modal.draggingHeader) {
                bringToFront(modal); // frontmost on click
                e.stopPropagation();
            }
        });
        modal.addEventListener('mousedown', (e) => {
            if (!modal.draggingHeader) e.stopPropagation();
        });
        modal.addEventListener('mouseup', (e) => {
            if (!modal.draggingHeader) e.stopPropagation();
        });
    });
}

// ============================================================
// MODAL MANAGER
// ============================================================
export function setupModalManager() {
    // Open modal buttons inside modals
    document.querySelectorAll('[data-action="openModal"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            toggleModal(btn.dataset.target, true); // frontmost handled in toggleModal
        });
    });

    // Close buttons
    document.querySelectorAll('.modal-close').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            toggleModal(btn.dataset.modal, false);
            e.stopPropagation();
        });
    });

    // Stats bar toggle
    const statsToggle = document.querySelector(
        '[data-action="toggleStatsBar"]'
    );
    if (statsToggle) {
        statsToggle.addEventListener('click', () => {
            const bar = document.getElementById('statsBar');
            const indicator = document.getElementById('statsBarIndicator');
            const visible = bar.style.display === 'flex';
            bar.style.display = visible ? 'none' : 'flex';
            indicator.textContent = visible ? 'OFF' : 'ON';
            indicator.style.color = visible ? '#666' : '#4CAF50';
        });
    }

    // Make modals draggable
    document.querySelectorAll('.modal-window').forEach((modal) => {
        const header = modal.querySelector('.modal-header');
        if (header) makeDraggable(modal, header);
    });

    // Top-level menu buttons
    const menuBtn = document.getElementById('menuBtn');
    const aboutBtn = document.getElementById('aboutBtn');
    const pauseBtn = document.getElementById('pauseBtn');
    const restartBtn = document.getElementById('restartBtn');

    if (menuBtn)
        menuBtn.addEventListener('click', () => toggleModal('menuModal', true));
    if (aboutBtn)
        aboutBtn.addEventListener('click', () =>
            toggleModal('aboutModal', true)
        );

    if (pauseBtn) {
        pauseBtn.addEventListener('click', function () {
            state.paused = !state.paused;
            this.innerHTML = state.paused
                ? "<span class='icon'>▶</span><span class='label'> Resume"
                : "<span class='icon'>⏸</span><span class='label'> Pause";
            this.classList.toggle('paused', state.paused);
        });
    }

    if (restartBtn) {
        restartBtn.addEventListener('click', () => {
            stopSimulation();
            resetSimulation();
            updateStats();
            startSimulation();
            if (pauseBtn) {
                pauseBtn.textContent = '⏸ Pause';
                pauseBtn.classList.remove('paused');
            }
        });
    }
    if (isMobile()) {
        // Hide non-essential buttons on mobile
        const menuDivider = document.getElementById('desktopMenuDivider');
        if (menuDivider) menuDivider.style.display = 'none';
        if (menuBtn) menuBtn.style.display = 'none';
        if (aboutBtn) aboutBtn.style.display = 'none';
        const projectTitle = document.getElementById('projectTitle');
        if (projectTitle) projectTitle.style.cursor = 'pointer';
        projectTitle.addEventListener('click', () =>
            toggleModal('aboutModal', true)
        );
    }
}

// ============================================================
// READY PROMISE
// ============================================================
export const uiReady = new Promise(async (resolve) => {
    if (document.readyState === 'loading') {
        // DOM not yet ready — wait for it
        document.addEventListener('DOMContentLoaded', async () => {
            await loadModals();
            setupModalManager();
            initGraph();
            initStatsBar();
            initializeParameters();
            setupAppearanceModal();
            blockModalClicks();
            await loadAboutText();
            log('UI fully initialized');
            resolve();
        });
    } else {
        // DOM already ready — just run immediately
        await loadModals();
        setupModalManager();
        initGraph();
        initStatsBar();
        initializeParameters();
        setupAppearanceModal();
        blockModalClicks();
        await loadAboutText();
        log('UI fully initialized');
        resolve();
    }
});

log(`[${moduleTag(import.meta)}] loaded`);

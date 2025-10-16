// ============================================================
// SIMULATION CONTROL (Canvas-Ready)
// ============================================================

import { state } from '../model/state.js';
import { createDigit, killDigit } from '../model/digit.js';
import { CONFIG } from '../config/config.js';
import { updateDigitPosition } from '../model/movement.js';
import { createCountsObject } from '../model/state.js';
import { reproduce } from '../model/reproduction.js';
import { updateStats } from '../ui/stats.js';
import { applyAttractor } from '../model/attractor.js';
import { attractorDebugCtx } from '../model/attractor.js';
import { log, moduleTag, trace } from '../utils/utilities.js';
import { renderAll } from '../render/render.js';

// ------------------------------------------------------------
// Canvas setup
// ------------------------------------------------------------
let canvas, ctx;

export function initSimulationCanvas() {
    canvas = document.getElementById('dslCanvas');
    if (!canvas) throw new Error('Canvas element not found: #dslCanvas');
    ctx = canvas.getContext('2d');
    return { canvas, ctx };
}

// ------------------------------------------------------------
// Initialize starting digits
// ------------------------------------------------------------
function initStartingDigits() {
    const cx = window.innerWidth / 2;
    const cy = window.innerHeight / 2;

    const d1 = createDigit('1', 'M', cx - 30, cy);
    const d2 = createDigit('1', 'F', cx + 30, cy);

    [d1, d2].forEach((d) => {
        const angle = Math.random() * 2 * Math.PI;
        const speed = Math.max(CONFIG.speed, 0.5);
        d.dx = Math.cos(angle) * speed;
        d.dy = Math.sin(angle) * speed;
        d.followTimer = 0; // start moving immediately
    });

    requestAnimationFrame(() => startSimulation());
}

// ------------------------------------------------------------
// Reset simulation
// ------------------------------------------------------------
export function resetSimulation() {
    state.digits = []; // clear all digits
    initStartingDigits();

    if (state.tick > 0) state.resetCount++;
    state.resetStartTime = Date.now();
    state.epochCumulativeCounts = createCountsObject();
}

// ------------------------------------------------------------
// Main simulation loop
// ------------------------------------------------------------
let lastTime = performance.now();

export function tickSimulation(now = performance.now()) {
    if (!state.paused) {
        state.tick++;
        const deltaTime = (now - lastTime) / 1000;
        lastTime = now;

        const frameIncrement = deltaTime * CONFIG.FPS;

        // Clear attractor debug if needed
        if (CONFIG.showAttractorLines && attractorDebugCtx) {
            attractorDebugCtx.clearRect(
                0,
                0,
                attractorDebugCtx.canvas.width,
                attractorDebugCtx.canvas.height
            );
        }

        // Update all digits
        for (const d of [...state.digits]) {
            d.age += frameIncrement;

            updateDigitPosition(d);

            if (CONFIG.enableAttractor) {
                applyAttractor(d);
            }

            if (d.age > CONFIG.maxAge) killDigit(d);
        }

        // Handle reproduction
        reproduce();

        // Reset if no digits remain
        if (state.digits.length === 0) resetSimulation();

        // Update stats UI (DOM)
        updateStats();

        // Render all digits on canvas
        renderAll(state.digits);
    }

    // Continue animation loop
    if (state.running) {
        state.animationFrameId = requestAnimationFrame(tickSimulation);
    }
}

// ------------------------------------------------------------
// Simulation control functions
// ------------------------------------------------------------
export function startSimulation() {
    if (!state.running) {
        state.running = true;
        state.paused = false;

        if (state.digits.length === 0) resetSimulation();

        tickSimulation();
    }
}

export function stopSimulation() {
    state.running = false;
    state.paused = false;
    if (state.animationFrameId) {
        cancelAnimationFrame(state.animationFrameId);
        state.animationFrameId = null;
    }
}

log(`[${moduleTag(import.meta)}] loaded`);

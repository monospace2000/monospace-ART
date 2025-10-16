// ============================================================
// SIMULATION CONTROL
// ============================================================

import { state } from "../model/state.js";
import { createDigit, killDigit } from "../model/digit.js";
import { CONFIG } from "../config/config.js";
import { updateDigitPosition } from "../model/movement.js";
import { updateDigitAppearance } from "../render/render.js";
import { createCountsObject } from "../model/state.js";
import { reproduce } from "../model/reproduction.js";
import { updateStats } from "../ui/stats.js";
import { applyAttractor } from "../model/attractor.js";
import { attractorDebugCtx } from "../model/attractor.js";
import { log, moduleTag, trace } from "../utils/utilities.js";

// Helper: initialize starting digits with proper velocity
function initStartingDigits() {
    const cx = window.innerWidth / 2;
    const cy = window.innerHeight / 2;

    const d1 = createDigit("1", "M", cx - 30, cy);
    const d2 = createDigit("1", "F", cx + 30, cy);

    [d1, d2].forEach((d) => {
        const angle = Math.random() * 2 * Math.PI;
        const speed = Math.max(CONFIG.speed, 0.5); // ensure minimum speed
        d.dx = Math.cos(angle) * speed;
        d.dy = Math.sin(angle) * speed;
        d.followTimer = 0; // start moving immediately

    });
    window.requestAnimationFrame(() => startSimulation());

}

export function resetSimulation() {
    // Remove old digits
    state.digits.forEach((d) => d.element.remove());
    state.digits = [];

    initStartingDigits();

    if (state.tick > 0) state.resetCount++;
    state.resetStartTime = Date.now();
    state.epochCumulativeCounts = createCountsObject();
}


let lastTime = performance.now();

export function tickSimulation(now = performance.now()) {
    if (!state.paused) {
        // --- Compute delta time in seconds ---
        const deltaTime = (now - lastTime) / 1000;
        lastTime = now;

        // --- Convert deltaTime to frame-equivalent using CONFIG.FPS ---
        const frameIncrement = deltaTime * CONFIG.FPS;

        if (CONFIG.showAttractorLines && attractorDebugCtx) {
            attractorDebugCtx.clearRect(
                0,
                0,
                attractorDebugCtx.canvas.width,
                attractorDebugCtx.canvas.height
            );
        }

        // Loop over a copy to safely remove digits during iteration
        for (const d of [...state.digits]) {
            d.age += frameIncrement; // scaled by FPS

            // Update digit position and appearance
            updateDigitPosition(d);
            updateDigitAppearance(d);

            // --- APPLY ATTRACTOR INTERACTION ---
            if (CONFIG.enableAttractor) {
                applyAttractor(d);
            }

            // Kill digit if it exceeds max age
            if (d.age > CONFIG.maxAge) killDigit(d);
        }

        // Handle reproduction
        reproduce();

        // Reset if no digits remain
        if (state.digits.length === 0) resetSimulation();

        // Update UI
        updateStats();
    }

    // Continue animation loop
    if (state.running) {
        state.animationFrameId = requestAnimationFrame(tickSimulation);
    }
}


export function startSimulation() {
    if (!state.running) {
        state.running = true;
        state.paused = false;

        // Ensure starting digits exist
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

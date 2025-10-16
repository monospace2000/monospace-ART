// ============================================================
// APPEARANCE MODULE (Canvas-Only, Globe Shading + Outline + Blur)
// ============================================================

import { CONFIG } from '../config/config.js';
import { log } from "../utils/utilities.js"; 

// --- Local visual settings (only used inside this module) ---
const LIGHT_SETTINGS = {
    angle: Math.PI / 4,      // light direction (0 = right, π/2 = down)
    distance: 0.3,           // relative distance from circle center
    intensity: 0.4,          // highlight brightness (0–1)
    shadowDepth: 0.4         // shadow darkness (0–1)
};

/**
 * Returns visual properties for canvas rendering of a digit.
 * - scale grows from 0.3 → 1 over maturity
 * - opacity fades past CONFIG.oldAge
 * - color changes based on sex and age
 * - globe-like gradient shading with configurable light
 * - thin dark outline for visibility
 * - subtle motion blur based on speed
 */
export function updateDigitAppearance(d) {
    // --- Scale grows with maturity ---
    const scale = 0.3 + Math.min(d.age / CONFIG.matureAge, 1) * 0.7;

    // --- Opacity fades when old ---
    const opacity = d.age < CONFIG.oldAge
        ? 1
        : 1 - (d.age - CONFIG.oldAge) / (CONFIG.maxAge - CONFIG.oldAge);

    // --- Color transition based on sex ---
    const t = Math.min(d.age / CONFIG.matureAge, 1);
    let r, g, b;
    if (d.sex === 'M') {
        r = 255 + t * (100 - 255) | 0;
        g = 255 + t * (160 - 255) | 0;
        b = 255;
    } else {
        r = 255;
        g = 255 + t * (100 - 255) | 0;
        b = 255 + t * (100 - 255) | 0;
    }
    const baseColor = `rgb(${r},${g},${b})`;

    // --- Derived highlight and shadow colors ---
    const lighten = (c) => Math.min(255, c + 255 * LIGHT_SETTINGS.intensity);
    const darken = (c) => Math.max(0, c * (1 - LIGHT_SETTINGS.shadowDepth));

    const highlight = `rgb(${lighten(r)}, ${lighten(g)}, ${lighten(b)})`;
    const shadow = `rgb(${darken(r)}, ${darken(g)}, ${darken(b)})`;

    // --- Thin dark outline ---
    const outlineColor = `rgba(0,0,0,${Math.min(opacity, 0.8)})`;
    const outlineWidth = 1 + scale * 0.5;

    // --- Motion blur based on speed ---
    const speed = Math.hypot(d.dx || 0, d.dy || 0);
    const blur = Math.min(speed / CONFIG.speed, 1) * 4; // max 4px blur

    // --- Light source for globe shading ---
    const lx = d.x - Math.cos(LIGHT_SETTINGS.angle) * LIGHT_SETTINGS.distance * CONFIG.digitSize * scale;
    const ly = d.y - Math.sin(LIGHT_SETTINGS.angle) * LIGHT_SETTINGS.distance * CONFIG.digitSize * scale;

    // --- Gradient definition for globe effect ---
    const gradient = {
        type: "radial",
        lightX: lx,
        lightY: ly,
        highlight,
        baseColor,
        shadow
    };

    return {
        scale,
        color: baseColor,
        opacity,
        outlineColor,
        outlineWidth,
        blur,
        gradient,
        light: LIGHT_SETTINGS
    };
}

log(`[${moduleTag(import.meta)}] loaded`);

// ============================================================
// CANVAS RENDERER (with optional gradient shading)
// ============================================================

import { CONFIG } from "../config/config.js";
import { updateDigitAppearance } from "./appearance.js";

let canvas, ctx;

export function initCanvasRenderer() {
    canvas = document.getElementById("dslCanvas");
    if (!canvas) {
        throw new Error("Canvas element not found: #dslCanvas");
    }
    ctx = canvas.getContext("2d");
    return { canvas, ctx };
}

// Render a single digit as a circle with optional globe shading and label
export function renderDigit(d) {
    const props = updateDigitAppearance(d);
    const size = CONFIG.digitSize * props.scale;

    ctx.save();
    ctx.globalAlpha = props.opacity;

    // --- Apply motion blur (if defined) ---
    if (props.blur && props.blur > 0) {
        ctx.shadowColor = props.color;
        ctx.shadowBlur = props.blur;
    } else {
        ctx.shadowBlur = 0;
    }

    // --- Draw main circle (flat or shaded) ---
    ctx.beginPath();

    // Use gradient shading if defined
    if (props.gradient && props.gradient.type === "radial") {
        const g = ctx.createRadialGradient(
            props.gradient.lightX,
            props.gradient.lightY,
            size * 0.1,
            d.x, d.y,
            size / 2
        );
        g.addColorStop(0, props.gradient.highlight);
        g.addColorStop(0.3, props.color);
        g.addColorStop(1, props.gradient.shadow);
        ctx.fillStyle = g;
    } else {
        ctx.fillStyle = props.color;
    }

    ctx.arc(d.x, d.y, size / 2, 0, Math.PI * 2);
    ctx.fill();

    // --- Draw thin dark outline (if defined) ---
/*     if (props.outlineColor) {
        ctx.lineWidth = props.outlineWidth || 1;
        ctx.strokeStyle = props.outlineColor;
        ctx.stroke();
    }
 */
    ctx.closePath();

    // --- Reset blur for text ---
    ctx.shadowBlur = 0;

    // --- Draw label (digit name) ---
    ctx.fillStyle = "#000"; // black text for readability
    ctx.font = `${size * 0.6}px sans-serif`;
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(d.name, d.x, d.y);

    ctx.restore();
}

// Render all digits
export function renderAll(digits) {
    if (!ctx) return;

    // Clear entire canvas
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    for (const d of digits) {
        renderDigit(d);
    }
}


log(`[${moduleTag(import.meta)}] loaded`);

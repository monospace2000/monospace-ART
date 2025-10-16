// ============================================================
// RENDER MODULE (Canvas-Only)
// Globe Shading + Outline + Glow + Drop Shadow + Motion Blur
// + Optional Simulation Overlays (search radius, bonds, attraction)
// ============================================================

import { CONFIG } from "../config/config.js";
import { log, moduleTag } from "../utils/utilities.js";

// ============================================================
// 🔧 CONFIGURABLE APPEARANCE SETTINGS
// ============================================================

const GLOBE_SETTINGS = {
    enabled: true,
    lightAngle: Math.PI / 4,
    lightDistance: 0.3,
    highlight: 0.4,
    shadow: 0.4,
};
const OUTLINE_SETTINGS = { enabled: true, maxAlpha: 0.8, baseWidth: 0.5, color:  "rgba(0,0,0,1)" };
const SHADOW_SETTINGS = {
    enabled: false,
    offsetX: 0,
    offsetY: 0,
    blur: 20,
    color: "rgba(0,0,0,0.3)",
};
const GLOW_SETTINGS = { enabled: true, strength: 20, opacity: 0.9 };
const BLUR_SETTINGS = { enabled: true, maxBlur: 4 };



// ============================================================
// 🔧 SIMULATION OVERLAY SETTINGS
// ============================================================

const SEARCH_RADIUS_SETTINGS = {
    enabled: true,
    color: "rgba(0,0,255,0.15)",
    lineWidth: 1,
    fillOpacity: 0.05,
};
const BOND_LINE_SETTINGS = {
    enabled: true,
    color: "rgba(0,200,0,0.5)",
    lineWidth: 2,
};
const CHILD_BOND_LINE_SETTINGS = {
    enabled: true,
    baseColor: [255, 165, 0],
    lineWidth: 1.5,
};
const ATTRACTION_LINE_SETTINGS = {
    enabled: true,
    maleToFemale: { color: "rgba(255,255,255,0.7)", width: 1 },
};

// ============================================================
// 🔧 VISUALIZATION TOGGLES
// ============================================================

export const VISUALS = {
    shadows: true,
    outlines: true,
    glow: true,
    searchRadius: true,
    bonds: true,
    childBonds: true,
    attractionLines: true,
};

// ============================================================
// INTERNAL STATE
// ============================================================

let canvas, ctx;

// ============================================================
// APPEARANCE LOGIC
// ============================================================

export function updateDigitAppearance(d) {
    const scale = 0.3 + Math.min(d.age / CONFIG.matureAge, 1) * 0.7;
    const opacity =
        d.age < CONFIG.oldAge
            ? 1
            : 1 - (d.age - CONFIG.oldAge) / (CONFIG.maxAge - CONFIG.oldAge);

    const t = Math.min(d.age / CONFIG.matureAge, 1);
    let r, g, b;
    if (d.sex === "M") {
        r = (255 + t * (100 - 255)) | 0;
        g = (255 + t * (160 - 255)) | 0;
        b = 255;
    } else {
        r = 255;
        g = (255 + t * (100 - 255)) | 0;
        b = (255 + t * (100 - 255)) | 0;
    }

    const baseColor = `rgb(${r},${g},${b})`;
    const lighten = (c) => Math.min(255, c + 255 * GLOBE_SETTINGS.highlight);
    const darken = (c) => Math.max(0, c * (1 - GLOBE_SETTINGS.shadow));
    const highlight = `rgb(${lighten(r)},${lighten(g)},${lighten(b)})`;
    const shadow = `rgb(${darken(r)},${darken(g)},${darken(b)})`;

    const outlineColor = `rgba(0,0,0,${Math.min(opacity, OUTLINE_SETTINGS.maxAlpha)})`;
    const outlineWidth = OUTLINE_SETTINGS.baseWidth + scale * 0.5;

    const speed = Math.hypot(d.dx || 0, d.dy || 0);
    const blur = BLUR_SETTINGS.enabled
        ? Math.min(speed / CONFIG.speed, 1) * BLUR_SETTINGS.maxBlur
        : 0;

    const lx =
        d.x -
        Math.cos(GLOBE_SETTINGS.lightAngle) *
            GLOBE_SETTINGS.lightDistance *
            CONFIG.digitSize *
            scale;
    const ly =
        d.y -
        Math.sin(GLOBE_SETTINGS.lightAngle) *
            GLOBE_SETTINGS.lightDistance *
            CONFIG.digitSize *
            scale;

    return {
        scale,
        color: baseColor,
        opacity,
        highlight,
        shadow,
        outlineColor,
        outlineWidth,
        blur,
        lx,
        ly,
    };
}

// ============================================================
// INITIALIZATION
// ============================================================

export function initCanvasRenderer() {
    canvas = document.getElementById("dslCanvas");
    if (!canvas) throw new Error("Canvas element not found: #dslCanvas");
    ctx = canvas.getContext("2d");
    return { canvas, ctx };
}

// ============================================================
// RENDERING FUNCTIONS
// ============================================================

export function renderDigit(d, activeSet) {
    const props = updateDigitAppearance(d);
    const size = CONFIG.digitSize * props.scale;

    ctx.save();
    ctx.globalAlpha = props.opacity;

    // --- Reset shadow ---
    ctx.shadowBlur = 0;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 0;

    // --- Apply shadow if enabled ---
    if (VISUALS.shadows && SHADOW_SETTINGS.enabled) {
        ctx.shadowColor = SHADOW_SETTINGS.color;
        ctx.shadowOffsetX = SHADOW_SETTINGS.offsetX;
        ctx.shadowOffsetY = SHADOW_SETTINGS.offsetY;
        ctx.shadowBlur = SHADOW_SETTINGS.blur;
    }

    // --- Apply glow if enabled ---
    if (VISUALS.glow && GLOW_SETTINGS.enabled) {
        ctx.shadowColor = `rgba(${props.color.match(/\d+/g).join(",")},${GLOW_SETTINGS.opacity})`;
        ctx.shadowBlur = GLOW_SETTINGS.strength;
    }

    // --- Main circle ---
    ctx.beginPath();
    if (GLOBE_SETTINGS.enabled) {
        const g = ctx.createRadialGradient(
            props.lx,
            props.ly,
            size * 0.1,
            d.x,
            d.y,
            size / 2
        );
        g.addColorStop(0, props.highlight);
        g.addColorStop(0.3, props.color);
        g.addColorStop(1, props.shadow);
        ctx.fillStyle = g;
    } else {
        ctx.fillStyle = props.color;
    }
    ctx.arc(d.x, d.y, size / 2, 0, Math.PI * 2);
    ctx.fill();
    ctx.closePath();

    // --- Reset shadow before outline ---
    ctx.shadowBlur = 0;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 0;

    // --- Outline ---
    if (VISUALS.outlines && OUTLINE_SETTINGS.enabled) {
        ctx.lineWidth = props.outlineWidth;
        ctx.strokeStyle = props.outlineColor;
        ctx.stroke();
    }

    // --- Label ---
    ctx.fillStyle = "#000";
    ctx.font = `${size * 0.6}px sans-serif`;
    ctx.textAlign = "center";
    ctx.textBaseline = "middle";
    ctx.fillText(d.name, d.x, d.y);
    ctx.restore();

    // --- Overlays ---
    if (VISUALS.searchRadius && SEARCH_RADIUS_SETTINGS.enabled && d.sex === "M" && d.age >= CONFIG.matureAge) {
        ctx.save();
        ctx.fillStyle = `rgba(0,0,255,${SEARCH_RADIUS_SETTINGS.fillOpacity})`;
        ctx.strokeStyle = SEARCH_RADIUS_SETTINGS.color;
        ctx.lineWidth = SEARCH_RADIUS_SETTINGS.lineWidth;
        ctx.beginPath();
        ctx.arc(d.x, d.y, CONFIG.attractionRadius, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();
        ctx.closePath();
        ctx.restore();
    }

    if (
        VISUALS.attractionLines &&
        ATTRACTION_LINE_SETTINGS.enabled &&
        d.sex === "M" &&
        d.age >= CONFIG.matureAge &&
        d.age < CONFIG.oldAge &&
        d.attractionTarget
    ) {
        const target = d.attractionTarget;
        const validTarget =
            target &&
            activeSet.has(target) &&
            target.sex === "F" &&
            target.age >= CONFIG.matureAge &&
            target.age < CONFIG.oldAge &&
            target.gestationTimer === 0 &&
            !target.bondedTo;

        if (validTarget) {
            ctx.save();
            ctx.strokeStyle = ATTRACTION_LINE_SETTINGS.maleToFemale.color;
            ctx.lineWidth = ATTRACTION_LINE_SETTINGS.maleToFemale.width;
            ctx.beginPath();
            ctx.moveTo(d.x, d.y);
            ctx.lineTo(target.x, target.y);
            ctx.stroke();
            ctx.restore();
        }
    }
}


// ============================================================
// Render bonding lines safely
// ============================================================

export function renderBonds(digits) {
    if (!ctx) return;
    ctx.save();
    ctx.lineCap = "round";

    const activeDigits = digits.filter((d) => d);
    const activeSet = new Set(activeDigits);

    for (const d of activeDigits) {
        // Adult bonds
// Adult bonds
if (VISUALS.bonds && BOND_LINE_SETTINGS.enabled && d.bondedTo && activeSet.has(d.bondedTo)) {
    const other = d.bondedTo;

    // Compute fade for each digit
    const fade1 = d.age < CONFIG.oldAge
        ? 1
        : 1 - (d.age - CONFIG.oldAge) / (CONFIG.maxAge - CONFIG.oldAge);
    const fade2 = other.age < CONFIG.oldAge
        ? 1
        : 1 - (other.age - CONFIG.oldAge) / (CONFIG.maxAge - CONFIG.oldAge);

    const alpha = Math.min(fade1, fade2); // line fades if either partner is old

    ctx.strokeStyle = BOND_LINE_SETTINGS.color.replace(/[\d.]+\)$/g, `${alpha})`);
    ctx.lineWidth = BOND_LINE_SETTINGS.lineWidth;
    ctx.beginPath();
    ctx.moveTo(d.x, d.y);
    ctx.lineTo(other.x, other.y);
    ctx.stroke();
}


        // Child bonds
        if (
            VISUALS.childBonds &&
            CHILD_BOND_LINE_SETTINGS.enabled &&
            d.mother &&
            activeSet.has(d.mother) &&
            d.age < CONFIG.adolescenceAge
        ) {
            const fade = 1 - d.age / CONFIG.adolescenceAge;
            ctx.strokeStyle = `rgba(${CHILD_BOND_LINE_SETTINGS.baseColor.join(",")},${fade})`;
            ctx.lineWidth = CHILD_BOND_LINE_SETTINGS.lineWidth;
            ctx.beginPath();
            ctx.moveTo(d.x, d.y);
            ctx.lineTo(d.mother.x, d.mother.y);
            ctx.stroke();
        }
    }

    ctx.restore();
}

// ============================================================
// Render all digits + overlays
// ============================================================

export function renderAll(digits) {
    if (!ctx) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const activeSet = new Set(digits.filter((d) => d));

    for (const d of digits) if (d) renderDigit(d, activeSet);
    renderBonds(digits);
}

// ============================================================
// MODULE LOAD MESSAGE
// ============================================================

log(`[${moduleTag(import.meta)}] loaded (render module)`);

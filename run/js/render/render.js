// ============================================================
// RENDER MODULE (Canvas-Only)
// Globe Shading + Outline + Glow + Drop Shadow + Motion Blur
// + Optional Simulation Overlays (search radius, bonds, attraction)
// ============================================================

import { CONFIG } from '../config/config.js';
import { log, moduleTag } from '../utils/utilities.js';

// ============================================================
// VISUALIZATION TOGGLES
// ============================================================

export const VISUALS = {
    //shadows: { enabled: false, label: 'Shadows' },
    outlines: { enabled: false, label: 'Outlines' },
    glow: { enabled: false, label: 'Glow' },
    searchRadius: { enabled: false, label: 'Search Radius' },
    bonds: { enabled: false, label: 'Bonds' },
    childBonds: { enabled: false, label: 'Child Bonds' },
    attractionLines: { enabled: false, label: 'Attraction Lines' },
    ageIndicator: { enabled: false, label: 'Age Indicator' },
};

// ============================================================
// APPEARANCE SETTINGS
// ============================================================

const GLOBE_SETTINGS = {
    lightAngle: Math.PI / 4,
    lightDistance: 0.3,
    highlight: 0.4,
    shadow: 0.4,
};
const OUTLINE_SETTINGS = {
    maxAlpha: 0.8,
    baseWidth: 0.5,
    color: 'rgba(0,0,0,1)',
};
const SHADOW_SETTINGS = {
    offsetX: 0,
    offsetY: 0,
    blur: 20,
    color: 'rgba(0,0,0,0.3)',
};
const GLOW_SETTINGS = { strength: 20, opacity: 0.9 };
const BLUR_SETTINGS = { maxBlur: 4 };

// ============================================================
// SIMULATION OVERLAY SETTINGS
// ============================================================

const SEARCH_RADIUS_SETTINGS = {
    color: 'rgba(0,0,255,0.15)',
    lineWidth: 1,
    fillOpacity: 0.05,
};
const BOND_LINE_SETTINGS = { color: 'rgba(0,200,0,0.5)', lineWidth: 2 };
const CHILD_BOND_LINE_SETTINGS = { baseColor: [255, 165, 0], lineWidth: 1.5 };
const ATTRACTION_LINE_SETTINGS = {
    maleToFemale: { color: 'rgba(255,255,255,0.7)', width: 1 },
};

// ============================================================
// AGE INDICATOR SETTINGS
// ============================================================

const AGE_INDICATOR_SETTINGS = {
    width: 2,
    colors: {
        young: 'rgba(255,255,255,1)',
        mature: 'rgba(0,255,0,1)',
        old: 'rgba(255,0,0,1)',
    },
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
    if (d.sex === 'M') {
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

    const outlineColor = `rgba(0,0,0,${Math.min(
        opacity,
        OUTLINE_SETTINGS.maxAlpha
    )})`;
    const outlineWidth = OUTLINE_SETTINGS.baseWidth + scale * 0.5;

    const speed = Math.hypot(d.dx || 0, d.dy || 0);
    const blur = Math.min(speed / CONFIG.speed, 1) * BLUR_SETTINGS.maxBlur;

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
    canvas = document.getElementById('dslCanvas');
    if (!canvas) throw new Error('Canvas element not found: #dslCanvas');
    ctx = canvas.getContext('2d');
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

    // --- Shadow ---
    /*     if (VISUALS.shadows.enabled) {
        ctx.shadowColor = SHADOW_SETTINGS.color;
        ctx.shadowOffsetX = SHADOW_SETTINGS.offsetX;
        ctx.shadowOffsetY = SHADOW_SETTINGS.offsetY;
        ctx.shadowBlur = SHADOW_SETTINGS.blur;
    } */

    // --- Glow ---
    if (VISUALS.glow.enabled) {
        ctx.shadowColor = `rgba(${props.color.match(/\d+/g).join(',')},${
            GLOW_SETTINGS.opacity
        })`;
        ctx.shadowBlur = GLOW_SETTINGS.strength;
    }

    // --- Main circle ---
    ctx.beginPath();
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
    ctx.arc(d.x, d.y, size / 2, 0, Math.PI * 2);
    ctx.fill();
    ctx.closePath();

    ctx.shadowBlur = 0;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 0;

    // --- Outline ---
    if (VISUALS.outlines.enabled) {
        ctx.lineWidth = props.outlineWidth;
        ctx.strokeStyle = props.outlineColor;
        ctx.stroke();
    }

    // --- Age Indicator ---
    if (VISUALS.ageIndicator.enabled) {
        const r = size / 2 + props.outlineWidth * 1.2;
        const ageRatio = Math.min(d.age / CONFIG.maxAge, 1);
        const remaining = 1 - ageRatio;

        const parseRGBA = (str) => {
            const m = str.match(
                /rgba?\((\d+),\s*(\d+),\s*(\d+),?\s*([\d.]*)\)/i
            );
            return m
                ? [parseInt(m[1]), parseInt(m[2]), parseInt(m[3])]
                : [255, 255, 255];
        };

        const [rY, gY, bY] = parseRGBA(AGE_INDICATOR_SETTINGS.colors.young);
        const [rM, gM, bM] = parseRGBA(AGE_INDICATOR_SETTINGS.colors.mature);
        const [rO, gO, bO] = parseRGBA(AGE_INDICATOR_SETTINGS.colors.old);

        let rC, gC, bC;
        if (d.age < CONFIG.matureAge) [rC, gC, bC] = [rY, gY, bY];
        else if (d.age < CONFIG.oldAge) [rC, gC, bC] = [rM, gM, bM];
        else [rC, gC, bC] = [rO, gO, bO];

        ctx.save();
        ctx.beginPath();
        ctx.lineWidth = AGE_INDICATOR_SETTINGS.width;
        ctx.strokeStyle = `rgb(${rC},${gC},${bC})`;
        ctx.arc(
            d.x,
            d.y,
            r,
            -Math.PI / 2,
            -Math.PI / 2 - Math.PI * 2 * remaining,
            true
        );
        ctx.stroke();
        ctx.closePath();
        ctx.restore();
    }

    // --- Label ---
    ctx.fillStyle = '#000';
    ctx.font = `${size * 0.6}px sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(d.name, d.x, d.y);
    ctx.restore();

    // --- Overlays ---
    if (
        VISUALS.searchRadius.enabled &&
        d.sex === 'M' &&
        d.age >= CONFIG.matureAge
    ) {
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
        VISUALS.attractionLines.enabled &&
        d.sex === 'M' &&
        d.age >= CONFIG.matureAge &&
        d.age < CONFIG.oldAge &&
        d.attractionTarget
    ) {
        const target = d.attractionTarget;
        const validTarget =
            target &&
            activeSet.has(target) &&
            target.sex === 'F' &&
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
// BONDS
// ============================================================

export function renderBonds(digits) {
    if (!ctx) return;
    ctx.save();
    ctx.lineCap = 'round';
    const activeDigits = digits.filter((d) => d);
    const activeSet = new Set(activeDigits);

    for (const d of activeDigits) {
        if (VISUALS.bonds.enabled && d.bondedTo && activeSet.has(d.bondedTo)) {
            const other = d.bondedTo;
            const fade1 =
                d.age < CONFIG.oldAge
                    ? 1
                    : 1 -
                      (d.age - CONFIG.oldAge) / (CONFIG.maxAge - CONFIG.oldAge);
            const fade2 =
                other.age < CONFIG.oldAge
                    ? 1
                    : 1 -
                      (other.age - CONFIG.oldAge) /
                          (CONFIG.maxAge - CONFIG.oldAge);
            const alpha = Math.min(fade1, fade2);

            ctx.strokeStyle = BOND_LINE_SETTINGS.color.replace(
                /[\d.]+\)$/g,
                `${alpha})`
            );
            ctx.lineWidth = BOND_LINE_SETTINGS.lineWidth;
            ctx.beginPath();
            ctx.moveTo(d.x, d.y);
            ctx.lineTo(other.x, other.y);
            ctx.stroke();
        }

        if (
            VISUALS.childBonds.enabled &&
            d.mother &&
            activeSet.has(d.mother) &&
            d.age < CONFIG.adolescenceAge
        ) {
            const fade = 1 - d.age / CONFIG.adolescenceAge;
            ctx.strokeStyle = `rgba(${CHILD_BOND_LINE_SETTINGS.baseColor.join(
                ','
            )},${fade})`;
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
// RENDER ALL
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

// ============================================================
// RENDER MODULE (Canvas-Only)
// ============================================================
// Handles all visual rendering for the digit simulation including:
// - Globe-shaded digit rendering with lighting effects
// - Visual overlays (outlines, glow, shadows, motion blur)
// - Simulation overlays (search radius, bonds, attraction lines)
// - Age indicators and relationship visualization
// ============================================================

import { CONFIG } from '../config/config.js';
import { log, moduleTag } from '../utils/utilities.js';

// ============================================================
// VISUALIZATION TOGGLES
// ============================================================
// These control which visual features are enabled/disabled
// Can be toggled dynamically during runtime

export const VISUALS = {
    //shadows: { enabled: false, label: 'Shadows' },
    outlines: { enabled: false, label: 'Outlines' },
    glow: { enabled: true, label: 'Glow' },
    searchRadius: { enabled: false, label: 'Search Radius' },
    bonds: { enabled: false, label: 'Bonds' },
    childBonds: { enabled: false, label: 'Child Bonds' },
    attractionLines: { enabled: false, label: 'Attraction Lines' },
    ageIndicator: { enabled: false, label: 'Age Indicator' },
};

// ============================================================
// APPEARANCE SETTINGS
// ============================================================

// Globe shading: creates 3D sphere effect with light source
const GLOBE_SETTINGS = {
    lightAngle: Math.PI / 4, // Direction of light source (45 degrees)
    lightDistance: 0.3, // Distance of highlight from center
    highlight: 0.4, // Brightness increase for lit areas
    shadow: 0.4, // Darkness increase for shadowed areas
};

// Outline rendering around each digit
const OUTLINE_SETTINGS = {
    maxAlpha: 0.8, // Maximum outline opacity
    baseWidth: 0.5, // Base outline thickness
    color: 'rgba(0,0,0,1)', // Outline color (black)
};

// Drop shadow effect (currently disabled)
const SHADOW_SETTINGS = {
    offsetX: 0, // Horizontal shadow offset
    offsetY: 0, // Vertical shadow offset
    blur: 20, // Shadow blur radius
    color: 'rgba(0,0,0,0.3)', // Shadow color and opacity
};

// Glow effect around digits
const GLOW_SETTINGS = {
    strength: 20, // Glow blur radius
    opacity: 0.9, // Glow opacity
};

// Motion blur based on digit speed
const BLUR_SETTINGS = {
    maxBlur: 4, // Maximum blur amount at full speed
};

// ============================================================
// SIMULATION OVERLAY SETTINGS
// ============================================================

// Search radius visualization (attraction range for males)
const SEARCH_RADIUS_SETTINGS = {
    color: 'rgba(0,0,255,0.15)', // Border color
    lineWidth: 1, // Border thickness
    fillOpacity: 0.05, // Fill transparency
};

// Bond lines between mated pairs
const BOND_LINE_SETTINGS = {
    color: 'rgba(0,200,0,0.5)', // Green line color
    lineWidth: 2, // Line thickness
};

// Bond lines between children and mothers
const CHILD_BOND_LINE_SETTINGS = {
    baseColor: [255, 165, 0], // Orange RGB values
    lineWidth: 1.5, // Line thickness
};

// Attraction lines from males to potential mates
const ATTRACTION_LINE_SETTINGS = {
    maleToFemale: {
        color: 'rgba(255,255,255,0.7)', // White semi-transparent
        width: 1, // Line thickness
    },
};

// ============================================================
// AGE INDICATOR SETTINGS
// ============================================================
// Circular progress indicator showing remaining lifespan

const AGE_INDICATOR_SETTINGS = {
    width: 2, // Ring thickness
    colors: {
        young: 'rgba(255,255,255,1)', // White for young
        mature: 'rgba(0,255,0,1)', // Green for mature
        old: 'rgba(255,0,0,1)', // Red for old
    },
};

// ============================================================
// MODULE STATE
// ============================================================
// Canvas and context references, initialized on startup

let canvas = null;
let ctx = null;

// ============================================================
// INITIALIZATION
// ============================================================

/**
 * Initialize the canvas renderer
 * Must be called before any rendering operations
 * @returns {Object} Object containing canvas and context references
 * @throws {Error} If canvas element is not found
 */
export function initCanvasRenderer() {
    // Get canvas element from DOM
    canvas = document.getElementById('dslCanvas');
    if (!canvas) {
        throw new Error('Canvas element not found: #dslCanvas');
    }

    // Get 2D rendering context
    ctx = canvas.getContext('2d');

    log(`[${moduleTag(import.meta)}] Canvas renderer initialized`);

    return { canvas, ctx };
}

// ============================================================
// APPEARANCE CALCULATION
// ============================================================

/**
 * Calculate all visual properties for a digit based on its state
 * Handles age-based scaling, color transitions, opacity fading, and lighting
 * @param {Object} d - Digit object to calculate appearance for
 * @returns {Object} Visual properties (scale, colors, opacity, etc.)
 */
export function updateDigitAppearance(d) {
    // Scale: grows from 30% to 100% as digit matures
    const scale = 0.3 + Math.min(d.age / CONFIG.matureAge, 1) * 0.7;

    // Opacity: full until old age, then fades to 0 at death
    const opacity =
        d.age < CONFIG.oldAge
            ? 1
            : 1 - (d.age - CONFIG.oldAge) / (CONFIG.maxAge - CONFIG.oldAge);

    // Color transition: shifts as digit matures (0-1 range)
    const t = Math.min(d.age / CONFIG.matureAge, 1);

    // Calculate base color based on sex and maturity
    let r, g, b;
    if (d.sex === 'M') {
        // Males: light blue (255,255,255) → darker blue (100,160,255)
        r = (255 + t * (100 - 255)) | 0;
        g = (255 + t * (160 - 255)) | 0;
        b = 255;
    } else {
        // Females: white (255,255,255) → pink (255,100,100)
        r = 255;
        g = (255 + t * (100 - 255)) | 0;
        b = (255 + t * (100 - 255)) | 0;
    }

    // Create color strings for base, highlight, and shadow
    const baseColor = `rgb(${r},${g},${b})`;

    const lighten = (c) => Math.min(255, c + 255 * GLOBE_SETTINGS.highlight);
    const darken = (c) => Math.max(0, c * (1 - GLOBE_SETTINGS.shadow));

    const highlight = `rgb(${lighten(r)},${lighten(g)},${lighten(b)})`;
    const shadow = `rgb(${darken(r)},${darken(g)},${darken(b)})`;

    // Outline properties
    const outlineColor = `rgba(0,0,0,${Math.min(
        opacity,
        OUTLINE_SETTINGS.maxAlpha
    )})`;
    const outlineWidth = OUTLINE_SETTINGS.baseWidth + scale * 0.5;

    // Motion blur based on velocity
    const speed = Math.hypot(d.dx || 0, d.dy || 0);
    const blur = Math.min(speed / CONFIG.speed, 1) * BLUR_SETTINGS.maxBlur;

    // Calculate light source position for gradient
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
// VISUAL OVERLAY FUNCTIONS
// ============================================================

/**
 * Render glow effect around a digit
 * @param {Object} props - Visual properties from updateDigitAppearance
 */
function renderGlow(props) {
    if (!VISUALS.glow.enabled) return;

    ctx.shadowColor = `rgba(${props.color.match(/\d+/g).join(',')},${
        GLOW_SETTINGS.opacity
    })`;
    ctx.shadowBlur = GLOW_SETTINGS.strength;
}

/**
 * Render outline around a digit
 * @param {Object} props - Visual properties from updateDigitAppearance
 */
function renderOutline(props) {
    if (!VISUALS.outlines.enabled) return;

    ctx.lineWidth = props.outlineWidth;
    ctx.strokeStyle = props.outlineColor;
    ctx.stroke();
}

/**
 * Render age indicator ring around a digit
 * Shows remaining lifespan as a circular progress bar
 * @param {Object} d - Digit object
 * @param {number} size - Digit size
 * @param {Object} props - Visual properties
 */
function renderAgeIndicator(d, size, props) {
    if (!VISUALS.ageIndicator.enabled) return;

    // Calculate ring radius (outside outline)
    const r = size / 2 + props.outlineWidth * 1.2;

    // Calculate age progression and remaining life
    const ageRatio = Math.min(d.age / CONFIG.maxAge, 1);
    const remaining = 1 - ageRatio;

    // Helper to parse RGB from rgba/rgb string
    const parseRGBA = (str) => {
        const m = str.match(/rgba?\((\d+),\s*(\d+),\s*(\d+),?\s*([\d.]*)\)/i);
        return m
            ? [parseInt(m[1]), parseInt(m[2]), parseInt(m[3])]
            : [255, 255, 255];
    };

    // Get color values for different life stages
    const [rY, gY, bY] = parseRGBA(AGE_INDICATOR_SETTINGS.colors.young);
    const [rM, gM, bM] = parseRGBA(AGE_INDICATOR_SETTINGS.colors.mature);
    const [rO, gO, bO] = parseRGBA(AGE_INDICATOR_SETTINGS.colors.old);

    // Select color based on current life stage
    let rC, gC, bC;
    if (d.age < CONFIG.matureAge) {
        [rC, gC, bC] = [rY, gY, bY];
    } else if (d.age < CONFIG.oldAge) {
        [rC, gC, bC] = [rM, gM, bM];
    } else {
        [rC, gC, bC] = [rO, gO, bO];
    }

    // Draw arc showing remaining life (counter-clockwise from top)
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

/**
 * Render search radius circle for mature males
 * Shows the range in which males can detect potential mates
 * @param {Object} d - Digit object
 */
function renderSearchRadius(d) {
    if (!VISUALS.searchRadius.enabled) return;
    if (d.sex !== 'M' || d.age < CONFIG.matureAge) return;

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

/**
 * Render attraction line from male to potential mate
 * Shows active pursuit behavior
 * @param {Object} d - Digit object (male)
 * @param {Set} activeSet - Set of all active digits for validation
 */
function renderAttractionLine(d, activeSet) {
    if (!VISUALS.attractionLines.enabled) return;
    if (d.sex !== 'M') return;
    if (d.age < CONFIG.matureAge || d.age >= CONFIG.oldAge) return;
    if (!d.attractionTarget) return;

    const target = d.attractionTarget;

    // Validate target is still viable
    const validTarget =
        target &&
        activeSet.has(target) &&
        target.sex === 'F' &&
        target.age >= CONFIG.matureAge &&
        target.age < CONFIG.oldAge &&
        target.gestationTimer === 0 &&
        !target.bondedTo;

    if (!validTarget) return;

    // Draw line from male to female
    ctx.save();
    ctx.strokeStyle = ATTRACTION_LINE_SETTINGS.maleToFemale.color;
    ctx.lineWidth = ATTRACTION_LINE_SETTINGS.maleToFemale.width;
    ctx.beginPath();
    ctx.moveTo(d.x, d.y);
    ctx.lineTo(target.x, target.y);
    ctx.stroke();
    ctx.restore();
}

// ============================================================
// DIGIT RENDERING
// ============================================================

/**
 * Render a single digit with all visual effects
 * Includes globe shading, outlines, glow, and overlays
 * @param {Object} d - Digit object to render
 * @param {Set} activeSet - Set of all active digits for overlay validation
 */
export function renderDigit(d, activeSet) {
    // Calculate all visual properties
    const props = updateDigitAppearance(d);
    const size = CONFIG.digitSize * props.scale;

    ctx.save();
    ctx.globalAlpha = props.opacity;

    // Apply glow effect (must be set before drawing)
    renderGlow(props);

    // Draw main circle with radial gradient (globe shading)
    ctx.beginPath();
    const g = ctx.createRadialGradient(
        props.lx, // Highlight center X
        props.ly, // Highlight center Y
        size * 0.1, // Inner radius (small bright spot)
        d.x, // Outer center X
        d.y, // Outer center Y
        size / 2 // Outer radius (full digit size)
    );
    g.addColorStop(0, props.highlight); // Bright center
    g.addColorStop(0.3, props.color); // Base color
    g.addColorStop(1, props.shadow); // Dark edge
    ctx.fillStyle = g;
    ctx.arc(d.x, d.y, size / 2, 0, Math.PI * 2);
    ctx.fill();
    ctx.closePath();

    // Clear shadow settings before outline
    ctx.shadowBlur = 0;
    ctx.shadowOffsetX = 0;
    ctx.shadowOffsetY = 0;

    // Draw outline around circle
    renderOutline(props);

    // Draw age indicator ring
    renderAgeIndicator(d, size, props);

    // Draw digit name/number in center
    ctx.fillStyle = '#000';
    ctx.font = `${size * 0.6}px sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(d.name, d.x, d.y);

    ctx.restore();

    // Draw simulation overlays (not affected by digit opacity)
    renderSearchRadius(d);
    renderAttractionLine(d, activeSet);
}

// ============================================================
// BOND RENDERING
// ============================================================

/**
 * Render all bond lines between digits
 * Includes mate bonds (green) and child-mother bonds (orange)
 * @param {Array} digits - Array of all digits
 */
export function renderBonds(digits) {
    if (!ctx) return;

    ctx.save();
    ctx.lineCap = 'round';

    // Filter to active digits only
    const activeDigits = digits.filter((d) => d);
    const activeSet = new Set(activeDigits);

    for (const d of activeDigits) {
        // Render mate bonds (between bonded pairs)
        if (VISUALS.bonds.enabled && d.bondedTo && activeSet.has(d.bondedTo)) {
            const other = d.bondedTo;

            // Calculate fade based on both digits' ages
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

            // Draw bond line with fade
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

        // Render child-mother bonds (for young digits)
        if (
            VISUALS.childBonds.enabled &&
            d.mother &&
            activeSet.has(d.mother) &&
            d.age < CONFIG.adolescenceAge
        ) {
            // Fade out as child reaches adolescence
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
// MAIN RENDER FUNCTION
// ============================================================

/**
 * Render all digits and their bonds
 * This is the main entry point for each frame
 * @param {Array} digits - Array of all digits to render
 */
export function renderAll(digits) {
    if (!ctx) return;

    // Clear previous frame
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // Create set of active digits for efficient lookup in overlays
    const activeSet = new Set(digits.filter((d) => d));

    // Render all individual digits
    for (const d of digits) {
        if (d) renderDigit(d, activeSet);
    }

    // Render bond lines on top of all digits
    renderBonds(digits);
}

// ============================================================
// MODULE LOAD MESSAGE
// ============================================================

log(`[${moduleTag(import.meta)}] loaded (render module)`);

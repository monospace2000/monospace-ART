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
import { handleBall, handleBubble, handleFlat } from '../ui/appearance.js';
import {
    getCachedAppearance,
    clearAppearanceCache,
} from '../model/movement.js';
import { getActiveDigits } from '../control/controls.js';

// ============================================================
// VISUALIZATION TOGGLES
// ============================================================
// Controls which visual features are enabled/disabled
// Can be toggled dynamically during runtime

export const VISUALS = {
    shadows: { enabled: false, label: 'Shadows' },
    glow: { enabled: false, label: 'Glow' },
    outlines: { enabled: false, label: 'Outlines' },
    searchRadius: { enabled: false, label: 'Search Radius' },
    bonds: { enabled: false, label: 'Bonds' },
    childBonds: { enabled: false, label: 'Child Bonds' },
    attractionLines: { enabled: false, label: 'Attraction Lines' },
    ageIndicator: { enabled: false, label: 'Age Indicator' },
};

// ============================================================
// APPEARANCE SETTINGS
// ============================================================
// Centralized visual style configuration

// Text rendering configuration
const TEXT_SETTINGS = {
    color: 'rgba(255, 255, 255, 1)',
    font: 'sans-serif',
};

// Globe shading: creates 3D sphere effect with light source
const GLOBE_SETTINGS = {
    lightAngle: Math.PI / 4, // Direction of light source (45 degrees)
    lightDistance: 0.3, // Distance of highlight from center (0.0-1.0)
    highlight: 0.4, // Brightness increase for lit areas (0.0-1.0)
    shadow: 0.4, // Darkness increase for shadowed areas (0.0-1.0)
    transparency: 1.0, // Globe transparency (1.0=opaque, 0.0=invisible)
    // Glass effect uses gradient opacity: highlights opaque, shadows transparent
    highlightOpacity: 1.0, // Opacity multiplier for highlight (typically 1.0)
    midtoneOpacity: 0.6, // Opacity multiplier for midtone (0.5-0.8 for glass)
    shadowOpacity: 0.2, // Opacity multiplier for shadow (0.1-0.3 for glass)
};

// Outline rendering around each digit
const OUTLINE_SETTINGS = {
    maxAlpha: 0.8, // Maximum outline opacity
    baseWidth: 0.5, // Base outline thickness in pixels
    color: 'rgba(255,255,255,0.5)', // Default outline color
};

// Drop shadow effect (rendered behind digit)
const SHADOW_SETTINGS = {
    color: 'rgba(0,0,0,5)', // Shadow tint color
    offsetX: 1, // Horizontal offset in pixels
    offsetY: 1.5, // Vertical offset in pixels
    blur: 4, // Blur radius in pixels
    spread: 0.3, // How much shadow expands outward
    opacity: 0.3, // Overall shadow opacity
    layers: 1, // Number of shadow layers (1 recommended)
};

// Glow effect around digits (rendered behind digit)
const GLOW_SETTINGS = {
    strength: 20, // Glow radius in pixels
    opacity: 0.9, // Glow opacity (0.0-1.0)
    layers: 1, // Number of glow layers for intensity (1-5 recommended)
    colorBoost: 1.2, // Color intensity multiplier (1.0=normal, >1.0=vibrant)
};

// Search radius visualization (attraction range for males)
const SEARCH_RADIUS_SETTINGS = {
    color: 'rgba(0,0,255,0.15)', // Border color
    lineWidth: 1, // Border thickness in pixels
    fillOpacity: 0.05, // Fill transparency
};

// Bond lines between mated pairs
const BOND_LINE_SETTINGS = {
    color: 'rgba(0,200,0,0.2)', // Green line color
    lineWidth: 1, // Line thickness in pixels
};

// Bond lines between children and mothers
const CHILD_BOND_LINE_SETTINGS = {
    color: 'rgba(255, 165, 0, 0.3)', // Orange line color
    lineWidth: 1.5, // Line thickness in pixels
};

// Attraction lines from males to potential mates
const ATTRACTION_LINE_SETTINGS = {
    maleToFemale: {
        color: 'rgba(255,255,255,0.7)', // White semi-transparent
        width: 1, // Line thickness in pixels
    },
};

// Age indicator ring configuration
const AGE_INDICATOR_SETTINGS = {
    width: 2, // Ring thickness in pixels
    colors: {
        young: 'rgba(255,255,255,1)', // White for young
        mature: 'rgba(0,255,0,1)', // Green for mature
        old: 'rgba(255,0,0,1)', // Red for old
    },
};

// Motion blur configuration (based on digit velocity)
const BLUR_SETTINGS = {
    maxBlur: 4, // Maximum blur amount at full speed
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
// APPEARANCE PRESETS
// ============================================================
// Three preset styles that configure multiple visual settings at once

/**
 * Configure settings for "ball" appearance
 * Creates solid, opaque spheres with strong shading
 */
export function ball() {
    GLOBE_SETTINGS.highlightOpacity = 1;
    GLOBE_SETTINGS.midtoneOpacity = 1;
    GLOBE_SETTINGS.shadowOpacity = 1;
    GLOBE_SETTINGS.highlight = 0.3;
    GLOBE_SETTINGS.shadow = 0.7;
    OUTLINE_SETTINGS.color = 'rgba(0,0,0,0.5)';
    TEXT_SETTINGS.color = 'rgba(0, 0, 0, 0.7)';
    VISUALS.outlines.enabled = true;
    VISUALS.glow.enabled = false;
    VISUALS.shadows.enabled = true;
}

/**
 * Configure settings for "bubble" appearance
 * Creates translucent glass-like spheres with glow
 */
export function bubble() {
    GLOBE_SETTINGS.highlightOpacity = 1;
    GLOBE_SETTINGS.midtoneOpacity = 0.6;
    GLOBE_SETTINGS.shadowOpacity = 0.2;
    OUTLINE_SETTINGS.color = 'rgba(255,255,255,0.5)';
    TEXT_SETTINGS.color = 'rgba(255, 255, 255, 1)';
    VISUALS.outlines.enabled = true;
    VISUALS.glow.enabled = true;
    VISUALS.shadows.enabled = false;
}

/**
 * Configure settings for "flat" appearance
 * Creates simple flat circles without shading effects
 */
export function flat() {
    GLOBE_SETTINGS.highlightOpacity = 1;
    GLOBE_SETTINGS.midtoneOpacity = 1;
    GLOBE_SETTINGS.shadowOpacity = 1;
    GLOBE_SETTINGS.highlight = 0;
    GLOBE_SETTINGS.shadow = 0;
    OUTLINE_SETTINGS.color = 'rgba(0,0,0,0.5)';
    TEXT_SETTINGS.color = 'rgba(0, 0, 0, 0.7)';
    VISUALS.outlines.enabled = false;
    VISUALS.shadows.enabled = false;
    VISUALS.glow.enabled = false;
}

/**
 * Select an appearance preset by name
 * @param {string} preset - Name of preset ('ball', 'bubble', or 'flat')
 * @returns {string} The preset name that was applied
 */
export function setAppearance(preset) {
    if (preset === 'bubble') {
        handleBubble();
        return 'bubble';
    } else if (preset === 'ball') {
        handleBall();
        return 'ball';
    } else {
        handleFlat();
        return 'flat';
    }
}

// ============================================================
// COLOR UTILITIES
// ============================================================
// Helper functions for color manipulation

/**
 * Lighten a color value by adding to it
 * @param {number} c - Color component (0-255)
 * @returns {number} Lightened color component, clamped to 255
 */
function lightenColor(c) {
    return Math.min(255, c + 255 * GLOBE_SETTINGS.highlight);
}

/**
 * Darken a color value by multiplying it
 * @param {number} c - Color component (0-255)
 * @returns {number} Darkened color component, clamped to 0
 */
function darkenColor(c) {
    return Math.max(0, c * (1 - GLOBE_SETTINGS.shadow));
}

/**
 * Parse RGB values from an rgba() or rgb() color string
 * @param {string} str - Color string in format 'rgba(r,g,b,a)' or 'rgb(r,g,b)'
 * @returns {Array<number>} Array of [r, g, b] values
 */
function parseRGB(str) {
    const match = str.match(/\d+/g);
    return match ? match.slice(0, 3).map(Number) : [255, 255, 255];
}

/**
 * Parse alpha value from an rgba() color string
 * @param {string} str - Color string in format 'rgba(r,g,b,a)'
 * @returns {number} Alpha value (0.0-1.0), defaults to 1.0 if not found
 */
function parseAlpha(str) {
    const match = str.match(/[\d.]+\)$/);
    return match ? parseFloat(match[0]) : 1.0;
}

// ============================================================
// APPEARANCE CALCULATION
// ============================================================

/**
 * Calculate base color (RGB) for a digit based on sex and maturity
 * Males: white → blue as they mature
 * Females: white → pink as they mature
 * @param {Object} d - Digit object
 * @param {number} maturityRatio - Maturity progress (0.0-1.0)
 * @returns {Object} RGB color components {r, g, b}
 */
function calculateDigitColor(d, maturityRatio) {
    let r, g, b;

    if (d.sex === 'M') {
        // Males: light blue (255,255,255) → darker blue (100,160,255)
        r = Math.floor(255 + maturityRatio * (100 - 255));
        g = Math.floor(255 + maturityRatio * (160 - 255));
        b = 255;
    } else {
        // Females: white (255,255,255) → pink (255,100,100)
        r = 255;
        g = Math.floor(255 + maturityRatio * (100 - 255));
        b = Math.floor(255 + maturityRatio * (100 - 255));
    }

    return { r, g, b };
}

/**
 * Calculate opacity for a digit based on age
 * Full opacity until old age, then fades out until death
 * @param {Object} d - Digit object
 * @returns {number} Opacity value (0.0-1.0)
 */
function calculateOpacity(d) {
    if (d.age < CONFIG.oldAge) {
        return 1.0;
    }

    // Cubic fade for smoother transition
    const ageProgress = (d.age - CONFIG.oldAge) / (d.maxAge - CONFIG.oldAge);
    return Math.pow(1 - ageProgress, 3);
}

/**
 * Calculate scale for a digit based on age
 * Grows from 30% to 100% as digit matures
 * @param {Object} d - Digit object
 * @returns {number} Scale multiplier (0.3-1.0)
 */
function calculateScale(d) {
    const maturityRatio = Math.min(d.age / CONFIG.matureAge, 1);
    return 0.3 + maturityRatio * 0.7;
}

/**
 * Calculate all visual properties for a digit based on its state
 * Handles age-based scaling, color transitions, opacity fading, and lighting
 * @param {Object} d - Digit object to calculate appearance for
 * @returns {Object} Visual properties (scale, colors, opacity, etc.)
 */
export function updateDigitAppearance(d) {
    // Calculate basic age-based properties
    const scale = calculateScale(d);
    const opacity = calculateOpacity(d);
    const maturityRatio = Math.min(d.age / CONFIG.matureAge, 1);

    // Calculate base color based on sex and maturity
    const { r, g, b } = calculateDigitColor(d, maturityRatio);

    // Create color strings for base, highlight, and shadow
    const baseColor = `rgb(${r},${g},${b})`;
    const highlightColor = `rgb(${lightenColor(r)},${lightenColor(
        g
    )},${lightenColor(b)})`;
    const shadowColor = `rgb(${darkenColor(r)},${darkenColor(g)},${darkenColor(
        b
    )})`;

    // Calculate gradient opacities for glass effect
    // Age-based opacity affects all parts, then glass settings create gradient
    const baseOpacity = opacity * GLOBE_SETTINGS.transparency;
    const highlightAlpha = baseOpacity * GLOBE_SETTINGS.highlightOpacity;
    const midtoneAlpha = baseOpacity * GLOBE_SETTINGS.midtoneOpacity;
    const shadowAlpha = baseOpacity * GLOBE_SETTINGS.shadowOpacity;

    // Convert RGB colors to RGBA with varying opacity for glass effect
    const highlightRGBA = `rgba(${lightenColor(r)},${lightenColor(
        g
    )},${lightenColor(b)},${highlightAlpha})`;
    const midtoneRGBA = `rgba(${r},${g},${b},${midtoneAlpha})`;
    const shadowRGBA = `rgba(${darkenColor(r)},${darkenColor(g)},${darkenColor(
        b
    )},${shadowAlpha})`;

    // Calculate outline properties
    const outlineRGB = parseRGB(OUTLINE_SETTINGS.color);
    const outlineSettingsAlpha = parseAlpha(OUTLINE_SETTINGS.color);
    const outlineColor = `rgba(${outlineRGB[0]},${outlineRGB[1]},${
        outlineRGB[2]
    },${Math.min(opacity * outlineSettingsAlpha, OUTLINE_SETTINGS.maxAlpha)})`;
    const outlineWidth = OUTLINE_SETTINGS.baseWidth + scale * 0.5;

    // Calculate motion blur based on velocity
    const speed = Math.hypot(d.dx || 0, d.dy || 0);
    const blur = Math.min(speed / CONFIG.speed, 1) * BLUR_SETTINGS.maxBlur;

    // Calculate light source position for gradient
    const size = CONFIG.digitSize * scale;
    const lx =
        d.x -
        Math.cos(GLOBE_SETTINGS.lightAngle) *
            GLOBE_SETTINGS.lightDistance *
            size;
    const ly =
        d.y -
        Math.sin(GLOBE_SETTINGS.lightAngle) *
            GLOBE_SETTINGS.lightDistance *
            size;

    return {
        scale,
        textColor: TEXT_SETTINGS.color,
        font: TEXT_SETTINGS.font,
        color: baseColor,
        opacity,
        highlight: highlightColor,
        shadow: shadowColor,
        highlightRGBA,
        midtoneRGBA,
        shadowRGBA,
        outlineColor,
        outlineWidth,
        blur,
        lx,
        ly,
    };
}

// ============================================================
// EFFECT RENDERING (GLOW AND SHADOW)
// ============================================================
// Both glow and shadow use similar ring-based rendering technique

/**
 * Render concentric rings of color around a digit
 * Used by both glow and shadow effects
 * @param {Object} d - Digit object
 * @param {number} size - Digit size
 * @param {Object} settings - Effect settings (strength, opacity, layers)
 * @param {Array<number>} rgb - RGB color array [r, g, b]
 * @param {number} baseOpacity - Base opacity from digit appearance
 * @param {number} offsetX - Horizontal offset for rings (0 for glow, offset for shadow)
 * @param {number} offsetY - Vertical offset for rings (0 for glow, offset for shadow)
 */
function renderRingEffect(
    d,
    size,
    settings,
    rgb,
    baseOpacity,
    offsetX = 0,
    offsetY = 0
) {
    const baseRadius = size / 2;
    const scaleFactor = size / CONFIG.digitSize;
    const rings = 15; // Number of concentric rings to draw

    ctx.save();
    ctx.globalCompositeOperation = 'destination-over'; // Render behind digit

    // Draw multiple layers for intensity
    for (let layer = 0; layer < settings.layers; layer++) {
        // Draw each ring from inner to outer
        for (let i = 0; i < rings; i++) {
            const progress = i / rings; // 0.0 at center, 1.0 at edge

            // Calculate outer radius (expands outward)
            const outerRadius =
                baseRadius + settings.strength * progress * scaleFactor;

            // Calculate alpha (fades to transparent at edge)
            const alpha =
                (settings.opacity * (1 - progress) * baseOpacity) / rings;

            // Draw ring as donut shape (outer circle minus inner circle)
            ctx.fillStyle = `rgba(${rgb[0]},${rgb[1]},${rgb[2]},${alpha})`;
            ctx.beginPath();
            ctx.arc(d.x + offsetX, d.y + offsetY, outerRadius, 0, Math.PI * 2);
            ctx.arc(d.x, d.y, baseRadius, 0, Math.PI * 2, true); // Subtract inner
            ctx.closePath();
            ctx.fill('evenodd');
        }
    }

    ctx.restore();
}

/**
 * Render glow effect around a digit
 * @param {Object} d - Digit object
 * @param {number} size - Digit size
 */
function renderGlow(d, size) {
    if (!VISUALS.glow.enabled) return;

    // Get digit color and boost it for glow
    const props = updateDigitAppearance(d);
    const rgb = parseRGB(props.color);
    const boostedRGB = rgb.map((c) =>
        Math.min(255, Math.floor(c * GLOW_SETTINGS.colorBoost))
    );

    // Render glow using ring effect (centered on digit)
    renderRingEffect(d, size, GLOW_SETTINGS, boostedRGB, props.opacity);
}

/**
 * Render drop shadow effect around a digit
 * @param {Object} d - Digit object
 * @param {number} size - Digit size
 */
function renderDropShadow(d, size) {
    if (!VISUALS.shadows.enabled) return;

    // Get shadow color
    const props = updateDigitAppearance(d);
    const rgb = parseRGB(SHADOW_SETTINGS.color);

    // Calculate scaled offsets
    const scaleFactor = size / CONFIG.digitSize;
    const offsetX = SHADOW_SETTINGS.offsetX * scaleFactor;
    const offsetY = SHADOW_SETTINGS.offsetY * scaleFactor;

    // Render shadow using ring effect (offset from digit)
    renderRingEffect(
        d,
        size,
        {
            strength: SHADOW_SETTINGS.blur,
            opacity: SHADOW_SETTINGS.opacity,
            layers: SHADOW_SETTINGS.layers,
        },
        rgb,
        props.opacity,
        offsetX,
        offsetY
    );
}

// ============================================================
// DIGIT OVERLAY RENDERING
// ============================================================
// Additional visual elements drawn on or around the digit

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

    // Calculate ring radius (just outside outline)
    const ringRadius = size / 2 + props.outlineWidth * 1.2;

    // Calculate remaining life percentage
    const ageRatio = Math.min(d.age / d.maxAge, 1);
    const remaining = 1 - ageRatio;

    // Determine color based on life stage
    let colorKey;
    if (d.age < CONFIG.matureAge) {
        colorKey = 'young';
    } else if (d.age < CONFIG.oldAge) {
        colorKey = 'mature';
    } else {
        colorKey = 'old';
    }

    // Parse color and apply opacity
    const [r, g, b] = parseRGB(AGE_INDICATOR_SETTINGS.colors[colorKey]);

    // Draw arc showing remaining life (counter-clockwise from top)
    ctx.save();
    ctx.beginPath();
    ctx.lineWidth = AGE_INDICATOR_SETTINGS.width;
    ctx.strokeStyle = `rgba(${r},${g},${b},${props.opacity})`;
    ctx.arc(
        d.x,
        d.y,
        ringRadius,
        -Math.PI / 2, // Start at top
        -Math.PI / 2 - Math.PI * 2 * remaining, // Sweep counter-clockwise
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

    // Validate target is still viable for mating
    const isValidTarget =
        target &&
        activeSet.has(target) &&
        target.sex === 'F' &&
        target.age >= CONFIG.matureAge &&
        target.age < CONFIG.oldAge &&
        target.gestationTimer === 0 &&
        !target.bondedTo;

    if (!isValidTarget) return;

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
    const props = getCachedAppearance(d);
    const size = CONFIG.digitSize * props.scale;

    // Skip rendering if opacity is too low (prevents visual artifacts)
    if (props.opacity < 0.01) return;

    // Render external effects first (behind main digit)
    renderGlow(d, size);
    renderDropShadow(d, size);

    ctx.save();

    // Draw main circle with radial gradient (globe shading with glass effect)
    ctx.beginPath();
    const gradient = ctx.createRadialGradient(
        props.lx, // Highlight center X
        props.ly, // Highlight center Y
        size * 0.1, // Inner radius (small bright spot)
        d.x, // Outer center X
        d.y, // Outer center Y
        size / 2 // Outer radius (full digit size)
    );

    // Glass effect: opaque highlight → semi-transparent midtone → very transparent shadow
    gradient.addColorStop(0, props.highlightRGBA); // Bright center (most opaque)
    gradient.addColorStop(0.3, props.midtoneRGBA); // Base color (medium opacity)
    gradient.addColorStop(1, props.shadowRGBA); // Dark edge (most transparent)

    ctx.fillStyle = gradient;
    ctx.arc(d.x, d.y, size / 2, 0, Math.PI * 2);
    ctx.fill();
    ctx.closePath();

    // Draw outline around circle
    renderOutline(props);

    // Draw age indicator ring
    renderAgeIndicator(d, size, props);

    // Draw digit name/number in center
    ctx.globalAlpha = props.opacity;
    ctx.fillStyle = props.textColor;
    ctx.font = `${size * 0.6}px ${props.font}`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(d.name, d.x, d.y);

    ctx.restore();

    // Draw simulation overlays (not affected by globe transparency)
    renderSearchRadius(d);
    renderAttractionLine(d, activeSet);
}

// ============================================================
// BOND RENDERING
// ============================================================

/**
 * Calculate fade factor for a digit based on age
 * Used to fade out bonds as digits age
 * @param {Object} d - Digit object
 * @returns {number} Fade factor (0.0-1.0)
 */
function calculateBondFade(d) {
    if (d.age < CONFIG.oldAge) {
        return 1.0;
    }
    return 1 - (d.age - CONFIG.oldAge) / (d.maxAge - CONFIG.oldAge);
}

/**
 * Render all bond lines between digits
 * Includes mate bonds (green) and child-mother bonds (orange)
 * @param {Array} digits - Array of all digits
 */
export function renderBonds(digits, activeSet) {
    if (!ctx) return;

    // Safety check: ensure we have an activeSet
    if (!activeSet) {
        console.warn('renderBonds called without activeSet, creating one');
        activeSet = new Set(digits.filter((d) => d));
    }

    ctx.save();
    ctx.lineCap = 'round';

    for (const d of digits) {
        if (!d) continue; // Safety check in case nulls slip through

        // Render mate bonds (green lines between bonded pairs)
        if (VISUALS.bonds.enabled && d.bondedTo && activeSet.has(d.bondedTo)) {
            const partner = d.bondedTo;

            // Calculate fade based on both partners' ages
            const fade1 = calculateBondFade(d);
            const fade2 = calculateBondFade(partner);
            const alpha = Math.min(fade1, fade2);

            // Draw bond line with age-based fading
            ctx.strokeStyle = BOND_LINE_SETTINGS.color.replace(
                /[\d.]+\)$/,
                `${alpha})`
            );
            ctx.lineWidth = BOND_LINE_SETTINGS.lineWidth;
            ctx.beginPath();
            ctx.moveTo(d.x, d.y);
            ctx.lineTo(partner.x, partner.y);
            ctx.stroke();
        }

        // Render child-mother bonds (orange lines that fade as child grows)
        if (
            VISUALS.childBonds.enabled &&
            d.mother &&
            activeSet.has(d.mother) &&
            d.age < CONFIG.adolescenceAge
        ) {
            // Fade bond as child approaches adolescence
            const fade = 1 - d.age / CONFIG.adolescenceAge;

            // Draw bond line with age-based fading
            ctx.strokeStyle = CHILD_BOND_LINE_SETTINGS.color.replace(
                /[\d.]+\)$/,
                `${fade})`
            );
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
 * @param {Array} digits - Array of active digits to render
 * @param {Set} activeSet - Set of active digits for efficient lookup
 */
export function renderAll(digits, activeSet) {
    if (!ctx) return;

    // Clear previous frame
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    // No need to create activeSet here anymore - it's passed in

    // Render all individual digits
    for (const d of digits) {
        renderDigit(d, activeSet);
    }

    // Render bond lines on top of all digits
    renderBonds(digits, activeSet);
}
// ============================================================
// MODULE LOAD MESSAGE
// ============================================================

log(`[${moduleTag(import.meta)}] loaded (render module)`);

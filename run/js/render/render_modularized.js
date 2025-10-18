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
import {
    renderBonds,
    renderDropShadow,
    renderGlow,
    renderAgeIndicator,
    renderOutline,
    renderSearchRadius,
    renderAttractionLine,
} from './overlays.js';

// ============================================================
// VISUALIZATION TOGGLES
// ============================================================
// These control which visual features are enabled/disabled
// Can be toggled dynamically during runtime

export const VISUALS = {
    //shadows: { enabled: false, label: 'Shadows' },
    outlines: { enabled: true, label: 'Outlines' },
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

// Text color and font
export const TEXT_SETTINGS = {
    color: 'rgba(255, 255, 255, 1)',
    font: 'sans-serif',
};

// Globe shading: creates 3D sphere effect with light source
export const GLOBE_SETTINGS = {
    lightAngle: Math.PI / 4, // Direction of light source (45 degrees)
    lightDistance: 0.3, // Distance of highlight from center
    highlight: 0.4, // Brightness increase for lit areas
    shadow: 0.4, // Darkness increase for shadowed areas
    transparency: 1.0, // Globe transparency (0.0-1.0): 1.0=opaque, 0.0=invisible
    // Glass effect uses gradient: highlights stay opaque, shadows become transparent
    highlightOpacity: 1.0, // Opacity multiplier for highlight (typically 1.0 for glass)
    midtoneOpacity: 0.7, // Opacity multiplier for midtone (0.5-0.8 for glass)
    shadowOpacity: 0.2, // Opacity multiplier for shadow (0.1-0.3 for glass)
};

// Outline rendering around each digit
export const OUTLINE_SETTINGS = {
    maxAlpha: 0.8, // Maximum outline opacity
    baseWidth: 0.5, // Base outline thickness
    color: 'rgba(255,255,255,0.5)', // Outline color
};

// Drop shadow effect (currently disabled)
export const SHADOW_SETTINGS = {
    offsetX: 0, // Horizontal shadow offset
    offsetY: 0, // Vertical shadow offset
    blur: 20, // Shadow blur radius
    color: 'rgba(0,0,0,0.3)', // Shadow color and opacity
};

// Glow effect around digits
export const GLOW_SETTINGS = {
    strength: 20, // Glow blur radius
    opacity: 0.9, // Glow opacity
    layers: 1, // Number of glow layers for intensity (1-5 recommended)
    colorBoost: 1.2, // Color intensity multiplier (1.0 = normal, higher = more vibrant)
};

// Motion blur based on digit speed
export const BLUR_SETTINGS = {
    maxBlur: 4, // Maximum blur amount at full speed
};

// ============================================================
// SIMULATION OVERLAY SETTINGS
// ============================================================

// Search radius visualization (attraction range for males)
export const SEARCH_RADIUS_SETTINGS = {
    color: 'rgba(0,0,255,0.15)', // Border color
    lineWidth: 1, // Border thickness
    fillOpacity: 0.05, // Fill transparency
};

// Bond lines between mated pairs
export const BOND_LINE_SETTINGS = {
    color: 'rgba(0,200,0,0.2)', // Green line color
    lineWidth: 1, // Line thickness
};

// Bond lines between children and mothers
export const CHILD_BOND_LINE_SETTINGS = {
    color: 'rgba(255, 165, 0, 0.3)', // Orange RGB values
    lineWidth: 1.5, // Line thickness
};

// Attraction lines from males to potential mates
export const ATTRACTION_LINE_SETTINGS = {
    maleToFemale: {
        color: 'rgba(255,255,255,0.7)', // White semi-transparent
        width: 1, // Line thickness
    },
};

// Circular progress indicator showing remaining lifespan
export const AGE_INDICATOR_SETTINGS = {
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
    // text color

    const textColor = TEXT_SETTINGS.color;
    const font = TEXT_SETTINGS.font;

    // Scale: grows from 30% to 100% as digit matures
    const scale = 0.3 + Math.min(d.age / CONFIG.matureAge, 1) * 0.7;

    // Opacity: full until old age, then fades to 0 at death
    const opacity =
        d.age < CONFIG.oldAge
            ? 1
            : Math.pow(
                  1 - (d.age - CONFIG.oldAge) / (d.maxAge - CONFIG.oldAge),
                  3
              );

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

    // Calculate gradient opacities for glass effect
    // Age-based opacity affects all parts, then glass settings create gradient
    const highlightAlpha =
        opacity * GLOBE_SETTINGS.transparency * GLOBE_SETTINGS.highlightOpacity;
    const midtoneAlpha =
        opacity * GLOBE_SETTINGS.transparency * GLOBE_SETTINGS.midtoneOpacity;
    const shadowAlpha =
        opacity * GLOBE_SETTINGS.transparency * GLOBE_SETTINGS.shadowOpacity;

    // Convert RGB colors to RGBA with varying opacity
    const highlightRGBA = `rgba(${lighten(r)},${lighten(g)},${lighten(
        b
    )},${highlightAlpha})`;
    const midtoneRGBA = `rgba(${r},${g},${b},${midtoneAlpha})`;
    const shadowRGBA = `rgba(${darken(r)},${darken(g)},${darken(
        b
    )},${shadowAlpha})`;

    // Outline properties
    // Parse color from settings and apply opacity
    const outlineRGB = OUTLINE_SETTINGS.color.match(/\d+/g) || [0, 0, 0];

    const settingsAlpha =
        parseFloat(OUTLINE_SETTINGS.color.match(/[\d.]+\)$/)?.[0]) || 1;
    const outlineColor = `rgba(${outlineRGB[0]},${outlineRGB[1]},${
        outlineRGB[2]
    },${Math.min(opacity * settingsAlpha, OUTLINE_SETTINGS.maxAlpha)})`;
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
        textColor,
        font,
        color: baseColor,
        opacity,
        highlight,
        shadow,
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

    // Skip rendering if opacity is too low (prevents "pop" at end of life)
    if (props.opacity < 0.01) return;

    // Render external effects first (glow and shadow as separate layers)
    // These are drawn before the main globe so they don't interfere with transparency
    renderGlow(d, size);
    // renderDropShadow(d, size);  // Uncomment to enable drop shadows

    ctx.save();

    //return;
    // Don't set globalAlpha - we'll use RGBA colors with per-gradient opacity

    // Draw main circle with radial gradient (globe shading with glass effect)
    // Each color stop has its own alpha for proper glass/transparent effect
    ctx.beginPath();
    const g = ctx.createRadialGradient(
        props.lx, // Highlight center X
        props.ly, // Highlight center Y
        size * 0.1, // Inner radius (small bright spot)
        d.x, // Outer center X
        d.y, // Outer center Y
        size / 2 // Outer radius (full digit size)
    );
    // Glass effect: opaque highlight → semi-transparent midtone → very transparent shadow
    g.addColorStop(0, props.highlightRGBA); // Bright center (most opaque)
    g.addColorStop(0.3, props.midtoneRGBA); // Base color (medium opacity)
    g.addColorStop(1, props.shadowRGBA); // Dark edge (most transparent)
    ctx.fillStyle = g;
    ctx.arc(d.x, d.y, size / 2, 0, Math.PI * 2);
    ctx.fill();
    ctx.closePath();

    // Draw outline around circle
    renderOutline(props);

    // Draw age indicator ring
    renderAgeIndicator(d, size, props);

    // Draw digit name/number in center
    ctx.globalAlpha = props.opacity; // Apply opacity to text too
    ctx.fillStyle = props.textColor;
    ctx.font = `${size * 0.6}px sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(d.name, d.x, d.y);

    ctx.restore();

    // Draw simulation overlays (not affected by globe transparency)
    // These render at full opacity regardless of GLOBE_SETTINGS.transparency
    renderSearchRadius(d);
    renderAttractionLine(d, activeSet);
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

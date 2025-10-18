import { flat, ball, bubble, VISUALS } from '../render/render.js';
import { log, moduleTag } from '../utils/utilities.js';

// ============================================================
// STYLE HANDLERS
// ============================================================
let style = null;
export function handleFlat() {
    flat();
    style = 'flat';
    syncAppearanceUI();
    triggerUpdate();
}

export function handleBall() {
    ball();
    style = 'ball';
    syncAppearanceUI();
    triggerUpdate();
}

export function handleBubble() {
    bubble();
    style = 'bubble';
    syncAppearanceUI();
    triggerUpdate();
}

// ============================================================
// EFFECTS HANDLERS (Mutually Exclusive)
// ============================================================
function handleDropShadow(enabled) {
    VISUALS.shadows.enabled = enabled;
    if (enabled) {
        VISUALS.glow.enabled = false;
        const glowCheck = document.getElementById('effect-glow');
        if (glowCheck) glowCheck.checked = false;
    } else {
        // When turning it off, just ensure mutual exclusivity
        VISUALS.glow.enabled = VISUALS.glow.enabled && !enabled;
    }
    syncAppearanceUI();
    triggerUpdate();
}

function handleGlow(enabled) {
    VISUALS.glow.enabled = enabled;
    if (enabled) {
        VISUALS.shadows.enabled = false;
        const shadowCheck = document.getElementById('effect-dropshadow');
        if (shadowCheck) shadowCheck.checked = false;
    } else {
        // When turning it off, just ensure mutual exclusivity
        VISUALS.shadows.enabled = VISUALS.shadows.enabled && !enabled;
    }
    syncAppearanceUI();
    triggerUpdate();
}

// ============================================================
// UPDATE RADIO BUTTONS TO MATCH CURRENT STATE
// ============================================================
function updateStyleRadios() {
    const flatRadio = document.getElementById('style-flat');
    const ballRadio = document.getElementById('style-ball');
    const bubbleRadio = document.getElementById('style-bubble');

    if (flatRadio) flatRadio.checked = style === 'flat';
    if (ballRadio) ballRadio.checked = style === 'ball';
    if (bubbleRadio) bubbleRadio.checked = style === 'bubble';
}

// ============================================================
// DYNAMIC APPEARANCE MODAL LOGIC
// ============================================================
export function setupAppearanceModal() {
    const container = document.getElementById('appearanceModalContainer');
    if (!container) return;

    container.innerHTML = '';

    // --- Appearance Style Section ---
    const styleSection = makeSection('Appearance Style');
    const styles = [
        {
            id: 'flat',
            label: 'Flat',
            fn: handleFlat,
            checked: style === 'flat',
        },
        {
            id: 'ball',
            label: 'Ball',
            fn: handleBall,
            checked: style === 'ball',
        },
        {
            id: 'bubble',
            label: 'Bubble',
            fn: handleBubble,
            checked: style === 'bubble',
        },
    ];
    styles.forEach(({ id, label, fn, checked }) => {
        styleSection.appendChild(makeRadio(`style-${id}`, label, checked, fn));
    });
    container.appendChild(styleSection);

    // --- Effects Section ---
    const effectsSection = makeSection('Effects');
    effectsSection.appendChild(
        makeCheckbox(
            'effect-dropshadow',
            'Drop Shadow',
            VISUALS.shadows.enabled,
            (e) => handleDropShadow(e.target.checked)
        )
    );
    effectsSection.appendChild(
        makeCheckbox('effect-glow', 'Glow', VISUALS.glow.enabled, (e) =>
            handleGlow(e.target.checked)
        )
    );
    container.appendChild(effectsSection);

    // --- Overlays Section ---
    const overlaysSection = makeSection('Overlays');
    const btnRow = document.createElement('div');
    btnRow.style.display = 'flex';
    btnRow.style.gap = '5px';

    const allOn = makeButton('Show All', () => {
        Object.keys(VISUALS).forEach((k) => {
            if (k === 'glow' || k === 'shadows') return; // skip these
            VISUALS[k].enabled = true;
        });
        syncAppearanceUI();
        triggerUpdate();
    });

    const allOff = makeButton('Hide All', () => {
        Object.keys(VISUALS).forEach((k) => {
            if (k === 'glow' || k === 'shadows') return; // skip these
            VISUALS[k].enabled = false;
        });
        syncAppearanceUI();
        triggerUpdate();
    });

    btnRow.append(allOn, allOff);
    overlaysSection.appendChild(btnRow);

    Object.keys(VISUALS).forEach((key) => {
        if (key === 'glow' || key === 'shadows') return;
        overlaysSection.appendChild(
            makeCheckbox(
                `toggle-${key}`,
                VISUALS[key].label || key,
                VISUALS[key].enabled,
                (e) => {
                    VISUALS[key].enabled = e.target.checked;
                    triggerUpdate();
                }
            )
        );
    });

    container.appendChild(overlaysSection);

    // --- Background Section ---
    const backgroundSection = makeSection('Background');

    const handleBackgroundChange = (theme) => {
        document.body.classList.remove('light', 'dark');
        document.body.classList.add(theme);
    };

    /*     // Assume "dark" is selected by default
    const isDark =
        document.body.classList.contains('dark') ||
        !document.body.classList.contains('light');
 */
    backgroundSection.appendChild(
        makeRadio('background-dark', 'Dark', true, () =>
            handleBackgroundChange('dark')
        )
    );

    backgroundSection.appendChild(
        makeRadio('background-light', 'Light', false, () =>
            handleBackgroundChange('light')
        )
    );

    container.appendChild(backgroundSection);

    // --- Initial sync ---
    syncAppearanceUI();
    document.getElementById('background-dark').checked = true;
}

// ============================================================
// SYNC LOGIC
// ============================================================
export function syncAppearanceUI() {
    // --- Radios ---
    updateStyleRadios();

    // --- Effects ---
    const shadowCheck = document.getElementById('effect-dropshadow');
    const glowCheck = document.getElementById('effect-glow');
    if (shadowCheck) {
        shadowCheck.checked = false; //reset
        shadowCheck.checked = VISUALS.shadows.enabled;
    }
    if (glowCheck) {
        glowCheck.checked = false; //reset
        glowCheck.checked = VISUALS.glow.enabled;
    }

    // --- Overlays ---
    Object.keys(VISUALS).forEach((key) => {
        const cb = document.getElementById(`toggle-${key}`);
        if (cb) cb.checked = VISUALS[key].enabled;
    });

    return;
    // === Enforce dependencies ===
    if (isBubble) {
        VISUALS.outlines.enabled = true;
        VISUALS.glow.enabled = true;
    } else if (isBall) {
        VISUALS.outlines.enabled = true;
        VISUALS.shadows.enabled = true;
    } else if (isFlat) {
        VISUALS.outlines.enabled = false;
        VISUALS.glow.enabled = false;
        VISUALS.shadows.enabled = false;
    }
}

// ============================================================
// HELPERS
// ============================================================
function makeSection(title) {
    const div = document.createElement('div');
    div.style.padding = '10px';
    div.style.marginBottom = '20px';
    div.style.borderBottom = '1px solid #444';
    const t = document.createElement('div');
    t.textContent = title;
    t.style.fontWeight = 'bold';
    t.style.marginBottom = '10px';
    t.style.fontSize = '14px';
    div.appendChild(t);
    return div;
}

function makeRadio(id, label, checked, fn) {
    const wrapper = document.createElement('div');
    wrapper.style.display = 'flex';
    wrapper.style.alignItems = 'center';
    const input = document.createElement('input');
    input.type = 'radio';
    input.name = 'appearance-style';
    input.id = id;
    input.checked = checked;
    input.onchange = fn;
    const lab = document.createElement('label');
    lab.htmlFor = id;
    lab.textContent = label;
    lab.style.marginLeft = '8px';
    lab.style.cursor = 'pointer';
    wrapper.append(input, lab);
    return wrapper;
}

function makeCheckbox(id, label, checked, fn) {
    const wrapper = document.createElement('div');
    wrapper.style.display = 'flex';
    wrapper.style.alignItems = 'center';
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.id = id;
    input.checked = checked;
    input.onchange = fn;
    const lab = document.createElement('label');
    lab.htmlFor = id;
    lab.textContent = label;
    lab.style.marginLeft = '8px';
    lab.style.cursor = 'pointer';
    wrapper.append(input, lab);
    return wrapper;
}

function makeButton(label, fn) {
    const b = document.createElement('button');
    b.textContent = label;
    b.style.padding = '4px 8px';
    b.style.fontSize = '12px';
    b.onclick = fn;
    return b;
}

function triggerUpdate() {
    if (typeof window.updateVisuals === 'function') {
        window.updateVisuals();
    }
}

log(`[${moduleTag(import.meta)}] appearance modal loaded`);

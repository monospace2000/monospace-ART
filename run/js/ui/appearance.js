import { VISUALS } from '../render/render.js';
import { log, moduleTag } from '../utils/utilities.js';

// ============================================================
// DYNAMIC APPEARANCE MODAL LOGIC
// ============================================================
export function setupAppearanceModal() {
    const container = document.getElementById('appearanceModalContainer');
    if (!container) return;

    // Clear container
    container.innerHTML = '';

    // --- Global toggles ---
    const globalDiv = document.createElement('div');
    globalDiv.style.marginBottom = '10px';

    const allOnBtn = document.createElement('button');
    allOnBtn.textContent = 'All On';
    allOnBtn.onclick = () => {
        Object.keys(VISUALS).forEach((key) => (VISUALS[key].enabled = true));
        refreshAppearanceToggles();
        triggerUpdate();
    };

    const allOffBtn = document.createElement('button');
    allOffBtn.textContent = 'All Off';
    allOffBtn.style.marginLeft = '5px';
    allOffBtn.onclick = () => {
        Object.keys(VISUALS).forEach((key) => (VISUALS[key].enabled = false));
        refreshAppearanceToggles();
        triggerUpdate();
    };

    globalDiv.appendChild(allOnBtn);
    globalDiv.appendChild(allOffBtn);
    container.appendChild(globalDiv);

    // --- Individual toggles ---
    Object.keys(VISUALS).forEach((key) => {
        const toggleDiv = document.createElement('div');
        toggleDiv.style.display = 'flex';
        toggleDiv.style.alignItems = 'center';
        toggleDiv.style.marginBottom = '5px';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.id = `toggle-${key}`;
        checkbox.checked = !!VISUALS[key].enabled;
        checkbox.onchange = () => {
            VISUALS[key].enabled = checkbox.checked;
            triggerUpdate();
        };

        const label = document.createElement('label');
        label.htmlFor = `toggle-${key}`;
        label.textContent = VISUALS[key].label || key;
        label.style.marginLeft = '5px';
        label.style.textTransform = 'capitalize';

        toggleDiv.appendChild(checkbox);
        toggleDiv.appendChild(label);
        container.appendChild(toggleDiv);
    });
}

// Refresh checkboxes to match VISUALS state (called after All On/Off)
function refreshAppearanceToggles() {
    Object.keys(VISUALS).forEach((key) => {
        const checkbox = document.getElementById(`toggle-${key}`);
        if (checkbox) checkbox.checked = !!VISUALS[key].enabled;
    });
}

// Trigger render update if updateVisuals exists
function triggerUpdate() {
    if (typeof window.updateVisuals === 'function') {
        window.updateVisuals();
    }
}

log(`[${moduleTag(import.meta)}] appearance modal loaded`);

// ============================================================
// REPRODUCTION
// ============================================================

import { state } from "./state.js";
import { createDigit } from "./digit.js";
import { CONFIG } from "../config/config.js";
import { log, moduleTag, trace, crossSum} from "../utils/utilities.js";


export function reproduce() {
    if (state.digits.length >= CONFIG.POP_CAP) return;

    for (const f of state.digits) {
        if (f.sex === "F" && f.gestationTimer > 0) {
        
        f.gestationTimer--;

            if (f.gestationTimer === 0 && f.bondedTo) {

                const m = f.bondedTo;
                const name = crossSum(
                    parseInt(f.name),
                    parseInt(m.name)
                ).toString();
                const sex = Math.random() < 0.5 ? "M" : "F";
                const x =
                    (f.x + m.x) / 2 +
                    (Math.random() - 0.5) * CONFIG.newbornOffset;
                const y =
                    (f.y + m.y) / 2 +
                    (Math.random() - 0.5) * CONFIG.newbornOffset;

                createDigit(name, sex, x, y, CONFIG.newbornSpeedFactor, f, m);

                f.lastRepro = state.tick;
                m.lastRepro = state.tick;
                f.bondedTo = null;
                m.bondedTo = null;
                //break;
            }
        }
    }
}

log(`[${moduleTag(import.meta)}] loaded`);

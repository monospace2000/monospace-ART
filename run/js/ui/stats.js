// ============================================================
// STATS & UI (Safe Version)
// ============================================================

import { state } from "../model/state.js";
import { log, moduleTag, trace } from "../utils/utilities.js";
import { crossSum } from "../utils/utilities.js";
// ============================================================
// CONFIG: Which stats to display
// Each entry defines a label and a value-producing function
// ============================================================
export const STATS_CONFIG = [
    {
        id: "epoch",
        label: "Epoch",
        value: () => state.resetCount + 1,
    },
    {
        id: "elapsed",
        label: "Elapsed Time",
        value: () =>
            ((Date.now() - state.resetStartTime) / 1000).toFixed(1) + "s",
    },
    {
        id: "current",
        label: "Current Value",
        value: () => {
            let total = 0;
            for (const d of state.digits)
                total = crossSum(total, parseInt(d.name));
            return total;
        },
    },
    {
        id: "epochTotal",
        label: "Epoch Total Value",
        value: () => {
            let epochTotal = 0;
            for (let n = 1; n <= 9; n++) {
                const digit = n.toString();
                const count =
                    (state.epochCumulativeCounts.M[digit] || 0) +
                    (state.epochCumulativeCounts.F[digit] || 0);
                for (let i = 0; i < count; i++) {
                    epochTotal = crossSum(epochTotal, parseInt(digit));
                }
            }
            return epochTotal;
        },
    },
];

// ============================================================
// INIT STATS BAR
// ============================================================
export function initStatsBar() {
    const container = document.getElementById("statsBarContent");
    if (!container) return;

    // Clear any existing stats
    container.innerHTML = "";

    // Dynamically build stat items
    STATS_CONFIG.forEach(({ id, label }) => {
        const item = document.createElement("div");
        item.className = "statsbar-item";
        item.id = `stat-${id}`;
        item.innerHTML = `
      <div class="statsbar-item-label">${label}</div>
      <div class="statsbar-item-value">–</div>
    `;
        container.appendChild(item);
    });
}
// ============================================================
// UPDATE STATS BAR
// ============================================================
export function updateStatsBar() {
    STATS_CONFIG.forEach(({ id, value }) => {
        const valueEl = document.querySelector(
            `#stat-${id} .statsbar-item-value`
        );
        if (!valueEl) return;

        try {
            const newValue = value();
            valueEl.textContent = newValue ?? "–";
        } catch (err) {
            console.warn(`⚠️ Error updating stat "${id}":`, err);
            valueEl.textContent = "–";
        }
    });
}

// ---------------------------
// STATS TABLE
// ---------------------------
export function updateStatsTable() {
    const tbody = document.querySelector("#statsTable tbody");
    if (!tbody) return; // Table not yet in DOM

    tbody.innerHTML = "";

    const rows = [];
    let maxCurrent = 0,
        maxCumulative = 0;
    const totals = { mCur: 0, fCur: 0, mCum: 0, fCum: 0 };

    for (let n = 1; n <= 9; n++) {
        const digit = n.toString();
        const mCur = state.currentCounts.M[digit] || 0;
        const fCur = state.currentCounts.F[digit] || 0;
        const mCum = state.epochCumulativeCounts.M[digit] || 0;
        const fCum = state.epochCumulativeCounts.F[digit] || 0;

        rows.push({ digit, mCur, fCur, mCum, fCum });

        totals.mCur += mCur;
        totals.fCur += fCur;
        totals.mCum += mCum;
        totals.fCum += fCum;

        maxCurrent = Math.max(maxCurrent, mCur, fCur);
        maxCumulative = Math.max(maxCumulative, mCum, fCum);
    }

    const getOpacity = (value, max) =>
        value && max ? 0.1 + (value / max) * 0.8 : 0.1;

    for (const row of rows) {
        const mCurOpacity = getOpacity(row.mCur, maxCurrent);
        const fCurOpacity = getOpacity(row.fCur, maxCurrent);
        const mCumOpacity = getOpacity(row.mCum, maxCumulative);
        const fCumOpacity = getOpacity(row.fCum, maxCumulative);

        tbody.innerHTML += `
      <tr>
        <td>${row.digit}</td>
        <td style="background-color: rgba(100, 160, 255, ${mCurOpacity});">${
            row.mCur || ""
        }</td>
        <td style="background-color: rgba(255, 100, 100, ${fCurOpacity});">${
            row.fCur || ""
        }</td>
        <td><strong>${row.mCur + row.fCur || ""}</strong></td>
        <td style="background-color: rgba(100, 160, 255, ${mCumOpacity});">${
            row.mCum || ""
        }</td>
        <td style="background-color: rgba(255, 100, 100, ${fCumOpacity});">${
            row.fCum || ""
        }</td>
        <td><strong>${row.mCum + row.fCum || ""}</strong></td>
      </tr>
    `;
    }

    // TOTAL row
    tbody.innerHTML += `
    <tr style="font-weight: bold;">
      <td style="background-color: rgba(200, 200, 200, 0.3);">TOTAL</td>
      <td style="background-color: rgba(100, 160, 255, 0.5);">${
          totals.mCur
      }</td>
      <td style="background-color: rgba(255, 100, 100, 0.5);">${
          totals.fCur
      }</td>
      <td style="background-color: rgba(220, 220, 220, 0.4); font-size: 1.3em;"><strong>${
          totals.mCur + totals.fCur
      }</strong></td>
      <td style="background-color: rgba(100, 160, 255, 0.5);">${
          totals.mCum
      }</td>
      <td style="background-color: rgba(255, 100, 100, 0.5);">${
          totals.fCum
      }</td>
      <td style="background-color: rgba(220, 220, 220, 0.4); font-size: 1.3em;"><strong>${
          totals.mCum + totals.fCum
      }</strong></td>
    </tr>
  `;
}

// ---------------------------
// GRAPH
// ---------------------------


let graph = null;

export function graphValue() {
    let rawTotalValue = 0;
    let currentAlive = state.digits.length;

    for (const d of state.digits) {
        const value = parseInt(d.name) || 0; // safely parse
        rawTotalValue += value;
    }

    state.rawTotalValue = rawTotalValue;
    return rawTotalValue;
    return Math.round(rawTotalValue/currentAlive);
}

export function initGraph() {
    const canvas = document.getElementById("graphCanvas");
    if (!canvas) {
        log("Graph canvas not yet available — will initialize later.");
        return false;
    }
    graph = {
        data: [],
        maxPoints: 200,
        updateCounter: 0,
        updateInterval: 5,
        canvas,
        ctx: canvas.getContext("2d"),
    };
    return true;
}

export function updateGraph() {
    if (!graph) {
        if (!initGraph()) return; // try initializing if canvas exists
    }

    let valueToGraph = graphValue();
    graph.updateCounter++;
    if (graph.updateCounter >= graph.updateInterval) {
        graph.data.push(valueToGraph);
        if (graph.data.length > graph.maxPoints) graph.data.shift();
        graph.updateCounter = 0;
    }

    drawGraph();
}

export function drawGraph() {
    if (!graph) return;
    const { canvas, ctx, data } = graph;
    const { width, height } = canvas;

    ctx.clearRect(0, 0, width, height);
    if (data.length < 2) return;

    const maxValue = Math.max(...data, 10);
    const range = maxValue;

    // Grid lines
    ctx.strokeStyle = "#e0e0e0";
    ctx.lineWidth = 1;
    for (let i = 0; i <= 5; i++) {
        const y = (height - 20) * (i / 5) + 10;
        ctx.beginPath();
        ctx.moveTo(30, y);
        ctx.lineTo(width - 10, y);
        ctx.stroke();
    }

    // Labels
    ctx.fillStyle = "black";
    ctx.font = "10px sans-serif";
    ctx.textAlign = "right";
    for (let i = 0; i <= 5; i++) {
        const y = (height - 20) * (i / 5) + 10;
        const value = Math.round(maxValue - (range * i) / 5);
        ctx.fillText(value, 25, y + 3);
    }

    // Graph line
    ctx.strokeStyle = "#2196F3";
    ctx.lineWidth = 2;
    ctx.beginPath();

    const pointSpacing = (width - 40) / (graph.maxPoints - 1);
    for (let i = 0; i < data.length; i++) {
        const x = 30 + i * pointSpacing;
        const y = height - 10 - (data[i] / range) * (height - 20);
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    }
    ctx.stroke();

    if (data.length > 0) {
        ctx.fillStyle = "black";
        ctx.font = "bold 12px sans-serif";
        ctx.textAlign = "left";
        ctx.fillText(`Current: ${data[data.length - 1]}`, 35, 20);
    }
}

initGraph();
initStatsBar();

export function updateStats() {
    updateStatsBar?.();
    updateStatsTable?.();
    updateGraph?.();
}

log(`[${moduleTag(import.meta)}] loaded`);

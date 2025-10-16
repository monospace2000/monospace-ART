(() => {
  const CONFIG = {
    enableColorCycle: true,
    colors: ['red', 'white', 'blue', 'white'],
    speed: 5,
  };

  function startColorCycle(elementId, config) {
    if (!config.enableColorCycle) return;
    const el = document.getElementById(elementId);
    if (!el) return;

    const tmp = document.createElement('div');
    document.body.appendChild(tmp);

    function parseColor(color) {
      tmp.style.color = color;
      const computed = getComputedStyle(tmp).color;
      const [r, g, b] = computed.match(/\d+/g).map(Number);
      return { r, g, b };
    }

    const colors = config.colors.map(parseColor);
    document.body.removeChild(tmp);

    let index = 0, t = 0;
    const rgbToCss = (r, g, b) => `rgb(${Math.round(r)}, ${Math.round(g)}, ${Math.round(b)})`;
    const interpolate = (c1, c2, t) => ({
      r: c1.r + (c2.r - c1.r) * t,
      g: c1.g + (c2.g - c1.g) * t,
      b: c1.b + (c2.b - c1.b) * t
    });

    function update() {
      const next = (index + 1) % colors.length;
      const c = interpolate(colors[index], colors[next], t);
      const colorCSS = rgbToCss(c.r, c.g, c.b);
      el.style.color = colorCSS;
      el.style.textShadow = `0 0 25px ${colorCSS}, 0 0 50px ${colorCSS}`;
      t += config.speed / 500;
      if (t >= 1) { t = 0; index = next; }
      requestAnimationFrame(update);
    }
    update();
  }

  document.addEventListener('DOMContentLoaded', () => {
    startColorCycle('logored', CONFIG);
  });
})();

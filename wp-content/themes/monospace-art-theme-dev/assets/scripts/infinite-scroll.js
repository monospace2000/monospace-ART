(function() {
  'use strict';

  // =========================
  // Infinite Scroll
  // =========================
  const container = document.querySelector('#main-content[data-infinite-scroll]');
  if (container) {
    let isLoading = false;
    let hasMorePages = !!container.getAttribute('data-next-page');

    // Create loading indicator
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'infinite-scroll-loading';
    loadingIndicator.style.cssText = 'text-align:center; padding:20px; display:none;';
    loadingIndicator.innerHTML = `<span style="color:#888;">${container.getAttribute('data-loading-text') || 'Loading more...'}</span>`;
    container.appendChild(loadingIndicator);

    function handleScroll() {
      if (isLoading || !hasMorePages) return;
      const threshold = parseInt(container.getAttribute('data-scroll-threshold')) || 300;
      if ((window.innerHeight + window.scrollY) >= (document.documentElement.scrollHeight - threshold)) {
        loadNextPage();
      }
    }

    async function loadNextPage() {
      const nextPageUrl = container.getAttribute('data-next-page');
      if (!nextPageUrl || isLoading) return;

      isLoading = true;
      loadingIndicator.style.display = 'block';

      try {
        const response = await fetch(nextPageUrl);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newContainer = doc.querySelector('#main-content[data-infinite-scroll]');
        if (!newContainer) {
          hasMorePages = false;
          loadingIndicator.style.display = 'none';
          return;
        }

        // Wrap new content for fade + slide effect
        const wrapper = document.createElement('div');
        wrapper.style.opacity = '0';
        wrapper.style.transform = 'translateY(20px)';
        wrapper.style.transition = 'opacity 0.75s ease, transform 0.75s ease';
        wrapper.style.width = '100%';
        wrapper.innerHTML = newContainer.innerHTML;

        // Make all images full width
        wrapper.querySelectorAll('img').forEach(img => {
          img.style.width = '100%';
          img.style.height = 'auto';
          img.style.display = 'block';
        });

        // Insert wrapper before loading indicator
        container.insertBefore(wrapper, loadingIndicator);

        // Force reflow and trigger animation
        wrapper.getBoundingClientRect();
        wrapper.style.opacity = '1';
        wrapper.style.transform = 'translateY(0)';

        // Update next page URL
        const newNextPage = newContainer.getAttribute('data-next-page');
        if (newNextPage) {
          container.setAttribute('data-next-page', newNextPage);
        } else {
          hasMorePages = false;
          container.removeAttribute('data-next-page');
        }

      } catch (error) {
        console.error('Infinite scroll error:', error);
        hasMorePages = false;
        loadingIndicator.innerHTML = '<span style="color:#888;">Could not load more posts</span>';
        setTimeout(() => { loadingIndicator.style.display = 'none'; }, 3000);
      } finally {
        isLoading = false;
        if (hasMorePages) loadingIndicator.style.display = 'none';
      }
    }

    window.addEventListener('scroll', handleScroll);
    handleScroll();
  }

  // =========================
  // Scroll to Top Button
  // =========================
  const scrollBtn = document.createElement('button');
  scrollBtn.innerHTML = '▲';
  scrollBtn.style.cssText = `
    position: fixed;
    right: 40px;
    bottom: 40px;
    background: rgba(0,0,0,0.7);
    color: #fff;
    border: none;
    border-radius: 5px;
    padding: 10px 15px;
    font-size: 18px;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.3s;
    z-index: 9999;
  `;
  document.body.appendChild(scrollBtn);

  function toggleScrollButton() {
    scrollBtn.style.opacity = (window.scrollY > 300) ? '1' : '0';
  }

  scrollBtn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  window.addEventListener('scroll', toggleScrollButton);

})();

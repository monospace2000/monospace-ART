(function() {
  'use strict';

  // =========================
  // Scroll Position Restoration
  // =========================
  const SCROLL_POS_KEY = 'monospace_feed_scroll_position';
  const SCROLL_URL_KEY = 'monospace_feed_url';
  const SCROLL_PAGES_KEY = 'monospace_feed_pages_loaded';

  let shouldRestoreScroll = false;
  let targetScrollPosition = 0;
  let targetPagesLoaded = 0;

  // Check if we should restore scroll position
  function checkScrollRestoration() {
    const savedUrl = sessionStorage.getItem(SCROLL_URL_KEY);
    const currentUrl = window.location.href;

    if (savedUrl === currentUrl) {
      const savedPosition = sessionStorage.getItem(SCROLL_POS_KEY);
      const savedPages = sessionStorage.getItem(SCROLL_PAGES_KEY);

      if (savedPosition && savedPages) {
        shouldRestoreScroll = true;
        targetScrollPosition = parseInt(savedPosition, 10);
        targetPagesLoaded = parseInt(savedPages, 10);
        return true;
      }
    }
    return false;
  }

  // Restore scroll position after pages are loaded
  function restoreScrollPosition() {
    if (shouldRestoreScroll) {
      setTimeout(() => {
        window.scrollTo(0, targetScrollPosition);
        // Clear restoration flags and storage
        shouldRestoreScroll = false;
        sessionStorage.removeItem(SCROLL_POS_KEY);
        sessionStorage.removeItem(SCROLL_URL_KEY);
        sessionStorage.removeItem(SCROLL_PAGES_KEY);
      }, 50);
    }
  }

  // Save scroll position before navigating to a post
  function saveScrollPosition(pagesLoaded) {
    sessionStorage.setItem(SCROLL_POS_KEY, window.scrollY.toString());
    sessionStorage.setItem(SCROLL_URL_KEY, window.location.href);
    sessionStorage.setItem(SCROLL_PAGES_KEY, pagesLoaded.toString());
  }

  // Attach click listeners to all links within articles
  function attachScrollSaveListeners(pagesLoaded) {
    document.querySelectorAll('article a').forEach(link => {
      link.addEventListener('click', () => saveScrollPosition(pagesLoaded));
    });
  }

  // =========================
  // Infinite Scroll
  // =========================
  const container = document.querySelector('#main-content[data-infinite-scroll]');
  if (container) {
    let isLoading = false;
    let hasMorePages = !!container.getAttribute('data-next-page');
    let pagesLoaded = 1; // Start at 1 for the initial page
    let isRestoringScroll = false;

    // Check if we need to restore scroll position
    const needsRestoration = checkScrollRestoration();
    if (needsRestoration) {
      isRestoringScroll = true;
    }

    // Attach scroll save listeners on initial load
    attachScrollSaveListeners(pagesLoaded);

    // Create loading indicator
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'infinite-scroll-loading';
    loadingIndicator.style.cssText = 'text-align:center; padding:20px; display:none;';
    loadingIndicator.innerHTML = `<span style="color:#888;">${container.getAttribute('data-loading-text') || 'Loading more...'}</span>`;
    container.appendChild(loadingIndicator);

    // Start fetching the next page this far from the bottom: one screen in list view, 2.5 in
    // grid view (small tiles - a page of posts is only a few rows, so it's reached quickly)
    function prefetchDistance() {
      const set = parseInt(container.getAttribute('data-scroll-threshold'));
      if (set) return set;
      const grid = document.documentElement.classList.contains('archive-view-grid');
      return window.innerHeight * (grid ? 2.5 : 1);
    }

    function handleScroll() {
      if (isLoading || !hasMorePages) return;
      if ((window.innerHeight + window.scrollY) >= (document.documentElement.scrollHeight - prefetchDistance())) {
        loadNextPage();
      }
    }

    async function loadNextPage() {
      const nextPageUrl = container.getAttribute('data-next-page');
      if (!nextPageUrl || isLoading) return;

      isLoading = true;
      let failed = false;
      // Only show loading indicator during normal scroll, not during restoration
      if (!isRestoringScroll) {
        loadingIndicator.style.display = 'block';
      }

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

        // Wrap new content for fade + slide effect (skip animation during restoration)
        const wrapper = document.createElement('div');
        wrapper.style.width = '100%';
        wrapper.innerHTML = newContainer.innerHTML;

        if (!isRestoringScroll) {
          // Only animate during normal scroll
          wrapper.style.opacity = '0';
          wrapper.style.transform = 'translateY(20px)';
          wrapper.style.transition = 'opacity 0.75s ease, transform 0.75s ease';
        }

        // Make all images full width
        wrapper.querySelectorAll('img').forEach(img => {
          img.style.width = '100%';
          img.style.height = 'auto';
          img.style.display = 'block';
        });

        // Insert wrapper before loading indicator
        container.insertBefore(wrapper, loadingIndicator);

        if (!isRestoringScroll) {
          // Force reflow and trigger animation only during normal scroll
          wrapper.getBoundingClientRect();
          wrapper.style.opacity = '1';
          wrapper.style.transform = 'translateY(0)';
        }

        // Increment pages loaded counter
        pagesLoaded++;

        // Attach scroll save listeners to newly loaded content
        wrapper.querySelectorAll('article a').forEach(link => {
          link.addEventListener('click', () => saveScrollPosition(pagesLoaded));
        });

        // Update next page URL
        const newNextPage = newContainer.getAttribute('data-next-page');
        if (newNextPage) {
          container.setAttribute('data-next-page', newNextPage);
        } else {
          hasMorePages = false;
          container.removeAttribute('data-next-page');
        }

        // If restoring scroll, check if we need to load more pages
        if (isRestoringScroll && pagesLoaded < targetPagesLoaded && hasMorePages) {
          // Load next page immediately (no delay during restoration)
          loadNextPage();
        } else if (isRestoringScroll && pagesLoaded >= targetPagesLoaded) {
          // We've loaded enough pages, restore scroll position
          isRestoringScroll = false;
          restoreScrollPosition();
        }

      } catch (error) {
        console.error('Infinite scroll error:', error);
        failed = true;
        hasMorePages = false;
        loadingIndicator.innerHTML = '<span style="color:#888;">Could not load more posts</span>';
        setTimeout(() => { loadingIndicator.style.display = 'none'; }, 3000);
      } finally {
        isLoading = false;
        // Hidden after every load - only the error stays up a moment. No "You've reached the end" message
        // after the last page (owner, 2026-11-17: hide it).
        if (!failed) loadingIndicator.style.display = 'none';
        // Still within the prefetch distance (e.g. grid view, short pages)? Keep going.
        if (hasMorePages && !isRestoringScroll) handleScroll();
      }
    }

    window.addEventListener('scroll', handleScroll);

    // If restoring scroll, start loading pages automatically. A position on the first page
    // needs no extra pages - restore it now. (Left pending, it fired on the first page loaded
    // by normal scrolling and jumped back up to the old position.)
    if (isRestoringScroll && targetPagesLoaded > 1) {
      loadNextPage();
    } else {
      if (isRestoringScroll) {
        isRestoringScroll = false;
        restoreScrollPosition();
      }
      handleScroll();
    }
  }

  // =========================
  // Scroll to Top Button (all pages)
  // =========================
  const scrollBtn = document.createElement('button');
  scrollBtn.innerHTML = '▲';
  scrollBtn.classList.add('scroll-to-top');
  scrollBtn.style.cssText = `
    position: fixed !important;
    right: 30px;
    bottom: 30px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    font-size: 24px;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease, background-color 0.3s ease;
    z-index: 9999;
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
  `;
  document.body.appendChild(scrollBtn);

  function toggleScrollButton() {
    const documentHeight = document.documentElement.scrollHeight;
    const viewportHeight = window.innerHeight;
    const scrollTop = window.scrollY;

    // Show button if page is taller than viewport AND user has scrolled down
    if (documentHeight > viewportHeight && scrollTop > 300) {
      scrollBtn.style.opacity = '1';
      scrollBtn.style.visibility = 'visible';
    } else {
      scrollBtn.style.opacity = '0';
      scrollBtn.style.visibility = 'hidden';
    }
  }

  scrollBtn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  // Hover effect
  scrollBtn.addEventListener('mouseenter', () => {
    scrollBtn.style.backgroundColor = 'var(--button-color-hover, #0353a4)';
  });
  scrollBtn.addEventListener('mouseleave', () => {
    scrollBtn.style.backgroundColor = 'var(--button-color, #046BD2)';
  });

  window.addEventListener('scroll', toggleScrollButton);
  toggleScrollButton(); // Check on load

})();
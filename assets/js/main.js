/**
 * SRI BALAJI GEARS — ENTERPRISE UI/UX INTERACTIONS
 * Modern responsive interactions, drawer, counters, tabs, accordions, modals, toasts & clipboard
 */

(function () {
  'use strict';

  // 1. Mobile Drawer Navigation & Backdrop
  const menuToggle = document.getElementById('menuToggle');
  const drawerClose = document.getElementById('drawerClose');
  const mobileDrawer = document.getElementById('mobileDrawer');
  const mobileBackdrop = document.getElementById('mobileBackdrop');

  function openDrawer() {
    if (mobileDrawer) mobileDrawer.classList.add('open');
    if (mobileBackdrop) mobileBackdrop.classList.add('open');
    if (menuToggle) {
      menuToggle.classList.add('open');
      menuToggle.setAttribute('aria-expanded', 'true');
    }
    document.body.classList.add('drawer-open');
  }

  function closeDrawer() {
    if (mobileDrawer) mobileDrawer.classList.remove('open');
    if (mobileBackdrop) mobileBackdrop.classList.remove('open');
    if (menuToggle) {
      menuToggle.classList.remove('open');
      menuToggle.setAttribute('aria-expanded', 'false');
    }
    document.body.classList.remove('drawer-open');
  }

  if (menuToggle) {
    menuToggle.addEventListener('click', () => {
      const isOpen = mobileDrawer && mobileDrawer.classList.contains('open');
      if (isOpen) {
        closeDrawer();
      } else {
        openDrawer();
      }
    });
  }

  if (drawerClose) drawerClose.addEventListener('click', closeDrawer);
  if (mobileBackdrop) mobileBackdrop.addEventListener('click', closeDrawer);

  // Close drawer when pressing Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeDrawer();
      closeModal();
    }
  });

  // Close drawer when clicking any link inside
  if (mobileDrawer) {
    mobileDrawer.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', closeDrawer);
    });
  }

  // 2. Active Page Highlight
  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.menu a, .drawer-nav a').forEach((a) => {
    const href = a.getAttribute('href');
    if (href === currentPath || (currentPath === '' && href === 'index.html')) {
      a.classList.add('active');
    }
  });

  // 3. Dynamic Year in Footer
  document.querySelectorAll('[data-year]').forEach((el) => {
    el.textContent = new Date().getFullYear();
  });

  // 4. Sticky Header Blur & Shrink on Scroll
  const header = document.querySelector('.header');
  const scrollTopBtn = document.getElementById('scrollTopBtn');

  function handleScroll() {
    const y = window.scrollY || window.pageYOffset;
    if (header) {
      if (y > 35) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    }

    if (scrollTopBtn) {
      if (y > 350) {
        scrollTopBtn.classList.add('show');
      } else {
        scrollTopBtn.classList.remove('show');
      }
    }
  }

  window.addEventListener('scroll', handleScroll, { passive: true });
  handleScroll();

  if (scrollTopBtn) {
    scrollTopBtn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  // 5. Scroll Reveal with IntersectionObserver
  const revealElements = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver(
      (entries, observer) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('show');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
    );
    revealElements.forEach((el) => revealObserver.observe(el));
  } else {
    revealElements.forEach((el) => el.classList.add('show'));
  }

  // 6. Interactive Animated Metric Counters
  const counters = document.querySelectorAll('[data-counter]');
  if ('IntersectionObserver' in window && counters.length > 0) {
    const counterObserver = new IntersectionObserver(
      (entries, observer) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const el = entry.target;
            const targetVal = parseInt(el.getAttribute('data-counter'), 10);
            const prefix = el.getAttribute('data-prefix') || '';
            const suffix = el.getAttribute('data-suffix') || '';
            const duration = 1600;
            const startTime = performance.now();

            function updateCounter(currentTime) {
              const elapsed = currentTime - startTime;
              const progress = Math.min(elapsed / duration, 1);
              // Ease-out cubic formula
              const easeProgress = 1 - Math.pow(1 - progress, 3);
              const currentVal = Math.floor(easeProgress * targetVal);

              el.textContent = `${prefix}${currentVal.toLocaleString()}${suffix}`;

              if (progress < 1) {
                requestAnimationFrame(updateCounter);
              } else {
                el.textContent = `${prefix}${targetVal.toLocaleString()}${suffix}`;
              }
            }

            requestAnimationFrame(updateCounter);
            observer.unobserve(el);
          }
        });
      },
      { threshold: 0.3 }
    );
    counters.forEach((c) => counterObserver.observe(c));
  }

  // 7. Interactive Tabs Switcher
  document.querySelectorAll('.tabs-wrapper').forEach((wrapper) => {
    const tabButtons = wrapper.querySelectorAll('.tab-btn');
    const tabPanes = wrapper.querySelectorAll('.tab-content');

    tabButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        const targetTab = btn.getAttribute('data-tab');

        tabButtons.forEach((b) => b.classList.remove('active'));
        tabPanes.forEach((p) => p.classList.remove('active'));

        btn.classList.add('active');
        const activePane = wrapper.querySelector(`#${targetTab}`);
        if (activePane) activePane.classList.add('active');
      });
    });
  });

  // 8. Interactive Accordion / FAQ Toggle
  document.querySelectorAll('.accordion-trigger').forEach((trigger) => {
    trigger.addEventListener('click', () => {
      const item = trigger.closest('.accordion-item');
      const body = item.querySelector('.accordion-body');
      const isOpen = item.classList.contains('open');

      // Optional: close other sibling items
      const parent = item.parentElement;
      if (parent) {
        parent.querySelectorAll('.accordion-item').forEach((sibling) => {
          if (sibling !== item) {
            sibling.classList.remove('open');
            const sibBody = sibling.querySelector('.accordion-body');
            if (sibBody) sibBody.style.maxHeight = null;
          }
        });
      }

      if (isOpen) {
        item.classList.remove('open');
        body.style.maxHeight = null;
      } else {
        item.classList.add('open');
        body.style.maxHeight = body.scrollHeight + 'px';
      }
    });
  });

  // 9. Toast Notification System
  function showToast(message, icon = '✓') {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `<span class="toast-icon">${icon}</span> <span>${message}</span>`;
    container.appendChild(toast);

    requestAnimationFrame(() => {
      toast.classList.add('show');
    });

    setTimeout(() => {
      toast.classList.remove('show');
      setTimeout(() => toast.remove(), 350);
    }, 3200);
  }
  window.showToast = showToast;

  // 10. Copy-to-Clipboard Functionality
  document.querySelectorAll('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const textToCopy = btn.getAttribute('data-copy');
      const label = btn.getAttribute('data-label') || 'Copied to clipboard';
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(textToCopy).then(() => {
          showToast(`${label}!`, '📋');
        }).catch(() => {
          fallbackCopy(textToCopy, label);
        });
      } else {
        fallbackCopy(textToCopy, label);
      }
    });
  });

  function fallbackCopy(text, label) {
    const tempInput = document.createElement('input');
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    try {
      document.execCommand('copy');
      showToast(`${label}!`, '📋');
    } catch (err) {
      showToast('Copy failed, please copy manually.', '⚠️');
    }
    document.body.removeChild(tempInput);
  }

  // 11. Modal / Lightbox System for Certifications & Technical Visuals
  const certModal = document.getElementById('certModal');
  function openModal(imgSrc, title, desc, refTag) {
    if (!certModal) return;
    const modalImg = certModal.querySelector('#modalImage');
    const modalTitle = certModal.querySelector('#modalTitle');
    const modalDesc = certModal.querySelector('#modalDesc');
    const modalRef = certModal.querySelector('#modalRef');

    if (modalImg) modalImg.src = imgSrc;
    if (modalTitle) modalTitle.textContent = title;
    if (modalDesc) modalDesc.textContent = desc;
    if (modalRef) modalRef.textContent = refTag || 'Automotive Compliance Standard';

    certModal.classList.add('active');
    document.body.classList.add('drawer-open');
  }

  function closeModal() {
    if (certModal) {
      certModal.classList.remove('active');
      document.body.classList.remove('drawer-open');
    }
  }

  if (certModal) {
    const closeBtn = certModal.querySelector('.modal-close');
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    certModal.addEventListener('click', (e) => {
      if (e.target === certModal) closeModal();
    });
  }

  document.querySelectorAll('.cert-card[data-cert]').forEach((card) => {
    card.addEventListener('click', () => {
      const img = card.querySelector('img')?.src || '';
      const title = card.querySelector('h3')?.textContent || '';
      const desc = card.querySelector('p')?.textContent || '';
      const tag = card.getAttribute('data-tag') || 'Reference Artwork';
      openModal(img, title, desc, tag);
    });
  });

  // 12. Dynamic "Others" Field Toggle
  const projectTypeSelect = document.getElementById('projectType');
  const otherCategoryWrap = document.getElementById('otherCategoryWrap');
  const otherCategoryInput = document.getElementById('otherCategory');

  if (projectTypeSelect && otherCategoryWrap) {
    projectTypeSelect.addEventListener('change', () => {
      if (projectTypeSelect.value === 'Others') {
        otherCategoryWrap.style.display = 'block';
        if (otherCategoryInput) {
          otherCategoryInput.setAttribute('required', 'true');
          otherCategoryInput.focus();
        }
      } else {
        otherCategoryWrap.style.display = 'none';
        if (otherCategoryInput) {
          otherCategoryInput.removeAttribute('required');
          otherCategoryInput.value = '';
        }
      }
    });
  }

  // 13. Contact & RFQ Form Handler
  const enquiryForm = document.getElementById('enquiry');
  if (enquiryForm) {
    enquiryForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const formData = new FormData(enquiryForm);
      const name = formData.get('name') || '';
      const company = formData.get('company') || '';
      const email = formData.get('email') || '';
      const phone = formData.get('phone') || '';
      let projectType = formData.get('projectType') || 'General Automotive Enquiry';
      const otherCategory = formData.get('otherCategory');
      if (projectType === 'Others' && otherCategory && otherCategory.trim()) {
        projectType = `Others: ${otherCategory.trim()}`;
      }
      const quantity = formData.get('quantity') || 'Not specified';
      const message = formData.get('message') || '';

      const bodyText = [
        `NEW AUTOMOTIVE RFQ / ENQUIRY`,
        `==================================`,
        `Contact Name:  ${name}`,
        `Company:       ${company}`,
        `Email:         ${email}`,
        `Phone:         ${phone}`,
        `Project Focus: ${projectType}`,
        `Est. Volume:   ${quantity}`,
        `----------------------------------`,
        `Requirement & Specifications:`,
        message,
        `==================================`,
        `Sent via Sri Balaji Gears Website RFQ Portal`
      ].join('\n');

      const subject = encodeURIComponent(`Automotive RFQ: ${projectType} - ${name} (${company || 'New Inquiry'})`);
      const body = encodeURIComponent(bodyText);

      const statusEl = document.getElementById('status');
      if (statusEl) {
        statusEl.className = 'form-status success';
        statusEl.innerHTML = `✓ Preparing your RFQ email to <strong>erp@sribalajigears.com</strong>. Your mail client will open momentarily.`;
      }

      showToast('Opening your email client to send RFQ...', '✉️');

      setTimeout(() => {
        window.location.href = `mailto:erp@sribalajigears.com?subject=${subject}&body=${body}`;
      }, 700);
    });
  }

})();

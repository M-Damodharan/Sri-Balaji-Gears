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

  let isScrolling = false;
  function updateScrollState() {
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
    isScrolling = false;
  }

  function handleScroll() {
    if (!isScrolling) {
      window.requestAnimationFrame(updateScrollState);
      isScrolling = true;
    }
  }

  window.addEventListener('scroll', handleScroll, { passive: true });
  updateScrollState();

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
      const tag = card.getAttribute('data-tag') || 'Certified Compliance Standard';
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

  // 13. Contact & RFQ Form Handler (Direct ERP Backend Transmission)
  const enquiryForm = document.getElementById('enquiry');
  if (enquiryForm) {
    enquiryForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const submitBtn = document.getElementById('submitBtn');
      const btnText = document.getElementById('btnText');
      const btnIcon = document.getElementById('btnIcon');
      const statusEl = document.getElementById('status');

      // Preserve original button text
      const origText = btnText ? btnText.textContent : 'SUBMIT ENQUIRY / RFQ';
      const origIcon = btnIcon ? btnIcon.textContent : '↗';

      // Set loading state
      if (submitBtn) submitBtn.disabled = true;
      if (btnText) btnText.textContent = 'TRANSMITTING RFQ...';
      if (btnIcon) btnIcon.textContent = '⏳';

      if (statusEl) {
        statusEl.className = 'form-status info';
        statusEl.style.display = 'block';
        statusEl.innerHTML = `<span class="spinner"></span> Transmitting enquiry &amp; attachments to <strong>prabhu@sribalajigears.com</strong> &amp; <strong>erp@sribalajigears.com</strong>...`;
      }

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

      // Resolve endpoint dynamically: on GitHub Pages static preview, attempt production backend
      const isGitHubPages = window.location.hostname.endsWith('github.io');
      let primaryEndpoint = isGitHubPages ? 'https://www.sribalajigears.in/api/enquiry.php' : 'api/enquiry.php';

      try {
        let response = null;
        let data = null;

        try {
          response = await fetch(primaryEndpoint, {
            method: 'POST',
            body: formData,
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          });
          data = await response.json().catch(() => null);
        } catch (fetchErr) {
          // If cross-origin to production backend failed, attempt local relative endpoint
          if (primaryEndpoint !== 'api/enquiry.php') {
            try {
              response = await fetch('api/enquiry.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
              });
              data = await response.json().catch(() => null);
            } catch (fallbackErr) {
              // Both endpoints unreachable
            }
          }
        }

        if (response && response.ok && data && data.success) {
          if (statusEl) {
            statusEl.className = 'form-status success';
            statusEl.innerHTML = `
              <div class="rfq-success-head">
                <span class="rfq-check-badge">✓</span>
                <div>
                  <h4 style="margin:0 0 4px 0; color:#34d399; font-size:1.05rem;">RFQ TRANSMITTED SUCCESSFULLY</h4>
                  <div class="rfq-ref-id">Reference ID: <strong>${data.reference_id}</strong></div>
                </div>
              </div>
              <p class="rfq-success-msg">
                Your enquiry and specifications have been automatically delivered to both <strong>prabhu@sribalajigears.com</strong> and <strong>erp@sribalajigears.com</strong> and permanently archived for plant review. A formal confirmation receipt has been sent to <strong>${escapeHtml(email)}</strong>.
              </p>
              <div class="rfq-success-footer">
                <span class="rfq-meta">⚡ Engineering Review Turnaround: 24–48 Hours</span>
                <button type="button" class="btn-reset-form" onclick="location.reload()">Submit Another Drawing</button>
              </div>
            `;
            // Smoothly scroll confirmation into center view immediately
            try {
              statusEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (scrollErr) {}
          }

          showToast(`RFQ Transmitted! Ref: ${data.reference_id}`, '✓');
          enquiryForm.reset();

          if (submitBtn) {
            submitBtn.disabled = false;
            if (btnText) btnText.textContent = 'TRANSMISSION COMPLETE';
            if (btnIcon) btnIcon.textContent = '✓';
          }
        } else {
          // Server returned an error or static host returned 405/404
          let errMsg;
          if (data && data.error) {
            errMsg = data.error;
          } else if (isGitHubPages || (response && (response.status === 405 || response.status === 404))) {
            errMsg = 'GitHub Pages is a static preview host and cannot execute PHP scripts. Tap the button below to send your enquiry directly via your email client.';
          } else {
            errMsg = 'Server could not process your submission. Please check your fields and try again.';
          }
          renderErrorWithFallback(errMsg);
        }
      } catch (err) {
        // Network failure or static environment fallback
        const errMsg = isGitHubPages
          ? 'GitHub Pages is a static preview host and cannot execute PHP scripts. Tap the button below to send your enquiry directly via your email client.'
          : 'Network or server gateway did not respond. You can immediately launch your email client with your pre-filled inquiry.';
        renderErrorWithFallback(errMsg);
      }

      function renderErrorWithFallback(msg) {
        if (submitBtn) {
          submitBtn.disabled = false;
          if (btnText) btnText.textContent = origText;
          if (btnIcon) btnIcon.textContent = origIcon;
        }

        const drawingFile = formData.get('attachment') || formData.get('drawing');
        const fileInfo = (drawingFile && drawingFile.name && drawingFile.size > 0)
          ? `Attached Drawing: ${drawingFile.name} (${(drawingFile.size / 1024).toFixed(1)} KB)`
          : 'Attached Drawing: None';

        const mailSubject = encodeURIComponent(`Automotive RFQ: ${projectType} - ${name} (${company || 'New Inquiry'})`);
        const mailBody = encodeURIComponent([
          `AUTOMOTIVE RFQ / SPECIFICATION INQUIRY`,
          `=========================================`,
          `Contact Name:     ${name}`,
          `Company:          ${company}`,
          `Email:            ${email}`,
          `Phone:            ${phone}`,
          `Project Focus:    ${projectType}`,
          `Est. Volume:      ${quantity}`,
          `${fileInfo}`,
          `-----------------------------------------`,
          `Requirement & Specifications:`,
          message,
          `=========================================`,
          `Deliver To: prabhu@sribalajigears.com, erp@sribalajigears.com`
        ].join('\n'));

        if (statusEl) {
          statusEl.className = 'form-status error';
          statusEl.style.display = 'block';
          statusEl.innerHTML = `
            <div style="font-weight:700; margin-bottom:6px; color:#f87171;">⚠️ ${escapeHtml(msg)}</div>
            <div style="margin-top:10px;">
              <a href="mailto:prabhu@sribalajigears.com?cc=erp@sribalajigears.com&subject=${mailSubject}&body=${mailBody}" class="button sm" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                ✉️ TRANSMIT DIRECTLY VIA EMAIL CLIENT ↗
              </a>
            </div>
          `;
        }
        showToast(isGitHubPages ? 'Static preview: launch email client below.' : 'Submission error. Direct email button available below.', '⚠️');
      }

      function escapeHtml(str) {
        return String(str || '')
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
      }
    });
  }

})();

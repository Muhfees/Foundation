import 'vite/modulepreload-polyfill';
import './styles/tokens.css';
import './styles/base.css';
import './styles/layout.css';
import './styles/homepage.css';
import './styles/pages.css';
import './styles/print.css';

const ALERT_STORAGE_KEY = 'nusry_dismissed_alert';

function getFocusable(container) {
  return [...container.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')].filter((el) => el.offsetParent !== null);
}

function trapFocus(container, event) {
  if (event.key !== 'Tab') {
    return;
  }
  const items = getFocusable(container);
  if (items.length === 0) {
    return;
  }
  const first = items[0];
  const last = items[items.length - 1];
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

function initAlertBanner() {
  const banner = document.querySelector('[data-urgent-banner]');
  if (!banner) {
    return;
  }
  const version = banner.dataset.alertVersion || 'v1';
  try {
    if (window.localStorage.getItem(ALERT_STORAGE_KEY) === version) {
      banner.hidden = true;
    }
  } catch {
    // Ignore storage denial.
  }
  const dismiss = banner.querySelector('[data-alert-dismiss]');
  dismiss?.addEventListener('click', () => {
    banner.hidden = true;
    try {
      window.localStorage.setItem(ALERT_STORAGE_KEY, version);
    } catch {
      // Ignore storage denial.
    }
    const headerLink = document.querySelector('.site-header .wordmark');
    headerLink?.focus();
  });
}

function initStickyHeader() {
  const header = document.querySelector('[data-site-header]');
  const sentinel = document.querySelector('[data-header-sentinel]');
  if (!header || !sentinel || !('IntersectionObserver' in window)) {
    return;
  }
  const observer = new IntersectionObserver((entries) => {
    const entry = entries[0];
    header.classList.toggle('is-compact', !entry.isIntersecting);
  }, { threshold: 0 });
  observer.observe(sentinel);
}

function initMegaMenu() {
  const header = document.querySelector('[data-site-header]');
  const triggers = [...document.querySelectorAll('[data-mega-trigger]')];
  const panels = [...document.querySelectorAll('[data-mega-panel]')];
  if (triggers.length === 0) {
    return;
  }
  let activeTrigger = null;

  const closeAll = (restoreFocus = false) => {
    for (const trigger of triggers) {
      trigger.setAttribute('aria-expanded', 'false');
    }
    for (const panel of panels) {
      panel.hidden = true;
    }
    if (restoreFocus && activeTrigger) {
      activeTrigger.focus();
    }
    activeTrigger = null;
  };

  for (const trigger of triggers) {
    trigger.addEventListener('click', () => {
      const targetId = trigger.getAttribute('aria-controls');
      const panel = targetId ? document.getElementById(targetId) : null;
      const isOpen = trigger.getAttribute('aria-expanded') === 'true';
      closeAll(false);
      if (!isOpen && panel) {
        trigger.setAttribute('aria-expanded', 'true');
        panel.hidden = false;
        activeTrigger = trigger;
      }
    });
  }

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && activeTrigger) {
      closeAll(true);
    }
  });

  document.addEventListener('click', (event) => {
    if (header && !header.contains(event.target)) {
      closeAll(false);
    }
  });
}

function initModalDialog({ triggerSelector, modalSelector, closeSelector, focusSelector, autoCloseWidth }) {
  const modal = document.querySelector(modalSelector);
  const triggers = [...document.querySelectorAll(triggerSelector)];
  if (!modal || triggers.length === 0) {
    return;
  }
  const backdrop = document.querySelector('[data-drawer-backdrop]');
  const closeBtn = modal.querySelector(closeSelector);
  let lastTrigger = null;

  const close = (restore = true) => {
    if (modal.hidden) {
      return;
    }
    modal.hidden = true;
    if (backdrop && modalSelector === '[data-mobile-drawer]') {
      backdrop.hidden = true;
    }
    for (const trigger of triggers) {
      trigger.setAttribute('aria-expanded', 'false');
    }
    if (restore && lastTrigger) {
      lastTrigger.focus();
    }
    lastTrigger = null;
  };

  const open = (trigger) => {
    lastTrigger = trigger;
    modal.hidden = false;
    if (backdrop && modalSelector === '[data-mobile-drawer]') {
      backdrop.hidden = false;
    }
    trigger.setAttribute('aria-expanded', 'true');
    const target = (focusSelector && modal.querySelector(focusSelector)) || getFocusable(modal)[0];
    target?.focus();
  };

  for (const trigger of triggers) {
    trigger.addEventListener('click', () => {
      if (modal.hidden) {
        open(trigger);
      } else {
        close(true);
      }
    });
  }

  closeBtn?.addEventListener('click', () => close(true));
  if (backdrop && modalSelector === '[data-mobile-drawer]') {
    backdrop.addEventListener('click', () => close(true));
  }
  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      close(true);
    }
  });
  modal.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      event.preventDefault();
      close(true);
    } else {
      trapFocus(modal, event);
    }
  });

  if (autoCloseWidth) {
    window.addEventListener('resize', () => {
      if (window.innerWidth >= autoCloseWidth && !modal.hidden) {
        close(false);
      }
    });
  }
}

function initTabs() {
  const tablists = [...document.querySelectorAll('[role="tablist"]')];
  for (const list of tablists) {
    const tabs = [...list.querySelectorAll('[role="tab"]')];
    const activate = (tab, moveFocus = false) => {
      for (const item of tabs) {
        const selected = item === tab;
        item.setAttribute('aria-selected', selected ? 'true' : 'false');
        item.setAttribute('tabindex', selected ? '0' : '-1');
        const panelId = item.getAttribute('aria-controls');
        const panel = panelId ? document.getElementById(panelId) : null;
        if (panel) {
          panel.hidden = !selected;
        }
      }
      if (moveFocus) {
        tab.focus();
      }
    };

    for (const tab of tabs) {
      tab.addEventListener('click', () => activate(tab, false));
      tab.addEventListener('keydown', (event) => {
        const index = tabs.indexOf(tab);
        let nextIndex = -1;
        if (event.key === 'ArrowRight') {
          nextIndex = (index + 1) % tabs.length;
        } else if (event.key === 'ArrowLeft') {
          nextIndex = (index - 1 + tabs.length) % tabs.length;
        } else if (event.key === 'Home') {
          nextIndex = 0;
        } else if (event.key === 'End') {
          nextIndex = tabs.length - 1;
        }
        if (nextIndex >= 0) {
          event.preventDefault();
          activate(tabs[nextIndex], true);
        }
      });
    }
  }
}

function initHeroDonatePanel() {
  const otherToggle = document.querySelector('[data-other-toggle]');
  const customBox = document.querySelector('[data-other-panel]');
  const customForm = document.querySelector('[data-other-form]');
  if (!otherToggle || !customBox || !customForm) {
    return;
  }
  const input = customForm.querySelector('input[name="dollars"]');
  const status = customForm.querySelector('[data-other-status]');

  otherToggle.addEventListener('click', () => {
    const opening = customBox.hidden;
    customBox.hidden = !opening;
    otherToggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
    if (opening) {
      input?.focus();
    }
  });

  customForm.addEventListener('submit', (event) => {
    event.preventDefault();
    const dollars = Number(input?.value || 0);
    if (!Number.isFinite(dollars) || dollars < 1) {
      if (status) {
        status.textContent = 'Enter a whole dollar amount of at least $1.';
        status.className = 'sr-status sr-status--error';
      }
      input?.focus();
      return;
    }
    const minor = Math.round(dollars * 100);
    const campaign = encodeURIComponent(customForm.dataset.campaign || 'DEMO-MAT-2025');
    const frequency = encodeURIComponent(customForm.dataset.frequency || 'monthly');
    window.location.href = `/donate?campaign=${campaign}&amount=${minor}&frequency=${frequency}`;
  });
}

function initNewsletterForms() {
  for (const form of document.querySelectorAll('[data-newsletter-form]')) {
    const status = form.querySelector('[data-newsletter-status]');
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const first = form.querySelector('input[name="first_name"]');
      const last = form.querySelector('input[name="last_name"]');
      const email = form.querySelector('input[name="email"]');
      const consent = form.querySelector('input[name="consent"]');
      const emailVal = (email?.value || '').trim();
      const missing = !first?.value.trim() || !last?.value.trim() || !emailVal.includes('@');
      if (missing) {
        if (status) {
          status.textContent = 'Please enter your first name, last name and a valid email address.';
          status.className = 'sr-status sr-status--error';
        }
        return;
      }
      if (!consent?.checked) {
        if (status) {
          status.textContent = 'Please check the consent box to try the demo newsletter signup.';
          status.className = 'sr-status sr-status--error';
        }
        consent?.focus();
        return;
      }
      if (status) {
        status.textContent = 'Demo subscription recorded locally (no email sent).';
        status.className = 'sr-status sr-status--success';
      }
      form.reset();
    });
  }
}

initAlertBanner();
initStickyHeader();
initMegaMenu();
initModalDialog({
  triggerSelector: '[data-drawer-open]',
  modalSelector: '[data-mobile-drawer]',
  closeSelector: '[data-drawer-close]',
  focusSelector: '[data-drawer-close]',
  autoCloseWidth: 1024,
});
initModalDialog({
  triggerSelector: '[data-search-open]',
  modalSelector: '[data-search-modal]',
  closeSelector: '[data-search-close]',
  focusSelector: '#nusry-search-input',
});
initTabs();
initHeroDonatePanel();
initNewsletterForms();

function initSensitiveReveals() {
  for (const btn of document.querySelectorAll('[data-sensitive-toggle]')) {
    const targetId = btn.getAttribute('aria-controls');
    const target = targetId ? document.getElementById(targetId) : null;
    if (!target) {
      continue;
    }
    btn.addEventListener('click', () => {
      const hidden = target.hidden;
      target.hidden = !hidden;
      btn.setAttribute('aria-expanded', hidden ? 'true' : 'false');
      btn.textContent = hidden
        ? (btn.dataset.hideLabel || 'Hide illustrative image')
        : (btn.dataset.showLabel || 'Reveal illustrative image');
    });
  }
}

function initVideoPlaceholders() {
  for (const btn of document.querySelectorAll('[data-video-load]')) {
    const targetId = btn.getAttribute('aria-controls');
    const target = targetId ? document.getElementById(targetId) : null;
    if (!target) {
      continue;
    }
    btn.addEventListener('click', () => {
      const opening = target.hidden;
      target.hidden = !opening;
      btn.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });
  }
}

function initCountryDirectoryFilter() {
  for (const root of document.querySelectorAll('[data-country-directory]')) {
    const keyword = root.querySelector('[data-dir-search]');
    const region = root.querySelector('[data-dir-region]');
    const status = root.querySelector('[data-dir-status]');
    const items = [...root.querySelectorAll('[data-dir-item]')];
    if (items.length === 0) {
      continue;
    }
    const update = () => {
      const q = (keyword?.value || '').trim().toLowerCase();
      const reg = region?.value || '';
      let visible = 0;
      for (const item of items) {
        const text = (item.textContent || '').toLowerCase();
        const itemReg = item.dataset.region || '';
        const match = (!q || text.includes(q)) && (!reg || itemReg === reg);
        item.hidden = !match;
        if (match) {
          visible += 1;
        }
      }
      if (status) {
        status.textContent = `Showing ${visible} illustrative country profiles.`;
      }
    };
    keyword?.addEventListener('input', update);
    region?.addEventListener('change', update);
  }
}

function initCopyShareLinks() {
  for (const btn of document.querySelectorAll('[data-copy-link]')) {
    const status = btn.parentElement?.querySelector('[data-copy-status]');
    btn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(window.location.href);
        if (status) {
          status.textContent = 'Page URL copied.';
        }
      } catch {
        if (status) {
          status.textContent = window.location.href;
        }
      }
    });
  }
}

initSensitiveReveals();
initVideoPlaceholders();
initCountryDirectoryFilter();
initCopyShareLinks();

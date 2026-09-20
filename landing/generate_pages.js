const fs = require('fs');
const path = require('path');

const BASE_DIR = '/Users/ajayprataptomar/Downloads/neuravolt_cloud_services/landing';

// Shared header HTML
function getHeader(activePath, depth = 0) {
  const prefix = depth === 0 ? '' : '../';
  return `
  <!-- Header / Navigation -->
  <header>
    <div class="container nav-wrapper">
      <a href="${prefix}index.html" class="logo-brand">
        <img src="${prefix}assets/logos/neuravolt_cloud_logo.png" alt="NeuraVolt Cloud Services">
      </a>
      
      <ul class="nav-links">
        <li><a href="${prefix}cloud-hosting.html" class="${activePath === 'cloud-hosting' ? 'active-nav' : ''}">Cloud Hosting</a></li>
        <li><a href="${prefix}cloud-vps.html" class="${activePath === 'cloud-vps' ? 'active-nav' : ''}">VPS & Compute</a></li>
        <li><a href="${prefix}managed-n8n.html" class="${activePath === 'managed-n8n' ? 'active-nav' : ''}">Managed n8n</a></li>
        <li><a href="${prefix}guardian-sre.html" class="${activePath === 'guardian-sre' ? 'active-nav' : ''}">Guardian SRE</a></li>
        <li><a href="${prefix}index.html#pricing">All Plans</a></li>
      </ul>

      <div class="nav-actions">
        <a href="https://app.neuravolt.cloud" target="_blank" class="btn btn-secondary btn-sm">Client Login</a>
        <a href="#pricing" class="btn btn-primary btn-sm">Deploy Now</a>
      </div>
    </div>
  </header>
`;
}

// Shared Footer HTML
function getFooter(depth = 0) {
  const prefix = depth === 0 ? '' : '../';
  return `
  <!-- Footer -->
  <footer>
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <img src="${prefix}assets/logos/neuravolt_cloud_logo_dark.png?v=4" alt="NeuraVolt Cloud Services" style="height: 44px; max-width: 220px; object-fit: contain; margin-bottom: 8px;">
          <p>Next-generation autonomous cloud infrastructure, high-frequency compute, and self-healing reliability engineered for modern enterprises.</p>
        </div>

        <div class="footer-col">
          <h4>Dedicated Products</h4>
          <ul>
            <li><a href="${prefix}cloud-hosting.html">High-Speed Cloud Web Hosting</a></li>
            <li><a href="${prefix}cloud-vps.html">Cloud VPS & Dedicated Compute</a></li>
            <li><a href="${prefix}managed-n8n.html">Managed n8n Automation Nodes</a></li>
            <li><a href="${prefix}guardian-sre.html">Guardian SRE Self-Healing Watchdog</a></li>
            <li><a href="${prefix}index.html#pricing">All Infrastructure Pricing</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Customer Portals</h4>
          <ul>
            <li><a href="https://app.neuravolt.cloud" target="_blank" style="color: #FF7722; font-weight: 600;">🚀 Client Portal (n8n)</a></li>
            <li><a href="https://reach.neuravolt.cloud" target="_blank">⚡ NeuraVolt Reach</a></li>
            <li><a href="https://monitor.neuravolt.cloud" target="_blank">📊 Metrics Telemetry</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Company & Support</h4>
          <ul>
            <li><a href="https://neuravolt.in" target="_blank">NeuraVolt Technologies</a></li>
            <li><a href="https://neuravolt.in/privacy" target="_blank">Privacy Policy</a></li>
            <li><a href="https://neuravolt.in/terms" target="_blank">Terms of Service</a></li>
            <li><a href="mailto:info@neuravolt.in">info@neuravolt.in</a></li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <div>
          &copy; 2026 NeuraVolt Cloud Services. All rights reserved. Powered by <a href="https://neuravolt.in" target="_blank">VOLTNOVATE SOLUTIONS LLP</a>.
        </div>
        <div>
          <span>Autonomous Reliability • Zero Downtime • Continuous SRE Defense</span>
        </div>
      </div>
    </div>
  </footer>
`;
}

// Shared Modal & Scripts HTML
function getModalAndScripts(defaultCategory, defaultPlan, depth = 0) {
  return `
  <!-- Lead Capture Modal (Hostinger Standard with OTP Verification) -->
  <div id="leadModal" class="modal-backdrop" onclick="handleBackdropClick(event)">
    <div class="lead-modal">
      <button class="modal-close" onclick="closeLeadModal()">&times;</button>
      
      <!-- Form Content -->
      <div id="modalFormContent">
        <div class="modal-header">
          <span class="service-pill" id="modalServiceBadge">Cloud Services</span>
          <h3 id="modalTitle">Deploy Cloud Service</h3>
          <p id="modalDesc">Enter your details to initiate instant provisioning and access your client credentials.</p>
        </div>

        <form id="leadCaptureForm" onsubmit="handleLeadSubmit(event)">
          <div class="form-group">
            <label for="leadName">Full Name / Organization *</label>
            <input type="text" id="leadName" class="form-input" placeholder="e.g. Ajay Pratap / TechCorp" required>
          </div>

          <!-- Email with Integrated OTP Verification -->
          <div class="form-group">
            <label for="leadEmail">Work Email Address *</label>
            <div style="display: flex; gap: 8px;">
              <input type="email" id="leadEmail" class="form-input" placeholder="name@company.com" required style="flex: 1;">
              <button type="button" id="sendOtpBtn" onclick="handleSendOtp()" class="btn btn-secondary" style="padding: 0 16px; font-size: 0.85rem; white-space: nowrap;">
                Send OTP
              </button>
            </div>
            <span id="otpStatusMsg" style="font-size: 0.78rem; margin-top: 4px; display: block; color: var(--text-subtle);"></span>
            
            <!-- OTP Verification Row -->
            <div id="otpGroup" style="display: none; margin-top: 10px; background: var(--bg-subtle); padding: 12px; border-radius: 8px; border: 1px dashed var(--border);">
              <label for="leadOtp" style="font-size: 0.8rem; font-weight: 600; color: var(--text); display: block; margin-bottom: 6px;">Enter 6-Digit Verification Code</label>
              <div style="display: flex; gap: 8px;">
                <input type="text" id="leadOtp" class="form-input" placeholder="123456" maxlength="6" style="letter-spacing: 4px; font-weight: 700; text-align: center; width: 140px;">
                <button type="button" id="verifyOtpBtn" onclick="handleVerifyOtp()" class="btn btn-primary" style="padding: 0 16px; font-size: 0.85rem;">
                  Verify OTP
                </button>
              </div>
            </div>

            <!-- Verified Badge -->
            <div id="emailVerifiedBadge" style="display: none; color: #10B981; font-size: 0.85rem; font-weight: 600; margin-top: 8px; align-items: center; gap: 6px;">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              <span>Email Verified Successfully</span>
            </div>
          </div>

          <div class="form-group">
            <label for="leadPhone">Phone / WhatsApp *</label>
            <input type="tel" id="leadPhone" class="form-input" placeholder="+91 9876543210" required>
          </div>

          <!-- Context-Aware Dynamic Select -->
          <div class="form-group">
            <label for="leadService">Selected Plan / Service *</label>
            <select id="leadService" class="form-select" required>
              <!-- Populated dynamically -->
            </select>
          </div>

          <div class="form-group">
            <label for="leadNotes">Custom Notes / Specs (Optional)</label>
            <textarea id="leadNotes" class="form-textarea" placeholder="Provide any custom RAM, storage, or migration requirements..."></textarea>
          </div>

          <div class="modal-footer">
            <button type="submit" id="submitLeadBtn" class="btn btn-primary" disabled style="opacity: 0.5; cursor: not-allowed;">
              <span>Verify Email to Submit</span>
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
          </div>
        </form>
      </div>

      <!-- Success State -->
      <div id="modalSuccessContent" class="modal-success" style="display: none;">
        <div class="success-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <h3>Request Confirmed!</h3>
        <p>Your verified inquiry has been logged. An automated confirmation with full service specifications has been sent to your email. Our cloud engineering team will reach out shortly with access details.</p>
        <button onclick="closeLeadModal()" class="btn btn-secondary">Close Window</button>
      </div>

    </div>
  </div>

  <!-- Shared JavaScript -->
  <script>
    function toggleFaq(button) {
      const item = button.parentElement;
      item.classList.toggle('active');
    }

    const SERVICE_CATEGORIES = {
      hosting: {
        badge: "NV Ultra Web Hosting",
        title: "Deploy NV Ultra Web Hosting",
        desc: "High-concurrency NVMe shared cloud powered by FastPanel and LiteSpeed/Nginx.",
        options: [
          { id: "hosting-starter", label: "Starter Web — ₹149/mo (1 Website, 10GB NVMe)" },
          { id: "hosting-business", label: "Business Web — ₹299/mo (5 Websites, 50GB NVMe, Free SSL)", default: true },
          { id: "hosting-agency", label: "Agency Pro — ₹599/mo (Unlimited Sites, 150GB NVMe, Dedicated IP)" }
        ]
      },
      vps: {
        badge: "Cloud VPS & Dedicated Compute",
        title: "Provision Cloud VPS / Bare Metal",
        desc: "Dedicated virtual CPU and raw compute with full root SSH control and 1Gbps uplink.",
        options: [
          { id: "vps-4g", label: "Cloud VPS 4G — ₹1,499/mo (2 vCPU, 4GB RAM, 80GB NVMe)" },
          { id: "vps-8g", label: "Cloud VPS 8G — ₹2,799/mo (4 vCPU, 8GB RAM, 160GB NVMe)", default: true },
          { id: "bare-metal", label: "Dedicated Bare Metal — ₹4,999/mo (8 Physical Cores, 32GB RAM, 500GB NVMe)" }
        ]
      },
      nodes: {
        badge: "Managed n8n Node",
        title: "Deploy Managed n8n Node",
        desc: "Fully managed n8n runtime with dedicated Docker resources and automated backups.",
        options: [
          { id: "n8n-basic", label: "n8n Basic — ₹999/mo (0.5 Core, 512MB RAM, 10GB NVMe)" },
          { id: "n8n-pro", label: "n8n Pro Node — ₹1,999/mo (1.0 Core, 1GB RAM, 25GB NVMe)", default: true },
          { id: "n8n-heavy", label: "n8n Heavy Enterprise — ₹3,999/mo (2.0 Cores, 2GB RAM, 50GB NVMe)" }
        ]
      },
      guardian: {
        badge: "Guardian SRE Autonomous Defense",
        title: "Activate Guardian SRE Defense",
        desc: "Autonomous monitoring, instant self-healing, and multi-channel incident alerts.",
        options: [
          { id: "guardian-audit", label: "SRE Vulnerability Audit — FREE (Comprehensive Server Audit)", default: true },
          { id: "guardian-pro", label: "Guardian Pro — ₹1,999/mo (1-Sec Heartbeats, Auto-Remediation)" },
          { id: "guardian-fleet", label: "Guardian Fleet Enterprise — Custom (Unlimited Nodes & SRE Engineers)" }
        ]
      }
    };

    let isEmailVerified = false;
    let timerInterval = null;

    function openLeadModal(categoryKey = '${defaultCategory}', planId = '${defaultPlan}') {
      const modal = document.getElementById('leadModal');
      const category = SERVICE_CATEGORIES[categoryKey] || SERVICE_CATEGORIES['${defaultCategory}'];

      document.getElementById('modalServiceBadge').innerText = category.badge;
      document.getElementById('modalTitle').innerText = category.title;
      document.getElementById('modalDesc').innerText = category.desc;

      const select = document.getElementById('leadService');
      select.innerHTML = '';
      category.options.forEach(opt => {
        const option = document.createElement('option');
        option.value = opt.id;
        option.innerText = opt.label;
        if (opt.id === planId || (!planId && opt.default)) {
          option.selected = true;
        }
        select.appendChild(option);
      });

      document.getElementById('modalFormContent').style.display = 'block';
      document.getElementById('modalSuccessContent').style.display = 'none';

      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeLeadModal() {
      const modal = document.getElementById('leadModal');
      modal.classList.remove('active');
      document.body.style.overflow = '';
      clearInterval(timerInterval);
    }

    function handleBackdropClick(e) {
      if (e.target.id === 'leadModal') {
        closeLeadModal();
      }
    }

    function getApiBase() {
      return (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        ? 'http://localhost:3000/api'
        : 'https://api.neuravolt.cloud';
    }

    async function apiPost(endpoint, data) {
      const baseUrl = getApiBase();
      const res = await fetch(baseUrl + endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      return await res.json();
    }

    function startOtpCountdown(seconds = 45) {
      const sendBtn = document.getElementById('sendOtpBtn');
      sendBtn.disabled = true;
      let remaining = seconds;

      sendBtn.innerText = 'Resend in ' + remaining + 's';

      timerInterval = setInterval(() => {
        remaining--;
        if (remaining <= 0) {
          clearInterval(timerInterval);
          sendBtn.disabled = false;
          sendBtn.innerText = 'Resend OTP';
        } else {
          sendBtn.innerText = 'Resend in ' + remaining + 's';
        }
      }, 1000);
    }

    async function handleSendOtp() {
      const emailInput = document.getElementById('leadEmail');
      const email = emailInput.value.trim();
      const statusMsg = document.getElementById('otpStatusMsg');
      const sendBtn = document.getElementById('sendOtpBtn');

      if (!email || !email.includes('@') || !email.includes('.')) {
        alert('Please enter a valid work email address first.');
        emailInput.focus();
        return;
      }

      sendBtn.disabled = true;
      sendBtn.innerText = 'Sending...';
      statusMsg.style.display = 'block';
      statusMsg.style.color = '#0284C7';
      statusMsg.innerText = 'Sending 6-digit verification code to your email...';

      try {
        const result = await apiPost('/leads/send-otp', { email });
        if (result && result.success) {
          statusMsg.style.color = '#10B981';
          statusMsg.innerHTML = '<strong>✓ OTP Sent!</strong> Please check your inbox.';
          document.getElementById('otpGroup').style.display = 'block';
          document.getElementById('leadOtp').focus();
          startOtpCountdown(45);
        } else {
          statusMsg.style.color = '#EF4444';
          statusMsg.innerText = result.message || 'Failed to send OTP.';
          sendBtn.disabled = false;
          sendBtn.innerText = 'Send OTP';
        }
      } catch (err) {
        statusMsg.style.color = '#EF4444';
        statusMsg.innerText = err.message || 'Network error sending OTP.';
        sendBtn.disabled = false;
        sendBtn.innerText = 'Send OTP';
      }
    }

    async function handleVerifyOtp() {
      const email = document.getElementById('leadEmail').value.trim();
      const otpInput = document.getElementById('leadOtp');
      const otp = otpInput.value.trim();
      const statusMsg = document.getElementById('otpStatusMsg');
      const verifyBtn = document.getElementById('verifyOtpBtn');

      if (!otp || otp.length < 6) {
        alert('Please enter the 6-digit OTP code.');
        otpInput.focus();
        return;
      }

      verifyBtn.disabled = true;
      verifyBtn.innerText = 'Checking...';

      try {
        const result = await apiPost('/leads/verify-otp', { email, otp });
        if (result && result.verified) {
          isEmailVerified = true;
          clearInterval(timerInterval);

          document.getElementById('otpGroup').style.display = 'none';
          document.getElementById('sendOtpBtn').style.display = 'none';
          document.getElementById('leadEmail').readOnly = true;

          const badge = document.getElementById('emailVerifiedBadge');
          badge.style.display = 'flex';

          statusMsg.style.display = 'none';

          const submitBtn = document.getElementById('submitLeadBtn');
          submitBtn.disabled = false;
          submitBtn.style.opacity = '1';
          submitBtn.style.cursor = 'pointer';
          submitBtn.innerHTML = '<span>Confirm & Deploy Service</span>';
        } else {
          statusMsg.style.display = 'block';
          statusMsg.style.color = '#EF4444';
          statusMsg.innerText = result.message || 'Invalid verification code.';
          verifyBtn.disabled = false;
          verifyBtn.innerText = 'Verify OTP';
        }
      } catch (err) {
        statusMsg.style.display = 'block';
        statusMsg.style.color = '#EF4444';
        statusMsg.innerText = err.message || 'Verification failed.';
        verifyBtn.disabled = false;
        verifyBtn.innerText = 'Verify OTP';
      }
    }

    async function handleLeadSubmit(e) {
      e.preventDefault();

      if (!isEmailVerified) {
        alert('Please verify your email with the 6-digit OTP first.');
        document.getElementById('leadEmail').focus();
        return;
      }

      const submitBtn = document.getElementById('submitLeadBtn');
      submitBtn.disabled = true;
      submitBtn.innerText = 'Submitting Request...';

      const payload = {
        name: document.getElementById('leadName').value.trim(),
        email: document.getElementById('leadEmail').value.trim(),
        phone: document.getElementById('leadPhone').value.trim(),
        service: document.getElementById('leadService').value,
        website: window.location.hostname,
        notes: document.getElementById('leadNotes').value.trim()
      };

      try {
        const data = await apiPost('/leads/capture', payload);
        if (data && (data.success || data.leadId)) {
          document.getElementById('modalFormContent').style.display = 'none';
          document.getElementById('modalSuccessContent').style.display = 'block';
          document.getElementById('leadCaptureForm').reset();
          isEmailVerified = false;
        } else {
          alert('Submission note: ' + (data.message || 'Please verify your information.'));
          submitBtn.disabled = false;
          submitBtn.innerText = 'Confirm & Deploy Service';
        }
      } catch (err) {
        console.error('Lead submission error:', err);
        alert('Could not submit lead: ' + err.message);
        submitBtn.disabled = false;
        submitBtn.innerText = 'Confirm & Deploy Service';
      }
    }
  </script>
</body>
</html>
`;
}

console.log('Template helpers ready');

module.exports = { getHeader, getFooter, getModalAndScripts };

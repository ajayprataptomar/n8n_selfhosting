const { getHeader, getFooter, getModalAndScripts } = require('./generate_pages.js');
const fs = require('fs');
const path = require('path');

const LANDING_DIR = '/Users/ajayprataptomar/Downloads/neuravolt_cloud_services/landing';

// Append extra styles to style.css if not already added
const stylePath = path.join(LANDING_DIR, 'style.css');
let cssContent = fs.readFileSync(stylePath, 'utf8');

if (!cssContent.includes('.breadcrumb')) {
  const extraCss = `
/* --- SEO & DEDICATED PRODUCT PAGE ENHANCEMENTS --- */
.active-nav {
  color: var(--primary) !important;
  font-weight: 700 !important;
}

.breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.825rem;
  color: var(--text-subtle);
  margin-bottom: 2rem;
  flex-wrap: wrap;
}

.breadcrumb a {
  color: var(--text-muted);
  text-decoration: none;
  transition: var(--transition);
}

.breadcrumb a:hover {
  color: var(--primary);
}

.breadcrumb span.current {
  color: var(--primary);
  font-weight: 600;
}

.product-full-link {
  text-align: center;
  margin-top: 2.25rem;
  padding: 1.25rem;
  background: var(--bg-subtle);
  border-radius: 14px;
  border: 1px dashed var(--border);
}

.product-full-link a {
  color: var(--primary);
  font-weight: 700;
  font-size: 0.9375rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  transition: var(--transition);
}

.product-full-link a:hover {
  color: var(--primary-hover);
  transform: translateX(4px);
}

/* Feature Showcase Grid */
.feature-showcase-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
  margin-top: 40px;
}

.showcase-card {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 28px;
  box-shadow: var(--shadow-sm);
  transition: var(--transition);
}

.showcase-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-md);
  border-color: rgba(255, 85, 0, 0.3);
}

.showcase-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: rgba(255, 85, 0, 0.1);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 18px;
}

.showcase-card h3 {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 10px;
}

.showcase-card p {
  font-size: 0.88rem;
  color: var(--text-muted);
  line-height: 1.6;
}

/* Tech Specs Table */
.specs-table-wrapper {
  overflow-x: auto;
  margin-top: 36px;
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 16px;
  box-shadow: var(--shadow-sm);
}

.specs-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.88rem;
}

.specs-table th {
  background: var(--bg-subtle);
  padding: 16px 20px;
  font-weight: 700;
  color: var(--text);
  border-bottom: 1px solid var(--border);
}

.specs-table td {
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  color: var(--text-muted);
}

.specs-table tr:last-child td {
  border-bottom: none;
}

.specs-table tr:hover td {
  background: rgba(248, 250, 252, 0.8);
}

.specs-table td strong {
  color: var(--text);
}
`;
  fs.appendFileSync(stylePath, extraCss);
  console.log('✓ Updated style.css with SEO and showcase classes');
}

// -------------------------------------------------------------
// PRODUCT DEFINITIONS
// -------------------------------------------------------------
const PRODUCTS = [
  {
    slug: 'cloud-hosting',
    title: 'NVMe Cloud Web Hosting India | FastPanel, Free SSL & 99.99% Uptime | NeuraVolt Cloud',
    metaDesc: 'Deploy lightning-fast NVMe cloud hosting in India. FastPanel control panel, 100% Gen4 NVMe storage, unmetered bandwidth, free automated SSL, and Guardian SRE protection starting at ₹149/mo.',
    canonical: 'https://neuravolt.cloud/cloud-hosting',
    badge: 'Ultra-High Concurrency NVMe Web Hosting',
    h1: 'Ultra-Fast NVMe Cloud Web Hosting.<br><span class="gradient-text">Engineered for Maximum Speed & 99.99% Uptime.</span>',
    heroSub: 'Host your websites, blogs, e-commerce stores, and agency clients on high-frequency NVMe cloud servers. Powered by modern FastPanel, HTTP/3 QUIC, free SSL certificates, and automated nightly backups.',
    ctaText: 'Deploy Web Hosting Now',
    schemaType: 'WebHostingService',
    priceStarting: '149',
    plans: [
      {
        id: 'hosting-starter',
        name: 'Starter Web',
        desc: 'For personal blogs, single portfolios, and landing pages needing guaranteed uptime.',
        price: '₹149',
        period: '/month',
        featured: false,
        btnText: 'Deploy Starter Web',
        specs: [
          '1 Hosted Website',
          '10 GB Gen4 NVMe Storage',
          'Unmetered High-Speed Bandwidth',
          'Free Let\'s Encrypt SSL',
          'FastPanel Modern Control Panel',
          'PHP 8.1 - 8.3 & Node.js Support',
          '24/7 Automated Monitoring'
        ]
      },
      {
        id: 'hosting-business',
        name: 'Business Web',
        desc: 'For businesses, dynamic WordPress sites, and fast e-commerce stores.',
        price: '₹299',
        period: '/month',
        featured: true,
        ribbon: 'Most Popular',
        btnText: 'Deploy Business Web',
        specs: [
          '5 Hosted Websites',
          '50 GB Gen4 NVMe Storage',
          'Free Domain (Annual Plan)',
          'Unlimited Free Wildcard SSL',
          'Daily Automated Snapshot Backups',
          'Redis Object Caching Enabled',
          'Integrated Guardian SRE Defense',
          'Priority WhatsApp & Ticket Support'
        ]
      },
      {
        id: 'hosting-agency',
        name: 'Agency Pro',
        desc: 'For digital agencies, web developers, and high-traffic multi-site portfolios.',
        price: '₹599',
        period: '/month',
        featured: false,
        btnText: 'Deploy Agency Pro',
        specs: [
          'Unlimited Hosted Websites',
          '150 GB Gen4 NVMe Storage',
          'Dedicated Clean Static IPv4',
          'Unlimited FastPanel Sub-Accounts',
          'Isolated Linux Containers per site',
          'Staging & Git Deployment Hooks',
          'Guardian Autonomous Self-Healer',
          'VIP 24/7 Dedicated Slack Support'
        ]
      }
    ],
    features: [
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m13 2-2 2.5h3L11 7h3l-5 7 2-4H8l2-4H7l3-4Z"/><path d="M13 14h-3l2-4"/></svg>',
        title: 'PCIe Gen4 NVMe Architecture',
        desc: 'Experience up to 7,000 MB/s read and write speeds. Database queries and dynamic PHP pages load up to 5x faster than conventional SATA SSD hosting.'
      },
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 8h10"/><path d="M7 12h10"/><path d="M7 16h10"/></svg>',
        title: 'Modern FastPanel Control',
        desc: 'Say goodbye to bloated legacy control panels. FastPanel offers instantaneous domain management, one-click WordPress, php version selector, and cron manager.'
      },
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
        title: 'Guardian SRE Defense Standard',
        desc: 'Continuous autonomous health checks. If an Apache/Nginx worker hangs or consumes excess memory, Guardian restarts it in milliseconds before visitors notice.'
      },
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 4.24 4.24"/><path d="m14.83 9.17 4.24-4.24"/><path d="m14.83 14.83 4.24 4.24"/><path d="m9.17 14.83-4.24 4.24"/></svg>',
        title: 'Free Let\'s Encrypt SSL',
        desc: 'Automated HTTPS provisioning for all domains and subdomains. Zero manual renewals with strict TLS 1.3 encryption and HSTS headers.'
      }
    ],
    comparison: [
      { feature: 'Storage Drive Tech', nv: '100% PCIe Gen4 NVMe', trad: 'Legacy SATA SSD / HDD', host: 'Shared SATA / NVMe' },
      { feature: 'Average TTFB (India)', nv: '< 120 ms', trad: '450 - 800 ms', host: '280 - 450 ms' },
      { feature: 'Control Panel', nv: 'FastPanel (Ultra-Fast)', trad: 'cPanel (Slow/Bloated)', host: 'Custom hPanel' },
      { feature: 'Autonomous Self-Healing', nv: 'Included (Guardian SRE)', trad: 'None (Manual Reboot)', host: 'Basic Monitoring' },
      { feature: 'Daily Backups', nv: 'Automated Nightly', trad: 'Paid Add-on', host: 'Weekly on budget plans' }
    ],
    faqs: [
      {
        q: 'How fast is NeuraVolt Cloud Web Hosting?',
        a: 'Our cloud servers are hosted in Tier-4 Indian datacenters with 100% PCIe Gen4 NVMe storage and LiteSpeed/Nginx acceleration. Typical time-to-first-byte (TTFB) is under 150ms across India.'
      },
      {
        q: 'Can I migrate my existing WordPress site for free?',
        a: 'Yes! Our cloud engineering team provides 100% free white-glove site migration with zero downtime. Simply request migration after signing up.'
      },
      {
        q: 'Do you provide email accounts with web hosting?',
        a: 'Yes, full corporate email accounts, webmail access, anti-spam filters, and DKIM/SPF/DMARC routing are included standard across all tiers.'
      },
      {
        q: 'What control panel is provided?',
        a: 'All plans include FastPanel — a fast, lightweight, and modern control panel with full support for PHP 7.4 through 8.3, Node.js, MariaDB, SSL, and DNS management.'
      }
    ]
  },

  {
    slug: 'cloud-vps',
    title: 'High-Performance NVMe Cloud VPS & Dedicated Compute Servers India | Root SSH | NeuraVolt Cloud',
    metaDesc: 'Deploy high-frequency NVMe Cloud VPS and Bare Metal compute in India. 1Gbps unmetered port, dedicated AMD EPYC / Intel Xeon vCPUs, clean static IPv4, full root SSH, and 99.99% uptime SLA.',
    canonical: 'https://neuravolt.cloud/cloud-vps',
    badge: 'Enterprise Bare Metal & KVM Virtualization',
    h1: 'High-Frequency NVMe Cloud VPS & Compute.<br><span class="gradient-text">Raw Power, Dedicated Resources & 1Gbps Uplink.</span>',
    heroSub: 'Spin up dedicated KVM virtual private servers and raw bare-metal hardware with full root SSH control. Engineered for Docker stacks, production databases, microservices, and AI model inference.',
    ctaText: 'Provision VPS Instance',
    schemaType: 'VirtualPrivateServer',
    priceStarting: '1499',
    plans: [
      {
        id: 'vps-4g',
        name: 'Cloud VPS 4G',
        desc: 'For modern backend APIs, microservices, and medium traffic production applications.',
        price: '₹1,499',
        period: '/month',
        featured: false,
        btnText: 'Provision VPS 4G',
        specs: [
          '2 High-Frequency vCPUs',
          '4 GB Dedicated ECC RAM',
          '80 GB Gen4 NVMe Storage',
          'Full Root Access via SSH',
          '1 Dedicated Clean IPv4',
          '1 Gbps High-Throughput Port',
          'Ubuntu / Debian / AlmaLinux OS'
        ]
      },
      {
        id: 'vps-8g',
        name: 'Cloud VPS 8G',
        desc: 'For production databases, heavy Docker stacks, enterprise ERP, and high-concurrency SaaS.',
        price: '₹2,799',
        period: '/month',
        featured: true,
        ribbon: 'Recommended',
        btnText: 'Provision VPS 8G',
        specs: [
          '4 High-Frequency vCPUs',
          '8 GB Dedicated ECC RAM',
          '160 GB Gen4 NVMe Storage',
          '1 Gbps Unmetered Port',
          'Automated Weekly Snapshot Backups',
          'Guardian Autonomous SRE Monitor',
          'Custom Reverse DNS (rDNS)',
          'Priority Engineering Assistance'
        ]
      },
      {
        id: 'bare-metal',
        name: 'Dedicated Bare Metal',
        desc: 'For enterprise workloads requiring 100% physically isolated bare metal hardware.',
        price: '₹4,999',
        period: '/month',
        featured: false,
        btnText: 'Deploy Bare Metal',
        specs: [
          '8 Dedicated Physical Cores',
          '32 GB ECC DDR4/DDR5 RAM',
          '500 GB NVMe Mirror (RAID 1)',
          'Out-of-Band IPMI / KVM Access',
          'Hardware DDoS Protection',
          'Custom Network Routing & SLA',
          'Dedicated SRE Technical Account Lead'
        ]
      }
    ],
    features: [
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="8" x="2" y="2" rx="2" ry="2"/><rect width="20" height="8" x="2" y="14" rx="2" ry="2"/><line x1="6" x2="6.01" y1="6" y2="6"/><line x1="6" x2="6.01" y1="18" y2="18"/></svg>',
        title: '100% Dedicated Compute Slices',
        desc: 'Zero CPU stealing or memory overcommit. Your virtual CPU cores and RAM are physically reserved on enterprise AMD EPYC and Intel Xeon Scalable hardware.'
      },
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>',
        title: '1 Gbps Ultra-Low Latency Uplink',
        desc: 'Direct peering with major Indian internet exchanges (NIXI, Extreme IX) ensuring sub-15ms round-trip times to Mumbai, Delhi, Bangalore, and Hyderabad.'
      },
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" x2="20" y1="19" y2="19"/></svg>',
        title: 'Full Root Access & Custom Kernels',
        desc: 'Total control over your operating system. Install Docker, Kubernetes, wireguard, custom firewalls, or swap kernel parameters without cloud provider locks.'
      },
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        title: 'Pre-Installed BYOVPS Guardian Agent',
        desc: 'Every VPS instance connects seamlessly to the NeuraVolt Guardian Dashboard, offering real-time memory leak detection, automated service recovery, and alert routing.'
      }
    ],
    comparison: [
      { feature: 'Compute CPU Guarantee', nv: '100% Dedicated vCPU', trad: 'Shared Burstable (EC2 t3)', host: 'Shared KVM' },
      { feature: 'Storage Technology', nv: 'Direct Gen4 NVMe', trad: 'EBS Network Attached Disk', host: 'Standard SSD' },
      { feature: 'Network Outbound Pricing', nv: 'Unmetered Fair-Use', trad: '$0.09 per GB (Expensive)', host: 'Strict Capped Bandwidth' },
      { feature: 'Autonomous SRE Remediation', nv: 'Built-in Standard', trad: 'CloudWatch (Costly & DIY)', host: 'Manual Intervention' },
      { feature: 'Provisioning Speed', nv: '< 60 Seconds', trad: '2 - 5 Minutes', host: '5 - 10 Minutes' }
    ],
    faqs: [
      {
        q: 'Do I get dedicated IPv4 and full root access?',
        a: 'Yes, every NeuraVolt Cloud VPS includes a dedicated static clean IPv4 address and full root SSH credentials. You have complete administrative control over the machine.'
      },
      {
        q: 'Which operating systems are supported?',
        a: 'We provide one-click instant images for Ubuntu 22.04 / 24.04 LTS, Debian 12, AlmaLinux 9, Rocky Linux, and custom ISO mounting upon request.'
      },
      {
        q: 'Can I scale CPU and RAM as my application grows?',
        a: 'Absolutely. You can seamlessly resize your VPS CPU, RAM, and NVMe disk without losing your IP address or reinstalling your operating system.'
      },
      {
        q: 'Where are your physical server datacenters located?',
        a: 'Our primary compute clusters are located in Tier-4 certified enterprise datacenters in Mumbai and Bangalore, India, offering direct NIXI peering and enterprise hardware firewalls.'
      }
    ]
  },

  {
    slug: 'managed-n8n',
    title: 'Managed n8n Hosting India | Dedicated Workflow Automation Nodes | NeuraVolt Cloud',
    metaDesc: 'Deploy fully managed, high-concurrency n8n nodes in India. Isolated Docker containers, auto-restart queue engine, custom domain mapping, instant SSL, and automated nightly backups starting at ₹999/mo.',
    canonical: 'https://neuravolt.cloud/managed-n8n',
    badge: 'Isolated Docker Workflow Execution Architecture',
    h1: 'Managed n8n Workflow Automation Nodes.<br><span class="gradient-text">Zero Maintenance, Dedicated Resources & Auto-Scaling.</span>',
    heroSub: 'Run mission-critical automation pipelines, CRM integrations, AI agent workflows, and webhook queues on dedicated n8n instances. No server management, no memory leak crashes, and 100% uptime.',
    ctaText: 'Deploy Managed n8n Node',
    schemaType: 'SoftwareApplication',
    priceStarting: '999',
    plans: [
      {
        id: 'n8n-basic',
        name: 'n8n Basic Node',
        desc: 'For solopreneurs and developers automating personal projects and light webhooks.',
        price: '₹999',
        period: '/month',
        featured: false,
        btnText: 'Deploy n8n Basic',
        specs: [
          '0.5 Dedicated CPU Core',
          '512 MB High-Speed RAM',
          '10 GB Gen4 NVMe Persistent Disk',
          'Instant Subdomain & SSL Certificate',
          'Docker Container Auto-Restart Engine',
          'Community Nodes & Custom NPM Support',
          'Standard Webhook Queue Processing'
        ]
      },
      {
        id: 'n8n-pro',
        name: 'n8n Pro Node',
        desc: 'For marketing agencies, CRM syncs, high-volume production webhooks, and active pipelines.',
        price: '₹1,999',
        period: '/month',
        featured: true,
        ribbon: 'Most Popular',
        btnText: 'Deploy Pro Node',
        specs: [
          '1.0 Dedicated CPU Core',
          '1024 MB High-Speed RAM',
          '25 GB Gen4 NVMe Storage',
          'Custom Domain Binding (e.g. n8n.yourdomain.com)',
          'Automated Nightly Snapshot Backups',
          'High-Throughput Webhook Worker',
          'Guardian Autonomous Crash Recovery',
          'Priority WhatsApp Support'
        ]
      },
      {
        id: 'n8n-heavy',
        name: 'n8n Heavy Enterprise',
        desc: 'For enterprise automation fleets, multi-worker queues, AI agents, and heavy payload transforms.',
        price: '₹3,999',
        period: '/month',
        featured: false,
        btnText: 'Deploy Heavy Node',
        specs: [
          '2.0 Dedicated CPU Cores',
          '2048 MB Dedicated RAM',
          '50 GB Gen4 NVMe Storage',
          'Dedicated Redis Queue Scaling',
          'Sub-50ms Global Webhook Response',
          'Custom Python/Node Module Injection',
          'Dedicated Slack Engineering Channel',
          '1-on-1 Automation Architecture Advisory'
        ]
      }
    ],
    features: [
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>',
        title: '100% Isolated Docker Sandbox',
        desc: 'Every client runs inside an isolated Docker container with reserved CPU, RAM, and persistent volume mount. No noisy neighbors or shared runtime bottlenecks.'
      },
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>',
        title: 'Custom Domain & Instant SSL',
        desc: 'Map your own custom white-labeled domain (e.g. automation.agency.com) in seconds with automated Let\'s Encrypt TLS certificates and HTTP/2 protocol.'
      },
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>',
        title: 'Automated Crash Protection',
        desc: 'Heavy loops or memory leaks in custom JavaScript nodes? Guardian SRE detects container freezes and auto-heals the instance in under 2 seconds without workflow loss.'
      },
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        title: 'Community & Custom Node Packages',
        desc: 'Easily install community nodes from npm, inject external AI library credentials (OpenAI, Anthropic, Gemini), and integrate WhatsApp Business APIs seamlessly.'
      }
    ],
    comparison: [
      { feature: 'Execution Limits', nv: 'Unlimited Executions (Hardware Capped)', trad: 'Hard Execution Limits (n8n Cloud)', host: 'Requires Manual Setup & DevOps' },
      { feature: 'Custom Domain SSL', nv: 'Included on all tiers', trad: 'Only on higher $50+ tiers', host: 'Requires manual Traefik/Nginx' },
      { feature: 'Workflow Auto-Backups', nv: 'Nightly Persistent Snapshots', trad: 'Cloud Storage Only', host: 'DIY cron scripts' },
      { feature: 'Crash Self-Healing', nv: 'Sub-2s Guardian SRE', trad: 'Standard cloud restart', host: 'Manual SSH reboot' },
      { feature: 'Pricing', nv: 'Starts at ₹999/mo ($12)', trad: 'Starts at $20 - $50/mo', host: 'VPS cost + DevOps labor' }
    ],
    faqs: [
      {
        q: 'Do you impose limits on workflow runs or executions?',
        a: 'No! Unlike n8n.cloud which bills per execution, NeuraVolt Managed n8n has zero artificial execution limits. Your node runs as many workflows as your dedicated hardware resources can handle.'
      },
      {
        q: 'Can I connect custom domains and white-label n8n?',
        a: 'Yes, on Pro and Enterprise tiers you can bind any custom domain (e.g. workflow.mybrand.com) with automated SSL certificates.'
      },
      {
        q: 'How are backups handled for my workflows and credentials?',
        a: 'Your entire SQLite/Postgres database and encryption key are backed up in nightly snapshot archives, ensuring your sensitive workflow credentials are safe.'
      },
      {
        q: 'Can I install community nodes from npm?',
        a: 'Yes, full community node support is enabled. You can install custom nodes directly from the n8n UI or request custom node package additions.'
      }
    ]
  },

  {
    slug: 'guardian-sre',
    title: 'Guardian Autonomous SRE Defense & 24/7 Server Monitoring | NeuraVolt Cloud',
    metaDesc: 'Protect your cloud servers with NeuraVolt Guardian SRE. Real-time eBPF metric telemetry, sub-second failure detection, autonomous service healing, and multi-channel incident alerts across WhatsApp & Slack.',
    canonical: 'https://neuravolt.cloud/guardian-sre',
    badge: 'Autonomous Reliability & Zero Downtime Watchdog',
    h1: 'Guardian Autonomous SRE Defense.<br><span class="gradient-text">24/7 Self-Healing Cloud & Server Protection.</span>',
    heroSub: 'Eliminate 3:00 AM server crash alerts. Guardian SRE acts as an autonomous digital Site Reliability Engineer that detects memory leaks, zombie containers, and unhandled crashes, auto-healing your servers in seconds.',
    ctaText: 'Activate Guardian SRE',
    schemaType: 'SiteReliabilityEngineeringService',
    priceStarting: '0',
    plans: [
      {
        id: 'guardian-audit',
        name: 'SRE Vulnerability Audit',
        desc: 'Comprehensive vulnerability, bottleneck, and latency audit for your existing servers.',
        price: 'FREE',
        period: '/one-time',
        featured: false,
        btnText: 'Request Free Audit',
        specs: [
          'Port & Firewall Vulnerability Scan',
          'Memory Leak & Zombie Process Detection',
          'SSL & DNS Configuration Verification',
          'Web Server (Nginx/Apache) Latency Profile',
          'Actionable Remediation Architecture Report',
          '30-Minute SRE Engineering Consultation'
        ]
      },
      {
        id: 'guardian-pro',
        name: 'Guardian Pro Watchdog',
        desc: 'For production web applications, SaaS platforms, and enterprise ecommerce stores.',
        price: '₹1,999',
        period: '/month/server',
        featured: true,
        ribbon: 'Enterprise Standard',
        btnText: 'Activate Guardian Pro',
        specs: [
          '1-Second Heartbeat Telemetry Polling',
          'Autonomous Process & Docker Self-Healing',
          'Out-of-Memory (OOM) Predictive Kill Prevention',
          'Multi-Channel Alerts (WhatsApp, Slack, Telegram, Email)',
          'Automated SSL Expiry Warning & Auto-Renew',
          'BYOVPS Support (AWS, Hetzner, DigitalOcean)',
          'Monthly Executive Health & Incident Reports'
        ]
      },
      {
        id: 'guardian-fleet',
        name: 'Guardian Fleet Enterprise',
        desc: 'For multi-cloud clusters, high-scale Kubernetes fleets, and enterprise DevOps teams.',
        price: 'Custom',
        period: '/fleet',
        featured: false,
        btnText: 'Schedule Architecture Call',
        specs: [
          'Unlimited Monitored Nodes & Microservices',
          'Custom eBPF Kernel Probe Telemetry',
          'Distributed DDoS Traffic Cleansing',
          'Dedicated 24/7 On-Call SRE Escalation Team',
          '99.999% Financial Backed Uptime SLA',
          'Tailored Disaster Recovery Failover Drills'
        ]
      }
    ],
    features: [
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
        title: 'Autonomous Self-Healing Loop',
        desc: 'When an Nginx worker, Docker container, or database hangs, Guardian doesn\'t just send an alert — it executes verified self-healing runbooks in seconds.'
      },
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
        title: '1-Second Heartbeat Resolution',
        desc: 'While legacy monitoring tools ping your server every 1 to 5 minutes, Guardian checks telemetry every second, stopping catastrophic downtime before it cascades.'
      },
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><line x1="3" x2="21" y1="9" y2="9"/><line x1="9" x2="9" y1="21" y2="9"/></svg>',
        title: 'BYOVPS 60-Second Agent Install',
        desc: 'Running servers on AWS EC2, DigitalOcean, Hetzner, or on-premise? One curl command installs the ultra-lightweight Guardian agent with zero CPU overhead.'
      },
      {
        icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
        title: 'WhatsApp & Slack Incident Dispatch',
        desc: 'Instant notifications sent directly to your engineering team\'s WhatsApp and Slack channels with detailed root-cause diagnostics and remediation logs.'
      }
    ],
    comparison: [
      { feature: 'Heartbeat Check Interval', nv: '1 Second Real-Time', trad: '1 - 5 Minutes (Datadog)', host: '5 - 15 Minutes' },
      { feature: 'Autonomous Remediation', nv: 'Automatic Self-Healing', trad: 'Alerts Only (No Action)', host: 'Manual Reboot Only' },
      { feature: 'WhatsApp Alert Routing', nv: 'Included Standard', trad: 'Requires PagerDuty Integration', host: 'Email Only' },
      { feature: 'Agent CPU & RAM Overhead', nv: '< 15MB RAM / 0.1% CPU', trad: '150 - 300MB RAM', host: 'Varies' },
      { feature: 'Pricing', nv: 'Transparent ₹1,999/mo', trad: '$15 - $23/host + metrics surge', host: 'Fixed Basic' }
    ],
    faqs: [
      {
        q: 'Can Guardian SRE monitor servers hosted outside NeuraVolt?',
        a: 'Yes! Our BYOVPS (Bring Your Own VPS) architecture allows you to protect servers running on AWS, DigitalOcean, Google Cloud, Linode, Hetzner, or local physical servers.'
      },
      {
        q: 'How does autonomous self-healing work safely?',
        a: 'Guardian executes deterministic, isolated remediation recipes (e.g. graceful service reload, zombie process reclamation, temp disk cache cleanup) validated against strict safety bounds.'
      },
      {
        q: 'Will installing the Guardian agent slow down my server?',
        a: 'Not at all. The Guardian agent is written in high-performance Go/C using lightweight eBPF probes. It consumes less than 15MB of RAM and under 0.1% CPU.'
      },
      {
        q: 'How does WhatsApp incident alerting work?',
        a: 'We integrate directly with WhatsApp Business API. In the event of a critical server state change or unhandled error, an instant alert is delivered to your designated emergency numbers.'
      }
    ]
  }
];

// Helper to generate full page HTML
function buildProductHtml(prod, depth = 0) {
  const prefix = depth === 0 ? '' : '../';

  // Schema.org JSON-LD
  const schemaJson = {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": prod.schemaType,
        "name": prod.badge,
        "description": prod.metaDesc,
        "brand": {
          "@type": "Brand",
          "name": "NeuraVolt Cloud Services"
        },
        "offers": {
          "@type": "AggregateOffer",
          "priceCurrency": "INR",
          "lowPrice": prod.priceStarting,
          "offerCount": prod.plans.length.toString()
        }
      },
      {
        "@type": "BreadcrumbList",
        "itemListElement": [
          {
            "@type": "ListItem",
            "position": 1,
            "name": "Home",
            "item": "https://neuravolt.cloud/"
          },
          {
            "@type": "ListItem",
            "position": 2,
            "name": prod.badge,
            "item": prod.canonical
          }
        ]
      },
      {
        "@type": "FAQPage",
        "mainEntity": prod.faqs.map(f => ({
          "@type": "Question",
          "name": f.q,
          "acceptedAnswer": {
            "@type": "Answer",
            "text": f.a
          }
        }))
      }
    ]
  };

  const pricingCardsHtml = prod.plans.map(plan => `
        <div class="price-card ${plan.featured ? 'featured' : ''}">
          ${plan.ribbon ? `<div class="featured-ribbon">${plan.ribbon}</div>` : ''}
          <div class="price-header">
            <h3>${plan.name}</h3>
            <p class="plan-desc">${plan.desc}</p>
          </div>
          <div class="price-value">${plan.price}<span>${plan.period}</span></div>
          <ul class="price-specs">
            ${plan.specs.map(s => `<li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg><span>${s}</span></li>`).join('\n            ')}
          </ul>
          <button onclick="openLeadModal('${prod.slug === 'cloud-hosting' ? 'hosting' : prod.slug === 'cloud-vps' ? 'vps' : prod.slug === 'managed-n8n' ? 'nodes' : 'guardian'}', '${plan.id}')" class="btn ${plan.featured ? 'btn-primary' : 'btn-secondary'}">${plan.btnText}</button>
        </div>
  `).join('');

  const featuresHtml = prod.features.map(f => `
        <div class="showcase-card">
          <div class="showcase-icon">${f.icon}</div>
          <h3>${f.title}</h3>
          <p>${f.desc}</p>
        </div>
  `).join('');

  const comparisonRows = prod.comparison.map(c => `
          <tr>
            <td><strong>${c.feature}</strong></td>
            <td style="color: var(--primary); font-weight: 700;">${c.nv}</td>
            <td>${c.trad}</td>
            <td>${c.host}</td>
          </tr>
  `).join('');

  const faqsHtml = prod.faqs.map((f, idx) => `
      <div class="faq-item ${idx === 0 ? 'active' : ''}">
        <button class="faq-question" onclick="toggleFaq(this)">
          <span>${f.q}</span>
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <div class="faq-answer">
          <p>${f.a}</p>
        </div>
      </div>
  `).join('');

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${prod.title}</title>
  <meta name="description" content="${prod.metaDesc}">
  <link rel="canonical" href="${prod.canonical}">
  
  <!-- Open Graph -->
  <meta property="og:title" content="${prod.title}">
  <meta property="og:description" content="${prod.metaDesc}">
  <meta property="og:url" content="${prod.canonical}">
  <meta property="og:type" content="product">
  <meta property="og:site_name" content="NeuraVolt Cloud Services">
  <meta property="og:image" content="https://neuravolt.cloud/assets/logos/neuravolt_cloud_logo.png">

  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="${prod.title}">
  <meta name="twitter:description" content="${prod.metaDesc}">
  <meta name="twitter:image" content="https://neuravolt.cloud/assets/logos/neuravolt_cloud_logo.png">

  <!-- Favicons & Styles -->
  <link rel="stylesheet" href="${prefix}style.css">
  <link rel="icon" type="image/png" href="${prefix}assets/favicon.png?v=3">
  <link rel="shortcut icon" href="${prefix}assets/favicon.ico?v=3">
  <link rel="apple-touch-icon" href="${prefix}assets/favicon.png?v=3">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Structured Data JSON-LD -->
  <script type="application/ld+json">
  ${JSON.stringify(schemaJson, null, 2)}
  </script>
</head>
<body>

  ${getHeader(prod.slug, depth)}

  <!-- Hero Section -->
  <section class="hero container">
    <div class="breadcrumb">
      <a href="${prefix}index.html">Home</a>
      <span>/</span>
      <a href="${prefix}index.html#pricing">Infrastructure Products</a>
      <span>/</span>
      <span class="current">${prod.badge}</span>
    </div>

    <div class="hero-badge">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      <span>${prod.badge}</span>
    </div>

    <h1>${prod.h1}</h1>
    <p class="hero-subtitle">${prod.heroSub}</p>

    <div class="hero-actions">
      <a href="#pricing" class="btn btn-primary">${prod.ctaText} →</a>
      <button onclick="openLeadModal('${prod.slug === 'cloud-hosting' ? 'hosting' : prod.slug === 'cloud-vps' ? 'vps' : prod.slug === 'managed-n8n' ? 'nodes' : 'guardian'}')" class="btn btn-secondary">Request Custom Quote</button>
    </div>
  </section>

  <!-- Pricing Cards Section -->
  <section id="pricing" class="pricing-section container">
    <div class="section-header">
      <span class="section-tag">${prod.badge} Plans & Pricing</span>
      <h2>Select Your Optimized Specification</h2>
      <p>Instant provisioning, transparent billing, and 100% NVMe performance standard on every tier.</p>
    </div>

    <div class="pricing-grid">
      ${pricingCardsHtml}
    </div>
  </section>

  <!-- Technical Features Showcase -->
  <section class="container" style="margin-top: 5rem; margin-bottom: 5rem;">
    <div class="section-header">
      <span class="section-tag">Engineering Advantages</span>
      <h2>Built for Extreme Concurrency & Throughput</h2>
      <p>Why leading engineering teams choose NeuraVolt Cloud over legacy providers.</p>
    </div>

    <div class="feature-showcase-grid">
      ${featuresHtml}
    </div>
  </section>

  <!-- Architectural Comparison Table -->
  <section class="container" style="margin-bottom: 5rem;">
    <div class="section-header">
      <span class="section-tag">Direct Benchmarks</span>
      <h2>NeuraVolt Cloud vs Industry Alternatives</h2>
      <p>Clear architectural differentiation with zero marketing fluff.</p>
    </div>

    <div class="specs-table-wrapper">
      <table class="specs-table">
        <thead>
          <tr>
            <th>Architecture Dimension</th>
            <th style="color: var(--primary);">NeuraVolt Cloud</th>
            <th>Traditional Shared / AWS</th>
            <th>Budget Providers</th>
          </tr>
        </thead>
        <tbody>
          ${comparisonRows}
        </tbody>
      </table>
    </div>
  </section>

  <!-- Frequently Asked Questions -->
  <section class="container" style="margin-bottom: 5rem;">
    <div class="section-header">
      <span class="section-tag">Knowledge Base & FAQ</span>
      <h2>Got Questions? We Have Answers.</h2>
      <p>Everything you need to know about provisioning, migration, SLAs, and technical support.</p>
    </div>

    <div class="faq-container">
      ${faqsHtml}
    </div>
  </section>

  <!-- High-Impact Conversion Banner -->
  <section class="container" style="margin-bottom: 5rem;">
    <div style="background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); border-radius: 20px; padding: 48px; color: white; text-align: center; box-shadow: var(--shadow-lg); border: 1px solid rgba(255,85,0,0.2);">
      <span style="background: rgba(255,85,0,0.2); color: #FF7722; padding: 6px 14px; border-radius: 9999px; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; display: inline-block; margin-bottom: 16px;">Ready to Scale?</span>
      <h2 style="font-size: 2.2rem; font-weight: 800; margin-bottom: 12px; color: white;">Experience Sub-Millisecond Cloud Infrastructure</h2>
      <p style="color: #94A3B8; max-width: 650px; margin: 0 auto 28px; font-size: 0.95rem;">Join forward-thinking agencies, developers, and enterprises deployed on NeuraVolt Cloud.</p>
      <div style="display: flex; gap: 14px; justify-content: center; flex-wrap: wrap;">
        <button onclick="openLeadModal('${prod.slug === 'cloud-hosting' ? 'hosting' : prod.slug === 'cloud-vps' ? 'vps' : prod.slug === 'managed-n8n' ? 'nodes' : 'guardian'}')" class="btn btn-primary" style="padding: 14px 28px; font-size: 0.95rem;">Deploy Instant Instance →</button>
        <a href="${prefix}index.html" class="btn btn-secondary" style="background: rgba(255,255,255,0.08); color: white; border-color: rgba(255,255,255,0.15); padding: 14px 24px;">Explore All Products</a>
      </div>
    </div>
  </section>

  ${getFooter(depth)}

  ${getModalAndScripts(prod.slug === 'cloud-hosting' ? 'hosting' : prod.slug === 'cloud-vps' ? 'vps' : prod.slug === 'managed-n8n' ? 'nodes' : 'guardian', prod.plans[1].id, depth)}
</body>
</html>`;
}

// Generate the 4 dedicated files and directories
PRODUCTS.forEach(prod => {
  // 1. Root .html file (e.g. cloud-hosting.html)
  const rootFilePath = path.join(LANDING_DIR, `${prod.slug}.html`);
  fs.writeFileSync(rootFilePath, buildProductHtml(prod, 0), 'utf8');
  console.log(`✓ Generated ${prod.slug}.html`);

  // 2. Directory with index.html (e.g. cloud-hosting/index.html)
  const dirPath = path.join(LANDING_DIR, prod.slug);
  if (!fs.existsSync(dirPath)) {
    fs.mkdirSync(dirPath, { recursive: true });
  }
  const dirIndexFilePath = path.join(dirPath, 'index.html');
  fs.writeFileSync(dirIndexFilePath, buildProductHtml(prod, 1), 'utf8');
  console.log(`✓ Generated ${prod.slug}/index.html`);
});

// Generate Sitemap.xml
const sitemapXml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://neuravolt.cloud/</loc>
    <lastmod>${new Date().toISOString().split('T')[0]}</lastmod>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://neuravolt.cloud/cloud-hosting</loc>
    <lastmod>${new Date().toISOString().split('T')[0]}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>https://neuravolt.cloud/cloud-vps</loc>
    <lastmod>${new Date().toISOString().split('T')[0]}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>https://neuravolt.cloud/managed-n8n</loc>
    <lastmod>${new Date().toISOString().split('T')[0]}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc>https://neuravolt.cloud/guardian-sre</loc>
    <lastmod>${new Date().toISOString().split('T')[0]}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
</urlset>
`;

fs.writeFileSync(path.join(LANDING_DIR, 'sitemap.xml'), sitemapXml, 'utf8');
console.log('✓ Generated sitemap.xml');

// Generate robots.txt
const robotsTxt = `User-agent: *
Allow: /

Sitemap: https://neuravolt.cloud/sitemap.xml
`;

fs.writeFileSync(path.join(LANDING_DIR, 'robots.txt'), robotsTxt, 'utf8');
console.log('✓ Generated robots.txt');

// -------------------------------------------------------------
// UPDATE index.html Navigation & Tab Panels with direct links
// -------------------------------------------------------------
const indexPath = path.join(LANDING_DIR, 'index.html');
let indexHtml = fs.readFileSync(indexPath, 'utf8');

// 1. Update navigation links in header
indexHtml = indexHtml.replace(
  /<ul class="nav-links">[\s\S]*?<\/ul>/,
  `<ul class="nav-links">
        <li><a href="cloud-hosting.html">Cloud Hosting</a></li>
        <li><a href="cloud-vps.html">VPS & Compute</a></li>
        <li><a href="managed-n8n.html">Managed n8n</a></li>
        <li><a href="guardian-sre.html">Guardian SRE</a></li>
        <li><a href="#pricing">All Plans</a></li>
      </ul>`
);

// 2. Add direct link buttons in each tab panel if not already present
if (!indexHtml.includes('product-full-link')) {
  // Tab hosting
  indexHtml = indexHtml.replace(
    /<\/div>\s*<\/div>\s*<!-- TAB 2: CLOUD VPS & COMPUTE -->/,
    `  <div class="product-full-link">
        <a href="cloud-hosting.html">View Dedicated Cloud Web Hosting Specifications & Benchmarks →</a>
      </div>
    </div>
  </div>

  <!-- TAB 2: CLOUD VPS & COMPUTE -->`
  );

  // Tab vps
  indexHtml = indexHtml.replace(
    /<\/div>\s*<\/div>\s*<!-- TAB 3: MANAGED N8N NODES -->/,
    `  <div class="product-full-link">
        <a href="cloud-vps.html">View Dedicated Cloud VPS & Bare Metal Hardware Specifications →</a>
      </div>
    </div>
  </div>

  <!-- TAB 3: MANAGED N8N NODES -->`
  );

  // Tab nodes
  indexHtml = indexHtml.replace(
    /<\/div>\s*<\/div>\s*<!-- TAB 4: GUARDIAN SRE DEFENSE -->/,
    `  <div class="product-full-link">
        <a href="managed-n8n.html">View Dedicated Managed n8n Node Specifications & Integrations →</a>
      </div>
    </div>
  </div>

  <!-- TAB 4: GUARDIAN SRE DEFENSE -->`
  );

  // Tab guardian
  indexHtml = indexHtml.replace(
    /<\/div>\s*<\/div>\s*<!-- Comparison Table/,
    `  <div class="product-full-link">
        <a href="guardian-sre.html">View Dedicated Guardian SRE Features & Agent Telemetry Specs →</a>
      </div>
    </div>
  </div>

  <!-- Comparison Table`
  );
}

// 3. Update footer infrastructure links
indexHtml = indexHtml.replace(
  /<div class="footer-col">\s*<h4>Infrastructure<\/h4>[\s\S]*?<\/div>/,
  `<div class="footer-col">
          <h4>Infrastructure Products</h4>
          <ul>
            <li><a href="cloud-hosting.html">High-Speed Cloud Web Hosting</a></li>
            <li><a href="cloud-vps.html">Cloud VPS & Dedicated Compute</a></li>
            <li><a href="managed-n8n.html">Managed n8n Automation Nodes</a></li>
            <li><a href="guardian-sre.html">Guardian SRE Self-Healing Watchdog</a></li>
            <li><a href="#pricing">All Infrastructure Pricing</a></li>
          </ul>
        </div>`
);

fs.writeFileSync(indexPath, indexHtml, 'utf8');
console.log('✓ Updated index.html with direct product links');

console.log('All dedicated SEO product pages and sitemaps generated successfully!');

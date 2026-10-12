<style>
/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.page-header-right {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

/* Reusable Stat Card Styles */
.lead-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.lead-stat-card {
    background: var(--card-bg, #fff);
    border-radius: 12px;
    padding: 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border: 1px solid var(--border-color, #e9ecef);
    transition: all 0.2s ease;
    position: relative;
    overflow: hidden;
}

.lead-stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 3px;
    opacity: 0.7;
    height: 100%;
    border-radius: 4px 0 0 4px;
}

.lead-stat-card.stat-primary::before { background: var(--color-primary); }
.lead-stat-card.stat-success::before { background: var(--success-color, #10b981); }
.lead-stat-card.stat-info::before { background: var(--info-color, #0ea5e9); }
.lead-stat-card.stat-warning::before { background: var(--warning-color, #f59e0b); }
.lead-stat-card.stat-danger::before { background: var(--danger-color, #ef4444); }

.lead-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.lead-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.lead-stat-icon.icon-primary { background: var(--color-primary-light); color: var(--color-primary); }
.lead-stat-icon.icon-success { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }
.lead-stat-icon.icon-info { background: rgba(14, 165, 233, 0.1); color: var(--info-color, #0ea5e9); }
.lead-stat-icon.icon-warning { background: rgba(245, 158, 11, 0.1); color: var(--warning-color, #f59e0b); }
.lead-stat-icon.icon-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger-color, #ef4444); }

.lead-stat-content h3 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    line-height: 1;
    color: var(--text-color, #1f2937);
}

.lead-stat-content p {
    font-size: 0.813rem;
    color: var(--text-muted, #6b7280);
    margin: 0;
    font-weight: 500;
}

/* Fix Dropdown Overflow in Tables */
.card,
.card-body,
.table-container {
    overflow: visible !important;
}
.table-container .dropdown-menu {
    z-index: 1050;
    min-width: 180px;
}
</style>

@extends('admin.layouts.app')
@push("style-include")
<style>
    /* Page Header Fix */
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

    /* Visual Builder Styles */
    .builder-container {
        display: flex;
        height: calc(100vh - 120px);
        background: var(--body-bg, #f8fafc);
        border-radius: 12px;
        overflow: hidden;
    }

    /* Sidebar */
    .builder-sidebar {
        width: 320px;
        background: var(--card-bg, #fff);
        border-right: 1px solid var(--border-color, #e9ecef);
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
    }

    .sidebar-header {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color, #e9ecef);
    }

    .sidebar-content {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }

    .node-category {
        margin-bottom: 1.5rem;
    }

    .node-category-title {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--text-muted, #6b7280);
        margin-bottom: 0.75rem;
        letter-spacing: 0.05em;
    }

    .node-palette {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .node-template {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem;
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-color, #e9ecef);
        border-radius: 8px;
        cursor: grab;
        transition: all 0.2s ease;
    }

    .node-template:hover {
        border-color: var(--color-primary);
        box-shadow: 0 2px 8px var(--color-primary-light);
    }

    .node-template:active {
        cursor: grabbing;
    }

    .node-template-icon {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .node-template-icon.trigger { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }
    .node-template-icon.action { background: rgba(59, 130, 246, 0.1); color: var(--info-color, #3b82f6); }
    .node-template-icon.condition { background: rgba(245, 158, 11, 0.1); color: var(--warning-color, #f59e0b); }
    .node-template-icon.wait { background: rgba(107, 114, 128, 0.1); color: var(--secondary-color, #6b7280); }

    .node-template-info h5 {
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 0;
        color: var(--text-color, #1f2937);
    }

    .node-template-info p {
        font-size: 0.75rem;
        color: var(--text-muted, #6b7280);
        margin-bottom: 0;
    }

    /* Canvas */
    .builder-canvas {
        flex: 1;
        position: relative;
        overflow: auto;
        background-color: var(--body-bg, #f8fafc);
        background-image:
            linear-gradient(90deg, var(--border-color, #e9ecef) 1px, transparent 1px),
            linear-gradient(180deg, var(--border-color, #e9ecef) 1px, transparent 1px);
        background-size: 20px 20px;
    }

    .canvas-area {
        min-width: 2000px;
        min-height: 1500px;
        position: relative;
        transition: outline-color 0.2s ease, background-color 0.2s ease;
    }

    .canvas-area.drag-over {
        background-color: rgba(80, 70, 229, 0.02);
        outline: 2px dashed var(--color-primary);
        outline-offset: -4px;
        border-radius: 8px;
    }

    /* Workflow Nodes */
    .workflow-node {
        position: absolute;
        width: 280px;
        background: var(--card-bg, #fff);
        border: 2px solid var(--border-color, #e9ecef);
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        cursor: move;
        user-select: none;
        z-index: 5;
    }

    .workflow-node.is-dragging {
        opacity: 0.92;
        z-index: 100;
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.15);
    }

    .workflow-node.selected {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px var(--color-primary-light);
    }

    .workflow-node.trigger { border-top: 3px solid var(--success-color, #10b981); }
    .workflow-node.action { border-top: 3px solid var(--info-color, #3b82f6); }
    .workflow-node.condition { border-top: 3px solid var(--warning-color, #f59e0b); }
    .workflow-node.wait { border-top: 3px solid var(--secondary-color, #6b7280); }

    .node-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border-color, #e9ecef);
    }

    .node-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .node-header .node-icon.trigger { background: rgba(16, 185, 129, 0.1); color: var(--success-color, #10b981); }
    .node-header .node-icon.action { background: rgba(59, 130, 246, 0.1); color: var(--info-color, #3b82f6); }
    .node-header .node-icon.condition { background: rgba(245, 158, 11, 0.1); color: var(--warning-color, #f59e0b); }
    .node-header .node-icon.wait { background: rgba(107, 114, 128, 0.1); color: var(--secondary-color, #6b7280); }

    .node-title {
        flex: 1;
        font-weight: 600;
        font-size: 0.875rem;
        color: var(--text-color, #1f2937);
    }

    .node-actions {
        display: flex;
        gap: 0.25rem;
    }

    .node-actions button {
        width: 24px;
        height: 24px;
        border: none;
        background: transparent;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted, #6b7280);
        transition: all 0.2s;
    }

    .node-actions button:hover {
        background: var(--border-color, #e9ecef);
        color: var(--text-color, #1f2937);
    }

    .node-actions button.delete:hover {
        background: #fee2e2;
        color: #ef4444;
    }

    .node-body {
        padding: 1rem;
        font-size: 0.813rem;
        color: var(--text-muted, #6b7280);
    }

    .node-connector {
        position: absolute;
        width: 18px;
        height: 18px;
        background: #fff;
        border: 3px solid var(--border-color, #e9ecef);
        border-radius: 50%;
        cursor: crosshair;
        z-index: 10;
        transition: all 0.2s ease;
    }

    /* Larger hit area for connectors */
    .node-connector::before {
        content: '';
        position: absolute;
        top: -12px;
        left: -12px;
        right: -12px;
        bottom: -12px;
        border-radius: 50%;
    }

    .node-connector.output {
        bottom: -9px;
        left: 50%;
        transform: translateX(-50%);
    }

    .node-connector.output-true {
        bottom: -9px;
        left: 30%;
        border-color: #10b981;
        background: rgba(16, 185, 129, 0.1);
        transform: none;
    }

    .node-connector.output-false {
        bottom: -9px;
        right: 30%;
        left: auto;
        border-color: #ef4444;
        background: rgba(239, 68, 68, 0.1);
        transform: none;
    }

    .node-connector.input {
        top: -9px;
        left: 50%;
        transform: translateX(-50%);
    }

    .node-connector:hover {
        border-color: var(--color-primary);
        background: rgba(80, 70, 229, 0.2);
        transform: translateX(-50%) scale(1.3);
    }

    .node-connector.output-true:hover {
        transform: scale(1.3);
    }

    .node-connector.output-false:hover {
        transform: scale(1.3);
    }

    .node-connector.can-connect {
        border-color: #10b981 !important;
        background: rgba(16, 185, 129, 0.3) !important;
        animation: pulse 0.5s infinite alternate;
        transform: translateX(-50%) scale(1.4) !important;
    }

    .node-connector.input.can-connect::after {
        content: '';
        position: absolute;
        top: -6px;
        left: -6px;
        right: -6px;
        bottom: -6px;
        border: 2px dashed #10b981;
        border-radius: 50%;
        animation: pulse-ring 0.5s infinite alternate;
    }

    @keyframes pulse {
        from { transform: translateX(-50%) scale(1.2); }
        to { transform: translateX(-50%) scale(1.5); }
    }

    @keyframes pulse-ring {
        from { opacity: 0.5; }
        to { opacity: 1; }
    }

    .connecting-line {
        fill: none;
        stroke: var(--color-primary);
        stroke-width: 3;
        stroke-dasharray: 8 4;
        pointer-events: none;
    }

    /* Connections SVG */
    .connections-svg {
        position: absolute;
        top: 0;
        left: 0;
        min-width: 100%;
        min-height: 100%;
        pointer-events: none;
        overflow: visible;
        z-index: 1;
    }

    .connections-svg path {
        pointer-events: stroke;
        cursor: pointer;
    }

    .connection-line {
        fill: none;
        stroke: #94a3b8;
        stroke-width: 3;
        cursor: pointer;
        transition: stroke 0.2s, stroke-width 0.2s;
        filter: drop-shadow(0 1px 2px rgba(0,0,0,0.1));
    }

    .connection-line:hover {
        stroke: var(--color-primary);
        stroke-width: 4;
        filter: drop-shadow(0 2px 4px rgba(80, 70, 229, 0.3));
    }

    .connection-line.true {
        stroke: var(--success-color, #10b981);
    }
    .connection-line.true:hover {
        stroke: var(--success-hover, #059669);
        filter: drop-shadow(0 2px 4px rgba(16, 185, 129, 0.3));
    }
    .connection-line.false {
        stroke: var(--danger-color, #ef4444);
    }
    .connection-line.false:hover {
        stroke: var(--danger-hover, #dc2626);
        filter: drop-shadow(0 2px 4px rgba(239, 68, 68, 0.3));
    }

    /* Canvas area should be above SVG for node interactions */
    .canvas-area {
        position: relative;
        z-index: 2;
    }

    /* Properties Panel */
    .builder-properties {
        width: 320px;
        background: var(--card-bg, #fff);
        border-left: 1px solid var(--border-color, #e9ecef);
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
    }

    .properties-header {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color, #e9ecef);
    }

    .properties-content {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
    }

    .property-group {
        margin-bottom: 1.25rem;
    }

    .property-label {
        font-size: 0.813rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-color, #1f2937);
    }

    /* Toolbar */
    .builder-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.75rem 1rem;
        background: var(--card-bg, #fff);
        border-bottom: 1px solid var(--border-color, #e9ecef);
        margin-bottom: 1rem;
        border-radius: 8px;
    }

    .toolbar-left {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .toolbar-right {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .zoom-controls {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem;
        background: var(--border-color, #e9ecef);
        border-radius: 6px;
    }

    .zoom-controls button {
        width: 28px;
        height: 28px;
        border: none;
        background: transparent;
        border-radius: 4px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .zoom-controls button:hover {
        background: #fff;
    }

    .zoom-level {
        min-width: 50px;
        text-align: center;
        font-size: 0.813rem;
        font-weight: 500;
    }

    /* Template Modal */
    .template-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1050;
        align-items: center;
        justify-content: center;
    }

    .template-modal.show {
        display: flex;
    }

    .template-modal-content {
        background: var(--card-bg, #fff);
        border-radius: 16px;
        width: 90%;
        max-width: 900px;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .template-modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color, #e9ecef);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .template-modal-header h4 {
        margin: 0;
        font-weight: 600;
    }

    .template-modal-body {
        padding: 1.5rem;
        overflow-y: auto;
        flex: 1;
    }

    .template-categories {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .template-category-btn {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        border: 1px solid var(--border-color, #e9ecef);
        background: var(--card-bg, #fff);
        cursor: pointer;
        font-weight: 500;
        font-size: 0.875rem;
        transition: all 0.2s ease;
    }

    .template-category-btn:hover,
    .template-category-btn.active {
        background: var(--color-primary);
        color: #fff;
        border-color: var(--color-primary);
    }

    .template-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
    }

    .template-card {
        background: var(--card-bg, #fff);
        border: 2px solid var(--border-color, #e9ecef);
        border-radius: 12px;
        padding: 1.25rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .template-card:hover {
        border-color: var(--color-primary);
        box-shadow: 0 4px 15px rgba(80, 70, 229, 0.1);
    }

    .template-card.selected {
        border-color: var(--color-primary);
        background: rgba(80, 70, 229, 0.03);
    }

    .template-card-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .template-card-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .template-card-title {
        font-weight: 600;
        font-size: 1rem;
        margin-bottom: 0.25rem;
    }

    .template-card-category {
        font-size: 0.75rem;
        color: var(--text-muted, #6b7280);
    }

    .template-card-description {
        font-size: 0.875rem;
        color: var(--text-muted, #6b7280);
        margin-bottom: 0.75rem;
    }

    .template-card-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.75rem;
        color: var(--text-muted, #6b7280);
    }

    .template-modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border-color, #e9ecef);
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
    }

    /* Confirmation Modal */
    .confirm-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1060;
        align-items: center;
        justify-content: center;
    }

    .confirm-modal-overlay.show {
        display: flex;
    }

    .confirm-modal-box {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        width: 90%;
        max-width: 360px;
        padding: 1.25rem;
        text-align: center;
        margin: 1rem;
    }

    .confirm-modal-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.75rem;
    }

    .confirm-modal-icon.warning {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }

    .confirm-modal-icon.danger {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .confirm-modal-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-color, #1f2937);
    }

    .confirm-modal-message {
        color: var(--text-muted, #6b7280);
        margin-bottom: 1.25rem;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .confirm-modal-actions {
        display: flex;
        gap: 0.5rem;
        justify-content: center;
        flex-wrap: wrap;
    }

    .confirm-modal-actions .i-btn {
        font-size: 0.813rem;
        padding: 0.5rem 1rem;
    }

    @media (max-width: 480px) {
        .confirm-modal-box {
            padding: 1rem;
            max-width: 300px;
        }

        .confirm-modal-icon {
            width: 40px;
            height: 40px;
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
        }

        .confirm-modal-title {
            font-size: 0.938rem;
        }

        .confirm-modal-message {
            font-size: 0.813rem;
        }

        .confirm-modal-actions {
            flex-direction: column;
        }

        .confirm-modal-actions .i-btn {
            width: 100%;
        }
    }

    /* Help Button */
    .help-btn {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--color-primary);
        color: #fff;
        border: none;
        font-size: 0.875rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .help-btn:hover {
        transform: scale(1.1);
        box-shadow: 0 2px 8px rgba(var(--color-primary-rgb), 0.4);
    }

    /* Help Modal */
    .help-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1060;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .help-modal-overlay.show {
        display: flex;
    }

    .help-modal-box {
        background: var(--card-bg, #fff);
        border-radius: 12px;
        width: 100%;
        max-width: 480px;
        max-height: 90vh;
        overflow-y: auto;
    }

    .help-modal-header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid var(--border-color, #e9ecef);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .help-modal-header h4 {
        margin: 0;
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-color, #1f2937);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .help-modal-header h4 i {
        color: var(--color-primary);
    }

    .help-modal-body {
        padding: 1.25rem;
    }

    .help-step {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }

    .help-step:last-child {
        margin-bottom: 0;
    }

    .help-step-number {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--color-primary);
        color: #fff;
        font-size: 0.813rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .help-step-content h5 {
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 0.25rem;
        color: var(--text-color, #1f2937);
    }

    .help-step-content p {
        font-size: 0.813rem;
        color: var(--text-muted, #6b7280);
        margin: 0;
        line-height: 1.5;
    }

    .help-flow-diagram {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 1rem;
        background: var(--body-bg, #f8fafc);
        border-radius: 8px;
        margin: 1rem 0;
    }

    .help-flow-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.375rem;
    }

    .help-flow-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    .help-flow-icon.trigger { background: rgba(16, 185, 129, 0.15); color: #059669; }
    .help-flow-icon.action { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
    .help-flow-icon.condition { background: rgba(245, 158, 11, 0.15); color: #d97706; }

    .help-flow-label {
        font-size: 0.688rem;
        font-weight: 500;
        color: var(--text-muted, #6b7280);
    }

    .help-flow-arrow {
        color: var(--text-muted, #6b7280);
        font-size: 1.25rem;
    }

    .help-tip {
        display: flex;
        gap: 0.75rem;
        padding: 0.875rem;
        background: rgba(245, 158, 11, 0.1);
        border-radius: 8px;
        margin-top: 1rem;
    }

    .help-tip i {
        color: #d97706;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .help-tip p {
        font-size: 0.813rem;
        color: #92400e;
        margin: 0;
        line-height: 1.5;
    }

    .help-modal-footer {
        padding: 1rem 1.25rem;
        border-top: 1px solid var(--border-color, #e9ecef);
        text-align: center;
    }

    @media (max-width: 480px) {
        .help-modal-box {
            max-width: 100%;
        }

        .help-flow-diagram {
            flex-wrap: wrap;
        }

        .help-flow-icon {
            width: 32px;
            height: 32px;
            font-size: 0.875rem;
        }
    }

    /* Node click visual feedback */
    .workflow-node .node-actions button.gear-btn {
        position: relative;
    }

    .workflow-node .node-actions button.gear-btn:hover {
        background: var(--color-primary-light);
        color: var(--color-primary);
    }

    /* Theme-aware button overrides */
    .builder-toolbar .btn-primary {
        background-color: var(--color-primary) !important;
        border-color: var(--color-primary) !important;
        color: #fff !important;
    }

    .builder-toolbar .btn-primary:hover {
        background-color: var(--color-primary-dark, var(--color-primary)) !important;
        border-color: var(--color-primary-dark, var(--color-primary)) !important;
    }

    .builder-toolbar .btn-outline-primary {
        color: var(--color-primary) !important;
        border-color: var(--color-primary) !important;
        background: transparent !important;
    }

    .builder-toolbar .btn-outline-primary:hover {
        background-color: var(--color-primary) !important;
        color: #fff !important;
    }

    .builder-toolbar .btn-outline-info {
        color: var(--color-primary) !important;
        border-color: var(--color-primary) !important;
        background: transparent !important;
    }

    .builder-toolbar .btn-outline-info:hover {
        background-color: var(--color-primary) !important;
        color: #fff !important;
    }

    /* Connection feedback - small toast style */
    .connection-feedback {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%) translateY(100px);
        padding: 8px 16px;
        background: var(--card-bg, #fff);
        border-radius: 6px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
        z-index: 1000;
        font-size: 0.813rem;
        opacity: 0;
        transition: all 0.3s ease;
        pointer-events: none;
    }

    .connection-feedback.show {
        display: flex;
        align-items: center;
        gap: 6px;
        transform: translateX(-50%) translateY(0);
        opacity: 1;
    }

    .connection-feedback.error {
        background: #fef2f2;
        color: #dc2626;
    }

    .connection-feedback.info {
        background: var(--color-primary-light, #f0f0ff);
        color: var(--color-primary);
    }

    .connection-feedback i {
        font-size: 1rem;
    }

    /* Trigger node hint badge */
    .workflow-node.trigger-node .node-hint {
        position: absolute;
        top: -24px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 0.625rem;
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
        padding: 2px 8px;
        border-radius: 4px;
        white-space: nowrap;
        opacity: 0;
        transition: opacity 0.2s;
    }

    .workflow-node.trigger-node:hover .node-hint,
    .workflow-node.trigger-node.cannot-receive-connection .node-hint {
        opacity: 1;
    }

    /* Sidebar Help Guide */
    .sidebar-help {
        padding: 1rem;
        border-top: 1px solid var(--border-color, #e9ecef);
    }

    .sidebar-help-title {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--text-primary, #6b7280);
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .flow-guide {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 0.25rem;
        padding: 0rem;
        background: var(--card-bg, #fff);
        border-radius: 8px;
        font-size: 0.75rem;
    }

    .flow-guide-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.25rem;
        border-radius: 8px;
        padding: 12px;
        border: 1px solid #eee;
    }

    .flow-guide-icon {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.875rem;
    }

    .flow-guide-icon.trigger { background: rgba(16, 185, 129, 0.1); color: #10b981; }
    .flow-guide-icon.action { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .flow-guide-icon.condition { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }

    .flow-guide-label {
        font-size: 0.625rem;
        color: var(--text-muted, #6b7280);
        text-align: center;
    }

    .flow-guide-arrow {
        color: var(--text-muted, #6b7280);
        font-size: 1rem;
    }

    /* Highlight valid drop targets */
    .workflow-node.can-receive-connection {
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.5);
    }

    .workflow-node.cannot-receive-connection {
        opacity: 0.5;
    }
</style>
@endpush

@section('panel')
<main class="main-body">
    <div class="container-fluid px-0 main-content">
        @if(request('preset') === 'auto_reply')
            <x-hint-banner key="automation.workflow.auto_reply_quickstart" />
        @endif
        <div class="page-header">
            <div class="page-header-left">
                <h2>{{ $title }}</h2>
                <div class="breadcrumb-wrapper">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            @foreach($breadcrumbs as $breadcrumb)
                                @if(isset($breadcrumb['url']))
                                    <li class="breadcrumb-item">
                                        <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['name'] }}</a>
                                    </li>
                                @else
                                    <li class="breadcrumb-item active" aria-current="page">{{ $breadcrumb['name'] }}</li>
                                @endif
                            @endforeach
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

    <!-- Toolbar -->
    <div class="builder-toolbar">
        <div class="toolbar-left">
            <input type="text"
                   id="workflowName"
                   class="form-control"
                   placeholder="{{ translate('Workflow Name') }}"
                   value="{{ $workflow->name ?? '' }}"
                   style="width: 250px;">

            <div class="zoom-controls">
                <button type="button" onclick="zoomOut()"><i class="ri-subtract-line"></i></button>
                <span class="zoom-level" id="zoomLevel">100%</span>
                <button type="button" onclick="zoomIn()"><i class="ri-add-line"></i></button>
            </div>
        </div>

        <div class="toolbar-right">
            <button type="button" class="help-btn" onclick="showHelpModal()" title="{{ translate('How to use workflow builder') }}">
                <i class="ri-question-line"></i>
            </button>
            <button type="button" class="btn btn-outline-info" onclick="showTemplateModal()">
                <i class="ri-file-copy-2-line me-1"></i>{{ translate('Use Template') }}
            </button>
            <a href="{{ route('admin.automation.index') }}" class="btn btn-outline-secondary">
                <i class="ri-arrow-left-line me-1"></i>{{ translate('Back') }}
            </a>
            @isset($workflow)
            <button type="button" class="btn btn-outline-success" onclick="openTestRunModal()">
                <i class="ri-flask-line me-1"></i>{{ translate('Test Run') }}
            </button>
            @endisset
            <button type="button" class="btn btn-outline-primary" onclick="saveWorkflow(true)">
                <i class="ri-save-line me-1"></i>{{ translate('Save Draft') }}
            </button>
            <button type="button" class="btn btn-primary" onclick="saveWorkflow(false)">
                <i class="ri-check-line me-1"></i>{{ translate('Save & Activate') }}
            </button>
        </div>
    </div>

    @isset($workflow)
    {{-- Test Run modal: simulates the workflow against a sample contact, no real sends --}}
    <div class="modal fade" id="testRunModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ri-flask-line me-2"></i>{{ translate('Test Run / Dry Run') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <i class="ri-information-line"></i>
                        {{ translate('Simulates the workflow against a sample contact. No real SMS, email, WhatsApp or webhook calls are made.') }}
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ translate('Pick a sample contact') }}</label>
                        <select id="testRunContactSelect" class="form-select">
                            <option value="">{{ translate('Loading recent contacts…') }}</option>
                        </select>
                        <small class="text-muted">{{ translate('Most recent 10 contacts are listed.') }}</small>
                    </div>

                    <div id="testRunResults" class="d-none">
                        <hr>
                        <h6 class="mb-3">{{ translate('Simulation Result') }}</h6>
                        <div id="testRunResultsBody"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ translate('Close') }}</button>
                    <button type="button" class="btn btn-success" id="runTestRunBtn" onclick="runTestRun()">
                        <i class="ri-play-line me-1"></i>{{ translate('Run Simulation') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endisset

    <!-- Builder -->
    <div class="builder-container">
        <!-- Sidebar - Node Palette -->
        <div class="builder-sidebar">
            <div class="sidebar-header">
                <h5 class="mb-1">{{ translate('Add Nodes') }}</h5>
                <p class="text-muted mb-0 small">{{ translate('Drag nodes to the canvas') }}</p>
            </div>
            <div class="sidebar-content">
                <!-- Triggers -->
                <div class="node-category">
                    <div class="node-category-title">{{ translate('Triggers') }}</div>
                    <div class="node-palette">
                        @foreach($triggerTypes as $key => $trigger)
                            <div class="node-template"
                                 draggable="true"
                                 data-type="trigger"
                                 data-action="{{ $key }}">
                                <div class="node-template-icon trigger">
                                    <i class="{{ $trigger['icon'] }}"></i>
                                </div>
                                <div class="node-template-info">
                                    <h5>{{ $trigger['label'] }}</h5>
                                    <p>{{ \Illuminate\Support\Str::limit($trigger['description'], 40) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Actions -->
                <div class="node-category">
                    <div class="node-category-title">{{ translate('Actions') }}</div>
                    <div class="node-palette">
                        @foreach($actionTypes as $key => $action)
                            <div class="node-template"
                                 draggable="true"
                                 data-type="action"
                                 data-action="{{ $key }}">
                                <div class="node-template-icon action">
                                    <i class="{{ $action['icon'] }}"></i>
                                </div>
                                <div class="node-template-info">
                                    <h5>{{ $action['label'] }}</h5>
                                    <p>{{ \Illuminate\Support\Str::limit($action['description'], 40) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Conditions -->
                <div class="node-category">
                    <div class="node-category-title">{{ translate('Conditions') }}</div>
                    <div class="node-palette">
                        @foreach($conditionTypes as $key => $condition)
                            <div class="node-template"
                                 draggable="true"
                                 data-type="condition"
                                 data-action="{{ $key }}">
                                <div class="node-template-icon condition">
                                    <i class="{{ $condition['icon'] }}"></i>
                                </div>
                                <div class="node-template-info">
                                    <h5>{{ $condition['label'] }}</h5>
                                    <p>{{ \Illuminate\Support\Str::limit($condition['description'], 40) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Wait -->
                <div class="node-category">
                    <div class="node-category-title">{{ translate('Wait / Delay') }}</div>
                    <div class="node-palette">
                        @foreach($waitTypes as $key => $wait)
                            <div class="node-template"
                                 draggable="true"
                                 data-type="wait"
                                 data-action="{{ $key }}">
                                <div class="node-template-icon wait">
                                    <i class="{{ $wait['icon'] }}"></i>
                                </div>
                                <div class="node-template-info">
                                    <h5>{{ $wait['label'] }}</h5>
                                    <p>{{ \Illuminate\Support\Str::limit($wait['description'], 40) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Help Guide -->
            <div class="sidebar-help">
                <div class="sidebar-help-title">
                    <i class="ri-lightbulb-line"></i>
                    {{ translate('How to Connect') }}
                </div>
                <div class="flow-guide">
                    <div class="flow-guide-item">
                        <div class="flow-guide-icon trigger">
                            <i class="ri-flashlight-line"></i>
                        </div>
                        <span class="flow-guide-label">{{ translate('Trigger') }}</span>
                    </div>
                    <i class="ri-arrow-right-line flow-guide-arrow"></i>
                    <div class="flow-guide-item">
                        <div class="flow-guide-icon action">
                            <i class="ri-send-plane-line"></i>
                        </div>
                        <span class="flow-guide-label">{{ translate('Action') }}</span>
                    </div>
                    <i class="ri-arrow-right-line flow-guide-arrow"></i>
                    <div class="flow-guide-item">
                        <div class="flow-guide-icon condition">
                            <i class="ri-git-branch-line"></i>
                        </div>
                        <span class="flow-guide-label">{{ translate('Condition') }}</span>
                    </div>
                </div>
                <p class="text-muted mt-2 mb-0" style="font-size: 0.75rem; text-align: center;">
                    {{ translate('Drag from bottom circle to top of next node') }}
                </p>
            </div>
        </div>

        <!-- Canvas -->
        <div class="builder-canvas" id="builderCanvas">
            <svg class="connections-svg" id="connectionsSvg"></svg>
            <div class="canvas-area" id="canvasArea"></div>
        </div>

        <!-- Properties Panel -->
        <div class="builder-properties" id="propertiesPanel" style="display: none;">
            <div class="properties-header">
                <h5 class="mb-0" id="propertyTitle">{{ translate('Node Properties') }}</h5>
            </div>
            <div class="properties-content" id="propertiesContent">
                <!-- Dynamic properties form -->
            </div>
        </div>
    </div>
    </div>

<!-- Connection Feedback -->
<div class="connection-feedback" id="connectionFeedback">
    <i class="ri-information-line"></i>
    <span id="connectionFeedbackText"></span>
</div>

<!-- Template Modal -->
<div class="template-modal" id="templateModal">
    <div class="template-modal-content">
        <div class="template-modal-header">
            <h4><i class="ri-file-copy-2-line me-2"></i>{{ translate('Select a Template') }}</h4>
            <button type="button" class="btn-close" onclick="hideTemplateModal()"></button>
        </div>
        <div class="template-modal-body">
            <div class="template-categories" id="templateCategories">
                <button class="template-category-btn active" data-category="all">{{ translate('All') }}</button>
            </div>
            <div class="template-grid" id="templateGrid">
                <!-- Templates will be loaded here -->
            </div>
        </div>
        <div class="template-modal-footer">
            <button type="button" class="btn btn-outline-secondary" onclick="hideTemplateModal()">
                {{ translate('Cancel') }}
            </button>
            <button type="button" class="btn btn-primary" id="useTemplateBtn" onclick="useSelectedTemplate()" disabled>
                <i class="ri-check-line me-1"></i>{{ translate('Use Template') }}
            </button>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="confirm-modal-overlay" id="confirmModal">
    <div class="confirm-modal-box">
        <div class="confirm-modal-icon warning" id="confirmModalIcon">
            <i class="ri-question-line"></i>
        </div>
        <h4 class="confirm-modal-title" id="confirmModalTitle">{{ translate('Confirm Action') }}</h4>
        <p class="confirm-modal-message" id="confirmModalMessage">{{ translate('Are you sure you want to proceed?') }}</p>
        <div class="confirm-modal-actions">
            <button type="button" class="btn btn-outline-secondary" onclick="hideConfirmModal()">
                {{ translate('Cancel') }}
            </button>
            <button type="button" class="btn btn-primary" id="confirmModalBtn" onclick="confirmModalAction()">
                {{ translate('Confirm') }}
            </button>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div class="help-modal-overlay" id="helpModal">
    <div class="help-modal-box">
        <div class="help-modal-header">
            <h4><i class="ri-lightbulb-line"></i> {{ translate('How to Build Workflows') }}</h4>
            <button type="button" class="btn-close" onclick="hideHelpModal()"></button>
        </div>
        <div class="help-modal-body">
            <!-- Flow Diagram -->
            <div class="help-flow-diagram">
                <div class="help-flow-item">
                    <div class="help-flow-icon trigger">
                        <i class="ri-flashlight-line"></i>
                    </div>
                    <span class="help-flow-label">{{ translate('Trigger') }}</span>
                </div>
                <i class="ri-arrow-right-line help-flow-arrow"></i>
                <div class="help-flow-item">
                    <div class="help-flow-icon action">
                        <i class="ri-send-plane-line"></i>
                    </div>
                    <span class="help-flow-label">{{ translate('Action') }}</span>
                </div>
                <i class="ri-arrow-right-line help-flow-arrow"></i>
                <div class="help-flow-item">
                    <div class="help-flow-icon condition">
                        <i class="ri-git-branch-line"></i>
                    </div>
                    <span class="help-flow-label">{{ translate('Condition') }}</span>
                </div>
            </div>

            <!-- Steps -->
            <div class="help-step">
                <div class="help-step-number">1</div>
                <div class="help-step-content">
                    <h5>{{ translate('Start with a Trigger') }}</h5>
                    <p>{{ translate('Drag a Trigger node to the canvas. This is your starting point that activates the workflow.') }}</p>
                </div>
            </div>

            <div class="help-step">
                <div class="help-step-number">2</div>
                <div class="help-step-content">
                    <h5>{{ translate('Add Actions') }}</h5>
                    <p>{{ translate('Drag Action nodes (Send SMS, Email, WhatsApp) and connect them to your trigger by dragging from the connector dot.') }}</p>
                </div>
            </div>

            <div class="help-step">
                <div class="help-step-number">3</div>
                <div class="help-step-content">
                    <h5>{{ translate('Use Conditions (Optional)') }}</h5>
                    <p>{{ translate('Add Condition nodes to create branching logic based on contact data or engagement.') }}</p>
                </div>
            </div>

            <div class="help-step">
                <div class="help-step-number">4</div>
                <div class="help-step-content">
                    <h5>{{ translate('Connect Nodes') }}</h5>
                    <p>{{ translate('Click the connector dot on a node and drag to another node to create connections.') }}</p>
                </div>
            </div>

            <!-- Tip -->
            <div class="help-tip">
                <i class="ri-information-line"></i>
                <p>{{ translate('Triggers are starting points and can only connect TO other nodes. They cannot receive connections from other nodes.') }}</p>
            </div>
        </div>
        <div class="help-modal-footer">
            <button type="button" class="i-btn btn--primary btn--sm" onclick="hideHelpModal()">
                {{ translate('Got it!') }}
            </button>
        </div>
    </div>
</div>
</main>
@endsection

@push('script-push')
<script>
    // Workflow Builder State
    const workflowState = {
        nodes: [],
        connections: [],
        selectedNode: null,
        zoom: 1,
        isEditing: {{ isset($workflow) ? 'true' : 'false' }},
        workflowId: '{{ $workflow->uid ?? '' }}',
        nodeIdCounter: 0
    };

    // Available resources
    const resources = {
        groups: @json($groups),
        smsGateways: @json($smsGateways),
        emailGateways: @json($emailGateways),
        whatsappDevices: @json($whatsappDevices),
        triggerTypes: @json($triggerTypes),
        actionTypes: @json($actionTypes),
        conditionTypes: @json($conditionTypes),
        waitTypes: @json($waitTypes),
        operators: @json($operators)
    };

    // ===== Schema-driven properties form (Phase C) =================================
    // Schemas drive a single field-renderer instead of one hand-coded form per node type.
    // Legacy generators (generateActionForm/generateConditionForm/...) still exist as a
    // fallback for node types not yet migrated; remove them once every type is in the schema.
    const nodeSchemas   = @json($nodeSchemas ?? []);
    const schemaOptions = @json($schemaOptions ?? []);

    function hasSchemaFor(node) {
        if (!node || !node.type || !node.action_type) return false;
        return !!nodeSchemas[`${node.type}.${node.action_type}`];
    }

    function getSchemaFor(node) {
        return nodeSchemas[`${node.type}.${node.action_type}`] || [];
    }

    function resolveOptions(field) {
        if (Array.isArray(field.options)) return field.options;
        if (field.options_key && Array.isArray(schemaOptions[field.options_key])) {
            return schemaOptions[field.options_key];
        }
        return [];
    }

    function fieldCurrentValue(node, field) {
        const cfg = node.config || {};
        const raw = cfg[field.key];
        if (raw === undefined || raw === null || raw === '') {
            return field.default !== undefined ? field.default : '';
        }
        return raw;
    }

    function escapeAttr(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function renderSchemaField(node, field) {
        const required = field.required ? ' required-field' : '';
        const star     = field.required ? '<span class="text-danger">*</span>' : '';
        const help     = field.help ? `<small class="text-muted d-block mt-1">${escapeAttr(field.help)}</small>` : '';
        const value    = fieldCurrentValue(node, field);
        const ph       = field.placeholder ? `placeholder="${escapeAttr(field.placeholder)}"` : '';
        const id       = `field-${node.id}-${field.key}`;

        let inputHtml = '';
        switch (field.type) {
            case 'textarea': {
                const rows = field.rows || 4;
                inputHtml = `<textarea id="${id}" class="form-control" rows="${rows}" ${ph}
                    data-node-id="${node.id}" data-field-key="${field.key}"
                    onchange="updateNodeConfig(${node.id}, '${field.key}', this.value)">${escapeAttr(value)}</textarea>`;
                break;
            }
            case 'select': {
                const opts = resolveOptions(field);
                const optionsHtml = opts.map(o =>
                    `<option value="${escapeAttr(o.value)}" ${String(value) === String(o.value) ? 'selected' : ''}>${escapeAttr(o.label)}</option>`
                ).join('');
                inputHtml = `<select id="${id}" class="form-select"
                    data-node-id="${node.id}" data-field-key="${field.key}"
                    onchange="updateNodeConfig(${node.id}, '${field.key}', this.value)">
                    ${field.required ? '' : '<option value="">— Select —</option>'}
                    ${optionsHtml}
                </select>`;
                break;
            }
            case 'number': {
                const min = field.min !== undefined ? `min="${field.min}"` : '';
                const max = field.max !== undefined ? `max="${field.max}"` : '';
                inputHtml = `<input id="${id}" type="number" class="form-control" ${min} ${max} value="${escapeAttr(value)}"
                    data-node-id="${node.id}" data-field-key="${field.key}"
                    onchange="updateNodeConfig(${node.id}, '${field.key}', parseInt(this.value) || 0)">`;
                break;
            }
            case 'checkbox': {
                inputHtml = `<div class="form-check"><input id="${id}" type="checkbox" class="form-check-input" ${value ? 'checked' : ''}
                    data-node-id="${node.id}" data-field-key="${field.key}"
                    onchange="updateNodeConfig(${node.id}, '${field.key}', this.checked)"></div>`;
                break;
            }
            case 'json': {
                const text = (typeof value === 'object' && value !== null) ? JSON.stringify(value, null, 2) : (value || '');
                inputHtml = `<textarea id="${id}" class="form-control font-monospace" rows="3" ${ph}
                    data-node-id="${node.id}" data-field-key="${field.key}"
                    onchange="updateWebhookJson(${node.id}, '${field.key}', this.value, this)">${escapeAttr(text)}</textarea>`;
                break;
            }
            case 'time':
            case 'date':
            case 'url':
            case 'email':
            case 'text':
            default: {
                inputHtml = `<input id="${id}" type="${field.type === 'text' ? 'text' : field.type}" class="form-control" value="${escapeAttr(value)}" ${ph}
                    data-node-id="${node.id}" data-field-key="${field.key}"
                    onchange="updateNodeConfig(${node.id}, '${field.key}', this.value)">`;
                break;
            }
        }

        return `<div class="property-group${required}">
            <label class="property-label" for="${id}">${escapeAttr(field.label)} ${star}</label>
            ${inputHtml}
            ${help}
        </div>`;
    }

    function renderSchemaForm(node) {
        const fields = getSchemaFor(node);
        return fields.map(f => renderSchemaField(node, f)).join('');
    }

    // Returns array of missing-required field labels. Used by save handler to block saves.
    function validateNodeAgainstSchema(node) {
        const fields = getSchemaFor(node);
        const cfg = node.config || {};
        const missing = [];
        for (const f of fields) {
            if (!f.required) continue;
            const v = cfg[f.key];
            if (v === undefined || v === null || v === '') {
                missing.push(f.label);
            }
        }
        return missing;
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        initDragAndDrop();
        initCanvasEvents();

        @if(isset($workflow))
            loadWorkflowData();
        @endif
    });

    // Drag and Drop
    function initDragAndDrop() {
        document.querySelectorAll('.node-template').forEach(template => {
            template.addEventListener('dragstart', handleDragStart);
        });

        const canvasArea = document.getElementById('canvasArea');
        canvasArea.addEventListener('dragover', handleDragOver);
        canvasArea.addEventListener('drop', handleDrop);
        canvasArea.addEventListener('dragleave', function(e) {
            if (!canvasArea.contains(e.relatedTarget)) {
                canvasArea.classList.remove('drag-over');
            }
        });
    }

    function handleDragStart(e) {
        const template = e.target.closest('.node-template');
        if (!template) return;

        e.dataTransfer.setData('nodeType', template.dataset.type);
        e.dataTransfer.setData('actionType', template.dataset.action);
        e.dataTransfer.effectAllowed = 'copy';

        // Custom drag ghost
        const ghost = template.cloneNode(true);
        ghost.style.width = '260px';
        ghost.style.opacity = '0.85';
        ghost.style.position = 'absolute';
        ghost.style.top = '-9999px';
        ghost.style.borderRadius = '10px';
        ghost.style.boxShadow = '0 8px 20px rgba(0,0,0,0.15)';
        document.body.appendChild(ghost);
        e.dataTransfer.setDragImage(ghost, 130, 24);
        requestAnimationFrame(() => ghost.remove());
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
        document.getElementById('canvasArea').classList.add('drag-over');

        // Auto-scroll when dragging near canvas edges
        const builderCanvas = document.getElementById('builderCanvas');
        const bcRect = builderCanvas.getBoundingClientRect();
        const margin = 50, speed = 10;
        if (e.clientX > bcRect.right - margin) builderCanvas.scrollLeft += speed;
        if (e.clientX < bcRect.left + margin) builderCanvas.scrollLeft -= speed;
        if (e.clientY > bcRect.bottom - margin) builderCanvas.scrollTop += speed;
        if (e.clientY < bcRect.top + margin) builderCanvas.scrollTop -= speed;
    }

    function handleDrop(e) {
        e.preventDefault();
        const canvasArea = document.getElementById('canvasArea');
        canvasArea.classList.remove('drag-over');

        const nodeType = e.dataTransfer.getData('nodeType');
        const actionType = e.dataTransfer.getData('actionType');
        if (!nodeType || !actionType) return;

        const rect = canvasArea.getBoundingClientRect();
        // getBoundingClientRect already accounts for scroll offset
        const x = (e.clientX - rect.left) / workflowState.zoom;
        const y = (e.clientY - rect.top) / workflowState.zoom;

        // Center node at drop point (node width = 280)
        addNode(nodeType, actionType, Math.max(10, x - 140), Math.max(10, y - 20));
    }

    // Add Node
    function addNode(type, actionType, x, y) {
        const nodeId = ++workflowState.nodeIdCounter;

        const node = {
            id: nodeId,
            type: type,
            action_type: actionType,
            config: {},
            label: getNodeLabel(type, actionType),
            position_x: Math.round(x),
            position_y: Math.round(y),
            next_node_id: null,
            condition_true_node_id: null,
            condition_false_node_id: null
        };

        workflowState.nodes.push(node);
        renderNode(node);
        selectNode(nodeId);
    }

    function getNodeLabel(type, actionType) {
        if (type === 'trigger') {
            return resources.triggerTypes[actionType]?.label || actionType;
        } else if (type === 'action') {
            return resources.actionTypes[actionType]?.label || actionType;
        } else if (type === 'condition') {
            return resources.conditionTypes[actionType]?.label || actionType;
        } else if (type === 'wait') {
            return resources.waitTypes[actionType]?.label || actionType;
        }
        return actionType;
    }

    function getNodeIcon(type, actionType) {
        if (type === 'trigger') {
            return resources.triggerTypes[actionType]?.icon || 'ri-flashlight-line';
        } else if (type === 'action') {
            return resources.actionTypes[actionType]?.icon || 'ri-play-circle-line';
        } else if (type === 'condition') {
            return resources.conditionTypes[actionType]?.icon || 'ri-git-branch-line';
        } else if (type === 'wait') {
            return resources.waitTypes[actionType]?.icon || 'ri-time-line';
        }
        return 'ri-node-tree';
    }

    // Render Node
    function renderNode(node) {
        const canvas = document.getElementById('canvasArea');

        const nodeEl = document.createElement('div');
        nodeEl.className = `workflow-node ${node.type}${node.type === 'trigger' ? ' trigger-node' : ''}`;
        nodeEl.id = `node-${node.id}`;
        nodeEl.style.left = `${node.position_x}px`;
        nodeEl.style.top = `${node.position_y}px`;

        const connectors = node.type === 'condition'
            ? `<div class="node-connector output-true" data-type="true" title="{{ translate('Yes - drag to connect') }}"></div>
               <div class="node-connector output-false" data-type="false" title="{{ translate('No - drag to connect') }}"></div>`
            : `<div class="node-connector output" data-type="output" title="{{ translate('Drag to connect') }}"></div>`;

        // Trigger nodes show a hint that they're starting points
        const triggerHint = node.type === 'trigger'
            ? '<div class="node-hint">{{ translate("Start Point") }}</div>'
            : '';

        nodeEl.innerHTML = `
            ${triggerHint}
            ${node.type !== 'trigger' ? '<div class="node-connector input" data-type="input" title="{{ translate('Drop connection here') }}"></div>' : ''}
            <div class="node-header">
                <div class="node-icon ${node.type}">
                    <i class="${getNodeIcon(node.type, node.action_type)}"></i>
                </div>
                <span class="node-title">${node.label}</span>
                <div class="node-actions" onclick="event.stopPropagation();">
                    <button type="button" class="gear-btn" data-node-id="${node.id}" title="{{ translate('Edit') }}">
                        <i class="ri-settings-3-line"></i>
                    </button>
                    <button type="button" class="delete" data-node-id="${node.id}" title="{{ translate('Delete') }}">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </div>
            </div>
            <div class="node-body">
                ${getNodeSummary(node)}
            </div>
            ${connectors}
        `;

        // Edit button click (gear icon) - opens properties panel
        nodeEl.querySelector('.gear-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            selectNode(node.id);
            showPropertiesPanel(node.id);
        });

        // Delete button click
        nodeEl.querySelector('.node-actions .delete').addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();
            deleteNode(node.id);
        });

        // Node body click - select AND open properties panel (matches "Click to configure" hint)
        nodeEl.addEventListener('click', (e) => {
            // Don't trigger if clicking on buttons or connectors
            if (e.target.closest('.node-actions') || e.target.closest('.node-connector')) {
                return;
            }
            e.stopPropagation();
            selectNode(node.id);
            showPropertiesPanel(node.id);
        });

        // Make draggable
        nodeEl.addEventListener('mousedown', (e) => startDragNode(e, node.id));

        // Connector events for creating connections
        nodeEl.querySelectorAll('.node-connector').forEach(connector => {
            connector.addEventListener('mousedown', (e) => {
                if (connector.classList.contains('input')) {
                    // Input connectors receive connections, not start them
                    return;
                }
                startConnection(e, node.id, connector.dataset.type);
            });
        });

        canvas.appendChild(nodeEl);
    }

    function getNodeSummary(node) {
        const config = node.config || {};
        if (node.type === 'action') {
            if (node.action_type === 'send_sms' && config.message) {
                return `Message: "${config.message.substring(0, 30)}..."`;
            }
            if (node.action_type === 'send_email' && config.subject) {
                return `Subject: ${config.subject}`;
            }
            if (node.action_type === 'send_whatsapp' && config.message) {
                return `WhatsApp: "${config.message.substring(0, 30)}..."`;
            }
            if (node.action_type === 'add_tag' && config.tag) {
                return `Tag: ${config.tag}`;
            }
            if ((node.action_type === 'add_to_group' || node.action_type === 'remove_from_group') && config.group_id) {
                return node.action_type === 'add_to_group' ? `Add to group #${config.group_id}` : `Remove from group #${config.group_id}`;
            }
            if (node.action_type === 'update_contact' && config.field) {
                return `Set ${config.field} = ${(config.value || '').substring(0, 20)}`;
            }
            if (node.action_type === 'notify_admin' && config.recipient) {
                return `Notify ${config.method || 'email'} → ${config.recipient}`;
            }
            if (node.action_type === 'call_webhook' && config.url) {
                try {
                    const u = new URL(config.url);
                    return `${(config.method || 'POST')} ${u.host}${u.pathname.substring(0, 25)}`;
                } catch (e) {
                    return `${(config.method || 'POST')} (invalid URL)`;
                }
            }
            if (node.action_type === 'ai_auto_reply') {
                return `AI Reply (${config.model || 'gpt-4o-mini'})`;
            }
        }
        if (node.type === 'wait') {
            if (config.duration && config.unit) {
                return `Wait ${config.duration} ${config.unit}`;
            }
        }
        if (node.type === 'condition') {
            if (node.action_type === 'has_tag' && config.tag) return `Has tag: ${config.tag}`;
            if (node.action_type === 'field_equals' && config.field) return `${config.field} ${config.operator || '='} ${config.value || ''}`;
            if (node.action_type === 'random_split' && config.percentage) return `${config.percentage}% / ${100 - config.percentage}%`;
        }
        return 'Click to configure';
    }

    // Node Selection - selects and opens properties
    function selectNode(nodeId) {
        selectNodeOnly(nodeId);
        showPropertiesPanel(nodeId);
    }

    // Node Selection - only selects, doesn't open properties
    function selectNodeOnly(nodeId) {
        // Deselect previous
        document.querySelectorAll('.workflow-node.selected').forEach(n => n.classList.remove('selected'));

        workflowState.selectedNode = nodeId;

        const nodeEl = document.getElementById(`node-${nodeId}`);
        if (nodeEl) {
            nodeEl.classList.add('selected');
        }
    }

    // Properties Panel
    function showPropertiesPanel(nodeId) {
        const node = workflowState.nodes.find(n => n.id === nodeId);
        if (!node) return;

        const panel = document.getElementById('propertiesPanel');
        const content = document.getElementById('propertiesContent');
        const title = document.getElementById('propertyTitle');

        panel.style.display = 'flex';
        title.textContent = node.label;

        content.innerHTML = generatePropertiesForm(node);
    }

    function generatePropertiesForm(node) {
        let html = `
            <div class="property-group">
                <label class="property-label">Label</label>
                <input type="text" class="form-control" value="${node.label}"
                       onchange="updateNodeLabel(${node.id}, this.value)">
            </div>
        `;

        // Phase C: schema-driven path is preferred. Legacy generators are kept as fallback
        // for the (shrinking) set of node types not yet migrated (webhook trigger has custom UI etc).
        if (hasSchemaFor(node)) {
            html += renderSchemaForm(node);
            if (node.type === 'action') {
                html += generateErrorHandlingSection(node);
            }
            return html;
        }

        if (node.type === 'action') {
            html += generateActionForm(node);
        } else if (node.type === 'condition') {
            html += generateConditionForm(node);
        } else if (node.type === 'wait') {
            html += generateWaitForm(node);
        } else if (node.type === 'trigger') {
            html += generateTriggerForm(node);
        }

        return html;
    }

    function generateActionForm(node) {
        const config = node.config || {};
        let html = '';

        if (node.action_type === 'send_sms') {
            html += `
                <div class="property-group">
                    <label class="property-label">SMS Gateway</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'gateway_id', this.value)">
                        <option value="">Select Gateway</option>
                        ${resources.smsGateways.map(g => `<option value="${g.id}" ${config.gateway_id == g.id ? 'selected' : ''}>${g.name}</option>`).join('')}
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">Message</label>
                    <textarea class="form-control" rows="4" onchange="updateNodeConfig(${node.id}, 'message', this.value)"
                              placeholder="Use @{{first_name}}, @{{phone}} for personalization">${config.message || ''}</textarea>
                </div>
            `;
        } else if (node.action_type === 'send_email') {
            html += `
                <div class="property-group">
                    <label class="property-label">Email Gateway</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'gateway_id', this.value)">
                        <option value="">Select Gateway</option>
                        ${resources.emailGateways.map(g => `<option value="${g.id}" ${config.gateway_id == g.id ? 'selected' : ''}>${g.name}</option>`).join('')}
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">Subject</label>
                    <input type="text" class="form-control" value="${config.subject || ''}"
                           onchange="updateNodeConfig(${node.id}, 'subject', this.value)">
                </div>
                <div class="property-group">
                    <label class="property-label">Message</label>
                    <textarea class="form-control" rows="4" onchange="updateNodeConfig(${node.id}, 'message', this.value)">${config.message || ''}</textarea>
                </div>
            `;
        } else if (node.action_type === 'send_whatsapp') {
            html += `
                <div class="property-group">
                    <label class="property-label">WhatsApp Device</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'device_id', this.value)">
                        <option value="">Select Device</option>
                        ${resources.whatsappDevices.map(g => `<option value="${g.id}" ${config.device_id == g.id ? 'selected' : ''}>${g.name}</option>`).join('')}
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">Message</label>
                    <textarea class="form-control" rows="4" onchange="updateNodeConfig(${node.id}, 'message', this.value)">${config.message || ''}</textarea>
                </div>
            `;
        } else if (node.action_type === 'add_to_group' || node.action_type === 'remove_from_group') {
            html += `
                <div class="property-group">
                    <label class="property-label">Contact Group</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'group_id', this.value)">
                        <option value="">Select Group</option>
                        ${resources.groups.map(g => `<option value="${g.id}" ${config.group_id == g.id ? 'selected' : ''}>${g.name}</option>`).join('')}
                    </select>
                </div>
            `;
        } else if (node.action_type === 'add_tag') {
            html += `
                <div class="property-group">
                    <label class="property-label">Tag Name</label>
                    <input type="text" class="form-control" value="${config.tag || ''}"
                           onchange="updateNodeConfig(${node.id}, 'tag', this.value)"
                           placeholder="e.g. vip, hot-lead, replied">
                    <small class="text-muted">A single tag to attach to the contact.</small>
                </div>
            `;
        } else if (node.action_type === 'update_contact') {
            html += `
                <div class="property-group">
                    <label class="property-label">Field <span class="text-danger">*</span></label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'field', this.value)">
                        <option value="">Select Field</option>
                        <option value="first_name"     ${config.field === 'first_name' ? 'selected' : ''}>First Name</option>
                        <option value="last_name"      ${config.field === 'last_name' ? 'selected' : ''}>Last Name</option>
                        <option value="email_contact"  ${config.field === 'email_contact' ? 'selected' : ''}>Email</option>
                        <option value="sms_contact"    ${config.field === 'sms_contact' ? 'selected' : ''}>Phone (SMS)</option>
                        <option value="whatsapp_contact" ${config.field === 'whatsapp_contact' ? 'selected' : ''}>WhatsApp Number</option>
                        <option value="status"         ${config.field === 'status' ? 'selected' : ''}>Status</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">New Value <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" value="${config.value || ''}"
                           onchange="updateNodeConfig(${node.id}, 'value', this.value)"
                           placeholder="Use @{{first_name}}, @{{email}} for templating">
                    <small class="text-muted">Variables from the contact will be substituted before saving.</small>
                </div>
            `;
        } else if (node.action_type === 'notify_admin') {
            html += `
                <div class="property-group">
                    <label class="property-label">Method <span class="text-danger">*</span></label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'method', this.value)">
                        <option value="email" ${(config.method || 'email') === 'email' ? 'selected' : ''}>Email</option>
                        <option value="sms"   ${config.method === 'sms' ? 'selected' : ''}>SMS</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">Recipient <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" value="${config.recipient || ''}"
                           onchange="updateNodeConfig(${node.id}, 'recipient', this.value)"
                           placeholder="admin@example.com or +1234567890">
                    <small class="text-muted">Email address (for email) or phone number (for SMS).</small>
                </div>
                <div class="property-group">
                    <label class="property-label">Message <span class="text-danger">*</span></label>
                    <textarea class="form-control" rows="3"
                              onchange="updateNodeConfig(${node.id}, 'message', this.value)"
                              placeholder="Contact @{{first_name}} @{{last_name}} just triggered the workflow.">${config.message || ''}</textarea>
                    <small class="text-muted">Supports contact variables like @{{first_name}}, @{{email}}.</small>
                </div>
            `;
        } else if (node.action_type === 'call_webhook') {
            const headersJson = config.headers ? JSON.stringify(config.headers, null, 2) : '';
            const bodyJson = config.body ? JSON.stringify(config.body, null, 2) : '';
            html += `
                <div class="property-group">
                    <div class="alert alert-info small p-2 mb-2">
                        <i class="ri-lightbulb-line"></i> <strong>Webhook Action:</strong>
                        Sends contact + workflow data to an external URL. The contact object is always appended to the body automatically.
                    </div>
                </div>
                <div class="property-group">
                    <label class="property-label">URL <span class="text-danger">*</span></label>
                    <input type="url" class="form-control" value="${config.url || ''}"
                           onchange="updateNodeConfig(${node.id}, 'url', this.value)"
                           placeholder="https://your-service.example.com/hook">
                </div>
                <div class="property-group">
                    <label class="property-label">HTTP Method</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'method', this.value)">
                        <option value="POST"  ${(config.method || 'POST') === 'POST' ? 'selected' : ''}>POST</option>
                        <option value="PUT"   ${config.method === 'PUT' ? 'selected' : ''}>PUT</option>
                        <option value="PATCH" ${config.method === 'PATCH' ? 'selected' : ''}>PATCH</option>
                        <option value="GET"   ${config.method === 'GET' ? 'selected' : ''}>GET</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">Custom Headers (JSON)</label>
                    <textarea class="form-control font-monospace" rows="3"
                              onchange="updateWebhookJson(${node.id}, 'headers', this.value, this)"
                              placeholder='{ "Authorization": "Bearer xxx", "X-Custom": "value" }'>${headersJson}</textarea>
                    <small class="text-muted">Optional. Must be valid JSON object. Leave empty for none.</small>
                </div>
                <div class="property-group">
                    <label class="property-label">Custom Body (JSON)</label>
                    <textarea class="form-control font-monospace" rows="4"
                              onchange="updateWebhookJson(${node.id}, 'body', this.value, this)"
                              placeholder='{ "event": "lead_replied", "source": "@{{first_name}}" }'>${bodyJson}</textarea>
                    <small class="text-muted">Optional extra fields merged with the auto-appended contact data. Variables like @{{first_name}} are substituted at runtime.</small>
                </div>
                <div class="property-group">
                    <details>
                        <summary class="small text-primary" style="cursor:pointer;">{{ translate('Show payload structure') }}</summary>
                        <pre class="bg-light p-2 rounded mt-2" style="font-size: 11px; white-space: pre-wrap;">{
  "...your custom body fields...",
  "contact": { "id": 1, "uid": "...", "first_name": "...", "email": "..." },
  "workflow": { "id": 1, "name": "..." },
  "execution_id": "...",
  "timestamp": "2026-05-08T..."
}</pre>
                    </details>
                </div>
            `;
        } else if (node.action_type === 'ai_auto_reply') {
            @if(!($aiConfigured ?? false))
            html += `
                <div class="property-group">
                    <div class="alert alert-danger small p-2 mb-2">
                        <i class="ri-error-warning-line"></i> <strong>AI Not Configured</strong><br>
                        Go to <a href="{{ route('admin.system.setting', 'ai') }}" target="_blank"><strong>Settings → AI Functions</strong></a> to enable AI and add your OpenAI API key. Without this, AI auto-reply will not work.
                    </div>
                </div>
            `;
            @else
            html += `
                <div class="property-group">
                    <div class="alert alert-success small p-2 mb-2">
                        <i class="ri-check-line"></i> <strong>AI Configured</strong> — OpenAI is active and ready.
                    </div>
                </div>
            `;
            @endif
            html += `
                <div class="property-group">
                    <div class="alert alert-info small p-2 mb-2">
                        <i class="ri-lightbulb-line"></i> <strong>How AI Chatbot Works:</strong><br>
                        1. Set trigger to <strong>"Contact Replied"</strong> (fires when someone messages you)<br>
                        2. Connect this <strong>"AI Auto Reply"</strong> action<br>
                        3. Configure the system prompt below (tells AI how to respond)<br>
                        4. Activate the workflow — AI will auto-respond to incoming messages
                    </div>
                </div>
            `;
            html += `
                <div class="property-group">
                    <label class="property-label">System Prompt</label>
                    <textarea class="form-control" rows="3" placeholder="You are a helpful customer service assistant..."
                              onchange="updateNodeConfig(${node.id}, 'system_prompt', this.value)">${config.system_prompt || 'You are a helpful customer service assistant. Reply concisely and professionally. Keep responses under 200 words.'}</textarea>
                    <small class="text-muted">Instructions for the AI on how to respond</small>
                </div>
                <div class="property-group">
                    <label class="property-label">AI Model</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'model', this.value)">
                        <option value="gpt-4o-mini" ${(config.model || 'gpt-4o-mini') === 'gpt-4o-mini' ? 'selected' : ''}>GPT-4o Mini (Fast & Cheap)</option>
                        <option value="gpt-4o" ${config.model === 'gpt-4o' ? 'selected' : ''}>GPT-4o (Best Quality)</option>
                        <option value="gpt-3.5-turbo" ${config.model === 'gpt-3.5-turbo' ? 'selected' : ''}>GPT-3.5 Turbo (Legacy)</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">Reply Channel</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'channel', this.value)">
                        <option value="" ${!config.channel ? 'selected' : ''}>Auto-detect (same as incoming)</option>
                        <option value="whatsapp" ${config.channel === 'whatsapp' ? 'selected' : ''}>WhatsApp</option>
                        <option value="sms" ${config.channel === 'sms' ? 'selected' : ''}>SMS</option>
                        <option value="email" ${config.channel === 'email' ? 'selected' : ''}>Email</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">WhatsApp Device (for WhatsApp replies)</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'device_id', this.value)">
                        <option value="">Select Device</option>
                        ${resources.whatsappDevices.map(g => `<option value="${g.id}" ${config.device_id == g.id ? 'selected' : ''}>${g.name}</option>`).join('')}
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">Max Response Length</label>
                    <input type="number" class="form-control" value="${config.max_tokens || 500}" min="50" max="2000"
                           onchange="updateNodeConfig(${node.id}, 'max_tokens', parseInt(this.value))">
                    <small class="text-muted">Tokens (roughly 1 token = 4 characters)</small>
                </div>
                <div class="property-group">
                    <label class="property-label">{{ translate('Reply Language') }}</label>
                    <input type="text" class="form-control" value="${config.reply_language || ''}"
                           placeholder="auto (match customer's language)"
                           onchange="updateNodeConfig(${node.id}, 'reply_language', this.value)">
                    <small class="text-muted">{{ translate('Leave empty or "auto" to detect the customer\'s language. Set to "English", "Spanish", "Bengali", etc. to force a specific reply language.') }}</small>
                </div>
            `;
        }

        // Error Handling — common to every action node
        html += generateErrorHandlingSection(node);

        return html;
    }

    function generateErrorHandlingSection(node) {
        const config = node.config || {};
        const onFailure  = config.on_failure_action || (config.stop_on_failure ? 'stop' : 'continue');
        const retryCount = Number.isInteger(config.retry_count) ? config.retry_count : 0;
        const retryDelay = Number.isInteger(config.retry_delay) ? config.retry_delay : 60;
        const expanded   = retryCount > 0 || onFailure !== 'continue';
        return `
            <details class="property-group" ${expanded ? 'open' : ''}>
                <summary class="small text-primary" style="cursor:pointer; user-select:none;">
                    <i class="ri-error-warning-line"></i> {{ translate('Error Handling') }}
                </summary>
                <div class="mt-2">
                    <div class="mb-2">
                        <label class="property-label small">{{ translate('On failure') }}</label>
                        <select class="form-select form-select-sm"
                                onchange="updateErrorAction(${node.id}, this.value)">
                            <option value="continue" ${onFailure === 'continue' ? 'selected' : ''}>
                                {{ translate('Continue to next node (default)') }}
                            </option>
                            <option value="stop" ${onFailure === 'stop' ? 'selected' : ''}>
                                {{ translate('Stop workflow') }}
                            </option>
                            <option value="skip" ${onFailure === 'skip' ? 'selected' : ''}>
                                {{ translate('Skip remaining and mark execution completed') }}
                            </option>
                        </select>
                        <div class="text-muted small mt-1">
                            {{ translate('What to do if this node fails after all retries are exhausted.') }}
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="property-label small">{{ translate('Retry attempts') }}</label>
                        <input type="number" class="form-control form-control-sm"
                               min="0" max="10" value="${retryCount}"
                               onchange="updateNodeConfig(${node.id}, 'retry_count', Math.max(0, parseInt(this.value) || 0))">
                        <div class="text-muted small mt-1">
                            {{ translate('Maximum number of times to retry this node before treating it as failed. 0 = no retry.') }}
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="property-label small">{{ translate('Retry delay (seconds)') }}</label>
                        <input type="number" class="form-control form-control-sm"
                               min="5" max="3600" value="${retryDelay}"
                               onchange="updateNodeConfig(${node.id}, 'retry_delay', Math.max(5, parseInt(this.value) || 60))">
                        <div class="text-muted small mt-1">
                            {{ translate('Seconds to wait between retry attempts.') }}
                        </div>
                    </div>
                </div>
            </details>
        `;
    }

    // Keep stop_on_failure boolean in sync with the new on_failure_action select.
    // The execution service still reads stop_on_failure as a fallback for older nodes.
    function updateErrorAction(nodeId, value) {
        updateNodeConfig(nodeId, 'on_failure_action', value);
        updateNodeConfig(nodeId, 'stop_on_failure', value === 'stop');
    }

    function generateConditionForm(node) {
        const config = node.config || {};
        let html = '';

        if (node.action_type === 'field_equals') {
            html += `
                <div class="property-group">
                    <label class="property-label">Field</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'field', this.value)">
                        <option value="">Select Field</option>
                        <option value="first_name" ${config.field === 'first_name' ? 'selected' : ''}>First Name</option>
                        <option value="last_name" ${config.field === 'last_name' ? 'selected' : ''}>Last Name</option>
                        <option value="email_contact" ${config.field === 'email_contact' ? 'selected' : ''}>Email</option>
                        <option value="status" ${config.field === 'status' ? 'selected' : ''}>Status</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">Operator</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'operator', this.value)">
                        ${Object.entries(resources.operators).map(([k, v]) => `<option value="${k}" ${config.operator === k ? 'selected' : ''}>${v}</option>`).join('')}
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">Value</label>
                    <input type="text" class="form-control" value="${config.value || ''}"
                           onchange="updateNodeConfig(${node.id}, 'value', this.value)">
                </div>
            `;
        } else if (node.action_type === 'has_tag') {
            html += `
                <div class="property-group">
                    <label class="property-label">Tag Name</label>
                    <input type="text" class="form-control" value="${config.tag || ''}"
                           onchange="updateNodeConfig(${node.id}, 'tag', this.value)">
                </div>
            `;
        } else if (node.action_type === 'random_split') {
            html += `
                <div class="property-group">
                    <label class="property-label">Percentage (Yes path)</label>
                    <input type="number" class="form-control" min="1" max="99" value="${config.percentage || 50}"
                           onchange="updateNodeConfig(${node.id}, 'percentage', parseInt(this.value))">
                    <small class="text-muted">${config.percentage || 50}% will go to Yes, ${100 - (config.percentage || 50)}% to No</small>
                </div>
            `;
        }

        return html;
    }

    function generateWaitForm(node) {
        const config = node.config || {};

        if (node.action_type === 'delay') {
            return `
                <div class="property-group">
                    <label class="property-label">Duration</label>
                    <input type="number" class="form-control" min="1" value="${config.duration || 1}"
                           onchange="updateNodeConfig(${node.id}, 'duration', parseInt(this.value))">
                </div>
                <div class="property-group">
                    <label class="property-label">Unit</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'unit', this.value)">
                        <option value="minutes" ${config.unit === 'minutes' ? 'selected' : ''}>Minutes</option>
                        <option value="hours" ${config.unit === 'hours' ? 'selected' : ''}>Hours</option>
                        <option value="days" ${config.unit === 'days' ? 'selected' : ''}>Days</option>
                    </select>
                </div>
            `;
        } else if (node.action_type === 'until_time') {
            return `
                <div class="property-group">
                    <label class="property-label">Time</label>
                    <input type="time" class="form-control" value="${config.time || '09:00'}"
                           onchange="updateNodeConfig(${node.id}, 'time', this.value)">
                </div>
            `;
        }

        return '';
    }

    function generateTriggerForm(node) {
        const config = node.config || {};
        let html = '';

        if (node.action_type === 'new_contact') {
            html += `
                <div class="property-group">
                    <label class="property-label">Contact Group</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'group_id', this.value)">
                        <option value="">Any Group</option>
                        ${resources.groups.map(g => `<option value="${g.id}" ${config.group_id == g.id ? 'selected' : ''}>${g.name}</option>`).join('')}
                    </select>
                </div>
            `;
        } else if (node.action_type === 'webhook') {
            // Auto-generate webhook_id if not set
            if (!config.webhook_id) {
                config.webhook_id = 'wh_' + Math.random().toString(36).substring(2, 10);
                updateNodeConfig(node.id, 'webhook_id', config.webhook_id);
            }
            var webhookUrl = '{{ url("/api/automation/webhook") }}/' + config.webhook_id;

            html += `
                <div class="property-group">
                    <div class="alert alert-info small p-2 mb-2">
                        <i class="ri-lightbulb-line"></i> <strong>How Webhook Trigger Works:</strong><br>
                        External systems (Zapier, Pabbly, Meta Lead Ads, CRMs) send a POST request to the URL below. The workflow triggers automatically when data is received.
                    </div>
                </div>
                <div class="property-group">
                    <label class="property-label">Webhook URL <small class="text-muted">(copy this)</small></label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control form-control-sm bg-light" value="${webhookUrl}" id="webhookUrlField" readonly>
                        <button class="btn btn-outline-primary btn-sm" type="button" onclick="copyToClipboard('${webhookUrl}')">
                            <i class="ri-clipboard-line"></i>
                        </button>
                    </div>
                </div>
                <div class="property-group">
                    <label class="property-label">Webhook ID</label>
                    <input type="text" class="form-control form-control-sm bg-light" value="${config.webhook_id}" readonly>
                    <small class="text-muted">Auto-generated unique identifier</small>
                </div>
                <div class="property-group">
                    <label class="property-label">Expected Payload</label>
                    <pre class="bg-light p-2 rounded" style="font-size: 11px; margin: 0; white-space: pre-wrap;">POST ${webhookUrl}
Content-Type: application/json

{
  "email": "john@example.com",
  "phone": "+1234567890",
  "first_name": "John",
  "last_name": "Doe"
}</pre>
                    <small class="text-muted">At least one of: email, phone, or whatsapp number is required to identify the contact.</small>
                </div>
            `;
        } else if (node.action_type === 'contact_replied') {
            html += `
                <div class="property-group">
                    <label class="property-label">Channel</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'channel', this.value)">
                        <option value="" ${!config.channel ? 'selected' : ''}>Any Channel</option>
                        <option value="whatsapp" ${config.channel === 'whatsapp' ? 'selected' : ''}>WhatsApp Only</option>
                        <option value="sms" ${config.channel === 'sms' ? 'selected' : ''}>SMS Only</option>
                        <option value="email" ${config.channel === 'email' ? 'selected' : ''}>Email Only</option>
                    </select>
                    <small class="text-muted">Fires when a contact sends a message on the selected channel</small>
                </div>
            `;
        } else if (node.action_type === 'no_response') {
            html += `
                <div class="property-group">
                    <label class="property-label">Days Without Response</label>
                    <input type="number" class="form-control" value="${config.days || 3}" min="1" max="365"
                           onchange="updateNodeConfig(${node.id}, 'days', parseInt(this.value))">
                    <small class="text-muted">Trigger if contact hasn't replied within this many days</small>
                </div>
            `;
        } else if (node.action_type === 'schedule') {
            html += `
                <div class="property-group">
                    <label class="property-label">Schedule Type</label>
                    <select class="form-select" onchange="updateNodeConfig(${node.id}, 'schedule_type', this.value)">
                        <option value="daily" ${(config.schedule_type || 'daily') === 'daily' ? 'selected' : ''}>Daily</option>
                        <option value="weekly" ${config.schedule_type === 'weekly' ? 'selected' : ''}>Weekly</option>
                        <option value="monthly" ${config.schedule_type === 'monthly' ? 'selected' : ''}>Monthly</option>
                    </select>
                </div>
                <div class="property-group">
                    <label class="property-label">Time</label>
                    <input type="time" class="form-control" value="${config.time || '09:00'}"
                           onchange="updateNodeConfig(${node.id}, 'time', this.value)">
                </div>
            `;
        } else if (node.action_type === 'birthday') {
            html += `
                <div class="property-group">
                    <label class="property-label">Days Before Birthday</label>
                    <input type="number" class="form-control" value="${config.days_before || 0}" min="0" max="30"
                           onchange="updateNodeConfig(${node.id}, 'days_before', parseInt(this.value))">
                    <small class="text-muted">0 = on birthday, 1 = one day before, etc.</small>
                </div>
            `;
        }

        return html;
    }

    // Update functions
    function updateNodeLabel(nodeId, label) {
        const node = workflowState.nodes.find(n => n.id === nodeId);
        if (node) {
            node.label = label;
            const titleEl = document.querySelector(`#node-${nodeId} .node-title`);
            if (titleEl) titleEl.textContent = label;
        }
    }

    function updateNodeConfig(nodeId, key, value) {
        const node = workflowState.nodes.find(n => n.id === nodeId);
        if (node) {
            node.config[key] = value;
            const bodyEl = document.querySelector(`#node-${nodeId} .node-body`);
            if (bodyEl) bodyEl.textContent = getNodeSummary(node);
        }
    }

    // Parse a JSON string from a textarea, set the parsed object on the node config,
    // and provide visual feedback for invalid JSON. Used by the webhook action form.
    function updateWebhookJson(nodeId, key, raw, el) {
        const trimmed = (raw || '').trim();
        if (trimmed === '') {
            updateNodeConfig(nodeId, key, null);
            el.classList.remove('is-invalid');
            return;
        }
        try {
            const parsed = JSON.parse(trimmed);
            if (typeof parsed !== 'object' || Array.isArray(parsed) || parsed === null) {
                throw new Error('Must be a JSON object');
            }
            updateNodeConfig(nodeId, key, parsed);
            el.classList.remove('is-invalid');
        } catch (e) {
            el.classList.add('is-invalid');
            notify('error', 'Invalid JSON in ' + key + ': ' + e.message);
        }
    }

    // Delete Node
    function deleteNode(nodeId) {
        showConfirmModal(
            '{{ translate("Delete Node") }}',
            '{{ translate("Are you sure you want to delete this node? This will also remove all its connections.") }}',
            () => {
                workflowState.nodes = workflowState.nodes.filter(n => n.id !== nodeId);
                workflowState.connections = workflowState.connections.filter(c => c.from !== nodeId && c.to !== nodeId);

                // Also remove connections pointing to this node
                workflowState.nodes.forEach(n => {
                    if (n.next_node_id === nodeId) n.next_node_id = null;
                    if (n.condition_true_node_id === nodeId) n.condition_true_node_id = null;
                    if (n.condition_false_node_id === nodeId) n.condition_false_node_id = null;
                });

                const nodeEl = document.getElementById(`node-${nodeId}`);
                if (nodeEl) nodeEl.remove();

                document.getElementById('propertiesPanel').style.display = 'none';
                renderConnections();
                notify('success', '{{ translate("Node deleted") }}');
            },
            'danger'
        );
    }

    // Node Dragging
    let dragState = { isDragging: false, nodeId: null, offsetX: 0, offsetY: 0, rafId: null };

    function startDragNode(e, nodeId) {
        if (e.target.closest('.node-actions') || e.target.closest('.node-connector')) return;

        const nodeEl = document.getElementById(`node-${nodeId}`);
        const rect = nodeEl.getBoundingClientRect();

        dragState = {
            isDragging: false, // Will become true only after mouse moves > 5px
            nodeId: nodeId,
            startX: e.clientX,
            startY: e.clientY,
            offsetX: e.clientX - rect.left,
            offsetY: e.clientY - rect.top,
            rafId: null,
            hasMoved: false
        };

        document.addEventListener('mousemove', dragNode);
        document.addEventListener('mouseup', stopDragNode);
    }

    function dragNode(e) {
        if (!dragState.nodeId) return;

        // Only start dragging if mouse moved more than 5 pixels (prevents drag on click)
        if (!dragState.isDragging) {
            const dx = Math.abs(e.clientX - dragState.startX);
            const dy = Math.abs(e.clientY - dragState.startY);
            if (dx < 5 && dy < 5) return;

            // Threshold exceeded — actually start dragging now
            dragState.isDragging = true;
            const nodeEl = document.getElementById(`node-${dragState.nodeId}`);
            if (nodeEl) nodeEl.classList.add('is-dragging');
            selectNodeOnly(dragState.nodeId);
        }

        // Auto-scroll when dragging near canvas edges
        const builderCanvas = document.getElementById('builderCanvas');
        const bcRect = builderCanvas.getBoundingClientRect();
        const margin = 50, speed = 10;
        if (e.clientX > bcRect.right - margin) builderCanvas.scrollLeft += speed;
        if (e.clientX < bcRect.left + margin) builderCanvas.scrollLeft -= speed;
        if (e.clientY > bcRect.bottom - margin) builderCanvas.scrollTop += speed;
        if (e.clientY < bcRect.top + margin) builderCanvas.scrollTop -= speed;

        if (dragState.rafId) cancelAnimationFrame(dragState.rafId);

        dragState.rafId = requestAnimationFrame(() => {
            const canvas = document.getElementById('canvasArea');
            const canvasRect = canvas.getBoundingClientRect();

            // getBoundingClientRect already accounts for scroll offset
            const x = (e.clientX - canvasRect.left - dragState.offsetX) / workflowState.zoom;
            const y = (e.clientY - canvasRect.top - dragState.offsetY) / workflowState.zoom;

            const node = workflowState.nodes.find(n => n.id === dragState.nodeId);
            if (node) {
                node.position_x = Math.max(0, Math.round(x));
                node.position_y = Math.max(0, Math.round(y));

                const nodeEl = document.getElementById(`node-${dragState.nodeId}`);
                nodeEl.style.left = `${node.position_x}px`;
                nodeEl.style.top = `${node.position_y}px`;

                renderConnections();
            }
        });
    }

    function stopDragNode() {
        if (dragState.rafId) cancelAnimationFrame(dragState.rafId);

        const nodeEl = document.getElementById(`node-${dragState.nodeId}`);
        if (nodeEl) nodeEl.classList.remove('is-dragging');

        dragState.isDragging = false;
        dragState.nodeId = null;
        document.removeEventListener('mousemove', dragNode);
        document.removeEventListener('mouseup', stopDragNode);
    }

    // Connections
    let connectionState = { isConnecting: false, fromNode: null, fromType: null, tempLine: null };

    function showConnectionFeedback(message, type = 'info') {
        const feedback = document.getElementById('connectionFeedback');
        const text = document.getElementById('connectionFeedbackText');
        const icon = feedback.querySelector('i');

        text.textContent = message;
        feedback.className = 'connection-feedback show ' + type;
        icon.className = type === 'error' ? 'ri-close-circle-line' : 'ri-lightbulb-line';

        // Auto-hide after 2 seconds
        setTimeout(() => {
            feedback.classList.remove('show');
        }, 2000);
    }

    function hideConnectionFeedback() {
        document.getElementById('connectionFeedback').classList.remove('show');
    }

    function startConnection(e, nodeId, type) {
        e.stopPropagation();
        e.preventDefault();

        connectionState = { isConnecting: true, fromNode: nodeId, fromType: type, tempLine: null };

        // Show brief feedback
        showConnectionFeedback('{{ translate("Drop on action or condition node") }}', 'info');

        // Highlight valid target nodes (non-trigger nodes with input connectors)
        document.querySelectorAll('.workflow-node').forEach(nodeEl => {
            const targetNodeId = parseInt(nodeEl.id.replace('node-', ''));
            const targetNode = workflowState.nodes.find(n => n.id === targetNodeId);

            if (targetNodeId !== nodeId && targetNode) {
                if (targetNode.type === 'trigger') {
                    // Triggers cannot receive connections
                    nodeEl.classList.add('cannot-receive-connection');
                } else {
                    // Valid targets
                    nodeEl.classList.add('can-receive-connection');
                    const inputConnector = nodeEl.querySelector('.node-connector.input');
                    if (inputConnector) {
                        inputConnector.classList.add('can-connect');
                    }
                }
            }
        });

        // Create temporary line
        const svg = document.getElementById('connectionsSvg');
        const tempLine = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        tempLine.setAttribute('class', 'connecting-line');
        tempLine.setAttribute('id', 'tempConnectionLine');
        svg.appendChild(tempLine);
        connectionState.tempLine = tempLine;

        // Get starting position from node state (more reliable)
        const fromNode = workflowState.nodes.find(n => n.id === nodeId);
        const nodeWidth = 280;
        const nodeEl = document.getElementById(`node-${nodeId}`);
        const nodeHeight = nodeEl ? nodeEl.offsetHeight : 100;

        if (type === 'true') {
            connectionState.startX = fromNode.position_x + (nodeWidth * 0.3);
        } else if (type === 'false') {
            connectionState.startX = fromNode.position_x + (nodeWidth * 0.7);
        } else {
            connectionState.startX = fromNode.position_x + (nodeWidth / 2);
        }
        connectionState.startY = fromNode.position_y + nodeHeight;

        document.addEventListener('mousemove', drawTempConnection);
        document.addEventListener('mouseup', finishConnection);
    }

    function drawTempConnection(e) {
        if (!connectionState.isConnecting || !connectionState.tempLine) return;

        const canvas = document.getElementById('canvasArea');
        const canvasRect = canvas.getBoundingClientRect();

        // getBoundingClientRect already accounts for scroll offset
        const endX = (e.clientX - canvasRect.left) / workflowState.zoom;
        const endY = (e.clientY - canvasRect.top) / workflowState.zoom;

        const startX = connectionState.startX;
        const startY = connectionState.startY;

        // Calculate smooth bezier curve
        const deltaY = Math.abs(endY - startY);
        const curveOffset = Math.max(Math.min(deltaY * 0.5, 100), 40);

        connectionState.tempLine.setAttribute('d', `M ${startX} ${startY} C ${startX} ${startY + curveOffset}, ${endX} ${endY - curveOffset}, ${endX} ${endY}`);
    }

    function finishConnection(e) {
        document.removeEventListener('mousemove', drawTempConnection);
        document.removeEventListener('mouseup', finishConnection);

        // Remove all highlights
        document.querySelectorAll('.node-connector.can-connect').forEach(c => c.classList.remove('can-connect'));
        document.querySelectorAll('.workflow-node.can-receive-connection').forEach(n => n.classList.remove('can-receive-connection'));
        document.querySelectorAll('.workflow-node.cannot-receive-connection').forEach(n => n.classList.remove('cannot-receive-connection'));

        // Remove temp line
        if (connectionState.tempLine) {
            connectionState.tempLine.remove();
        }

        // Hide feedback
        hideConnectionFeedback();

        if (!connectionState.isConnecting) return;

        // First check if we dropped on any workflow node
        let targetNode = null;
        let targetConnector = null;
        const elementsAtPoint = document.elementsFromPoint(e.clientX, e.clientY);

        for (const element of elementsAtPoint) {
            // Check for input connector
            if (element.classList.contains('node-connector') && element.classList.contains('input')) {
                targetConnector = element;
                targetNode = element.closest('.workflow-node');
                break;
            }
            // Check for workflow node
            if (element.classList.contains('workflow-node')) {
                targetNode = element;
                targetConnector = element.querySelector('.node-connector.input');
                break;
            }
            // Check parent
            const parentNode = element.closest('.workflow-node');
            if (parentNode) {
                targetNode = parentNode;
                targetConnector = parentNode.querySelector('.node-connector.input');
                break;
            }
        }

        // If still no target found, check nearby nodes within tolerance
        if (!targetNode) {
            const tolerance = 50; // pixels
            let closestDistance = Infinity;

            document.querySelectorAll('.workflow-node').forEach(nodeEl => {
                const rect = nodeEl.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;
                const distance = Math.sqrt(Math.pow(e.clientX - centerX, 2) + Math.pow(e.clientY - centerY, 2));

                if (distance <= tolerance && distance < closestDistance) {
                    closestDistance = distance;
                    targetNode = nodeEl;
                    targetConnector = nodeEl.querySelector('.node-connector.input');
                }
            });
        }

        if (targetNode) {
            const toNodeId = parseInt(targetNode.id.replace('node-', ''));
            const toNode = workflowState.nodes.find(n => n.id === toNodeId);

            if (connectionState.fromNode === toNodeId) {
                showConnectionFeedback('{{ translate("Cannot connect to itself") }}', 'error');
                connectionState = { isConnecting: false, fromNode: null, fromType: null, tempLine: null };
                return;
            }

            // Check if target is a trigger node
            if (toNode && toNode.type === 'trigger') {
                showConnectionFeedback('{{ translate("Triggers are start points only") }}', 'error');
                connectionState = { isConnecting: false, fromNode: null, fromType: null, tempLine: null };
                return;
            }

            // Check if target has input connector
            if (!targetConnector) {
                showConnectionFeedback('{{ translate("Invalid drop target") }}', 'error');
                connectionState = { isConnecting: false, fromNode: null, fromType: null, tempLine: null };
                return;
            }

            if (!isNaN(toNodeId)) {
                const fromNode = workflowState.nodes.find(n => n.id === connectionState.fromNode);

                if (fromNode) {
                    if (connectionState.fromType === 'true') {
                        fromNode.condition_true_node_id = toNodeId;
                    } else if (connectionState.fromType === 'false') {
                        fromNode.condition_false_node_id = toNodeId;
                    } else {
                        fromNode.next_node_id = toNodeId;
                    }

                    // Use requestAnimationFrame to ensure DOM is ready
                    requestAnimationFrame(() => {
                        renderConnections();
                    });
                    notify('success', '{{ translate("Connected!") }}');
                }
            }
        }

        connectionState = { isConnecting: false, fromNode: null, fromType: null, tempLine: null };
    }

    function renderConnections() {
        const svg = document.getElementById('connectionsSvg');
        svg.innerHTML = '';

        // Set SVG to match canvas size
        const canvas = document.getElementById('canvasArea');
        svg.style.width = canvas.scrollWidth + 'px';
        svg.style.height = canvas.scrollHeight + 'px';

        workflowState.nodes.forEach(node => {
            if (node.next_node_id) {
                drawConnection(node.id, node.next_node_id, 'output', '');
            }
            if (node.condition_true_node_id) {
                drawConnection(node.id, node.condition_true_node_id, 'output-true', 'true');
            }
            if (node.condition_false_node_id) {
                drawConnection(node.id, node.condition_false_node_id, 'output-false', 'false');
            }
        });
    }

    function drawConnection(fromId, toId, outputType, pathClass) {
        const fromEl = document.getElementById(`node-${fromId}`);
        const toEl = document.getElementById(`node-${toId}`);
        const svg = document.getElementById('connectionsSvg');

        if (!fromEl || !toEl) {
            console.warn('Connection elements not found:', fromId, toId);
            return;
        }

        // Find the specific output connector
        let outputConnector = null;
        if (outputType === 'output-true') {
            outputConnector = fromEl.querySelector('.node-connector.output-true');
        } else if (outputType === 'output-false') {
            outputConnector = fromEl.querySelector('.node-connector.output-false');
        } else {
            outputConnector = fromEl.querySelector('.node-connector.output');
        }

        const inputConnector = toEl.querySelector('.node-connector.input');

        if (!outputConnector || !inputConnector) {
            console.warn('Connectors not found for nodes:', fromId, toId);
            return;
        }

        // Get node positions directly from state for more reliable coordinates
        const fromNode = workflowState.nodes.find(n => n.id === fromId);
        const toNode = workflowState.nodes.find(n => n.id === toId);

        if (!fromNode || !toNode) return;

        // Calculate positions based on node positions in state
        const nodeWidth = 280;
        const nodeHeight = fromEl.offsetHeight || 100;
        const toNodeHeight = toEl.offsetHeight || 100;

        let startX, startY;

        // Output connector position
        if (outputType === 'output-true') {
            startX = fromNode.position_x + (nodeWidth * 0.3);
            startY = fromNode.position_y + nodeHeight;
        } else if (outputType === 'output-false') {
            startX = fromNode.position_x + (nodeWidth * 0.7);
            startY = fromNode.position_y + nodeHeight;
        } else {
            startX = fromNode.position_x + (nodeWidth / 2);
            startY = fromNode.position_y + nodeHeight;
        }

        // Input connector position (top center of target node)
        const endX = toNode.position_x + (nodeWidth / 2);
        const endY = toNode.position_y;

        // Calculate control points for smooth curve
        const deltaY = Math.abs(endY - startY);
        const curveOffset = Math.max(Math.min(deltaY * 0.5, 100), 40);

        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', `M ${startX} ${startY} C ${startX} ${startY + curveOffset}, ${endX} ${endY - curveOffset}, ${endX} ${endY}`);
        path.setAttribute('class', `connection-line ${pathClass}`);
        path.setAttribute('data-from', fromId);
        path.setAttribute('data-to', toId);
        path.setAttribute('data-type', outputType);

        // Double-click to delete connection
        path.addEventListener('dblclick', function() {
            showConfirmModal(
                '{{ translate("Delete Connection") }}',
                '{{ translate("Are you sure you want to delete this connection?") }}',
                () => {
                    const fromNode = workflowState.nodes.find(n => n.id === parseInt(fromId));
                    if (fromNode) {
                        if (outputType === 'output-true') {
                            fromNode.condition_true_node_id = null;
                        } else if (outputType === 'output-false') {
                            fromNode.condition_false_node_id = null;
                        } else {
                            fromNode.next_node_id = null;
                        }
                        renderConnections();
                        notify('success', '{{ translate("Connection deleted") }}');
                    }
                },
                'warning'
            );
        });

        svg.appendChild(path);
    }

    // Canvas Events
    function initCanvasEvents() {
        document.getElementById('canvasArea').addEventListener('click', function(e) {
            if (e.target === this) {
                document.querySelectorAll('.workflow-node.selected').forEach(n => n.classList.remove('selected'));
                document.getElementById('propertiesPanel').style.display = 'none';
                workflowState.selectedNode = null;
            }
        });
    }

    // Zoom
    function zoomIn() {
        workflowState.zoom = Math.min(2, workflowState.zoom + 0.1);
        applyZoom();
    }

    function zoomOut() {
        workflowState.zoom = Math.max(0.5, workflowState.zoom - 0.1);
        applyZoom();
    }

    function applyZoom() {
        document.getElementById('canvasArea').style.transform = `scale(${workflowState.zoom})`;
        document.getElementById('canvasArea').style.transformOrigin = '0 0';
        document.getElementById('zoomLevel').textContent = `${Math.round(workflowState.zoom * 100)}%`;
    }

    // Load Workflow
    function loadWorkflowData() {
        fetch(`{{ route('admin.automation.data', $workflow->uid ?? '') }}`)
            .then(r => r.json())
            .then(data => {
                if (!data.status) return;

                document.getElementById('workflowName').value = data.data.name;

                // Create ID mapping for node references
                const idMap = {};
                data.data.nodes.forEach((n, i) => {
                    idMap[n.id] = ++workflowState.nodeIdCounter;
                });

                // Load nodes
                data.data.nodes.forEach(n => {
                    const node = {
                        id: idMap[n.id],
                        type: n.type,
                        action_type: n.action_type,
                        config: n.config || {},
                        label: n.label || getNodeLabel(n.type, n.action_type),
                        position_x: n.position_x || 100,
                        position_y: n.position_y || 100,
                        next_node_id: n.next_node_id ? idMap[n.next_node_id] : null,
                        condition_true_node_id: n.condition_true_node_id ? idMap[n.condition_true_node_id] : null,
                        condition_false_node_id: n.condition_false_node_id ? idMap[n.condition_false_node_id] : null
                    };

                    workflowState.nodes.push(node);
                    renderNode(node);
                });

                // Wait for DOM to fully render nodes before drawing connections
                requestAnimationFrame(() => {
                    setTimeout(() => {
                        renderConnections();
                    }, 100);
                });
            })
            .catch(err => {
                console.error('Error loading workflow:', err);
                notify('error', '{{ translate("Failed to load workflow data") }}');
            });
    }

    // Save Workflow
    function saveWorkflow(asDraft = true) {
        const name = document.getElementById('workflowName').value.trim();
        if (!name) {
            notify('error', '{{ translate("Please enter a workflow name") }}');
            return;
        }

        if (workflowState.nodes.length === 0) {
            notify('error', '{{ translate("Please add at least one node") }}');
            return;
        }

        // Find trigger node
        const triggerNode = workflowState.nodes.find(n => n.type === 'trigger');
        if (!triggerNode) {
            notify('error', '{{ translate("Please add a trigger node") }}');
            return;
        }

        // Phase C — block save if any schema-required field is empty across all nodes.
        const validationIssues = [];
        workflowState.nodes.forEach(n => {
            if (!hasSchemaFor(n)) return;
            const missing = validateNodeAgainstSchema(n);
            if (missing.length) {
                validationIssues.push({ node: n, missing });
            }
        });
        if (validationIssues.length) {
            const first = validationIssues[0];
            const list  = first.missing.join(', ');
            notify('error', `{{ translate("Node") }} "${first.node.label}" {{ translate("is missing required fields") }}: ${list}`);
            // Focus the offending node + reopen its properties so the user can fix it
            selectNode(first.node.id);
            return;
        }

        // Build node index mapping
        const nodeIndexMap = {};
        workflowState.nodes.forEach((n, i) => {
            nodeIndexMap[n.id] = i;
        });

        const payload = {
            name: name,
            description: '',
            trigger_type: triggerNode.action_type,
            trigger_config: triggerNode.config || {},
            nodes: workflowState.nodes.map(n => ({
                type: n.type,
                action_type: n.action_type,
                config: n.config,
                label: n.label,
                position_x: n.position_x,
                position_y: n.position_y,
                next_node_index: n.next_node_id ? nodeIndexMap[n.next_node_id] : null,
                condition_true_index: n.condition_true_node_id ? nodeIndexMap[n.condition_true_node_id] : null,
                condition_false_index: n.condition_false_node_id ? nodeIndexMap[n.condition_false_node_id] : null
            }))
        };

        const url = workflowState.isEditing
            ? `{{ url('admin/automation/update') }}/${workflowState.workflowId}`
            : '{{ route('admin.automation.store') }}';

        const method = workflowState.isEditing ? 'PUT' : 'POST';

        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.status) {
                notify('success', data.message);

                if (!asDraft && data.data?.workflow_id) {
                    // Activate workflow
                    fetch(`{{ url('admin/automation/activate') }}/${data.data.workflow_id}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    }).then(() => {
                        if (data.data?.redirect_url) {
                            window.location.href = data.data.redirect_url;
                        }
                    });
                } else if (data.data?.redirect_url) {
                    setTimeout(() => window.location.href = data.data.redirect_url, 1000);
                }
            } else {
                notify('error', data.message);
            }
        })
        .catch(err => {
            notify('error', '{{ translate("An error occurred") }}');
        });
    }

    function editNode(nodeId) {
        selectNode(nodeId);
    }

    // Template Modal Functions
    let templates = [];
    let selectedTemplate = null;
    const categoryColors = {
        'welcome': '#10b981',
        'engagement': '#f59e0b',
        'sales': '#3b82f6',
        'reminder': '#8b5cf6',
        'birthday': '#ec4899',
        'feedback': '#06b6d4'
    };

    const categoryLabels = {
        'welcome': '{{ translate("Welcome & Onboarding") }}',
        'engagement': '{{ translate("Re-engagement") }}',
        'sales': '{{ translate("Sales & Promotions") }}',
        'reminder': '{{ translate("Reminders") }}',
        'birthday': '{{ translate("Birthday & Anniversary") }}',
        'feedback': '{{ translate("Feedback & Survey") }}'
    };

    function showTemplateModal() {
        if (workflowState.nodes.length > 0) {
            showConfirmModal(
                '{{ translate("Replace Workflow?") }}',
                '{{ translate("Loading a template will replace your current workflow. All unsaved changes will be lost. Continue?") }}',
                () => {
                    document.getElementById('templateModal').classList.add('show');
                    loadTemplates();
                },
                'warning'
            );
            return;
        }

        document.getElementById('templateModal').classList.add('show');
        loadTemplates();
    }

    function hideTemplateModal() {
        document.getElementById('templateModal').classList.remove('show');
        selectedTemplate = null;
        document.getElementById('useTemplateBtn').disabled = true;
    }

    function loadTemplates() {
        fetch('{{ route("admin.automation.templates.list") }}')
            .then(r => r.json())
            .then(data => {
                if (data.status && data.data) {
                    templates = data.data;
                    renderCategories();
                    renderTemplates('all');
                }
            })
            .catch(err => {
                console.error('Error loading templates:', err);
                notify('error', '{{ translate("Failed to load templates") }}');
            });
    }

    function renderCategories() {
        const container = document.getElementById('templateCategories');
        container.innerHTML = '<button class="template-category-btn active" data-category="all" onclick="filterTemplates(\'all\', this)">{{ translate("All") }}</button>';

        const categories = [...new Set(templates.map(t => t.category))];
        categories.forEach(cat => {
            const label = categoryLabels[cat] || cat;
            container.innerHTML += `<button class="template-category-btn" data-category="${cat}" onclick="filterTemplates('${cat}', this)">${label}</button>`;
        });
    }

    function filterTemplates(category, btn) {
        document.querySelectorAll('.template-category-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        renderTemplates(category);
    }

    function renderTemplates(category) {
        const grid = document.getElementById('templateGrid');
        const filtered = category === 'all' ? templates : templates.filter(t => t.category === category);

        if (filtered.length === 0) {
            grid.innerHTML = `<div class="text-center py-4 text-muted"><i class="ri-folder-line fs-2 d-block mb-2"></i>{{ translate("No templates found") }}</div>`;
            return;
        }

        grid.innerHTML = filtered.map(template => {
            const color = categoryColors[template.category] || '#6b7280';
            const nodeCount = template.nodes ? template.nodes.length : 0;
            const catLabel = categoryLabels[template.category] || template.category;

            return `
                <div class="template-card" onclick="selectTemplate('${template.slug}')" data-slug="${template.slug}">
                    <div class="template-card-header">
                        <div class="template-card-icon" style="background: ${color}20; color: ${color};">
                            <i class="${template.icon || 'ri-flow-chart'}"></i>
                        </div>
                        <div>
                            <div class="template-card-title">${template.name}</div>
                            <div class="template-card-category">${catLabel}</div>
                        </div>
                    </div>
                    <div class="template-card-description">${template.description || ''}</div>
                    <div class="template-card-meta">
                        <span><i class="ri-node-tree me-1"></i>${nodeCount} {{ translate("nodes") }}</span>
                        <span><i class="ri-download-2-line me-1"></i>${template.usage_count || 0} {{ translate("uses") }}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function selectTemplate(slug) {
        document.querySelectorAll('.template-card').forEach(card => card.classList.remove('selected'));
        const card = document.querySelector(`.template-card[data-slug="${slug}"]`);
        if (card) {
            card.classList.add('selected');
            selectedTemplate = templates.find(t => t.slug === slug);
            document.getElementById('useTemplateBtn').disabled = false;
        }
    }

    function useSelectedTemplate() {
        if (!selectedTemplate) return;

        // Clear current workflow
        workflowState.nodes = [];
        workflowState.connections = [];
        workflowState.nodeIdCounter = 0;
        document.getElementById('canvasArea').innerHTML = '';
        document.getElementById('connectionsSvg').innerHTML = '';

        // Set workflow name from template
        document.getElementById('workflowName').value = selectedTemplate.name;

        // Load template nodes
        if (selectedTemplate.nodes && selectedTemplate.nodes.length > 0) {
            const nodeIdMap = {};

            selectedTemplate.nodes.forEach((templateNode, index) => {
                const nodeId = ++workflowState.nodeIdCounter;
                nodeIdMap[index] = nodeId;

                const node = {
                    id: nodeId,
                    type: templateNode.type || 'action',
                    action_type: templateNode.action || templateNode.action_type || 'send_sms',
                    config: templateNode.config || {},
                    label: templateNode.label || getNodeLabel(templateNode.type, templateNode.action),
                    position_x: templateNode.position_x || 400,
                    position_y: templateNode.position_y || 50 + (index * 130),
                    next_node_id: null,
                    condition_true_node_id: null,
                    condition_false_node_id: null
                };

                workflowState.nodes.push(node);
                renderNode(node);
            });

            // Create connections between sequential nodes
            for (let i = 0; i < workflowState.nodes.length - 1; i++) {
                const currentNode = workflowState.nodes[i];
                const nextNode = workflowState.nodes[i + 1];

                if (currentNode.type !== 'condition') {
                    currentNode.next_node_id = nextNode.id;
                    workflowState.connections.push({
                        from: currentNode.id,
                        to: nextNode.id,
                        type: 'default'
                    });
                }
            }

            // Redraw connections
            drawAllConnections();
        }

        hideTemplateModal();
        notify('success', '{{ translate("Template loaded successfully") }}');
    }

    function drawAllConnections() {
        // Use requestAnimationFrame to ensure DOM is ready
        requestAnimationFrame(() => {
            renderConnections();
        });
    }

    // Confirmation Modal Functions
    let confirmModalCallback = null;

    function showConfirmModal(title, message, callback, type = 'warning') {
        const modal = document.getElementById('confirmModal');
        const iconEl = document.getElementById('confirmModalIcon');
        const titleEl = document.getElementById('confirmModalTitle');
        const messageEl = document.getElementById('confirmModalMessage');
        const btnEl = document.getElementById('confirmModalBtn');

        titleEl.textContent = title;
        messageEl.textContent = message;
        confirmModalCallback = callback;

        // Set icon and style based on type
        iconEl.className = 'confirm-modal-icon ' + type;
        if (type === 'danger') {
            iconEl.innerHTML = '<i class="ri-delete-bin-line"></i>';
            btnEl.className = 'btn btn-danger';
            btnEl.textContent = '{{ translate("Delete") }}';
        } else {
            iconEl.innerHTML = '<i class="ri-question-line"></i>';
            btnEl.className = 'btn btn-primary';
            btnEl.textContent = '{{ translate("Confirm") }}';
        }

        modal.classList.add('show');
    }

    function hideConfirmModal() {
        document.getElementById('confirmModal').classList.remove('show');
        confirmModalCallback = null;
    }

    function confirmModalAction() {
        if (confirmModalCallback && typeof confirmModalCallback === 'function') {
            confirmModalCallback();
        }
        hideConfirmModal();
    }

    // Help Modal Functions
    function showHelpModal() {
        document.getElementById('helpModal').classList.add('show');
    }

    function hideHelpModal() {
        document.getElementById('helpModal').classList.remove('show');
    }

    // Close modal on overlay click
    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideConfirmModal();
        }
    });

    document.getElementById('helpModal').addEventListener('click', function(e) {
        if (e.target === this) {
            hideHelpModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideConfirmModal();
            hideTemplateModal();
            hideHelpModal();
        }
    });

    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                notify('success', 'Copied!');
            });
        } else {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            notify('success', 'Copied!');
        }
    }

    @isset($workflow)
    // ===== Test Run / Dry Run =====================================================
    let testRunModalInstance = null;
    let testRunSampleLoaded = false;

    function openTestRunModal() {
        if (workflowState.nodes.length === 0) {
            notify('error', '{{ translate("Add at least one node before running a test") }}');
            return;
        }
        const modalEl = document.getElementById('testRunModal');
        testRunModalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        testRunModalInstance.show();

        // Reset previous results
        document.getElementById('testRunResults').classList.add('d-none');
        document.getElementById('testRunResultsBody').innerHTML = '';

        if (!testRunSampleLoaded) {
            loadTestRunSampleContacts();
        }
    }

    function loadTestRunSampleContacts() {
        const select = document.getElementById('testRunContactSelect');
        fetch(`{{ route('admin.automation.sample-contacts', $workflow->uid) }}`)
            .then(r => r.json())
            .then(data => {
                if (!data.contacts || data.contacts.length === 0) {
                    select.innerHTML = '<option value="">{{ translate("No contacts available") }}</option>';
                    return;
                }
                select.innerHTML = '<option value="">{{ translate("-- Select contact --") }}</option>' +
                    data.contacts.map(c =>
                        `<option value="${c.id}">${c.name} ${c.email ? '— ' + c.email : ''}</option>`
                    ).join('');
                testRunSampleLoaded = true;
            })
            .catch(() => {
                select.innerHTML = '<option value="">{{ translate("Failed to load contacts") }}</option>';
            });
    }

    function runTestRun() {
        const contactId = document.getElementById('testRunContactSelect').value;
        if (!contactId) {
            notify('error', '{{ translate("Pick a sample contact first") }}');
            return;
        }

        const btn = document.getElementById('runTestRunBtn');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i>{{ translate("Simulating…") }}';

        const formData = new FormData();
        formData.append('contact_id', contactId);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

        fetch(`{{ route('admin.automation.test-run', $workflow->uid) }}`, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(r => r.json().then(d => ({ status: r.status, body: d })))
            .then(({ status, body }) => {
                btn.disabled = false;
                btn.innerHTML = original;
                if (status >= 400 || !body.status) {
                    document.getElementById('testRunResults').classList.remove('d-none');
                    document.getElementById('testRunResultsBody').innerHTML =
                        `<div class="alert alert-danger small">${escapeHtml(body.message || 'Test run failed')}</div>`;
                    return;
                }
                renderTestRunResults(body.result);
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = original;
                document.getElementById('testRunResults').classList.remove('d-none');
                document.getElementById('testRunResultsBody').innerHTML =
                    `<div class="alert alert-danger small">${escapeHtml(err.message)}</div>`;
            });
    }

    function renderTestRunResults(result) {
        document.getElementById('testRunResults').classList.remove('d-none');
        const body = document.getElementById('testRunResultsBody');
        const headerClass = result.success ? 'alert-success' : 'alert-warning';
        const headerIcon = result.success ? 'ri-checkbox-circle-line' : 'ri-alert-line';
        let html = `<div class="alert ${headerClass} small mb-3">
            <i class="${headerIcon}"></i>
            <strong>${result.success ? '{{ translate("All checks passed") }}' : '{{ translate("Issues detected") }}'}.</strong>
            ${result.step_count} {{ translate("steps simulated") }}, ${result.errors} {{ translate("errors") }}.
        </div>`;

        html += `<div class="mb-3 small text-muted">{{ translate("Sample contact") }}:
            <strong>${escapeHtml((result.contact.first_name || '') + ' ' + (result.contact.last_name || ''))}</strong>
            ${result.contact.email ? '— ' + escapeHtml(result.contact.email) : ''}
        </div>`;

        html += '<ul class="list-group list-group-flush border rounded">';
        result.steps.forEach(s => {
            const statusBadge = {
                ok:      '<span class="badge bg-success">OK</span>',
                error:   '<span class="badge bg-danger">ERROR</span>',
                warning: '<span class="badge bg-warning text-dark">WARN</span>',
                info:    '<span class="badge bg-info">INFO</span>',
            }[s.status] || '<span class="badge bg-secondary">?</span>';

            html += `<li class="list-group-item d-flex gap-3 align-items-start">
                <div style="min-width:35px;">${s.step ? '#' + s.step : ''}</div>
                <div style="min-width:60px;">${statusBadge}</div>
                <div class="flex-grow-1">
                    ${s.label ? `<strong>${escapeHtml(s.label)}</strong>` : ''}
                    ${s.action_type ? `<span class="text-muted small ms-1">(${escapeHtml(s.action_type)})</span>` : ''}
                    <div class="small">${escapeHtml(s.message || '')}</div>
                </div>
            </li>`;
        });
        html += '</ul>';

        body.innerHTML = html;
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    @endisset
</script>
@endpush

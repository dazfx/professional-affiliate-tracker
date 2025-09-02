/**
 * Professional Affiliate Tracking System - Main Application JavaScript
 * Version: 2.0.0
 * Author: TeamLead Optimized
 * Last Updated: 2025-09-01
 */

'use strict';

const AffiliateTracker = (function($) {
    
    // Configuration
    const CONFIG = {
        API_ENDPOINT: 'api.php',
        TOAST_DELAY: 4000,
        DEBOUNCE_DELAY: 400,
        REFRESH_INTERVAL: 30000,
        MAX_RETRIES: 3
    };
    
    const SELECTORS = {
        PARTNER_MODAL: '#partnerModal',
        TOAST: '#liveToast',
        THEME_TOGGLE: '#theme-toggle',
        PARTNERS_TABLE: '#partnersTableBody',
        PARTNER_FORM: '#partnerForm'
    };
    
    // Private variables
    let partnerModal = null;
    let toast = null;
    let lastFocusedElement = null;
    
    const cache = {
        refreshTimers: new Map(),
        dataTableInstances: new Map(),
        tagInputs: new Map()
    };
    
    // Utility functions
    const Logger = {
        info: (message, data = null) => console.info(`[AffiliateTracker] ${message}`, data || ''),
        warn: (message, data = null) => console.warn(`[AffiliateTracker] ${message}`, data || ''),
        error: (message, error = null) => console.error(`[AffiliateTracker] ${message}`, error || '')
    };
    
    const debounce = (func, delay) => {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    };
    
    // Notification manager
    const NotificationManager = {
        show: (message, type = 'success') => {
            try {
                const isSuccess = type === 'success';
                const title = isSuccess ? 'Успех' : 'Ошибка';
                const bgClass = isSuccess ? 'bg-success' : 'bg-danger';
                
                $(SELECTORS.TOAST)
                    .find('#toastTitle').text(title).end()
                    .find('#toastBody').text(message).end()
                    .removeClass('bg-success bg-danger')
                    .addClass(bgClass);
                
                if (toast) {
                    toast.show();
                }
                
                Logger.info(`Notification shown: ${type} - ${message}`);
            } catch (error) {
                Logger.error('Failed to show notification', error);
                alert(`${type.toUpperCase()}: ${message}`);
            }
        }
    };
    
    // API manager
    const ApiManager = {
        call: async (body, button = null) => {
            const $btn = $(button);
            let originalContent = '';
            
            if ($btn.length) {
                originalContent = $btn.html();
                $btn.prop('disabled', true);
                $btn.find('i').removeClass().addClass('fas fa-spinner fa-spin');
            }
            
            try {
                Logger.info('API call', { endpoint: CONFIG.API_ENDPOINT, body });
                
                const response = await fetch(CONFIG.API_ENDPOINT, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(body)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                Logger.info('API call successful', { success: result.success });
                
                return result;
                
            } catch (error) {
                Logger.error('API call failed', error);
                return {
                    success: false,
                    message: `Ошибка сети или сервера: ${error.message}`
                };
            } finally {
                if ($btn.length) {
                    $btn.prop('disabled', false).html(originalContent);
                }
            }
        }
    };
    
    // Tag input system
    function createTagsInput(inputElement) {
        if (!inputElement) return { render: () => {}, setValue: () => {} };
        
        const container = document.createElement('div');
        container.className = 'tags-input-container';
        
        const typingInput = document.createElement('input');
        typingInput.type = 'text';
        typingInput.className = 'tags-typing-input';
        typingInput.placeholder = inputElement.placeholder;
        
        inputElement.type = 'hidden';
        inputElement.parentNode.insertBefore(container, inputElement);
        container.appendChild(typingInput);
        
        const getTags = () => inputElement.value ? 
            inputElement.value.split(',').map(t => t.trim()).filter(Boolean) : [];
        
        const renderTags = () => {
            container.querySelectorAll('.tag').forEach(tag => tag.remove());
            getTags().forEach(tagText => {
                const tag = document.createElement('span');
                tag.className = 'tag';
                tag.innerHTML = `${tagText} <span class="tag-remove" data-tag="${tagText}">&times;</span>`;
                container.insertBefore(tag, typingInput);
            });
        };
        
        const addTags = (tagsToAdd) => {
            const currentTags = getTags();
            const newTags = tagsToAdd.split(',').map(t => t.trim()).filter(Boolean);
            if (newTags.length === 0) return;
            
            const allTags = [...new Set([...currentTags, ...newTags])];
            inputElement.value = allTags.join(',');
            renderTags();
            typingInput.value = '';
        };
        
        // Event listeners
        typingInput.addEventListener('keydown', (e) => {
            if (e.key === ',' || e.key === 'Enter') {
                e.preventDefault();
                addTags(typingInput.value);
            }
        });
        
        typingInput.addEventListener('blur', () => addTags(typingInput.value));
        
        container.addEventListener('click', (e) => {
            if (e.target.classList.contains('tag-remove')) {
                const tagToRemove = e.target.dataset.tag;
                inputElement.value = getTags().filter(t => t !== tagToRemove).join(',');
                renderTags();
            } else if (e.target === container) {
                typingInput.focus();
            }
        });
        
        renderTags();
        return {
            render: renderTags,
            setValue: (value) => {
                inputElement.value = value;
                renderTags();
            }
        };
    }
    
    // Theme manager
    const ThemeManager = {
        apply: (theme) => {
            const isDark = theme === 'dark';
            
            $('body').addClass('theme-changing');
            
            setTimeout(() => {
                $('body').toggleClass('dark-mode', isDark);
                
                const icon = $(SELECTORS.THEME_TOGGLE).find('i');
                icon.removeClass('fa-sun fa-moon');
                icon.addClass(isDark ? 'fa-sun' : 'fa-moon');
                
                $('body').removeClass('theme-changing');
            }, 50);
        },
        
        toggle: () => {
            const newTheme = $('body').hasClass('dark-mode') ? 'light' : 'dark';
            localStorage.setItem('theme', newTheme);
            ThemeManager.apply(newTheme);
        }
    };
    
    // DataTable initialization
    function initializeDataTable(partnerId) {
        if (cache.dataTableInstances.has(partnerId)) {
            return cache.dataTableInstances.get(partnerId);
        }
        
        const tableEl = $(`#table-${partnerId}`);
        if (!tableEl.length) {
            Logger.error(`Table not found for partner: ${partnerId}`);
            return null;
        }
        
        const dataTable = tableEl.DataTable({
            processing: true,
            ajax: {
                url: 'api.php?action=get_detailed_stats',
                type: 'GET',
                data: (d) => {
                    d.partner_id = partnerId;
                    // Add filters here
                },
                dataSrc: 'data'
            },
            columns: [
                { data: 'date', title: 'Дата' },
                { data: 'click_id', title: 'Click ID' },
                { data: 'url', title: 'URL' },
                { data: 'status', title: 'Статус' },
                { data: 'sum', title: 'Sum' },
                { data: 'sum_mapping', title: 'Sum Map' }
            ],
            order: [[0, 'desc']],
            pageLength: 25,
            responsive: true,
            language: {
                url: 'https://cdn.datatables.net/plug-ins/2.0.8/i18n/ru.json'
            }
        });
        
        cache.dataTableInstances.set(partnerId, dataTable);
        return dataTable;
    }
    
    // Public initialization
    function init() {
        Logger.info('Initializing Affiliate Tracker');
        
        // Initialize Bootstrap components
        const partnerModalEl = document.getElementById('partnerModal');
        if (partnerModalEl) {
            partnerModal = new bootstrap.Modal(partnerModalEl);
        }
        
        const toastEl = document.getElementById('liveToast');
        if (toastEl) {
            toast = new bootstrap.Toast(toastEl, { delay: CONFIG.TOAST_DELAY });
        }
        
        // Initialize tooltips
        $('[data-bs-toggle="tooltip"]').tooltip();
        
        // Initialize tag inputs
        const tagInputElements = [
            { id: 'clickidKeys', instance: 'clickid' },
            { id: 'sumKeys', instance: 'sum' },
            { id: 'telegramWhitelistKeywords', instance: 'whitelist' },
            { id: 'allowedIps', instance: 'ips' }
        ];
        
        tagInputElements.forEach(({ id, instance }) => {
            const element = document.getElementById(id);
            if (element) {
                cache.tagInputs.set(instance, createTagsInput(element));
            }
        });
        
        // Theme initialization
        const savedTheme = localStorage.getItem('theme') || 
            (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        ThemeManager.apply(savedTheme);
        
        // Event listeners
        $(SELECTORS.THEME_TOGGLE).on('click', (e) => {
            e.preventDefault();
            ThemeManager.toggle();
        });
        
        // Partner form events
        $('#addPartnerBtn').on('click', function() {
            if (partnerModal) {
                partnerModal.show();
            }
        });
        
        // API form submissions
        $(SELECTORS.PARTNER_FORM).on('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const partnerData = Object.fromEntries(formData.entries());
            
            const result = await ApiManager.call({
                action: 'save_partner',
                partner: partnerData
            }, $('#savePartnerBtn')[0]);
            
            NotificationManager.show(result.message, result.success ? 'success' : 'error');
            
            if (result.success && partnerModal) {
                partnerModal.hide();
                setTimeout(() => window.location.reload(), 1000);
            }
        });
        
        Logger.info('Affiliate Tracker initialized successfully');
    }
    
    // Public API
    return {
        init: init,
        NotificationManager: NotificationManager,
        ApiManager: ApiManager,
        ThemeManager: ThemeManager
    };
    
})(jQuery);

// Initialize when DOM is ready
jQuery(document).ready(function() {
    AffiliateTracker.init();
});
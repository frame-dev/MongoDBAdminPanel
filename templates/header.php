<?php
require_once 'config/security.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MongoDB Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <link rel="stylesheet" href="styles.css">
    <script>
        function switchCollection(collectionName) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('collection', collectionName);
            urlParams.set('page', '1');
            window.location.href = '?' + urlParams.toString();
        }

        function switchTab(tabName, buttonElement) {
            const tabContent = document.getElementById(tabName);
            if (!tabContent) {
                console.error('Tab content not found:', tabName);
                return;
            }
            
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            tabContent.classList.add('active');
            if (buttonElement) {
                buttonElement.classList.add('active');
            }

            try {
                localStorage.setItem('activeTab', tabName);
            } catch (e) {
                // ignore storage errors (private mode, blocked, etc.)
            }
        }
        
        function performSearch() {
            const query = document.getElementById('searchInput').value;
            const sort = document.getElementById('sortField').value;
            const order = document.getElementById('sortOrder').value;
            window.location.href = '?search=' + encodeURIComponent(query) + '&sort=' + encodeURIComponent(sort) + '&order=' + encodeURIComponent(order) + '&page=1';
        }
        
        function executeQuickQuery() {
            const quickForm = document.getElementById('quickQueryForm') || document;
            const field = quickForm.querySelector('input[name="query_field"]').value;
            const value = quickForm.querySelector('input[name="query_value"]').value;
            const operator = quickForm.querySelector('select[name="query_op"]').value;
            const sort = quickForm.querySelector('select[name="sort"]').value;
            const sortOrder = (quickForm.querySelector('select[name="sort_order"]') || { value: 'desc' }).value;
            const limit = quickForm.querySelector('input[name="limit"]').value;
            const projection = (quickForm.querySelector('input[name="projection"]') || { value: '' }).value;
            const valueType = (quickForm.querySelector('select[name="value_type"]') || { value: 'string' }).value;
            
            if (!field || !value) {
                alert('Please enter both field name and value');
                return false;
            }
            
            // Keep user on Query tab after reload
            try { localStorage.setItem('activeTab', 'query'); } catch (e) {}

            // Get CSRF token from the page (if present)
            const csrfInput = quickForm.querySelector('input[name="csrf_token"]') || document.querySelector('input[name="csrf_token"]');
            const csrfToken = csrfInput ? csrfInput.value : '';
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            form.innerHTML = `
                <input type="hidden" name="action" value="execute_query">
                <input type="hidden" name="query_field" value="${escapeHtml(field)}">
                <input type="hidden" name="query_value" value="${escapeHtml(value)}">
                <input type="hidden" name="query_op" value="${escapeHtml(operator)}">
                <input type="hidden" name="sort" value="${escapeHtml(sort)}">
                <input type="hidden" name="sort_order" value="${escapeHtml(sortOrder)}">
                <input type="hidden" name="limit" value="${escapeHtml(limit)}">
                <input type="hidden" name="projection" value="${escapeHtml(projection)}">
                <input type="hidden" name="value_type" value="${escapeHtml(valueType)}">
                <input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}">
                <input type="hidden" name="stay_on_query" value="1">
            `;
            
            document.body.appendChild(form);
            form.submit();
            return false;
        }
        
        function executeCustomQuery() {
            const customForm = document.getElementById('customQueryForm') || document;
            const customQuery = customForm.querySelector('textarea[name="custom_query"]').value;
            const sort = (customForm.querySelector('select[name="sort"]') || { value: '_id' }).value;
            const sortOrder = (customForm.querySelector('select[name="sort_order"]') || { value: 'desc' }).value;
            const limit = (customForm.querySelector('input[name="limit"]') || { value: '100' }).value;
            const projection = (customForm.querySelector('input[name="projection"]') || { value: '' }).value;
            
            if (!customQuery.trim()) {
                alert('Please enter a query');
                return false;
            }
            
            // Keep user on Query tab after reload
            try { localStorage.setItem('activeTab', 'query'); } catch (e) {}

            // Get CSRF token from the page (if present)
            const csrfInput = customForm.querySelector('input[name="csrf_token"]') || document.querySelector('input[name="csrf_token"]');
            const csrfToken = csrfInput ? csrfInput.value : '';
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            form.innerHTML = `
                <input type="hidden" name="action" value="execute_custom_query">
                <input type="hidden" name="custom_query" value="${escapeHtml(customQuery)}">
                <input type="hidden" name="sort" value="${escapeHtml(sort)}">
                <input type="hidden" name="sort_order" value="${escapeHtml(sortOrder)}">
                <input type="hidden" name="limit" value="${escapeHtml(limit)}">
                <input type="hidden" name="projection" value="${escapeHtml(projection)}">
                <input type="hidden" name="csrf_token" value="${escapeHtml(csrfToken)}">
                <input type="hidden" name="stay_on_query" value="1">
            `;
            
            document.body.appendChild(form);
            form.submit();
            return false;
        }
        
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
        
        function jumpToPage(pageNum) {
            if (pageNum) {
                const query = document.getElementById('searchInput').value;
                const sort = document.getElementById('sortField').value;
                const order = document.getElementById('sortOrder').value;
                window.location.href = '?search=' + encodeURIComponent(query) + '&sort=' + encodeURIComponent(sort) + '&order=' + encodeURIComponent(order) + '&page=' + pageNum;
            }
        }
        
        // NOTE: viewDocument, editDocument, closeViewModal, etc. are now defined in index.php Browse Tab section
        // These old query builder functions have been moved to the Browse Tab implementation
        
        function loadTemplate(templateData) {
            // Parse the template data
            try {
                const parsedTemplate = JSON.parse(templateData);
                const formattedJson = JSON.stringify(parsedTemplate, null, 2);
                
                // Switch to Add Document tab
                switchTab('add', document.querySelectorAll('.tab-btn')[3]);
                
                // Fill in the textarea
                setTimeout(() => {
                    const textarea = document.querySelector('#add textarea[name="json_data"]');
                    if (textarea) {
                        textarea.value = formattedJson;
                        textarea.focus();
                        
                        // Show success message
                        const tempMsg = document.createElement('div');
                        tempMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 10000; animation: slideInRight 0.3s ease;';
                        tempMsg.innerHTML = '✅ Template loaded successfully!';
                        document.body.appendChild(tempMsg);
                        
                        setTimeout(() => {
                            tempMsg.style.animation = 'slideOutRight 0.3s ease';
                            setTimeout(() => tempMsg.remove(), 300);
                        }, 2000);
                    }
                }, 100);
            } catch (e) {
                alert('Error loading template: ' + e.message);
            }
        }
        
        // Theme Toggle Function
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            html.setAttribute('data-theme', newTheme);
            
            try {
                localStorage.setItem('theme', newTheme);
            } catch (e) {
                console.log('Could not save theme preference');
            }
            
            // Update toggle button icon
            const themeBtn = document.getElementById('themeToggle');
            if (themeBtn) {
                themeBtn.innerHTML = newTheme === 'dark' ? '☀️' : '🌙';
                themeBtn.title = newTheme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode';
            }
            
            // Update inline styles
            updateInlineStyles();
        }
        
        // Function to update inline styled elements for theme
        function updateInlineStyles() {
            const root = document.documentElement;
            const cardBg = getComputedStyle(root).getPropertyValue('--card-bg').trim();
            const textPrimary = getComputedStyle(root).getPropertyValue('--text-primary').trim();
            const textSecondary = getComputedStyle(root).getPropertyValue('--text-secondary').trim();
            const textMuted = getComputedStyle(root).getPropertyValue('--text-muted').trim();
            const tableHeaderBg = getComputedStyle(root).getPropertyValue('--table-header-bg').trim();
            const inputBg = getComputedStyle(root).getPropertyValue('--input-bg').trim();
            const codeBg = getComputedStyle(root).getPropertyValue('--code-bg').trim();
            const tableBorder = getComputedStyle(root).getPropertyValue('--table-border').trim();
            const warningText = getComputedStyle(root).getPropertyValue('--warning-text').trim();
            const dangerText = getComputedStyle(root).getPropertyValue('--danger-text').trim();
            
            // Update all white backgrounds
            document.querySelectorAll('[style*="background"]').forEach(el => {
                const style = el.getAttribute('style');
                if (!style) return;
                
                let newStyle = style;
                
                // Handle various white patterns
                if ((style.includes('background: white') || style.includes('background:white') || 
                     style.includes('background:#fff') || style.includes('background: #fff')) && 
                    !style.includes('linear-gradient') && !style.includes('rgba(255,255,255')) {
                    newStyle = newStyle.replace(/background:\s*white/g, 'background:' + cardBg);
                    newStyle = newStyle.replace(/background:white/g, 'background:' + cardBg);
                    newStyle = newStyle.replace(/background:\s*#fff(?![0-9a-fA-F])/g, 'background:' + cardBg);
                }
                
                // Handle #f8f9fa, #f5f5f5 backgrounds
                newStyle = newStyle.replace(/#f8f9fa/g, tableHeaderBg);
                newStyle = newStyle.replace(/#f5f5f5/g, tableHeaderBg);
                
                // Handle #e9ecef, #dee2e6 backgrounds
                newStyle = newStyle.replace(/#e9ecef/g, tableBorder);
                newStyle = newStyle.replace(/#dee2e6/g, tableBorder);
                
                // Handle #1e1e1e code backgrounds
                newStyle = newStyle.replace(/background:\s*#1e1e1e/g, 'background:' + codeBg);
                
                // Handle #fff3cd warning backgrounds
                if (style.includes('#fff3cd')) {
                    const warnBg = getComputedStyle(root).getPropertyValue('--warning-bg').trim();
                    newStyle = newStyle.replace(/#fff3cd/g, warnBg);
                }
                
                if (newStyle !== style) {
                    el.setAttribute('style', newStyle);
                }
            });
            
            // Update all text colors - comprehensive
            document.querySelectorAll('[style*="color"]').forEach(el => {
                const style = el.getAttribute('style');
                if (!style) return;
                
                let newStyle = style;
                
                // Primary text colors (#333, #000)
                newStyle = newStyle.replace(/color:\s*#333(?![0-9a-fA-F])/g, 'color:' + textPrimary);
                newStyle = newStyle.replace(/color:#333(?![0-9a-fA-F])/g, 'color:' + textPrimary);
                
                // Secondary text colors (#666, #555, #495057)
                newStyle = newStyle.replace(/color:\s*#666(?![0-9a-fA-F])/g, 'color:' + textSecondary);
                newStyle = newStyle.replace(/color:#666(?![0-9a-fA-F])/g, 'color:' + textSecondary);
                newStyle = newStyle.replace(/color:\s*#555(?![0-9a-fA-F])/g, 'color:' + textSecondary);
                newStyle = newStyle.replace(/color:#555(?![0-9a-fA-F])/g, 'color:' + textSecondary);
                newStyle = newStyle.replace(/color:\s*#495057/g, 'color:' + textSecondary);
                newStyle = newStyle.replace(/color:#495057/g, 'color:' + textSecondary);
                
                // Muted text colors (#6c757d, #999, #808080)
                newStyle = newStyle.replace(/color:\s*#6c757d/g, 'color:' + textMuted);
                newStyle = newStyle.replace(/color:#6c757d/g, 'color:' + textMuted);
                newStyle = newStyle.replace(/color:\s*#999(?![0-9a-fA-F])/g, 'color:' + textMuted);
                newStyle = newStyle.replace(/color:#999(?![0-9a-fA-F])/g, 'color:' + textMuted);
                newStyle = newStyle.replace(/color:\s*#808080/g, 'color:' + textMuted);
                newStyle = newStyle.replace(/color:#808080/g, 'color:' + textMuted);
                
                // Warning/orange text colors (#856404, #ffc107, #f57c00)
                newStyle = newStyle.replace(/color:\s*#856404/g, 'color:' + warningText);
                newStyle = newStyle.replace(/color:#856404/g, 'color:' + warningText);
                newStyle = newStyle.replace(/color:\s*#ffc107/g, 'color:' + warningText);
                newStyle = newStyle.replace(/color:#ffc107/g, 'color:' + warningText);
                newStyle = newStyle.replace(/color:\s*#f57c00/g, 'color:' + warningText);
                newStyle = newStyle.replace(/color:#f57c00/g, 'color:' + warningText);
                
                // Danger/red text colors (#dc3545, #c62828, #d84315)
                newStyle = newStyle.replace(/color:\s*#dc3545/g, 'color:' + dangerText);
                newStyle = newStyle.replace(/color:#dc3545/g, 'color:' + dangerText);
                newStyle = newStyle.replace(/color:\s*#c62828/g, 'color:' + dangerText);
                newStyle = newStyle.replace(/color:#c62828/g, 'color:' + dangerText);
                newStyle = newStyle.replace(/color:\s*#d84315/g, 'color:' + dangerText);
                newStyle = newStyle.replace(/color:#d84315/g, 'color:' + dangerText);
                
                // Code editor text (#d4d4d4, #abb2bf)
                newStyle = newStyle.replace(/color:\s*#d4d4d4/g, 'color:' + textPrimary);
                newStyle = newStyle.replace(/color:#d4d4d4/g, 'color:' + textPrimary);
                newStyle = newStyle.replace(/color:\s*#abb2bf/g, 'color:' + textPrimary);
                newStyle = newStyle.replace(/color:#abb2bf/g, 'color:' + textPrimary);
                
                if (newStyle !== style) {
                    el.setAttribute('style', newStyle);
                }
            });
            
            // Update borders
            document.querySelectorAll('[style*="border"]').forEach(el => {
                const style = el.getAttribute('style');
                if (!style) return;
                
                let newStyle = style;
                
                // Border colors
                newStyle = newStyle.replace(/border:\s*2px\s+solid\s+#e0e0e0/g, 'border:2px solid ' + tableBorder);
                newStyle = newStyle.replace(/border:\s*1px\s+solid\s+#e9ecef/g, 'border:1px solid ' + tableBorder);
                newStyle = newStyle.replace(/border:\s*1px\s+solid\s+#dee2e6/g, 'border:1px solid ' + tableBorder);
                newStyle = newStyle.replace(/border:\s*2px\s+solid\s+#dee2e6/g, 'border:2px solid ' + tableBorder);
                newStyle = newStyle.replace(/border-bottom:\s*1px\s+solid\s+#e9ecef/g, 'border-bottom:1px solid ' + tableBorder);
                newStyle = newStyle.replace(/border-top:\s*1px\s+solid\s+#e9ecef/g, 'border-top:1px solid ' + tableBorder);
                
                if (newStyle !== style) {
                    el.setAttribute('style', newStyle);
                }
            });
            
            // Update code blocks
            document.querySelectorAll('pre[style*="background"], code[style*="background"]').forEach(el => {
                const style = el.getAttribute('style');
                if (!style) return;
                
                if (style.includes('#f8f9fa') || style.includes('#f5f5f5')) {
                    let newStyle = style.replace(/#f8f9fa|#f5f5f5/g, codeBg);
                    el.setAttribute('style', newStyle);
                }
                
                // Fix code text color
                if (style.includes('color: #333') || style.includes('color:#333')) {
                    let newStyle = style.replace(/color:\s*#333/g, 'color:' + textPrimary);
                    el.setAttribute('style', newStyle);
                }
            });
            
            // Update select/input backgrounds
            document.querySelectorAll('select, input[type="text"], input[type="number"], input[type="file"], textarea').forEach(el => {
                const style = el.getAttribute('style');
                if (!style) return;
                
                if (style.includes('background: white') || style.includes('background:#fff') || 
                    style.includes('background:white') || style.includes('background: #fff')) {
                    el.style.background = inputBg;
                    if (!style.includes('color:')) {
                        el.style.color = textPrimary;
                    }
                }
            });
        }
        
        // Initialize theme on page load
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
        
        document.addEventListener('DOMContentLoaded', function() {
            // Set theme toggle button icon
            const themeBtn = document.getElementById('themeToggle');
            const currentTheme = document.documentElement.getAttribute('data-theme');
            if (themeBtn) {
                themeBtn.innerHTML = currentTheme === 'dark' ? '☀️' : '🌙';
                themeBtn.title = currentTheme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode';
            }
            
            // Apply initial theme styles
            updateInlineStyles();
            
            // Restore last active tab after reloads (POST/GET). URL param overrides storage.
            const urlParams = new URLSearchParams(window.location.search);
            const tabFromUrl = urlParams.get('tab');
            let initialTab = tabFromUrl;

            if (!initialTab) {
                try {
                    initialTab = localStorage.getItem('activeTab');
                } catch (e) {
                    initialTab = null;
                }
            }

            if (initialTab) {
                const btn = document.querySelector(`.tab-btn[data-tab="${initialTab}"]`);
                const tab = document.getElementById(initialTab);
                if (btn && tab) {
                    if (tabFromUrl) {
                        try { localStorage.setItem('activeTab', initialTab); } catch (e) {}
                    }
                    switchTab(initialTab, btn);
                }
            }
            
            document.querySelectorAll('pre code').forEach((block) => {
                delete block.dataset.highlighted;
                hljs.highlightElement(block);
            });
            
            // Add smooth scroll behavior
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
            
            // Add loading animation for forms
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.dataset.noLoading) {
                        submitBtn.innerHTML = '⏳ Processing...';
                        submitBtn.disabled = true;
                    }
                });
            });
        });
        
        // Missing utility functions
        function resetFilters() {
            window.location.href = window.location.pathname + '?' + new URLSearchParams({collection: new URLSearchParams(window.location.search).get('collection') || ''}).toString();
        }
        
        // autoRefreshInterval is now declared in index.php Browse Tab section
        function toggleAutoRefresh() {
            const btn = document.getElementById('autoRefreshBtn');
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
                btn.textContent = '🔄 Auto Refresh: OFF';
                btn.style.background = '#6c757d';
            } else {
                autoRefreshInterval = setInterval(() => window.location.reload(), 5000);
                btn.textContent = '🔄 Auto Refresh: ON';
                btn.style.background = '#28a745';
            }
        }
        
        function toggleBulkSelection() {
            const bulkActions = document.getElementById('bulkActions');
            if (bulkActions) {
                bulkActions.style.display = bulkActions.style.display === 'none' ? 'flex' : 'none';
            }
        }
        
        function exportVisible() {
            const docs = [];
            document.querySelectorAll('input[name="selected_docs[]"]:checked').forEach(checkbox => {
                docs.push(checkbox.value);
            });
            if (docs.length === 0) {
                alert('No documents selected');
                return;
            }
            // Export selected documents as JSON
            const form = document.createElement('form');
            form.method = 'POST';
            const collection = new URLSearchParams(window.location.search).get('collection') || document.querySelector('input[name="collection"]')?.value || '';
            form.innerHTML = `<input type="hidden" name="collection" value="${collection}"><input type="hidden" name="action" value="export_selected"><input type="hidden" name="doc_ids" value="${docs.join(',')}">`;
            document.body.appendChild(form);
            form.submit();
        }
        
        function bulkDelete() {
            const docs = [];
            document.querySelectorAll('input[name="selected_docs[]"]:checked').forEach(checkbox => {
                docs.push(checkbox.value);
            });
            if (docs.length === 0) {
                alert('No documents selected');
                return;
            }
            if (!confirm(`Delete ${docs.length} document(s)?`)) return;
            const form = document.createElement('form');
            form.method = 'POST';
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            const collection = new URLSearchParams(window.location.search).get('collection') || document.querySelector('input[name="collection"]')?.value || '';
            form.innerHTML = `<input type="hidden" name="csrf_token" value="${csrfToken}"><input type="hidden" name="collection" value="${collection}"><input type="hidden" name="action" value="bulk_delete"><input type="hidden" name="doc_ids" value="${docs.join(',')}">`;
            document.body.appendChild(form);
            form.submit();
        }
        
        function bulkExport() {
            exportVisible();
        }
        
        function bulkUpdate() {
            const docs = [];
            document.querySelectorAll('input[name="selected_docs[]"]:checked').forEach(checkbox => {
                docs.push(checkbox.value);
            });
            if (docs.length === 0) {
                alert('No documents selected');
                return;
            }
            alert('Bulk update feature - will open update modal');
        }
        
        function clearSelection() {
            document.querySelectorAll('input[name="selected_docs[]"]:checked').forEach(checkbox => {
                checkbox.checked = false;
            });
            const selectAll = document.getElementById('selectAll');
            if (selectAll) selectAll.checked = false;
        }
        
        function toggleView() {
            const btn = document.getElementById('viewToggleBtn');
            const tableView = document.getElementById('tableView');
            const cardView = document.getElementById('cardView');
            if (tableView && cardView) {
                if (tableView.style.display === 'none') {
                    tableView.style.display = 'block';
                    cardView.style.display = 'none';
                    btn.textContent = '📇 Card View';
                } else {
                    tableView.style.display = 'none';
                    cardView.style.display = 'grid';
                    btn.textContent = '📊 Table View';
                }
            }
        }
        
        function toggleSelectAll(checkbox) {
            document.querySelectorAll('input[name="selected_docs[]"]').forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }
        
        function duplicateDoc(docId) {
            if (!confirm('Duplicate this document?')) return;
            const form = document.createElement('form');
            form.method = 'POST';
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            const collection = new URLSearchParams(window.location.search).get('collection') || document.querySelector('input[name="collection"]')?.value || '';
            form.innerHTML = `<input type="hidden" name="csrf_token" value="${csrfToken}"><input type="hidden" name="collection" value="${collection}"><input type="hidden" name="action" value="duplicate"><input type="hidden" name="doc_id" value="${docId}">`;
            document.body.appendChild(form);
            form.submit();
        }
        
        function exportSingle(docId) {
            const form = document.createElement('form');
            form.method = 'POST';
            const collection = new URLSearchParams(window.location.search).get('collection') || document.querySelector('input[name="collection"]')?.value || '';
            form.innerHTML = `<input type="hidden" name="collection" value="${collection}"><input type="hidden" name="action" value="export_single"><input type="hidden" name="doc_id" value="${docId}">`;
            document.body.appendChild(form);
            form.submit();
        }
        
        function deleteDoc(docId) {
            if (!confirm('Delete this document?')) return;
            const form = document.createElement('form');
            form.method = 'POST';
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';
            const collection = new URLSearchParams(window.location.search).get('collection') || document.querySelector('input[name="collection"]')?.value || '';
            form.innerHTML = `<input type="hidden" name="csrf_token" value="${csrfToken}"><input type="hidden" name="collection" value="${collection}"><input type="hidden" name="action" value="delete"><input type="hidden" name="doc_id" value="${docId}">`;
            document.body.appendChild(form);
            form.submit();
        }
        
        function openJsonImportModal() {
            const modal = document.getElementById('jsonImportModal');
            if (modal) modal.style.display = 'block';
        }
        
        function validateJSON() {
            const textarea = document.querySelector('textarea[name="json_data"]');
            if (!textarea) return;
            try {
                JSON.parse(textarea.value);
                alert('✓ Valid JSON!');
            } catch (e) {
                alert('✗ Invalid JSON: ' + e.message);
            }
        }
        
        function previewImportJson() {
            const textarea = document.querySelector('textarea[name="import_json"]');
            if (!textarea) return;
            try {
                const data = JSON.parse(textarea.value);
                const count = Array.isArray(data) ? data.length : 1;
                alert(`Preview: ${count} document(s) will be imported`);
            } catch (e) {
                alert('✗ Invalid JSON: ' + e.message);
            }
        }
        
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const editFromViewModal = document.getElementById('editFromViewModal');
            const viewModal = document.getElementById('viewModal');
            const jsonImportModal = document.getElementById('jsonImportModal');
            
            if (event.target === editModal) editModal.style.display = 'none';
            if (event.target === editFromViewModal) editFromViewModal.style.display = 'none';
            if (event.target === viewModal) viewModal.style.display = 'none';
            if (event.target === jsonImportModal) jsonImportModal.style.display = 'none';
        }
    </script>
</head>
<body>

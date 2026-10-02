document.addEventListener('DOMContentLoaded', function() {
    // File upload handling
    const fileInput = document.getElementById('fileInput');
    const dropArea = document.getElementById('dropArea');
    
    if (fileInput && dropArea) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        dropArea.addEventListener('drop', handleDrop, false);
        fileInput.addEventListener('change', handleFiles, false);
    }
    
    // Account tabs switching
    const accountTabs = document.querySelectorAll('.account-tab');
    if (accountTabs.length > 0) {
        accountTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Update active tab
                accountTabs.forEach(item => item.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding content
                const accountTabContents = document.querySelectorAll('.account-tab-content');
                accountTabContents.forEach(content => {
                    content.classList.remove('active');
                    if (content.id === tabId) {
                        content.classList.add('active');
                    }
                });
            });
        });
    }
    
    // Search functionality
    const accountSearch = document.getElementById('account-search');
    if (accountSearch) {
        accountSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const activeTab = document.querySelector('.account-tab.active').getAttribute('data-tab');
            const tableId = activeTab === 'pending-accounts' ? 'pending-accounts-table' : 'accepted-accounts-table';
            const rows = document.querySelectorAll(`#${tableId} tbody tr`);
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
    
    // Modal handling
    const modals = document.querySelectorAll('.modal');
    
    if (modals.length > 0) {
        // Modal close buttons
        document.querySelectorAll('.close, .cancel-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                modals.forEach(modal => modal.classList.remove('active'));
            });
        });
        
        // Open modal buttons
        document.querySelectorAll('[data-toggle="modal"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const target = this.getAttribute('data-target');
                document.querySelector(target).classList.add('active');
            });
        });
    }
    
    // Helper functions for file upload
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    function highlight() {
        dropArea.classList.add('highlight');
    }
    
    function unhighlight() {
        dropArea.classList.remove('highlight');
    }
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles({ target: { files } });
    }
    
    function handleFiles(e) {
        const fileInfo = document.getElementById('fileInfo');
        if (!fileInfo) return;
        
        const files = e.target.files;
        if (files.length) {
            const file = files[0];
            if (file.type === 'application/pdf') {
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileSize').textContent = formatFileSize(file.size);
                fileInfo.style.display = 'block';
            } else {
                alert('Please select a PDF file.');
            }
        }
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2) + ' ' + sizes[i]);
    }
});
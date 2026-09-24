/**
 * Task Files Manager
 * Handles file upload and management for tasks
 */

// Prevent duplicate class declaration
if (typeof window.TaskFilesManager === 'undefined') {
    window.TaskFilesManager = class TaskFilesManager {
    constructor() {
        this.currentTaskId = null;
        this.uploadInProgress = false;
        this.eventsBound = false;
        this.init();
    }

    init() {
        // Don't bind events immediately - wait for task ID to be set
    }

    ensureEventsBound() {
        if (!this.eventsBound) {
            this.bindEvents();
            this.eventsBound = true;
        }
    }

    getAppRoot() {
        if (typeof window.baseUrl === 'string' && window.baseUrl.length > 0) {
            const u = window.baseUrl;
            return u.endsWith('/') ? u : (u + '/');
        }
        const currentPath = window.location.pathname || '';
        if (currentPath.includes('/admin/') || currentPath.includes('/staff/') || currentPath.includes('/client/') || currentPath.includes('/mail/')) {
            return '../';
        }
        return '';
    }

    getApiPath() {
        return this.getAppRoot();
    }

    getBaseUrl() {
        return this.getAppRoot();
    }

    bindEvents() {
        // File input change event
        document.getElementById('task-file-input').addEventListener('change', (e) => {
            this.handleFileSelect(e.target.files);
        });

        // Dropzone click event
        document.getElementById('task-file-dropzone').addEventListener('click', () => {
            document.getElementById('task-file-input').click();
        });

        // Drag and drop events
        const dropzone = document.getElementById('task-file-dropzone');
        
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.classList.add('drag-over');
        });

        dropzone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropzone.classList.remove('drag-over');
        });

        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.classList.remove('drag-over');
            this.handleFileSelect(e.dataTransfer.files);
        });
    }

    setTaskId(taskId) {
        this.currentTaskId = taskId;
        if (taskId) {
            this.ensureEventsBound(); // Bind events now that we have a task ID
            this.loadFiles();
            
            // Also update count from DOM in case files were pre-rendered
            setTimeout(() => {
                this.updateFileCount();
            }, 100);
        }
    }

    async loadFiles() {
        if (!this.currentTaskId) return;

        try {
            const apiPath = this.getApiPath();
            const response = await (window.fetchWithCsrf ? window.fetchWithCsrf : fetch)(`${apiPath}ajax/task_files.php?task_id=${this.currentTaskId}`);
            const data = await response.json();

            if (data.status === 'success') {
                this.renderFiles(data.files);
            } else {
                console.error('Error loading files:', data.message);
                this.showError('Error loading files: ' + data.message);
            }
        } catch (error) {
            console.error('Error loading files:', error);
            this.showError('Error loading files');
        }
    }

    updateFileCount() {
        const filesCountElement = document.getElementById('files-count');
        if (filesCountElement) {
            const container = document.getElementById('task-files-list');
            if (container) {
                // Count file items in the container
                const fileItems = container.querySelectorAll('.file-item');
                const count = fileItems.length;
                filesCountElement.textContent = count;
            }
        }
    }

    renderFiles(files) {
        const container = document.getElementById('task-files-list');
        
        if (files.length === 0) {
            container.innerHTML = '<div class="no-files"><i class="fa fa-folder-open"></i><p>No files uploaded yet</p></div>';
            // Update count to 0
            this.updateFileCount();
            return;
        }

        let html = '<div class="files-grid">';
        
        files.forEach(file => {
            const isImage = this.isImageFile(file.file_type);
            const fileSize = this.formatFileSize(file.file_size);
            const uploadDate = new Date(file.upload_date).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });

            // Get file icon based on both MIME type and file extension
            const fileIcon = this.getFileIconByExtension(file.original_name, file.file_type);
            const baseUrl = this.getBaseUrl();
            const genericIcon = `${baseUrl}assets/images/files/file-types/generic.png`;
            const typeIcon = `${baseUrl}assets/images/files/file-types/${fileIcon}.png`;

            let iconInner = '';
            if (isImage) {
                iconInner = `<div class="file-thumbnail-container"><a href="javascript:void(0)" class="file-thumbnail-link" onclick="previewTaskImage(${file.id}, '${this.escapeHtml(file.original_name)}', '${file.file_url}', '${file.thumb_url}')">
                                <img src="${file.thumb_url}" alt="${this.escapeHtml(file.original_name)}" class="file-thumbnail" onerror="this.src='${genericIcon}'">
                            </a></div>`;
            } else if (this.isPdfFile(file.file_type)) {
                iconInner = `<div class="file-thumbnail-container"><a href="${file.file_url}" target="_blank" class="file-thumbnail-link" rel="noopener noreferrer" onclick="window.open('${file.file_url}', '_blank'); return false;">
                                <img src="${typeIcon}" alt="${this.escapeHtml(file.original_name)}" class="file-thumbnail" onerror="this.src='${genericIcon}'">
                            </a></div>`;
            } else {
                iconInner = `<div class="file-thumbnail-container"><img src="${typeIcon}" alt="${this.escapeHtml(file.original_name)}" class="file-thumbnail" onerror="this.src='${genericIcon}'"></div>`;
            }

            html += `
                <div class="file-item file-item-file" data-id="${file.id}" data-type="file" data-file-id="${file.id}">
                    <div class="file-checkbox" style="display:none;" aria-hidden="true"></div>
                    <div class="file-icon">
                        ${iconInner}
                    </div>
                    <div class="file-info">
                        <h6 class="file-name" title="${this.escapeHtml(file.original_name)}">${this.escapeHtml(file.original_name)}</h6>
                        <div class="file-date">
                            <p class="file-meta">${fileSize}</p>
                            <p class="file-date">${uploadDate}</p>
                        </div>
                        <small class="text-muted">by ${this.escapeHtml(file.uploaded_by_name || 'Unknown')}</small>
                    </div>
                    <div class="file-actions-dropdown">
                        <div class="dropdown">
                            <button class="btn-dots dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                ${typeof tsIcon === 'function' ? tsIcon('dots-vertical', 'w-6') : ''}
                            </button>
                            <ul class="dropdown-menu" data-popper-placement="bottom-end">
                                <li>
                                    <a class="dropdown-item" href="${file.file_url}" download="${file.original_name}">
                                        ${typeof tsIcon === 'function' ? tsIcon('download', 'w-4 h-4 me-2 tasksession-timer-log-menu-ico') : ''}Download
                                    </a>
                                </li>
                                <li>
                                    ${this.isPdfFile(file.file_type) ? 
                                        `<a class="dropdown-item" href="${file.file_url}" target="_blank">
                                            ${typeof tsIcon === 'function' ? tsIcon('eye', 'w-4 h-4 me-2 tasksession-timer-log-menu-ico') : ''}Preview
                                        </a>` :
                                        isImage ?
                                        `<a class="dropdown-item" href="javascript:void(0)" onclick="previewTaskImage(${file.id}, '${this.escapeHtml(file.original_name)}', '${file.file_url}', '${file.thumb_url}')">
                                            ${typeof tsIcon === 'function' ? tsIcon('eye', 'w-4 h-4 me-2 tasksession-timer-log-menu-ico') : ''}Preview
                                        </a>` :
                                        `<a class="dropdown-item" href="${file.file_url}" target="_blank">
                                            ${typeof tsIcon === 'function' ? tsIcon('eye', 'w-4 h-4 me-2 tasksession-timer-log-menu-ico') : ''}Preview
                                        </a>`
                                    }
                                </li>
                                <li>
                                    <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="taskFilesManager.deleteFile(${file.id})">
                                        ${typeof tsIcon === 'function' ? tsIcon('delete', 'w-4 h-4 me-2 tasksession-timer-log-menu-ico') : ''}Delete
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
        // Update file count in the tab
        this.updateFileCount();
    }

    handleFileSelect(files) {
        // Events are only bound when task ID is set, so this should always be true
        if (!this.currentTaskId) {
            this.showError('No task selected. Please select a task first.');
            return;
        }

        if (this.uploadInProgress) {
            this.showError('Upload already in progress');
            return;
        }

        this.processFiles(files);
    }

    processFiles(files) {
        const validFiles = Array.from(files).filter(file => {
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['gif', 'png', 'jpg', 'jpeg', 'zip', 'pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'pptx', 'eps', 'psd', 'ai', 'fw'];
            const extension = file.name.split('.').pop().toLowerCase();
            
            if (file.size > maxSize) {
                this.showError(`File ${file.name} is too large (max 5MB)`);
                return false;
            }
            
            if (!allowedTypes.includes(extension)) {
                this.showError(`File type ${extension} is not allowed`);
                return false;
            }
            
            return true;
        });

        if (validFiles.length > 0) {
            this.uploadFiles(validFiles);
        }
    }

    async uploadFiles(files) {
        this.uploadInProgress = true;
        this.showProgress(true);

        const formData = new FormData();
        formData.append('task_id', this.currentTaskId);
        
        files.forEach(file => {
            formData.append('files[]', file);
        });

        try {
            const apiPath = this.getApiPath();
            const csrfToken = (typeof window !== 'undefined' && window.csrfToken) ? window.csrfToken : '';
            const response = await fetch(`${apiPath}ajax/task_files_upload.php`, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken
                },
                body: formData
            });

            const data = await response.json();

            if (data.status === 'success') {
                this.showSuccess(`Successfully uploaded ${data.files.length} file(s)`);
                this.loadFiles(); // Reload files list
                
                // Also reload images in the images manager
                if (typeof window.taskImagesManager !== 'undefined') {
                    window.taskImagesManager.loadTaskImages();
                }
            } else {
                this.showError('Upload failed: ' + data.message);
            }

            if (data.errors && data.errors.length > 0) {
                data.errors.forEach(error => this.showError(error));
            }

        } catch (error) {
            console.error('Upload error:', error);
            this.showError('Upload failed: ' + error.message);
        } finally {
            this.uploadInProgress = false;
            this.showProgress(false);
        }
    }

    async deleteFile(fileId) {
        if (!confirm('Are you sure you want to delete this file?')) {
            return;
        }

        try {
            const apiPath = this.getApiPath();
            const csrfToken = (typeof window !== 'undefined' && window.csrfToken) ? window.csrfToken : '';
            const response = await (window.fetchWithCsrf ? window.fetchWithCsrf : fetch)(`${apiPath}ajax/task_files.php?task_id=${this.currentTaskId}&file_id=${fileId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-Token': csrfToken
                }
            });

            const data = await response.json();

            if (data.status === 'success') {
                this.showSuccess('File deleted successfully');
                this.loadFiles(); // Reload files list
            } else {
                this.showError('Delete failed: ' + data.message);
            }

        } catch (error) {
            console.error('Delete error:', error);
            this.showError('Delete failed: ' + error.message);
        }
    }

    showProgress(show) {
        const progress = document.getElementById('upload-progress');
        if (show) {
            progress.style.display = 'block';
        } else {
            progress.style.display = 'none';
        }
    }

    showSuccess(message) {
        // You can implement a toast notification here
        console.log('Success:', message);
    }

    showError(message) {
        // You can implement a toast notification here
        console.error('Error:', message);
        alert(message); // Temporary - replace with proper notification
    }

    isImageFile(fileType) {
        return fileType.startsWith('image/');
    }

    isPdfFile(fileType) {
        return fileType.includes('pdf') || fileType === 'application/pdf';
    }

    getFileIcon(fileType) {
        // Convert to lowercase for consistent matching
        const type = fileType.toLowerCase();
        
        // Design files (check these first as they might be detected as generic)
        if (type.includes('photoshop') || type.includes('psd') || type === 'image/vnd.adobe.photoshop') return 'photoshop';
        if (type.includes('illustrator') || type.includes('ai') || type === 'application/postscript') return 'illustrator';
        if (type.includes('fireworks') || type.includes('fw')) return 'fireworks';
        
        // Image files (but not design files)
        if (type.startsWith('image/') && !type.includes('photoshop') && !type.includes('psd')) return 'generic';
        
        // Document files
        if (type.includes('pdf')) return 'pdf';
        if (type.includes('word') || type.includes('document') || type.includes('doc')) return 'word';
        if (type.includes('excel') || type.includes('spreadsheet') || type.includes('xls')) return 'excel';
        if (type.includes('powerpoint') || type.includes('presentation') || type.includes('ppt')) return 'powerpoint';
        if (type.includes('text') || type.includes('txt')) return 'text';
        
        // Archive files
        if (type.includes('zip') || type.includes('archive') || type.includes('rar') || type.includes('7z')) return 'zip';
        
        // Presentation files
        if (type.includes('keynote')) return 'keynote';
        
        // Media files
        if (type.includes('movie') || type.includes('video') || type.includes('mp4') || type.includes('avi') || type.includes('mov')) return 'movie';
        if (type.includes('music') || type.includes('audio') || type.includes('mp3') || type.includes('wav')) return 'music';
        
        // Default fallback
        return 'generic';
    }

    getFileIconByExtension(fileName, fileType) {
        // First try MIME type detection
        const mimeIcon = this.getFileIcon(fileType);
        if (mimeIcon !== 'generic') {
            return mimeIcon;
        }
        
        // If MIME type detection fails, check file extension
        const extension = fileName.split('.').pop().toLowerCase();
        
        // Design files by extension
        if (extension === 'psd') return 'photoshop';
        if (extension === 'ai') return 'illustrator';
        if (extension === 'fw') return 'fireworks';
        
        // Document files by extension
        if (extension === 'pdf') return 'pdf';
        if (extension === 'doc' || extension === 'docx') return 'word';
        if (extension === 'xls' || extension === 'xlsx') return 'excel';
        if (extension === 'ppt' || extension === 'pptx') return 'powerpoint';
        if (extension === 'txt') return 'text';
        
        // Archive files by extension
        if (extension === 'zip' || extension === 'rar' || extension === '7z') return 'zip';
        
        // Media files by extension
        if (extension === 'mp4' || extension === 'avi' || extension === 'mov' || extension === 'wmv') return 'movie';
        if (extension === 'mp3' || extension === 'wav' || extension === 'flac') return 'music';
        
        // Default fallback
        return 'generic';
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    };
}

// Initialize the task files manager
window.taskFilesManager = new window.TaskFilesManager();

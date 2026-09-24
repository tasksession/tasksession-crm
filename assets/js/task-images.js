/**
 * Task Images Manager
 * Handles image preview, lightbox, and navigation for task files
 * Based on media vault actions.js functionality
 */

// Prevent duplicate class declaration
if (typeof window.TaskImagesManager === 'undefined') {
    window.TaskImagesManager = class TaskImagesManager {
        constructor() {
            this.currentTaskId = null;
            this.imageList = [];
            this.currentImageIndex = 0;
            this.previewModalOpen = false;
            this.init();
        }

        init() {
            this.bindEvents();
        }

        getBaseUrl() {
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

        bindEvents() {
            // Close on Escape key and add navigation
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeImageLightbox();
                } else if (e.key === 'ArrowLeft') {
                    e.preventDefault();
                    this.navigateImage('prev');
                } else if (e.key === 'ArrowRight') {
                    e.preventDefault();
                    this.navigateImage('next');
                }
            });
        }

        setTaskId(taskId) {
            this.currentTaskId = taskId;
            if (taskId) {
                this.loadTaskImages();
                // Also update the task files manager with the same task ID
                if (typeof window.taskFilesManager !== 'undefined') {
                    window.taskFilesManager.setTaskId(taskId);
                }
            }
        }

        async loadTaskImages() {
            if (!this.currentTaskId) return;

            try {
                const apiPath = this.getBaseUrl();
                const response = await fetch(`${apiPath}ajax/task_files.php?task_id=${this.currentTaskId}`);
                const data = await response.json();

                if (data.status === 'success') {
                    // Filter only image files and update the image list
                    this.imageList = data.files.filter(file => this.isImageFile(file.file_type));
                } else {
                }
            } catch (error) {
                // Error loading images
            }
        }

        isImageFile(fileType) {
            return fileType.startsWith('image/');
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Preview task image function
         */
        previewTaskImage(fileId, fileName, fileUrl, thumbUrl) {
            // Prevent multiple modals from opening
            if (this.previewModalOpen) {
                return;
            }

            // Find the image in our list
            const imageData = this.imageList.find(img => img.id == fileId);
            if (!imageData) {
                // Try to reload the image list and try again
                this.loadTaskImages().then(() => {
                    const retryImageData = this.imageList.find(img => img.id == fileId);
                    if (retryImageData) {
                        this.currentImageIndex = this.imageList.findIndex(img => img.id == fileId);
                        this.showImageLightbox(fileUrl, fileName, fileId, fileUrl);
                    } else {
                        alert('Image information not found. Please try again.');
                    }
                });
                return;
            }

            // Find current index
            this.currentImageIndex = this.imageList.findIndex(img => img.id == fileId);
            
            // Use full image URL instead of thumbnail
            this.showImageLightbox(fileUrl, fileName, fileId, fileUrl);
        }

        /**
         * Show image lightbox
         */
        showImageLightbox(imageSrc, fileName, fileId, thumbUrl) {
            // Set flag to prevent multiple modals
            this.previewModalOpen = true;

            // Create lightbox modal using the same structure as media vault
            const lightbox = document.createElement('div');
            lightbox.id = 'task-lightbox';
            lightbox.className = 'lightbox';
            lightbox.style.display = 'block';
            lightbox.style.position = 'fixed';
            lightbox.style.top = '0';
            lightbox.style.left = '0';
            lightbox.style.width = '100%';
            lightbox.style.height = '100%';
            lightbox.style.zIndex = '9999';
            lightbox.style.backgroundColor = 'rgba(0,0,0,0.9)';

            lightbox.innerHTML = `
                <div class="lb-top-actions">
                    <a class="lb-download" href="${imageSrc}" download="${fileName}" title="Download Full Size Image">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"></path>
                            <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"></path>
                        </svg>
                    </a>
                    <a class="lb-close" href="#" title="Close" onclick="event.stopPropagation(); event.preventDefault(); taskImagesManager.closeImageLightbox(); return false;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"></path>
                        </svg>
                    </a>
                </div>
                <div class="lb-nav-arrows" style="display: ${this.imageList.length > 1 ? 'flex' : 'none'};">
                    <button class="lb-nav-prev" title="Previous Image" onclick="taskImagesManager.navigateImage('prev')">
                        ‹
                    </button>
                    <button class="lb-nav-next" title="Next Image" onclick="taskImagesManager.navigateImage('next')">
                        ›
                    </button>
                </div>
                <div class="lb-outerContainer gdrive-outer">
                    <div class="lb-container">
                        <img class="lb-image" src="${thumbUrl}" alt="${fileName}">
                        <div class="lb-nav" style="display: block;">
                            <a class="lb-prev" href="" style="display: none;"></a>
                            <a class="lb-next" href="" style="display: none;"></a>
                        </div>
                        <div class="lb-loader" style="display: none;">
                            <a class="lb-cancel"></a>
                        </div>
                    </div>
                </div>
                <div class="lb-dataContainer">
                    <div class="lb-data">
                        <div class="lb-details">
                            <span class="lb-caption" style="display: none;"></span>
                            <span class="lb-number" style="display: none;"></span>
                        </div>
                    </div>
                </div>
                <div class="lb-zoom-controls">
                    <button class="lb-zoom-out" title="Zoom Out">
                        −
                    </button>
                    <button class="lb-zoom-reset" title="Reset Zoom">
                        1:1
                    </button>
                    <button class="lb-zoom-in" title="Zoom In">
                        +
                    </button>
                </div>
            `;

            document.body.appendChild(lightbox);

            // Show loading for initial image
            this.showImageLoading();

            // Set up image loading handlers for initial image
            const img = lightbox.querySelector('.lb-image');
            img.onload = function() {
                taskImagesManager.hideImageLoading();
                img.style.opacity = '1';
                taskImagesManager.enableNavigationButtons();
            };

            img.onerror = function() {
                taskImagesManager.hideImageLoading();
                img.style.opacity = '0.5';
                taskImagesManager.enableNavigationButtons();
            };

            // Start with image hidden
            img.style.opacity = '0';
            img.style.transition = 'opacity 0.3s ease';
            
            // Use full image URL instead of thumbnail
            img.src = imageSrc;

            // Prevent body scroll
            document.body.style.overflow = 'hidden';

            // Add click handler to close lightbox when clicking on background
            lightbox.addEventListener('click', (e) => {
                if (e.target === lightbox) {
                    this.closeImageLightbox();
                }
            });

            // Prevent lightbox from closing when clicking on image container
            const outerContainer = lightbox.querySelector('.lb-outerContainer');
            outerContainer.addEventListener('click', (e) => {
                e.stopPropagation();
            });

            // Add zoom functionality
            this.setupZoomControls(lightbox);
        }

        /**
         * Close image lightbox
         */
        closeImageLightbox(event) {
            const lightbox = document.querySelector('#task-lightbox');
            if (lightbox) {
                // Prevent event bubbling if event is provided
                if (event) {
                    event.stopPropagation();
                    event.preventDefault();
                }
                
                lightbox.remove();
                document.body.style.overflow = '';
                // Clear flag to allow new modals
                this.previewModalOpen = false;
            }
        }

        /**
         * Navigate to previous or next image
         */
        navigateImage(direction) {
            if (!this.imageList || this.imageList.length <= 1) return;

            // Check if already loading
            const lightbox = document.getElementById('task-lightbox');
            if (lightbox && lightbox.querySelector('.lb-loading-indicator') && lightbox.querySelector('.lb-loading-indicator').style.display === 'block') {
                return; // Don't navigate while loading
            }

            let newIndex = this.currentImageIndex;

            if (direction === 'prev') {
                newIndex = this.currentImageIndex === 0 ? this.imageList.length - 1 : this.currentImageIndex - 1;
            } else if (direction === 'next') {
                newIndex = (this.currentImageIndex + 1) % this.imageList.length;
            }

            if (newIndex !== this.currentImageIndex) {
                const newImage = this.imageList[newIndex];
                this.currentImageIndex = newIndex;

                // Disable navigation buttons during loading
                this.disableNavigationButtons();

                // Update the lightbox with new image
                this.updateLightboxImage(newImage);
            }
        }

        /**
         * Update the lightbox with a new image
         */
        updateLightboxImage(imageData) {
            const lightbox = document.getElementById('task-lightbox');
            if (!lightbox) return;

            const img = lightbox.querySelector('.lb-image');
            const downloadLink = lightbox.querySelector('.lb-download');
            const outerContainer = lightbox.querySelector('.lb-outerContainer');

            // Show loading state
            this.showImageLoading();

            // Set up image loading handlers
            img.onload = function() {
                taskImagesManager.hideImageLoading();
                img.style.opacity = '1';
                taskImagesManager.enableNavigationButtons();
            };

            img.onerror = function() {
                taskImagesManager.hideImageLoading();
                // Show error state
                img.style.opacity = '0.5';
                taskImagesManager.enableNavigationButtons();
            };

            // Start with image hidden
            img.style.opacity = '0';
            img.style.transition = 'opacity 0.3s ease';

            // Update image source (this will trigger onload/onerror)
            img.src = imageData.file_url;
            img.alt = imageData.original_name;

            // Update download link
            downloadLink.href = imageData.file_url;
            downloadLink.download = imageData.original_name;

            // Reset zoom
            if (outerContainer) {
                outerContainer.style.transform = 'translate(-50%, -50%) scale(1)';
            }
        }

        /**
         * Setup zoom controls
         */
        setupZoomControls(lightbox) {
            const outerContainer = lightbox.querySelector('.lb-outerContainer');
            const zoomInBtn = lightbox.querySelector('.lb-zoom-in');
            const zoomOutBtn = lightbox.querySelector('.lb-zoom-out');
            const zoomResetBtn = lightbox.querySelector('.lb-zoom-reset');

            let currentZoom = 1;
            const minZoom = 0.1;
            const maxZoom = 5;
            const zoomStep = 0.2;

            // Drag functionality variables
            let isDragging = false;
            let dragStart = { x: 0, y: 0 };
            let dragOffset = { x: 0, y: 0 };
            let currentTransform = { x: 0, y: 0 };

            // Update transform function
            function updateTransform() {
                outerContainer.style.transform = `translate(calc(-50% + ${currentTransform.x}px), calc(-50% + ${currentTransform.y}px)) scale(${currentZoom})`;
            }

            // Zoom in function
            zoomInBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (currentZoom < maxZoom) {
                    currentZoom = Math.min(currentZoom + zoomStep, maxZoom);
                    updateTransform();
                    outerContainer.style.transition = 'transform 0.2s ease';
                }
            });

            // Zoom out function
            zoomOutBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (currentZoom > minZoom) {
                    currentZoom = Math.max(currentZoom - zoomStep, minZoom);
                    updateTransform();
                    outerContainer.style.transition = 'transform 0.2s ease';
                }
            });

            // Reset zoom function
            zoomResetBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                currentZoom = 1;
                currentTransform = { x: 0, y: 0 };
                updateTransform();
                outerContainer.style.transition = 'transform 0.2s ease';
            });

            // Mouse wheel zoom
            lightbox.addEventListener('wheel', function(e) {
                e.preventDefault();
                if (e.deltaY < 0) {
                    // Zoom in
                    if (currentZoom < maxZoom) {
                        currentZoom = Math.min(currentZoom + zoomStep, maxZoom);
                        updateTransform();
                        outerContainer.style.transition = 'transform 0.2s ease';
                    }
                } else {
                    // Zoom out
                    if (currentZoom > minZoom) {
                        currentZoom = Math.max(currentZoom - zoomStep, minZoom);
                        updateTransform();
                        outerContainer.style.transition = 'transform 0.2s ease';
                    }
                }
            });

            // Drag functionality
            function handleDragStart(e) {
                if (currentZoom > 1) {
                    isDragging = true;
                    dragStart.x = e.clientX;
                    dragStart.y = e.clientY;
                    dragOffset.x = currentTransform.x;
                    dragOffset.y = currentTransform.y;
                    outerContainer.style.cursor = 'grabbing';
                    outerContainer.style.transition = 'none';
                    e.preventDefault();
                    e.stopPropagation();
                }
            }

            outerContainer.addEventListener('mousedown', handleDragStart);

            document.addEventListener('mousemove', function(e) {
                if (isDragging && currentZoom > 1) {
                    const deltaX = e.clientX - dragStart.x;
                    const deltaY = e.clientY - dragStart.y;
                    currentTransform.x = dragOffset.x + deltaX;
                    currentTransform.y = dragOffset.y + deltaY;
                    updateTransform();
                    e.preventDefault();
                }
            });

            document.addEventListener('mouseup', function() {
                if (isDragging) {
                    isDragging = false;
                    outerContainer.style.cursor = currentZoom > 1 ? 'grab' : 'default';
                    outerContainer.style.transition = 'transform 0.2s ease';
                }
            });
        }

        /**
         * Disable navigation buttons during loading
         */
        disableNavigationButtons() {
            const lightbox = document.getElementById('task-lightbox');
            if (!lightbox) return;

            const prevBtn = lightbox.querySelector('.lb-nav-prev');
            const nextBtn = lightbox.querySelector('.lb-nav-next');

            if (prevBtn) {
                prevBtn.disabled = true;
                prevBtn.style.opacity = '0.5';
                prevBtn.style.cursor = 'not-allowed';
            }

            if (nextBtn) {
                nextBtn.disabled = true;
                nextBtn.style.opacity = '0.5';
                nextBtn.style.cursor = 'not-allowed';
            }
        }

        /**
         * Enable navigation buttons after loading
         */
        enableNavigationButtons() {
            const lightbox = document.getElementById('task-lightbox');
            if (!lightbox) return;

            const prevBtn = lightbox.querySelector('.lb-nav-prev');
            const nextBtn = lightbox.querySelector('.lb-nav-next');

            if (prevBtn) {
                prevBtn.disabled = false;
                prevBtn.style.opacity = '1';
                prevBtn.style.cursor = 'pointer';
            }

            if (nextBtn) {
                nextBtn.disabled = false;
                nextBtn.style.opacity = '1';
                nextBtn.style.cursor = 'pointer';
            }
        }

        /**
         * Show loading indicator
         */
        showImageLoading() {
            const lightbox = document.getElementById('task-lightbox');
            if (!lightbox) return;

            // Create or get loading indicator
            let loadingDiv = lightbox.querySelector('.lb-loading-indicator');
            if (!loadingDiv) {
                loadingDiv = document.createElement('div');
                loadingDiv.className = 'lb-loading-indicator';
                loadingDiv.style.cssText = `
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    z-index: 10002;
                    text-align: center;
                    color: white;
                `;
                loadingDiv.innerHTML = `
                    <div style="margin-bottom: 10px;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 32px; height: 32px; animation: spin 1s linear infinite;">
                           <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </div>
                    <div style="font-size: 14px; color: #ccc;">Loading image...</div>
                `;
                lightbox.appendChild(loadingDiv);

                // Add spin animation if not already added
                if (!document.querySelector('#loading-spin-style')) {
                    const style = document.createElement('style');
                    style.id = 'loading-spin-style';
                    style.textContent = `
                        @keyframes spin {
                            from { transform: rotate(0deg); }
                            to { transform: rotate(360deg); }
                        }
                    `;
                    document.head.appendChild(style);
                }
            }

            loadingDiv.style.display = 'block';
        }

        /**
         * Hide loading indicator
         */
        hideImageLoading() {
            const lightbox = document.getElementById('task-lightbox');
            if (!lightbox) return;

            const loadingDiv = lightbox.querySelector('.lb-loading-indicator');
            if (loadingDiv) {
                loadingDiv.style.display = 'none';
            }
        }
    };
}

// Initialize the task images manager
window.taskImagesManager = new window.TaskImagesManager();

// Global function for task files to call
function previewTaskImage(fileId, fileName, fileUrl, thumbUrl) {
    if (window.taskImagesManager) {
        window.taskImagesManager.previewTaskImage(fileId, fileName, fileUrl, thumbUrl);
    }
}

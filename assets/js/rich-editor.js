/**
 * Lightweight Rich Text Editor
 */

class RichEditor {
    constructor(selector, options = {}) {
        this.selector = selector;
        const publicShareToken = (typeof window !== 'undefined' && window.PRIVATE_NOTE_PUBLIC && window.PRIVATE_NOTE_PUBLIC.token)
            ? String(window.PRIVATE_NOTE_PUBLIC.token).trim()
            : '';
        const defaultEnableImageReplace = !publicShareToken;
        this.options = {
            height: options.height || 300,
            placeholder: options.placeholder || 'Start typing...',
            toolbar: options.toolbar || ['undo', 'redo', '|', 'styles', 'bold', 'italic', 'underline', '|', 'align-left', 'align-center', 'align-right', 'align-justify', '|', 'more'],
            extendedToolbar: !!options.extendedToolbar,
            uploadUrl: options.uploadUrl || '',
            maxUploadSizeMb: Number(options.maxUploadSizeMb || 8),
            enableImageReplace: options.enableImageReplace !== undefined ? !!options.enableImageReplace : defaultEnableImageReplace
        };
        
        this.textarea = typeof selector === 'string'
            ? document.querySelector(selector)
            : selector;
        if (!this.textarea) return;
        
        // Check if editor is already initialized
        if (this.textarea.dataset.richEditorInitialized === 'true') {
            console.log('Rich editor already initialized for:', selector);
            return;
        }
        
        // Check if there's already an editor container
        const existingContainer = this.textarea.parentNode
            ? this.textarea.parentNode.querySelector('.rich-editor-container')
            : null;
        if (existingContainer) {
            console.log('Rich editor container already exists for:', selector);
            return;
        }
        
        this.isSourceMode = false;
        this._savedEditorRange = null;
        this.init();
    }
    
    init() {
        // Mark as initialized
        this.textarea.dataset.richEditorInitialized = 'true';
        this.textarea._richEditorInstance = this;
        if (!window.RichEditorRegistry) {
            window.RichEditorRegistry = {};
        }
        if (this.textarea.id) {
            window.RichEditorRegistry[this.textarea.id] = this;
        }
        if (this.textarea.name) {
            window.RichEditorRegistry['name:' + this.textarea.name] = this;
        }
        
        // Create editor container
        this.createEditorContainer();
        
        // Create toolbar
        this.createToolbar();
        
        // Create content area
        this.createContentArea();
        
        // Create source code textarea
        this.createSourceTextarea();
        
        // Bind events
        this.bindEvents();
        
        if (this.options.extendedToolbar) {
            this.setupImageFloatingToolbar();
        }
        
        // Set initial content
        this.setContent(this.textarea.value);
    }
    
    createEditorContainer() {
        // Hide original textarea
        this.textarea.style.display = 'none';
        
        // Create editor wrapper
        this.editorContainer = document.createElement('div');
        this.editorContainer.className = 'rich-editor-container';
        
        // Insert after textarea
        this.textarea.parentNode.insertBefore(this.editorContainer, this.textarea.nextSibling);

        this.mediaInput = document.createElement('input');
        this.mediaInput.type = 'file';
        this.mediaInput.accept = 'image/*';
        this.mediaInput.style.display = 'none';
        this.editorContainer.appendChild(this.mediaInput);
    }
    
    createToolbar() {
        this.toolbar = document.createElement('div');
        this.toolbar.className = 'rich-editor-toolbar';
        
        // Create toolbar buttons
        this.createToolbarButtons();
        
        this.editorContainer.appendChild(this.toolbar);
    }
    
    createToolbarButtons() {
        const buttonConfigs = [
            { id: 'undo', icon: this.getUndoIcon(), title: 'Undo', command: 'undo' },
            { id: 'redo', icon: this.getRedoIcon(), title: 'Redo', command: 'redo' },
            { id: 'separator1', type: 'separator' },
            { id: 'styles', icon: this.getParagraphIcon(), title: 'Paragraph', type: 'dropdown', items: [
                { text: 'Paragraph', command: 'formatBlock', value: 'p' },
                { text: 'Heading 1', command: 'formatBlock', value: 'h1' },
                { text: 'Heading 2', command: 'formatBlock', value: 'h2' },
                { text: 'Heading 3', command: 'formatBlock', value: 'h3' }
            ]},
            { id: 'bold', icon: this.getBoldIcon(), title: 'Bold', command: 'bold' },
            { id: 'italic', icon: this.getItalicIcon(), title: 'Italic', command: 'italic' },
            { id: 'underline', icon: this.getUnderlineIcon(), title: 'Underline', command: 'underline' },
            ...(this.options.extendedToolbar ? [{ id: 'strikethrough', icon: this.getStrikethroughIcon(), title: 'Strikethrough', command: 'strikeThrough' }] : []),
            ...(this.options.extendedToolbar ? [{ id: 'clear-format', icon: this.getClearFormattingIcon(), title: 'Clear formatting', command: 'removeFormat' }] : []),
            { id: 'separator2', type: 'separator' },
            ...(this.options.extendedToolbar ? [{
                id: 'font-size',
                icon: this.getFontSizeIcon(),
                title: 'Font Size',
                type: 'dropdown',
                items: [
                    { text: 'Small', command: 'fontSize', value: '2' },
                    { text: 'Normal', command: 'fontSize', value: '3' },
                    { text: 'Large', command: 'fontSize', value: '4' },
                    { text: 'X-Large', command: 'fontSize', value: '5' }
                ]
            }] : []),
            ...(this.options.extendedToolbar ? [{
                id: 'colors',
                icon: this.getColorIcon(),
                title: 'Text/Highlight Color',
                type: 'dropdown',
                items: [
                    { text: 'Text: Black', command: 'foreColor', value: '#111827', previewColor: '#111827' },
                    { text: 'Text: Blue', command: 'foreColor', value: '#2563eb', previewColor: '#2563eb' },
                    { text: 'Text: Green', command: 'foreColor', value: '#059669', previewColor: '#059669' },
                    { text: 'Text: Orange', command: 'foreColor', value: '#ea580c', previewColor: '#ea580c' },
                    { text: 'Text: Red', command: 'foreColor', value: '#dc2626', previewColor: '#dc2626' },
                    { text: 'Highlight: None', command: 'hiliteColor', value: 'transparent', previewBgColor: 'transparent' },
                    { text: 'Highlight: Yellow', command: 'hiliteColor', value: '#fef08a', previewBgColor: '#fef08a' },
                    { text: 'Highlight: Green', command: 'hiliteColor', value: '#bbf7d0', previewBgColor: '#bbf7d0' },
                    { text: 'Highlight: Blue', command: 'hiliteColor', value: '#bfdbfe', previewBgColor: '#bfdbfe' },
                    { text: 'Highlight: Pink', command: 'hiliteColor', value: '#fbcfe8', previewBgColor: '#fbcfe8' }
                ]
            }] : []),
            { id: 'align-left', icon: this.getAlignLeftIcon(), title: 'Align Left', command: 'justifyLeft' },
            { id: 'align-center', icon: this.getAlignCenterIcon(), title: 'Align Center', command: 'justifyCenter' },
            { id: 'align-right', icon: this.getAlignRightIcon(), title: 'Align Right', command: 'justifyRight' },
            { id: 'align-justify', icon: this.getAlignJustifyIcon(), title: 'Justify', command: 'justifyFull' },
            { id: 'separator3', type: 'separator' },
            { id: 'bullet-list', icon: this.getBulletListIcon(), title: 'Bullet List', command: 'insertUnorderedList' },
            { id: 'numbered-list', icon: this.getNumberedListIcon(), title: 'Numbered List', command: 'insertOrderedList' },
            ...(this.options.extendedToolbar ? [{ id: 'insert-image', icon: this.getImageIcon(), title: 'Insert image', command: 'insertImage' }] : []),
            { id: 'separator4', type: 'separator' },
            { id: 'more', icon: this.getMoreIcon(), title: 'More Options', type: 'dropdown', items: [
                { text: 'Link', command: 'createLink' },
                { text: 'Clear Formatting', command: 'removeFormat' },
                { text: 'Clean selection (inline CSS)', command: 'removeInlineCss' },
                { text: 'Source Code', command: 'toggleSource', icon: this.getSourceCodeIcon() }
            ]}
        ];
        
        buttonConfigs.forEach(config => {
            if (config.type === 'separator') {
                const separator = document.createElement('div');
                separator.className = 'rich-editor-separator';
                this.toolbar.appendChild(separator);
            } else if (config.type === 'dropdown') {
                this.createDropdownButton(config);
            } else {
                this.createButton(config);
            }
        });
        this.bindToolbarFloatingDropdownSupport();
    }

    _toolbarNeedsFloatingDropdowns() {
        const toolbar = this.toolbar;
        if (!toolbar) return false;
        if (typeof toolbar.closest === 'function' && toolbar.closest('.profile-doc')) {
            return true;
        }
        if (typeof window.matchMedia === 'function' && window.matchMedia('(max-width: 767px)').matches) {
            return true;
        }
        return toolbar.scrollWidth > toolbar.clientWidth + 2;
    }

    _clearToolbarDropdownPosition(dropdown) {
        if (!dropdown) return;
        dropdown.classList.remove('rich-editor-dropdown--floating', 'rich-editor-dropdown--open');
        ['position', 'top', 'left', 'right', 'width', 'maxHeight', 'overflowY', 'zIndex', 'boxSizing'].forEach((prop) => {
            dropdown.style[prop] = '';
        });
    }

    _positionToolbarDropdown(dropdown, anchorBtn) {
        const toolbar = this.toolbar;
        if (!toolbar || !dropdown || !anchorBtn) return;
        if (!this._toolbarNeedsFloatingDropdowns()) {
            this._clearToolbarDropdownPosition(dropdown);
            return;
        }
        const rect = anchorBtn.getBoundingClientRect();
        const margin = 8;
        dropdown.classList.add('rich-editor-dropdown--floating');
        requestAnimationFrame(() => {
            const measured = dropdown.offsetWidth || 250;
            const w = Math.min(Math.max(measured, 200), window.innerWidth - margin * 2);
            let left = rect.left;
            if (left + w > window.innerWidth - margin) {
                left = window.innerWidth - margin - w;
            }
            if (left < margin) {
                left = margin;
            }
            dropdown.style.position = 'fixed';
            dropdown.style.top = (rect.bottom + 4) + 'px';
            dropdown.style.left = left + 'px';
            dropdown.style.right = 'auto';
            dropdown.style.width = w + 'px';
            dropdown.style.zIndex = '10050';
            dropdown.style.maxHeight = 'min(70vh, 380px)';
            dropdown.style.overflowY = 'auto';
            dropdown.style.boxSizing = 'border-box';
        });
    }

    bindToolbarFloatingDropdownSupport() {
        if (this._toolbarFloatUiBound) return;
        this._toolbarFloatUiBound = true;
        const toolbar = this.toolbar;
        if (!toolbar) return;
        const reposition = () => {
            const open = toolbar.querySelector('.rich-editor-dropdown.rich-editor-dropdown--open');
            if (!open) return;
            const wrap = open.closest('.rich-editor-dropdown-container');
            const btn = wrap && wrap.querySelector('button.rich-editor-dropdown-btn');
            if (btn) {
                this._positionToolbarDropdown(open, btn);
            }
        };
        toolbar.addEventListener('scroll', reposition, { passive: true });
        window.addEventListener('resize', reposition, { passive: true });
    }
    
    createButton(config) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'rich-editor-btn';
        button.innerHTML = config.icon;
        button.title = config.title;
        
        // Save caret/selection before focus moves to the toolbar (otherwise execCommand hits wrong block)
        button.addEventListener('mousedown', () => {
            this.saveEditorSelection();
        });
        
        button.addEventListener('click', (e) => {
            e.preventDefault();
            this.executeCommand(config.command);
        });
        
        this.toolbar.appendChild(button);
    }
    
    createDropdownButton(config) {
        const dropdownContainer = document.createElement('div');
        dropdownContainer.className = 'rich-editor-dropdown-container';
        
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'rich-editor-btn dropdown-btn';
        button.innerHTML = config.icon;
        button.title = config.title;
        button.classList.add('rich-editor-dropdown-btn');
        
        const dropdown = document.createElement('div');
        dropdown.className = 'rich-editor-dropdown';
        if (config.id === 'styles') {
            dropdown.classList.add('rich-editor-dropdown-paragraph');
        }
        
        // Add dropdown items
        if (config.items) {
            config.items.forEach(item => {
                const itemElement = document.createElement('div');
                itemElement.className = 'rich-editor-dropdown-item';
                
                // Create text span
                const textSpan = document.createElement('span');
                textSpan.textContent = item.text;
                itemElement.appendChild(textSpan);

                if (item.previewColor || item.previewBgColor) {
                    const preview = document.createElement('span');
                    preview.className = 'rich-editor-dropdown-color-preview';
                    preview.style.border = '1px solid #d1d5db';
                    preview.style.backgroundColor = item.previewBgColor || 'transparent';
                    if (item.previewColor) {
                        preview.style.color = item.previewColor;
                        preview.textContent = 'A';
                    }
                    itemElement.appendChild(preview);
                }
                
                // Add right arrow for submenu items or icon for source code
                if (item.text === 'Paragraph' || item.text === 'Heading 1' || item.text === 'Heading 2' || item.text === 'Heading 3') {
                    const arrow = document.createElement('span');
                    arrow.innerHTML = this.getRightArrowIcon();
                    arrow.className = 'rich-editor-dropdown-arrow';
                    itemElement.appendChild(arrow);
                } else if (item.text === 'Source Code' && item.icon) {
                    const icon = document.createElement('span');
                    icon.innerHTML = item.icon;
                    icon.className = 'rich-editor-dropdown-icon';
                    itemElement.appendChild(icon);
                }
                
                // mousedown + preventDefault keeps contenteditable selection; click would blur first and formatBlock targets line 1
                itemElement.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.saveEditorSelection();
                    this.executeCommand(item.command, item.value);
                    dropdown.classList.remove('rich-editor-dropdown--open');
                    dropdown.style.display = 'none';
                    this._clearToolbarDropdownPosition(dropdown);
                });
                dropdown.appendChild(itemElement);
            });
        }
        
        button.addEventListener('mousedown', () => {
            this.saveEditorSelection();
        });
        
        button.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const wasOpen = dropdown.classList.contains('rich-editor-dropdown--open');
            this.toolbar.querySelectorAll('.rich-editor-dropdown.rich-editor-dropdown--open').forEach((d) => {
                if (d !== dropdown) {
                    d.classList.remove('rich-editor-dropdown--open');
                    d.style.display = 'none';
                    this._clearToolbarDropdownPosition(d);
                }
            });
            if (wasOpen) {
                dropdown.classList.remove('rich-editor-dropdown--open');
                dropdown.style.display = 'none';
                this._clearToolbarDropdownPosition(dropdown);
            } else {
                dropdown.style.display = 'block';
                dropdown.classList.add('rich-editor-dropdown--open');
                this._positionToolbarDropdown(dropdown, button);
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', () => {
            dropdown.classList.remove('rich-editor-dropdown--open');
            dropdown.style.display = 'none';
            this._clearToolbarDropdownPosition(dropdown);
        });
        
        dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        dropdownContainer.appendChild(button);
        dropdownContainer.appendChild(dropdown);
        this.toolbar.appendChild(dropdownContainer);
    }
    
    createContentArea() {
        this.contentArea = document.createElement('div');
        this.contentArea.className = 'rich-editor-content';
        this.contentArea.contentEditable = true;
        this.contentArea.classList.add('rich-editor-content');
        this.contentArea.classList.add('scroll-bar');
        this.contentArea.style.minHeight = `${this.options.height}px`;
        
        if (this.options.placeholder) {
            this.contentArea.setAttribute('data-placeholder', this.options.placeholder);
        }
        
        this.editorContainer.appendChild(this.contentArea);
    }
    
    createSourceTextarea() {
        // Create preview button for source mode
        this.previewButton = document.createElement('button');
        this.previewButton.type = 'button';
        this.previewButton.className = 'rich-editor-preview-btn';
        this.previewButton.textContent = 'Preview HTML';
        this.previewButton.classList.add('rich-editor-preview-btn');
        
        this.previewButton.addEventListener('click', () => {
            this.previewHTML();
        });
        
        this.editorContainer.appendChild(this.previewButton);
    }
    
    bindEvents() {
        // Update textarea on content change
        this.contentArea.addEventListener('input', () => {
            if (this._imageToolbarImg && !this.contentArea.contains(this._imageToolbarImg)) {
                this.clearEditorImageSelection();
            }
            this.updateTextarea();
        });
        
        // Handle paste events to clean HTML
        this.contentArea.addEventListener('paste', (e) => {
            const imageItems = Array.from((e.clipboardData && e.clipboardData.items) || []).filter((item) => item.type && item.type.indexOf('image/') === 0);
            if (imageItems.length > 0) {
                e.preventDefault();
                this.saveEditorSelection();
                const imageFiles = imageItems.map((item) => item.getAsFile()).filter(Boolean);
                this.uploadAndInsertImages(imageFiles);
                return;
            }
            e.preventDefault();
            this.handlePaste(e);
        });

        this.contentArea.addEventListener('dragover', (e) => {
            if (!this.options.extendedToolbar) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
        });

        this.contentArea.addEventListener('drop', (e) => {
            if (!this.options.extendedToolbar) return;
            e.preventDefault();
            const files = Array.from((e.dataTransfer && e.dataTransfer.files) || []).filter((file) => file && file.type && file.type.indexOf('image/') === 0);
            if (files.length === 0) return;

            if (document.caretRangeFromPoint) {
                const range = document.caretRangeFromPoint(e.clientX, e.clientY);
                if (range) {
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
            } else if (document.caretPositionFromPoint) {
                const position = document.caretPositionFromPoint(e.clientX, e.clientY);
                if (position) {
                    const range = document.createRange();
                    range.setStart(position.offsetNode, position.offset);
                    range.collapse(true);
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
            }
            this.saveEditorSelection();
            this.uploadAndInsertImages(files);
        });
        
        // Handle placeholder
        this.contentArea.addEventListener('focus', () => {
            if (this.contentArea.textContent === '' && this.options.placeholder) {
                this.contentArea.textContent = '';
            }
        });
        
        this.contentArea.addEventListener('blur', () => {
            if (this.contentArea.textContent === '' && this.options.placeholder) {
                this.contentArea.textContent = '';
            }
        });
        
        // Handle form submission
        const form = this.textarea.closest('form');
        if (form) {
            form.addEventListener('submit', () => {
                this.updateTextarea();
            });
        }

        if (this.mediaInput) {
            this.mediaInput.addEventListener('change', () => {
                const files = Array.from(this.mediaInput.files || []).filter((file) => file && file.type && file.type.indexOf('image/') === 0);
                if (files.length > 0) {
                    this.uploadAndInsertImages(files);
                }
                this.mediaInput.value = '';
            });
        }
    }

    setupImageFloatingToolbar() {
        this._imageToolbarImg = null;
        this.imageToolbar = document.createElement('div');
        this.imageToolbar.className = 'rich-editor-img-toolbar';
        this.imageToolbar.style.display = 'none';
        this.imageToolbar.setAttribute('role', 'toolbar');

        const mkBtn = (inner, title, extraClass = '') => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'rich-editor-img-toolbar-btn' + (extraClass ? ' ' + extraClass : '');
            b.innerHTML = inner;
            b.title = title;
            b.addEventListener('mousedown', (e) => e.preventDefault());
            return b;
        };

        const groupAlign = document.createElement('div');
        groupAlign.className = 'rich-editor-img-toolbar-group';

        const alignLeft = mkBtn(this.getAlignLeftIcon(), 'Align image left', 'rich-editor-img-toolbar-align');
        alignLeft.dataset.align = 'left';
        const alignCenter = mkBtn(this.getAlignCenterIcon(), 'Align image center', 'rich-editor-img-toolbar-align');
        alignCenter.dataset.align = 'center';
        const alignRight = mkBtn(this.getAlignRightIcon(), 'Align image right', 'rich-editor-img-toolbar-align');
        alignRight.dataset.align = 'right';

        alignLeft.addEventListener('click', () => this.applyImageToolbarAlign('left'));
        alignCenter.addEventListener('click', () => this.applyImageToolbarAlign('center'));
        alignRight.addEventListener('click', () => this.applyImageToolbarAlign('right'));

        groupAlign.appendChild(alignLeft);
        groupAlign.appendChild(alignCenter);
        groupAlign.appendChild(alignRight);

        const groupSize = document.createElement('div');
        groupSize.className = 'rich-editor-img-toolbar-group';

        const btnSmaller = mkBtn('<span class="rich-editor-img-toolbar-txt">−</span>', 'Smaller (width)');
        const btnLarger = mkBtn('<span class="rich-editor-img-toolbar-txt">+</span>', 'Larger (width)');
        btnSmaller.addEventListener('click', () => this.adjustImageToolbarWidth(-10));
        btnLarger.addEventListener('click', () => this.adjustImageToolbarWidth(10));

        this.imageToolbarLabel = document.createElement('span');
        this.imageToolbarLabel.className = 'rich-editor-img-toolbar-label';
        this.imageToolbarLabel.textContent = '';

        groupSize.appendChild(btnSmaller);
        groupSize.appendChild(this.imageToolbarLabel);
        groupSize.appendChild(btnLarger);

        const groupPct = document.createElement('div');
        groupPct.className = 'rich-editor-img-toolbar-group rich-editor-img-toolbar-pct';
        [25, 50, 75, 100].forEach((pct) => {
            const b = mkBtn(`<span class="rich-editor-img-toolbar-txt">${pct}%</span>`, `Width ${pct}%`, 'rich-editor-img-toolbar-pctbtn');
            b.dataset.pct = String(pct);
            b.addEventListener('click', () => this.applyImageToolbarWidth(pct));
            groupPct.appendChild(b);
        });

        const groupDel = document.createElement('div');
        groupDel.className = 'rich-editor-img-toolbar-group';
        const btnDownload = mkBtn(this.getDownloadImageIcon(), 'Download image');
        btnDownload.addEventListener('click', () => this.downloadSelectedEditorImage());
        const btnDelete = mkBtn(this.getDeleteImageIcon(), 'Remove image', 'rich-editor-img-toolbar-btn--danger');
        btnDelete.addEventListener('click', () => this.deleteSelectedEditorImage());

        this.imageToolbar.appendChild(groupAlign);
        this.imageToolbar.appendChild(groupSize);
        this.imageToolbar.appendChild(groupPct);
        groupDel.appendChild(btnDelete);
        groupDel.appendChild(btnDownload);
        if (this.options.enableImageReplace) {
            const btnReplace = mkBtn(this.getReplaceImageIcon(), 'Replace image');
            btnReplace.addEventListener('click', () => this.openReplaceImageModal());
            groupDel.appendChild(btnReplace);
        }
        this.imageToolbar.appendChild(groupDel);
        document.body.appendChild(this.imageToolbar);

        this._alignButtons = [alignLeft, alignCenter, alignRight];
        this._pctButtons = Array.from(groupPct.querySelectorAll('.rich-editor-img-toolbar-pctbtn'));

        this._repositionImageToolbar = () => {
            if (this._imageToolbarImg && this.imageToolbar && this.imageToolbar.style.display !== 'none') {
                this.positionImageToolbar();
            }
        };

        this._onDocMouseDownImage = (e) => {
            if (!this.imageToolbar) return;
            if (this.imageToolbar.contains(e.target)) return;
            const imgEl = e.target && e.target.closest ? e.target.closest('img') : null;
            if (imgEl && this.contentArea.contains(imgEl)) {
                if (!this.contentArea.isContentEditable) {
                    this.clearEditorImageSelection();
                    return;
                }
                this.selectEditorImage(imgEl);
                return;
            }
            this.clearEditorImageSelection();
        };

        document.addEventListener('mousedown', this._onDocMouseDownImage, true);
        window.addEventListener('scroll', this._repositionImageToolbar, true);
        window.addEventListener('resize', this._repositionImageToolbar);

        this._onImageToolbarKeydown = (e) => {
            if (!this._imageToolbarImg || !this.contentArea.contains(this._imageToolbarImg)) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                this.clearEditorImageSelection();
                return;
            }
            if (e.key !== 'Delete' && e.key !== 'Backspace') return;
            if (!this.contentArea.isContentEditable) return;
            const ae = document.activeElement;
            if (ae && (ae.tagName === 'INPUT' || ae.tagName === 'TEXTAREA' || ae.tagName === 'SELECT')) {
                if (this.imageToolbar && !this.imageToolbar.contains(ae)) {
                    return;
                }
            }
            const inBar = this.imageToolbar && ae && this.imageToolbar.contains(ae);
            const inContent = ae && (ae === this.contentArea || this.contentArea.contains(ae));
            if (!inBar && !inContent) return;
            e.preventDefault();
            e.stopPropagation();
            this.deleteSelectedEditorImage();
        };
        document.addEventListener('keydown', this._onImageToolbarKeydown, true);
    }

    selectEditorImage(img) {
        if (!img || !this.contentArea.contains(img) || !this.contentArea.isContentEditable) return;
        this.contentArea.querySelectorAll('img.rich-editor-img--active').forEach((el) => el.classList.remove('rich-editor-img--active'));
        img.classList.add('rich-editor-img--active');
        this._imageToolbarImg = img;
        this.imageToolbar.style.display = 'flex';
        this.syncImageToolbarState();
        this.positionImageToolbar();
    }

    clearEditorImageSelection() {
        if (this.contentArea) {
            this.contentArea.querySelectorAll('img.rich-editor-img--active').forEach((el) => el.classList.remove('rich-editor-img--active'));
        }
        this._imageToolbarImg = null;
        if (this.imageToolbar) {
            this.imageToolbar.style.display = 'none';
        }
    }

    deleteSelectedEditorImage() {
        const img = this._imageToolbarImg;
        if (!img || !this.contentArea.contains(img) || !this.contentArea.isContentEditable) return;
        const parent = img.parentNode;
        img.remove();
        if (parent && parent !== this.contentArea && parent.nodeType === 1
            && parent.tagName === 'DIV' && parent.hasAttribute('align') && parent.childNodes.length === 0) {
            parent.remove();
        }
        this.clearEditorImageSelection();
        this.updateTextarea();
        try {
            this.contentArea.focus();
        } catch (err) { /* ignore */ }
    }

    downloadSelectedEditorImage() {
        const img = this._imageToolbarImg;
        if (!img || !this.contentArea.contains(img)) return;
        const src = String(img.getAttribute('src') || '').trim();
        if (src === '') return;
        const a = document.createElement('a');
        a.href = src;
        a.download = '';
        a.target = '_blank';
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    getCurrentNoteId() {
        if (this.textarea && typeof this.textarea.closest === 'function' && this.textarea.closest('#user-notes-editor')) {
            return 0;
        }
        const localInput = this.textarea && this.textarea.closest('form')
            ? this.textarea.closest('form').querySelector('input[name="note_id"]')
            : null;
        const globalInput = document.querySelector('input[name="note_id"]');
        const input = localInput || globalInput;
        const raw = input ? input.value : '';
        const parsed = parseInt(raw || '0', 10);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    replaceSelectedEditorImage(nextUrl, targetImg = null) {
        const img = targetImg && this.contentArea.contains(targetImg) ? targetImg : this._imageToolbarImg;
        if (!img || !this.contentArea.contains(img)) return;
        const safeUrl = String(nextUrl || '').trim();
        if (!safeUrl) return;
        img.setAttribute('src', safeUrl);
        this._imageToolbarImg = img;
        this.contentArea.querySelectorAll('img.rich-editor-img--active').forEach((el) => el.classList.remove('rich-editor-img--active'));
        img.classList.add('rich-editor-img--active');
        this.syncImageToolbarState();
        this.positionImageToolbar();
        this.updateTextarea();
        this.emitEditorInputEvents();
    }

    insertLibraryImageAtCursor(nextUrl) {
        const safeUrl = String(nextUrl || '').trim();
        if (!safeUrl) return;
        if (this._savedEditorRange) {
            this.restoreEditorSelection();
        } else {
            this.contentArea.focus();
        }
        const escaped = safeUrl.replace(/"/g, '&quot;');
        document.execCommand('insertHTML', false, `<img src="${escaped}" alt="" data-img-align="left" style="display:block;max-width:100%;height:auto;margin:0.35em 0;margin-left:0;margin-right:auto;box-sizing:border-box;" />`);
        this.updateTextarea();
        this.emitEditorInputEvents();
    }

    emitEditorInputEvents() {
        if (this.contentArea) {
            this.contentArea.dispatchEvent(new Event('input', { bubbles: true }));
            this.contentArea.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (this.textarea) {
            this.textarea.dispatchEvent(new Event('input', { bubbles: true }));
            this.textarea.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    openReplaceImageModal() {
        if (!this.options.enableImageReplace) return;
        const img = this._imageToolbarImg;
        if (!img || !this.contentArea.contains(img)) return;
        if (!window.PrivateNotesMediaReplaceModal || typeof window.PrivateNotesMediaReplaceModal.openForEditorImage !== 'function') {
            alert('Replace modal is not available.');
            return;
        }
        window.PrivateNotesMediaReplaceModal.openForEditorImage(this, img, this.getCurrentNoteId());
    }

    positionImageToolbar() {
        const img = this._imageToolbarImg;
        if (!img || !this.imageToolbar) return;
        this.imageToolbar.style.display = 'flex';
        this.imageToolbar.style.position = 'fixed';
        const place = () => {
            const rect = img.getBoundingClientRect();
            const tbr = this.imageToolbar.getBoundingClientRect();
            let top = rect.bottom + 6;
            let left = rect.left + rect.width / 2 - tbr.width / 2;
            if (top + tbr.height > window.innerHeight - 8) {
                top = rect.top - tbr.height - 6;
            }
            top = Math.max(8, top);
            left = Math.max(8, Math.min(left, window.innerWidth - tbr.width - 8));
            this.imageToolbar.style.top = `${top}px`;
            this.imageToolbar.style.left = `${left}px`;
        };
        place();
        requestAnimationFrame(place);
    }

    getImageWidthPercent(img) {
        const sw = img.style.width;
        if (sw && sw.indexOf('%') !== -1) {
            const n = parseInt(sw, 10);
            if (!isNaN(n)) return Math.max(10, Math.min(100, n));
        }
        const rect = img.getBoundingClientRect();
        let parent = img.parentElement;
        while (parent && parent !== this.contentArea && rect.width > 0) {
            const pw = parent.getBoundingClientRect().width;
            if (pw > 10) {
                return Math.max(10, Math.min(100, Math.round((rect.width / pw) * 100)));
            }
            parent = parent.parentElement;
        }
        return 100;
    }

    getImageAlign(img) {
        const a = (img.getAttribute('data-img-align') || '').toLowerCase();
        if (a === 'left' || a === 'center' || a === 'right') return a;
        return 'left';
    }

    syncImageToolbarState() {
        const img = this._imageToolbarImg;
        if (!img || !this.imageToolbarLabel) return;
        this.imageToolbarLabel.textContent = `${this.getImageWidthPercent(img)}%`;
        const align = this.getImageAlign(img);
        this._alignButtons.forEach((b) => {
            b.classList.toggle('rich-editor-img-toolbar-btn--active', b.dataset.align === align);
        });
        const pct = this.getImageWidthPercent(img);
        const sw = img.style.width;
        const hasPct = sw && sw.indexOf('%') !== -1;
        this._pctButtons.forEach((b) => {
            const v = parseInt(b.dataset.pct || '0', 10);
            b.classList.toggle('rich-editor-img-toolbar-btn--active', hasPct && v === pct);
        });
    }

    applyImageToolbarAlign(align) {
        const img = this._imageToolbarImg;
        if (!img || !this.contentArea.isContentEditable) return;
        img.style.float = 'none';
        img.style.display = 'block';
        img.style.clear = 'both';
        img.style.maxWidth = '100%';
        img.style.height = 'auto';
        img.style.boxSizing = 'border-box';
        const my = '0.35em';
        if (align === 'left') {
            img.style.marginTop = my;
            img.style.marginBottom = my;
            img.style.marginLeft = '0';
            img.style.marginRight = 'auto';
        } else if (align === 'center') {
            img.style.marginTop = my;
            img.style.marginBottom = my;
            img.style.marginLeft = 'auto';
            img.style.marginRight = 'auto';
        } else {
            img.style.marginTop = my;
            img.style.marginBottom = my;
            img.style.marginLeft = 'auto';
            img.style.marginRight = '0';
        }
        img.setAttribute('data-img-align', align);
        this.syncImageToolbarState();
        this.positionImageToolbar();
        this.updateTextarea();
    }

    applyImageToolbarWidth(pct) {
        const img = this._imageToolbarImg;
        if (!img || !this.contentArea.isContentEditable) return;
        pct = Math.max(10, Math.min(100, parseInt(pct, 10) || 100));
        img.style.width = pct + '%';
        img.style.maxWidth = '100%';
        img.style.height = 'auto';
        img.style.boxSizing = 'border-box';
        img.style.display = 'block';
        this.syncImageToolbarState();
        this.positionImageToolbar();
        this.updateTextarea();
    }

    adjustImageToolbarWidth(delta) {
        const img = this._imageToolbarImg;
        if (!img) return;
        let pct = this.getImageWidthPercent(img);
        pct = Math.max(10, Math.min(100, pct + delta));
        this.applyImageToolbarWidth(pct);
    }
    
    handlePaste(e) {
        const clipboardData = e.clipboardData || window.clipboardData;
        let pastedData = '';
        
        if (clipboardData.types.includes('text/html')) {
            pastedData = clipboardData.getData('text/html');
        } else if (clipboardData.types.includes('text/plain')) {
            pastedData = clipboardData.getData('text/plain');
        }
        
        if (pastedData) {
            // Clean the pasted HTML
            const cleanHTML = this.cleanHTML(pastedData);
            
            // Insert at cursor position
            const selection = window.getSelection();
            if (selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                range.deleteContents();
                
                // Create a temporary div to parse HTML
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = cleanHTML;
                
                // Normalize the content before insertion
                this.normalizeSpacing(tempDiv);
                
                // Insert the cleaned content
                const fragment = document.createDocumentFragment();
                while (tempDiv.firstChild) {
                    fragment.appendChild(tempDiv.firstChild);
                }
                range.insertNode(fragment);
                
                // Move cursor to end of inserted content
                range.collapse(false);
                selection.removeAllRanges();
                selection.addRange(range);
            } else {
                // Fallback: append to content
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = cleanHTML;
                this.normalizeSpacing(tempDiv);
                this.contentArea.innerHTML += tempDiv.innerHTML;
            }
            
            this.updateTextarea();
        }
    }
    
    cleanHTML(html) {
        // Create a temporary div to parse and clean HTML
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        
        // Remove potentially dangerous elements and attributes
        const dangerousTags = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea'];
        const dangerousAttrs = ['onclick', 'onload', 'onerror', 'onmouseover', 'onmouseout', 'onfocus', 'onblur', 'onchange', 'onsubmit'];
        
        // Remove dangerous tags
        dangerousTags.forEach(tag => {
            const elements = tempDiv.querySelectorAll(tag);
            elements.forEach(el => el.remove());
        });
        
        // Remove dangerous attributes from all elements
        const allElements = tempDiv.querySelectorAll('*');
        allElements.forEach(el => {
            dangerousAttrs.forEach(attr => {
                if (el.hasAttribute(attr)) {
                    el.removeAttribute(attr);
                }
            });
        });
        
        // Preserve spacing elements (don't clean empty elements for email templates)
        this.preserveEmailSpacing(tempDiv);
        
        return tempDiv.innerHTML;
    }
    
    normalizeSpacing(element) {
        // Normalize text nodes to remove excessive spacing
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );
        
        const textNodes = [];
        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }
        
        textNodes.forEach(textNode => {
            let text = textNode.textContent;
            
            // Normalize multiple spaces to single spaces
            text = text.replace(/ {2,}/g, ' ');
            
            // Normalize line breaks to proper spacing
            text = text.replace(/\n\s*\n/g, '\n'); // Remove multiple line breaks
            text = text.replace(/\n/g, ' '); // Convert single line breaks to spaces
            
            // Trim excessive whitespace
            text = text.trim();
            
            // Update text node
            if (text !== textNode.textContent) {
                textNode.textContent = text;
            }
        });
        
        // Clean up paragraph spacing
        const paragraphs = element.querySelectorAll('p');
        paragraphs.forEach(p => {
            // Remove excessive margins
            p.style.margin = '0 0 8px 0';
            p.style.padding = '0';
            
            // If paragraph is empty or only contains whitespace, remove it
            if (p.textContent.trim() === '') {
                p.remove();
            }
        });
        
        // Clean up div spacing
        const divs = element.querySelectorAll('div');
        divs.forEach(div => {
            // Remove excessive margins and padding
            div.style.margin = '0';
            div.style.padding = '0';
            
            // If div is empty or only contains whitespace, remove it
            if (div.textContent.trim() === '' && div.children.length === 0) {
                div.remove();
            }
        });
    }
    
    preserveFormatting(element) {
        // Preserve line breaks and spacing
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );
        
        const textNodes = [];
        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }
        
        textNodes.forEach(textNode => {
            let text = textNode.textContent;
            
            // Preserve multiple spaces
            text = text.replace(/ {2,}/g, (match) => {
                return '&nbsp;'.repeat(match.length);
            });
            
            // Preserve line breaks
            text = text.replace(/\n/g, '<br>');
            
            // Create a temporary div to convert text to HTML
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = text;
            
            // Replace text node with HTML
            if (tempDiv.innerHTML !== textNode.textContent) {
                const parent = textNode.parentNode;
                parent.insertBefore(tempDiv, textNode);
                parent.removeChild(textNode);
            }
        });
    }
    
    cleanEmptyElements(element) {
        const children = Array.from(element.children);
        children.forEach(child => {
            // Remove empty elements (except for specific tags that can be empty)
            const emptyTags = ['br', 'hr', 'img', 'input'];
            if (!emptyTags.includes(child.tagName.toLowerCase()) && 
                child.children.length === 0 && 
                child.textContent.trim() === '') {
                child.remove();
            } else {
                // Recursively clean child elements
                this.cleanEmptyElements(child);
            }
        });
    }
    
    saveEditorSelection() {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) {
            return;
        }
        const r = sel.getRangeAt(0);
        if (this.contentArea && this.contentArea.contains(r.commonAncestorContainer)) {
            this._savedEditorRange = r.cloneRange();
        }
    }
    
    restoreEditorSelection() {
        if (!this._savedEditorRange || !this.contentArea) {
            return;
        }
        const range = this._savedEditorRange;
        this._savedEditorRange = null;
        this.contentArea.focus();
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    }

    getSelectionRangeWithinEditor() {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) {
            return null;
        }
        const range = sel.getRangeAt(0);
        if (!this.contentArea || !this.contentArea.contains(range.commonAncestorContainer)) {
            return null;
        }
        return range;
    }

    _nodeDepthWithinRoot(node, root) {
        let d = 0;
        let n = node;
        while (n && n !== root) {
            d++;
            n = n.parentNode;
        }
        return d;
    }

    unwrapElement(el) {
        const parent = el.parentNode;
        if (!parent) {
            return;
        }
        while (el.firstChild) {
            parent.insertBefore(el.firstChild, el);
        }
        parent.removeChild(el);
    }

    stripElementPresentationAttrs(el) {
        if (!el || el.nodeType !== Node.ELEMENT_NODE) {
            return 0;
        }
        let removed = 0;
        const tag = el.tagName.toLowerCase();
        const attrs = Array.from(el.attributes || []);
        attrs.forEach((attr) => {
            const n = String(attr.name).toLowerCase();
            if (n === 'style' || n === 'class') {
                el.removeAttribute(attr.name);
                removed++;
                return;
            }
            if (n.indexOf('data-') === 0 || n.indexOf('on') === 0) {
                el.removeAttribute(attr.name);
                removed++;
            }
        });

        const tableTags = new Set(['table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'col', 'colgroup']);
        if (tableTags.has(tag)) {
            ['width', 'height', 'align', 'valign', 'bgcolor', 'border', 'cellpadding', 'cellspacing'].forEach((a) => {
                if (el.hasAttribute(a)) {
                    el.removeAttribute(a);
                    removed++;
                }
            });
        }
        if (tag === 'img') {
            ['width', 'height', 'border'].forEach((a) => {
                if (el.hasAttribute(a)) {
                    el.removeAttribute(a);
                    removed++;
                }
            });
        }
        return removed;
    }

    unwrapBareContainers(root) {
        let unwraps = 0;
        let guard = 0;
        while (guard++ < 500) {
            const candidates = Array.from(root.querySelectorAll('span, div, font')).filter((el) => el.attributes.length === 0);
            if (candidates.length === 0) {
                break;
            }
            candidates.sort((a, b) => this._nodeDepthWithinRoot(b, root) - this._nodeDepthWithinRoot(a, root));
            candidates.forEach((el) => {
                this.unwrapElement(el);
                unwraps++;
            });
        }
        return unwraps;
    }

    removeEmptyBareParagraphs(root) {
        let removed = 0;
        root.querySelectorAll('p').forEach((p) => {
            if (!p || p.attributes.length > 0) {
                return;
            }
            const inner = String(p.innerHTML || '').replace(/&nbsp;/gi, '').trim();
            if (inner === '' || inner === '<br>' || inner === '<br/>') {
                p.remove();
                removed++;
            }
        });
        return removed;
    }

    deepCleanRichHtmlRoot(root) {
        if (!root) {
            return { removedAttrs: 0, unwraps: 0, emptyPs: 0 };
        }
        let removedAttrs = 0;
        root.querySelectorAll('*').forEach((el) => {
            removedAttrs += this.stripElementPresentationAttrs(el);
        });
        const unwraps = this.unwrapBareContainers(root);
        const emptyPs = this.removeEmptyBareParagraphs(root);
        return { removedAttrs, unwraps, emptyPs };
    }

    deepCleanSelectionContext(range) {
        if (!range || !this.contentArea) {
            return { removedAttrs: 0, unwraps: 0, emptyPs: 0 };
        }
        let removedAttrs = 0;
        const touched = new Set();
        const root = this.contentArea;

        const collectAncestors = (node) => {
            let current = node && node.nodeType === Node.ELEMENT_NODE ? node : (node ? node.parentElement : null);
            while (current && current !== root) {
                touched.add(current);
                current = current.parentElement;
            }
        };

        collectAncestors(range.startContainer);
        collectAncestors(range.endContainer);

        root.querySelectorAll('*').forEach((el) => {
            try {
                if (range.intersectsNode(el)) {
                    touched.add(el);
                }
            } catch (err) {
                // ignore browser edge cases for detached nodes
            }
        });

        touched.forEach((el) => {
            removedAttrs += this.stripElementPresentationAttrs(el);
        });

        return { removedAttrs, unwraps: 0, emptyPs: 0 };
    }

    removeInlineCssFromSelection() {
        const range = this.getSelectionRangeWithinEditor();
        if (!range || range.collapsed) {
            alert('Select text in the editor first, then use: More → Clean selection (inline CSS).');
            return;
        }

        const fragment = range.extractContents();
        const wrapper = document.createElement('div');
        while (fragment.firstChild) {
            wrapper.appendChild(fragment.firstChild);
        }

        const stats = this.deepCleanRichHtmlRoot(wrapper);

        const moved = [];
        while (wrapper.firstChild) {
            moved.push(wrapper.firstChild);
            fragment.appendChild(wrapper.firstChild);
        }

        range.insertNode(fragment);
        let fallbackStats = { removedAttrs: 0, unwraps: 0, emptyPs: 0 };
        if (stats.removedAttrs === 0 && stats.unwraps === 0 && stats.emptyPs === 0) {
            fallbackStats = this.deepCleanSelectionContext(range);
        }

        if (moved.length > 0) {
            const newRange = document.createRange();
            newRange.setStartBefore(moved[0]);
            newRange.setEndAfter(moved[moved.length - 1]);
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(newRange);
        }

        const totalRemovedAttrs = stats.removedAttrs + fallbackStats.removedAttrs;
        alert('Selection cleaned.\n'
            + '- attributes removed: ' + totalRemovedAttrs + '\n'
            + '- bare wrappers unwrapped: ' + stats.unwraps + '\n'
            + '- empty <p> removed: ' + stats.emptyPs);
    }
    
    executeCommand(command, value = null) {
        if (command === 'toggleSource') {
            this.openSourceCodeModal();
            return;
        }
        if (command === 'insertImage') {
            this.saveEditorSelection();
            if (window.PrivateNotesMediaReplaceModal && typeof window.PrivateNotesMediaReplaceModal.openForEditorImage === 'function') {
                window.PrivateNotesMediaReplaceModal.openForEditorImage(this, null, this.getCurrentNoteId());
            } else if (this.mediaInput) {
                this.mediaInput.click();
            }
            return;
        }
        
        if (this._savedEditorRange) {
            this.restoreEditorSelection();
        } else {
            this.contentArea.focus();
        }
        
        switch (command) {
            case 'createLink':
                const url = prompt('Enter URL:');
                if (url) {
                    document.execCommand('createLink', false, url);
                }
                break;
            case 'removeFormat':
                document.execCommand('removeFormat', false, null);
                break;
            case 'removeInlineCss':
                this.removeInlineCssFromSelection();
                break;
            case 'formatBlock': {
                const tag = String(value || 'p').toLowerCase();
                const blockTag = tag === 'p' ? 'p' : tag;
                document.execCommand('formatBlock', false, blockTag);
                break;
            }
            case 'foreColor':
            case 'hiliteColor':
            case 'backColor':
                try {
                    document.execCommand('styleWithCSS', false, true);
                } catch (err) { /* optional in some engines */ }
                document.execCommand(command, false, value);
                break;
            default:
                document.execCommand(command, false, value);
        }
        this.updateTextarea();
    }

    async uploadAndInsertImages(files) {
        if (!Array.isArray(files) || files.length === 0) return;
        if (!this.options.uploadUrl) {
            alert('Image upload is not configured for this editor.');
            return;
        }

        const maxBytes = this.options.maxUploadSizeMb * 1024 * 1024;
        for (const file of files) {
            if (!file || !file.type || file.type.indexOf('image/') !== 0) continue;
            if (file.size > maxBytes) {
                alert(`Image is too large. Max allowed size is ${this.options.maxUploadSizeMb}MB.`);
                continue;
            }

            try {
                const formData = new FormData();
                formData.append('media', file);
                const inProfile = this.textarea && typeof this.textarea.closest === 'function'
                    ? this.textarea.closest('#user-notes-editor')
                    : null;
                const noteIdInput = document.querySelector('input[name="note_id"]');
                if (window.PRIVATE_NOTE_PUBLIC && window.PRIVATE_NOTE_PUBLIC.token && window.PRIVATE_NOTE_PUBLIC.noteId) {
                    formData.append('public_token', String(window.PRIVATE_NOTE_PUBLIC.token));
                    formData.append('note_id', String(window.PRIVATE_NOTE_PUBLIC.noteId));
                } else if (inProfile) {
                    formData.append('note_id', '0');
                } else if (noteIdInput && noteIdInput.value) {
                    formData.append('note_id', noteIdInput.value);
                }
                let ctk = (window.PRIVATE_NOTE_SHARE && window.PRIVATE_NOTE_SHARE.csrfToken)
                    ? String(window.PRIVATE_NOTE_SHARE.csrfToken)
                    : '';
                if (!ctk && window.PROFILE_AUTOSAVE && window.PROFILE_AUTOSAVE.csrfToken) {
                    ctk = String(window.PROFILE_AUTOSAVE.csrfToken);
                }
                if (!ctk && window.PROFILE_NOTE_SHARE && window.PROFILE_NOTE_SHARE.csrfToken) {
                    ctk = String(window.PROFILE_NOTE_SHARE.csrfToken);
                }
                if (!ctk && window.PROFILE_NOTE_CLIENT_SAVE && window.PROFILE_NOTE_CLIENT_SAVE.csrfToken) {
                    ctk = String(window.PROFILE_NOTE_CLIENT_SAVE.csrfToken);
                }
                if (!ctk) {
                    const pcfg = document.getElementById('profileNotesMediaConfig');
                    if (pcfg) {
                        const pt = (pcfg.getAttribute('data-csrf') || '').trim();
                        if (pt) ctk = pt;
                    }
                }
                if (!ctk && typeof window.csrfToken !== 'undefined' && window.csrfToken) {
                    ctk = String(window.csrfToken);
                }
                if (ctk) {
                    formData.append('csrf_token', ctk);
                }

                const reqHeaders = {};
                if (ctk) {
                    reqHeaders['X-CSRF-Token'] = ctk;
                }
                const response = await fetch(this.options.uploadUrl, {
                    method: 'POST',
                    headers: Object.keys(reqHeaders).length ? reqHeaders : undefined,
                    body: formData,
                    credentials: 'same-origin'
                });
                const payload = await response.json();
                if (!payload || payload.status !== 'ok' || !payload.url) {
                    throw new Error((payload && payload.error) ? payload.error : 'Failed to upload image');
                }

                if (this._savedEditorRange) {
                    this.restoreEditorSelection();
                } else {
                    this.contentArea.focus();
                }

                const safeUrl = String(payload.url).replace(/"/g, '&quot;');
                document.execCommand('insertHTML', false, `<img src="${safeUrl}" alt="" data-img-align="left" style="display:block;max-width:100%;height:auto;margin:0.35em 0;margin-left:0;margin-right:auto;box-sizing:border-box;" />`);
                this.updateTextarea();
            } catch (err) {
                alert(err && err.message ? err.message : 'Image upload failed');
            }
        }
    }
    
    openSourceCodeModal() {
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'rich-editor-modal-overlay';
        
        const modalContent = document.createElement('div');
        modalContent.className = 'rich-editor-modal-content rich-editor-modal-content-source';
        
        // Modal header
        const modalHeader = document.createElement('div');
        modalHeader.className = 'rich-editor-modal-header';
        
        const modalTitle = document.createElement('h3');
        modalTitle.textContent = 'Source Code';
        modalTitle.className = 'rich-editor-modal-title';
        
        const closeButton = document.createElement('button');
        closeButton.textContent = '×';
        closeButton.className = 'rich-editor-modal-close';
        
        closeButton.addEventListener('click', () => {
            document.body.removeChild(modal);
        });
        
        // Modal body with textarea
        const modalBody = document.createElement('div');
        modalBody.className = 'rich-editor-modal-body';
        
        const sourceTextarea = document.createElement('textarea');
        sourceTextarea.className = 'rich-editor-source-textarea';
        
        // Set current HTML content
        sourceTextarea.value = this.getContent();
        
        // Modal footer with buttons
        const modalFooter = document.createElement('div');
        modalFooter.className = 'rich-editor-modal-footer';
        
        const stripSourceButton = document.createElement('button');
        stripSourceButton.type = 'button';
        stripSourceButton.textContent = 'Strip inline CSS';
        stripSourceButton.className = 'rich-editor-modal-btn rich-editor-modal-btn-cancel';

        const cancelButton = document.createElement('button');
        cancelButton.textContent = 'Cancel';
        cancelButton.className = 'rich-editor-modal-btn rich-editor-modal-btn-cancel';
        
        const saveButton = document.createElement('button');
        saveButton.textContent = 'Save';
        saveButton.className = 'rich-editor-modal-btn rich-editor-modal-btn-save';

        stripSourceButton.addEventListener('click', () => {
            const holder = document.createElement('div');
            holder.innerHTML = String(sourceTextarea.value || '');
            const stats = this.deepCleanRichHtmlRoot(holder);
            sourceTextarea.value = holder.innerHTML;
            alert('Source HTML cleaned.\n'
                + '- attributes removed: ' + stats.removedAttrs + '\n'
                + '- bare wrappers unwrapped: ' + stats.unwraps + '\n'
                + '- empty <p> removed: ' + stats.emptyPs);
        });
        
        // Button event handlers
        cancelButton.addEventListener('click', () => {
            document.body.removeChild(modal);
        });
        
        saveButton.addEventListener('click', () => {
            // Save the HTML content and render it
            const htmlContent = sourceTextarea.value;
            this.setContent(htmlContent);
            document.body.removeChild(modal);
        });
        
        // Assemble modal
        modalHeader.appendChild(modalTitle);
        modalHeader.appendChild(closeButton);
        modalBody.appendChild(sourceTextarea);
        modalFooter.appendChild(stripSourceButton);
        modalFooter.appendChild(cancelButton);
        modalFooter.appendChild(saveButton);
        
        modalContent.appendChild(modalHeader);
        modalContent.appendChild(modalBody);
        modalContent.appendChild(modalFooter);
        modal.appendChild(modalContent);
        
        document.body.appendChild(modal);
        
        // Focus on textarea
        sourceTextarea.focus();
        
        // Close modal on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                document.body.removeChild(modal);
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function closeOnEscape(e) {
            if (e.key === 'Escape') {
                document.body.removeChild(modal);
                document.removeEventListener('keydown', closeOnEscape);
            }
        });
        
        // Handle Ctrl+S to save
        sourceTextarea.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                saveButton.click();
            }
        });
    }
    
    setContent(html) {
        // Clean HTML before setting content in visual mode
        const cleanHTML = this.cleanHTML(html);
        this.contentArea.innerHTML = cleanHTML;
        this.updateTextarea();
    }
    
    getContent() {
        return this.contentArea.innerHTML;
    }
    
    updateTextarea() {
        this.textarea.value = this.getContent();
    }
    
    /**
     * Push editor HTML into original textareas (call before form submit / batch save).
     */
    static syncAll(root) {
        const scope = root || document;
        scope.querySelectorAll('textarea.editor').forEach((textarea) => {
            const inst = textarea._richEditorInstance;
            if (inst && typeof inst.updateTextarea === 'function') {
                inst.updateTextarea();
            }
        });
    }
    
    previewHTML() {
        const html = this.getContent();
        
        // Create preview modal
        const modal = document.createElement('div');
        modal.className = 'rich-editor-modal-overlay';
        
        const modalContent = document.createElement('div');
        modalContent.className = 'rich-editor-modal-content rich-editor-modal-content-preview';
        
        const modalHeader = document.createElement('div');
        modalHeader.className = 'rich-editor-modal-header';
        
        const modalTitle = document.createElement('h3');
        modalTitle.textContent = 'HTML Preview';
        modalTitle.className = 'rich-editor-modal-title';
        
        const closeButton = document.createElement('button');
        closeButton.textContent = '×';
        closeButton.className = 'rich-editor-modal-close';
        
        closeButton.addEventListener('click', () => {
            document.body.removeChild(modal);
        });
        
        const modalBody = document.createElement('div');
        modalBody.className = 'rich-editor-modal-body rich-editor-modal-body-preview';
        
        // Create preview iframe
        const previewFrame = document.createElement('iframe');
        previewFrame.className = 'rich-editor-preview-frame';
        
        modalHeader.appendChild(modalTitle);
        modalHeader.appendChild(closeButton);
        modalContent.appendChild(modalHeader);
        modalBody.appendChild(previewFrame);
        modalContent.appendChild(modalBody);
        modal.appendChild(modalContent);
        
        document.body.appendChild(modal);
        
        // Write HTML to iframe
        previewFrame.onload = () => {
            const doc = previewFrame.contentDocument || previewFrame.contentWindow.document;
            const richTextCssLink = document.querySelector('link[href*="assets/css/rich-text.css"]');
            const richTextCssHref = richTextCssLink ? richTextCssLink.href : '';
            doc.open();
            doc.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <title>HTML Preview</title>
                    ${richTextCssHref ? `<link rel="stylesheet" href="${richTextCssHref}">` : ''}
                </head>
                <body class="rich-editor-preview-body">
                    ${html}
                </body>
                </html>
            `);
            doc.close();
        };
        
        // Close modal on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                document.body.removeChild(modal);
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function closeOnEscape(e) {
            if (e.key === 'Escape') {
                document.body.removeChild(modal);
                document.removeEventListener('keydown', closeOnEscape);
            }
        });
    }
    
    destroy() {
        if (this._onDocMouseDownImage) {
            document.removeEventListener('mousedown', this._onDocMouseDownImage, true);
            this._onDocMouseDownImage = null;
        }
        if (this._repositionImageToolbar) {
            window.removeEventListener('scroll', this._repositionImageToolbar, true);
            window.removeEventListener('resize', this._repositionImageToolbar);
            this._repositionImageToolbar = null;
        }
        if (this._onImageToolbarKeydown) {
            document.removeEventListener('keydown', this._onImageToolbarKeydown, true);
            this._onImageToolbarKeydown = null;
        }
        if (this.imageToolbar && this.imageToolbar.parentNode) {
            this.imageToolbar.parentNode.removeChild(this.imageToolbar);
        }
        this.imageToolbar = null;
        this._imageToolbarImg = null;
        if (this.editorContainer && this.editorContainer.parentNode) {
            this.editorContainer.parentNode.removeChild(this.editorContainer);
        }
        this.textarea.style.display = '';
        this.textarea.dataset.richEditorInitialized = 'false';
    }
    
    // SVG Icons - Lighter and less bold
    getUndoIcon() {
        return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
        </svg>`;
    }
    
    getRedoIcon() {
        return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="m15 15 6-6m0 0-6-6m6 6H9a6 6 0 0 0 0 12h3" />
        </svg>`;
    }
    
    getParagraphIcon() {
        return `<div class="rich-editor-paragraph-icon">
            <span class="rich-editor-paragraph-label">Paragraph</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6,9 12,15 18,9"></polyline>
            </svg>
        </div>`;
    }
    
    getBoldIcon() {
        return `<span class="rich-editor-format-icon rich-editor-format-bold">B</span>`;
    }
    
    getItalicIcon() {
        return `<span class="rich-editor-format-icon rich-editor-format-italic">I</span>`;
    }
    
    getUnderlineIcon() {
        return `<span class="rich-editor-format-icon rich-editor-format-underline">U</span>`;
    }

    getStrikethroughIcon() {
        return `<span class="rich-editor-format-icon rich-editor-format-strikethrough">S</span>`;
    }

    getClearFormattingIcon() {
        return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 4l16 16"></path>
            <path d="M7 7h10"></path>
            <path d="M9 11h6"></path>
            <path d="M11 15h2"></path>
        </svg>`;
    }

    getFontSizeIcon() {
        return `<div class="rich-editor-paragraph-icon">
            <span class="rich-editor-paragraph-label">Size</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6,9 12,15 18,9"></polyline>
            </svg>
        </div>`;
    }

    getColorIcon() {
        return `<div class="rich-editor-paragraph-icon">
            <span class="rich-editor-format-icon">A</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6,9 12,15 18,9"></polyline>
            </svg>
        </div>`;
    }

    getImageIcon() {
        return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="5" width="18" height="14" rx="2"></rect>
            <circle cx="8.5" cy="10.5" r="1.5"></circle>
            <path d="M21 16l-5.5-5.5L7 19"></path>
        </svg>`;
    }

    getDeleteImageIcon() {
        return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 6h18"></path>
            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
            <path d="M10 11v6M14 11v6"></path>
        </svg>`;
    }

    getDownloadImageIcon() {
        return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3v12"></path>
            <path d="m7.5 10.5 4.5 4.5 4.5-4.5"></path>
            <path d="M4.5 19.5h15"></path>
        </svg>`;
    }

    getReplaceImageIcon() {
        return `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="7" height="7" rx="1.25"></rect>
            <rect x="14" y="14" width="7" height="7" rx="1.25"></rect>
            <path d="M14.5 4.5a7 7 0 0 1 5 5"></path>
            <path d="M19.5 9.5l.75-.75.75.75"></path>
            <path d="M9.5 19.5a7 7 0 0 1-5-5"></path>
            <path d="M4.5 14.5l-.75.75-.75-.75"></path>
        </svg>`;
    }
    
    getAlignLeftIcon() {
        return `<svg width="25" height="25" viewBox="0 0 24 24" fill="currentColor" fill-rule="evenodd">
            <path d="M5 5h14c.6 0 1 .4 1 1s-.4 1-1 1H5a1 1 0 1 1 0-2Zm0 4h8c.6 0 1 .4 1 1s-.4 1-1 1H5a1 1 0 1 1 0-2Zm0 8h8c.6 0 1 .4 1 1s-.4 1-1 1H5a1 1 0 0 1 0-2Zm0-4h14c.6 0 1 .4 1 1s-.4 1-1 1H5a1 1 0 0 1 0-2Z" />
        </svg>`;
    }
    
    getAlignCenterIcon() {
        return `<svg width="25" height="25" viewBox="0 0 24 24" fill="currentColor" fill-rule="evenodd">
            <path d="M5 5h14c.6 0 1 .4 1 1s-.4 1-1 1H5a1 1 0 1 1 0-2Zm3 4h8c.6 0 1 .4 1 1s-.4 1-1 1H8a1 1 0 1 1 0-2Zm0 8h8c.6 0 1 .4 1 1s-.4 1-1 1H8a1 1 0 0 1 0-2Zm-3-4h14c.6 0 1 .4 1 1s-.4 1-1 1H5a1 1 0 0 1 0-2Z" />
        </svg>`;
    }
    
    getAlignRightIcon() {
        return `<svg width="25" height="25" viewBox="0 0 24 24" fill="currentColor" fill-rule="evenodd">
            <path d="M5 5h14c.6 0 1 .4 1 1s-.4 1-1 1H5a1 1 0 1 1 0-2Zm6 4h8c.6 0 1 .4 1 1s-.4 1-1 1h-8a1 1 0 0 1 0-2Zm0 8h8c.6 0 1 .4 1 1s-.4 1-1 1h-8a1 1 0 0 1 0-2Zm-6-4h14c.6 0 1 .4 1 1s-.4 1-1 1H5a1 1 0 0 1 0-2Z" />
        </svg>`;
    }
    
    getAlignJustifyIcon() {
        return `<svg width="25" height="25" viewBox="0 0 24 24" fill="currentColor" fill-rule="evenodd">
            <path d="M5 5h14c.6 0 1 .4 1 1s-.4 1-1 1H5a1 1 0 1 1 0-2Zm0 4h14c.6 0 1 .4 1 1s-.4 1-1 1H5a1 1 0 1 1 0-2Zm0 4h14c.6 0 1 .4 1 1s-.4 1-1 1H5a1 1 0 0 1 0-2Zm0 4h14c.6 0 1 .4 1 1s-.4 1-1 1H5a1 1 0 0 1 0-2Z" />
        </svg>`;
    }
    
    getMoreIcon() {
        return `<svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="1"></circle>
            <circle cx="19" cy="12" r="1"></circle>
            <circle cx="5" cy="12" r="1"></circle>
        </svg>`;
    }
    
    getRightArrowIcon() {
        return `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9,18 15,12 9,6"></polyline>
        </svg>`;
    }
    
    getSourceCodeIcon() {
        return `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="25,18 22,12 25,6"></polyline>
            <polyline points="8,6 2,12 8,18"></polyline>
        </svg>`;
    }
    
    getBulletListIcon() {
        return `<svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
        </svg>`;
    }
    
    getNumberedListIcon() {
        return `<svg width="25" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8.242 5.992h12m-12 6.003H20.24m-12 5.999h12M4.117 7.495v-3.75H2.99m1.125 3.75H2.99m1.125 0H5.24m-1.92 2.577a1.125 1.125 0 1 1 1.591 1.59l-1.83 1.83h2.25M2.99 15.745h1.125a1.125 1.125 0 0 1 0 2.25H3.74m0-.002h.375a1.125 1.125 0 0 1 0 2.25H2.99" />
        </svg>`;
    }

    preserveEmailSpacing(element) {
        // Preserve empty table rows that contain spacing
        const tableRows = element.querySelectorAll('tr');
        tableRows.forEach(tr => {
            const cells = tr.querySelectorAll('td');
            
            // If row has cells with height attributes, preserve the row
            cells.forEach(cell => {
                if (cell.getAttribute('height')) {
                    // This is a spacing row, preserve it
                    return;
                }
            });
            
            // If row is empty but has height attribute, preserve it
            if (tr.getAttribute('height')) {
                return;
            }
        });
        
        // Preserve br elements that create spacing
        const brElements = element.querySelectorAll('br');
        brElements.forEach(br => {
            // Keep all br elements for spacing
        });
        
        // Preserve non-breaking spaces in cells
        const cells = element.querySelectorAll('td');
        cells.forEach(cell => {
            // If cell only contains &nbsp; or is empty with height, preserve it
            if (cell.innerHTML === '&nbsp;' || 
                cell.innerHTML === '' || 
                cell.innerHTML === '<br>' ||
                cell.innerHTML === '<br data-mce-bogus="1">') {
                if (cell.getAttribute('height')) {
                    // This is a spacing cell, preserve it
                    if (cell.innerHTML === '') {
                        cell.innerHTML = '&nbsp;';
                    }
                }
            }
        });
    }
}

/**
 * Initialize Rich Editor
 */
function initializeRichEditor() {
    // Check if already initialized globally
    if (window.richEditorInitialized) {
        return;
    }
    
    // Clean up any existing instances first
    cleanupExistingEditors();
    
    // Initialize for description textareas
    const descriptionTextareas = document.querySelectorAll('textarea[name="description"], textarea[name="note_content"], textarea[name="task_description"], textarea.editor');
    
    descriptionTextareas.forEach(textarea => {
        // Check if already initialized
        if (textarea.dataset.richEditorInitialized !== 'true') {
            let selector;
            if (textarea.classList.contains('editor')) {
                selector = `textarea[name="${textarea.name}"]`;
            } else {
                selector = `textarea[name="${textarea.name}"]`;
            }
            
            const publicShareToken = (window.PRIVATE_NOTE_PUBLIC && window.PRIVATE_NOTE_PUBLIC.token)
                ? String(window.PRIVATE_NOTE_PUBLIC.token).trim()
                : '';
            new RichEditor(selector, {
                height: 300,
                placeholder: textarea.placeholder || 'Start typing...',
                extendedToolbar: textarea.name === 'note_content',
                uploadUrl: textarea.name === 'note_content' ? '../ajax/private_notes/upload_media.php' : '',
                enableImageReplace: textarea.name !== 'note_content' || !publicShareToken
            });
        }
    });
    
    // Mark as globally initialized
    window.richEditorInitialized = true;
}

/**
 * Clean up existing editor instances
 */
function cleanupExistingEditors() {
    document.querySelectorAll('.rich-editor-img-toolbar:not([data-email-compose-img-toolbar])').forEach((el) => {
        if (el.parentNode) el.parentNode.removeChild(el);
    });
    const existingContainers = document.querySelectorAll('.rich-editor-container');
    existingContainers.forEach(container => {
        if (container.parentNode) {
            container.parentNode.removeChild(container);
        }
    });
    
    // Reset initialization flags
    const textareas = document.querySelectorAll('textarea[name="description"], textarea[name="note_content"], textarea[name="task_description"], textarea.editor');
    textareas.forEach(textarea => {
        textarea.dataset.richEditorInitialized = 'false';
        textarea.style.display = '';
    });
}

// Initialize when DOM is loaded (skip on pages that set window.skipAutoRichEditorInit)
document.addEventListener('DOMContentLoaded', function() {
    // Clear any existing initialization flag
    window.richEditorInitialized = false;
    if (window.skipAutoRichEditorInit) {
        return;
    }
    initializeRichEditor();
});

// Export for global use
window.RichEditor = RichEditor;
window.initializeRichEditor = initializeRichEditor;
window.cleanupExistingEditors = cleanupExistingEditors;
window.syncRichEditors = function (root) {
    if (typeof RichEditor !== 'undefined' && typeof RichEditor.syncAll === 'function') {
        RichEditor.syncAll(root);
    }
};
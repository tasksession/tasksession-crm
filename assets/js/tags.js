// Global Tags Dropdown – Reusable Module
// Can be used for leads, tasks, projects, invoices, etc.
(function () {
  'use strict';

  // Utility functions
  function escapeHtml(str) {
    return String(str || '').replace(/[&<>"']/g, s => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[s]));
  }

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  // Main TagsDropdown module
  const TagsDropdown = {
    /**
     * Initialize tags dropdown for form/input contexts
     * @param {Object} config Configuration object
     * @param {string} config.searchInputId - ID of search input element
     * @param {string} config.dropdownMenuId - ID of dropdown menu container
     * @param {string} config.listContainerId - ID of tags list container inside dropdown
     * @param {string} config.arrowIconId - ID of arrow icon element
     * @param {string} config.selectedTagsInputId - ID of hidden input to store selected tag IDs
     * @param {string} config.badgesContainerId - ID of container to display selected tag badges
     * @param {string} config.apiBase - Base URL for API endpoints (e.g., '../includes/tags/')
     * @param {Function} [config.onTagChange] - Callback when tags change (receives selectedTagIds array)
     * @param {Object} [config.i18n] - Internationalization object with keys: addNewTag, asNewTag, failedToCreate, networkError, remove
     */
    init: function(config) {
      const {
        searchInputId,
        dropdownMenuId,
        listContainerId,
        arrowIconId,
        selectedTagsInputId,
        badgesContainerId,
        apiBase,
        onTagChange,
        i18n = {}
      } = config;

      const tagsMenu = document.getElementById(dropdownMenuId);
      const selectedTagsInput = document.getElementById(selectedTagsInputId);
      const tagsBadgesContainer = document.getElementById(badgesContainerId);
      const searchInput = document.getElementById(searchInputId);
      const tagsListContainer = document.getElementById(listContainerId);

      if (!tagsMenu || !selectedTagsInput || !tagsBadgesContainer || !searchInput || !tagsListContainer) {
        return;
      }

      let selectedTags = [];
      let allTags = [];
      let query = '';
      let isLoading = false;

      function fetchTags() {
        if (isLoading) return;
        isLoading = true;
        fetch(apiBase + 'tags_list.php')
          .then(r => r.json())
          .then(data => {
            isLoading = false;
            if (data.status === 'ok' && data.data && data.data.tags_by_category) {
              allTags = [];
              (data.data.tags_by_category || []).forEach(cat => {
                (cat.tags || []).forEach(tag => {
                  allTags.push({ ...tag, category_name: cat.category_name });
                });
              });
              renderMenu();
            }
          })
          .catch(() => {
            isLoading = false;
          });
      }

      function renderMenu(showAll = false) {
        if (!tagsListContainer) return;
        tagsListContainer.innerHTML = '';

        const q = String(query || '').toLowerCase().trim();
        const filteredTags = allTags.filter(tag => {
          if (showAll || !q) return true;
          const name = String(tag.name || '').toLowerCase();
          return name.includes(q);
        });

        // Show matching tags grouped by category
        if (filteredTags.length > 0) {
          const byCategory = {};
          filteredTags.forEach(tag => {
            const catName = tag.category_name || 'Other';
            if (!byCategory[catName]) byCategory[catName] = [];
            byCategory[catName].push(tag);
          });

          Object.keys(byCategory).sort().forEach(catName => {
            const header = document.createElement('div');
            header.className = 'fw-bold text-muted small mb-1 mt-2 px-2';
            header.textContent = catName;
            tagsListContainer.appendChild(header);

            byCategory[catName].forEach(tag => {
              const isSelected = selectedTags.some(t => String(t.id) === String(tag.id));
              const item = document.createElement('div');
              item.className = `d-flex align-items-center p-2 ${isSelected ? 'bg-light' : ''}`;
              item.style.cursor = 'pointer';
              item.innerHTML = `
                <span class="badge ${tag.color_class || ''} me-2">${escapeHtml(tag.name)}</span>
                ${isSelected ? '<span class="text-success small">✓</span>' : ''}
              `;
              item.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleTag(tag);
                searchInput.value = '';
                query = '';
                tagsMenu.style.display = 'none';
              });
              tagsListContainer.appendChild(item);
            });
          });
        }

        // Show "Add 'X' as a new tag" option if query doesn't match any existing tag
        if (q && filteredTags.length === 0) {
          const addNewItem = document.createElement('div');
          addNewItem.className = 'd-flex align-items-center p-2';
          addNewItem.style.cursor = 'pointer';
          addNewItem.innerHTML = `
            <span class="text-muted">${escapeHtml(i18n.addNewTag || 'Add')} "${escapeHtml(q)}" ${escapeHtml(i18n.asNewTag || 'as a new tag')}</span>
          `;
          addNewItem.addEventListener('click', function(e) {
            e.stopPropagation();
            createTagFromQuery(q);
          });
          tagsListContainer.appendChild(addNewItem);
        }

        // Show/hide dropdown based on query or showAll flag
        if (q || showAll) {
          tagsMenu.style.display = 'block';
        } else {
          tagsMenu.style.display = 'none';
        }
      }

      function toggleTag(tag) {
        const idx = selectedTags.findIndex(t => String(t.id) === String(tag.id));
        if (idx >= 0) {
          selectedTags.splice(idx, 1);
        } else {
          selectedTags.push(tag);
        }
        updateBadges();
        if (typeof onTagChange === 'function') {
          onTagChange(selectedTags.map(t => t.id));
        }
      }

      function createTagFromQuery(tagName) {
        if (!tagName || !tagName.trim()) return;

        fetch(apiBase + 'tags_create.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ tag_name: tagName.trim() })
        })
          .then(r => r.json())
          .then(data => {
            if (data.status === 'ok' && data.data && data.data.tag) {
              const newTag = data.data.tag;
              newTag.category_name = 'General';
              allTags.push(newTag);
              toggleTag(newTag);
              searchInput.value = '';
              query = '';
              tagsMenu.style.display = 'none';
              // Refresh tags list
              fetchTags();
            } else {
              alert(data.error || (i18n.failedToCreate || 'Failed to create tag'));
            }
          })
          .catch(() => {
            alert(i18n.networkError || 'Network error occurred');
          });
      }

      function updateBadges() {
        selectedTagsInput.value = selectedTags.map(t => t.id).join(',');
        try { selectedTagsInput.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {}

        tagsBadgesContainer.innerHTML = '';
        if (selectedTags.length) {
          selectedTags.forEach(tag => {
            const badge = document.createElement('span');
            badge.className = `badge ${tag.color_class || ''} me-1 mb-1`;
            badge.textContent = tag.name;
            badge.style.cursor = 'pointer';
            badge.title = i18n.remove || 'Remove';
            const removeIcon = document.createElement('span');
            removeIcon.innerHTML = ' ×';
            removeIcon.style.cursor = 'pointer';
            removeIcon.style.fontWeight = 'bold';
            badge.appendChild(removeIcon);
            badge.addEventListener('click', function(e) {
              e.stopPropagation();
              toggleTag(tag);
            });
            tagsBadgesContainer.appendChild(badge);
          });
        }
      }

      // Arrow icon click handler
      const arrowIcon = document.getElementById(arrowIconId);
      if (arrowIcon) {
        arrowIcon.addEventListener('click', function(e) {
          e.stopPropagation();
          if (tagsMenu.style.display === 'none' || tagsMenu.style.display === '') {
            renderMenu(true); // Show all tags
          } else {
            tagsMenu.style.display = 'none';
          }
        });
      }

      // Search input handlers
      searchInput.addEventListener('input', function() {
        query = this.value || '';
        renderMenu(false); // Filter based on query
      });

      searchInput.addEventListener('focus', function() {
        if (query) {
          renderMenu(false);
        } else if (tagsMenu.style.display === 'none' || tagsMenu.style.display === '') {
          renderMenu(true); // Show all tags when focusing on empty input
        }
      });

      // Close dropdown when clicking outside
      document.addEventListener('click', function(e) {
        if (!tagsMenu.contains(e.target) && e.target !== searchInput && (!arrowIcon || !arrowIcon.contains(e.target))) {
          tagsMenu.style.display = 'none';
        }
      });

      // Prevent dropdown from closing when clicking inside
      tagsMenu.addEventListener('click', function(e) {
        e.stopPropagation();
      });

      // Fetch tags on init
      fetchTags();
    },

    /**
     * Initialize tags display/edit for sidebar contexts with hover pattern
     * @param {Object} config Configuration object
     * @param {string} config.displayWrapperId - ID of wrapper element containing tags display
     * @param {string} config.badgesContainerId - ID of container to display tag badges
     * @param {string} config.editHoverId - ID of hover overlay element
     * @param {string} config.editContainerId - ID of edit container (search input + dropdown)
     * @param {string} config.searchInputId - ID of search input element
     * @param {string} config.dropdownMenuId - ID of dropdown menu container
     * @param {string} config.listContainerId - ID of tags list container inside dropdown
     * @param {string} config.arrowIconId - ID of arrow icon element
     * @param {string} config.closeBtnId - ID of close button
     * @param {string} config.apiBase - Base URL for API endpoints
     * @param {string} config.entityType - Entity type ('lead', 'task', 'project', 'invoice')
     * @param {number} config.entityId - Entity ID
     * @param {Array} [config.initialTags] - Initial assigned tags array
     * @param {Function} [config.onTagChange] - Callback when tags change (receives entityId, tagId, action)
     * @param {Function} [config.onTagsUpdated] - Callback after tags are updated (for refreshing display)
     * @param {Object} [config.i18n] - Internationalization object
     */
    initDisplayEdit: function(config) {
      const {
        displayWrapperId,
        badgesContainerId,
        editHoverId,
        editContainerId,
        searchInputId,
        dropdownMenuId,
        listContainerId,
        arrowIconId,
        closeBtnId,
        apiBase,
        entityType,
        entityId,
        initialTags = [],
        onTagChange,
        onTagsUpdated,
        i18n = {}
      } = config;

      const tagsDisplay = qs('#' + displayWrapperId);
      const tagsHover = qs('#' + editHoverId);
      const tagsEditContainer = qs('#' + editContainerId);
      const tagsBadgesContainer = qs('#' + badgesContainerId);
      const tagsMenu = qs('#' + dropdownMenuId);
      const searchInput = qs('#' + searchInputId);
      const tagsListContainer = qs('#' + listContainerId);
      const arrowIcon = qs('#' + arrowIconId);
      const closeBtn = qs('#' + closeBtnId);

      if (!tagsDisplay || !tagsBadgesContainer || !tagsEditContainer || !tagsMenu || !searchInput || !tagsListContainer) {
        return;
      }

      // Render initial tags
      this.renderTags(badgesContainerId, initialTags, true, function(tagId) {
        updateTag(entityId, tagId, 'remove');
      }, i18n);

      // Store reference for updating from outside
      const dropdownObj = {
        selectedTags: initialTags.map(t => ({ id: t.id, name: t.name, color_class: t.color_class })),
        updateBadges: function() {}
      };
      window['currentTagsDropdown_' + entityType + '_' + entityId] = dropdownObj;

      let allTags = [];
      let selectedTags = initialTags.map(t => ({ id: t.id, name: t.name, color_class: t.color_class }));
      let query = '';
      let isLoading = false;

      // Flatten tags from initial data if provided
      if (config.tagsByCategory) {
        (config.tagsByCategory || []).forEach(cat => {
          (cat.tags || []).forEach(tag => {
            allTags.push({ ...tag, category_name: cat.category_name });
          });
        });
      }

      function fetchTags() {
        if (isLoading) return;
        isLoading = true;
        fetch(apiBase + 'tags_list.php')
          .then(r => r.json())
          .then(data => {
            isLoading = false;
            if (data.status === 'ok' && data.data && data.data.tags_by_category) {
              allTags = [];
              (data.data.tags_by_category || []).forEach(cat => {
                (cat.tags || []).forEach(tag => {
                  allTags.push({ ...tag, category_name: cat.category_name });
                });
              });
              renderMenu();
            }
          })
          .catch(() => {
            isLoading = false;
          });
      }

      function renderMenu(showAll = false) {
        if (!tagsListContainer) return;
        tagsListContainer.innerHTML = '';

        const q = String(query || '').toLowerCase().trim();
        const filteredTags = allTags.filter(tag => {
          if (showAll || !q) return true;
          const name = String(tag.name || '').toLowerCase();
          return name.includes(q);
        });

        // Show matching tags grouped by category
        if (filteredTags.length > 0) {
          const byCategory = {};
          filteredTags.forEach(tag => {
            const catName = tag.category_name || 'Other';
            if (!byCategory[catName]) byCategory[catName] = [];
            byCategory[catName].push(tag);
          });

          Object.keys(byCategory).sort().forEach(catName => {
            const header = document.createElement('div');
            header.className = 'fw-bold text-muted small mb-1 mt-2 px-2';
            header.textContent = catName;
            tagsListContainer.appendChild(header);

            byCategory[catName].forEach(tag => {
              const isSelected = selectedTags.some(t => String(t.id) === String(tag.id));
              const item = document.createElement('div');
              item.className = `d-flex align-items-center p-2 ${isSelected ? 'bg-light' : ''}`;
              item.style.cursor = 'pointer';
              item.innerHTML = `
                <span class="badge ${tag.color_class || ''} me-2">${escapeHtml(tag.name)}</span>
                ${isSelected ? '<span class="text-success small">✓</span>' : ''}
              `;
              item.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleTag(tag);
                searchInput.value = '';
                query = '';
                tagsMenu.style.display = 'none';
              });
              tagsListContainer.appendChild(item);
            });
          });
        }

        // Show "Add 'X' as a new tag" option if query doesn't match any existing tag
        if (q && filteredTags.length === 0) {
          const addNewItem = document.createElement('div');
          addNewItem.className = 'd-flex align-items-center p-2';
          addNewItem.style.cursor = 'pointer';
          addNewItem.innerHTML = `
            <span class="text-muted">${escapeHtml(i18n.addNewTag || 'Add')} "${escapeHtml(q)}" ${escapeHtml(i18n.asNewTag || 'as a new tag')}</span>
          `;
          addNewItem.addEventListener('click', function(e) {
            e.stopPropagation();
            createTagFromQuery(q);
          });
          tagsListContainer.appendChild(addNewItem);
        }

        // Show/hide dropdown based on query or showAll flag
        if (q || showAll) {
          tagsMenu.style.display = 'block';
        } else {
          tagsMenu.style.display = 'none';
        }
      }

      function toggleTag(tag) {
        const idx = selectedTags.findIndex(t => String(t.id) === String(tag.id));
        if (idx >= 0) {
          selectedTags.splice(idx, 1);
          updateTag(entityId, tag.id, 'remove');
        } else {
          selectedTags.push({ id: tag.id, name: tag.name, color_class: tag.color_class });
          updateTag(entityId, tag.id, 'add');
        }
        updateBadges();
      }

      function updateTag(entityId, tagId, action) {
        fetch(apiBase + 'tags.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            entity_type: entityType,
            entity_id: entityId,
            tag_id: tagId,
            action: action
          })
        })
          .then(r => r.json())
          .then(data => {
            if (data.status !== 'ok') {
              alert(data.error || (i18n.failedToUpdate || 'Failed to update tag'));
              // Revert selection on error
              if (action === 'add') {
                const idx = selectedTags.findIndex(t => String(t.id) === String(tagId));
                if (idx >= 0) selectedTags.splice(idx, 1);
              } else {
                // Would need to re-add, but we don't have tag details here
                // Best to refresh from server
              }
              updateBadges();
              return;
            }
            if (typeof onTagChange === 'function') {
              onTagChange(entityId, tagId, action);
            }
            if (typeof onTagsUpdated === 'function') {
              onTagsUpdated();
            }
          })
          .catch(() => {
            alert(i18n.networkError || 'Network error occurred');
          });
      }

      function createTagFromQuery(tagName) {
        if (!tagName || !tagName.trim()) return;

        fetch(apiBase + 'tags_create.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ tag_name: tagName.trim() })
        })
          .then(r => r.json())
          .then(data => {
            if (data.status === 'ok' && data.data && data.data.tag) {
              const newTag = data.data.tag;
              newTag.category_name = 'General';
              allTags.push(newTag);
              toggleTag(newTag);
              searchInput.value = '';
              query = '';
              tagsMenu.style.display = 'none';
              // Refresh tags list
              fetchTags();
            } else {
              alert(data.error || (i18n.failedToCreate || 'Failed to create tag'));
            }
          })
          .catch(() => {
            alert(i18n.networkError || 'Network error occurred');
          });
      }

      function updateBadges() {
        tagsBadgesContainer.innerHTML = '';
        if (selectedTags.length) {
          selectedTags.forEach(tag => {
            const badge = document.createElement('span');
            badge.className = `badge ${tag.color_class || ''} me-1 mb-1`;
            badge.innerHTML = `${escapeHtml(tag.name)}<span class="tag-remove" data-tag-id="${tag.id}"> ×</span>`;
            const removeBtn = badge.querySelector('.tag-remove');
            if (removeBtn) {
              removeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleTag(tag);
              });
            }
            tagsBadgesContainer.appendChild(badge);
          });
        }
        // Update stored reference
        dropdownObj.updateBadges = updateBadges;
        dropdownObj.selectedTags = selectedTags;
      }

      // Arrow icon click handler
      if (arrowIcon) {
        arrowIcon.addEventListener('click', function(e) {
          e.stopPropagation();
          e.preventDefault();
          // Ensure edit container is visible
          if (tagsEditContainer) {
            tagsEditContainer.style.display = 'block';
            if (tagsHover) tagsHover.style.display = 'none';
          }
          // Toggle dropdown
          const isHidden = tagsMenu.style.display === 'none' || tagsMenu.style.display === '';
          if (isHidden) {
            // Fetch tags if not already loaded
            if (allTags.length === 0) {
              fetchTags();
            } else {
              renderMenu(true); // Show all tags
            }
          } else {
            tagsMenu.style.display = 'none';
          }
        });
      }

      // Search input handlers
      searchInput.addEventListener('input', function() {
        query = this.value || '';
        renderMenu(false); // Filter based on query
      });

      searchInput.addEventListener('focus', function() {
        if (query) {
          renderMenu(false);
        } else if (tagsMenu.style.display === 'none' || tagsMenu.style.display === '') {
          renderMenu(true); // Show all tags when focusing on empty input
        }
      });

      // Close button handler
      if (closeBtn && tagsEditContainer && tagsHover) {
        closeBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          tagsEditContainer.style.display = 'none';
          tagsHover.style.display = 'none';
          searchInput.value = '';
          query = '';
          if (tagsMenu) tagsMenu.style.display = 'none';
        });
      }

      // Hover click handler to open edit
      if (tagsHover && tagsEditContainer) {
        tagsHover.addEventListener('click', function(e) {
          e.stopPropagation();
          tagsEditContainer.style.display = 'block';
          tagsHover.style.display = 'none';
          // Fetch tags if not already loaded
          if (allTags.length === 0) {
            fetchTags();
          } else {
            renderMenu();
          }
        });
      }

      // Close dropdown when clicking outside
      document.addEventListener('click', function(e) {
        if (!tagsMenu.contains(e.target) && e.target !== searchInput && (!arrowIcon || !arrowIcon.contains(e.target)) && (!tagsDisplay || !tagsDisplay.contains(e.target))) {
          tagsMenu.style.display = 'none';
        }
      });

      // Prevent dropdown from closing when clicking inside
      tagsMenu.addEventListener('click', function(e) {
        e.stopPropagation();
      });

      searchInput.addEventListener('click', function(e) {
        e.stopPropagation();
      });

      searchInput.addEventListener('mousedown', function(e) {
        e.stopPropagation();
      });
    },

    /**
     * Render tags as badges (utility function)
     * @param {string} containerId - ID of container element
     * @param {Array} tags - Array of tag objects {id, name, color_class}
     * @param {boolean} showRemoveBtn - Whether to show remove button
     * @param {Function} onRemove - Callback when remove is clicked (receives tagId)
     * @param {Object} i18n - Internationalization object
     */
    renderTags: function(containerId, tags, showRemoveBtn, onRemove, i18n = {}) {
      const container = qs('#' + containerId);
      if (!container) return;
      container.innerHTML = '';
      (tags || []).forEach(t => {
        const chip = document.createElement('span');
        chip.className = `badge ${t.color_class ? t.color_class : ''}`.trim();
        if (showRemoveBtn && typeof onRemove === 'function') {
          chip.innerHTML = `${escapeHtml(t.name)}<span class="tag-remove" data-tag-id="${t.id}"> ×</span>`;
          const removeBtn = chip.querySelector('.tag-remove');
          if (removeBtn) {
            removeBtn.addEventListener('click', function(e) {
              e.stopPropagation();
              onRemove(t.id);
            });
          }
        } else {
          chip.textContent = t.name;
        }
        container.appendChild(chip);
      });
    }
  };

  // Export to global scope
  window.TagsDropdown = TagsDropdown;
})();

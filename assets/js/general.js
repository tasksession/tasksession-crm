var last_time = 0; // <--- New
var new_time = 0; // <--- New

function tsIcon(name, extraClass) {
	name = String(name || '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
	if (!name) {
		return '';
	}
	extraClass = extraClass ? String(extraClass).replace(/[^a-zA-Z0-9 _-]/g, '').replace(/\s+/g, ' ').trim() : '';
	var cls = 'ts-icon ts-icon-' + name + (extraClass ? ' ' + extraClass : '');
	return '<span class="' + cls + '" aria-hidden="true"></span>';
}
window.tsIcon = tsIcon;
$(".language-selecter ul li").click( function(){
	 if(!$(this).hasClass('active')){
	var thisval = $(this).data('value');
	$('.user_language').val(thisval);
	setTimeout( function(){
		$("form.language-form").submit();
	},500);
	 }
	 return false;
});
 
// Global Tab System for all forms and pages
function initTabSystem() {
    // Handle Bootstrap tabs with data-toggle="tab"
    const bootstrapTabLinks = document.querySelectorAll('[data-toggle="tab"]');
    bootstrapTabLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            if (!targetId || !targetId.startsWith('#')) return;
            
            const contentId = targetId.substring(1);
            const contentElement = document.getElementById(contentId);
            if (!contentElement) return;
            
            // Find the tab navigation container
            const navContainer = this.closest('.nav-tabs');
            if (!navContainer) return;
            
            // Find the corresponding tab content container
            let tabContentContainer = navContainer.nextElementSibling;
            if (!tabContentContainer || !tabContentContainer.classList.contains('tab-content')) {
                // e.g. task sidebar: ul is inside .modal-tabs-scroll, .tab-content is a sibling below
                const section = navContainer.closest('.task-tabs-section');
                const searchRoot = section || navContainer.parentElement;
                tabContentContainer = searchRoot ? searchRoot.querySelector('.tab-content') : null;
                if (!tabContentContainer) return;
            }
            
            // Remove active class from all tabs in this navigation
            const allTabLinks = navContainer.querySelectorAll('.nav-link');
            allTabLinks.forEach(tab => tab.classList.remove('active'));
            
            // Remove active class from all tab contents
            const allTabContents = tabContentContainer.querySelectorAll('.tab-pane');
            allTabContents.forEach(content => content.classList.remove('show', 'active'));
            
            // Add active class to clicked tab and its content
            this.classList.add('active');
            contentElement.classList.add('show', 'active');
        });
    });
    
    // Handle custom tabs without data-toggle
    const customTabContainers = document.querySelectorAll('.tab-content:not([data-toggle="tab"])');
    customTabContainers.forEach(container => {
        const tabLinks = container.querySelectorAll('.nav-link');
        const tabContents = container.querySelectorAll('.tab-pane');
        
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId && targetId.startsWith('#')) {
                    e.preventDefault();
                    
                    const contentId = targetId.substring(1);
                    const contentElement = document.getElementById(contentId);
                    
                    if (contentElement) {
                        tabLinks.forEach(tab => tab.classList.remove('active'));
                        tabContents.forEach(content => {
                            if (content) content.classList.remove('show', 'active');
                        });
                        
                        this.classList.add('active');
                        contentElement.classList.add('show', 'active');
                    }
                }
            });
        });
    });
    
    // Handle direct linking to tabs from URL hash
    if(window.location.hash) {
        const hash = window.location.hash.substring(1);
        const tabLink = document.querySelector(`a[href="#${hash}"]`);
        if(tabLink) {
            tabLink.click();
        }
    }
}

// Global Password Visibility Toggle
function initPasswordVisibilityToggle() {
    const passwordFields = document.querySelectorAll('.passwordfield, input[type="password"]');

    passwordFields.forEach(passwordField => {
        const row = passwordField.closest('.eyes-row') || passwordField.parentElement;
        if (!row) {
            return;
        }
        const toggleIcon = row.querySelector('.eye');
        const eyeOpen = row.querySelector('.eye-open, #eyeOpen');
        const eyeClosed = row.querySelector('.eye-closed, #eyeClosed');

        if (!toggleIcon || toggleIcon.dataset.tsEyeBound === '1') {
            return;
        }
        if (!eyeOpen || !eyeClosed) {
            return;
        }
        toggleIcon.dataset.tsEyeBound = '1';

        const syncIcons = function () {
            const showing = passwordField.type !== 'password';
            eyeOpen.style.display = showing ? 'inline' : 'none';
            eyeClosed.style.display = showing ? 'none' : 'inline';
            toggleIcon.setAttribute('aria-pressed', showing ? 'true' : 'false');
        };

        const toggle = function (e) {
            if (e) {
                e.preventDefault();
            }
            passwordField.type = passwordField.type === 'password' ? 'text' : 'password';
            syncIcons();
        };

        toggleIcon.addEventListener('click', toggle);
        toggleIcon.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                toggle(e);
            }
        });
        syncIcons();
    });
}

// Multi-step form functionality
function initMultiStepForm() {
    const nextButton = document.getElementById('next-to-permissions');
    const accountSubmitBox = document.getElementById('account-submit-box');
    const permissionsSubmitBox = document.getElementById('permissions-submit-box');
    const permissionsTab = document.getElementById('permissions-tab');
    
    if (nextButton && accountSubmitBox && permissionsSubmitBox && permissionsTab) {
        // Function to validate required fields in account tab
        function validateAccountFields() {
            const requiredFields = [
                'firstName',
                'email',
                'password'
            ];
            
            let isValid = true;
            const errors = [];
            
            requiredFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    const value = field.value.trim();
                    if (!value) {
                        isValid = false;
                        errors.push(fieldName);
                        // Add visual error indication
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                }
            });
            
            // Special validation for email format
            const emailField = document.querySelector('[name="email"]');
            if (emailField && emailField.value.trim()) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailField.value.trim())) {
                    isValid = false;
                    emailField.classList.add('is-invalid');
                    errors.push('email');
                }
            }
            
            return { isValid, errors };
        }
        
        // Function to show validation message
        function showValidationMessage(message, type = 'error') {
            // Remove existing validation message
            const existingMessage = document.querySelector('.validation-message');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            // Create new validation message
            const messageDiv = document.createElement('div');
            messageDiv.className = `validation-message alert alert-${type === 'error' ? 'danger' : 'success'} mt-2`;
            messageDiv.textContent = message;
            
            // Insert after the next button
            nextButton.parentNode.appendChild(messageDiv);
            
            // Auto-remove success message after 3 seconds
            if (type === 'success') {
                setTimeout(() => {
                    if (messageDiv.parentNode) {
                        messageDiv.remove();
                    }
                }, 3000);
            }
        }
        
        // Next button functionality with validation
        nextButton.addEventListener('click', function() {
            const validation = validateAccountFields();
            
            if (validation.isValid) {
                // Switch to permissions tab
                permissionsTab.click();
                
                            // Show permissions submit box and hide account submit box
            accountSubmitBox.style.display = 'none';
            permissionsSubmitBox.style.display = 'flex';
                
                // Show success message
                const successMessage = window.lang && window.lang['Account information validated successfully!'] 
                    ? window.lang['Account information validated successfully!'] 
                    : 'Account information validated successfully!';
                showValidationMessage(successMessage, 'success');
            } else {
                // Show error message
                const fieldNames = validation.errors.map(field => {
                    switch(field) {
                        case 'firstName': return window.lang && window.lang['Full Name'] ? window.lang['Full Name'] : 'Full Name';
                        case 'email': return window.lang && window.lang['Email'] ? window.lang['Email'] : 'Email';
                        case 'password': return window.lang && window.lang['Password'] ? window.lang['Password'] : 'Password';
                        default: return field;
                    }
                });
                const errorMessage = window.lang && window.lang['Please fill in the following required fields'] 
                    ? `${window.lang['Please fill in the following required fields']}: ${fieldNames.join(', ')}`
                    : `Please fill in the following required fields: ${fieldNames.join(', ')}`;
                showValidationMessage(errorMessage);
                
                // Scroll to first error field
                const firstErrorField = document.querySelector('.is-invalid');
                if (firstErrorField) {
                    firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstErrorField.focus();
                }
            }
        });
        
        // Real-time validation on field changes
        const requiredFields = ['firstName', 'email', 'password'];
        requiredFields.forEach(fieldName => {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.addEventListener('input', function() {
                    if (this.value.trim()) {
                        this.classList.remove('is-invalid');
                        // Remove validation message if all fields are now valid
                        const validation = validateAccountFields();
                        if (validation.isValid) {
                            const existingMessage = document.querySelector('.validation-message');
                            if (existingMessage) {
                                existingMessage.remove();
                            }
                        }
                    }
                });
            }
        });
        
        // Handle tab switching to show/hide appropriate submit boxes
        const tabLinks = document.querySelectorAll('[data-toggle="tab"]');
        tabLinks.forEach(link => {
            link.addEventListener('click', function() {
                const targetId = this.getAttribute('href');
                if (targetId === '#account') {
                    accountSubmitBox.style.display = 'flex';
                    permissionsSubmitBox.style.display = 'none';
                } else if (targetId === '#permissions') {
                    accountSubmitBox.style.display = 'none';
                    permissionsSubmitBox.style.display = 'flex';
                }
            });
        });
    }
}

// Initialize all global features when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tab system if tabs exist on the page
    if(document.querySelector('.tab-content, .nav-tabs, [data-toggle="tab"]')) {
        initTabSystem();
    }
    
    // Initialize password visibility toggle if password fields exist
    if(document.querySelector('.passwordfield')) {
        initPasswordVisibilityToggle();
    }
    
    // Initialize multi-step form if next button exists
    // Skip if the page uses the newer accounts.js implementation
    if (document.getElementById('next-to-permissions') && !document.querySelector('form[data-accounts-js="1"]')) {
        initMultiStepForm();
    }
});

 
$(".mobile-menu .ts-icon, .mobile-menu svg, .mobile-menu i").on("click", function(){
	$(".sidebar-admin.col-md-3").animate({left:0});
});
$(".cross-mobile").on("click", function(e){
	// Do not close CRM nav when Ask AI / chat-thread close (X) is clicked
	if ($(this).closest('#aiAssistantRail, #aiSidebar, .ai-assistant-rail, .ai-thread-col').length) {
		e.preventDefault();
		e.stopImmediatePropagation();
		return false;
	}
	$(".sidebar-admin.col-md-3").animate({left:'-107%'});
});

var winWidth = $(window).width();
if(winWidth <= 767){
$("#messages-stack-list").on("click", '.prepare-message', function(event) {
	$(".messages-box .messageWrapper").animate({
		right: 0
	});
	$(".messages-box .messageWrapper").removeClass("animatedBox");
});
$(".mobilearr").click( function(){
	$(this).parent().animate({
		right: '-100%'
	});
	$(this).parent().addClass("animatedBox");
});
$("#text-messages-request").on("click", ".mobilesmenu", function(){

$(".buttons-cont").fadeToggle("fast");

});

}
$(".btn-action").click( function(e){e.preventDefault();});
var client_val = $("select[name=client]").val();
$('.new_val').val(client_val);
$("select[name=client]").change(function() {
	$('.new_val').val($(this).val());
});


  $("#protbl-input").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#projects-tbl tr").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
  });
  $("#staff-searchnew").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $(".col-sm-3.staff").filter(function() {
      $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
	  $(".norecords").hide();
 if($('.staff:visible').length == 0)
            {
                $(".norecords").show();
            }
    });
  });
$(':checkbox[name=btSelectAll]').click (function () {
var alltrs = $(this).parent().parent().parent().parent().find('tr');
	$(':checkbox[name=btSelectItem]').prop('checked', this.checked);
 var checkbox_val = [];
            $.each($("input[name='btSelectItem']:checked"), function(){            
                checkbox_val.push($(this).val());
            });
			var check_fcal = checkbox_val.join(", ");
            console.log("My favourite sports are: " + check_fcal);
			$(".bulk_ids").val(check_fcal);
			if(check_fcal){
				$(".pm-trash").addClass('active');
				$('button[name="bulk_del_proj"]').prop('disabled','');
			} else {
				$(".pm-trash").removeClass('active');
				$('button[name="bulk_del_proj"]').prop('disabled','disabled');
			}
			
});
$(':checkbox[name=btSelectItem]').click( function(){
var checkbox_val = [];
            $.each($("input[name='btSelectItem']:checked"), function(){            
                checkbox_val.push($(this).val());
            });
			var check_fcal = checkbox_val.join(", ");

			$(".bulk_ids").val(check_fcal);
			if(check_fcal){
				$(".pm-trash").addClass('active');
				$('button[name="bulk_del_proj"]').prop('disabled','');
			} else {
				$(".pm-trash").removeClass('active');
				$('button[name="bulk_del_proj"]').prop('disabled','disabled');
			}
});
$(':checkbox[name=client-checkbox]').click( function(){
var checkbox_val = [];
            $.each($("input[name='client-checkbox']:checked"), function(){            
                checkbox_val.push($(this).val());
            });
			var check_ucal = checkbox_val.join(", ");

			$(".bulk_uids").val(check_ucal);
			if(check_ucal){
				$(".um-trash").addClass('active');
				$('button[name="bulk_del_user"]').prop('disabled','');
			} else {
				$(".um-trash").removeClass('active');
				$('button[name="bulk_del_user"]').prop('disabled','disabled');
			}
});

// Select-all for client bulk checkboxes (works for table + grid)
$(document).on('change', '#select-all-clients', function () {
	const checked = $(this).is(':checked');
	$("input[name='client-checkbox']").prop('checked', checked).trigger('click');
});


// Global Bootstrap dropdown and search functionality
document.addEventListener('DOMContentLoaded', function() {
  // Initialize Bootstrap dropdowns properly for all pages
  initializeBootstrapDropdowns();
  
  // Initialize global search functionality
  initializeGlobalSearch();
  
  // Initialize action toggle dropdowns for all pages
  initializeActionToggles();
});

// Initialize Bootstrap dropdowns properly
function initializeBootstrapDropdowns() {
  if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
    // Only initialize dropdown elements (not collapse elements)
    const dropdownElements = document.querySelectorAll('[data-bs-toggle="dropdown"]:not([data-bs-toggle="collapse"])');
    dropdownElements.forEach(element => {
      const dropdownInstance = bootstrap.Dropdown.getInstance(element);
      if (dropdownInstance) {
        dropdownInstance.dispose();
      }
      // Create new dropdown instance
      new bootstrap.Dropdown(element);
    });
  }
}

// Initialize global search functionality
function initializeGlobalSearch() {
  const searchInput = document.getElementById('task-search');

  // Search functionality for projects table (skip if AJAX search is enabled)
  const projectsTable = document.querySelector('#projects-tbl');
  if (searchInput && projectsTable && !window.ajaxProjectSearchEnabled) {
    searchInput.addEventListener('keyup', function() {
      const input = this.value.toUpperCase();
      const rows = projectsTable.getElementsByTagName('tr');

      for (let i = 0; i < rows.length; i++) {
        const projectName = rows[i].getElementsByTagName('td')[1];
        if (projectName) {
          const txtValue = projectName.textContent || projectName.innerText;
          rows[i].style.display = (txtValue.toUpperCase().indexOf(input) > -1) ? '' : 'none';
        }
      }
    });
  }

  // Search functionality for tasks table
  const tasksTable = document.getElementById('tasks-table');
  if (searchInput && tasksTable && !projectsTable) {
    searchInput.addEventListener('keyup', function() {
      const input = this.value.toUpperCase();
      const tr = tasksTable.getElementsByTagName('tr');
      
      for (let i = 0; i < tr.length; i++) {
        if (i === 0) continue; // Skip header row
        
        // Search in title and description
        const td = tr[i].getElementsByTagName('td')[1];
        if (td) {
          const txtValue = td.textContent || td.innerText;
          if (txtValue.toUpperCase().indexOf(input) > -1) {
            tr[i].style.display = '';
          } else {
            // Also check project name
            const projectTd = tr[i].getElementsByTagName('td')[2];
            const projectTxtValue = projectTd ? (projectTd.textContent || projectTd.innerText) : '';
            tr[i].style.display = (projectTxtValue.toUpperCase().indexOf(input) > -1) ? '' : 'none';
          }
        }
      }
    });
  }

  // Search functionality for staff list
  const staffSearchInput = document.getElementById('staff-searchnew');
  if (staffSearchInput) {
    staffSearchInput.addEventListener('keyup', function() {
      const value = this.value.toLowerCase();
      const staffElements = document.querySelectorAll('.col-sm-3.staff');
      
      staffElements.forEach(element => {
        const text = element.textContent.toLowerCase();
        if (text.indexOf(value) > -1) {
          element.style.display = '';
        } else {
          element.style.display = 'none';
        }
      });
      
      // Handle no records message
      const noRecords = document.querySelector('.norecords');
      if (noRecords) {
        const visibleStaff = document.querySelectorAll('.col-sm-3.staff:not([style*="display: none"])');
        if (visibleStaff.length === 0) {
          noRecords.style.display = '';
        } else {
          noRecords.style.display = 'none';
        }
      }
    });
  }
}

// Action menus (.action-toggle + .toggle-action): do not use Bootstrap Collapse.
// Collapse JS + jQuery .toggle() + a second click binder left menus stuck after the first open.
function tsActionMenuTarget(toggle) {
  if (!toggle || !toggle.classList || !toggle.classList.contains('action-toggle')) {
    return null;
  }
  if (toggle.hasAttribute('data-ts-filter-panel') || toggle.classList.contains('ts-ecom-filter-toggle')) {
    return null;
  }
  var selector = toggle.getAttribute('data-bs-target') || toggle.getAttribute('data-target');
  var menu = null;
  if (selector && selector !== '#') {
    try {
      menu = document.querySelector(selector);
    } catch (err) {
      menu = null;
    }
  }
  if (!menu && toggle.nextElementSibling && toggle.nextElementSibling.classList.contains('toggle-action')) {
    menu = toggle.nextElementSibling;
  }
  if (!menu || !menu.classList.contains('toggle-action')) {
    return null;
  }
  return menu;
}

function tsActionMenuToggleFor(menu) {
  if (!menu) {
    return null;
  }
  if (menu.id) {
    var escaped = (window.CSS && CSS.escape) ? CSS.escape(menu.id) : menu.id.replace(/"/g, '\\"');
    var byAttr = document.querySelector('.action-toggle[data-bs-target="#' + escaped + '"], .action-toggle[data-target="#' + escaped + '"]');
    if (byAttr) {
      return byAttr;
    }
  }
  var prev = menu.previousElementSibling;
  return (prev && prev.classList.contains('action-toggle')) ? prev : null;
}

function tsDetachActionMenuCollapse(toggle, menu) {
  if (typeof bootstrap !== 'undefined' && bootstrap.Collapse && menu) {
    var inst = bootstrap.Collapse.getInstance(menu);
    if (inst) {
      inst.dispose();
    }
  }
  if (toggle) {
    if (toggle.getAttribute('data-bs-toggle') === 'collapse') {
      toggle.removeAttribute('data-bs-toggle');
    }
    if (toggle.getAttribute('data-toggle') === 'collapse') {
      toggle.removeAttribute('data-toggle');
    }
  }
  if (menu) {
    menu.classList.remove('collapsing');
    menu.style.height = '';
    menu.style.overflow = '';
  }
}

function tsResetActionMenuPosition(menu) {
  if (!menu) {
    return;
  }
  menu.classList.remove('ts-dropup', 'ts-action-menu-measure');
  menu.style.position = '';
  menu.style.top = '';
  menu.style.bottom = '';
  menu.style.left = '';
  menu.style.right = '';
  menu.style.marginTop = '';
  menu.style.marginBottom = '';
  menu.style.zIndex = '';
}

function tsCloseActionMenu(menu) {
  if (!menu) {
    return;
  }
  var wasOpen = menu.classList.contains('show') || menu.classList.contains('in') || menu.classList.contains('collapsing');
  menu.classList.remove('show', 'in', 'collapsing');
  tsResetActionMenuPosition(menu);
  menu.style.height = '';
  menu.style.overflow = '';
  var toggle = tsActionMenuToggleFor(menu);
  if (toggle) {
    toggle.classList.remove('show');
    toggle.setAttribute('aria-expanded', 'false');
  }
  if (wasOpen) {
    menu.dispatchEvent(new Event('hidden.bs.collapse', { bubbles: true }));
  }
}

function tsCloseAllActionMenus(exceptMenu) {
  document.querySelectorAll('.toggle-action.show, .toggle-action.in, .toggle-action.collapsing').forEach(function (menu) {
    if (menu !== exceptMenu) {
      tsCloseActionMenu(menu);
    }
  });
}

function tsActionMenuBottomLimit(toggle) {
  var limit = window.innerHeight;
  if (!toggle || !toggle.getBoundingClientRect) {
    return limit;
  }
  var toggleRect = toggle.getBoundingClientRect();
  var root = toggle.closest('[data-ts-list-bulk-root], .ts-ecommerce-wrap, .page-content');
  var scope = root || document;
  scope.querySelectorAll('.pagination-box').forEach(function (el) {
    var r = el.getBoundingClientRect();
    if (r.height <= 0 || r.top <= toggleRect.bottom) {
      return;
    }
    if (r.top < limit) {
      limit = r.top;
    }
  });
  return limit;
}

function tsPlaceActionMenu(toggle, menu) {
  tsResetActionMenuPosition(menu);
  menu.classList.add('ts-action-menu-measure');
  var menuH = Math.max(menu.scrollHeight || 0, menu.offsetHeight || 0);
  if (!menuH) {
    menuH = Math.max(40, menu.querySelectorAll('li').length * 36 + 10);
  }
  var menuW = Math.max(menu.offsetWidth || 0, 175);
  menu.classList.remove('ts-action-menu-measure');

  var toggleRect = toggle.getBoundingClientRect();
  var gap = 4;
  var bottomLimit = tsActionMenuBottomLimit(toggle);
  var spaceBelow = bottomLimit - toggleRect.bottom - gap;
  var spaceAbove = toggleRect.top - gap;
  var dropup = spaceBelow < menuH && (spaceAbove >= menuH || spaceAbove > spaceBelow);

  var left = toggleRect.right - menuW;
  left = Math.max(8, Math.min(left, window.innerWidth - menuW - 8));

  menu.style.position = 'fixed';
  menu.style.zIndex = '1080';
  menu.style.right = 'auto';
  menu.style.left = left + 'px';
  menu.style.marginTop = '0px';
  if (dropup) {
    menu.classList.add('ts-dropup');
    menu.style.top = 'auto';
    menu.style.bottom = (window.innerHeight - toggleRect.top + gap) + 'px';
  } else {
    menu.style.bottom = 'auto';
    menu.style.top = (toggleRect.bottom + gap) + 'px';
  }
}

function tsOpenActionMenu(toggle, menu) {
  tsDetachActionMenuCollapse(toggle, menu);
  menu.classList.remove('in', 'collapsing');
  menu.style.height = '';
  menu.style.overflow = '';
  tsPlaceActionMenu(toggle, menu);
  menu.classList.add('show');
  if (toggle) {
    toggle.classList.add('show');
    toggle.setAttribute('aria-expanded', 'true');
  }
  menu.dispatchEvent(new Event('shown.bs.collapse', { bubbles: true }));
}

function tsActionMenuEventTarget(e) {
  var node = e && e.target;
  if (!node) {
    return null;
  }
  if (node.nodeType === 3) {
    node = node.parentElement;
  }
  if (!node || !node.closest) {
    return null;
  }
  // Clear (X) inside Filters must navigate — not open/close the menu
  if (node.closest('.kanban-filter-clear')) {
    return null;
  }
  var toggle = node.closest('.action-toggle');
  if (toggle) {
    return toggle;
  }
  var ellipsis = node.closest('.mobile-ellipsis');
  return ellipsis ? ellipsis.closest('.action-toggle') : null;
}

function initializeActionToggles() {
  document.querySelectorAll('.action-toggle').forEach(function (toggle) {
    var menu = tsActionMenuTarget(toggle);
    if (menu) {
      tsDetachActionMenuCollapse(toggle, menu);
    }
  });

  if (window.__tsActionTogglesBound) {
    return;
  }
  window.__tsActionTogglesBound = true;

  document.addEventListener('click', function (e) {
    var toggle = tsActionMenuEventTarget(e);
    if (!toggle) {
      return;
    }
    var menu = tsActionMenuTarget(toggle);
    if (!menu) {
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    tsDetachActionMenuCollapse(toggle, menu);
    var isOpen = menu.classList.contains('show') || menu.classList.contains('in');
    tsCloseAllActionMenus(menu);
    if (isOpen) {
      tsCloseActionMenu(menu);
    } else {
      tsOpenActionMenu(toggle, menu);
    }
  }, true);

  document.addEventListener('click', function (e) {
    if (tsActionMenuEventTarget(e) || (e.target && e.target.closest && e.target.closest('.toggle-action'))) {
      return;
    }
    tsCloseAllActionMenus(null);
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      tsCloseAllActionMenus(null);
    }
  });

  window.addEventListener('resize', function () {
    tsCloseAllActionMenus(null);
  });
  var ignoreScrollUntil = 0;
  var origOpen = tsOpenActionMenu;
  tsOpenActionMenu = function (toggle, menu) {
    ignoreScrollUntil = Date.now() + 400;
    origOpen(toggle, menu);
  };
  document.addEventListener('scroll', function () {
    if (Date.now() < ignoreScrollUntil) {
      return;
    }
    tsCloseAllActionMenus(null);
  }, true);
}
window.chatComposerTooltipSelector =
    '#type.send-box [data-bs-toggle="tooltip"], .send-box [data-bs-toggle="tooltip"], .chat-toolbar-left [data-bs-toggle="tooltip"], .message-btn-target [data-bs-toggle="tooltip"]';

window.isChatComposerTooltipEl = function (el) {
    if (!el || !el.closest) {
        return false;
    }
    return !!(el.closest('.send-box') || el.closest('.chat-toolbar-left') || el.closest('.message-btn-target'));
};

window.chatComposerTooltipsEnabled = function () {
    return !(window.matchMedia && window.matchMedia('(max-width: 768px)').matches);
};

window.chatDisposeComposerTooltips = function (root) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
        return;
    }
    var scope = root && root.querySelectorAll ? root : document;
    var nodes = scope.querySelectorAll(window.chatComposerTooltipSelector);
    nodes.forEach(function (el) {
        try {
            var inst = bootstrap.Tooltip.getInstance(el);
            if (inst) {
                inst.hide();
                inst.dispose();
            }
        } catch (err) {}
    });
};

/** Chat composer tooltips (attach, emoji, send, etc.) — desktop only. */
window.chatInitComposerTooltips = function (root) {
    var scope = root && root.querySelectorAll ? root : document;
    var nodes = scope.querySelectorAll(window.chatComposerTooltipSelector);
    if (!nodes.length) {
        return;
    }
    if (!window.chatComposerTooltipsEnabled()) {
        window.chatDisposeComposerTooltips(scope);
        return;
    }
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        nodes.forEach(function (el) {
            try {
                if (bootstrap.Tooltip.getInstance(el)) {
                    return;
                }
                new bootstrap.Tooltip(el, {
                    trigger: 'hover',
                    placement: el.getAttribute('data-bs-placement') || 'top',
                    html: false,
                    customClass: 'custom-tooltip chat-composer-tooltip',
                    container: 'body',
                    offset: [0, 8]
                });
            } catch (err) {}
        });
    }
};

// Tooltip initialization with custom styling
function initializeTooltips() {
    // Initialize Bootstrap tooltips with custom styling
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            if (window.isChatComposerTooltipEl && window.isChatComposerTooltipEl(tooltipTriggerEl)) {
                return;
            }
            if (bootstrap.Tooltip.getInstance(tooltipTriggerEl)) {
                return;
            }
            new bootstrap.Tooltip(tooltipTriggerEl, {
                trigger: 'hover',
                placement: 'top',
                html: false,
                customClass: 'custom-tooltip',
                container: 'body'
            });
        });
        // console.log('Tooltips initialized successfully with custom styling');
    } else {
        // console.log('Bootstrap JS not loaded, tooltips will be initialized when available');
    }
}
// Server-side list pages render correct start/end in .pagination-box; do not overwrite.
$(".upload-up").click( function(){
	$(this).parent().parent().find("input").click();
	return false;
});
$(function() {
    // Multiple images preview in browser
    var imagesPreview = function(input, placeToInsertImagePreview) {

        if (input.files) {
            var filesAmount = input.files.length;

            for (i = 0; i < filesAmount; i++) {
                var reader = new FileReader();

                reader.onload = function(event) {
                    $($.parseHTML('<img>')).attr('src', event.target.result).appendTo(placeToInsertImagePreview);
                }

                reader.readAsDataURL(input.files[i]);
            }
        }

    };

    $('input[type="file"]').on('change', function() {
		$(this).parent().find('div.uploaded-img').css('display','block');
		$(this).parent().find('div.uploaded-img').html('');
        imagesPreview(this, $(this).parent().find('div.uploaded-img'));
		$(this).parent().find(".remove-up").css('display','block');
    });
});
$(".remove-up").click( function(){
	$(this).hide();
	$(this).parent().parent().find('div.uploaded-img').hide();
	$(this).parent().parent().find(".uploaded-img").html('');
	$(this).parent().parent().find('input[type="file"]').val('');
	return false;
});
$(".setup-btn").click( function(){
	$(this).parent().parent().parent().find('.payment-fields').toggle();
	return false;
});
/* Image Upload Profile */
$('#imgInpb').change( function(event) {
var tmppath = URL.createObjectURL(event.target.files[0]);
    $("img#blah").attr('src', tmppath);
    $(".img-uploadwrap img").attr('src', tmppath);
});
$('#imgInp').change( function(event) {
var tmppath = URL.createObjectURL(event.target.files[0]);
    $(".upload-profile-pic .img-uploadwrap img, .upload-pro-pic .img-uploadwrap img").attr('src',URL.createObjectURL(event.target.files[0]));
	$(".upload-profile-pic .img-uploadwrap img, .upload-pro-pic .img-uploadwrap img").css('opacity','0.4');
var formData = new FormData();
formData.append('pro-pic', $(this)[0].files[0]);
formData.append('editClient', $(this).data('clientid'));
	 uploadFile(formData);
	
});
function profilePicUploadNotify(message, type) {
    var cfg = window.__profilePicToast || {};
    var msg = message;
    if (!msg) {
        msg = type === 'success'
            ? (cfg.success || 'Profile picture updated.')
            : (cfg.fail || 'Image could not be uploaded.');
    }
    if (typeof window.showToast === 'function') {
        window.showToast(msg, type === 'success' ? 'success' : 'error');
        return;
    }
    if (type === 'success') {
        $(".imageupates").show();
        $(".imageupatesfail").hide();
    } else {
        $(".imageupates").hide();
        $(".imageupatesfail").show();
        if (msg) {
            $(".imageupatesfail p").text(msg);
        }
    }
}
function uploadFile(formData)
{
   if (window.csrfToken && formData && typeof formData.append === 'function') {
       formData.append('csrf_token', window.csrfToken);
   }
   $.ajax({
	   type: "POST",
	   url: $.base_url + "ajax/ajax_profilepic.php",
      data : formData,
       processData: false,
       contentType: false,
       dataType: 'json',
       headers: window.csrfToken ? { 'X-CSRF-TOKEN': window.csrfToken } : {},
       success : function(data) {
           console.log('Upload response:', data);
		   // Handle both JSON and plain text responses (for backward compatibility)
		   var responseData = data;
		   if (typeof data === 'string') {
			   try {
				   responseData = JSON.parse(data);
			   } catch(e) {
				   // If it's not JSON, treat 'ok' as success
				   if(data.trim() === 'ok') {
					   responseData = {status: 'ok'};
				   } else {
					   responseData = {status: 'error', message: data};
				   }
			   }
		   }
		   
		   if(responseData && responseData.status == 'ok'){
			   // Update image source with the actual server image URL
			   var $img = $(".upload-profile-pic .img-uploadwrap img, .upload-pro-pic .img-uploadwrap img");
			   if(responseData.url) {
				   // Add cache-busting parameter to force reload
				   var imageUrl = responseData.url;
				   // Replace or add cache-busting parameter
				   if(imageUrl.indexOf('&cb=') > -1 || imageUrl.indexOf('?cb=') > -1) {
					   imageUrl = imageUrl.replace(/[&?]cb=\d+/, '') + (imageUrl.indexOf('?') > -1 ? '&' : '?') + 'cb=' + new Date().getTime();
				   } else {
					   imageUrl = imageUrl + (imageUrl.indexOf('?') > -1 ? '&' : '?') + 't=' + new Date().getTime();
				   }
				   console.log('Updating image src to:', imageUrl);
				   $img.attr('src', imageUrl);
				   // Force image reload by creating a new image object
				   var newImg = new Image();
				   newImg.onload = function() {
					   $img.attr('src', imageUrl);
				   };
				   newImg.src = imageUrl;
			   } else {
				   // If no URL in response, reload the image with cache-busting
				   var currentSrc = $img.attr('src');
				   if(currentSrc && currentSrc.indexOf('thumbnail.php') > -1) {
					   var newSrc = currentSrc.split('&cb=')[0].split('?cb=')[0] + (currentSrc.indexOf('?') > -1 ? '&' : '?') + 'cb=' + new Date().getTime();
					   $img.attr('src', newSrc);
				   }
			   }
			   profilePicUploadNotify(
				   (responseData && responseData.message) ? responseData.message : null,
				   'success'
			   );
		   } else{
			   profilePicUploadNotify(
				   (responseData && responseData.message) ? responseData.message : null,
				   'error'
			   );
		   }
		   $(".upload-profile-pic .img-uploadwrap img, .upload-pro-pic .img-uploadwrap img").css('opacity','1');
       },
       error: function(xhr, status, error) {
           console.error('Upload error:', error);
           $(".upload-profile-pic .img-uploadwrap img, .upload-pro-pic .img-uploadwrap img").css('opacity','1');
           var errorMessage = null;
           try {
               var errorData = JSON.parse(xhr.responseText);
               if(errorData && errorData.message) {
                   errorMessage = errorData.message;
               }
           } catch(e) {
               errorMessage = (window.__profilePicToast && window.__profilePicToast.error)
                   ? window.__profilePicToast.error
                   : 'An error occurred during upload';
           }
           profilePicUploadNotify(errorMessage, 'error');
       }
    });
}
/* Image Upload Profile End*/
  $(".collapse").on('shown.bs.collapse', function(){
        $(this).prev().addClass('show');
    });
    $(".collapse").on('hidden.bs.collapse', function(){
        $(this).prev().removeClass('show');
    });

var today = new Date();
var dd = today.getDate();
var mm = today.getMonth() + 1; //January is 0!

var yyyy = today.getFullYear();
if (dd < 10) {
  dd = '0' + dd;
} 
if (mm < 10) {
  mm = '0' + mm;
} 
var today = yyyy + '-' + mm + '-' + dd;
 $(".status1").change(function () {
        var thisval = this.value;
		if(thisval == '1'){
        $(".releaseDate").val(today);
		} else{
		$(".releaseDate").val('1970-01-01');	
		}
    });

// Initialize tooltips for staff avatars
document.addEventListener('DOMContentLoaded', function() {
  // Add CSS for date input cursor
  const style = document.createElement('style');
  style.textContent = `
    input[type="date"]:hover {
      cursor: pointer;
    }
  `;
  document.head.appendChild(style);
  
  // Initialize tooltips with custom styling
  initializeTooltips();
  if (window.chatComposerTooltipsEnabled && !window.chatComposerTooltipsEnabled()) {
    window.chatDisposeComposerTooltips(document);
  }
  
  // Add filtering functionality for all tasks view
  if (document.getElementById('project-filter')) {
    setupTaskFilters();
  }
  
  // Setup mobile scroll indicators and touch handling
  setupMobileScrolling();
  
  // Date input: open picker on focus/click
  document.querySelectorAll('input[type="date"]').forEach(function(input) {
            // console.log('Setting up date picker for:', input);
    input.addEventListener('focus', function(e) {
      // Do NOT call showPicker() here to avoid NotAllowedError
      // Focus events are not always considered user gestures
    });
    input.addEventListener('click', function(e) {
              // console.log('Date input clicked, attempting to show picker');
      if (this.showPicker) {
        this.showPicker();
      } else {
        // console.log('showPicker method not available');
      }
    });
  });
});

// Function to scroll board left
function scrollBoardLeft() {
  const boardWrap = document.querySelector('.board-wrap');
  if (boardWrap) {
    const scrollAmount = 350; // One column width
    boardWrap.scrollBy({
      left: -scrollAmount,
      behavior: 'smooth'
    });
  }
}

// Function to scroll board right
function scrollBoardRight() {
  const boardWrap = document.querySelector('.board-wrap');
  if (boardWrap) {
    const scrollAmount = 350; // One column width
    boardWrap.scrollBy({
      left: scrollAmount,
      behavior: 'smooth'
    });
  }
}

// Remove the custom mouse wheel handler for horizontal scrolling
// Only keep the 'scrolled-right' class toggle for visual effect
$(function() {
  const boardWrap = document.querySelector('.board-wrap');
  if (boardWrap) {
    // Toggle 'scrolled-right' class
    boardWrap.addEventListener('scroll', function() {
      if (boardWrap.scrollLeft > 20) {
        boardWrap.classList.add('scrolled-right');
      } else {
        boardWrap.classList.remove('scrolled-right');
      }
    });
  }
});

// Setup mobile scrolling behavior (native touch scroll on phones; mouse drag on desktop only)
function setupMobileScrolling() {
  const boardWrap = document.querySelector('.board-wrap');
  if (!boardWrap) {
    return;
  }

  boardWrap.classList.add('board-wrap--native-touch-scroll');

  let isDown = false;
  let startX;
  let scrollLeft;
  const useMouseDragScroll = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  if (useMouseDragScroll) {
    boardWrap.addEventListener('mousedown', (e) => {
      if (e.button !== 0) return;
      if (e.target.closest('a, button, input, textarea, select, label, .dropdown-menu, .task-card')) return;
      if (boardWrap.classList.contains('scroll-locked') || isBoardFrozen) return;
      isDown = true;
      boardWrap.classList.add('is-grabbing');
      startX = e.pageX;
      scrollLeft = boardWrap.scrollLeft;
    });

    boardWrap.addEventListener('mouseleave', () => {
      isDown = false;
      boardWrap.classList.remove('is-grabbing');
    });

    boardWrap.addEventListener('mouseup', () => {
      isDown = false;
      boardWrap.classList.remove('is-grabbing');
    });

    boardWrap.addEventListener('mousemove', (e) => {
      if (!isDown || isBoardFrozen) return;
      e.preventDefault();
      const walk = (e.pageX - startX) * 2;
      boardWrap.scrollLeft = scrollLeft - walk;
    });
  }

  boardWrap.addEventListener('scroll', () => {
    if (boardWrap.scrollLeft > 20) {
      boardWrap.classList.add('scrolled-right');
    } else {
      boardWrap.classList.remove('scrolled-right');
    }

    const isAtEnd = boardWrap.scrollLeft + boardWrap.clientWidth >= boardWrap.scrollWidth - 10;
    const scrollIndicator = document.querySelector('.swipe-indicator');
    if (scrollIndicator) {
      scrollIndicator.style.opacity = isAtEnd ? '0.5' : '1';
    }
  }, { passive: true });

  boardWrap.addEventListener('click', () => {
    if (isBoardFrozen) {
      boardWrap.style.overflowX = 'auto';
      boardWrap.style.cursor = 'default';
      boardWrap.classList.remove('frozen');
      isBoardFrozen = false;
    }
  });
}

// board dynamic get full height

function setBoardHeight() {
  const boardColumn = document.querySelector('.board-column');
  
  // Only proceed if the board column exists
  if (!boardColumn) {
    return;
  }
  
  // Get the height of the window
  const windowHeight = window.innerHeight;
  
  // Subtract 120px from the viewport height
  const adjustedHeight = windowHeight - 120;

  // Set the minimum height to the adjusted value
  boardColumn.style.minHeight = adjustedHeight + 'px';
}

// Initial call to set the minimum height when the page loads
setBoardHeight();

// Update the height when the window is resized
window.addEventListener('resize', setBoardHeight);


// Setup task filtering functionality
function setupTaskFilters() {
  const projectFilter = document.getElementById('project-filter');
  const taskSearch = document.getElementById('task-search');
  const resetButton = document.getElementById('reset-filters');
  
  // Project filter change event
  projectFilter.addEventListener('change', filterTasks);
  
  // Task search input event
  taskSearch.addEventListener('input', filterTasks);
  
  // Reset filters button click
  resetButton.addEventListener('click', function() {
    projectFilter.value = 'all';
    taskSearch.value = '';
    filterTasks();
  });
  
  // Filter function
  function filterTasks() {
    const selectedProject = projectFilter.value;
    const searchText = taskSearch.value.toLowerCase();
    const taskCards = document.querySelectorAll('.task-card');
    
    taskCards.forEach(card => {
      const projectId = card.getAttribute('data-project-id');
      const title = card.querySelector('.task-title').textContent.toLowerCase();
      const description = card.querySelector('.task-description') ? 
                          card.querySelector('.task-description').textContent.toLowerCase() : '';
      
      // Project filter - handle both "all" and internal tasks (projectId = "0")
      const projectMatch = 
        selectedProject === 'all' || 
        projectId === selectedProject || 
        (selectedProject === '0' && (projectId === '0' || projectId === null || projectId === ''));
      
      // Text search filter
      const textMatch = title.includes(searchText) || description.includes(searchText);
      
      // Show/hide based on both filters
      if (projectMatch && textMatch) {
        card.style.display = '';
      } else {
        card.style.display = 'none';
      }
    });
  }
}

// Drag & Drop
let isDraggingCard = false;
let isBoardFrozen = false;
let kanbanDragPreviewEl = null;
let kanbanDragSourceCard = null;
let kanbanDragPointerOffsetX = 0;
let kanbanDragPointerOffsetY = 0;
let kanbanEmptyDragCanvas = null;
let kanbanDropSlotEl = null;
let kanbanDraggedSlotHeight = 120;
let kanbanDropSlotColumn = null;
let kanbanDropSlotBeforeKey = null;

function ensureKanbanDropSlotLabel(slot) {
  var lbl = slot.querySelector('.kanban-drop-slot-label');
  if (!lbl) {
    lbl = document.createElement('span');
    lbl.className = 'kanban-drop-slot-label';
    lbl.textContent = 'Drop here';
    slot.appendChild(lbl);
  }
}

function getKanbanDropSlot() {
  if (!kanbanDropSlotEl) {
    kanbanDropSlotEl = document.createElement('div');
    kanbanDropSlotEl.className = 'kanban-drop-slot';
    kanbanDropSlotEl.setAttribute('aria-hidden', 'true');
    ensureKanbanDropSlotLabel(kanbanDropSlotEl);
  }
  return kanbanDropSlotEl;
}

function disposeKanbanDropSlot() {
  kanbanDropSlotColumn = null;
  kanbanDropSlotBeforeKey = null;
  if (kanbanDropSlotEl && kanbanDropSlotEl.parentNode) {
    kanbanDropSlotEl.parentNode.removeChild(kanbanDropSlotEl);
  }
}

function kanbanTaskCardNeighborIds(cardEl) {
  var insertBeforeId = null;
  var insertAfterId = null;
  var el = cardEl.previousElementSibling;
  while (el) {
    if (el.classList && el.classList.contains('task-card') && el.dataset && el.dataset.id) {
      insertAfterId = String(el.dataset.id);
      break;
    }
    el = el.previousElementSibling;
  }
  el = cardEl.nextElementSibling;
  while (el) {
    if (el.classList && el.classList.contains('task-card') && el.dataset && el.dataset.id) {
      insertBeforeId = String(el.dataset.id);
      break;
    }
    el = el.nextElementSibling;
  }
  return { insertBeforeId: insertBeforeId, insertAfterId: insertAfterId };
}

/**
 * All task-card IDs in this column, top-to-bottom in DOM order (includes display:none from filters).
 * Server must receive the full column list so positions match the DB after refresh.
 */
function kanbanColumnTaskIdsDomOrder(columnEl) {
  if (!columnEl || !columnEl.querySelectorAll) {
    return [];
  }
  var raw = Array.from(columnEl.querySelectorAll('.task-card'))
    .filter(function (c) {
      return c && c.dataset && c.dataset.id;
    })
    .map(function (c) {
      return parseInt(String(c.dataset.id), 10);
    })
    .filter(function (n) {
      return n > 0;
    });
  var seen = {};
  var out = [];
  for (var i = 0; i < raw.length; i++) {
    var n = raw[i];
    if (!seen[n]) {
      seen[n] = true;
      out.push(n);
    }
  }
  return out;
}

/**
 * The dragged task card on the Kanban board (never the floating drag preview).
 * Prefer the element that was actually dragged; fall back to a board-scoped lookup
 * so we never move the wrong node when another [data-id] exists earlier in the document.
 */
function kanbanFindTaskCardInBoard(taskId) {
  var idStr = String(taskId == null ? '' : taskId).trim();
  if (idStr === '') {
    return null;
  }
  var wraps = document.querySelectorAll('.board-wrap');
  for (var w = 0; w < wraps.length; w++) {
    var list = wraps[w].querySelectorAll('.task-card');
    for (var i = 0; i < list.length; i++) {
      var c = list[i];
      if (!c || !c.dataset || !c.dataset.id) {
        continue;
      }
      if (String(c.dataset.id) !== idStr) {
        continue;
      }
      if (c.classList.contains('kanban-drag-preview')) {
        continue;
      }
      return c;
    }
  }
  return null;
}

function kanbanBoardUpdateUrl() {
  var root = (typeof window !== 'undefined' && window.siteRootUrl) ? String(window.siteRootUrl).replace(/\/$/, '') : '';
  return root ? root + '/includes/board_update.php' : '../includes/board_update.php';
}

function kanbanSortOrderFromUrl() {
  var urlParams = new URLSearchParams(
    typeof window !== 'undefined' && window.location && window.location.search ? window.location.search : ''
  );
  return (urlParams.get('sort_order') || 'desc').toLowerCase() === 'asc' ? 'asc' : 'desc';
}

/** POST JSON to board_update.php; resolves parsed object or rejects on network/parse errors. */
function kanbanPostBoardUpdate(body) {
  return fetch(kanbanBoardUpdateUrl(), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': (typeof window !== 'undefined' && window.csrfToken) ? window.csrfToken : ''
    },
    body: JSON.stringify(body)
  }).then(function (response) {
    return response.text().then(function (text) {
      var trimmed = String(text || '').trim();
      if (!trimmed) {
        throw new Error('Empty response from board_update (HTTP ' + response.status + ')');
      }
      try {
        return JSON.parse(trimmed);
      } catch (parseErr) {
        throw new Error(
          'Invalid JSON from board_update (HTTP ' + response.status + '). First 300 chars: ' + trimmed.slice(0, 300)
        );
      }
    });
  });
}

function kanbanRefreshSidebarActivityForTaskId(taskId) {
  try {
    var sidebar = document.getElementById('task-sidebar');
    var sidebarOpen = sidebar && sidebar.classList.contains('open');
    var currentTid = sidebar && sidebar.dataset.taskId ? String(sidebar.dataset.taskId) : '';
    var tidStr = String(taskId == null ? '' : taskId);
    if (sidebarOpen && currentTid === tidStr && typeof loadTaskActivity === 'function') {
      loadTaskActivity(parseInt(tidStr, 10) || 0);
    }
  } catch (e) {}
}

function updateKanbanDropSlot(columnEl, clientY) {
  if (!columnEl || typeof clientY !== 'number') return;
  const slot = getKanbanDropSlot();
  const h = kanbanDraggedSlotHeight || 120;
  slot.style.height = h + 'px';
  slot.style.minHeight = h + 'px';
  ensureKanbanDropSlotLabel(slot);

  const cards = Array.from(columnEl.querySelectorAll('.task-card:not(.dragging)'));
  let insertBefore = null;
  for (let i = 0; i < cards.length; i++) {
    const rect = cards[i].getBoundingClientRect();
    const mid = rect.top + rect.height / 2;
    if (clientY < mid) {
      insertBefore = cards[i];
      break;
    }
  }

  const beforeKey = insertBefore ? String(insertBefore.dataset.id || '') : '__end__';
  if (kanbanDropSlotColumn === columnEl && kanbanDropSlotBeforeKey === beforeKey && slot.parentNode === columnEl) {
    return;
  }
  kanbanDropSlotColumn = columnEl;
  kanbanDropSlotBeforeKey = beforeKey;

  if (slot.parentNode) {
    slot.parentNode.removeChild(slot);
  }
  if (insertBefore) {
    columnEl.insertBefore(slot, insertBefore);
  } else {
    columnEl.appendChild(slot);
  }
}

function getKanbanEmptyDragCanvas() {
  if (!kanbanEmptyDragCanvas) {
    kanbanEmptyDragCanvas = document.createElement('canvas');
    kanbanEmptyDragCanvas.width = 1;
    kanbanEmptyDragCanvas.height = 1;
  }
  return kanbanEmptyDragCanvas;
}

function kanbanDragFloatMove(ev) {
  if (!kanbanDragPreviewEl) return;
  if (typeof ev.clientX !== 'number' || typeof ev.clientY !== 'number') return;
  if (ev.clientX === 0 && ev.clientY === 0) return;
  kanbanDragPreviewEl.style.left = (ev.clientX - kanbanDragPointerOffsetX) + 'px';
  kanbanDragPreviewEl.style.top = (ev.clientY - kanbanDragPointerOffsetY) + 'px';
}

function disposeKanbanDragPreview() {
  if (kanbanDragSourceCard) {
    kanbanDragSourceCard.removeEventListener('drag', kanbanDragFloatMove);
    kanbanDragSourceCard = null;
  }
  if (kanbanDragPreviewEl && kanbanDragPreviewEl.parentNode) {
    kanbanDragPreviewEl.parentNode.removeChild(kanbanDragPreviewEl);
  }
  kanbanDragPreviewEl = null;
}

function allowDrop(ev){ 
  ev.preventDefault(); 
  // Only add dropping-over class if we're actually dragging a task
  if (ev.dataTransfer.types.includes('text/plain')) {
    // Add the dropping-over class to the target column
    document.querySelectorAll('.board-column.dropping-over').forEach(col => col.classList.remove('dropping-over'));
    ev.currentTarget.classList.add('dropping-over');

    if (typeof ev.clientY === 'number') {
      updateKanbanDropSlot(ev.currentTarget, ev.clientY);
    }
    
     // Freeze the board when dragging over a column
    const boardWrap = document.querySelector('.board-wrap');
    if (boardWrap && !isBoardFrozen) {
      boardWrap.style.overflowX = 'hidden';
      boardWrap.style.cursor = 'grabbing';
      boardWrap.classList.add('frozen');
      isBoardFrozen = true;
    }
  }
}

function drag(ev){ 
  const card =
    ev.currentTarget && ev.currentTarget.classList && ev.currentTarget.classList.contains('task-card')
      ? ev.currentTarget
      : ev.target.closest && ev.target.closest('.task-card');
  const dragEl = card || ev.target;
  const taskId = dragEl.dataset && dragEl.dataset.id;
  if (!taskId) {
    return;
  }

  disposeKanbanDropSlot();
  kanbanDraggedSlotHeight = Math.max(card ? card.offsetHeight : 0, 80);

  ev.dataTransfer.setData('text/plain', taskId);

  if (card && typeof ev.dataTransfer.setDragImage === 'function') {
    disposeKanbanDragPreview();
    const w = card.offsetWidth;
    const h = card.offsetHeight;
    kanbanDraggedSlotHeight = Math.max(h, 80);
    const offsetX = Math.max(16, Math.min(Math.round(w / 2), w));
    const offsetY = Math.max(16, Math.min(Math.round(h / 2), h));
    const dragPreview = card.cloneNode(true);
    dragPreview.style.position = 'fixed';
    dragPreview.style.left = (ev.clientX - offsetX) + 'px';
    dragPreview.style.top = (ev.clientY - offsetY) + 'px';
    dragPreview.style.width = w + 'px';
    dragPreview.style.margin = '0';
    dragPreview.style.zIndex = '2147483647';
    dragPreview.style.pointerEvents = 'none';
    dragPreview.style.opacity = '1';
    dragPreview.style.boxShadow = '0 18px 45px rgba(0, 0, 0, 0.22)';
    dragPreview.style.transformOrigin = 'center center';
    dragPreview.style.transition = 'transform 220ms cubic-bezier(0.33, 1, 0.68, 1)';
    dragPreview.style.transform = 'rotate(0deg) scale(1.045)';
    dragPreview.style.filter = 'none';
    dragPreview.style.webkitFontSmoothing = 'antialiased';
    dragPreview.classList.add('kanban-drag-preview');
    document.body.appendChild(dragPreview);
    void dragPreview.offsetWidth;
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        if (!dragPreview.parentNode) return;
        dragPreview.style.transform = 'rotate(3.93deg) scale(1.045)';
      });
    });
    ev.dataTransfer.setDragImage(getKanbanEmptyDragCanvas(), 0, 0);
    kanbanDragPointerOffsetX = offsetX;
    kanbanDragPointerOffsetY = offsetY;
    kanbanDragSourceCard = card;
    card.addEventListener('drag', kanbanDragFloatMove);
    kanbanDragPreviewEl = dragPreview;
  }

  dragEl.classList.add('dragging');

  isDraggingCard = true;
  const boardWrap = document.querySelector('.board-wrap');
  if (boardWrap) {
    boardWrap.style.overflowX = 'hidden';
    boardWrap.style.cursor = 'grabbing';
  }
}

function drop(ev){
  ev.preventDefault();
  const idPlain = ev.dataTransfer.getData('text/plain');
  const idLegacy = ev.dataTransfer.getData('text');
  const id       = idPlain || idLegacy;
  const cardFromDrag =
    kanbanDragSourceCard &&
    kanbanDragSourceCard.classList &&
    kanbanDragSourceCard.classList.contains('task-card') &&
    kanbanDragSourceCard.dataset &&
    String(kanbanDragSourceCard.dataset.id) === String(id)
      ? kanbanDragSourceCard
      : null;
  disposeKanbanDragPreview();
  // Use the card that was dragged (captured before dispose) or a board-only lookup — never a stray [data-id] elsewhere on the page.
  const card = cardFromDrag || kanbanFindTaskCardInBoard(id);
  // Use the column key (custom or default) from the column's id
  const newCol   = ev.currentTarget.id.replace('-column','');
  const columnEl = ev.currentTarget;
  if (!card) {
    disposeKanbanDropSlot();
    return;
  }
  
  // Store current scroll position before moving the card
  const boardWrap = document.querySelector('.board-wrap');
  const prevScrollLeft = boardWrap ? boardWrap.scrollLeft : 0;
  
  // Remove visual effects
  document.querySelectorAll('.board-column.dropping-over').forEach(col => col.classList.remove('dropping-over'));
  card.classList.remove('dragging');
  card.classList.remove('pulse');

  const slotEl = columnEl.querySelector('.kanban-drop-slot');
  const hadSlot = !!(slotEl && slotEl.parentNode === columnEl);
  if (hadSlot) {
    columnEl.insertBefore(card, slotEl);
    slotEl.remove();
  } else {
    columnEl.appendChild(card);
  }
  disposeKanbanDropSlot();
  
  // Keep board frozen after drop - user needs to click to unfreeze
  if (boardWrap) {
    boardWrap.scrollLeft = prevScrollLeft;
    boardWrap.style.overflowX = 'hidden';
    boardWrap.style.cursor = 'default';
    boardWrap.classList.add('frozen');
    
    // Use a small timeout to ensure scroll position is maintained after DOM update
    setTimeout(() => {
      if (boardWrap) {
        boardWrap.scrollLeft = prevScrollLeft;
      }
    }, 10);
  }
  
  isDraggingCard = false;
  // Keep isBoardFrozen = true so board stays frozen until user clicks

  var kanbanSortOrder = kanbanSortOrderFromUrl();
  var domOrderIds = kanbanColumnTaskIdsDomOrder(columnEl);
  var idNum = parseInt(String(id), 10);
  var position = domOrderIds.indexOf(idNum);
  if (position < 0) {
    position = 0;
  }
  const neighbors = kanbanTaskCardNeighborIds(card);
  const insertBeforeNum = neighbors.insertBeforeId ? parseInt(neighbors.insertBeforeId, 10) : null;
  const insertAfterNum = neighbors.insertAfterId ? parseInt(neighbors.insertAfterId, 10) : null;

  kanbanPostBoardUpdate({
    id: id,
    status: newCol,
    position: position,
    insert_before_id: insertBeforeNum,
    insert_after_id: insertAfterNum,
    ordered_ids: domOrderIds,
    sort_order: kanbanSortOrder
  })
    .then(function (data) {
      if (!data) {
        alert('Error: empty response from server');
        return;
      }
      if (data.status !== 'ok') {
        var errParts = [String(data.error || data.message || 'Request failed')];
        if (data.detail) errParts.push(String(data.detail).slice(0, 600));
        if (data.file != null || data.line != null) {
          errParts.push('(' + String(data.file || '') + ':' + String(data.line != null ? data.line : '') + ')');
        }
        alert('Error: ' + errParts.join(' | '));
      } else {
        kanbanRefreshSidebarActivityForTaskId(id);
      }
    })
    .catch(function (error) {
      var msg = error && error.message ? error.message : String(error);
      alert('AJAX error: ' + msg);
    });

  updateNoTasksMessages();
}

/**
 * When the server already set a task status (e.g. timer "Task is completed") but the kanban DOM is stale,
 * move the card into the target column and POST board_update.php (same contract as drag-drop).
 */
window.comonKanbanSyncTaskToColumn = function (taskId, statusKey) {
  statusKey = statusKey && String(statusKey).trim() ? String(statusKey).trim() : 'done';
  taskId = parseInt(String(taskId || ''), 10) || 0;
  if (!taskId) {
    return Promise.resolve({ ok: false, reason: 'no_id' });
  }
  var columnEl = document.getElementById(statusKey + '-column');
  var card = kanbanFindTaskCardInBoard(taskId);
  if (!columnEl || !card) {
    return Promise.resolve({ ok: false, reason: 'no_dom' });
  }

  if (card.parentNode) {
    card.parentNode.removeChild(card);
  }
  columnEl.appendChild(card);
  card.classList.remove('dragging', 'pulse');

  var kanbanSortOrder = kanbanSortOrderFromUrl();
  var domOrderIds = kanbanColumnTaskIdsDomOrder(columnEl);
  var position = domOrderIds.indexOf(taskId);
  if (position < 0) {
    position = 0;
  }
  var neighbors = kanbanTaskCardNeighborIds(card);
  var insertBeforeNum = neighbors.insertBeforeId ? parseInt(neighbors.insertBeforeId, 10) : null;
  var insertAfterNum = neighbors.insertAfterId ? parseInt(neighbors.insertAfterId, 10) : null;

  return kanbanPostBoardUpdate({
    id: taskId,
    status: statusKey,
    position: position,
    insert_before_id: insertBeforeNum,
    insert_after_id: insertAfterNum,
    ordered_ids: domOrderIds,
    sort_order: kanbanSortOrder
  })
    .then(function (data) {
      if (!data || data.status !== 'ok') {
        throw new Error(String((data && (data.error || data.message)) || 'Request failed'));
      }
      kanbanRefreshSidebarActivityForTaskId(taskId);
      if (typeof updateNoTasksMessages === 'function') {
        updateNoTasksMessages();
      }
      return { ok: true, data: data };
    })
    .catch(function (error) {
      var msg = error && error.message ? error.message : String(error);
      if (typeof window !== 'undefined' && window.alert) {
        window.alert('Could not update board: ' + msg);
      }
      return Promise.reject(error);
    });
};

function dragLeave(ev) {
  // Only remove dropping-over class if we're actually dragging a task
  if (isDraggingCard) {
    ev.currentTarget.classList.remove('dropping-over');
    
    // Unfreeze the board when leaving a column
    const boardWrap = document.querySelector('.board-wrap');
    if (boardWrap && isBoardFrozen) {
      boardWrap.style.overflowX = 'auto';
      boardWrap.style.cursor = 'default';
      boardWrap.classList.remove('frozen');
      isBoardFrozen = false;
    }
  }
}

function dragEnd(ev) {
  disposeKanbanDragPreview();
  disposeKanbanDropSlot();
  const endCard = ev.target.closest ? ev.target.closest('.task-card') : ev.target;
  if (endCard && endCard.classList) {
    endCard.classList.remove('dragging');
    endCard.classList.remove('pulse');
  } else {
    ev.target.classList.remove('dragging');
    ev.target.classList.remove('pulse');
  }
  document.querySelectorAll('.dropping-over').forEach(col => {
    col.classList.remove('dropping-over');
  });
  // Keep board frozen - user needs to click to unfreeze
  const boardWrap = document.querySelector('.board-wrap');
  if (boardWrap) {
    boardWrap.style.overflowX = 'hidden';
    boardWrap.style.cursor = 'default';
    boardWrap.classList.add('frozen');
  }
  isDraggingCard = false;
  // Keep isBoardFrozen = true so board stays frozen until user clicks

  updateNoTasksMessages();
}

// Make task cards clickable to open sidebar
(function() {
    // Track if we're currently dragging to prevent click on drag end
    let isDragging = false;
    let dragStartTime = 0;
    
    // Listen for drag start events
    document.addEventListener('dragstart', function(e) {
        if (e.target.closest('.task-card')) {
            isDragging = true;
            dragStartTime = Date.now();
        }
    }, true);
    
    // Listen for drag end events
    document.addEventListener('dragend', function(e) {
        // Reset dragging flag after a short delay to prevent click from firing
        setTimeout(function() {
            isDragging = false;
        }, 100);
    }, true);
    
    // Handle clicks on task cards
    document.addEventListener('click', function(e) {
        // Find the closest task card
        const taskCard = e.target.closest('.task-card');
        
        if (!taskCard) {
            return; // Not clicking on a task card
        }
        
        // Don't open sidebar if:
        // 1. Clicking on the dropdown button or menu
        if (e.target.closest('.btn-dots') || 
            e.target.closest('.dropdown-menu') || 
            e.target.closest('.dropdown')) {
            return;
        }
        
        // 2. Clicking on the bulk delete checkbox
        if (e.target.closest('.bulk-delete-checkbox') || 
            e.target.closest('.task-checkbox')) {
            return;
        }
        
        // 3. Card is currently being dragged (has dragging class)
        if (taskCard.classList.contains('dragging')) {
            return;
        }
        
        // 4. We just finished dragging (prevent click after drag)
        if (isDragging) {
            return;
        }
        
        // 5. If the click happened very quickly after drag start (user was dragging)
        if (Date.now() - dragStartTime < 200) {
            return;
        }
        
        // Get the task ID from the card's data-id attribute
        const taskId = taskCard.getAttribute('data-id');
        
        if (taskId && typeof openTaskSidebar === 'function') {
            e.preventDefault();
            e.stopPropagation();
            openTaskSidebar(parseInt(taskId));
        }
    }, true);
})();

// Show "Add Task" modal & set category
function setTaskCategory(status){
  document.getElementById('task-status').value = status;
  new bootstrap.Modal(document.getElementById('taskModal')).show();
}

// Simple Load More Tasks functionality
function loadMoreTasks(status, page) {
  const button = document.querySelector(`.load-more-btn[data-status="${status}"]`);
  if (!button) {
    return;
  }
  
  // Get the actual page from the button's data-page attribute
  const actualPage = parseInt(button.getAttribute('data-page')) || 2;
  
  // Use the page from the button, not the parameter
  page = actualPage;
  
  const loadMoreText = button.querySelector('.load-more-text');
  const loadMoreCount = button.querySelector('.load-more-count');
  const spinner = button.querySelector('.spinner-border');
  
  // Show loading state
  button.disabled = true;
  if (loadMoreText) loadMoreText.style.display = 'none';
  if (loadMoreCount) loadMoreCount.style.display = 'none';
  if (spinner) spinner.style.display = 'inline-block';
  
  // Detect current view mode from URL
  const urlParams = new URLSearchParams(window.location.search);
  const allTasks = urlParams.get('all_tasks') === '1';
  const archiveView = urlParams.has('archive');
  const projectId = urlParams.get('projectId') || '0';
  const sortOrder = (urlParams.get('sort_order') || 'desc').toLowerCase() === 'asc' ? 'asc' : 'desc';
  const profileUserId = window.profileUserId || '0';
  const isProfileContext = profileUserId !== '0';
  
  
  // Make AJAX request
  let requestBody = `status=${status}&page=${page}&all_tasks=${allTasks ? '1' : '0'}&project_id=${projectId}&sort_order=${sortOrder}`;
  if (archiveView) {
    requestBody += '&archive=1';
  }
  if (isProfileContext) {
    requestBody += `&profile_user_id=${profileUserId}`;
  }
  console.log('Request body:', requestBody);
  
  fetch('../ajax/load_more_tasks.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    credentials: 'same-origin',
    body: requestBody
  })
  .then(response => response.json())
  .then(data => {
    
    if (data.status === 'success') {
        // Find the column container
        const column = document.getElementById(`${status}-column`);
        const loadMoreContainer = document.getElementById(`load-more-${status}`);
        
        // Append new tasks before the load more button
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = data.html;
        const insertedCards = [];
        
        // Insert tasks before load more button
        while (tempDiv.firstChild) {
          const node = tempDiv.firstChild;
          if (node.nodeType === 1 && node.classList && node.classList.contains('task-card')) {
            insertedCards.push(node);
          }
          loadMoreContainer.parentNode.insertBefore(node, loadMoreContainer);
        }

        // Keep bulk-select UI in sync for newly loaded cards
        if (typeof window.syncKanbanBulkModeAfterLoadMore === 'function') {
          window.syncKanbanBulkModeAfterLoadMore(insertedCards.length ? insertedCards : (document.getElementById(`${status}-column`) || document));
        }
        
        // Update button for next page
        button.setAttribute('data-page', page + 1);
        
        // Make sure button is visible for next click
        button.style.display = 'block';
        
        // Update button text with remaining count
        if (data.has_more && data.remaining_count > 0) {
          
          // Find the button elements again to make sure we have the right ones
          const currentButton = document.querySelector(`.load-more-btn[data-status="${status}"]`);
          const currentLoadMoreText = currentButton.querySelector('.load-more-text');
          const currentLoadMoreCount = currentButton.querySelector('.load-more-count');
          
          if (currentLoadMoreText) {
            currentLoadMoreText.textContent = 'Load more tasks';
            currentLoadMoreText.style.display = 'inline';
          }
          if (currentLoadMoreCount) {
            currentLoadMoreCount.textContent = ` (${data.remaining_count})`;
            currentLoadMoreCount.style.display = 'inline';
          }
          currentButton.style.display = 'block';
        } else {
          // Hide button if no more tasks
          button.style.display = 'none';
          loadMoreContainer.style.display = 'none';
        }
        
        // Update column task count
        updateColumnTaskCount(status);
        
    } else {
      alert('Error loading more tasks: ' + (data.message || 'Unknown error'));
    }
  })
  .catch(error => {
    alert('Error loading more tasks: ' + error);
  })
  .finally(() => {
    // Reset button state
    button.disabled = false;
    if (loadMoreText) loadMoreText.style.display = 'inline';
    if (spinner) spinner.style.display = 'none';
  });
}

// Update column task count (keep original total count, don't update badge)
function updateColumnTaskCount(status) {
  // The badge should always show the original total count
  // No need to update it when tasks are loaded via AJAX
  // The badge is set to show originalColumnCounts in PHP
}

// Initialize Load More buttons
function initializeLoadMoreButtons() {
  // Show load more buttons for columns with more than 10 tasks
  document.querySelectorAll('.board-column').forEach(column => {
    const tasks = column.querySelectorAll('.task-card');
    const status = column.id.replace('-column', '');
    const loadMoreContainer = document.getElementById(`load-more-${status}`);
    
    if (tasks.length >= 10 && loadMoreContainer) {
      loadMoreContainer.style.display = 'block';
      
      // Add click event listener
      const button = loadMoreContainer.querySelector('.load-more-btn');
      if (button && !button.hasAttribute('data-listener-added')) {
        button.addEventListener('click', function() {
          loadMoreTasks(status);
        });
        button.setAttribute('data-listener-added', 'true');
      }
    }
  });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(() => {
    initializeLoadMoreButtons();
  }, 100);
});

// AJAX form submit
var taskForm = document.getElementById('taskForm');
if (taskForm) {
  taskForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      const form = this;
      const submitBtn = form.querySelector('button[type="submit"]');
      submitBtn.disabled = true;

      try {
          // console.log('Submitting form data:', Object.fromEntries(new FormData(form)));
          
          const response = await (window.fetchWithCsrf || fetch)('../includes/save_task.php', {
              method: 'POST',
              body: new FormData(form)
          });

          // console.log('Response status:', response.status);
          // console.log('Response headers:', Object.fromEntries(response.headers));

          let responseText;
          try {
              responseText = await response.text();
              // console.log('Raw response:', responseText);
              
              const data = JSON.parse(responseText);
              // console.log('Parsed response:', data);
              
              if (data.status === 'ok') {
                  location.reload();
              } else {
                  throw new Error(data.error || 'Unknown error occurred');
              }
          } catch (parseError) {
              throw new Error('Server returned invalid JSON: ' + responseText);
          }
      } catch (error) {
          alert('Error saving task: ' + error.message);
      } finally {
          submitBtn.disabled = false;
      }
  });
}


// Delete task function
function archiveTask(id) {
    var msg = (typeof window !== 'undefined' && window.langArchiveConfirm)
        ? window.langArchiveConfirm
        : 'Are you sure you want to archive this task?';
    if (!confirm(msg)) return;
    fetch('../includes/archive_task.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': (typeof window !== 'undefined' && window.csrfToken) ? window.csrfToken : ''
        },
        body: JSON.stringify({ id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            var card = document.querySelector('.task-card[data-id="' + id + '"]');
            if (card) {
                card.remove();
            } else {
                var checkbox = document.getElementById('task-' + id);
                if (checkbox) {
                    var row = checkbox.closest('tr');
                    if (row) row.remove();
                } else {
                    location.reload();
                }
            }
        } else {
            alert(data.error || 'Failed to archive task');
        }
    })
    .catch(error => alert('Error: ' + error));
}

function unarchiveTask(id) {
    var msg = (typeof window !== 'undefined' && window.langUnarchiveConfirm)
        ? window.langUnarchiveConfirm
        : 'Restore this task to the active board?';
    if (!confirm(msg)) return;
    fetch('../includes/unarchive_task.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': (typeof window !== 'undefined' && window.csrfToken) ? window.csrfToken : ''
        },
        body: JSON.stringify({ id })
    })
    .then(response => response.json())
    .then(data => data.status === 'ok' ? location.reload() : alert(data.error || 'Failed to unarchive task'))
    .catch(error => alert('Error: ' + error));
}

function deleteTask(id) {
    if (confirm('Are you sure you want to delete this task?')) {
        var endpoint = '../includes/delete_task.php';
        if (typeof window !== 'undefined' && parseInt(window.accountStatus, 10) === 2) {
            endpoint = '../client/delete_task.php';
        }
        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': (typeof window !== 'undefined' && window.csrfToken) ? window.csrfToken : ''
            },
            body: JSON.stringify({ id })
        })
        .then(response => response.json())
        .then(data => data.status === 'ok' ? location.reload() : alert(data.error))
        .catch(error => alert('Error: ' + error));
    }
}

// Column rename functions
function startColumnRename(columnKey) {
    const modal = new bootstrap.Modal(document.getElementById('columnRenameModal'));
    const columnTitle = document.querySelector(`.column-title[data-column="${columnKey}"]`).textContent;
    
    document.getElementById('column-key').value = columnKey;
    document.getElementById('column-name').value = columnTitle;
    
    modal.show();
}

// Save column name change
var columnRenameForm = document.getElementById('columnRenameForm');
if (columnRenameForm) {
  columnRenameForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      const form = this;
      const submitBtn = form.querySelector('button[type="submit"]');
      submitBtn.disabled = true;

      const formData = new FormData(form);
      const columnKey = formData.get('column_key');
      const newName = formData.get('column_name');
      const projectId = formData.get('project_id');

      // On kanban pages, save via the current page POST handler.
      // This supports renaming custom_* columns (and avoids project-permission constraints for project_id=0).
      const isKanbanPage = window.location && window.location.pathname && window.location.pathname.includes('kanban.php');
      if (isKanbanPage) {
          try {
              await fetch('', { method: 'POST', body: formData });
              var colTitle = document.querySelector(`.column-title[data-column="${columnKey}"]`);
              if (colTitle) colTitle.textContent = newName;
              var modalEl = document.getElementById('columnRenameModal');
              if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                  const instance = bootstrap.Modal.getInstance(modalEl);
                  if (instance) instance.hide();
              }
          } catch (error) {
              alert('Error saving column name: ' + error.message);
          } finally {
              submitBtn.disabled = false;
          }
          return;
      }
      
      // console.log('Column rename form submitted with data:', {
      //     column_key: columnKey,
      //     column_name: newName,
      //     project_id: projectId
      // });
      
      try {
          const response = await fetch('../includes/save_column_name.php', {
              method: 'POST',
              headers: {'Content-Type': 'application/json'},
              body: JSON.stringify({
                  column_key: columnKey,
                  column_name: newName,
                  project_id: projectId
              })
          });
          
          // Log the raw response for debugging
          const responseText = await response.text();
          // console.log('Raw server response:', responseText);
          
          // Parse the response as JSON
          const data = JSON.parse(responseText);
          // console.log('Parsed response:', data);
          
          if (data.status === 'ok') {
              var colTitle = document.querySelector(`.column-title[data-column="${columnKey}"]`);
              if (colTitle) colTitle.textContent = newName;
              var modalEl = document.getElementById('columnRenameModal');
              if (modalEl) bootstrap.Modal.getInstance(modalEl).hide();
          } else {
              alert(data.error || 'Unknown error occurred');
          }
      } catch (error) {
          alert('Error saving column name: ' + error.message);
      } finally {
          submitBtn.disabled = false;
      }
  });
}

document.addEventListener('DOMContentLoaded', function() {
  // Use a more specific selector if you have more than one .board-wrap
  var boardWraps = document.querySelectorAll('.board-wrap');
  // If you have a top dummy and a main, pick the main one (usually the last)
  var boardWrap = boardWraps[boardWraps.length - 1];
  if (boardWrap) {
    function scrollToFirstColumn() {
      // Try both 0 and scrollWidth for best cross-browser support
      boardWrap.scrollLeft = 0;
      setTimeout(function() {
        if (boardWrap.scrollLeft !== 0) {
          boardWrap.scrollLeft = boardWrap.scrollWidth;
        }
      }, 50); // Slightly longer delay to ensure layout is done
    }
    // Run after a short delay to ensure layout is complete
    setTimeout(scrollToFirstColumn, 100);
    // Also run after window load as a fallback
    window.addEventListener('load', scrollToFirstColumn);
  }
});





 function toggleSearch() {
        // Try one-to-one chat form first, then group chat form
        const searchForm = document.getElementById("searchForm") || document.getElementById("group-searchForm");
        if (!searchForm) return;
        searchForm.classList.toggle("expanded");
        if (searchForm.classList.contains("expanded")) {
            var input = searchForm.querySelector("input[type=text]");
            if (input) input.focus();
        }
    }
    // Close search when clicking outside
    document.addEventListener("click", function(event) {
        const searchForm = document.getElementById("searchForm");
        const groupSearchForm = document.getElementById("group-searchForm");
        const searchIcon = event.target.closest(".search-icon");
        const searchContainer = event.target.closest(".search");
        
        // Close one-to-one chat search if clicking outside
        if (searchForm && !searchForm.contains(event.target) && (!searchIcon || !searchContainer || !searchContainer.contains(searchForm))) {
            searchForm.classList.remove("expanded");
        }
        
        // Close group chat search if clicking outside
        if (groupSearchForm && !groupSearchForm.contains(event.target) && (!searchIcon || !searchContainer || !searchContainer.contains(groupSearchForm))) {
            groupSearchForm.classList.remove("expanded");
        }
    });








// add all-task.php js code bellow the line

// View Project — absolute role URL so /ai/* pages do not resolve to /ai/overview.php
function setViewProjectButton(projectId) {
  var pid = parseInt(projectId, 10) || 0;
  var show = pid > 0;
  var href = '#';
  if (show) {
    var base = (typeof window.baseUrl === 'string' && window.baseUrl) ? String(window.baseUrl) : '/';
    if (base.slice(-1) !== '/') {
      base += '/';
    }
    var role = '';
    var st = window.accountStatus;
    if (st === 1 || st === '1') {
      role = 'admin/';
    } else if (st === 2 || st === '2') {
      role = 'client/';
    } else if (st === 3 || st === '3') {
      role = 'staff/';
    } else {
      var parts = (window.location.pathname || '').split('/').filter(Boolean);
      for (var i = 0; i < parts.length; i++) {
        var seg = String(parts[i]).toLowerCase();
        if (seg === 'admin' || seg === 'staff' || seg === 'client') {
          role = seg + '/';
          break;
        }
      }
      if (!role) {
        role = 'admin/';
      }
    }
    href = base + role + 'overview.php?projectId=' + pid;
  }
  document.querySelectorAll('#view-project-btn, #sidebar-view-project-link').forEach(function (btn) {
    btn.href = href;
    btn.style.display = show ? '' : 'none';
    var item = btn.closest('li');
    if (item) {
      item.style.display = show ? '' : 'none';
    }
  });
}

// Add after other kanban functions
function updateNoTasksMessages() {
    document.querySelectorAll('.board-column').forEach(function(column) {
        const tasks = column.querySelectorAll('.task-card');
        const noTasksMsg = column.querySelector('.alert.alert-light.text-center');
        if (tasks.length === 0) {
            if (noTasksMsg) noTasksMsg.style.display = '';
        } else {
            if (noTasksMsg) noTasksMsg.style.display = 'none';
        }
    });
}

// All Tasks Page Specific Functions
(function() {
  // Initialize all tasks page functionality when DOM is loaded
  document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on the all tasks page
    if (document.querySelector('.all-tasks-container') || window.location.pathname.includes('all-tasks.php')) {
      initAllTasksPage();
    }
  });

  function initAllTasksPage() {
    // Initialize task filtering if elements exist
    if (document.getElementById('project-filter')) {
      setupTaskFilters();
    }

    // Initialize search functionality
    initTaskSearch();

    // Initialize bulk actions
    initBulkActions();

    // Initialize task status updates
    initTaskStatusUpdates();

    // Initialize pagination helpers
    initPaginationHelpers();
  }

  function initTaskSearch() {
    const searchInput = document.getElementById('task-search');
    if (searchInput) {
      searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const taskRows = document.querySelectorAll('.task-row');
        
        taskRows.forEach(row => {
          const title = row.querySelector('.task-title')?.textContent.toLowerCase() || '';
          const description = row.querySelector('.task-description')?.textContent.toLowerCase() || '';
          const project = row.querySelector('.task-project')?.textContent.toLowerCase() || '';
          
          const matches = title.includes(searchTerm) || 
                         description.includes(searchTerm) || 
                         project.includes(searchTerm);
          
          row.style.display = matches ? '' : 'none';
        });
      });
    }
  }

  function initBulkActions() {
    // Select all checkbox functionality
    const selectAllCheckbox = document.querySelector('input[name="select_all_tasks"]');
    if (selectAllCheckbox) {
      selectAllCheckbox.addEventListener('change', function() {
        const taskCheckboxes = document.querySelectorAll('input[name="task_ids[]"]');
        taskCheckboxes.forEach(checkbox => {
          checkbox.checked = this.checked;
        });
        updateBulkActionButtons();
      });
    }

    // Individual task checkbox functionality
    document.addEventListener('change', function(e) {
      if (e.target.name === 'task_ids[]') {
        updateBulkActionButtons();
        updateSelectAllCheckbox();
      }
    });

    function updateBulkActionButtons() {
      const selectedTasks = document.querySelectorAll('input[name="task_ids[]"]:checked');
      const bulkActionButtons = document.querySelectorAll('.bulk-action-btn');
      
      bulkActionButtons.forEach(btn => {
        btn.disabled = selectedTasks.length === 0;
      });
    }

    function updateSelectAllCheckbox() {
      const allCheckboxes = document.querySelectorAll('input[name="task_ids[]"]');
      const checkedCheckboxes = document.querySelectorAll('input[name="task_ids[]"]:checked');
      const selectAllCheckbox = document.querySelector('input[name="select_all_tasks"]');
      
      if (selectAllCheckbox) {
        selectAllCheckbox.checked = checkedCheckboxes.length === allCheckboxes.length;
        selectAllCheckbox.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
      }
    }
  }

  function initTaskStatusUpdates() {
    // Quick status update dropdowns
    document.addEventListener('change', function(e) {
      if (e.target.classList.contains('task-status-select')) {
        const taskId = e.target.dataset.taskId;
        const newStatus = e.target.value;
        
        updateTaskStatus(taskId, newStatus);
      }
    });

    function updateTaskStatus(taskId, status) {
      fetch('../includes/update_task_status.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          task_id: taskId,
          status: status
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'ok') {
          // Show success message
          showNotification('Task status updated successfully', 'success');
          
          // Update the status badge if it exists
          const statusBadge = document.querySelector(`[data-task-id="${taskId}"] .status-badge`);
          if (statusBadge) {
            statusBadge.className = `status-badge badge ${getStatusBadgeClass(status)}`;
            statusBadge.textContent = getStatusText(status);
          }
        } else {
          showNotification('Error updating task status: ' + (data.error || 'Unknown error'), 'error');
        }
      })
      .catch(error => {
        showNotification('Error updating task status', 'error');
      });
    }

    function getStatusBadgeClass(status) {
      const statusClasses = {
        'todo': 'bg-secondary',
        'in_progress': 'bg-primary',
        'review': 'bg-warning',
        'done': 'bg-success',
        'cancelled': 'bg-danger'
      };
      return statusClasses[status] || 'bg-secondary';
    }

    function getStatusText(status) {
      const statusTexts = {
        'todo': 'To Do',
        'in_progress': 'In Progress',
        'review': 'Review',
        'done': 'Done',
        'cancelled': 'Cancelled'
      };
      return statusTexts[status] || status;
    }
  }

  function initPaginationHelpers() {
    // Counts come from server-rendered .pagination-box (see includes/list-pagination.php).
  }

  function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
      if (notification.parentNode) {
        notification.remove();
      }
    }, 5000);
  }
})();

// Kanban Board Specific Functions
(function() {
  // Kanban scroll lock functionality
  function initKanbanScrollLock() {
    const lockBtn = document.getElementById('kanban-lock-btn');
    const lockIcon = document.getElementById('kanban-lock-icon');
    
    if (!lockBtn || !lockIcon) return;
    
    let locked = localStorage.getItem('kanbanScrollLocked') === 'true';

    function updateLockIcon() {
      if (locked) {
        lockIcon.innerHTML = ' <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />';
        lockBtn.classList.add('locked');
      } else {
        lockIcon.innerHTML = ' <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21.75h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />';
        lockBtn.classList.remove('locked');
      }
    }

    function setScrollLock(state) {
      locked = state;
      localStorage.setItem('kanbanScrollLocked', locked ? 'true' : 'false');
      updateLockIcon();
      var boardWrap = document.querySelector('.board-wrap');
      if (boardWrap) {
        boardWrap.style.overflowX = locked ? 'hidden' : 'auto';
        boardWrap.style.webkitOverflowScrolling = locked ? 'auto' : 'touch';
        if (locked) {
          boardWrap.classList.add('scroll-locked');
        } else {
          boardWrap.classList.remove('scroll-locked');
        }
      }
    }

    lockBtn.addEventListener('click', function(e) {
      e.preventDefault();
      setScrollLock(!locked);
    });

    // On page load, set initial state
    setScrollLock(locked);
  }

  // Kanban column management
  function initKanbanColumns() {
    // Add new column functionality
    const addColumnBtn = document.getElementById('addColumnBtn');
    const addColumnModal = document.getElementById('addColumnModal');
    const addColumnForm = document.getElementById('addColumnForm');

    if (addColumnBtn && addColumnModal) {
      addColumnBtn.addEventListener('click', function() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
          const modal = new bootstrap.Modal(addColumnModal);
          modal.show();
        } else {
          // Fallback for older Bootstrap versions
          $(addColumnModal).modal('show');
        }
      });
    }

    if (addColumnForm) {
      addColumnForm.addEventListener('submit', function(e) {
        const nameInput = this.querySelector('input[name="column_name"]');
        const name = nameInput ? nameInput.value.trim() : '';
        
        if (!name) {
          e.preventDefault();
          return false;
        }
        
        // Set a unique key for the new column
        const key = 'custom_' + Date.now();
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'column_key';
        hiddenInput.value = key;
        this.appendChild(hiddenInput);
        
        return true;
      });
    }

    // Modal close on background click
    if (addColumnModal) {
      addColumnModal.addEventListener('hidden.bs.modal', function() {
        if (addColumnForm) {
          addColumnForm.reset();
        }
      });
    }

    // Remove column functionality
    document.addEventListener('click', function(e) {
      if (e.target.closest('.remove-btn')) {
        const removeBtn = e.target.closest('.remove-btn');
        const key = removeBtn.dataset.key;
        
        if (confirm('Are you sure you want to delete this column? All tasks in this column will remain but the column will be removed.')) {
          const formData = new FormData();
          formData.append('delete_column', '1');
          formData.append('column_key', key);
          
          fetch('', {
            method: 'POST',
            body: formData
          }).then(() => {
            location.reload();
          });
        }
      }
    });
  }

  // Initialize kanban functionality when DOM is loaded
  document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on a kanban page
    if (document.querySelector('.board-wrap')) {
      initKanbanScrollLock();
      initKanbanColumns();
    }
  });
})();

// Project Sidebar Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize project sidebar functionality
    initProjectSidebar();
    
    // Initialize full height functionality
    initFullHeight();
    
    // Initialize main sidebar functionality
    initMainSidebar();
    
    // Initialize chat sidebar functionality
    initChatSidebar();

    // Initialize Create New modal (sidebar)
    initCreateNewModal();
    
    // Initialize discussion features
    initDiscussionFeatures();
});

// Initialize discussion features
function initDiscussionFeatures() {
    // Show/hide clear button when text is entered
    const chatSearchInput = document.getElementById('chat-search-input');
    if (chatSearchInput) {
        chatSearchInput.addEventListener('input', function() {
            var crossButtons = document.querySelectorAll('.search-form .cross');
            crossButtons.forEach(function(btn) {
                btn.style.display = this.value ? 'block' : 'none';
            }.bind(this));
        });
    }
}

// Initialize Create New modal (sidebar)
function initCreateNewModal() {
    const modalEl = document.getElementById('createNewModal');
    if (!modalEl) return;

    // Prevent "#" jump on trigger
    document.querySelectorAll('a.create-new-trigger[href="#"]').forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
        });
    });

    const stepMain = modalEl.querySelector('.create-new-step[data-step="main"]');
    const stepUser = modalEl.querySelector('.create-new-step[data-step="user"]');
    if (!stepMain || !stepUser) return;

    const showStep = (step) => {
        const isUser = step === 'user';
        stepMain.classList.toggle('d-none', isUser);
        stepUser.classList.toggle('d-none', !isUser);
    };

    modalEl.querySelectorAll('.js-create-new-open-user').forEach(btn => {
        btn.addEventListener('click', function() {
            showStep('user');
        });
    });

    modalEl.querySelectorAll('.js-create-new-back').forEach(btn => {
        btn.addEventListener('click', function() {
            showStep('main');
        });
    });

    // Reset when modal closes
    modalEl.addEventListener('hidden.bs.modal', function() {
        showStep('main');
    });
}

// Event fire utility function
function eventFire(el, etype) {
    if(el.fireEvent) {
        el.fireEvent('on' + etype);
    } else {
        var evObj = document.createEvent('Events');
        evObj.initEvent(etype, true, false);
        el.dispatchEvent(evObj);
    }
}

// Initialize main sidebar functionality
function initMainSidebar() {
    var sidebar = document.querySelector('.sidebar-admin:not(.project-sidebar)');
    var shrinkBtn = document.getElementById('sidebarShrinkBtn');
    var scrollBody = sidebar ? sidebar.querySelector('.sidebar-scroll-body') : null;
    var iconMinus = '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />';
    var iconPlus = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />';

    function isShrunk() {
        return sidebar && sidebar.classList.contains('shrink');
    }

    function clearFlyoutStyles(collapseEl) {
        if (!collapseEl) return;
        collapseEl.style.position = '';
        collapseEl.style.left = '';
        collapseEl.style.top = '';
        collapseEl.style.minWidth = '';
        collapseEl.style.zIndex = '';
        collapseEl.style.maxHeight = '';
    }

    function positionShrinkFlyout(collapseEl) {
        if (!isShrunk() || !collapseEl || !collapseEl.classList.contains('show')) return;
        var parentLi = collapseEl.parentElement;
        if (!parentLi) return;
        var rect = parentLi.getBoundingClientRect();
        var gap = 4;
        var flyoutWidth = 240;
        var left = rect.right + gap;
        var top = rect.top;

        collapseEl.style.position = 'fixed';
        collapseEl.style.left = left + 'px';
        collapseEl.style.top = top + 'px';
        collapseEl.style.minWidth = flyoutWidth + 'px';
        collapseEl.style.zIndex = '1100';

        requestAnimationFrame(function() {
            var maxBottom = window.innerHeight - 12;
            var flyoutHeight = collapseEl.offsetHeight;
            if (top + flyoutHeight > maxBottom) {
                collapseEl.style.top = Math.max(8, maxBottom - flyoutHeight) + 'px';
            }
        });
    }

    var hoverHideTimer = null;
    var hoverShowTimer = null;
    var HOVER_HIDE_DELAY = 280;
    var HOVER_SHOW_DELAY = 140;
    var FLYOUT_ANIM_MS = 400;

    function isWithinShrinkFlyoutZone(el) {
        if (!el || !sidebar || !sidebar.contains(el)) return false;
        if (el.closest('.admin-nav-area > ul > li > ul.collapse')) return true;
        var li = el.closest('.admin-nav-area > ul > li');
        return !!(li && li.querySelector(':scope > ul.collapse'));
    }

    function hasOpenShrinkFlyout() {
        return !!(sidebar && sidebar.querySelector('.admin-nav-area ul.collapse.show'));
    }

    function closeAllShrinkFlyouts() {
        if (!sidebar) return;
        clearTimeout(hoverHideTimer);
        clearTimeout(hoverShowTimer);
        sidebar.querySelectorAll('.admin-nav-area ul.collapse.show').forEach(function(el) {
            el.classList.remove('show');
            clearFlyoutStyles(el);
            var parentLi = el.parentElement;
            if (parentLi) {
                var t = parentLi.querySelector('[data-bs-target]');
                var ic = t ? t.querySelector('.dropdown-toggle-icon') : null;
                updateToggleIcon(ic, false);
            }
        });
    }

    function openShrinkFlyout(collapseEl, icon) {
        if (!isShrunk() || !collapseEl) return;
        clearTimeout(hoverHideTimer);
        clearTimeout(hoverShowTimer);
        var isSwitch = hasOpenShrinkFlyout() && !collapseEl.classList.contains('show');

        sidebar.querySelectorAll('.admin-nav-area ul.collapse.show').forEach(function(openEl) {
            if (openEl === collapseEl) return;
            openEl.classList.remove('show');
            clearFlyoutStyles(openEl);
            var otherLi = openEl.parentElement;
            if (otherLi) {
                var otherToggle = otherLi.querySelector('[data-bs-target]');
                updateToggleIcon(otherToggle ? otherToggle.querySelector('.dropdown-toggle-icon') : null, false);
            }
        });

        if (isSwitch || collapseEl.classList.contains('show')) {
            collapseEl.classList.add('show');
            positionShrinkFlyout(collapseEl);
            updateToggleIcon(icon, true);
            return;
        }

        collapseEl.classList.remove('show');
        positionShrinkFlyout(collapseEl);
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                if (!isShrunk()) return;
                collapseEl.classList.add('show');
                positionShrinkFlyout(collapseEl);
                updateToggleIcon(icon, true);
            });
        });
    }

    function scheduleCloseShrinkFlyout(collapseEl, icon) {
        clearTimeout(hoverHideTimer);
        hoverHideTimer = setTimeout(function() {
            if (!collapseEl.classList.contains('show')) return;
            collapseEl.classList.remove('show');
            setTimeout(function() {
                if (!collapseEl.classList.contains('show')) {
                    clearFlyoutStyles(collapseEl);
                }
            }, FLYOUT_ANIM_MS);
            updateToggleIcon(icon, false);
        }, HOVER_HIDE_DELAY);
    }

    function resetFlyoutsForExpandedSidebar() {
        if (!sidebar) return;
        sidebar.querySelectorAll('.admin-nav-area ul.collapse').forEach(clearFlyoutStyles);
    }

    function syncShrinkCollapseMode() {
        if (!sidebar) return;
        sidebar.querySelectorAll('.admin-nav-area [data-bs-target]').forEach(function(toggle) {
            if (isShrunk()) {
                if (!toggle.dataset.bsToggleOriginal) {
                    toggle.dataset.bsToggleOriginal = toggle.getAttribute('data-bs-toggle') || 'collapse';
                }
                toggle.removeAttribute('data-bs-toggle');
            } else if (toggle.dataset.bsToggleOriginal) {
                toggle.setAttribute('data-bs-toggle', toggle.dataset.bsToggleOriginal);
            }
        });
    }

    function updateToggleIcon(icon, isOpen) {
        if (!icon) return;
        if (icon.classList && icon.classList.contains('ts-icon')) {
            icon.classList.toggle('ts-icon-minus', !!isOpen);
            icon.classList.toggle('ts-icon-plus', !isOpen);
            return;
        }
        icon.innerHTML = isOpen ? iconMinus : iconPlus;
    }

    var expandBtn = document.getElementById('sidebarExpandBtn');
    var logoArea = sidebar ? sidebar.querySelector('.logo-admin-area') : null;

    if (expandBtn && shrinkBtn) {
        expandBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (isShrunk()) shrinkBtn.click();
        });
    }

    if (logoArea && shrinkBtn) {
        logoArea.addEventListener('click', function(e) {
            if (!isShrunk()) return;
            if (e.target.closest('.cross-mobile')) return;
            shrinkBtn.click();
        });
    }

    if (sidebar && shrinkBtn) {
        shrinkBtn.addEventListener('click', function() {
            var willShrink = !sidebar.classList.contains('shrink');
            if (willShrink) {
                sidebar.classList.add('sidebar-animating');
                sidebar.classList.add('shrink');
                document.documentElement.classList.remove('sidebar-shrunk-initial');
                setTimeout(function() {
                    sidebar.classList.remove('sidebar-animating');
                }, 300);
                localStorage.setItem('sidebarShrunk', 'true');
                closeAllShrinkFlyouts();
            } else {
                closeAllShrinkFlyouts();
                sidebar.classList.remove('shrink');
                sidebar.classList.remove('sidebar-animating');
                document.documentElement.classList.remove('sidebar-shrunk-initial');
                localStorage.setItem('sidebarShrunk', 'false');
                resetFlyoutsForExpandedSidebar();
            }
            syncShrinkCollapseMode();
        });
    }

    if (sidebar) {
        if (localStorage.getItem('sidebarShrunk') === 'true') {
            sidebar.classList.add('shrink');
            document.documentElement.classList.remove('sidebar-shrunk-initial');
        } else {
            sidebar.classList.remove('shrink');
            document.documentElement.classList.remove('sidebar-shrunk-initial');
        }
        sidebar.classList.remove('sidebar-animating');
        syncShrinkCollapseMode();
    }

    if (!sidebar) return;

    sidebar.querySelectorAll('.admin-nav-area [data-bs-target]').forEach(function(toggle) {
        var icon = toggle.querySelector('.dropdown-toggle-icon');
        var targetId = toggle.getAttribute('data-bs-target');
        var target = targetId ? document.querySelector(targetId) : null;
        var parentLi = toggle.closest('li');
        if (!target || !parentLi) return;

        updateToggleIcon(icon, target.classList.contains('show'));

        toggle.addEventListener('click', function(e) {
            if (isShrunk()) e.preventDefault();
        });

        target.addEventListener('shown.bs.collapse', function() {
            if (isShrunk()) return;
            updateToggleIcon(icon, true);
        });

        target.addEventListener('hidden.bs.collapse', function() {
            if (isShrunk()) return;
            updateToggleIcon(icon, false);
            clearFlyoutStyles(target);
        });

        parentLi.addEventListener('mouseenter', function() {
            if (!isShrunk()) return;
            clearTimeout(hoverHideTimer);
            clearTimeout(hoverShowTimer);
            var openDelay = hasOpenShrinkFlyout() ? 0 : HOVER_SHOW_DELAY;
            hoverShowTimer = setTimeout(function() {
                openShrinkFlyout(target, icon);
            }, openDelay);
        });

        parentLi.addEventListener('mouseleave', function(e) {
            if (!isShrunk()) return;
            clearTimeout(hoverShowTimer);
            if (isWithinShrinkFlyoutZone(e.relatedTarget)) return;
            scheduleCloseShrinkFlyout(target, icon);
        });

        target.addEventListener('mouseenter', function() {
            if (!isShrunk()) return;
            clearTimeout(hoverHideTimer);
            clearTimeout(hoverShowTimer);
        });

        target.addEventListener('mouseleave', function(e) {
            if (!isShrunk()) return;
            if (isWithinShrinkFlyoutZone(e.relatedTarget)) return;
            scheduleCloseShrinkFlyout(target, icon);
        });
    });

    if (scrollBody) {
        scrollBody.addEventListener('mouseover', function(e) {
            if (!isShrunk()) return;
            if (isWithinShrinkFlyoutZone(e.target)) {
                clearTimeout(hoverHideTimer);
            }
        });
    }

    if (scrollBody) {
        scrollBody.addEventListener('scroll', function() {
            if (!isShrunk()) return;
            sidebar.querySelectorAll('.admin-nav-area ul.collapse.show').forEach(positionShrinkFlyout);
        }, { passive: true });
    }

    window.addEventListener('resize', function() {
        if (!isShrunk()) return;
        sidebar.querySelectorAll('.admin-nav-area ul.collapse.show').forEach(positionShrinkFlyout);
    });
}

// Initialize project sidebar functionality
function initProjectSidebar() {
    var sidebar = document.getElementById('project-sidebar');
    var shrinkBtn = document.getElementById('projectSidebarShrinkBtn');
    var layoutRow = document.getElementById('project-layout-row');

    if (!sidebar) return;

    // IMPORTANT: Ask AI rail (header) also has .sidebar-toggle + .sidebar-overlay.
    // Never use document.querySelector('.sidebar-toggle') — that binds the AI button.
    // Markup order in project-sidebar.php / user-profile-sidebar.php:
    //   .filter-btn > .sidebar-toggle  →  .sidebar-overlay  →  #project-sidebar
    var overlay = sidebar.previousElementSibling;
    if (!overlay || !overlay.classList.contains('sidebar-overlay')) {
        overlay = null;
        var sib = sidebar.previousElementSibling;
        while (sib) {
            if (sib.classList && sib.classList.contains('sidebar-overlay')) {
                overlay = sib;
                break;
            }
            sib = sib.previousElementSibling;
        }
    }
    var filterWrap = overlay ? overlay.previousElementSibling : sidebar.previousElementSibling;
    var toggleBtn = null;
    if (filterWrap && filterWrap.classList && filterWrap.classList.contains('filter-btn')) {
        toggleBtn = filterWrap.querySelector('.sidebar-toggle');
    }
    if (!toggleBtn) {
        var prev = sidebar.previousElementSibling;
        while (prev && !toggleBtn) {
            if (prev.classList && prev.classList.contains('filter-btn')) {
                toggleBtn = prev.querySelector('.sidebar-toggle');
            }
            prev = prev.previousElementSibling;
        }
    }

    function setShrunkState(isShrunk) {
        if (isShrunk) {
            sidebar.classList.add('shrink');
            if (layoutRow) layoutRow.classList.add('sidebar-shrunk');
        } else {
            sidebar.classList.remove('shrink');
            if (layoutRow) layoutRow.classList.remove('sidebar-shrunk');
        }
    }

    function openDrawer() {
        // Desktop shrink hides .cs-card — strip it when opening the mobile drawer
        if (window.matchMedia('(max-width: 991.98px)').matches) {
            setShrunkState(false);
        }
        sidebar.classList.remove('hide');
        sidebar.classList.add('show');
        if (overlay) overlay.classList.add('show');
        if (toggleBtn) toggleBtn.classList.add('active');
    }

    function closeDrawer() {
        sidebar.classList.remove('show');
        sidebar.classList.add('hide');
        if (overlay) overlay.classList.remove('show');
        if (toggleBtn) toggleBtn.classList.remove('active');
    }

    // Desktop sidebar shrink functionality
    if (shrinkBtn) {
        shrinkBtn.addEventListener('click', function () {
            if (window.matchMedia('(max-width: 991.98px)').matches) return;
            var isShrunk = !sidebar.classList.contains('shrink');
            setShrunkState(isShrunk);
            localStorage.setItem('projectSidebarShrunk', isShrunk ? 'true' : 'false');
        });
    }

    // Mobile: View project / profile details
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (sidebar.classList.contains('show')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function () {
            closeDrawer();
        });
    }

    var closeBtn = sidebar.querySelector('.cross-mobile');
    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeDrawer();
        });
    }

    // Desktop-only restore shrink preference
    if (!window.matchMedia('(max-width: 991.98px)').matches) {
        setShrunkState(localStorage.getItem('projectSidebarShrunk') === 'true');
    } else {
        setShrunkState(false);
    }
}

// Initialize full height functionality
function initFullHeight() {
    function setFullHeight() {
        var els = document.querySelectorAll('.full-height');
        var vh = window.innerHeight || document.documentElement.clientHeight || 0;
        els.forEach(function (el) {
            // Fill from this element's top edge to the bottom of the viewport
            // (fixed -105 left a gap under profile/project sidebars).
            var top = el.getBoundingClientRect().top;
            var h = Math.floor(vh - top);
            if (h < 0) {
                h = 0;
            }
            el.style.height = h + 'px';
        });
    }

    // On load and on resize apply
    setFullHeight();
    window.addEventListener('resize', setFullHeight);
    // Recalc after layout/fonts (header height can shift)
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () {
            setFullHeight();
        }).catch(function () {});
    }
    window.addEventListener('load', setFullHeight);
}

// Initialize chat sidebar functionality
function initChatSidebar() {
    // Exclude Ask AI rail thread list (.ai-thread-col / #aiSidebar) — same class names as chatting
    var sidebar = document.querySelector('.chat-sidebar:not(.ai-thread-col):not(#aiSidebar)');
    if (!sidebar) {
        return;
    }
    var row = sidebar.closest('.row') || sidebar.parentElement;
    var toggleBtn = row
        ? row.querySelector('.sidebar-toggle:not(#aiSidebarToggle)')
        : document.querySelector('.sidebar-toggle:not(#aiSidebarToggle)');
    var overlay = row
        ? row.querySelector('.sidebar-overlay')
        : null;
    var closeBtn = sidebar.querySelector('.cross-mobile');

    function closeSidebar() {
        sidebar.classList.remove('show');
        sidebar.classList.add('hide');
        if (overlay) {
            overlay.classList.remove('show');
        }
        if (toggleBtn) {
            toggleBtn.classList.remove('active');
        }
    }

    // Mobile sidebar toggle functionality
    if (toggleBtn && overlay) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.remove('hide');
            sidebar.classList.add('show');
            overlay.classList.add('show');
            toggleBtn.classList.add('active');
        });

        overlay.addEventListener('click', function() {
            closeSidebar();
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                closeSidebar();
            });
        }

        document.addEventListener('click', function(e) {
            var target = e.target;
            if (!target.closest) return;
            if (target.closest('#aiAssistantRail, #aiSidebar')) return;
            if (target.closest('.prepare-message') || target.closest('.prepare-group-message')) {
                closeSidebar();
            }
        });
    }
}

// Initialize datepicker for payments and forms
$(document).ready(function() {
    // Only initialize datepicker if we're on a page that needs it
    if (document.querySelector('.datepicker') || window.location.pathname.includes('/payments')) {
        $(".datepicker").datepicker({ 
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            yearRange: '-10:+10'
        });
    }
    
    // Specific initialization for payments page
    if (window.location.pathname.includes('/payments')) {
        // Initialize datepicker for payment forms
        $(".datepicker").datepicker({ 
            dateFormat: 'yy-mm-dd',
            changeMonth: true,
            changeYear: true,
            yearRange: '-10:+10'
        });
        
        // Handle releaseDate field updates
        $(".status1").change(function () {
            var thisval = this.value;
            var today = new Date();
            var dd = today.getDate();
            var mm = today.getMonth() + 1;
            var yyyy = today.getFullYear();
            if (dd < 10) { dd = '0' + dd; }
            if (mm < 10) { mm = '0' + mm; }
            var todayStr = yyyy + '-' + mm + '-' + dd;
            
            if(thisval == '1'){
                $(".releaseDate").val(todayStr);
            } else {
                $(".releaseDate").val('1970-01-01');	
            }
        });
    }
});

// Create Account spinner (admin/add-staff.php, admin/add-client.php)
(function() {
  function initCreateAccountSpinner(buttonId) {
    const btn = document.getElementById(buttonId);
    if (!btn) return;

    const form = btn.closest('form');
    if (!form) return;

    function showSpinner() {
      if (form.dataset.submitting === 'true') return;
      form.dataset.submitting = 'true';

      if (!btn.dataset.originalContent) {
        btn.dataset.originalContent = btn.innerHTML;
      }

      const originalContent = btn.dataset.originalContent || btn.innerHTML;
      btn.innerHTML = `
        <svg class="spinner-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle class="spinner-circle-animated" cx="12" cy="12" r="10" stroke-dasharray="24" stroke-dashoffset="24"></circle>
        </svg>
        ${originalContent}
      `;

      btn.disabled = true;
      btn.style.pointerEvents = 'none';
      btn.style.opacity = '0.6';
      btn.style.cursor = 'not-allowed';

      // Safety restore if request hangs
      setTimeout(function() {
        if (form.dataset.submitting === 'true') {
          if (btn.dataset.originalContent) {
            btn.innerHTML = btn.dataset.originalContent;
          }
          btn.disabled = false;
          btn.style.pointerEvents = '';
          btn.style.opacity = '';
          btn.style.cursor = '';
          form.dataset.submitting = 'false';
        }
      }, 30000);
    }

    // Submit event only fires when native browser validation passes
    form.addEventListener('submit', function() {
      // If the page uses accounts.js validation (novalidate), only spin when accounts.js allows it
      if (form.matches('[data-accounts-js="1"]') && form.dataset.allowSpinner !== 'true') {
        return;
      }
      showSpinner();
    });
  }

  function init() {
    initCreateAccountSpinner('create-staff-btn');
    initCreateAccountSpinner('create-staff-btn-attendance');
    initCreateAccountSpinner('create-admin-btn');
    initCreateAccountSpinner('create-admin-btn-attendance');
    initCreateAccountSpinner('create-client-btn');
    initCreateAccountSpinner('staff-edit-save-btn');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

// Table row action menus: lift overflow on .table-responsive/.scroll-x while collapse is open (pairs with style.min.css)
(function () {
  function wrapperForMenu(el) {
    if (!el || !el.closest) return null;
    return el.closest('.table-responsive, .scroll-x');
  }
  document.addEventListener('shown.bs.collapse', function (e) {
    var el = e.target;
    if (!el || !el.classList || !el.classList.contains('toggle-action')) return;
    // Same breakpoint as style.min.css: lifting overflow breaks horizontal scroll on narrow viewports.
    if (window.innerWidth <= 991) return;
    var wrap = wrapperForMenu(el);
    if (wrap) wrap.classList.add('table-action-overflow-open');
  });
  document.addEventListener('hidden.bs.collapse', function (e) {
    var el = e.target;
    if (!el || !el.classList || !el.classList.contains('toggle-action')) return;
    var wrap = wrapperForMenu(el);
    if (!wrap) return;
    if (!wrap.querySelector('.toggle-action.collapse.show')) {
      wrap.classList.remove('table-action-overflow-open');
    }
  });
})();

// Card pointer spotlight: updates --x/--y for radial mask (neutral darken, not theme primary)
(function () {
	var ticking = false;
	var lastX = 0;
	var lastY = 0;

	function updateVars() {
		ticking = false;
		var cards = document.getElementsByClassName('card');
		var n = cards.length;
		for (var i = 0; i < n; i++) {
			var el = cards[i];
			var rect = el.getBoundingClientRect();
			el.style.setProperty('--x', String(lastX - rect.left));
			el.style.setProperty('--y', String(lastY - rect.top));
		}
	}

	document.addEventListener(
		'pointermove',
		function (ev) {
			lastX = ev.clientX;
			lastY = ev.clientY;
			if (ticking) {
				return;
			}
			ticking = true;
			requestAnimationFrame(updateVars);
		},
		{ passive: true }
	);
})();

(function () {
	var numRe = /[-+]?(?:\d*\.\d+|\d+)/g;

	function easeOutCubic(t) {
		return 1 - Math.pow(1 - t, 3);
	}

	function lerpPath(from, to, t) {
		var fromNums = from.match(numRe);
		var toNums = to.match(numRe);
		if (!fromNums || !toNums || fromNums.length !== toNums.length) {
			return t >= 1 ? to : from;
		}
		var i = 0;
		return from.replace(numRe, function () {
			var a = parseFloat(fromNums[i]);
			var b = parseFloat(toNums[i]);
			i += 1;
			return (a + (b - a) * t).toFixed(2);
		});
	}

	function morphPath(el, from, to, duration) {
		if (!el || !to) {
			return;
		}
		if (!from || from === to) {
			el.setAttribute('d', to);
			return;
		}
		var start = null;
		function frame(now) {
			if (start === null) {
				start = now;
			}
			var t = Math.min(1, (now - start) / duration);
			el.setAttribute('d', lerpPath(from, to, easeOutCubic(t)));
			if (t < 1) {
				requestAnimationFrame(frame);
			}
		}
		requestAnimationFrame(frame);
	}

	function initStatSparklines() {
		var wraps = document.querySelectorAll('.stat-sparkline');
		if (!wraps.length) {
			return;
		}
		var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		wraps.forEach(function (wrap, index) {
			var delayMs = reduce ? 0 : index * 70;
			var duration = reduce ? 0 : 720;
			var paths = wrap.querySelectorAll('[data-spark-to]');
			window.setTimeout(function () {
				paths.forEach(function (el) {
					morphPath(el, el.getAttribute('data-spark-from') || el.getAttribute('d'), el.getAttribute('data-spark-to'), duration);
				});
			}, delayMs);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initStatSparklines);
	} else {
		initStatSparklines();
	}
})();








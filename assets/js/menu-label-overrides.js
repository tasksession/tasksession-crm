(function ($) {
  'use strict';

  function collectOrder($sortable) {
    var order = [];
    $sortable.find('.sidebar-menu-sortable__item:not(.is-fixed)').each(function () {
      order.push(String($(this).data('menu-key') || ''));
    });
    return order;
  }

  function collectHidden($sortable) {
    var hidden = [];
    $sortable.find('.sidebar-menu-sortable__item').each(function () {
      var $item = $(this);
      if (String($item.data('available')) !== '1') {
        return;
      }
      if (String($item.find('.sidebar-menu-sortable__toggle').data('hidden')) === '1') {
        hidden.push(String($item.data('menu-key') || ''));
      }
    });
    return hidden;
  }

  function bindVisibilityList($sortable, options) {
    if (!$sortable.length) {
      return;
    }

    var orderField = options.orderField || null;
    var hiddenField = options.hiddenField || null;
    var form = options.form || null;
    var enableSort = options.enableSort !== false;

    function syncHiddenFields() {
      if (orderField) {
        $(orderField).val(JSON.stringify(collectOrder($sortable)));
      }
      if (hiddenField) {
        $(hiddenField).val(JSON.stringify(collectHidden($sortable)));
      }
    }

    if (enableSort) {
      $sortable.sortable({
        items: '> li:not(.is-fixed)',
        handle: '.sidebar-menu-sortable__handle:not(.is-fixed-handle)',
        placeholder: 'sidebar-menu-sortable__placeholder',
        update: syncHiddenFields
      });
    }

    $sortable.on('click', '.sidebar-menu-sortable__toggle', function () {
      var $btn = $(this);
      var hidden = String($btn.data('hidden')) === '1' ? '0' : '1';
      $btn.data('hidden', hidden);
      $btn.attr('data-hidden', hidden);
      var $item = $btn.closest('.sidebar-menu-sortable__item');
      if (hidden === '1') {
        $item.addClass('is-hidden-item');
        $btn.text($btn.data('label-show') || 'Show menu');
      } else {
        $item.removeClass('is-hidden-item');
        $btn.text($btn.data('label-hide') || 'Hide menu');
      }
      syncHiddenFields();
    });

    if (form) {
      $(form).on('submit', function () {
        syncHiddenFields();
      });
    }

    syncHiddenFields();
  }

  $(function () {
    bindVisibilityList($('#sidebarMenuSortable'), {
      orderField: '#sidebar_menu_order_json',
      hiddenField: '#sidebar_menu_hidden_json',
      form: '#menuReorderForm',
      enableSort: true
    });

    bindVisibilityList($('#headerMenuSortable'), {
      hiddenField: '#header_menu_hidden_json',
      form: '#headerMenuForm',
      enableSort: false
    });
  });
})(jQuery);

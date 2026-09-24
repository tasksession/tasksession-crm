<?php
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/stripe_saved_payment.php");
require_once("../payment-api/stripe-php/init.php");

if (!$session->isLoggedIn() || (int) $_SESSION['accountStatus'] !== 2) {
    redirectTo($url . "index.php");
}

require_once("../includes/task_permission.php");
$taskPermissions = TaskPermission::getOrCreate($session->userId);
if (!$taskPermissions->can_view_milestones) {
    redirectTo($url . "client/projects");
}

$id = (int) $session->userId;
$user = User::findById($id);
$username = $user ? ($user->firstName ?? '') : '';
$email = $user ? ($user->email ?? '') : '';
$account_stat = $user ? $user->status : '';

$title = ($lang['Payment Methods'] ?? 'Payment methods') . " | " . $syatem_title;
include("../templates/header.php");

$adminSettings = settings::findById(1);
$stripe_sk = !empty($adminSettings->stripe_sk) ? decryptString($adminSettings->stripe_sk) : '';
$stripe_pk = !empty($adminSettings->stripe_pk) ? decryptString($adminSettings->stripe_pk) : '';
$cards = stripe_list_payment_methods_for_user($id);

$h = static function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
$migrationCss = rtrim($url, '/') . '/import-export/assets/css/migration.css?v=' . time();
$ajaxBase = rtrim($url, '/') . '/ajax/';
?>
<link rel="stylesheet" href="<?php echo $h($migrationCss); ?>">
<style>
.payment-methods-card .pm-table-head {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 1rem;
  padding: 0.75rem 1rem;
  font-size: 0.85rem;
  color: var(--text-muted, #888);
  border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.08));
}
.payment-methods-card .pm-row {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 1rem;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid var(--border-color, rgba(255,255,255,0.06));
}
.payment-methods-card .pm-row:last-of-type { border-bottom: none; }
.payment-methods-card .pm-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: flex-end; }
.add-pm-panel {
  padding: 0 1.25rem 1.25rem;
}
.add-pm-panel:not(.is-hidden) {
  padding-top: 1.25rem;
}
.add-pm-panel.is-hidden { display: none !important; }
.payment-methods-empty-state.is-hidden { display: none !important; }
.payment-methods-card .pm-add-footer.is-hidden { display: none !important; }
#setup-card-element {
  padding: 12px;
  border: 1px solid var(--border-color, #ccc);
  border-radius: 8px;
  background: var(--body-bg, #fff);
}
.payment-methods-empty-actions {
  margin-top: 1rem;
  display: flex;
  justify-content: center;
  width: 100%;
}
.payment-methods-card .pm-add-footer {
  display: flex;
  justify-content: center;
  width: 100%;
}
.payment-methods-card .payment-methods-empty-actions .primary-btn,
.payment-methods-card .pm-add-footer:not(.is-hidden) .primary-btn,
.payment-methods-card #save-setup-card.primary-btn {
  opacity: 1 !important;
  display: inline-flex !important;
  width: auto;
}
</style>

<div class="page-container">
  <div class="container-fluid">
    <div class="row row-eq-height">
      <?php include("../templates/sidebar.php"); ?>
      <div class="page-content">
        <?php include('../templates/top-header.php'); ?>

        <div class="row mt-3 center-col">
          <div class="col-12 migration-intro">
            <div class="migration-action-icon migration-action-icon--import mx-auto" style="margin-bottom: 1rem;" aria-hidden="true">
              <?php echo ts_icon('payments'); ?>
            </div>
            <h2 class="migration-panel-title"><?php echo $h($lang['Payment Methods'] ?? 'Payment methods'); ?></h2>
            <p class="text-muted migration-panel-lead mb-3"><?php echo $h($lang['Payment methods help'] ?? 'Save a card to pay invoices faster and enable subscription auto-charge.'); ?></p>
          </div>

          <div class="col-12 mb-3">
            <?php if (empty($stripe_pk)): ?>
              <div class="alert alert-warning mb-0"><?php echo $h($lang['Stripe not configured'] ?? 'Card saving is not available until Stripe is configured.'); ?></div>
            <?php endif; ?>

            <div class="settings-card payment-methods-card">
              <div class="card-body p-0">
                <?php if (!empty($cards)): ?>
                  <div class="pm-table-head d-none d-md-grid">
                    <span><?php echo $h($lang['Method'] ?? 'Method'); ?></span>
                    <span><?php echo $h($lang['Expires'] ?? 'Expires'); ?></span>
                    <span class="text-end"><?php echo $h($lang['Actions'] ?? 'Actions'); ?></span>
                  </div>
                  <?php foreach ($cards as $card): ?>
                    <?php
                      $expLabel = '—';
                      if (!empty($card['exp_month']) && !empty($card['exp_year'])) {
                          $expLabel = sprintf('%02d / %02d', (int) $card['exp_month'], (int) $card['exp_year'] % 100);
                      }
                      $methodLabel = ucfirst($card['brand'] ?: 'Card') . ' •••• ' . ($card['last4'] ?? '');
                    ?>
                    <div class="pm-row">
                      <div class="d-flex align-items-center col-gap flex-wrap">
                        <strong class="mb-0"><?php echo $h($methodLabel); ?></strong>
                        <?php if (!empty($card['is_default'])): ?>
                          <span class="badge success"><?php echo $h($lang['Default'] ?? 'Default'); ?></span>
                        <?php endif; ?>
                      </div>
                      <div class="text-muted d-flex align-items-center"><?php echo $h($expLabel); ?></div>
                      <div class="pm-actions">
                        <?php if (empty($card['is_default'])): ?>
                          <button type="button" class="btn btn-sm primary-btn set-default-btn" data-pm="<?php echo $h($card['stripe_payment_method_id']); ?>"><?php echo $h($lang['Make default'] ?? 'Make default'); ?></button>
                        <?php endif; ?>
                        <button type="button" class="btn outline-btn delete-pm-btn" data-pm="<?php echo $h($card['stripe_payment_method_id']); ?>"><?php echo $h($lang['Delete'] ?? 'Delete'); ?></button>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div id="payment-methods-empty-state" class="p-4 text-center payment-methods-empty-state">
                    <p class="mb-0 text-muted"><?php echo $h($lang['No saved methods found'] ?? 'No saved methods found.'); ?></p>
                    <?php if (!empty($stripe_pk)): ?>
                      <div class="payment-methods-empty-actions">
                        <button type="button" class="btn primary-btn js-show-add-pm" id="btn-show-add-pm"><?php echo $h($lang['Add payment method'] ?? 'Add payment method'); ?></button>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <?php if (!empty($stripe_pk)): ?>
                  <div id="add-pm-panel" class="add-pm-panel is-hidden" aria-hidden="true">
                    <p class="fw-semibold mb-2 text-start"><?php echo $h($lang['Credit / Debit card'] ?? 'Credit / Debit card'); ?></p>
                    <div id="setup-card-element"></div>
                    <div id="setup-card-errors" class="text-danger mt-2 small"></div>
                    <div class="d-flex col-gap align-items-center mt-3">
                      <div class="checkbox-wrapper-6">
                        <input class="tgl tgl-light" id="setup-set-default" name="setup-set-default" type="checkbox" checked>
                        <label class="tgl-btn" for="setup-set-default"></label>
                      </div>
                      <div>
                        <label for="setup-set-default" class="permission-label mb-0"><?php echo $h($lang['Set as default card'] ?? 'Set as default card'); ?></label>
                      </div>
                    </div>
                    <div class="d-flex flex-wrap col-gap mt-4">
                      <button type="button" class="btn primary-btn" id="save-setup-card"><?php echo $h($lang['Add payment method'] ?? 'Add payment method'); ?></button>
                      <button type="button" class="btn btn-secondary" id="cancel-add-pm"><?php echo $h($lang['Cancel'] ?? 'Cancel'); ?></button>
                    </div>
                  </div>

                  <?php if (!empty($cards)): ?>
                    <div id="payment-methods-add-footer" class="p-4 pt-0 pm-add-footer">
                      <button type="button" class="btn primary-btn js-show-add-pm"><?php echo $h($lang['Add payment method'] ?? 'Add payment method'); ?></button>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<?php if (!empty($stripe_pk)): ?>
<?php
    ob_start();
?>
<script src="https://js.stripe.com/v3/"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var setupIntentUrl = <?php echo json_encode($ajaxBase . 'client-stripe-setup-intent.php'); ?>;
  var saveUrl = <?php echo json_encode($ajaxBase . 'client-payment-method-save.php'); ?>;
  var deleteUrl = <?php echo json_encode($ajaxBase . 'client-payment-method-delete.php'); ?>;
  var defaultUrl = <?php echo json_encode($ajaxBase . 'client-payment-method-set-default.php'); ?>;

  function parseJsonResponse(r) {
    return r.text().then(function (text) {
      try {
        return JSON.parse(text);
      } catch (e) {
        throw new Error(text ? text.substring(0, 200) : ('HTTP ' + r.status));
      }
    });
  }

  if (typeof Stripe === 'undefined') {
    console.error('Stripe.js failed to load');
    return;
  }
  var stripe = Stripe(<?php echo json_encode($stripe_pk); ?>);
  var elements = stripe.elements();
  var card = elements.create('card');
  var setupSecret = null;
  var cardMounted = false;
  var panel = document.getElementById('add-pm-panel');
  var emptyState = document.getElementById('payment-methods-empty-state');
  var addFooter = document.getElementById('payment-methods-add-footer');
  var showBtns = document.querySelectorAll('.js-show-add-pm');
  var cancelBtn = document.getElementById('cancel-add-pm');
  var errEl = document.getElementById('setup-card-errors');

  function loadSetupIntent() {
    setupSecret = null;
    if (errEl) errEl.textContent = '';
    return fetch(setupIntentUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(parseJsonResponse)
      .then(function (data) {
        if (data.success) {
          setupSecret = data.client_secret;
        } else if (errEl) {
          errEl.textContent = data.message || 'Could not start card setup.';
        }
      })
      .catch(function (err) {
        if (errEl) errEl.textContent = err.message || 'Network error. Please try again.';
      });
  }

  function openAddPanel() {
    if (!panel) return;
    panel.classList.remove('is-hidden');
    panel.setAttribute('aria-hidden', 'false');
    if (emptyState) {
      emptyState.classList.add('is-hidden');
      emptyState.setAttribute('aria-hidden', 'true');
    }
    if (addFooter) {
      addFooter.classList.add('is-hidden');
      addFooter.setAttribute('aria-hidden', 'true');
    }
    if (!cardMounted) {
      card.mount('#setup-card-element');
      cardMounted = true;
    }
    loadSetupIntent();
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function closeAddPanel() {
    if (!panel) return;
    panel.classList.add('is-hidden');
    panel.setAttribute('aria-hidden', 'true');
    if (emptyState) {
      emptyState.classList.remove('is-hidden');
      emptyState.setAttribute('aria-hidden', 'false');
    }
    if (addFooter) {
      addFooter.classList.remove('is-hidden');
      addFooter.setAttribute('aria-hidden', 'false');
    }
    if (errEl) errEl.textContent = '';
    setupSecret = null;
  }

  showBtns.forEach(function (btn) {
    btn.addEventListener('click', openAddPanel);
  });
  if (cancelBtn) {
    cancelBtn.addEventListener('click', closeAddPanel);
  }

  var saveBtn = document.getElementById('save-setup-card');
  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      if (errEl) errEl.textContent = '';
      if (!setupSecret) {
        if (errEl) errEl.textContent = 'Please wait for the form to load…';
        loadSetupIntent();
        return;
      }
      saveBtn.disabled = true;
      stripe.confirmCardSetup(setupSecret, { payment_method: { card: card } }).then(function (result) {
        if (result.error) {
          if (errEl) errEl.textContent = result.error.message;
          saveBtn.disabled = false;
          return;
        }
        var pmId = result.setupIntent.payment_method;
        var body = new URLSearchParams();
        body.set('payment_method_id', pmId);
        if (window.tasksessionAppendCsrf) { window.tasksessionAppendCsrf(body); }
        if (document.getElementById('setup-set-default') && document.getElementById('setup-set-default').checked) {
          body.set('set_default', '1');
        }
        fetch(saveUrl, { method: 'POST', body: body, credentials: 'same-origin', headers: window.tasksessionCsrfHeaders({ 'X-Requested-With': 'XMLHttpRequest' }) })
          .then(parseJsonResponse)
          .then(function (data) {
            if (data.success) {
              window.location.reload();
            } else {
              if (errEl) errEl.textContent = data.message || 'Save failed';
              saveBtn.disabled = false;
            }
          })
          .catch(function (err) {
            if (errEl) errEl.textContent = err.message || 'Network error while saving.';
            saveBtn.disabled = false;
          });
      });
    });
  }

  document.querySelectorAll('.delete-pm-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!confirm(<?php echo json_encode($lang['Remove card confirm'] ?? 'Remove this card?'); ?>)) return;
      var body = new URLSearchParams();
      body.set('payment_method_id', btn.getAttribute('data-pm'));
      if (window.tasksessionAppendCsrf) { window.tasksessionAppendCsrf(body); }
      fetch(deleteUrl, { method: 'POST', body: body, credentials: 'same-origin', headers: window.tasksessionCsrfHeaders ? window.tasksessionCsrfHeaders() : {} })
        .then(parseJsonResponse)
        .then(function (data) { if (data.success) location.reload(); else alert(data.message || 'Failed'); });
    });
  });
  document.querySelectorAll('.set-default-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var body = new URLSearchParams();
      body.set('payment_method_id', btn.getAttribute('data-pm'));
      fetch(defaultUrl, { method: 'POST', body: body, credentials: 'same-origin' })
        .then(parseJsonResponse)
        .then(function (data) { if (data.success) location.reload(); else alert(data.message || 'Failed'); });
    });
  });

  if (window.location.hash === '#add') {
    openAddPanel();
  }
});
</script>
<?php
    $GLOBALS['comon_before_body_close_html'] = ob_get_clean();
endif;

include("../templates/main-footer.php");

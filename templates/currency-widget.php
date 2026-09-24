<?php
if (defined('CURRENCY_WIDGET_INCLUDED')) return;
define('CURRENCY_WIDGET_INCLUDED', true);
?>
<div class="currency-widget">
  <p class="last-update">
    Last update | <span id="cw-last">–</span>
  </p>

  <form id="cw-form" action="../ajax/currency-converter.php" method="POST">
    <div class="floating-field">
      <label class="floating-label">Amount</label>
      <input
        type="number"
        name="amount"
        placeholder="Amount"
        step="any"
        min="0.01"
        required
        value="1.00"
      >
    </div>

    <div class="selectors">
      <?php
      $currencies = [
        'USD' => 'USD',
        'EUR' => 'EUR',
        'GBP' => 'GBP',
        'PKR' => 'PKR',
        'INR' => 'INR',
        'CAD' => 'CAD',
        'AUD' => 'AUD',
        'AED' => 'AED',
        'SAR' => 'SAR',
        'CNY' => 'CNY',
        'JPY' => 'JPY',
        'TRY' => 'TRY',
        'RUB' => 'RUB',
        'ZAR' => 'ZAR',
      ];
      ?>

      <div class="floating-field">
        <label class="floating-label">From</label>
        <select name="from" required>
          <?php foreach ($currencies as $code => $label): ?>
            <option value="<?= $code ?>" <?= $code === 'USD' ? 'selected' : '' ?>>
              <?= $label ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="floating-field">
        <label class="floating-label">To</label>
        <select name="to" required>
          <?php foreach ($currencies as $code => $label): ?>
            <option value="<?= $code ?>" <?= $code === 'CAD' ? 'selected' : '' ?>>
              <?= $label ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <button type="submit"><?php echo $lang['CONVERT NOW']; ?></button>
  </form>

  <div id="cw-result" class="result"></div>

  <div class="currency-trend-box" aria-hidden="true">
    <svg class="currency-sparkline" viewBox="0 0 320 70" preserveAspectRatio="none">
      <defs>
        <linearGradient id="sparkStroke" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="var(--primary-color)"></stop>
          <stop offset="100%" stop-color="var(--secondary-color)"></stop>
        </linearGradient>
        <linearGradient id="sparkFill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="var(--primary-color)" stop-opacity="0.24"></stop>
          <stop offset="100%" stop-color="var(--primary-color)" stop-opacity="0.02"></stop>
        </linearGradient>
      </defs>
      <path class="spark-area" d="M0 46 L24 54 L52 40 L80 44 L110 20 L142 56 L172 36 L202 41 L230 34 L258 39 L286 28 L320 34 L320 70 L0 70 Z"></path>
      <path class="spark-line" d="M0 46 L24 54 L52 40 L80 44 L110 20 L142 56 L172 36 L202 41 L230 34 L258 39 L286 28 L320 34"></path>
      <circle class="spark-point" cx="110" cy="20" r="3"></circle>
    </svg>
  </div>
</div>

<script>
  // AJAX conversion function
  function convertCurrency() {
    const form   = document.getElementById('cw-form');
    const result = document.getElementById('cw-result');
    const last   = document.getElementById('cw-last');
    const data   = new FormData(form);

    result.textContent = 'Calculating…';
    fetch(form.action, { method: 'POST', body: data })
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          result.innerHTML =
            `${json.amount} ${json.from} = <strong>` +
            `${json.converted} ${json.to}</strong>`;
          last.textContent = json.last_update;
        } else {
          result.innerHTML = `<span style="color:#d00">${json.error}</span>`;
        }
      })
      .catch(() => {
        result.innerHTML = `<span style="color:#d00">Network error</span>`;
      });
  }

  // Intercept manual submits
  document.getElementById('cw-form').addEventListener('submit', function(e) {
    e.preventDefault();
    convertCurrency();
  });

  // Perform default conversion on load
  window.addEventListener('DOMContentLoaded', function() {
    convertCurrency();
  });
</script>



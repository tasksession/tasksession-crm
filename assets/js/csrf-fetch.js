/*
 * Shared CSRF fetch helper.
 * Adds X-CSRF-Token header and csrf_token body value for mutating requests.
 */
(function () {
  function getCsrfTokenValue() {
    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
    if (tokenMeta) {
      const val = tokenMeta.getAttribute('content') || '';
      if (val) return val;
    }
    if (typeof window.csrfToken === 'string' && window.csrfToken) {
      return window.csrfToken;
    }
    const tokenInput = document.querySelector('input[name="csrf_token"]');
    return tokenInput ? (tokenInput.value || '') : '';
  }

  function applyCsrfToFetchInit(init) {
    const next = init || {};
    const method = String(next.method || 'GET').toUpperCase();
    if (!['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) return next;

    const token = getCsrfTokenValue();
    if (!token) return next;

    const headers = new Headers(next.headers || {});
    if (!headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token);

    let body = next.body;
    if (typeof body === 'string') {
      const contentType = String(headers.get('Content-Type') || '').toLowerCase();
      if (contentType.includes('application/json')) {
        try {
          const parsed = JSON.parse(body || '{}');
          if (parsed && typeof parsed === 'object' && !Array.isArray(parsed) && typeof parsed.csrf_token === 'undefined') {
            parsed.csrf_token = token;
            body = JSON.stringify(parsed);
          }
        } catch (_) {}
      } else if (contentType.includes('application/x-www-form-urlencoded')) {
        const params = new URLSearchParams(body);
        if (!params.has('csrf_token')) {
          params.set('csrf_token', token);
          body = params.toString();
        }
      }
    } else if (typeof FormData !== 'undefined' && body instanceof FormData) {
      if (!body.has('csrf_token')) body.append('csrf_token', token);
    }

    return Object.assign({}, next, { headers, body });
  }

  function fetchWithCsrf(url, init) {
    return fetch(url, applyCsrfToFetchInit(init));
  }

  window.getCsrfTokenValue = getCsrfTokenValue;
  window.applyCsrfToFetchInit = applyCsrfToFetchInit;
  window.fetchWithCsrf = fetchWithCsrf;
})();

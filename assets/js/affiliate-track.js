/**
 * Envia parâmetro de indicação (ex. ?pid=) e document.referrer para admin-ajax,
 * para definir cookie HttpOnly mesmo quando o HTML veio de cache (Cloudflare / plugins).
 */
(function () {
	'use strict';

	var cfg = window.pbAffiliateTrack;
	if (!cfg || !cfg.ajaxUrl || !cfg.nonce || !cfg.param || !cfg.action) {
		return;
	}

	var params;
	try {
		params = new URLSearchParams(window.location.search);
	} catch (e) {
		return;
	}

	var code = params.get(cfg.param);
	code = code && typeof code === 'string' ? code.trim() : '';
	var ref = typeof document.referrer === 'string' ? document.referrer.trim() : '';

	if (!code && !ref) {
		return;
	}

	var pageUrl = window.location.href || '';
	var hashPos = pageUrl.indexOf('#');
	if (hashPos !== -1) {
		pageUrl = pageUrl.slice(0, hashPos);
	}

	var body = new window.FormData();
	body.append('action', cfg.action);
	body.append('nonce', cfg.nonce);
	body.append('code', code);
	body.append('referrer_url', ref);
	body.append('page_url', pageUrl);

	window
		.fetch(cfg.ajaxUrl, {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
		})
		.catch(function () {
			/* silencioso: tracking é melhor esforço */
		});
})();

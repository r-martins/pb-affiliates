/**
 * Montar link de indicação a partir de um URL interno (produto, categoria, etc.).
 */
(function () {
	if (typeof pbAffLinkBuilder === 'undefined') {
		return;
	}

	var cfg = pbAffLinkBuilder;
	var i18n = cfg.i18n || {};
	var paste = document.getElementById('pb-aff-lb-paste');
	var btn = document.getElementById('pb-aff-lb-generate');
	var wrap = document.getElementById('pb-aff-lb-result');
	var out = document.getElementById('pb-aff-lb-out');
	var copyBtn = document.getElementById('pb-aff-lb-copy');
	var notice = document.getElementById('pb-aff-lb-notice');

	function normalizeHost(h) {
		if (!h) {
			return '';
		}
		return String(h).toLowerCase().replace(/^www\./, '');
	}

	function siteHostOk(urlStr) {
		try {
			var u = new URL(urlStr);
			return normalizeHost(u.hostname) === normalizeHost(cfg.siteHost);
		} catch (e) {
			return false;
		}
	}

	function resolvePasted(raw) {
		var s = (raw || '').trim();
		if (!s) {
			return null;
		}
		if (s.indexOf('//') === 0) {
			s = 'https:' + s;
		}
		var baseStr = cfg.homeUrl || window.location.origin + '/';
		var base;
		try {
			base = new URL(baseStr);
		} catch (e) {
			return null;
		}
		if (s.charAt(0) === '/' && s.charAt(1) !== '/') {
			try {
				return new URL(s, base).toString();
			} catch (e2) {
				return null;
			}
		}
		try {
			return new URL(s).toString();
		} catch (e3) {
			try {
				return new URL(s, base).toString();
			} catch (e4) {
				return null;
			}
		}
	}

	function addReferral(urlStr) {
		var param = (cfg.referralParam && String(cfg.referralParam).trim()) || 'pid';
		var code = cfg.affiliateCode != null ? String(cfg.affiliateCode) : '';
		if (!code) {
			return urlStr;
		}
		var u = new URL(urlStr);
		var cur = u.searchParams.get(param);
		if (cur === null || cur === '') {
			u.searchParams.set(param, code);
		}
		return u.toString();
	}

	function showNotice(text, isErr) {
		if (!notice) {
			return;
		}
		var t = text != null ? String(text).trim() : '';
		if (isErr && !t) {
			t = i18n.invalidUrl || '';
		}
		notice.textContent = t;
		notice.hidden = !t;
		notice.className =
			'pb-aff-lb-notice' +
			(t && isErr ? ' pb-aff-zip1-notice--error woocommerce-error' : t ? ' woocommerce-message' : '');
	}

	function hideNotice() {
		if (!notice) {
			return;
		}
		notice.hidden = true;
		notice.textContent = '';
		notice.className = 'pb-aff-lb-notice';
	}

	if (btn && paste) {
		btn.addEventListener('click', function () {
			hideNotice();
			var resolved = resolvePasted(paste.value);
			if (!resolved) {
				showNotice(i18n.invalidUrl || i18n.needUrl || '', true);
				return;
			}
			if (cfg.siteHost && !siteHostOk(resolved)) {
				showNotice(i18n.notOurSite || i18n.invalidUrl || '', true);
				return;
			}
			var finalUrl = addReferral(resolved);
			if (out) {
				out.value = finalUrl;
			}
			if (wrap) {
				wrap.removeAttribute('hidden');
			}
		});
	}

	if (copyBtn && out) {
		copyBtn.addEventListener('click', function () {
			out.focus();
			out.select();
			out.setSelectionRange(0, 99999);
			var done = copyBtn.getAttribute('data-label-done') || i18n.copied || 'OK';
			var orig = copyBtn.textContent;
			function feedback() {
				copyBtn.textContent = done;
				copyBtn.disabled = true;
				setTimeout(function () {
					copyBtn.textContent = orig;
					copyBtn.disabled = false;
				}, 2000);
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(out.value).then(feedback).catch(function () {
					try {
						document.execCommand('copy');
						feedback();
					} catch (e) {}
				});
			} else {
				try {
					document.execCommand('copy');
					feedback();
				} catch (e) {}
			}
		});
	}
})();

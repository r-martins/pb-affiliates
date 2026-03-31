/**
 * Encurtamento zip1.io — POST direto à API pública (sem proxy no WordPress).
 * URL Shortener by zip1.io
 */
(function () {
	if (typeof pbAffZip1 === 'undefined') {
		return;
	}
	var box = document.querySelector('[data-pb-aff-zip1]');
	if (!box) {
		return;
	}

	var notice = document.getElementById('pb-aff-zip1-notice');
	var panel = document.getElementById('pb-aff-zip1-panel');
	var formPanel = document.getElementById('pb-aff-zip1-form-panel');
	var shortInp = document.getElementById('pb-aff-zip1-short-input');
	var statsLine = box.querySelector('.pb-aff-zip1-stats-line');
	var statsLink = document.getElementById('pb-aff-zip1-stats-link');
	var aliasInp = document.getElementById('pb-aff-zip1-alias');
	var submitBtn = document.getElementById('pb-aff-zip1-submit');
	var replaceBtn = document.getElementById('pb-aff-zip1-replace');
	var copyBtn = document.getElementById('pb-aff-zip1-copy');
	var sourceUrlInp = document.getElementById('pb-aff-zip1-source-url');
	var i18n = pbAffZip1.i18n || {};

	function getLongUrlToShorten() {
		var fromInput = sourceUrlInp && sourceUrlInp.value ? String(sourceUrlInp.value).trim() : '';
		if (fromInput) {
			return fromInput;
		}
		return pbAffZip1.longUrl || '';
	}

	function isValidHttpUrl(s) {
		return /^https?:\/\/.+/i.test((s || '').trim());
	}

	/** Adiciona o parâmetro de indicação e o código do afiliado se faltarem no URL. */
	function ensureReferralQuery(url) {
		var param = (pbAffZip1.referralParam && String(pbAffZip1.referralParam).trim()) || 'pid';
		var code = pbAffZip1.affiliateCode != null ? String(pbAffZip1.affiliateCode) : '';
		if (!code || !url) {
			return url;
		}
		var u;
		try {
			u = new URL(url);
		} catch (e) {
			return url;
		}
		var cur = u.searchParams.get(param);
		if (cur === null || cur === '') {
			u.searchParams.set(param, code);
			return u.toString();
		}
		return url;
	}

	/** Mensagem final se API/i18n não trouxer texto (evita caixa de erro vazia). */
	function fallbackErrorText() {
		var e = i18n.err && String(i18n.err).trim();
		if (e) {
			return e;
		}
		var f = i18n.fallback && String(i18n.fallback).trim();
		return f || 'Erro.';
	}

	function statsUrlFromShort(shortUrl) {
		try {
			var u = new URL(shortUrl);
			var path = u.pathname.replace(/^\/+|\/+$/g, '');
			if (!path) {
				return '';
			}
			return 'https://zip1.io/stats/' + encodeURIComponent(path);
		} catch (e) {
			return '';
		}
	}

	function aliasInvalid(raw) {
		var a = (raw || '').trim();
		if (!a) {
			return false;
		}
		if (/^[a-zA-Z0-9\-]{3,16}$/.test(a)) {
			return false;
		}
		if (/[\r\n\u0000/\\]/.test(a)) {
			return true;
		}
		var len = a.length;
		return len < 1 || len > 16;
	}

	function showNotice(text, isError) {
		if (!notice) return;
		var t = text != null ? String(text).trim() : '';
		if (isError && !t) {
			t = fallbackErrorText();
		}
		notice.textContent = t;
		notice.hidden = false;
		notice.className =
			'pb-aff-zip1-notice' + (isError ? ' pb-aff-zip1-notice--error woocommerce-error' : ' woocommerce-message');
	}

	function hideNotice() {
		if (!notice) return;
		notice.hidden = true;
		notice.textContent = '';
		notice.className = 'pb-aff-zip1-notice';
	}

	function setHidden(el, hidden) {
		if (!el) return;
		if (hidden) {
			el.setAttribute('hidden', 'hidden');
		} else {
			el.removeAttribute('hidden');
		}
	}

	function bindCopy(btn, inp) {
		if (!btn || !inp) return;
		btn.addEventListener('click', function () {
			inp.focus();
			inp.select();
			inp.setSelectionRange(0, 99999);
			var done = btn.getAttribute('data-label-done') || i18n.copied;
			var orig = btn.textContent;
			function feedback() {
				btn.textContent = done;
				btn.disabled = true;
				setTimeout(function () {
					btn.textContent = orig;
					btn.disabled = false;
				}, 2000);
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(inp.value).then(feedback).catch(function () {
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

	bindCopy(copyBtn, shortInp);

	if (replaceBtn && formPanel && panel) {
		replaceBtn.addEventListener('click', function () {
			hideNotice();
			setHidden(panel, true);
			setHidden(formPanel, false);
			if (aliasInp) {
				aliasInp.value = '';
				aliasInp.focus();
			}
		});
	}

	if (!submitBtn) return;

	function applySuccess(shortUrl, statsUrl) {
		if (shortInp) {
			shortInp.value = shortUrl;
		}
		if (statsLink && statsUrl) {
			statsLink.href = statsUrl;
			setHidden(statsLine, false);
		}
		setHidden(formPanel, true);
		setHidden(panel, false);
	}

	submitBtn.addEventListener('click', function () {
		hideNotice();
		var longUrl = getLongUrlToShorten();
		longUrl = ensureReferralQuery(longUrl);
		if (sourceUrlInp && longUrl) {
			sourceUrlInp.value = longUrl;
		}
		if (!longUrl) {
			showNotice((i18n.noLongUrl && String(i18n.noLongUrl).trim()) || fallbackErrorText(), true);
			return;
		}
		if (!isValidHttpUrl(longUrl)) {
			showNotice((i18n.badLongUrl && String(i18n.badLongUrl).trim()) || fallbackErrorText(), true);
			return;
		}
		var alias = aliasInp ? aliasInp.value.trim() : '';
		if (aliasInvalid(alias)) {
			showNotice((i18n.badAlias && String(i18n.badAlias).trim()) || fallbackErrorText(), true);
			return;
		}
		var busy = i18n.busy || '…';
		var origLabel = submitBtn.textContent;
		submitBtn.disabled = true;
		submitBtn.textContent = busy;

		var body = { url: longUrl };
		if (alias) {
			body.alias = alias;
		}

		fetch(pbAffZip1.createUrl, {
			method: 'POST',
			credentials: 'omit',
			mode: 'cors',
			headers: {
				'Content-Type': 'application/json',
				Accept: 'application/json',
			},
			body: JSON.stringify(body),
		})
			.then(function (r) {
				return r.text().then(function (text) {
					var data = {};
					try {
						data = text ? JSON.parse(text) : {};
					} catch (e) {
						data = {};
					}
					var apiErr = '';
					if (data && data.error != null) {
						apiErr =
							typeof data.error === 'string'
								? data.error.trim()
								: data.error && data.error.message
									? String(data.error.message).trim()
									: '';
					}
					if (r.status === 409) {
						throw new Error((i18n.conflict && String(i18n.conflict).trim()) || apiErr || fallbackErrorText());
					}
					if (r.status === 429) {
						throw new Error((i18n.ratelimit && String(i18n.ratelimit).trim()) || apiErr || fallbackErrorText());
					}
					if (!r.ok) {
						throw new Error(apiErr || fallbackErrorText());
					}
					if (!data.short_url) {
						throw new Error(apiErr || fallbackErrorText());
					}
					return data.short_url;
				});
			})
			.then(function (shortUrl) {
				var statsUrl = statsUrlFromShort(shortUrl);
				applySuccess(shortUrl, statsUrl);
			})
			.catch(function (err) {
				var msg = err && err.message ? String(err.message).trim() : '';
				showNotice(msg || fallbackErrorText(), true);
			})
			.finally(function () {
				submitBtn.disabled = false;
				submitBtn.textContent = origLabel;
			});
	});
})();

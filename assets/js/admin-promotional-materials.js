/* global wp, pbAffPromoMaterials */
(function ($) {
	'use strict';

	function escHtml(s) {
		return $('<div>').text(String(s)).html();
	}

	$(function () {
		var attInput = $('#pb_aff_material_attachment_id');
		var preview = $('#pb_aff_material_file_preview');
		var clearBtn = $('#pb_aff_material_clear_file');
		var selectBtn = $('#pb_aff_material_select_file');
		if (!attInput.length || typeof wp === 'undefined' || !wp.media) {
			return;
		}

		function bindPromoUploadParam(uploader) {
			if (!uploader || !uploader.bind) {
				return;
			}
			uploader.bind('BeforeUpload', function (up) {
				up.settings.multipart_params = up.settings.multipart_params || {};
				up.settings.multipart_params.pb_aff_promo_upload = '1';
			});
		}

		selectBtn.on('click', function (ev) {
			ev.preventDefault();
			var frame = wp.media({
				title: pbAffPromoMaterials.frameTitle,
				button: { text: pbAffPromoMaterials.frameButton },
				multiple: false,
			});
			frame.on('open', function () {
				var lib = frame.uploader;
				if (lib && lib.uploader) {
					bindPromoUploadParam(lib.uploader);
				}
			});
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				attInput.val(a.id);
				clearBtn.show();
				var html = '';
				if (a.type === 'image') {
					var imgUrl =
						(a.sizes && a.sizes.medium && a.sizes.medium.url) ||
						(a.sizes && a.sizes.thumbnail && a.sizes.thumbnail.url) ||
						a.url;
					if (imgUrl) {
						html += '<div class="pb-aff-thumb-wrap"><img src="' + escHtml(imgUrl) + '" alt="" /></div>';
					}
				}
				var name = a.filename || a.title || '#' + a.id;
				html += '<p class="pb-aff-filename"><strong>' + escHtml(name) + '</strong></p>';
				preview.html(html);
			});
			frame.open();
		});

		clearBtn.on('click', function (ev) {
			ev.preventDefault();
			attInput.val('');
			preview.empty();
			clearBtn.hide();
		});
	});
})(jQuery);

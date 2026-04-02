document.addEventListener('DOMContentLoaded', () => {
	const root = document.querySelector('.kt-review-images-admin');

	if (!root || typeof wp === 'undefined' || !wp.media) {
		return;
	}

	const input = root.querySelector('[data-kt-review-image-id]');
	const preview = root.querySelector('[data-kt-review-image-preview]');
	const previewWrap = root.querySelector('[data-kt-review-image-preview-wrap]');
	const selectButton = root.querySelector('[data-kt-review-image-select]');
	const removeButton = root.querySelector('[data-kt-review-image-remove]');

	if (!input || !preview || !previewWrap || !selectButton || !removeButton) {
		return;
	}

	const frame = wp.media({
		title: 'Select review image',
		button: {
			text: 'Use this image',
		},
		library: {
			type: 'image',
		},
		multiple: false,
	});

	frame.on('select', () => {
		const attachment = frame.state().get('selection').first().toJSON();

		input.value = attachment.id || '';
		preview.src = (attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url) || attachment.url || '';
		previewWrap.hidden = false;
		removeButton.hidden = false;
		selectButton.textContent = 'Replace image';
	});

	selectButton.addEventListener('click', () => {
		frame.open();
	});

	removeButton.addEventListener('click', () => {
		input.value = '';
		preview.src = '';
		previewWrap.hidden = true;
		removeButton.hidden = true;
		selectButton.textContent = 'Select image';
	});
});

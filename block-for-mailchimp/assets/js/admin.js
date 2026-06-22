function mcbHandleShortcode(id) {
	var input = document.querySelector('#mcbFrontShortcode-' + id + ' input');
	var tooltip = document.querySelector('#mcbFrontShortcode-' + id + ' .tooltip');

	if (navigator.clipboard && navigator.clipboard.writeText) {
		navigator.clipboard.writeText(input.value).then(function() {
			tooltip.innerHTML = wp.i18n.__('Copied Successfully!', 'block-for-mailchimp');
			setTimeout(function() {
				tooltip.innerHTML = wp.i18n.__('Copy To Clipboard', 'block-for-mailchimp');
			}, 1500);
		});
	} else {
		// Fallback for older browsers or insecure contexts
		input.select();
		input.setSelectionRange(0, 30);
		document.execCommand('copy');
		tooltip.innerHTML = wp.i18n.__('Copied Successfully!', 'block-for-mailchimp');
		setTimeout(function() {
			tooltip.innerHTML = wp.i18n.__('Copy To Clipboard', 'block-for-mailchimp');
		}, 1500);
	}
}
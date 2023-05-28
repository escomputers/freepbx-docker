$('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
	var evt = document.createEvent('Event');
	evt.initEvent('autosize:update', true, false);
	$('textarea')[0].dispatchEvent(evt);
});

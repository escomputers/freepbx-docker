var fpbxClass = Class.extend({
	params: {},
	init: function() {
		var self = this;
		var path = window.location.pathname.toString().split('/');
		path[path.length - 1] = 'ajax.php';
		if (typeof window.location.origin == 'undefined') {
			// Oh look, IE. Hur Dur, I'm a bwowsah.
			window.location.origin = window.location.protocol+'//'+window.location.host;
			if (window.location.port.length != 0) {
				window.location.origin = window.location.origin+':'+window.location.port;
			}
		}
		this.ajaxurl = window.location.origin + path.join('/');
		if (window.location.search.length) {
			var params = window.location.search.split(/\?|&/);
			for (var i = 0, len = params.length; i < len; i++) {
				if (res = params[i].match(/(.+)=(.+)/)) {
					self.params[res[1]] = res[2];
				}
			}
		}
	}
});
window.FreePBX = new fpbxClass();

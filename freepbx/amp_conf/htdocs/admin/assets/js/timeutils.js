var Timeutils = Class.extend({
	init: function() {
	},
	dateTimeFormatter: function(unixtimestamp) {
		unixtimestamp = parseInt(unixtimestamp);
		return moment.unix(unixtimestamp).tz(timezone).format(datetimeformat);
	},
	timeFormatter: function(unixtimestamp) {
		unixtimestamp = parseInt(unixtimestamp);
		return moment.unix(unixtimestamp).tz(timezone).format(timeformat);
	},
	dateFormatter: function(unixtimestamp) {
		unixtimestamp = parseInt(unixtimestamp);
		return moment.unix(unixtimestamp).tz(timezone).format(dateformat);
	},
	humanDiff: function(unixtimestamp) {
		unixtimestamp = parseInt(unixtimestamp);
		return moment.duration(moment.unix(unixtimestamp).diff(moment(new Date()))).humanize(true);
	}
});
window.FreePBX.Timeutils = new Timeutils();

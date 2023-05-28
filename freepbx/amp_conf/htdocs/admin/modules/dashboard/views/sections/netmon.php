<?php

if (!class_exists('\FreePBX\modules\Dashboard\Netmon')) {
	include __DIR__."/../../classes/Netmon.class.php";
}

$netmon = new \FreePBX\modules\Dashboard\Netmon();
// Wait until we actually have some data back
$count = 5;
$stats = $netmon->getStats();
if(!$stats['status']) {
	echo "Error. Unable to get Netmon stats\n";
	return;
}

// Excellent. We have data!
?>
<script>var netmonData = {};</script>
<div class="row" id="netmon">
	<div class="col-sm-2">
		<div class="btn-group-vertical">
<?php

$first = false;
echo "<script>netmonData[".time()."] = ".json_encode($stats['data'])."</script>";
foreach ($stats['data'] as $name => $row) {
	// If this is lo, skip
	if ($name === 'lo') {
		continue;
	}
	// If there has been no traffic received on this interface, skip
	// Why rx? If there's traffic coming in AT ALL, it means it's plugged
	// into something. Even if it's not being used.
	if (!isset($row['rx']) || $row['rx']['bytes'] == 0) {
		continue;
	}

	if (!$first) {
		// We need to remember the first interface
		$first = $name;
	}
	// This is a valid interface, so give it a button.
	echo "<button type='button' class='btn btn-default' data-intname='$name'>$name</button>\n";
}
?>
		</div>
	</div>
	<div class='col-sm-10' id='netmonout' style='min-height:200px; width: 75%'><p><i><?php echo sprintf(_("Loading Interface %s..."), $first); ?></i></p></div>
</div>

<script>
var source = new EventSource(FreePBX.ajaxurl+"?module=dashboard&command=netmon", {withCredentials:true});
source.addEventListener("new-msgs", function(event){
	var data = JSON.parse(event.data);
	if(!data.status) {
		fpbxToast(data.message,_('Error'),'error')
		source.close();
		return;
	}
	var date = Date.now() / 1000 | 0;
	netmonData[date] = data.data
}, false);

// Remote anything hanging around if we've been reloaded.
if (typeof window.Netchart === "undefined") {
	// New instantiation
	window.NetchartObj = Class.extend({
		refresh: false,
		refreshperiod: 500,
		chartdata: [{
			xValueType: "dateTime",
			xValueFormatString: "h:mm:ss tt",
			type: "splineArea",
			dataPoints: [],
			toolTipContent: "<span style='color: {color};'>RX: <strong>{y}</strong>kB/sec</span>",
		},
		{	name: "TX Kb/s",
			xValueType: "dateTime",
			xValueFormatString: "h:mm:ss tt",
			type: "splineArea",
			dataPoints: [],
			toolTipContent: "<span style='color: {color};'>TX: <strong>{y}</strong>kB/sec</span>",
		}],
		init: function(intname) {
			var self = this;
			if (typeof intname == "undefined") {
				intname = "";
			}
			this.chart =  new CanvasJS.Chart('netmonout', {
				title: { text: _("Interface") + " " + intname },
				data: self.chartdata,
				saxisX: { valueFormatString: " ", tickLength: 0 },
				axisY: { valueFormatString: " ", tickLength: 0 },
				toolTip: { shared: true },
			});
			this.set_binds();
			this.load_chart(intname);
		},
		set_binds: function() {
			var self = this;
			// Make sure there are none hanging around
			$("#netmon").off("click", "button");

			$("#netmon").on("click", "button", function(e) {
				var intname = $(e.target).data('intname');
				if (typeof intname !== "undefined") {
					self.clear_timeout();
					self.load_chart(intname);
				} else {
					console.log("Bug. No intname from e!", e);
				}
			});
		},
		clear_timeout: function() {
			if (this.refresh !== false) {
				clearTimeout(this.refresh);
				this.refresh = false;
			}
		},
		load_chart: function(intname) {
			var self = this;
			self.render_chart(intname, netmonData);
			self.clear_timeout();
			self.refresh = setTimeout(function() { self.load_chart(intname); }, self.refreshperiod);
		},
		render_chart: function(intname, data) {
			var self = this;
			var count = 0;
			self.chartdata[0]['dataPoints'] = [];
			self.chartdata[1]['dataPoints'] = [];
			// Loop through all the timestamps
			Object.keys(data).slice(-40).forEach(function(k) {
				var rx, lastrx, rxbytes, tx, lasttx, txbytes;
				var timestamp = k * 1000;
				if (typeof data[k][intname] == "undefined") {
					self.chartdata[0]['dataPoints'][count] = { x: timestamp, y: 0, rawval: 0 };
					self.chartdata[1]['dataPoints'][count] = { x: timestamp, y: 0, rawval: 0 };
				} else {
					rx = data[k][intname]['rx']['bytes'];
					tx = data[k][intname]['tx']['bytes'];
					if (count === 0) {
						lastrx = rx;
						lasttx = tx;
					} else {
						lastrx = self.chartdata[0]['dataPoints'][count-1]['rawval'];
						lasttx = self.chartdata[1]['dataPoints'][count-1]['rawval'];
					}
					rxbytes = rx - lastrx;
					txbytes = tx - lasttx;

					self.chartdata[0]['dataPoints'][count] = { x: timestamp, y: Math.floor(rxbytes/1024), rawval: rx };
					self.chartdata[1]['dataPoints'][count] = { x: timestamp, y: Math.floor(txbytes/1024), rawval: tx };
				}
				count++;
			});
			if (typeof self.chart.options.title !== "undefined") {
				self.chart.options.title.text = _("Interface") + " " + intname;
			}
			self.chart.render();
		},
	});

}

// (Re?)Create the window.Netchart object and start it.
window.Netchart = new window.NetchartObj("<?php echo $first; ?>");

</script>

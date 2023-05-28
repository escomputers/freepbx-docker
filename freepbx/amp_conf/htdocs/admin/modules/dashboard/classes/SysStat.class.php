<?php
// vim: set ai ts=4 sw=4 ft=php:
//
// License for all code of this FreePBX module can be found in the license file inside the module directory
// Copyright 2006-2014 Schmooze Com Inc.

class SysStat {
	public function getHTML() {
		$html  = "<div id='builtin_sysstat'>";
		$html .= " <div class='row'>";
		$html .= "  <div class='col-sm-6' id='sysstat_swap'></div>";
		$html .= "  <div class='col-sm-6' id='sysstat_mem'></div>";
		$html .= " </div>";
		$html .= " <div class='row'>";
		$html .= "  <div class='col-sm-6 text-center' id='sysstat_swap_text'>Swap</div>";
		$html .= "  <div class='col-sm-6 text-center' id='sysstat_mem'>Ram</div>";
		$html .= "</div>";
		$html .= "</div>";
		$html .= "<script type='text/javascript'>".$this->getJs()."</script>";
		return $html;
	}

	private function getJs() {
		if (!class_exists('MemInfo')) {
			include 'MemInfo.class.php';
		}
		$m = new MemInfo();
		$m = $m->getAll();
		$swap = $m['swap'];
		$mem = $m['mem'];

		$freeswap = $swap['free'];
		$usedswap = $swap['used'];
		$swapfreepct = $swap['freepct'];
		$swapusedpct = $swap['usedpct'];

		$usedmem = $mem['used'];
		$freemem = $mem['free'];
		$buffers = $mem['buffers'];
		$cache = $mem['cached'];

		$js = "

$.elycharts.templates['swap'] = {
 type : 'pie',
 height : '200',
 labels: ['$swapfreepct%\\nFree', '$swapusedpct%\\nUsed'],
 defaultSeries : { 
  plotProps : { stroke : 'black', 'stroke-width' : 2, opacity : 0.7, },
  values: [{ plotProps: { fill: 'green' } }, { plotProps: { fill: 'red' } }],
  label: { active: true, props: { fill: 'black', 'font-size': '14' } },
  startAnimation : { active : true, type : 'avg' },
  highlight: { newProps: { opacity: 1 } },
 },
};

$.elycharts.templates['mem'] = {
 type : 'pie',
 height : '200',
 defaultSeries : { 
  plotProps : { stroke : 'black', 'stroke-width' : 2, opacity : 0.7, },
  values: [{ plotProps: { fill: 'red' } }, { plotProps: { fill: 'orange' } }, { plotProps: { fill: 'green' } },{ plotProps: { fill: 'lightgreen' } }],
  label: { active: true, props: { fill: 'black', 'font-size': '14' } },
  startAnimation : { active : true, type : 'avg' },
  highlight: { newProps: { opacity: 1 } },
 },
};

window.observers['builtin_sysstat'] = function() {
  $('#sysstat_swap').chart({ template : 'swap', values : { serie1 : [$swapfreepct, $swapusedpct] }, });
  $('#sysstat_mem').chart({ template : 'mem', 
    labels: ['Used', 'Buffers', 'Free', 'Cache'],
    values : { 
      inside : [$usedmem, $buffers, $freemem, $cache], 
    },
  });
};";
		return $js;
	}
}

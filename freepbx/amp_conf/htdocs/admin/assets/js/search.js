// FreePBX Search
//
var SearchC = Class.extend({
  init: function() {
    // initialize
    var self = this;
    this.moduleSearch = new Bloodhound({
      datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
      queryTokenizer: Bloodhound.tokenizers.whitespace,
      limit: 10,
      prefetch: {
        ttl: 1000,
        url: window.FreePBX.ajaxurl+'?module=search&command=global',
       filter: function(data) { return $.map(data, function(t) { return { value: t.text, o: t } }); },
      }
    });
    this.moduleSearch.initialize();

    this.itemSearch = new Bloodhound({
      datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
      queryTokenizer: Bloodhound.tokenizers.whitespace,
      limit: 10,
      remote: {
        ttl: 100,
        url: window.FreePBX.ajaxurl+'?module=search&command=local&query=%QUERY&section='+window.FreePBX.params.display,
        filter: function(data) { return $.map(data, function(t) { return { raw: true, value: t.text, o: t } }); },
      },
    });
    this.itemSearch.initialize();

    $('#fpbxsearch .typeahead').typeahead({
      hint: true,
      highlight: true,
      minLength: 1
    }, {
      name: 'moduleSearch',
      displayKey: 'value',
      source: this.moduleSearch.ttAdapter(),
      templates: {
        suggestion: self.genItemHtml,
      }
    }, {
      name: 'itemSearch',
      displayKey: 'value',
      source: this.itemSearch.ttAdapter(),
      templates: {
        suggestion: self.genItemHtml,
      }
    })
    .bind("typeahead:selected", function(o,d,n) { self.processSearchClick(o,d,n); })
    .focus();
  },

  processSearchClick: function(o, d, name) {
    if (name == "moduleSearch") {
      return this.processModuleClick(d.o);
    } else if (name == "itemSearch") {
      return this.processItemClick(d);
    } else {
      //just follow the link, name is undefined when clicked manually
      return false;
    }
  },

  processModuleClick: function(o) {
    if (o.type == "get") {
      window.location = o.dest;
      return true;
    }
    console.log("No idea what to do with this: ", o);
  },

  processItemClick: function(o) {
    var item = o.o;

    var href;

    if(item.dest.match(/\/\//)) {
      // It's an explicit link. (check for // anywhere) Go there.
      href = item.dest;
    } else {
      // It's a relative link. Let's rebuild our URL. Note that IE compat in
      // w.l.origin is in header, where we preload our ajaxurl and modulename
      href = window.location.origin;
      if (item.dest.match(/^\//)) {
        // It starts with a slash
        href += item.dest;
      } else {
        // It's inside freepbx.
        href += window.location.pathname + item.dest;
      }
    }
    if (typeof(item.search) != "undefined") {
      href += "?" + item.search;
    }
    if (typeof(item.hash) != "undefined") {
      href += "#" + item.hash;
    }
    // Actually go there!
    window.location.href = href;
    return true;
  },

  genItemHtml: function(o) {
    if (o.o.type == "text") {
      return "<div>"+o.value+"</div>";
    } else {
      return "<div>"+o.value+"</div>";
    }
  },
});

$(document).ready(function() {
	if(typeof fpbx !== "undefined" && typeof fpbx.conf.ver !== "undefined") {
		window.Search = new SearchC();
	}
});

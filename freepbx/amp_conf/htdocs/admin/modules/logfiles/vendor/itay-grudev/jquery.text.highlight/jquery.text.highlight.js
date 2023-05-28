/**
 * jQuery.text.highlight.js
 *
 * Highlights text within HTML
 *
 * Copyright (c) 2016 Itay Grudev <itay[]grudev.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
// ==ClosureCompiler==
// @output_file_name jquery.text.highlight.min.js
// @compilation_level ADVANCED_OPTIMIZATIONS
// ==/ClosureCompiler==
(function ( $, undefined ) {
  // Apply highlight
  $['fn']['textHighlight'] = function ( term, options ) {
    // Default options
    options = $['extend']( {}, {
      'element': 'mark',
      'class': '',
      'caseSensitive': false,
      'ignore': undefined
    }, options);

    function processNode( term, node ) {
      if( node.nodeType == Node.TEXT_NODE ) {
        var pos = null, length;
        if( term instanceof RegExp ) {
          // If the ignoreCase of the RegEx has incorrect setting
          if( term.ignoreCase == options['caseSensitive'] ) {
            var flags = (options['caseSensitive'])? '' : 'i';
            flags = term['flags'].replace('i', '') + flags;
            term = new RegExp( term.source, flags );
          }
          var match = term.exec( node.data );
          if( match !== null ) {
            pos = match.index;
            length = match[0].length;
          }
        } else if( typeof term == 'string' ) {
          if( term.length == 0 ) return 0;
          if( options['caseSensitive'] )
            pos = node.data.indexOf( term );
          else pos = node.data.toUpperCase().indexOf( term.toUpperCase() );
          length = term.length;
        }
        if( pos !== null && pos !== -1 ) {
          var highlightedNode = document.createElement( options['element'] );
          highlightedNode.className = options['class'];
          $(highlightedNode)['attr']( 'data-text-highlight', '' );
          var beginingNode = node.splitText( pos );
          var endNode = beginingNode.splitText( length );
          var middleNode = beginingNode.cloneNode( true );
          highlightedNode.appendChild( middleNode );
          beginingNode.parentNode.replaceChild( highlightedNode, beginingNode );
          return 1;
        }
      } else if( node.nodeType == Node.ELEMENT_NODE && node.childNodes &&
                 ! /(script|style)/i.test( node.tagName ) &&
                 ! node.hasAttribute('data-text-highlight') ) {
        if( options['ignore'] !== undefined && $(node).is(options['ignore']) )
          return 0;
        for( var i = 0; i < node.childNodes.length; ++i ) {
          i += processNode( term, node.childNodes[i] );
        }
      }

      return 0;
    }

    return this['each'](function () {
      if( term instanceof RegExp || typeof term == 'string' ) {
        // The term is a regular expression or a string
        processNode( term, this );
      } else if( typeof term == 'object' && term.length !== undefined ) {
        // The term is an array containing any of the two above
        for( var i = 0; i < term.length; ++i ) {
          processNode( term[i], this );
        }
      }
    });
  }

  // Remove highlight function
  $['fn']['removeHighlight'] = function ( options ) {
    return this['each']( function () {
      $(this)['find']('[data-text-highlight]')['each']( function() {
        this.parentNode.replaceChild( this.firstChild, this );
      });
      this.normalize();
    });
  }
})( jQuery );

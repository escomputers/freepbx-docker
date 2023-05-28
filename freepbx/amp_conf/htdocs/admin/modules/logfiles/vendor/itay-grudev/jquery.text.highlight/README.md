jQuery Text Highlight
=====================

jQuery Text Highlight is a plugin for highlighting Words, Text or Regular
Expressions within the DOM.

_Feature requests are welcome._

Usage Examples
--------------

Given the following html:

```html
<h1>Hello World</h1>
```

```javascript
$('h1').textHighlight('Hello');
$('h1').textHighlight(/World/);
$('h1').textHighlight([/Hello/, 'World']);
$('h1').textHighlight(['Hello', 'World'], {
  element: 'mark',
  class: '',
  caseSensitive: false
});
$('h1').removeHighlight();
```

Each line will produce the corresponding markup:

```html
<h1>
  <mark>Hello</mark>
  World
</h1>
<h1>
  Hello
  <mark>World</mark>
</h1>
<h1>
  <mark>Hello</mark>
  <mark>World</mark>
</h1>
<h1>
  <mark>Hello</mark>
  <mark>World</mark>
</h1>
```

Methods
-------

```javascript
$.fn.textHighlight( term, options )
```

Encapsulates text within a specified element.

* `term` could be either a string, a regular expression or an array of both.
* `options` is an object containing configuration options. See below for more details.

```javascript
$.fn.removeHighlight();
```

Removes the encapsulation applied by `$.fn.textHighlight`.

Options
-------

|Option         | Type       | Description |
| ------------- |----------- | ----------- |
| element       | `object`   | The tag name in which the matched text should be encapsulated.|
| class         | `string`   | A specific class to be added to that element. |
| caseSensitive | `boolean`  | Whether to ignore the case of the specified keyword/regex. |
| ignore        | `selector` | A selector that specifies a subset of elements that should be ignored. |

_**Note:** The `caseSensitive` option overrides the `i` flag of Regular Expressions._

License
-------
This plugin and the associated documentation are distributed under the terms of
the MIT License. See `COPYING` for more details.

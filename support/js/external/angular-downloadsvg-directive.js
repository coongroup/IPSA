(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);var f=new Error("Cannot find module '"+o+"'");throw f.code="MODULE_NOT_FOUND",f}var l=n[o]={exports:{}};t[o][0].call(l.exports,function(e){var n=t[o][1][e];return s(n?n:e)},l,l.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){
/*
 * angular-downloadsvg-directive
 * (c) 2013-2015 J. Harshbarger
 * Licensed MIT
 */

/* jshint undef: true, unused: true */
/* global angular */
/* global require */
/* global document */

var SvgSaver = require('svgsaver');

angular.module('hc.downloader', [])
.factory('svgDownload', ['$log', '$rootScope', '$q', function ($log, $rootScope, $q) {
  var svgSaver = new SvgSaver();

  return function (svg) {
    return {
      getHtml: function () {
        return svgSaver.getHTML(svg);
      },
      getBlob: function () {
        return svgSaver.getBlob(svg);
      },
      asSvg: function (filename) {
        return $q(function (resolve, reject) {
          $log.debug('hc.downloader asSvg', filename);
          $rootScope.$emit('$svgSaver:start', filename);
          setTimeout(function () {
            try {
              resolve(svgSaver.asSvg(svg, filename));
              $rootScope.$emit('$svgSaver:end', filename);
            } catch (err) {
              $rootScope.$emit('$svgSaver:error', filename);
              reject(err);
            }
          });
        });
      },
      asPng: function (filename) {
        return $q(function (resolve, reject) {
          $log.debug('hc.downloader asSvg', filename);
          $rootScope.$emit('$svgSaver:start', filename);
          setTimeout(function () {
            try {
              resolve(svgSaver.asPng(svg, filename));
              $rootScope.$emit('$svgSaver:end', filename);
            } catch (err) {
              $rootScope.$emit('$svgSaver:error', filename);
              reject(err);
            }
          });
        });
      }
    };
  };
}])

/**
 * @ngdoc directive
 * @name hc.downloader.directive:svgDownload
 * @restrict A
 * @element button
 *
 * @description
 * AngularJS directive to download an SVG element as an SVG file.
 *
 * @param {string} svg-download The source element to download.  If blank uses the first svg in the body.
 * @param {string=} filename Basename of file to save.  If blank uses svg element title or 'untitled'
 * @param {string=} [type='svg'] Type of file to download (svg or png)
 *
 * @example
 ## A simple example
  <example module="hc.downloader">
  <file name="example.html">
    <div ng-include="'octocat.html'"></div>
      <button svg-download title="mysvg">Download as SVG</button>
      <button svg-download title="mysvg" type="png">Download as PNG</button>
    <small class="pull-right">Source: <a href="https://gist.github.com/johan/1007813">https://gist.github.com/johan/1007813</a></small>
  </file>
  <file name="octocat.html">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="-0.2 -1 379 334">
     <path id="puddle" d="m296.94 295.43c0 20.533-47.56 37.176-106.22 37.176-58.67 0-106.23-16.643-106.23-37.176s47.558-37.18 106.23-37.18c58.66 0 106.22 16.65 106.22 37.18z"/>
     <g id="shadow-legs">
    <path d="m161.85 331.22v-26.5c0-3.422-.619-6.284-1.653-8.701 6.853 5.322 7.316 18.695 7.316 18.695v17.004c6.166.481 12.534.773 19.053.861l-.172-16.92c-.944-23.13-20.769-25.961-20.769-25.961-7.245-1.645-7.137 1.991-6.409 4.34-7.108-12.122-26.158-10.556-26.158-10.556-6.611 2.357-.475 6.607-.475 6.607 10.387 3.775 11.33 15.105 11.33 15.105v23.622c5.72.98 11.71 1.79 17.94 2.4z"/>
    <path d="m245.4 283.48s-19.053-1.566-26.16 10.559c.728-2.35.839-5.989-6.408-4.343 0 0-19.824 2.832-20.768 25.961l-.174 16.946c6.509-.025 12.876-.254 19.054-.671v-17.219s.465-13.373 7.316-18.695c-1.034 2.417-1.653 5.278-1.653 8.701v26.775c6.214-.544 12.211-1.279 17.937-2.188v-24.113s.944-11.33 11.33-15.105c0-.01 6.13-4.26-.48-6.62z"/>
     </g>
     <path id="cat" d="m378.18 141.32l.28-1.389c-31.162-6.231-63.141-6.294-82.487-5.49 3.178-11.451 4.134-24.627 4.134-39.32 0-21.073-7.917-37.931-20.77-50.759 2.246-7.25 5.246-23.351-2.996-43.963 0 0-14.541-4.617-47.431 17.396-12.884-3.22-26.596-4.81-40.328-4.81-15.109 0-30.376 1.924-44.615 5.83-33.94-23.154-48.923-18.411-48.923-18.411-9.78 24.457-3.733 42.566-1.896 47.063-11.495 12.406-18.513 28.243-18.513 47.659 0 14.658 1.669 27.808 5.745 39.237-19.511-.71-50.323-.437-80.373 5.572l.276 1.389c30.231-6.046 61.237-6.256 80.629-5.522.898 2.366 1.899 4.661 3.021 6.879-19.177.618-51.922 3.062-83.303 11.915l.387 1.36c31.629-8.918 64.658-11.301 83.649-11.882 11.458 21.358 34.048 35.152 74.236 39.484-5.704 3.833-11.523 10.349-13.881 21.374-7.773 3.718-32.379 12.793-47.142-12.599 0 0-8.264-15.109-24.082-16.292 0 0-15.344-.235-1.059 9.562 0 0 10.267 4.838 17.351 23.019 0 0 9.241 31.01 53.835 21.061v32.032s-.943 11.33-11.33 15.105c0 0-6.137 4.249.475 6.606 0 0 28.792 2.361 28.792-21.238v-34.929s-1.142-13.852 5.663-18.667v57.371s-.47 13.688-7.551 18.881c0 0-4.723 8.494 5.663 6.137 0 0 19.824-2.832 20.769-25.961l.449-58.06h4.765l.453 58.06c.943 23.129 20.768 25.961 20.768 25.961 10.383 2.357 5.663-6.137 5.663-6.137-7.08-5.193-7.551-18.881-7.551-18.881v-56.876c6.801 5.296 5.663 18.171 5.663 18.171v34.929c0 23.6 28.793 21.238 28.793 21.238 6.606-2.357.474-6.606.474-6.606-10.386-3.775-11.33-15.105-11.33-15.105v-45.786c0-17.854-7.518-27.309-14.87-32.3 42.859-4.25 63.426-18.089 72.903-39.591 18.773.516 52.557 2.803 84.873 11.919l.384-1.36c-32.131-9.063-65.692-11.408-84.655-11.96.898-2.172 1.682-4.431 2.378-6.755 19.25-.80 51.38-.79 82.66 5.46z"/>
     <path id="face" d="m258.19 94.132c9.231 8.363 14.631 18.462 14.631 29.343 0 50.804-37.872 52.181-84.585 52.181-46.721 0-84.589-7.035-84.589-52.181 0-10.809 5.324-20.845 14.441-29.174 15.208-13.881 40.946-6.531 70.147-6.531 29.07-.004 54.72-7.429 69.95 6.357z"/>
     <path id="eyes" d="m160.1 126.06 c0 13.994-7.88 25.336-17.6 25.336-9.72 0-17.6-11.342-17.6-25.336 0-13.992 7.88-25.33 17.6-25.33 9.72.01 17.6 11.34 17.6 25.33z m94.43 0 c0 13.994-7.88 25.336-17.6 25.336-9.72 0-17.6-11.342-17.6-25.336 0-13.992 7.88-25.33 17.6-25.33 9.72.01 17.6 11.34 17.6 25.33z"/>
     <g id="face-features">
     <path id="pupils" d="m154.46 126.38 c0 9.328-5.26 16.887-11.734 16.887s-11.733-7.559-11.733-16.887c0-9.331 5.255-16.894 11.733-16.894 6.47 0 11.73 7.56 11.73 16.89z m94.42 0 c0 9.328-5.26 16.887-11.734 16.887s-11.733-7.559-11.733-16.887c0-9.331 5.255-16.894 11.733-16.894 6.47 0 11.73 7.56 11.73 16.89z"/>
     <circle id="nose" cx="188.5" cy="148.56" r="4.401"/>
     <path id="mouth" d="m178.23 159.69c-.26-.738.128-1.545.861-1.805.737-.26 1.546.128 1.805.861 1.134 3.198 4.167 5.346 7.551 5.346s6.417-2.147 7.551-5.346c.26-.738 1.067-1.121 1.805-.861s1.121 1.067.862 1.805c-1.529 4.324-5.639 7.229-10.218 7.229s-8.68-2.89-10.21-7.22z"/>
     </g>
     <path id="octo" d="m80.641 179.82 c0 1.174-1.376 2.122-3.07 2.122-1.693 0-3.07-.948-3.07-2.122 0-1.175 1.377-2.127 3.07-2.127 1.694 0 3.07.95 3.07 2.13z m8.5 4.72 c0 1.174-1.376 2.122-3.07 2.122-1.693 0-3.07-.948-3.07-2.122 0-1.175 1.377-2.127 3.07-2.127 1.694 0 3.07.95 3.07 2.13z m5.193 6.14 c0 1.174-1.376 2.122-3.07 2.122-1.693 0-3.07-.948-3.07-2.122 0-1.175 1.377-2.127 3.07-2.127 1.694 0 3.07.95 3.07 2.13z m4.72 7.08 c0 1.174-1.376 2.122-3.07 2.122-1.693 0-3.07-.948-3.07-2.122 0-1.175 1.377-2.127 3.07-2.127 1.694 0 3.07.95 3.07 2.13z m5.188 6.61 c0 1.174-1.376 2.122-3.07 2.122-1.693 0-3.07-.948-3.07-2.122 0-1.175 1.377-2.127 3.07-2.127 1.694 0 3.07.95 3.07 2.13z m7.09 5.66 c0 1.174-1.376 2.122-3.07 2.122-1.693 0-3.07-.948-3.07-2.122 0-1.175 1.377-2.127 3.07-2.127 1.694 0 3.07.95 3.07 2.13z m9.91 3.78 c0 1.174-1.376 2.122-3.07 2.122-1.693 0-3.07-.948-3.07-2.122 0-1.175 1.377-2.127 3.07-2.127 1.694 0 3.07.95 3.07 2.13z m9.87 0 c0 1.174-1.376 2.122-3.07 2.122-1.693 0-3.07-.948-3.07-2.122 0-1.175 1.377-2.127 3.07-2.127 1.694 0 3.07.95 3.07 2.13z m10.01 -1.64 c0 1.174-1.376 2.122-3.07 2.122-1.693 0-3.07-.948-3.07-2.122 0-1.175 1.377-2.127 3.07-2.127 1.694 0 3.07.95 3.07 2.13z"/>
     <path id="drop" d="m69.369 186.12l-3.066 10.683s-.8 3.861 2.84 4.546c3.8-.074 3.486-3.627 3.223-4.781z"/>
    </svg>
  </file>
  <file name="octocat.css">
    #puddle { fill: #9CDAF1; }
    #shadow-legs { fill: #7DBBE6; }
    #face { fill: #F4CBB2; }
    #eyes { fill: #FFF; }
    #face-features { fill: #AD5C51; }
    #octo { fill: #C3E4D8; }
    #drop { fill: #9CDAF1; }}
  </file>
  </example>
 */
.directive('svgDownload', ['svgDownload', function (svgDownload) {
  return {
    restrict: 'A',
    link: function (scope, element, attrs) {
      element.on('click', function download () {
        var ext = attrs.type || 'svg';
        var filename = attrs.filename || attrs.title;
        if (filename && filename.indexOf('.' + ext) < 0) {
          filename += '.' + ext;
        }
        var svg = svgDownload(attrs.svgDownload);
        if (svg) {
          if (ext === 'svg') {
            svg.asSvg(filename);
          } else if (ext === 'png') {
            svg.asPng(filename);
          }
        }
      });
    }
  };
}]);

},{"svgsaver":2}],2:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, '__esModule', {
  value: true
});

var _createClass = (function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ('value' in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; })();

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError('Cannot call a class as a function'); } }

var svgStyles = { // Whitelist of CSS styles and default values
  'alignment-baseline': 'auto',
  'baseline-shift': 'baseline',
  'clip': 'auto',
  'clip-path': 'none',
  'clip-rule': 'nonzero',
  'color': 'rgb(51, 51, 51)',
  'color-interpolation': 'srgb',
  'color-interpolation-filters': 'linearrgb',
  'color-profile': 'auto',
  'color-rendering': 'auto',
  'cursor': 'auto',
  'direction': 'ltr',
  'display': 'inline',
  'dominant-baseline': 'auto',
  'enable-background': '',
  'fill': 'rgb(0, 0, 0)',
  'fill-opacity': '1',
  'fill-rule': 'nonzero',
  'filter': 'none',
  'flood-color': 'rgb(0, 0, 0)',
  'flood-opacity': '1',
  'font': '',
  'font-family': 'normal',
  'font-size': 'medium',
  'font-size-adjust': 'auto',
  'font-stretch': 'normal',
  'font-style': 'normal',
  'font-variant': 'normal',
  'font-weight': '400',
  'glyph-orientation-horizontal': '0deg',
  'glyph-orientation-vertical': 'auto',
  'image-rendering': 'auto',
  'kerning': 'auto',
  'letter-spacing': '0',
  'lighting-color': 'rgb(255, 255, 255)',
  'marker': '',
  'marker-end': 'none',
  'marker-mid': 'none',
  'marker-start': 'none',
  'mask': 'none',
  'opacity': '1',
  'overflow': 'visible',
  'paint-order': 'fill',
  'pointer-events': 'auto',
  'shape-rendering': 'auto',
  'stop-color': 'rgb(0, 0, 0)',
  'stop-opacity': '1',
  'stroke': 'none',
  'stroke-dasharray': 'none',
  'stroke-dashoffset': '0',
  'stroke-linecap': 'butt',
  'stroke-linejoin': 'miter',
  'stroke-miterlimit': '4',
  'stroke-opacity': '1',
  'stroke-width': '1',
  'text-anchor': 'start',
  'text-decoration': 'none',
  'text-rendering': 'auto',
  'unicode-bidi': 'normal',
  'visibility': 'visible',
  'word-spacing': '0px',
  'writing-mode': 'lr-tb'
};

var svgAttrs = [// white list of attributes
'id', 'xml: base', 'xml: lang', 'xml: space', // Core
'height', 'result', 'width', 'x', 'y', // Primitive
'xlink: href', // Xlink attribute
'style', 'class', 'd', 'pathLength', // Path
'x', 'y', 'dx', 'dy', 'glyphRef', 'format', 'x1', 'y1', 'x2', 'y2', 'rotate', 'textLength', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'width', 'height', 'refX', 'refY', 'orient', 'markerUnits', 'markerWidth', 'markerHeight', 'maskUnits', 'transform', 'viewBox', 'version', // Container
'preserveAspectRatio', 'xmlns', 'points', // Polygons
'offset'];

// http://www.w3.org/TR/SVG/propidx.html
// via https://github.com/svg/svgo/blob/master/plugins/_collections.js
var inheritableAttrs = ['clip-rule', 'color', 'color-interpolation', 'color-interpolation-filters', 'color-profile', 'color-rendering', 'cursor', 'direction', 'fill', 'fill-opacity', 'fill-rule', 'font', 'font-family', 'font-size', 'font-size-adjust', 'font-stretch', 'font-style', 'font-variant', 'font-weight', 'glyph-orientation-horizontal', 'glyph-orientation-vertical', 'image-rendering', 'kerning', 'letter-spacing', 'marker', 'marker-end', 'marker-mid', 'marker-start', 'pointer-events', 'shape-rendering', 'stroke', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'text-anchor', 'text-rendering', 'transform', 'visibility', 'white-space', 'word-spacing', 'writing-mode'];

/* Some simple utilities */

var isFunction = function isFunction(a) {
  return typeof a === 'function';
};
var isDefined = function isDefined(a) {
  return typeof a !== 'undefined';
};
var isUndefined = function isUndefined(a) {
  return typeof a === 'undefined';
};
var isObject = function isObject(a) {
  return a !== null && typeof a === 'object';
};

// from https://github.com/npm-dom/is-dom/blob/master/index.js
function isNode(val) {
  if (!isObject(val)) {
    return false;
  }
  if (isDefined(window) && isObject(window.Node)) {
    return val instanceof window.Node;
  }
  return typeof val.nodeType === 'number' && typeof val.nodeName === 'string';
}

// detection
var DownloadAttributeSupport = typeof document !== 'undefined' && 'download' in document.createElement('a');

function saveUri(uri, name) {
  if (DownloadAttributeSupport) {
    var dl = document.createElement('a');
    dl.setAttribute('href', uri);
    dl.setAttribute('download', name);
    // firefox doesn't support `.click()`...
    // from https://github.com/sindresorhus/multi-download/blob/gh-pages/index.js
    dl.dispatchEvent(new MouseEvent('click'));
    return true;
  } else if (typeof window !== 'undefined') {
    window.open(uri, '_blank', '');
    return true;
  }

  return false;
}

function savePng(uri, name) {
  var canvas = document.createElement('canvas');
  var context = canvas.getContext('2d');

  var image = new Image();
  image.onload = function () {
    canvas.width = image.width;
    canvas.height = image.height;
    context.drawImage(image, 0, 0);

    if (isDefined(window.saveAs) && isDefined(canvas.toBlob)) {
      canvas.toBlob(function (blob) {
        saveAs(blob, name);
      });
    } else {
      saveUri(canvas.toDataURL('image/png'), name);
    }
  };
  image.src = uri;
  return true;
}

var _isDefined = function _isDefined(a) {
  return typeof a !== 'undefined';
};
var _isUndefined = function _isUndefined(a) {
  return typeof a === 'undefined';
};
var _isObject = function _isObject(a) {
  return a !== null && typeof a === 'object';
};

// from https://github.com/npm-dom/is-dom/blob/master/index.js
function _isNode(val) {
  if (!_isObject(val)) return false;
  if (_isDefined(window) && _isObject(window.Node)) return val instanceof window.Node;
  return 'number' == typeof val.nodeType && 'string' == typeof val.nodeName;
}

var useComputedStyles = _isDefined(window) && _isDefined(window.getComputedStyle);

// Gets computed styles for an element
// from https://github.com/jquery/jquery/blob/master/src/css/var/getStyles.js
function getComputedStyles(node) {
  if (useComputedStyles) {
    var view = node.ownerDocument.defaultView;
    if (!view.opener) view = window;
    return view.getComputedStyle(node, null);
  } else {
    return node.currentStyle || node.style;
  }
}

/**
* Returns a collection of CSS property-value pairs
* @param  {Element} node A DOM element to copy styles from
* @param  {Object} [target] An optional object to copy styles to
* @param {(Object|Boolean)} [default=true] A collection of CSS property-value pairs, false: copy none, true: copy all
* @return {object} collection of CSS property-value pairs
* @api public
*/
function computedStyles(node) {
  var target = arguments.length <= 1 || arguments[1] === undefined ? {} : arguments[1];
  var styleList = arguments.length <= 2 || arguments[2] === undefined ? true : arguments[2];

  if (!_isNode(node)) {
    throw new Error('parameter 1 is not of type \'Element\'');
  }

  if (styleList === false) return target;

  var computed = getComputedStyles(node);

  if (styleList === true) {
    var keysArray = useComputedStyles ? computed : Object.keys(computed);
  } else {
    var keysArray = Object.keys(styleList);
  }

  for (var i = 0, l = keysArray.length; i < l; i++) {
    var key = keysArray[i];

    var def = styleList === true || styleList[key];
    if (def === false || _isUndefined(def)) continue; // copy never

    var value = /* computed.getPropertyValue(key) || */computed[key]; // using getPropertyValue causes error in IE11
    if (typeof value !== 'string' || value === '') continue; // invalid value

    if (def === true || value !== def) {
      // styleList === true || styleList[key] === true || styleList[key] !== value
      target[key] = value;
    }
  }

  return target;
}

// Removes attributes that are not valid for SVGs
function cleanAttrs(el, attrs, styles) {
  // attrs === false - remove all, attrs === true - allow all
  if (attrs === true) {
    return;
  }

  Array.prototype.slice.call(el.attributes).forEach(function (attr) {
    // remove if it is not style nor on attrs  whitelist
    // keeping attributes that are also styles because attributes override
    if (attr.specified) {
      if (attrs === '' || attrs === false || isUndefined(styles[attr.name]) && attrs.indexOf(attr.name) < 0) {
        el.removeAttribute(attr.name);
      }
    }
  });
}

function cleanStyle(tgt, parentStyles) {
  parentStyles = parentStyles || tgt.parentNode.style;
  inheritableAttrs.forEach(function (key) {
    if (tgt.style[key] === parentStyles[key]) {
      tgt.style.removeProperty(key);
    }
  });
}

function domWalk(src, tgt, down, up) {
  down(src, tgt);
  var children = src.childNodes;
  for (var i = 0; i < children.length; i++) {
    domWalk(children[i], tgt.childNodes[i], down, up);
  }
  up(src, tgt);
}

// Clones an SVGElement, copies approprate atttributes and styles.
function cloneSvg(src, attrs, styles) {
  var clonedSvg = src.cloneNode(true);

  domWalk(src, clonedSvg, function (src, tgt) {
    if (tgt.style) {
      computedStyles(src, tgt.style, styles);
    }
  }, function (src, tgt) {
    if (tgt.style && tgt.parentNode) {
      cleanStyle(tgt);
    }
    if (tgt.attributes) {
      cleanAttrs(tgt, attrs, styles);
    }
  });

  return clonedSvg;
}

// inheritable styles may be overridden by parent, always copy for now
inheritableAttrs.forEach(function (k) {
  if (k in svgStyles) {
    svgStyles[k] = true;
  }
});

function getSvg(el) {
  if (isUndefined(el) || el === '') {
    el = document.body.querySelector('svg');
  } else if (typeof el === 'string') {
    el = document.body.querySelector(el);
  }
  if (el && el.tagName !== 'svg') {
    el = el.querySelector('svg');
  }
  if (!isNode(el)) {
    throw new Error('svgsaver: Can\'t find an svg element');
  }
  return el;
}

function getFilename(el, filename, ext) {
  if (!filename || filename === '') {
    filename = (el.getAttribute('title') || 'untitled') + '.' + ext;
  }
  return encodeURI(filename);
}

var SvgSaver = (function () {

  /**
  * SvgSaver constructor.
  * @constructs SvgSaver
  * @api public
  *
  * @example
  * var svgsaver = new SvgSaver();                      // creates a new instance
  * var svg = document.querySelector('#mysvg');         // find the SVG element
  * svgsaver.asSvg(svg);                                // save as SVG
  */

  function SvgSaver() {
    var _ref = arguments.length <= 0 || arguments[0] === undefined ? {} : arguments[0];

    var attrs = _ref.attrs;
    var styles = _ref.styles;

    _classCallCheck(this, SvgSaver);

    this.attrs = attrs === undefined ? svgAttrs : attrs;
    this.styles = styles === undefined ? svgStyles : styles;
  }

  /**
  * Return the SVG HTML text after cleaning
  *
  * @param {SVGElement} el The element to copy.
  * @returns {String} SVG text after cleaning
  * @api public
  */

  _createClass(SvgSaver, [{
    key: 'getHTML',
    value: function getHTML(el) {
      el = getSvg(el);
      var svg = cloneSvg(el, this.attrs, this.styles);

      svg.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
      svg.setAttribute('version', 1.1);

      // height and width needed to download in FireFox
      svg.setAttribute('width', svg.getAttribute('width') || '500');
      svg.setAttribute('height', svg.getAttribute('height') || '900');

      return svg.outerHTML || new window.XMLSerializer().serializeToString(svg);
    }

    /**
    * Return the SVG, after cleaning, as a text/xml Blob
    *
    * @param {SVGElement} el The element to copy.
    * @returns {Blog} SVG as a text/xml Blob
    * @api public
    */
  }, {
    key: 'getBlob',
    value: function getBlob(el) {
      var html = this.getHTML(el);
      return new Blob([html], { type: 'text/xml' });
    }

    /**
    * Return the SVG, after cleaning, as a image/svg+xml;base64 URI encoded string
    *
    * @param {SVGElement} el The element to copy.
    * @returns {String} SVG as image/svg+xml;base64 URI encoded string
    * @api public
    */
  }, {
    key: 'getUri',
    value: function getUri(el) {
      var html = encodeURIComponent(this.getHTML(el));
      if (isDefined(window.btoa)) {
        // see http://stackoverflow.com/questions/23223718/failed-to-execute-btoa-on-window-the-string-to-be-encoded-contains-characte
        return 'data:image/svg+xml;base64,' + window.btoa(unescape(html));
      }
      return 'data:image/svg+xml,' + html;
    }

    /**
    * Saves the SVG as a SVG file using method compatible with the browser
    *
    * @param {SVGElement} el The element to copy.
    * @param {string} [filename] The filename to save, defaults to the SVG title or 'untitled.svg'
    * @returns {SvgSaver} The SvgSaver instance
    * @api public
    */
  }, {
    key: 'asSvg',
    value: function asSvg(el, filename) {
      el = getSvg(el);
      filename = getFilename(el, filename, 'svg');
      if (isDefined(window.saveAs) && isFunction(Blob)) {
        return saveAs(this.getBlob(el), filename);
      } else {
        return saveUri(this.getUri(el), filename);
      }
    }

    /**
    * Saves the SVG as a PNG file using method compatible with the browser
    *
    * @param {SVGElement} el The element to copy.
    * @param {string} [filename] The filename to save, defaults to the SVG title or 'untitled.png'
    * @returns {SvgSaver} The SvgSaver instance
    * @api public
    */
  }, {
    key: 'asPng',
    value: function asPng(el, filename) {
      el = getSvg(el);
      filename = getFilename(el, filename, 'png');
      return savePng(this.getUri(el), filename);
    }
  }]);

  return SvgSaver;
})();

exports['default'] = SvgSaver;
module.exports = exports['default'];

},{}]},{},[1]);
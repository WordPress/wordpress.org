(()=>{var t={432:(t,e,r)=>{var n=r(36).Symbol;t.exports=n},77:(t,e,r)=>{var n=r(432),o=r(444),i=r(371),u=n?n.toStringTag:void 0;t.exports=function(t){return null==t?void 0===t?"[object Undefined]":"[object Null]":u&&u in Object(t)?o(t):i(t)}},625:(t,e,r)=>{var n=r(531),o=/^\s+/;t.exports=function(t){return t?t.slice(0,n(t)+1).replace(o,""):t}},565:(t,e,r)=>{var n="object"==typeof r.g&&r.g&&r.g.Object===Object&&r.g;t.exports=n},444:(t,e,r)=>{var n=r(432),o=Object.prototype,i=o.hasOwnProperty,u=o.toString,a=n?n.toStringTag:void 0;t.exports=function(t){var e=i.call(t,a),r=t[a];try{t[a]=void 0;var n=!0}catch(t){}var o=u.call(t);return n&&(e?t[a]=r:delete t[a]),o}},371:t=>{var e=Object.prototype.toString;t.exports=function(t){return e.call(t)}},36:(t,e,r)=>{var n=r(565),o="object"==typeof self&&self&&self.Object===Object&&self,i=n||o||Function("return this")();t.exports=i},531:t=>{var e=/\s/;t.exports=function(t){for(var r=t.length;r--&&e.test(t.charAt(r)););return r}},738:(t,e,r)=>{var n=r(130),o=r(593),i=r(909),u=Math.max,a=Math.min;t.exports=function(t,e,r){var c,f,s,l,p,v,d=0,b=!1,y=!1,x=!0;if("function"!=typeof t)throw new TypeError("Expected a function");function g(e){var r=c,n=f;return c=f=void 0,d=e,l=t.apply(n,r)}function m(t){var r=t-v;return void 0===v||r>=e||r<0||y&&t-d>=s}function h(){var t=o();if(m(t))return j(t);p=setTimeout(h,function(t){var r=e-(t-v);return y?a(r,s-(t-d)):r}(t))}function j(t){return p=void 0,x&&c?g(t):(c=f=void 0,l)}function w(){var t=o(),r=m(t);if(c=arguments,f=this,v=t,r){if(void 0===p)return function(t){return d=t,p=setTimeout(h,e),b?g(t):l}(v);if(y)return clearTimeout(p),p=setTimeout(h,e),g(v)}return void 0===p&&(p=setTimeout(h,e)),l}return e=i(e)||0,n(r)&&(b=!!r.leading,s=(y="maxWait"in r)?u(i(r.maxWait)||0,e):s,x="trailing"in r?!!r.trailing:x),w.cancel=function(){void 0!==p&&clearTimeout(p),d=0,c=v=f=p=void 0},w.flush=function(){return void 0===p?l:j(o())},w}},130:t=>{t.exports=function(t){var e=typeof t;return null!=t&&("object"==e||"function"==e)}},189:t=>{t.exports=function(t){return null!=t&&"object"==typeof t}},733:(t,e,r)=>{var n=r(77),o=r(189);t.exports=function(t){return"symbol"==typeof t||o(t)&&"[object Symbol]"==n(t)}},593:(t,e,r)=>{var n=r(36);t.exports=function(){return n.Date.now()}},909:(t,e,r)=>{var n=r(625),o=r(130),i=r(733),u=/^[-+]0x[0-9a-f]+$/i,a=/^0b[01]+$/i,c=/^0o[0-7]+$/i,f=parseInt;t.exports=function(t){if("number"==typeof t)return t;if(i(t))return NaN;if(o(t)){var e="function"==typeof t.valueOf?t.valueOf():t;t=o(e)?e+"":e}if("string"!=typeof t)return 0===t?t:+t;t=n(t);var r=a.test(t);return r||c.test(t)?f(t.slice(2),r?2:8):u.test(t)?NaN:+t}}},e={};function r(n){var o=e[n];if(void 0!==o)return o.exports;var i=e[n]={exports:{}};return t[n](i,i.exports,r),i.exports}r.n=t=>{var e=t&&t.__esModule?()=>t.default:()=>t;return r.d(e,{a:e}),e},r.d=(t,e)=>{for(var n in e)r.o(e,n)&&!r.o(t,n)&&Object.defineProperty(t,n,{enumerable:!0,get:e[n]})},r.g=function(){if("object"==typeof globalThis)return globalThis;try{return this||new Function("return this")()}catch(t){if("object"==typeof window)return window}}(),r.o=(t,e)=>Object.prototype.hasOwnProperty.call(t,e),(()=>{"use strict";var t=r(738),e=r.n(t);window.addEventListener("DOMContentLoaded",(()=>{const t=document.querySelector("#wpadminbar");function r(){if(!t)return;const{top:e,height:r}=t.getBoundingClientRect(),n=Math.max(0,r+e);document.documentElement.style.setProperty("--sensei-wpadminbar-offset",n+"px")}t&&(r(),window.addEventListener("scroll",r,{capture:!1,passive:!0}),window.addEventListener("resize",e()(r,500)))}))})()})();